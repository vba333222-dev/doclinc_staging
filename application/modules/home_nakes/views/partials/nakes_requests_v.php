<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="req_konsul" class="content animate__animated animate__fadeInUp animate__faster">
					<?php
					$this->load->view('partials/nakes_section_header_v', array(
						'section_kicker' => 'Antrian konsultasi',
						'section_title' => 'Permintaan Masuk',
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
								'empty_message' => 'Belum ada permintaan konsultasi baru.',
								'empty_image' => base_url('assets/images/not found.svg'),
								'empty_class' => 'dl-empty-state text-center',
							));
							?>
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
								<?php $this->load->view('partials/nakes_full_request_card_v', get_defined_vars()); ?>

						<?php
								$i++;
							}
						}
						?>
						<input type="hidden" value="<?php echo $i - 1; ?>" id="jumlah_request">
					</div>
				</div>
