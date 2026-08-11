<?php
require_once dirname(__DIR__, 2) . '/realtime_requests/tests/MariaDbReadiness.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$passed = 0;
$failed = 0;
$databases = array();
$admin = null;

function phase45_migration_expect($condition, $name)
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

function phase45_migration_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function phase45_migration_identifier($value)
{
	if (preg_match('/\Adoclinc_session_care_test_matrix_[a-f0-9]+\z/', $value) !== 1) {
		throw new RuntimeException('unsafe_database_identifier');
	}
	return '`' . $value . '`';
}

function phase45_migration_run(array $arguments, array $environment)
{
	$names = array(
		'DOCLINC_SESSION_CARE_SCHEMA_WRITE_ENABLED',
		'DOCLINC_SESSION_CARE_DISPOSABLE_TEST',
		'DOCLINC_SESSION_CARE_SCHEMA_DB_HOST',
		'DOCLINC_SESSION_CARE_SCHEMA_DB_PORT',
		'DOCLINC_SESSION_CARE_SCHEMA_DB_NAME',
		'DOCLINC_SESSION_CARE_SCHEMA_DB_USER',
		'DOCLINC_SESSION_CARE_SCHEMA_DB_PASSWORD',
		'DOCLINC_SESSION_CARE_SCHEMA_ALLOWED_USERS',
	);
	foreach ($names as $name) {
		putenv($name);
	}
	foreach ($environment as $name => $value) {
		putenv($name . '=' . $value);
	}
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/application/migrations/20260811000100_session_care_team_foundation.php');
	foreach ($arguments as $argument) {
		$command .= ' ' . escapeshellarg($argument);
	}
	$output = array();
	$exit = 1;
	exec($command . ' 2>&1', $output, $exit);
	return array('exit' => $exit, 'output' => implode("\n", $output));
}

function phase45_migration_settings($database, $host, $port, $user, $password)
{
	return array(
		'DOCLINC_SESSION_CARE_SCHEMA_WRITE_ENABLED' => 'true',
		'DOCLINC_SESSION_CARE_DISPOSABLE_TEST' => 'true',
		'DOCLINC_SESSION_CARE_SCHEMA_DB_HOST' => $host,
		'DOCLINC_SESSION_CARE_SCHEMA_DB_PORT' => $port,
		'DOCLINC_SESSION_CARE_SCHEMA_DB_NAME' => $database,
		'DOCLINC_SESSION_CARE_SCHEMA_DB_USER' => $user,
		'DOCLINC_SESSION_CARE_SCHEMA_DB_PASSWORD' => $password,
		'DOCLINC_SESSION_CARE_SCHEMA_ALLOWED_USERS' => $user,
	);
}

function phase45_migration_apply($database, array $settings)
{
	return phase45_migration_run(array(
		'--apply',
		'--environment=test',
		'--confirm-database=' . $database,
		'--confirm-disposable-test=true',
	), $settings);
}

