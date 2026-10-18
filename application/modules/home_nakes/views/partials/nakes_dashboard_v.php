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
						/* Papan status tim menjawab satu pertanyaan: siapa sedang menangani
						   pasien. Grup mengikuti status tugas — bukan presence — supaya daftar
						   tidak perlu diacak ulang setiap heartbeat masuk; kehadiran tetap
						   terbaca per baris lewat titik dan labelnya. */
						$dashboard_staff_rows = isset($puskesmas_staff_list) && is_array($puskesmas_staff_list) ? $puskesmas_staff_list : array();
						$dashboard_staff_count = isset($puskesmas_staff_count) ? (int) $puskesmas_staff_count : count($dashboard_staff_rows);
						$dashboard_team_duty = isset($nakes_team_duty) && is_array($nakes_team_duty) ? $nakes_team_duty : array();
						$dashboard_team_counts = isset($nakes_team_duty_counts) && is_array($nakes_team_duty_counts) ? $nakes_team_duty_counts : array();
						$dashboard_duty_rows = array();
						$dashboard_idle_rows = array();
						foreach ($dashboard_staff_rows as $dashboard_staff) {
							$dashboard_staff_user_id = isset($dashboard_staff->profile_user_id) ? (int) $dashboard_staff->profile_user_id : (isset($dashboard_staff->user_id) ? (int) $dashboard_staff->user_id : 0);
							$dashboard_duty = $dashboard_staff_user_id > 0 && isset($dashboard_team_duty[$dashboard_staff_user_id]) ? $dashboard_team_duty[$dashboard_staff_user_id] : null;
							$dashboard_row = array('staff' => $dashboard_staff, 'user_id' => $dashboard_staff_user_id, 'duty' => $dashboard_duty);
							if ($dashboard_duty !== null) {
								$dashboard_duty_rows[] = $dashboard_row;
							} else {
								$dashboard_idle_rows[] = $dashboard_row;
							}
						}
						/* Urutan baris di sel "Sedang bertugas" mengikuti kegentingan,
						   bukan abjad: kunjungan yang berjalan lebih dulu, lalu
						   disposisi, lalu konsultasi yang sedang ditangani. Di dalam
						   keadaan yang sama, nomor antrean terkecil lebih dulu supaya
						   sejajar dengan halaman Operasional; nama hanya memutus seri.
						   Peringkat ini cermin `$rank` di
						   Home_nakes::puskesmas_team_duty() dan dijaga oleh suite
						   puskesmas_team_status. Daftar "Tidak bertugas" tetap abjad:
						   tidak ada yang mendesak di sana. */
						$dashboard_duty_rank = array('visit' => 3, 'disposisi' => 2, 'menangani' => 1);
						usort($dashboard_duty_rows, static function ($a, $b) use ($dashboard_duty_rank) {
							$state_a = isset($a['duty']['state']) ? (string) $a['duty']['state'] : '';
							$state_b = isset($b['duty']['state']) ? (string) $b['duty']['state'] : '';
							$rank_a = isset($dashboard_duty_rank[$state_a]) ? (int) $dashboard_duty_rank[$state_a] : 0;
							$rank_b = isset($dashboard_duty_rank[$state_b]) ? (int) $dashboard_duty_rank[$state_b] : 0;
							if ($rank_a !== $rank_b) {
								return $rank_b <=> $rank_a;
							}
							$queue_a = isset($a['duty']['queue_label']) ? trim((string) $a['duty']['queue_label']) : '';
							$queue_b = isset($b['duty']['queue_label']) ? trim((string) $b['duty']['queue_label']) : '';
							if ($queue_a === '' || $queue_b === '') {
								if ($queue_a !== $queue_b) {
									return $queue_a === '' ? 1 : -1;
								}
							} else {
								$queue_order = strnatcasecmp($queue_a, $queue_b);
								if ($queue_order !== 0) {
									return $queue_order;
								}
							}
							return strnatcasecmp(
								trim((string) (isset($a['staff']->nama) ? $a['staff']->nama : '')),
								trim((string) (isset($b['staff']->nama) ? $b['staff']->nama : ''))
							);
						});
						usort($dashboard_idle_rows, static function ($a, $b) {
							return strnatcasecmp(
								trim((string) (isset($a['staff']->nama) ? $a['staff']->nama : '')),
								trim((string) (isset($b['staff']->nama) ? $b['staff']->nama : ''))
							);
						});
						$dashboard_team_cells = array(
							array(
								'key' => 'duty',
								'title' => 'Sedang bertugas',
								'count' => isset($dashboard_team_counts['on_duty']) ? (int) $dashboard_team_counts['on_duty'] : count($dashboard_duty_rows),
								'rows' => $dashboard_duty_rows,
								'empty' => 'Belum ada staf yang sedang menangani pasien.',
							),
							array(
								'key' => 'idle',
								'title' => 'Tidak bertugas',
								'count' => count($dashboard_idle_rows),
								'rows' => $dashboard_idle_rows,
								'empty' => 'Semua staf sedang bertugas.',
							),
						);
						$dashboard_duty_labels = array('visit' => 'Visit', 'disposisi' => 'Disposisi', 'menangani' => 'Menangani');
						$dashboard_duty_icons = array('visit' => 'fas fa-stethoscope', 'disposisi' => 'fas fa-share', 'menangani' => 'fas fa-comment-medical');
						$dashboard_duty_hints = array(
							'visit' => 'Kunjungan dokter sedang berjalan',
							'disposisi' => 'Perawat atau bidan sedang menjalankan kunjungan',
							'menangani' => 'Sedang menangani konsultasi',
						);
						$dashboard_ready_attention = isset($puskesmas_data_readiness['attention_count']) ? (int) $puskesmas_data_readiness['attention_count'] : 0;
						$dashboard_ready_sip = isset($puskesmas_data_readiness['staff_missing_sip']) ? (int) $puskesmas_data_readiness['staff_missing_sip'] : 0;
						$dashboard_without_pic = isset($puskesmas_operation_exceptions['accepted_without_pic']) ? (int) $puskesmas_operation_exceptions['accepted_without_pic'] : 0;
						?>
						<div class="dl-team-bento doclinc-presence-panel" id="doclincNakesPresence" data-presence-roster="true" aria-busy="<?= !empty($nakes_presence_enabled) ? 'true' : 'false'; ?>">
							<div class="dl-team-bento__head">
								<?php $this->load->view('partials/nakes_section_header_v', array(
									'section_title' => 'Staf Puskesmas',
									'section_meta' => (string) $dashboard_staff_count . ' staf',
									'section_inside' => true,
									'section_class' => 'dl-team-bento__title',
								)); ?>
								<p class="dl-team-bento__summary" data-presence-summary role="status" aria-live="polite"><?php if (!empty($nakes_presence_enabled)) : ?><span class="dl-team-bento__summary-loading">Memuat status&hellip;</span><?php endif; ?></p>
							</div>
							<div class="dl-team-bento__grid">
								<?php foreach ($dashboard_team_cells as $dashboard_cell) : ?>
								<section class="dl-nakes-card dl-team-cell" data-team-cell="<?= html_escape($dashboard_cell['key']); ?>" aria-labelledby="dlTeam<?= html_escape(ucfirst($dashboard_cell['key'])); ?>Title">
									<div class="dl-team-cell__head">
										<h3 class="dl-team-cell__title" id="dlTeam<?= html_escape(ucfirst($dashboard_cell['key'])); ?>Title"><?= html_escape($dashboard_cell['title']); ?></h3>
										<span class="dl-team-cell__count"><?= (int) $dashboard_cell['count']; ?></span>
									</div>
									<?php if (empty($dashboard_cell['rows'])) : ?>
										<p class="dl-team-cell__empty"><?= html_escape($dashboard_cell['empty']); ?></p>
									<?php else : ?>
										<div class="nk-staff-list dl-team-list" role="list">
											<?php foreach ($dashboard_cell['rows'] as $dashboard_row) :
												$staff = $dashboard_row['staff'];
												$staff_name = trim((string) (isset($staff->nama) ? $staff->nama : ''));
												$staff_profesi = trim((string) (isset($staff->profesi) ? $staff->profesi : ''));
												$staff_user_id = (int) $dashboard_row['user_id'];
												$staff_photo = isset($staff->profile_photo) ? trim((string) $staff->profile_photo) : '';
												$duty = $dashboard_row['duty'];
												$duty_state = is_array($duty) && isset($duty['state']) && isset($dashboard_duty_labels[$duty['state']]) ? (string) $duty['state'] : '';
												$duty_queue = is_array($duty) && !empty($duty['queue_label']) ? (string) $duty['queue_label'] : '';
												$duty_extra_cases = is_array($duty) && isset($duty['cases']) ? max(0, (int) $duty['cases'] - 1) : 0;
											?>
											<div class="nk-staff-item nk-staff-item--presence dl-team-row" role="listitem" data-duty-state="<?= $duty_state !== '' ? html_escape($duty_state) : 'idle'; ?>"<?= $staff_user_id > 0 ? ' data-presence-user-id="' . html_escape((string) $staff_user_id) . '"' : ''; ?>>
												<?php $this->load->view('partials/nakes_avatar_v', array(
													'avatar_name' => $staff_name,
													'avatar_photo' => $staff_photo,
													'avatar_user_id' => $staff_user_id,
													'avatar_alt' => 'Foto ' . ($staff_name !== '' ? $staff_name : 'Nakes'),
													'avatar_class' => 'nk-avatar--sm nk-avatar--nakes',
													'avatar_icon' => 'fas fa-user-md',
												)); ?>
												<div class="nk-staff-main">
													<strong><span class="dl-team-row__name"><?= html_escape($staff_name !== '' ? $staff_name : 'Nama belum diisi'); ?></span><?php if ($staff_profesi !== '') : ?><span class="dl-team-row__role"><?= html_escape($staff_profesi); ?></span><?php endif; ?></strong>
												</div>
												<div class="nk-staff-meta">
													<?php if ($staff_user_id > 0) : ?>
														<span class="nk-staff-presence-chip">
															<span class="doclinc-presence-dot is-offline" data-presence-dot aria-hidden="true"></span>
															<small class="nk-staff-presence is-offline" data-presence-label>Offline</small>
														</span>
														<small class="nk-staff-last-seen" data-presence-last-seen>Belum pernah online</small>
													<?php else : ?>
														<span class="dl-team-row__no-account"><i class="fas fa-user-slash" aria-hidden="true"></i>Tanpa akun</span>
													<?php endif; ?>
													<?php if ($duty_state !== '') : ?>
														<span class="dl-team-badge is-<?= html_escape($duty_state); ?><?= $duty_state === 'menangani' ? ' is-sr-only' : ''; ?>" title="<?= html_escape($dashboard_duty_hints[$duty_state]); ?>"><i class="<?= html_escape($dashboard_duty_icons[$duty_state]); ?>" aria-hidden="true"></i><?= html_escape($dashboard_duty_labels[$duty_state]); ?></span>
													<?php endif; ?>
													<?php if ($duty_queue !== '') : ?>
														<span class="dl-team-row__queue"><?= html_escape($duty_queue); ?><?= $duty_extra_cases > 0 ? ' +' . (int) $duty_extra_cases : ''; ?></span>
													<?php endif; ?>
												</div>
											</div>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</section>
								<?php endforeach; ?>
							</div>
							<?php if ($dashboard_ready_attention > 0 || $dashboard_without_pic > 0) : ?>
								<p class="dl-team-bento__note" data-puskesmas-readiness="<?= html_escape(json_encode(array('attention_count' => $dashboard_ready_attention, 'staff_missing_sip' => $dashboard_ready_sip))); ?>">
									<i class="fas fa-info-circle" aria-hidden="true"></i>
									<?php if ($dashboard_ready_attention > 0) : ?>
										<span><?= (int) $dashboard_ready_attention; ?> data staf perlu dilengkapi<?= $dashboard_ready_sip > 0 ? ' — SIP belum dilengkapi' : ''; ?>.</span>
									<?php endif; ?>
									<?php if ($dashboard_without_pic > 0) : ?>
										<span data-puskesmas-exception-board>Belum ada penanggung jawab layanan untuk <?= (int) $dashboard_without_pic; ?> kunjungan.</span>
									<?php endif; ?>
								</p>
							<?php endif; ?>
						</div>
						<?php endif; ?>
						
											</div>
				</div>
