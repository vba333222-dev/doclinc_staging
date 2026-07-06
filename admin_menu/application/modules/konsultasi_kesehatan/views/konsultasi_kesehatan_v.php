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
				return '<span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> Pending</span>';
			case 'Accepted':
				return '<span class="badge badge-primary"><i class="fas fa-check-circle"></i> Accepted</span>';
			case 'Completed':
				return '<span class="badge badge-success"><i class="fas fa-check"></i> Completed</span>';
			case 'Cancelled':
				return '<span class="badge badge-danger"><i class="fas fa-times"></i> Cancelled</span>';
			default:
				return '<span class="badge badge-secondary">Unknown</span>';
		}
	}
}

if (!function_exists('doclinc_admin_puskesmas_label')) {
	function doclinc_admin_puskesmas_label($value)
	{
		$value = trim((string) $value);
		if ($value === '' || strtoupper($value) === 'DEFAULT' || strtoupper($value) === 'PUSKESMAS DEFAULT') {
			return 'Legacy / Belum terklasifikasi';
		}
		return $value;
	}
}
?>

<div class="d-sm-flex align-items-start justify-content-between pt-4 pb-4 px-4 mt-n4 mx-n4 you-are-here">
	<div>
		<h1 class="h3 mb-1 font-weight-bold"><i class="fas fa-fw fa-stethoscope"></i> Monitoring Konsultasi</h1>
		<div class="text-white-50">Pantau status konsultasi, Puskesmas tujuan, PIC personel, dan timeline operasional.</div>
	</div>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-body">
		<form method="get" action="<?= site_url('konsultasi_kesehatan'); ?>">
			<div class="form-row align-items-end">
				<div class="form-group col-md-2">
					<label class="small font-weight-bold text-muted" for="status">Status</label>
					<select name="status" id="status" class="form-control">
						<option value="">Semua</option>
						<?php foreach (array('Pending', 'Accepted', 'Completed', 'Cancelled') as $status): ?>
							<option value="<?= html_escape($status); ?>" <?= (($filters['status'] ?? '') === $status) ? 'selected' : ''; ?>><?= html_escape($status); ?></option>
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
						<option value="__legacy__" <?= (($filters['puskesmas'] ?? '') === '__legacy__') ? 'selected' : ''; ?>>Legacy / Belum terklasifikasi</option>
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
					<label class="small font-weight-bold text-muted" for="keyword">Keyword</label>
					<input type="text" name="keyword" id="keyword" class="form-control" value="<?= html_escape($filters['keyword'] ?? ''); ?>" placeholder="ID, warga, Puskesmas">
				</div>
				<div class="form-group col-md-1">
					<button type="submit" class="btn btn-primary btn-block">Filter</button>
				</div>
			</div>
			<?php if (!empty(array_filter($filters))): ?>
				<a href="<?= site_url('konsultasi_kesehatan'); ?>" class="btn btn-sm btn-light border">Reset filter</a>
			<?php endif; ?>
		</form>
	</div>
</div>

