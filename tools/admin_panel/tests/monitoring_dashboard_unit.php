<?php
/**
 * Dasbor Monitoring Layanan Kesehatan (Dinas Kesehatan) - kontrak yang dikunci.
 *
 * Uji ini memeriksa SUMBER, bukan menjalankan kueri: tidak butuh basis data dan
 * tidak menyentuh data siapa pun. Yang dikunci adalah hal-hal yang bila
 * dilanggar membuat angka dasbor salah tanpa terlihat:
 *
 *   1. Definisi KPI hanya ada di model. View tidak menghitung dan tidak
 *      menyentuh basis data.
 *   2. Batas periode setengah terbuka (>= awal AND < akhir), bukan BETWEEN.
 *   3. Kolom waktu TIDAK dibungkus fungsi pada penyaring rentang, supaya indeks
 *      (request_status, completed_at) tetap dipakai.
 *   4. Penggeseran kerangka jam dilakukan pada BATAS, bukan pada kolom.
 *   5. Filter Puskesmas ikut ke semua agregat yang berbicara tentang permintaan.
 *   6. Pemeriksaan mutu data tidak pernah menulis (baca-saja).
 *   7. Ambang eskalasi dibaca dari config yang sama di KEDUA aplikasi.
 *   8. Zona waktu PHP dinyatakan di kedua config, supaya tidak berbeda dari
 *      zona sesi basis data.
 *   9. Nilai yang tidak tersedia ditampilkan sebagai tanda hubung, bukan nol.
 *  10. Permukaan ini memakai satu berkas gaya sendiri, tanpa hex baru, tanpa
 *      penanda prioritas paksa, dan menghormati prefers-reduced-motion.
 *
 * Jalankan:
 *   php tools/admin_panel/tests/monitoring_dashboard_unit.php
 */
$root = dirname(__DIR__, 3) . '/';
define('BASEPATH', $root . 'admin_menu/system/');
define('APPPATH', $root . 'admin_menu/application/');

$passed = 0;
$failed = 0;

function mon_check($condition, $label, $detail = '')
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

function mon_source($relative)
{
	$path = dirname(__DIR__, 3) . '/' . $relative;
	return is_file($path) ? file_get_contents($path) : '';
}

/** Badan blok monitoring di model, dari penanda sampai akhir berkas. */
function mon_monitoring_block($model)
{
	$start = strpos($model, 'MONITORING LAYANAN KESEHATAN');
	return $start === false ? '' : substr($model, $start);
}

$model = mon_source('admin_menu/application/modules/home/models/Home_m.php');
$controller = mon_source('admin_menu/application/modules/home/controllers/Home.php');
$view = mon_source('admin_menu/application/modules/home/views/home_v.php');
$adminConfig = mon_source('admin_menu/application/config/config.php');
$mainConfig = mon_source('application/config/config.php');
$surfaceCss = mon_source('admin_menu/assets/css/doclinc-admin-monitoring.css');
$surfaceJs = mon_source('admin_menu/assets/js/doclinc-admin-monitoring.js');

$block = mon_monitoring_block($model);

echo "\n== berkas ada ==\n";
mon_check($model !== '', 'model Home_m terbaca');
mon_check($controller !== '', 'controller Home terbaca');
mon_check($view !== '', 'view home_v terbaca');
mon_check($surfaceCss !== '', 'CSS permukaan ada');
mon_check($surfaceJs !== '', 'JS permukaan ada');
mon_check($block !== '', 'blok monitoring ditemukan di model');

echo "\n== model: agregat berperiode ==\n";
mon_check(substr_count($block, '$this->monitoring_facility_where($filter)') === 5,
	'lima agregat permintaan memakai penyaring Puskesmas yang sama',
	'ditemukan ' . substr_count($block, '$this->monitoring_facility_where($filter)'));

mon_check(strpos($block, 'created_at >= ? AND created_at < ?') !== false,
	'permintaan masuk memakai batas setengah terbuka');
mon_check(strpos($block, 'completed_at >= ? AND completed_at < ?') !== false,
	'layanan selesai memakai batas setengah terbuka');
