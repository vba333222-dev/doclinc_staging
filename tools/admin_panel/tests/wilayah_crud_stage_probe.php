<?php
/**
 * Probe aturan master wilayah (kecamatan + puskesmas).
 *
 * Yang diuji adalah pagar-pagarnya, bukan hanya jalur bahagianya: nama ganda,
 * kode ganda, kode yang masih dipakai permintaan, dan kecamatan yang masih
 * berisi puskesmas. Semua tulisan dibungkus transaksi lalu di-ROLLBACK, jadi
 * tabel wilayah kembali seperti semula (dibuktikan di akhir).
 *
 * Jalankan:
 *   docker exec -e ALLOW_STAGE_PROBE=1 doclinc-php8100 \
 *     php /var/www/html/tools/admin_panel/tests/wilayah_crud_stage_probe.php
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

require_once APPPATH . 'modules/wilayah/models/Wilayah_m.php';
require_once APPPATH . 'modules/diagnosis/models/Diagnosis_infographic_m.php';
$wilayah = new Wilayah_m();
$infographic = new Diagnosis_infographic_m();

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

echo "== DB: {$name} ==\n";
check('W1 skema wilayah siap', $wilayah->schemaReady() === true);

$baseline_kecamatan = count_rows($db, 'kecamatan');
$baseline_puskesmas = count_rows($db, 'puskesmas');
$existing = $wilayah->getKecamatanList();
check('W2 master wilayah terisi', $baseline_kecamatan > 0 && $baseline_puskesmas > 0,
	'kecamatan=' . $baseline_kecamatan . ' puskesmas=' . $baseline_puskesmas);

$db->query('START TRANSACTION');

$target_kecamatan = $existing[0];
$nama_uji = 'Kecamatan Uji Probe';

/* Nama ganda harus terdeteksi tanpa membedakan huruf besar/kecil. */
check('W3 nama kecamatan yang sudah ada terdeteksi',
	$wilayah->kecamatanNameExists((string) $target_kecamatan->nama_kecamatan) === true);
check('W4 nama kecamatan terdeteksi walau beda huruf besar/kecil',
	$wilayah->kecamatanNameExists(strtoupper((string) $target_kecamatan->nama_kecamatan)) === true);

$new_kecamatan_id = $wilayah->createKecamatan($nama_uji);
check('W5 kecamatan uji dibuat', $new_kecamatan_id > 0 && $wilayah->getKecamatanById($new_kecamatan_id) !== null);
check('W6 nama kecamatan uji kini terdeteksi ganda', $wilayah->kecamatanNameExists($nama_uji) === true);
check('W7 saat diubah, kecamatan tidak dianggap bentrok dengan dirinya sendiri',
	$wilayah->kecamatanNameExists($nama_uji, $new_kecamatan_id) === false);

$kode_uji = 'PROBE-9911';
$new_puskesmas_id = $wilayah->createPuskesmas($kode_uji, 'Puskesmas Uji Probe', $new_kecamatan_id);
$created = $wilayah->getPuskesmasById($new_puskesmas_id);
check('W8 puskesmas uji dibuat dan menunjuk kecamatan uji',
	$created !== null && (int) $created->id_kecamatan === $new_kecamatan_id);

check('W9 kode ganda terdeteksi walau beda huruf besar/kecil',
	$wilayah->codeExists(strtolower($kode_uji)) === true);
check('W10 kode uji belum dipakai permintaan', $wilayah->codeUsageCount($kode_uji) === 0);
check('W11 kecamatan uji terhitung punya 1 puskesmas',
	$wilayah->puskesmasCountInKecamatan($new_kecamatan_id) === 1);

$wilayah->updatePuskesmas($new_puskesmas_id, $kode_uji, 'Puskesmas Uji Probe (diubah)', (int) $target_kecamatan->id_kecamatan);
$updated = $wilayah->getPuskesmasById($new_puskesmas_id);
check('W12 ubah puskesmas memindahkan kecamatan dan mengubah nama',
	$updated !== null
	&& (int) $updated->id_kecamatan === (int) $target_kecamatan->id_kecamatan
	&& (string) $updated->nama_puskesmas === 'Puskesmas Uji Probe (diubah)');
check('W13 kecamatan uji kembali kosong setelah puskesmas dipindah',
	$wilayah->puskesmasCountInKecamatan($new_kecamatan_id) === 0);

/* Kode nyata dari data staging: masih dipakai permintaan, jadi pagar hapus aktif. */
$used_code = '';
foreach ($wilayah->getPuskesmasList() as $row) {
	if ((int) $row->usage_count > 0) { $used_code = (string) $row->kode_pkm; break; }
}
check('W14 ada kode puskesmas yang dipakai permintaan (dasar uji pagar hapus)', $used_code !== '');
check('W15 hitungan pemakaian kode nyata lebih dari nol', $wilayah->codeUsageCount($used_code) > 0,
	'kode=' . $used_code . ' usage=' . $wilayah->codeUsageCount($used_code));

$wilayah->deletePuskesmas($new_puskesmas_id);
check('W16 puskesmas tanpa pemakaian boleh dihapus', $wilayah->getPuskesmasById($new_puskesmas_id) === null);
check('W17 kecamatan kosong boleh dihapus',
	$wilayah->deleteKecamatan($new_kecamatan_id) === true && $wilayah->getKecamatanById($new_kecamatan_id) === null);

/* Agregasi infografis harus tetap utuh setelah CRUD di atas. */
$payload = $infographic->getInfographic(array());
$sum_kecamatan = 0;
foreach ($payload['by_kecamatan'] as $row) { $sum_kecamatan += (int) $row['entries']; }
check('W18 agregasi tetap konsisten setelah CRUD wilayah',
	$sum_kecamatan + $payload['unknown_wilayah']['entries'] === $payload['city']['total_entries']
	&& $payload['city']['coded'] + $payload['city']['uncoded'] === $payload['city']['total_entries']);

$db->query('ROLLBACK');
check('W19 tabel wilayah kembali seperti semula',
	count_rows($db, 'kecamatan') === $baseline_kecamatan && count_rows($db, 'puskesmas') === $baseline_puskesmas,
	'kecamatan=' . count_rows($db, 'kecamatan') . ' puskesmas=' . count_rows($db, 'puskesmas'));

echo "\n" . ($fails === 0 ? 'PROBE_RESULT=PASS' : "PROBE_RESULT=FAIL (gagal={$fails})") . "\n";
exit($fails === 0 ? 0 : 1);
