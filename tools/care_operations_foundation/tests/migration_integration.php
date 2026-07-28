<?php

require_once dirname(__DIR__) . '/CareOperationsFixture.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$passed = 0;
$failed = 0;
$databases = array();
$writers = array();

function integration_expect($condition, $label)
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

function integration_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function integration_database_name($suffix)
{
	return 'doclinc_care_operations_test_' . $suffix . '_' . bin2hex(random_bytes(3));
}

function integration_identifier($value)
{
	if (!is_string($value) || preg_match('/\A[a-zA-Z0-9_]+\z/', $value) !== 1) {
		throw new InvalidArgumentException('unsafe_test_identifier');
	}
	return '`' . $value . '`';
}

function integration_create_database(mysqli $admin, $name, $staff_type = 'int(10) unsigned')
{
	global $databases;
	$admin->query('CREATE DATABASE ' . integration_identifier($name) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$databases[] = $name;
	$db = new mysqli(
		integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1'),
		integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root'),
		integration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD'),
		$name,
		(int) integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306')
	);
	$db->set_charset('utf8mb4');
	CareOperationsFixture::createSchema($db, $staff_type);
	CareOperationsFixture::seedBaseline($db);
	return $db;
}

function integration_create_writer(mysqli $admin, $database)
{
	global $writers;
	$user = 'care_operations_' . bin2hex(random_bytes(4));
	$password = bin2hex(random_bytes(24));
	$quoted_user = "'" . $admin->real_escape_string($user) . "'@'%'";
	$quoted_password = "'" . $admin->real_escape_string($password) . "'";
	$admin->query('CREATE USER ' . $quoted_user . ' IDENTIFIED BY ' . $quoted_password);
	$writers[] = $user;
	$admin->query('GRANT CREATE ON ' . integration_identifier($database) . '.* TO ' . $quoted_user);
	foreach (array(
		'users', 'm_puskesmas', 'puskesmas_staff', 'requests',
		'request_staff_assignments', 'medicalrecords', 'notifications',
		'clinical_suggestion_terms',
	) as $table) {
		$admin->query('GRANT SELECT ON ' . integration_identifier($database) . '.' . integration_identifier($table) . ' TO ' . $quoted_user);
	}
	return array($user, $password);
}

function integration_run_migration($database, $user, $password, array $extra_args = array())
{
	$migration = dirname(__DIR__, 3) . '/application/migrations/20260728000100_care_operations_foundation.php';
	$command = array_merge(array(
		PHP_BINARY,
		$migration,
		'--apply',
		'--environment=test',
		'--confirm-database=' . $database,
		'--backup-reference=DISPOSABLE-CARE_OPERATIONS',
		'--confirm-backup-sha256=' . str_repeat('a', 64),
		'--confirm-disposable-test=true',
	), $extra_args);
	$environment = array_merge(getenv(), array(
		'DOCLINC_CARE_OPERATIONS_SCHEMA_WRITE_ENABLED' => 'true',
		'DOCLINC_CARE_OPERATIONS_DISPOSABLE_TEST' => 'true',
		'DOCLINC_CARE_OPERATIONS_SCHEMA_DB_HOST' => integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1'),
		'DOCLINC_CARE_OPERATIONS_SCHEMA_DB_PORT' => integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306'),
		'DOCLINC_CARE_OPERATIONS_SCHEMA_DB_NAME' => $database,
		'DOCLINC_CARE_OPERATIONS_SCHEMA_DB_USER' => $user,
		'DOCLINC_CARE_OPERATIONS_SCHEMA_DB_PASSWORD' => $password,
		'DOCLINC_CARE_OPERATIONS_SCHEMA_ALLOWED_USERS' => $user,
	));
	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);
	$process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 3), $environment);
	if (!is_resource($process)) {
		throw new RuntimeException('migration_process_start_failed');
	}
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit_code = proc_close($process);
	$password = null;
	return array('exit_code' => $exit_code, 'stdout' => $stdout, 'stderr' => $stderr);
}

function integration_count(mysqli $db, $table)
{
	return (int) $db->query('SELECT COUNT(1) AS total FROM ' . integration_identifier($table))->fetch_assoc()['total'];
}

$host = integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1');
$port = (int) integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306');
$admin_user = integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root');
$admin_password = integration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');

if ($admin_password === '') {
	fwrite(STDERR, "INTEGRATION_RESULT=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n");
	exit(2);
}

