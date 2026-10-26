<?php
$filters = isset($filters) && is_array($filters) ? $filters : array();
$records = isset($records) && is_array($records) ? $records : array();
$summary = isset($summary) && is_array($summary) ? $summary : array();
$puskesmas_options = isset($puskesmas_options) && is_array($puskesmas_options) ? $puskesmas_options : array();
$pagination = isset($pagination) && is_array($pagination) ? $pagination : array();
$pagination += array(
	'page' => 1,
	'pages' => 1,
	'total' => count($records),
	'per_page' => 25,
	'from' => count($records) > 0 ? 1 : 0,
	'to' => count($records),
);
$clinical_ready = !empty($clinical_ready);

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

if (!function_exists('doclinc_record_puskesmas_label')) {
	function doclinc_record_puskesmas_label($value)
	{
		$value = trim((string) $value);
		return $value === '' || in_array(strtoupper($value), array('DEFAULT', 'PUSKESMAS DEFAULT', 'PERLU DICEK'), true)
			? 'Puskesmas belum tersedia'
			: $value;
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
		return 'Status belum tersedia';
	}
}

if (!function_exists('doclinc_record_status_label')) {
	function doclinc_record_status_label($status)
	{
		$labels = array(
			'Pending' => 'Menunggu konfirmasi Puskesmas',
			'Accepted' => 'Diterima',
			'in_service' => 'Sedang ditangani',
			'Completed' => 'Selesai',
			'Cancelled' => 'Dibatalkan',
		);
		return isset($labels[$status]) ? $labels[$status] : 'Status belum tersedia';
	}
}

$summary_cards = array(
	array('label' => 'Total rekam medis', 'value' => (int) ($summary['total'] ?? 0), 'icon' => 'fa-notes-medical', 'tone' => 'primary'),
	array('label' => '30 hari terakhir', 'value' => (int) ($summary['last_30_days'] ?? 0), 'icon' => 'fa-calendar-alt', 'tone' => 'info'),
	array('label' => 'Puskesmas terlibat', 'value' => (int) ($summary['puskesmas_count'] ?? 0), 'icon' => 'fa-hospital', 'tone' => 'success'),
);
?>

