<?php
/**
 * Tambahkan tabel penandaan tindak lanjut untuk dasbor Monitoring Layanan
 * Kesehatan (Dinkes).
 *
 * Mengapa perlu
 * -------------
 * Panel pengecualian pada dasbor menunjukkan permintaan yang perlu ditangani,
 * tetapi tidak menyimpan siapa yang sudah menanganinya. Tanpa jejak itu,
 * daftar pengecualian hanya sebuah laporan, bukan alat pengawasan: tidak ada
 * cara menjawab "apakah ini sudah dilihat?" pada hari berikutnya.
 *
 * Mengapa tabel sendiri, bukan `request_events`
 * ----------------------------------------------
 * `request_events` adalah jejak siklus permintaan dan dibaca oleh pengawas
 * permintaan, notifikasi, dan penampil audit. Menitipkan penanda pengawasan ke
 * sana akan mencampur urusan pemantauan ke dalam jejak klinis dan dapat
 * memicu perilaku yang tidak diminta. Tabel terpisah tidak menyentuh satu pun
 * jalur yang sudah ada.
 *
 * Yang TIDAK disentuh migrasi ini
 * -------------------------------
 *   - `requests` dan `medicalrecords` tidak dibaca, diubah, atau dihapus.
 *     Dasbor tetap tidak boleh mengubah catatan klinis.
 *   - tidak ada tabel yang diubah atau dihapus.
 *   - hanya satu tabel baru yang dibuat.
 *
 * Sifat migrasi ini: TAMBAHAN dan TIDAK MENGHAPUS apa pun.
 *   - idempoten: `CREATE TABLE IF NOT EXISTS`, jadi menjalankannya dua kali aman;
 *   - dapat dibatalkan sepenuhnya dengan `DROP TABLE monitoring_followups`
 *     (lihat REVERSIBLE_BY); karena tabelnya baru, tidak ada data lama yang
 *     hilang bila dibatalkan;
 *   - DDL di MariaDB melakukan commit implisit, jadi TIDAK dibungkus transaksi.
 *     Karena itu tidak ada penulisan data yang perlu di-rollback.
 *
 * Pemakaian
 * ---------
 *   php admin_menu/application/migrations/20261025000200_monitoring_followups.php
 *     -> mencetak rencana, tidak menulis apa pun
 *
 *   php admin_menu/application/migrations/20261025000200_monitoring_followups.php \
 *     --apply --environment=staging --confirm-database=<nama-database>
 *     -> membuat tabel bila belum ada
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_MONITORING_FOLLOWUP_MIGRATION_ID = '20261025000200_monitoring_followups';
const DOCLINC_MONITORING_FOLLOWUP_TABLE = 'monitoring_followups';

/**
 * Definisi tabel.
 *
 * `request_id` sebagai kunci utama berarti satu penanda terkini per permintaan.
 * Riwayat lengkap siapa menandai apa dan kapan tersimpan di `audit_logs`, jadi
 * tabel ini sengaja hanya menyimpan keadaan sekarang - bukan salinan kedua
 * dari jejak audit.
 */
const DOCLINC_MONITORING_FOLLOWUP_DDL = "CREATE TABLE IF NOT EXISTS `monitoring_followups` (
	`request_id` bigint(20) NOT NULL,
	`marked_by_user_id` int(11) NOT NULL,
	`note` varchar(255) NULL,
	`marked_at` datetime NOT NULL,
	PRIMARY KEY (`request_id`),
	KEY `idx_monitoring_followups_marked_at` (`marked_at`),
	KEY `idx_monitoring_followups_actor` (`marked_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

function monitoring_followup_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function monitoring_followup_fail($reason, $connected = true)
{
	fwrite(
		STDERR,
		"MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\n"
		. 'DATABASE_CONNECTION_OPENED=' . ($connected ? 'true' : 'false') . "\n"
		. "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\n"
	);
	exit(1);
}

/** Apakah tabel sudah ada. */
function monitoring_followup_exists(mysqli $db, $table)
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

/** Nama kolom yang benar-benar ada pada tabel, untuk laporan sesudah apply. */
function monitoring_followup_columns(mysqli $db, $table)
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

if ($apply && !monitoring_followup_true(getenv('DOCLINC_MONITORING_FOLLOWUP_APPLY'))) {
	monitoring_followup_fail('apply_confirmation_missing', false);
}
if ($apply && strtolower(trim($environment)) !== 'staging') {
	monitoring_followup_fail('environment_not_allowed', false);
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
	monitoring_followup_fail('database_name_missing', false);
}
if ($apply && $confirm_database !== $database) {
	monitoring_followup_fail('database_confirmation_mismatch', false);
}
if ($user === '' || !is_string($password)) {
	monitoring_followup_fail('database_identity_missing', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
} catch (Throwable $exception) {
	monitoring_followup_fail('database_connection_failed', false);
}

/* ---------------------------------------------------------------------
   Rencana
   --------------------------------------------------------------------- */
$table_exists = monitoring_followup_exists($db, DOCLINC_MONITORING_FOLLOWUP_TABLE);

echo 'MIGRATION_ID=' . DOCLINC_MONITORING_FOLLOWUP_MIGRATION_ID . "\n"
	. 'EXECUTION_MODE=' . ($apply ? 'APPLY' : 'DRY_RUN') . "\n"
	. 'DATABASE=' . $database . "\n"
	. 'TARGET_TABLE=' . DOCLINC_MONITORING_FOLLOWUP_TABLE . "\n"
	. 'TABLE_ALREADY_PRESENT=' . ($table_exists ? 'true' : 'false') . "\n";

if ($table_exists) {
	echo "PLAN SKIP tabel sudah ada; tidak ada yang diubah\n";
	echo "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
	exit(0);
}

echo "PLAN CREATE TABLE " . DOCLINC_MONITORING_FOLLOWUP_TABLE . "\n";
echo "PLAN REASON tabel penanda tindak lanjut untuk panel pengecualian dasbor\n";

if (!$apply) {
	echo "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
	exit(0);
}

/* ---------------------------------------------------------------------
   Terapkan
   --------------------------------------------------------------------- */
$lock = $db->query("SELECT GET_LOCK('doclinc_monitoring_followups', 10) AS acquired")->fetch_assoc();
if (empty($lock['acquired'])) {
	monitoring_followup_fail('followup_migration_lock_unavailable');
}

try {
	$db->query(DOCLINC_MONITORING_FOLLOWUP_DDL);
} catch (Throwable $exception) {
	$db->query("SELECT RELEASE_LOCK('doclinc_monitoring_followups')");
	monitoring_followup_fail('create_table_failed');
}

$columns = monitoring_followup_columns($db, DOCLINC_MONITORING_FOLLOWUP_TABLE);
if (empty($columns)) {
	$db->query("SELECT RELEASE_LOCK('doclinc_monitoring_followups')");
	monitoring_followup_fail('table_missing_after_create');
}

$db->query("SELECT RELEASE_LOCK('doclinc_monitoring_followups')");

echo 'TABLE_CREATED=' . DOCLINC_MONITORING_FOLLOWUP_TABLE . "\n";
echo 'COLUMNS=' . implode(',', $columns) . "\n";
echo "REVERSIBLE_BY=DROP TABLE `" . DOCLINC_MONITORING_FOLLOWUP_TABLE . "`\n";
echo "DATA_WRITES_EXECUTED=true\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
exit(0);
