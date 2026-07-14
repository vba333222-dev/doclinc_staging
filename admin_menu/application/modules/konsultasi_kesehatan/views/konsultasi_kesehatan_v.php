<?php
$filters = isset($filters) && is_array($filters) ? $filters : array();
$puskesmas_options = isset($puskesmas_options) && is_array($puskesmas_options) ? $puskesmas_options : array();
$data_konsultasi = isset($data_konsultasi) && is_array($data_konsultasi) ? $data_konsultasi : array();

if (!function_exists('doclinc_admin_short_text')) {
	function doclinc_admin_short_text($text, $limit = 90)
	{
		$text = trim((string) $text);
		if ($text === '') {
			return '-';
		}
		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 3) . '...' : $text;
		}
		return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
	}
}

if (!function_exists('doclinc_admin_status_badge')) {
	function doclinc_admin_status_badge($status)
	{
		switch ((string) $status) {
			case 'Pending':
				return '<span class="badge badge-warning doclinc-status-badge"><i class="fas fa-hourglass-half"></i> Menunggu</span>';
			case 'Accepted':
				return '<span class="badge badge-primary doclinc-status-badge"><i class="fas fa-check-circle"></i> Diterima</span>';
			case 'Completed':
				return '<span class="badge badge-success doclinc-status-badge"><i class="fas fa-check"></i> Selesai</span>';
			case 'Cancelled':
				return '<span class="badge badge-danger doclinc-status-badge"><i class="fas fa-times"></i> Dibatalkan</span>';
			default:
				return '<span class="badge badge-secondary doclinc-status-badge">Perlu dicek</span>';
		}
	}
}

if (!function_exists('doclinc_admin_visit_badge')) {
	function doclinc_admin_visit_badge($status)
	{
		$status = trim((string) $status);
		if ($status === '') {
			return '<span class="badge badge-light border doclinc-status-badge">Belum ada kunjungan</span>';
		}
		$labels = array(
			'not_started' => 'Belum dimulai',
			'en_route' => 'Dalam perjalanan',
			'arrived' => 'Sudah tiba',
			'in_service' => 'Ditangani',
			'completed' => 'Selesai',
		);
		return '<span class="badge badge-info doclinc-status-badge">' . html_escape(isset($labels[$status]) ? $labels[$status] : 'Perlu dicek') . '</span>';
	}
}

if (!function_exists('doclinc_admin_event_label')) {
	function doclinc_admin_event_label($event_type)
	{
		$labels = array(
			'request_created' => 'Konsultasi baru dibuat',
			'request_accepted' => 'Konsultasi diterima',
			'request_cancelled' => 'Konsultasi dibatalkan',
			'pic_assigned' => 'PIC ditugaskan',
			'pic_changed' => 'PIC diganti',
			'pic_cleared' => 'PIC dilepas',
			'visit_started' => 'Kunjungan dimulai',
			'visit_arrived' => 'PIC tiba di lokasi',
			'visit_in_service' => 'Sedang ditangani',
			'visit_completed' => 'Kunjungan selesai',
			'request_completed' => 'Konsultasi selesai',
		);
		return isset($labels[$event_type]) ? $labels[$event_type] : 'Konsultasi diperbarui';
	}
}

if (!function_exists('doclinc_admin_puskesmas_label')) {
	function doclinc_admin_puskesmas_label($value)
	{
		$value = trim((string) $value);
		if ($value === '' || strtoupper($value) === 'DEFAULT' || strtoupper($value) === 'PUSKESMAS DEFAULT') {
			return 'Perlu dicek';
		}
		return $value;
	}
}

