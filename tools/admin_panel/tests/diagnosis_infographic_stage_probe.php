<?php
/**
 * Probe agregasi infografis diagnosis (per kecamatan + seluruh kota).
 *
 * Tidak menghapus data lama: baris uji ditambahkan dengan kode yang tidak
 * dipakai data nyata, lalu hasilnya diperiksa sebagai DELTA. Semua tulisan
 * dibungkus transaksi luar dan di-ROLLBACK, jadi tabel kembali seperti semula
 * (dibuktikan di akhir).
 *
 * Jalankan di dalam container PHP:
 *   docker exec -e ALLOW_STAGE_PROBE=1 doclinc-php8100 \
 *     php /var/www/html/tools/admin_panel/tests/diagnosis_infographic_stage_probe.php
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
function kecamatan_id($db, $nama)
{
	$row = $db->query("SELECT id_kecamatan FROM kecamatan WHERE nama_kecamatan='" . $db->escape_str($nama) . "'")->row_array();
	return empty($row) ? 0 : (int) $row['id_kecamatan'];
}
function entries_for($payload, $nama_kecamatan)
{
	foreach ($payload['by_kecamatan'] as $row) {
		if ($row['kecamatan'] === $nama_kecamatan) { return (int) $row['entries']; }
	}
	return 0;
}
function top_entry($payload, $code)
{
	foreach ($payload['city']['top_codes'] as $row) {
		if ($row['code'] === $code) { return $row; }
	}
	return null;
}

echo "== DB: {$name} ==\n";
$filters = $model->normalizeFilters(array());

check('S1 skema diagnosis siap', $model->canQueryDiagnosis() === true);
check('S2 master wilayah siap', $model->canQueryWilayah() === true);
$date_expr = $model->dateExpression();
check('S3 waktu kanonik dipakai dan created_at TIDAK ikut',
	strpos($date_expr, 'clinical_finalized_at') !== false
	&& strpos($date_expr, 'completed_at') !== false
	&& strpos($date_expr, 'created_at') === false,
	$date_expr);
$normalized = $model->normalizeFilters(array('days' => 45, 'kecamatan_id' => -5, 'coding_state' => 'aneh'));
check('S4 filter asing jatuh ke default',
	$normalized['days'] === 30 && $normalized['kecamatan_id'] === 0 && $normalized['coding_state'] === '');

$baseline = $model->getInfographic($filters);
$baseline_kecamatan = array();
foreach ($baseline['by_kecamatan'] as $row) { $baseline_kecamatan[$row['kecamatan']] = (int) $row['entries']; }
check('S5 baseline: coded + belum dikode = total',
	$baseline['city']['coded'] + $baseline['city']['uncoded'] === $baseline['city']['total_entries'],
	json_encode($baseline['city']));
$sum_kecamatan = 0;
foreach ($baseline['by_kecamatan'] as $row) { $sum_kecamatan += (int) $row['entries']; }
check('S6 baseline: jumlah per kecamatan + tak terpetakan = total kota',
	$sum_kecamatan + $baseline['unknown_wilayah']['entries'] === $baseline['city']['total_entries'],
	'kecamatan=' . $sum_kecamatan . ' unknown=' . $baseline['unknown_wilayah']['entries'] . ' total=' . $baseline['city']['total_entries']);

$baseline_diag = count_rows($db, 'medicalrecord_diagnoses');
$baseline_records = count_rows($db, 'medicalrecords');
$baseline_requests = count_rows($db, 'requests');
$baseline_kec_rows = count_rows($db, 'kecamatan');
$baseline_pkm_rows = count_rows($db, 'puskesmas');

function insert_diagnosis($db, $record_id, $request_id, $position, $code, $label, $state, $user_id)
{
	$db->query(
		'INSERT INTO medicalrecord_diagnoses (medicalrecord_id, request_id, position, diagnosis_role, '
		. 'diagnosis_code, diagnosis_label, coding_state, display_text, created_by_user_id) '
		. 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
		array($record_id, $request_id, $position, $position === 1 ? 'primary' : 'secondary', $code, $label, $state, $label, $user_id)
	);
}

$db->query('START TRANSACTION');
$user_id = 9001;

/* Empat permintaan uji: dua terpetakan ke kecamatan berbeda, satu kode puskesmas
 * tidak dikenal, satu tanpa waktu selesai kanonik (hanya created_at). */
