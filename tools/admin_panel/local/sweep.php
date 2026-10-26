<?php
/**
 * Sapuan rute panel admin pada fixture lokal.
 *
 * Fungsinya bukan menguji logika bisnis, melainkan memastikan satu hal:
 * SETIAP halaman panel admin benar-benar dapat dirender dari data yang ada.
 * Tanpa langkah ini, redesign tata letak bisa "berhasil" di dua halaman yang
 * kebetulan dibuka dan tetap menyisakan halaman yang fatal error karena kolom
 * yang tidak ada.
 *
 * Yang diperiksa per rute:
 *   - status HTTP 200 (3xx dianggap gagal: biasanya sesi hilang)
 *   - tidak ada jejak galat PHP atau MySQL di badan halaman
 *   - jumlah <h1> dan <h2> dicatat sebagai metrik sistem judul
 *
 * Pemakaian:
 *   docker exec doclinc-app php /var/www/html/tools/admin_panel/local/sweep.php
 *   docker exec doclinc-app php /var/www/html/tools/admin_panel/local/sweep.php \
 *     --json=/tmp/sweep.json
 *
 * Basis URL dapat diubah lewat BASE_URL; kredensial lewat ADMIN_EMAIL dan
 * ADMIN_PASSWORD (default sama dengan seed `bootstrap.php`). Default-nya
 * menyertakan `/admin_menu/` karena begitulah panel disajikan — baik di
 * produksi maupun di harness (`tools/admin_panel/local/router.php`). Tanpa
 * prefiks itu, sapuan akan mengukur halaman lain dan hasilnya menyesatkan.
 */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "refuse: hanya untuk CLI\n");
	exit(2);
}

/* Sapuan memakai ekstensi curl PHP, bukan biner `curl`. Bedanya penting: image
   harness memasang ekstensi (bawaannya php:apache) tetapi tidak selalu biner
   CLI-nya, dan galat "call to undefined function curl_init()" tidak menjelaskan
   apa pun. Diperiksa di sini supaya pesannya jelas. */
if (!function_exists('curl_init')) {
	fwrite(STDERR, "refuse: ekstensi curl PHP tidak tersedia (pasang ext-curl)\n");
	exit(2);
}

/* Penjaga host lokal, bukan kemalasan: sapuan menembak 20 rute dengan sesi
   admin yang sudah masuk, jadi satu salah ketik BASE_URL saja cukup untuk
   mengirim 20 permintaan ber-sesi ke produksi. Staging karena itu diperiksa
   dengan uji browser (`tools/task11_browser`, TASK11_BASE_URL) yang menguji
   hal yang sama - status 200, tanpa jejak galat, tidak terpantul ke login -
   tanpa pernah menembakkan 20 rute sekaligus dari harness ini. */
$base = rtrim(getenv('BASE_URL') ?: 'http://127.0.0.1:8080/admin_menu', '/') . '/';
$host = parse_url($base, PHP_URL_HOST);
if (!in_array($host, array('127.0.0.1', 'localhost'), true)) {
	fwrite(STDERR, "REFUSE: sapuan hanya untuk host lokal (didapat: {$host});"
		. " untuk staging pakai uji browser: tools/task11_browser + TASK11_BASE_URL\n");
	exit(2);
}

$email = getenv('ADMIN_EMAIL') ?: 'admin@lokal.test';
$password = getenv('ADMIN_PASSWORD') ?: 'Lokal#2026!';
$json_path = null;
foreach ($_SERVER['argv'] as $arg) {
	if (strpos($arg, '--json=') === 0) {
		$json_path = substr($arg, 7);
	}
}

/* 20 rute = dasbor + 19 modul, diambil dari tautan sidebar di
   `application/views/commons/header.php` (termasuk "Sesi & keamanan").
   Daftar ini sengaja literal: bila sebuah modul ditambahkan ke sidebar, sapuan
   harus ikut diperbarui, dan itu memang yang diinginkan (tidak ada rute yang
   lolos diam-diam). */
