<?php
$puskesmas_options = isset($puskesmas_options) && is_array($puskesmas_options) ? $puskesmas_options : array();
?>
<div class="d-sm-flex align-items-start justify-content-between pt-4 pb-4 px-4 mt-n4 mx-n4 you-are-here doclinc-page-header">
	<div>
		<h1 class="h3 mb-1 font-weight-bold doclinc-page-title"><i class="fas fa-fw fa-file-alt"></i> Laporan</h1>
		<div class="text-white-50 doclinc-page-subtitle">Lihat rekap konsultasi terbaru.</div>
	</div>
</div>

<div class="row">
	<div class="col-lg-3 col-md-6 mb-4">
		<div class="card border-left-primary shadow h-100 py-2">
			<div class="card-body text-center">
				<div class="text-xs font-weight-bold text-primary text-uppercase mb-3">
					<i class="fas fa-calendar-day fa-2x mb-2"></i><br>Laporan harian
				</div>
				<button type="button" class="btn btn-primary btn-block btn-sm rounded-pill show-form" data-form="form-perhari">Lihat laporan</button>
			</div>
		</div>
	</div>
	<div class="col-lg-3 col-md-6 mb-4">
		<div class="card border-left-success shadow h-100 py-2">
			<div class="card-body text-center">
				<div class="text-xs font-weight-bold text-success text-uppercase mb-3">
					<i class="fas fa-calendar-week fa-2x mb-2"></i><br>Laporan mingguan
				</div>
				<button type="button" class="btn btn-success btn-block btn-sm rounded-pill show-form" data-form="form-perminggu">Lihat laporan</button>
			</div>
		</div>
	</div>
	<div class="col-lg-3 col-md-6 mb-4">
		<div class="card border-left-info shadow h-100 py-2">
			<div class="card-body text-center">
				<div class="text-xs font-weight-bold text-info text-uppercase mb-3">
					<i class="fas fa-calendar-alt fa-2x mb-2"></i><br>Laporan bulanan
				</div>
				<button type="button" class="btn btn-info btn-block btn-sm rounded-pill show-form" data-form="form-perbulan">Lihat laporan</button>
			</div>
		</div>
	</div>
	<div class="col-lg-3 col-md-6 mb-4">
		<div class="card border-left-warning shadow h-100 py-2">
			<div class="card-body text-center">
				<div class="text-xs font-weight-bold text-warning text-uppercase mb-3">
					<i class="fas fa-calendar fa-2x mb-2"></i><br>Laporan tahunan
				</div>
				<button type="button" class="btn btn-warning btn-block btn-sm rounded-pill show-form" data-form="form-pertahun">Lihat laporan</button>
			</div>
		</div>
	</div>
</div>

