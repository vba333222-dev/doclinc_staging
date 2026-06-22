<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-fw fa-hospital"></i> Master Puskesmas</h1>
</div>

<div class="container-fluid">
	<div class="card shadow mb-4">
		<div class="card-header py-3 d-flex align-items-center justify-content-between">
			<h6 class="m-0 font-weight-bold text-primary">Daftar Puskesmas</h6>
			<button type="button" class="btn btn-sm btn-success shadow-sm rounded-pill" data-toggle="modal" data-target="#modalTambahPuskesmas">
				<i class="fas fa-plus-circle mr-1"></i> Tambah Puskesmas
			</button>
		</div>
		<div class="card-body">
			<div class="table-responsive">
				<table class="table table-bordered" id="tbl_puskesmas" width="100%" cellspacing="0">
					<thead>
						<tr class="bg-info text-black">
							<th>Kode</th>
							<th>Nama</th>
							<th>Alamat</th>
							<th>Latitude</th>
							<th>Longitude</th>
							<th>Status</th>
							<th class="text-center">Aksi</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($puskesmas->result() as $row): ?>
							<tr>
								<td><span class="badge badge-info"><?= html_escape($row->kode_pkm ?? '-'); ?></span></td>
								<td><?= html_escape($row->nama_puskesmas ?? '-'); ?></td>
								<td><?= html_escape($row->alamat ?? '-'); ?></td>
								<td><?= html_escape($row->latitude ?? '-'); ?></td>
								<td><?= html_escape($row->longitude ?? '-'); ?></td>
								<td>
									<span class="badge badge-<?= ($row->status ?? '') === 'aktif' ? 'success' : 'secondary'; ?>">
										<?= html_escape(ucfirst($row->status ?? '-')); ?>
									</span>
								</td>
								<td class="text-center">
									<button type="button" class="btn btn-info btn-sm rounded-pill" data-toggle="modal" data-target="#editPuskesmas<?= html_escape($row->kode_pkm); ?>">
										<i class="fas fa-edit"></i> Edit
									</button>
									<?php if (($row->status ?? '') === 'aktif'): ?>
										<form action="<?= site_url('master_puskesmas/disable') ?>" method="post" class="d-inline">
											<input type="hidden" name="kode_pkm" value="<?= html_escape($row->kode_pkm ?? ''); ?>">
											<button type="submit" class="btn btn-secondary btn-sm rounded-pill" onclick="return confirm('Nonaktifkan puskesmas ini?');">
												<i class="fas fa-ban"></i> Nonaktif
											</button>
										</form>
									<?php else: ?>
										<form action="<?= site_url('master_puskesmas/enable') ?>" method="post" class="d-inline">
											<input type="hidden" name="kode_pkm" value="<?= html_escape($row->kode_pkm ?? ''); ?>">
											<button type="submit" class="btn btn-success btn-sm rounded-pill" onclick="return confirm('Aktifkan puskesmas ini?');">
												<i class="fas fa-check"></i> Aktif
											</button>
										</form>
									<?php endif; ?>
								</td>
							</tr>

							<div class="modal fade" id="editPuskesmas<?= html_escape($row->kode_pkm); ?>" tabindex="-1" role="dialog" aria-hidden="true">
								<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
									<div class="modal-content border-0 shadow-sm">
										<div class="modal-header bg-info text-white">
											<h5 class="modal-title"><i class="fas fa-hospital mr-2"></i>Edit Puskesmas</h5>
											<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
												<span aria-hidden="true">&times;</span>
											</button>
										</div>
										<form action="<?= site_url('master_puskesmas/update/' . rawurlencode($row->kode_pkm ?? '')) ?>" method="post">
											<div class="modal-body bg-light">
												<div class="form-group">
													<label class="text-info">Kode Puskesmas</label>
													<input type="text" class="form-control rounded-pill border-info" value="<?= html_escape($row->kode_pkm ?? '') ?>" readonly>
												</div>
												<div class="form-group">
													<label class="text-info">Nama Puskesmas</label>
													<input type="text" class="form-control rounded-pill border-info" name="nama_puskesmas" value="<?= html_escape($row->nama_puskesmas ?? '') ?>" required>
												</div>
												<div class="form-group">
													<label class="text-info">Alamat</label>
													<textarea class="form-control border-info" name="alamat" rows="2"><?= html_escape($row->alamat ?? '') ?></textarea>
												</div>
												<div class="row">
													<div class="col-md-4">
														<div class="form-group">
															<label class="text-info">Latitude</label>
															<input type="text" class="form-control rounded-pill border-info" name="latitude" value="<?= html_escape($row->latitude ?? '') ?>">
														</div>
													</div>
													<div class="col-md-4">
														<div class="form-group">
															<label class="text-info">Longitude</label>
															<input type="text" class="form-control rounded-pill border-info" name="longitude" value="<?= html_escape($row->longitude ?? '') ?>">
														</div>
													</div>
													<div class="col-md-4">
														<div class="form-group">
															<label class="text-info">Status</label>
															<select class="form-control rounded-pill border-info" name="status">
																<option value="aktif" <?= ($row->status ?? '') === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
																<option value="nonaktif" <?= ($row->status ?? '') === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
															</select>
														</div>
													</div>
												</div>
											</div>
											<div class="modal-footer border-0 px-4 pb-4">
												<button type="button" class="btn btn-light rounded-pill px-4 border" data-dismiss="modal">Batal</button>
												<button type="submit" class="btn btn-info rounded-pill px-4">Simpan</button>
											</div>
										</form>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modalTambahPuskesmas" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-0 shadow-sm">
			<div class="modal-header bg-success text-white">
				<h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Tambah Puskesmas</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="<?= site_url('master_puskesmas/store') ?>" method="post">
				<div class="modal-body bg-light">
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success">Kode Puskesmas</label>
								<input type="text" class="form-control rounded-pill border-success" name="kode_pkm" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success">Nama Puskesmas</label>
								<input type="text" class="form-control rounded-pill border-success" name="nama_puskesmas" required>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="text-success">Alamat</label>
						<textarea class="form-control border-success" name="alamat" rows="2"></textarea>
					</div>
					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label class="text-success">Latitude</label>
								<input type="text" class="form-control rounded-pill border-success" name="latitude">
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label class="text-success">Longitude</label>
								<input type="text" class="form-control rounded-pill border-success" name="longitude">
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label class="text-success">Status</label>
								<select class="form-control rounded-pill border-success" name="status">
									<option value="aktif">Aktif</option>
									<option value="nonaktif">Nonaktif</option>
								</select>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer border-0 px-4 pb-4">
					<button type="button" class="btn btn-light rounded-pill px-4 border" data-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-success rounded-pill px-4">Simpan</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
	$(document).ready(function() {
		$('#tbl_puskesmas').DataTable();
	});
</script>
