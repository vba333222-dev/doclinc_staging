<?php
/**
 * Bukti EXPLAIN untuk indeks analitik infografis.
 *
 * Pertanyaan yang dijawab: apakah `idx_medical_finalized_at` benar-benar
 * dipakai oleh query agregasi? Skrip ini menambah volume uji di dalam
 * transaksi (lalu ROLLBACK), mengambil SQL ASLI yang dihasilkan model lewat
 * `$db->queries`, dan membandingkan rencana eksekusi:
 *   1. bentuk model apa adanya (COALESCE waktu kanonik)
 *   2. bentuk yang sama dengan filter tanggal langsung ke kolom
 *   3. bentuk (2) dengan `IGNORE INDEX` sebagai pembanding tanpa indeks
 *
 * Jalankan:
 *   docker exec -e ALLOW_STAGE_PROBE=1 doclinc-php8100 \
 *     php /var/www/html/tools/admin_panel/tests/diagnosis_infographic_index_evidence.php
 */
$root = dirname(__DIR__, 3) . '/';
define('BASEPATH', $root . 'system/');
define('APPPATH', $root . 'admin_menu/application/');
define('FCPATH', $root);
define('ENVIRONMENT', 'testing');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';
$port = (int) (getenv('DB_PORT') ?: 3306);
if (getenv('ALLOW_STAGE_PROBE') !== '1') { fwrite(STDERR, "refuse: set ALLOW_STAGE_PROBE=1\n"); exit(2); }
if (stripos($name, 'stage') === false) { fwrite(STDERR, "refuse: bukan database stage ($name)\n"); exit(2); }

require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';
$db = DB(array(
	'dsn' => '', 'hostname' => $host, 'username' => $user, 'password' => $pass,
	'database' => $name, 'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false,
	'db_debug' => false, 'cache_on' => false, 'cachedir' => '', 'char_set' => 'utf8',
	'dbcollat' => 'utf8_general_ci', 'port' => $port, 'stricton' => true,
), true);
if (!$db) { fwrite(STDERR, "refuse: koneksi gagal\n"); exit(2); }

require_once BASEPATH . 'core/Model.php';
class ProbeApp { public $db; public function __construct($db) { $this->db = $db; } }
$GLOBALS['probe_app'] = new ProbeApp($db);
if (!function_exists('get_instance')) { function &get_instance() { return $GLOBALS['probe_app']; } }

require_once APPPATH . 'modules/diagnosis/models/Diagnosis_infographic_m.php';
$model = new Diagnosis_infographic_m();

$fails = 0;
function check($label, $cond, $detail = '')
{
	global $fails;
	if ($cond) { echo "PASS  {$label}\n"; return; }
	$fails++;
	echo "FAIL  {$label}  {$detail}\n";
}
function count_rows($db, $table)
{
	$row = $db->query('SELECT COUNT(*) AS total FROM `' . $table . '`')->row_array();
	return (int) $row['total'];
}
/** Ringkas satu baris EXPLAIN menjadi teks yang bisa dibaca mata. */
function explain_summary($db, $sql, $label)
{
	$rows = $db->query('EXPLAIN ' . $sql)->result_array();
	$parts = array();
	foreach ($rows as $row) {
		$parts[] = ($row['table'] ?? '?') . ':type=' . ($row['type'] ?? '?')
			. ',key=' . (($row['key'] === null || $row['key'] === '') ? 'NULL' : $row['key'])
			. ',rows=' . ($row['rows'] ?? '?');
	}
	$summary = implode(' | ', $parts);
	echo "EXPLAIN {$label}: {$summary}\n";
	return $rows;
}
function explain_uses_key($rows, $key)
{
	foreach ($rows as $row) {
		if (($row['key'] ?? '') === $key) { return true; }
	}
	return false;
}

echo "== DB: {$name} ==\n";

