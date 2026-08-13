<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
				<div id="operasional" class="content <?= (isset($nakes_initial_section) && $nakes_initial_section === 'operasional') ? 'active' : ''; ?>">
					<div class="dl-dashboard-stack dl-puskesmas-operations">
						<div class="dl-nakes-card dl-dashboard-section dl-operations-intro">
							<div>
								<span class="dl-operations-kicker">Puskesmas</span>
								<h2>Operasional layanan</h2>
								<p>Pantau layanan dan ketersediaan Nakes.</p>
							</div>
							<span class="dl-operations-readonly"><i class="fas fa-eye" aria-hidden="true"></i> Hanya pantau</span>
						</div>
						<form id="doclincOperationsFilters" class="dl-nakes-card dl-dashboard-section dl-operations-filters">
							<label><span>Cari pasien</span><input type="search" name="q" maxlength="100" autocomplete="off" placeholder="Nama atau NIK"></label>
							<label><span>Dari tanggal</span><input type="date" name="date_from" value="<?= html_escape(date('Y-m-d')); ?>"></label>
							<label><span>Sampai tanggal</span><input type="date" name="date_to" value="<?= html_escape(date('Y-m-d')); ?>"></label>
							<button type="submit" class="btn btn-success">Tampilkan</button>
						</form>
						<div id="doclincPuskesmasOperations" class="dl-operations-runtime" aria-live="polite" aria-busy="true">
							<div class="dl-nakes-card dl-dashboard-section dl-operations-loading">
								<div class="spinner-border spinner-border-sm text-success" role="status"><span class="visually-hidden">Memuat</span></div>
								<span>Memuat ringkasan operasional...</span>
							</div>
						</div>
						<div id="doclincOperationsMedicalRecord" class="dl-nakes-card dl-dashboard-section dl-operations-record" hidden aria-live="polite"></div>
						<noscript><div class="alert alert-warning">Aktifkan JavaScript untuk melihat ringkasan operasional.</div></noscript>
					</div>
				</div>
