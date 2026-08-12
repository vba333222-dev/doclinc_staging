<?php
require_once dirname(__DIR__, 2) . '/realtime_requests/tests/MariaDbReadiness.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$passed = 0;
$failed = 0;
$databases = array();
$admin = null;

function phase6_migration_expect($condition, $name)
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

function phase6_migration_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function phase6_migration_identifier($value)
{
	if (preg_match('/\Adoclinc_phase6_test_[a-f0-9]+\z/', $value) !== 1) {
		throw new RuntimeException('unsafe_database_identifier');
	}
	return '`' . $value . '`';
}

function phase6_migration_run(array $arguments, array $environment)
{
	$names = array(
		'DOCLINC_PHASE6_SCHEMA_WRITE_ENABLED', 'DOCLINC_PHASE6_DISPOSABLE_TEST',
		'DOCLINC_PHASE6_SCHEMA_DB_HOST', 'DOCLINC_PHASE6_SCHEMA_DB_PORT',
		'DOCLINC_PHASE6_SCHEMA_DB_NAME', 'DOCLINC_PHASE6_SCHEMA_DB_USER',
		'DOCLINC_PHASE6_SCHEMA_DB_PASSWORD', 'DOCLINC_PHASE6_SCHEMA_ALLOWED_USERS',
	);
	foreach ($names as $name) { putenv($name); }
	foreach ($environment as $name => $value) { putenv($name . '=' . $value); }
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/application/migrations/20260813000100_phase6_clinical_visit_foundation.php');
	foreach ($arguments as $argument) { $command .= ' ' . escapeshellarg($argument); }
	$output = array();
	$exit = 1;
	exec($command . ' 2>&1', $output, $exit);
	return array('exit' => $exit, 'output' => implode("\n", $output));
}

function phase6_migration_settings($database, $host, $port, $user, $password)
{
	return array(
		'DOCLINC_PHASE6_SCHEMA_WRITE_ENABLED' => 'true',
		'DOCLINC_PHASE6_DISPOSABLE_TEST' => 'true',
		'DOCLINC_PHASE6_SCHEMA_DB_HOST' => $host,
		'DOCLINC_PHASE6_SCHEMA_DB_PORT' => $port,
		'DOCLINC_PHASE6_SCHEMA_DB_NAME' => $database,
		'DOCLINC_PHASE6_SCHEMA_DB_USER' => $user,
		'DOCLINC_PHASE6_SCHEMA_DB_PASSWORD' => $password,
		'DOCLINC_PHASE6_SCHEMA_ALLOWED_USERS' => $user,
	);
}

function phase6_migration_apply($database, array $settings)
{
	return phase6_migration_run(array('--apply', '--environment=test', '--confirm-database=' . $database, '--confirm-disposable-test=true'), $settings);
}

