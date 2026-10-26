<?php
/**
 * Arsip rekam medis (modul `rekam_medis`) - kontrak yang dikunci.
 *
 * Uji ini memeriksa SUMBER, bukan menjalankan kueri: tidak butuh basis data,
 * server, maupun akun siapa pun. Yang dikunci adalah hal-hal yang bila
 * dilanggar membuat arsip salah tanpa terlihat:
 *
 *   1. Ukuran halaman hanya ditentukan controller; view menerima `pagination`
 *      dan tidak menghitung offset sendiri.
 *   2. Jumlah total memakai join dan filter yang sama dengan daftar, tetapi
 *      tanpa kolom mahal dan tanpa `limit`.
 *   3. Pindah halaman dilakukan tombol DI DALAM form filter (`form="..."`),
 *      jadi filter yang aktif tidak hilang; nomor halaman dijepit ke rentang
 *      [1, pages] sebelum dipakai sebagai offset.
 *   4. Tabel arsip tidak memuat isi klinis dan tidak menampilkan identitas
 *      pasien; muatan per baris hanya ringkasan layanan.
 *   5. Isi klinis hanya diminta lewat endpoint yang sudah ada
 *      (`rekam_medis/detail`), hanya setelah alasan dipilih, dan jawaban
 *      server (428/403/404/200) dibaca sebagai kalimat.
 *   6. Bila feature flag `clinical_audit` mati, tidak ada kolom, tombol,
 *      modal, maupun skrip yang tersisa - hanya kalimat penjelas.
 *   7. Tidak ada tabel, rute, atau migrasi baru: modul tetap baca-saja.
 *
 * Jalankan:
 *   php tools/admin_panel/tests/record_archive_unit.php
 */
$root = dirname(__DIR__, 3) . '/';

$passed = 0;
$failed = 0;

function rec_check($condition, $label, $detail = '')
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

function rec_source($relative)
{
	$path = dirname(__DIR__, 3) . '/' . $relative;
	return is_file($path) ? file_get_contents($path) : '';
}

/** Badan satu fungsi, dari `function nama(` sampai penutup fungsi berikutnya. */
function rec_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	if ($start === false) {
		return '';
	}
	$next = strpos($source, "\n\tfunction ", $start + 10);
	$public = strpos($source, "\n\tpublic function ", $start + 10);
	$candidates = array_filter(array($next, $public), function ($value) {
		return $value !== false;
	});
	$end = empty($candidates) ? strlen($source) : min($candidates);

	return substr($source, $start, $end - $start);
}

$controller = rec_source('admin_menu/application/modules/rekam_medis/controllers/Rekam_medis.php');
$model = rec_source('admin_menu/application/modules/rekam_medis/models/Rekam_medis_m.php');
$view = rec_source('admin_menu/application/modules/rekam_medis/views/rekam_medis_v.php');
$script = rec_source('admin_menu/assets/js/doclinc-record-archive.js');
$spec = rec_source('tools/task11_browser/tests/admin_panel_record_archive.spec.js');
$playwright = rec_source('tools/task11_browser/playwright.admin.config.js');
$routes = rec_source('admin_menu/application/config/routes.php');

echo "\n== controller ==\n";
rec_check(strpos($controller, 'const PER_PAGE = 25;') !== false,
	'ukuran halaman ditentukan satu tempat di controller');
rec_check(strpos($controller, 'count_records($filters)') !== false,
	'controller meminta jumlah total sebelum mengambil baris');
rec_check(strpos($controller, 'get_records($filters, self::PER_PAGE, $offset)') !== false,
	'daftar diambil sebesar satu halaman, bukan 200 baris sekaligus');
rec_check(strpos($controller, 'ceil($total / self::PER_PAGE)') !== false,
	'jumlah halaman dihitung dari total, bukan dari panjang hasil');
rec_check(preg_match('/\$page = min\(\$this->requested_page\(\), \$pages\);/', $controller) === 1,
	'halaman yang diminta dijepit ke rentang yang ada');
$requested = rec_function_body($controller, 'requested_page');
rec_check(strpos($requested, "\$this->input->post('page', TRUE)") !== false,
	'nomor halaman dibaca dari tombol pager (POST), bukan dari sesi');
rec_check(strpos($requested, '$page > 0 ? $page : 1') !== false,
	'permintaan tanpa nomor halaman jatuh ke halaman pertama');
$ready = rec_function_body($controller, 'clinical_detail_ready');
rec_check(strpos($ready, "Admin_capability_flags::enabled('clinical_audit')") !== false,
	'ketersediaan isi klinis dibaca dari feature flag, bukan dari data pasien');
