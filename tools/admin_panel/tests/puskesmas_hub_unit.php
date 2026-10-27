<?php
/**
 * Kontrak hub Puskesmas (uji unit + statis, tanpa basis data).
 *
 * Sejak `master_puskesmas` dan `kelola_staff_puskesmas` keluar dari sidebar,
 * `kelola_dokter_nakes` adalah satu-satunya pintu pekerjaan Puskesmas: sembilan
 * baris inti di daftar, lalu halaman detail dengan profil fasilitas, akun
 * koordinator, dan staf tersemat. Yang paling mudah rusak sesudah penggabungan
 * itu bukan tampilan, melainkan JALURNYA:
 *
 *   1. formulir di halaman detail kehilangan token CSRF atau mengirim ke rute
 *      yang salah - halaman tetap terbuka, penyimpanan selalu ditolak;
 *   2. reset password menemukan jalur tulis kedua, sehingga `must_change_password`
 *      dan pencabutan sesi lama tidak lagi ikut berjalan;
 *   3. aturan kesiapan fasilitas disalin ke model baru, lalu dua salinan itu
 *      menyimpang; dan
 *   4. `master_puskesmas` kembali merender halaman sendiri, sehingga Puskesmas
 *      tampil di dua tempat dengan dua sumber data.
 *
 * Pemakaian:
 *   php tools/admin_panel/tests/puskesmas_hub_unit.php
 */
$root = dirname(__DIR__, 3) . '/';
define('BASEPATH', $root . 'admin_menu/system/');
define('APPPATH', $root . 'admin_menu/application/');
if (!class_exists('MX_Controller')) {
	class MX_Controller
	{
		public function __construct()
		{
		}
	}
}

require_once $root . 'application/libraries/Core_puskesmas.php';
require_once APPPATH . 'modules/master_puskesmas/models/Master_puskesmas_m.php';
require_once APPPATH . 'modules/kelola_dokter_nakes/models/Kelola_dokter_nakes_m.php';

$passed = 0;
$failed = 0;

function hub_check($condition, $label, $detail = '')
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "  ok   - {$label}\n";
		return;
	}
	$failed++;
	echo "  FAIL - {$label}" . ($detail === '' ? '' : "  ({$detail})") . "\n";
}

function hub_read($relative)
{
	$path = dirname(__DIR__, 3) . '/' . $relative;
	return is_file($path) ? file_get_contents($path) : null;
}

/** Potongan satu metode, supaya pemeriksaan tidak tertipu metode lain. */
function hub_method($source, $name)
{
	if (preg_match('/function\s+' . preg_quote($name, '/') . '\s*\([^)]*\)\s*\{/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
		return '';
	}
	$start = $match[0][1] + strlen($match[0][0]) - 1;
	$depth = 0;
	$length = strlen($source);
	for ($i = $start; $i < $length; $i++) {
		if ($source[$i] === '{') {
			$depth++;
			continue;
		}
		if ($source[$i] === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, $i - $start + 1);
			}
		}
	}
	return '';
}

echo "== daftar Puskesmas inti ==\n";
$core = new Core_puskesmas();
hub_check(count($core->codes()) === 9, 'sembilan kode inti', (string) count($core->codes()));
hub_check(count(array_unique($core->codes())) === 9, 'tidak ada kode inti ganda');
$names_ok = true;
$kecamatan_ok = true;
foreach ($core->codes() as $code) {
	$names_ok = $names_ok && strpos((string) $core->name($code), 'Puskesmas ') === 0;
	$kecamatan_ok = $kecamatan_ok && trim((string) $core->kecamatan($code)) !== '';
}
hub_check($names_ok, 'setiap kode inti punya nama resmi');
hub_check($kecamatan_ok, 'setiap kode inti punya kecamatan');
hub_check($core->normalize(' 10280101 ') === '10280101' && $core->normalize(null) === '',
	'kode dipangkas dan bukan-skalar menjadi string kosong');
hub_check($core->isCore(' 10280101 ') && !$core->isCore('PKM10'),
	'hanya kode inti yang diterima, spasi diabaikan');
hub_check($core->rows()[0] === array('kode_pkm' => '10280101', 'nama_puskesmas' => 'Puskesmas Cilegon', 'kecamatan' => 'Cilegon'),
	'baris pertama adalah Puskesmas Cilegon');

