<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');

const DOCLINC_NAKES_PROFILE_V2_MIGRATION_ID = '20260810000100_nakes_profile_v2_foundation';
const DOCLINC_NAKES_PROFILE_V2_LOCK = 'doclinc_nakes_profile_v2_20260810000100';

function nakes_profile_v2_fail($code)
{
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$code}\n");
	exit(1);
}

function nakes_profile_v2_env_true($name)
{
	$value = getenv($name);
	return is_string($value) && in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
}

function nakes_profile_v2_column(mysqli $db, $database, $column)
{
	$stmt = $db->prepare('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
	$table = 'puskesmas_staff';
	$stmt->bind_param('sss', $database, $table, $column);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function nakes_profile_v2_assert_base(mysqli $db, $database)
{
	$stmt = $db->prepare('SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
	$table = 'puskesmas_staff';
	$column = 'staff_id';
	$stmt->bind_param('sss', $database, $table, $column);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	if (!$row || (int) $row['total'] !== 1) {
		throw new RuntimeException('base_schema_mismatch');
	}
}

function nakes_profile_v2_assert_target(mysqli $db, $database)
{
	$gelar = nakes_profile_v2_column($db, $database, 'gelar');
	$expiry = nakes_profile_v2_column($db, $database, 'sip_expired_at');
	$null_default = static function ($value) {
		return $value === null || strtoupper(trim((string) $value)) === 'NULL';
	};
	if (!$gelar
		|| strtolower((string) $gelar['COLUMN_TYPE']) !== 'varchar(100)'
		|| (string) $gelar['IS_NULLABLE'] !== 'YES'
		|| !$null_default($gelar['COLUMN_DEFAULT'])
		|| strtolower((string) $gelar['CHARACTER_SET_NAME']) !== 'utf8mb4'
		|| strtolower((string) $gelar['COLLATION_NAME']) !== 'utf8mb4_unicode_ci') {
		throw new RuntimeException('target_column_mismatch');
	}
	if (!$expiry
		|| strtolower((string) $expiry['COLUMN_TYPE']) !== 'date'
		|| (string) $expiry['IS_NULLABLE'] !== 'YES'
		|| !$null_default($expiry['COLUMN_DEFAULT'])) {
		throw new RuntimeException('target_column_mismatch');
	}
}

$options = getopt('', array('apply', 'environment:', 'confirm-database:', 'backup-reference:', 'confirm-backup-sha256:', 'confirm-disposable-test:'));
if (!array_key_exists('apply', $options)) {
	echo 'MIGRATION_ID=' . DOCLINC_NAKES_PROFILE_V2_MIGRATION_ID . "\n";
	echo "EXECUTION_MODE=PLAN\nDATABASE_CONNECTION_OPENED=false\nDDL_EXECUTED=false\nPLANNED_COLUMN_COUNT=2\nEXISTING_ROWS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
	exit(0);
}

if (!nakes_profile_v2_env_true('DOCLINC_NAKES_PROFILE_V2_SCHEMA_WRITE_ENABLED')) {
	nakes_profile_v2_fail('schema_write_disabled');
}
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
if (!in_array($environment, array('staging', 'test'), true)) {
	nakes_profile_v2_fail('environment_not_allowed');
}
$database = trim((string) (getenv('DOCLINC_NAKES_PROFILE_V2_SCHEMA_DB_NAME') ?: ''));
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	nakes_profile_v2_fail('database_confirmation_mismatch');
}
if ($environment === 'test') {
	if (!nakes_profile_v2_env_true('DOCLINC_NAKES_PROFILE_V2_DISPOSABLE_TEST')
		|| !isset($options['confirm-disposable-test'])
		|| !hash_equals('true', strtolower((string) $options['confirm-disposable-test']))
		|| preg_match('/\Adoclinc_nakes_profile_v2_test_[a-z0-9_]+\z/', strtolower($database)) !== 1) {
		nakes_profile_v2_fail('disposable_test_database_required');
	}
} elseif (!hash_equals('doclinc-staging', $database) || nakes_profile_v2_env_true('DOCLINC_NAKES_PROFILE_V2_DISPOSABLE_TEST')) {
	nakes_profile_v2_fail('staging_database_required');
}
$backup_reference = trim((string) ($options['backup-reference'] ?? ''));
$backup_sha256 = strtolower(trim((string) ($options['confirm-backup-sha256'] ?? '')));
if ($backup_reference === '' || !is_file($backup_reference) || !is_readable($backup_reference)
	|| preg_match('/\A[a-f0-9]{64}\z/', $backup_sha256) !== 1
	|| !hash_equals($backup_sha256, strtolower((string) hash_file('sha256', $backup_reference)))) {
	nakes_profile_v2_fail('backup_confirmation_mismatch');
}

$host = getenv('DOCLINC_NAKES_PROFILE_V2_SCHEMA_DB_HOST') ?: 'localhost';
$port = (int) (getenv('DOCLINC_NAKES_PROFILE_V2_SCHEMA_DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_NAKES_PROFILE_V2_SCHEMA_DB_USER') ?: ''));
$password = getenv('DOCLINC_NAKES_PROFILE_V2_SCHEMA_DB_PASSWORD');
$allowed_users = array_filter(array_map('trim', explode(',', (string) (getenv('DOCLINC_NAKES_PROFILE_V2_SCHEMA_ALLOWED_USERS') ?: ''))));
if ($user === '' || !is_string($password) || $password === '' || !in_array($user, $allowed_users, true)) {
	nakes_profile_v2_fail('database_identity_not_allowed');
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
	$lock_name = DOCLINC_NAKES_PROFILE_V2_LOCK;
	$lock->bind_param('s', $lock_name);
	$lock->execute();
	$lock_row = $lock->get_result()->fetch_assoc();
	if (!$lock_row || (int) $lock_row['acquired'] !== 1) {
		throw new RuntimeException('migration_lock_unavailable');
	}
	$lock_acquired = true;
	nakes_profile_v2_assert_base($db, $database);
	$present = (nakes_profile_v2_column($db, $database, 'gelar') ? 1 : 0)
		+ (nakes_profile_v2_column($db, $database, 'sip_expired_at') ? 1 : 0);
	if ($present === 2) {
		nakes_profile_v2_assert_target($db, $database);
		echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=false\nDDL_STATEMENT_COUNT=0\nALREADY_APPLIED=true\nEXISTING_ROWS_CHANGED=false\n";
		exit(0);
	}
	if ($present !== 0) {
		throw new RuntimeException('partial_schema_detected');
	}
	$db->query("ALTER TABLE `puskesmas_staff` ADD COLUMN `gelar` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `nama`, ADD COLUMN `sip_expired_at` DATE NULL AFTER `nomor_sip`, ALGORITHM=INPLACE, LOCK=NONE");
	$ddl_count++;
	nakes_profile_v2_assert_target($db, $database);
	echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=true\nDDL_STATEMENT_COUNT=1\nALREADY_APPLIED=false\nEXISTING_ROWS_CHANGED=false\n";
} catch (Throwable $exception) {
	$safe = array('connected_database_mismatch', 'migration_lock_unavailable', 'base_schema_mismatch', 'target_column_mismatch', 'partial_schema_detected');
	$code = in_array($exception->getMessage(), $safe, true) ? $exception->getMessage() : 'migration_failed';
	if ($ddl_count > 0) {
		$code = 'migration_failed_after_ddl';
	}
	nakes_profile_v2_fail($code);
} finally {
	if ($db && $lock_acquired) {
		try {
			$release = $db->prepare('SELECT RELEASE_LOCK(?)');
			$lock_name = DOCLINC_NAKES_PROFILE_V2_LOCK;
			$release->bind_param('s', $lock_name);
			$release->execute();
		} catch (Throwable $ignored) {
		}
	}
	if ($db) {
		$db->close();
	}
}
