<?php
/**
 * Probe panel "Pengodean diagnosis": pemisahan kode dari teks, pemeriksaan
 * master ICD-10, analisis, dan penulisan balik kode.
 *
 * Yang diperiksa bukan hanya "berhasil" tetapi batasnya:
 *   - teks yang memuat kode sah  -> dikodekan otomatis;
 *   - teks yang memuat KATEGORI (A09) -> tidak ditulis, harus dipilih manual;
 *   - teks tanpa kode            -> tidak disentuh sampai admin memilih;
 *   - kode di luar master        -> ditolak, walaupun bentuknya benar;
 *   - baris yang sudah punya kode -> tidak pernah ditimpa;
 *   - `display_text` tidak pernah diubah; `diagnosis_label` dibersihkan.
 *
 * Semua tulisan berada di dalam transaksi luar yang di-ROLLBACK di akhir, jadi
 * tabel kembali seperti semula (dibuktikan dengan menghitung ulang baris).
 *
 * Jalankan di dalam container PHP:
 *   ALLOW_STAGE_PROBE=1 DB_NAME=doclinc_stage_local_test DB_USER=... DB_PASS=... \
 *     ~/.local/phpbulk/php tools/admin_panel/tests/diagnosis_coding_stage_probe.php
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
/** Stub superobjek: cukup `db`, `load`, dan model ICD-10 yang sudah dibuat. */
class CodingProbeApp
{
	public $db;
	public $load;
	public $Icd10_m;
	public function __construct($db) { $this->db = $db; $this->load = new CodingProbeLoader(); }
}
class CodingProbeLoader
{
	/** Model ICD-10 disuntikkan langsung oleh probe, bukan lewat loader HMVC. */
	public function model($name) { return true; }
}
$GLOBALS['coding_probe_app'] = new CodingProbeApp($db);
if (!function_exists('get_instance')) { function &get_instance() { return $GLOBALS['coding_probe_app']; } }

require_once APPPATH . 'modules/icd10/models/Icd10_m.php';
$GLOBALS['coding_probe_app']->Icd10_m = new Icd10_m();

require_once $root . 'application/libraries/Icd10_code_text.php';
require_once APPPATH . 'modules/diagnosis/models/Diagnosis_infographic_m.php';
require_once APPPATH . 'modules/diagnosis/models/Diagnosis_coding_m.php';

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

$infographic = new Diagnosis_infographic_m();
$coding = new Diagnosis_coding_m();

echo "== DB: {$name} ==\n";
check('S1 skema pengodean siap', $coding->schemaReady() === true);
check('S2 master ICD-10 terbaca', $coding->masterAvailable() === true, count($coding->master()) . ' kode');
$master = $coding->master();
check('S3 L20.9 ada dan boleh dipakai', isset($master['L20.9']) && $master['L20.9']['selectable'] === true);
check('S4 A09 ada tetapi kategori', isset($master['A09']) && $master['A09']['selectable'] === false);
check('S5 B12 tidak ada di master', !isset($master['B12']));

echo "-- P1 parser memisahkan kode dari teks\n";
$parser_cases = array(
	'Uji dermatitis (L20.9)' => array('L20.9', 'Uji dermatitis'),
	'L20.9 — Uji dermatitis' => array('L20.9', 'Uji dermatitis'),
	'L20.9 Uji dermatitis' => array('L20.9', 'Uji dermatitis'),
	'Uji dermatitis - L20.9' => array('L20.9', 'Uji dermatitis'),
	'L20.9' => array('L20.9', ''),
	'UJI ISPA' => array(null, 'UJI ISPA'),
	'Uji vitamin B12' => array('B12', 'Uji vitamin'),
	'Uji anemia (Hb 8)' => array(null, 'Uji anemia (Hb 8)'),
);
foreach ($parser_cases as $input => $expected) {
	$parsed = Icd10_code_text::extract($input);
	check('P1 "' . $input . '"', $parsed['code'] === $expected[0] && $parsed['label'] === $expected[1],
		var_export($parsed['code'], true) . ' / ' . $parsed['label']);
}
check('P2 huruf kecil bukan kode', Icd10_code_text::isCode('l20.9') === false);
check('P3 rentang bab bukan kode', Icd10_code_text::isCode('A00-A09') === false);

$baseline_diag = count_rows($db, 'medicalrecord_diagnoses');
$baseline_records = count_rows($db, 'medicalrecords');
$baseline_requests = count_rows($db, 'requests');

/* trans_begin(), bukan START TRANSACTION mentah: model memakai trans_begin()/
 * trans_commit() sendiri, dan transaksi bersarang hanya menjadi SAVEPOINT bila
 * penghitung internal CodeIgniter sudah > 0. Dengan START TRANSACTION mentah,
 * transaksi model akan COMMIT dan ROLLBACK di akhir tidak membatalkan apa pun. */
