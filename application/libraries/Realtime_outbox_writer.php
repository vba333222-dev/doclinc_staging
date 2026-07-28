<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Realtime_outbox_contract.php';

class Realtime_outbox_writer
{
	private $contract;

	public function __construct(?Realtime_outbox_contract $contract = null)
	{
		$this->contract = $contract ?: new Realtime_outbox_contract();
	}

	public function enqueue($db, array $event, array $known_puskesmas_codes, array $feature_state = array())
	{
		if (!isset($feature_state['enabled']) || $feature_state['enabled'] !== true) {
			return $this->result(false, false, null, 'feature_disabled');
		}
		if (!is_object($db) || !method_exists($db, 'insert') || !method_exists($db, 'error')) {
			return $this->result(false, false, null, 'database_connection_invalid');
		}
		try {
			$prepared = $this->contract->prepare($event, $known_puskesmas_codes);
		} catch (Realtime_outbox_exception $exception) {
			return $this->result(false, false, null, $exception->getSafeCode());
		}

		$previous_debug = property_exists($db, 'db_debug') ? $db->db_debug : null;
		if (property_exists($db, 'db_debug')) {
			$db->db_debug = false;
		}
		try {
			$inserted = $db->insert('realtime_outbox', array(
				'event_type' => $prepared['event_type'],
				'aggregate_type' => $prepared['aggregate_type'],
				'aggregate_id' => $prepared['aggregate_id'],
				'audience_type' => $prepared['audience_type'],
				'audience_key' => $prepared['audience_key'],
				'payload_json' => $prepared['payload_json'],
				'event_version' => $prepared['event_version'],
				'idempotency_key' => $prepared['idempotency_key'],
			));
			$error = $db->error();
			$insert_id = $inserted && method_exists($db, 'insert_id') ? $db->insert_id() : null;
		} catch (Throwable $exception) {
			$inserted = false;
			$error = array('code' => 0);
			$insert_id = null;
		} finally {
			if (property_exists($db, 'db_debug')) {
				$db->db_debug = $previous_debug;
			}
		}

		if (!$inserted) {
			$error_code = is_array($error) && isset($error['code']) ? (int) $error['code'] : 0;
			if ($error_code === 1062) {
				return $this->result(true, true, null, 'duplicate_idempotency');
			}
			return $this->result(false, false, null, 'outbox_insert_failed');
		}
		return $this->result(true, false, (int) $insert_id, 'none');
	}

	private function result($success, $duplicate, $outbox_id, $safe_error_code)
	{
		return array(
			'success' => (bool) $success,
			'duplicate' => (bool) $duplicate,
			'outbox_id' => $outbox_id,
			'safe_error_code' => $safe_error_code,
		);
	}
}
