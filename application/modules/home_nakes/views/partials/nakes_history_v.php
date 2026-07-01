<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="riwayat_konsul" class="content animate__animated animate__fadeInUp animate__faster">
					<div class="dl-section-header dl-nakes-page-header">
						<h2 class="dl-section-title">Riwayat Konsultasi</h2>
						<span class="dl-nakes-link"><?= html_escape((string) $nakes_active_count); ?> aktif</span>
					</div>
					<ul class="nav nav-tabs nav-justified mb-3 dl-tabs" id="myTab" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" id="proses-tab" data-bs-toggle="tab" data-bs-target="#proses-tab-pane" type="button" role="tab" aria-controls="proses-tab-pane" aria-selected="false">Saat ini</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="selesai-tab" data-bs-toggle="tab" data-bs-target="#selesai-tab-pane" type="button" role="tab" aria-controls="selesai-tab-pane" aria-selected="false">Riwayat</button>
						</li>
					</ul>
					<div class="tab-content" id="myTabContent">
						<div class="tab-pane fade show active" id="proses-tab-pane" role="tabpanel" aria-labelledby="proses-tab" tabindex="0">
							<?php
							$CI = &get_instance();
							$CI->load->library('encryption');
							foreach ($data_request_new->result() as $x) {
								$keluhan = $CI->encryption->decrypt(base64_decode($x->request_description));
								$riwayat = $CI->encryption->decrypt(base64_decode($x->riwayat));
								$queue_code = doclinc_request_queue_code($x);
								$mode_label = doclinc_consultation_mode_label(isset($x->consultation_mode) ? $x->consultation_mode : '');
								$handling_nakes_name = doclinc_request_handling_nakes_name($x);
								$handling_nakes_label = $handling_nakes_name !== '' ? 'Ditangani oleh: ' . $handling_nakes_name : 'Menunggu nakes menerima konsultasi';
							?>
								<div class="card shadow mb-2 dl-nakes-request-card" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-request-id="<?= html_escape((int) $x->request_id); ?>" data-patient-lat="<?= html_escape($x->lattitude); ?>" data-patient-lng="<?= html_escape($x->longitude); ?>">
									<div class="card-header d-flex align-items-start gap-3">
										<div class="dl-nakes-avatar-icon">
											<i class="fas fa-user"></i>
										</div>
										<div class="flex-grow-1">
											<strong><?= html_escape(strtoupper((string) $x->nama)); ?></strong>
											<span><?= html_escape(date('d-m-Y', strtotime($x->created_at))); ?> · No. Antrian: <?= html_escape($queue_code); ?></span>
										</div>
										<span class="dl-badge animate__animated animate__flash animate__infinite animate__slower" id="status-konsul">Baru</span>
									</div>
									<div class="card-body">
										<div class="dl-nakes-complaint-box">
											<span>Keluhan</span>
											<p><?= doclinc_history_safe_lines($keluhan); ?></p>
										</div>
										<div class="dl-nakes-meta-list">
											<div><i class="fas fa-user-md fa-fw"></i><span>PIC Nakes</span><strong><?= html_escape($handling_nakes_label); ?></strong></div>
											<div><i class="fas fa-clipboard-check fa-fw"></i><span>Mode</span><strong><?= html_escape($mode_label); ?></strong></div>
											<div><i class="far fa-clock fa-fw"></i><span>Estimasi</span><strong><?= doclinc_history_safe_text($x->duration); ?></strong></div>
											<div><i class="fas fa-motorcycle fa-fw"></i><span>Jarak</span><strong><?= doclinc_history_safe_text($x->distance); ?></strong></div>
										</div>
									</div>
									<div class="card-footer d-flex">
										<button type="button" class="btn btn-outline-success shadow-sm rounded-pill lihat-map" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMapTujuan" aria-controls="offcanvasMapTujuan" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
											<i class="fas fa-map-marker-alt me-2"></i> Lihat Lokasi
										</button>
										<button type="button" class="btn btn-outline-danger shadow-sm rounded-pill ms-2 cancel-nakes-request" data-request-id="<?= html_escape((int) $x->request_id); ?>">
											<i class="fas fa-times-circle me-2"></i> Tolak
										</button>
										<!-- <button class="btn btn-success shadow-sm rounded-pill ms-auto" id="tombolSaran">Berikan Saran</button> -->
									</div>
								</div>
							<?php
							}
							$i = 1;

							$CI = &get_instance();
							$CI->load->library('encryption');
							foreach ($data_request_accept->result() as $x) {
								$keluhan = $CI->encryption->decrypt(base64_decode($x->request_description));
								$riwayat = $CI->encryption->decrypt(base64_decode($x->riwayat));
								$queue_code = doclinc_request_queue_code($x);
								$visit_status = isset($x->visit_status) ? doclinc_normalize_visit_status($x->visit_status) : '';
								$visit_status = $visit_status !== '' ? $visit_status : 'not_started';
								$mode_label = doclinc_consultation_mode_label(isset($x->consultation_mode) ? $x->consultation_mode : '');
								$handling_nakes_name = doclinc_request_handling_nakes_name($x);
								$handling_nakes_label = $handling_nakes_name !== '' ? 'Ditangani oleh: ' . $handling_nakes_name : 'Menunggu nakes menerima konsultasi';
								$visit_next_status = array(
									'not_started' => 'en_route',
									'en_route' => 'arrived',
									'arrived' => 'in_service',
									'in_service' => 'completed',
									'completed' => '',
								);
							?>
								<div class="card shadow mb-2 dl-nakes-request-card" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-request-id="<?= html_escape((int) $x->request_id); ?>" data-patient-lat="<?= html_escape($x->lattitude); ?>" data-patient-lng="<?= html_escape($x->longitude); ?>">
									<div class="card-header d-flex align-items-start gap-3">
										<div class="dl-nakes-avatar-icon">
											<i class="fas fa-user-check"></i>
										</div>
										<div class="flex-grow-1">
											<strong><?= html_escape(strtoupper((string) $x->nama)); ?></strong>
											<span><?= html_escape(date('d-m-Y', strtotime($x->created_at))); ?> · No. Antrian: <?= html_escape($queue_code); ?></span>
										</div>
										<span class="dl-badge animate__animated animate__flash animate__infinite animate__slower" id="status-konsul">Accepted</span>
									</div>
									<div class="card-body">
										<div class="dl-nakes-complaint-box">
											<span>Keluhan</span>
											<p><?= doclinc_history_safe_lines($keluhan); ?></p>
										</div>
										<input type="text" class="visit-patient-lat d-none" value="<?= html_escape($x->lattitude); ?>">
										<input type="text" class="visit-patient-lng d-none" value="<?= html_escape($x->longitude); ?>">
										<div class="dl-nakes-meta-list">
											<div><i class="fas fa-user-md fa-fw"></i><span>PIC Nakes</span><strong><?= html_escape($handling_nakes_label); ?></strong></div>
											<div><i class="fas fa-clipboard-check fa-fw"></i><span>Mode</span><strong><?= html_escape($mode_label); ?></strong></div>
											<div><i class="fas fa-motorcycle fa-fw"></i><span>Jarak</span><strong class="visit-route-distance" data-route-distance="<?= html_escape((int) $x->request_id); ?>">Menghitung...</strong></div>
											<div><i class="far fa-clock fa-fw"></i><span>Estimasi</span><strong class="visit-route-eta" data-route-eta="<?= html_escape((int) $x->request_id); ?>">Menghitung...</strong></div>
										</div>
										<div class="visit-route-provider-note mt-2 d-none" data-route-provider-note="<?= html_escape((int) $x->request_id); ?>"></div>
										<div class="alert alert-success py-2 px-3 mt-2 mb-0 d-none" data-arrival-notice="<?= html_escape((int) $x->request_id); ?>">
											<div class="small fw-bold" data-arrival-message="<?= html_escape((int) $x->request_id); ?>"></div>
											<button type="button" class="btn btn-success btn-sm rounded-pill mt-2 visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="arrived">
												Konfirmasi tiba di lokasi
											</button>
										</div>
										<div class="mt-3 visit-workflow-control" data-visit-workflow="<?= html_escape((int) $x->request_id); ?>" data-current-status="<?= html_escape($visit_status); ?>">
											<div class="small text-muted mb-2">Status kunjungan: <span class="fw-bold visit-workflow-label"><?= html_escape(doclinc_visit_status_label($visit_status)); ?></span></div>
											<div class="d-flex flex-wrap gap-2">
												<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="en_route" <?= $visit_next_status[$visit_status] === 'en_route' ? '' : 'disabled'; ?>>Mulai Perjalanan</button>
												<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="arrived" <?= $visit_next_status[$visit_status] === 'arrived' ? '' : 'disabled'; ?>>Tiba di Lokasi</button>
												<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="in_service" <?= $visit_next_status[$visit_status] === 'in_service' ? '' : 'disabled'; ?>>Mulai Penanganan</button>
												<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="completed" <?= $visit_next_status[$visit_status] === 'completed' ? '' : 'disabled'; ?>>Kunjungan Selesai</button>
											</div>
											<div class="small mt-2 visit-workflow-message" data-visit-workflow-message="<?= html_escape((int) $x->request_id); ?>"></div>
										</div>
									</div>
									<div class="card-footer d-flex flex-wrap gap-2 dl-nakes-actions">
										<button type="button" class="btn btn-outline-success shadow-sm rounded-pill lihat-map" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMapTujuan" aria-controls="offcanvasMapTujuan" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
											<i class="fas fa-map-marker-alt me-2"></i> Lihat Lokasi
										</button>
										<a href="<?= html_escape(base_url('konsultasi_nakes/konsultasi/' . (int) $x->request_id) . '?kriteria=1'); ?>" class="btn btn-success shadow-sm rounded-pill">
											<i class="fas fa-notes-medical me-2"></i> Lanjut Konsultasi
										</a>
										<a href="<?= html_escape(base_url('chat?request_id=' . (int) $x->request_id)); ?>" class="btn btn-outline-success shadow-sm rounded-pill">
											<i class="fas fa-comments me-2"></i> Chat Konsultasi
										</a>
										<button type="button" class="btn btn-outline-primary shadow-sm rounded-pill start-nakes-visit-tracking" data-request-id="<?= html_escape((int) $x->request_id); ?>">
											<i class="fas fa-location-arrow me-2"></i> Aktifkan Lokasi Visit
										</button>
										<button type="button" class="btn btn-outline-danger shadow-sm rounded-pill cancel-nakes-request" data-request-id="<?= html_escape((int) $x->request_id); ?>">
											<i class="fas fa-times-circle me-2"></i> Batalkan
										</button>
										<span class="small text-muted w-100 visit-tracking-status" data-tracking-status="<?= html_escape((int) $x->request_id); ?>"></span>
									</div>
								</div>
							<?php
								$i++;
							}
							?>
							<input type="hidden" id="jumlah_accepted" value="<?php echo $i - 1; ?>">
						</div>
						<div class="tab-pane fade" id="selesai-tab-pane" role="tabpanel" aria-labelledby="selesai-tab" tabindex="0">
							<?php
							$CI = &get_instance();
							$CI->load->library('encryption');
							if ($data_request_completed->num_rows() < 1) {
							?>
								<div class="text-center py-4">
									<img src="<?= html_escape(base_url('assets/images/not found.svg')); ?>" width="180" alt="Tidak ada data">
									<p class="mb-0 mt-3 text-muted">Belum ada riwayat konsultasi selesai.</p>
								</div>
							<?php
							}
							foreach ($data_request_completed->result() as $x) {
								try {
									$keluhan = $CI->encryption->decrypt(base64_decode($x->request_description));
								} catch (Exception $e) {
									$keluhan = 'Keluhan tersimpan';
								}
								try {
									$riwayat = $CI->encryption->decrypt(base64_decode($x->riwayat));
								} catch (Exception $e) {
									$riwayat = '';
								}
								$diagnosa = !empty($x->diagnosa) ? $x->diagnosa : (!empty($x->diagnosis) ? $x->diagnosis : '-');
								$saran = !empty($x->saran) ? $x->saran : (!empty($x->recommendations) ? $x->recommendations : '-');
								$treatment = !empty($x->treatment) ? $x->treatment : '';
								$puskesmas = !empty($x->assigned_puskesmas_name) ? $x->assigned_puskesmas_name : '';
								$tanggal_selesai = !empty($x->created_at) ? date('d-m-Y', strtotime($x->created_at)) : '-';
								$queue_code = doclinc_request_queue_code($x);
								$mode_label = doclinc_consultation_mode_label(isset($x->consultation_mode) ? $x->consultation_mode : '');
								$handling_nakes_name = doclinc_request_handling_nakes_name($x);
								$handling_nakes_label = $handling_nakes_name !== '' ? 'Ditangani oleh: ' . $handling_nakes_name : 'Menunggu nakes menerima konsultasi';
							?>
								<div class="card shadow mb-3 history-result-card" data-request-id="<?= (int) $x->request_id; ?>">
									<div class="card-header d-flex align-items-start gap-3">
										<div class="flex-grow-1">
											<div class="history-request-id">No. Antrian: <?= html_escape($queue_code); ?></div>
											<div class="history-meta">
												<?= doclinc_history_safe_text($tanggal_selesai); ?><br>
												Pasien: <?= doclinc_history_safe_text(strtoupper((string) $x->nama)); ?>
												<br><?= html_escape($handling_nakes_label); ?>
												<br>Mode: <?= html_escape($mode_label); ?>
												<?php if ($puskesmas !== '') : ?>
													<br><?= doclinc_history_safe_text($puskesmas); ?>
												<?php endif; ?>
											</div>
										</div>
										<span class="history-status-badge">Completed</span>
									</div>
									<div class="card-body">
										<div class="history-section">
											<div class="history-section-title"><i class="fas fa-notes-medical me-1"></i> Keluhan Awal</div>
											<?= doclinc_history_format_complaint($keluhan); ?>
										</div>
										<div class="history-section">
											<div class="history-section-title"><i class="fas fa-file-medical-alt me-1"></i> Hasil Konsultasi</div>
											<div class="history-result-row">
												<span class="history-result-label">Diagnosa</span>
												<div class="history-result-value"><?= doclinc_history_safe_lines($diagnosa); ?></div>
											</div>
											<div class="history-result-row">
												<span class="history-result-label">Terapi / Tindakan</span>
												<?php if ($treatment !== '') : ?>
													<div class="history-result-value"><?= doclinc_history_safe_lines($treatment); ?></div>
												<?php else : ?>
													<p class="history-empty-text mb-0">Belum ada terapi/tindakan yang tercatat.</p>
												<?php endif; ?>
											</div>
											<div class="history-result-row">
												<span class="history-result-label">Rekomendasi / Saran</span>
												<div class="history-result-value"><?= doclinc_history_safe_lines($saran); ?></div>
											</div>
										</div>
									</div>
									<div class="card-footer">
										<a href="<?= html_escape(base_url('chat?request_id=' . (int) $x->request_id)); ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
											<i class="fas fa-comments me-1"></i> Riwayat Chat
										</a>
									</div>
								</div>
							<?php } ?>
						</div>
					</div>
				</div>