$db->trans_begin();
$user_id = 9001;
/* Kolom waktu kanonik tidak selalu ada (fixture lokal tidak memilikinya),
 * jadi rekam medis diisi hanya dengan kolom yang benar-benar ada. */
function insert_record($db, $request_id)
{
	$columns = array('request_id', 'diagnosis', 'created_at');
	$values = array($request_id, 'uji', date('Y-m-d H:i:s'));
	if ($db->field_exists('clinical_finalized_at', 'medicalrecords')) {
		$columns[] = 'clinical_finalized_at';
		$values[] = date('Y-m-d H:i:s');
	}
	$placeholders = implode(', ', array_fill(0, count($columns), '?'));
	$db->query('INSERT INTO medicalrecords (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')', $values);
	return (int) $db->insert_id();
}

$db->query(
	"INSERT INTO requests (request_id, user_id, location, assigned_puskesmas_code, assigned_puskesmas_name, request_status, created_at, updated_at, completed_at) "
	. "VALUES (990101, ?, 'uji', '10280101', 'Puskesmas Cilegon', 'Completed', NOW(), NOW(), NOW())",
	array($user_id)
);
$record_id = insert_record($db, 990101);

function insert_diagnosis($db, $record_id, $request_id, $position, $code, $label, $state, $display, $user_id)
{
	$db->query(
		'INSERT INTO medicalrecord_diagnoses (medicalrecord_id, request_id, position, diagnosis_role, '
		. 'diagnosis_code, diagnosis_label, coding_state, display_text, created_by_user_id) '
		. 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
		array($record_id, $request_id, $position, $position === 1 ? 'primary' : 'secondary', $code, $label, $state, $display, $user_id)
	);
	return (int) $db->insert_id();
}

/* Enam baris uji, satu untuk setiap keadaan yang harus dibedakan panel. */
$id_ready_a = insert_diagnosis($db, $record_id, 990101, 1, null, 'Uji dermatitis (L20.9)', 'uncoded', 'Uji dermatitis (L20.9)', $user_id);
$id_ready_b = insert_diagnosis($db, $record_id, 990101, 2, null, 'Uji migrain (G43.9)', 'uncoded', 'Uji migrain (G43.9)', $user_id);
$id_manual = insert_diagnosis($db, $record_id, 990101, 3, null, 'UJI SESAK', 'uncoded', 'UJI SESAK', $user_id);
$id_category = insert_diagnosis($db, $record_id, 990101, 4, null, 'Uji kategori (A09)', 'uncoded', 'Uji kategori (A09)', $user_id);
$id_unknown = insert_diagnosis($db, $record_id, 990101, 5, null, 'Uji vitamin B12', 'uncoded', 'Uji vitamin B12', $user_id);
/* Baris yang SUDAH dikode: tidak boleh disentuh, walau teksnya memuat kode. */
$db->query(
	"INSERT INTO requests (request_id, user_id, location, assigned_puskesmas_code, assigned_puskesmas_name, request_status, created_at, updated_at, completed_at) "
	. "VALUES (990102, ?, 'uji', '10280101', 'Puskesmas Cilegon', 'Completed', NOW(), NOW(), NOW())",
	array($user_id)
);
$record_b = insert_record($db, 990102);
$id_already = insert_diagnosis($db, $record_b, 990102, 1, 'I10', 'Uji hipertensi', 'coded', 'Uji hipertensi (I10)', $user_id);

/* Analisis hanya atas kelompok uji, supaya angka yang diperiksa tidak
 * bergantung pada isi database tempat probe dijalankan. */
function test_groups($model)
{
	$groups = array();
	foreach ($model->getUncodedTextGroups(500) as $group) {
		if (stripos($group['text'], 'Uji ') === 0 || stripos($group['text'], 'UJI ') === 0) {
			$groups[] = $group;
		}
	}
	return $groups;
}
function group_by_text(array $analysis)
{
	$map = array();
	foreach ($analysis['groups'] as $group) { $map[$group['text']] = $group; }
	return $map;
}

$groups = test_groups($infographic);
check('A1 enam teks uji terbaca dari database (5 belum dikode + 1 sudah, teks tertulis beda)',
	count($groups) === 5, json_encode(array_column($groups, 'text')));
$analysis = $coding->analyze($groups);
$by_text = group_by_text($analysis);
check('A2 teks berkode sah -> READY',
	$by_text['Uji dermatitis (L20.9)']['status'] === 'ready' && $by_text['Uji dermatitis (L20.9)']['code'] === 'L20.9',
	$by_text['Uji dermatitis (L20.9)']['status']);
