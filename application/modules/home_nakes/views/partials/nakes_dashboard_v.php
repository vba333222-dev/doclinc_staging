<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="beranda" class="content active">
					<div class="dl-dashboard-stack">
						<?php $this->load->view('partials/nakes_stat_cards_v', get_defined_vars()); ?>
						<?php
						$this->load->view('partials/nakes_section_header_v', array(
							'section_title' => 'Tugas Sedang Berjalan',
							'section_link_label' => 'Lihat',
							'section_link_target' => '#riwayat_konsul',
						));
						$this->load->view('partials/nakes_active_task_card_v', get_defined_vars());
						$this->load->view('partials/nakes_section_header_v', array(
							'section_title' => 'Permintaan Terbaru',
							'section_link_label' => 'Semua',
							'section_link_target' => '#req_konsul',
						));
						?>
						<div class="dl-nakes-card dl-dashboard-section">
							<?php if (empty($nakes_latest_pending_rows)) : ?>
								<?php
								$this->load->view('partials/nakes_empty_state_v', array(
									'empty_title' => 'Belum ada permintaan baru',
									'empty_message' => 'Daftar permintaan akan diperbarui saat ada pasien masuk.',
								));
								?>
							<?php else :
								$CI = &get_instance();
								$CI->load->library('encryption');
								$latest_total = count($nakes_latest_pending_rows);
								$latest_index = 0;
								foreach ($nakes_latest_pending_rows as $latest_request) :
									$latest_keluhan = $CI->encryption->decrypt(base64_decode($latest_request->request_description));
									$latest_queue_code = doclinc_request_queue_code($latest_request);
									$is_last_latest_request = ++$latest_index >= $latest_total;
							?>
									<?php $this->load->view('partials/nakes_compact_request_item_v', get_defined_vars()); ?>
							<?php endforeach;
							endif; ?>
						</div>
					</div>
				</div>
