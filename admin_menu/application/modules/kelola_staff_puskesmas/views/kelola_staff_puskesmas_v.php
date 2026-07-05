<?php
$filters = isset($filters) && is_array($filters) ? $filters : array();
$staff_rows = isset($staff_rows) && is_array($staff_rows) ? $staff_rows : array();
$puskesmas_options = isset($puskesmas_options) && is_array($puskesmas_options) ? $puskesmas_options : array();
$form_mode = isset($form_mode) ? (string) $form_mode : '';
$form_staff = isset($form_staff) ? $form_staff : null;
$is_form = in_array($form_mode, array('create', 'edit'), true);
$form_action = $form_mode === 'edit' && $form_staff ? site_url('kelola_staff_puskesmas/update/' . (int) $form_staff->staff_id) : site_url('kelola_staff_puskesmas/store');
$form_title = $form_mode === 'edit' ? 'Edit Staff Puskesmas' : 'Tambah Staff';
$form_values = array(
	'kode_pkm' => $form_staff ? (string) $form_staff->kode_pkm : '',
	'nama' => $form_staff ? (string) $form_staff->nama : '',
	'profesi' => $form_staff ? (string) $form_staff->profesi : '',
	'no_hp' => $form_staff ? (string) $form_staff->no_hp : '',
	'nomor_sip' => $form_staff ? (string) $form_staff->nomor_sip : '',
	'status' => $form_staff ? (string) $form_staff->status : 'aktif',
);
?>
<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<div>
		<h1 class="h3 mb-1 font-weight-bold"><i class="fas fa-fw fa-users-cog"></i> Kelola Staff Puskesmas</h1>
		<div class="text-white-50">Data personel yang berada di bawah koordinasi Puskesmas.</div>
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
		<div class="alert alert-warning shadow-sm">Tabel personel Puskesmas belum tersedia.</div>
	<?php else: ?>
		<?php if ($is_form): ?>
			<div class="card shadow mb-4">
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
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Nama</label>
									<input type="text" class="form-control rounded-pill border-info" name="nama" value="<?= html_escape($form_values['nama']); ?>" required>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Profesi</label>
									<input type="text" class="form-control rounded-pill border-info" name="profesi" value="<?= html_escape($form_values['profesi']); ?>">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">No HP</label>
									<input type="text" class="form-control rounded-pill border-info" name="no_hp" value="<?= html_escape($form_values['no_hp']); ?>">
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Nomor SIP</label>
									<input type="text" class="form-control rounded-pill border-info" name="nomor_sip" value="<?= html_escape($form_values['nomor_sip']); ?>">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Status</label>
									<select class="form-control rounded-pill border-info" name="status">
										<option value="aktif" <?= $form_values['status'] === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
										<option value="nonaktif" <?= $form_values['status'] === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
									</select>
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

		<div class="card shadow mb-4">
			<div class="card-header py-3 d-flex align-items-center justify-content-between">
				<h6 class="m-0 font-weight-bold text-primary">Daftar Personel Puskesmas</h6>
				<a href="<?= site_url('kelola_staff_puskesmas/create'); ?>" class="btn btn-sm btn-success shadow-sm rounded-pill">
					<i class="fas fa-plus-circle mr-1"></i> Tambah Staff
				</a>
			</div>
			<div class="card-body">
				<form method="get" action="<?= site_url('kelola_staff_puskesmas'); ?>" class="mb-3">
					<div class="row">
						<div class="col-md-4 mb-2">
							<select name="kode_pkm" class="form-control rounded-pill">
								<option value="">Semua Puskesmas</option>
								<?php foreach ($puskesmas_options as $puskesmas): ?>
									<option value="<?= html_escape($puskesmas->kode_pkm); ?>" <?= (isset($filters['kode_pkm']) && $filters['kode_pkm'] === (string) $puskesmas->kode_pkm) ? 'selected' : ''; ?>>
										<?= html_escape($puskesmas->nama_puskesmas); ?>
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
					<table class="table table-bordered" id="tbl_staff_puskesmas" width="100%" cellspacing="0">
						<thead>
							<tr class="bg-info text-black">
								<th class="text-center" width="5%">No</th>
								<th>Nama</th>
								<th>Puskesmas</th>
								<th>Profesi</th>
								<th>No HP</th>
								<th>Nomor SIP</th>
								<th>Status</th>
								<th class="text-center">Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($staff_rows)): ?>
								<tr>
									<td colspan="8" class="text-center text-muted py-4">Belum ada data personel Puskesmas.</td>
								</tr>
							<?php else: ?>
								<?php $no = 1; foreach ($staff_rows as $row): ?>
									<tr>
										<td class="text-center"><?= $no++; ?></td>
										<td><?= html_escape($row->nama ?? '-'); ?></td>
										<td>
											<span class="badge badge-info"><?= html_escape($row->kode_pkm ?? '-'); ?></span>
											<div class="small text-muted"><?= html_escape($row->nama_puskesmas ?? 'Puskesmas tidak ditemukan'); ?></div>
										</td>
										<td><?= html_escape($row->profesi ?? '-'); ?></td>
										<td><?= html_escape($row->no_hp ?? '-'); ?></td>
										<td><?= html_escape($row->nomor_sip ?? '-'); ?></td>
										<td>
											<span class="badge badge-<?= ($row->status ?? '') === 'aktif' ? 'success' : 'secondary'; ?>">
												<?= html_escape(ucfirst($row->status ?? '-')); ?>
											</span>
										</td>
										<td class="text-center">
											<a href="<?= site_url('kelola_staff_puskesmas/edit/' . (int) $row->staff_id); ?>" class="btn btn-info btn-sm rounded-pill">
												<i class="fas fa-edit"></i> Edit
											</a>
											<?php if (($row->status ?? '') === 'aktif'): ?>
												<form action="<?= site_url('kelola_staff_puskesmas/deactivate/' . (int) $row->staff_id); ?>" method="post" class="d-inline">
													<button type="submit" class="btn btn-secondary btn-sm rounded-pill" onclick="return confirm('Nonaktifkan staff ini?');">
														<i class="fas fa-ban"></i> Nonaktifkan
													</button>
												</form>
											<?php else: ?>
												<form action="<?= site_url('kelola_staff_puskesmas/activate/' . (int) $row->staff_id); ?>" method="post" class="d-inline">
													<button type="submit" class="btn btn-success btn-sm rounded-pill" onclick="return confirm('Aktifkan staff ini?');">
														<i class="fas fa-check"></i> Aktifkan
													</button>
												</form>
											<?php endif; ?>
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
		$('#tbl_staff_puskesmas').DataTable({
			searching: false
		});
	});
</script>
