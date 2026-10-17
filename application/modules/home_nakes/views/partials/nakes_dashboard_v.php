<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="beranda" class="content <?= (isset($nakes_initial_section) && $nakes_initial_section === 'operasional') ? '' : 'active'; ?>">
					<div class="dl-dashboard-stack nakes-dashboard-workspace">
						<?php
						$role_prerequisite_state = isset($role_prerequisite_state) && is_array($role_prerequisite_state) ? $role_prerequisite_state : array();
						$role_prerequisite_blocked = !empty($role_prerequisite_state['enforced']) && empty($role_prerequisite_state['allowed']);
						$role_prerequisite_incomplete = array_key_exists('complete', $role_prerequisite_state) && empty($role_prerequisite_state['complete']);
						$role_prerequisite_labels = !empty($role_prerequisite_state['missing_labels']) && is_array($role_prerequisite_state['missing_labels'])
							? array_slice($role_prerequisite_state['missing_labels'], 0, 3)
							: array();
						$role_prerequisite_extra_count = !empty($role_prerequisite_state['missing_labels']) && is_array($role_prerequisite_state['missing_labels'])
							? max(0, count($role_prerequisite_state['missing_labels']) - count($role_prerequisite_labels))
							: 0;
						$role_prerequisite_cta_url = !empty($role_prerequisite_state['cta_url']) ? (string) $role_prerequisite_state['cta_url'] : '';
						$role_prerequisite_cta_label = !empty($role_prerequisite_state['cta_label']) ? (string) $role_prerequisite_state['cta_label'] : '';
						?>
						<?php if ($role_prerequisite_incomplete) : ?>
							<div class="alert <?= $role_prerequisite_blocked ? 'alert-warning' : 'alert-info'; ?> d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-0" role="<?= $role_prerequisite_blocked ? 'alert' : 'status'; ?>" data-role-prerequisite-alert data-enforced="<?= $role_prerequisite_blocked ? 'true' : 'false'; ?>">
								<div>
									<strong><?= $role_prerequisite_blocked ? 'Data profil wajib dilengkapi.' : (!empty($nakes_is_command_center) ? 'Data Puskesmas belum lengkap.' : 'Data profesi Anda belum lengkap.'); ?></strong>
									<span><?= html_escape(!empty($role_prerequisite_labels) ? 'Data yang belum tercatat: ' . implode(', ', $role_prerequisite_labels) . ($role_prerequisite_extra_count > 0 ? ', serta ' . $role_prerequisite_extra_count . ' data lainnya' : '') . '.' : 'Lengkapi data profil untuk melanjutkan.'); ?></span>
									<?php if (!$role_prerequisite_blocked) : ?><span class="d-block small mt-1">Aplikasi tetap dapat digunakan.</span><?php endif; ?>
								</div>
								<?php if ($role_prerequisite_cta_url !== '') : ?>
									<a class="btn btn-warning btn-sm" href="#profile" onclick="showContent('profile')"><?= html_escape($role_prerequisite_cta_label !== '' ? $role_prerequisite_cta_label : 'Lengkapi profil'); ?></a>
								<?php else : ?>
									<span class="small fw-semibold">Hubungi Admin Dinas Kesehatan.</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if (!empty($nakes_is_personal)) : ?>
							<div class="alert alert-info mb-0" role="status">Hanya tugas Anda yang tampil di sini.</div>
						<?php endif; ?>
						<?php $this->load->view('partials/nakes_stat_cards_v', get_defined_vars()); ?>
						<?php if (!empty($nakes_is_command_center)) : ?>
						<?php
						$dashboard_staff_rows = isset($puskesmas_staff_list) && is_array($puskesmas_staff_list) ? $puskesmas_staff_list : array();
						$dashboard_staff_count = isset($puskesmas_staff_count) ? (int) $puskesmas_staff_count : count($dashboard_staff_rows);
						$this->load->view('partials/nakes_section_header_v', array(
							'section_title' => 'Staf Puskesmas',
							'section_meta' => !empty($nakes_presence_enabled) ? 'Status diperbarui otomatis' : (string) $dashboard_staff_count . ' terdaftar',
						));
						?>
						<div class="dl-nakes-card dl-dashboard-section nk-staff-card doclinc-presence-panel" id="doclincNakesPresence" data-presence-roster="true" aria-live="polite" aria-busy="<?= !empty($nakes_presence_enabled) ? 'true' : 'false'; ?>">

							<?php if (empty($dashboard_staff_rows)) : ?>
								<div class="nk-staff-empty">Belum ada staf.</div>
							<?php else : ?>
								<div class="nk-staff-list nk-staff-list--presence">
									<?php foreach ($dashboard_staff_rows as $staff) :
										$staff_name = trim((string) (isset($staff->nama) ? $staff->nama : ''));
										$staff_profesi = trim((string) (isset($staff->profesi) ? $staff->profesi : ''));
										$staff_phone = trim((string) (isset($staff->no_hp) ? $staff->no_hp : ''));
										$staff_sip = trim((string) (isset($staff->nomor_sip) ? $staff->nomor_sip : ''));
										$staff_user_id = isset($staff->profile_user_id) ? (int) $staff->profile_user_id : (isset($staff->user_id) ? (int) $staff->user_id : 0);
										$staff_photo = isset($staff->profile_photo) ? trim((string) $staff->profile_photo) : '';
									?>
										<div class="nk-staff-item nk-staff-item--presence" data-presence-user-id="<?= html_escape((string) $staff_user_id); ?>">
											<?php $this->load->view('partials/nakes_avatar_v', array(
												'avatar_name' => $staff_name,
												'avatar_photo' => $staff_photo,
												'avatar_user_id' => $staff_user_id,
												'avatar_alt' => 'Foto ' . ($staff_name !== '' ? $staff_name : 'Nakes'),
												'avatar_class' => 'nk-avatar--sm nk-avatar--nakes',
												'avatar_icon' => 'fas fa-user-md',
											)); ?>
											<div class="nk-staff-main">
												<strong><?= html_escape($staff_name !== '' ? $staff_name : 'Nama belum diisi'); ?> <span class="doclinc-presence-dot is-offline" data-presence-dot aria-hidden="true"></span></strong>
												<?php if ($staff_profesi !== '') : ?><span><?= html_escape($staff_profesi); ?></span><?php endif; ?>
												<small class="nk-staff-presence is-offline" data-presence-label>Offline</small>
												<small class="nk-staff-last-seen" data-presence-last-seen>Belum pernah online</small>
											</div>
											<?php if ($staff_phone !== '') : ?>
												<div class="nk-staff-meta"><span><?= html_escape($staff_phone); ?></span></div>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
						<?php endif; ?>
						<?php
						$this->load->view('partials/nakes_section_header_v', array(
							'section_title' => !empty($nakes_is_personal) ? 'Tugas Anda' : 'Diterima',
							'section_meta' => (string) $nakes_active_count . (!empty($nakes_is_personal) ? ' tugas' : ' diterima'),
							'section_link_label' => 'Lihat',
							'section_link_target' => '#riwayat_konsul',
							'section_class' => 'nakes-current-section-heading',
						));
						$this->load->view('partials/nakes_active_task_card_v', get_defined_vars());
						?>
						<?php if (!empty($nakes_active_rows) && count($nakes_active_rows) > 1) : ?>
						<?php
						$secondary_active_rows = array_slice($nakes_active_rows, 1);
						$secondary_active_total = count($secondary_active_rows);
						$secondary_active_index = 0;
						$secondary_care_team_enabled = !empty($care_team_workflow_enabled);
						$secondary_current_user_id = (int) $this->session->userdata('id');
						?>
						<div class="dl-nakes-card dl-dashboard-section">
							<?php foreach ($secondary_active_rows as $secondary_request) :
								$secondary_queue_label = doclinc_request_queue_number_label($secondary_request);
								$secondary_mode_label = doclinc_consultation_mode_label(isset($secondary_request->consultation_mode) ? $secondary_request->consultation_mode : '');
								$secondary_responsible = $secondary_care_team_enabled
									&& isset($secondary_request->responsible_doctor_user_id)
									&& (int) $secondary_request->responsible_doctor_user_id === $secondary_current_user_id;
								$secondary_performer = $secondary_care_team_enabled
									&& (string) ($secondary_request->consultation_mode ?? '') === 'visit'
									&& isset($secondary_request->visit_performer_user_id)
									&& (int) $secondary_request->visit_performer_user_id === $secondary_current_user_id;
								$secondary_visit_status = isset($secondary_request->visit_status) ? doclinc_normalize_visit_status($secondary_request->visit_status) : 'not_started';
								$secondary_visit_status = $secondary_visit_status !== '' ? $secondary_visit_status : 'not_started';
								if ($secondary_performer) {
									$secondary_link = base_url('home_nakes#riwayat_konsul');
									$secondary_link_label = $secondary_visit_status === 'in_service' ? 'Catat Hasil' : 'Buka Tugas';
								} else {
									$secondary_link = base_url('konsultasi_nakes/konsultasi/' . (int) $secondary_request->request_id);
									$secondary_link_label = $secondary_care_team_enabled && !$secondary_responsible ? 'Lihat' : 'Lanjutkan';
								}
								$secondary_is_last = ++$secondary_active_index >= $secondary_active_total;
							?>
								<div class="dl-latest-row <?= $secondary_is_last ? '' : 'mb-2 pb-2 border-bottom'; ?>">
									<?php
									$secondary_patient_photo = '';
									foreach (array('patient_photo', 'foto', 'photo', 'profile_photo', 'profile_image', 'avatar') as $secondary_photo_key) {
										if (isset($secondary_request->{$secondary_photo_key}) && trim((string) $secondary_request->{$secondary_photo_key}) !== '') {
											$secondary_patient_photo = trim((string) $secondary_request->{$secondary_photo_key});
											break;
										}
									}
									$this->load->view('partials/nakes_avatar_v', array(
										'avatar_name' => $secondary_request->nama,
										'avatar_photo' => $secondary_patient_photo,
										'avatar_user_id' => (int) $secondary_request->user_id,
										'avatar_alt' => 'Foto pasien',
										'avatar_class' => 'nk-avatar--md nk-avatar--patient',
										'avatar_icon' => 'fas fa-user-check',
									));
									?>
									<div class="flex-grow-1 min-w-0">
										<p class="dl-latest-name"><?= html_escape(strtoupper((string) $secondary_request->nama)); ?></p>
										<div class="dl-latest-meta"><?= html_escape($secondary_queue_label); ?> · <?= html_escape($secondary_mode_label); ?></div>
									</div>
									<a href="<?= html_escape($secondary_link); ?>" class="btn btn-outline-success btn-sm rounded-pill fw-bold"><?= html_escape($secondary_link_label); ?></a>
								</div>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
						<?php if (!empty($nakes_is_command_center)) :
						$this->load->view('partials/nakes_section_header_v', array(
							'section_title' => 'Permintaan Konsultasi',
							'section_meta' => (string) $nakes_pending_count . ' menunggu',
							'section_link_label' => 'Semua',
							'section_link_target' => '#req_konsul',
						));
						?>
						<div class="dl-nakes-card dl-dashboard-section">
							<?php if (empty($nakes_latest_pending_rows)) : ?>
								<?php
								$this->load->view('partials/nakes_empty_state_v', array(
									'empty_title' => 'Belum ada permintaan baru',
									'empty_message' => 'Antrian Puskesmas akan diperbarui saat ada pasien masuk.',
								));
								?>
							<?php else :
								$CI = &get_instance();
								$CI->load->library('encryption');
								$latest_total = count($nakes_latest_pending_rows);
								$latest_index = 0;
								foreach ($nakes_latest_pending_rows as $latest_request) :
									$latest_keluhan = $CI->encryption->decrypt(base64_decode($latest_request->request_description));
									$latest_queue_label = doclinc_request_queue_number_label($latest_request);
									$is_last_latest_request = ++$latest_index >= $latest_total;
							?>
									<?php $this->load->view('partials/nakes_compact_request_item_v', get_defined_vars()); ?>
							<?php endforeach;
							endif; ?>
						</div>
						<?php endif; ?>
					</div>
				</div>