$db->query(
	"INSERT INTO requests (request_id, user_id, location, assigned_puskesmas_code, assigned_puskesmas_name, request_status, created_at, updated_at, completed_at) "
	. "VALUES (990001, ?, 'uji', '10280101', 'Puskesmas Cilegon', 'Completed', NOW(), NOW(), NOW())",
	array($user_id)
);
$db->query(
	"INSERT INTO requests (request_id, user_id, location, assigned_puskesmas_code, assigned_puskesmas_name, request_status, created_at, updated_at, completed_at) "
	. "VALUES (990002, ?, 'uji', '10280201', 'Puskesmas Cibeber', 'Completed', NOW(), NOW(), NOW())",
	array($user_id)
);
$db->query(
	"INSERT INTO requests (request_id, user_id, location, assigned_puskesmas_code, assigned_puskesmas_name, request_status, created_at, updated_at, completed_at) "
	. "VALUES (990003, ?, 'uji', 'KODE-UJI-TIDAK-DIKENAL', 'Puskesmas Uji', 'Completed', NOW(), NOW(), NOW())",
	array($user_id)
);
$db->query(
	"INSERT INTO requests (request_id, user_id, location, assigned_puskesmas_code, assigned_puskesmas_name, request_status, created_at, updated_at, completed_at) "
	. "VALUES (990004, ?, 'uji', '10280101', 'Puskesmas Cilegon', 'Completed', NOW(), NOW(), NULL)",
	array($user_id)
);

$db->query("INSERT INTO medicalrecords (request_id, diagnosis, clinical_finalized_at, created_at) VALUES (990001, 'uji', NOW(), NOW())");
$record_a = (int) $db->insert_id();
$db->query("INSERT INTO medicalrecords (request_id, diagnosis, clinical_finalized_at, created_at) VALUES (990002, 'uji', NOW(), NOW())");
$record_b = (int) $db->insert_id();
$db->query("INSERT INTO medicalrecords (request_id, diagnosis, clinical_finalized_at, created_at) VALUES (990003, 'uji', NOW(), NOW())");
$record_c = (int) $db->insert_id();
$db->query("INSERT INTO medicalrecords (request_id, diagnosis, clinical_finalized_at, created_at) VALUES (990004, 'uji', NULL, NOW())");
$record_d = (int) $db->insert_id();

insert_diagnosis($db, $record_a, 990001, 1, 'Z99', 'Uji kode A', 'coded', $user_id);
insert_diagnosis($db, $record_a, 990001, 2, 'Z98', 'Uji kode B', 'coded', $user_id);
insert_diagnosis($db, $record_b, 990002, 1, 'Z99', 'Uji kode A', 'coded', $user_id);
insert_diagnosis($db, $record_c, 990003, 1, null, 'Demam uji', 'uncoded', $user_id);
insert_diagnosis($db, $record_c, 990003, 2, 'Z96', 'Batuk uji', 'uncoded', $user_id);
insert_diagnosis($db, $record_d, 990004, 1, 'Z97', 'Uji tanpa waktu selesai', 'coded', $user_id);

$after = $model->getInfographic($filters);
$delta_total = $after['city']['total_entries'] - $baseline['city']['total_entries'];
$delta_coded = $after['city']['coded'] - $baseline['city']['coded'];
$delta_uncoded = $after['city']['uncoded'] - $baseline['city']['uncoded'];
check('A1 delta total 5 baris (baris tanpa waktu selesai dikecualikan)',
	$delta_total === 5, "total={$delta_total}");
check('A2 delta terkode 3, belum dikode 2',
	$delta_coded === 3 && $delta_uncoded === 2, "coded={$delta_coded} uncoded={$delta_uncoded}");
check('A3 coded + belum dikode tetap sama dengan total',
	$after['city']['coded'] + $after['city']['uncoded'] === $after['city']['total_entries']);

$delta_cilegon = entries_for($after, 'Cilegon') - (isset($baseline_kecamatan['Cilegon']) ? $baseline_kecamatan['Cilegon'] : 0);
$delta_cibeber = entries_for($after, 'Cibeber') - (isset($baseline_kecamatan['Cibeber']) ? $baseline_kecamatan['Cibeber'] : 0);
check('A4 pemetaan wilayah benar: Cilegon +2, Cibeber +1',
	$delta_cilegon === 2 && $delta_cibeber === 1, "cilegon={$delta_cilegon} cibeber={$delta_cibeber}");
