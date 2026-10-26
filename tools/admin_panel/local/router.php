<?php
/**
 * Router `php -S` untuk harness panel admin.
 *
 * Docker adalah jalur utama (lihat `docker-compose.yml`), tetapi harness ini
 * harus tetap bisa dipakai di mesin yang tidak mengizinkan daemon Docker —
 * misalnya laptop tanpa hak sudo. PHP bawaan (`php -S`) sudah cukup, asalkan
 * tata letaknya SAMA dengan produksi.
 *
 * Yang ditiru: panel disajikan dari `/admin_menu/` di bawah akar repo, bukan
 * dari akar repo. Ini bukan detail kosmetik. `commons/header.php` memuat token
 * desain lewat `base_url('../assets/css/doclinc-reference-ui.css')`, jadi
 * `/admin_menu/../assets/...` harus sampai ke `assets/` milik repo. Bila
 * DocumentRoot diarahkan langsung ke `admin_menu/`, permintaan itu keluar dari
 * docroot, diteruskan ke CodeIgniter, dan dijawab halaman login HTTP 200
 * berbadan HTML - bukan 404. Akibatnya setiap `var(--dl-ref-*)` menjadi tidak
 * terdefinisi, ukuran teks jatuh ke nilai warisan, dan tangkapan layar lokal
 * tidak lagi mewakili staging.
 *
 * Pemakaian (dari akar repo, dengan fixture sudah dijalankan):
 *
 *   ADMIN_BASE_URL=http://127.0.0.1:8080/admin_menu/ \
 *   ADMIN_DB_HOST=127.0.0.1 ADMIN_DB_PORT=3307 \
 *   ADMIN_DB_NAME=doclinc_stage_local_test \
 *   ADMIN_DB_USER=doclinc ADMIN_DB_PASS=doclinclokal \
 *   DOCLINC_SESSION_PATH=$HOME/.local/doclinc-sessions \
 *     php -S 127.0.0.1:8080 -t . tools/admin_panel/local/router.php
 *
 * Penjaga (sama seperti `bootstrap.php`): host database wajib loopback dan nama
 * database wajib memuat "stage" dan "test". Router ini melayani fixture berisi
 * akun contoh, jadi ia menolak berjalan di atas database lain — termasuk kalau
 * variabel lingkungannya lupa diisi dan `config/database.php` jatuh ke default.
 */

$root = dirname(__DIR__, 3);
$app = $root . '/admin_menu';

$db_host = strtolower(trim((string) (getenv('ADMIN_DB_HOST') ?: getenv('DB_HOST') ?: '')));
$db_name = (string) (getenv('ADMIN_DB_NAME') ?: getenv('DB_NAME') ?: '');

if (!in_array($db_host, array('127.0.0.1', 'localhost'), true)
	|| stripos($db_name, 'stage') === false
	|| stripos($db_name, 'test') === false) {
	http_response_code(500);
	header('Content-Type: text/plain; charset=utf-8');
	echo "router: penjaga fixture gagal (host='{$db_host}', nama='{$db_name}')\n";
	echo "butuh ADMIN_DB_HOST loopback dan ADMIN_DB_NAME yang memuat 'stage' dan 'test'\n";
	return true;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';

if ($path === '/') {
	header('Location: /admin_menu/', true, 302);
	return true;
}

/* Berkas statis dilayani langsung dari akar repo: `assets/` (token desain),
   `admin_menu/assets/` (sb-admin, shell), dan berkas unggahan. Pemeriksaan
   prefiks `realpath` menahan `../` pada URL agar router tidak bisa dipakai
   membaca berkas di luar repo. */
$candidate = realpath($root . $path);
if ($candidate !== false
	&& strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0
	&& is_file($candidate)) {
	return false;
}

if (strpos($path, '/admin_menu') !== 0) {
	http_response_code(404);
	header('Content-Type: text/plain; charset=utf-8');
	echo "router: hanya /admin_menu/ dan berkas statis repo yang dilayani\n";
	return true;
}

/* Apache memotong prefiks direktori sebelum CodeIgniter membaca REQUEST_URI
   (RewriteRule di `admin_menu/.htaccess` bekerja relatif terhadap direktori
   itu). Di sini pemotongan dilakukan eksplisit supaya rute terbaca '/home',
   bukan '/admin_menu/home'. */
$rest = substr($path, strlen('/admin_menu'));
$_SERVER['REQUEST_URI'] = ($rest === '' || $rest === false ? '/' : $rest);
$_SERVER['SCRIPT_NAME'] = '/admin_menu/index.php';
$_SERVER['PHP_SELF'] = '/admin_menu/index.php';

chdir($app);
require $app . '/index.php';
