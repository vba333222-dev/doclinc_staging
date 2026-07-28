<?php

require_once __DIR__ . '/RealtimeTransport.php';

if (!defined('DOCLINC_CARE_OPERATIONS_DEFINITIONS_ONLY')) {
	define('DOCLINC_CARE_OPERATIONS_DEFINITIONS_ONLY', true);
}
require_once dirname(__DIR__, 2) . '/application/migrations/20260728000100_care_operations_foundation.php';

final class RealtimeOutboxDispatcher
{
	private const LOCK_NAME = 'doclinc_realtime_outbox_dispatcher';
	private const PAYLOAD_KEYS = array('aggregate_id', 'audience', 'event_id', 'event_type', 'invalidation', 'version');

	private $db;
	private $transport;
	private $lock_acquired = false;

	public function __construct(mysqli $db, RealtimeTransport $transport)
	{
		$this->db = $db;
		$this->transport = $transport;
	}

	public static function assertIdentityAndGrants(mysqli $db, $expected_database, $configured_user, array $allowed_users)
	{
		$identity = $db->query('SELECT DATABASE() AS database_name, CURRENT_USER() AS account')->fetch_assoc();
		$account = $identity ? (string) $identity['account'] : '';
		$separator = strpos($account, '@');
		$actual_user = $separator === false ? '' : substr($account, 0, $separator);
		if (!$identity || !hash_equals($expected_database, (string) $identity['database_name'])
			|| !hash_equals($configured_user, $actual_user)
			|| !in_array($actual_user, $allowed_users, true)) {
			throw new RuntimeException('dispatcher_identity_not_allowed');
		}

		$table_privileges = array();
		$usage_seen = false;
		foreach ($db->query('SHOW GRANTS FOR CURRENT_USER()')->fetch_all(MYSQLI_NUM) as $row) {
			$grant = (string) $row[0];
			if (preg_match('/\AGRANT USAGE ON \*\.\* TO /i', $grant) === 1) {
				if ($usage_seen) {
					throw new RuntimeException('dispatcher_grants_excessive');
				}
				$usage_seen = true;
				continue;
			}
			if (preg_match('/\AGRANT ([A-Z, ]+) ON `?([^` .]+)`?\.`?([^` ]+)`? TO /i', $grant, $match) !== 1
				|| !hash_equals($expected_database, $match[2])
				|| !hash_equals('realtime_outbox', $match[3])
				|| !empty($table_privileges)) {
				throw new RuntimeException('dispatcher_grants_excessive');
			}
			$table_privileges = array_map('trim', explode(',', strtoupper($match[1])));
			sort($table_privileges);
			if (array_diff($table_privileges, array('SELECT', 'UPDATE')) !== array()) {
				throw new RuntimeException('dispatcher_grants_excessive');
			}
		}
		if (!$usage_seen || $table_privileges !== array('SELECT', 'UPDATE')) {
			throw new RuntimeException('dispatcher_grants_incomplete');
		}
	}

	public function assertSchema()
	{
		$definitions = care_operations_definitions();
		if (!isset($definitions['realtime_outbox'])) {
			throw new RuntimeException('outbox_schema_contract_missing');
		}
		care_operations_assert_target($this->db, 'realtime_outbox', $definitions['realtime_outbox']);
	}

