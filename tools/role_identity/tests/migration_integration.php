<?php
require_once dirname(__DIR__, 2) . '/realtime_requests/tests/MariaDbReadiness.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$passed = 0;
$failed = 0;
$databases = array();
$connections = array();
$admin = null;
$backup_file = '';
$cleanup_failed = false;

function identity_integration_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; fwrite(STDERR, "FAIL {$label}\n");
}

function identity_integration_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function identity_integration_identifier($value)
{
	if (!is_string($value) || preg_match('/\A[a-zA-Z0-9_]+\z/', $value) !== 1) {
		throw new InvalidArgumentException('unsafe_test_identifier');
	}
	return '`' . $value . '`';
}

function identity_integration_database(mysqli $admin, $suffix)
{
	global $databases, $connections;
	$name = 'doclinc_role_identity_test_' . $suffix . '_' . bin2hex(random_bytes(4));
	$admin->query('CREATE DATABASE ' . identity_integration_identifier($name) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$databases[] = $name;
	$db = new mysqli(
		identity_integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1'),
		identity_integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root'),
		identity_integration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD'),
		$name,
		(int) identity_integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306')
	);
	$db->set_charset('utf8mb4');
	$connections[] = $db;
	$db->query("CREATE TABLE users (
		userId int(11) NOT NULL AUTO_INCREMENT,
		nama varchar(100) NOT NULL,
		email varchar(100) NOT NULL,
		role enum('admin','dokter','warga') NOT NULL,
		status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
		PRIMARY KEY (userId), UNIQUE KEY uq_users_email (email)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$db->query("CREATE TABLE puskesmas_staff (
		staff_id int(10) unsigned NOT NULL AUTO_INCREMENT,
		kode_pkm varchar(100) NOT NULL,
		nama varchar(100) NOT NULL,
		user_id int(11) DEFAULT NULL,
		status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
		PRIMARY KEY (staff_id), KEY idx_staff_user (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$db->query("INSERT INTO users (nama,email,role,status) VALUES
		('Fixture Warga','fixture-warga@example.test','warga','aktif'),
		('Fixture Nakes','fixture-nakes@example.test','dokter','aktif')");
	$db->query("INSERT INTO puskesmas_staff (kode_pkm,nama,user_id,status) VALUES
		('FIXTURE01','Fixture Staff',2,'aktif')");
	return array($name, $db);
}

function identity_integration_snapshot(mysqli $db)
{
	$users = $db->query('SELECT userId,nama,email,role,status FROM users ORDER BY userId')->fetch_all(MYSQLI_ASSOC);
	$staff = $db->query('SELECT staff_id,kode_pkm,nama,user_id,status FROM puskesmas_staff ORDER BY staff_id')->fetch_all(MYSQLI_ASSOC);
	return hash('sha256', json_encode(array($users, $staff), JSON_UNESCAPED_SLASHES));
}

function identity_integration_run($database, $backup_file, $backup_sha)
{
	$migration = dirname(__DIR__, 3) . '/application/migrations/20260802000100_role_identity_foundation.php';
	$command = array(
		PHP_BINARY, $migration, '--apply', '--environment=test', '--confirm-database=' . $database,
		'--backup-reference=' . $backup_file, '--confirm-backup-sha256=' . $backup_sha,
		'--confirm-disposable-test=true',
	);
	$environment = array_merge(getenv(), array(
		'DOCLINC_ROLE_IDENTITY_SCHEMA_WRITE_ENABLED' => 'true',
		'DOCLINC_ROLE_IDENTITY_DISPOSABLE_TEST' => 'true',
		'DOCLINC_ROLE_IDENTITY_SCHEMA_DB_HOST' => identity_integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1'),
		'DOCLINC_ROLE_IDENTITY_SCHEMA_DB_PORT' => identity_integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306'),
		'DOCLINC_ROLE_IDENTITY_SCHEMA_DB_NAME' => $database,
		'DOCLINC_ROLE_IDENTITY_SCHEMA_DB_USER' => identity_integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root'),
		'DOCLINC_ROLE_IDENTITY_SCHEMA_DB_PASSWORD' => identity_integration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD'),
		'DOCLINC_ROLE_IDENTITY_SCHEMA_ALLOWED_USERS' => identity_integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root'),
	));
	$descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 3), $environment);
	if (!is_resource($process)) { throw new RuntimeException('migration_process_start_failed'); }
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	return array('exit' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr);
}

function identity_integration_schema_ready(mysqli $db)
{
	$columns = array(
		array('users', 'nik', 'char(16)', 'ascii', 'ascii_bin'),
		array('users', 'nomor_kk', 'char(16)', 'ascii', 'ascii_bin'),
		array('users', 'nomor_bpjs_kis', 'varchar(13)', 'ascii', 'ascii_bin'),
		array('puskesmas_staff', 'nip', 'char(18)', 'ascii', 'ascii_bin'),
	);
	foreach ($columns as $expected) {
		$stmt = $db->prepare('SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
		$stmt->bind_param('ss', $expected[0], $expected[1]); $stmt->execute();
		$row = $stmt->get_result()->fetch_assoc(); $stmt->close();
		if (!$row || strtolower((string) $row['COLUMN_TYPE']) !== $expected[2]
			|| (string) $row['IS_NULLABLE'] !== 'YES' || $row['COLUMN_DEFAULT'] !== null
			|| strtolower((string) $row['CHARACTER_SET_NAME']) !== $expected[3]
			|| strtolower((string) $row['COLLATION_NAME']) !== $expected[4]) { return false; }
	}
	$indexes = array(
		array('users', 'uq_users_nik', 'nik'),
		array('users', 'uq_users_nomor_bpjs_kis', 'nomor_bpjs_kis'),
		array('puskesmas_staff', 'uq_puskesmas_staff_nip', 'nip'),
	);
	foreach ($indexes as $expected) {
		$stmt = $db->prepare('SELECT NON_UNIQUE,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX');
		$stmt->bind_param('ss', $expected[0], $expected[1]); $stmt->execute();
		$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
		if (count($rows) !== 1 || (int) $rows[0]['NON_UNIQUE'] !== 0 || (string) $rows[0]['COLUMN_NAME'] !== $expected[2]) { return false; }
	}
	return true;
}

$password = identity_integration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($password === '') {
	fwrite(STDERR, "ROLE_IDENTITY_MIGRATION_INTEGRATION=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n");
	exit(2);
}

try {
	$readiness = MariaDbReadiness::wait(array(
		'host' => identity_integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1'),
		'port' => (int) identity_integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306'),
		'user' => identity_integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root'),
		'password' => $password, 'timeout_ms' => 45000, 'interval_ms' => 500,
	));
	$admin = $readiness->connection;
	$admin->set_charset('utf8mb4');
	$backup_file = tempnam(sys_get_temp_dir(), 'doclinc-role-identity-backup-');
	if (!is_string($backup_file) || file_put_contents($backup_file, "DISPOSABLE ROLE IDENTITY BACKUP\n") === false) {
		throw new RuntimeException('backup_fixture_failed');
	}
	chmod($backup_file, 0600);
	$backup_sha = hash_file('sha256', $backup_file);

	list($valid_name, $valid_db) = identity_integration_database($admin, 'valid');
	$before = identity_integration_snapshot($valid_db);
	$apply = identity_integration_run($valid_name, $backup_file, $backup_sha);
	identity_integration_expect($apply['exit'] === 0 && strpos($apply['stdout'], 'MIGRATION_RESULT=PASS') !== false, 'actual_migration_apply_passes');
	identity_integration_expect(strpos($apply['stdout'], 'DDL_STATEMENT_COUNT=2') !== false, 'actual_migration_executes_two_additive_alters');
	identity_integration_expect(identity_integration_schema_ready($valid_db), 'exact_columns_and_unique_indexes_created');
	identity_integration_expect(hash_equals($before, identity_integration_snapshot($valid_db)), 'existing_rows_preserved_exactly');
	$null_count = (int) $valid_db->query('SELECT (SELECT COUNT(*) FROM users WHERE nik IS NULL AND nomor_kk IS NULL AND nomor_bpjs_kis IS NULL) + (SELECT COUNT(*) FROM puskesmas_staff WHERE nip IS NULL) AS total')->fetch_assoc()['total'];
	identity_integration_expect($null_count === 3, 'new_identity_fields_are_nullable_without_backfill');
	$idempotent = identity_integration_run($valid_name, $backup_file, $backup_sha);
	identity_integration_expect($idempotent['exit'] === 0 && strpos($idempotent['stdout'], 'ALREADY_APPLIED=true') !== false && strpos($idempotent['stdout'], 'DDL_EXECUTED=false') !== false, 'migration_rerun_is_idempotent');

	list($checksum_name, $checksum_db) = identity_integration_database($admin, 'checksum');
	$checksum = identity_integration_run($checksum_name, $backup_file, str_repeat('0', 64));
	identity_integration_expect($checksum['exit'] !== 0 && strpos($checksum['stderr'], 'SAFE_ERROR_CODE=backup_checksum_confirmation_mismatch') !== false, 'invalid_backup_checksum_rejected');
	identity_integration_expect(!identity_integration_schema_ready($checksum_db), 'checksum_failure_executes_zero_ddl');

	list($partial_name, $partial_db) = identity_integration_database($admin, 'partial');
	$partial_db->query('ALTER TABLE users ADD COLUMN nik CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL');
	$partial = identity_integration_run($partial_name, $backup_file, $backup_sha);
	identity_integration_expect($partial['exit'] !== 0 && strpos($partial['stderr'], 'SAFE_ERROR_CODE=partial_schema_detected') !== false, 'partial_schema_fails_closed');
	$partial_other_count = (int) $partial_db->query("SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='users' AND COLUMN_NAME IN ('nomor_kk','nomor_bpjs_kis')) OR (TABLE_NAME='puskesmas_staff' AND COLUMN_NAME='nip'))")->fetch_assoc()['total'];
	identity_integration_expect($partial_other_count === 0, 'partial_schema_failure_executes_no_additional_ddl');

} catch (Throwable $exception) {
	fwrite(STDERR, "ROLE_IDENTITY_MIGRATION_INTEGRATION=FAIL\nSAFE_ERROR_CODE=disposable_integration_failed\n");
	$failed++;
} finally {
	if ($admin instanceof mysqli) {
		foreach ($connections as $connection) {
			try { $connection->close(); } catch (Throwable $ignored) {}
		}
		foreach (array_reverse($databases) as $database) {
			try {
				$admin->query('DROP DATABASE IF EXISTS ' . identity_integration_identifier($database));
				$stmt = $admin->prepare('SELECT COUNT(*) AS total FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
				$stmt->bind_param('s', $database); $stmt->execute();
				$remaining = (int) $stmt->get_result()->fetch_assoc()['total']; $stmt->close();
				if ($remaining !== 0) { $cleanup_failed = true; $failed++; }
			} catch (Throwable $ignored) { $cleanup_failed = true; $failed++; }
		}
		try { $admin->close(); } catch (Throwable $ignored) { $cleanup_failed = true; $failed++; }
	}
	if (is_string($backup_file) && $backup_file !== '' && is_file($backup_file) && !@unlink($backup_file)) { $cleanup_failed = true; $failed++; }
}

echo 'DISPOSABLE_DATABASES_REMAINING=' . ($cleanup_failed ? 'verification_required' : '0') . "\n";
echo 'DISPOSABLE_BACKUP_REMAINING=' . (is_string($backup_file) && $backup_file !== '' && is_file($backup_file) ? '1' : '0') . "\n";
echo "ROLE_IDENTITY_MIGRATION_INTEGRATION_PASSED={$passed}\n";
echo "ROLE_IDENTITY_MIGRATION_INTEGRATION_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
