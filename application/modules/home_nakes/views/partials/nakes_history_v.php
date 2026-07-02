<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$CI = &get_instance();
$CI->load->library('encryption');

$history_is_weak_value = static function ($value) {
	$value = trim(strip_tags((string) $value));
	if ($value === '') {
		return true;
	}
	return in_array(strtolower($value), array('n/a', 'na', '-', 'belum ditentukan', 'menghitung...', 'menghitung'), true);
};

$history_short_text = static function ($value, $limit = 120) {
	$value = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)));
	if ($value === '') {
		return '';
	}
	if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $limit) {
		return mb_substr($value, 0, max(0, $limit - 3), 'UTF-8') . '...';
	}
	if (!function_exists('mb_strlen') && strlen($value) > $limit) {
		return substr($value, 0, max(0, $limit - 3)) . '...';
	}
	return $value;
};

$history_complaint_summary = static function ($keluhan) use ($history_short_text, $history_is_weak_value) {
	$complaint_text = trim(str_replace(array("\r\n", "\r"), "\n", strip_tags((string) $keluhan)));
	$complaint_lines = preg_split('/\n+/', $complaint_text);
	$fields = array();
	foreach ($complaint_lines as $line) {
		$line = trim(str_replace(array('**', '__'), '', $line));
		if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
			$fields[strtolower(trim($matches[1]))] = trim($matches[2]);
		}
	}

	$primary = isset($fields['keluhan utama']) ? $fields['keluhan utama'] : '';
	if ($primary === '') {
		$primary = $history_short_text(str_replace(array('**', '__'), '', $complaint_text), 120);
	}

	return array(
		'primary' => $primary !== '' ? $primary : 'Keluhan belum diisi.',
		'duration' => isset($fields['lama keluhan']) && !$history_is_weak_value($fields['lama keluhan']) ? $fields['lama keluhan'] : '',
		'symptoms' => isset($fields['gejala tambahan']) && !$history_is_weak_value($fields['gejala tambahan']) ? $fields['gejala tambahan'] : '',
	);
};

$history_patient_photo = static function ($row) {
	$photo_keys = array('foto', 'photo', 'profile_photo', 'profile_image', 'patient_photo', 'avatar');
	foreach ($photo_keys as $key) {
		if (isset($row->{$key}) && trim((string) $row->{$key}) !== '') {
			return trim((string) $row->{$key});
		}
	}
	return '';
};

