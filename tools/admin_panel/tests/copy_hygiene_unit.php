<?php
/**
 * Kebersihan sistem tata letak panel admin (uji statis).
 *
 * Uji ini tidak membutuhkan basis data dan tidak menjalankan aplikasi: yang
 * diperiksa adalah SUMBER view dan CSS. Tujuannya menahan tiga kemunduran yang
 * paling mudah terjadi saat 19 halaman disunting satu per satu:
 *
 *   1. kepala halaman kembali dikarang di dalam view, sehingga ukuran judul dan
 *      susunan aksi menyimpang lagi;
 *   2. aturan CSS judul ditambahi `!important` untuk "menang" atas aturan lain,
 *      dan sesudah itu tidak ada lagi satu sumber kebenaran;
 *   3. bingkai kartu daftar ditulis ulang per modul, sehingga `shadow`, `mb-4`,
 *      dan pembungkus isinya menyimpang tanpa alasan.
 *
 * Dua mode:
 *   php tools/admin_panel/tests/copy_hygiene_unit.php --report   (mencatat utang)
 *   php tools/admin_panel/tests/copy_hygiene_unit.php            (menegakkan)
 *
 * Mode `--report` selalu keluar dengan kode 0: ia dipakai sebelum migrasi untuk
 * mengukur berapa halaman yang belum memakai partial. Setelah F2 selesai,
 * jalankan tanpa `--report` sebagai gerbang.
 */
$root = dirname(__DIR__, 3) . '/';
define('BASEPATH', $root . 'admin_menu/system/');
define('APPPATH', $root . 'admin_menu/application/');

$report_only = in_array('--report', $_SERVER['argv'], true);
$passed = 0;
$failed = 0;
$debts = array();

function chk_check($condition, $label, $detail = '')
{
	global $passed, $failed, $debts, $report_only;
	if ($condition) {
		$passed++;
		echo "  ok   - {$label}\n";
		return;
	}
	if ($report_only) {
		$debts[] = $label;
		echo "  utang- {$label}" . ($detail === '' ? '' : "  ({$detail})") . "\n";
		return;
	}
	$failed++;
	echo "  FAIL - {$label}" . ($detail === '' ? '' : "  ({$detail})") . "\n";
}

function chk_read($relative)
{
	$path = dirname(__DIR__, 3) . '/' . $relative;
	return is_file($path) ? file_get_contents($path) : null;
}

/**
 * Daftar halaman yang dimigrasi. Ditulis literal, bukan hasil pemindaian
 * folder: `landing_page` punya belasan view lama dan hanya satu yang tampil
 * untuk rute `landing_page` (fallback ketika tabel warisan tidak ada), jadi
 * pemindaian folder akan memeriksa berkas yang tidak pernah dirender.
 */
$pages = array(
	'modules/icd10/views/icd10_v.php',
	'modules/diagnosis/views/diagnosis_v.php',
	'modules/kelola_keluhan/views/kelola_keluhan_v.php',
	'modules/kelola_tindakan/views/kelola_tindakan_v.php',
	'modules/master_puskesmas/views/master_puskesmas_v.php',
	'modules/wilayah/views/wilayah_v.php',
	'modules/kelola_staff_puskesmas/views/kelola_staff_puskesmas_v.php',
	'modules/kelola_dokter_nakes/views/kelola_dokter_nakes_v.php',
	'modules/rekam_medis/views/rekam_medis_v.php',
	'modules/konsultasi_kesehatan/views/konsultasi_kesehatan_v.php',
	'modules/laporan/views/laporan_v.php',
	'modules/audit/views/audit_v.php',
	'modules/kapabilitas/views/kapabilitas_v.php',
	'modules/sesi/views/sesi_v.php',
	'modules/kelola_warga/views/kelola_warga_v.php',
	'modules/kelola_layanan_kesehatan/views/kelola_layanan_kesehatan_v.php',
	'modules/kelola_news_feed/views/kelola_news_feed_v.php',
	'modules/konten_landing/views/konten_landing_v.php',
	'modules/landing_page/views/legacy_unavailable_v.php',
);
$dashboard = 'modules/home/views/home_v.php';


