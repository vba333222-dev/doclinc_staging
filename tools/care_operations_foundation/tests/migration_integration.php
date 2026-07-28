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

function integration_create_database(mysqli $admin, $name, $staff_type = 'int(10) unsigned', $assignment_variant = 'live', $compact = false)
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
	CareOperationsFixture::createSchema($db, $staff_type, $assignment_variant);
	CareOperationsFixture::seedBaseline($db, $compact ? 1 : 11450, $compact ? 1 : 701);
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

function integration_target_table_count(mysqli $db)
{
	return (int) $db->query("SELECT COUNT(1) AS total FROM information_schema.TABLES
		WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
		('realtime_outbox','consultation_visit_media','medicalrecord_diagnoses','nakes_presence','visit_location_updates')")
		->fetch_assoc()['total'];
}

function integration_normalize_default($value)
{
	if ($value === null || strtolower((string) $value) === 'null') {
		return null;
	}
	$value = strtolower((string) $value);
	if (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") {
		return str_replace("''", "'", substr($value, 1, -1));
	}
	return $value;
}

function integration_assignment_schema_is_live(mysqli $db)
{
	$table = $db->query("SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES
		WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='request_staff_assignments'")->fetch_assoc();
	if (!$table || strtoupper((string) $table['ENGINE']) !== 'INNODB'
		|| (string) $table['TABLE_COLLATION'] !== 'utf8mb4_general_ci') {
		return false;
	}
	$expected_columns = array(
		'assignment_id' => array('int(10) unsigned', 'NO', null, 'auto_increment'),
		'request_id' => array('int(11)', 'NO', null, ''),
		'staff_id' => array('int(10) unsigned', 'NO', null, ''),
		'kode_pkm' => array('varchar(100)', 'NO', null, ''),
		'assigned_by_user_id' => array('int(11)', 'NO', null, ''),
		'status' => array("enum('aktif','diganti','dibatalkan')", 'NO', 'aktif', ''),
		'note' => array('text', 'YES', null, ''),
		'assigned_at' => array('datetime', 'NO', 'current_timestamp()', ''),
		'ended_at' => array('datetime', 'YES', null, ''),
		'created_at' => array('datetime', 'NO', 'current_timestamp()', ''),
		'updated_at' => array('datetime', 'YES', null, 'on update current_timestamp()'),
	);
	$actual_columns = array();
	$result = $db->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA
		FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
		AND TABLE_NAME='request_staff_assignments' ORDER BY ORDINAL_POSITION");
	while ($row = $result->fetch_assoc()) {
		$actual_columns[(string) $row['COLUMN_NAME']] = array(
			strtolower((string) $row['COLUMN_TYPE']),
			(string) $row['IS_NULLABLE'],
			integration_normalize_default($row['COLUMN_DEFAULT']),
			strtolower((string) $row['EXTRA']),
		);
	}
	if ($actual_columns !== $expected_columns) {
		return false;
	}
	$actual_indexes = array();
	$result = $db->query("SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME FROM information_schema.STATISTICS
		WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='request_staff_assignments'
		ORDER BY INDEX_NAME,SEQ_IN_INDEX");
	while ($row = $result->fetch_assoc()) {
		$name = (string) $row['INDEX_NAME'];
		if (!isset($actual_indexes[$name])) {
			$actual_indexes[$name] = array((int) $row['NON_UNIQUE'], array());
		}
		$actual_indexes[$name][1][] = (string) $row['COLUMN_NAME'];
	}
	$expected_indexes = array(
		'PRIMARY' => array(0, array('assignment_id')),
		'idx_rsa_assigned_by' => array(1, array('assigned_by_user_id')),
		'idx_rsa_kode_status' => array(1, array('kode_pkm', 'status')),
		'idx_rsa_request_status' => array(1, array('request_id', 'status')),
		'idx_rsa_staff_status' => array(1, array('staff_id', 'status')),
	);
	ksort($actual_indexes);
	ksort($expected_indexes);
	return $actual_indexes === $expected_indexes;
}

function integration_expect_assignment_variant_rejected(mysqli $admin, $variant, $label)
{
	$name = integration_database_name('rsa_' . substr(hash('sha256', $label), 0, 8));
	$db = integration_create_database($admin, $name, 'int(10) unsigned', $variant, true);
	$before = CareOperationsFixture::snapshot($db);
	list($writer, $password) = integration_create_writer($admin, $name);
	$result = integration_run_migration($name, $writer, $password);
	$after = CareOperationsFixture::snapshot($db);

	integration_expect($result['exit_code'] !== 0
		&& strpos($result['stderr'], 'SAFE_ERROR_CODE=base_schema_mismatch') !== false,
		$label . '_rejected');
	integration_expect(strpos($result['stderr'], 'DDL_STATEMENT_COUNT=0') !== false
		&& integration_target_table_count($db) === 0,
		$label . '_zero_ddl');
	integration_expect($after['counts'] === $before['counts']
		&& hash_equals($before['password_digest'], $after['password_digest']),
		$label . '_existing_state_unchanged');
	$password = null;
	$db->close();
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
	$live_assignment_signature = integration_assignment_schema_is_live($valid_db);
	integration_expect($live_assignment_signature, 'assignment_schema_live_signature_accepted');
	if ($live_assignment_signature) {
		echo "CARE_OPERATIONS_ASSIGNMENT_SCHEMA_LIVE_SIGNATURE_ACCEPTED=PASS\n";
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
	$partial_db = integration_create_database($admin, $partial_name, 'int(10) unsigned', 'live', true);
	$partial_db->query('CREATE TABLE realtime_outbox (fixture_id int(11) NOT NULL PRIMARY KEY) ENGINE=InnoDB');
	list($partial_writer, $partial_password) = integration_create_writer($admin, $partial_name);
	$partial = integration_run_migration($partial_name, $partial_writer, $partial_password);
	integration_expect($partial['exit_code'] !== 0
		&& strpos($partial['stderr'], 'SAFE_ERROR_CODE=partial_schema_detected') !== false, 'partial_schema_rejected');
	integration_expect(integration_count($partial_db, 'realtime_outbox') === 0, 'partial_schema_test_row_state_unchanged');

	$mismatch_name = integration_database_name('mismatch');
	$mismatch_db = integration_create_database($admin, $mismatch_name, 'int(10)', 'live', true);
	list($mismatch_writer, $mismatch_password) = integration_create_writer($admin, $mismatch_name);
	$mismatch = integration_run_migration($mismatch_name, $mismatch_writer, $mismatch_password);
	integration_expect($mismatch['exit_code'] !== 0
		&& strpos($mismatch['stderr'], 'SAFE_ERROR_CODE=base_schema_mismatch') !== false, 'base_signature_signed_staff_id_rejected');
	$created_on_mismatch = integration_target_table_count($mismatch_db);
	integration_expect($created_on_mismatch === 0, 'base_signature_failure_zero_ddl');

	foreach (array(
		'assignment_id_int11' => 'assignment_id_int11',
		'assignment_id_signed_int10' => 'assignment_id_signed_int10',
		'staff_id_int11' => 'assignment_staff_id_int11',
		'staff_id_signed_int10' => 'assignment_staff_id_signed_int10',
		'status_varchar' => 'assignment_status_varchar',
		'status_nullable' => 'assignment_status_nullable',
		'status_without_default' => 'assignment_status_without_default',
		'status_enum_less' => 'assignment_status_enum_less',
		'status_enum_more' => 'assignment_status_enum_more',
		'status_enum_reordered' => 'assignment_status_enum_reordered',
		'primary_missing' => 'assignment_primary_missing',
		'primary_different' => 'assignment_primary_different',
	) as $variant => $label) {
		integration_expect_assignment_variant_rejected($admin, $variant, $label);
	}

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