check('A3 label yang akan ditulis tanpa kode',
	$by_text['Uji dermatitis (L20.9)']['write_label'] === 'Uji dermatitis',
	$by_text['Uji dermatitis (L20.9)']['write_label']);
check('A4 kategori A09 -> REVIEW, tidak ditulis otomatis',
	$by_text['Uji kategori (A09)']['status'] === 'review' && $by_text['Uji kategori (A09)']['reason'] === 'code_not_selectable',
	$by_text['Uji kategori (A09)']['status'] . '/' . $by_text['Uji kategori (A09)']['reason']);
check('A5 kode di luar master -> REVIEW',
	$by_text['Uji vitamin B12']['status'] === 'review' && $by_text['Uji vitamin B12']['reason'] === 'code_not_in_master',
	$by_text['Uji vitamin B12']['reason']);
check('A6 teks tanpa kode -> MANUAL',
	$by_text['UJI SESAK']['status'] === 'manual' && $by_text['UJI SESAK']['code'] === null,
	$by_text['UJI SESAK']['status']);
check('A7 total kelompok uji: 2 siap, 2 perlu diperiksa, 1 manual',
	$analysis['totals']['ready_groups'] === 2 && $analysis['totals']['review_groups'] === 2 && $analysis['totals']['manual_groups'] === 1,
	json_encode($analysis['totals']));

$repair = $coding->repair(test_groups($infographic));
check('B1 kodekan otomatis menulis 2 baris dari 2 teks', $repair['ok'] === true && $repair['rows'] === 2 && $repair['texts'] === 2,
	json_encode($repair));
$row = $db->query('SELECT * FROM medicalrecord_diagnoses WHERE diagnosis_id = ?', array($id_ready_a))->row_array();
check('B2 kode & status ditulis', $row['diagnosis_code'] === 'L20.9' && $row['coding_state'] === 'coded', json_encode($row));
check('B3 label dibersihkan dari kode', $row['diagnosis_label'] === 'Uji dermatitis', $row['diagnosis_label']);
check('B4 teks asli tidak diubah', $row['display_text'] === 'Uji dermatitis (L20.9)', $row['display_text']);
check('B5 sumber & versi referensi dicatat',
	$row['reference_source'] === Diagnosis_coding_m::SOURCE_DATASET && $row['reference_version'] === Diagnosis_coding_m::SOURCE_VERSION,
	$row['reference_source'] . '/' . $row['reference_version']);

$manual_row = $db->query('SELECT coding_state, diagnosis_code FROM medicalrecord_diagnoses WHERE diagnosis_id = ?', array($id_manual))->row_array();
check('B6 teks tanpa kode tidak disentuh', $manual_row['coding_state'] === 'uncoded' && $manual_row['diagnosis_code'] === null, json_encode($manual_row));
$category_row = $db->query('SELECT coding_state, diagnosis_code FROM medicalrecord_diagnoses WHERE diagnosis_id = ?', array($id_category))->row_array();
check('B7 kategori tidak dipakai otomatis', $category_row['coding_state'] === 'uncoded' && $category_row['diagnosis_code'] === null, json_encode($category_row));
$already_row = $db->query('SELECT coding_state, diagnosis_code, diagnosis_label FROM medicalrecord_diagnoses WHERE diagnosis_id = ?', array($id_already))->row_array();
check('B8 baris yang sudah dikode tidak ditimpa',
	$already_row['coding_state'] === 'coded' && $already_row['diagnosis_code'] === 'I10' && $already_row['diagnosis_label'] === 'Uji hipertensi',
	json_encode($already_row));

$repeat = $coding->repair(test_groups($infographic));
check('B9 menjalankan ulang tidak mengubah apa pun (idempoten)', $repeat['ok'] === true && $repeat['rows'] === 0, json_encode($repeat));

echo "-- C jalur manual\n";
$reject = $coding->assign('Uji kategori (A09)', 'A09');
check('C1 menolak kategori A09', $reject['ok'] === false && $reject['reason'] === 'code_not_selectable', $reject['reason']);
$reject = $coding->assign('Uji kategori (A09)', 'XYZ');
check('C2 menolak bentuk kode yang salah', $reject['ok'] === false && $reject['reason'] === 'invalid_code', $reject['reason']);
$reject = $coding->assign('Uji kategori (A09)', 'B12');
check('C3 menolak kode di luar master', $reject['ok'] === false && $reject['reason'] === 'code_not_in_master', $reject['reason']);
$reject = $coding->assign('', 'A09.0');
check('C4 menolak teks kosong', $reject['ok'] === false && $reject['reason'] === 'text_missing', $reject['reason']);