$routes = array(
	'home' => 'Dasbor',
	'kelola_warga' => 'Kelola warga',
	'kelola_dokter_nakes' => 'Kelola dokter & nakes',
	'kelola_staff_puskesmas' => 'Kelola staf Puskesmas',
	'master_puskesmas' => 'Master Puskesmas',
	'wilayah' => 'Wilayah',
	'rekam_medis' => 'Rekam medis',
	'diagnosis' => 'Diagnosis',
	'icd10' => 'ICD-10',
	'kelola_tindakan' => 'Kelola tindakan',
	'kelola_keluhan' => 'Kelola keluhan',
	'kelola_layanan_kesehatan' => 'Kelola layanan kesehatan',
	'konsultasi_kesehatan' => 'Konsultasi kesehatan',
	'kelola_news_feed' => 'News feed',
	'konten_landing' => 'Konten landing',
	'landing_page' => 'Landing page',
	'laporan' => 'Laporan',
	'kapabilitas' => 'Kapabilitas admin',
	'sesi' => 'Sesi & keamanan',
	'audit' => 'Audit',
);

$jar = tempnam(sys_get_temp_dir(), 'doclinc-cookie-');
$failures = 0;
$report = array('base' => $base, 'generated_at' => date('c'), 'routes' => array());

function http_request($url, $post_fields = null, $jar = null)
{
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_HEADER => true,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_COOKIEJAR => $jar,
		CURLOPT_COOKIEFILE => $jar,
	));
	if ($post_fields !== null) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
	}
	$started = microtime(true);
	$raw = curl_exec($ch);
	$elapsed_ms = (int) round((microtime(true) - $started) * 1000);
	$error = curl_error($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$sent_bytes = (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
	curl_close($ch);

	if ($raw === false) {
		return array('status' => 0, 'error' => $error, 'body' => '', 'headers' => '', 'ms' => $elapsed_ms, 'bytes' => 0);
	}
	$split = strpos($raw, "\r\n\r\n");
	$headers = $split === false ? '' : substr($raw, 0, $split);
	$body = $split === false ? $raw : substr($raw, $split + 4);
	return array(
		'status' => $status,
		'error' => '',
		'body' => $body,
		'headers' => $headers,
		'ms' => $elapsed_ms,
		'bytes' => $sent_bytes > 0 ? $sent_bytes : strlen($body),
	);
}

function header_value($headers, $name)
{
	if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $headers, $match)) {
		return trim($match[1]);
	}
	return null;
}

/**
 * Pemeriksaan galat sengaja memakai potongan teks yang khas, bukan satu pola
 * besar: halaman CodeIgniter menampilkan galat dengan beberapa gaya berbeda
 * tergantung environment, dan yang dicari adalah kehadirannya, bukan bentuknya.
 */
function page_errors($body)
{
	$needles = array(
		'A PHP Error was encountered',
		'<p>Severity:',
		'Fatal error:',
		'Parse error:',
		'Database Error',
		'Unknown column',
		"doesn't exist",
		'does not exist in the database',
		'Unable to connect to your database',
	);
	$found = array();
	foreach ($needles as $needle) {
		if (stripos($body, $needle) !== false) {
			$found[] = $needle;
		}
	}
	return $found;
}

echo "== login: {$email} di {$base} ==\n";
$login_page = http_request($base . 'login', null, $jar);
if ($login_page['status'] !== 200) {
	fwrite(STDERR, "GAGAL membuka halaman login (HTTP {$login_page['status']} {$login_page['error']})\n");
	fwrite(STDERR, "Petunjuk: pastikan container aplikasi hidup dan fixture sudah dijalankan.\n");
	exit(1);
}
if (!preg_match('/name="csrf_test_name"\s+value="([^"]+)"/', $login_page['body'], $csrf)) {
	fwrite(STDERR, "GAGAL: token CSRF tidak ditemukan pada formulir login\n");
	exit(1);
}
$login_post = http_request($base . 'login/ceklogin', array(
	'email' => $email,
	'password' => $password,
	'csrf_test_name' => $csrf[1],
), $jar);
$login_body = trim(strip_tags($login_post['body']));
if ($login_body !== '1') {
	fwrite(STDERR, "GAGAL masuk (HTTP {$login_post['status']}, jawaban: " . substr($login_body, 0, 120) . ")\n");
	fwrite(STDERR, "Petunjuk: jalankan bootstrap.php lebih dulu, atau periksa ADMIN_EMAIL/ADMIN_PASSWORD.\n");
	exit(1);
}
echo "== login: berhasil ==\n\n";