/** Indeks ada? Dibaca dari information_schema, bukan ditebak. */
function index_present($db)
{
	$row = $db->query(
		"SELECT COUNT(*) AS total FROM information_schema.statistics "
		. "WHERE table_schema = DATABASE() AND table_name = 'medicalrecords' "
		. "AND index_name = 'idx_medical_finalized_at'"
	)->row_array();
	return (int) $row['total'] === 1;
}

check('E1 keadaan awal: idx_medical_finalized_at memang tidak ada', index_present($db) === false);

/* Indeks dibuat SEMENTARA untuk membuktikan apakah ia berguna, lalu dibuang lagi.
 * Dibuat di luar transaksi: DDL melakukan commit implisit, jadi kalau dibuat di
 * dalam transaksi, ROLLBACK data uji akan ikut gagal dan data uji tertinggal. */
$db->query('CREATE INDEX idx_medical_finalized_at ON medicalrecords (clinical_finalized_at)');
check('E1b indeks sementara berhasil dibuat', index_present($db) === true);

$baseline = array(
	'diag' => count_rows($db, 'medicalrecord_diagnoses'),
	'records' => count_rows($db, 'medicalrecords'),
	'requests' => count_rows($db, 'requests'),
);

$db->query('START TRANSACTION');
$volume_records = 2000;
$per_record = 3;
$batch = 500;
for ($offset = 0; $offset < $volume_records; $offset += $batch) {
	$request_values = array();
	$record_values = array();
	for ($i = $offset; $i < $offset + $batch; $i++) {
		$request_id = 900000 + $i;
		$request_values[] = '(' . $request_id . ", 9001, 'volume uji', '10280101', 'Puskesmas Cilegon', 'Completed', "
			. 'DATE_SUB(NOW(), INTERVAL ' . ($i % 400) . ' DAY), NOW(), DATE_SUB(NOW(), INTERVAL ' . ($i % 400) . ' DAY))';
		$record_values[] = '(' . $request_id . ', ' . $request_id . ", 'volume', DATE_SUB(NOW(), INTERVAL " . ($i % 400) . ' DAY))';
	}
	$db->query(
		'INSERT INTO requests (request_id, user_id, location, assigned_puskesmas_code, assigned_puskesmas_name, '
		. 'request_status, created_at, updated_at, completed_at) VALUES ' . implode(',', $request_values)
	);
	$db->query('INSERT INTO medicalrecords (record_id, request_id, diagnosis, clinical_finalized_at) VALUES ' . implode(',', $record_values));
}
$codes = array('A09', 'J06.9', 'B34.9', 'I10', 'E11.9', 'K30');
$diagnosis_values = array();
for ($i = 0; $i < $volume_records; $i++) {
	$record_id = 900000 + $i;
	for ($position = 1; $position <= $per_record; $position++) {
		$code = $codes[($i + $position) % count($codes)];
		$role = $position === 1 ? 'primary' : 'secondary';
		$diagnosis_values[] = '(' . $record_id . ', ' . (900000 + $i) . ', ' . $position . ", '" . $role . "', '"
			. $code . "', 'Uji " . $code . "', 'coded', 'Uji " . $code . "', 9001)";
	}
}
foreach (array_chunk($diagnosis_values, 1000) as $chunk) {
	$db->query(
		'INSERT INTO medicalrecord_diagnoses (medicalrecord_id, request_id, position, diagnosis_role, '
		. 'diagnosis_code, diagnosis_label, coding_state, display_text, created_by_user_id) VALUES ' . implode(',', $chunk)
	);
}
$volume_diag = count_rows($db, 'medicalrecord_diagnoses') - $baseline['diag'];
check('E2 volume uji masuk', $volume_diag === $volume_records * $per_record, 'baris=' . $volume_diag);

$filters = array('days' => 30);
$db->save_queries = true;
$started = microtime(true);
$payload = $model->getInfographic($filters);
$elapsed = (microtime(true) - $started) * 1000;
$db->save_queries = false;

