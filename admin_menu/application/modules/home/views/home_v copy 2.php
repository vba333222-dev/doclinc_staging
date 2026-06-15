<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-fw fa-stethoscope"></i> Dashboard DokLinc</h1>
</div>
<?php
$total_konsultasi = $konsultasi_baru + $konsultasi_proses + $konsultasi_selesai + $konsultasi_cancel;
$total_catatan = 0;
$total_diagnosa = 0;
?>
<!-- Content Row -->
<div class="row mb-4">
	<!-- Konsultasi Kesehatan Card -->
	<div class="col-lg-3 col-md-6">
		<div class="card border-left-primary shadow-sm h-100">
			<div class="card-body">
				<div class="row no-gutters align-items-center">
					<div class="col mr-2">
						<div class="text-xs font-weight-bold text-uppercase mb-1">Konsultasi Kesehatan</div>
						<div class="h4 mb-0 font-weight-bold text-gray-800"><?= $total_konsultasi; ?></div>
						<hr class="my-2">
						<div class="row small">
							<div class="col-6">
								<i class="fas fa-user-md fa-fw text-warning"></i> <span class="text-muted">Baru</span>
							</div>
							<div class="col-6 text-muted">: <?= $konsultasi_baru; ?></div>
						</div>
						<div class="row small">
							<div class="col-6">
								<i class="fas fa-spinner fa-fw text-primary"></i> <span class="text-muted">Diproses</span>
							</div>
							<div class="col-6 text-muted">: <?= $konsultasi_proses; ?></div>
						</div>
						<div class="row small">
							<div class="col-6">
								<i class="fas fa-check-circle fa-fw text-success"></i> <span class="text-muted">Selesai</span>
							</div>
							<div class="col-6 text-muted">: <?= $konsultasi_selesai; ?></div>
						</div>
						<div class="row small">
							<div class="col-6">
								<i class="fas fa-ban fa-fw text-danger"></i> <span class="text-muted">Batal</span>
							</div>
							<div class="col-6 text-muted">: <?= $konsultasi_cancel; ?></div>
						</div>
					</div>
					<div class="col-auto">
						<i class="fas fa-comments-medical fa-2x text-gray-300"></i>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!-- Catatan Kesehatan Card -->
	<div class="col-lg-3 col-md-6">
		<div class="card border-left-success shadow-sm h-100">
			<div class="card-body">
				<div class="row no-gutters align-items-center">
					<div class="col mr-2">
						<div class="text-xs font-weight-bold text-uppercase mb-1">Catatan Kesehatan</div>
						<div class="h4 mb-0 font-weight-bold text-gray-800"><?= $total_catatan; ?></div>
					</div>
					<div class="col-auto">
						<i class="fas fa-notes-medical fa-2x text-gray-300"></i>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!-- Diagnosa & Saran Card -->
	<div class="col-lg-3 col-md-6">
		<div class="card border-left-info shadow-sm h-100">
			<div class="card-body">
				<div class="row no-gutters align-items-center">
					<div class="col mr-2">
						<div class="text-xs font-weight-bold text-uppercase mb-1">Diagnosa & Saran</div>
						<div class="h4 mb-0 font-weight-bold text-gray-800"><?= $total_diagnosa; ?></div>
					</div>
					<div class="col-auto">
						<i class="fas fa-file-medical fa-2x text-gray-300"></i>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Statistik Konsultasi -->
<div class="row">
	<div class="col">
		<div class="card shadow-sm mb-4">
			<div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
				<h6 class="m-0 font-weight-bold">Statistik Konsultasi Bulanan - <?php echo date('Y'); ?></h6>
			</div>
			<div class="card-body">
				<div class="chart-area">
					<canvas id="chartKonsultasi"></canvas>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
	const ctx = document.getElementById('chartKonsultasi');
	const chartKonsultasi = new Chart(ctx, {
		type: 'line',
		data: {
			labels: [<?= $bulan_txt; ?>],
			datasets: [{
				label: 'Konsultasi',
				data: [<?= $nilai_txt; ?>],
				fill: true,
				lineTension: 0.4,
				backgroundColor: "rgba(52, 152, 219, 0.2)",
				borderColor: "rgb(52, 152, 219)",
				pointRadius: 3,
				pointBackgroundColor: "rgb(52, 152, 219)",
				pointBorderColor: "rgb(52, 152, 219)",
				pointHoverRadius: 5,
				pointHoverBackgroundColor: "rgb(52, 152, 219)",
				pointHoverBorderColor: "rgb(52, 152, 219)",
				pointHitRadius: 30,
				pointBorderWidth: 2
			}]
		},
		options: {
			maintainAspectRatio: false,
			plugins: {
				legend: {
					display: false
				},
				tooltip: {
					backgroundColor: "rgba(255, 255, 255, 0.8)",
					titleColor: "rgb(52, 152, 219)",
					bodyColor: 'rgb(52, 152, 219)',
					borderColor: 'rgb(52, 152, 219)',
					borderWidth: 1,
					padding: 10,
					displayColors: false,
					intersect: false,
					mode: 'index',
				}
			}
		}
	});
</script>