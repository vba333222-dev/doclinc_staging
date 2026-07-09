<?php
$summary = isset($operational_summary) && is_array($operational_summary) ? $operational_summary : array();
$cards = array(
	array('label' => 'Konsultasi hari ini', 'value' => (int) ($summary['today'] ?? 0), 'icon' => 'fa-calendar-day', 'tone' => 'primary'),
	array('label' => 'Menunggu / Pending', 'value' => (int) ($summary['pending'] ?? 0), 'icon' => 'fa-hourglass-half', 'tone' => 'warning'),
	array('label' => 'Diterima / Accepted', 'value' => (int) ($summary['accepted'] ?? 0), 'icon' => 'fa-check-circle', 'tone' => 'info'),
	array('label' => 'Dalam layanan', 'value' => (int) ($summary['in_progress'] ?? 0), 'icon' => 'fa-stethoscope', 'tone' => 'service'),
	array('label' => 'Selesai / Completed', 'value' => (int) ($summary['completed'] ?? 0), 'icon' => 'fa-clipboard-check', 'tone' => 'success'),
	array('label' => 'Legacy / Belum terklasifikasi', 'value' => (int) ($summary['legacy'] ?? 0), 'icon' => 'fa-exclamation-triangle', 'tone' => 'legacy'),
);
$distribution = isset($puskesmas_distribution) && is_array($puskesmas_distribution) ? $puskesmas_distribution : array();
$attention = isset($attention_requests) && is_array($attention_requests) ? $attention_requests : array();
$activity = isset($recent_activity) && is_array($recent_activity) ? $recent_activity : array();
$diagnosis_analytics = isset($diagnosis_analytics) && is_array($diagnosis_analytics) ? $diagnosis_analytics : array();
$top_diagnoses = isset($diagnosis_analytics['top_diagnoses']) && is_array($diagnosis_analytics['top_diagnoses']) ? $diagnosis_analytics['top_diagnoses'] : array();
$diagnosis_distribution = isset($diagnosis_analytics['puskesmas_distribution']) && is_array($diagnosis_analytics['puskesmas_distribution']) ? $diagnosis_analytics['puskesmas_distribution'] : array();
$diagnosis_period_days = (int) ($diagnosis_analytics['period_days'] ?? 30);
$max_top_diagnosis = (int) ($diagnosis_analytics['max_top_total'] ?? 0);
$max_puskesmas_diagnosis = (int) ($diagnosis_analytics['max_puskesmas_total'] ?? 0);
$max_distribution = 0;
foreach ($distribution as $row) {
	$max_distribution = max($max_distribution, (int) ($row->total ?? 0));
}
?>

<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here doclinc-page-header">
	<div>
		<h1 class="h3 mb-1 font-weight-bold doclinc-page-title"><i class="fas fa-fw fa-tachometer-alt"></i> Dashboard Operasional</h1>
		<p class="mb-0 doclinc-page-subtitle">Ringkasan koordinasi Puskesmas dan aktivitas konsultasi Doclinc.</p>
	</div>
	<a href="<?= site_url('konsultasi_kesehatan'); ?>" class="btn btn-primary btn-sm shadow-sm">
		<i class="fas fa-stethoscope mr-1"></i> Monitoring Konsultasi
	</a>
</div>

