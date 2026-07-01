<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="req_konsul" class="content animate__animated animate__fadeInUp animate__faster">
					<div class="dl-section-header dl-nakes-page-header d-flex justify-content-between">
						<div>
							<div class="dl-card-kicker">Antrian konsultasi</div>
							<h2 class="dl-section-title">Permintaan Masuk</h2>
						</div>
						<span class="dl-header-count"><?= html_escape((string) $nakes_pending_count); ?> menunggu</span>
					</div>
					<div class="dl-nakes-request-list">
						<?php
						$i = 1;
						if ($data_request_new->num_rows() < 1) {
						?>
							<div class="dl-empty-state text-center">
								<img src="<?= html_escape(base_url('assets/images/not found.svg')); ?>" width="150" alt="Tidak ada data">
								<p class="mb-0 mt-3">Belum ada permintaan konsultasi baru.</p>
							</div>
							<?php
						} else {
							$CI = &get_instance();
							$CI->load->library('encryption');
							foreach ($data_request_new->result() as $y => $x) {
								$keluhan = $CI->encryption->decrypt(base64_decode($x->request_description));
								$riwayat = $CI->encryption->decrypt(base64_decode($x->riwayat));
								$queue_code = doclinc_request_queue_code($x);
								$mode_label = doclinc_consultation_mode_label(isset($x->consultation_mode) ? $x->consultation_mode : '');
								$handling_nakes_name = doclinc_request_handling_nakes_name($x);
								$handling_nakes_label = $handling_nakes_name !== '' ? 'Ditangani oleh: ' . $handling_nakes_name : 'Menunggu nakes menerima konsultasi';
								$area_label = !empty($x->assigned_puskesmas_name) ? $x->assigned_puskesmas_name : 'Area pasien';
								$distance_label = !empty($x->distance) ? $x->distance : 'Menghitung...';
								$received_label = !empty($x->created_at) ? date('d M H:i', strtotime($x->created_at)) : 'Baru masuk';
							?>
								<div class="dl-nakes-request-item">
									<div class="card shadow request-card dl-nakes-request-card" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-request-id="<?= html_escape((int) $x->request_id); ?>" data-patient-lat="<?= html_escape($x->lattitude); ?>" data-patient-lng="<?= html_escape($x->longitude); ?>" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
										<div class="card-header d-flex align-items-start gap-3">
											<div class="dl-queue-badge">
												<small>No</small>
												<span><?= html_escape(doclinc_nakes_queue_badge($x)); ?></span>
											</div>
											<div class="flex-grow-1">
												<div class="dl-request-title"><?= html_escape(strtoupper((string) $x->nama)); ?></div>
												<div class="dl-request-tags">
													<span class="dl-request-tag"><?= html_escape($area_label); ?></span>
													<span class="dl-request-tag"><?= html_escape($mode_label); ?></span>
													<span class="dl-request-tag"><?= html_escape($distance_label); ?></span>
													<span class="dl-request-tag"><?= html_escape($received_label); ?></span>
												</div>
											</div>
											<span class="dl-badge animate__animated animate__flash animate__infinite animate__slower">Baru</span>
										</div>
										<div id="cekStatus"></div>
										<div class="card-body">
											<div class="dl-nakes-complaint-box">
												<span>Keluhan</span>
												<p><?= doclinc_history_safe_lines($keluhan); ?></p>
											</div>
											<div class="dl-nakes-meta-list">
												<div><i class="fas fa-user-md fa-fw"></i><span>PIC Nakes</span><strong><?= html_escape($handling_nakes_label); ?></strong></div>
												<div><i class="fas fa-clipboard-check fa-fw"></i><span>Mode</span><strong><?= html_escape($mode_label); ?></strong></div>
												<div><i class="fas fa-file fa-fw"></i><span>Riwayat</span><strong><?= doclinc_history_safe_text($riwayat); ?></strong></div>
												<div><i class="fas fa-map-marker-alt fa-fw"></i><span>Alamat</span><strong><?= doclinc_history_safe_text($x->location); ?></strong></div>
												<div><i class="fas fa-motorcycle fa-fw"></i><span>Jarak</span><strong class="distance">Menghitung...</strong></div>
												<div><i class="far fa-clock fa-fw"></i><span>Estimasi</span><strong class="duration">Menghitung...</strong></div>
											</div>
											<!-- <a class="btn btn-info btn-sm">Lihat Foto</a> <a class="btn btn-info btn-sm">Lihat Video</a> -->
											<?php if (!empty($x->photos)) : ?>
												<button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#fotoModal_<?= html_escape((int) $x->user_id); ?>">Lihat Foto</button>

											<?php endif; ?>

											<?php if (!empty($x->video)) : ?>
												<button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#videoModal_<?= html_escape((int) $x->user_id); ?>">Lihat Video</button>
											<?php endif; ?>
											<div class="dl-nakes-visit-toggle">
												<i class="far fa-question-circle fa-fw"></i> Konfirmasikan kunjungan Anda:
											</div>
											<div class="form-check form-switch mb-0">
												<label class="form-check-label" for="kunjung" id="labelKunjung">Tidak</label>
												<input class="form-check-input" type="checkbox" role="switch" id="kunjung" name="kunjung">
											</div>
										</div>
										<div class="card-footer">
											<div class="row g-2 dl-nakes-actions">
												<div class="col d-grid">
													<input type="hidden" id="id_request<?php echo $i; ?>" value="<?= html_escape((int) $x->request_id); ?>">
													<button type="button" class="btn btn-success shadow-sm rounded-pill start-chat"
														id="terimaKonsul<?php echo $i; ?>"
														data-reqid="<?= html_escape((int) $x->request_id); ?>"
														data-userid="<?= html_escape((int) $x->user_id); ?>"
														data-dokterid="<?= html_escape((int) $x->dokter_id); ?>"
														data-namapasien="<?= html_escape($x->nama); ?>"
														data-riwayat="<?= html_escape($riwayat); ?>"
														data-keluhan="<?= html_escape($keluhan); ?>">
														<i class="fa fa-comment-medical me-2"></i> Terima
													</button>
												</div>
												<div class="col d-grid">
													<button type="button" class="btn btn-outline-success shadow-sm rounded-pill lihat-map" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMapTujuan" aria-controls="offcanvasMapTujuan" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
														<i class="fas fa-map-marker-alt me-2"></i> Lihat Lokasi
													</button>
												</div>
												<div class="col d-grid">
													<button type="button" class="btn btn-outline-danger shadow-sm rounded-pill cancel-nakes-request" data-request-id="<?= html_escape((int) $x->request_id); ?>">
														<i class="fas fa-times-circle me-2"></i> Tolak
													</button>
												</div>
											</div>
										</div>
									</div>
								</div>
								<!-- sampai sini -->
						<?php
								$i++;
							}
						}
						?>
						<input type="hidden" value="<?php echo $i - 1; ?>" id="jumlah_request">
					</div>
				</div>
