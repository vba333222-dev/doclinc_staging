<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/* Halaman kesalahan PHP (warning/notice/fatal).
 *
 * Sebelumnya berkas ini hanya mencetak potongan <div> tanpa doctype, tanpa
 * charset, dan tanpa viewport. Saat terjadi kesalahan — misalnya basis data
 * mati — peramban menampilkan halaman tanpa gaya sama sekali dan teks Indonesia
 * berisiko salah encoding. Selain itu nama berkas dicetak sebagai jalur absolut
 * (di Windows pemotongan bawaan CodeIgniter gagal karena pemisah '\'), sehingga
 * struktur folder server ikut terlihat pengunjung.
 *
 * error_php.php dan error_exception.php dapat tampil pada SATU permintaan yang
 * sama. Karena itu pembuka dokumen dipasang sekali saja (dijaga konstanta) dan
 * penutupnya dipasang melalui register_shutdown_function, agar dokumen tetap sah
 * walau keduanya ikut tercetak.
 */
$doclinc_error_roots = array();
foreach (array(FCPATH, APPPATH, BASEPATH) as $doclinc_error_root) {
	$doclinc_error_root = realpath($doclinc_error_root);
	if ($doclinc_error_root !== FALSE) {
		$doclinc_error_roots[] = str_replace('\\', '/', $doclinc_error_root);
	}
}
if (!function_exists('doclinc_error_relative_path')) {
	function doclinc_error_relative_path($path, array $roots)
	{
		$path = str_replace('\\', '/', (string) $path);
		foreach ($roots as $root) {
			if ($root !== '' && strpos($path, $root) === 0) {
				return ltrim(substr($path, strlen($root)), '/');
			}
		}
		return basename($path);
	}
}

if (!defined('DOCLINC_ERROR_DOC_OPEN')) :
	define('DOCLINC_ERROR_DOC_OPEN', TRUE);
	register_shutdown_function(function () {
		echo "\n\t</main>\n</body>\n</html>\n";
	});
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Terjadi kesalahan - DocLink</title>
<style type="text/css">
	body {
		margin: 0;
		padding: 24px 16px;
		background: #F7F7F7;
		color: #1F2A24;
		font: 15px/1.55 "Manrope", "Inter", "Segoe UI", Arial, sans-serif;
	}
	.doclinc-error-shell { max-width: 900px; margin: 0 auto; }
	.doclinc-error-banner {
		margin: 0 0 16px;
		padding: 12px 14px;
		border: 1px solid #F2C56D;
		border-radius: 8px;
		background: #FFF4DF;
		color: #7A4B00;
		font-size: 13px;
		font-weight: 700;
	}
	.doclinc-error-box {
		margin: 0 0 16px;
		padding: 16px 18px;
		border: 1px solid #E6B8BC;
		border-left: 4px solid #D32E2E;
		border-radius: 8px;
		background: #fff;
		box-shadow: 0 2px 12px rgba(31, 42, 36, .06);
		overflow-wrap: anywhere;
	}
	.doclinc-error-box h2 { margin: 0 0 10px; font-size: 16px; font-weight: 800; }
	.doclinc-error-box p { margin: 0 0 6px; font-size: 14px; }
	.doclinc-error-box code {
		display: block;
		margin: 10px 0;
		padding: 10px 12px;
		border: 1px solid #E7ECE9;
		border-radius: 6px;
		background: #F4F8F6;
		font-family: Consolas, Monaco, "Courier New", monospace;
		font-size: 12px;
		white-space: pre-wrap;
	}
	.doclinc-error-trace { margin: 10px 0 0; padding: 0; list-style: none; }
	.doclinc-error-trace li { margin: 0 0 6px; font-size: 13px; color: #4E5F57; }
</style>
</head>
<body>
	<main class="doclinc-error-shell">
		<p class="doclinc-error-banner">Mode pengembangan aktif: rincian teknis ditampilkan. Di lingkungan produksi halaman ini tidak menampilkan detail apa pun.</p>
<?php endif; ?>

	<section class="doclinc-error-box">

<h2>A PHP Error was encountered</h2>

<p>Severity: <?php echo $severity; ?></p>
<p>Message:  <?php
	/* CodeIgniter menuliskan jalur absolut di dalam teks pesannya sendiri
	 * (mis. "output started at C:\...\system\core\Exceptions.php:271"). Akar
	 * proyek dibuang agar struktur folder server tidak ikut terlihat, tanpa
	 * mengubah isi diagnostiknya. */
	$doclinc_error_message = (string) $message;
	foreach ($doclinc_error_roots as $doclinc_error_root) {
		$doclinc_error_message = str_replace(array($doclinc_error_root, str_replace('/', '\\', $doclinc_error_root)), '', $doclinc_error_message);
	}
	echo $doclinc_error_message;
	?></p>
<p>Filename: <?php echo htmlspecialchars(doclinc_error_relative_path($filepath, $doclinc_error_roots), ENT_QUOTES, 'UTF-8'); ?></p>
<p>Line Number: <?php echo $line; ?></p>

<?php if (defined('SHOW_DEBUG_BACKTRACE') && SHOW_DEBUG_BACKTRACE === TRUE): ?>

	<p>Backtrace:</p>
	<ul class="doclinc-error-trace">
	<?php foreach (debug_backtrace() as $error): ?>

		<?php if (isset($error['file']) && strpos($error['file'], realpath(BASEPATH)) !== 0): ?>

			<li>
			File: <?php echo htmlspecialchars(doclinc_error_relative_path($error['file'], $doclinc_error_roots), ENT_QUOTES, 'UTF-8'); ?><br />
			Line: <?php echo $error['line'] ?><br />
			Function: <?php echo $error['function'] ?>
			</li>

		<?php endif ?>

	<?php endforeach ?>
	</ul>

<?php endif ?>

	</section>
