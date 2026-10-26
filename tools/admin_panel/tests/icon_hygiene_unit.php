<?php
/**
 * Kebersihan ikon Font Awesome (uji statis, tanpa basis data).
 *
 * Latar belakang: panel ini memuat Font Awesome **5.13.1**, tetapi dua nama
 * dari **Font Awesome 6** sempat dipakai - `fa-circle-info` dan
 * `fa-circle-check`. Di FA5 keduanya tidak ada, jadi `::before` kosong dan
 * yang tampil hanya kotak tombol 44px tanpa isi: tombol bantuan di /home,
 * /kelola_staff_puskesmas, dan /laporan. Sapuan visual F5b tidak menangkapnya
 * karena yang disapu kelas CSS, bukan nama ikon; uji ini menutup celah itu.
 *
 * Yang diperiksa:
 *   1. setiap token `fa-*` di view/JS benar-benar punya `.fa-<nama>:before`
 *      di bundel CSS lokal (bukan di CDN, bukan di tebakan);
 *   2. token yang bukan nama ikon (modifier seperti fa-fw/fa-lg/fa-spin)
 *      diizinkan lewat daftar putih tertutup, sehingga modifier baru harus
 *      disengaja;
 *   3. bundel CSS terbaca dan berisi ribuan definisi - kalau berkasnya hilang
 *      atau formatnya berubah, uji ini gagal alih-alih "lulus tanpa memeriksa".
 */
$root = dirname(__DIR__, 3) . '/';
$passed = 0;
$failed = 0;

function icon_check($condition, $label, $detail = '')
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

$fa_css_path = $root . 'admin_menu/assets/vendor/fontawesome-free/css/all.min.css';
$fa_css = @file_get_contents($fa_css_path);

icon_check($fa_css !== false, 'bundel CSS Font Awesome lokal terbaca', $fa_css_path);
if ($fa_css === false) {
	echo "\n== ringkasan ==\n  lulus: 0, gagal: {$failed}\n";
	exit(1);
}

/* Versi disebut di komentar kepala bundel; dipakai untuk pesan kegagalan supaya
   orang yang memperbaikinya tahu nama mana yang harus dicari. */
$fa_version = 'tidak diketahui';
if (preg_match('/Font Awesome Free ([0-9]+\.[0-9]+\.[0-9]+)/', $fa_css, $m)) {
	$fa_version = $m[1];
}

/* Nama ikon: `.fa-x:before` di bundel. */
$defined = array();
if (preg_match_all('/\.fa-([a-z0-9-]+):before/', $fa_css, $defs)) {
	$defined = array_fill_keys($defs[1], true);
}
icon_check(count($defined) > 1000, 'bundel memuat daftar nama ikon',
	'terbaca ' . count($defined) . ' nama, mengharapkan >1000');

/* Modifier resmi Font Awesome 5 yang bukan nama ikon. Daftar tertutup: token
   baru harus ditambahkan di sini dengan sengaja, bukan lolos diam-diam. */
$modifiers = array(
	'fw', 'ul', 'li', 'border', 'inverse', 'stack', 'stack-1x', 'stack-2x',
	'spin', 'pulse', 'pull-left', 'pull-right', 'fixed-width',
	'xs', 'sm', 'lg',
	'2x', '3x', '4x', '5x', '6x', '7x', '8x', '9x', '10x',
);
$modifier_set = array_fill_keys($modifiers, true);

/* Berkas yang dipindai: semua view/controller PHP panel admin + JS milik panel.
   Bundel vendor tidak ikut (isinya justru sumber kebenaran). */
function icon_files($dir, &$out)
{
	foreach (glob($dir . '*', GLOB_ONLYDIR) as $sub) {
		icon_files($sub . '/', $out);
	}
	/* Dua glob terpisah, bukan GLOB_BRACE: sebagian build PHP (termasuk build
	   lokal di mesin ini) tidak menyediakan GLOB_BRACE. */
	foreach (array('*.php', '*.js') as $pattern) {
		foreach (glob($dir . $pattern) as $file) {
			$out[] = $file;
		}
	}
}

$files = array();
icon_files($root . 'admin_menu/application/', $files);
icon_files($root . 'admin_menu/assets/js/', $files);
sort($files);
icon_check(count($files) > 50, 'berkas panel terkumpul untuk dipindai',
	'terkumpul ' . count($files) . ' berkas');

/* Pemindaian. Token diambil dari teks apa adanya: `fas fa-user`, `fa fa-bars`,
   dan `'<i class="fas fa-check-circle">'` semuanya tertangkap oleh awalan
   fa/fas/far/fal/fab/fad. */
$dead = array();
$unknown_modifier = array();
$scanned_tokens = 0;

foreach ($files as $file) {
	$text = @file_get_contents($file);
	if ($text === false) {
		continue;
	}
	if (!preg_match_all('/\b(fa[srlbd]?)-([a-z0-9]+(?:-[a-z0-9]+)*)/', $text, $matches, PREG_OFFSET_CAPTURE)) {
		continue;
	}
	foreach ($matches[2] as $i => $capture) {
		$name = $capture[0];
		$offset = $matches[0][$i][1];
		$scanned_tokens++;

		if (isset($defined[$name])) {
			continue;
		}
		if (isset($modifier_set[$name])) {
			continue;
		}
		/* `fa-<angka>x` sudah tercakup daftar putih, tetapi jaga-jaga bila
		   Font Awesome menambah ukuran lain di masa depan. */
		if (preg_match('/^[0-9]+x$/', $name)) {
			continue;
		}
		$line = substr_count(substr($text, 0, $offset), "\n") + 1;
		$where = str_replace($root, '', $file) . ':' . $line;
		if (strpos($name, '-') !== false && in_array($name, $modifiers, true)) {
			$unknown_modifier[$where . '  fa-' . $name] = true;
			continue;
		}
		$dead[$where . '  fa-' . $name] = true;
	}
}

icon_check($scanned_tokens > 50, 'token ikon benar-benar terbaca dari sumber',
	'terbaca ' . $scanned_tokens . ' token');

if ($dead !== array()) {
	echo "  -- nama ikon tanpa definisi di Font Awesome {$fa_version}:\n";
	foreach (array_keys($dead) as $where) {
		echo "       {$where}\n";
	}
}
icon_check($dead === array(),
	'semua nama ikon ada di bundel Font Awesome ' . $fa_version,
	count($dead) . ' token mati');

if ($unknown_modifier !== array()) {
	foreach (array_keys($unknown_modifier) as $where) {
		echo "       modifier tidak dikenal: {$where}\n";
	}
}
icon_check($unknown_modifier === array(), 'tidak ada modifier fa-* di luar daftar putih');

/* Penjaga terhadap "hijau karena tidak memeriksa": dua nama FA6 yang dulu
   lolos dipakai sebagai contoh negatif. Kalau bundelnya naik ke FA6 kelak,
   uji ini justru harus berubah - bukan diam-diam lulus. */
icon_check(isset($defined['info-circle']) && isset($defined['check-circle'])
	&& !isset($defined['circle-info']) && !isset($defined['circle-check']),
	'bundel masih FA5: fa-info-circle/fa-check-circle ada, bentuk FA6 tidak');

echo "\n== ringkasan ==\n";
echo '  lulus: ' . $passed . ', gagal: ' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
