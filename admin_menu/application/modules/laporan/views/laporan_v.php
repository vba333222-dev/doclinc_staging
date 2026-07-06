<?php
$puskesmas_options = isset($puskesmas_options) && is_array($puskesmas_options) ? $puskesmas_options : array();
?>
<div class="d-sm-flex align-items-start justify-content-between pt-4 pb-4 px-4 mt-n4 mx-n4 you-are-here doclinc-page-header">
	<div>
		<h1 class="h3 mb-1 font-weight-bold doclinc-page-title"><i class="fas fa-fw fa-file-alt"></i> Laporan</h1>
		<div class="text-white-50 doclinc-page-subtitle">Rekap konsultasi berdasarkan tanggal, status, Puskesmas, PIC, dan aktivitas terakhir.</div>
	</div>
	<!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Generate Report</a> -->
</div>
<!-- Content Row -->
<div class="row">
	<div class="col-lg-3 col-md-6 mb-4">
		<div class="card border-left-primary shadow h-100 py-2">
			<div class="card-body text-center">
				<div class="text-xs font-weight-bold text-primary text-uppercase mb-3">
					<i class="fas fa-calendar-day fa-2x mb-2"></i><br>Laporan Per Hari
				</div>
				<button type="button" class="btn btn-primary btn-block btn-sm rounded-pill show-form" data-form="form-perhari">Lihat Laporan</button>
			</div>
		</div>
	</div>
	<div class="col-lg-3 col-md-6 mb-4">
		<div class="card border-left-success shadow h-100 py-2">
			<div class="card-body text-center">
				<div class="text-xs font-weight-bold text-success text-uppercase mb-3">
					<i class="fas fa-calendar-week fa-2x mb-2"></i><br>Laporan Per Minggu
				</div>
				<button type="button" class="btn btn-success btn-block btn-sm rounded-pill show-form" data-form="form-perminggu">Lihat Laporan</button>
			</div>
		</div>
	</div>
	<div class="col-lg-3 col-md-6 mb-4">
		<div class="card border-left-info shadow h-100 py-2">
			<div class="card-body text-center">
				<div class="text-xs font-weight-bold text-info text-uppercase mb-3">
					<i class="fas fa-calendar-alt fa-2x mb-2"></i><br>Laporan Per Bulan
				</div>
				<button type="button" class="btn btn-info btn-block btn-sm rounded-pill show-form" data-form="form-perbulan">Lihat Laporan</button>
			</div>
		</div>
	</div>
	<div class="col-lg-3 col-md-6 mb-4">
		<div class="card border-left-warning shadow h-100 py-2">
			<div class="card-body text-center">
				<div class="text-xs font-weight-bold text-warning text-uppercase mb-3">
					<i class="fas fa-calendar fa-2x mb-2"></i><br>Laporan Per Tahun
				</div>
				<button type="button" class="btn btn-warning btn-block btn-sm rounded-pill show-form" data-form="form-pertahun">Lihat Laporan</button>
			</div>
		</div>
	</div>
</div>

