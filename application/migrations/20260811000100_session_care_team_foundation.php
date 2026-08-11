<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');

const DOCLINC_SESSION_CARE_MIGRATION_ID = '20260811000100_session_care_team_foundation';
const DOCLINC_SESSION_CARE_LOCK = 'doclinc_session_care_20260811000100';

function session_care_fail($code, $connection_opened = false, $ddl_count = 0)
{
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$code}\nDATABASE_CONNECTION_OPENED=" . ($connection_opened ? 'true' : 'false')
		. "\nDDL_EXECUTED=" . ($ddl_count > 0 ? 'true' : 'false') . "\nDDL_STATEMENT_COUNT=" . (int) $ddl_count . "\n");
	exit(1);
}

function session_care_true($name)
{
	$value = getenv($name);
	return is_string($value) && in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
}

function session_care_table(mysqli $db, $database, $table)
{
	$stmt = $db->prepare('SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
	$stmt->bind_param('ss', $database, $table);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function session_care_column(mysqli $db, $database, $table, $column)
{
	$stmt = $db->prepare('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
	$stmt->bind_param('sss', $database, $table, $column);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function session_care_index(mysqli $db, $database, $table, $index)
{
	$stmt = $db->prepare('SELECT NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? GROUP BY NON_UNIQUE');
	$stmt->bind_param('sss', $database, $table, $index);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function session_care_normalize($value)
{
	return $value === null ? null : strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
}

function session_care_normalize_default($value)
{
	$normalized = session_care_normalize($value);
	if (!is_string($normalized) || strlen($normalized) < 2 || $normalized[0] !== "'" || substr($normalized, -1) !== "'") {
		return $normalized;
	}
	return str_replace("''", "'", substr($normalized, 1, -1));
}

function session_care_assert_table_exact(mysqli $db, $database, $table)
{
	$row = session_care_table($db, $database, $table);
	if (!$row || session_care_normalize($row['ENGINE']) !== 'innodb'
		|| session_care_normalize($row['TABLE_COLLATION']) !== 'utf8mb4_unicode_ci') {
		throw new RuntimeException('target_schema_mismatch');
	}
}

function session_care_assert_column_exact(mysqli $db, $database, $table, $column, array $expected)
{
	$row = session_care_column($db, $database, $table, $column);
	if (!$row
		|| session_care_normalize($row['COLUMN_TYPE']) !== $expected['type']
		|| session_care_normalize($row['IS_NULLABLE']) !== $expected['nullable']
		|| session_care_normalize_default($row['COLUMN_DEFAULT']) !== $expected['default']
		|| session_care_normalize($row['EXTRA']) !== $expected['extra']
		|| session_care_normalize($row['CHARACTER_SET_NAME']) !== $expected['charset']
		|| session_care_normalize($row['COLLATION_NAME']) !== $expected['collation']) {
		throw new RuntimeException('target_schema_mismatch');
	}
}

function session_care_column_contract($type, $nullable, $default = null, $extra = '', $charset = null, $collation = null)
{
	return array(
		'type' => session_care_normalize($type),
		'nullable' => session_care_normalize($nullable),
		'default' => session_care_normalize_default($default),
		'extra' => session_care_normalize($extra),
		'charset' => session_care_normalize($charset),
		'collation' => session_care_normalize($collation),
	);
}

function session_care_assert_base(mysqli $db, $database)
{
	foreach (array('users', 'requests', 'puskesmas_staff') as $table) {
		$row = session_care_table($db, $database, $table);
		if (!$row || strtoupper((string) $row['ENGINE']) !== 'INNODB') {
			throw new RuntimeException('base_schema_mismatch');
		}
	}
	$required = array(
		array('users', 'userId'),
		array('requests', 'request_id'),
		array('requests', 'assigned_puskesmas_code'),
		array('requests', 'consultation_mode'),
		array('puskesmas_staff', 'staff_id'),
		array('puskesmas_staff', 'user_id'),
	);
	foreach ($required as $item) {
		if (!session_care_column($db, $database, $item[0], $item[1])) {
			throw new RuntimeException('base_schema_mismatch');
		}
	}
}

function session_care_assert_target(mysqli $db, $database)
{
	session_care_assert_column_exact($db, $database, 'requests', 'responsible_doctor_user_id', session_care_column_contract('int(11)', 'yes', 'null'));
	session_care_assert_column_exact($db, $database, 'requests', 'visit_performer_user_id', session_care_column_contract('int(11)', 'yes', 'null'));
	foreach (array('user_login_bindings', 'request_responsible_doctor_assignments', 'request_visit_performer_assignments') as $table) {
		session_care_assert_table_exact($db, $database, $table);
	}
	$binding_columns = array(
		'user_id' => session_care_column_contract('int(11)', 'no'),
		'token_hash' => session_care_column_contract('char(64)', 'yes', 'null', '', 'ascii', 'ascii_bin'),
		'issued_at' => session_care_column_contract('datetime(6)', 'yes', 'null'),
		'revoked_at' => session_care_column_contract('datetime(6)', 'yes', 'null'),
		'created_at' => session_care_column_contract('datetime(6)', 'no', 'current_timestamp(6)'),
		'updated_at' => session_care_column_contract('datetime(6)', 'no', 'current_timestamp(6)', 'on update current_timestamp(6)'),
	);
	foreach ($binding_columns as $column => $expected) {
		session_care_assert_column_exact($db, $database, 'user_login_bindings', $column, $expected);
	}
	$assignment_columns = array(
		'responsible_assignment_id' => session_care_column_contract('bigint(20) unsigned', 'no', null, 'auto_increment'),
		'request_id' => session_care_column_contract('int(11)', 'no'),
		'staff_id' => session_care_column_contract('int(10) unsigned', 'no'),
		'user_id' => session_care_column_contract('int(11)', 'no'),
		'assigned_by_user_id' => session_care_column_contract('int(11)', 'no'),
		'status' => session_care_column_contract("enum('aktif','diganti')", 'no', 'aktif', '', 'utf8mb4', 'utf8mb4_unicode_ci'),
		'assigned_at' => session_care_column_contract('datetime(6)', 'no'),
		'ended_at' => session_care_column_contract('datetime(6)', 'yes', 'null'),
		'created_at' => session_care_column_contract('datetime(6)', 'no', 'current_timestamp(6)'),
		'updated_at' => session_care_column_contract('datetime(6)', 'no', 'current_timestamp(6)', 'on update current_timestamp(6)'),
	);
	foreach ($assignment_columns as $column => $expected) {
		session_care_assert_column_exact($db, $database, 'request_responsible_doctor_assignments', $column, $expected);
	}
	$performer_assignment_columns = array(
		'visit_assignment_id' => session_care_column_contract('bigint(20) unsigned', 'no', null, 'auto_increment'),
		'request_id' => session_care_column_contract('int(11)', 'no'),
		'staff_id' => session_care_column_contract('int(10) unsigned', 'no'),
		'user_id' => session_care_column_contract('int(11)', 'no'),
		'assigned_by_user_id' => session_care_column_contract('int(11)', 'no'),
		'status' => session_care_column_contract("enum('aktif','diganti','dibatalkan')", 'no', 'aktif', '', 'utf8mb4', 'utf8mb4_unicode_ci'),
		'assigned_at' => session_care_column_contract('datetime(6)', 'no'),
		'ended_at' => session_care_column_contract('datetime(6)', 'yes', 'null'),
		'created_at' => session_care_column_contract('datetime(6)', 'no', 'current_timestamp(6)'),
		'updated_at' => session_care_column_contract('datetime(6)', 'no', 'current_timestamp(6)', 'on update current_timestamp(6)'),
	);
	foreach ($performer_assignment_columns as $column => $expected) {
		session_care_assert_column_exact($db, $database, 'request_visit_performer_assignments', $column, $expected);
	}
	$indexes = array(
		array('user_login_bindings', 'PRIMARY', 'user_id', 0),
		array('user_login_bindings', 'idx_login_bindings_revoked', 'revoked_at,user_id', 1),
		array('requests', 'idx_requests_responsible_doctor', 'responsible_doctor_user_id', 1),
		array('requests', 'idx_requests_visit_performer', 'visit_performer_user_id', 1),
		array('request_responsible_doctor_assignments', 'PRIMARY', 'responsible_assignment_id', 0),
		array('request_responsible_doctor_assignments', 'idx_responsible_request_status', 'request_id,status,responsible_assignment_id', 1),
		array('request_responsible_doctor_assignments', 'idx_responsible_user_status', 'user_id,status,request_id', 1),
		array('request_responsible_doctor_assignments', 'idx_responsible_staff_status', 'staff_id,status,request_id', 1),
		array('request_visit_performer_assignments', 'PRIMARY', 'visit_assignment_id', 0),
		array('request_visit_performer_assignments', 'idx_visit_performer_request_status', 'request_id,status,visit_assignment_id', 1),
		array('request_visit_performer_assignments', 'idx_visit_performer_user_status', 'user_id,status,request_id', 1),
		array('request_visit_performer_assignments', 'idx_visit_performer_staff_status', 'staff_id,status,request_id', 1),
	);
	foreach ($indexes as $expected) {
		$row = session_care_index($db, $database, $expected[0], $expected[1]);
		if (!$row || (string) $row['columns_list'] !== $expected[2] || (int) $row['NON_UNIQUE'] !== $expected[3]) {
			throw new RuntimeException('target_schema_mismatch');
		}
	}
}

$options = getopt('', array('apply', 'environment:', 'confirm-database:', 'backup-reference:', 'confirm-backup-sha256:', 'confirm-disposable-test:'));
if (!array_key_exists('apply', $options)) {
	echo 'MIGRATION_ID=' . DOCLINC_SESSION_CARE_MIGRATION_ID . "\n";
	echo "EXECUTION_MODE=PLAN\nDATABASE_CONNECTION_OPENED=false\nDDL_EXECUTED=false\nPLANNED_TABLE_COUNT=3\nPLANNED_COLUMN_COUNT=2\nPLANNED_INDEX_COUNT=12\nEXISTING_ROWS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
	exit(0);
}

if (!session_care_true('DOCLINC_SESSION_CARE_SCHEMA_WRITE_ENABLED')) {
	session_care_fail('schema_write_disabled');
}
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
if (!in_array($environment, array('staging', 'test'), true)) {
	session_care_fail('environment_not_allowed');
}
$database = trim((string) (getenv('DOCLINC_SESSION_CARE_SCHEMA_DB_NAME') ?: ''));
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	session_care_fail('database_confirmation_mismatch');
}
if ($environment === 'test') {
	if (!session_care_true('DOCLINC_SESSION_CARE_DISPOSABLE_TEST')
		|| !isset($options['confirm-disposable-test'])
		|| !hash_equals('true', strtolower((string) $options['confirm-disposable-test']))
		|| preg_match('/\Adoclinc_session_care_test_[a-z0-9_]+\z/', strtolower($database)) !== 1) {
		session_care_fail('disposable_test_database_required');
	}
} elseif (!hash_equals('doclinc-staging', $database) || session_care_true('DOCLINC_SESSION_CARE_DISPOSABLE_TEST')) {
	session_care_fail('staging_database_required');
}
if ($environment === 'staging') {
	$backup_reference = trim((string) ($options['backup-reference'] ?? ''));
	$backup_sha256 = strtolower(trim((string) ($options['confirm-backup-sha256'] ?? '')));
	if ($backup_reference === '' || !is_file($backup_reference) || !is_readable($backup_reference)
		|| preg_match('/\A[a-f0-9]{64}\z/', $backup_sha256) !== 1
		|| !hash_equals($backup_sha256, strtolower((string) hash_file('sha256', $backup_reference)))) {
		session_care_fail('backup_confirmation_mismatch');
	}
}

$host = getenv('DOCLINC_SESSION_CARE_SCHEMA_DB_HOST') ?: 'localhost';
$port = (int) (getenv('DOCLINC_SESSION_CARE_SCHEMA_DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_SESSION_CARE_SCHEMA_DB_USER') ?: ''));
$password = getenv('DOCLINC_SESSION_CARE_SCHEMA_DB_PASSWORD');
$allowed_users = array_filter(array_map('trim', explode(',', (string) (getenv('DOCLINC_SESSION_CARE_SCHEMA_ALLOWED_USERS') ?: ''))));
if ($user === '' || !is_string($password) || $password === '' || !in_array($user, $allowed_users, true)) {
	session_care_fail('database_identity_not_allowed');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = null;
$lock_acquired = false;
$ddl_count = 0;
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	$actual = $db->query('SELECT DATABASE() AS database_name')->fetch_assoc();
	if (!$actual || !hash_equals($database, (string) $actual['database_name'])) {
		throw new RuntimeException('connected_database_mismatch');
	}
	$lock = $db->prepare('SELECT GET_LOCK(?, 10) AS acquired');
	$lock_name = DOCLINC_SESSION_CARE_LOCK;
	$lock->bind_param('s', $lock_name);
	$lock->execute();
	$lock_row = $lock->get_result()->fetch_assoc();
	if (!$lock_row || (int) $lock_row['acquired'] !== 1) {
		throw new RuntimeException('migration_lock_unavailable');
	}
	$lock_acquired = true;
	session_care_assert_base($db, $database);
	$present = (session_care_table($db, $database, 'user_login_bindings') ? 1 : 0)
		+ (session_care_table($db, $database, 'request_responsible_doctor_assignments') ? 1 : 0)
		+ (session_care_table($db, $database, 'request_visit_performer_assignments') ? 1 : 0)
		+ (session_care_column($db, $database, 'requests', 'responsible_doctor_user_id') ? 1 : 0)
		+ (session_care_column($db, $database, 'requests', 'visit_performer_user_id') ? 1 : 0);
	if ($present === 5) {
		session_care_assert_target($db, $database);
		echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=false\nDDL_STATEMENT_COUNT=0\nALREADY_APPLIED=true\nEXISTING_ROWS_CHANGED=false\n";
		exit(0);
	}
	if ($present !== 0) {
		throw new RuntimeException('partial_schema_detected');
	}
	$db->query("CREATE TABLE `user_login_bindings` (`user_id` INT(11) NOT NULL, `token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, `issued_at` DATETIME(6) NULL, `revoked_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`user_id`), KEY `idx_login_bindings_revoked` (`revoked_at`, `user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$ddl_count++;
	$db->query("ALTER TABLE `requests` ADD COLUMN `responsible_doctor_user_id` INT(11) NULL AFTER `accepted_by_user_id`, ADD COLUMN `visit_performer_user_id` INT(11) NULL AFTER `responsible_doctor_user_id`, ADD KEY `idx_requests_responsible_doctor` (`responsible_doctor_user_id`), ADD KEY `idx_requests_visit_performer` (`visit_performer_user_id`), ALGORITHM=INPLACE, LOCK=NONE");
	$ddl_count++;
	$db->query("CREATE TABLE `request_responsible_doctor_assignments` (`responsible_assignment_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `request_id` INT(11) NOT NULL, `staff_id` INT(10) UNSIGNED NOT NULL, `user_id` INT(11) NOT NULL, `assigned_by_user_id` INT(11) NOT NULL, `status` ENUM('aktif','diganti') NOT NULL DEFAULT 'aktif', `assigned_at` DATETIME(6) NOT NULL, `ended_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`responsible_assignment_id`), KEY `idx_responsible_request_status` (`request_id`, `status`, `responsible_assignment_id`), KEY `idx_responsible_user_status` (`user_id`, `status`, `request_id`), KEY `idx_responsible_staff_status` (`staff_id`, `status`, `request_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$ddl_count++;
	$db->query("CREATE TABLE `request_visit_performer_assignments` (`visit_assignment_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `request_id` INT(11) NOT NULL, `staff_id` INT(10) UNSIGNED NOT NULL, `user_id` INT(11) NOT NULL, `assigned_by_user_id` INT(11) NOT NULL, `status` ENUM('aktif','diganti','dibatalkan') NOT NULL DEFAULT 'aktif', `assigned_at` DATETIME(6) NOT NULL, `ended_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`visit_assignment_id`), KEY `idx_visit_performer_request_status` (`request_id`, `status`, `visit_assignment_id`), KEY `idx_visit_performer_user_status` (`user_id`, `status`, `request_id`), KEY `idx_visit_performer_staff_status` (`staff_id`, `status`, `request_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$ddl_count++;
	session_care_assert_target($db, $database);
	echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=true\nDDL_STATEMENT_COUNT={$ddl_count}\nALREADY_APPLIED=false\nEXISTING_ROWS_CHANGED=false\n";
} catch (Throwable $exception) {
	$safe = array('connected_database_mismatch', 'migration_lock_unavailable', 'base_schema_mismatch', 'target_schema_mismatch', 'partial_schema_detected');
	$code = in_array($exception->getMessage(), $safe, true) ? $exception->getMessage() : 'migration_failed';
	if ($ddl_count > 0) {
		$code = 'migration_failed_after_ddl';
	}
	session_care_fail($code, $db instanceof mysqli, $ddl_count);
} finally {
	if ($db && $lock_acquired) {
		try {
			$release = $db->prepare('SELECT RELEASE_LOCK(?)');
			$lock_name = DOCLINC_SESSION_CARE_LOCK;
			$release->bind_param('s', $lock_name);
			$release->execute();
		} catch (Throwable $ignored) {
		}
	}
	if ($db) {
		$db->close();
	}
}
