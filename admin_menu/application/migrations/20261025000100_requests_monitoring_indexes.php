<?php
/**
 * Tambahkan indeks pendukung dasbor Monitoring Layanan Kesehatan (Dinkes).
 *
 * Mengapa perlu
 * -------------
 * Tiga agregat dasbor memindai tabel penuh pada skema tanpa indeks ini:
 *
 *   1. Permintaan masuk berperiode (preset "Hari ini" / "7 hari"):
 *      WHERE created_at >= ? AND created_at < ?
 *   2. Layanan selesai berperiode:
 *      WHERE request_status = 'Completed' AND completed_at >= ? AND completed_at < ?
 *      `idx_requests_archive` dimulai dari assigned_puskesmas_code, sehingga
 *      indeks itu TIDAK dapat mempersempit rentang request_status+completed_at;
 *      MariaDB memindai seluruh indeks (100.000 baris pada uji volume).
 *   3. Median waktu respons:
 *      JOIN request_events + GROUP BY, dengan penyaring rentang pada created_at.
 *
 * Bukti (skema sementara, 100.000 permintaan / 175.020 event, ANALYZE TABLE
 * sebelum setiap EXPLAIN; skema sementara itu sudah dihapus):
 *
 *   Query                          Sebelum                       Sesudah
 *   ----------------------------   ---------------------------   ---------------------------
 *   Permintaan masuk, 1 hari       ALL, rows 100.219              range, rows 100 (Using index)
 *   Permintaan masuk, 7 hari       ALL, rows 100.219              range, rows 1.200 (Using index)
 *   Permintaan masuk, 30 hari      ALL, rows  99.915              ALL, rows 99.915
 *                                                                 (indeks dipertimbangkan,
 *                                                                  ditolak optimizer - wajar,
 *                                                                  ~17% baris)
 *   Layanan selesai, 30 hari       index, rows 100.000            range, rows 23.136
 *                                  (idx_requests_archive)         (Using index)
 *   Median respons                 index PRIMARY, rows 99.915     range, rows 47.984
 *
 * Indeks komposit pada `request_events (event_type, request_id, created_at)`
 * SENGAJA TIDAK dibuat: pada uji volume, optimizer tidak memilihnya
 * (possible_keys memuatnya, key tetap idx_events_request). Terukur, bukan
 * diasumsikan.
 *
 * Sifat migrasi ini: TAMBAHAN dan TIDAK MENGHAPUS apa pun.
 *   - hanya `CREATE INDEX`; tidak ada baris yang dibaca, diubah, atau dihapus;
 *   - idempoten: indeks yang sudah ada dilewati, bukan dibuat ulang;
 *   - dapat dibatalkan dengan `DROP INDEX <nama> ON <tabel>` (lihat REVERSIBLE_BY);
 *   - DDL di MariaDB melakukan commit implisit, jadi TIDAK dibungkus transaksi.
 *     Karena itu tidak ada penulisan data yang perlu di-rollback.
 *
 * Berbeda dari `20261024000200_puskesmas_staff_placement_repoint`, migrasi ini
 * TIDAK menuntut referensi backup: tidak ada satu baris pun yang ditulis atau
 * dihapus, sehingga backup penuh bukan syarat yang sepadan.
 *
 * Pemakaian
 * ---------
 *   php admin_menu/application/migrations/20261025000100_requests_monitoring_indexes.php
 *     -> mencetak rencana dan EXPLAIN, tidak menulis apa pun
 *
 *   php admin_menu/application/migrations/20261025000100_requests_monitoring_indexes.php \
 *     --apply --environment=staging --confirm-database=<nama-database>
 *     -> membuat indeks yang belum ada, lalu mencetak EXPLAIN sesudahnya
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_MONITORING_INDEX_MIGRATION_ID = '20261025000100_requests_monitoring_indexes';

/**
 * Indeks yang ditambahkan. Kunci = nama indeks, nilai = definisi.
 * Kolom dipilih dari bukti EXPLAIN, bukan dari dugaan.
 */
