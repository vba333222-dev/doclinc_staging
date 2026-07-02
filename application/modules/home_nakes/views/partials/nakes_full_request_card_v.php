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
													<div class="dl-request-subtitle">
														<span><?= html_escape($area_label); ?></span>
														<span><?= html_escape($received_label); ?></span>
													</div>
												</div>
												<span class="dl-badge animate__animated animate__flash animate__infinite animate__slower">BARU</span>
											</div>
										</div>
										<div id="cekStatus"></div>
										<div class="card-body nk-card-body nk-request-card__body">
											<div class="dl-nakes-complaint-box nk-complaint nk-card-section">
												<span class="nk-complaint__title">Keluhan utama</span>
												<p class="nk-complaint__main"><?= html_escape($complaint_primary); ?></p>
												<?php if ($show_complaint_duration || $show_complaint_symptoms || $show_complaint_description) : ?>
													<div class="dl-complaint-facts nk-fact-list">
														<?php if ($show_complaint_duration) : ?>
															<div class="nk-fact-row"><span class="nk-fact-label">Lama</span><strong class="nk-fact-value"><?= html_escape($complaint_duration); ?></strong></div>
														<?php endif; ?>
														<?php if ($show_complaint_symptoms) : ?>
															<div class="nk-fact-row"><span class="nk-fact-label">Gejala</span><strong class="nk-fact-value"><?= html_escape($complaint_symptoms); ?></strong></div>
														<?php endif; ?>
														<?php if ($show_complaint_description) : ?>
															<div class="nk-fact-row"><span class="nk-fact-label">Catatan</span><strong class="nk-fact-value"><?= html_escape($complaint_description); ?></strong></div>
														<?php endif; ?>
													</div>
												<?php endif; ?>
											</div>
											<?php if ($show_address || $show_mode_label || $show_distance_label) : ?>
												<div class="dl-nakes-meta-list nk-info-list nk-card-section">
													<?php if ($show_address) : ?>
														<div class="nk-info-row"><i class="fas fa-map-marker-alt fa-fw"></i><span class="nk-info-label">Alamat</span><strong class="nk-info-value"><?= doclinc_history_safe_text($x->location); ?></strong></div>
													<?php endif; ?>
													<?php if ($show_mode_label) : ?>
														<div class="nk-info-row"><i class="fas fa-clipboard-check fa-fw"></i><span class="nk-info-label">Mode</span><strong class="nk-info-value"><?= html_escape($mode_label); ?></strong></div>
													<?php endif; ?>
													<?php if ($show_distance_label) : ?>
														<div class="nk-info-row"><i class="fas fa-motorcycle fa-fw"></i><span class="nk-info-label">Rute</span><strong class="nk-info-value"><span class="distance"><?= html_escape($distance_label); ?></span><span class="duration dl-route-duration"></span></strong></div>
													<?php endif; ?>
												</div>
												<?php if (!$show_distance_label) : ?>
													<span class="distance dl-js-route-probe"><?= html_escape($distance_label); ?></span>
													<span class="duration dl-js-route-probe"></span>
												<?php endif; ?>
											<?php else : ?>
												<span class="distance dl-js-route-probe"><?= html_escape($distance_label); ?></span>
												<span class="duration dl-js-route-probe"></span>
											<?php endif; ?>
											<!-- <a class="btn btn-info btn-sm">Lihat Foto</a> <a class="btn btn-info btn-sm">Lihat Video</a> -->
											<?php if (!empty($x->photos)) : ?>
												<button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#fotoModal_<?= html_escape((int) $x->user_id); ?>">Lihat Foto</button>

											<?php endif; ?>

											<?php if (!empty($x->video)) : ?>
												<button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#videoModal_<?= html_escape((int) $x->user_id); ?>">Lihat Video</button>
											<?php endif; ?>
											<div class="dl-nakes-visit-toggle nk-visit-confirm-block">
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
