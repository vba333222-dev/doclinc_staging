<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');

const DOCLINC_PHASE6_MIGRATION_ID = '20260813000100_phase6_clinical_visit_foundation';
const DOCLINC_PHASE6_LOCK = 'doclinc_phase6_20260813000100';

function phase6_fail($code, $opened = false, $ddl_count = 0)
{
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$code}\nDATABASE_CONNECTION_OPENED=" . ($opened ? 'true' : 'false')
		. "\nDDL_EXECUTED=" . ($ddl_count > 0 ? 'true' : 'false') . "\nDDL_STATEMENT_COUNT=" . (int) $ddl_count . "\n");
	exit(1);
}

function phase6_true($name)
{
	$value = getenv($name);
	return is_string($value) && in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
}

function phase6_table(mysqli $db, $database, $table)
{
	$stmt = $db->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
	$stmt->bind_param('ss', $database, $table);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function phase6_column(mysqli $db, $database, $table, $column)
{
	$stmt = $db->prepare('SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
	$stmt->bind_param('sss', $database, $table, $column);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function phase6_index(mysqli $db, $database, $table, $index)
{
	$stmt = $db->prepare('SELECT NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? GROUP BY NON_UNIQUE');
	$stmt->bind_param('sss', $database, $table, $index);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function phase6_normalize($value)
{
	return $value === null ? null : strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
}

function phase6_default($value)
{
	$value = phase6_normalize($value);
	if (is_string($value) && strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") {
		return str_replace("''", "'", substr($value, 1, -1));
	}
	return $value;
}

function phase6_assert_column(mysqli $db, $database, $table, $column, $type, $nullable, $default = null, $extra = '', $charset = null, $collation = null)
{
	$row = phase6_column($db, $database, $table, $column);
	if (!$row
		|| phase6_normalize($row['COLUMN_TYPE']) !== phase6_normalize($type)
		|| phase6_normalize($row['IS_NULLABLE']) !== phase6_normalize($nullable)
		|| phase6_default($row['COLUMN_DEFAULT']) !== phase6_default($default)
		|| phase6_normalize($row['EXTRA']) !== phase6_normalize($extra)
		|| phase6_normalize($row['CHARACTER_SET_NAME']) !== phase6_normalize($charset)
		|| phase6_normalize($row['COLLATION_NAME']) !== phase6_normalize($collation)) {
		throw new RuntimeException('target_schema_mismatch');
	}
}

function phase6_assert_index(mysqli $db, $database, $table, $index, $columns, $non_unique)
{
	$row = phase6_index($db, $database, $table, $index);
	if (!$row || (string) $row['columns_list'] !== (string) $columns || (int) $row['NON_UNIQUE'] !== (int) $non_unique) {
		throw new RuntimeException('target_schema_mismatch');
	}
}

function phase6_assert_target(mysqli $db, $database)
{
	$table = phase6_table($db, $database, 'request_vital_sign_measurements');
	if (!$table || strtoupper((string) $table['ENGINE']) !== 'INNODB' || strtolower((string) $table['TABLE_COLLATION']) !== 'utf8mb4_unicode_ci') {
		throw new RuntimeException('target_schema_mismatch');
	}
	$columns = array(
		array('measurement_id', 'bigint(20) unsigned', 'no', null, 'auto_increment'),
		array('request_id', 'int(11)', 'no'),
		array('measured_by_user_id', 'int(11)', 'no'),
		array('measured_by_staff_id', 'int(10) unsigned', 'no'),
		array('responsible_doctor_user_id', 'int(11)', 'no'),
		array('visit_performer_user_id', 'int(11)', 'no'),
		array('systolic', 'smallint(5) unsigned', 'yes', 'null'),
		array('diastolic', 'smallint(5) unsigned', 'yes', 'null'),
		array('pulse', 'smallint(5) unsigned', 'yes', 'null'),
		array('respiratory_rate', 'smallint(5) unsigned', 'yes', 'null'),
		array('temperature_c', 'decimal(4,1) unsigned', 'yes', 'null'),
		array('oxygen_saturation', 'tinyint(3) unsigned', 'yes', 'null'),
		array('notes', 'varchar(1000)', 'yes', 'null', '', 'utf8mb4', 'utf8mb4_unicode_ci'),
		array('measured_at', 'datetime(6)', 'no'),
		array('created_at', 'datetime(6)', 'no', 'current_timestamp(6)'),
	);
	foreach ($columns as $column) {
		phase6_assert_column($db, $database, 'request_vital_sign_measurements', ...$column);
	}
	phase6_assert_column($db, $database, 'medicalrecords', 'responsible_doctor_user_id', 'int(11)', 'yes', 'null');
	phase6_assert_column($db, $database, 'medicalrecords', 'recorded_by_user_id', 'int(11)', 'yes', 'null');
	$indexes = array(
		array('request_vital_sign_measurements', 'PRIMARY', 'measurement_id', 0),
		array('request_vital_sign_measurements', 'idx_vital_request_measured', 'request_id,measured_at,measurement_id', 1),
		array('request_vital_sign_measurements', 'idx_vital_performer_measured', 'visit_performer_user_id,measured_at,measurement_id', 1),
		array('medicalrecords', 'idx_medical_responsible_doctor', 'responsible_doctor_user_id,request_id', 1),
		array('medicalrecords', 'idx_medical_recorded_by', 'recorded_by_user_id,request_id', 1),
	);
	foreach ($indexes as $index) {
		phase6_assert_index($db, $database, ...$index);
	}
}

$options = getopt('', array('apply', 'environment:', 'confirm-database:', 'backup-reference:', 'confirm-backup-sha256:', 'confirm-disposable-test:'));
if (!array_key_exists('apply', $options)) {
	echo 'MIGRATION_ID=' . DOCLINC_PHASE6_MIGRATION_ID . "\nEXECUTION_MODE=PLAN\nDATABASE_CONNECTION_OPENED=false\nDDL_EXECUTED=false\nPLANNED_TABLE_COUNT=1\nPLANNED_COLUMN_COUNT=2\nPLANNED_INDEX_COUNT=5\nEXISTING_ROWS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
	exit(0);
}
if (!phase6_true('DOCLINC_PHASE6_SCHEMA_WRITE_ENABLED')) {
	phase6_fail('schema_write_disabled');
}
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
if (!in_array($environment, array('staging', 'test'), true)) {
	phase6_fail('environment_not_allowed');
}
$database = trim((string) (getenv('DOCLINC_PHASE6_SCHEMA_DB_NAME') ?: ''));
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	phase6_fail('database_confirmation_mismatch');
}
if ($environment === 'test') {
	if (!phase6_true('DOCLINC_PHASE6_DISPOSABLE_TEST')
		|| !isset($options['confirm-disposable-test'])
		|| !hash_equals('true', strtolower((string) $options['confirm-disposable-test']))
		|| preg_match('/\Adoclinc_phase6_test_[a-z0-9_]+\z/', strtolower($database)) !== 1) {
		phase6_fail('disposable_test_database_required');
	}
} elseif (!hash_equals('doclinc-staging', $database) || phase6_true('DOCLINC_PHASE6_DISPOSABLE_TEST')) {
	phase6_fail('staging_database_required');
}
if ($environment === 'staging') {
	$backup = trim((string) ($options['backup-reference'] ?? ''));
	$sha = strtolower(trim((string) ($options['confirm-backup-sha256'] ?? '')));
	if ($backup === '' || !is_file($backup) || !is_readable($backup) || preg_match('/\A[a-f0-9]{64}\z/', $sha) !== 1
		|| !hash_equals($sha, strtolower((string) hash_file('sha256', $backup)))) {
		phase6_fail('backup_confirmation_mismatch');
	}
}
$host = getenv('DOCLINC_PHASE6_SCHEMA_DB_HOST') ?: 'localhost';
$port = (int) (getenv('DOCLINC_PHASE6_SCHEMA_DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_PHASE6_SCHEMA_DB_USER') ?: ''));
$password = getenv('DOCLINC_PHASE6_SCHEMA_DB_PASSWORD');
$allowed = array_filter(array_map('trim', explode(',', (string) (getenv('DOCLINC_PHASE6_SCHEMA_ALLOWED_USERS') ?: ''))));
if ($user === '' || !is_string($password) || $password === '' || !in_array($user, $allowed, true)) {
	phase6_fail('database_identity_not_allowed');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = null;
$lock = false;
$ddl_count = 0;
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	$lock_stmt = $db->prepare('SELECT GET_LOCK(?, 10)');
	$lock_name = DOCLINC_PHASE6_LOCK;
	$lock_stmt->bind_param('s', $lock_name);
	$lock_stmt->execute();
	$lock = (int) $lock_stmt->get_result()->fetch_row()[0] === 1;
	if (!$lock) {
		throw new RuntimeException('migration_lock_unavailable');
	}
	foreach (array('requests', 'medicalrecords', 'puskesmas_staff', 'users') as $table_name) {
		if (!phase6_table($db, $database, $table_name)) {
			throw new RuntimeException('base_schema_mismatch');
		}
	}
	foreach (array(
		array('requests', 'request_id'),
		array('medicalrecords', 'record_id'),
		array('medicalrecords', 'request_id'),
		array('puskesmas_staff', 'staff_id'),
		array('users', 'userId'),
	) as $base_column) {
		if (!phase6_column($db, $database, $base_column[0], $base_column[1])) {
			throw new RuntimeException('base_schema_mismatch');
		}
	}
	$target_present = array(
		phase6_table($db, $database, 'request_vital_sign_measurements') !== null,
		phase6_column($db, $database, 'medicalrecords', 'responsible_doctor_user_id') !== null,
		phase6_column($db, $database, 'medicalrecords', 'recorded_by_user_id') !== null,
	);
	$present_count = count(array_filter($target_present));
	if ($present_count > 0 && $present_count < count($target_present)) {
		throw new RuntimeException('partial_schema_detected');
	}
	if ($present_count === 0 && (phase6_index($db, $database, 'medicalrecords', 'idx_medical_responsible_doctor')
		|| phase6_index($db, $database, 'medicalrecords', 'idx_medical_recorded_by'))) {
		throw new RuntimeException('target_schema_mismatch');
	}
	if ($present_count === count($target_present)) {
		phase6_assert_target($db, $database);
		echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nALREADY_APPLIED=true\nDATABASE_CONNECTION_OPENED=true\nDDL_EXECUTED=false\nDDL_STATEMENT_COUNT=0\n";
	} else {
		$db->query("CREATE TABLE request_vital_sign_measurements (measurement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,request_id INT(11) NOT NULL,measured_by_user_id INT(11) NOT NULL,measured_by_staff_id INT(10) UNSIGNED NOT NULL,responsible_doctor_user_id INT(11) NOT NULL,visit_performer_user_id INT(11) NOT NULL,systolic SMALLINT UNSIGNED NULL,diastolic SMALLINT UNSIGNED NULL,pulse SMALLINT UNSIGNED NULL,respiratory_rate SMALLINT UNSIGNED NULL,temperature_c DECIMAL(4,1) UNSIGNED NULL,oxygen_saturation TINYINT UNSIGNED NULL,notes VARCHAR(1000) NULL,measured_at DATETIME(6) NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),PRIMARY KEY(measurement_id),KEY idx_vital_request_measured(request_id,measured_at,measurement_id),KEY idx_vital_performer_measured(visit_performer_user_id,measured_at,measurement_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$ddl_count++;
		$db->query('ALTER TABLE medicalrecords ADD COLUMN responsible_doctor_user_id INT(11) NULL,ADD COLUMN recorded_by_user_id INT(11) NULL,ADD KEY idx_medical_responsible_doctor(responsible_doctor_user_id,request_id),ADD KEY idx_medical_recorded_by(recorded_by_user_id,request_id),ALGORITHM=INPLACE,LOCK=NONE');
		$ddl_count++;
		phase6_assert_target($db, $database);
		echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nALREADY_APPLIED=false\nDATABASE_CONNECTION_OPENED=true\nDDL_EXECUTED=true\nDDL_STATEMENT_COUNT={$ddl_count}\n";
	}
} catch (Throwable $exception) {
	if ($lock && $db) {
		$db->query("SELECT RELEASE_LOCK('" . $db->real_escape_string(DOCLINC_PHASE6_LOCK) . "')");
	}
	if ($db) {
		$db->close();
	}
	phase6_fail(preg_match('/\A[a-z0-9_]+\z/', $exception->getMessage()) ? $exception->getMessage() : 'migration_failed', $db !== null, $ddl_count);
}
if ($lock && $db) {
	$db->query("SELECT RELEASE_LOCK('" . $db->real_escape_string(DOCLINC_PHASE6_LOCK) . "')");
}
if ($db) {
	$db->close();
}
