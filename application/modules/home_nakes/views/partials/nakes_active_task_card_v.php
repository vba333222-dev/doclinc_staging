<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="dl-nakes-card dl-dashboard-section nakes-current-task-surface">
	<?php if ($nakes_primary_active) :
		$active_queue_label = doclinc_request_queue_number_label($nakes_primary_active);
		$active_mode_label = doclinc_consultation_mode_label(isset($nakes_primary_active->consultation_mode) ? $nakes_primary_active->consultation_mode : '');
		$primary_assignment_map = isset($request_staff_assignment_map) && is_array($request_staff_assignment_map) ? $request_staff_assignment_map : array();
		$primary_pic_assignment = isset($primary_assignment_map[(int) $nakes_primary_active->request_id]) ? $primary_assignment_map[(int) $nakes_primary_active->request_id] : null;
		$primary_staff_assignment_ready = isset($staff_assignment_ready) ? (bool) $staff_assignment_ready : false;
		$primary_staff_options = isset($puskesmas_staff_options) && is_array($puskesmas_staff_options) ? $puskesmas_staff_options : array();
		$primary_event_map = isset($request_event_map) && is_array($request_event_map) ? $request_event_map : array();
		$primary_events = isset($primary_event_map[(int) $nakes_primary_active->request_id]) ? array_slice($primary_event_map[(int) $nakes_primary_active->request_id], 0, 3) : array();
		$care_team_enabled = !empty($care_team_workflow_enabled);
		$responsible_doctor_name = isset($nakes_primary_active->responsible_doctor_name) ? trim((string) $nakes_primary_active->responsible_doctor_name) : '';
		$is_responsible_doctor = $care_team_enabled
			&& isset($nakes_primary_active->responsible_doctor_user_id)
			&& (int) $nakes_primary_active->responsible_doctor_user_id === (int) $this->session->userdata('id');
		$is_visit_performer = $care_team_enabled
			&& (string) ($nakes_primary_active->consultation_mode ?? '') === 'visit'
			&& isset($nakes_primary_active->visit_performer_user_id)
			&& (int) $nakes_primary_active->visit_performer_user_id === (int) $this->session->userdata('id');
		$primary_visit_status = isset($nakes_primary_active->visit_status) ? doclinc_normalize_visit_status($nakes_primary_active->visit_status) : 'not_started';
		$primary_visit_status = $primary_visit_status !== '' ? $primary_visit_status : 'not_started';
		$primary_workflow_enabled = !empty($visit_clinical_workflow_enabled);
		$primary_clinical = isset($clinical_ui[(int) $nakes_primary_active->request_id]) && is_array($clinical_ui[(int) $nakes_primary_active->request_id])
			? $clinical_ui[(int) $nakes_primary_active->request_id]
			: null;
		$primary_clinical_anchor = $primary_clinical && !empty($primary_clinical['next_action_url'])
			? (string) $primary_clinical['next_action_url']
			: '';
		$primary_disposition = isset($nakes_disposition_map[(int) $nakes_primary_active->request_id])
			? $nakes_disposition_map[(int) $nakes_primary_active->request_id]
			: null;
		$primary_required_profession = $primary_disposition && (string) $primary_disposition->decision === 'visit'
			? trim((string) ($primary_disposition->required_profession ?? ''))
			: '';
		$primary_mode_locked = $primary_workflow_enabled && ($primary_disposition !== null || $primary_visit_status !== 'not_started');
		$primary_role_label = $is_responsible_doctor ? 'Dokter penanggung jawab' : ($is_visit_performer ? 'Nakes kunjungan' : 'Pemantau layanan');
		$primary_next_action = '';
		$primary_mode = strtolower(trim((string) ($nakes_primary_active->consultation_mode ?? '')));
		if ($is_responsible_doctor) {
			if ($primary_workflow_enabled) {
				if (!$primary_disposition) {
					$primary_next_action = $primary_mode === 'non_visit' ? 'Lanjutkan konsultasi' : 'Pilih jenis layanan atau Tetapkan disposisi (opsional)';
				} elseif ((string) $primary_disposition->decision === 'visit' && (int) ($nakes_primary_active->visit_performer_user_id ?? 0) < 1) {
					$primary_next_action = 'Pilih Nakes kunjungan';
				} elseif ((string) $primary_disposition->decision === 'visit' && $primary_visit_status !== 'completed') {
					$primary_next_action = 'Pantau hasil kunjungan';
				} else {
					$primary_next_action = 'Lengkapi dan selesaikan konsultasi';
				}
			} elseif ((string) ($nakes_primary_active->consultation_mode ?? '') === '') {
				$primary_next_action = 'Pilih jenis layanan';
			} elseif ((string) ($nakes_primary_active->consultation_mode ?? '') === 'visit' && (int) ($nakes_primary_active->visit_performer_user_id ?? 0) < 1) {
				$primary_next_action = 'Pilih Nakes kunjungan';
			} elseif ((string) ($nakes_primary_active->consultation_mode ?? '') === 'visit' && $primary_visit_status !== 'completed') {
				$primary_next_action = 'Pantau hasil kunjungan';
			} else {
				$primary_next_action = 'Lengkapi dan selesaikan konsultasi';
			}
		} elseif ($is_visit_performer) {
			$primary_visit_actions = array(
				'not_started' => 'Mulai perjalanan',
				'en_route' => 'Tiba di lokasi',
				'arrived' => 'Mulai pemeriksaan',
				'in_service' => 'Catat Hasil',
				'completed' => 'Kunjungan selesai',
			);
			$primary_next_action = isset($primary_visit_actions[$primary_visit_status]) ? $primary_visit_actions[$primary_visit_status] : 'Lihat tugas kunjungan';
		}
		if ($primary_clinical && !empty($primary_clinical['next_action'])) {
			$primary_next_action = (string) $primary_clinical['next_action'];
		}
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
		$primary_patient_photo = '';
		foreach (array('patient_photo', 'foto', 'photo', 'profile_photo', 'profile_image', 'avatar') as $primary_photo_key) {
			if (isset($nakes_primary_active->{$primary_photo_key}) && trim((string) $nakes_primary_active->{$primary_photo_key}) !== '') {
				$primary_patient_photo = trim((string) $nakes_primary_active->{$primary_photo_key});
				break;
			}
		}
	?>
		<div class="dl-task-row">
			<?php $this->load->view('partials/nakes_avatar_v', array(
				'avatar_name' => $nakes_primary_active->nama,
				'avatar_photo' => $primary_patient_photo,
				'avatar_user_id' => (int) $nakes_primary_active->user_id,
				'avatar_alt' => 'Foto pasien',
				'avatar_class' => 'nk-avatar--md nk-avatar--patient',
				'avatar_icon' => 'fas fa-user-check',
			)); ?>
			<div class="flex-grow-1 min-w-0">
				<p class="dl-task-title"><?= html_escape(strtoupper((string) $nakes_primary_active->nama)); ?></p>
				<div class="dl-task-meta"><?= html_escape($active_queue_label); ?> · <?= html_escape($active_mode_label); ?></div>
				<?php if ($primary_clinical) : ?>
					<div class="mt-2"><?php $this->load->view('partials/clinical_state_chip_v', array('clinical_ui' => $primary_clinical)); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php if ($care_team_enabled && ($is_responsible_doctor || $is_visit_performer)) : ?>
			<div class="alert alert-light border my-3 py-2 px-3" data-primary-next-action="<?= $is_responsible_doctor ? 'responsible_doctor' : 'visit_performer'; ?>">
				<span class="small text-muted d-block">Peran Anda · <?= html_escape($primary_role_label); ?></span>
				<strong data-primary-next-action-label><?= html_escape($primary_next_action); ?></strong>
			</div>
		<?php endif; ?>
		<?php if ($primary_clinical) : ?>
			<div class="cw-card nk-card-section mt-3">
				<div class="cw-card-body py-2">
					<?php $this->load->view('partials/clinical_thread_mini_v', array('clinical_ui' => $primary_clinical)); ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if (!empty($can_coordinate_staff)) : ?>
		<div class="nk-action-panel nk-action-panel--pic nk-card-section nk-pic-dashboard-panel" data-pic-panel data-request-id="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
			<div class="nk-action-panel__title"><?= $care_team_enabled ? 'Dokter penanggung jawab' : 'Penanggung jawab layanan'; ?></div>
			<div class="nk-action-panel__body">
				<?php if (!$primary_staff_assignment_ready) : ?>
					<p class="nk-pic-muted">Pengaturan penanggung jawab belum tersedia.</p>
				<?php elseif (empty($primary_staff_options)) : ?>
					<p class="nk-pic-muted">Belum ada staf aktif.</p>
				<?php else : ?>
					<form method="post" action="<?= html_escape(base_url($care_team_enabled ? 'home_nakes/assign_responsible_doctor' : 'home_nakes/assign_staff')); ?>" class="<?= $care_team_enabled ? 'nk-care-team-form' : 'nk-pic-form'; ?>">
						<input type="hidden" name="request_id" value="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
						<select name="staff_id" class="form-select form-select-sm" required>
							<option value=""><?= $care_team_enabled ? 'Pilih Dokter' : 'Pilih staf'; ?></option>
							<?php foreach ($primary_staff_options as $staff_option) : ?>
								<?php if ($care_team_enabled && empty($staff_option->responsible_doctor_available)) { continue; } ?>
								<option value="<?= html_escape((int) $staff_option->staff_id); ?>" <?= $care_team_enabled ? ((int) ($nakes_primary_active->responsible_doctor_user_id ?? 0) === (int) $staff_option->user_id ? 'selected' : '') : ($primary_pic_assignment && (int) $primary_pic_assignment->staff_id === (int) $staff_option->staff_id ? 'selected' : ''); ?> <?= ($staff_option->personal_account_state ?? '') === 'invalid' ? 'disabled' : ''; ?>>
									<?= html_escape($staff_option->nama); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input type="text" name="note" class="form-control form-control-sm" maxlength="255" placeholder="Catatan opsional">
						<div class="nk-pic-actions">
							<button type="submit" class="btn btn-outline-success btn-sm rounded-pill" <?= $care_team_enabled ? '' : 'data-pic-submit'; ?>><?= $care_team_enabled ? ($responsible_doctor_name !== '' ? 'Ganti Dokter' : 'Pilih Dokter') : ($primary_pic_assignment ? 'Ganti Dokter' : 'Tetapkan Dokter'); ?></button>
						</div>
					</form>
					<form method="post" action="<?= html_escape(base_url('home_nakes/clear_staff_assignment')); ?>" class="nk-pic-clear-form" <?= !$care_team_enabled && $primary_pic_assignment ? '' : 'hidden'; ?>>
						<input type="hidden" name="request_id" value="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
						<button type="submit" class="btn btn-outline-danger btn-sm rounded-pill">Hapus</button>
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
		<?php if ($primary_workflow_enabled && $primary_disposition) : ?>
			<div class="nk-action-panel nk-card-section" data-disposition-panel>
				<div class="nk-action-panel__title">Disposisi dokter</div>
				<div class="nk-action-panel__body">
					<?php $this->load->view('partials/nakes_disposition_summary_v', array('disposition' => $primary_disposition)); ?>
					<?php if ($is_responsible_doctor) : ?>
						<?php if ((string) $primary_disposition->decision === 'visit' && (int) ($nakes_primary_active->visit_performer_user_id ?? 0) < 1 && $primary_visit_status === 'not_started') : ?>
							<form method="post" action="<?= html_escape(base_url('home_nakes/assign_visit_performer')); ?>" class="nk-care-team-form mt-2">
								<input type="hidden" name="request_id" value="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
								<label class="form-label">Nakes kunjungan<?= $primary_required_profession !== '' ? ' (' . html_escape($primary_required_profession) . ')' : ''; ?></label>
								<select name="staff_id" class="form-select form-select-sm" required>
									<option value="">Pilih Nakes</option>
									<?php foreach ($primary_staff_options as $staff_option) : ?>
										<?php if (empty($staff_option->visit_performer_eligible)) { continue; } ?>
										<?php if ($primary_required_profession !== '' && strcasecmp(trim((string) ($staff_option->profesi ?? '')), $primary_required_profession) !== 0) { continue; } ?>
										<option value="<?= html_escape((int) $staff_option->staff_id); ?>" <?= (int) ($nakes_primary_active->visit_performer_user_id ?? 0) === (int) $staff_option->user_id ? 'selected' : ''; ?>>
											<?= html_escape($staff_option->nama); ?><?= isset($staff_option->is_online) ? ' · ' . ((int) $staff_option->is_online === 1 ? 'Online' : 'Offline') : ''; ?>
										</option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="btn btn-outline-success btn-sm rounded-pill mt-2">Pilih Nakes</button>
							</form>
						<?php elseif ((string) $primary_disposition->decision === 'visit' && $primary_visit_status === 'not_started') : ?>
							<p class="small text-muted mb-0">Nakes sudah ditunjuk. Kunjungan belum dimulai.</p>
						<?php elseif ((string) $primary_disposition->decision === 'visit') : ?>
							<p class="small text-muted mb-0">Nakes sedang menjalankan kunjungan.</p>
						<?php else : ?>
							<p class="small text-muted mb-0">Tanpa kunjungan. Lanjutkan lewat halaman konsultasi.</p>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		<?php elseif ($primary_workflow_enabled && $is_responsible_doctor) : ?>
			<div class="nk-action-panel nk-card-section" data-disposition-panel>
				<div class="nk-action-panel__title">Disposisi dokter</div>
				<div class="nk-action-panel__body">
					<p class="small text-muted">Tetapkan disposisi sebelum menunjuk Nakes.</p>
					<a href="<?= html_escape(base_url('konsultasi_nakes/konsultasi/' . (int) $nakes_primary_active->request_id) . '#disposisi'); ?>" class="btn btn-success btn-sm rounded-pill">Tetapkan disposisi</a>
				</div>
			</div>
		<?php elseif ($is_responsible_doctor) : ?>
			<div class="nk-action-panel nk-card-section">
				<div class="nk-action-panel__title">Layanan</div>
				<div class="nk-action-panel__body">
					<form method="post" action="<?= html_escape(base_url('home_nakes/choose_service_mode')); ?>" class="nk-care-team-form">
						<input type="hidden" name="request_id" value="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
						<div class="nk-pic-actions">
							<button type="submit" name="service_mode" value="non_visit" class="btn btn-outline-success btn-sm rounded-pill">Tanpa kunjungan</button>
							<button type="submit" name="service_mode" value="visit" class="btn btn-success btn-sm rounded-pill">Kunjungan</button>
						</div>
					</form>
					<?php if ((string) ($nakes_primary_active->consultation_mode ?? '') === 'visit') : ?>
						<form method="post" action="<?= html_escape(base_url('home_nakes/assign_visit_performer')); ?>" class="nk-care-team-form mt-2">
							<input type="hidden" name="request_id" value="<?= html_escape((int) $nakes_primary_active->request_id); ?>">
							<label class="form-label">Nakes kunjungan</label>
							<select name="staff_id" class="form-select form-select-sm" required>
								<option value="">Pilih Nakes</option>
								<?php foreach ($primary_staff_options as $staff_option) : ?>
									<?php if (empty($staff_option->visit_performer_eligible)) { continue; } ?>
									<option value="<?= html_escape((int) $staff_option->staff_id); ?>" <?= (int) ($nakes_primary_active->visit_performer_user_id ?? 0) === (int) $staff_option->user_id ? 'selected' : ''; ?>>
										<?= html_escape($staff_option->nama); ?><?= isset($staff_option->is_online) ? ' · ' . ((int) $staff_option->is_online === 1 ? 'Online' : 'Offline') : ''; ?>
									</option>
								<?php endforeach; ?>
							</select>
							<button type="submit" class="btn btn-outline-success btn-sm rounded-pill mt-2">Pilih Nakes</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
		<a href="<?= html_escape(($is_visit_performer && $primary_visit_status !== 'in_service'
			? base_url('home_nakes#riwayat_konsul')
			: base_url('konsultasi_nakes/konsultasi/' . (int) $nakes_primary_active->request_id)) . $primary_clinical_anchor); ?>" class="btn btn-success rounded-pill w-100 mt-3 py-2 fw-bold">
			<?= $primary_clinical && !empty($primary_clinical['next_action']) ? html_escape((string) $primary_clinical['next_action']) : ($is_visit_performer ? ($primary_visit_status === 'in_service' ? 'Catat Hasil' : 'Buka Tugas') : ($care_team_enabled && !$is_responsible_doctor ? 'Lihat' : 'Lanjutkan')); ?>
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
