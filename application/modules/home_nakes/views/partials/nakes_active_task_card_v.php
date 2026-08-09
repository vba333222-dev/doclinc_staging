<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="dl-nakes-card dl-dashboard-section">
	<?php if ($nakes_primary_active) :
		$active_queue_code = doclinc_request_queue_code($nakes_primary_active);
		$active_mode_label = doclinc_consultation_mode_label(isset($nakes_primary_active->consultation_mode) ? $nakes_primary_active->consultation_mode : '');
		$primary_assignment_map = isset($request_staff_assignment_map) && is_array($request_staff_assignment_map) ? $request_staff_assignment_map : array();
		$primary_pic_assignment = isset($primary_assignment_map[(int) $nakes_primary_active->request_id]) ? $primary_assignment_map[(int) $nakes_primary_active->request_id] : null;
		$primary_staff_assignment_ready = isset($staff_assignment_ready) ? (bool) $staff_assignment_ready : false;
		$primary_staff_options = isset($puskesmas_staff_options) && is_array($puskesmas_staff_options) ? $puskesmas_staff_options : array();
		$primary_event_map = isset($request_event_map) && is_array($request_event_map) ? $request_event_map : array();
		$primary_events = isset($primary_event_map[(int) $nakes_primary_active->request_id]) ? array_slice($primary_event_map[(int) $nakes_primary_active->request_id], 0, 3) : array();
		$primary_event_label = static function ($event) {
			$event_type = isset($event->event_type) ? (string) $event->event_type : '';
			if ($event_type === 'pic_assigned') {
				return 'Penanggung jawab ditetapkan';
			}
			if ($event_type === 'pic_changed') {
				return 'Penanggung jawab diganti';
			}
			if ($event_type === 'pic_cleared') {
				return 'Penanggung jawab dihapus';
			}
			$event_labels = array(
				'request_created' => 'Menunggu konfirmasi Puskesmas',
				'request_accepted' => 'Diterima',
				'request_cancelled' => 'Dibatalkan',
				'visit_started' => 'Dalam perjalanan',
				'visit_arrived' => 'Sudah tiba',
				'visit_in_service' => 'Sedang ditangani',
				'visit_completed' => 'Selesai',
				'request_completed' => 'Selesai',
			);
			if (isset($event_labels[$event_type])) {
				return $event_labels[$event_type];
			}
			return '';
		};
	?>
		<div class="dl-task-row">
			<div class="dl-task-avatar"><i class="fas fa-user-check"></i></div>
			<div class="flex-grow-1 min-w-0">
				<p class="dl-task-title"><?= html_escape(strtoupper((string) $nakes_primary_active->nama)); ?></p>
				<div class="dl-task-meta">No. Antrian: <?= html_escape($active_queue_code); ?> · <?= html_escape($active_mode_label); ?></div>
			</div>
		</div>
		<?php if (!empty($can_coordinate_staff)) : ?>
		<div class="nk-pic-inline" data-pic-summary data-request-id="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
			<span>Penanggung jawab layanan</span>
			<strong data-pic-name>
				<?php if ($primary_pic_assignment) : ?>
					<?= html_escape($primary_pic_assignment->staff_nama); ?><?= !empty($primary_pic_assignment->staff_profesi) ? ' · ' . html_escape($primary_pic_assignment->staff_profesi) : ''; ?>
				<?php else : ?>
					Belum ditentukan
				<?php endif; ?>
			</strong>
		</div>
		<div class="nk-action-panel nk-action-panel--pic nk-card-section nk-pic-dashboard-panel" data-pic-panel data-request-id="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
			<div class="nk-action-panel__title">Penanggung jawab layanan</div>
			<div class="nk-action-panel__body">
				<?php if (!$primary_staff_assignment_ready) : ?>
					<p class="nk-pic-muted">Pengaturan penanggung jawab belum tersedia.</p>
				<?php elseif (empty($primary_staff_options)) : ?>
					<p class="nk-pic-muted">Belum ada staf aktif.</p>
				<?php else : ?>
					<form method="post" action="<?= html_escape(base_url('home_nakes/assign_staff')); ?>" class="nk-pic-form">
						<input type="hidden" name="request_id" value="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
						<select name="staff_id" class="form-select form-select-sm" required>
							<option value="">Pilih staf</option>
							<?php foreach ($primary_staff_options as $staff_option) : ?>
								<option value="<?= html_escape((int) $staff_option->staff_id); ?>" <?= $primary_pic_assignment && (int) $primary_pic_assignment->staff_id === (int) $staff_option->staff_id ? 'selected' : ''; ?> <?= ($staff_option->personal_account_state ?? '') === 'invalid' ? 'disabled' : ''; ?>>
									<?= html_escape($staff_option->nama); ?><?= !empty($staff_option->profesi) ? ' - ' . html_escape($staff_option->profesi) : ''; ?> · <?= ($staff_option->personal_account_state ?? '') === 'linked' ? 'Akun personal terhubung' : (($staff_option->personal_account_state ?? '') === 'invalid' ? 'Status belum tersedia' : 'Belum ada akun personal'); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input type="text" name="note" class="form-control form-control-sm" maxlength="255" placeholder="Catatan opsional">
						<div class="nk-pic-actions">
							<button type="submit" class="btn btn-outline-success btn-sm rounded-pill" data-pic-submit><?= $primary_pic_assignment ? 'Ganti penanggung jawab' : 'Tetapkan penanggung jawab'; ?></button>
						</div>
					</form>
					<form method="post" action="<?= html_escape(base_url('home_nakes/clear_staff_assignment')); ?>" class="nk-pic-clear-form" <?= $primary_pic_assignment ? '' : 'hidden'; ?>>
						<input type="hidden" name="request_id" value="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
						<button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill">Hapus penugasan</button>
					</form>
					<div class="nk-pic-native-feedback" data-pic-feedback role="status" aria-live="polite" hidden></div>
				<?php endif; ?>
			</div>
		</div>
		<?php if (!empty($primary_events)) : ?>
			<div class="nk-action-panel nk-action-panel--timeline nk-card-section nk-timeline-dashboard-panel">
				<div class="nk-action-panel__title">Riwayat</div>
				<div class="nk-timeline-list">
					<?php foreach ($primary_events as $event) :
						$event_label = $primary_event_label($event);
						$event_time = !empty($event->created_at) && strtotime($event->created_at) ? date('d M H:i', strtotime($event->created_at)) : '';
						if ($event_label === '') {
							continue;
						}
					?>
						<div class="nk-timeline-item">
							<strong><?= html_escape($event_label); ?></strong>
							<?php if ($event_time !== '') : ?><span><?= html_escape($event_time); ?></span><?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php endif; ?>
		<a href="<?= html_escape(base_url('konsultasi_nakes/konsultasi/' . (int) $nakes_primary_active->request_id)); ?>" class="btn btn-success rounded-pill w-100 mt-3 fw-bold">
			Lanjutkan penanganan
		</a>
	<?php else : ?>
		<?php
		$this->load->view('partials/nakes_empty_state_v', array(
			'empty_title' => 'Tidak ada penanganan aktif',
			'empty_message' => !empty($nakes_is_personal) ? 'Layanan yang ditugaskan kepada Anda akan muncul di sini.' : 'Permintaan baru akan muncul di sini atau di Antrian Puskesmas.',
		));
		?>
	<?php endif; ?>
</div>