$page_head_path = 'admin_menu/application/views/commons/page_head.php';
$empty_state_path = 'admin_menu/application/views/commons/empty_state.php';

/* Nama berkas CSS diambil dari header.php, bukan dikarang di sini: uji ini
   pernah menunjuk `doclinc_admin_shell.css` (garis bawah) padahal berkas yang
   benar `doclinc-admin-shell.css` (tanda hubung), dan kegagalannya menyamar
   sebagai "judul belum punya satu sumber" — bukan sebagai berkas tidak ada. */
$header = chk_read('admin_menu/application/views/commons/header.php');
$header = $header === null ? '' : $header;
chk_check(preg_match('#assets/css/doclinc-admin-shell\.css#', $header) === 1,
'header memuat doclinc-admin-shell.css', 'nama berkas CSS shell');

$shell_css = chk_read('admin_menu/assets/css/doclinc-admin-shell.css');
chk_check($shell_css !== null, 'berkas CSS shell tersedia', 'assets/css/doclinc-admin-shell.css');
$shell_css = $shell_css === null ? '' : $shell_css;
$custom_css = chk_read('admin_menu/assets/css/custom.css');
$custom_css = $custom_css === null ? '' : $custom_css;

echo "== partial bersama ==\n";
$head_src = chk_read($page_head_path);
$empty_src = chk_read($empty_state_path);
chk_check($head_src !== null, 'partial kepala halaman tersedia', $page_head_path);
chk_check($empty_src !== null, 'partial keadaan kosong tersedia', $empty_state_path);
chk_check($head_src === null || substr_count($head_src, '<h1') === 1,
	'partial kepala halaman mencetak <h1> tepat satu kali',
	'ditemukan ' . ($head_src === null ? 0 : substr_count($head_src, '<h1')));
chk_check($head_src === null || strpos($head_src, 'dl-adm-head-title') !== false,
	'partial memakai kelas judul yang sudah ada');
chk_check($empty_src === null || strpos($empty_src, 'doclinc-empty-state') !== false,
	'partial keadaan kosong memakai kelas dasar dasbor');
chk_check(($head_src === null || strpos($head_src, 'style=') === false)
	&& ($empty_src === null || strpos($empty_src, 'style=') === false),
	'tidak ada gaya inline di dalam partial');

/* Partial tidak boleh menerima HTML jadi. Kalau pemanggil boleh menempel
   potongan HTML, alasan keberadaan partial ini (satu bentuk kepala halaman)
   hilang pada halaman pertama yang butuh tombol berbeda. */
chk_check($head_src === null || strpos($head_src, 'echo $page_head[') === false,
	'partial tidak mencetak HTML mentah dari pemanggil');

/* Dua penyaring inilah yang membuat kontrak bertahan tanpa bergantung pada
   disiplin pemanggil: varian tombol dari daftar putih, nama ikon dari pola. */
chk_check($head_src === null || strpos($head_src, 'outline-secondary') !== false,
	'partial menyaring varian tombol dengan daftar putih');
chk_check($head_src === null || strpos($head_src, 'preg_match') !== false,
	'partial menyaring nama ikon dengan pola kelas');
/* Nama ikon FA5, bukan FA6: bundel yang dimuat adalah Font Awesome 5.13.1,
   dan `fa-circle-info` (bentuk FA6) menghasilkan ::before kosong - yaitu
   tombol 44px tanpa isi, persis di tiga halaman. Pemeriksaan ini dulu mengunci
   nama yang salah, sehingga gerbangnya justru menjaga cacatnya. */
chk_check($head_src === null || substr_count($head_src, 'fa-info-circle') === 1,
	'tombol bantuan memakai satu ikon yang ada di bundel FA5', 'fa-info-circle');

