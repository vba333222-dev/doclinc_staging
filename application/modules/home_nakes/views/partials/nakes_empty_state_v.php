<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$empty_title = isset($empty_title) ? (string) $empty_title : '';
$empty_message = isset($empty_message) ? (string) $empty_message : '';
$empty_image = isset($empty_image) ? (string) $empty_image : '';
$empty_icon = isset($empty_icon) ? (string) $empty_icon : '';
$empty_class = isset($empty_class) ? (string) $empty_class : 'dl-soft-empty';
?>
<div class="<?= html_escape($empty_class); ?>">
	<?php if ($empty_image !== '') : ?>
		<img src="<?= html_escape($empty_image); ?>" width="140" height="140" alt="Tidak ada data">
	<?php endif; ?>
	<?php
	/* Keadaan kosong memakai satu glyph, bukan ilustrasi. Beranda warga menjadi
	   acuannya (`dl-bento-empty__icon`: glyph Font Awesome di dalam petak), dan
	   ilustrasi "not found" berukuran besar dengan lingkaran navy gelap berada di
	   luar palet bento (hijau tua/mint/putih). Pemanggil boleh tetap mengirim
	   `empty_image`; bila keduanya kosong tidak ada penanda visual sama sekali. */
	?>
	<?php if ($empty_icon !== '') : ?>
		<span class="dl-soft-empty__icon" aria-hidden="true"><i class="<?= html_escape($empty_icon); ?>"></i></span>
	<?php endif; ?>
	<?php if ($empty_title !== '') : ?>
		<p class="dl-task-title mb-1"><?= html_escape($empty_title); ?></p>
	<?php endif; ?>
	<?php if ($empty_message !== '') : ?>
		<p class="dl-muted-copy mb-0"><?= html_escape($empty_message); ?></p>
	<?php endif; ?>
</div>
