<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');

$options = getopt('', array(
	'apply',
	'confirm-database:',
	'environment:',
	'backup-reference:',
	'confirm-backup-sha256:',
));

const DOCLINC_NAKES_FOUNDATION_MIGRATION = '20260727000300_nakes_import_account_foundation';
const DOCLINC_NAKES_BACKUP_SHA256 = '0a383da6019e4e8f2a217f79cd601aa004783fdd6d16803b459ad0f570102bfa';

function nakes_foundation_fail($code)
{
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE=" . $code . "\n");
	exit(1);
}

function nakes_foundation_env_true($name)
{
	$value = getenv($name);
	return is_string($value) && in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
}

function nakes_foundation_column(mysqli $db, $table, $column)
{
	$stmt = $db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
	$stmt->bind_param('ss', $table, $column);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function nakes_foundation_assert_table(mysqli $db, $table)
{
	$stmt = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
	$stmt->bind_param('s', $table);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	if (!$row || strtoupper((string) $row['ENGINE']) !== 'INNODB') {
		throw new RuntimeException('base_schema_mismatch');
	}
}

function nakes_foundation_assert_base(mysqli $db)
{
	nakes_foundation_assert_table($db, 'users');
	nakes_foundation_assert_table($db, 'puskesmas_staff');
	$required = array(
		array('users', 'userId', 'int(11)', 'NO', 'auto_increment'),
		array('users', 'password', 'varchar(100)', 'NO', ''),
		array('users', 'role', "enum('admin','dokter','warga','')", 'NO', ''),
		array('users', 'status', "enum('aktif','nonaktif')", 'YES', ''),
		array('puskesmas_staff', 'staff_id', 'int(11)', 'NO', 'auto_increment'),
		array('puskesmas_staff', 'kode_pkm', 'varchar(100)', 'NO', ''),
		array('puskesmas_staff', 'profesi', 'varchar(100)', 'YES', ''),
		array('puskesmas_staff', 'user_id', 'int(11)', 'YES', ''),
	);
	foreach ($required as $signature) {
		$column = nakes_foundation_column($db, $signature[0], $signature[1]);
		if (!$column
			|| strtolower((string) $column['COLUMN_TYPE']) !== strtolower($signature[2])
			|| (string) $column['IS_NULLABLE'] !== $signature[3]
			|| strtolower((string) $column['EXTRA']) !== $signature[4]) {
			throw new RuntimeException('base_schema_mismatch');
		}
	}
}

function nakes_foundation_target_state(mysqli $db)
{
	return array(
		'must_change_password' => nakes_foundation_column($db, 'users', 'must_change_password'),
		'password_changed_at' => nakes_foundation_column($db, 'users', 'password_changed_at'),
		'penugasan' => nakes_foundation_column($db, 'puskesmas_staff', 'penugasan'),
	);
}

function nakes_foundation_assert_targets(mysqli $db)
{
	$state = nakes_foundation_target_state($db);
	$must = $state['must_change_password'];
	$changed = $state['password_changed_at'];
	$assignment = $state['penugasan'];
	if (!$must || strtolower((string) $must['COLUMN_TYPE']) !== 'tinyint(1)'
		|| (string) $must['IS_NULLABLE'] !== 'NO' || (string) $must['COLUMN_DEFAULT'] !== '0'
		|| (string) $must['EXTRA'] !== '') {
		throw new RuntimeException('target_schema_signature_mismatch');
	}
	if (!$changed || strtolower((string) $changed['COLUMN_TYPE']) !== 'datetime'
		|| (string) $changed['IS_NULLABLE'] !== 'YES'
		|| !($changed['COLUMN_DEFAULT'] === null || strtoupper((string) $changed['COLUMN_DEFAULT']) === 'NULL')
		|| (string) $changed['EXTRA'] !== '') {
		throw new RuntimeException('target_schema_signature_mismatch');
	}
	if (!$assignment || strtolower((string) $assignment['COLUMN_TYPE']) !== 'varchar(150)'
		|| (string) $assignment['IS_NULLABLE'] !== 'YES'
		|| !($assignment['COLUMN_DEFAULT'] === null || strtoupper((string) $assignment['COLUMN_DEFAULT']) === 'NULL')
		|| (string) $assignment['EXTRA'] !== '') {
		throw new RuntimeException('target_schema_signature_mismatch');
	}
	if ((int) $changed['ORDINAL_POSITION'] !== (int) $must['ORDINAL_POSITION'] + 1) {
		throw new RuntimeException('target_schema_signature_mismatch');
	}
}

function nakes_foundation_assert_grants(mysqli $db, $database, $configured_user)
{
	$current = $db->query('SELECT CURRENT_USER() AS account')->fetch_assoc();
	$account = isset($current['account']) ? (string) $current['account'] : '';
	$separator = strpos($account, '@');
	if ($separator === false || !hash_equals($configured_user, substr($account, 0, $separator))) {
		throw new RuntimeException('schema_writer_identity_mismatch');
	}
	$allowed = array(
		'users' => array('SELECT' => true, 'ALTER' => true),
		'puskesmas_staff' => array('SELECT' => true, 'ALTER' => true),
	);
	$seen = array();
	foreach ($db->query('SHOW GRANTS FOR CURRENT_USER()')->fetch_all(MYSQLI_NUM) as $row) {
		$grant = (string) $row[0];
		if (preg_match('/\AGRANT USAGE ON \*\.\*/i', $grant) === 1) continue;
		if (preg_match('/\AGRANT (.+) ON `?([^`. ]+)`?\.`?([^` ]+)`? TO /i', $grant, $match) !== 1
			|| !hash_equals($database, $match[2]) || !isset($allowed[$match[3]])
			|| stripos($grant, 'GRANT OPTION') !== false) {
			throw new RuntimeException('schema_writer_grants_excessive');
		}
		foreach (array_map('trim', explode(',', $match[1])) as $privilege) {
			$privilege = strtoupper($privilege);
			if (!isset($allowed[$match[3]][$privilege])) throw new RuntimeException('schema_writer_grants_excessive');
			$seen[$match[3]][$privilege] = true;
		}
	}
	foreach ($allowed as $table => $privileges) foreach ($privileges as $privilege => $unused) {
		if (empty($seen[$table][$privilege])) throw new RuntimeException('schema_writer_grants_incomplete');
	}
}

$apply = array_key_exists('apply', $options);
if (!$apply) {
	echo "MIGRATION_ID=" . DOCLINC_NAKES_FOUNDATION_MIGRATION . "\n";
	echo "EXECUTION_MODE=PLAN\n";
	echo "DDL_EXECUTED=false\nEXISTING_ROWS_CHANGED=false\nEXISTING_PASSWORDS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
	exit(0);
}

$database = trim((string) (getenv('DOCLINC_NAKES_SCHEMA_DB_NAME') ?: ''));
$environment = isset($options['environment']) ? strtolower(trim((string) $options['environment'])) : '';
if (!nakes_foundation_env_true('DOCLINC_NAKES_SCHEMA_WRITE_ENABLED')) {
	nakes_foundation_fail('schema_write_disabled');
}
if (!in_array($environment, array('staging', 'uat'), true)) {
	nakes_foundation_fail('environment_not_allowed');
}
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	nakes_foundation_fail('database_confirmation_mismatch');
}
$disposable = nakes_foundation_env_true('DOCLINC_NAKES_DISPOSABLE_TEST');
if ((!$disposable && !hash_equals('doclinc-staging', $database))
	|| ($disposable && preg_match('/(?:^|[_-])test(?:$|[_-])/', strtolower($database)) !== 1)) {
	nakes_foundation_fail($disposable ? 'disposable_test_database_required' : 'staging_database_required');
}
$backup_reference = isset($options['backup-reference']) ? trim((string) $options['backup-reference']) : '';
if ($backup_reference === '' || strlen($backup_reference) > 255 || preg_match('/[\x00-\x1F\x7F]/', $backup_reference) === 1) {
	nakes_foundation_fail('backup_reference_required');
}
if (!$disposable && (!isset($options['confirm-backup-sha256']) || !hash_equals(DOCLINC_NAKES_BACKUP_SHA256, strtolower((string) $options['confirm-backup-sha256'])))) {
	nakes_foundation_fail('backup_checksum_confirmation_mismatch');
}

