<?php
/**
 * Tabel simpanan hasil pemeriksaan mutu data untuk dasbor Dinkes.
 *
 * Mengapa perlu
 * -------------
 * Sepuluh pemeriksaan mutu data membaca SELURUH riwayat dan sengaja tidak
 * punya penyaring rentang tanggal - itu memang maksudnya. Akibatnya biayanya
 * tumbuh mengikuti total riwayat, bukan periode yang dipilih.
 *
 * Diukur pada skema sementara berisi 100.000 permintaan / 266.000 event:
 * satu muat halaman menjalankan 3.207 ms, dan **2.600 ms di antaranya** berasal
 * dari panel mutu data. Sepuluh pemeriksaan itu yang paling berat:
 *
 *   selesai_beda_kerangka            1.075 ms
 *   jejak_event_beda_kerangka          612 ms
 *   tanpa_jejak_diterima               232 ms
 *   tanpa_fasilitas                    194 ms
 *   fasilitas_nonaktif                 144 ms
 *   kode_tidak_terdaftar               137 ms
 *   selesai_sebelum_dibuat             111 ms
 *
 * Berbagai rumusan alternatif diuji dan **semuanya lebih lambat** - menulis
 * ulang EXISTS menjadi JOIN, menggabungkan pemeriksaan, dan menambah indeks
 * pada request_events. Jadi biaya itu inheren, dan satu-satunya jalan adalah
 * tidak menghitungnya ulang di setiap muat halaman.
 *
 * Mengapa tabel, bukan cache berkas CodeIgniter
 * ---------------------------------------------
 * Repo ini di-mount **read-only** ke container PHP (`docker inspect`:
 * `/var/www/html` Mode=ro), sehingga `application/cache/` tidak dapat ditulis
 * dan driver `file` milik CodeIgniter tidak dapat dipakai. Basis data sudah
 * menjadi syarat aplikasi ini, jadi simpanan di sana tidak menambah syarat baru
 * dan tidak mengikat aplikasi pada susunan mount satu deployment.
 *
 * Sifat migrasi ini: TAMBAHAN dan TIDAK MENGHAPUS apa pun.
 *   - idempoten: `CREATE TABLE IF NOT EXISTS`;
 *   - dapat dibatalkan dengan `DROP TABLE monitoring_cache`; isinya hanya
 *     salinan sementara, jadi tidak ada data yang hilang bila dibatalkan;
 *   - DDL di MariaDB melakukan commit implisit, jadi TIDAK dibungkus transaksi.
 *
 * Pemakaian
 * ---------
 *   php admin_menu/application/migrations/20261025000400_monitoring_cache.php
 *     -> mencetak rencana, tidak menulis apa pun
 *
 *   php admin_menu/application/migrations/20261025000400_monitoring_cache.php \
 *     --apply --environment=staging --confirm-database=<nama-database>
 *     -> membuat tabel bila belum ada
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_MON_CACHE_MIGRATION_ID = '20261025000400_monitoring_cache';
const DOCLINC_MON_CACHE_TABLE = 'monitoring_cache';

/**
 * Definisi tabel.
 *
 * `cache_key` adalah kunci utama, jadi satu baris per jenis simpanan.
 * `computed_at` dipakai dua hal: menentukan kedaluwarsa, dan - yang lebih
 * penting - memberi tahu pembaca dasbor kapan hasil ini dihitung. Simpanan yang
 * tidak terlihat umurnya adalah kesegaran palsu.
 */
