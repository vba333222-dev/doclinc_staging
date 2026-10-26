<?php
/**
 * Kontrak IA navigasi samping (uji statis, tanpa basis data).
 *
 * F5 mengubah tujuh judul sidebar - lima di antaranya berisi satu butir -
 * menjadi empat kelompok. Yang paling mudah rusak sesudahnya bukan tampilan,
 * melainkan ISI: satu tautan hilang saat blok <li> ditulis ulang, atau satu
 * rute tertinggal dari daftar sapuan. Uji ini menahan keduanya:
 *
 *   1. kelompok, urutan, dan isinya sama dengan daftar harapan yang ditulis
 *      literal di sini (bukan dibaca dari sumber yang sedang diuji);
 *   2. himpunan rute sidebar sama dengan himpunan rute `sweep.php`, sehingga
 *      tidak ada halaman yang bisa lolos dari sapuan;
 *   3. penanda aktif dihitung dari rute butir itu, bukan dari variabel kedua
 *      yang harus dirawat tangan;
 *   4. pemisah kelompok tetap punya aturan CSS, dan sidebar tetap bisa
 *      digulir - dua puluh tautan lebih tinggi dari layar 900px.
 */
$root = dirname(__DIR__, 3) . '/';
$passed = 0;
$failed = 0;

function f5_check($condition, $label, $detail = '')
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

$header = @file_get_contents($root . 'admin_menu/application/views/commons/header.php');
$sweep = @file_get_contents($root . 'tools/admin_panel/local/sweep.php');
$shell = @file_get_contents($root . 'admin_menu/assets/css/doclinc-admin-shell.css');
$custom = @file_get_contents($root . 'admin_menu/assets/css/custom.css');

f5_check($header !== false && $sweep !== false && $shell !== false && $custom !== false,
	'berkas yang diperiksa terbaca');

/* Harapan ditulis literal: kelompok -> rute, berurutan. */
$expected = array(
	'Operasional' => array('home', 'rekam_medis', 'konsultasi_kesehatan', 'kelola_tindakan'),
	'Data induk' => array('kelola_warga', 'kelola_dokter_nakes', 'kelola_staff_puskesmas',
		'master_puskesmas', 'wilayah', 'icd10', 'diagnosis', 'kelola_keluhan'),
	'Konten' => array('kelola_layanan_kesehatan', 'kelola_news_feed', 'konten_landing', 'landing_page'),
	'Tata kelola' => array('laporan', 'kapabilitas', 'audit', 'sesi'),
);

f5_check(substr_count($header, '$doclinc_sidebar_groups = array(') === 1,
	'navigasi didefinisikan satu kali sebagai daftar data');

/* Kelompok diparse dari sumbernya sendiri, lalu dibandingkan dengan harapan. */
$found = array();
if (preg_match_all("/\n\t{3}'([^']+)' => array\((.*?)\n\t{3}\),/s", $header, $groups, PREG_SET_ORDER)) {
	foreach ($groups as $group) {
		preg_match_all("/'route' => '([^']+)'/", $group[2], $routes);
		$found[$group[1]] = $routes[1];
	}
}

f5_check(array_keys($found) === array_keys($expected), 'tepat empat kelompok, dalam urutan yang disepakati',
	implode(', ', array_keys($found)));
foreach ($expected as $label => $routes) {
	f5_check(isset($found[$label]) && $found[$label] === $routes,
		"isi kelompok {$label}", isset($found[$label]) ? implode(',', $found[$label]) : 'tidak ada');
}

$all = array();
foreach ($found as $routes) {
	$all = array_merge($all, $routes);
}
f5_check(count($all) === 20, 'dua puluh tautan hadir', (string) count($all));
f5_check(count(array_unique($all)) === count($all), 'tidak ada rute ganda',
	implode(', ', array_diff_key($all, array_unique($all))));

/* Label dan ikon: dua tautan dengan ikon sama membuat mata kehilangan
   pembeda; sebelumnya Tindakan dan Penampil audit sama-sama fa-clipboard-list. */
preg_match_all("/'text' => '([^']+)'/", $header, $texts);
preg_match_all("/'icon' => '([^']+)'/", $header, $icons);
f5_check(count($texts[1]) === 20 && count(array_unique($texts[1])) === 20,
	'tiap tautan berlabel unik', (string) count($texts[1]));
f5_check(count($icons[1]) === 20 && count(array_unique($icons[1])) === 20,
	'tiap tautan berikon unik', (string) count($icons[1]));

/* Satu sumber penanda aktif: rute butir itu sendiri. */
f5_check(substr_count($header, '$doclinc_active_') === 0,
	'tidak ada variabel penanda aktif terpisah');
f5_check(substr_count($header, "\$doclinc_admin_segment === \$doclinc_item['route']") === 1,
	'penanda aktif dihitung dari rute butir');

/* Potongan sidebar saja: topbar juga memakai kelas `nav-item`, jadi hitungan
   pada seluruh berkas akan selalu lebih dari satu tanpa ada yang salah. */
$sidebar_start = strpos($header, 'id="accordionSidebar"');
$sidebar_end = $sidebar_start === false ? false : strpos($header, '</ul>', $sidebar_start);
$sidebar = ($sidebar_start === false || $sidebar_end === false)
	? ''
	: substr($header, $sidebar_start, $sidebar_end - $sidebar_start);
f5_check($sidebar !== '' && substr_count($sidebar, '<li class="nav-item') === 1,
	'butir navigasi dirender perulangan, bukan ditulis tangan',
	'<li class="nav-item" di sidebar: ' . substr_count($sidebar, '<li class="nav-item'));