<!-- Forms for each report type -->
<div id="form-perhari" class="laporan-form doclinc-filter-card card shadow-sm p-3 mb-4" style="display:none;">
	<form id="formLaporanPerHari" action="<?= site_url('laporan'); ?>" method="get" class="mb-4">
		<div class="form-row align-items-end">
			<div class="col-auto">
				<label for="tanggal_hari" class="col-form-label">Tanggal</label>
				<input type="date" class="form-control" id="tanggal_hari" name="tanggal">
			</div>
			<div class="col-auto">
				<label for="puskesmas_hari" class="col-form-label">Nama Puskesmas</label>
				<select class="form-control" id="puskesmas_hari" name="puskesmas">
					<option value="">Semua Puskesmas</option>
					<?php foreach ($puskesmas_options as $puskesmas): ?>
						<option value="<?= html_escape($puskesmas->kode_pkm); ?>"><?= html_escape($puskesmas->nama_puskesmas); ?></option>
					<?php endforeach; ?>
					<option value="__legacy__">Legacy / Belum terklasifikasi</option>
				</select>
			</div>
			<div class="col-auto">
				<label for="status_hari" class="col-form-label">Status</label>
				<select class="form-control" id="status_hari" name="status">
					<option value="">Semua</option>
					<option value="Pending">Pending</option>
					<option value="Accepted">Accepted</option>
					<option value="Completed">Completed</option>
					<option value="Cancelled">Cancelled</option>
				</select>
			</div>
			<div class="col-auto">
				<label for="dokter_hari" class="col-form-label">Akun Puskesmas</label>
				<input type="text" class="form-control" id="dokter_hari" name="dokter" placeholder="Nama akun">
			</div>
			<div class="col-auto">
				<label for="keyword_hari" class="col-form-label">Keyword</label>
				<input type="text" class="form-control" id="keyword_hari" name="keyword" placeholder="ID, warga, diagnosa">
			</div>
			<input type="hidden" name="tipe" value="perhari">
			<input type="hidden" name="tipeBtn" id="tipeBtn">

			<div class="col-auto">
				<button type="button" id="btnTampilkan" class="btn btn-primary">Tampilkan</button>
				<button type="button" id="btnJumlahPasien" class="btn btn-success">Jumlah Pasien</button>
				<button type="button" id="btnJumlahDiagnosa" class="btn btn-secondary">Jumlah Diagnosa</button>
			</div>

			<!-- Tombol Cetak & Export Excel di kanan -->
			<div class="col ms-auto d-flex justify-content-end" style="gap: 1rem;">
				<button type="button" id="btnCetak" class="btn btn-secondary">Cetak</button>
				<button type="button" id="btnExport" class="btn btn-success">Export Excel</button>
			</div>
		</div>
	</form>
	<div id="hasil-laporan-perhari" class="mt-4 doclinc-table-card"></div>
</div>

<div id="form-perminggu" class="laporan-form doclinc-filter-card card shadow-sm p-3 mb-4" style="display:none;">
	<form action="<?= site_url('laporan'); ?>" method="get" class="mb-4">
		<div class="form-row align-items-end">
			<div class="col-auto">
				<label for="minggu_tahun" class="col-form-label">Tahun</label>
				<input type="number" class="form-control" id="minggu_tahun" name="tahun" min="2000" max="2100" required>
			</div>
			<div class="col-auto">
				<label for="minggu_ke" class="col-form-label">Minggu ke-</label>
				<input type="number" class="form-control" id="minggu_ke" name="minggu" min="1" max="53" required>
			</div>
			<input type="hidden" name="tipe" value="perminggu">

			<div class="col-auto">
				<button type="submit" class="btn btn-success">Tampilkan</button>
			</div>
		</div>
	</form>
</div>
<div id="form-perbulan" class="laporan-form doclinc-filter-card card shadow-sm p-3 mb-4" style="display:none;">
	<form action="<?= site_url('laporan'); ?>" method="get" class="mb-4">
		<div class="form-row align-items-end">
			<div class="col-auto">
				<label for="bulan" class="col-form-label">Bulan</label>
				<select class="form-control" id="bulan" name="bulan" required>
					<option value="">Pilih Bulan</option>
					<?php
					for ($i = 1; $i <= 12; $i++) {
						echo '<option value="' . $i . '">' . date('F', mktime(0, 0, 0, $i, 10)) . '</option>';
					}
					?>
				</select>
			</div>
			<div class="col-auto">
				<label for="tahun_bulan" class="col-form-label">Tahun</label>
				<input type="number" class="form-control" id="tahun_bulan" name="tahun" min="2000" max="2100" required>
			</div>
			<div class="col-auto">
				<button type="submit" class="btn btn-info">Tampilkan</button>
			</div>
		</div>
	</form>