echo "\n== aturan kesiapan dipinjam, bukan disalin ==\n";
$hub_model = (new ReflectionClass('Kelola_dokter_nakes_m'))->newInstanceWithoutConstructor();
$ready = (object) array(
	'nama_puskesmas' => 'Puskesmas Cilegon', 'alamat' => 'Jalan Pelayanan Nomor 1',
	'latitude' => '-6.0101', 'longitude' => '106.0101', 'status' => 'aktif',
);
hub_check($hub_model->readiness_issues($ready) === array(), 'fasilitas lengkap tidak punya masalah');
$gap = clone $ready;
$gap->alamat = '-';
$gap->latitude = '91';
hub_check($hub_model->readiness_issues($gap) === array('facility_address', 'facility_latitude'),
	'kode masalah sama persis dengan model master', implode(',', $hub_model->readiness_issues($gap)));
hub_check($hub_model->readiness_issues(null) === array('facility_name', 'facility_address', 'facility_latitude', 'facility_longitude'),
	'baris tanpa master dilaporkan belum lengkap seluruhnya');

$summary_rows = array(
	(object) array('readiness_issues' => array()),
	(object) array('readiness_issues' => array('facility_address')),
	(object) array('readiness_issues' => array('facility_address', 'facility_latitude')),
);
$summary = $hub_model->readiness_summary($summary_rows);
hub_check($summary['total'] === 3 && $summary['ready'] === 1 && $summary['attention'] === 2,
	'ringkasan menghitung siap dan perlu dilengkapi',
	$summary['ready'] . '/' . $summary['attention']);
hub_check(($summary['issue_counts']['facility_address'] ?? 0) === 2, 'ringkasan menghitung tiap kode masalah');
hub_check($hub_model->readiness_summary(array()) === array('total' => 0, 'ready' => 0, 'attention' => 0, 'issue_counts' => array()),
	'ringkasan kosong tetap berbentuk sama');

echo "\n== halaman daftar ==\n";
$hub_view = hub_read('admin_menu/application/modules/kelola_dokter_nakes/views/kelola_dokter_nakes_v.php');
$detail_view = hub_read('admin_menu/application/modules/kelola_dokter_nakes/views/puskesmas_detail_v.php');
$password_view = hub_read('admin_menu/application/modules/kelola_dokter_nakes/views/puskesmas_password_v.php');
hub_check($hub_view !== null && $detail_view !== null && $password_view !== null, 'tiga view hub terbaca');

hub_check($hub_view !== null
	&& strpos($hub_view, 'data-puskesmas-readiness="<?= html_escape($readiness_state); ?>"') !== false
	&& strpos($hub_view, "querySelectorAll('[data-puskesmas-readiness]')") !== false,
	'tiap baris membawa penanda kesiapan dan skrip menyaringnya');
hub_check($hub_view !== null && strpos($hub_view, 'id="puskesmasReadinessFilter"') !== false,
	'saringan kesiapan punya id yang dipakai skrip');
hub_check($hub_view !== null && strpos($hub_view, "'attention' => array('label' => 'Perlu dilengkapi'") !== false,
	'lencana "Perlu dilengkapi" tersedia');
hub_check($hub_view !== null && strpos($hub_view, "site_url('kelola_dokter_nakes/detail/'") !== false,
	'baris menautkan ke halaman detail');
hub_check($hub_view !== null && strpos($hub_view, "'part' => 'open'") !== false
	&& substr_count($hub_view, "'part' => 'close'") === 1,
	'kartu daftar dibuka dan ditutup lewat partial');

echo "\n== halaman detail ==\n";
hub_check($detail_view !== null && substr_count($detail_view, "site_url('kelola_dokter_nakes/update_profile')") === 1,
	'satu formulir profil');
hub_check($detail_view !== null && strpos($detail_view, "site_url('kelola_dokter_nakes/store')") !== false,
	'modal akun menuju rute store');
hub_check($detail_view !== null && strpos($detail_view, "site_url('kelola_dokter_nakes/password/'") !== false,
	'tautan reset password menuju halaman password');
hub_check($detail_view !== null && strpos($detail_view, 'name="kode_pkm"') !== false,
	'kode Puskesmas dikirim sebagai field tersembunyi');
hub_check($detail_view !== null && strpos($detail_view, '$staff_section') !== false
	&& strpos($detail_view, 'Daftar staf tidak dapat dimuat') !== false,
	'staf tersemat dengan keadaan cadangan yang jelas');


