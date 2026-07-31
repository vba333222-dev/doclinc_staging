<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
require_once dirname(__DIR__, 3) . '/application/libraries/Request_realtime_delivery.php';
require_once __DIR__ . '/MariaDbReadiness.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$passed = 0; $failed = 0; $database = ''; $stage = 'bootstrap';
function rr_expect($condition, $label) { global $passed, $failed; if ($condition) { $passed++; echo "PASS {$label}\n"; } else { $failed++; fwrite(STDERR, "FAIL {$label}\n"); } }
function rr_env($name, $fallback = '') { $value = getenv($name); return is_string($value) && $value !== '' ? $value : $fallback; }
function rr_ident($value) { if (preg_match('/\A[A-Za-z0-9_]+\z/D', $value) !== 1) { throw new InvalidArgumentException('unsafe_identifier'); } return '`' . $value . '`'; }
function rr_count(mysqli $db, $table) { return (int) $db->query('SELECT COUNT(1) total FROM ' . rr_ident($table))->fetch_assoc()['total']; }

final class RequestIntegrationDatabase
{
	public $db_debug = true; private $db; private $insert_id = 0; private $error = array('code' => 0); private $fail_outbox;
	public function __construct(mysqli $db, $fail_outbox = false) { $this->db = $db; $this->fail_outbox = $fail_outbox; }
	public function insert($table, array $data) {
		if ($table === 'realtime_outbox' && $this->fail_outbox) { $this->error = array('code' => 1205); return false; }
		if (!in_array($table, array('notifications', 'realtime_outbox'), true)) { return false; }
		$columns = array_keys($data); $values = array_values($data);
		$stmt = $this->db->prepare('INSERT INTO ' . rr_ident($table) . ' (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
		$types = ''; foreach ($values as $value) { $types .= is_int($value) ? 'i' : 's'; }
		try { $stmt->bind_param($types, ...$values); $stmt->execute(); $this->insert_id = (int) $stmt->insert_id; $this->error = array('code' => 0); $stmt->close(); return true; }
		catch (mysqli_sql_exception $e) { $this->error = array('code' => (int) $e->getCode()); return false; }
	}
	public function insert_id() { return $this->insert_id; }
	public function error() { return $this->error; }
}

$password = rr_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($password === '') { fwrite(STDERR, "REALTIME_REQUEST_INTEGRATION_RESULT=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n"); exit(2); }
try {
	$host = rr_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1'); $port = (int) rr_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306'); $user = rr_env('DOCLINC_TEST_DB_ADMIN_USER', 'root');
	$stage = 'admin_readiness';
	$readiness = MariaDbReadiness::wait(array(
		'host' => $host, 'user' => $user, 'password' => $password, 'port' => $port,
		'timeout_ms' => (int) rr_env('DOCLINC_TEST_DB_READY_TIMEOUT_MS', '45000'),
		'interval_ms' => (int) rr_env('DOCLINC_TEST_DB_READY_INTERVAL_MS', '500'),
	));
	$admin = $readiness->connection; $database = 'doclinc_request_test_' . bin2hex(random_bytes(5));
	$stage = 'database_create';
	$admin->query('CREATE DATABASE ' . rr_ident($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$stage = 'database_connect';
	$db = new mysqli($host, $user, $password, $database, $port); $db->set_charset('utf8mb4');
	$stage = 'schema_create';
	$db->query("CREATE TABLE requests (request_id int(11) NOT NULL AUTO_INCREMENT,user_id int(11) NOT NULL,request_status enum('Pending','Accepted','Completed','Cancelled') DEFAULT 'Pending',PRIMARY KEY(request_id)) ENGINE=InnoDB");
	$db->query("CREATE TABLE notifications (notification_id int(11) NOT NULL AUTO_INCREMENT,recipient_user_id int(11) NULL,recipient_role varchar(32) NULL,recipient_puskesmas_code varchar(32) NULL,actor_user_id int(11) NULL,event_type varchar(64) NOT NULL,entity_type varchar(64) NOT NULL,entity_id varchar(64) NOT NULL,title varchar(160) NOT NULL,message text NULL,is_read tinyint(1) NOT NULL DEFAULT 0,created_at datetime NOT NULL,PRIMARY KEY(notification_id)) ENGINE=InnoDB");
	$db->query("CREATE TABLE realtime_outbox (outbox_id bigint unsigned NOT NULL AUTO_INCREMENT,event_type varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,aggregate_type varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,aggregate_id varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,audience_type varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,audience_key varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,payload_json longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,event_version bigint unsigned NOT NULL DEFAULT 1,idempotency_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,state varchar(16) NOT NULL DEFAULT 'pending',attempt_count smallint unsigned NOT NULL DEFAULT 0,available_at datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),claimed_at datetime(6) NULL,published_at datetime(6) NULL,last_error_code varchar(64) NULL,created_at datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),PRIMARY KEY(outbox_id),UNIQUE KEY uq_realtime_outbox_idempotency(idempotency_key)) ENGINE=InnoDB");
	foreach (array('consultation_visit_media','medicalrecord_diagnoses','nakes_presence','visit_location_updates') as $table) { $db->query('CREATE TABLE ' . rr_ident($table) . ' (id int NOT NULL PRIMARY KEY) ENGINE=InnoDB'); }
	$adapter = new RequestIntegrationDatabase($db); $service = new Request_realtime_delivery($adapter, array('enabled' => true), array('enabled' => false));

	$db->begin_transaction(); $db->query("INSERT INTO requests(user_id,request_status) VALUES(101,'Pending')"); $request_id = (int) $db->insert_id;
	$ok = $service->deliver('created', $request_id, $request_id, array('user:101','puskesmas:PKM01:ops'), array('PKM01')); $db->commit();
	rr_expect($ok && rr_count($db, 'requests') === 1 && rr_count($db, 'realtime_outbox') === 2, 'caller_commit_domain_and_outbox');

	$notification = array('recipient_user_id' => 101, 'recipient_role' => 'warga', 'actor_user_id' => 202,
		'event_type' => 'request_accepted', 'entity_type' => 'request', 'entity_id' => (string) $request_id,
		'title' => 'Synthetic', 'message' => 'Synthetic', 'is_read' => 0, 'created_at' => '2026-01-01 00:00:00');
	$atomic_service = new Request_realtime_delivery($adapter, array('enabled' => true), array('enabled' => true));
	$db->begin_transaction(); $db->query("UPDATE requests SET request_status='Accepted' WHERE request_id={$request_id}");
	$atomic = $atomic_service->deliver('accepted', $request_id, $request_id, array('user:101'), array(), array($notification)); $db->commit();
	rr_expect($atomic && rr_count($db, 'notifications') === 1 && rr_count($db, 'realtime_outbox') === 4,
		'notification_and_request_invalidation_commit_atomically');

	$db->begin_transaction(); $db->query("UPDATE requests SET request_status='Completed' WHERE request_id={$request_id}");
	$inside = $atomic_service->deliver('completed', $request_id, $request_id, array('user:101'), array(), array($notification)); $db->rollback();
	rr_expect($inside && $db->query("SELECT request_status FROM requests WHERE request_id={$request_id}")->fetch_assoc()['request_status'] === 'Accepted'
		&& rr_count($db, 'notifications') === 1 && rr_count($db, 'realtime_outbox') === 4, 'caller_rollback_domain_notification_and_outbox');

	$db->begin_transaction(); $first = $service->deliver('created', $request_id, $request_id, array('user:101'), array()); $second = $service->deliver('created', $request_id, $request_id, array('user:101'), array()); $db->commit();
	rr_expect($first && $second && rr_count($db, 'realtime_outbox') === 4, 'duplicate_idempotency_no_extra_row');

	$db->begin_transaction(); $db->query("UPDATE requests SET request_status='Cancelled' WHERE request_id={$request_id}");
	$failed_delivery = new Request_realtime_delivery(new RequestIntegrationDatabase($db, true), array('enabled' => true), array('enabled' => false));
	$failed_write = $failed_delivery->deliver('cancelled', $request_id, $request_id, array('user:101'), array()); if (!$failed_write) { $db->rollback(); }
	rr_expect(!$failed_write && $db->query("SELECT request_status FROM requests WHERE request_id={$request_id}")->fetch_assoc()['request_status'] === 'Accepted', 'enqueue_failure_rolls_back_domain');
	foreach (array('consultation_visit_media','medicalrecord_diagnoses','nakes_presence','visit_location_updates') as $table) { rr_expect(rr_count($db, $table) === 0, $table . '_unchanged'); }
	$row = $db->query("SELECT payload_json FROM realtime_outbox WHERE event_type='request.created' LIMIT 1")->fetch_assoc(); $payload = json_decode($row['payload_json'], true);
	rr_expect(array_keys($payload) === array('aggregate_id','audience','event_id','event_type','invalidation','version'), 'stored_payload_exact');
	echo "REALTIME_REQUEST_INTEGRATION_PASSED={$passed}\nREALTIME_REQUEST_INTEGRATION_FAILED={$failed}\n";
} catch (Throwable $e) { $failed++; fwrite(STDERR, "FAIL integration_safe_error\nSAFE_ERROR_STAGE={$stage}\nSAFE_EXCEPTION_CLASS=" . get_class($e) . "\n"); }
finally { if (isset($db)) { $db->close(); } if (isset($admin)) { if ($database !== '') { $admin->query('DROP DATABASE IF EXISTS ' . rr_ident($database)); } $admin->close(); } }
exit($failed === 0 ? 0 : 1);