const DOCLINC_MONITORING_INDEXES = array(
	'idx_requests_created_at' => array(
		'table' => 'requests',
		'columns' => '(`created_at`)',
		'why' => 'Rentang created_at pada preset Hari ini / 7 hari dan pada median waktu respons.',
	),
	'idx_requests_status_completed' => array(
		'table' => 'requests',
		'columns' => '(`request_status`, `completed_at`)',
		'why' => 'Agregat layanan selesai berperiode; idx_requests_archive tidak dapat dipakai karena dimulai dari assigned_puskesmas_code.',
	),
);

function monitoring_index_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function monitoring_index_fail($reason, $connected = true)
{
	fwrite(
		STDERR,
		"MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\n"
		. 'DATABASE_CONNECTION_OPENED=' . ($connected ? 'true' : 'false') . "\n"
		. "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\n"
	);
	exit(1);
}

/** Indeks apa saja yang sudah ada pada tabel tertentu: nama => kolom. */
function monitoring_index_existing(mysqli $db, $table)
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

/** Kolom yang benar-benar ada pada tabel, untuk memastikan definisi indeks sah. */
function monitoring_index_columns(mysqli $db, $table)
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

/** EXPLAIN ringkas sebagai satu baris teks per query. */
function monitoring_index_explain(mysqli $db, $label, $sql)
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

/** Tiga query dasbor yang dipakai sebagai pembanding EXPLAIN. */
function monitoring_index_probe_queries()
{
	return array(
		'masuk_30hari' => 'SELECT COUNT(*) FROM requests
			WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
			  AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)',
		'selesai_30hari' => "SELECT COUNT(*) FROM requests
			WHERE request_status = 'Completed'
			  AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
			  AND completed_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)",
	);
}

/* ---------------------------------------------------------------------
   Argumen
   --------------------------------------------------------------------- */
$argv_rest = array_slice($argv, 1);
$apply = false;
$environment = '';
$confirm_database = '';
foreach ($argv_rest as $argument) {
	if ($argument === '--apply') {
		$apply = true;
	} elseif (strpos($argument, '--environment=') === 0) {
		$environment = substr($argument, strlen('--environment='));
	} elseif (strpos($argument, '--confirm-database=') === 0) {
		$confirm_database = substr($argument, strlen('--confirm-database='));
	}
}

