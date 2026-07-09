<?php
$filters = isset($filters) && is_array($filters) ? $filters : array();
$staff_rows = isset($staff_rows) && is_array($staff_rows) ? $staff_rows : array();
$puskesmas_options = isset($puskesmas_options) && is_array($puskesmas_options) ? $puskesmas_options : array();
$form_mode = isset($form_mode) ? (string) $form_mode : '';
$form_staff = isset($form_staff) ? $form_staff : null;
$is_form = in_array($form_mode, array('create', 'edit'), true);
$form_action = $form_mode === 'edit' && $form_staff ? site_url('kelola_staff_puskesmas/update/' . (int) $form_staff->staff_id) : site_url('kelola_staff_puskesmas/store');
$form_title = $form_mode === 'edit' ? 'Edit Staff Puskesmas' : 'Tambah Staff Puskesmas';
$form_values = array(
	'kode_pkm' => $form_staff ? (string) $form_staff->kode_pkm : '',
	'nama' => $form_staff ? (string) $form_staff->nama : '',
	'profesi' => $form_staff ? (string) $form_staff->profesi : '',
	'no_hp' => $form_staff ? (string) $form_staff->no_hp : '',
	'nomor_sip' => $form_staff ? (string) $form_staff->nomor_sip : '',
	'status' => $form_staff ? (string) $form_staff->status : 'aktif',
);
?>
<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here doclinc-page-header">
	<div>
		<h1 class="h3 mb-1 font-weight-bold doclinc-page-title"><i class="fas fa-fw fa-users-cog"></i> Staff Puskesmas</h1>
		<div class="text-white-50 doclinc-page-subtitle">Kelola personel Puskesmas yang dapat ditetapkan sebagai PIC layanan.</div>
	</div>
</div>

