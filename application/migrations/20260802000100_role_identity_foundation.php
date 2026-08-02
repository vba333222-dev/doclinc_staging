<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');

const DOCLINC_ROLE_IDENTITY_MIGRATION_ID = '20260802000100_role_identity_foundation';
const DOCLINC_ROLE_IDENTITY_LOCK = 'doclinc_role_identity_foundation_20260802000100';

function role_identity_fail($code)
{
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE=" . $code . "\n");
	exit(1);
}

function role_identity_env_true($name)
{
	$value = getenv($name);
	return is_string($value) && in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
}

function role_identity_columns()
{
	return array(
		'users' => array(
			'nik' => array('char(16)', 'ascii', 'ascii_bin'),
			'nomor_kk' => array('char(16)', 'ascii', 'ascii_bin'),
			'nomor_bpjs_kis' => array('varchar(13)', 'ascii', 'ascii_bin'),
		),
		'puskesmas_staff' => array(
			'nip' => array('char(18)', 'ascii', 'ascii_bin'),
		),
	);
}

function role_identity_indexes()
{
	return array(
		'users' => array(
			'uq_users_nik' => array('nik'),
			'uq_users_nomor_bpjs_kis' => array('nomor_bpjs_kis'),
		),
		'puskesmas_staff' => array(
			'uq_puskesmas_staff_nip' => array('nip'),
		),
	);
}

