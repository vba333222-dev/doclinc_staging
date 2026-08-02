<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
				<div id="operasional" class="content <?= (isset($nakes_initial_section) && $nakes_initial_section === 'operasional') ? 'active' : ''; ?>">
					<div class="dl-dashboard-stack dl-puskesmas-operations">
						<div class="dl-nakes-card dl-dashboard-section dl-operations-intro">
							<div>
								<span class="dl-operations-kicker">Command Center Puskesmas</span>
								<h2>Operasional layanan</h2>
								<p>Pantau kesiapan dan beban tugas Nakes dalam unit Anda. Panel ini tidak menampilkan identitas pasien, isi klinis, atau koordinat.</p>
							</div>
							<span class="dl-operations-readonly"><i class="fas fa-eye" aria-hidden="true"></i> Hanya pantau</span>
						</div>
						<div id="doclincPuskesmasOperations" class="dl-operations-runtime" aria-live="polite" aria-busy="true">
							<div class="dl-nakes-card dl-dashboard-section dl-operations-loading">
								<div class="spinner-border spinner-border-sm text-success" role="status"><span class="visually-hidden">Memuat</span></div>
								<span>Memuat ringkasan operasional...</span>
							</div>
						</div>
						<noscript><div class="alert alert-warning">Aktifkan JavaScript untuk melihat ringkasan operasional.</div></noscript>
					</div>
				</div>