try {
	$admin = new mysqli($host, $admin_user, $admin_password, '', $port);
	$admin->set_charset('utf8mb4');

	$valid_name = integration_database_name('valid');
	$valid_db = integration_create_database($admin, $valid_name);
	$before = CareOperationsFixture::snapshot($valid_db);
	list($valid_writer, $valid_password) = integration_create_writer($admin, $valid_name);
	$result = integration_run_migration($valid_name, $valid_writer, $valid_password);
	integration_expect($result['exit_code'] === 0, 'migration_apply_exit_success');
	integration_expect(strpos($result['stdout'], 'MIGRATION_RESULT=PASS') !== false, 'migration_apply_pass_marker');
	integration_expect(strpos($result['stdout'], 'DDL_STATEMENT_COUNT=5') !== false, 'migration_apply_five_tables');
	if ($result['exit_code'] !== 0) {
		$safe_code = preg_match('/SAFE_ERROR_CODE=([a-z0-9_]+)/', $result['stderr'], $match) === 1
			? $match[1]
			: 'migration_apply_failed';
		fwrite(STDERR, 'DIAGNOSTIC_SAFE_ERROR_CODE=' . $safe_code . "\n");
		if (preg_match('/FAILED_SCHEMA_OBJECT=([A-Za-z0-9_.]+)/', $result['stderr'], $object_match) === 1) {
			fwrite(STDERR, 'DIAGNOSTIC_FAILED_SCHEMA_OBJECT=' . $object_match[1] . "\n");
		}
		throw new RuntimeException('valid_apply_failed');
	}

	$target_tables = array('realtime_outbox', 'consultation_visit_media', 'medicalrecord_diagnoses', 'nakes_presence', 'visit_location_updates');
	foreach ($target_tables as $table) {
		$row = $valid_db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $valid_db->real_escape_string($table) . "'")->fetch_assoc();
		integration_expect($row && strtoupper((string) $row['ENGINE']) === 'INNODB', $table . '_innodb');
		integration_expect(integration_count($valid_db, $table) === 0, $table . '_starts_empty');
	}
	$foreign_key_count = (int) $valid_db->query("SELECT COUNT(1) AS total FROM information_schema.TABLE_CONSTRAINTS
		WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME IN ('realtime_outbox','consultation_visit_media','medicalrecord_diagnoses','nakes_presence','visit_location_updates')
		AND CONSTRAINT_TYPE='FOREIGN KEY'")->fetch_assoc()['total'];
	integration_expect($foreign_key_count === 0, 'target_logical_references_have_no_fk');

	$after = CareOperationsFixture::snapshot($valid_db);
	integration_expect($after['counts'] === $before['counts'], 'existing_row_counts_unchanged');
	integration_expect(hash_equals($before['password_digest'], $after['password_digest']), 'existing_passwords_unchanged');
	integration_expect($after['counts']['users'] === 51
		&& (int) $valid_db->query("SELECT COUNT(1) AS total FROM users WHERE role='admin' AND status='aktif'")->fetch_assoc()['total'] === 1
		&& (int) $valid_db->query("SELECT COUNT(1) AS total FROM users WHERE role='dokter' AND status='aktif'")->fetch_assoc()['total'] === 34
		&& (int) $valid_db->query("SELECT COUNT(1) AS total FROM users WHERE role='warga' AND status='aktif'")->fetch_assoc()['total'] === 16,
		'baseline_account_cardinality_preserved');
	integration_expect($after['counts']['puskesmas_staff'] === 26
		&& $after['counts']['requests'] === 7
		&& $after['counts']['medicalrecords'] === 7
		&& $after['counts']['request_staff_assignments'] === 7
		&& $after['counts']['notifications'] === 26,
		'baseline_domain_cardinality_preserved');
	integration_expect((int) $valid_db->query("SELECT COUNT(1) AS total FROM request_staff_assignments WHERE status='aktif'")->fetch_assoc()['total'] === 7,
		'active_historical_assignments_preserved');
	integration_expect((int) $after['inactive_staff']['staff_id'] === 31
		&& $after['inactive_staff']['user_id'] === null
		&& $after['inactive_staff']['status'] === 'nonaktif', 'inactive_staff_31_preserved');
	integration_expect($after['command_center_count'] === 9, 'command_centers_preserved');
	integration_expect($after['personal_presence_target_count'] === 25, 'inactive_staff_excluded_from_presence_targets');
	integration_expect($after['counts']['m_puskesmas'] === 9, 'puskesmas_preserved');
	integration_expect($after['counts']['clinical_suggestion_import_batches'] === 1
		&& $after['counts']['clinical_suggestion_terms'] === 11450
		&& $after['counts']['clinical_suggestion_aliases'] === 701
		&& $after['counts']['clinical_schema_migrations'] === 6, 'clinical_rows_preserved');

	$idempotent = integration_run_migration($valid_name, $valid_writer, $valid_password);
	integration_expect($idempotent['exit_code'] === 0, 'migration_idempotency_exit_success');
	integration_expect(strpos($idempotent['stdout'], 'ALREADY_APPLIED=true') !== false
		&& strpos($idempotent['stdout'], 'DDL_EXECUTED=false') !== false, 'migration_idempotency_zero_ddl');
	$valid_db->query('ALTER TABLE nakes_presence ADD COLUMN fixture_unreviewed_column int(11) NULL');
	$signature_mismatch = integration_run_migration($valid_name, $valid_writer, $valid_password);
	integration_expect($signature_mismatch['exit_code'] !== 0
		&& strpos($signature_mismatch['stderr'], 'SAFE_ERROR_CODE=target_schema_hash_mismatch') !== false
		&& strpos($signature_mismatch['stderr'], 'FAILED_SCHEMA_OBJECT=nakes_presence.__show_create') !== false,
		'target_schema_hash_mismatch_rejected');

	$partial_name = integration_database_name('partial');
	$partial_db = integration_create_database($admin, $partial_name);
	$partial_db->query('CREATE TABLE realtime_outbox (fixture_id int(11) NOT NULL PRIMARY KEY) ENGINE=InnoDB');
	list($partial_writer, $partial_password) = integration_create_writer($admin, $partial_name);
	$partial = integration_run_migration($partial_name, $partial_writer, $partial_password);
	integration_expect($partial['exit_code'] !== 0
		&& strpos($partial['stderr'], 'SAFE_ERROR_CODE=partial_schema_detected') !== false, 'partial_schema_rejected');
	integration_expect(integration_count($partial_db, 'realtime_outbox') === 0, 'partial_schema_test_row_state_unchanged');

	$mismatch_name = integration_database_name('mismatch');
	$mismatch_db = integration_create_database($admin, $mismatch_name, 'int(10)');
	list($mismatch_writer, $mismatch_password) = integration_create_writer($admin, $mismatch_name);
	$mismatch = integration_run_migration($mismatch_name, $mismatch_writer, $mismatch_password);
	integration_expect($mismatch['exit_code'] !== 0
		&& strpos($mismatch['stderr'], 'SAFE_ERROR_CODE=base_schema_mismatch') !== false, 'base_signature_signed_staff_id_rejected');
	$created_on_mismatch = (int) $mismatch_db->query("SELECT COUNT(1) AS total FROM information_schema.TABLES
		WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('realtime_outbox','consultation_visit_media','medicalrecord_diagnoses','nakes_presence','visit_location_updates')")->fetch_assoc()['total'];
	integration_expect($created_on_mismatch === 0, 'base_signature_failure_zero_ddl');

	$valid_password = null;
	$partial_password = null;
	$mismatch_password = null;
} catch (Throwable $exception) {
	$failed++;
	fwrite(STDERR, "FAIL integration_unexpected\nSAFE_ERROR_CODE=integration_execution_failed\n");
	fwrite(STDERR, 'DIAGNOSTIC_EXCEPTION_CLASS=' . get_class($exception) . "\n");
	fwrite(STDERR, 'DIAGNOSTIC_EXCEPTION_CODE=' . (int) $exception->getCode() . "\n");
} finally {
	if (isset($admin) && $admin instanceof mysqli) {
		foreach ($databases as $database) {
			try {
				$admin->query('DROP DATABASE IF EXISTS ' . integration_identifier($database));
			} catch (Throwable $ignored) {
			}
		}
		foreach ($writers as $writer) {
			try {
				$admin->query("DROP USER IF EXISTS '" . $admin->real_escape_string($writer) . "'@'%'");
			} catch (Throwable $ignored) {
			}
		}
		$admin->close();
	}
	$admin_password = null;
}

echo 'INTEGRATION_TESTS_PASSED=' . $passed . "\n";
echo 'INTEGRATION_TESTS_FAILED=' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