echo "\n== halaman password ==\n";
hub_check($password_view !== null && substr_count($password_view, "site_url('kelola_dokter_nakes/reset_password')") === 1,
	'satu formulir password menuju reset_password');
hub_check($password_view !== null && strpos($password_view, 'name="kode_pkm"') !== false
	&& strpos($password_view, 'name="confirm_password"') !== false,
	'kode Puskesmas dan konfirmasi password dikirim');
hub_check($password_view !== null && strpos($password_view, 'Tanpa akun') !== false
	|| ($password_view !== null && strpos($password_view, 'belum punya akun') !== false),
	'keadaan "belum ada akun" dijelaskan, bukan formulir kosong');

echo "\n== token CSRF di setiap formulir ==\n";
foreach (array('daftar' => $hub_view, 'detail' => $detail_view, 'password' => $password_view) as $label => $source) {
	if ($source === null) {
		hub_check(false, "view {$label} terbaca");
		continue;
	}
	$forms = substr_count($source, '<form');
	$post_forms = substr_count($source, 'method="post"');
	$tokens = substr_count($source, 'get_csrf_token_name()');
	hub_check($forms === $post_forms && $forms === $tokens,
		"setiap formulir POST di view {$label} membawa token CSRF",
		$forms . ' formulir, ' . $post_forms . ' POST, ' . $tokens . ' token');
}

echo "\n== controller ==\n";
$hub_controller = hub_read('admin_menu/application/modules/kelola_dokter_nakes/controllers/Kelola_dokter_nakes.php');
hub_check($hub_controller !== null, 'controller hub terbaca');
hub_check($hub_controller !== null && strpos($hub_controller, "userdata('level') !== 'admin'") !== false,
	'hub hanya untuk administrator');
foreach (array('update_profile', 'reset_password', 'store', 'update', 'delete_dokter_nakes', 'destroy_dokter_nakes', 'aktifkan_user', 'nonaktifkan_user') as $action) {
	$body = $hub_controller === null ? '' : hub_method($hub_controller, $action);
	hub_check($body !== '' && strpos($body, 'require_post()') !== false,
		"{$action} menolak metode selain POST");
}

$reset = $hub_controller === null ? '' : hub_method($hub_controller, 'reset_password');
hub_check($reset !== '' && strpos($reset, 'update_dokter_nakes') !== false,
	'reset password memakai jalur tulis yang sudah ada');
hub_check($reset !== '' && strpos($reset, "->update('users'") === false && strpos($reset, 'db->update') === false,
	'tidak ada jalur tulis password kedua');
hub_check($reset !== '' && strpos($reset, 'Password_strength_policy') !== false,
	'password baru melewati kebijakan kekuatan password');

$profile = $hub_controller === null ? '' : hub_method($hub_controller, 'update_profile');
hub_check($profile !== '' && strpos($profile, 'is_numeric($raw)') !== false,
	'titik lokasi divalidasi sebagai angka');
hub_check($profile !== '' && strpos($profile, "'latitude' => 90") !== false && strpos($profile, "'longitude' => 180") !== false,
	'rentang koordinat sama dengan validasi master');

$detail_method = $hub_controller === null ? '' : hub_method($hub_controller, 'detail');
hub_check($detail_method !== '' && strpos($detail_method, 'staff_section_html') !== false,
	'detail menyematkan daftar staf');
hub_check($detail_method !== '' && strpos($detail_method, "'error'") !== false
	&& strpos($detail_method, "redirect('kelola_dokter_nakes'") !== false,
	'kode tak dikenal dipantulkan ke daftar dengan pesan');

echo "\n== rute lama tetap hidup ==\n";
$master_controller = hub_read('admin_menu/application/modules/master_puskesmas/controllers/Master_puskesmas.php');
$master_index = $master_controller === null ? '' : hub_method($master_controller, 'index');
hub_check($master_index !== '' && strpos($master_index, "redirect('kelola_dokter_nakes', 'refresh')") !== false,
	'master_puskesmas mengalihkan ke daftar Puskesmas');
hub_check($master_index !== '' && strpos($master_index, '->view(') === false,
	'master_puskesmas tidak lagi merender halaman sendiri');

echo "\n== ringkasan ==\n";
echo "  lulus: {$passed}, gagal: {$failed}\n";
exit($failed > 0 ? 1 : 0);