<div class="doclinc-record-library">
	<?php
	/* Kepala halaman dari partial bersama. */
	$page_head = array(
		'title' => 'Pemantauan layanan',
		'sub' => 'Ringkasan layanan tanpa isi klinis pasien.',
	);
	$this->load->view('commons/page_head', array('page_head' => $page_head));
	?>

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

	<form method="post" action="<?= site_url('rekam_medis'); ?>" class="dl-adm-toolbar" id="doclincRecordFilter">
		<div class="dl-adm-toolbar-field">
			<label class="dl-adm-toolbar-label" for="keyword">Cari</label>
			<input type="text" class="form-control" id="keyword" name="keyword" value="<?= html_escape($filters['keyword'] ?? ''); ?>" placeholder="Nomor permintaan atau Puskesmas">
		</div>
		<div class="dl-adm-toolbar-field">
			<label class="dl-adm-toolbar-label" for="puskesmas">Puskesmas</label>
			<select class="form-control" id="puskesmas" name="puskesmas">
				<option value="">Semua Puskesmas</option>
				<?php foreach ($puskesmas_options as $option): ?>
					<option value="<?= html_escape($option->kode_pkm ?? ''); ?>" <?= (($filters['puskesmas'] ?? '') === (string) ($option->kode_pkm ?? '')) ? 'selected' : ''; ?>>
						<?= html_escape(doclinc_record_puskesmas_label($option->nama_puskesmas ?? $option->kode_pkm ?? '')); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="dl-adm-toolbar-field">
			<label class="dl-adm-toolbar-label" for="period">Periode</label>
			<select class="form-control" id="period" name="period">
				<?php foreach (array('7' => '7 hari', '30' => '30 hari', '90' => '90 hari', 'all' => 'Semua') as $value => $label): ?>
					<option value="<?= html_escape($value); ?>" <?= (($filters['period'] ?? '30') === $value) ? 'selected' : ''; ?>><?= html_escape($label); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="dl-adm-toolbar-actions">
			<button type="submit" class="btn btn-primary"><i class="fas fa-search" aria-hidden="true"></i> Terapkan filter</button>
		</div>
	</form>

	<div class="card shadow-sm doclinc-record-card">
		<div class="card-header d-flex flex-wrap align-items-center justify-content-between">
			<h2 class="h6 mb-0 font-weight-bold">Arsip rekam medis</h2>
			<span class="doclinc-meta-text">Terbaru dahulu, <?= (int) $pagination['per_page']; ?> rekam per halaman</span>
		</div>
		<div class="card-body">
			<?php if (!$clinical_ready): ?>
				<p class="doclinc-meta-text">Isi klinis tidak tersedia untuk Admin di lingkungan ini; tabel ini memuat ringkasan layanan saja.</p>
			<?php endif; ?>
			<?php if (empty($records)): ?>
				<div class="doclinc-record-empty">Belum ada data rekam medis untuk filter ini.</div>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table doclinc-record-table" id="doclincRecordTable">
						<thead>
							<tr>
								<th scope="col">Tanggal</th>
								<th scope="col">Permintaan</th>
								<th scope="col">Puskesmas</th>
								<th scope="col">Dokter penanggung jawab</th>
								<th scope="col">Dicatat oleh</th>
								<th scope="col">Jenis layanan</th>
								<th scope="col">Status</th>
								<?php if ($clinical_ready): ?>
									<th scope="col">Rekam</th>
								<?php endif; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($records as $row): ?>
								<?php
								$record_payload = array(
									'recordId' => (int) ($row->record_id ?? 0),
									'requestId' => (int) ($row->request_id ?? 0),
									'date' => doclinc_record_date($row->created_at ?? ''),
									'puskesmas' => doclinc_record_puskesmas_label($row->puskesmas_name ?? ''),
									'code' => trim((string) ($row->puskesmas_code ?? '')),
									'doctor' => trim((string) ($row->responsible_doctor_name ?? '')),
									'recordedBy' => trim((string) ($row->recorded_by_name ?? '')),
									'service' => doclinc_record_service_label($row->consultation_mode ?? '', $row->visit_status ?? ''),
									'status' => doclinc_record_status_label($row->request_status ?? ''),
								);
								?>
								<tr>
									<td><?= html_escape(doclinc_record_date($row->created_at ?? '')); ?></td>
									<td>#<?= (int) ($row->request_id ?? 0); ?></td>
									<td>
										<div class="font-weight-bold"><?= html_escape(doclinc_record_puskesmas_label($row->puskesmas_name ?? '')); ?></div>
										<div class="doclinc-meta-text"><?= html_escape($row->puskesmas_code ?? '-'); ?></div>
									</td>
									<td><?= html_escape(trim((string) ($row->responsible_doctor_name ?? '')) !== '' ? $row->responsible_doctor_name : 'Belum tercatat'); ?></td>
									<td><?= html_escape(trim((string) ($row->recorded_by_name ?? '')) !== '' ? $row->recorded_by_name : 'Belum tercatat'); ?></td>
									<td><span class="doclinc-record-badge"><?= html_escape(doclinc_record_service_label($row->consultation_mode ?? '', $row->visit_status ?? '')); ?></span></td>
									<td><span class="doclinc-record-badge"><?= html_escape(doclinc_record_status_label($row->request_status ?? '')); ?></span></td>
									<?php if ($clinical_ready): ?>
										<td>
											<button type="button" class="btn btn-sm btn-outline-secondary doclinc-record-open" data-record="<?= html_escape(json_encode($record_payload, JSON_UNESCAPED_UNICODE)); ?>">Buka rekam</button>
										</td>
									<?php endif; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="d-flex flex-wrap align-items-center justify-content-between mt-3">
					<p class="doclinc-meta-text mb-0" id="doclincRecordPagerInfo">
						Menampilkan <?= (int) $pagination['from']; ?>&ndash;<?= (int) $pagination['to']; ?> dari <?= number_format((int) $pagination['total']); ?> rekam
						&middot; halaman <?= (int) $pagination['page']; ?> dari <?= (int) $pagination['pages']; ?>
					</p>
					<div class="mt-2 mt-sm-0">
						<button type="submit" form="doclincRecordFilter" name="page" value="<?= max(1, (int) $pagination['page'] - 1); ?>" class="btn btn-sm btn-outline-secondary" <?= (int) $pagination['page'] <= 1 ? 'disabled' : ''; ?>>Sebelumnya</button>
						<button type="submit" form="doclincRecordFilter" name="page" value="<?= min((int) $pagination['pages'], (int) $pagination['page'] + 1); ?>" class="btn btn-sm btn-outline-secondary ml-2" <?= (int) $pagination['page'] >= (int) $pagination['pages'] ? 'disabled' : ''; ?>>Berikutnya</button>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php if ($clinical_ready): ?>
		<div class="modal fade" id="doclincRecordModal" tabindex="-1" role="dialog" aria-labelledby="doclincRecordModalTitle" aria-hidden="true" data-endpoint="<?= site_url('rekam_medis/detail'); ?>">
			<div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h2 class="h6 modal-title mb-0" id="doclincRecordModalTitle">Rekam medis</h2>
						<button type="button" class="close" data-dismiss="modal" aria-label="Tutup rincian rekam medis"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body">
						<div class="doclinc-record-detail-grid" id="doclincRecordMeta"></div>
						<div class="mt-3" id="doclincRecordGate">
							<p class="doclinc-meta-text mb-2">Isi klinis (anamnesis, diagnosis, terapi, anjuran) dibuka dengan alasan yang dicatat di penampil audit.</p>
							<div class="dl-adm-toolbar-field" id="doclincRecordReasonWrap" hidden>
								<label class="dl-adm-toolbar-label" for="doclincRecordReason">Alasan akses</label>
								<select class="form-control" id="doclincRecordReason">
									<option value="">Pilih alasan akses</option>
									<option value="clinical_governance">Tata kelola klinis</option>
									<option value="quality_review">Tinjauan mutu layanan</option>
									<option value="incident_review">Penelusuran insiden</option>
									<option value="complaint_followup">Tindak lanjut keluhan</option>
									<option value="legal_regulatory">Permintaan hukum atau regulasi</option>
									<option value="other">Alasan lain</option>
								</select>
								<input type="text" class="form-control mt-2" id="doclincRecordReasonText" placeholder="Keterangan alasan" hidden>
							</div>
							<button type="button" class="btn btn-primary mt-2" id="doclincRecordOpen">Buka isi klinis</button>
						</div>
						<div class="doclinc-record-detail-grid mt-3" id="doclincRecordClinical" hidden></div>
						<p class="doclinc-meta-text mt-2 mb-0" id="doclincRecordNote" role="status"></p>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
<?php if ($clinical_ready): ?>
	<?php $archive_js = 'assets/js/doclinc-record-archive.js'; ?>
	<script src="<?= html_escape(base_url($archive_js) . '?v=' . rawurlencode((string) @filemtime(FCPATH . $archive_js))); ?>"></script>
<?php endif; ?>
