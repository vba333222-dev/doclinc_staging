<?php
/**
 * Master wilayah: kecamatan + puskesmas.
 *
 * Halaman ini adalah sumber pemetaan yang dipakai infografis penyakit
 * terbanyak. Karena itu tabelnya menjelaskan akibat setiap kolom: puskesmas
 * tanpa kode tidak akan pernah terpetakan, dan puskesmas yang kodenya dipakai
 * permintaan tidak boleh dihapus.
 */
$schema_ready = !empty($schema_ready);
$kecamatan_list = isset($kecamatan_list) && is_array($kecamatan_list) ? $kecamatan_list : array();
$puskesmas_list = isset($puskesmas_list) && is_array($puskesmas_list) ? $puskesmas_list : array();
$total_usage = 0;
foreach ($puskesmas_list as $row) {
	$total_usage += (int) ($row->usage_count ?? 0);
}
?>
<div class="doclinc-analytics">
<?php
/* Kepala halaman dari partial bersama. */
$page_head = array(
	'title' => 'Master wilayah',
	'sub' => 'Dasar pemetaan wilayah pada infografis penyakit.',
	'actions' => array(
		array('kind' => 'link', 'label' => 'Lihat infografis', 'href' => site_url('diagnosis'), 'icon' => 'fas fa-chart-bar', 'variant' => 'outline-primary'),
	),
);
$this->load->view('commons/page_head', array('page_head' => $page_head));
?>

	<?php if (!$schema_ready): ?>
		<div class="alert alert-warning" role="status">
			Tabel <code>kecamatan</code> dan <code>puskesmas</code> belum lengkap di database ini, jadi belum ada yang bisa dikelola.
			Jalankan migrasi seed wilayah lebih dulu.
		</div>
	<?php else: ?>
		<div class="alert alert-info" role="status">
			Pemetaan wilayah dibaca dari <code>requests.assigned_puskesmas_code</code> &rarr; <code>puskesmas.kode_pkm</code> &rarr; <code>puskesmas.id_kecamatan</code>.
			Kode puskesmas yang belum terdaftar membuat baris masuk keranjang &ldquo;tanpa pemetaan wilayah&rdquo; pada infografis.
			Saat ini <strong><?= number_format($total_usage); ?></strong> permintaan layanan memakai kode yang terdaftar di sini.
		</div>
	<?php endif; ?>

	<?php if ($schema_ready): ?>
		<div class="card shadow-sm mb-4">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0 font-weight-bold">Kecamatan (<?= number_format(count($kecamatan_list)); ?>)</h2>
			</div>
			<div class="card-body">
				<?= form_open('wilayah/store_kecamatan', array('class' => 'dl-adm-toolbar mb-4', 'method' => 'post')); ?>
					<div class="dl-adm-toolbar-field">
						<label class="dl-adm-toolbar-label" for="nama_kecamatan_baru">Nama kecamatan</label>
						<input type="text" class="form-control" id="nama_kecamatan_baru" name="nama_kecamatan" required maxlength="150" placeholder="Contoh: Citangkil">
					</div>
					<div class="dl-adm-toolbar-actions">
						<button type="submit" class="btn btn-primary dl-adm-action">
							<i class="fas fa-plus" aria-hidden="true"></i> Tambah kecamatan
						</button>
					</div>
				<?= form_close(); ?>

				<?php if (empty($kecamatan_list)): ?>
					<p class="text-muted mb-0">Belum ada kecamatan. Tambahkan minimal satu supaya puskesmas bisa dipetakan.</p>
				<?php else: ?>
					<div class="table-responsive">
						<table class="table table-sm align-middle">
							<caption class="text-muted">Nama ini jadi label di peta infografis.</caption>
							<thead>
								<tr>
									<th scope="col">Kecamatan</th>
									<th scope="col">Puskesmas</th>
									<th scope="col">Aksi</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($kecamatan_list as $row): ?>
									<tr>
										<td><?= html_escape($row->nama_kecamatan); ?></td>
										<td>
											<span class="badge badge-light border"><?= number_format((int) $row->puskesmas_count); ?></span>
										</td>
										<td>
											<button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse"
												data-target="#ubahKecamatan<?= (int) $row->id_kecamatan; ?>"
												aria-expanded="false" aria-controls="ubahKecamatan<?= (int) $row->id_kecamatan; ?>">
												<i class="fas fa-pen" aria-hidden="true"></i> Ubah
											</button>
											<?= form_open('wilayah/delete_kecamatan/' . (int) $row->id_kecamatan, array(
												'class' => 'd-inline',
												'method' => 'post',
												'data-doclinc-confirm' => 'Hapus kecamatan ' . $row->nama_kecamatan . '?',
											)); ?>
												<button type="submit" class="btn btn-sm btn-outline-danger">
													<i class="fas fa-trash" aria-hidden="true"></i>
													<span class="sr-only">Hapus kecamatan <?= html_escape($row->nama_kecamatan); ?></span>
												</button>
											<?= form_close(); ?>
										</td>
									</tr>
									<tr class="collapse" id="ubahKecamatan<?= (int) $row->id_kecamatan; ?>">
										<td colspan="3">
											<?= form_open('wilayah/update_kecamatan/' . (int) $row->id_kecamatan, array('class' => 'dl-adm-toolbar mb-0', 'method' => 'post')); ?>
												<div class="dl-adm-toolbar-field">
													<label class="dl-adm-toolbar-label" for="nama_kecamatan_<?= (int) $row->id_kecamatan; ?>">Nama kecamatan</label>
													<input type="text" class="form-control" id="nama_kecamatan_<?= (int) $row->id_kecamatan; ?>"
														name="nama_kecamatan" value="<?= html_escape($row->nama_kecamatan); ?>" required maxlength="150">
												</div>
												<div class="dl-adm-toolbar-actions">
													<?php /* Tanpa `dl-adm-action`: baris ini `collapse`, jadi tombolnya
													   tidak dirender sampai "Ubah" ditekan. Memberi kelas aksi
													   bersama di sini membuat gerbang "setiap .dl-adm-action
													   minimal 44px" membaca tinggi 0 dari elemen tersembunyi. */ ?>
													<button type="submit" class="btn btn-primary">Simpan</button>
												</div>
											<?= form_close(); ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="card shadow-sm mb-4">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0 font-weight-bold">Puskesmas (<?= number_format(count($puskesmas_list)); ?>)</h2>
				<span class="text-muted small">Kode dipakai mencocokkan permintaan layanan</span>
			</div>
			<div class="card-body">
				<?= form_open('wilayah/store_puskesmas', array('class' => 'dl-adm-toolbar mb-4', 'method' => 'post')); ?>
					<div class="dl-adm-toolbar-field">
						<label class="dl-adm-toolbar-label" for="kode_pkm_baru">Kode puskesmas</label>
						<input type="text" class="form-control" id="kode_pkm_baru" name="kode_pkm" required maxlength="100" placeholder="Contoh: 10280501">
					</div>
					<div class="dl-adm-toolbar-field">
						<label class="dl-adm-toolbar-label" for="nama_puskesmas_baru">Nama puskesmas</label>
						<input type="text" class="form-control" id="nama_puskesmas_baru" name="nama_puskesmas" required maxlength="150" placeholder="Contoh: Puskesmas Citangkil">
					</div>
					<div class="dl-adm-toolbar-field">
						<label class="dl-adm-toolbar-label" for="id_kecamatan_baru">Kecamatan</label>
						<select class="form-control" id="id_kecamatan_baru" name="id_kecamatan" required>
							<option value="">Pilih kecamatan</option>
							<?php foreach ($kecamatan_list as $kecamatan): ?>
								<option value="<?= (int) $kecamatan->id_kecamatan; ?>"><?= html_escape($kecamatan->nama_kecamatan); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="dl-adm-toolbar-actions">
						<button type="submit" class="btn btn-primary dl-adm-action">
							<i class="fas fa-plus" aria-hidden="true"></i> Tambah
						</button>
					</div>
				<?= form_close(); ?>

				<?php if (empty($puskesmas_list)): ?>
					<p class="text-muted mb-0">Belum ada Puskesmas; infografis hanya bisa menampilkan total kota.</p>
				<?php else: ?>
					<div class="table-responsive">
						<table class="table table-sm align-middle">
							<caption class="text-muted">Satu kode untuk satu Puskesmas; kode ganda menghitung dua kali.</caption>
							<thead>
								<tr>
									<th scope="col">Kode</th>
									<th scope="col">Nama puskesmas</th>
									<th scope="col">Kecamatan</th>
									<th scope="col">Dipakai</th>
									<th scope="col">Aksi</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($puskesmas_list as $row): ?>
									<?php $usage_count = (int) ($row->usage_count ?? 0); ?>
									<tr>
										<td><code><?= html_escape((string) $row->kode_pkm); ?></code></td>
										<td><?= html_escape($row->nama_puskesmas); ?></td>
										<td>
											<?php if (empty($row->nama_kecamatan)): ?>
												<span class="badge badge-warning">Belum dipetakan</span>
											<?php else: ?>
												<?= html_escape($row->nama_kecamatan); ?>
											<?php endif; ?>
										</td>
										<td>
											<?php if ($usage_count > 0): ?>
												<span class="badge badge-light border"><?= number_format($usage_count); ?> permintaan</span>
											<?php else: ?>
												<span class="text-muted small">belum dipakai</span>
											<?php endif; ?>
										</td>
										<td>
											<button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse"
												data-target="#ubahPuskesmas<?= (int) $row->id_puskesmas; ?>"
												aria-expanded="false" aria-controls="ubahPuskesmas<?= (int) $row->id_puskesmas; ?>">
												<i class="fas fa-pen" aria-hidden="true"></i> Ubah
											</button>
											<?= form_open('wilayah/delete_puskesmas/' . (int) $row->id_puskesmas, array(
												'class' => 'd-inline',
												'method' => 'post',
												'data-doclinc-confirm' => 'Hapus puskesmas ' . $row->nama_puskesmas . '?',
											)); ?>
												<button type="submit" class="btn btn-sm btn-outline-danger">
													<i class="fas fa-trash" aria-hidden="true"></i>
													<span class="sr-only">Hapus puskesmas <?= html_escape($row->nama_puskesmas); ?></span>
												</button>
											<?= form_close(); ?>
										</td>
									</tr>
									<tr class="collapse" id="ubahPuskesmas<?= (int) $row->id_puskesmas; ?>">
										<td colspan="5">
											<?= form_open('wilayah/update_puskesmas/' . (int) $row->id_puskesmas, array('class' => 'dl-adm-toolbar mb-0', 'method' => 'post')); ?>
												<div class="dl-adm-toolbar-field">
													<label class="dl-adm-toolbar-label" for="kode_pkm_<?= (int) $row->id_puskesmas; ?>">Kode puskesmas</label>
													<input type="text" class="form-control" id="kode_pkm_<?= (int) $row->id_puskesmas; ?>"
														name="kode_pkm" value="<?= html_escape((string) $row->kode_pkm); ?>" required maxlength="100">
												</div>
												<div class="dl-adm-toolbar-field">
													<label class="dl-adm-toolbar-label" for="nama_puskesmas_<?= (int) $row->id_puskesmas; ?>">Nama puskesmas</label>
													<input type="text" class="form-control" id="nama_puskesmas_<?= (int) $row->id_puskesmas; ?>"
														name="nama_puskesmas" value="<?= html_escape($row->nama_puskesmas); ?>" required maxlength="150">
												</div>
												<div class="dl-adm-toolbar-field">
													<label class="dl-adm-toolbar-label" for="id_kecamatan_<?= (int) $row->id_puskesmas; ?>">Kecamatan</label>
													<select class="form-control" id="id_kecamatan_<?= (int) $row->id_puskesmas; ?>" name="id_kecamatan" required>
														<option value="">Pilih kecamatan</option>
														<?php foreach ($kecamatan_list as $kecamatan): ?>
															<option value="<?= (int) $kecamatan->id_kecamatan; ?>"
																<?= (int) $row->id_kecamatan === (int) $kecamatan->id_kecamatan ? 'selected' : ''; ?>>
																<?= html_escape($kecamatan->nama_kecamatan); ?>
															</option>
														<?php endforeach; ?>
													</select>
												</div>
												<div class="dl-adm-toolbar-actions">
													<?php /* Sama seperti formulir kecamatan: baris collapse, jadi
													   kelas aksi bersama tidak dipakai di sini. */ ?>
													<button type="submit" class="btn btn-primary">Simpan</button>
												</div>
											<?= form_close(); ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>

<script>
	(function($) {
		$(function() {
			/* Penghapusan master wilayah mengubah pemetaan infografis, jadi selalu
			   lewat konfirmasi. Server tetap menolak hapus yang merusak pemetaan. */
			$('form[data-doclinc-confirm]').on('submit', function(event) {
				var form = this;
				if (typeof window.Swal !== 'function') {
					return;
				}
				event.preventDefault();
				window.Swal.fire({
					title: 'Konfirmasi',
					text: $(form).data('doclinc-confirm'),
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: 'Ya, hapus',
					cancelButtonText: 'Batal'
				}).then(function(result) {
					if (result.isConfirmed) {
						form.submit();
					}
				});
			});
		});
	})(window.jQuery);
</script>

