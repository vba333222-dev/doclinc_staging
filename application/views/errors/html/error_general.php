<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Terjadi kesalahan - DocLink</title>
<style type="text/css">

::selection { background-color: #E13300; color: white; }
::-moz-selection { background-color: #E13300; color: white; }

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
/* ---- Gaya DocLinc, ditulis setelah gaya bawaan agar menimpa tanpa menghapusnya. ---- */
html { background: #F7F7F7; }

body {
	background-color: #F7F7F7;
	margin: 0;
	padding: 24px 16px;
	font: 15px/1.55 "Manrope", "Inter", "Segoe UI", Arial, sans-serif;
	color: #1F2A24;
}

h1 {
	border: 0;
	padding: 0;
	margin: 0 0 10px;
	color: #1F2A24;
	font-size: 18px;
	font-weight: 800;
}

#container {
	max-width: 900px;
	margin: 0 auto;
	padding: 18px 20px;
	border: 1px solid #E6B8BC;
	border-left: 4px solid #D32E2E;
	border-radius: 10px;
	background: #fff;
	box-shadow: 0 2px 12px rgba(31, 42, 36, .06);
	overflow-wrap: anywhere;
}

p { margin: 0 0 8px; font-size: 14px; }

.doclinc-error-lead {
	margin: 0 0 14px;
	padding: 12px 14px;
	border: 1px solid #F2C56D;
	border-radius: 8px;
	background: #FFF4DF;
	color: #7A4B00;
	font-size: 13px;
	font-weight: 700;
}
</style>
</head>
<body>
	<div id="container">
		<p class="doclinc-error-lead">DocLink mengalami kendala teknis saat memuat halaman ini. Silakan coba lagi beberapa saat lagi. Rincian teknis di bawah ditujukan untuk pengelola sistem.</p>
		<h1><?php echo $heading; ?></h1>
		<?php echo $message; ?>
	</div>
</body>
</html>