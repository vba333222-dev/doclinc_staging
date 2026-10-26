<?php
/* Kepala halaman dari partial bersama. Kalimat penjelas yang tadinya berdiri
   sebagai `.doclinc-helper-note` di bawah judul dipindah ke `sub`: isinya satu
   kalimat, dan blok terpisah untuk satu kalimat menambah satu kotak lagi tanpa
   menambah informasi. */
$page_head = array(
	'title' => 'Master Gejala/Keluhan',
	'sub' => 'Pilihan gejala saat warga membuat konsultasi.',
	'actions' => array(
		array('kind' => 'modal', 'label' => 'Tambah keluhan', 'target' => '#modalTambah', 'icon' => 'fas fa-plus', 'variant' => 'success'),
	),
);
$this->load->view('commons/page_head', array('page_head' => $page_head));
?>
<!-- Content Row -->
<div class="row">
	<div class="col">
		<div class="card shadow-sm">
			<div class="card-body table-responsive">
				<table class="table table-bordered" id="tbl_keluhan" style="width:100%" cellspacing="0">
					<thead>
						<th scope="col">No.</th>
						<th scope="col">ID Keluhan</th>
						<th scope="col">Kategori</th>
						<th scope="col">Nama Keluhan</th>
						<th scope="col">Deskripsi</th>
						<th scope="col">Status</th>
						<th scope="col">Aksi</th>
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
								<td><?= html_escape($row->id_keluhan ?? '-'); ?></td>
								<td><?= html_escape($row->kategori ?? '-'); ?></td>
								<td><?= html_escape($row->nama_keluhan ?? '-'); ?></td>
								<td><?= html_escape($row->deskripsi ?? '-'); ?></td>
								<td><span class="badge badge-<?= html_escape($status_color); ?>"><?= html_escape($row->status ?? '-'); ?></span></td>
								<td>
									<div class="btn-group">
										<button type="button" class="btn btn-outline-secondary">Aksi</button>
										<button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
											<span class="sr-only">Buka menu</span>
										</button>
										<div class="dropdown-menu">
											<a class="dropdown-item" href="#modalEdit" data-toggle="modal"
												data-idkeluhan="<?= html_escape($row->id_keluhan ?? ''); ?>"
												data-kategori="<?= html_escape($row->kategori ?? ''); ?>"
												data-namakeluhan="<?= html_escape($row->nama_keluhan ?? ''); ?>"
												data-deskripsi="<?= html_escape($row->deskripsi ?? ''); ?>">
												<i class="fas fa-edit fa-fw text-primary"></i> Edit
											</a>
											<?php
											if ($row->status == 'Aktif') {
											?>
												<a class="dropdown-item" href="#modalDelete" data-toggle="modal" data-idkeluhan="<?= html_escape($row->id_keluhan ?? ''); ?>">
													<i class="fas fa-ban fa-fw text-danger"></i> Nonaktifkan
												</a>
											<?php
											} else {
											?>
												<a class="dropdown-item" href="#modalAktif" data-toggle="modal" data-idkeluhan="<?= html_escape($row->id_keluhan ?? ''); ?>">
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
				<h5 class="modal-title" id="modalTambahLabel">Tambah keluhan</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="<?= site_url('kelola_keluhan/tambah_keluhan'); ?>" method="POST">
				<div class="modal-body">
					<div class="form-group">
						<label for="add_kategori" class="col-form-label">Kategori :</label>
						<input type="text" class="form-control" id="add_kategori" name="kategori" required>
					</div>
					<div class="form-group">
						<label for="nama_keluhan" class="col-form-label">Nama Keluhan :</label>
						<input type="text" class="form-control" id="nama_keluhan" name="nama_keluhan" required>
					</div>
					<div class="form-group mb-0">
						<label for="add_deskripsi" class="col-form-label">Deskripsi :</label>
						<textarea class="form-control" id="add_deskripsi" name="deskripsi" required></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-primary">Simpan</button>
				</div>
			</form>
		</div>
	</div>
</div>
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-labelledby="modalEditLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="modalEditLabel">Ubah keluhan</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
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
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-primary">Simpan</button>
				</div>
			</form>
		</div>
	</div>
</div>
<div class="modal fade" id="modalAktif" tabindex="-1" role="dialog" aria-labelledby="modalAktifLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="modalAktifLabel">Aktifkan keluhan?</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="<?= site_url('kelola_keluhan/activate_keluhan'); ?>" method="POST">
				<div class="modal-body">
					<input type="hidden" name="id_keluhan">
					<div class="form-group">
						<label for="remark_aktif" class="col-form-label">Catatan :</label>
						<textarea class="form-control" id="remark_aktif" name="remark" required></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-primary">Aktifkan</button>
				</div>
			</form>
		</div>
	</div>
</div>
<div class="modal fade" id="modalDelete" tabindex="-1" role="dialog" aria-labelledby="modalDeleteLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="modalDeleteLabel">Nonaktifkan keluhan?</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="<?= site_url('kelola_keluhan/delete_keluhan'); ?>" method="POST">
				<div class="modal-body">
					<input type="hidden" name="id_keluhan">
					<p class="text-muted mb-2">Keluhan tidak lagi tersedia untuk pilihan baru.</p>
					<div class="form-group">
						<label for="remark_hapus" class="col-form-label">Catatan :</label>
						<textarea class="form-control" id="remark_hapus" name="remark" required></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
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
