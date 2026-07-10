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

$staff_assignment_ready = isset($staff_assignment_ready) ? (bool) $staff_assignment_ready : false;
$puskesmas_staff_options = isset($puskesmas_staff_options) && is_array($puskesmas_staff_options) ? $puskesmas_staff_options : array();
$request_staff_assignment_map = isset($request_staff_assignment_map) && is_array($request_staff_assignment_map) ? $request_staff_assignment_map : array();
$request_staff_latest_assignment_map = isset($request_staff_latest_assignment_map) && is_array($request_staff_latest_assignment_map) ? $request_staff_latest_assignment_map : array();
$request_event_map = isset($request_event_map) && is_array($request_event_map) ? $request_event_map : array();
$can_coordinate_staff = isset($can_coordinate_staff) ? (bool) $can_coordinate_staff : false;
$staff_assignment_success = $this->session->flashdata('staff_assignment_success');
$staff_assignment_error = $this->session->flashdata('staff_assignment_error');

$history_event_label = static function ($event) {
	$event_type = isset($event->event_type) ? (string) $event->event_type : '';
	if ($event_type === 'pic_assigned') {
		return 'PIC personel ditetapkan';
	}
	if ($event_type === 'pic_changed') {
		return 'PIC personel diganti';
	}
	if ($event_type === 'pic_cleared') {
		return 'PIC personel dibatalkan';
	}
	$event_labels = array(
		'request_created' => 'Permintaan dibuat',
		'request_accepted' => 'Permintaan diterima',
		'request_cancelled' => 'Permintaan dibatalkan/ditolak',
		'visit_started' => 'Perjalanan dimulai',
		'visit_arrived' => 'Tiba di lokasi',
		'visit_in_service' => 'Pelayanan dimulai',
		'visit_completed' => 'Kunjungan selesai',
		'request_completed' => 'Permintaan selesai',
	);
	if (isset($event_labels[$event_type])) {
		return $event_labels[$event_type];
	}
	return '';
};

