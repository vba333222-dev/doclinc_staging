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

	<form method="post" action="<?= site_url('rekam_medis'); ?>" class="dl-adm-toolbar">
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
			<h2 class="h6 mb-0 font-weight-bold">Daftar layanan</h2>
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
								<th scope="col">Tanggal</th>
								<th scope="col">Permintaan</th>
								<th scope="col">Puskesmas</th>
								<th scope="col">Dokter penanggung jawab</th>
								<th scope="col">Dicatat oleh</th>
								<th scope="col">Jenis layanan</th>
								<th scope="col">Status</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($records as $row): ?>
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
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
