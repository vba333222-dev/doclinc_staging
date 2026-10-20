<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="req_konsul" class="content">
					<div class="cq-page cq-page--requests">
						<header class="cq-page-head">
							<div class="cq-page-heading">
								<h2 class="cq-page-title">Permintaan konsultasi</h2>
								<p class="cq-page-sub">Antrean yang belum diterima Puskesmas.</p>
							</div>
							<div class="cq-page-count" aria-label="<?= (int) $nakes_pending_count; ?> permintaan menunggu">
								<strong><?= html_escape(str_pad((string) (int) $nakes_pending_count, 2, '0', STR_PAD_LEFT)); ?></strong>
								<span>menunggu</span>
							</div>
						</header>
						<div class="dl-nakes-request-list cq-request-list">
						<?php
						$cq_wait_label = static function ($created_at) {
							$created_at = trim((string) $created_at);
							if ($created_at === '') {
								return 'Baru masuk';
							}
							try {
								$cq_zone = new DateTimeZone('Asia/Jakarta');
								$cq_now = new DateTime('now', $cq_zone);
								$cq_created = new DateTime($created_at, $cq_zone);
								$minutes = max(0, (int) floor(($cq_now->getTimestamp() - $cq_created->getTimestamp()) / 60));
							} catch (Exception $cq_wait_error) {
								return 'Baru masuk';
							}
							if ($minutes < 60) {
								return 'Baru masuk';
							}
							if ($minutes < 1440) {
								$hours = (int) floor($minutes / 60);
								$rest = $minutes % 60;
								return $hours . ' j' . ($rest > 0 ? ' ' . $rest . ' m' : '');
							}
							$days = (int) floor($minutes / 1440);
							$hours = (int) floor(($minutes % 1440) / 60);
							return $days . ' hari' . ($hours > 0 ? ' ' . $hours . ' j' : '');
						};
						$i = 1;
						if ($data_request_new->num_rows() < 1) {
						?>
							<div class="dl-soft-empty dl-history-empty cq-empty">
								<i class="bi bi-inbox" aria-hidden="true"></i>
								<div>
									<strong>Tidak ada permintaan menunggu</strong>
									<p>Antrean baru muncul di sini setelah warga mengirim permintaan.</p>
								</div>
							</div>
						<?php
						} else {

							$CI = &get_instance();
							$CI->load->library('encryption');
							$is_weak_request_value = static function ($value) {
								$value = trim(strip_tags((string) $value));
								if ($value === '') {
									return true;
								}
								return in_array(strtolower($value), array('n/a', 'na', '-', 'belum ditentukan', 'menghitung...', 'menghitung'), true);
							};
							foreach ($data_request_new->result() as $y => $x) {
								$keluhan = $CI->encryption->decrypt(base64_decode($x->request_description));
								$riwayat = $CI->encryption->decrypt(base64_decode($x->riwayat));
								$mode_label = doclinc_consultation_mode_label(isset($x->consultation_mode) ? $x->consultation_mode : '');
								$handling_nakes_name = doclinc_request_handling_nakes_name($x);
								$handling_nakes_label = $handling_nakes_name !== '' ? 'Ditangani oleh akun: ' . $handling_nakes_name : 'Menunggu akun koordinasi menerima konsultasi';
								$area_label = !empty($x->assigned_puskesmas_name) ? $x->assigned_puskesmas_name : 'Puskesmas Penugasan';
								// Pending requests expose patient location only; route/ETA belong to an assigned Visit.
								$distance_label = '';
								$queue_label = doclinc_request_queue_number_label($x);
								$wait_label = $cq_wait_label(isset($x->created_at) ? $x->created_at : '');
								$received_label = !empty($x->created_at) ? date('d M H.i', strtotime($x->created_at)) : 'Baru masuk';
																$complaint_text = trim(str_replace(array("\r\n", "\r"), "\n", strip_tags((string) $keluhan)));
								/* Tidak setiap baris punya label. Baris pertama sering berupa judul bagian
								   ("Anamnesa"), dan baris tanpa titik dua setelah sebuah label adalah lanjutan
								   nilai label itu - "Gejala tambahan: pusing" lalu "Tidak Nafsu Makan". */
								$complaint_lines = preg_split('/\n+/', $complaint_text);
								$complaint_fields = array();
								$complaint_lead = '';
								$complaint_last_key = null;
								foreach ($complaint_lines as $complaint_line) {
									$complaint_line = trim(str_replace(array('**', '__'), '', $complaint_line));
									if ($complaint_line === '') {
										continue;
									}
									if (preg_match('/^([^:]{1,40}):\s*(.+)$/s', $complaint_line, $matches)) {
										$complaint_key = strtolower(trim($matches[1]));
										if (strpos($complaint_key, '/') !== false) { $complaint_key = trim(substr($complaint_key, strrpos($complaint_key, '/') + 1)); }
$complaint_fields[$complaint_key] = trim($matches[2]);
										$complaint_last_key = $complaint_key;
									} elseif ($complaint_last_key !== null) {
										$complaint_fields[$complaint_last_key] .= ', ' . $complaint_line;
									} elseif ($complaint_lead === '') {
										$complaint_lead = $complaint_line;
									}
								}
$complaint_primary = isset($complaint_fields['keluhan utama']) ? $complaint_fields['keluhan utama'] : '';
								$complaint_duration = isset($complaint_fields['lama keluhan']) ? $complaint_fields['lama keluhan'] : '';
								$complaint_symptoms = isset($complaint_fields['gejala tambahan']) ? $complaint_fields['gejala tambahan'] : '';
								$complaint_description = isset($complaint_fields['deskripsi keluhan']) ? $complaint_fields['deskripsi keluhan'] : '';
								$complaint_preview = trim(preg_replace('/\s+/', ' ', str_replace(array('**', '__'), '', $complaint_text)));
																/* Baris berlabel sudah tampil sebagai fakta di bawah judul, jadi seluruh teks
								   tidak diulang sebagai satu kalimat di baris utama. */
								if ($complaint_primary === '' && empty($complaint_fields)) {
									$complaint_primary = $complaint_preview;
								}
								if ($complaint_primary === '' && $complaint_lead === '') {
									$complaint_primary = 'Keluhan belum diisi.';
								}
if (function_exists('mb_strlen') && mb_strlen($complaint_primary, 'UTF-8') > 120) {
									$complaint_primary = mb_substr($complaint_primary, 0, 117, 'UTF-8') . '…';
								} elseif (!function_exists('mb_strlen') && strlen($complaint_primary) > 120) {
									$complaint_primary = substr($complaint_primary, 0, 117) . '…';
								}
								if (preg_match('/^anamnesa\s*:?$/i', trim($complaint_lead))) { $complaint_lead = ''; }
$show_complaint_duration = !$is_weak_request_value($complaint_duration);
								$show_complaint_symptoms = !$is_weak_request_value($complaint_symptoms);
								$show_complaint_description = !$is_weak_request_value($complaint_description) && ((function_exists('mb_strlen') && mb_strlen($complaint_description, 'UTF-8') <= 80) || (!function_exists('mb_strlen') && strlen($complaint_description) <= 80));
								$show_mode_label = !$is_weak_request_value($mode_label);
								$show_distance_label = false;
								$show_address = !$is_weak_request_value($x->location);
							?>
								<?php $this->load->view('partials/puskesmas_request_row_v', get_defined_vars()); ?>

						<?php
								$i++;
							}
						}
						?>
						<input type="hidden" value="<?php echo $i - 1; ?>" id="jumlah_request">
						</div>
					</div>
				</div>
