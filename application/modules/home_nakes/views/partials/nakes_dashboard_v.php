<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="beranda" class="content active animate__animated animate__fadeInUp animate__faster">
					<div class="dl-dashboard-stack">
						<div class="dl-nakes-card dl-device-card">
							<div class="dl-device-row">
								<span class="dl-status-dot"></span>
								<div class="min-w-0">
									<div class="dl-card-kicker">Lokasi perangkat aktif</div>
									<p class="dl-muted-copy mb-0">Lokasi Anda digunakan untuk menentukan penugasan terdekat.</p>
								</div>
							</div>
						</div>

						<div class="dl-dashboard-stats">
							<a href="#req_konsul" class="dl-nakes-card dl-dashboard-stat" onclick="showContent('req_konsul')">
								<span>Menunggu</span>
								<strong><?= html_escape(str_pad((string) $nakes_pending_count, 2, '0', STR_PAD_LEFT)); ?></strong>
							</a>
							<a href="#riwayat_konsul" class="dl-nakes-card dl-dashboard-stat" onclick="showContent('riwayat_konsul')">
								<span>Aktif</span>
								<strong><?= html_escape(str_pad((string) $nakes_active_count, 2, '0', STR_PAD_LEFT)); ?></strong>
							</a>
							<a href="#riwayat_konsul" class="dl-nakes-card dl-dashboard-stat" onclick="showContent('riwayat_konsul')">
								<span>Selesai</span>
								<strong><?= html_escape(str_pad((string) $nakes_completed_count, 2, '0', STR_PAD_LEFT)); ?></strong>
							</a>
						</div>

						<div class="dl-section-header d-flex justify-content-between">
							<h2 class="dl-section-title">Tugas Sedang Berjalan</h2>
							<a href="#riwayat_konsul" class="dl-nakes-link" onclick="showContent('riwayat_konsul')">Lihat</a>
						</div>
						<div class="dl-nakes-card dl-dashboard-section">
							<?php if ($nakes_primary_active) :
								$active_queue_code = doclinc_request_queue_code($nakes_primary_active);
								$active_mode_label = doclinc_consultation_mode_label(isset($nakes_primary_active->consultation_mode) ? $nakes_primary_active->consultation_mode : '');
							?>
								<div class="dl-task-row">
									<div class="dl-task-avatar"><i class="fas fa-user-check"></i></div>
									<div class="flex-grow-1 min-w-0">
										<p class="dl-task-title"><?= html_escape(strtoupper((string) $nakes_primary_active->nama)); ?></p>
										<div class="dl-task-meta">No. Antrian: <?= html_escape($active_queue_code); ?> · <?= html_escape($active_mode_label); ?></div>
									</div>
								</div>
								<a href="<?= html_escape(base_url('konsultasi_nakes/konsultasi/' . (int) $nakes_primary_active->request_id) . '?kriteria=1'); ?>" class="btn btn-success rounded-pill w-100 mt-3 fw-bold">
									Lanjutkan Tugas
								</a>
							<?php else : ?>
								<div class="dl-soft-empty">
									<p class="dl-task-title mb-1">Tidak ada tugas aktif</p>
									<p class="dl-muted-copy mb-0">Permintaan baru akan muncul di sini atau di menu Permintaan.</p>
								</div>
							<?php endif; ?>
						</div>

						<div class="dl-section-header d-flex justify-content-between">
							<h2 class="dl-section-title">Permintaan Terbaru</h2>
							<a href="#req_konsul" class="dl-nakes-link" onclick="showContent('req_konsul')">Semua</a>
						</div>
						<div class="dl-nakes-card dl-dashboard-section">
							<?php if (empty($nakes_latest_pending_rows)) : ?>
								<div class="dl-soft-empty">
									<p class="dl-task-title mb-1">Belum ada permintaan baru</p>
									<p class="dl-muted-copy mb-0">Daftar permintaan akan diperbarui saat ada pasien masuk.</p>
								</div>
							<?php else :
								$CI = &get_instance();
								$CI->load->library('encryption');
								foreach ($nakes_latest_pending_rows as $latest_request) :
									$latest_keluhan = $CI->encryption->decrypt(base64_decode($latest_request->request_description));
									$latest_queue_code = doclinc_request_queue_code($latest_request);
							?>
									<div class="dl-latest-row <?= $latest_request !== end($nakes_latest_pending_rows) ? 'mb-3 pb-3 border-bottom' : ''; ?>">
										<div class="dl-latest-badge"><?= html_escape(doclinc_nakes_queue_badge($latest_request)); ?></div>
										<div class="flex-grow-1 min-w-0">
											<p class="dl-latest-name"><?= html_escape(strtoupper((string) $latest_request->nama)); ?></p>
											<div class="dl-latest-meta">No. Antrian: <?= html_escape($latest_queue_code); ?></div>
											<div class="dl-muted-copy mt-1"><?= html_escape(doclinc_nakes_short_text($latest_keluhan)); ?></div>
										</div>
										<a href="#req_konsul" class="btn btn-outline-success btn-sm rounded-pill fw-bold" onclick="showContent('req_konsul')">Lihat</a>
									</div>
							<?php endforeach;
							endif; ?>
						</div>

						<div class="dl-nakes-card dl-info-card">
							<div class="dl-device-row">
								<div class="dl-task-avatar"><i class="fas fa-location-arrow"></i></div>
								<div>
									<div class="dl-card-kicker">Informasi Lapangan</div>
									<p class="dl-muted-copy mb-0">Pastikan lokasi perangkat tetap aktif agar estimasi jarak, rute, dan penugasan kunjungan tetap akurat.</p>
								</div>
							</div>
						</div>
					</div>
				</div>
