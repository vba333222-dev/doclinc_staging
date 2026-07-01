<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="dl-nakes-card dl-dashboard-section">
	<?php if ($nakes_primary_active) :
		$active_queue_code = doclinc_request_queue_code($nakes_primary_active);
		$active_mode_label = doclinc_consultation_mode_label(isset($nakes_primary_active->consultation_mode) ? $nakes_primary_active->consultation_mode : '');
	?>
		<div class="dl-task-row">
			<div class="dl-task-avatar"><i class="fas fa-user-check"></i></div>
			<div class="flex-grow-1 min-w-0">
				<p class="dl-task-title"><?= html_escape(strtoupper((string) $nakes_primary_active->nama)); ?></p>
				<div class="dl-task-meta">No. Antrian: <?= html_escape($active_queue_code); ?> · <?= html_escape($active_mode_label); ?></div>
			</div>
		</div>
		<a href="<?= html_escape(base_url('konsultasi_nakes/konsultasi/' . (int) $nakes_primary_active->request_id) . '?kriteria=1'); ?>" class="btn btn-success rounded-pill w-100 mt-3 fw-bold">
			Lanjutkan Tugas
		</a>
	<?php else : ?>
		<?php
		$this->load->view('partials/nakes_empty_state_v', array(
			'empty_title' => 'Tidak ada tugas aktif',
			'empty_message' => 'Permintaan baru akan muncul di sini atau di menu Permintaan.',
		));
		?>
	<?php endif; ?>
</div>