$host = getenv('DOCLINC_NAKES_SCHEMA_DB_HOST') ?: 'localhost';
$port = (int) (getenv('DOCLINC_NAKES_SCHEMA_DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_NAKES_SCHEMA_DB_USER') ?: ''));
$password = getenv('DOCLINC_NAKES_SCHEMA_DB_PASSWORD');
$allowed_users = array_filter(array_map('trim', explode(',', (string) (getenv('DOCLINC_NAKES_SCHEMA_ALLOWED_USERS') ?: ''))));
if ($user === '' || !is_string($password) || $password === '' || !in_array($user, $allowed_users, true)) {
	nakes_foundation_fail('database_identity_not_allowed');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$lock_name = 'doclinc_nakes_account_foundation_20260727000300';
$lock_acquired = false;
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	$actual = $db->query('SELECT DATABASE() AS database_name')->fetch_assoc();
	if (!$actual || !hash_equals($database, (string) $actual['database_name'])) {
		throw new RuntimeException('connected_database_mismatch');
	}
	nakes_foundation_assert_grants($db, $database, $user);
	$lock = $db->prepare('SELECT GET_LOCK(?, 10) AS acquired');
	$lock->bind_param('s', $lock_name);
	$lock->execute();
	$lock_row = $lock->get_result()->fetch_assoc();
	if (!$lock_row || (int) $lock_row['acquired'] !== 1) {
		throw new RuntimeException('migration_lock_unavailable');
	}
	$lock_acquired = true;

	nakes_foundation_assert_base($db);
	$state = nakes_foundation_target_state($db);
	$present = count(array_filter($state));
	if ($present === 3) {
		nakes_foundation_assert_targets($db);
		echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=false\nALREADY_APPLIED=true\nEXISTING_ROWS_CHANGED=false\nEXISTING_PASSWORDS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
		return;
	}
	if ($present !== 0) {
		throw new RuntimeException('partial_schema_detected');
	}

	$before = $db->query("SELECT COUNT(*) AS rows_total, COALESCE(SUM(CRC32(CONCAT_WS('#',userId,password,status,COALESCE(remark,'')))),0) AS row_digest FROM users")->fetch_assoc();
	$ddl = array(
		"ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`, ADD COLUMN `password_changed_at` DATETIME NULL AFTER `must_change_password`, ALGORITHM=INPLACE, LOCK=NONE",
		"ALTER TABLE `puskesmas_staff` ADD COLUMN `penugasan` VARCHAR(150) NULL AFTER `profesi`, ALGORITHM=INPLACE, LOCK=NONE",
	);
	foreach ($ddl as $statement) {
		$db->query($statement);
	}
	nakes_foundation_assert_targets($db);
	$after = $db->query("SELECT COUNT(*) AS rows_total, COALESCE(SUM(CRC32(CONCAT_WS('#',userId,password,status,COALESCE(remark,'')))),0) AS row_digest FROM users")->fetch_assoc();
	if ($before !== $after) {
		throw new RuntimeException('existing_rows_changed');
	}
	echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=true\nALREADY_APPLIED=false\nEXISTING_ROWS_CHANGED=false\nEXISTING_PASSWORDS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
} catch (Throwable $exception) {
	$safe = array('connected_database_mismatch', 'schema_writer_identity_mismatch', 'schema_writer_grants_excessive', 'schema_writer_grants_incomplete', 'migration_lock_unavailable', 'base_schema_mismatch', 'target_schema_signature_mismatch', 'partial_schema_detected', 'existing_rows_changed');
	$code = in_array($exception->getMessage(), $safe, true) ? $exception->getMessage() : 'migration_execution_failed';
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE=" . $code . "\n");
	exit(1);
} finally {
	$password = null;
	if (isset($db) && $db instanceof mysqli) {
		if ($lock_acquired) {
			try {
				$release = $db->prepare('SELECT RELEASE_LOCK(?)');
				$release->bind_param('s', $lock_name);
				$release->execute();
			} catch (Throwable $ignored) {
				// Connection close releases this session-scoped lock.
			}
		}
		$db->close();
	}
}
