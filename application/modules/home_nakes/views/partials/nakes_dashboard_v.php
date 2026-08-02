<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="beranda" class="content active">
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
									<strong><?= $role_prerequisite_blocked ? 'Profil wajib dilengkapi.' : 'Kesiapan profil belum lengkap.'; ?></strong>
									<span><?= html_escape(!empty($role_prerequisite_labels) ? implode(', ', $role_prerequisite_labels) . ($role_prerequisite_extra_count > 0 ? ' dan ' . $role_prerequisite_extra_count . ' data lainnya' : '') . ' perlu dilengkapi.' : 'Lengkapi data profil untuk melanjutkan.'); ?></span>
									<?php if (!$role_prerequisite_blocked) : ?><span class="d-block small mt-1">Layanan tetap dapat digunakan selama tahap penyiapan data.</span><?php endif; ?>
								</div>
								<?php if ($role_prerequisite_cta_url !== '') : ?>
									<a class="btn btn-warning btn-sm" href="#profile" onclick="showContent('profile')"><?= html_escape($role_prerequisite_cta_label !== '' ? $role_prerequisite_cta_label : 'Lengkapi profil'); ?></a>
								<?php else : ?>
									<span class="small fw-semibold">Hubungi pengelola DocLink.</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if (!empty($nakes_is_personal)) : ?>
							<div class="alert alert-info mb-0" role="status">Hanya tugas Anda yang tampil di sini.</div>
						<?php endif; ?>
						<?php $this->load->view('partials/nakes_stat_cards_v', get_defined_vars()); ?>
						<?php if (!empty($nakes_is_command_center) && !empty($nakes_presence_enabled)) : ?>
							<?php $this->load->view('partials/nakes_section_header_v', array(
								'section_title' => 'Status Nakes',
								'section_meta' => 'Diperbarui otomatis',
							)); ?>
							<div class="dl-nakes-card dl-dashboard-section doclinc-presence-panel">
								<div id="doclincNakesPresence" aria-live="polite" aria-busy="true">
									<div class="doclinc-presence-empty">Memuat status Nakes...</div>
								</div>
							</div>
						<?php endif; ?>
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
									<strong>Kesiapan data Puskesmas</strong>
									<span>Ringkasan data unit dan staf aktif</span>
								</div>
								<span class="nk-readiness-state <?= !empty($puskesmas_readiness['complete']) ? 'is-ready' : 'needs-attention'; ?>">
									<?= !empty($puskesmas_readiness['complete']) ? 'Lengkap' : html_escape((string) $readiness_attention_count . ' perlu ditinjau'); ?>
								</span>
							</div>
							<div class="nk-readiness-grid">
								<div><span>Data unit</span><strong><?= !empty($puskesmas_readiness['facility_complete']) ? 'Lengkap' : 'Perlu dilengkapi'; ?></strong></div>
								<div><span>Staf siap</span><strong><?= html_escape((string) $readiness_staff_ready); ?> / <?= html_escape((string) $readiness_staff_total); ?></strong></div>
								<div><span>SIP belum lengkap</span><strong><?= html_escape((string) (isset($puskesmas_readiness['staff_missing_sip']) ? (int) $puskesmas_readiness['staff_missing_sip'] : 0)); ?></strong></div>
								<div><span>NIP belum lengkap</span><strong><?= html_escape((string) (isset($puskesmas_readiness['staff_missing_nip']) ? (int) $puskesmas_readiness['staff_missing_nip'] : 0)); ?></strong></div>
								<div><span>Akun perlu ditinjau</span><strong><?= html_escape((string) ((isset($puskesmas_readiness['staff_unlinked']) ? (int) $puskesmas_readiness['staff_unlinked'] : 0) + (isset($puskesmas_readiness['staff_invalid_account']) ? (int) $puskesmas_readiness['staff_invalid_account'] : 0))); ?></strong></div>
							</div>
							<div class="nk-readiness-foot">
								<span>Panel ini hanya membaca data tenant Anda dan belum memblokir layanan.</span>
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
								<div><strong>Perlu perhatian</strong><span>Prioritas operasional Puskesmas saat ini</span></div>
								<span class="nk-readiness-state <?= ($pending_attention + $without_pic_attention) > 0 ? 'needs-attention' : 'is-ready'; ?>"><?= html_escape((string) ($pending_attention + $without_pic_attention)); ?> item</span>
							</div>
							<div class="nk-readiness-grid">
								<div><span>Permintaan menunggu</span><strong><?= html_escape((string) $pending_attention); ?></strong></div>
								<div><span>Diterima tanpa PIC</span><strong><?= html_escape((string) $without_pic_attention); ?></strong></div>
								<div><span>Data staf/unit</span><strong><?= html_escape((string) (isset($operation_exceptions['staff_data_attention']) ? (int) $operation_exceptions['staff_data_attention'] : 0)); ?></strong></div>
							</div>
							<div class="nk-readiness-foot"><span>Hanya data Puskesmas Anda. Nomor identitas dan data klinis tidak ditampilkan.</span><a href="#req_konsul" onclick="showContent('req_konsul')">Buka antrian</a></div>
						</div>
						<?php
						$dashboard_staff_rows = isset($puskesmas_staff_list) && is_array($puskesmas_staff_list) ? array_slice($puskesmas_staff_list, 0, 3) : array();
						$dashboard_staff_count = isset($puskesmas_staff_count) ? (int) $puskesmas_staff_count : count($dashboard_staff_rows);
						$this->load->view('partials/nakes_section_header_v', array(
							'section_title' => 'Staf Puskesmas',
							'section_meta' => (string) $dashboard_staff_count . ' terdaftar',
						));
						?>
						<div class="dl-nakes-card dl-dashboard-section nk-staff-card">
							<?php if (empty($dashboard_staff_rows)) : ?>
								<div class="nk-staff-empty">Belum ada staf.</div>
							<?php else : ?>
								<div class="nk-staff-list">
									<?php foreach ($dashboard_staff_rows as $staff) :
										$staff_name = trim((string) (isset($staff->nama) ? $staff->nama : ''));
										$staff_profesi = trim((string) (isset($staff->profesi) ? $staff->profesi : ''));
										$staff_phone = trim((string) (isset($staff->no_hp) ? $staff->no_hp : ''));
										$staff_sip = trim((string) (isset($staff->nomor_sip) ? $staff->nomor_sip : ''));
									?>
										<div class="nk-staff-item">
											<div class="nk-staff-main">
												<strong><?= html_escape($staff_name !== '' ? $staff_name : 'Nama belum diisi'); ?></strong>
												<?php if ($staff_profesi !== '') : ?><span><?= html_escape($staff_profesi); ?></span><?php endif; ?>
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
								<?php if ($dashboard_staff_count > count($dashboard_staff_rows)) : ?>
									<div class="nk-staff-more"><?= html_escape((string) ($dashboard_staff_count - count($dashboard_staff_rows))); ?> staf lainnya ada di profil.</div>
								<?php endif; ?>
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
