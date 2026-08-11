<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 3) . '/'); }
if (!defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }

require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';
require_once APPPATH . 'libraries/Puskesmas_operations_service.php';
require_once dirname(__DIR__, 2) . '/realtime_requests/tests/MariaDbReadiness.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$passed = 0;
$failed = 0;
$admin = null;
$db = null;
$database_name = '';
$stage = 'bootstrap';

function operations_integration_expect($condition, $name)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$name}\n";
		return;
	}
	$failed++;
	fwrite(STDERR, "FAIL {$name}\n");
}

function operations_integration_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function operations_integration_identifier($value)
{
	if (preg_match('/\A[A-Za-z0-9_]+\z/D', (string) $value) !== 1) {
		throw new InvalidArgumentException('identifier_invalid');
	}
	return '`' . $value . '`';
}

function operations_command_center_actor($user_id, $puskesmas_code)
{
	return array(
		'authenticated' => true,
		'user_id' => (int) $user_id,
		'role' => 'dokter',
		'status' => 'aktif',
		'must_change_password' => false,
		'identity' => array(
			'valid' => true,
			'account_type' => 'command_center',
			'is_command_center' => true,
			'user_id' => (int) $user_id,
			'puskesmas_code' => (string) $puskesmas_code,
		),
	);
}

function operations_database_digest($db)
{
	$queries = array(
		'SELECT * FROM users ORDER BY userId',
		'SELECT * FROM m_puskesmas ORDER BY kode_pkm',
		'SELECT * FROM puskesmas_staff ORDER BY staff_id',
		'SELECT * FROM nakes_presence ORDER BY user_id',
		'SELECT * FROM requests ORDER BY request_id',
		'SELECT * FROM request_staff_assignments ORDER BY assignment_id',
	);
	$state = array();
	foreach ($queries as $query) {
		$result = $db->query($query);
		if (!$result) {
			throw new RuntimeException('digest_read_failed');
		}
		$state[] = $result->result_array();
	}
	return hash('sha256', json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

$password = operations_integration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($password === '') {
	fwrite(STDERR, "PUSKESMAS_OPERATIONS_INTEGRATION=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n");
	exit(2);
}

try {
	$host = operations_integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1');
	$port = (int) operations_integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306');
	$user = operations_integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root');

	$stage = 'admin_readiness';
	$ready = MariaDbReadiness::wait(array(
		'host' => $host,
		'user' => $user,
		'password' => $password,
		'port' => $port,
		'timeout_ms' => (int) operations_integration_env('DOCLINC_TEST_DB_READY_TIMEOUT_MS', '45000'),
		'interval_ms' => (int) operations_integration_env('DOCLINC_TEST_DB_READY_INTERVAL_MS', '500'),
	));
	$admin = $ready->connection;
	$database_name = 'doclinc_puskesmas_ops_test_' . bin2hex(random_bytes(5));
	$admin->query(
		'CREATE DATABASE ' . operations_integration_identifier($database_name)
		. ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
	);

	$db = DB(array(
		'dsn' => '',
		'hostname' => $host,
		'username' => $user,
		'password' => $password,
		'database' => $database_name,
		'dbdriver' => 'mysqli',
		'dbprefix' => '',
		'pconnect' => false,
		'db_debug' => false,
		'cache_on' => false,
		'cachedir' => '',
		'char_set' => 'utf8mb4',
		'dbcollat' => 'utf8mb4_unicode_ci',
		'swap_pre' => '',
		'encrypt' => false,
		'compress' => false,
		'stricton' => true,
		'failover' => array(),
		'save_queries' => true,
		'port' => $port,
	), true);

	$stage = 'schema';
	$ddl = array(
		"CREATE TABLE users(userId int NOT NULL,nama varchar(100) NOT NULL,no_hp varchar(32) NULL,nik varchar(16) NULL,role enum('admin','dokter','warga','') NOT NULL,status enum('aktif','nonaktif') NOT NULL,PRIMARY KEY(userId)) ENGINE=InnoDB",
		"CREATE TABLE m_puskesmas(kode_pkm varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,nama_puskesmas varchar(150) NOT NULL,status enum('aktif','nonaktif') NOT NULL,PRIMARY KEY(kode_pkm)) ENGINE=InnoDB",
		"CREATE TABLE puskesmas_staff(staff_id int NOT NULL,kode_pkm varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,nama varchar(150) NOT NULL,profesi varchar(100) NULL,nomor_sip varchar(100) NULL,nip varchar(18) NULL,user_id int NULL,status enum('aktif','nonaktif') NOT NULL,PRIMARY KEY(staff_id)) ENGINE=InnoDB",
		"CREATE TABLE nakes_presence(user_id int NOT NULL,puskesmas_code varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,last_seen_at datetime(6) NOT NULL,PRIMARY KEY(user_id),KEY idx_presence_tenant(puskesmas_code,last_seen_at,user_id)) ENGINE=InnoDB",
		"CREATE TABLE requests(request_id int NOT NULL,user_id int NOT NULL,request_description text NULL,request_status enum('Pending','Accepted','Completed','Cancelled') NOT NULL,assigned_puskesmas_code varchar(100) NOT NULL,assigned_nakes_user_id int NULL,visit_status varchar(32) NULL,PRIMARY KEY(request_id),KEY idx_request_tenant_status(assigned_puskesmas_code,request_status,request_id)) ENGINE=InnoDB",
	);
	foreach ($ddl as $sql) {
		$db->query($sql);
	}
	$collations = $db->query("SELECT TABLE_NAME,COLUMN_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (TABLE_NAME,COLUMN_NAME) IN (('puskesmas_staff','kode_pkm'),('nakes_presence','puskesmas_code')) ORDER BY TABLE_NAME,COLUMN_NAME")->result_array();
	operations_integration_expect($collations === array(
		array('TABLE_NAME' => 'nakes_presence', 'COLUMN_NAME' => 'puskesmas_code', 'COLLATION_NAME' => 'utf8mb4_unicode_ci'),
		array('TABLE_NAME' => 'puskesmas_staff', 'COLUMN_NAME' => 'kode_pkm', 'COLLATION_NAME' => 'utf8mb4_general_ci'),
	), 'presence_fixture_uses_real_mixed_collations');

	$service = new Puskesmas_operations_service($db, 90);
	operations_integration_expect(!$service->schemaReady(), 'missing_assignment_schema_fails_closed');
	$missing_schema = $service->snapshot(operations_command_center_actor(10, 'PKM01'));
	operations_integration_expect(empty($missing_schema['ok']) && $missing_schema['code'] === 'schema_unavailable', 'missing_assignment_snapshot_fails_closed');

	$db->query("CREATE TABLE request_staff_assignments(assignment_id int NOT NULL,request_id int NOT NULL,staff_id int NOT NULL,status enum('aktif','diganti','dibatalkan') NOT NULL,PRIMARY KEY(assignment_id),KEY idx_assignment_request_status(request_id,status,assignment_id)) ENGINE=InnoDB");
	$db->data_cache = array();
	$complete_schema_ready = $service->schemaReady();
	operations_integration_expect($complete_schema_ready, 'complete_schema_ready');
	if (!$complete_schema_ready) {
		throw new RuntimeException('complete_schema_not_ready');
	}

	$stage = 'fixture';
	$db->query("INSERT INTO users VALUES
		(10,'Command A',NULL,NULL,'dokter','aktif'),
		(20,'Command B',NULL,NULL,'dokter','aktif'),
		(101,'Nakes A','081200000001','3671000000000001','dokter','aktif'),
		(102,'Nakes B','081200000002','3671000000000002','dokter','aktif'),
		(103,'Nakes Inactive','081200000003','3671000000000003','dokter','nonaktif'),
		(201,'Nakes Tenant B','081200000004','3671000000000004','dokter','aktif'),
		(301,'Warga Rahasia','081299999999','3671999999999999','warga','aktif')");
	$db->query("INSERT INTO m_puskesmas VALUES
		('PKM01','Puskesmas A','aktif'),
		('PKM02','Puskesmas B','aktif'),
		('PKM03','Puskesmas Nonaktif','nonaktif')");
	$db->query("INSERT INTO puskesmas_staff VALUES
		(11,'PKM01','Nakes A','Dokter','SIP-SECRET-A','198001012010011001',101,'aktif'),
		(12,'PKM01','Nakes B','Perawat','SIP-SECRET-B','198001012010011002',102,'aktif'),
		(13,'PKM01','Nakes Inactive','Dokter','SIP-SECRET-C','198001012010011003',103,'aktif'),
		(21,'PKM02','Nakes Tenant B','Bidan','SIP-SECRET-D','198001012010011004',201,'aktif')");
	$db->query("INSERT INTO nakes_presence VALUES
		(101,'PKM01',NOW(6)),
		(102,'PKM01',DATE_SUB(NOW(6),INTERVAL 5 MINUTE)),
		(201,'PKM02',NOW(6))");
	$db->query("INSERT INTO requests VALUES
		(1,301,'complaint-secret-1','Pending','PKM01',NULL,'not_started'),
		(2,301,'complaint-secret-2','Accepted','PKM01',101,'en_route'),
		(3,301,'complaint-secret-3','Accepted','PKM01',NULL,'arrived'),
		(4,301,'complaint-secret-4','Accepted','PKM01',102,'not_started'),
		(5,301,'complaint-secret-5','Accepted','PKM01',101,'in_service'),
		(6,301,'complaint-secret-6','Accepted','PKM01',201,'not_started'),
		(7,301,'complaint-secret-7','Pending','PKM02',NULL,'not_started'),
		(8,301,'complaint-secret-8','Accepted','PKM02',201,'arrived')");
	$db->query("INSERT INTO request_staff_assignments VALUES
		(20,2,11,'aktif'),
		(40,4,12,'aktif'),
		(50,5,11,'aktif'),
		(51,5,12,'aktif'),
		(60,6,21,'aktif'),
		(80,8,21,'aktif')");

	$stage = 'runtime';
	$before_digest = operations_database_digest($db);
	$tenant_a = $service->snapshot(operations_command_center_actor(10, 'PKM01'));
	$after_digest = operations_database_digest($db);
	$tenant_a_ready = !empty($tenant_a['ok']) && $tenant_a['code'] === 'ok' && isset($tenant_a['data']);
	operations_integration_expect($tenant_a_ready, 'tenant_a_snapshot_succeeds');
	operations_integration_expect($before_digest === $after_digest, 'tenant_a_snapshot_zero_database_mutation');
	if (!$tenant_a_ready) {
		throw new RuntimeException('tenant_a_snapshot_unavailable');
	}

	$summary = $tenant_a['data']['summary'];
	operations_integration_expect($summary['pending_requests'] === 1 && $summary['accepted_requests'] === 5, 'tenant_a_request_counts_exact');
	operations_integration_expect($summary['unassigned_requests'] === 1 && $summary['ambiguous_assignments'] === 2, 'tenant_a_assignment_exceptions_exact');
	operations_integration_expect($summary['online_staff'] === 1 && $summary['offline_staff'] === 1, 'tenant_a_presence_counts_exact');
	operations_integration_expect($summary['busy_staff'] === 2 && $summary['offline_with_active_requests'] === 1, 'tenant_a_workload_counts_exact');
	operations_integration_expect($summary['not_started_requests'] === 2 && $summary['en_route_requests'] === 1 && $summary['arrived_requests'] === 1 && $summary['in_service_requests'] === 1, 'tenant_a_visit_counts_exact');

	$tenant_a_user_ids = array_column($tenant_a['data']['staff'], 'user_id');
	sort($tenant_a_user_ids);
	operations_integration_expect($tenant_a_user_ids === array(101, 102), 'tenant_a_staff_exact_and_inactive_excluded');
	$exception_ids = array_column($tenant_a['data']['exceptions'], 'request_id');
	sort($exception_ids);
	operations_integration_expect($exception_ids === array(3, 5, 6), 'tenant_a_exception_ids_exact');
	operations_integration_expect($tenant_a['data']['puskesmas_code'] === 'PKM01', 'tenant_a_scope_explicit');

	$serialized_a = json_encode($tenant_a['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	foreach (array('Warga Rahasia', '081299999999', '3671999999999999', 'complaint-secret', 'SIP-SECRET', '198001012010011') as $forbidden_value) {
		operations_integration_expect(strpos($serialized_a, $forbidden_value) === false, 'tenant_a_private_value_absent_' . substr(hash('sha256', $forbidden_value), 0, 8));
	}
	operations_integration_expect(strpos($serialized_a, 'Nakes Tenant B') === false, 'tenant_b_staff_absent_from_tenant_a');

	$tenant_b = $service->snapshot(operations_command_center_actor(20, 'PKM02'));
	$tenant_b_ready = !empty($tenant_b['ok']) && isset($tenant_b['data']['staff']) && count($tenant_b['data']['staff']) === 1;
	operations_integration_expect($tenant_b_ready, 'tenant_b_snapshot_succeeds');
	if (!$tenant_b_ready) {
		throw new RuntimeException('tenant_b_snapshot_unavailable');
	}
	operations_integration_expect($tenant_b['data']['staff'][0]['user_id'] === 201 && $tenant_b['data']['puskesmas_code'] === 'PKM02', 'tenant_b_scope_exact');
	operations_integration_expect($tenant_b['data']['summary']['pending_requests'] === 1 && $tenant_b['data']['summary']['accepted_requests'] === 1, 'tenant_b_request_counts_exact');
	operations_integration_expect(count($tenant_b['data']['exceptions']) === 0, 'tenant_b_has_no_tenant_a_exceptions');

	$personal_actor = operations_command_center_actor(101, 'PKM01');
	$personal_actor['identity']['account_type'] = 'personal';
	$personal_actor['identity']['is_command_center'] = false;
	$denied_digest = operations_database_digest($db);
	$personal_denied = $service->snapshot($personal_actor);
	operations_integration_expect(empty($personal_denied['ok']) && $personal_denied['code'] === 'actor_denied', 'personal_nakes_denied');
	operations_integration_expect($denied_digest === operations_database_digest($db), 'personal_denial_zero_database_mutation');

	$admin_actor = operations_command_center_actor(1, 'PKM01');
	$admin_actor['role'] = 'admin';
	$admin_denied = $service->snapshot($admin_actor);
	operations_integration_expect(empty($admin_denied['ok']) && $admin_denied['code'] === 'actor_denied', 'admin_denied');

	$injection_actor = operations_command_center_actor(10, "PKM01' OR 1=1 --");
	$injection_denied = $service->snapshot($injection_actor);
	operations_integration_expect(empty($injection_denied['ok']) && $injection_denied['code'] === 'tenant_denied', 'tenant_identifier_injection_fails_closed');

	$inactive_tenant = $service->snapshot(operations_command_center_actor(30, 'PKM03'));
	operations_integration_expect(empty($inactive_tenant['ok']) && $inactive_tenant['code'] === 'tenant_denied', 'inactive_tenant_denied');

	$staff_overflow_digest = operations_database_digest($db);
	$staff_overflow = $service->snapshot(operations_command_center_actor(10, 'PKM01'), 1, 200);
	operations_integration_expect(empty($staff_overflow['ok']) && $staff_overflow['code'] === 'result_too_large', 'staff_limit_overflow_fails_closed');
	operations_integration_expect($staff_overflow_digest === operations_database_digest($db), 'staff_overflow_zero_database_mutation');

	$request_overflow_digest = operations_database_digest($db);
	$request_overflow = $service->snapshot(operations_command_center_actor(10, 'PKM01'), 200, 2);
	operations_integration_expect(empty($request_overflow['ok']) && $request_overflow['code'] === 'result_too_large', 'request_limit_overflow_fails_closed');
	operations_integration_expect($request_overflow_digest === operations_database_digest($db), 'request_overflow_zero_database_mutation');

	$overflow_values = array();
	for ($assignment_id = 1000; $assignment_id <= 1600; $assignment_id++) {
		$overflow_values[] = '(' . $assignment_id . ",8,21,'aktif')";
	}
	$db->query('INSERT INTO request_staff_assignments(assignment_id,request_id,staff_id,status) VALUES ' . implode(',', $overflow_values));
	$assignment_overflow_digest = operations_database_digest($db);
	$assignment_overflow = $service->snapshot(operations_command_center_actor(20, 'PKM02'));
	operations_integration_expect(empty($assignment_overflow['ok']) && $assignment_overflow['code'] === 'result_too_large', 'assignment_limit_overflow_fails_closed');
	operations_integration_expect($assignment_overflow_digest === operations_database_digest($db), 'assignment_overflow_zero_database_mutation');

	$tenant_a_after_b_overflow = $service->snapshot(operations_command_center_actor(10, 'PKM01'));
	operations_integration_expect(!empty($tenant_a_after_b_overflow['ok']), 'tenant_b_overflow_does_not_poison_tenant_a');

	$transaction = $db->query('SELECT @@in_transaction AS active')->row();
	operations_integration_expect((int) $transaction->active === 0, 'all_snapshot_transactions_closed');

	echo "PUSKESMAS_OPERATIONS_INTEGRATION_PASSED={$passed}\n";
	echo "PUSKESMAS_OPERATIONS_INTEGRATION_FAILED={$failed}\n";
} catch (Throwable $exception) {
	$failed++;
	fwrite(STDERR, "FAIL puskesmas_operations_integration_safe_error\n");
	fwrite(STDERR, "SAFE_ERROR_STAGE={$stage}\n");
	fwrite(STDERR, 'SAFE_EXCEPTION_CLASS=' . get_class($exception) . "\n");
	fwrite(STDERR, 'SAFE_EXCEPTION_FILE=' . basename($exception->getFile()) . "\n");
	fwrite(STDERR, 'SAFE_EXCEPTION_LINE=' . (int) $exception->getLine() . "\n");
} finally {
	if (is_object($db) && method_exists($db, 'close')) {
		$db->close();
	}
	if ($admin instanceof mysqli) {
		if ($database_name !== '') {
			$admin->query('DROP DATABASE IF EXISTS ' . operations_integration_identifier($database_name));
		}
		$admin->close();
	}
}

exit($failed === 0 ? 0 : 1);
