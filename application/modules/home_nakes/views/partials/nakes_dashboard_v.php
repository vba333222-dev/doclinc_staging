<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="beranda" class="content active">
					<div class="dl-dashboard-stack">
						<?php if (!empty($nakes_is_personal)) : ?>
							<div class="alert alert-info mb-0" role="status">Hanya tugas Anda yang tampil di sini.</div>
						<?php endif; ?>
						<?php $this->load->view('partials/nakes_stat_cards_v', get_defined_vars()); ?>
						<?php if (!empty($nakes_is_command_center)) : ?>
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
													<?php if ($staff_sip !== '') : ?><span>SIP <?= html_escape($staff_sip); ?></span><?php endif; ?>
												</div>
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
							'section_title' => !empty($nakes_is_personal) ? 'Tugas Anda' : 'Sedang ditangani',
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
