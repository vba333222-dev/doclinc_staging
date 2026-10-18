<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
								<div class="dl-nakes-request-item">
									<div class="card shadow request-card dl-nakes-request-card" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-id="<?= html_escape((int) $x->request_id); ?>" data-visit-request-id="<?= html_escape((int) $x->request_id); ?>" data-patient-lat="<?= html_escape($x->lattitude); ?>" data-patient-lng="<?= html_escape($x->longitude); ?>" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
										<div class="card-header">
											<div class="dl-request-heading">
										<?php
										$request_card_photo = '';
										foreach (array('patient_photo', 'foto', 'photo', 'profile_photo', 'profile_image', 'avatar') as $request_card_photo_key) {
										if (isset($x->{$request_card_photo_key}) && trim((string) $x->{$request_card_photo_key}) !== '') {
										$request_card_photo = trim((string) $x->{$request_card_photo_key});
										break;
										}
										}
										$this->load->view('partials/nakes_avatar_v', array(
										'avatar_name' => $x->nama,
										'avatar_photo' => $request_card_photo,
										'avatar_user_id' => (int) $x->user_id,
										'avatar_alt' => 'Foto pasien',
										'avatar_class' => 'nk-avatar--md nk-avatar--patient',
										));
										?>
										<div class="dl-request-identity">
											<div class="dl-request-title"><?= html_escape((string) $x->nama); ?></div>
											<div class="dl-request-subtitle">
												<span><?= html_escape($area_label); ?></span>
												<span><?= html_escape($received_label); ?></span>
											</div>
																					<?php
											$request_state = strtolower(trim((string) (isset($x->request_status) ? $x->request_status : '')));
											if ($request_state === '') {
												$request_state = 'pending';
											}
											/* Keadaan menentukan warna chip; teksnya tetap sumber makna untuk pembaca
											   layar, karena warna saja tidak boleh menjadi satu-satunya pembeda. */
											$request_chip_label = $request_state === 'accepted' ? 'Sudah diterima' : 'Segera konfirmasi';
										?>
										<span class="dl-badge dl-badge--action" data-request-state="<?= html_escape($request_state); ?>"><?= html_escape($request_chip_label); ?></span>
										</div>
										<div class="dl-queue-badge">
											<small>Antrian</small>
											<span><?= html_escape(doclinc_nakes_queue_badge($x)); ?></span>
										</div>
									</div>
									</div>
									<div id="cekStatus"></div>
										<div class="card-body nk-card-body nk-request-card__body">
											<div class="dl-nakes-complaint-box nk-complaint nk-card-section">
												<h3 class="nk-complaint__title">Keluhan utama</h3>
																								<?php if (!empty($complaint_lead)) : ?>
													<span class="nk-complaint__lead"><?= html_escape($complaint_lead); ?></span>
												<?php endif; ?>
												<?php if ($complaint_primary !== '') : ?>
													<p class="nk-complaint__main"><?= html_escape($complaint_primary); ?></p>
												<?php endif; ?>
												<?php if ($show_complaint_duration || $show_complaint_symptoms || $show_complaint_description) : ?>
													<dl class="dl-complaint-facts nk-fact-list">>
														<?php if ($show_complaint_duration) : ?>
															<div class="nk-fact-row"><dt class="nk-fact-label">Lama</dt><dd class="nk-fact-value"><?= html_escape($complaint_duration); ?></dd></div>
														<?php endif; ?>
														<?php if ($show_complaint_symptoms) : ?>
															<div class="nk-fact-row"><dt class="nk-fact-label">Gejala</dt><dd class="nk-fact-value"><?= html_escape($complaint_symptoms); ?></dd></div>
														<?php endif; ?>
														<?php if ($show_complaint_description) : ?>
															<div class="nk-fact-row"><dt class="nk-fact-label">Catatan</dt><dd class="nk-fact-value"><?= html_escape($complaint_description); ?></dd></div>
														<?php endif; ?>
													</dl>
												<?php endif; ?>
											</div>
											<?php if ($show_address || $show_mode_label || $show_distance_label) : ?>
												<div class="nk-detail-panel nk-card-section">
										<div class="nk-detail-panel__title">Lokasi pasien</div>
													<div class="nk-detail-panel__body">
														<?php if ($show_address) : ?>
															<div class="nk-detail-row"><span class="nk-detail-label">Alamat</span><strong class="nk-detail-value"><?= doclinc_history_safe_text($x->location); ?></strong></div>
														<?php endif; ?>
														<?php if ($show_mode_label) : ?>
															<div class="nk-detail-row"><span class="nk-detail-label">Mode</span><strong class="nk-detail-value"><?= html_escape($mode_label); ?></strong></div>
														<?php endif; ?>
														<?php if ($show_distance_label) : ?>
															<div class="nk-detail-row"><span class="nk-detail-label">Rute</span><strong class="nk-detail-value"><span class="distance"><?= html_escape($distance_label); ?></span><span class="duration dl-route-duration"></span></strong></div>
														<?php endif; ?>
													</div>
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
														<button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#fotoModal_<?= html_escape((int) $x->user_id); ?>">Foto</button>

											<?php endif; ?>

											<?php if (!empty($x->video)) : ?>
														<button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#videoModal_<?= html_escape((int) $x->user_id); ?>">Video</button>
											<?php endif; ?>
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
													Terima
												</button>
												<div class="dl-nakes-secondary-actions">
													<button type="button" class="btn btn-outline-success shadow-sm rounded-pill lihat-map" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMapTujuan" aria-controls="offcanvasMapTujuan" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
														Lokasi
													</button>
													<button type="button" class="btn btn-outline-danger shadow-sm rounded-pill cancel-nakes-request" data-request-id="<?= html_escape((int) $x->request_id); ?>">
														Batalkan
													</button>
												</div>
											</div>
										</div>
									</div>
								</div>
								<!-- sampai sini -->
