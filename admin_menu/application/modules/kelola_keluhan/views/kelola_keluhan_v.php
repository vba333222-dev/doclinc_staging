<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-fw fa-comments"></i> Keluhan</h1>
	<button type="button" class="btn btn-sm btn-success shadow-sm" data-toggle="modal" data-target="#modalTambah">
		<i class="fas fa-plus fa-sm text-white-50"></i> Tambah Keluhan
	</button>
</div>
<!-- Content Row -->
<div class="row">
	<div class="col">
		<div class="card shadow-sm">
			<div class="card-body table-responsive">
				<table class="table table-bordered" id="tbl_keluhan" style="width:100%" cellspacing="0">
					<thead>
						<th>No.</th>
						<th>ID Keluhan</th>
						<th>Kategori</th>
						<th>Nama Keluhan</th>
						<th>Deskripsi</th>
						<th>Status</th>
						<th>Aksi</th>
					</thead>
					<tbody>
						<?php
						$no = 0;
						$status_color = '';
						foreach ($data_keluhan->result() as $row):
							$no++;
							if ($row->status == 'Aktif') {
								$status_color = 'success';
							} else {
								$status_color = 'danger';
							}
						?>
							<tr>
								<td><?= $no; ?></td>
								<td><?= $row->id_keluhan; ?></td>
								<td><?= $row->kategori; ?></td>
								<td><?= $row->nama_keluhan; ?></td>
								<td><?= $row->deskripsi; ?></td>
								<td><span class="badge badge-<?= $status_color; ?>"><?= $row->status; ?></span></td>
								<td>
									<div class="btn-group">
										<button type="button" class="btn btn-outline-secondary">Aksi</button>
										<button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
											<span class="sr-only">Toggle Dropdown</span>
										</button>
										<div class="dropdown-menu">
											<a class="dropdown-item" href="#modalEdit" data-toggle="modal"
												data-idkeluhan="<?= $row->id_keluhan; ?>"
												data-kategori="<?= $row->kategori; ?>"
												data-namakeluhan="<?= $row->nama_keluhan; ?>"
												data-deskripsi="<?= $row->deskripsi; ?>">
												<i class="fas fa-edit fa-fw text-primary"></i> Edit
											</a>
											<?php
											if ($row->status == 'Aktif') {
											?>
												<a class="dropdown-item" href="#modalDelete" data-toggle="modal" data-idkeluhan="<?= $row->id_keluhan; ?>">
													<i class="fas fa-ban fa-fw text-danger"></i> Non-Aktifkan
												</a>
											<?php
											} else {
											?>
												<a class="dropdown-item" href="#modalAktif" data-toggle="modal" data-idkeluhan="<?= $row->id_keluhan; ?>">
													<i class="fas fa-check fa-fw text-success"></i> Aktifkan
												</a>
											<?php
											}
											?>
										</div>
									</div>
								</td>
							</tr>
						<?php endforeach ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<!-- Modal Tambah Data -->
<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-labelledby="modalTambahLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="modalTambahLabel">Tambah Keluhan</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="<?= site_url('kelola_keluhan/tambah_keluhan'); ?>" method="POST">
				<div class="modal-body">
					<div class="form-group">
						<label for="kategori" class="col-form-label">Kategori :</label>
						<input type="text" class="form-control" id="kategori" name="kategori" required>
					</div>
					<div class="form-group">
						<label for="nama_keluhan" class="col-form-label">Nama Keluhan :</label>
						<input type="text" class="form-control" id="nama_keluhan" name="nama_keluhan" required>
					</div>
					<div class="form-group mb-0">
						<label for="deskripsi" class="col-form-label">Deskripsi :</label>
						<textarea class="form-control" id="deskripsi" name="deskripsi" required></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
					<button type="submit" class="btn btn-primary">Simpan</button>
				</div>
			</form>
		</div>
	</div>
</div>
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="exampleModalLabel">Edit Keluhan</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="<?= site_url('kelola_keluhan/edit_keluhan'); ?>" method="POST">
				<div class="modal-body">
					<div class="form-group">
						<label for="id_keluhan" class="col-form-label">ID Keluhan :</label>
						<input type="text" class="form-control" id="id_keluhan" name="id_keluhan" readonly>
					</div>
					<div class="form-group">
						<label for="kategori" class="col-form-label">Kategori :</label>
						<input type="text" class="form-control" id="kategori" name="kategori" required>
					</div>
					<div class="form-group">
						<label for="namakeluhan" class="col-form-label">Nama Keluhan :</label>
						<input type="text" class="form-control" id="namakeluhan" name="namakeluhan" required>
					</div>
					<div class="form-group mb-0">
						<label for="deskripsi" class="col-form-label">Deskripsi :</label>
						<textarea class="form-control" id="deskripsi" name="deskripsi" required></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
					<button type="submit" class="btn btn-primary">Simpan</button>
				</div>
			</form>
		</div>
	</div>
</div>
<div class="modal fade" id="modalAktif" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="exampleModalLabel">Aktifkan keluhan?</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="<?= site_url('kelola_keluhan/activate_keluhan'); ?>" method="POST">
				<div class="modal-body">
					<input type="hidden" name="id_keluhan">
					<div class="form-group">
						<label for="remark" class="col-form-label">Catatan :</label>
						<textarea class="form-control" id="remark" name="remark" required></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
					<button type="submit" class="btn btn-primary">Aktifkan</button>
				</div>
			</form>
		</div>
	</div>
</div>
<div class="modal fade" id="modalDelete" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="exampleModalLabel">Non-Aktifkan keluhan?</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="<?= site_url('kelola_keluhan/delete_keluhan'); ?>" method="POST">
				<div class="modal-body">
					<input type="hidden" name="id_keluhan">
					<div class="form-group">
						<label for="remark" class="col-form-label">Catatan :</label>
						<textarea class="form-control" id="remark" name="remark" required></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
					<button type="submit" class="btn btn-primary">Nonaktifkan</button>
				</div>
			</form>
		</div>
	</div>
</div>
<script type="text/javascript">
	$(document).ready(function() {
		$('#tbl_keluhan').DataTable({
			language: {
				lengthMenu: 'Tampilkan _MENU_ data',
				search: 'Cari:',
				emptyTable: 'Tidak ada data',
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
	$('#modalEdit').on('show.bs.modal', function(event) {
		var button = $(event.relatedTarget);
		var id_keluhan = button.data('idkeluhan');
		var kategori = button.data('kategori');
		var namakeluhan = button.data('namakeluhan');
		var deskripsi = button.data('deskripsi');
		var modal = $(this);
		modal.find('.modal-body input[name="id_keluhan"]').val(id_keluhan);
		modal.find('.modal-body input[name="kategori"]').val(kategori);
		modal.find('.modal-body input[name="namakeluhan"]').val(namakeluhan);
		modal.find('.modal-body textarea[name="deskripsi"]').val(deskripsi);
	});
	$('#modalAktif').on('show.bs.modal', function(event) {
		var button = $(event.relatedTarget);
		var id_keluhan = button.data('idkeluhan');
		var modal = $(this);
		modal.find('.modal-body input[name="id_keluhan"]').val(id_keluhan);
	});
	$('#modalDelete').on('show.bs.modal', function(event) {
		var button = $(event.relatedTarget);
		var id_keluhan = button.data('idkeluhan');
		var modal = $(this);
		modal.find('.modal-body input[name="id_keluhan"]').val(id_keluhan);
	});
</script>