rec_check(stripos($ready, 'Clinical_access') === false && stripos($ready, '->table') === false,
	'controller tidak memutuskan hak akses dan tidak menyentuh basis data di jalur itu');
rec_check(stripos($controller, 'Admin_clinical_access_gateway') !== false,
	'izin per akun tetap diputuskan gateway yang sudah ada saat satu rekam dibuka');
$index_body = rec_function_body($controller, 'index');
rec_check($index_body !== '' && stripos($index_body, 'gateway') === false
	&& stripos($index_body, 'authorize') === false,
	'daftar tidak memeriksa izin per akun; itu urusan detail() lewat gateway');

echo "\n== model ==\n";
rec_check(strpos($model, 'public function get_records($filters = array(), $limit = 200, $offset = 0)') !== false,
	'get_records menerima offset dengan nilai bawaan yang aman untuk pemanggil lama');
rec_check(strpos($model, '(int) $offset > 0 ? (int) $offset : 0') !== false,
	'offset negatif tidak pernah dikirim ke basis data');
$count = rec_function_body($model, 'count_records');
rec_check($count !== '', 'count_records ada');
rec_check(strpos($count, 'join_base();') !== false && strpos($count, 'apply_filters($filters);') !== false,
	'hitungan memakai join dan filter yang sama dengan daftar');
rec_check(strpos($count, 'count_all_results()') !== false,
	'satu kueri hitung, bukan mengambil baris lalu menghitung panjangnya');
rec_check(strpos($count, 'select_record_fields') === false,
	'kolom berat tidak dipilih untuk sekadar menghitung');
rec_check(strpos($count, 'limit(') === false && strpos($count, 'order_by(') === false,
	'hitungan tanpa limit dan tanpa urutan');
rec_check(strpos($model, '->insert(') === false && strpos($model, '->update(') === false
	&& strpos($model, '->delete(') === false && strpos($model, 'trans_start(') === false,
	'modul tetap baca-saja: tidak ada tulis ke basis data');

echo "\n== view ==\n";
rec_check(strpos($view, 'id="doclincRecordFilter"') !== false,
	'form filter punya id yang bisa dirujuk tombol di luar form');
rec_check(substr_count($view, 'form="doclincRecordFilter"') === 2,
	'dua tombol halaman menunjuk form filter, jadi filter aktif ikut terkirim');
rec_check(substr_count($view, 'name="page"') === 2,
	'kedua tombol mengirim satu isian bernama page');
rec_check(strpos($view, 'id="doclincRecordPagerInfo"') !== false
	&& strpos($view, 'Menampilkan') !== false
	&& strpos($view, 'halaman ') !== false,
	'paginasi menyebut rentang, jumlah total, dan halaman yang dibuka');
rec_check(strpos($view, 'rekam per halaman') !== false,
	'kepala kartu menyebut ukuran halaman, bukan "maksimal 200 data terbaru"');
rec_check(strpos($view, 'Maksimal 200 data terbaru') === false,
	'janji lama yang sudah tidak benar tidak tertinggal');
/* Kunci array PHP yang berbentuk angka menjadi int: array('7' => ...) memberi
   $value = 7, sedangkan filter yang dibaca dari input selalu string. Perbandingan
   === tanpa cast karena itu tidak pernah cocok, tidak ada opsi yang ditandai
   `selected`, dan peramban memilih opsi PERTAMA — "7 hari". Akibatnya menekan
   "Berikutnya" diam-diam mempersempit periode dan daftar menyusut ke satu
   halaman. Ditemukan uji browser pada 2026-10-26, bukan oleh gerbang statis. */
rec_check(strpos($view, "((string) (\$filters['period'] ?? '30') === (string) \$value)") !== false,
	'opsi periode dibandingkan sebagai string, jadi pilihan yang aktif benar-benar ditandai');
rec_check(preg_match('/if \(\$clinical_ready\):\s*\?>\s*<th scope="col">Rekam<\/th>/', $view) === 1,
	'kolom Rekam hanya ada bila akses klinis aktif');
rec_check(strpos($view, 'Isi klinis tidak tersedia untuk Admin') !== false,
	'keadaan tanpa akses klinis dijelaskan dengan kalimat, bukan tombol mati');
rec_check(preg_match('/if \(\$clinical_ready\):\s*\?>\s*<div class="modal fade" id="doclincRecordModal"/', $view) === 1,
	'modal tidak dirender sama sekali bila akses klinis mati');