mon_check(stripos($block, ' BETWEEN ') === false,
	'tidak ada BETWEEN pada kolom waktu (batas setengah terbuka menjaga tepi periode)');

/* Kolom pada predikat rentang harus polos: membungkusnya dengan fungsi
   mematikan indeks. DATE() tetap boleh di daftar SELECT / GROUP BY (itu
   pengelompokan harian), jadi yang diperiksa hanya bentuk predikat. */
$bare = preg_match_all('/(\w+(?:\.\w+)?)\s*(?:>=|<=|<|>)\s*\?/', $block, $bare_matches);
mon_check($bare >= 5, 'predikat rentang memakai kolom polos di sisi kiri',
	'ditemukan ' . $bare . ' predikat: ' . implode(', ', array_unique($bare_matches[1])));
mon_check(preg_match('/\)\s*(?:>=|<=|<|>)\s*\?/', $block) === 0,
	'tidak ada ekspresi terbungkus fungsi yang dipakai sebagai predikat rentang');

mon_check(strpos($block, 'strtotime($start) - $shift') !== false
	&& strpos($block, 'strtotime($end) - $shift') !== false,
	'penggeseran kerangka jam dilakukan pada BATAS, bukan pada kolom');

mon_check(strpos($block, 'if ($shift === 0)') !== false,
	'penggeseran kerangka jam punya jalur cepat saat tidak ada selisih');

mon_check(strpos($block, "table_exists('requests')") !== false,
	'agregat berhenti dengan aman bila tabel requests tidak ada');

echo "\n== model: definisi KPI ==\n";
mon_check(strpos($block, 'selesai_pada_periode') !== false
	&& strpos($block, 'completed_at >= ?') !== false,
	'layanan selesai diukur dari waktu selesai, bukan waktu masuk');

/* Tingkat penyelesaian harus memakai kohort yang sama di pembilang dan
   penyebut. Membagi selesai-periode dengan masuk-periode bukan tingkat
   penyelesaian yang sah. */
mon_check(strpos($block, "\$current['selesai_kohort'] + \$current['batal_kohort'] + \$current['aktif_kohort']") !== false,
	'tingkat penyelesaian memakai kohort permintaan yang masuk pada periode itu');

mon_check(strpos($block, 'request_accept_reminder_minutes') !== false
	&& strpos($block, 'request_accept_overdue_minutes') !== false,
	'ambang eskalasi dibaca dari config, bukan ditulis di dalam kode');

mon_check(strpos($block, "'__legacy__'") !== false,
	'permintaan tanpa Puskesmas tujuan punya penanda sendiri, bukan dicampur');

mon_check(strpos($block, '$this->db->escape($filter)') !== false,
	'nilai penyaring Puskesmas di-escape, tidak disambung mentah');

echo "\n== model: tidak menyentuh catatan klinis ==\n";
/* Invarian yang sesungguhnya bukan \"dasbor tidak menulis sama sekali\", melainkan
   \"dasbor tidak pernah mengubah catatan klinis\". Penandaan tindak lanjut
   menulis, tetapi hanya ke tabel penandanya sendiri dan ke jejak audit. */
$clinical_writes = preg_match_all(
	'/\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO|TRUNCATE|ALTER\s+TABLE|DROP\s+TABLE)\s+`?(requests|medicalrecords)`?/i',
	$block
);
mon_check($clinical_writes === 0,
	'dasbor tidak pernah menulis ke requests atau medicalrecords',
	'ditemukan ' . (int) $clinical_writes);

$write_targets = array();
foreach (array(
	'/\bINSERT\s+INTO\s+`?([a-z_]+)`?/i',
	'/->insert\(\s*\'([a-z_]+)\'/i',
	'/->delete\(\s*\'([a-z_]+)\'/i',
	'/->update\(\s*\'([a-z_]+)\'/i',
) as $write_pattern) {
	if (preg_match_all($write_pattern, $block, $write_matches)) {
		foreach ($write_matches[1] as $write_target) {
			$write_targets[] = strtolower($write_target);
		}
	}
}
$write_targets = array_values(array_unique($write_targets));
/* Tiga tabel yang boleh ditulis dasbor: penanda tindak lanjut, simpanan
   sementara hasil pemeriksaan, dan jejak audit. Ketiganya milik dasbor atau
   jejak audit - tidak satu pun catatan klinis. */
