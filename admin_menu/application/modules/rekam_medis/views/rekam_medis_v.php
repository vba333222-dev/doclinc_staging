<?php
$filters = isset($filters) && is_array($filters) ? $filters : array();
$records = isset($records) && is_array($records) ? $records : array();
$summary = isset($summary) && is_array($summary) ? $summary : array();
$puskesmas_options = isset($puskesmas_options) && is_array($puskesmas_options) ? $puskesmas_options : array();

if (!function_exists('doclinc_record_short_text')) {
	function doclinc_record_short_text($text, $limit = 72)
	{
		$text = trim((string) $text);
		if ($text === '') {
			return '-';
		}
		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit - 3, 'UTF-8') . '...' : $text;
		}
		return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
	}
}

if (!function_exists('doclinc_record_date')) {
	function doclinc_record_date($value)
	{
		$timestamp = strtotime((string) $value);
		return $timestamp ? date('d M Y H:i', $timestamp) : '-';
	}
}

if (!function_exists('doclinc_record_service_label')) {
	function doclinc_record_service_label($mode, $visit_status)
	{
		$mode = strtolower(trim((string) $mode));
		$visit_status = strtolower(trim((string) $visit_status));
		if ($mode === 'visit' || in_array($visit_status, array('en_route', 'arrived', 'in_service', 'completed'), true)) {
			return 'Kunjungan';
		}
		if ($mode === 'non_visit') {
			return 'Tanpa kunjungan';
		}
		return 'Perlu dicek';
	}
}

if (!function_exists('doclinc_record_status_label')) {
	function doclinc_record_status_label($status)
	{
		$labels = array(
			'Pending' => 'Menunggu',
			'Accepted' => 'Diterima',
			'Completed' => 'Selesai',
			'Cancelled' => 'Dibatalkan',
		);
		return isset($labels[$status]) ? $labels[$status] : 'Perlu dicek';
	}
}

$summary_cards = array(
	array('label' => 'Total rekam medis', 'value' => (int) ($summary['total'] ?? 0), 'icon' => 'fa-notes-medical', 'tone' => 'primary'),
	array('label' => '30 hari terakhir', 'value' => (int) ($summary['last_30_days'] ?? 0), 'icon' => 'fa-calendar-alt', 'tone' => 'info'),
	array('label' => 'Puskesmas terlibat', 'value' => (int) ($summary['puskesmas_count'] ?? 0), 'icon' => 'fa-hospital', 'tone' => 'success'),
	array('label' => 'Diagnosis terbanyak', 'value' => doclinc_record_short_text($summary['top_diagnosis'] ?? '-', 28), 'icon' => 'fa-chart-bar', 'tone' => 'service', 'meta' => number_format((int) ($summary['top_diagnosis_count'] ?? 0)) . ' kasus'),
);
?>