<div id="form-perhari" class="laporan-form doclinc-filter-card card shadow-sm p-3 mb-4" style="display:none;">
	<form id="formLaporanPerHari" action="<?= site_url('laporan'); ?>" method="get" class="mb-4">
		<div class="d-flex align-items-start justify-content-between flex-wrap mb-3">
			<div>
				<h5 class="mb-1 font-weight-bold">Laporan konsultasi harian</h5>
			</div>
		</div>
		<div class="form-row align-items-end">
			<div class="col-auto mb-2">
				<label for="tanggal_hari" class="col-form-label">Tanggal</label>
				<input type="date" class="form-control" id="tanggal_hari" name="tanggal">
			</div>
			<div class="col-auto mb-2">
				<label for="tanggal_awal_hari" class="col-form-label">Dari</label>
				<input type="date" class="form-control" id="tanggal_awal_hari" name="tanggal_awal">
			</div>
			<div class="col-auto mb-2">
				<label for="tanggal_akhir_hari" class="col-form-label">Sampai</label>
				<input type="date" class="form-control" id="tanggal_akhir_hari" name="tanggal_akhir">
			</div>
			<div class="col-auto mb-2">
				<label for="puskesmas_hari" class="col-form-label">Puskesmas</label>
				<select class="form-control" id="puskesmas_hari" name="puskesmas">
					<option value="">Semua Puskesmas</option>
					<?php foreach ($puskesmas_options as $puskesmas): ?>
						<option value="<?= html_escape($puskesmas->kode_pkm); ?>"><?= html_escape($puskesmas->nama_puskesmas); ?></option>
					<?php endforeach; ?>
					<option value="__legacy__">Perlu dicek</option>
				</select>
			</div>
			<div class="col-auto mb-2">
				<label for="status_hari" class="col-form-label">Status</label>
				<select class="form-control" id="status_hari" name="status">
					<option value="">Semua status</option>
					<option value="Pending">Menunggu</option>
					<option value="Accepted">Diterima</option>
					<option value="Completed">Selesai</option>
					<option value="Cancelled">Dibatalkan</option>
				</select>
			</div>
			<div class="col-auto mb-2">
				<label for="dokter_hari" class="col-form-label">Akun Puskesmas</label>
				<input type="text" class="form-control" id="dokter_hari" name="dokter" placeholder="Nama akun">
			</div>
			<div class="col-auto mb-2">
				<label for="keyword_hari" class="col-form-label">Kata kunci</label>
				<input type="text" class="form-control" id="keyword_hari" name="keyword" placeholder="ID, warga, diagnosa">
			</div>
			<input type="hidden" name="tipe" value="perhari">
			<input type="hidden" name="tipeBtn" id="tipeBtn">

			<div class="col-auto mb-2">
				<button type="button" id="btnTampilkan" class="btn btn-primary">Lihat laporan</button>
				<button type="button" id="btnJumlahPasien" class="btn btn-success">Jumlah pasien</button>
				<button type="button" id="btnJumlahDiagnosa" class="btn btn-secondary">Jumlah diagnosis</button>
				<button type="button" id="btnResetLaporan" class="btn btn-outline-secondary">Hapus filter</button>
			</div>

			<div class="col mb-2 ml-auto d-flex justify-content-end flex-wrap" style="gap: 0.75rem;">
				<button type="button" id="btnCetak" class="btn btn-secondary">Cetak laporan</button>
				<button type="button" id="btnExport" class="btn btn-outline-secondary" disabled title="Ekspor belum tersedia.">Ekspor Excel</button>
			</div>
		</div>
	</form>
	<div id="hasil-laporan-perhari" class="mt-4 doclinc-table-card">
		<div class="doclinc-empty-state">Pilih filter, lalu tampilkan laporan.</div>
	</div>
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
				<button type="submit" class="btn btn-success">Lihat laporan</button>
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
					<option value="">Pilih bulan</option>
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
				<button type="submit" class="btn btn-info">Lihat laporan</button>
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
				<button type="submit" class="btn btn-warning">Lihat laporan</button>
			</div>
		</div>
	</form>
</div>

<div class="modal fade doclinc-detail-modal" id="laporanDetailModal" tabindex="-1" role="dialog" aria-labelledby="laporanDetailTitle" aria-hidden="true">
	<div class="modal-dialog modal-xl" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="laporanDetailTitle">Detail laporan</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body bg-light" id="laporanDetailBody"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
			</div>
		</div>
	</div>
</div>

<script>
	document.querySelectorAll('.show-form').forEach(function(btn) {
		btn.addEventListener('click', function() {
			document.querySelectorAll('.laporan-form').forEach(function(form) {
				form.style.display = 'none';
			});
			var formId = this.getAttribute('data-form');
			document.getElementById(formId).style.display = 'block';
			document.getElementById(formId).scrollIntoView({
				behavior: 'smooth',
				block: 'start'
			});
		});
	});
</script>