$illegal_targets = array_diff($write_targets, array('monitoring_followups', 'monitoring_cache', 'audit_logs'));
mon_check(empty($illegal_targets),
	'penulisan hanya menyentuh tabel dasbor sendiri dan jejak audit',
	'target: ' . (empty($write_targets) ? '(tidak ada)' : implode(',', $write_targets)));

mon_check(strpos($block, 'public function monitoring_') !== false
	&& strpos($block, 'private function monitoring_') !== false,
	'metode agregat publik, pembantu tetap privat');

echo "\n== controller: masukan dari permintaan ==\n";
foreach (array('periode', 'dari', 'sampai', 'puskesmas') as $param) {
	mon_check(strpos($controller, "'" . $param . "'") !== false, "controller membaca parameter {$param}");
}
mon_check(strpos($controller, 'monitoring_resolve_period') !== false,
	'controller menyerahkan penafsiran periode ke model, bukan menghitungnya sendiri');

echo "\n== view: tidak menghitung, tidak menyentuh basis data ==\n";
mon_check(strpos($view, '$this->db') === false, 'view tidak menyentuh basis data');
/* View tidak boleh memuat kueri. Daftar Query Builder lebih tepat daripada
   kata "select", karena view ini memang punya elemen <select> HTML. */
mon_check(preg_match('/->(query|select|where|get|order_by|join|group_by)\s*\(/i', $view) === 0,
	'view tidak memuat kueri atau Query Builder');
mon_check(substr_count($view, 'html_escape') > 20, 'keluaran dinamis di-escape',
	'ditemukan ' . substr_count($view, 'html_escape'));
mon_check(strpos($view, '—') !== false,
	'nilai yang tidak tersedia ditampilkan sebagai tanda hubung, bukan nol');

/* Satu CSS per permukaan, dimuat sekali. */
mon_check(substr_count($view, 'doclinc-admin-monitoring.css') === 1,
	'berkas gaya permukaan dimuat tepat sekali',
	'ditemukan ' . substr_count($view, 'doclinc-admin-monitoring.css'));
mon_check(substr_count($view, 'doclinc-admin-monitoring.js') === 1,
	'berkas skrip permukaan dimuat tepat sekali');
mon_check(stripos($view, 'chart.js') === false && stripos($view, 'chart.min') === false,
	'Chart.js tidak dimuat ulang (sudah global di header panel)');
mon_check(stripos($view, '24 jam') === false && stripos($view, '24 hours') === false,
	'aturan waktu lama 24 jam tidak kembali ke permukaan ini');

echo "\n== kedua aplikasi tidak boleh berbeda ==\n";
foreach (array('request_accept_reminder_minutes', 'request_accept_overdue_minutes') as $key) {
	mon_check(strpos($adminConfig, "'" . $key . "'") !== false && strpos($mainConfig, "'" . $key . "'") !== false,
		"config {$key} ada di kedua aplikasi");
}
mon_check(strpos($adminConfig, 'date_default_timezone_set') !== false
	&& strpos($mainConfig, 'date_default_timezone_set') !== false,
	'zona waktu PHP dinyatakan di kedua config');
mon_check(strpos($adminConfig, "getenv('DOCLINC_TIMEZONE')") !== false
	&& strpos($mainConfig, "getenv('DOCLINC_TIMEZONE')") !== false,
	'kedua config membaca zona waktu dari DOCLINC_TIMEZONE yang sama');