<div class="doclinc-record-library">
	<div class="d-sm-flex align-items-start justify-content-between pt-4 pb-4 px-4 mt-n4 mx-n4 you-are-here doclinc-page-header">
		<div>
			<h1 class="h3 mb-1 font-weight-bold doclinc-page-title"><i class="fas fa-fw fa-notes-medical"></i> Rekam medis</h1>
			<div class="doclinc-page-subtitle">Rekam medis yang sudah diisi.</div>
		</div>
	</div>

	<div class="doclinc-monitor-summary mb-4">
		<?php foreach ($summary_cards as $card): ?>
			<div class="doclinc-monitor-summary-card doclinc-monitor-summary-<?= html_escape($card['tone']); ?>">
				<div class="doclinc-monitor-summary-icon"><i class="fas <?= html_escape($card['icon']); ?>"></i></div>
				<div>
					<div class="doclinc-monitor-summary-value"><?= is_numeric($card['value']) ? number_format((int) $card['value']) : html_escape($card['value']); ?></div>
					<div class="doclinc-monitor-summary-label"><?= html_escape($card['label']); ?></div>
					<?php if (!empty($card['meta'])): ?>
						<div class="doclinc-meta-text"><?= html_escape($card['meta']); ?></div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="card shadow-sm mb-4 doclinc-filter-card">
		<div class="card-body">
			<form method="post" action="<?= site_url('rekam_medis'); ?>">
				<div class="form-row align-items-end">
					<div class="form-group col-lg-4">
						<label class="small font-weight-bold text-muted" for="keyword">Cari</label>
						<input type="text" class="form-control" id="keyword" name="keyword" value="<?= html_escape($filters['keyword'] ?? ''); ?>" placeholder="Pasien, diagnosis, Puskesmas, ID">
					</div>
					<div class="form-group col-lg-3">
						<label class="small font-weight-bold text-muted" for="puskesmas">Puskesmas</label>
						<select class="form-control" id="puskesmas" name="puskesmas">
							<option value="">Semua Puskesmas</option>
							<?php foreach ($puskesmas_options as $option): ?>
								<option value="<?= html_escape($option->kode_pkm ?? ''); ?>" <?= (($filters['puskesmas'] ?? '') === (string) ($option->kode_pkm ?? '')) ? 'selected' : ''; ?>>
									<?= html_escape($option->nama_puskesmas ?? $option->kode_pkm ?? '-'); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="form-group col-lg-3">
						<label class="small font-weight-bold text-muted" for="period">Periode</label>
						<select class="form-control" id="period" name="period">
							<?php foreach (array('7' => '7 hari', '30' => '30 hari', '90' => '90 hari', 'all' => 'Semua') as $value => $label): ?>
								<option value="<?= html_escape($value); ?>" <?= (($filters['period'] ?? '30') === $value) ? 'selected' : ''; ?>><?= html_escape($label); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="form-group col-lg-2">
						<button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i> Filter</button>
					</div>
				</div>
			</form>
		</div>
	</div>

	<div class="card shadow-sm doclinc-record-card">
		<div class="card-header d-flex flex-wrap align-items-center justify-content-between">
			<h2 class="h6 mb-0 font-weight-bold">Daftar rekam medis</h2>
			<span class="doclinc-meta-text">Maksimal 200 data terbaru</span>
		</div>
		<div class="card-body">
			<?php if (empty($records)): ?>
				<div class="doclinc-record-empty">Belum ada data rekam medis untuk filter ini.</div>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table doclinc-record-table" id="doclincRecordTable">
						<thead>
							<tr>
								<th>Tanggal</th>
								<th>Pasien</th>
								<th>Puskesmas</th>
								<th>Diagnosis</th>
								<th>Jenis layanan</th>
								<th>Status</th>
								<th>Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($records as $row): ?>
								<tr>
									<td><?= html_escape(doclinc_record_date($row->created_at ?? '')); ?></td>
									<td><?= html_escape($row->patient_name ?? '-'); ?></td>
									<td>
										<div class="font-weight-bold"><?= html_escape($row->puskesmas_name ?? 'Perlu dicek'); ?></div>
										<div class="doclinc-meta-text"><?= html_escape($row->puskesmas_code ?? '-'); ?></div>
									</td>
									<td><?= html_escape(doclinc_record_short_text($row->diagnosis ?? '-', 64)); ?></td>
									<td><span class="doclinc-record-badge"><?= html_escape(doclinc_record_service_label($row->consultation_mode ?? '', $row->visit_status ?? '')); ?></span></td>
									<td><span class="doclinc-record-badge"><?= html_escape(doclinc_record_status_label($row->request_status ?? '')); ?></span></td>
									<td>
										<button type="button" class="btn btn-sm btn-outline-primary doclinc-record-detail" data-record-id="<?= (int) ($row->record_id ?? 0); ?>">
											Detail
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="modal fade" id="recordDetailModal" tabindex="-1" role="dialog" aria-labelledby="recordDetailModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<h5 class="modal-title" id="recordDetailModalLabel">Detail rekam medis</h5>
					<div class="doclinc-meta-text">Hanya dapat dilihat.</div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body" id="recordDetailBody">
				<div class="doclinc-record-empty">Memuat detail rekam medis...</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
			</div>
		</div>
	</div>
</div>

