<?php
/**
 * Tabel kode aktivasi akun nakes (sekali pakai, berbatas waktu).
 *
 * Mengapa perlu
 * -------------
 * Serah-terima kredensial sekarang hanya lewat password sementara: password itu
 * hidup di dua tempat (catatan Admin dan ingatan nakes), tidak punya masa
 * berlaku, dan tidak ada bukti bahwa serah-terima benar terjadi. Kode aktivasi
 * menggantikan peran "password yang dibacakan": Admin menyerahkan **kode 8
 * karakter** yang hanya berlaku sekali dan kedaluwarsa, lalu nakes membuat
 * password pribadinya sendiri.
 *
 * Yang disimpan hanya **hash** kode (`password_hash`), bukan kodenya. Kode asli
 * tidak pernah bisa dibaca lagi dari basis data — sama seperti password.
 *
 * Sifat migrasi ini: ADITIF. Hanya membuat tabel bila belum ada; tidak
 * mengubah, tidak menghapus, dan tidak menyentuh data akun mana pun.
 *
 * Pemakaian
 * ---------
 *   php admin_menu/application/migrations/20261024000300_nakes_activation_codes.php
 *     -> mencetak rencana, tidak menulis apa pun
 *
 *   DOCLINC_ACTIVATION_CODES_APPLY=1 \
 *   DOCLINC_ACTIVATION_CODES_BACKUP_REFERENCE="dump.sql.gz:sha256:..." \
 *   php admin_menu/application/migrations/20261024000300_nakes_activation_codes.php \
 *     --apply --environment=staging --confirm-database=<db>
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_ACTIVATION_CODES_MIGRATION_ID = '20261024000300_nakes_activation_codes';
const DOCLINC_ACTIVATION_CODES_TABLE = 'nakes_activation_codes';

function activation_codes_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function activation_codes_fail($reason, $connected = true)
{
	fwrite(
		STDERR,
		"MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\n"
		. 'DATABASE_CONNECTION_OPENED=' . ($connected ? 'true' : 'false') . "\n"
		. "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\n"
	);
	exit(1);
}

function activation_codes_table_exists(mysqli $db, $database)
{
	/* `bind_param()` menerima argumen by-reference, jadi konstanta tidak boleh
	   dikirim langsung — harus lewat variabel. */
	$table = DOCLINC_ACTIVATION_CODES_TABLE;
	$stmt = $db->prepare('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
	$stmt->bind_param('ss', $database, $table);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return (int) ($row['total'] ?? 0) > 0;
}

function activation_codes_index_exists(mysqli $db, $database)
{
	$table = DOCLINC_ACTIVATION_CODES_TABLE;
	$index = 'idx_nakes_activation_codes_user';
	$stmt = $db->prepare('SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?');
	$stmt->bind_param('sss', $database, $table, $index);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return (int) ($row['total'] ?? 0) > 0;
}

$options = getopt('', array('apply', 'environment:', 'confirm-database:'));

$host = trim((string) (getenv('DOCLINC_ACTIVATION_CODES_DB_HOST') ?: getenv('ADMIN_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1'));
$port = (int) (getenv('DOCLINC_ACTIVATION_CODES_DB_PORT') ?: getenv('ADMIN_DB_PORT') ?: getenv('DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_ACTIVATION_CODES_DB_USER') ?: getenv('ADMIN_DB_USER') ?: getenv('DB_USER') ?: ''));
$password = getenv('DOCLINC_ACTIVATION_CODES_DB_PASSWORD');
if ($password === false || $password === '') {
	$password = getenv('ADMIN_DB_PASS');
}
if ($password === false || $password === '') {
	$password = getenv('DB_PASS');
}
$database = trim((string) (getenv('DOCLINC_ACTIVATION_CODES_DB_NAME') ?: getenv('ADMIN_DB_NAME') ?: getenv('DB_NAME') ?: ''));

if (!array_key_exists('apply', $options)) {
	if ($database === '' || $user === '' || !is_string($password)) {
		echo 'MIGRATION_ID=' . DOCLINC_ACTIVATION_CODES_MIGRATION_ID . "\n"
			. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=definitions_only\nDATABASE_WRITE_EXECUTED=false\n"
			. 'TARGET_TABLE=' . DOCLINC_ACTIVATION_CODES_TABLE . "\nDESTRUCTIVE_STATEMENTS=0\n";
		exit(0);
	}
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	try {
		$db = new mysqli($host, $user, $password, $database, $port);
		$db->set_charset('utf8mb4');
	} catch (Throwable $exception) {
		activation_codes_fail('database_connection_failed', false);
	}
	$exists = activation_codes_table_exists($db, $database);
	echo 'MIGRATION_ID=' . DOCLINC_ACTIVATION_CODES_MIGRATION_ID . "\n"
		. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=information_schema\nDATABASE_WRITE_EXECUTED=false\n"
		. 'TARGET_TABLE=' . DOCLINC_ACTIVATION_CODES_TABLE . ' TABLE_EXISTS=' . ($exists ? 'true' : 'false') . "\n"
		. 'PLANNED_CREATE=' . ($exists ? 'none' : DOCLINC_ACTIVATION_CODES_TABLE) . "\n"
		. "DESTRUCTIVE_STATEMENTS=0\n";
	exit(0);
}