if ($apply && !monitoring_index_true(getenv('DOCLINC_MONITORING_INDEX_APPLY'))) {
	monitoring_index_fail('apply_confirmation_missing', false);
}
if ($apply && strtolower(trim($environment)) !== 'staging') {
	monitoring_index_fail('environment_not_allowed', false);
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
	monitoring_index_fail('database_name_missing', false);
}
if ($apply && $confirm_database !== $database) {
	monitoring_index_fail('database_confirmation_mismatch', false);
}
if ($user === '' || !is_string($password)) {
	monitoring_index_fail('database_identity_missing', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
} catch (Throwable $exception) {
	monitoring_index_fail('database_connection_failed', false);
}

/* ---------------------------------------------------------------------
   Rencana
   --------------------------------------------------------------------- */
$to_create = array();
$skipped = array();
$columns_cache = array();

foreach (DOCLINC_MONITORING_INDEXES as $index_name => $definition) {
	$table = $definition['table'];
	if (!isset($columns_cache[$table])) {
		$columns_cache[$table] = monitoring_index_columns($db, $table);
	}
	if (empty($columns_cache[$table])) {
		monitoring_index_fail('table_missing_' . $table);
	}

	$needed = array();
	if (preg_match_all('/`([^`]+)`/', $definition['columns'], $matches)) {
		$needed = $matches[1];
	}
	foreach ($needed as $column) {
		if (!in_array($column, $columns_cache[$table], true)) {
			monitoring_index_fail('column_missing_' . $table . '_' . $column);
		}
	}

	$existing = monitoring_index_existing($db, $table);
	if (isset($existing[$index_name])) {
		$skipped[$index_name] = $existing[$index_name];
		continue;
	}
	$to_create[$index_name] = $definition;
}

$probe_queries = monitoring_index_probe_queries();
$explain_before = array();
foreach ($probe_queries as $label => $sql) {
	$explain_before[] = monitoring_index_explain($db, $label, $sql);
}

echo 'MIGRATION_ID=' . DOCLINC_MONITORING_INDEX_MIGRATION_ID . "\n"
	. 'EXECUTION_MODE=' . ($apply ? 'APPLY' : 'DRY_RUN') . "\n"
	. 'DATABASE=' . $database . "\n"
	. 'INDEXES_TO_CREATE=' . (empty($to_create) ? 'none' : implode(',', array_keys($to_create))) . "\n"
	. 'INDEXES_ALREADY_PRESENT=' . (empty($skipped) ? 'none' : implode(',', array_keys($skipped))) . "\n";
foreach ($explain_before as $line) {
	echo 'EXPLAIN_BEFORE ' . $line . "\n";
}

if (!$apply) {
	foreach ($to_create as $index_name => $definition) {
		echo 'PLAN CREATE INDEX ' . $index_name . ' ON ' . $definition['table'] . ' ' . $definition['columns'] . "\n";
		echo 'PLAN REASON ' . $index_name . ': ' . $definition['why'] . "\n";
	}
	echo "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
	exit(0);
}

/* ---------------------------------------------------------------------
   Terapkan
   --------------------------------------------------------------------- */
$lock = $db->query("SELECT GET_LOCK('doclinc_monitoring_indexes', 10) AS acquired")->fetch_assoc();
if (empty($lock['acquired'])) {
	monitoring_index_fail('index_migration_lock_unavailable');
}

$created = 0;
foreach ($to_create as $index_name => $definition) {
	/* Nama indeks dan tabel berasal dari konstanta di berkas ini, bukan dari
	   masukan pengguna, jadi tidak ada jalur injeksi di sini. */
	$sql = 'CREATE INDEX `' . $index_name . '` ON `' . $definition['table'] . '` ' . $definition['columns'];
	try {
		$db->query($sql);
		$created++;
		echo 'CREATED ' . $index_name . "\n";
	} catch (Throwable $exception) {
		$db->query("SELECT RELEASE_LOCK('doclinc_monitoring_indexes')");
		monitoring_index_fail('create_index_failed_' . $index_name);
	}
}

/* Statistik harus disegarkan; kalau tidak, optimizer masih memakai perkiraan lama
   dan EXPLAIN sesudahnya tidak mencerminkan keadaan baru. */
foreach (array_unique(array_map(function ($definition) {
	return $definition['table'];
}, DOCLINC_MONITORING_INDEXES)) as $table) {
	try {
		$db->query('ANALYZE TABLE `' . $table . '`');
	} catch (Throwable $exception) {
		// ANALYZE gagal bukan alasan membatalkan; hanya dicatat.
		echo 'WARN analyze_failed_' . $table . "\n";
	}
}

$db->query("SELECT RELEASE_LOCK('doclinc_monitoring_indexes')");

$verified = array();
foreach (DOCLINC_MONITORING_INDEXES as $index_name => $definition) {
	$existing = monitoring_index_existing($db, $definition['table']);
	$verified[$index_name] = isset($existing[$index_name]) ? 'present' : 'missing';
}

$explain_after = array();
foreach ($probe_queries as $label => $sql) {
	$explain_after[] = monitoring_index_explain($db, $label, $sql);
}

$all_present = !in_array('missing', $verified, true);
$reversible = array();
foreach (DOCLINC_MONITORING_INDEXES as $index_name => $definition) {
	$reversible[] = 'DROP INDEX ' . $index_name . ' ON ' . $definition['table'];
}

echo 'INDEXES_CREATED=' . $created . "\n";
foreach ($verified as $index_name => $state) {
	echo 'VERIFY ' . $index_name . '=' . $state . "\n";
}
foreach ($explain_after as $line) {
	echo 'EXPLAIN_AFTER ' . $line . "\n";
}
echo 'REVERSIBLE_BY=' . implode(';', $reversible) . "\n"
	. "DATA_WRITES_EXECUTED=true\nROWS_MODIFIED=0\nDESTRUCTIVE_STATEMENTS=0\n"
	. 'MIGRATION_RESULT=' . ($all_present ? 'PASS' : 'FAIL') . "\n";

exit($all_present ? 0 : 1);
