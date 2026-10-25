<?php
/**
 * Tambahkan indeks pada `medicalrecords.created_at` untuk panel diagnosis.
 *
 * Mengapa perlu
 * -------------
 * Panel "Diagnosis tercatat per Puskesmas" menyaring rentang tanggal pada
 * `medicalrecords.created_at`, dan `medicalrecords` tidak punya indeks pada
 * kolom itu. Akibatnya MariaDB memindai seluruh tabel lalu menggabungkannya ke
 * `requests` dengan `type=ALL`.
 *
 * Penyebab keduanya adalah pembungkus fungsi pada kolom tanggalnya
 * (`DATE(kolom) >= ...`). Pembungkus itu sudah dilepas di model karena
 * `DATE(x) >= D` setara persis dengan `x >= D`; setelah dilepas, indeks inilah
 * yang membuat penyaringnya benar-benar mempersempit baris.
 *
 * Bukti (skema sementara, 100.000 permintaan / 69.334 rekam medis, ANALYZE TABLE
 * sebelum setiap EXPLAIN; skema sementara itu sudah dihapus):
 *
 *   Bentuk kueri                                  Sebelum                Sesudah
 *   -------------------------------------------   --------------------   --------------------
 *   DATE(kolom) >= ..., tanpa indeks              ALL, rows 69.264       -
 *   kolom >= ..., tanpa indeks                    ALL, rows 69.264       149 ms
 *   kolom >= ..., dengan indeks ini               -                      range, rows 2.853
 *                                                                        25 ms
 *
 * Hasilnya diverifikasi setara: jumlah baris dan sidik CRC32 atas nilai
 * diagnosis **identik** sebelum dan sesudah pembungkus dilepas, jadi yang
 * berubah hanya rencana eksekusinya.
 *
 * Sifat migrasi ini: TAMBAHAN dan TIDAK MENGHAPUS apa pun.
 *   - hanya `CREATE INDEX`; tidak ada baris yang dibaca, diubah, atau dihapus;
 *   - idempoten: indeks yang sudah ada dilewati, bukan dibuat ulang;
 *   - dapat dibatalkan dengan `DROP INDEX <nama> ON <tabel>` (lihat REVERSIBLE_BY);
 *   - DDL di MariaDB melakukan commit implisit, jadi TIDAK dibungkus transaksi.
 *
 * Pemakaian
 * ---------
 *   php admin_menu/application/migrations/20261025000300_medicalrecords_created_at_index.php
 *     -> mencetak rencana dan EXPLAIN, tidak menulis apa pun
 *
 *   php admin_menu/application/migrations/20261025000300_medicalrecords_created_at_index.php \
 *     --apply --environment=staging --confirm-database=<nama-database>
 *     -> membuat indeks bila belum ada
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_MR_INDEX_MIGRATION_ID = '20261025000300_medicalrecords_created_at_index';
const DOCLINC_MR_INDEX_NAME = 'idx_medicalrecords_created_at';
const DOCLINC_MR_INDEX_TABLE = 'medicalrecords';

function doclinc_mr_index_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function doclinc_mr_index_fail($reason, $connected = true)
{
	fwrite(
		STDERR,
		"MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\n"
		. 'DATABASE_CONNECTION_OPENED=' . ($connected ? 'true' : 'false') . "\n"
		. "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\n"
	);
	exit(1);
}

/** Indeks yang sudah ada pada tabel: nama => kolom. */
function doclinc_mr_index_existing(mysqli $db, $table)
{
	$existing = array();
	$statement = $db->prepare(
		'SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
		 FROM information_schema.STATISTICS
		 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
		 GROUP BY INDEX_NAME'
	);
	$statement->bind_param('s', $table);
	$statement->execute();
	$rows = $statement->get_result();
	while ($row = $rows->fetch_assoc()) {
		$existing[(string) $row['INDEX_NAME']] = (string) $row['cols'];
	}
	$statement->close();

	return $existing;
}