<script>
	(function($) {
		function escapeHtml(value) {
			return $('<div>').text(value == null ? '-' : String(value)).html();
		}

		function serviceLabel(mode, visitStatus) {
			mode = String(mode || '').toLowerCase();
			visitStatus = String(visitStatus || '').toLowerCase();
			if (mode === 'visit' || ['en_route', 'arrived', 'in_service', 'completed'].indexOf(visitStatus) !== -1) {
				return 'Kunjungan';
			}
			if (mode === 'non_visit') {
				return 'Tanpa kunjungan';
			}
			return 'Perlu dicek';
		}

		function requestStatusLabel(status) {
			var labels = {
				Pending: 'Menunggu',
				Accepted: 'Diterima',
				Completed: 'Selesai',
				Cancelled: 'Dibatalkan'
			};
			return labels[status] || 'Perlu dicek';
		}

		function visitStatusLabel(status) {
			var labels = {
				not_started: 'Belum dimulai',
				en_route: 'Dalam perjalanan',
				arrived: 'Sudah tiba',
				in_service: 'Ditangani',
				completed: 'Selesai'
			};
			return status ? (labels[status] || 'Perlu dicek') : '-';
		}

		function formatText(value) {
			value = String(value || '').trim();
			return value !== '' ? escapeHtml(value).replace(/\n/g, '<br>') : '-';
		}

		function renderDetail(data) {
			return '' +
				'<div class="doclinc-record-detail-grid">' +
				'<div><span>Pasien</span><strong>' + escapeHtml(data.patient_name) + '</strong></div>' +
				'<div><span>ID permintaan</span><strong>#' + escapeHtml(data.request_id) + '</strong></div>' +
				'<div><span>Puskesmas</span><strong>' + escapeHtml(data.puskesmas_name) + '</strong></div>' +
				'<div><span>Status konsultasi</span><strong>' + escapeHtml(requestStatusLabel(data.request_status)) + '</strong></div>' +
				'<div><span>Jenis layanan</span><strong>' + escapeHtml(serviceLabel(data.consultation_mode, data.visit_status)) + '</strong></div>' +
				'<div><span>Status kunjungan</span><strong>' + escapeHtml(visitStatusLabel(data.visit_status)) + '</strong></div>' +
				'<div><span>Nakes atau dokter</span><strong>' + escapeHtml(data.provider_name || '-') + '</strong></div>' +
				'<div><span>Dibuat</span><strong>' + escapeHtml(data.created_at || '-') + '</strong></div>' +
				'</div>' +
				'<hr>' +
				'<h6 class="font-weight-bold">Diagnosis</h6><p>' + formatText(data.diagnosis) + '</p>' +
				'<h6 class="font-weight-bold">Tindakan dan terapi</h6><p>' + formatText(data.treatment) + '</p>' +
				'<h6 class="font-weight-bold">Saran dan catatan</h6><p>' + formatText(data.recommendations || data.notes) + '</p>';
		}

		$(function() {
			if ($.fn.DataTable && $('#doclincRecordTable').length) {
				$('#doclincRecordTable').DataTable({
					pageLength: 25,
					order: [[0, 'desc']],
					language: {
						search: 'Cari cepat:',
						lengthMenu: 'Tampilkan _MENU_ data',
						zeroRecords: 'Tidak ada rekam medis yang cocok',
						info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
						infoEmpty: 'Tidak ada data',
						paginate: {
							previous: 'Sebelumnya',
							next: 'Berikutnya'
						}
					}
				});
			}

			$(document).on('click', '.doclinc-record-detail', function() {
				var recordId = $(this).data('record-id');
				$('#recordDetailBody').html('<div class="doclinc-record-empty">Memuat detail rekam medis...</div>');
				$('#recordDetailModal').modal('show');

				$.ajax({
					url: '<?= site_url('rekam_medis/detail'); ?>',
					type: 'POST',
					dataType: 'json',
					data: { record_id: recordId },
					success: function(response) {
						if (response && response.success && response.data) {
							$('#recordDetailBody').html(renderDetail(response.data));
							return;
						}
						$('#recordDetailBody').html('<div class="doclinc-record-empty">Detail rekam medis tidak tersedia.</div>');
					},
					error: function() {
						$('#recordDetailBody').html('<div class="doclinc-record-empty">Detail rekam medis belum dapat dimuat.</div>');
					}
				});
			});
		});
	})(jQuery);
</script>