$history_initials = static function ($name) {
	$name = trim((string) $name);
	if ($name === '') {
		return '';
	}
	$parts = preg_split('/\s+/', $name);
	$initials = '';
	foreach ($parts as $part) {
		if ($part === '') {
			continue;
		}
		$initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
		if ((function_exists('mb_strlen') ? mb_strlen($initials, 'UTF-8') : strlen($initials)) >= 2) {
			break;
		}
	}
	return strtoupper($initials);
};
?>
				<div id="riwayat_konsul" class="content animate__animated animate__fadeInUp animate__faster">
					<div class="dl-history-page-head">
						<div>
							<span>Operasional konsultasi</span>
							<h2>Riwayat Konsultasi</h2>
						</div>
						<div class="dl-history-counts">
							<strong><?= html_escape((string) $nakes_active_count); ?></strong>
							<span>aktif</span>
						</div>
					</div>
					<ul class="nav nav-tabs nav-justified mb-3 dl-tabs dl-history-tabs" id="myTab" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" id="proses-tab" data-bs-toggle="tab" data-bs-target="#proses-tab-pane" type="button" role="tab" aria-controls="proses-tab-pane" aria-selected="true">Saat ini</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="selesai-tab" data-bs-toggle="tab" data-bs-target="#selesai-tab-pane" type="button" role="tab" aria-controls="selesai-tab-pane" aria-selected="false">Riwayat</button>
						</li>
					</ul>
					<div class="tab-content" id="myTabContent">
						<div class="tab-pane fade show active" id="proses-tab-pane" role="tabpanel" aria-labelledby="proses-tab" tabindex="0">
							<div class="dl-history-stack">
								<?php if ($data_request_accept->num_rows() < 1) : ?>
									<?php
									$this->load->view('partials/nakes_empty_state_v', array(
										'empty_title' => 'Tidak ada konsultasi aktif',
										'empty_message' => 'Konsultasi yang sudah diterima akan tampil di sini.',
										'empty_class' => 'dl-soft-empty dl-history-empty',
									));
									?>
								<?php endif; ?>
								<?php
								$i = 1;
								foreach ($data_request_accept->result() as $x) {
									$keluhan = $CI->encryption->decrypt(base64_decode($x->request_description));
									$riwayat = $CI->encryption->decrypt(base64_decode($x->riwayat));
									$queue_code = doclinc_request_queue_code($x);
									$visit_status = isset($x->visit_status) ? doclinc_normalize_visit_status($x->visit_status) : '';
									$visit_status = $visit_status !== '' ? $visit_status : 'not_started';
									$visit_status_label = doclinc_visit_status_label($visit_status);
									$mode_label = doclinc_consultation_mode_label(isset($x->consultation_mode) ? $x->consultation_mode : '');
									$complaint = $history_complaint_summary($keluhan);
									$patient_photo = $history_patient_photo($x);
									$area_label = !empty($x->assigned_puskesmas_name) ? $x->assigned_puskesmas_name : '';
									$address_label = !$history_is_weak_value(isset($x->location) ? $x->location : '') ? $x->location : '';
									$show_mode = !$history_is_weak_value($mode_label);
									$show_visit_status = !$history_is_weak_value($visit_status_label);
									$visit_next_status = array(
										'not_started' => 'en_route',
										'en_route' => 'arrived',
										'arrived' => 'in_service',
										'in_service' => 'completed',
										'completed' => '',
									);
								?>
									<div class="card shadow dl-history-card dl-history-card-active" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-request-id="<?= html_escape((int) $x->request_id); ?>" data-patient-lat="<?= html_escape($x->lattitude); ?>" data-patient-lng="<?= html_escape($x->longitude); ?>">
										<div class="dl-history-card-head">
											<?php
											$this->load->view('partials/nakes_avatar_v', array(
												'avatar_name' => $x->nama,
												'avatar_photo' => $patient_photo,
												'avatar_alt' => 'Foto pasien',
												'avatar_class' => 'nk-avatar--md nk-avatar--patient',
											));
											?>
											<div class="dl-history-title-wrap">
												<strong><?= html_escape(strtoupper((string) $x->nama)); ?></strong>
												<span>No. Antrian <?= html_escape($queue_code); ?><?= $area_label !== '' ? ' · ' . html_escape($area_label) : ''; ?></span>
											</div>
											<span class="dl-status-pill dl-status-pill-active">Aktif</span>
										</div>
										<div class="card-body">
											<div class="dl-history-summary">
												<span>Keluhan utama</span>
												<p><?= html_escape($complaint['primary']); ?></p>
												<?php if ($complaint['duration'] !== '' || $complaint['symptoms'] !== '') : ?>
													<div class="dl-complaint-facts">
														<?php if ($complaint['duration'] !== '') : ?>
															<div><span>Lama</span><strong><?= html_escape($complaint['duration']); ?></strong></div>
														<?php endif; ?>
														<?php if ($complaint['symptoms'] !== '') : ?>
															<div><span>Gejala</span><strong><?= html_escape($complaint['symptoms']); ?></strong></div>
														<?php endif; ?>
													</div>
												<?php endif; ?>
											</div>
											<input type="text" class="visit-patient-lat d-none" value="<?= html_escape($x->lattitude); ?>">
											<input type="text" class="visit-patient-lng d-none" value="<?= html_escape($x->longitude); ?>">
											<div class="dl-history-meta-rows">
												<?php if ($show_mode || $show_visit_status) : ?>
													<div><span>Status</span><strong><?= html_escape(trim(($show_mode ? $mode_label : '') . ($show_mode && $show_visit_status ? ' · ' : '') . ($show_visit_status ? $visit_status_label : ''))); ?></strong></div>
												<?php endif; ?>
												<?php if ($address_label !== '') : ?>
													<div><span>Alamat</span><strong><?= doclinc_history_safe_text($address_label); ?></strong></div>
												<?php endif; ?>
												<div><span>Rute</span><strong><span class="visit-route-distance dl-route-soft" data-route-distance="<?= html_escape((int) $x->request_id); ?>">Menghitung...</span><span class="visit-route-eta dl-route-soft dl-route-duration" data-route-eta="<?= html_escape((int) $x->request_id); ?>">Menghitung...</span></strong></div>
											</div>
											<div class="visit-route-provider-note mt-2 d-none" data-route-provider-note="<?= html_escape((int) $x->request_id); ?>"></div>
											<div class="alert alert-success py-2 px-3 mt-2 mb-0 d-none" data-arrival-notice="<?= html_escape((int) $x->request_id); ?>">
												<div class="small fw-bold" data-arrival-message="<?= html_escape((int) $x->request_id); ?>"></div>
												<button type="button" class="btn btn-success btn-sm rounded-pill mt-2 visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="arrived">
													Konfirmasi tiba di lokasi
												</button>
											</div>
											<div class="visit-workflow-control dl-visit-workflow" data-visit-workflow="<?= html_escape((int) $x->request_id); ?>" data-current-status="<?= html_escape($visit_status); ?>">
												<div class="dl-visit-workflow-label">Status kunjungan: <span class="visit-workflow-label"><?= html_escape($visit_status_label); ?></span></div>
												<div class="dl-visit-workflow-actions">
													<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="en_route" <?= $visit_next_status[$visit_status] === 'en_route' ? '' : 'disabled'; ?>>Mulai Perjalanan</button>
													<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="arrived" <?= $visit_next_status[$visit_status] === 'arrived' ? '' : 'disabled'; ?>>Tiba di Lokasi</button>
													<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="in_service" <?= $visit_next_status[$visit_status] === 'in_service' ? '' : 'disabled'; ?>>Mulai Penanganan</button>
													<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="completed" <?= $visit_next_status[$visit_status] === 'completed' ? '' : 'disabled'; ?>>Kunjungan Selesai</button>
												</div>
												<div class="small mt-2 visit-workflow-message" data-visit-workflow-message="<?= html_escape((int) $x->request_id); ?>"></div>
											</div>
										</div>
										<div class="card-footer dl-history-actions">
											<a href="<?= html_escape(base_url('konsultasi_nakes/konsultasi/' . (int) $x->request_id) . '?kriteria=1'); ?>" class="btn btn-success shadow-sm rounded-pill dl-history-primary-action">
												<i class="fas fa-notes-medical me-2"></i> Lanjutkan Tugas
											</a>
											<div class="dl-history-secondary-actions">
												<a href="<?= html_escape(base_url('chat?request_id=' . (int) $x->request_id)); ?>" class="btn btn-outline-success shadow-sm rounded-pill">
													<i class="fas fa-comments me-2"></i> Chat
												</a>
												<button type="button" class="btn btn-outline-success shadow-sm rounded-pill lihat-map" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMapTujuan" aria-controls="offcanvasMapTujuan" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
													<i class="fas fa-map-marker-alt me-2"></i> Lokasi
												</button>
											</div>
											<div class="dl-history-secondary-actions">
												<button type="button" class="btn btn-outline-primary shadow-sm rounded-pill start-nakes-visit-tracking" data-request-id="<?= html_escape((int) $x->request_id); ?>">
													<i class="fas fa-location-arrow me-2"></i> Aktifkan Visit
												</button>
												<button type="button" class="btn btn-outline-danger shadow-sm rounded-pill cancel-nakes-request" data-request-id="<?= html_escape((int) $x->request_id); ?>">
													<i class="fas fa-times-circle me-2"></i> Batalkan
												</button>
											</div>
											<span class="small text-muted w-100 visit-tracking-status" data-tracking-status="<?= html_escape((int) $x->request_id); ?>"></span>
										</div>
									</div>
								<?php
									$i++;
								}
								?>
								<input type="hidden" id="jumlah_accepted" value="<?php echo $i - 1; ?>">
							</div>
						</div>
						<div class="tab-pane fade" id="selesai-tab-pane" role="tabpanel" aria-labelledby="selesai-tab" tabindex="0">
							<div class="dl-history-stack">
								<?php if ($data_request_completed->num_rows() < 1) : ?>
									<?php
									$this->load->view('partials/nakes_empty_state_v', array(
										'empty_title' => 'Belum ada riwayat selesai',
										'empty_message' => 'Konsultasi yang selesai akan tersimpan di sini.',
										'empty_image' => base_url('assets/images/not found.svg'),
										'empty_class' => 'dl-soft-empty dl-history-empty text-center',
									));
									?>
								<?php endif; ?>
								<?php
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
									$diagnosa = !empty($x->diagnosa) ? $x->diagnosa : (!empty($x->diagnosis) ? $x->diagnosis : '');
									$saran = !empty($x->saran) ? $x->saran : (!empty($x->recommendations) ? $x->recommendations : '');
									$treatment = !empty($x->treatment) ? $x->treatment : '';
									$completed_source = !$history_is_weak_value($diagnosa) ? $diagnosa : (!$history_is_weak_value($treatment) ? $treatment : (!$history_is_weak_value($saran) ? $saran : ''));
									$completed_preview = $history_short_text($completed_source, 130);
									$puskesmas = !empty($x->assigned_puskesmas_name) ? $x->assigned_puskesmas_name : '';
									$tanggal_selesai = !empty($x->created_at) ? date('d M Y H:i', strtotime($x->created_at)) : '';
									$queue_code = doclinc_request_queue_code($x);
									$mode_label = doclinc_consultation_mode_label(isset($x->consultation_mode) ? $x->consultation_mode : '');
									$handling_nakes_name = doclinc_request_handling_nakes_name($x);
									$patient_photo = $history_patient_photo($x);
									$complaint = $history_complaint_summary($keluhan);
								?>
									<div class="card shadow dl-history-card dl-history-card-completed" data-request-id="<?= (int) $x->request_id; ?>">
										<div class="dl-history-card-head">
											<?php
											$this->load->view('partials/nakes_avatar_v', array(
												'avatar_name' => $x->nama,
												'avatar_photo' => $patient_photo,
												'avatar_alt' => 'Foto pasien',
												'avatar_class' => 'nk-avatar--md nk-avatar--patient nk-avatar--muted',
											));
											?>
											<div class="dl-history-title-wrap">
												<strong><?= html_escape(strtoupper((string) $x->nama)); ?></strong>
												<span>No. Antrian <?= html_escape($queue_code); ?><?= $tanggal_selesai !== '' ? ' · ' . html_escape($tanggal_selesai) : ''; ?></span>
											</div>
											<span class="dl-status-pill dl-status-pill-done">Selesai</span>
										</div>
										<div class="card-body">
											<div class="dl-history-summary dl-history-summary-muted">
												<span>Keluhan utama</span>
												<p><?= html_escape($complaint['primary']); ?></p>
											</div>
											<div class="dl-history-meta-rows">
												<?php if (!$history_is_weak_value($mode_label) || $handling_nakes_name !== '') : ?>
													<div><span>Info</span><strong><?= html_escape(trim((!$history_is_weak_value($mode_label) ? $mode_label : '') . (!$history_is_weak_value($mode_label) && $handling_nakes_name !== '' ? ' · ' : '') . ($handling_nakes_name !== '' ? $handling_nakes_name : ''))); ?></strong></div>
												<?php endif; ?>
												<?php if ($puskesmas !== '') : ?>
													<div><span>Area</span><strong><?= doclinc_history_safe_text($puskesmas); ?></strong></div>
												<?php endif; ?>
												<?php if ($completed_preview !== '') : ?>
													<div><span>Hasil</span><strong><?= html_escape($completed_preview); ?></strong></div>
												<?php endif; ?>
											</div>
										</div>
										<div class="card-footer dl-history-actions dl-history-actions-inline">
											<a href="<?= html_escape(base_url('chat?request_id=' . (int) $x->request_id)); ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
												<i class="fas fa-comments me-1"></i> Riwayat Chat
											</a>
										</div>
									</div>
								<?php } ?>
							</div>
						</div>
					</div>
				</div>