echo "\n== permukaan: anggaran slop ==\n";
mon_check(preg_match('/#[0-9a-fA-F]{3,8}\b/', $surfaceCss) === 0, 'tidak ada hex baru di CSS permukaan');
mon_check(stripos($surfaceCss, 'important') === false, 'tidak ada penanda prioritas paksa');
mon_check(preg_match('/outline\s*:\s*none/i', $surfaceCss) === 0, 'outline tidak dihapus');
mon_check(strpos($surfaceCss, 'prefers-reduced-motion') !== false, 'gerak dihormati saat pengguna memintanya berhenti');
mon_check(substr_count($surfaceCss, 'var(--dl-') > 50, 'warna dari token design system',
	'ditemukan ' . substr_count($surfaceCss, 'var(--dl-'));

mon_check(strpos($surfaceJs, 'prefers-reduced-motion') !== false, 'JS juga menghormati permintaan berhenti bergerak');
mon_check(strpos($surfaceJs, 'typeof window.Chart') !== false,
	'JS tidak mogok bila Chart.js tidak ada');
mon_check(preg_match('/createElement\s*\(\s*[\'"]script/i', $surfaceJs) === 0,
	'JS tidak menyuntikkan berkas skrip baru');

echo "\n== pembaruan berkala: model ==\n";
mon_check(strpos($block, 'public function monitoring_live_slice') !== false,
	'model menyediakan irisan tipis untuk pembaruan berkala');
mon_check(strpos($block, "'sidik'") !== false,
	'irisan memuat sidik jari keadaan supaya klien dapat melewati render');

/* Definisi eskalasi hanya boleh ada satu. Kalau irisan menulis SQL-nya
   sendiri, ambang dan tingkatnya bisa berbeda dari panel pengecualian. */
$slice_start = strpos($block, 'public function monitoring_live_slice');
$slice_body = $slice_start === false ? '' : substr($block, $slice_start);
mon_check($slice_body !== '' && stripos($slice_body, 'SELECT ') === false,
	'irisan tidak menulis SQL sendiri; ia memakai agregat yang sudah ada');
mon_check($slice_body !== '' && stripos($slice_body, 'TIMESTAMPDIFF') === false,
	'irisan tidak menyalin ulang logika eskalasi');
mon_check($slice_body !== '' && strpos($slice_body, 'monitoring_outstanding(') !== false
	&& strpos($slice_body, 'monitoring_exceptions(') !== false,
	'irisan memanggil agregat yang sudah ada, bukan definisi kedua');
/* Diukur pada 100.000 baris: kueri baris pengecualian berharga ~115 ms per
   penyegaran, dan irisan tidak pernah membaca barisnya. Memintanya berarti
   membayar pengurutan atas seluruh pengecualian untuk hasil yang dibuang. */
mon_check(strpos($block, '$with_rows = true') !== false,
	'pengecualian dapat diminta tanpa daftar barisnya');
mon_check(strpos($block, 'monitoring_exceptions(0, $filter, false)') !== false,
	'irisan pembaruan berkala tidak membayar kueri baris yang tidak dibacanya');

$severity_definition = substr_count($block, "WHEN \$is_critical THEN 'kritis'");
mon_check($severity_definition === 1, 'tingkat keparahan hanya punya satu definisi',
	'ditemukan ' . $severity_definition);

echo "\n== pembaruan berkala: probe skema tidak diulang ==\n";
/* `DB_driver::field_exists()` memanggil `list_fields()` yang tidak menyimpan
   hasilnya pada build CodeIgniter di repo ini, jadi setiap panggilan adalah
   satu SHOW COLUMNS sungguhan. Diukur di staging: 115 kueri per muat halaman
   sebelum disimpan, 48 sesudahnya. */
mon_check(strpos($model, 'private function table_columns') !== false,
	'daftar kolom disimpan per tabel selama satu permintaan');
mon_check(strpos($model, '$this->db->field_exists(') === false,
	'tidak ada pemeriksaan kolom yang melewati jalur tersimpan');
mon_check(substr_count($model, 'field_exists_cached(') > 10,
	'seluruh pemeriksaan kolom memakai jalur tersimpan',
	'ditemukan ' . substr_count($model, 'field_exists_cached('));

echo "\n== pembaruan berkala: controller ==\n";
mon_check(strpos($controller, 'public function live_snapshot') !== false,
	'controller menyediakan endpoint pembaruan berkala');