const DOCLINC_MON_CACHE_DDL = "CREATE TABLE IF NOT EXISTS `monitoring_cache` (
	`cache_key` varchar(64) NOT NULL,
	`payload` mediumtext NOT NULL,
	`computed_at` datetime NOT NULL,
	PRIMARY KEY (`cache_key`),
	KEY `idx_monitoring_cache_computed_at` (`computed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

function doclinc_mon_cache_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function doclinc_mon_cache_fail($reason, $connected = true)
{
	fwrite(
		STDERR,
		"MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\n"
		. 'DATABASE_CONNECTION_OPENED=' . ($connected ? 'true' : 'false') . "\n"
		. "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\n"
	);
	exit(1);
}

function doclinc_mon_cache_exists(mysqli $db, $table)
{
	$statement = $db->prepare(
		'SELECT COUNT(*) AS total FROM information_schema.TABLES
		 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
	);
	$statement->bind_param('s', $table);
	$statement->execute();
	$row = $statement->get_result()->fetch_assoc();
	$statement->close();

	return ((int) $row['total']) > 0;
}

function doclinc_mon_cache_columns(mysqli $db, $table)
{
	$columns = array();
	$statement = $db->prepare(
		'SELECT COLUMN_NAME FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
		 ORDER BY ORDINAL_POSITION'
	);
	$statement->bind_param('s', $table);
	$statement->execute();
	$rows = $statement->get_result();
	while ($row = $rows->fetch_assoc()) {
		$columns[] = (string) $row['COLUMN_NAME'];
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

if ($apply && !doclinc_mon_cache_true(getenv('DOCLINC_MON_CACHE_APPLY'))) {
	doclinc_mon_cache_fail('apply_confirmation_missing', false);
}
if ($apply && strtolower(trim($environment)) !== 'staging') {
	doclinc_mon_cache_fail('environment_not_allowed', false);
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
	doclinc_mon_cache_fail('database_name_missing', false);
}
if ($apply && $confirm_database !== $database) {
	doclinc_mon_cache_fail('database_confirmation_mismatch', false);
}
if ($user === '' || !is_string($password)) {
	doclinc_mon_cache_fail('database_identity_missing', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
} catch (Throwable $exception) {
	doclinc_mon_cache_fail('database_connection_failed', false);
}

/* ---------------------------------------------------------------------
   Rencana
   --------------------------------------------------------------------- */
$exists = doclinc_mon_cache_exists($db, DOCLINC_MON_CACHE_TABLE);

echo 'MIGRATION_ID=' . DOCLINC_MON_CACHE_MIGRATION_ID . "\n"
	. 'EXECUTION_MODE=' . ($apply ? 'APPLY' : 'DRY_RUN') . "\n"
	. 'DATABASE=' . $database . "\n"
	. 'TARGET_TABLE=' . DOCLINC_MON_CACHE_TABLE . "\n"
	. 'TABLE_ALREADY_PRESENT=' . ($exists ? 'true' : 'false') . "\n";

if ($exists) {
	echo "PLAN SKIP tabel sudah ada; tidak ada yang diubah\n";
	echo "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
	exit(0);
}

echo 'PLAN CREATE TABLE ' . DOCLINC_MON_CACHE_TABLE . "\n";
echo "PLAN REASON simpanan hasil pemeriksaan mutu data yang membaca seluruh riwayat\n";

if (!$apply) {
	echo "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
	exit(0);
}

/* ---------------------------------------------------------------------
   Terapkan
   --------------------------------------------------------------------- */
$lock = $db->query("SELECT GET_LOCK('doclinc_monitoring_cache', 10) AS acquired")->fetch_assoc();
if (empty($lock['acquired'])) {
	doclinc_mon_cache_fail('cache_migration_lock_unavailable');
}

try {
	$db->query(DOCLINC_MON_CACHE_DDL);
} catch (Throwable $exception) {
	$db->query("SELECT RELEASE_LOCK('doclinc_monitoring_cache')");
	doclinc_mon_cache_fail('create_table_failed');
}

$columns = doclinc_mon_cache_columns($db, DOCLINC_MON_CACHE_TABLE);
if (empty($columns)) {
	$db->query("SELECT RELEASE_LOCK('doclinc_monitoring_cache')");
	doclinc_mon_cache_fail('table_missing_after_create');
}

$db->query("SELECT RELEASE_LOCK('doclinc_monitoring_cache')");

echo 'TABLE_CREATED=' . DOCLINC_MON_CACHE_TABLE . "\n";
echo 'COLUMNS=' . implode(',', $columns) . "\n";
echo 'REVERSIBLE_BY=DROP TABLE `' . DOCLINC_MON_CACHE_TABLE . "`\n";
echo "DATA_WRITES_EXECUTED=true\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
exit(0);