function phase6_migration_database(mysqli $admin, $host, $port, $user, $password, array &$databases, $with_rows = false)
{
	$database = 'doclinc_phase6_test_' . bin2hex(random_bytes(6));
	$admin->query('CREATE DATABASE ' . phase6_migration_identifier($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$databases[] = $database;
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	$db->query("CREATE TABLE users(userId INT(11) NOT NULL,nama VARCHAR(100) NULL,PRIMARY KEY(userId)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$db->query("CREATE TABLE requests(request_id INT(11) NOT NULL,request_status VARCHAR(30) NULL,PRIMARY KEY(request_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$db->query("CREATE TABLE puskesmas_staff(staff_id INT(10) UNSIGNED NOT NULL,user_id INT(11) NULL,PRIMARY KEY(staff_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$db->query("CREATE TABLE medicalrecords(record_id INT(11) NOT NULL AUTO_INCREMENT,request_id INT(11) NOT NULL,diagnosis TEXT NULL,PRIMARY KEY(record_id),KEY idx_medical_request(request_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	if ($with_rows) {
		$db->query("INSERT INTO users VALUES(10,'Synthetic')");
		$db->query("INSERT INTO requests VALUES(100,'Accepted')");
		$db->query("INSERT INTO puskesmas_staff VALUES(31,10)");
		$db->query("INSERT INTO medicalrecords(request_id,diagnosis) VALUES(100,'Synthetic')");
	}
	return $db;
}

function phase6_migration_digest(mysqli $db, $table, $order, $columns = '*')
{
	$rows = $db->query('SELECT ' . $columns . ' FROM `' . $table . '` ORDER BY `' . $order . '`')->fetch_all(MYSQLI_ASSOC);
	return hash('sha256', json_encode($rows));
}

$password = phase6_migration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($password === '') {
	fwrite(STDERR, "PHASE6_MIGRATION_INTEGRATION=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n");
	exit(2);
}

try {
	$host = phase6_migration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1');
	$port = (int) phase6_migration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306');
	$user = phase6_migration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root');
	$ready = MariaDbReadiness::wait(array('host' => $host, 'user' => $user, 'password' => $password, 'port' => $port, 'timeout_ms' => 45000, 'interval_ms' => 500));
	$admin = $ready->connection;

	$plan = phase6_migration_run(array(), array('DOCLINC_PHASE6_SCHEMA_DB_HOST' => 'invalid.invalid'));
	phase6_migration_expect($plan['exit'] === 0 && strpos($plan['output'], 'DATABASE_CONNECTION_OPENED=false') !== false && strpos($plan['output'], 'DDL_EXECUTED=false') !== false, 'plan_opens_no_database');

	$clean = phase6_migration_database($admin, $host, $port, $user, $password, $databases);
	$clean_database = $clean->query('SELECT DATABASE()')->fetch_row()[0];
	$clean_settings = phase6_migration_settings($clean_database, $host, $port, $user, $password);
	$clean_result = phase6_migration_apply($clean_database, $clean_settings);
	phase6_migration_expect($clean_result['exit'] === 0 && strpos($clean_result['output'], 'DDL_STATEMENT_COUNT=2') !== false, 'clean_apply_succeeds');
	$noop = phase6_migration_apply($clean_database, $clean_settings);
	phase6_migration_expect($noop['exit'] === 0 && strpos($noop['output'], 'ALREADY_APPLIED=true') !== false && strpos($noop['output'], 'DDL_EXECUTED=false') !== false, 'exact_schema_is_noop');
	$clean->close();

	$partial = phase6_migration_database($admin, $host, $port, $user, $password, $databases);
	$partial_database = $partial->query('SELECT DATABASE()')->fetch_row()[0];
	$partial->query('ALTER TABLE medicalrecords ADD COLUMN responsible_doctor_user_id INT(11) NULL');
	$partial->close();
	$partial_result = phase6_migration_apply($partial_database, phase6_migration_settings($partial_database, $host, $port, $user, $password));
	phase6_migration_expect($partial_result['exit'] !== 0 && strpos($partial_result['output'], 'SAFE_ERROR_CODE=partial_schema_detected') !== false && strpos($partial_result['output'], 'DDL_EXECUTED=false') !== false, 'partial_schema_fails_closed');

	$invalid_base = phase6_migration_database($admin, $host, $port, $user, $password, $databases);
	$invalid_base_database = $invalid_base->query('SELECT DATABASE()')->fetch_row()[0];
	$invalid_base->query('ALTER TABLE medicalrecords DROP INDEX idx_medical_request, DROP COLUMN request_id');
	$invalid_base->close();
	$invalid_base_result = phase6_migration_apply($invalid_base_database, phase6_migration_settings($invalid_base_database, $host, $port, $user, $password));
	phase6_migration_expect($invalid_base_result['exit'] !== 0 && strpos($invalid_base_result['output'], 'SAFE_ERROR_CODE=base_schema_mismatch') !== false && strpos($invalid_base_result['output'], 'DDL_EXECUTED=false') !== false, 'invalid_base_schema_fails_before_ddl');

	$index_conflict = phase6_migration_database($admin, $host, $port, $user, $password, $databases);
	$index_conflict_database = $index_conflict->query('SELECT DATABASE()')->fetch_row()[0];
	$index_conflict->query('ALTER TABLE medicalrecords ADD INDEX idx_medical_responsible_doctor(request_id)');
	$index_conflict->close();
	$index_conflict_result = phase6_migration_apply($index_conflict_database, phase6_migration_settings($index_conflict_database, $host, $port, $user, $password));
	phase6_migration_expect($index_conflict_result['exit'] !== 0 && strpos($index_conflict_result['output'], 'SAFE_ERROR_CODE=target_schema_mismatch') !== false && strpos($index_conflict_result['output'], 'DDL_EXECUTED=false') !== false, 'target_index_conflict_fails_before_ddl');

	$incompatible = phase6_migration_database($admin, $host, $port, $user, $password, $databases);
	$incompatible_database = $incompatible->query('SELECT DATABASE()')->fetch_row()[0];
	$incompatible_settings = phase6_migration_settings($incompatible_database, $host, $port, $user, $password);
	phase6_migration_apply($incompatible_database, $incompatible_settings);
	$incompatible->query('ALTER TABLE request_vital_sign_measurements MODIFY notes VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL');
	$incompatible->close();
	$incompatible_result = phase6_migration_apply($incompatible_database, $incompatible_settings);
	phase6_migration_expect($incompatible_result['exit'] !== 0 && strpos($incompatible_result['output'], 'SAFE_ERROR_CODE=target_schema_mismatch') !== false, 'incompatible_target_fails_closed');

	$existing = phase6_migration_database($admin, $host, $port, $user, $password, $databases, true);
	$existing_database = $existing->query('SELECT DATABASE()')->fetch_row()[0];
	$before = array(phase6_migration_digest($existing, 'users', 'userId'), phase6_migration_digest($existing, 'requests', 'request_id'), phase6_migration_digest($existing, 'puskesmas_staff', 'staff_id'), phase6_migration_digest($existing, 'medicalrecords', 'record_id', 'record_id,request_id,diagnosis'));
	$existing->close();
	$existing_result = phase6_migration_apply($existing_database, phase6_migration_settings($existing_database, $host, $port, $user, $password));
	$existing = new mysqli($host, $user, $password, $existing_database, $port);
	$after = array(phase6_migration_digest($existing, 'users', 'userId'), phase6_migration_digest($existing, 'requests', 'request_id'), phase6_migration_digest($existing, 'puskesmas_staff', 'staff_id'), phase6_migration_digest($existing, 'medicalrecords', 'record_id', 'record_id,request_id,diagnosis'));
	$null_attribution_count = (int) $existing->query('SELECT COUNT(*) FROM medicalrecords WHERE responsible_doctor_user_id IS NULL AND recorded_by_user_id IS NULL')->fetch_row()[0];
	$existing->close();
	phase6_migration_expect($existing_result['exit'] === 0 && $before === $after, 'existing_rows_preserved');
	phase6_migration_expect($null_attribution_count === 1, 'existing_medicalrecord_not_backfilled');

	$wrong_confirmation = phase6_migration_run(array('--apply', '--environment=test', '--confirm-database=wrong', '--confirm-disposable-test=true'), $clean_settings);
	phase6_migration_expect($wrong_confirmation['exit'] !== 0 && strpos($wrong_confirmation['output'], 'SAFE_ERROR_CODE=database_confirmation_mismatch') !== false && strpos($wrong_confirmation['output'], 'DATABASE_CONNECTION_OPENED=false') !== false, 'wrong_database_confirmation_fails_before_connection');
	$write_disabled = $clean_settings;
	$write_disabled['DOCLINC_PHASE6_SCHEMA_WRITE_ENABLED'] = 'false';
	$write_disabled_result = phase6_migration_apply($clean_database, $write_disabled);
	phase6_migration_expect($write_disabled_result['exit'] !== 0 && strpos($write_disabled_result['output'], 'SAFE_ERROR_CODE=schema_write_disabled') !== false && strpos($write_disabled_result['output'], 'DDL_EXECUTED=false') !== false, 'write_flag_required');
} catch (Throwable $exception) {
	$failed++;
	fwrite(STDERR, "FAIL migration_runtime\nSAFE_ERROR_CODE=phase6_migration_test_failed\n");
} finally {
	if ($admin) {
		foreach (array_reverse($databases) as $database) {
			if (preg_match('/\Adoclinc_phase6_test_[a-f0-9]+\z/', $database) === 1) {
				$admin->query('DROP DATABASE IF EXISTS ' . phase6_migration_identifier($database));
			}
		}
		$leftovers = (int) $admin->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'doclinc_phase6_test_%'")->fetch_row()[0];
		phase6_migration_expect($leftovers === 0, 'disposable_databases_cleaned');
		$admin->close();
	}
}

echo "PHASE6_MIGRATION_ASSERTIONS=" . ($passed + $failed) . "\n";
echo "PHASE6_MIGRATION_PASS={$passed}\nPHASE6_MIGRATION_FAIL={$failed}\nPHASE6_MIGRATION_SKIP=0\n";
exit($failed === 0 ? 0 : 1);