	public function run($batch_size = 20, $lease_seconds = 120, $maximum_attempts = 5)
	{
		$batch_size = self::boundedInteger($batch_size, 1, 100, 'batch_size_invalid');
		$lease_seconds = self::boundedInteger($lease_seconds, 30, 900, 'lease_seconds_invalid');
		$maximum_attempts = self::boundedInteger($maximum_attempts, 1, 20, 'maximum_attempts_invalid');
		$summary = array(
			'stale_recovered' => 0,
			'claimed' => 0,
			'published' => 0,
			'retry_scheduled' => 0,
			'failed' => 0,
		);

		$this->acquireLock();
		try {
			$this->assertSchema();
			list($events, $stale_recovered) = $this->claim($batch_size, $lease_seconds);
			$summary['stale_recovered'] = $stale_recovered;
			$summary['claimed'] = count($events);
			foreach ($events as $event) {
				$message = $this->transportMessage($event);
				if ($message === null) {
					$this->markFailed($event, 'stored_payload_invalid');
					$summary['failed']++;
					continue;
				}
				try {
					$result = $this->transport->publish($message);
				} catch (Throwable $exception) {
					$result = RealtimeTransportResult::retryable('gateway_request_failed');
				}
				if (!($result instanceof RealtimeTransportResult)) {
					$result = RealtimeTransportResult::retryable('gateway_result_invalid');
				}
				if ($result->successful) {
					$this->markPublished($event);
					$summary['published']++;
					continue;
				}
				if (!$result->retryable || (int) $event['attempt_count'] >= $maximum_attempts) {
					$this->markFailed($event, $result->safe_error_code);
					$summary['failed']++;
					continue;
				}
				$this->scheduleRetry($event, $result->safe_error_code);
				$summary['retry_scheduled']++;
			}
		} finally {
			$this->releaseLock();
		}
		return $summary;
	}

	private function acquireLock()
	{
		$stmt = $this->db->prepare('SELECT GET_LOCK(?, 0) AS acquired');
		$name = self::LOCK_NAME;
		$stmt->bind_param('s', $name);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if (!$row || (int) $row['acquired'] !== 1) {
			throw new RuntimeException('dispatcher_lock_unavailable');
		}
		$this->lock_acquired = true;
	}

	private function releaseLock()
	{
		if (!$this->lock_acquired) {
			return;
		}
		$stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
		$name = self::LOCK_NAME;
		$stmt->bind_param('s', $name);
		$stmt->execute();
		$stmt->close();
		$this->lock_acquired = false;
	}