function phase45_migration_database(mysqli $admin, $host, $port, $user, $password, array &$databases, $with_rows = false)
{
	$database = 'doclinc_session_care_test_matrix_' . bin2hex(random_bytes(6));
	$admin->query('CREATE DATABASE ' . phase45_migration_identifier($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$databases[] = $database;
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	$db->query("CREATE TABLE users(userId INT(11) NOT NULL AUTO_INCREMENT,password VARCHAR(255) NOT NULL,role ENUM('admin','dokter','warga','') NOT NULL,status ENUM('aktif','nonaktif') NULL DEFAULT 'aktif',remark VARCHAR(100) NULL,PRIMARY KEY(userId)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$db->query("CREATE TABLE requests(request_id INT(11) NOT NULL AUTO_INCREMENT,request_status ENUM('Pending','Accepted','Completed','Cancelled') NULL DEFAULT 'Pending',assigned_puskesmas_code VARCHAR(100) NULL,accepted_by_user_id INT(11) NULL,assigned_nakes_user_id INT(11) NULL,consultation_mode VARCHAR(30) NULL,PRIMARY KEY(request_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$db->query("CREATE TABLE puskesmas_staff(staff_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,user_id INT(11) NULL,kode_pkm VARCHAR(100) NOT NULL,profesi VARCHAR(100) NULL,status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',PRIMARY KEY(staff_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	if ($with_rows) {
		$db->query("INSERT INTO users(userId,password,role,status,remark) VALUES(10,'synthetic-hash-a','dokter','aktif','PKM01'),(11,'synthetic-hash-b','warga','aktif',NULL)");
		$db->query("INSERT INTO requests(request_id,request_status,assigned_puskesmas_code,accepted_by_user_id,assigned_nakes_user_id,consultation_mode) VALUES(100,'Accepted','PKM01',10,10,'visit')");
		$db->query("INSERT INTO puskesmas_staff(staff_id,user_id,kode_pkm,profesi,status) VALUES(31,10,'PKM01','Dokter','aktif')");
	}
	return $db;
}

function phase45_migration_hash(mysqli $db, $table, $order, $columns = '*')
{
	$result = $db->query('SELECT ' . $columns . ' FROM `' . $table . '` ORDER BY `' . $order . '`');
	return hash('sha256', json_encode($result->fetch_all(MYSQLI_ASSOC)));
}

function phase45_migration_partial_result($result)
{
	return $result['exit'] !== 0
		&& strpos($result['output'], 'SAFE_ERROR_CODE=partial_schema_detected') !== false
		&& strpos($result['output'], 'DDL_EXECUTED=false') !== false;
}

function phase45_migration_incompatible_database(mysqli $admin, $host, $port, $user, $password, array &$databases)
{
	$db = phase45_migration_database($admin, $host, $port, $user, $password, $databases);
	$database = $db->query('SELECT DATABASE()')->fetch_row()[0];
	$settings = phase45_migration_settings($database, $host, $port, $user, $password);
	$result = phase45_migration_apply($database, $settings);
	if ($result['exit'] !== 0) {
		throw new RuntimeException('fixture_migration_failed');
	}
	return array($db, $database, $settings);
}

$password = phase45_migration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($password === '') {
	fwrite(STDERR, "PHASE45_MIGRATION_MATRIX=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n");
	exit(2);
}

try {
	$host = phase45_migration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1');
	$port = (int) phase45_migration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306');
	$user = phase45_migration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root');
	$readiness = MariaDbReadiness::wait(array(
		'host' => $host,
		'user' => $user,
		'password' => $password,
		'port' => $port,
		'timeout_ms' => 45000,
		'interval_ms' => 500,
	));
	$admin = $readiness->connection;

	$clean = phase45_migration_database($admin, $host, $port, $user, $password, $databases);
	$clean_database = $clean->query('SELECT DATABASE()')->fetch_row()[0];
	$clean_settings = phase45_migration_settings($clean_database, $host, $port, $user, $password);
	$case1 = phase45_migration_apply($clean_database, $clean_settings);
	phase45_migration_expect($case1['exit'] === 0 && strpos($case1['output'], 'DDL_EXECUTED=true') !== false, 'case_01_clean_apply');
	$case2 = phase45_migration_apply($clean_database, $clean_settings);
	phase45_migration_expect($case2['exit'] === 0 && strpos($case2['output'], 'ALREADY_APPLIED=true') !== false
		&& strpos($case2['output'], 'DDL_EXECUTED=false') !== false, 'case_02_exact_noop');
	$clean->close();

	$partial = phase45_migration_database($admin, $host, $port, $user, $password, $databases);
	$partial_database = $partial->query('SELECT DATABASE()')->fetch_row()[0];
	$partial->query('ALTER TABLE requests ADD COLUMN responsible_doctor_user_id INT(11) NULL');
	$partial->close();
	phase45_migration_expect(phase45_migration_partial_result(phase45_migration_apply($partial_database, phase45_migration_settings($partial_database, $host, $port, $user, $password))), 'case_03_partial_requests_columns');

	$partial = phase45_migration_database($admin, $host, $port, $user, $password, $databases);
	$partial_database = $partial->query('SELECT DATABASE()')->fetch_row()[0];
	$partial->query('CREATE TABLE user_login_bindings(user_id INT(11) NOT NULL,PRIMARY KEY(user_id)) ENGINE=InnoDB');
	$partial->close();
	phase45_migration_expect(phase45_migration_partial_result(phase45_migration_apply($partial_database, phase45_migration_settings($partial_database, $host, $port, $user, $password))), 'case_04_partial_binding_table');

	$partial = phase45_migration_database($admin, $host, $port, $user, $password, $databases);
	$partial_database = $partial->query('SELECT DATABASE()')->fetch_row()[0];
	$partial->query('CREATE TABLE request_responsible_doctor_assignments(responsible_assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,PRIMARY KEY(responsible_assignment_id)) ENGINE=InnoDB');
	$partial->close();
	phase45_migration_expect(phase45_migration_partial_result(phase45_migration_apply($partial_database, phase45_migration_settings($partial_database, $host, $port, $user, $password))), 'case_05_partial_responsible_table');

	$partial = phase45_migration_database($admin, $host, $port, $user, $password, $databases);
	$partial_database = $partial->query('SELECT DATABASE()')->fetch_row()[0];
	$partial->query('CREATE TABLE request_visit_performer_assignments(visit_assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,PRIMARY KEY(visit_assignment_id)) ENGINE=InnoDB');
	$partial->close();
	phase45_migration_expect(phase45_migration_partial_result(phase45_migration_apply($partial_database, phase45_migration_settings($partial_database, $host, $port, $user, $password))), 'case_06_partial_visit_table');

	list($incompatible, $incompatible_database, $incompatible_settings) = phase45_migration_incompatible_database($admin, $host, $port, $user, $password, $databases);
	$incompatible->query('ALTER TABLE user_login_bindings MODIFY token_hash CHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL');
	$incompatible->close();
	$result = phase45_migration_apply($incompatible_database, $incompatible_settings);
	phase45_migration_expect($result['exit'] !== 0 && strpos($result['output'], 'SAFE_ERROR_CODE=target_schema_mismatch') !== false, 'case_07_incompatible_token_hash');

	list($incompatible, $incompatible_database, $incompatible_settings) = phase45_migration_incompatible_database($admin, $host, $port, $user, $password, $databases);
	$incompatible->query("ALTER TABLE request_responsible_doctor_assignments MODIFY status ENUM('aktif','dibatalkan') NOT NULL DEFAULT 'aktif'");
	$incompatible->close();
	$result = phase45_migration_apply($incompatible_database, $incompatible_settings);
	phase45_migration_expect($result['exit'] !== 0 && strpos($result['output'], 'SAFE_ERROR_CODE=target_schema_mismatch') !== false, 'case_08_incompatible_enum');

	list($incompatible, $incompatible_database, $incompatible_settings) = phase45_migration_incompatible_database($admin, $host, $port, $user, $password, $databases);
	$incompatible->query('ALTER TABLE request_visit_performer_assignments DROP INDEX idx_visit_performer_request_status, ADD INDEX idx_visit_performer_request_status(status,request_id,visit_assignment_id)');
	$incompatible->close();
	$result = phase45_migration_apply($incompatible_database, $incompatible_settings);
	phase45_migration_expect($result['exit'] !== 0 && strpos($result['output'], 'SAFE_ERROR_CODE=target_schema_mismatch') !== false, 'case_09_incompatible_index');

	$case10 = true;
	list($incompatible, $incompatible_database, $incompatible_settings) = phase45_migration_incompatible_database($admin, $host, $port, $user, $password, $databases);
	$incompatible->query('ALTER TABLE requests MODIFY responsible_doctor_user_id INT(11) NOT NULL DEFAULT 0');
	$incompatible->close();
	$result = phase45_migration_apply($incompatible_database, $incompatible_settings);
	$case10 = $case10 && $result['exit'] !== 0 && strpos($result['output'], 'SAFE_ERROR_CODE=target_schema_mismatch') !== false;
	list($incompatible, $incompatible_database, $incompatible_settings) = phase45_migration_incompatible_database($admin, $host, $port, $user, $password, $databases);
	$incompatible->query('ALTER TABLE request_responsible_doctor_assignments MODIFY responsible_assignment_id BIGINT UNSIGNED NOT NULL');
	$incompatible->close();
	$result = phase45_migration_apply($incompatible_database, $incompatible_settings);
	$case10 = $case10 && $result['exit'] !== 0 && strpos($result['output'], 'SAFE_ERROR_CODE=target_schema_mismatch') !== false;
	phase45_migration_expect($case10, 'case_10_wrong_nullability_default_extra');

	$existing = phase45_migration_database($admin, $host, $port, $user, $password, $databases, true);
	$existing_database = $existing->query('SELECT DATABASE()')->fetch_row()[0];
	$before = array(
		'users' => array((int) $existing->query('SELECT COUNT(*) FROM users')->fetch_row()[0], phase45_migration_hash($existing, 'users', 'userId')),
		'requests' => array((int) $existing->query('SELECT COUNT(*) FROM requests')->fetch_row()[0], phase45_migration_hash($existing, 'requests', 'request_id', 'request_id,request_status,assigned_puskesmas_code,accepted_by_user_id,assigned_nakes_user_id,consultation_mode')),
		'staff' => array((int) $existing->query('SELECT COUNT(*) FROM puskesmas_staff')->fetch_row()[0], phase45_migration_hash($existing, 'puskesmas_staff', 'staff_id')),
	);
	$existing->close();
	$result = phase45_migration_apply($existing_database, phase45_migration_settings($existing_database, $host, $port, $user, $password));
	$existing = new mysqli($host, $user, $password, $existing_database, $port);
	$after = array(
		'users' => array((int) $existing->query('SELECT COUNT(*) FROM users')->fetch_row()[0], phase45_migration_hash($existing, 'users', 'userId')),
		'requests' => array((int) $existing->query('SELECT COUNT(*) FROM requests')->fetch_row()[0], phase45_migration_hash($existing, 'requests', 'request_id', 'request_id,request_status,assigned_puskesmas_code,accepted_by_user_id,assigned_nakes_user_id,consultation_mode')),
		'staff' => array((int) $existing->query('SELECT COUNT(*) FROM puskesmas_staff')->fetch_row()[0], phase45_migration_hash($existing, 'puskesmas_staff', 'staff_id')),
	);
	$existing->close();
	phase45_migration_expect($result['exit'] === 0 && $before === $after, 'case_11_existing_rows_preserved');

	$legacy = phase45_migration_database($admin, $host, $port, $user, $password, $databases, true);
	$legacy_database = $legacy->query('SELECT DATABASE()')->fetch_row()[0];
	$legacy_password = $legacy->query('SELECT password FROM users WHERE userId=10')->fetch_row()[0];
	$legacy->query("UPDATE users SET password='synthetic-hash-c' WHERE userId=10");
	$legacy_request = $legacy->query('SELECT request_status FROM requests WHERE request_id=100')->fetch_row()[0];
	$legacy_schema_count = (int) $legacy->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('user_login_bindings','request_responsible_doctor_assignments','request_visit_performer_assignments')")->fetch_row()[0];
	$legacy->close();
	phase45_migration_expect($legacy_password === 'synthetic-hash-a' && $legacy_request === 'Accepted' && $legacy_schema_count === 0, 'case_12_feature_off_pre_migration');

	$plan = phase45_migration_run(array(), array('DOCLINC_SESSION_CARE_SCHEMA_DB_HOST' => 'invalid.invalid'));
	phase45_migration_expect($plan['exit'] === 0 && strpos($plan['output'], 'DATABASE_CONNECTION_OPENED=false') !== false
		&& strpos($plan['output'], 'DDL_EXECUTED=false') !== false, 'case_13_plan_zero_connection');

	$staging_settings = array('DOCLINC_SESSION_CARE_SCHEMA_WRITE_ENABLED' => 'true', 'DOCLINC_SESSION_CARE_SCHEMA_DB_NAME' => 'doclinc-staging');
	$missing_backup = phase45_migration_run(array('--apply', '--environment=staging', '--confirm-database=doclinc-staging'), $staging_settings);
	phase45_migration_expect($missing_backup['exit'] !== 0 && strpos($missing_backup['output'], 'SAFE_ERROR_CODE=backup_confirmation_mismatch') !== false
		&& strpos($missing_backup['output'], 'DATABASE_CONNECTION_OPENED=false') !== false, 'case_14_backup_missing');

	$backup = tempnam(sys_get_temp_dir(), 'phase45_backup_');
	file_put_contents($backup, 'synthetic-backup-reference');
	$mismatch = phase45_migration_run(array('--apply', '--environment=staging', '--confirm-database=doclinc-staging', '--backup-reference=' . $backup, '--confirm-backup-sha256=' . str_repeat('0', 64)), $staging_settings);
	if (is_file($backup)) { unlink($backup); }
	phase45_migration_expect($mismatch['exit'] !== 0 && strpos($mismatch['output'], 'SAFE_ERROR_CODE=backup_confirmation_mismatch') !== false
		&& strpos($mismatch['output'], 'DATABASE_CONNECTION_OPENED=false') !== false, 'case_15_backup_sha_mismatch');

	$wrong_confirmation = phase45_migration_run(array('--apply', '--environment=test', '--confirm-database=wrong', '--confirm-disposable-test=true'), $clean_settings);
	phase45_migration_expect($wrong_confirmation['exit'] !== 0 && strpos($wrong_confirmation['output'], 'SAFE_ERROR_CODE=database_confirmation_mismatch') !== false
		&& strpos($wrong_confirmation['output'], 'DATABASE_CONNECTION_OPENED=false') !== false, 'case_16_wrong_database_confirmation');

	$not_allowed = $clean_settings;
	$not_allowed['DOCLINC_SESSION_CARE_SCHEMA_ALLOWED_USERS'] = 'not_' . $user;
	$not_allowed_result = phase45_migration_apply($clean_database, $not_allowed);
	phase45_migration_expect($not_allowed_result['exit'] !== 0 && strpos($not_allowed_result['output'], 'SAFE_ERROR_CODE=database_identity_not_allowed') !== false
		&& strpos($not_allowed_result['output'], 'DDL_EXECUTED=false') !== false, 'case_17_user_not_allowlisted');

	$write_disabled = $clean_settings;
	$write_disabled['DOCLINC_SESSION_CARE_SCHEMA_WRITE_ENABLED'] = 'false';
	$write_disabled_result = phase45_migration_apply($clean_database, $write_disabled);
	phase45_migration_expect($write_disabled_result['exit'] !== 0 && strpos($write_disabled_result['output'], 'SAFE_ERROR_CODE=schema_write_disabled') !== false
		&& strpos($write_disabled_result['output'], 'DDL_EXECUTED=false') !== false, 'case_18_write_flag_absent');
} catch (Throwable $exception) {
	$failed++;
	fwrite(STDERR, "FAIL migration_matrix_runtime\nSAFE_ERROR_CODE=phase45_migration_matrix_failed\n");
} finally {
	if ($admin) {
		foreach (array_reverse($databases) as $database) {
			try { $admin->query('DROP DATABASE ' . phase45_migration_identifier($database)); } catch (Throwable $ignored) { $failed++; }
		}
		$admin->close();
	}
}

echo "MIGRATION_MATRIX_CASES=18\nMIGRATION_MATRIX_PASS={$passed}\nMIGRATION_MATRIX_FAIL={$failed}\nMIGRATION_MATRIX_SKIP=0\n";
exit($failed === 0 && $passed === 18 ? 0 : 1);