function role_identity_column_state(mysqli $db, $database, $table, $column)
{
	$stmt = $db->prepare('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
	$stmt->bind_param('sss', $database, $table, $column);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function role_identity_index_state(mysqli $db, $database, $table, $index)
{
	$stmt = $db->prepare('SELECT NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX');
	$stmt->bind_param('sss', $database, $table, $index);
	$stmt->execute();
	return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function role_identity_assert_base(mysqli $db, $database)
{
	foreach (array('users' => 'userId', 'puskesmas_staff' => 'staff_id') as $table => $primary) {
		$stmt = $db->prepare('SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
		$stmt->bind_param('sss', $database, $table, $primary);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		if (!$row || (int) $row['total'] !== 1) {
			throw new RuntimeException('base_schema_mismatch');
		}
	}
}

function role_identity_assert_target(mysqli $db, $database)
{
	foreach (role_identity_columns() as $table => $columns) {
		foreach ($columns as $name => $expected) {
			$actual = role_identity_column_state($db, $database, $table, $name);
			if (!$actual
				|| strtolower((string) $actual['COLUMN_TYPE']) !== $expected[0]
				|| (string) $actual['IS_NULLABLE'] !== 'YES'
				|| $actual['COLUMN_DEFAULT'] !== null
				|| strtolower((string) $actual['CHARACTER_SET_NAME']) !== $expected[1]
				|| strtolower((string) $actual['COLLATION_NAME']) !== $expected[2]) {
				throw new RuntimeException('target_column_mismatch');
			}
		}
	}
	foreach (role_identity_indexes() as $table => $indexes) {
		foreach ($indexes as $name => $columns) {
			$actual = role_identity_index_state($db, $database, $table, $name);
			$actual_columns = array_column($actual, 'COLUMN_NAME');
			if (count($actual) !== count($columns) || (int) $actual[0]['NON_UNIQUE'] !== 0 || $actual_columns !== $columns) {
				throw new RuntimeException('target_index_mismatch');
			}
		}
	}
}

$options = getopt('', array('apply', 'environment:', 'confirm-database:', 'backup-reference:', 'confirm-backup-sha256:', 'confirm-disposable-test:'));
if (!array_key_exists('apply', $options)) {
	echo "MIGRATION_ID=" . DOCLINC_ROLE_IDENTITY_MIGRATION_ID . "\n";
	echo "EXECUTION_MODE=PLAN\nDATABASE_CONNECTION_OPENED=false\nDDL_EXECUTED=false\n";
	echo "PLANNED_COLUMN_COUNT=4\nPLANNED_UNIQUE_INDEX_COUNT=3\nEXISTING_ROWS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
	exit(0);
}

if (!role_identity_env_true('DOCLINC_ROLE_IDENTITY_SCHEMA_WRITE_ENABLED')) {
	role_identity_fail('schema_write_disabled');
}
$environment = isset($options['environment']) ? strtolower(trim((string) $options['environment'])) : '';
if (!in_array($environment, array('staging', 'test'), true)) {
	role_identity_fail('environment_not_allowed');
}
$database = trim((string) (getenv('DOCLINC_ROLE_IDENTITY_SCHEMA_DB_NAME') ?: ''));
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	role_identity_fail('database_confirmation_mismatch');
}
if ($environment === 'test') {
	if (!role_identity_env_true('DOCLINC_ROLE_IDENTITY_DISPOSABLE_TEST')
		|| !isset($options['confirm-disposable-test'])
		|| !hash_equals('true', strtolower((string) $options['confirm-disposable-test']))
		|| preg_match('/\Adoclinc_role_identity_test_[a-z0-9_]+\z/', strtolower($database)) !== 1) {
		role_identity_fail('disposable_test_database_required');
	}
} elseif (!hash_equals('doclinc-staging', $database) || role_identity_env_true('DOCLINC_ROLE_IDENTITY_DISPOSABLE_TEST')) {
	role_identity_fail('staging_database_required');
}
$backup_reference = isset($options['backup-reference']) ? trim((string) $options['backup-reference']) : '';
$backup_sha256 = isset($options['confirm-backup-sha256']) ? strtolower(trim((string) $options['confirm-backup-sha256'])) : '';
if ($backup_reference === '' || strlen($backup_reference) > 255 || preg_match('/[\x00-\x1f\x7f]/', $backup_reference) === 1) {
	role_identity_fail('backup_reference_required');
}
if (preg_match('/\A[a-f0-9]{64}\z/', $backup_sha256) !== 1) {
	role_identity_fail('backup_checksum_confirmation_mismatch');
}
if (!is_file($backup_reference) || !is_readable($backup_reference)) {
	role_identity_fail('backup_reference_unavailable');
}
$actual_backup_sha256 = strtolower((string) hash_file('sha256', $backup_reference));
if (!hash_equals($backup_sha256, $actual_backup_sha256)) {
	role_identity_fail('backup_checksum_confirmation_mismatch');
}

$host = getenv('DOCLINC_ROLE_IDENTITY_SCHEMA_DB_HOST') ?: 'localhost';
$port = (int) (getenv('DOCLINC_ROLE_IDENTITY_SCHEMA_DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_ROLE_IDENTITY_SCHEMA_DB_USER') ?: ''));
$password = getenv('DOCLINC_ROLE_IDENTITY_SCHEMA_DB_PASSWORD');
$allowed_users = array_filter(array_map('trim', explode(',', (string) (getenv('DOCLINC_ROLE_IDENTITY_SCHEMA_ALLOWED_USERS') ?: ''))));
if ($user === '' || !is_string($password) || $password === '' || !in_array($user, $allowed_users, true)) {
	role_identity_fail('database_identity_not_allowed');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$lock_acquired = false;
$db = null;
$ddl_count = 0;
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	$actual_database = $db->query('SELECT DATABASE() AS database_name')->fetch_assoc();
	if (!$actual_database || !hash_equals($database, (string) $actual_database['database_name'])) {
		throw new RuntimeException('connected_database_mismatch');
	}
	$lock = $db->prepare('SELECT GET_LOCK(?, 10) AS acquired');
	$lock_name = DOCLINC_ROLE_IDENTITY_LOCK;
	$lock->bind_param('s', $lock_name);
	$lock->execute();
	$lock_row = $lock->get_result()->fetch_assoc();
	if (!$lock_row || (int) $lock_row['acquired'] !== 1) {
		throw new RuntimeException('migration_lock_unavailable');
	}
	$lock_acquired = true;
	role_identity_assert_base($db, $database);

	$present = 0;
	foreach (role_identity_columns() as $table => $columns) {
		foreach (array_keys($columns) as $column) {
			$present += role_identity_column_state($db, $database, $table, $column) ? 1 : 0;
		}
	}
	foreach (role_identity_indexes() as $table => $indexes) {
		foreach (array_keys($indexes) as $index) {
			$present += role_identity_index_state($db, $database, $table, $index) ? 1 : 0;
		}
	}
	if ($present === 7) {
		role_identity_assert_target($db, $database);
		echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=false\nDDL_STATEMENT_COUNT=0\nALREADY_APPLIED=true\nEXISTING_ROWS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
		exit(0);
	}
	if ($present !== 0) {
		throw new RuntimeException('partial_schema_detected');
	}

	$db->query("ALTER TABLE `users`
		ADD COLUMN `nik` CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
		ADD COLUMN `nomor_kk` CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
		ADD COLUMN `nomor_bpjs_kis` VARCHAR(13) CHARACTER SET ascii COLLATE ascii_bin NULL,
		ADD UNIQUE KEY `uq_users_nik` (`nik`),
		ADD UNIQUE KEY `uq_users_nomor_bpjs_kis` (`nomor_bpjs_kis`)");
	$ddl_count++;
	$db->query("ALTER TABLE `puskesmas_staff`
		ADD COLUMN `nip` CHAR(18) CHARACTER SET ascii COLLATE ascii_bin NULL,
		ADD UNIQUE KEY `uq_puskesmas_staff_nip` (`nip`)");
	$ddl_count++;
	role_identity_assert_target($db, $database);
	echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=true\nDDL_STATEMENT_COUNT=" . $ddl_count . "\nALREADY_APPLIED=false\nEXISTING_ROWS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
} catch (Throwable $exception) {
	$safe = array('connected_database_mismatch', 'migration_lock_unavailable', 'base_schema_mismatch', 'target_column_mismatch', 'target_index_mismatch', 'partial_schema_detected');
	$code = in_array($exception->getMessage(), $safe, true) ? $exception->getMessage() : 'migration_failed';
	if ($db && $ddl_count > 0) {
		$code = 'migration_failed_after_ddl';
	}
	role_identity_fail($code);
} finally {
	if ($db && $lock_acquired) {
		try {
			$release = $db->prepare('SELECT RELEASE_LOCK(?)');
			$lock_name = DOCLINC_ROLE_IDENTITY_LOCK;
			$release->bind_param('s', $lock_name);
			$release->execute();
		} catch (Throwable $ignored) {
		}
	}
	if ($db) {
		$db->close();
	}
}