mon_check(strpos($controller, "set_content_type('application/json')") !== false,
	'endpoint menjawab sebagai JSON');
mon_check(strpos($controller, "method(TRUE) !== 'GET'") !== false,
	'endpoint menolak metode selain GET');

/* Otorisasi sudah ditangani konstruktor untuk SETIAP metode. Menulis ulang
   pemeriksaan di dalam endpoint berarti ada dua definisi yang bisa berbeda. */
$live_start = strpos($controller, 'public function live_snapshot');
$live_end = strpos($controller, 'private function admin_monitoring_response');
$live_body = ($live_start !== false && $live_end !== false && $live_end > $live_start)
	? substr($controller, $live_start, $live_end - $live_start)
	: '';
mon_check($live_body !== '', 'badan endpoint terbaca');
mon_check($live_body !== '' && strpos($live_body, 'is_login') === false
	&& strpos($live_body, "userdata('level')") === false,
	'endpoint tidak menulis ulang otorisasi; konstruktor yang menanganinya');

echo "\n== pembaruan berkala: view ==\n";
$hooks = substr_count($view, 'data-dl-mon-live="');
mon_check($hooks >= 11, 'nilai yang disegarkan punya kait di halaman', 'ditemukan ' . $hooks);
mon_check(strpos($view, 'data-dl-mon-live-status') !== false,
	'ada baris status pembaruan');
mon_check(strpos($view, 'tiap 30 detik') !== false,
	'view menyebut iramanya, tidak berpura-pura angka mengalir seketika');
mon_check(strpos($view, "'liveUrl'") !== false && strpos($view, "'liveIntervalMs'") !== false,
	'URL dan irama pembaruan dikirim ke klien dari sisi server');
mon_check(strpos($view, 'aria-live="polite"') !== false,
	'perubahan angka diumumkan ke pembaca layar tanpa merebut fokus');

echo "\n== pembaruan berkala: JS ==\n";
mon_check(strpos($surfaceJs, 'function initLive') !== false, 'ada modul pembaruan berkala');
mon_check(strpos($surfaceJs, 'visibilitychange') !== false,
	'tab yang tidak terlihat berhenti menyegarkan');
mon_check(strpos($surfaceJs, "visibilityState === 'hidden'") !== false,
	'keadaan tersembunyi diperiksa, bukan hanya peristiwanya');
mon_check(strpos($surfaceJs, 'sidik') !== false,
	'klien membandingkan sidik sebelum menyentuh halaman');
mon_check(strpos($surfaceJs, 'setInterval') !== false, 'pembaruan berkala memakai selang waktu');
mon_check(strpos($surfaceJs, 'cache: false') === false,
	'tidak memakai cache-buster yang ditolak router CodeIgniter');

echo "\n== pembaruan berkala: CSS ==\n";
mon_check(strpos($surfaceCss, 'dl-mon-live') !== false, 'gaya baris status ada');
mon_check(strpos($surfaceCss, 'dl-mon-live-pulse') !== false, 'titik status punya denyut');
mon_check(strpos($surfaceCss, 'is-stale') !== false,
	'keadaan gagal menyegarkan punya gaya sendiri');

echo "\n== cakupan pelaporan per Puskesmas ==\n";
mon_check(strpos($block, "'fasilitas_melapor'") !== false
	&& strpos($block, "'cakupan_persen'") !== false,
	'model menghitung berapa fasilitas aktif yang benar-benar melaporkan');
/* Penyebut nol berarti "belum dapat dihitung", bukan "nol persen". Keduanya
   keadaan berbeda dan tidak boleh tampil sama. */
mon_check(strpos($block, "if (\$result['jumlah_fasilitas_aktif'] > 0)") !== false,
	'cakupan hanya dihitung bila penyebutnya ada');
mon_check(strpos($block, "'cakupan_persen' => null,") !== false,
	'penyebut kosong menghasilkan tanda hubung, bukan nol');
mon_check(strpos($view, 'Cakupan pelaporan') !== false,
	'view menampilkan cakupan pelaporan');
mon_check(strpos($view, 'belum menggambarkan seluruh wilayah kerja') !== false,
	'view menyatakan bahwa angka panel hanya mencerminkan fasilitas yang melapor');