echo "\n== satu sumber gaya judul ==\n";
chk_check(substr_count($shell_css, '.dl-adm-head-title') === 1,
'judul halaman didefinisikan sekali di doclinc-admin-shell.css',
'ditemukan ' . substr_count($shell_css, '.dl-adm-head-title'));
chk_check(strpos($custom_css, '.dl-adm-head-title') === false,
'custom.css tidak menimpa judul halaman');
chk_check(substr_count($shell_css, '.dl-adm-empty-title') === 1,
'judul keadaan kosong didefinisikan sekali',
'ditemukan ' . substr_count($shell_css, '.dl-adm-empty-title'));

/* `!important` pada selektor judul adalah tanda dua aturan bertabrakan; yang
   diinginkan adalah satu aturan yang menang karena urutan muat, bukan paksaan.
   shell.css dimuat paling akhir oleh header.php, jadi tidak perlu memaksa. */
$forced = array();
foreach (preg_split('/\R/', $shell_css) as $line) {
	if (stripos($line, '!important') === false) {
		continue;
	}
	if (stripos($line, 'dl-adm-head') !== false || stripos($line, 'dl-adm-empty') !== false) {
		$forced[] = trim($line);
	}
}
chk_check(empty($forced), 'tidak ada !important pada selektor judul', implode(' | ', array_slice($forced, 0, 2)));

/* Shell harus dimuat SESUDAH custom.css. Tanpa urutan itu, aturan judul di
   shell kalah dan satu-satunya jalan pintas adalah `!important`. */
$custom_pos = strpos($header, 'assets/css/custom.css');
$shell_pos = strpos($header, 'assets/css/doclinc-admin-shell.css');
chk_check($custom_pos !== false && $shell_pos !== false && $shell_pos > $custom_pos,
	'CSS shell dimuat sesudah custom.css');

echo "\n== halaman modul ==\n";
$without_partial = array();
$wrong_h1 = array();
$inline_size = array();
foreach ($pages as $relative) {
	$source = chk_read('admin_menu/application/' . $relative);
	if ($source === null) {
		chk_check(false, 'view tersedia: ' . $relative);
		continue;
	}
	$uses_partial = strpos($source, 'commons/page_head') !== false;
	if (!$uses_partial) {
		$without_partial[] = basename($relative);
	}
	/* View yang memakai partial tidak boleh mencetak <h1> sendiri — judulnya
	   datang dari partial. Yang dihitung adalah judul EFEKTIF, sebab menghitung
	   `<h1>` di dalam berkas view saja akan melaporkan halaman yang sudah benar
	   sebagai halaman tanpa judul. */
	$h1_count = preg_match_all('/<h1[\s>]/i', $source) + ($uses_partial ? 1 : 0);
	if ($h1_count !== 1) {
		$wrong_h1[] = basename($relative) . '(' . $h1_count . ')';
	}
	if (preg_match('/<h[12][^>]*style="[^"]*font-size/i', $source) === 1) {
		$inline_size[] = basename($relative);
	}
}
chk_check(empty($without_partial), 'setiap halaman memakai partial kepala halaman',
count($without_partial) . ' belum: ' . implode(', ', array_slice($without_partial, 0, 6)));
chk_check(empty($wrong_h1), 'setiap halaman punya tepat satu <h1>', implode(', ', $wrong_h1));
chk_check(empty($inline_size), 'tidak ada ukuran huruf judul lewat atribut style', implode(', ', $inline_size));

echo "\n== dasbor ==\n";
$home = chk_read('admin_menu/application/' . $dashboard);
chk_check($home !== null, 'view dasbor tersedia', $dashboard);
chk_check($home !== null && strpos($home, 'id="dlMonHelp"') !== false,
	'modal bantuan dasbor tetap di dalam home_v.php', 'penanda id="dlMonHelp"');
chk_check($home !== null && strpos($home, 'commons/page_head') !== false,
	'dasbor memakai partial kepala halaman');
chk_check($home !== null && preg_match_all('/<h1[\s>]/i', $home)
	+ (strpos((string) $home, 'commons/page_head') !== false ? 1 : 0) === 1,
	'dasbor punya tepat satu <h1>');