/* <ul> hanya boleh berisi <li>: pembungkus tombol ciut dulu <div> dan logo
   dulu <a> langsung di dalam <ul> (bawaan SB Admin). Keduanya kini <li>, jadi
   struktur daftar navigasi sah - sebagian pembaca layar mengumumkan daftar
   dengan struktur rusak sebagai satu blok tanpa butir. */
f5_check(strpos($header, '<div class="text-center') === false
	&& strpos($header, '<li class="text-center d-none d-md-inline">') !== false,
	'pembungkus tombol ciut adalah <li>, bukan <div>');
f5_check(preg_match('/<li class="doclinc-sidebar-brand-item">\s*<a class="sidebar-brand/', $header) === 1,
	'logo sidebar dibungkus <li>');
f5_check(substr_count($header, "site_url(\$doclinc_item['route'])") === 1,
	'tautan dibentuk dari rute butir');
f5_check(strpos($header, "aria-current=\"page\"") !== false,
	'halaman aktif ditandai aria-current');

/* Sidebar dan sapuan harus memeriksa himpunan rute yang sama. Blok $routes
   dipotong dulu: berkas sapuan punya array lain (body, error, headers) yang
   kuncinya bukan rute. */
$sweep_start = strpos($sweep, '$routes = array(');
$sweep_block = $sweep_start === false ? '' : substr($sweep, $sweep_start, strpos($sweep, "\n);", $sweep_start) - $sweep_start);
preg_match_all("/'([a-z0-9_]+)' => '/", $sweep_block, $swept);
$sidebar_set = $all;
$sweep_set = $swept[1];
sort($sidebar_set);
sort($sweep_set);
f5_check(count($sweep_set) === 20 && $sidebar_set === $sweep_set,
	'rute sidebar sama dengan rute sweep.php',
	'beda: ' . implode(',', array_merge(array_diff($sidebar_set, $sweep_set), array_diff($sweep_set, $sidebar_set))));

/* Wayfinding: dua puluh tautan lebih tinggi dari layar 900px, jadi butir aktif
   harus dijangkau tanpa menggeser halaman. */
f5_check(strpos($header, "sidebar.querySelector('.nav-item.active')") !== false,
	'butir aktif digulirkan ke dalam pandangan');
f5_check(strpos($header, 'sidebar.scrollTop = offset > 0 ? offset : 0;') !== false,
	'hanya wadah sidebar yang digeser, dan tidak pernah negatif');

/* Panel geser di layar sempit. Tiga bagian harus sepakat: ambang 768px di
   custom.css, keadaan terbuka di shell CSS, dan skrip header. Sebelumnya
   keadaan terbuka dikaitkan ke `.show` yang tidak pernah dipasang siapa pun,
   sehingga menu tidak bisa dibuka di ponsel dan tidak ada uji yang menangkapnya. */
/* Aturan mati diperiksa sebagai aturan, bukan sebagai kata: komentar
   penggantinya menyebut selektor itu untuk menjelaskan mengapa ia dihapus. */
f5_check(preg_match('/\.doclinc-sidebar\.show\s*\{/', $custom) === 0,
	'aturan `.doclinc-sidebar.show` yang tidak pernah cocok sudah dihapus');
f5_check(preg_match('/@media \(max-width: 768px\)\s*\{[^@]*body:not\(\.sidebar-toggled\) \.doclinc-sidebar\s*\{\s*transform: translateX\(0\)/s', $shell) === 1,
	'keadaan terbuka mengikuti kelas yang dipakai SB Admin');
f5_check(strpos($header, "id=\"sidebarBackdrop\"") !== false && strpos($header, "backdrop.hidden = !(narrowScreen && open);") !== false,
	'latar gelap disediakan dan dikendalikan skrip');
f5_check(strpos($header, "matchMedia('(max-width: 768px)')") !== false,
	'ambang skrip sama dengan ambang CSS (768px)');
f5_check(strpos($header, "event.key === 'Escape' && drawerOpen()") !== false,
	'Escape menutup panel geser');
f5_check(strpos($header, "button.setAttribute('aria-expanded'") !== false,
	'aria-expanded tombol menu disinkronkan');

/* Kontras keadaan sidebar. Dua aturan warisan (pil `#e0f2f1` + teks
   `--green-dark`) dulu menghasilkan teks putih di atas latar hampir putih
   (1,2:1) saat hover, dan teks hijau tua di atas pil gelap (1,1:1) sesudah
   pilnya diperbaiki. Keduanya dihapus; keadaan sekarang hanya dari token.
   Angka kontrasnya: aktif 8,3:1, hover 6,8:1 di atas hijau panel. */
f5_check(preg_match('/background(-color)?:\s*#e0f2f1/i', $custom) === 0,
	'pil hampir putih di sidebar sudah dihapus dari custom.css');
f5_check(preg_match('/--doclinc-sidebar-active:\s*rgba\(0, 0, 0,/', $custom) === 1,
	'butir aktif memakai lapisan gelap di tema terang');
f5_check(preg_match('/--doclinc-sidebar-hover:\s*rgba\(0, 0, 0,/', $custom) === 1,
	'butir hover memakai lapisan gelap di tema terang');

/* Batas kelompok harus terlihat, dan sidebar harus bisa digulir. */
f5_check(preg_match('/\.doclinc-sidebar \.doclinc-sidebar-heading\s*\{[^}]*border-top/s', $shell) === 1,
	'judul kelompok diberi garis pemisah');
f5_check(strpos($header, 'doclinc-sidebar-section doclinc-sidebar-heading') !== false,
	'judul kelompok memakai kelas yang digayai');
f5_check(preg_match('/\.doclinc-sidebar\s*\{[^}]*overflow-y:\s*auto/s', $custom) === 1,
	'sidebar dapat digulir');

echo "\n== ringkasan ==\n";
echo '  lulus: ' . $passed . ', gagal: ' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