echo "\n== penjaga kerangka jam terlihat ==\n";
mon_check(strpos($block, "'kerangka_jam_detik' => (int) \$frame_shift") !== false,
	'selisih kerangka jam dilaporkan ke dasbor, bukan hanya dikoreksi diam-diam');
$shift_calls = substr_count($block, '$this->monitoring_datetime_frame_shift_seconds()');
mon_check($shift_calls === 2, 'selisih kerangka jam dihitung di dua tempat saja',
	'ditemukan ' . $shift_calls);
mon_check(strpos($view, 'Batas periode digeser otomatis') !== false,
	'view menyatakan koreksi batas periode apa adanya');

echo "\n== catatan tren memakai definisi yang sama ==\n";
mon_check(strpos($view, "\$mutu_by_code['selesai_beda_kerangka']") !== false,
	'catatan tren membaca pemeriksaan mutu data, bukan menghitung ulang');
mon_check(strpos($view, 'artefak pencatatan') !== false,
	'view menyebut lompatan tren sebagai artefak pencatatan, bukan perubahan perilaku');

echo "\n== penandaan tindak lanjut: model ==\n";
mon_check(strpos($block, 'public function monitoring_mark_followup') !== false
	&& strpos($block, 'public function monitoring_unmark_followup') !== false,
	'model menyediakan penandaan beserta pencabutannya');
mon_check(strpos($block, 'public function monitoring_followup_map') !== false,
	'penanda dibaca sekali untuk seluruh baris, bukan satu kueri per baris');
mon_check(strpos($block, 'INSERT INTO monitoring_followups') !== false,
	'penanda disimpan di tabelnya sendiri');
$audit_calls = substr_count($block, '$this->monitoring_followup_audit(');
mon_check($audit_calls === 2
	&& strpos($block, "'monitoring_followup_marked'") !== false
	&& strpos($block, "'monitoring_followup_cleared'") !== false,
	'setiap perubahan meninggalkan jejak audit',
	'pemanggilan audit: ' . $audit_calls);
mon_check(strpos($block, "table_exists('monitoring_followups')") !== false,
	'fitur berhenti dengan aman bila tabel penandanya belum ada');
mon_check(strpos($block, 'ON DUPLICATE KEY UPDATE') !== false,
	'penandaan berulang tidak menggandakan baris');
mon_check(strpos($block, "'permintaan_tidak_ditemukan'") !== false,
	'penanda ditolak untuk permintaan yang tidak ada');
mon_check(strpos($block, "role', 'admin'") !== false,
	'pelaku jejak audit dipastikan admin aktif, bukan sekadar dari sesi');
/* Kemajuan dihitung di dalam kueri agregat pengecualian yang sudah ada, bukan
   dengan kueri tambahan yang menyalin definisi eskalasi. Polanya memakai batas
   kata: pencocokan awalan saja akan meloloskan alias yang sudah dirusak. */
$followup_alias = preg_match_all('/AS sudah_ditandai(?![\w])/', $block);
mon_check($followup_alias === 1,
	'kemajuan penandaan dihitung pada himpunan pengecualian yang sama',
	'ditemukan ' . $followup_alias);
mon_check(strpos($block, 'isset($counts->sudah_ditandai)') !== false,
	'nilai kemajuan benar-benar dibaca dari hasil kueri');
mon_check(strpos($block, "'sudah_ditandai' => 0,") !== false,
	'kemajuan punya nilai awal sebelum sumber data tersedia');
mon_check(strpos($block, 'LEFT JOIN monitoring_followups mf') !== false,
	'penanda disambungkan 1:1 sehingga hitungan tidak tergandakan');

echo "\n== penandaan tindak lanjut: controller ==\n";
mon_check(strpos($controller, 'public function followup_mark') !== false
	&& strpos($controller, 'public function followup_unmark') !== false,
	'controller menyediakan kedua endpoint penandaan');
