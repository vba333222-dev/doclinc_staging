<?php

if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 3) . '/system/');
}
require_once dirname(__DIR__, 3) . '/application/libraries/Notification_delivery_service.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$passed = 0;
$failed = 0;
$database = '';
$stage = 'bootstrap';

function notification_integration_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo 'PASS ' . $label . "\n";
		return;
	}
	$failed++;
	fwrite(STDERR, 'FAIL ' . $label . "\n");
}

function notification_integration_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function notification_integration_identifier($value)
{
	if (!is_string($value) || preg_match('/\A[A-Za-z0-9_]+\z/D', $value) !== 1) {
		throw new InvalidArgumentException('unsafe_test_identifier');
	}
	return '`' . $value . '`';
}

final class NotificationIntegrationDatabase
{
	public $db_debug = true;
	private $db;
	private $insert_id = 0;
	private $error = array('code' => 0);
	private $fail_outbox;

	public function __construct(mysqli $db, $fail_outbox = false)
	{
		$this->db = $db;
		$this->fail_outbox = (bool) $fail_outbox;
	}

	public function trans_begin()
	{
		return $this->db->begin_transaction();
	}

	public function trans_status()
	{
		return true;
	}

	public function trans_commit()
	{
		return $this->db->commit();
	}

	public function trans_rollback()
	{
		return $this->db->rollback();
	}

	public function insert($table, array $data)
	{
		if ($table === 'realtime_outbox' && $this->fail_outbox) {
			$this->error = array('code' => 1205);
			return false;
		}
		if (!in_array($table, array('notifications', 'realtime_outbox'), true)) {
			$this->error = array('code' => 1146);
			return false;
		}
		$columns = array_keys($data);
		$sql = 'INSERT INTO ' . notification_integration_identifier($table)
			. ' (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
		try {
			$stmt = $this->db->prepare($sql);
			$values = array_values($data);
			$types = '';
			foreach ($values as $value) {
				$types .= is_int($value) ? 'i' : 's';
			}
			$stmt->bind_param($types, ...$values);
			$stmt->execute();
			$this->insert_id = (int) $stmt->insert_id;
			$stmt->close();
			$this->error = array('code' => 0);
			return true;
		} catch (mysqli_sql_exception $exception) {
			$this->error = array('code' => (int) $exception->getCode());
			return false;
		}
	}

	public function insert_id()
	{
		return $this->insert_id;
	}

	public function error()
	{
		return $this->error;
	}
}

function notification_integration_data($entity_id)
{
	return array(
		'recipient_user_id' => 101,
		'recipient_role' => 'warga',
		'recipient_puskesmas_code' => null,
		'actor_user_id' => 202,
		'event_type' => 'request_accepted',
		'entity_type' => 'request',
		'entity_id' => (string) $entity_id,
		'title' => 'Synthetic notification',
		'message' => 'Synthetic message',
		'is_read' => 0,
		'created_at' => '2026-01-01 00:00:00',
	);
}

function notification_integration_count(mysqli $db, $table)
{
	return (int) $db->query('SELECT COUNT(1) AS total FROM ' . notification_integration_identifier($table))->fetch_assoc()['total'];
}

$admin_password = notification_integration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($admin_password === '') {
	fwrite(STDERR, "REALTIME_NOTIFICATION_INTEGRATION_RESULT=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n");
	exit(2);
}