/** Kolom yang benar-benar ada, untuk memastikan definisi indeks sah. */
function doclinc_mr_index_columns(mysqli $db, $table)
{
	$columns = array();
	$statement = $db->prepare(
		'SELECT COLUMN_NAME FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
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

/** Rencana eksekusi ringkas untuk kueri diagnosis. */
function doclinc_mr_index_explain(mysqli $db, $label, $sql)
{
	$lines = array();
	try {
		$rows = $db->query('EXPLAIN ' . $sql);
		while ($row = $rows->fetch_assoc()) {
			$lines[] = ($row['table'] ?? '?') . ':type=' . ($row['type'] ?? '?')
				. ',key=' . (($row['key'] === null) ? 'NULL' : $row['key'])
				. ',rows=' . ($row['rows'] ?? '?');
		}
	} catch (Throwable $exception) {
		$lines[] = 'explain_unavailable';
	}

	return $label . ' => ' . implode(' | ', $lines);
}

/** Kueri diagnosis seperti dijalankan model, setelah pembungkus DATE() dilepas. */
function doclinc_mr_index_probe()
{
	return 'SELECT TRIM(medicalrecords.diagnosis) AS diagnosis, COUNT(*) AS total
		FROM medicalrecords
		INNER JOIN requests ON requests.request_id = medicalrecords.request_id
		WHERE medicalrecords.diagnosis IS NOT NULL
			AND TRIM(medicalrecords.diagnosis) <> \'\'
			AND medicalrecords.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
		GROUP BY TRIM(medicalrecords.diagnosis)
		ORDER BY total DESC
		LIMIT 8';
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

if ($apply && !doclinc_mr_index_true(getenv('DOCLINC_MR_INDEX_APPLY'))) {
	doclinc_mr_index_fail('apply_confirmation_missing', false);
}
if ($apply && strtolower(trim($environment)) !== 'staging') {
	doclinc_mr_index_fail('environment_not_allowed', false);
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
	doclinc_mr_index_fail('database_name_missing', false);
}
if ($apply && $confirm_database !== $database) {
	doclinc_mr_index_fail('database_confirmation_mismatch', false);
}
if ($user === '' || !is_string($password)) {
	doclinc_mr_index_fail('database_identity_missing', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
} catch (Throwable $exception) {
	doclinc_mr_index_fail('database_connection_failed', false);
}

/* ---------------------------------------------------------------------
   Rencana
   --------------------------------------------------------------------- */
$columns = doclinc_mr_index_columns($db, DOCLINC_MR_INDEX_TABLE);
if (empty($columns)) {
	doclinc_mr_index_fail('table_missing_' . DOCLINC_MR_INDEX_TABLE);
}
if (!in_array('created_at', $columns, true)) {
	doclinc_mr_index_fail('column_missing_created_at');
}

$existing = doclinc_mr_index_existing($db, DOCLINC_MR_INDEX_TABLE);
$already = isset($existing[DOCLINC_MR_INDEX_NAME]);
$probe = doclinc_mr_index_probe();

echo 'MIGRATION_ID=' . DOCLINC_MR_INDEX_MIGRATION_ID . "\n"
	. 'EXECUTION_MODE=' . ($apply ? 'APPLY' : 'DRY_RUN') . "\n"
	. 'DATABASE=' . $database . "\n"
	. 'INDEX_NAME=' . DOCLINC_MR_INDEX_NAME . "\n"
	. 'INDEX_ALREADY_PRESENT=' . ($already ? 'true' : 'false') . "\n"
	. 'EXPLAIN_BEFORE ' . doclinc_mr_index_explain($db, 'diagnosis', $probe) . "\n";

if ($already) {
	echo "PLAN SKIP indeks sudah ada; tidak ada yang diubah\n";
	echo "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
	exit(0);
}

echo 'PLAN CREATE INDEX ' . DOCLINC_MR_INDEX_NAME . ' ON ' . DOCLINC_MR_INDEX_TABLE . " (`created_at`)\n";
echo "PLAN REASON penyaring rentang tanggal pada panel diagnosis memakai kolom ini\n";

if (!$apply) {
	echo "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
	exit(0);
}

/* ---------------------------------------------------------------------
   Terapkan
   --------------------------------------------------------------------- */
$lock = $db->query("SELECT GET_LOCK('doclinc_mr_index', 10) AS acquired")->fetch_assoc();
if (empty($lock['acquired'])) {
	doclinc_mr_index_fail('migration_lock_unavailable');
}

try {
	$db->query('CREATE INDEX `' . DOCLINC_MR_INDEX_NAME . '` ON `' . DOCLINC_MR_INDEX_TABLE . '` (`created_at`)');
} catch (Throwable $exception) {
	$db->query("SELECT RELEASE_LOCK('doclinc_mr_index')");
	doclinc_mr_index_fail('create_index_failed');
}

$db->query("SELECT RELEASE_LOCK('doclinc_mr_index')");

$after = doclinc_mr_index_existing($db, DOCLINC_MR_INDEX_TABLE);
if (!isset($after[DOCLINC_MR_INDEX_NAME])) {
	doclinc_mr_index_fail('index_missing_after_create');
}

echo 'INDEX_CREATED=' . DOCLINC_MR_INDEX_NAME . "\n";
echo 'EXPLAIN_AFTER ' . doclinc_mr_index_explain($db, 'diagnosis', $probe) . "\n";
echo 'REVERSIBLE_BY=DROP INDEX `' . DOCLINC_MR_INDEX_NAME . '` ON `' . DOCLINC_MR_INDEX_TABLE . "`\n";
echo "DATA_WRITES_EXECUTED=true\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
exit(0);