$mark_start = strpos($controller, 'public function followup_mark');
$mark_end = strpos($controller, 'private function admin_monitoring_response');
$mark_body = ($mark_start !== false && $mark_end !== false && $mark_end > $mark_start)
	? substr($controller, $mark_start, $mark_end - $mark_start)
	: '';
mon_check($mark_body !== '', 'badan endpoint penandaan terbaca');
mon_check($mark_body !== '' && substr_count($mark_body, "method(TRUE) !== 'POST'") === 2,
	'kedua endpoint menolak metode selain POST');
mon_check($mark_body !== '' && strpos($mark_body, 'is_login') === false,
	'endpoint penandaan tidak menulis ulang otorisasi');

echo "\n== penandaan tindak lanjut: view ==\n";
mon_check(strpos($view, 'data-dl-mon-followup=') !== false, 'view memuat formulir penanda');
mon_check(strpos($view, 'method="post"') !== false, 'penulisan memakai POST, bukan tautan biasa');
mon_check(strpos($view, 'get_csrf_token_name()') !== false, 'formulir membawa token CSRF');
mon_check(strpos($view, 'Ditandai sudah ditindaklanjuti') !== false,
	'keadaan sudah ditandai dinyatakan di halaman');
mon_check(strpos($view, 'pengecualian sudah ditandai ditindaklanjuti.') !== false,
	'view menampilkan angka kemajuan pengawasan');
mon_check(strpos($view, 'is-ditandai') !== false,
	'baris yang sudah ditandai punya penanda visual');

echo "\n== penandaan tindak lanjut: JS dan CSS ==\n";
mon_check(strpos($surfaceJs, 'function initFollowup') !== false, 'ada penanganan penandaan di skrip');
mon_check(strpos($surfaceJs, "credentials: 'same-origin'") !== false,
	'permintaan penandaan membawa kredensial sesi');
mon_check(strpos($surfaceCss, 'dl-mon-followup-btn') !== false, 'tombol penanda punya gaya sendiri');
mon_check(strpos($surfaceCss, 'min-height: 44px') !== false,
	'tombol penanda memenuhi ambang sasaran sentuh 44px');

echo "\n== pelabelan panel diagnosis ==\n";
/* Panel ini sumber terdekat ke data penanganan pasien, yang menurut keputusan
   pemilik produk kelak menjadi asal angka sasaran. Salah label di sini akan
   menyesatkan keputusan program, jadi dikunci oleh uji. */
mon_check(strpos($view, 'Diagnosis tercatat per Puskesmas') !== false,
	'judul panel menyatakan isinya komposisi, bukan tren waktu');
mon_check(strpos($view, 'Tren Diagnosis') === false,
	'judul lama yang menyiratkan tren waktu sudah tidak dipakai');
mon_check(strpos($view, 'bukan angka kesakitan seluruh wilayah') !== false,
	'catatan menyatakan panel bukan angka kesakitan wilayah');
mon_check(strpos($view, 'tidak boleh dibaca sebagai data') !== false,
	'catatan menyatakan panel bukan data surveilans');
mon_check(strpos($view, 'maupun sebagai penyebut sasaran') !== false,
	'catatan menyatakan panel bukan penyebut sasaran');
mon_check(strpos($view, 'tidak mengikuti filter periode') !== false,
	'cakupan panel dinyatakan, supaya tidak tertukar dengan panel lain');
mon_check(strpos($view, 'belum tersedia') !== false,
	'ketiadaan angka sasaran dinyatakan apa adanya, bukan dikarang');

echo "\n== kueri diagnosis memakai indeks ==\n";
/* `DATE(x) >= D` setara persis dengan `x >= D` karena D adalah tanggal, tetapi
   pembungkusnya mematikan indeks pada kolom tanggalnya. Diukur pada 100.000
   permintaan: type=ALL 69.264 baris menjadi type=range 2.853 baris, dan waktu
   kueri 226 ms menjadi 59 ms. Jumlah serta sidik hasilnya identik. */
mon_check(preg_match('/DATE\(\s*\$date_expr/', $model) === 0,
	'penyaring tanggal tidak membungkus kolomnya dengan DATE()');