<script>
	$(document).ready(function() {
		var latestRows = [];
		var uploadBaseUrl = '<?= base_url('../uploads/'); ?>';
		var eventLabels = {
			request_created: 'Konsultasi baru dibuat',
			request_accepted: 'Konsultasi diterima',
			request_cancelled: 'Konsultasi dibatalkan',
			pic_assigned: 'PIC ditugaskan',
			pic_changed: 'PIC diganti',
			pic_cleared: 'PIC dihapus',
			visit_started: 'Kunjungan dimulai',
			visit_arrived: 'Nakes tiba di lokasi',
			visit_in_service: 'Sedang ditangani',
			visit_completed: 'Kunjungan selesai',
			request_completed: 'Konsultasi selesai'
		};

		function escapeHtml(value) {
			return $('<div>').text(value == null || value === '' ? '-' : value).html();
		}

		function shortText(value, limit) {
			value = value == null || value === '' ? '-' : String(value);
			if (value.length <= limit) {
				return value;
			}
			return value.substring(0, limit - 3) + '...';
		}

		function safeText(value) {
			return value == null || value === '' ? '-' : String(value);
		}

		function statusBadge(status) {
			var labels = {
				Pending: 'Menunggu',
				Accepted: 'Diterima',
				Completed: 'Selesai',
				Cancelled: 'Dibatalkan'
			};
			var safeStatus = escapeHtml(labels[status] || 'Perlu dicek');
			var classes = {
				Pending: 'badge-warning',
				Accepted: 'badge-primary',
				Completed: 'badge-success',
				Cancelled: 'badge-danger'
			};
			return '<span class="badge ' + (classes[status] || 'badge-secondary') + '">' + safeStatus + '</span>';
		}

		function visitBadge(visitStatus) {
			if (!visitStatus) {
				return '';
			}
			var labels = {
				not_started: 'Belum dimulai',
				en_route: 'Dalam perjalanan',
				arrived: 'Sudah tiba',
				in_service: 'Ditangani',
				completed: 'Selesai'
			};
			return '<span class="badge badge-light border ml-1">' + escapeHtml(labels[visitStatus] || 'Perlu dicek') + '</span>';
		}

		function latestEventInfo(row) {
			var latestEvent = row.latest_request_event || null;
			if (!latestEvent) {
				return {
					label: 'Belum ada aktivitas',
					time: ''
				};
			}
			return {
				label: latestEvent.message || eventLabels[latestEvent.event_type] || 'Konsultasi diperbarui',
				time: latestEvent.created_at || ''
			};
		}

		function picHtml(row) {
			if (!row.pic_staff_name) {
				return '<span class="doclinc-pic-empty">Belum ditentukan</span>';
			}
			var meta = row.pic_staff_profesi ? '<div class="doclinc-report-meta">' + escapeHtml(row.pic_staff_profesi) + '</div>' : '';
			return '<span class="doclinc-pic-chip"><i class="fas fa-user-nurse"></i>' + escapeHtml(row.pic_staff_name) + '</span>' + meta;
		}

		function renderSummary(summary) {
			summary = summary || {};
			var cards = [{
				label: 'Total konsultasi',
				value: summary.total || 0,
				icon: 'fa-clipboard-list'
			}, {
				label: 'Selesai',
				value: summary.completed || 0,
				icon: 'fa-check-circle'
			}, {
				label: 'Dibatalkan',
				value: summary.cancelled || 0,
				icon: 'fa-ban'
			}, {
				label: 'Ditangani',
				value: summary.active || 0,
				icon: 'fa-hourglass-half'
			}, {
				label: 'Perlu dicek',
				value: summary.legacy || 0,
				icon: 'fa-exclamation-triangle'
			}, {
				label: 'Puskesmas aktif terlibat',
				value: summary.puskesmas_count || 0,
				icon: 'fa-hospital'
			}];
			var html = '<div class="doclinc-report-summary mb-4">';
			$.each(cards, function(_, card) {
				html += '<div class="doclinc-report-summary-card">';
				html += '<div class="doclinc-monitor-summary-icon"><i class="fas ' + card.icon + '"></i></div>';
				html += '<div><div class="doclinc-monitor-summary-value">' + escapeHtml(card.value) + '</div>';
				html += '<div class="doclinc-monitor-summary-label">' + escapeHtml(card.label) + '</div></div>';
				html += '</div>';
			});
			html += '</div>';
			return html;
		}

		function renderBreakdown(items) {
			items = items || [];
			if (!items.length) {
				return '<div class="doclinc-report-breakdown mb-4"><div class="doclinc-empty-state">Belum ada rekap Puskesmas.</div></div>';
			}
			var html = '<div class="doclinc-report-breakdown mb-4"><div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">';
			html += '<h6 class="font-weight-bold mb-1">Rekap Puskesmas</h6>';
			html += '</div>';
			html += '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr>';
			html += '<th>Puskesmas</th><th>Total</th><th>Selesai</th><th>Batal</th><th>Aktif</th><th>% Selesai</th>';
			html += '</tr></thead><tbody>';
			$.each(items, function(_, item) {
				html += '<tr>';
				html += '<td class="font-weight-bold">' + escapeHtml(item.nama_puskesmas) + '</td>';
				html += '<td>' + escapeHtml(item.total) + '</td>';
				html += '<td>' + escapeHtml(item.completed) + '</td>';
				html += '<td>' + escapeHtml(item.cancelled) + '</td>';
				html += '<td>' + escapeHtml(item.active) + '</td>';
				html += '<td><div class="doclinc-report-progress"><span style="width:' + escapeHtml(item.completed_percent || 0) + '%"></span></div><small>' + escapeHtml(item.completed_percent || 0) + '%</small></td>';
				html += '</tr>';
			});
			html += '</tbody></table></div></div>';
			return html;
		}

		function renderReportRows(rows, summary, breakdown) {
			var html = '<div id="print-area">';
			html += '<div class="d-flex align-items-start justify-content-between flex-wrap mb-3">';
			html += '<div><h5 class="font-weight-bold mb-1">Laporan konsultasi</h5></div>';
			html += '</div>';
			html += renderSummary(summary);
			html += renderBreakdown(breakdown);
			html += '<div class="table-responsive"><table class="table table-sm doclinc-report-table"><thead><tr>';
			html += '<th>Tanggal</th><th>ID permintaan</th><th>Puskesmas</th><th>Warga</th><th>Status</th><th>PIC</th><th>Aktivitas terbaru</th><th>Diagnosis dan saran</th><th>Lampiran</th><th class="no-print">Detail</th>';
			html += '</tr></thead><tbody>';
			$.each(rows, function(i, row) {
				var latest = latestEventInfo(row);
				var hasFoto = row.foto && String(row.foto).trim() !== '';
				html += '<tr>';
				html += '<td><strong>' + escapeHtml(row.tanggal || row.waktu) + '</strong><div class="doclinc-report-meta">' + escapeHtml(row.waktu || '-') + '</div></td>';
				html += '<td><strong>#' + escapeHtml(row.request_id) + '</strong></td>';
				html += '<td><strong>' + escapeHtml(row.nama_puskesmas) + '</strong></td>';
				html += '<td>' + escapeHtml(row.nama_user) + '</td>';
				html += '<td>' + statusBadge(row.request_status) + visitBadge(row.visit_status) + '</td>';
				html += '<td>' + picHtml(row) + '</td>';
				html += '<td><div class="doclinc-timeline-pill">' + escapeHtml(latest.label) + (latest.time ? '<span>' + escapeHtml(latest.time) + '</span>' : '') + '</div></td>';
				html += '<td><strong>Diagnosa:</strong> ' + escapeHtml(shortText(row.diagnosa, 56)) + '<div class="doclinc-report-meta"><strong>Saran:</strong> ' + escapeHtml(shortText(row.saran, 64)) + '</div></td>';
				html += '<td>' + (hasFoto ? '<span class="badge badge-info">Ada foto</span>' : '<span class="doclinc-report-meta">Tidak ada</span>') + '</td>';
				html += '<td class="no-print"><button type="button" class="btn btn-sm btn-outline-primary btn-detail-laporan" data-index="' + i + '">Lihat detail</button></td>';
				html += '</tr>';
			});
			html += '</tbody></table></div>';
			html += '<br><br><div class="print-signature"><div style="width: 87%; text-align: right; margin-bottom: 40px;">Cilegon, <span id="tanggal-hari-ini"></span></div><div style="display: flex; justify-content: space-between;"><div style="text-align: center; width: 40%;">Petugas,<br><br><br><br>(________________)</div><div style="text-align: center; width: 40%;">Kepala Dinas Kesehatan,<br><br><br><br>(________________)</div></div></div>';
			html += '</div>';
			return html;
		}

		function setPrintDate() {
			var tanggalElemen = document.getElementById('tanggal-hari-ini');
			if (tanggalElemen) {
				tanggalElemen.innerText = new Date().toLocaleDateString('id-ID', {
					year: 'numeric',
					month: 'long',
					day: 'numeric'
				});
			}
		}

		function fetchReport(tipeBtn) {
			$('#tipeBtn').val(tipeBtn);
			var $form = $('#formLaporanPerHari');
			var $hasil = $('#hasil-laporan-perhari');
			$hasil.html('<div class="text-center"><span class="spinner-border spinner-border-sm"></span> Memuat laporan...</div>');
			$.ajax({
				url: $form.attr('action'),
				type: 'GET',
				data: $form.serialize(),
				dataType: 'json',
				success: function(response) {
					var isSuccess = response && (response.success === true || response.status === 'success');
					if (!isSuccess) {
						latestRows = [];
						$hasil.html('<div class="alert alert-warning">' + escapeHtml(response && response.message ? response.message : 'Data laporan belum bisa dimuat.') + '</div>');
						return;
					}
					var rows = response.data || [];
					if (rows.length === 0) {
						latestRows = [];
						$hasil.html('<div class="doclinc-empty-state">Belum ada data laporan. Hapus filter untuk melihat semua data.</div>');
						return;
					}
					if (tipeBtn === 1) {
						latestRows = rows;
						$hasil.html(renderReportRows(rows, response.summary, response.breakdown));
						setPrintDate();
					} else if (tipeBtn === 2) {
						renderJumlahPasien(rows, $hasil);
					} else {
						renderJumlahDiagnosa(rows, $hasil);
					}
				},
				error: function() {
					latestRows = [];
					$hasil.html('<div class="alert alert-warning">Data laporan belum bisa dimuat. Silakan coba ulang atau sesuaikan filter.</div>');
				}
			});
		}

		function renderJumlahPasien(rows, $hasil) {
			var html = '<div id="print-area"><h5 class="font-weight-bold">Jumlah pasien per Puskesmas</h5>';
			html += '<div class="table-responsive"><table class="table table-sm doclinc-report-table"><thead><tr><th>No</th><th>Puskesmas</th><th>Jumlah pasien</th></tr></thead><tbody>';
			$.each(rows, function(i, row) {
				html += '<tr><td>' + (i + 1) + '</td><td class="font-weight-bold">' + escapeHtml(row.nama_puskesmas) + '</td><td>' + escapeHtml(row.jumlah_pasien) + '</td></tr>';
			});
			html += '</tbody></table></div></div>';
			$hasil.html(html);
		}

		function renderJumlahDiagnosa(rows, $hasil) {
			var html = '<div id="print-area"><h5 class="font-weight-bold">Jumlah diagnosis per Puskesmas</h5>';
			html += '<div class="table-responsive"><table class="table table-sm doclinc-report-table"><thead><tr><th>No</th><th>Puskesmas</th><th>Diagnosa</th><th>Jumlah</th></tr></thead><tbody>';
			var grouped = {};
			$.each(rows, function(_, row) {
				var key = safeText(row.nama_puskesmas);
				if (!grouped[key]) {
					grouped[key] = [];
				}
				grouped[key].push(row);
			});
			var no = 1;
			$.each(grouped, function(puskesmas, items) {
				$.each(items, function(idx, row) {
					html += '<tr>';
					if (idx === 0) {
						html += '<td rowspan="' + items.length + '">' + (no++) + '</td>';
						html += '<td rowspan="' + items.length + '" class="font-weight-bold">' + escapeHtml(puskesmas) + '</td>';
					}
					html += '<td>' + escapeHtml(row.diagnosa) + '</td><td>' + escapeHtml(row.jumlah_diagnosa) + '</td></tr>';
				});
			});
			html += '</tbody></table></div></div>';
			$hasil.html(html);
		}

		$('#btnTampilkan').on('click', function(e) {
			e.preventDefault();
			fetchReport(1);
		});

		$('#btnJumlahPasien').on('click', function(e) {
			e.preventDefault();
			fetchReport(2);
		});

		$('#btnJumlahDiagnosa').on('click', function(e) {
			e.preventDefault();
			fetchReport(3);
		});

		$('#btnResetLaporan').on('click', function() {
			$('#formLaporanPerHari')[0].reset();
			$('#tipeBtn').val('');
			$('#hasil-laporan-perhari').html('<div class="doclinc-empty-state">Pilih filter, lalu tampilkan laporan.</div>');
			latestRows = [];
		});

		$(document).on('click', '.btn-detail-laporan', function() {
			var row = latestRows[$(this).data('index')];
			if (!row) {
				return;
			}
			var latest = latestEventInfo(row);
			var fotoHtml = row.foto ? '<img src="' + uploadBaseUrl + encodeURIComponent(row.foto) + '" alt="Foto konsultasi" class="img-fluid rounded border">' : '<div class="doclinc-empty-state">Tidak ada lampiran foto.</div>';
			var body = '<div class="row">';
			body += '<div class="col-lg-6 mb-3"><div class="doclinc-detail-block"><h6>Identitas laporan</h6>';
			body += '<p><strong>ID permintaan:</strong> #' + escapeHtml(row.request_id) + '</p>';
			body += '<p><strong>Warga:</strong> ' + escapeHtml(row.nama_user) + '</p>';
			body += '<p><strong>Puskesmas:</strong> ' + escapeHtml(row.nama_puskesmas) + '</p>';
			body += '<p><strong>Status:</strong> ' + statusBadge(row.request_status) + visitBadge(row.visit_status) + '</p>';
			body += '<p class="mb-0"><strong>PIC:</strong><br>' + picHtml(row) + '</p></div></div>';
			body += '<div class="col-lg-6 mb-3"><div class="doclinc-detail-block"><h6>Aktivitas terakhir</h6>';
			body += '<div class="doclinc-timeline-pill">' + escapeHtml(latest.label) + (latest.time ? '<span>' + escapeHtml(latest.time) + '</span>' : '') + '</div></div></div>';
			body += '<div class="col-lg-7 mb-3"><div class="doclinc-detail-block"><h6>Diagnosis dan saran</h6>';
			body += '<p><strong>Diagnosa:</strong><br>' + escapeHtml(row.diagnosa) + '</p>';
			body += '<p class="mb-0"><strong>Saran:</strong><br>' + escapeHtml(row.saran) + '</p></div></div>';
			body += '<div class="col-lg-5 mb-3"><div class="doclinc-detail-block"><h6>Lampiran foto</h6>' + fotoHtml + '</div></div>';
			body += '</div>';
			$('#laporanDetailTitle').text('Detail laporan #' + safeText(row.request_id));
			$('#laporanDetailBody').html(body);
			$('#laporanDetailModal').modal('show');
		});

		$('#btnCetak').on('click', function() {
			var printArea = document.getElementById('print-area');
			if (!printArea) {
				return;
			}
			var printContents = printArea.innerHTML;
			var originalContents = document.body.innerHTML;
			document.body.innerHTML = printContents;
			window.print();
			document.body.innerHTML = originalContents;
			location.reload();
		});
	});
</script>