	private function claim($batch_size, $lease_seconds)
	{
		$this->db->begin_transaction();
		try {
			$stale = $this->db->prepare("SELECT outbox_id FROM realtime_outbox
				WHERE state='claimed' AND claimed_at < DATE_SUB(NOW(6), INTERVAL ? SECOND)
				ORDER BY claimed_at,outbox_id LIMIT ? FOR UPDATE SKIP LOCKED");
			$stale->bind_param('ii', $lease_seconds, $batch_size);
			$stale->execute();
			$stale_ids = array_map('intval', array_column($stale->get_result()->fetch_all(MYSQLI_ASSOC), 'outbox_id'));
			$stale->close();
			$recover = $this->db->prepare("UPDATE realtime_outbox SET state='pending',claimed_at=NULL,
				available_at=NOW(6),last_error_code='stale_lease_recovered' WHERE outbox_id=? AND state='claimed'");
			$stale_recovered = 0;
			foreach ($stale_ids as $outbox_id) {
				$recover->bind_param('i', $outbox_id);
				$recover->execute();
				$stale_recovered += $recover->affected_rows;
			}
			$recover->close();

			$select = $this->db->prepare("SELECT outbox_id,event_type,aggregate_type,aggregate_id,audience_type,
				audience_key,payload_json,event_version,idempotency_key,attempt_count
				FROM realtime_outbox WHERE state='pending' AND available_at<=NOW(6)
				ORDER BY available_at,outbox_id LIMIT ? FOR UPDATE SKIP LOCKED");
			$select->bind_param('i', $batch_size);
			$select->execute();
			$events = $select->get_result()->fetch_all(MYSQLI_ASSOC);
			$select->close();
			$claim_time = $this->db->query("SELECT DATE_FORMAT(NOW(6),'%Y-%m-%d %H:%i:%s.%f') AS claim_time")->fetch_assoc()['claim_time'];
			$claim = $this->db->prepare("UPDATE realtime_outbox SET state='claimed',claimed_at=?,
				attempt_count=attempt_count+1,last_error_code=NULL WHERE outbox_id=? AND state='pending'");
			$claimed = array();
			foreach ($events as $event) {
				$outbox_id = (int) $event['outbox_id'];
				$claim->bind_param('si', $claim_time, $outbox_id);
				$claim->execute();
				if ($claim->affected_rows === 1) {
					$event['claimed_at'] = $claim_time;
					$event['attempt_count'] = (int) $event['attempt_count'] + 1;
					$claimed[] = $event;
				}
			}
			$claim->close();
			$this->db->commit();
			return array($claimed, $stale_recovered);
		} catch (Throwable $exception) {
			$this->db->rollback();
			throw $exception;
		}
	}

	private function transportMessage(array $event)
	{
		try {
			$payload = json_decode((string) $event['payload_json'], true, 16, JSON_THROW_ON_ERROR);
		} catch (Throwable $exception) {
			return null;
		}
		if (!is_array($payload)) {
			return null;
		}
		$keys = array_keys($payload);
		sort($keys);
		$expected = self::PAYLOAD_KEYS;
		sort($expected);
		if ($keys !== $expected
			|| !isset($payload['event_type'], $payload['aggregate_id'], $payload['audience'], $payload['version'])
			|| !hash_equals((string) $event['event_type'], (string) $payload['event_type'])
			|| !hash_equals((string) $event['aggregate_id'], (string) $payload['aggregate_id'])
			|| !hash_equals((string) $event['audience_key'], (string) $payload['audience'])
			|| (int) $event['event_version'] !== (int) $payload['version']) {
			return null;
		}
		return array(
			'audience_type' => (string) $event['audience_type'],
			'audience_key' => (string) $event['audience_key'],
			'payload' => $payload,
			'idempotency_key' => (string) $event['idempotency_key'],
		);
	}

	private function markPublished(array $event)
	{
		$stmt = $this->db->prepare("UPDATE realtime_outbox SET state='published',published_at=NOW(6),
			claimed_at=NULL,last_error_code=NULL WHERE outbox_id=? AND state='claimed' AND claimed_at=?");
		$outbox_id = (int) $event['outbox_id'];
		$stmt->bind_param('is', $outbox_id, $event['claimed_at']);
		$stmt->execute();
		$changed = $stmt->affected_rows;
		$stmt->close();
		if ($changed !== 1) {
			throw new RuntimeException('dispatcher_lease_lost');
		}
	}

	private function markFailed(array $event, $safe_error_code)
	{
		$safe_error_code = self::safeErrorCode($safe_error_code);
		$stmt = $this->db->prepare("UPDATE realtime_outbox SET state='failed',claimed_at=NULL,
			last_error_code=? WHERE outbox_id=? AND state='claimed' AND claimed_at=?");
		$outbox_id = (int) $event['outbox_id'];
		$stmt->bind_param('sis', $safe_error_code, $outbox_id, $event['claimed_at']);
		$stmt->execute();
		$changed = $stmt->affected_rows;
		$stmt->close();
		if ($changed !== 1) {
			throw new RuntimeException('dispatcher_lease_lost');
		}
	}

	private function scheduleRetry(array $event, $safe_error_code)
	{
		$safe_error_code = self::safeErrorCode($safe_error_code);
		$delay_seconds = min(300, 5 * (2 ** min(6, max(0, (int) $event['attempt_count'] - 1))));
		$stmt = $this->db->prepare("UPDATE realtime_outbox SET state='pending',claimed_at=NULL,
			available_at=TIMESTAMPADD(SECOND, ?, NOW(6)),last_error_code=?
			WHERE outbox_id=? AND state='claimed' AND claimed_at=?");
		$outbox_id = (int) $event['outbox_id'];
		$stmt->bind_param('isis', $delay_seconds, $safe_error_code, $outbox_id, $event['claimed_at']);
		$stmt->execute();
		$changed = $stmt->affected_rows;
		$stmt->close();
		if ($changed !== 1) {
			throw new RuntimeException('dispatcher_lease_lost');
		}
	}

	private static function safeErrorCode($value)
	{
		return is_string($value) && preg_match('/\A[a-z0-9_]{1,64}\z/', $value) === 1
			? $value
			: 'transport_error';
	}

	private static function boundedInteger($value, $minimum, $maximum, $safe_code)
	{
		$value = filter_var($value, FILTER_VALIDATE_INT);
		if ($value === false || $value < $minimum || $value > $maximum) {
			throw new InvalidArgumentException($safe_code);
		}
		return (int) $value;
	}
}
