<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 3) . '/'); }
if (!defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }
require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';
require_once APPPATH . 'libraries/Nakes_presence_service.php';
require_once dirname(__DIR__, 2) . '/realtime_requests/tests/MariaDbReadiness.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$passed = 0;
$failed = 0;
$admin = null;
$db = null;
$database_name = '';
$stage = 'bootstrap';

function presence_integration_expect($condition, $name)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$name}\n"; return; }
	$failed++; fwrite(STDERR, "FAIL {$name}\n");
}

function presence_integration_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function presence_integration_identifier($value)
{
	if (preg_match('/\A[A-Za-z0-9_]+\z/D', $value) !== 1) { throw new InvalidArgumentException('identifier_invalid'); }
	return '`' . $value . '`';
}

function presence_personal_actor($user_id, $code)
{
	return array(
		'authenticated' => true, 'user_id' => (int) $user_id, 'role' => 'dokter', 'status' => 'aktif', 'must_change_password' => false,
		'identity' => array('valid' => true, 'account_type' => 'personal', 'user_id' => (int) $user_id, 'staff_id' => (int) $user_id, 'staff_status' => 'aktif', 'puskesmas_code' => $code),
	);
}

$password = presence_integration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($password === '') {
	fwrite(STDERR, "NAKES_PRESENCE_INTEGRATION=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n");
	exit(2);
}