<div class="row">
	<div class="col">
		<div class="card shadow-sm">
			<div class="card-header bg-white d-flex justify-content-between align-items-center">
				<h6 class="m-0 font-weight-bold text-primary">Daftar Konsultasi</h6>
				<span class="badge badge-light border"><?= count($data_konsultasi); ?> data</span>
			</div>
			<div class="card-body table-responsive">
				<table class="table table-bordered table-hover table-sm" id="tbl_konsultasi" style="width:100%" cellspacing="0">
					<thead class="thead-light">
						<tr>
							<th class="text-center" width="4%">No</th>
							<th>Request</th>
							<th>Warga</th>
							<th>Keluhan</th>
							<th>Status</th>
							<th>Puskesmas</th>
							<th>PIC Personel</th>
							<th>Timeline</th>
							<th>Diagnosa / Saran</th>
							<th>Foto</th>
							<th>Akun Puskesmas</th>
						</tr>
					</thead>
					<tbody>
						<?php $no = 0; ?>
						<?php foreach ($data_konsultasi as $row): ?>
							<?php
							$no++;
							$puskesmas_label = doclinc_admin_puskesmas_label($row->puskesmas ?? '');
							$request_description = (string) ($row->request_description ?? '-');
							$location = trim((string) ($row->location ?? ''));
							$date_label = !empty($row->date) && strtotime($row->date) ? date('d-m-Y', strtotime($row->date)) : '-';
							?>
							<tr>
								<td class="text-center"><?= $no; ?></td>
								<td>
									<div class="font-weight-bold">#<?= html_escape($row->request_id ?? '-'); ?></div>
									<small class="text-muted"><?= html_escape($date_label); ?></small>
								</td>
								<td>
									<div class="font-weight-bold"><?= html_escape($row->nama_warga ?? '-'); ?></div>
									<?php if ($location !== ''): ?>
										<small class="text-muted" title="<?= html_escape($location); ?>"><?= html_escape(doclinc_admin_short_text($location, 70)); ?></small>
									<?php endif; ?>
								</td>
								<td title="<?= html_escape($request_description); ?>"><?= html_escape(doclinc_admin_short_text($request_description, 110)); ?></td>
								<td><?= doclinc_admin_status_badge($row->request_status ?? ''); ?></td>
								<td>
									<div class="font-weight-bold"><?= html_escape($puskesmas_label); ?></div>
									<?php if (!empty($row->assigned_puskesmas_code)): ?>
										<small class="text-muted"><?= html_escape($row->assigned_puskesmas_code); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php if (!empty($row->pic_staff_name)): ?>
										<strong><?= html_escape($row->pic_staff_name); ?></strong>
										<?php if (!empty($row->pic_staff_profesi)): ?>
											<br><small class="text-muted"><?= html_escape($row->pic_staff_profesi); ?></small>
										<?php endif; ?>
									<?php else: ?>
										<span class="text-muted">Belum ditentukan</span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$event_labels = array(
										'request_created' => 'Permintaan dibuat',
										'request_accepted' => 'Permintaan diterima',
										'request_cancelled' => 'Permintaan dibatalkan/ditolak',
										'pic_assigned' => 'PIC ditetapkan',
										'pic_changed' => 'PIC diganti',
										'pic_cleared' => 'PIC dibatalkan',
										'visit_started' => 'Perjalanan dimulai',
										'visit_arrived' => 'Tiba di lokasi',
										'visit_in_service' => 'Pelayanan dimulai',
										'visit_completed' => 'Kunjungan selesai',
										'request_completed' => 'Permintaan selesai',
									);
									$request_events = !empty($row->request_events) && is_array($row->request_events) ? $row->request_events : array();
									?>
									<?php if (!empty($request_events)): ?>
										<div class="small">
											<?php foreach ($request_events as $event): ?>
												<?php
												$event_type = isset($event->event_type) ? (string) $event->event_type : '';
												$event_label = !empty($event->message) ? $event->message : (isset($event_labels[$event_type]) ? $event_labels[$event_type] : '');
												$event_time = !empty($event->created_at) && strtotime($event->created_at) ? date('d-m H:i', strtotime($event->created_at)) : '';
												if ($event_label === '') {
													continue;
												}
												?>
												<div class="mb-1">
													<strong><?= html_escape($event_label); ?></strong>
													<?php if ($event_time !== ''): ?>
														<br><span class="text-muted"><?= html_escape($event_time); ?></span>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="text-muted">Belum ada timeline</span>
									<?php endif; ?>
								</td>
								<td>
									<div><strong>Diagnosa:</strong> <?= html_escape(doclinc_admin_short_text($row->diagnosa ?? '-', 70)); ?></div>
									<div><strong>Saran:</strong> <?= html_escape(doclinc_admin_short_text($row->saran ?? '-', 90)); ?></div>
									<?php if (!empty($row->kriteria)): ?>
										<small class="text-muted"><?= html_escape($row->kriteria); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php if (!empty($row->foto)): ?>
										<img src="<?= base_url('../uploads/' . rawurlencode($row->foto)); ?>" alt="Foto konsultasi" class="img-thumbnail" width="80" height="80">
									<?php else: ?>
										<span class="text-muted">-</span>
									<?php endif; ?>
								</td>
								<td><?= html_escape($row->nama_akun_puskesmas ?? '-'); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	$(document).ready(function() {
		$('#tbl_konsultasi').DataTable({
			pageLength: 25,
			order: [],
			language: {
				emptyTable: 'Belum ada konsultasi sesuai filter.'
			}
		});
	});
</script>