/**
 * Prefix `index.php/` dideteksi sekali di awal: sebagian deployment memakai
 * rewrite, sebagian tidak, dan sapuan ini harus jalan di keduanya tanpa
 * konfigurasi tambahan.
 */
$prefix = '';
$prefix_checked = false;

printf("%-4s %-28s %3s %7s %8s %5s %5s  %s\n", 'HASIL', 'RUTE', 'KODE', 'WAKTU', 'UKURAN', 'H1', 'H2', 'JUDUL');
echo str_repeat('-', 110) . "\n";

foreach ($routes as $route => $label) {
	$attempt = 0;
	do {
		$attempt++;
		$result = http_request($base . $prefix . $route, null, $jar);
		if ($result['status'] === 404 && !$prefix_checked) {
			$prefix = 'index.php/';
			$prefix_checked = true;
			continue;
		}
		break;
	} while ($attempt < 3);
	$prefix_checked = true;

	$errors = $result['status'] === 200 ? page_errors($result['body']) : array();
	$location = header_value($result['headers'], 'Location');
	$is_login_page = stripos($result['body'], 'Masukkan alamat email') !== false;
	$title = preg_match('#<title>(.*?)</title>#is', $result['body'], $match) ? trim(preg_replace('/\s+/', ' ', $match[1])) : '';
	$h1 = preg_match_all('/<h1[\s>]/i', $result['body']);
	$h2 = preg_match_all('/<h2[\s>]/i', $result['body']);

	$ok = $result['status'] === 200 && empty($errors) && !$is_login_page;
	if (!$ok) {
		$failures++;
	}

	$note = $title;
	if (!$ok) {
		$reason = array();
		if ($result['status'] !== 200) {
			$reason[] = 'HTTP ' . $result['status'] . ($location ? ' -> ' . $location : '');
		}
		if (!empty($errors)) {
			$reason[] = 'galat: ' . implode(', ', array_slice($errors, 0, 2));
		}
		if ($is_login_page) {
			$reason[] = 'dipantulkan ke halaman login';
		}
		$note = implode(' | ', $reason);
	}

	printf(
		"%-4s %-28s %3d %6dms %7dB %5d %5d  %s\n",
		$ok ? 'OK' : 'FAIL',
		$route,
		$result['status'],
		$result['ms'],
		$result['bytes'],
		$h1,
		$h2,
		$note
	);

	$report['routes'][] = array(
		'route' => $route,
		'label' => $label,
		'ok' => $ok,
		'status' => $result['status'],
		'ms' => $result['ms'],
		'bytes' => $result['bytes'],
		'title' => $title,
		'h1' => $h1,
		'h2' => $h2,
		'errors' => $errors,
		'redirect' => $location,
		'login_page' => $is_login_page,
	);
}

$report['failures'] = $failures;
$report['total'] = count($routes);

echo str_repeat('-', 110) . "\n";
$heading_note = array();
foreach ($report['routes'] as $row) {
	$heading_note[$row['h1']] = ($heading_note[$row['h1']] ?? 0) + 1;
}
ksort($heading_note);
$heading_summary = array();
foreach ($heading_note as $count => $routes_count) {
	$heading_summary[] = $count . ' h1 di ' . $routes_count . ' rute';
}
echo '== judul: ' . implode(', ', $heading_summary) . " ==\n";
echo '== hasil: ' . (count($routes) - $failures) . '/' . count($routes) . " rute bersih ==\n";

if ($json_path !== null) {
	file_put_contents($json_path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
	echo "== laporan: {$json_path} ==\n";
}

@unlink($jar);
exit($failures === 0 ? 0 : 1);