$delta_unknown = $after['unknown_wilayah']['entries'] - $baseline['unknown_wilayah']['entries'];
check('A5 baris tanpa pemetaan masuk keranjang, tidak hilang', $delta_unknown === 2, "unknown={$delta_unknown}");
$sum_after = 0;
foreach ($after['by_kecamatan'] as $row) { $sum_after += (int) $row['entries']; }
check('A6 per kecamatan + tak terpetakan = total kota (setelah data uji)',
	$sum_after + $after['unknown_wilayah']['entries'] === $after['city']['total_entries']);

$top_z99 = top_entry($after, 'Z99');
check('A7 kode terbanyak: Z99 muncul dengan 2 baris dan label',
	$top_z99 !== null && (int) $top_z99['entries'] === 2 && $top_z99['label'] === 'Uji kode A',
	json_encode($top_z99));
check('A8 keranjang belum dikode menampilkan isinya, bukan angka kosong',
	count($after['city']['uncoded_top']) > 0, json_encode($after['city']['uncoded_top']));

$drill = $model->getCodeByKecamatan('Z99', $filters);
$drill_names = array();
foreach ($drill as $row) { $drill_names[$row['kecamatan']] = (int) $row['entries']; }
/* Diurutkan dulu: saat jumlahnya seri, urutan baris dari database bisa berubah,
 * jadi perbandingan tidak boleh bergantung urutan. */
ksort($drill_names);
check('A9 drill-down Z99 tersebar: 1 baris di Cibeber dan 1 di Cilegon',
	$drill_names === array('Cibeber' => 1, 'Cilegon' => 1), json_encode($drill_names));
check('A10 drill-down menolak kode di luar pola ICD-10',
	$model->getCodeByKecamatan('bukan-kode', $filters) === array());

$only_coded = $model->getInfographic(array('coding_state' => 'coded'));
check('A11 filter terkode hanya menghitung baris coded',
	$only_coded['city']['uncoded'] === 0
	&& $only_coded['city']['total_entries'] === $after['city']['coded']);

$cilegon_id = kecamatan_id($db, 'Cilegon');
$only_cilegon = $model->getInfographic(array('kecamatan_id' => $cilegon_id));
check('A12 filter kecamatan menyaring agregat kota',
	$only_cilegon['city']['total_entries'] === entries_for($after, 'Cilegon')
	&& $only_cilegon['unknown_wilayah']['entries'] === 0);

$recent = $model->getInfographic(array('days' => 7));
check('A13 baris yang hanya punya created_at tidak ikut periode 7 hari',
	top_entry($recent, 'Z97') === null);
$all_time = $model->getInfographic(array('days' => 0));
check('A14 baris yang sama tetap terhitung pada seluruh periode',
	top_entry($all_time, 'Z97') !== null);

$db->query('DELETE FROM `puskesmas`');
$db->query('DELETE FROM `kecamatan`');
$no_wilayah = $model->getInfographic($filters);
check('W1 tanpa master wilayah: rincian kecamatan kosong tapi total kota tetap ada',
	$no_wilayah['wilayah_available'] === false
	&& $no_wilayah['reason'] === 'wilayah_master_empty'
	&& $no_wilayah['by_kecamatan'] === array()
	&& $no_wilayah['city']['total_entries'] === $after['city']['total_entries'],
	$no_wilayah['reason']);

$db->query('ROLLBACK');

check('E1 tabel kembali seperti semula',
	count_rows($db, 'medicalrecord_diagnoses') === $baseline_diag
	&& count_rows($db, 'medicalrecords') === $baseline_records
	&& count_rows($db, 'requests') === $baseline_requests
	&& count_rows($db, 'kecamatan') === $baseline_kec_rows
	&& count_rows($db, 'puskesmas') === $baseline_pkm_rows,
	'diag=' . count_rows($db, 'medicalrecord_diagnoses') . ' kec=' . count_rows($db, 'kecamatan') . ' pkm=' . count_rows($db, 'puskesmas'));

echo "\n" . ($fails === 0 ? 'PROBE_RESULT=PASS' : "PROBE_RESULT=FAIL (gagal={$fails})") . "\n";
exit($fails === 0 ? 0 : 1);