<div class="container-fluid">
	<?php if ($this->session->flashdata('success')): ?>
		<div class="alert alert-success shadow-sm"><?= html_escape($this->session->flashdata('success')); ?></div>
	<?php endif; ?>
	<?php if ($this->session->flashdata('error')): ?>
		<div class="alert alert-danger shadow-sm"><?= html_escape($this->session->flashdata('error')); ?></div>
	<?php endif; ?>

	<?php if (empty($table_ready)): ?>
		<div class="alert alert-warning shadow-sm">Tabel staff Puskesmas belum tersedia.</div>
	<?php else: ?>
		<div class="doclinc-staff-note shadow-sm">
			<i class="fas fa-info-circle"></i>
			<span>Data staff digunakan sebagai personel/PIC layanan. Ini tidak otomatis membuat akun login.</span>
		</div>
		<?php if ($is_form): ?>
			<div class="card shadow mb-4 doclinc-filter-card">
				<div class="card-header py-3 d-flex align-items-center justify-content-between">
					<h6 class="m-0 font-weight-bold text-primary"><?= html_escape($form_title); ?></h6>
					<a href="<?= site_url('kelola_staff_puskesmas'); ?>" class="btn btn-sm btn-light rounded-pill border">Batal</a>
				</div>
				<form action="<?= $form_action; ?>" method="post">
					<div class="card-body bg-light">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Puskesmas</label>
									<select class="form-control rounded-pill border-info" name="kode_pkm" required>
										<option value="">Pilih Puskesmas</option>
										<?php foreach ($puskesmas_options as $puskesmas): ?>
											<option value="<?= html_escape($puskesmas->kode_pkm); ?>" <?= $form_values['kode_pkm'] === (string) $puskesmas->kode_pkm ? 'selected' : ''; ?>>
												<?= html_escape($puskesmas->nama_puskesmas); ?> (<?= html_escape($puskesmas->kode_pkm); ?>)
											</option>
										<?php endforeach; ?>
									</select>
									<small class="form-text text-muted">Pilih Puskesmas aktif sebagai unit koordinasi staff.</small>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Nama Staff</label>
									<input type="text" class="form-control rounded-pill border-info" name="nama" value="<?= html_escape($form_values['nama']); ?>" placeholder="Nama personel" required>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Profesi</label>
									<input type="text" class="form-control rounded-pill border-info" name="profesi" value="<?= html_escape($form_values['profesi']); ?>" placeholder="Profesi atau peran layanan">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">No HP</label>
									<input type="text" class="form-control rounded-pill border-info" name="no_hp" value="<?= html_escape($form_values['no_hp']); ?>" placeholder="Nomor kontak staff">
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Nomor SIP</label>
									<input type="text" class="form-control rounded-pill border-info" name="nomor_sip" value="<?= html_escape($form_values['nomor_sip']); ?>" placeholder="Nomor SIP jika ada">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Status</label>
									<select class="form-control rounded-pill border-info" name="status">
										<option value="aktif" <?= $form_values['status'] === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
										<option value="nonaktif" <?= $form_values['status'] === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
									</select>
									<small class="form-text text-muted">Staff aktif dapat dipilih sebagai PIC untuk Puskesmas yang sama.</small>
								</div>
							</div>
						</div>
					</div>
					<div class="card-footer bg-white border-0 text-right">
						<a href="<?= site_url('kelola_staff_puskesmas'); ?>" class="btn btn-light rounded-pill px-4 border">Batal</a>
						<button type="submit" class="btn btn-success rounded-pill px-4">Simpan</button>
					</div>
				</form>
			</div>
		<?php endif; ?>

		<div class="card shadow mb-4 doclinc-table-card">
			<div class="card-header py-3 d-flex align-items-center justify-content-between">
				<h6 class="m-0 font-weight-bold text-primary">Daftar Staff Puskesmas</h6>
				<a href="<?= site_url('kelola_staff_puskesmas/create'); ?>" class="btn btn-sm btn-success shadow-sm rounded-pill">
					<i class="fas fa-plus-circle mr-1"></i> Tambah Staff Puskesmas
				</a>
			</div>
			<div class="card-body">
				<form method="get" action="<?= site_url('kelola_staff_puskesmas'); ?>" class="mb-3" id="staffPuskesmasFilterForm" data-filter-url="<?= html_escape(site_url('kelola_staff_puskesmas')); ?>">
					<div class="row">
						<div class="col-md-4 mb-2">
							<select name="kode_pkm" class="form-control rounded-pill">
								<option value="">Semua Puskesmas</option>
								<?php foreach ($puskesmas_options as $puskesmas): ?>
									<option value="<?= html_escape($puskesmas->kode_pkm); ?>" <?= (isset($filters['kode_pkm']) && $filters['kode_pkm'] === (string) $puskesmas->kode_pkm) ? 'selected' : ''; ?>>
										<?= html_escape($puskesmas->nama_puskesmas); ?> (<?= html_escape($puskesmas->kode_pkm); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-3 mb-2">
							<select name="status" class="form-control rounded-pill">
								<option value="">Semua Status</option>
								<option value="aktif" <?= (isset($filters['status']) && $filters['status'] === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
								<option value="nonaktif" <?= (isset($filters['status']) && $filters['status'] === 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
							</select>
						</div>
						<div class="col-md-3 mb-2">
							<input type="text" name="keyword" class="form-control rounded-pill" value="<?= html_escape($filters['keyword'] ?? ''); ?>" placeholder="Nama, no HP, profesi, SIP">
						</div>
						<div class="col-md-2 mb-2">
							<button type="submit" class="btn btn-info rounded-pill btn-block">Filter</button>
						</div>
					</div>
				</form>

				<div class="table-responsive">
					<table class="table table-bordered doclinc-staff-table" id="tbl_staff_puskesmas" width="100%" cellspacing="0">
						<thead>
							<tr class="bg-info text-black">
								<th>Staff</th>
								<th>Puskesmas</th>
								<th>Profesi</th>
								<th>Kontak</th>
								<th>Akun terkait</th>
								<th>Status</th>
								<th class="text-center">Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php if (!empty($staff_rows)): ?>
								<?php foreach ($staff_rows as $row): ?>
									<?php
									$akun_label = 'Tidak ada akun terkait';
									if (!empty($row->akun_nama) || !empty($row->akun_username) || !empty($row->akun_email)) {
										$akun_label = trim((string) ($row->akun_nama ?: $row->akun_username ?: $row->akun_email));
									}
									?>
									<tr>
										<td title="<?= html_escape($row->nama ?? '-'); ?>">
											<div class="font-weight-bold"><?= html_escape($row->nama ?? '-'); ?></div>
											<div class="small text-muted">Staff Puskesmas</div>
										</td>
										<td>
											<span class="doclinc-staff-chip"><i class="fas fa-clinic-medical"></i> <?= html_escape($row->kode_pkm ?? '-'); ?></span>
											<div class="small text-muted mt-1"><?= html_escape($row->nama_puskesmas ?? 'Puskesmas tidak ditemukan'); ?></div>
										</td>
										<td title="<?= html_escape($row->profesi ?? '-'); ?>">
											<?= html_escape($row->profesi ?: '-'); ?>
											<?php if (!empty($row->nomor_sip)): ?>
												<div class="small text-muted">SIP: <?= html_escape($row->nomor_sip); ?></div>
											<?php endif; ?>
										</td>
										<td>
											<?= html_escape($row->no_hp ?: '-'); ?>
										</td>
										<td>
											<div><?= html_escape($akun_label); ?></div>
											<?php if (!empty($row->akun_username) && $akun_label !== $row->akun_username): ?>
												<div class="small text-muted"><?= html_escape($row->akun_username); ?></div>
											<?php endif; ?>
											<?php if (!empty($row->akun_email)): ?>
												<div class="small text-muted"><?= html_escape($row->akun_email); ?></div>
											<?php endif; ?>
										</td>
										<td>
											<span class="doclinc-staff-chip <?= ($row->status ?? '') === 'aktif' ? 'doclinc-staff-chip-active' : 'doclinc-staff-chip-inactive'; ?>">
												<?= ($row->status ?? '') === 'aktif' ? 'Aktif' : 'Nonaktif'; ?>
											</span>
										</td>
										<td class="text-center">
											<div class="doclinc-action-stack">
												<a href="<?= site_url('kelola_staff_puskesmas/edit/' . (int) $row->staff_id); ?>" class="btn btn-info btn-sm rounded-pill">
													<i class="fas fa-edit"></i> Edit
												</a>
												<?php if (($row->status ?? '') === 'aktif'): ?>
													<form action="<?= site_url('kelola_staff_puskesmas/deactivate/' . (int) $row->staff_id); ?>" method="post">
														<button type="submit" class="btn btn-secondary btn-sm rounded-pill" onclick="return confirm('Nonaktifkan staff ini? Data staff, riwayat, dan assignment PIC tidak akan dihapus.');">
															<i class="fas fa-ban"></i> Nonaktifkan
														</button>
													</form>
												<?php else: ?>
													<form action="<?= site_url('kelola_staff_puskesmas/activate/' . (int) $row->staff_id); ?>" method="post">
														<button type="submit" class="btn btn-success btn-sm rounded-pill" onclick="return confirm('Aktifkan staff ini?');">
															<i class="fas fa-check"></i> Aktifkan
														</button>
													</form>
												<?php endif; ?>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<script type="text/javascript">
	$(document).ready(function() {
		$('#staffPuskesmasFilterForm').on('submit', function(event) {
			event.preventDefault();
			var baseUrl = $(this).data('filter-url');
			var params = $(this).serializeArray().filter(function(item) {
				return item.value !== '';
			});
			var query = $.param(params);
			window.location.href = baseUrl + (query ? '&' + query : '');
		});

		$('#tbl_staff_puskesmas').DataTable({
			searching: false,
			language: {
				lengthMenu: 'Tampilkan _MENU_ data',
				search: 'Cari:',
				emptyTable: 'Belum ada staff Puskesmas sesuai filter.',
				zeroRecords: 'Tidak ada data sesuai filter',
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
