<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="dl-dashboard-stats">
	<?php if (!empty($nakes_is_command_center)) : ?>
	<a href="#req_konsul" class="dl-nakes-card dl-dashboard-stat" onclick="showContent('req_konsul')">
		<span>Antrian</span>
		<strong><?= html_escape(str_pad((string) $nakes_pending_count, 2, '0', STR_PAD_LEFT)); ?></strong>
	</a>
	<?php else : ?>
	<div class="dl-nakes-card dl-dashboard-stat">
		<span>Antrian</span>
		<strong>00</strong>
	</div>
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
