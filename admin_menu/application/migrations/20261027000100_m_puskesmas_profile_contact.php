<?php
/**
 * Menambah kolom profil layar Puskesmas: `email` dan `kepala_puskesmas`.
 *
 * Mengapa perlu
 * -------------
 * Halaman `/kelola_dokter_nakes` sekarang menjadi satu pintu pengelolaan
 * Puskesmas: identitas fasilitas, kontak, dan nama kepala. `m_puskesmas` sudah
 * memuat `alamat`, `telepon`, `kota`, dan `provinsi`, tetapi belum punya
 * kolom email resmi maupun nama kepala. Tanpa kolomnya, dua isian di layar
 * hanya bisa "disimpan" dengan mengarang tempat lain - dan data yang ditulis
 * di tempat lain tidak akan pernah terbaca oleh layar yang sama.
 *
 * Sifat migrasi ini: TAMBAHAN dan TIDAK MENGHAPUS apa pun.
 *   - idempoten: kolom diperiksa lewat `information_schema.COLUMNS` sebelum
 *     `ALTER TABLE`, jadi menjalankannya dua kali tidak gagal;
 *   - kedua kolom `NULL`-able tanpa DEFAULT, sehingga baris lama tetap sah dan
 *     tidak ada nilai yang dikarang untuk data yang belum diketahui;
 *   - dapat dibatalkan dengan `ALTER TABLE m_puskesmas DROP COLUMN email,
 *     DROP COLUMN kepala_puskesmas`; yang hilang hanya isian yang diisi lewat
 *     layar baru;
 *   - DDL di MariaDB melakukan commit implisit, jadi TIDAK dibungkus transaksi.
 *
 * Pemakaian
 * ---------
 *   php admin_menu/application/migrations/20261027000100_m_puskesmas_profile_contact.php
 *     -> mencetak rencana, tidak menulis apa pun
 *
 *   DOCLINC_PKM_PROFILE_APPLY=1 \
 *   php admin_menu/application/migrations/20261027000100_m_puskesmas_profile_contact.php \
 *     --apply --environment=staging --confirm-database=<nama-database>
 *     -> menambah kolom yang belum ada
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_PKM_PROFILE_MIGRATION_ID = '20261027000100_m_puskesmas_profile_contact';
const DOCLINC_PKM_PROFILE_TABLE = 'm_puskesmas';
const DOCLINC_PKM_PROFILE_COLUMNS = array(
	'email' => "ALTER TABLE `m_puskesmas` ADD COLUMN `email` varchar(150) NULL AFTER `telepon`",
	'kepala_puskesmas' => "ALTER TABLE `m_puskesmas` ADD COLUMN `kepala_puskesmas` varchar(150) NULL AFTER `email`",
);

function doclinc_pkm_profile_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function doclinc_pkm_profile_fail($reason, $connected = true)
{
	fwrite(
		STDERR,
		"MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\n"
		. 'DATABASE_CONNECTION_OPENED=' . ($connected ? 'true' : 'false') . "\n"
		. "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\n"
	);
	exit(1);
}

function doclinc_pkm_profile_columns(mysqli $db, $table)
{
	$statement = $db->prepare(
		'SELECT COLUMN_NAME AS column_name FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
		 ORDER BY ORDINAL_POSITION'
	);
	$statement->bind_param('s', $table);
	$statement->execute();
	$rows = $statement->get_result();
	$columns = array();
	while ($row = $rows->fetch_assoc()) {
		$columns[] = (string) $row['column_name'];
	}
	$statement->close();

	return $columns;
}

/* ---------------------------------------------------------------------
   Argumen
   --------------------------------------------------------------------- */
$apply = false;
$environment = '';
$confirm_database = '';
foreach (array_slice($argv, 1) as $argument) {
	if ($argument === '--apply') {
		$apply = true;
	} elseif (strpos($argument, '--environment=') === 0) {
		$environment = substr($argument, strlen('--environment='));
	} elseif (strpos($argument, '--confirm-database=') === 0) {
		$confirm_database = substr($argument, strlen('--confirm-database='));
	}
}