mon_check(strpos($model, '$date_expr >= DATE_SUB(CURDATE()') !== false,
	'penyaring tanggal membandingkan kolomnya langsung');
mon_check(preg_match('/DATE\([a-z_]+\./', $model) === 0,
	'tidak ada pembungkus DATE() atas kolom mana pun di seluruh model',
	'ditemukan ' . preg_match_all('/DATE\([a-z_]+\./', $model));

$mr_index_migration = dirname(__DIR__, 3) . '/admin_menu/application/migrations/20261025000300_medicalrecords_created_at_index.php';
mon_check(is_file($mr_index_migration),
	'migrasi indeks medicalrecords.created_at tersedia');
$mr_index_source = is_file($mr_index_migration) ? file_get_contents($mr_index_migration) : '';
mon_check(strpos($mr_index_source, 'idx_medicalrecords_created_at') !== false
	&& strpos($mr_index_source, 'REVERSIBLE_BY=') !== false,
	'migrasi indeks dapat dibatalkan dan menyatakan namanya');

echo "\n== simpanan hasil pemeriksaan mutu data ==\n";
/* Sepuluh pemeriksaan mutu data membaca seluruh riwayat: diukur 2.600 ms dari
   3.207 ms satu muat halaman pada 100.000 permintaan. Simpanannya harus
   memudar dengan aman dan umurnya harus terlihat di halaman. */
mon_check(strpos($block, 'private function monitoring_data_quality_compute') !== false,
	'perhitungan dipisahkan dari pembungkus simpanan');
mon_check(strpos($block, 'private function monitoring_cache_read') !== false
	&& strpos($block, 'private function monitoring_cache_write') !== false,
	'ada pembacaan dan penulisan simpanan');
mon_check(strpos($block, "table_exists('monitoring_cache')") !== false,
	'simpanan berhenti dengan aman bila tabelnya belum ada');
mon_check(strpos($block, "if (\$umur < 0 || \$umur > (int) \$ttl)") !== false,
	'simpanan yang kedaluwarsa dihitung ulang, bukan dipakai');
mon_check(strpos($block, "'dihitung_pada'") !== false && strpos($block, "'dari_simpanan'") !== false,
	'umur hasil ikut dilaporkan, bukan disembunyikan');
/* Asersi ini mengikat dua hal sekaligus: kalimat pengungkapannya ada, DAN
   kalimat itu memang dikendalikan oleh penanda simpanan. Sebelumnya hanya
   kalimatnya yang diperiksa, sehingga penanda yang terlepas dari tampilan
   masih lolos. */
mon_check(strpos($view, "!empty(\$mutu['dari_simpanan'])") !== false
	&& strpos($view, 'hasilnya dari simpanan') !== false,
	'view menyatakan bila angkanya berasal dari simpanan');
mon_check(strpos($view, 'bisa tertinggal beberapa menit') !== false,
	'view menyatakan keterbatasan umur simpanan apa adanya');
mon_check(strpos($adminConfig, 'monitoring_data_quality_cache_seconds') !== false,
	'umur simpanan dapat disetel lewat config');
mon_check(strpos($adminConfig, 'DOCLINC_MONITORING_DQ_CACHE_SECONDS') !== false,
	'umur simpanan dapat disetel lewat environment');
mon_check(strpos($mainConfig, 'monitoring_data_quality_cache_seconds') === false,
	'kunci hanya di panel admin, tempat panelnya dipakai');

$cache_migration = dirname(__DIR__, 3) . '/admin_menu/application/migrations/20261025000400_monitoring_cache.php';
mon_check(is_file($cache_migration), 'migrasi tabel simpanan tersedia');
$cache_source = is_file($cache_migration) ? file_get_contents($cache_migration) : '';
mon_check(strpos($cache_source, 'monitoring_cache') !== false
	&& strpos($cache_source, 'REVERSIBLE_BY=') !== false,
	'migrasi simpanan dapat dibatalkan dan menyatakan namanya');

echo "\n== hasil ==\n";
echo "MONITORING_DASHBOARD_PASSED={$passed}\n";
echo "MONITORING_DASHBOARD_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
