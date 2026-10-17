<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="req_konsul" class="content">
					<?php
					$this->load->view('partials/nakes_section_header_v', array(
						'section_kicker' => 'Antrian Puskesmas',
						'section_title' => 'Permintaan Konsultasi',
						'section_meta' => (string) $nakes_pending_count . ' menunggu',
						'section_class' => 'dl-nakes-page-header',
					));
					?>
					<div class="dl-nakes-request-list">
						<?php
						$i = 1;
						if ($data_request_new->num_rows() < 1) {
						?>
							<?php
							$this->load->view('partials/nakes_empty_state_v', array(
								/* CI3 menumpuk data view ke $_ci_cached_vars, sehingga nilai yang
								   dikirim Beranda (nakes_dashboard_v) ikut terbaca di sini.
								   Sebelumnya `empty_title` tidak dikirim sama sekali, jadi judul
								   milik Beranda ("Belum ada permintaan baru") bocor ke bagian
								   ini. Sekarang setiap kunci dikirim eksplisit, termasuk
								   `empty_image` kosong supaya ilustrasi "not found" milik
								   Beranda tidak ikut terbawa. */
								'empty_title' => 'Belum ada permintaan baru',
								/* Pesan ini mengulang judul di atasnya tanpa menambah
								   arahan, jadi dikosongkan. Partikel empty_state_v
								   sudah melewatkan barisnya saat nilainya kosong. */
								'empty_message' => '',
								'empty_icon' => 'fas fa-clipboard-list',
								'empty_image' => '',
								'empty_class' => 'dl-soft-empty text-center',
							));
							?>
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
								$received_label = !empty($x->created_at) ? date('d M H:i', strtotime($x->created_at)) : 'Baru masuk';
								$complaint_text = trim(str_replace(array("\r\n", "\r"), "\n", strip_tags((string) $keluhan)));
								$complaint_lines = preg_split('/\n+/', $complaint_text);
								$complaint_fields = array();
								foreach ($complaint_lines as $complaint_line) {
									$complaint_line = trim(str_replace(array('**', '__'), '', $complaint_line));
									if (preg_match('/^([^:]+):\s*(.+)$/', $complaint_line, $matches)) {
										$complaint_key = strtolower(trim($matches[1]));
										$complaint_fields[$complaint_key] = trim($matches[2]);
									}
								}
								$complaint_primary = isset($complaint_fields['keluhan utama']) ? $complaint_fields['keluhan utama'] : '';
								$complaint_duration = isset($complaint_fields['lama keluhan']) ? $complaint_fields['lama keluhan'] : '';
								$complaint_symptoms = isset($complaint_fields['gejala tambahan']) ? $complaint_fields['gejala tambahan'] : '';
								$complaint_description = isset($complaint_fields['deskripsi keluhan']) ? $complaint_fields['deskripsi keluhan'] : '';
								$complaint_preview = trim(preg_replace('/\s+/', ' ', str_replace(array('**', '__'), '', $complaint_text)));
								if ($complaint_primary === '') {
									$complaint_primary = $complaint_preview !== '' ? $complaint_preview : 'Keluhan belum diisi.';
								}
								if (function_exists('mb_strlen') && mb_strlen($complaint_primary, 'UTF-8') > 120) {
									$complaint_primary = mb_substr($complaint_primary, 0, 117, 'UTF-8') . '...';
								} elseif (!function_exists('mb_strlen') && strlen($complaint_primary) > 120) {
									$complaint_primary = substr($complaint_primary, 0, 117) . '...';
								}
								$show_complaint_duration = !$is_weak_request_value($complaint_duration);
								$show_complaint_symptoms = !$is_weak_request_value($complaint_symptoms);
								$show_complaint_description = !$is_weak_request_value($complaint_description) && ((function_exists('mb_strlen') && mb_strlen($complaint_description, 'UTF-8') <= 80) || (!function_exists('mb_strlen') && strlen($complaint_description) <= 80));
								$show_mode_label = !$is_weak_request_value($mode_label);
								$show_distance_label = false;
								$show_address = !$is_weak_request_value($x->location);
							?>
								<?php $this->load->view('partials/nakes_full_request_card_v', get_defined_vars()); ?>

						<?php
								$i++;
							}
						}
						?>
						<input type="hidden" value="<?php echo $i - 1; ?>" id="jumlah_request">
					</div>
				</div>
