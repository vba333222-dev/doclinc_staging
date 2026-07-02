<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
								<div class="dl-nakes-request-item">
									<div class="card shadow request-card dl-nakes-request-card" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-request-id="<?= html_escape((int) $x->request_id); ?>" data-patient-lat="<?= html_escape($x->lattitude); ?>" data-patient-lng="<?= html_escape($x->longitude); ?>" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
										<div class="card-header">
											<div class="dl-request-heading">
												<div class="dl-queue-badge">
													<small>ANTRIAN</small>
													<span><?= html_escape(doclinc_nakes_queue_badge($x)); ?></span>
												</div>
												<div class="dl-request-identity">
													<div class="dl-request-title"><?= html_escape(strtoupper((string) $x->nama)); ?></div>
													<div class="dl-request-subtitle"><?= html_escape($area_label); ?></div>
												</div>
												<span class="dl-badge animate__animated animate__flash animate__infinite animate__slower">BARU</span>
											</div>
											<div class="dl-request-tags" aria-label="Ringkasan permintaan">
												<span class="dl-request-tag dl-request-tag-strong"><i class="fas fa-clipboard-check"></i><?= html_escape($mode_label); ?></span>
												<span class="dl-request-tag"><i class="fas fa-motorcycle"></i><span class="distance"><?= html_escape($distance_label); ?></span></span>
												<span class="dl-request-tag"><i class="far fa-clock"></i><span class="duration">Menghitung...</span></span>
												<span class="dl-request-tag"><i class="far fa-calendar-alt"></i><?= html_escape($received_label); ?></span>
											</div>
										</div>
										<div id="cekStatus"></div>
										<div class="card-body">
											<div class="dl-nakes-complaint-box">
												<span>Ringkasan keluhan</span>
												<p><?= html_escape($complaint_summary); ?></p>
											</div>
											<div class="dl-nakes-meta-list">
												<div><i class="fas fa-file fa-fw"></i><span>Riwayat</span><strong><?= doclinc_history_safe_text($riwayat); ?></strong></div>
												<div><i class="fas fa-map-marker-alt fa-fw"></i><span>Alamat</span><strong><?= doclinc_history_safe_text($x->location); ?></strong></div>
												<div><i class="fas fa-user-md fa-fw"></i><span>Status</span><strong><?= html_escape($handling_nakes_label); ?></strong></div>
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
											<div class="dl-nakes-actions">
												<input type="hidden" id="id_request<?php echo $i; ?>" value="<?= html_escape((int) $x->request_id); ?>">
												<button type="button" class="btn btn-success shadow-sm rounded-pill start-chat dl-nakes-action-primary"
													id="terimaKonsul<?php echo $i; ?>"
													data-reqid="<?= html_escape((int) $x->request_id); ?>"
													data-userid="<?= html_escape((int) $x->user_id); ?>"
													data-dokterid="<?= html_escape((int) $x->dokter_id); ?>"
													data-namapasien="<?= html_escape($x->nama); ?>"
													data-riwayat="<?= html_escape($riwayat); ?>"
													data-keluhan="<?= html_escape($keluhan); ?>">
													<i class="fa fa-comment-medical me-2"></i> Terima
												</button>
												<div class="dl-nakes-secondary-actions">
													<button type="button" class="btn btn-outline-success shadow-sm rounded-pill lihat-map" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMapTujuan" aria-controls="offcanvasMapTujuan" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
														<i class="fas fa-map-marker-alt me-2"></i> Lihat Lokasi
													</button>
													<button type="button" class="btn btn-outline-danger shadow-sm rounded-pill cancel-nakes-request" data-request-id="<?= html_escape((int) $x->request_id); ?>">
														<i class="fas fa-times-circle me-2"></i> Tolak
													</button>
												</div>
											</div>
										</div>
									</div>
								</div>
								<!-- sampai sini -->