echo "\n== keadaan kosong ==\n";
/* Keadaan kosong tingkat panel di dasbor seluruhnya datang dari partial. Yang
   diuji bukan jumlahnya (panel bisa bertambah), melainkan bahwa tidak ada lagi
   markup `.doclinc-empty-state` yang ditulis tangan di view dasbor. */
chk_check($home !== null && strpos($home, "load->view('commons/empty_state'") !== false,
	'dasbor merender keadaan kosong lewat partial');
chk_check($home !== null && strpos($home, 'doclinc-empty-state') === false,
	'dasbor tidak lagi menulis markup keadaan kosong sendiri',
	'ditemukan ' . substr_count((string) $home, 'doclinc-empty-state') . ' blok lokal');
$laporan_view = chk_read('admin_menu/application/modules/laporan/views/laporan_v.php');
chk_check($laporan_view !== null && substr_count($laporan_view, "load->view('commons/empty_state'") === 1,
	'laporan merender keadaan kosong awal lewat partial');
/* Dua daftar di laporan dirakit dari JS, jadi partial tidak bisa dipanggil.
   Satu perakit dipakai bersama supaya markup tidak disalin per tempat. */
chk_check($laporan_view !== null && substr_count($laporan_view, 'function emptyStateHtml') === 1,
	'laporan punya satu perakit keadaan kosong untuk daftar dari JS');
chk_check($laporan_view !== null && substr_count($laporan_view, 'emptyStateHtml(') >= 3,
	'daftar dari JS memakai perakit keadaan kosong yang sama',
	'ditemukan ' . substr_count((string) $laporan_view, 'emptyStateHtml(') . ' pemakaian');

echo "\n== kartu daftar ==\n";
/* Bingkai kartu daftar (kartu + kepala kartu + pembungkus isi) dulu ditulis
   ulang di tiap modul, dan menyimpang pada hal-hal yang tidak pernah
   disengaja: `shadow` vs `shadow-sm`, `mb-4` ada/tidak, `card-body` vs
   `card-body doclinc-table-body`. Partial ini menyatukan bingkainya, sedangkan
   isinya (toolbar, tabel, daftar kartu) tetap ditulis view pemanggil. */
$list_card_path = 'admin_menu/application/views/commons/list_card.php';
$list_card_src = chk_read($list_card_path);
chk_check($list_card_src !== null, 'partial kartu daftar tersedia', $list_card_path);
chk_check($list_card_src !== null && strpos($list_card_src, "=== 'close'") !== false,
	'partial kartu daftar punya bagian penutup');
chk_check($list_card_src !== null && strpos($list_card_src, 'echo $list_card[') === false,
	'partial kartu daftar tidak mencetak HTML mentah dari pemanggil');
chk_check($list_card_src !== null && substr_count($list_card_src, 'preg_match') >= 2,
	'partial kartu daftar menyaring kelas dan ikon dengan pola');
/* Judul kartu `<h2 class="h6">`: tingkat judul untuk struktur, `.h6` untuk
   ukuran — pola yang sama dengan kartu dasbor, jadi menaikkan tingkat judul
   dari `<h6>` tidak mengubah tampilan. */
chk_check($list_card_src !== null && strpos($list_card_src, '<h2 class="h6') !== false,
	'judul kartu memakai tingkat judul yang benar tanpa mengubah ukuran');
chk_check($list_card_src !== null && strpos($list_card_src, '<h6') === false,
	'partial kartu daftar tidak memakai <h6> untuk judul');
/* Aksi kepala kartu dan aksi kepala halaman harus berbicara dengan kelas yang
   sama; kalau tidak, "tombol utama" punya dua bahasa. Tinggi tombol TIDAK
   diuji di sini karena bukan milik berkas shell: lapisan token
   (`assets/css/doclinc-reference-ui.css`) menetapkannya dengan `!important`,
   jadi deklarasi `min-height` di berkas shell pasti kalah dan hanya menjadi
   sumber kebenaran semu. Yang diuji: partial tidak memakai varian kecil yang
   sudah dihapus, dan berkas shell tidak lagi mendeklarasikan tinggi aksi. */
