<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="beranda" class="content <?= (isset($nakes_initial_section) && $nakes_initial_section === 'operasional') ? '' : 'active'; ?>">
					<div class="dl-dashboard-stack">
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
									<?php if (!$role_prerequisite_blocked) : ?><span class="d-block small mt-1">Anda tetap dapat menggunakan aplikasi sementara data diperbarui.</span><?php endif; ?>
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
						$puskesmas_readiness = isset($puskesmas_data_readiness) && is_array($puskesmas_data_readiness) ? $puskesmas_data_readiness : array();
						$readiness_staff_total = isset($puskesmas_readiness['staff_total']) ? (int) $puskesmas_readiness['staff_total'] : 0;
						$readiness_staff_ready = isset($puskesmas_readiness['staff_ready']) ? (int) $puskesmas_readiness['staff_ready'] : 0;
						$readiness_attention_count = isset($puskesmas_readiness['attention_count']) ? (int) $puskesmas_readiness['attention_count'] : 0;
						?>
						<div class="dl-nakes-card dl-dashboard-section nk-readiness-card" data-puskesmas-readiness>
							<div class="nk-readiness-head">
								<div>
									<strong>Kelengkapan data Puskesmas</strong>
									<span>Ringkasan data unit dan staf aktif</span>
								</div>
								<span class="nk-readiness-state <?= !empty($puskesmas_readiness['complete']) ? 'is-ready' : 'needs-attention'; ?>">
									<?= !empty($puskesmas_readiness['complete']) ? 'Lengkap' : html_escape((string) $readiness_attention_count . ' data perlu dilengkapi'); ?>
								</span>
							</div>
							<div class="nk-readiness-grid">
								<div><span>Data unit</span><strong><?= !empty($puskesmas_readiness['facility_complete']) ? 'Lengkap' : 'Perlu dilengkapi'; ?></strong></div>
								<div><span>Data staf lengkap</span><strong><?= html_escape((string) $readiness_staff_ready); ?> / <?= html_escape((string) $readiness_staff_total); ?></strong></div>
								<div><span>SIP belum lengkap</span><strong><?= html_escape((string) (isset($puskesmas_readiness['staff_missing_sip']) ? (int) $puskesmas_readiness['staff_missing_sip'] : 0)); ?></strong></div>
								<div><span>SIP mendekati kedaluwarsa</span><strong><?= html_escape((string) (isset($puskesmas_readiness['staff_sip_expiring']) ? (int) $puskesmas_readiness['staff_sip_expiring'] : 0)); ?></strong></div>
								<div><span>SIP kedaluwarsa</span><strong><?= html_escape((string) (isset($puskesmas_readiness['staff_sip_expired']) ? (int) $puskesmas_readiness['staff_sip_expired'] : 0)); ?></strong></div>
								<div><span>Akun staf perlu diperiksa</span><strong><?= html_escape((string) ((isset($puskesmas_readiness['staff_unlinked']) ? (int) $puskesmas_readiness['staff_unlinked'] : 0) + (isset($puskesmas_readiness['staff_invalid_account']) ? (int) $puskesmas_readiness['staff_invalid_account'] : 0))); ?></strong></div>
							</div>
							<div class="nk-readiness-foot">
								<a href="#profile" onclick="showContent('profile')">Lihat rincian</a>
							</div>
						</div>
						<?php
						$operation_exceptions = isset($puskesmas_operation_exceptions) && is_array($puskesmas_operation_exceptions) ? $puskesmas_operation_exceptions : array();
						$pending_attention = isset($operation_exceptions['pending_requests']) ? (int) $operation_exceptions['pending_requests'] : 0;
						$without_pic_attention = isset($operation_exceptions['accepted_without_pic']) ? (int) $operation_exceptions['accepted_without_pic'] : 0;
						?>
						<div class="dl-nakes-card dl-dashboard-section nk-readiness-card" data-puskesmas-exception-board>
							<div class="nk-readiness-head">
							<div><strong>Yang perlu ditangani</strong><span>Ringkasan permintaan saat ini</span></div>
							<span class="nk-readiness-state <?= ($pending_attention + $without_pic_attention) > 0 ? 'needs-attention' : 'is-ready'; ?>"><?= html_escape((string) ($pending_attention + $without_pic_attention)); ?> permintaan</span>
							</div>
							<div class="nk-readiness-grid">
								<div><span>Permintaan menunggu</span><strong><?= html_escape((string) $pending_attention); ?></strong></div>
							<div><span>Belum ada penanggung jawab layanan</span><strong><?= html_escape((string) $without_pic_attention); ?></strong></div>
								<div><span>Data staf/unit</span><strong><?= html_escape((string) (isset($operation_exceptions['staff_data_attention']) ? (int) $operation_exceptions['staff_data_attention'] : 0)); ?></strong></div>
							</div>
						<div class="nk-readiness-foot"><a href="#req_konsul" onclick="showContent('req_konsul')">Buka antrian</a></div>
						</div>
						<?php
						$dashboard_staff_rows = isset($puskesmas_staff_list) && is_array($puskesmas_staff_list) ? $puskesmas_staff_list : array();
						$dashboard_staff_count = isset($puskesmas_staff_count) ? (int) $puskesmas_staff_count : count($dashboard_staff_rows);
						$this->load->view('partials/nakes_section_header_v', array(
							'section_title' => 'Staf Puskesmas',
							'section_meta' => !empty($nakes_presence_enabled) ? 'Status diperbarui otomatis' : (string) $dashboard_staff_count . ' terdaftar',
						));
						?>
						<div class="dl-nakes-card dl-dashboard-section nk-staff-card doclinc-presence-panel" id="doclincNakesPresence" data-presence-roster="true" aria-live="polite" aria-busy="<?= !empty($nakes_presence_enabled) ? 'true' : 'false'; ?>">
							<div class="doclinc-presence-summary" data-presence-summary>
								<span class="doclinc-presence-count doclinc-presence-count--online">0 online</span>
								<span class="doclinc-presence-count doclinc-presence-count--offline"><?= html_escape((string) $dashboard_staff_count); ?> offline</span>
							</div>
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
											<?php if ($staff_phone !== '' || $staff_sip !== '') : ?>
												<div class="nk-staff-meta">
													<?php if ($staff_phone !== '') : ?><span><?= html_escape($staff_phone); ?></span><?php endif; ?>
													<?php if ($staff_sip !== '') : ?><span>SIP <?= html_escape($staff_sip); ?></span><?php else : ?><span class="is-warning">SIP belum dilengkapi</span><?php endif; ?>
												</div>
											<?php elseif ($staff_sip === '') : ?>
												<div class="nk-staff-meta"><span class="is-warning">SIP belum dilengkapi</span></div>
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
							'section_link_label' => 'Lihat',
							'section_link_target' => '#riwayat_konsul',
						));
						$this->load->view('partials/nakes_active_task_card_v', get_defined_vars());
						if (!empty($nakes_is_command_center)) :
						$this->load->view('partials/nakes_section_header_v', array(
							'section_title' => 'Permintaan masuk Puskesmas',
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
									$latest_queue_code = doclinc_request_queue_code($latest_request);
									$is_last_latest_request = ++$latest_index >= $latest_total;
							?>
									<?php $this->load->view('partials/nakes_compact_request_item_v', get_defined_vars()); ?>
							<?php endforeach;
							endif; ?>
						</div>
						<?php endif; ?>
					</div>
				</div>