/* ---------------------------------------------------------------------------
 * Mode terapkan: empat pagar - saklar env, --apply, konfirmasi nama database,
 * dan referensi backup.
 * ------------------------------------------------------------------------ */
if (!activation_codes_true(getenv('DOCLINC_ACTIVATION_CODES_APPLY'))) {
	activation_codes_fail('activation_codes_apply_disabled', false);
}
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
if (!in_array($environment, array('test', 'staging', 'uat', 'production'), true)) {
	activation_codes_fail('environment_not_allowed', false);
}
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	activation_codes_fail('database_confirmation_mismatch', false);
}
$backup_reference = trim((string) (getenv('DOCLINC_ACTIVATION_CODES_BACKUP_REFERENCE') ?: ''));
if ($backup_reference === '') {
	activation_codes_fail('backup_reference_missing', false);
}
if ($user === '' || !is_string($password)) {
	activation_codes_fail('database_identity_missing', false);
}
if ($password === '' && !in_array(strtolower($host), array('127.0.0.1', 'localhost', '::1'), true)) {
	activation_codes_fail('database_password_required_for_remote_host', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
} catch (Throwable $exception) {
	activation_codes_fail('database_connection_failed', false);
}

$lock = $db->query("SELECT GET_LOCK('doclinc_nakes_activation_codes', 10) AS acquired")->fetch_assoc();
if (empty($lock['acquired'])) {
	activation_codes_fail('activation_codes_lock_unavailable');
}

$created = 0;
$already = activation_codes_table_exists($db, $database);
if (!$already) {
	$sql = 'CREATE TABLE ' . DOCLINC_ACTIVATION_CODES_TABLE . ' ('
		. 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
		. 'user_id INT NOT NULL,'
		. 'code_hash VARCHAR(255) NOT NULL,'
		. 'expires_at DATETIME NOT NULL,'
		. 'used_at DATETIME NULL DEFAULT NULL,'
		. 'issued_by_user_id INT NULL DEFAULT NULL,'
		. "issued_reason VARCHAR(40) NOT NULL DEFAULT 'account_created',"
		. 'created_at DATETIME NOT NULL,'
		. 'PRIMARY KEY (id),'
		. 'KEY idx_nakes_activation_codes_user (user_id, used_at, expires_at)'
		. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
	try {
		$db->query($sql);
		$created = 1;
	} catch (Throwable $exception) {
		$db->query("SELECT RELEASE_LOCK('doclinc_nakes_activation_codes')");
		activation_codes_fail('activation_codes_create_failed');
	}
}
$db->query("SELECT RELEASE_LOCK('doclinc_nakes_activation_codes')");

$table_exists = activation_codes_table_exists($db, $database);
$index_exists = $table_exists && activation_codes_index_exists($db, $database);
$columns = array();
if ($table_exists) {
	$result = $db->query('SELECT column_name FROM information_schema.columns WHERE table_schema = "' . $db->real_escape_string($database) . '" AND table_name = "' . DOCLINC_ACTIVATION_CODES_TABLE . '"');
	while ($row = $result->fetch_assoc()) {
		$columns[] = strtolower((string) ($row['column_name'] ?? $row['COLUMN_NAME'] ?? ''));
	}
}
$required = array('id', 'user_id', 'code_hash', 'expires_at', 'used_at', 'issued_by_user_id', 'issued_reason', 'created_at');
$missing = array_values(array_diff($required, $columns));

echo 'MIGRATION_ID=' . DOCLINC_ACTIVATION_CODES_MIGRATION_ID . "\n"
	. "EXECUTION_MODE=APPLY\nDATABASE_WRITE_EXECUTED=true\n"
	. 'BACKUP_REFERENCE=' . $backup_reference . "\n"
	. 'TABLES_CREATED=' . $created . ' ALREADY_EXISTED=' . ($already ? 'true' : 'false') . "\n"
	. 'VERIFY_TABLE=' . ($table_exists ? 'true' : 'false') . ' VERIFY_INDEX=' . ($index_exists ? 'true' : 'false')
	. ' VERIFY_COLUMNS=' . count($columns) . "\n"
	. 'VERIFY_MISSING_COLUMNS=' . (empty($missing) ? 'none' : implode(',', $missing)) . "\n"
	. "DESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT="
	. ($table_exists && $index_exists && empty($missing) ? 'PASS' : 'FAIL') . "\n";
exit($table_exists && $index_exists && empty($missing) ? 0 : 1);