$history_event_time = static function ($event) {
	if (empty($event->created_at)) {
		return '';
	}
	$timestamp = strtotime($event->created_at);
	return $timestamp ? date('d M H:i', $timestamp) : '';
};
?>
				<div id="riwayat_konsul" class="content">
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
					<?php if ($can_coordinate_staff && $staff_assignment_success) : ?>
						<div class="alert alert-success nk-pic-alert" role="alert"><?= html_escape($staff_assignment_success); ?></div>
					<?php elseif ($can_coordinate_staff && $staff_assignment_error) : ?>
						<div class="alert alert-warning nk-pic-alert" role="alert"><?= html_escape($staff_assignment_error); ?></div>
					<?php endif; ?>
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
									$pic_assignment = isset($request_staff_assignment_map[(int) $x->request_id]) ? $request_staff_assignment_map[(int) $x->request_id] : null;
									$request_events = isset($request_event_map[(int) $x->request_id]) ? array_slice($request_event_map[(int) $x->request_id], 0, 5) : array();
									$visit_next_status = array(
										'not_started' => 'en_route',
										'en_route' => 'arrived',
										'arrived' => 'in_service',
										'in_service' => 'completed',
										'completed' => '',
									);
								?>
									<div class="card shadow dl-history-card dl-history-card-active" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-id="<?= html_escape((int) $x->request_id); ?>" data-visit-request-id="<?= html_escape((int) $x->request_id); ?>" data-patient-lat="<?= html_escape($x->lattitude); ?>" data-patient-lng="<?= html_escape($x->longitude); ?>">
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
										<div class="card-body nk-card-body nk-history-card__body">
											<div class="dl-history-summary nk-complaint nk-card-section">
												<span class="nk-complaint__title">Keluhan utama</span>
												<p class="nk-complaint__main"><?= html_escape($complaint['primary']); ?></p>
												<?php if ($complaint['duration'] !== '' || $complaint['symptoms'] !== '') : ?>
													<div class="dl-complaint-facts nk-fact-list">
														<?php if ($complaint['duration'] !== '') : ?>
															<div class="nk-fact-row"><span class="nk-fact-label">Lama</span><strong class="nk-fact-value"><?= html_escape($complaint['duration']); ?></strong></div>
														<?php endif; ?>
														<?php if ($complaint['symptoms'] !== '') : ?>
															<div class="nk-fact-row"><span class="nk-fact-label">Gejala</span><strong class="nk-fact-value"><?= html_escape($complaint['symptoms']); ?></strong></div>
														<?php endif; ?>
													</div>
												<?php endif; ?>
											</div>
											<input type="text" class="visit-patient-lat d-none" value="<?= html_escape($x->lattitude); ?>">
											<input type="text" class="visit-patient-lng d-none" value="<?= html_escape($x->longitude); ?>">
											<div class="nk-detail-panel nk-card-section">
												<div class="nk-detail-panel__title">Detail kunjungan</div>
												<div class="nk-detail-panel__body">
													<?php if ($show_mode || $show_visit_status) : ?>
														<div class="nk-detail-row"><span class="nk-detail-label">Status</span><strong class="nk-detail-value"><?= html_escape(trim(($show_mode ? $mode_label : '') . ($show_mode && $show_visit_status ? ' · ' : '') . ($show_visit_status ? $visit_status_label : ''))); ?></strong></div>
													<?php endif; ?>
													<?php if ($address_label !== '') : ?>
														<div class="nk-detail-row"><span class="nk-detail-label">Alamat</span><strong class="nk-detail-value"><?= doclinc_history_safe_text($address_label); ?></strong></div>
													<?php endif; ?>
													<div class="nk-detail-row"><span class="nk-detail-label">Rute</span><strong class="nk-detail-value"><span class="visit-route-distance dl-route-soft" data-route-distance="<?= html_escape((int) $x->request_id); ?>">Menghitung...</span><span class="visit-route-eta dl-route-soft dl-route-duration" data-route-eta="<?= html_escape((int) $x->request_id); ?>">Menghitung...</span></strong></div>
												</div>
											</div>
											<?php if ($can_coordinate_staff) : ?>
											<div class="nk-action-panel nk-action-panel--pic nk-card-section">
												<div class="nk-action-panel__title">PIC Personel</div>
												<div class="nk-action-panel__body">
													<div class="nk-pic-current">
														<span>Status PIC</span>
														<strong>
															<?php if ($pic_assignment) : ?>
																<?= html_escape($pic_assignment->staff_nama); ?><?= !empty($pic_assignment->staff_profesi) ? ' · ' . html_escape($pic_assignment->staff_profesi) : ''; ?>
															<?php else : ?>
																Belum ditentukan
															<?php endif; ?>
														</strong>
														<?php if ($pic_assignment && !empty($pic_assignment->staff_no_hp)) : ?>
															<small><?= html_escape($pic_assignment->staff_no_hp); ?></small>
														<?php endif; ?>
													</div>
													<?php if (!$staff_assignment_ready) : ?>
														<p class="nk-pic-muted">Fitur PIC personel belum tersedia.</p>
													<?php elseif (empty($puskesmas_staff_options)) : ?>
														<p class="nk-pic-muted">Belum ada personel aktif untuk ditetapkan.</p>
													<?php else : ?>
														<form method="post" action="<?= html_escape(base_url('home_nakes/assign_staff')); ?>" class="nk-pic-form">
															<input type="hidden" name="request_id" value="<?= html_escape((int) $x->request_id); ?>">
															<select name="staff_id" class="form-select form-select-sm" required>
																<option value="">Pilih personel</option>
																<?php foreach ($puskesmas_staff_options as $staff_option) : ?>
																	<option value="<?= html_escape((int) $staff_option->staff_id); ?>" <?= $pic_assignment && (int) $pic_assignment->staff_id === (int) $staff_option->staff_id ? 'selected' : ''; ?>>
																		<?= html_escape($staff_option->nama); ?><?= !empty($staff_option->profesi) ? ' - ' . html_escape($staff_option->profesi) : ''; ?>
																	</option>
																<?php endforeach; ?>
															</select>
															<input type="text" name="note" class="form-control form-control-sm" maxlength="255" placeholder="Catatan opsional">
															<div class="nk-pic-actions">
																<button type="submit" class="btn btn-outline-success btn-sm rounded-pill"><?= $pic_assignment ? 'Ganti PIC' : 'Tetapkan PIC'; ?></button>
															</div>
														</form>
														<?php if ($pic_assignment) : ?>
															<form method="post" action="<?= html_escape(base_url('home_nakes/clear_staff_assignment')); ?>" class="nk-pic-clear-form">
																<input type="hidden" name="request_id" value="<?= html_escape((int) $x->request_id); ?>">
																<button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill">Batalkan PIC</button>
															</form>
														<?php endif; ?>
													<?php endif; ?>
												</div>
											</div>
											<?php if (!empty($request_events)) : ?>
												<div class="nk-action-panel nk-action-panel--timeline nk-card-section">
													<div class="nk-action-panel__title">Timeline Operasional</div>
													<div class="nk-timeline-list">
														<?php foreach ($request_events as $event) :
															$event_label = $history_event_label($event);
															$event_time = $history_event_time($event);
															if ($event_label === '') {
																continue;
															}
														?>
															<div class="nk-timeline-item">
																<strong><?= html_escape($event_label); ?></strong>
																<?php if ($event_time !== '') : ?><span><?= html_escape($event_time); ?></span><?php endif; ?>
															</div>
														<?php endforeach; ?>
													</div>
												</div>
											<?php endif; ?>
											<?php endif; ?>
											<div class="visit-route-provider-note mt-2 d-none" data-route-provider-note="<?= html_escape((int) $x->request_id); ?>"></div>
											<div class="alert alert-success py-2 px-3 mt-2 mb-0 d-none" data-arrival-notice="<?= html_escape((int) $x->request_id); ?>">
												<div class="small fw-bold" data-arrival-message="<?= html_escape((int) $x->request_id); ?>"></div>
												<button type="button" class="btn btn-success btn-sm rounded-pill mt-2 visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="arrived">
													Konfirmasi tiba di lokasi
												</button>
											</div>
											<div class="visit-workflow-control dl-visit-workflow nk-visit-status nk-visit-status-block nk-action-panel nk-action-panel--visit-status nk-card-section" data-visit-workflow="<?= html_escape((int) $x->request_id); ?>" data-current-status="<?= html_escape($visit_status); ?>">
												<div class="nk-action-panel__title">Status kunjungan</div>
												<div class="nk-action-panel__body">
													<div class="nk-detail-row dl-visit-workflow-label"><span class="nk-detail-label">Status</span><strong class="nk-detail-value visit-workflow-label"><?= html_escape($visit_status_label); ?></strong></div>
													<div class="dl-visit-workflow-actions nk-visit-actions">
														<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="en_route" <?= $visit_next_status[$visit_status] === 'en_route' ? '' : 'disabled'; ?>>Mulai Perjalanan</button>
														<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="arrived" <?= $visit_next_status[$visit_status] === 'arrived' ? '' : 'disabled'; ?>>Tiba di Lokasi</button>
														<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="in_service" <?= $visit_next_status[$visit_status] === 'in_service' ? '' : 'disabled'; ?>>Mulai Penanganan</button>
														<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="completed" <?= $visit_next_status[$visit_status] === 'completed' ? '' : 'disabled'; ?>>Kunjungan Selesai</button>
													</div>
												</div>
												<div class="small mt-2 visit-workflow-message" data-visit-workflow-message="<?= html_escape((int) $x->request_id); ?>"></div>
											</div>
										</div>
										<div class="card-footer dl-history-actions">
											<a href="<?= html_escape(base_url('konsultasi_nakes/konsultasi/' . (int) $x->request_id)); ?>" class="btn btn-success shadow-sm rounded-pill dl-history-primary-action">
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
									$pic_assignment = isset($request_staff_assignment_map[(int) $x->request_id]) ? $request_staff_assignment_map[(int) $x->request_id] : (isset($request_staff_latest_assignment_map[(int) $x->request_id]) ? $request_staff_latest_assignment_map[(int) $x->request_id] : null);
									$request_events = isset($request_event_map[(int) $x->request_id]) ? array_slice($request_event_map[(int) $x->request_id], 0, 5) : array();
								?>
									<div class="card shadow dl-history-card dl-history-card-completed" data-request-id="<?= (int) $x->request_id; ?>" data-visit-id="<?= (int) $x->request_id; ?>">
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
											<div class="dl-history-summary dl-history-summary-muted nk-complaint">
												<span class="nk-complaint__title">Keluhan utama</span>
												<p class="nk-complaint__main"><?= html_escape($complaint['primary']); ?></p>
											</div>
											<div class="dl-history-meta-rows nk-info-list">
												<?php if (!$history_is_weak_value($mode_label) || $handling_nakes_name !== '') : ?>
													<div class="nk-info-row"><span class="nk-info-label">Info</span><strong class="nk-info-value"><?= html_escape(trim((!$history_is_weak_value($mode_label) ? $mode_label : '') . (!$history_is_weak_value($mode_label) && $handling_nakes_name !== '' ? ' · ' : '') . ($handling_nakes_name !== '' ? $handling_nakes_name : ''))); ?></strong></div>
												<?php endif; ?>
												<?php if ($puskesmas !== '') : ?>
													<div class="nk-info-row"><span class="nk-info-label">Puskesmas</span><strong class="nk-info-value"><?= doclinc_history_safe_text($puskesmas); ?></strong></div>
												<?php endif; ?>
												<?php if ($can_coordinate_staff && $pic_assignment) : ?>
													<div class="nk-info-row"><span class="nk-info-label">PIC Personel</span><strong class="nk-info-value"><?= html_escape($pic_assignment->staff_nama); ?><?= !empty($pic_assignment->staff_profesi) ? ' · ' . html_escape($pic_assignment->staff_profesi) : ''; ?><?= !empty($pic_assignment->staff_no_hp) ? ' · ' . html_escape($pic_assignment->staff_no_hp) : ''; ?></strong></div>
												<?php endif; ?>
												<?php if ($completed_preview !== '') : ?>
													<div class="nk-info-row"><span class="nk-info-label">Hasil</span><strong class="nk-info-value"><?= html_escape($completed_preview); ?></strong></div>
												<?php endif; ?>
											</div>
											<?php if ($can_coordinate_staff && !empty($request_events)) : ?>
												<div class="nk-action-panel nk-action-panel--timeline nk-card-section">
													<div class="nk-action-panel__title">Timeline Operasional</div>
													<div class="nk-timeline-list">
														<?php foreach ($request_events as $event) :
															$event_label = $history_event_label($event);
															$event_time = $history_event_time($event);
															if ($event_label === '') {
																continue;
															}
														?>
															<div class="nk-timeline-item">
																<strong><?= html_escape($event_label); ?></strong>
																<?php if ($event_time !== '') : ?><span><?= html_escape($event_time); ?></span><?php endif; ?>
															</div>
														<?php endforeach; ?>
													</div>
												</div>
											<?php endif; ?>
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
