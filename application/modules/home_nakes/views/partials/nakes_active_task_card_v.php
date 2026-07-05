<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="dl-nakes-card dl-dashboard-section">
	<?php if ($nakes_primary_active) :
		$active_queue_code = doclinc_request_queue_code($nakes_primary_active);
		$active_mode_label = doclinc_consultation_mode_label(isset($nakes_primary_active->consultation_mode) ? $nakes_primary_active->consultation_mode : '');
		$primary_assignment_map = isset($request_staff_assignment_map) && is_array($request_staff_assignment_map) ? $request_staff_assignment_map : array();
		$primary_pic_assignment = isset($primary_assignment_map[(int) $nakes_primary_active->request_id]) ? $primary_assignment_map[(int) $nakes_primary_active->request_id] : null;
		$primary_staff_assignment_ready = isset($staff_assignment_ready) ? (bool) $staff_assignment_ready : false;
		$primary_staff_options = isset($puskesmas_staff_options) && is_array($puskesmas_staff_options) ? $puskesmas_staff_options : array();
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
		<div class="nk-action-panel nk-action-panel--pic nk-card-section nk-pic-dashboard-panel">
			<div class="nk-action-panel__title">PIC Personel</div>
			<div class="nk-action-panel__body">
				<?php if (!$primary_staff_assignment_ready) : ?>
					<p class="nk-pic-muted">Fitur PIC personel belum tersedia.</p>
				<?php elseif (empty($primary_staff_options)) : ?>
					<p class="nk-pic-muted">Belum ada personel aktif untuk ditetapkan.</p>
				<?php else : ?>
					<form method="post" action="<?= html_escape(base_url('home_nakes/assign_staff')); ?>" class="nk-pic-form">
						<input type="hidden" name="request_id" value="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
						<select name="staff_id" class="form-select form-select-sm" required>
							<option value="">Pilih personel</option>
							<?php foreach ($primary_staff_options as $staff_option) : ?>
								<option value="<?= html_escape((int) $staff_option->staff_id); ?>" <?= $primary_pic_assignment && (int) $primary_pic_assignment->staff_id === (int) $staff_option->staff_id ? 'selected' : ''; ?>>
									<?= html_escape($staff_option->nama); ?><?= !empty($staff_option->profesi) ? ' - ' . html_escape($staff_option->profesi) : ''; ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input type="text" name="note" class="form-control form-control-sm" maxlength="255" placeholder="Catatan opsional">
						<div class="nk-pic-actions">
							<button type="submit" class="btn btn-outline-success btn-sm rounded-pill"><?= $primary_pic_assignment ? 'Ganti PIC' : 'Tetapkan PIC'; ?></button>
						</div>
					</form>
					<?php if ($primary_pic_assignment) : ?>
						<form method="post" action="<?= html_escape(base_url('home_nakes/clear_staff_assignment')); ?>" class="nk-pic-clear-form">
							<input type="hidden" name="request_id" value="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
							<button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill">Batalkan PIC</button>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</div>
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