$summary = array('total' => count($data_konsultasi), 'Pending' => 0, 'Accepted' => 0, 'Completed' => 0, 'legacy' => 0, 'in_progress' => 0);
$has_visit_status = false;
foreach ($data_konsultasi as $row) {
	$status = isset($row->request_status) ? (string) $row->request_status : '';
	if (isset($summary[$status])) {
		$summary[$status]++;
	}
	$puskesmas_label = doclinc_admin_puskesmas_label($row->puskesmas ?? '');
	if ($puskesmas_label === 'Perlu dicek') {
		$summary['legacy']++;
	}
	$visit_status = trim((string) ($row->visit_status ?? ''));
	if ($visit_status !== '') {
		$has_visit_status = true;
		if ($status !== 'Completed') {
			$summary['in_progress']++;
		}
	}
}
if (!$has_visit_status) {
	$summary['in_progress'] = $summary['Accepted'];
}
$summary_cards = array(
	array('label' => 'Total konsultasi', 'value' => $summary['total'], 'icon' => 'fa-list-alt', 'tone' => 'primary'),
	array('label' => 'Menunggu', 'value' => $summary['Pending'], 'icon' => 'fa-hourglass-half', 'tone' => 'warning'),
	array('label' => 'Diterima', 'value' => $summary['Accepted'], 'icon' => 'fa-check-circle', 'tone' => 'info'),
	array('label' => 'Ditangani', 'value' => $summary['in_progress'], 'icon' => 'fa-stethoscope', 'tone' => 'service'),
	array('label' => 'Selesai', 'value' => $summary['Completed'], 'icon' => 'fa-clipboard-check', 'tone' => 'success'),
	array('label' => 'Perlu dicek', 'value' => $summary['legacy'], 'icon' => 'fa-exclamation-triangle', 'tone' => 'legacy'),
);
?>

<div class="d-sm-flex align-items-start justify-content-between pt-4 pb-4 px-4 mt-n4 mx-n4 you-are-here doclinc-page-header">
	<div>
		<h1 class="h3 mb-1 font-weight-bold doclinc-page-title"><i class="fas fa-fw fa-stethoscope"></i> Monitoring konsultasi</h1>
		<div class="doclinc-page-subtitle">Pantau konsultasi dan riwayatnya.</div>
	</div>
</div>