$assign = $coding->assign('UJI SESAK', 'R06.0');
check('C5 teks tanpa kode berhasil dikode manual', $assign['ok'] === true && $assign['rows'] === 1, json_encode($assign));
$manual_row = $db->query('SELECT * FROM medicalrecord_diagnoses WHERE diagnosis_id = ?', array($id_manual))->row_array();
check('C6 label memakai teks asli (tanpa judul master berbahasa Inggris)',
	$manual_row['diagnosis_code'] === 'R06.0' && $manual_row['diagnosis_label'] === 'UJI SESAK' && $manual_row['coding_state'] === 'coded',
	json_encode($manual_row));
$assign = $coding->assign('UJI SESAK', 'R06.0');
check('C7 mengulang kode yang sama pada baris yang sudah dikode tidak mengubah apa pun', $assign['ok'] === true && $assign['rows'] === 0, json_encode($assign));

$assign = $coding->assign('Uji kategori (A09)', 'A09.0');
check('C8 kategori dapat dikode setelah admin memilih subkode', $assign['ok'] === true && $assign['rows'] === 1, json_encode($assign));
$category_row = $db->query('SELECT diagnosis_code, diagnosis_label FROM medicalrecord_diagnoses WHERE diagnosis_id = ?', array($id_category))->row_array();
check('C9 label dibersihkan dari kode kategori lama',
	$category_row['diagnosis_code'] === 'A09.0' && $category_row['diagnosis_label'] === 'Uji kategori',
	json_encode($category_row));

$assign = $coding->assign('Uji vitamin B12', 'D51.9');
check('C10 kode di luar master dapat diganti setelah dipilih manual', $assign['ok'] === true && $assign['rows'] === 1, json_encode($assign));
$unknown_row = $db->query('SELECT diagnosis_code, diagnosis_label FROM medicalrecord_diagnoses WHERE diagnosis_id = ?', array($id_unknown))->row_array();
check('C11 label tidak membuang "B12" saat kode pilihan berbeda',
	$unknown_row['diagnosis_code'] === 'D51.9' && $unknown_row['diagnosis_label'] === 'Uji vitamin B12',
	json_encode($unknown_row));

echo "-- D dampak ke infografis\n";
$filters = $infographic->normalizeFilters(array());
$payload = $infographic->getInfographic($filters);
$delta_coded = $payload['city']['coded'] - 0;
check('D1 baris uji ikut terhitung sebagai terkode', $payload['city']['coded'] > 0, 'coded=' . $delta_coded);
$top = array();
foreach ($payload['city']['top_codes'] as $code_row) { $top[$code_row['code']] = (int) $code_row['entries']; }
check('D2 L20.9 hasil pemulihan muncul di peringkat kota', isset($top['L20.9']) && $top['L20.9'] >= 1, json_encode($top));
check('D3 kategori/teks bebas tidak pernah masuk peringkat tanpa kode',
	!isset($top['UJI SESAK']) && !isset($top['B12']));

/* Pencarian manual: nama Indonesia ikut dihitung sebagai kecocokan, supaya
 * admin yang mengetik "demam" tidak diberi peringatan "hanya cocok sebagian"
 * untuk kode yang memang ada di daftar (R50.9 - Demam, tidak spesifik). */
$search_id = $coding->search('demam', 10);
check('D4 kata Indonesia menemukan kode walau judul resminya berbahasa Inggris',
	(int) $search_id['total'] > 0
		&& stripos((string) $search_id['rows'][0]['label_id'], 'demam') === 0,
	json_encode(array_slice($search_id['rows'], 0, 1)));
check('D5 kecocokan nama Indonesia tidak ditandai lemah', $search_id['weak'] === false,
	'weak=' . ($search_id['weak'] ? 'true' : 'false'));
$search_loose = $coding->search('itis', 10);
check('D6 kata yang hanya cocok di tengah judul tetap ditandai lemah',
	(int) $search_loose['total'] === 0 || $search_loose['weak'] === true,
	'total=' . $search_loose['total'] . ' weak=' . ($search_loose['weak'] ? 'true' : 'false'));

echo "-- E rollback\n";
$db->trans_rollback();
check('E1 jumlah baris diagnosis kembali seperti semula', count_rows($db, 'medicalrecord_diagnoses') === $baseline_diag,
	count_rows($db, 'medicalrecord_diagnoses') . ' vs ' . $baseline_diag);
check('E2 jumlah rekam medis kembali seperti semula', count_rows($db, 'medicalrecords') === $baseline_records,
	count_rows($db, 'medicalrecords') . ' vs ' . $baseline_records);
check('E3 jumlah permintaan kembali seperti semula', count_rows($db, 'requests') === $baseline_requests,
	count_rows($db, 'requests') . ' vs ' . $baseline_requests);

echo "\n" . ($fails === 0 ? "SEMUA PASS\n" : "{$fails} GAGAL\n");
exit($fails === 0 ? 0 : 1);