if ($apply && !doclinc_pkm_profile_true(getenv('DOCLINC_PKM_PROFILE_APPLY'))) {
	doclinc_pkm_profile_fail('apply_confirmation_missing', false);
}
if ($apply && strtolower(trim($environment)) !== 'staging') {
	doclinc_pkm_profile_fail('environment_not_allowed', false);
}

$host = getenv('ADMIN_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$database = getenv('ADMIN_DB_NAME') ?: getenv('DB_NAME') ?: '';
$user = getenv('ADMIN_DB_USER') ?: getenv('DB_USER') ?: '';
$password = getenv('ADMIN_DB_PASS');
if ($password === false) {
	$password = getenv('DB_PASS');
}
$port = (int) (getenv('ADMIN_DB_PORT') ?: getenv('DB_PORT') ?: 3306);

if ($database === '') {
	doclinc_pkm_profile_fail('database_name_missing', false);
}
if ($apply && $confirm_database !== $database) {
	doclinc_pkm_profile_fail('database_confirmation_mismatch', false);
}
if ($user === '' || !is_string($password)) {
	doclinc_pkm_profile_fail('database_identity_missing', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
} catch (Throwable $exception) {
	doclinc_pkm_profile_fail('database_connection_failed', false);
}

/* ---------------------------------------------------------------------
   Rencana
   --------------------------------------------------------------------- */
$columns = doclinc_pkm_profile_columns($db, DOCLINC_PKM_PROFILE_TABLE);
echo 'MIGRATION_ID=' . DOCLINC_PKM_PROFILE_MIGRATION_ID . "\n"
	. 'EXECUTION_MODE=' . ($apply ? 'APPLY' : 'DRY_RUN') . "\n"
	. 'DATABASE=' . $database . "\n"
	. 'TARGET_TABLE=' . DOCLINC_PKM_PROFILE_TABLE . "\n"
	. 'COLUMNS_PRESENT=' . implode(',', $columns) . "\n";

$pending = array();
foreach (DOCLINC_PKM_PROFILE_COLUMNS as $column => $statement) {
	if (in_array($column, $columns, true)) {
		echo "PLAN SKIP kolom {$column} sudah ada\n";
		continue;
	}
	$pending[$column] = $statement;
	echo "PLAN ADD COLUMN {$column}\n";
}

if (empty($pending) || !$apply) {
	echo "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
	exit(0);
}

/* ---------------------------------------------------------------------
   Terapkan
   --------------------------------------------------------------------- */
$lock = $db->query("SELECT GET_LOCK('doclinc_pkm_profile_contact', 10) AS acquired")->fetch_assoc();
if (empty($lock['acquired'])) {
	doclinc_pkm_profile_fail('profile_migration_lock_unavailable');
}

$added = array();
foreach ($pending as $column => $statement) {
	try {
		$db->query($statement);
		$added[] = $column;
	} catch (Throwable $exception) {
		$db->query("SELECT RELEASE_LOCK('doclinc_pkm_profile_contact')");
		doclinc_pkm_profile_fail('add_column_failed_' . $column);
	}
}

$columns_after = doclinc_pkm_profile_columns($db, DOCLINC_PKM_PROFILE_TABLE);
$db->query("SELECT RELEASE_LOCK('doclinc_pkm_profile_contact')");

$missing = array_diff(array_keys(DOCLINC_PKM_PROFILE_COLUMNS), $columns_after);
if (!empty($missing)) {
	doclinc_pkm_profile_fail('column_missing_after_alter');
}

echo 'COLUMNS_ADDED=' . implode(',', $added) . "\n";
echo 'COLUMNS=' . implode(',', $columns_after) . "\n";
echo "REVERSIBLE_BY=ALTER TABLE `m_puskesmas` DROP COLUMN `email`, DROP COLUMN `kepala_puskesmas`\n";
echo "DATA_WRITES_EXECUTED=true\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
exit(0);

