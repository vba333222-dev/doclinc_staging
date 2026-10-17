<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* Akun Puskesmas punya tiga penghitung; akun dokter/nakes hanya dua, karena
   antrian fasilitas bukan beban kerja dokter. Petak ketiga di akun personal
   dulu berisi angka "00" tetap dan bukan tautan, sehingga petak paling
   menonjol di layar - CSS bento memberi petak pertama permukaan hijau tua -
   justru satu-satunya yang tidak bisa diklik. Sekarang akun personal hanya
   merender dua petak nyata, dan modifier `dl-dashboard-stats--personal`
   membuat keduanya berdampingan selebar sama. */
$stats_is_command_center = !empty($nakes_is_command_center);
?>
<div class="dl-dashboard-stats<?= $stats_is_command_center ? '' : ' dl-dashboard-stats--personal'; ?>">
	<?php if ($stats_is_command_center) : ?>
	<a href="#req_konsul" class="dl-nakes-card dl-dashboard-stat" onclick="showContent('req_konsul')">
		<span>Antrian</span>
		<strong><?= html_escape(str_pad((string) $nakes_pending_count, 2, '0', STR_PAD_LEFT)); ?></strong>
	</a>
	<?php endif; ?>
	<a href="#riwayat_konsul" class="dl-nakes-card dl-dashboard-stat" onclick="showContent('riwayat_konsul')">
		<span>Diterima</span>
		<strong><?= html_escape(str_pad((string) $nakes_active_count, 2, '0', STR_PAD_LEFT)); ?></strong>
	</a>
	<a href="#riwayat_konsul" class="dl-nakes-card dl-dashboard-stat" onclick="showContent('riwayat_konsul')">
		<span>Selesai</span>
		<strong><?= html_escape(str_pad((string) $nakes_completed_count, 2, '0', STR_PAD_LEFT)); ?></strong>
	</a>
</div>
