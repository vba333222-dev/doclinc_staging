<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/* Halaman 404 aplikasi utama.
 *
 * Sebelumnya berkas ini adalah bawaan CodeIgniter: berbahasa Inggris
 * ("404 Page Not Found") dan tanpa identitas DocLinc sama sekali, padahal
 * $route['404_override'] bernilai kosong sehingga SETIAP alamat yang salah
 * berakhir di sini.
 *
 * Berkas ini sengaja mandiri: tanpa gambar, tanpa berkas CSS luar, dan tanpa
 * helper aplikasi. show_error() hanya melakukan include biasa dan bisa
 * dipanggil sebelum kerangka aplikasi siap, jadi base_url() belum tentu
 * tersedia. config_item() dan html_escape() berasal dari system/core/Common.php
 * yang selalu dimuat, dan tetap dijaga dengan function_exists sebagai pengaman.
 */
$doclinc_base = function_exists('config_item') ? (string) config_item('base_url') : '/';
if ($doclinc_base === '') {
	$doclinc_base = '/';
}
$doclinc_esc = function_exists('html_escape') ? 'html_escape' : 'htmlspecialchars';
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Halaman tidak ditemukan - DocLink</title>
<style type="text/css">

::selection { background-color: #379A69; color: #fff; }
::-moz-selection { background-color: #379A69; color: #fff; }

body {
	background-color: #fff;
	margin: 40px;
	font: 13px/20px normal Helvetica, Arial, sans-serif;
	color: #4F5155;
}

a {
	color: #003399;
	background-color: transparent;
	font-weight: normal;
}

h1 {
	color: #444;
	background-color: transparent;
	border-bottom: 1px solid #D0D0D0;
	font-size: 19px;
	font-weight: normal;
	margin: 0 0 14px 0;
	padding: 14px 15px 10px 15px;
}

code {
	font-family: Consolas, Monaco, Courier New, Courier, monospace;
	font-size: 12px;
	background-color: #f9f9f9;
	border: 1px solid #D0D0D0;
	color: #002166;
	display: block;
	margin: 14px 0 14px 0;
	padding: 12px 10px 12px 10px;
}

#container {
	margin: 10px;
	border: 1px solid #D0D0D0;
	box-shadow: 0 0 8px #D0D0D0;
}

p {
	margin: 12px 15px 12px 15px;
}
/* ---- Gaya DocLinc, ditulis SETELAH gaya bawaan CodeIgniter di atas supaya
   menimpa tanpa perlu menghapusnya. ---- */
html { background: #1f6f4b; }

body {
	background-color: #1f6f4b;
	background-image: linear-gradient(135deg, #1f6f4b 0%, #379A69 55%, #1f6f4b 100%);
	margin: 0;
	min-height: 100vh;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 24px 16px;
	box-sizing: border-box;
	font: 15px/1.55 "Manrope", "Inter", "Segoe UI", Arial, sans-serif;
	color: #1F2A24;
}

a { color: #2D7D56; font-weight: 700; }

.doclinc-error-card {
	width: min(440px, 100%);
	padding: 32px 26px 26px;
	border: 1px solid rgba(255, 255, 255, .5);
	border-radius: 20px;
	background: #fff;
	box-shadow: 0 24px 60px rgba(15, 40, 28, .26);
	text-align: center;
}

.doclinc-error-brand {
	margin: 0 0 18px;
	color: #1f6f4b;
	font-size: 20px;
	font-weight: 800;
	letter-spacing: -.01em;
}

.doclinc-error-code {
	margin: 0;
	color: #379A69;
	font-size: 56px;
	font-weight: 800;
	line-height: 1;
	letter-spacing: -.03em;
}

.doclinc-error-title {
	margin: 12px 0 6px;
	border: 0;
	padding: 0;
	color: #1F2A24;
	font-size: 20px;
	font-weight: 800;
	line-height: 1.3;
}

.doclinc-error-copy {
	margin: 0 0 22px;
	color: #4E5F57;
	font-size: 14px;
	line-height: 1.5;
}

.doclinc-error-action {
	display: block;
	min-height: 48px;
	padding: 13px 18px;
	border: 1px solid #1f6f4b;
	border-radius: 8px;
	background: linear-gradient(180deg, #379A69 0%, #2D7D56 100%);
	color: #fff;
	font-size: 15px;
	font-weight: 800;
	text-align: center;
	text-decoration: none;
}

.doclinc-error-action:hover,
.doclinc-error-action:focus-visible {
	background: linear-gradient(180deg, #2D7D56 0%, #1f6f4b 100%);
}

.doclinc-error-action:focus-visible { outline: 3px solid #0c7c87; outline-offset: 2px; }

.doclinc-error-help {
	margin: 18px 0 0;
	padding: 16px 0 0;
	border-top: 1px solid #EFF3F1;
	color: #64736B;
	font-size: 13px;
	line-height: 1.5;
}
</style>
</head>
<body>
	<main class="doclinc-error-card">
		<p class="doclinc-error-brand">DocLink</p>
		<p class="doclinc-error-code">404</p>
		<h1 class="doclinc-error-title">Halaman tidak ditemukan</h1>
		<p class="doclinc-error-copy">Alamat yang Anda buka tidak tersedia atau sudah dipindahkan.</p>
		<a class="doclinc-error-action" href="<?php echo $doclinc_esc($doclinc_base); ?>">Kembali ke beranda</a>
		<p class="doclinc-error-help">Jika menurut Anda alamatnya sudah benar, keluar lalu masuk kembali, atau hubungi pengelola layanan.</p>
		<?php /* $heading bawaan CodeIgniter berbahasa Inggris; diganti judul Indonesia di atas. */ ?>
		<?php /* $message bawaan CodeIgniter berbahasa Inggris; tidak ditampilkan ke pengguna. */ ?>
	</main>
</body>
</html>