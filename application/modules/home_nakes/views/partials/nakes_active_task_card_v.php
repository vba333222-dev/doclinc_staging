<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="dl-nakes-card dl-dashboard-section">
	<?php if ($nakes_primary_active) :
		$active_queue_code = doclinc_request_queue_code($nakes_primary_active);
		$active_mode_label = doclinc_consultation_mode_label(isset($nakes_primary_active->consultation_mode) ? $nakes_primary_active->consultation_mode : '');
		$primary_assignment_map = isset($request_staff_assignment_map) && is_array($request_staff_assignment_map) ? $request_staff_assignment_map : array();
		$primary_pic_assignment = isset($primary_assignment_map[(int) $nakes_primary_active->request_id]) ? $primary_assignment_map[(int) $nakes_primary_active->request_id] : null;
	?>
		<div class="dl-task-row">
			<div class="dl-task-avatar"><i class="fas fa-user-check"></i></div>
			<div class="flex-grow-1 min-w-0">
				<p class="dl-task-title"><?= html_escape(strtoupper((string) $nakes_primary_active->nama)); ?></p>
				<div class="dl-task-meta">No. Antrian: <?= html_escape($active_queue_code); ?> · <?= html_escape($active_mode_label); ?></div>
			</div>
		</div>
		<div class="nk-pic-inline">
			<span>PIC Personel</span>
			<strong>
				<?php if ($primary_pic_assignment) : ?>
					<?= html_escape($primary_pic_assignment->staff_nama); ?><?= !empty($primary_pic_assignment->staff_profesi) ? ' · ' . html_escape($primary_pic_assignment->staff_profesi) : ''; ?>
				<?php else : ?>
					Belum ditentukan
				<?php endif; ?>
			</strong>
		</div>
		<a href="<?= html_escape(base_url('konsultasi_nakes/konsultasi/' . (int) $nakes_primary_active->request_id) . '?kriteria=1'); ?>" class="btn btn-success rounded-pill w-100 mt-3 fw-bold">
			Lanjutkan Penanganan
		</a>
	<?php else : ?>
		<?php
		$this->load->view('partials/nakes_empty_state_v', array(
			'empty_title' => 'Tidak ada penanganan aktif',
			'empty_message' => 'Permintaan baru akan muncul di sini atau di Antrian Puskesmas.',
		));
		?>
	<?php endif; ?>
</div>