<div class="doclinc-monitor-summary mb-4">
	<?php foreach ($summary_cards as $card): ?>
		<div class="doclinc-monitor-summary-card doclinc-monitor-summary-<?= html_escape($card['tone']); ?>">
			<div class="doclinc-monitor-summary-icon"><i class="fas <?= html_escape($card['icon']); ?>"></i></div>
			<div>
				<div class="doclinc-monitor-summary-value"><?= number_format((int) $card['value']); ?></div>
				<div class="doclinc-monitor-summary-label"><?= html_escape($card['label']); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<div class="card shadow-sm mb-4 doclinc-filter-card">
	<div class="card-body">
		<form method="get" action="<?= site_url('konsultasi_kesehatan'); ?>">
			<div class="form-row align-items-end">
				<div class="form-group col-md-2">
					<label class="small font-weight-bold text-muted" for="status">Status</label>
					<select name="status" id="status" class="form-control">
						<option value="">Semua</option>
						<?php foreach (array('Pending' => 'Menunggu', 'Accepted' => 'Diterima', 'Completed' => 'Selesai', 'Cancelled' => 'Dibatalkan') as $status => $status_label): ?>
							<option value="<?= html_escape($status); ?>" <?= (($filters['status'] ?? '') === $status) ? 'selected' : ''; ?>><?= html_escape($status_label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group col-md-3">
					<label class="small font-weight-bold text-muted" for="puskesmas">Puskesmas</label>
					<select name="puskesmas" id="puskesmas" class="form-control">
						<option value="">Semua Puskesmas</option>
						<?php foreach ($puskesmas_options as $puskesmas): ?>
							<option value="<?= html_escape($puskesmas->kode_pkm); ?>" <?= (($filters['puskesmas'] ?? '') === (string) $puskesmas->kode_pkm) ? 'selected' : ''; ?>>
								<?= html_escape($puskesmas->nama_puskesmas); ?>
							</option>
						<?php endforeach; ?>
						<option value="__legacy__" <?= (($filters['puskesmas'] ?? '') === '__legacy__') ? 'selected' : ''; ?>>Perlu dicek</option>
					</select>
				</div>
				<div class="form-group col-md-2">
					<label class="small font-weight-bold text-muted" for="date_from">Dari</label>
					<input type="date" name="date_from" id="date_from" class="form-control" value="<?= html_escape($filters['date_from'] ?? ''); ?>">
				</div>
				<div class="form-group col-md-2">
					<label class="small font-weight-bold text-muted" for="date_to">Sampai</label>
					<input type="date" name="date_to" id="date_to" class="form-control" value="<?= html_escape($filters['date_to'] ?? ''); ?>">
				</div>
				<div class="form-group col-md-2">
					<label class="small font-weight-bold text-muted" for="keyword">Kata kunci</label>
					<input type="text" name="keyword" id="keyword" class="form-control" value="<?= html_escape($filters['keyword'] ?? ''); ?>" placeholder="ID, warga, Puskesmas">
				</div>
				<div class="form-group col-md-1">
					<button type="submit" class="btn btn-primary btn-block">Terapkan filter</button>
				</div>
			</div>
			<div class="d-flex justify-content-between align-items-center flex-wrap">
				<a href="<?= site_url('konsultasi_kesehatan'); ?>" class="btn btn-sm btn-light border">Hapus filter</a>
			</div>
		</form>
	</div>
</div>

<div class="row">
	<div class="col">
		<div class="card shadow-sm doclinc-table-card">
			<div class="card-header bg-white d-flex justify-content-between align-items-center">
				<h6 class="m-0 font-weight-bold text-primary">Daftar konsultasi</h6>
				<span class="badge badge-light border"><?= count($data_konsultasi); ?> data</span>
			</div>
			<div class="card-body table-responsive">
				<table class="table table-hover doclinc-monitor-table" id="tbl_konsultasi" style="width:100%" cellspacing="0">
					<thead>
						<tr>
							<th>Permintaan</th>
							<th>Warga</th>
							<th>Puskesmas dan PIC</th>
							<th>Status</th>
							<th>Riwayat</th>
							<th>Diagnosis dan saran</th>
							<th>Lampiran</th>
							<th>Detail</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($data_konsultasi as $row): ?>
							<?php
							$request_id = isset($row->request_id) ? (int) $row->request_id : 0;
							$puskesmas_label = doclinc_admin_puskesmas_label($row->puskesmas ?? '');
							$request_description = (string) ($row->request_description ?? '-');
							$location = trim((string) ($row->location_detail ?? '')) !== '' ? trim((string) $row->location_detail) : trim((string) ($row->location ?? ''));
							$date_label = !empty($row->date) && strtotime($row->date) ? date('d M Y', strtotime($row->date)) : '-';
							$request_events = !empty($row->request_events) && is_array($row->request_events) ? $row->request_events : array();
							$latest_event = !empty($request_events) ? $request_events[0] : null;
							$latest_event_label = $latest_event ? (!empty($latest_event->message) ? $latest_event->message : doclinc_admin_event_label($latest_event->event_type ?? '')) : '';
							$latest_event_time = $latest_event && !empty($latest_event->created_at) && strtotime($latest_event->created_at) ? date('d M H:i', strtotime($latest_event->created_at)) : '';
							$pic_name = trim((string) ($row->pic_staff_name ?? ''));
							$pic_profesi = trim((string) ($row->pic_staff_profesi ?? ''));
							?>
							<tr class="doclinc-request-card">
								<td>
									<div class="font-weight-bold text-nowrap">#<?= html_escape($request_id ?: '-'); ?></div>
									<div class="doclinc-request-meta"><?= html_escape($date_label); ?></div>
								</td>
								<td>
									<div class="font-weight-bold"><?= html_escape($row->nama_warga ?? '-'); ?></div>
									<div class="doclinc-request-meta" title="<?= html_escape($location); ?>"><?= html_escape(doclinc_admin_short_text($location, 72)); ?></div>
								</td>
								<td>
									<div class="font-weight-bold"><?= html_escape($puskesmas_label); ?></div>
									<?php if (!empty($row->assigned_puskesmas_code)): ?>
										<div class="doclinc-request-meta"><?= html_escape($row->assigned_puskesmas_code); ?></div>
									<?php endif; ?>
									<div class="mt-2">
										<?php if ($pic_name !== ''): ?>
											<span class="doclinc-pic-chip"><i class="fas fa-user-check"></i> PIC: <?= html_escape($pic_name); ?></span>
											<?php if ($pic_profesi !== ''): ?>
												<div class="doclinc-request-meta mt-1"><?= html_escape($pic_profesi); ?></div>
											<?php endif; ?>
										<?php else: ?>
											<span class="doclinc-pic-chip doclinc-pic-empty">PIC belum ditentukan</span>
										<?php endif; ?>
									</div>
								</td>
								<td>
									<?= doclinc_admin_status_badge($row->request_status ?? ''); ?>
									<div class="mt-2"><?= doclinc_admin_visit_badge($row->visit_status ?? ''); ?></div>
								</td>
								<td>
									<?php if ($latest_event): ?>
										<div class="doclinc-timeline-pill">
											<strong><?= html_escape(doclinc_admin_short_text($latest_event_label, 70)); ?></strong>
											<?php if ($latest_event_time !== ''): ?>
												<span><?= html_escape($latest_event_time); ?></span>
											<?php endif; ?>
										</div>
									<?php else: ?>
										<span class="text-muted">Belum ada riwayat</span>
									<?php endif; ?>
								</td>
								<td>
									<div><strong>Diagnosa:</strong> <?= html_escape(doclinc_admin_short_text($row->diagnosa ?? '-', 62)); ?></div>
									<div class="doclinc-request-meta"><strong>Saran:</strong> <?= html_escape(doclinc_admin_short_text($row->saran ?? '-', 78)); ?></div>
								</td>
								<td>
									<?php if (!empty($row->foto)): ?>
										<img src="<?= base_url('../uploads/' . rawurlencode($row->foto)); ?>" alt="Foto konsultasi" class="doclinc-monitor-thumb">
									<?php else: ?>
										<span class="text-muted">-</span>
									<?php endif; ?>
								</td>
								<td>
									<button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#detailKonsultasi<?= $request_id; ?>">
										Lihat detail
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if (empty($data_konsultasi)): ?>
					<div class="doclinc-empty-state">
						Belum ada konsultasi sesuai filter.
						<div class="mt-2"><a href="<?= site_url('konsultasi_kesehatan'); ?>" class="btn btn-sm btn-light border">Hapus filter</a></div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php foreach ($data_konsultasi as $row): ?>
	<?php
	$request_id = isset($row->request_id) ? (int) $row->request_id : 0;
	$puskesmas_label = doclinc_admin_puskesmas_label($row->puskesmas ?? '');
	$location = trim((string) ($row->location_detail ?? '')) !== '' ? trim((string) $row->location_detail) : trim((string) ($row->location ?? ''));
	$request_events = !empty($row->request_events) && is_array($row->request_events) ? $row->request_events : array();
	$pic_name = trim((string) ($row->pic_staff_name ?? ''));
	?>
	<div class="modal fade doclinc-detail-modal" id="detailKonsultasi<?= $request_id; ?>" tabindex="-1" role="dialog" aria-labelledby="detailKonsultasiLabel<?= $request_id; ?>" aria-hidden="true">
		<div class="modal-dialog modal-xl" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="detailKonsultasiLabel<?= $request_id; ?>">Detail konsultasi #<?= html_escape($request_id ?: '-'); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="row">
						<div class="col-lg-5 mb-3">
							<div class="doclinc-detail-block">
								<h6>Koordinasi</h6>
								<dl class="row mb-0">
									<dt class="col-sm-4">ID permintaan</dt>
									<dd class="col-sm-8">#<?= html_escape($request_id ?: '-'); ?></dd>
									<dt class="col-sm-4">Warga</dt>
									<dd class="col-sm-8"><?= html_escape($row->nama_warga ?? '-'); ?></dd>
									<dt class="col-sm-4">Alamat</dt>
									<dd class="col-sm-8"><?= html_escape($location !== '' ? $location : '-'); ?></dd>
									<dt class="col-sm-4">Puskesmas</dt>
									<dd class="col-sm-8"><?= html_escape($puskesmas_label); ?></dd>
									<dt class="col-sm-4">PIC</dt>
									<dd class="col-sm-8"><?= html_escape($pic_name !== '' ? $pic_name : 'Belum ditentukan'); ?></dd>
								</dl>
							</div>
							<div class="doclinc-detail-block mt-3">
								<h6>Status</h6>
								<?= doclinc_admin_status_badge($row->request_status ?? ''); ?>
								<span class="ml-2"><?= doclinc_admin_visit_badge($row->visit_status ?? ''); ?></span>
							</div>
							<div class="doclinc-detail-block mt-3">
								<h6>Diagnosis dan saran</h6>
								<p><strong>Diagnosa:</strong><br><?= nl2br(html_escape(trim((string) ($row->diagnosa ?? '')) !== '' ? $row->diagnosa : '-')); ?></p>
								<p class="mb-0"><strong>Saran:</strong><br><?= nl2br(html_escape(trim((string) ($row->saran ?? '')) !== '' ? $row->saran : '-')); ?></p>
							</div>
						</div>
						<div class="col-lg-4 mb-3">
							<div class="doclinc-detail-block h-100">
								<h6>Riwayat</h6>
								<?php if (empty($request_events)): ?>
									<div class="doclinc-empty-state">Belum ada riwayat.</div>
								<?php else: ?>
									<div class="doclinc-timeline-list">
										<?php foreach ($request_events as $event): ?>
											<?php
											$event_label = !empty($event->message) ? $event->message : doclinc_admin_event_label($event->event_type ?? '');
											$event_time = !empty($event->created_at) && strtotime($event->created_at) ? date('d M Y H:i', strtotime($event->created_at)) : '-';
											?>
											<div class="doclinc-timeline-row">
												<div class="font-weight-bold"><?= html_escape($event_label); ?></div>
												<div class="doclinc-request-meta"><?= html_escape($event_time); ?></div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
						<div class="col-lg-3 mb-3">
							<div class="doclinc-detail-block h-100">
								<h6>Lampiran</h6>
								<?php if (!empty($row->foto)): ?>
									<img src="<?= base_url('../uploads/' . rawurlencode($row->foto)); ?>" alt="Foto konsultasi" class="img-fluid rounded border">
								<?php else: ?>
									<div class="doclinc-empty-state">Tidak ada lampiran foto.</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
				</div>
			</div>
		</div>
	</div>
<?php endforeach; ?>

<script type="text/javascript">
	$(document).ready(function() {
		$('#tbl_konsultasi').DataTable({
			pageLength: 25,
			order: [],
			language: {
				lengthMenu: 'Tampilkan _MENU_ data',
				search: 'Cari:',
				emptyTable: 'Belum ada konsultasi sesuai filter.',
				zeroRecords: 'Belum ada konsultasi sesuai filter.',
				info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
				infoEmpty: 'Menampilkan 0 data',
				infoFiltered: '(difilter dari _MAX_ total data)',
				paginate: {
					previous: 'Sebelumnya',
					next: 'Berikutnya'
				}
			}
		});
	});
</script>