<div class="doclinc-dashboard-grid mb-4">
	<?php foreach ($cards as $card): ?>
		<div class="doclinc-dashboard-card doclinc-dashboard-card-<?= html_escape($card['tone']); ?>">
			<div class="doclinc-dashboard-card-icon"><i class="fas <?= html_escape($card['icon']); ?>"></i></div>
			<div>
				<div class="doclinc-dashboard-card-value"><?= number_format((int) $card['value']); ?></div>
				<div class="doclinc-dashboard-card-label"><?= html_escape($card['label']); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<div class="row">
	<div class="col-xl-7 mb-4">
		<div class="card shadow-sm doclinc-dashboard-panel h-100">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0 font-weight-bold">Distribusi Puskesmas</h2>
				<span class="doclinc-meta-text">Top <?= count($distribution); ?> berdasarkan jumlah request</span>
			</div>
			<div class="card-body">
				<?php if (empty($distribution)): ?>
					<div class="doclinc-empty-state">Belum ada data distribusi Puskesmas.</div>
				<?php else: ?>
					<?php foreach ($distribution as $row): ?>
						<?php
						$total = (int) ($row->total ?? 0);
						$percent = $max_distribution > 0 ? max(4, round(($total / $max_distribution) * 100)) : 0;
						?>
						<div class="doclinc-distribution-row">
							<div class="d-flex justify-content-between align-items-start">
								<div>
									<div class="font-weight-bold"><?= html_escape($row->puskesmas ?? 'Legacy / Belum terklasifikasi'); ?></div>
									<div class="doclinc-meta-text"><?= html_escape($row->puskesmas_code ?? '-'); ?></div>
								</div>
								<div class="font-weight-bold"><?= number_format($total); ?></div>
							</div>
							<div class="doclinc-distribution-bar">
								<div style="width: <?= (int) $percent; ?>%;"></div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="col-xl-5 mb-4">
		<div class="card shadow-sm doclinc-dashboard-panel h-100">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0 font-weight-bold">Shortcut Admin</h2>
			</div>
			<div class="card-body">
				<div class="doclinc-shortcut-grid">
					<a href="<?= site_url('konsultasi_kesehatan'); ?>"><i class="fas fa-stethoscope"></i><span>Monitoring Konsultasi</span></a>
					<a href="<?= site_url('master_puskesmas'); ?>"><i class="fas fa-hospital"></i><span>Master Puskesmas</span></a>
					<a href="<?= site_url('kelola_dokter_nakes'); ?>"><i class="fas fa-user-md"></i><span>Akun Puskesmas</span></a>
					<a href="<?= site_url('kelola_staff_puskesmas'); ?>"><i class="fas fa-users-cog"></i><span>Staff Puskesmas</span></a>
					<a href="<?= site_url('laporan'); ?>"><i class="fas fa-file-alt"></i><span>Laporan</span></a>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="card shadow-sm doclinc-dashboard-panel doclinc-diagnosis-panel mb-4">
	<div class="card-header d-flex flex-wrap align-items-center justify-content-between">
		<div>
			<h2 class="h6 mb-1 font-weight-bold">Tren Diagnosis per Puskesmas</h2>
			<div class="doclinc-meta-text">Periode <?= number_format($diagnosis_period_days); ?> hari terakhir</div>
		</div>
		<span class="badge badge-light border">Rekam medis</span>
	</div>
	<div class="card-body">
		<div class="doclinc-dashboard-note mb-3">
			Data ini berasal dari rekam medis yang sudah diisi Nakes/Dokter. Tren gejala warga membutuhkan penyimpanan gejala terstruktur agar analitik lebih akurat.
		</div>
		<?php if (empty($top_diagnoses) && empty($diagnosis_distribution)): ?>
			<div class="doclinc-empty-state">Belum ada data diagnosis pada periode ini.</div>
		<?php else: ?>
			<div class="row">
				<div class="col-xl-5 mb-3 mb-xl-0">
					<h3 class="doclinc-section-kicker">Top Diagnosis</h3>
					<?php foreach ($top_diagnoses as $row): ?>
						<?php
						$total = (int) ($row->total ?? 0);
						$percent = $max_top_diagnosis > 0 ? max(4, round(($total / $max_top_diagnosis) * 100)) : 0;
						?>
						<div class="doclinc-diagnosis-row">
							<div class="d-flex justify-content-between align-items-start">
								<strong><?= html_escape($row->diagnosis ?? '-'); ?></strong>
								<span><?= number_format($total); ?></span>
							</div>
							<div class="doclinc-diagnosis-bar"><div style="width: <?= (int) $percent; ?>%;"></div></div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="col-xl-7">
					<h3 class="doclinc-section-kicker">Distribusi per Puskesmas</h3>
					<?php foreach ($diagnosis_distribution as $row): ?>
						<?php
						$total = (int) ($row->total ?? 0);
						$percent = $max_puskesmas_diagnosis > 0 ? max(4, round(($total / $max_puskesmas_diagnosis) * 100)) : 0;
						?>
						<div class="doclinc-diagnosis-puskesmas">
							<div class="d-flex justify-content-between align-items-start">
								<div>
									<strong><?= html_escape($row->puskesmas ?? 'Legacy / Belum terklasifikasi'); ?></strong>
									<div class="doclinc-meta-text">
										Top: <?= html_escape($row->top_diagnosis ?? '-'); ?> · <?= number_format((int) ($row->top_diagnosis_total ?? 0)); ?> kasus
									</div>
								</div>
								<span class="font-weight-bold"><?= number_format($total); ?></span>
							</div>
							<div class="doclinc-diagnosis-bar"><div style="width: <?= (int) $percent; ?>%;"></div></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="row">
	<div class="col-xl-6 mb-4">
		<div class="card shadow-sm doclinc-dashboard-panel h-100">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0 font-weight-bold">Perlu Perhatian</h2>
				<a href="<?= site_url('konsultasi_kesehatan'); ?>" class="btn btn-sm btn-outline-primary">Buka Monitoring</a>
			</div>
			<div class="card-body p-0">
				<?php if (empty($attention)): ?>
					<div class="doclinc-empty-state">Tidak ada request yang perlu perhatian khusus.</div>
				<?php else: ?>
					<div class="list-group list-group-flush">
						<?php foreach ($attention as $row): ?>
							<div class="list-group-item doclinc-attention-item">
								<div class="d-flex justify-content-between align-items-start">
									<div>
										<div class="font-weight-bold">Request #<?= html_escape($row->request_id ?? '-'); ?></div>
										<div class="doclinc-meta-text"><?= html_escape($row->puskesmas ?? 'Legacy / Belum terklasifikasi'); ?></div>
									</div>
									<span class="badge badge-light border"><?= html_escape($row->request_status ?? '-'); ?></span>
								</div>
								<div class="mt-2"><?= html_escape($row->attention_reason ?? 'Perlu dipantau'); ?></div>
								<div class="doclinc-meta-text mt-1">Dibuat: <?= html_escape($row->created_at_label ?? '-'); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="col-xl-6 mb-4">
		<div class="card shadow-sm doclinc-dashboard-panel h-100">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0 font-weight-bold">Aktivitas Terbaru</h2>
				<span class="doclinc-meta-text">Dari request_events</span>
			</div>
			<div class="card-body p-0">
				<?php if (empty($activity)): ?>
					<div class="doclinc-empty-state">Belum ada aktivitas konsultasi terbaru.</div>
				<?php else: ?>
					<div class="list-group list-group-flush">
						<?php foreach ($activity as $row): ?>
							<div class="list-group-item doclinc-activity-item">
								<div class="d-flex justify-content-between align-items-start">
									<div>
										<div class="font-weight-bold"><?= html_escape($row->event_label ?? 'Aktivitas konsultasi diperbarui'); ?></div>
										<div class="doclinc-meta-text">Request #<?= html_escape($row->request_id ?? '-'); ?> &middot; <?= html_escape($row->puskesmas ?? 'Legacy / Belum terklasifikasi'); ?></div>
									</div>
									<div class="doclinc-meta-text text-right"><?= html_escape($row->created_at_label ?? '-'); ?></div>
								</div>
								<div class="doclinc-meta-text mt-2"><?= html_escape($row->event_message ?? 'Status konsultasi diperbarui.'); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