</div>
<div id="form-pertahun" class="laporan-form doclinc-filter-card card shadow-sm p-3 mb-4" style="display:none;">
	<form action="<?= site_url('laporan'); ?>" method="get" class="mb-4">
		<div class="form-row align-items-end">
			<div class="col-auto">
				<label for="tahun_tahun" class="col-form-label">Tahun</label>
				<input type="number" class="form-control" id="tahun_tahun" name="tahun" min="2000" max="2100" required>
			</div>
			<div class="col-auto">
				<button type="submit" class="btn btn-warning">Tampilkan</button>
			</div>
		</div>
	</form>
</div>

<script>
	document.querySelectorAll('.show-form').forEach(function(btn) {
		btn.addEventListener('click', function() {
			document.querySelectorAll('.laporan-form').forEach(function(form) {
				form.style.display = 'none';
			});
			var formId = this.getAttribute('data-form');
			document.getElementById(formId).style.display = 'block';
			// Scroll to form
			document.getElementById(formId).scrollIntoView({
				behavior: 'smooth',
				block: 'start'
			});
		});
	});
</script>

<script>
	$(document).ready(function() {
		function escapeHtml(value) {
			return $('<div>').text(value == null ? '-' : value).html();
		}

		function shortText(value, limit) {
			value = value == null ? '-' : String(value);
			if (value.length <= limit) {
				return value;
			}
			return value.substring(0, limit - 3) + '...';
		}

		function statusBadge(status) {
			var safeStatus = escapeHtml(status || 'Unknown');
			var classes = {
				Pending: 'badge-warning',
				Accepted: 'badge-primary',
				Completed: 'badge-success',
				Cancelled: 'badge-danger'
			};
			return '<span class="badge ' + (classes[status] || 'badge-secondary') + '">' + safeStatus + '</span>';
		}

		// Handler untuk tombol Tampilkan
		$('#btnTampilkan').on('click', function(e) {
			e.preventDefault();
			var tipeBtn = $('#tipeBtn').val(1);
			var $form = $('#formLaporanPerHari');
			var $hasil = $('#hasil-laporan-perhari');
			$hasil.html('<div class="text-center"><span class="spinner-border spinner-border-sm"></span> Memuat...</div>');
			$.ajax({
				url: $form.attr('action'),
				type: 'GET',
				data: $form.serialize(),
				dataType: 'json',
				success: function(response) {
					if (response.status === 'success' && response.data.length > 0) {
						var eventLabels = {
							request_created: 'Permintaan dibuat',
							request_accepted: 'Permintaan diterima',
							request_cancelled: 'Permintaan dibatalkan/ditolak',
							pic_assigned: 'PIC ditetapkan',
							pic_changed: 'PIC diganti',
							pic_cleared: 'PIC dibatalkan',
							visit_started: 'Perjalanan dimulai',
							visit_arrived: 'Tiba di lokasi',
							visit_in_service: 'Pelayanan dimulai',
							visit_completed: 'Kunjungan selesai',
							request_completed: 'Permintaan selesai'
						};
						var html = '<div id="print-area"><h5>Rincian Konsultasi Per Hari</h5>';
						html += '<div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr>';
						html += '<th>No</th><th>Tanggal/Waktu</th><th>Request</th><th>Status</th><th>Puskesmas</th><th>PIC</th><th>Aktivitas Terakhir</th><th>Akun Puskesmas</th><th>Warga</th><th>Diagnosa</th><th>Saran</th>';
						html += '</tr></thead><tbody>';
						$.each(response.data, function(i, row) {
							var picName = row.pic_staff_name || 'Belum ditentukan';
							var picProfesi = row.pic_staff_profesi ? '<br><small>' + escapeHtml(row.pic_staff_profesi) + '</small>' : '';
							var latestEvent = row.latest_request_event || null;
							var latestLabel = 'Belum ada aktivitas';
							var latestTime = '';
							if (latestEvent) {
								latestLabel = latestEvent.message || eventLabels[latestEvent.event_type] || 'Aktivitas tercatat';
								latestTime = latestEvent.created_at || '';
							}
							html += '<tr>';
							html += '<td>' + (i + 1) + '</td>';
							html += '<td>' + escapeHtml(row.waktu) + '</td>';
							html += '<td>#' + escapeHtml(row.request_id) + '</td>';
							html += '<td>' + statusBadge(row.request_status) + '</td>';
							html += '<td>' + escapeHtml(row.nama_puskesmas) + '</td>';
							html += '<td>' + escapeHtml(picName) + picProfesi + '</td>';
							html += '<td>' + escapeHtml(latestLabel) + (latestTime ? '<br><small>' + escapeHtml(latestTime) + '</small>' : '') + '</td>';
							html += '<td>' + escapeHtml(row.name) + '</td>';
							html += '<td>' + escapeHtml(row.nama_user) + '</td>';
							html += '<td title="' + escapeHtml(row.diagnosa) + '">' + escapeHtml(shortText(row.diagnosa, 80)) + '</td>';
							html += '<td title="' + escapeHtml(row.saran) + '">' + escapeHtml(shortText(row.saran, 90)) + '</td>';
							html += '</tr>';
						});
						html += '</tbody></table></div><br><br><div class="print-signature"><div style="width: 87%; text-align: right; margin-bottom: 40px;">Cilegon, <span id="tanggal-hari-ini"></span></div><div style="display: flex; justify-content: space-between;"><div style="text-align: center; width: 40%;">Petugas,<br><br><br><br>(________________)</div><div style="text-align: center; width: 40%;">Kepala Dinas Kesehatan,<br><br><br><br>(________________)</div></div></div></div>';
						$hasil.html(html);

						// Set tanggal otomatis
						const tanggalElemen = document.getElementById("tanggal-hari-ini");
						if (tanggalElemen) {
							const today = new Date();
							const options = {
								year: 'numeric',
								month: 'long',
								day: 'numeric'
							};
							tanggalElemen.innerText = today.toLocaleDateString('id-ID', options);
						}
					} else {
						$hasil.html('<div class="alert alert-info">Belum ada data laporan sesuai filter.</div>');
					}
				},
				error: function() {
					$hasil.html('<div class="alert alert-danger">Terjadi kesalahan saat mengambil data.</div>');
				}
			});
		});

		$('#btnJumlahPasien').on('click', function(e) {
			e.preventDefault();
			var tipeBtn = $('#tipeBtn').val(2);
			var $form = $('#formLaporanPerHari');
			var $hasil = $('#hasil-laporan-perhari');
			$hasil.html('<div class="text-center"><span class="spinner-border spinner-border-sm"></span> Memuat...</div>');
			$.ajax({
				url: $form.attr('action'),
				type: 'GET',
				data: $form.serialize(),
				dataType: 'json',
				success: function(response) {
					if (response.status === 'success' && response.data.length > 0) {
						var html = '<div id="print-area"><h5>Jumlah Pasien per Puskesmas</h5>';
						html += '<div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr>';
						html += '<th>No</th><th>Nama Puskesmas</th><th>Jumlah Pasien</th>';
						html += '</tr></thead><tbody>';
						$.each(response.data, function(i, row) {
							html += '<tr>';
							html += '<td>' + (i + 1) + '</td>';
							html += '<td>' + escapeHtml(row.nama_puskesmas) + '</td>';
							html += '<td>' + escapeHtml(row.jumlah_pasien) + '</td>';
							html += '</tr>';
						});
						html += '</tbody></table></div><br><br><div class="print-signature"><div style="width: 87%; text-align: right; margin-bottom: 40px;">Cilegon, <span id="tanggal-hari-ini"></span></div><div style="display: flex; justify-content: space-between;"><div style="text-align: center; width: 40%;">Petugas,<br><br><br><br>(________________)</div><div style="text-align: center; width: 40%;">Kepala Dinas Kesehatan,<br><br><br><br>(________________)</div></div></div></div>';
						$hasil.html(html);

						// Set tanggal otomatis
						const tanggalElemen = document.getElementById("tanggal-hari-ini");
						if (tanggalElemen) {
							const today = new Date();
							const options = {
								year: 'numeric',
								month: 'long',
								day: 'numeric'
							};
							tanggalElemen.innerText = today.toLocaleDateString('id-ID', options);
						}
					} else {
						$hasil.html('<div class="alert alert-info">Belum ada data laporan sesuai filter.</div>');
					}
				},
				error: function() {
					$hasil.html('<div class="alert alert-danger">Terjadi kesalahan saat mengambil data.</div>');
				}
			});
		});

		$('#btnJumlahDiagnosa').on('click', function(e) {
			e.preventDefault();
			$('#tipeBtn').val(3);
			var $form = $('#formLaporanPerHari');
			var $hasil = $('#hasil-laporan-perhari');
			$hasil.html('<div class="text-center"><span class="spinner-border spinner-border-sm"></span> Memuat...</div>');
			$.ajax({
				url: $form.attr('action'),
				type: 'GET',
				data: $form.serialize(),
				dataType: 'json',
				success: function(response) {
					if (response.status === 'success' && response.data.length > 0) {
						var html = '<div id="print-area"><h5>Jumlah Diagnosa per Puskesmas</h5>';
						html += '<div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr>';
						html += '<th>No</th><th>Nama Puskesmas</th><th>Diagnosa</th><th>Jumlah Diagnosa</th>';
						html += '</tr></thead><tbody>';
						// Kelompokkan data berdasarkan nama_puskesmas
						var grouped = {};
						$.each(response.data, function(i, row) {
							if (!grouped[row.nama_puskesmas]) {
								grouped[row.nama_puskesmas] = [];
							}
							grouped[row.nama_puskesmas].push(row);
						});
						var no = 1;
						$.each(grouped, function(puskesmas, rows) {
							$.each(rows, function(idx, row) {
								html += '<tr>';
								if (idx === 0) {
									html += '<td rowspan="' + rows.length + '" style="vertical-align: middle; text-align: center;">' + (no++) + '</td>';
									html += '<td rowspan="' + rows.length + '" style="vertical-align: middle; text-align: center;">' + escapeHtml(row.nama_puskesmas) + '</td>';
								}
								html += '<td>' + escapeHtml(row.diagnosa) + '</td>';
								html += '<td>' + escapeHtml(row.jumlah_diagnosa) + '</td>';
								html += '</tr>';
							});
						});
						html += '</tbody></table></div><br><br><div class="print-signature"><div style="width: 87%; text-align: right; margin-bottom: 40px;">Cilegon, <span id="tanggal-hari-ini"></span></div><div style="display: flex; justify-content: space-between;"><div style="text-align: center; width: 40%;">Petugas,<br><br><br><br>(________________)</div><div style="text-align: center; width: 40%;">Kepala Dinas Kesehatan,<br><br><br><br>(________________)</div></div></div></div>';
						$hasil.html(html);

						// Set tanggal otomatis
						const tanggalElemen = document.getElementById("tanggal-hari-ini");
						if (tanggalElemen) {
							const today = new Date();
							const options = {
								year: 'numeric',
								month: 'long',
								day: 'numeric'
							};
							tanggalElemen.innerText = today.toLocaleDateString('id-ID', options);
						}
					} else {
						$hasil.html('<div class="alert alert-info">Belum ada data laporan sesuai filter.</div>');
					}
				},
				error: function() {
					$hasil.html('<div class="alert alert-danger">Terjadi kesalahan saat mengambil data.</div>');
				}
			});
		});
	});
</script>

<script>
	// Cetak laporan per hari
	$('#btnCetak').on('click', function() {
		const printContents = document.getElementById('print-area').innerHTML;
		const originalContents = document.body.innerHTML;

		document.body.innerHTML = printContents;
		window.print();
		document.body.innerHTML = originalContents;
		location.reload(); // untuk refresh supaya event handler dan layout normal kembali
	});

	// Export laporan per hari ke Excel
	$('#btnExport').on('click', function() {
		var $form = $('#formLaporanPerHari');
		var params = $form.serialize();
		var url = $form.attr('action') + '/export_excel?' + params;
		window.open(url, '_blank');
	});
</script>