try {
	$host = presence_integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1');
	$port = (int) presence_integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306');
	$user = presence_integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root');
	$stage = 'admin_readiness';
	$ready = MariaDbReadiness::wait(array(
		'host' => $host, 'user' => $user, 'password' => $password, 'port' => $port,
		'timeout_ms' => (int) presence_integration_env('DOCLINC_TEST_DB_READY_TIMEOUT_MS', '45000'),
		'interval_ms' => (int) presence_integration_env('DOCLINC_TEST_DB_READY_INTERVAL_MS', '500'),
	));
	$admin = $ready->connection;
	$database_name = 'doclinc_presence_test_' . bin2hex(random_bytes(5));
	$admin->query('CREATE DATABASE ' . presence_integration_identifier($database_name) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$db = DB(array(
		'dsn' => '', 'hostname' => $host, 'username' => $user, 'password' => $password, 'database' => $database_name,
		'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false, 'db_debug' => false, 'cache_on' => false,
		'cachedir' => '', 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci', 'swap_pre' => '',
		'encrypt' => false, 'compress' => false, 'stricton' => true, 'failover' => array(), 'save_queries' => true, 'port' => $port,
	), true);

	$stage = 'schema';
	$ddl = array(
		"CREATE TABLE users(userId int NOT NULL,nama varchar(100) NOT NULL,role enum('admin','dokter','warga','') NOT NULL,status enum('aktif','nonaktif') NOT NULL,must_change_password tinyint(1) NOT NULL DEFAULT 0,PRIMARY KEY(userId)) ENGINE=InnoDB",
		"CREATE TABLE m_puskesmas(kode_pkm varchar(100) NOT NULL,nama_puskesmas varchar(150) NOT NULL,status enum('aktif','nonaktif') NOT NULL,PRIMARY KEY(kode_pkm)) ENGINE=InnoDB",
		"CREATE TABLE puskesmas_staff(staff_id int NOT NULL,kode_pkm varchar(100) NOT NULL,nama varchar(150) NOT NULL,profesi varchar(100) NULL,user_id int NULL,status enum('aktif','nonaktif') NOT NULL,PRIMARY KEY(staff_id)) ENGINE=InnoDB",
		"CREATE TABLE nakes_presence(user_id int NOT NULL,puskesmas_code varchar(100) NOT NULL,last_seen_at datetime(6) NOT NULL,last_transition_at datetime(6) NULL,last_persisted_at datetime(6) NOT NULL,updated_at datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),PRIMARY KEY(user_id),KEY idx_presence_tenant(puskesmas_code,last_seen_at,user_id)) ENGINE=InnoDB",
		"CREATE TABLE presence_test_marker(marker_id int NOT NULL,PRIMARY KEY(marker_id)) ENGINE=InnoDB",
	);
	foreach ($ddl as $sql) { $db->query($sql); }
	$db->query("INSERT INTO users VALUES (1,'Admin','admin','aktif',0),(10,'Command A','dokter','aktif',0),(20,'Command B','dokter','aktif',0),(201,'Nakes A','dokter','aktif',0),(202,'Nakes B','dokter','aktif',0),(203,'Nakes C','dokter','aktif',0),(204,'Inactive','dokter','nonaktif',0)");
	$db->query("INSERT INTO m_puskesmas VALUES ('PKM01','Puskesmas A','aktif'),('PKM02','Puskesmas B','aktif')");
	$db->query("INSERT INTO puskesmas_staff VALUES (31,'PKM01','Nakes A','Dokter',201,'aktif'),(32,'PKM01','Nakes B','Perawat',202,'aktif'),(33,'PKM02','Nakes C','Bidan',203,'aktif'),(34,'PKM01','Inactive','Dokter',204,'aktif')");

	$stage = 'runtime';
	$service = new Nakes_presence_service($db, 90, 45);
	presence_integration_expect($service->schemaReady(), 'schema_ready');
	$first = $service->touch(presence_personal_actor(201, 'PKM01'));
	presence_integration_expect(!empty($first['ok']) && !empty($first['persisted']), 'first_heartbeat_persisted');
	$standalone_transaction = $db->query('SELECT @@in_transaction AS active')->row();
	presence_integration_expect((int) $standalone_transaction->active === 0, 'standalone_heartbeat_closes_transaction');
	$first_row = $db->query('SELECT last_seen_at,last_persisted_at,last_transition_at FROM nakes_presence WHERE user_id=201')->row_array();
	$second = $service->touch(presence_personal_actor(201, 'PKM01'));
	$second_row = $db->query('SELECT last_seen_at,last_persisted_at,last_transition_at FROM nakes_presence WHERE user_id=201')->row_array();
	presence_integration_expect(!empty($second['ok']) && empty($second['persisted']) && $first_row === $second_row, 'second_heartbeat_throttled_without_write');
	$throttled_transaction = $db->query('SELECT @@in_transaction AS active')->row();
	presence_integration_expect((int) $throttled_transaction->active === 0, 'throttled_heartbeat_closes_transaction');

	$db->query("CREATE TRIGGER fail_presence_insert BEFORE INSERT ON nakes_presence FOR EACH ROW BEGIN IF NEW.user_id = 202 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic_presence_write_failure'; END IF; END");
	$failed_standalone = $service->touch(presence_personal_actor(202, 'PKM01'));
	$failed_transaction = $db->query('SELECT @@in_transaction AS active')->row();
	presence_integration_expect(empty($failed_standalone['ok']) && $failed_standalone['code'] === 'write_failed', 'standalone_write_failure_returned');
	presence_integration_expect((int) $db->where('user_id', 202)->count_all_results('nakes_presence') === 0
		&& (int) $failed_transaction->active === 0, 'standalone_write_failure_rolled_back_and_closed');
	$db->query('DROP TRIGGER fail_presence_insert');

	$db->trans_begin();
	$db->query('INSERT INTO presence_test_marker(marker_id) VALUES (1)');
	$nested_success = $service->touch(presence_personal_actor(202, 'PKM01'));
	$nested_success_transaction = $db->query('SELECT @@in_transaction AS active')->row();
	presence_integration_expect(!empty($nested_success['ok']) && !empty($nested_success['persisted'])
		&& (int) $nested_success_transaction->active === 1, 'nested_success_keeps_caller_transaction_open');
	$db->trans_rollback();
	$nested_success_closed = $db->query('SELECT @@in_transaction AS active')->row();
	presence_integration_expect((int) $db->count_all('presence_test_marker') === 0
		&& (int) $db->where('user_id', 202)->count_all_results('nakes_presence') === 0
		&& (int) $nested_success_closed->active === 0, 'outer_rollback_reverts_successful_nested_touch');

	$db->query("CREATE TRIGGER fail_presence_insert BEFORE INSERT ON nakes_presence FOR EACH ROW BEGIN IF NEW.user_id = 202 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic_presence_nested_failure'; END IF; END");
	$db->trans_begin();
	$db->query('INSERT INTO presence_test_marker(marker_id) VALUES (2)');
	$nested_failure = $service->touch(presence_personal_actor(202, 'PKM01'));
	$nested_failure_transaction = $db->query('SELECT @@in_transaction AS active')->row();
	presence_integration_expect(empty($nested_failure['ok']) && $nested_failure['code'] === 'write_failed'
		&& (int) $nested_failure_transaction->active === 1, 'nested_failure_propagates_without_closing_caller_transaction');
	$db->trans_rollback();
	$nested_failure_closed = $db->query('SELECT @@in_transaction AS active')->row();
	presence_integration_expect((int) $db->count_all('presence_test_marker') === 0
		&& (int) $db->where('user_id', 202)->count_all_results('nakes_presence') === 0
		&& (int) $nested_failure_closed->active === 0, 'caller_rollback_after_nested_failure_is_effective');
	$db->query('DROP TRIGGER fail_presence_insert');

	$denied_actor = presence_personal_actor(202, 'PKM01');
	$denied_actor['identity']['valid'] = false;
	$denied = $service->touch($denied_actor);
	presence_integration_expect(empty($denied['ok']) && (int) $db->count_all('nakes_presence') === 1, 'denied_heartbeat_zero_write');

	$service->touch(presence_personal_actor(203, 'PKM02'));
	$tenant_actor = array('authenticated' => true, 'user_id' => 10, 'role' => 'dokter', 'status' => 'aktif', 'must_change_password' => false,
		'identity' => array('valid' => true, 'account_type' => 'command_center', 'is_command_center' => true, 'puskesmas_code' => 'PKM01'));
	$tenant = $service->snapshot($tenant_actor);
	presence_integration_expect(!empty($tenant['ok']) && count($tenant['data']['rows']) === 2, 'tenant_snapshot_contains_only_active_linked_staff');
	presence_integration_expect(count(array_unique(array_column($tenant['data']['rows'], 'puskesmas_code'))) === 1 && $tenant['data']['rows'][0]['puskesmas_code'] === 'PKM01', 'tenant_snapshot_cannot_cross_puskesmas');
	$admin_result = $service->snapshot(array('authenticated' => true, 'user_id' => 1, 'role' => 'admin', 'status' => 'aktif', 'must_change_password' => false));
	presence_integration_expect(!empty($admin_result['ok']) && count($admin_result['data']['rows']) === 3, 'admin_snapshot_all_active_tenants');
	$keys = array_keys($admin_result['data']['rows'][0]);
	presence_integration_expect($keys === array('user_id','staff_id','display_name','profession','puskesmas_code','puskesmas_name','is_online','last_seen_at'), 'snapshot_exact_safe_keys');

	$db->query("UPDATE nakes_presence SET last_seen_at=DATE_SUB(NOW(6),INTERVAL 120 SECOND),last_persisted_at=DATE_SUB(NOW(6),INTERVAL 120 SECOND) WHERE user_id=201");
	$before_transition = $db->query('SELECT last_transition_at FROM nakes_presence WHERE user_id=201')->row()->last_transition_at;
	$recovered = $service->touch(presence_personal_actor(201, 'PKM01'));
	$after_transition = $db->query('SELECT last_transition_at FROM nakes_presence WHERE user_id=201')->row()->last_transition_at;
	presence_integration_expect(!empty($recovered['persisted']) && $before_transition !== $after_transition, 'offline_to_online_transition_recorded');
	$transaction = $db->query('SELECT @@in_transaction AS active')->row();
	presence_integration_expect((int) $transaction->active === 0, 'heartbeat_transaction_closed');

	echo "NAKES_PRESENCE_INTEGRATION_PASSED={$passed}\nNAKES_PRESENCE_INTEGRATION_FAILED={$failed}\n";
} catch (Throwable $exception) {
	$failed++;
	fwrite(STDERR, "FAIL presence_integration_safe_error\nSAFE_ERROR_STAGE={$stage}\nSAFE_EXCEPTION_CLASS=" . get_class($exception) . "\nSAFE_EXCEPTION_FILE=" . basename($exception->getFile()) . "\nSAFE_EXCEPTION_LINE=" . (int) $exception->getLine() . "\n");
} finally {
	if (is_object($db) && method_exists($db, 'close')) { $db->close(); }
	if ($admin instanceof mysqli) {
		if ($database_name !== '') { $admin->query('DROP DATABASE IF EXISTS ' . presence_integration_identifier($database_name)); }
		$admin->close();
	}
}
exit($failed === 0 ? 0 : 1);