try {
	$stage = 'admin_connect';
	$admin = new mysqli(
		notification_integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1'),
		notification_integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root'),
		$admin_password,
		'',
		(int) notification_integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306')
	);
	$database = 'doclinc_notification_test_' . bin2hex(random_bytes(5));
	$stage = 'database_create';
	$admin->query('CREATE DATABASE ' . notification_integration_identifier($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$stage = 'database_connect';
	$db = new mysqli(
		notification_integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1'),
		notification_integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root'),
		$admin_password,
		$database,
		(int) notification_integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306')
	);
	$db->set_charset('utf8mb4');
	$stage = 'notifications_schema';
	$db->query("CREATE TABLE notifications (
		notification_id int(11) NOT NULL AUTO_INCREMENT,
		recipient_user_id int(11) NULL, recipient_role varchar(32) NULL,
		recipient_puskesmas_code varchar(32) NULL, actor_user_id int(11) NULL,
		event_type varchar(64) NOT NULL, entity_type varchar(64) NOT NULL,
		entity_id varchar(64) NOT NULL, title varchar(160) NOT NULL, message text NULL,
		is_read tinyint(1) NOT NULL DEFAULT 0, read_at datetime NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (notification_id),
		KEY idx_notifications_user_read_created (recipient_user_id,is_read,created_at),
		KEY idx_notifications_puskesmas_read_created (recipient_puskesmas_code,is_read,created_at),
		KEY idx_notifications_event_entity (event_type,entity_type,entity_id),
		KEY idx_notifications_created (created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$stage = 'outbox_schema';
	$db->query("CREATE TABLE realtime_outbox (
		outbox_id bigint unsigned NOT NULL AUTO_INCREMENT,
		event_type varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		aggregate_type varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		aggregate_id varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		audience_type varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		audience_key varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		payload_json longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
		event_version bigint unsigned NOT NULL DEFAULT 1,
		idempotency_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		state varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
		attempt_count smallint unsigned NOT NULL DEFAULT 0,
		available_at datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
		claimed_at datetime(6) NULL, published_at datetime(6) NULL,
		last_error_code varchar(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
		created_at datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
		updated_at datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
		PRIMARY KEY (outbox_id), UNIQUE KEY uq_realtime_outbox_idempotency (idempotency_key),
		KEY idx_realtime_outbox_dispatch (state,available_at,outbox_id),
		KEY idx_realtime_outbox_audience (audience_type,audience_key,outbox_id),
		KEY idx_realtime_outbox_aggregate (aggregate_type,aggregate_id,outbox_id),
		CONSTRAINT chk_realtime_outbox_payload_json CHECK (json_valid(payload_json)),
		CONSTRAINT chk_realtime_outbox_audience CHECK (audience_type in ('user','puskesmas','request','admin')),
		CONSTRAINT chk_realtime_outbox_state CHECK (state in ('pending','claimed','published','failed'))
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='doclinc_care_operations_realtime_outbox_20260728000100'");
	$stage = 'baseline_schema';
	$db->query('CREATE TABLE unrelated_domain (id int(11) NOT NULL PRIMARY KEY, marker varchar(32) NOT NULL) ENGINE=InnoDB');
	$db->query("INSERT INTO unrelated_domain (id,marker) VALUES (1,'preserved')");

	$stage = 'delivery_commit';
	$adapter = new NotificationIntegrationDatabase($db);
	$service = new Notification_delivery_service($adapter, array('enabled' => true));
	$committed = $service->create(notification_integration_data(1001));
	notification_integration_expect($committed === 1 && notification_integration_count($db, 'notifications') === 1
		&& notification_integration_count($db, 'realtime_outbox') === 1, 'notification_and_outbox_commit_together');

	$stage = 'caller_rollback';
	$db->begin_transaction();
	$caller_adapter = new NotificationIntegrationDatabase($db);
	$inside = (new Notification_delivery_service($caller_adapter, array('enabled' => true)))->createWithinTransaction(notification_integration_data(1002));
	$db->rollback();
	notification_integration_expect($inside === 2 && notification_integration_count($db, 'notifications') === 1
		&& notification_integration_count($db, 'realtime_outbox') === 1, 'caller_rollback_removes_notification_and_outbox');

	$stage = 'forced_failure';
	$failed_service = new Notification_delivery_service(new NotificationIntegrationDatabase($db, true), array('enabled' => true));
	$forced = $failed_service->create(notification_integration_data(1003));
	notification_integration_expect($forced === false && notification_integration_count($db, 'notifications') === 1
		&& notification_integration_count($db, 'realtime_outbox') === 1, 'enqueue_failure_zero_partial_rows');

	$stage = 'payload_assertion';
	$row = $db->query('SELECT audience_key,payload_json,idempotency_key FROM realtime_outbox WHERE outbox_id=1')->fetch_assoc();
	$payload = json_decode($row['payload_json'], true);
	notification_integration_expect($row['audience_key'] === 'user:101' && is_array($payload)
		&& array_keys($payload) === array('aggregate_id', 'audience', 'event_id', 'event_type', 'invalidation', 'version'), 'stored_outbox_payload_exact');
	$payload_text = (string) $row['payload_json'];
	notification_integration_expect(strpos($payload_text, 'Synthetic notification') === false && strpos($payload_text, 'Synthetic message') === false, 'durable_content_absent_from_outbox');

	$stage = 'idempotency';
	$writer = new Realtime_outbox_writer();
	$event = array('event_id' => 'notification:9001', 'event_type' => 'notification.created', 'aggregate_type' => 'notification',
		'aggregate_id' => '9001', 'version' => 1, 'invalidation' => 'notifications', 'audience' => 'user:101');
	$first = $writer->enqueue($adapter, $event, array(), array('enabled' => true));
	$second = $writer->enqueue($adapter, $event, array(), array('enabled' => true));
	notification_integration_expect($first['success'] && $second['success'] && $second['duplicate']
		&& notification_integration_count($db, 'realtime_outbox') === 2, 'duplicate_idempotency_no_duplicate_row');

	$unrelated = $db->query('SELECT marker FROM unrelated_domain WHERE id=1')->fetch_assoc();
	notification_integration_expect($unrelated && $unrelated['marker'] === 'preserved', 'unrelated_domain_unchanged');

	echo 'REALTIME_NOTIFICATION_INTEGRATION_PASSED=' . $passed . "\n";
	echo 'REALTIME_NOTIFICATION_INTEGRATION_FAILED=' . $failed . "\n";
} catch (Throwable $exception) {
	$failed++;
	fwrite(STDERR, "FAIL integration_safe_error\n");
	fwrite(STDERR, 'SAFE_ERROR_STAGE=' . $stage . "\n");
	fwrite(STDERR, 'SAFE_EXCEPTION_CLASS=' . get_class($exception) . "\n");
	fwrite(STDERR, 'SAFE_EXCEPTION_CODE=' . (int) $exception->getCode() . "\n");
} finally {
	if (isset($db) && $db instanceof mysqli) {
		$db->close();
	}
	if (isset($admin) && $admin instanceof mysqli) {
		if ($database !== '') {
			$admin->query('DROP DATABASE IF EXISTS ' . notification_integration_identifier($database));
		}
		$admin->close();
	}
}

exit($failed === 0 ? 0 : 1);