rec_check(preg_match('/if \(\$clinical_ready\):\s*\?>\s*<\?php \$archive_js/', $view) === 1
	&& strpos($view, 'filemtime(') !== false,
	'skrip arsip hanya dimuat saat dibutuhkan, dengan pemutus tembolok');
rec_check(preg_match('/\$row->(anamnesis|diagnosis|treatment|recommendations)\b/i', $view) === 0,
	'isi klinis tidak pernah dirender ke dalam tabel');
rec_check(preg_match('/\$row->(patient|pasien|nama_pasien|nik|no_hp)\b/i', $view) === 0,
	'identitas pasien tidak tampil di daftar');
rec_check(substr_count(strtolower($view), 'anamnesis') === 1,
	'satu-satunya sebutan isi klinis adalah kalimat penjelas gerbang');

echo "\n== muatan per baris ==\n";
$payload_keys = array();
if (preg_match('/\$record_payload = array\((.*?)\n\t+\);/s', $view, $block)) {
	preg_match_all("/'([A-Za-z_]+)' =>/", $block[1], $matches);
	$payload_keys = $matches[1];
}
$allowed_keys = array('recordId', 'requestId', 'date', 'puskesmas', 'code', 'doctor', 'recordedBy', 'service', 'status');
rec_check(empty($payload_keys) === false, 'muatan baris dirakit di view, bukan dikirim utuh dari model');
rec_check(empty(array_diff($payload_keys, $allowed_keys)),
	'muatan baris hanya berisi ringkasan layanan',
	implode(', ', array_diff($payload_keys, $allowed_keys)));

echo "\n== skrip arsip ==\n";
rec_check(strpos($script, "modal.getAttribute('data-endpoint')") !== false,
	'alamat endpoint dibaca dari halaman, tidak ditulis ulang di skrip');
rec_check(strpos($script, 'record_id: current.recordId') !== false,
	'permintaan menyebut satu rekam, bukan seluruh daftar');
rec_check(strpos($script, "if (reasonSelect.value === '')") !== false
	&& strpos($script, 'Pilih alasan akses terlebih dahulu.') !== false,
	'isi klinis tidak diminta sebelum alasan dipilih');
rec_check(strpos($script, 'if (reason) {') !== false
	&& strpos($script, 'body.reason_code = reason.code;') > strpos($script, 'if (reason) {'),
	'alasan hanya ikut terkirim setelah dipilih');
rec_check(strpos($script, 'if (xhr.status === 428) {') !== false
	&& strpos($script, 'showReason();') !== false,
	'jawaban 428 membuka pemilih alasan, bukan pesan galat');
rec_check(strpos($script, 'xhr.status === 403 || xhr.status === 404') !== false
	&& strpos($script, 'gate.hidden = true;') !== false,
	'jawaban 403/404 menutup gerbang dan menyisakan kalimat penjelas');
rec_check(strpos($script, "setAttribute('aria-busy', 'true')") !== false
	&& strpos($script, 'disabled = true') === false,
	'permintaan berjalan ditandai aria-busy, tombol tidak dimatikan');
rec_check(strpos($script, "'hidden.bs.modal'") !== false && strpos($script, 'lastButton.focus()') !== false,
	'fokus kembali ke tombol pemicu saat dialog ditutup');
rec_check(strpos($script, "event.key === 'Escape'") !== false,
	'Escape tetap bekerja walau fokus sempat keluar dari dialog');

echo "\n== pengikatan uji dan ketiadaan perubahan skema ==\n";
rec_check($spec !== '' && strpos($spec, '[200, 403, 428]') !== false,
	'uji Playwright menerima tepat tiga jawaban server yang sah');
rec_check(strpos($playwright, 'admin_panel_record_archive.spec.js') !== false,
	'uji arsip ikut dijalankan konfigurasi panel admin');
rec_check(strpos($routes, 'rekam_medis') === false,
	'tidak ada rute baru: endpoint detail tetap memakai rute modul yang ada');
$module_files = glob(dirname(__DIR__, 3) . '/admin_menu/application/modules/rekam_medis/*/*');
$extra_files = array();
foreach ($module_files as $file) {
	// `index.html` penjaga direktori adalah kebiasaan CodeIgniter, bukan skema.
	if (!preg_match('/\.(php|html)$/', $file)) {
		$extra_files[] = basename($file);
	}
}
rec_check(empty($extra_files), 'tidak ada berkas migrasi atau SQL baru di modul',
	implode(', ', $extra_files));

echo "\n== ringkasan ==\n";
echo '  lulus: ' . $passed . ', gagal: ' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