chk_check($list_card_src !== null
	&& strpos($list_card_src, 'dl-adm-action"') !== false
	&& preg_match('/class="[^"]*dl-adm-action-sm/', $list_card_src) === 0,
	'aksi kepala kartu memakai kelas aksi bersama tanpa varian mati');
chk_check(preg_match_all('/min-height:\s*\d+px/', $shell_css) === 0,
	'CSS shell tidak mendeklarasikan tinggi aksi yang kalah oleh lapisan token',
	'ditemukan ' . preg_match_all('/min-height:\s*\d+px/', $shell_css) . ' deklarasi');
/* Sumber tingginya dipastikan ada. Bila aturan `!important` itu hilang dari
   lapisan token, tombol kehilangan ambang sasaran sentuh - dan itu tidak akan
   terlihat dari berkas mana pun di panel. */
$reference_css = chk_read('assets/css/doclinc-reference-ui.css');
chk_check($reference_css !== null
	&& preg_match('/\.doclinc-admin-shell \.btn-[a-z]+[^{]*\{[^}]*min-height:\s*var\(--dl-ref-control/', $reference_css) === 1
	&& strpos($reference_css, '--dl-ref-control-primary: 48px') !== false,
	'tinggi aksi dipegang lapisan token referensi',
	$reference_css === null ? 'assets/css/doclinc-reference-ui.css tidak terbaca' : '');
chk_check(strpos($custom_css, 'dl-adm-action-sm') === false,
	'custom.css tidak menimpa ukuran aksi kepala kartu');

$list_card_pages = array(
	'modules/konsultasi_kesehatan/views/konsultasi_kesehatan_v.php' => 0,
	'modules/kelola_staff_puskesmas/views/kelola_staff_puskesmas_v.php' => 1,
	'modules/kelola_dokter_nakes/views/kelola_dokter_nakes_v.php' => 0,
	'modules/master_puskesmas/views/master_puskesmas_v.php' => 0,
);
$unpaired = array();
$frame_rewritten = array();
foreach ($list_card_pages as $relative => $allowed_local_frames) {
	$source = chk_read('admin_menu/application/' . $relative);
	if ($source === null) {
		chk_check(false, 'view daftar tersedia: ' . $relative);
		continue;
	}
	$opens = substr_count($source, "'part' => 'open'");
	$closes = substr_count($source, "'part' => 'close'");
	if ($opens !== 1 || $closes !== 1) {
		$unpaired[] = basename($relative) . '(open ' . $opens . ', close ' . $closes . ')';
	}
	/* Sisa bingkai lokal dihitung, bukan dilarang mutlak: kartu pemberitahuan
	   serah-terima kode di `kelola_staff_puskesmas` bukan daftar, jadi ia tetap
	   memakai `doclinc-table-card` sebagai wadah. */
	$local_frames = substr_count($source, 'doclinc-table-card');
	if ($local_frames > $allowed_local_frames) {
		$frame_rewritten[] = basename($relative) . '(' . $local_frames . ')';
	}
}
chk_check(empty($unpaired), 'setiap kartu daftar dibuka dan ditutup lewat partial', implode(', ', $unpaired));
chk_check(empty($frame_rewritten), 'tidak ada view daftar yang menulis bingkai kartu sendiri', implode(', ', $frame_rewritten));

/* Header kolom tanpa `scope` memaksa pembaca layar menebak hubungan sel dan
   header. Tabel konsultasi adalah tabel pertama yang ikut migrasi bingkai. */
$konsultasi_view = chk_read('admin_menu/application/modules/konsultasi_kesehatan/views/konsultasi_kesehatan_v.php');
$th_total = $konsultasi_view === null ? 0 : preg_match_all('/<th[\s>]/i', $konsultasi_view);
$th_scoped = $konsultasi_view === null ? 0 : preg_match_all('/<th scope="col">/i', $konsultasi_view);
chk_check($th_total > 0 && $th_total === $th_scoped,
	'setiap header kolom konsultasi punya scope="col"', 'ditemukan ' . $th_scoped . '/' . $th_total);

