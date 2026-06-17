<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-fw fa-user-md"></i> Kelola Dokter / Nakes</h1>
	<a href="<?= base_url('kelola_dokter_nakes/tambah') ?>" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
		<i class="fas fa-plus fa-sm text-white-50"></i> Tambah Data
	</a>
</div>

<div class="container-fluid">
	<div class="card shadow mb-4">
		<div class="card-header py-3">
			<h6 class="m-0 font-weight-bold text-primary">Daftar Dokter / Nakes</h6>
		</div>
		<div class="card-body">
			<!-- <div class="row mb-3">
				<div class="col-md-4">
					<input type="text" id="searchInput" class="form-control rounded-pill" placeholder="Cari Dokter / Nakes...">
				</div>
			</div> -->
			<div class="table-responsive">
				<table class="table table-bordered" id="tbl_dokter_nakes" width="100%" cellspacing="0">
					<thead>
						<tr class="bg-info text-black">
							<th class="text-center" width="5%"><i class="fas fa-list-ol"></i></th>
							<th><i class="fas fa-user-md"></i> Nama</th>
							<th><i class="fas fa-hospital"></i> Kode PKM</th>
							<th><i class="fas fa-phone"></i> No. Telepon</th>
							<th class="text-center"><i class="fas fa-cogs"></i> Aksi</th>
						</tr>
					</thead>
					<tbody>
						<?php $no = 1;
						foreach ($data_dokter_nakes->result() as $data): ?>
							<tr class="align-middle">
								<td class="text-center"><?= $no++; ?></td>
								<td>
									<i class="fas fa-user-circle mr-2 text-secondary"></i>
									<?= $data->nama; ?>
								</td>
								<td><span class="badge badge-info"><?= $data->remark; ?></span></td>
								<td><i class="fas fa-phone-alt text-info mr-1"></i><?= $data->no_hp; ?></td>
								<td class="text-center">
									<button type="button" class="btn btn-info btn-sm rounded-pill" data-toggle="modal" data-target="#editModal<?= $data->userId ?>">
										<i class="fas fa-edit"></i> Edit
									</button>
									<a href="<?= base_url('kelola_dokter_nakes/delete_dokter_nakes/?id_dokter_nakes=' . $data->userId) ?>"
										class="btn btn-danger btn-sm rounded-pill"
										onclick="return confirm('Yakin ingin menghapus data ini?');">
										<i class="fas fa-trash"></i> Hapus
									</a>
								</td>
							</tr>

							<!-- Edit Modal -->
							<div class="modal fade" id="editModal<?= $data->userId ?>" tabindex="-1" role="dialog" aria-labelledby="editModalLabel<?= $data->userId ?>" aria-hidden="true">
								<div class="modal-dialog modal-dialog-centered" role="document">
									<div class="modal-content border-0 shadow-sm">
										<div class="modal-header bg-info text-white">
											<h5 class="modal-title" id="editModalLabel<?= $data->userId ?>">
												<i class="fas fa-stethoscope mr-2"></i>Perbarui Data Tenaga Kesehatan
											</h5>
											<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
												<span aria-hidden="true">&times;</span>
											</button>
										</div>
										<form action="<?= base_url('kelola_dokter_nakes/update/' . $data->userId) ?>" method="post">
											<input type="hidden" name="old_photo" value="<?= $data->foto ?>">
											<div class="form-group mt-3 px-3">
												<div class="row">
													<div class="col-md-3">
														<?php if ($data->foto): ?>
															<img src="<?= base_url('../uploads/profile/' . $data->foto) ?>" class="img-thumbnail rounded-circle">
														<?php else: ?>
															<img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRE0DSJPJD2kGUbNsOpT8Qx6mxi6VFKPY_wDw&s" class="img-thumbnail rounded-circle">
														<?php endif; ?>
													</div>
													<div class="col-md-9">
														<input type="file" class="form-control-file" name="photo">
														<small class="text-muted">Upload foto baru atau biarkan kosong untuk menggunakan foto yang ada</small>
													</div>
												</div>
											</div>
											<div class="modal-body bg-light rounded mx-3">
												<div class="form-group">
													<label class="text-info"><i class="fas fa-user-md mr-1"></i> Nama Lengkap</label>
													<input type="text" class="form-control rounded-pill border-info" name="nama" value="<?= $data->nama ?>" required>
												</div>
												<div class="form-group">
													<label class="text-info"><i class="fas fa-clinic-medical mr-1"></i> Kode Puskesmas</label>
													<input type="text" class="form-control rounded-pill border-info" name="remark" value="<?= $data->remark ?>" required>
												</div>
												<div class="form-group">
													<label class="text-info"><i class="fas fa-phone-alt mr-1"></i> Nomor Telepon</label>
													<input type="text" class="form-control rounded-pill border-info" name="no_hp" value="<?= $data->no_hp ?>" required>
												</div>
											</div>
											<div class="modal-footer border-0 px-4 pb-4">
												<button type="button" class="btn btn-light rounded-pill px-4 border" data-dismiss="modal">
													<i class="fas fa-times-circle mr-1"></i>Batal
												</button>
												<button type="submit" class="btn btn-info rounded-pill px-4">
													<i class="fas fa-check-circle mr-1"></i>Simpan
												</button>
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

<script>
	document.addEventListener('DOMContentLoaded', function() {
		const searchInput = document.getElementById('searchInput');
		const tableRows = document.querySelectorAll('#dataTable tbody tr');

		searchInput.addEventListener('keyup', function() {
			const searchText = searchInput.value.toLowerCase();

			tableRows.forEach(row => {
				const rowText = row.textContent.toLowerCase();
				row.style.display = rowText.includes(searchText) ? '' : 'none';
			});
		});
	});
</script>

<script type="text/javascript">
	$(document).ready(function() {
		$('#tbl_dokter_nakes').DataTable();
	});
</script>