$date_expr = $model->dateExpression();
$model_sql = '';
foreach ($db->queries as $query) {
	if (stripos($query, 'medicalrecord_diagnoses') !== false
		&& stripos($query, 'GROUP BY') !== false
		&& strpos($query, $date_expr) !== false) {
		$model_sql = $query;
		break;
	}
}
check('E3 SQL agregasi kota milik model tertangkap', $model_sql !== '');
echo "INFORMASI: SQL model = {$model_sql}\n";
echo 'INFORMASI: baris dalam jendela 30 hari = ' . $payload['city']['total_entries'] . "\n";

/* Bentuk pembanding: filter tanggal menunjuk kolom apa adanya, tanpa COALESCE.
 * Dua bentuk penggantian ditangani karena model bisa memakai (atau tidak lagi
 * memakai) pembungkus DATE(). */
$direct_sql = str_replace(array('DATE(' . $date_expr . ')', $date_expr), 'm.clinical_finalized_at', $model_sql);
$ignore_sql = str_replace('`medicalrecords` `m`', '`medicalrecords` `m` IGNORE INDEX (idx_medical_finalized_at)', $direct_sql);
check('E4 SQL pembanding terbentuk', $direct_sql !== $model_sql && $ignore_sql !== $direct_sql);

$plan_model = explain_summary($db, $model_sql, 'model apa adanya (COALESCE)');
$plan_direct = explain_summary($db, $direct_sql, 'filter tanggal langsung ke kolom');
$plan_ignore = explain_summary($db, $ignore_sql, 'filter langsung + IGNORE INDEX');

check('E5 temuan: bentuk COALESCE memindai penuh dan TIDAK bisa memakai indeks tanggal',
	explain_uses_key($plan_model, 'idx_medical_finalized_at') === false);
check('E6 filter tanggal langsung ke kolom memakai idx_medical_finalized_at',
	explain_uses_key($plan_direct, 'idx_medical_finalized_at') === true);
$rows_direct = 0;
$rows_ignore = 0;
foreach ($plan_direct as $row) { if (($row['table'] ?? '') === 'm') { $rows_direct = (int) $row['rows']; } }
foreach ($plan_ignore as $row) { if (($row['table'] ?? '') === 'm') { $rows_ignore = (int) $row['rows']; } }
check('E7 tanpa indeks baris yang diperiksa jauh lebih banyak',
	$rows_direct > 0 && $rows_direct < $rows_ignore, "dengan_indeks={$rows_direct} tanpa_indeks={$rows_ignore}");
check('E8 agregasi penuh pada volume uji tetap cepat',
	$elapsed < 2000, sprintf('%.0f ms', $elapsed));
echo 'INFORMASI: waktu agregasi (7 query) = ' . sprintf('%.0f ms', $elapsed) . "\n";

$db->query('ROLLBACK');
check('E9 tabel kembali seperti semula',
	count_rows($db, 'medicalrecord_diagnoses') === $baseline['diag']
	&& count_rows($db, 'medicalrecords') === $baseline['records']
	&& count_rows($db, 'requests') === $baseline['requests'],
	'diag=' . count_rows($db, 'medicalrecord_diagnoses') . ' records=' . count_rows($db, 'medicalrecords')
	. ' requests=' . count_rows($db, 'requests'));

/* Indeks sementara dibuang lagi: skrip ini hanya mengukur, tidak mengubah skema.
 * Bila skrip mati di tengah jalan, bersihkan dengan:
 *   DROP INDEX idx_medical_finalized_at ON medicalrecords; */
$db->query('DROP INDEX idx_medical_finalized_at ON medicalrecords');
check('E10 keadaan akhir: indeks sementara sudah dibuang', index_present($db) === false);

echo "\n" . ($fails === 0 ? 'EVIDENCE_RESULT=PASS' : "EVIDENCE_RESULT=FAIL (gagal={$fails})") . "\n";
exit($fails === 0 ? 0 : 1);