echo "\n== label tombol ==\n";
/* Label tombol adalah tempat teks paling sering menjadi samar: "Submit",
   "Proses", "Terapkan" tidak memberi tahu apa yang terjadi. Daftar di bawah
   hanya menolak label yang SELURUHNYA berupa kata generik — "Simpan" di dalam
   modal yang judulnya "Tambah wilayah" tetap boleh, karena konteksnya sudah
   menyebut objek. Yang dicari adalah label yang tidak punya konteks apa pun. */
$generic_labels = array('submit', 'ok', 'oke', 'cancel', 'save', 'delete', 'update',
	'search', 'proses', 'terapkan', 'eksekusi', 'lanjut');
$generic_found = array();
$checked_views = $pages;
$checked_views[] = $dashboard;
foreach ($checked_views as $relative) {
	$source = chk_read('admin_menu/application/' . $relative);
	if ($source === null) {
		continue;
	}
	if (preg_match_all('/<button[^>]*>([\s\S]*?)<\/button>/i', $source, $matches) === false) {
		continue;
	}
	foreach ($matches[1] as $inner) {
		$label = strtolower(trim(preg_replace('/\s+/', ' ', strip_tags($inner))));
		if (in_array($label, $generic_labels, true)) {
			$generic_found[] = basename($relative) . ': ' . $label;
		}
	}
}
chk_check(empty($generic_found), 'tidak ada tombol berlabel kata generik saja',
	implode(', ', array_slice($generic_found, 0, 4)));

echo "\n== pesan galat controller ==\n";
/* Pesan galat yang berhenti pada "apa yang gagal" memaksa pengguna menebak
   keadaan datanya: apakah stafnya sudah masuk, apakah statusnya berubah, apakah
   akunnya sudah hilang. Bentuk yang dipakai panel: sebut apa yang gagal, lalu
   sebut keadaan data dan langkah berikutnya - "Tidak ada data yang tersimpan;
   periksa kembali isian lalu simpan ulang." Yang dikunci di sini hanya keluarga
   "Gagal ..." yang muncul di controller; pesan lain sudah spesifik sejak awal
   ("Lengkapi nama, gelar, profesi, ..."), jadi tidak perlu diubah. */
$controller_paths = glob(APPPATH . 'modules/*/controllers/*.php');
$controller_paths = $controller_paths === false ? array() : $controller_paths;
$gagal_total = 0;
$gagal_tanpa_langkah = array();
foreach ($controller_paths as $controller_path) {
	$controller_source = (string) file_get_contents($controller_path);
	if (preg_match_all("/set_flashdata\\('error',\\s*'(Gagal [^']*)'\\)/", $controller_source, $gagal_matches) < 1) {
		continue;
	}
	foreach ($gagal_matches[1] as $gagal_message) {
		$gagal_total++;
		/* Satu kalimat saja = tidak ada keadaan data maupun langkah berikutnya. */
		if (preg_match('/^Gagal [^.]+\.\s+\S/', $gagal_message) !== 1) {
			$gagal_tanpa_langkah[] = basename($controller_path) . ': ' . $gagal_message;
		}
	}
}
chk_check($gagal_total > 0, 'pesan galat "Gagal ..." di controller terbaca', 'ditemukan ' . $gagal_total);
chk_check(empty($gagal_tanpa_langkah), 'setiap pesan galat "Gagal ..." menyebut keadaan data dan langkah berikutnya',
	implode(' | ', array_slice($gagal_tanpa_langkah, 0, 4)));

echo "\n== ringkasan ==\n";
if ($report_only) {
	echo '  utang: ' . count($debts) . ' butir, lulus: ' . $passed . "\n";
	echo "  (mode --report tidak pernah gagal; jalankan tanpa --report untuk menegakkan)\n";
	exit(0);
}
echo '  lulus: ' . $passed . ', gagal: ' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
