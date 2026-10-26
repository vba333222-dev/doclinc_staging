<?php
/**
 * Editor konten landing page DocLinc.
 *
 * Blok dan tanya-jawab adalah teks yang diketik pengelola. Statistik dihitung
 * dari data nyata oleh model - view tidak mengarang angka dan tidak
 * menyembunyikan angka yang belum bisa dihitung (ditampilkan sebagai "belum
 * tersedia" beserta sumbernya).
 */
$schema_ready = !empty($schema_ready);
$blocks = isset($blocks) && is_array($blocks) ? $blocks : array();
$faqs = isset($faqs) && is_array($faqs) ? $faqs : array();
$stats = isset($stats) && is_array($stats) ? $stats : array();
$active_blocks = array();
foreach ($blocks as $block) {
	if ((int) $block->aktif === 1) {
		$active_blocks[(string) $block->block_key] = $block;
	}
}
$block_label = array(
	'hero_judul' => 'Judul utama',
	'hero_ringkasan' => 'Ringkasan pembuka',
	'tentang' => 'Tentang layanan',
	'kontak' => 'Kontak resmi',
);
?>
<?php
/* Kepala halaman dari partial bersama. */
$page_head = array(
	'title' => 'Konten landing',
	'sub' => 'Teks halaman perkenalan DocLinc.',
	'actions' => array(
		array('kind' => 'link', 'label' => 'Master wilayah', 'href' => site_url('wilayah'), 'icon' => 'fas fa-map-marked-alt', 'variant' => 'outline-secondary'),
	),
);
$this->load->view('commons/page_head', array('page_head' => $page_head));
?>

<?php if (!$schema_ready): ?>
	<div class="alert alert-warning" role="status">
		Tabel <code>landing_blocks</code> dan <code>landing_faqs</code> belum ada di database ini.
		Jalankan migrasi fondasi konten landing lebih dulu.
	</div>
<?php else: ?>
	<div class="alert alert-info" role="status">
		Model konten ini milik DocLinc dan terpisah dari modul <code>landing_page</code> warisan template
		lain (yang membaca tabel <code>tbl_*</code> dan tidak bisa dipakai di database ini).
		Angka statistik di bawah dihitung dari data nyata saat halaman dibuka, bukan disimpan,
		sehingga tidak ada angka basi yang perlu diperbarui manual.
	</div>

	<?php foreach ($blocks as $block): ?>
		<div class="card shadow-sm mb-3">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0 font-weight-bold">
					<?= html_escape($block_label[(string) $block->block_key] ?? (string) $block->block_key); ?>
					<code class="ml-2"><?= html_escape((string) $block->block_key); ?></code>
				</h2>
				<span class="text-muted small">
					<?php if ((int) $block->aktif === 1): ?>
						ditampilkan
					<?php else: ?>
						disembunyikan
					<?php endif; ?>
					&middot; urutan <?= (int) $block->urutan; ?>
					<?php if (!empty($block->updated_at)): ?>
						&middot; diubah <?= html_escape((string) $block->updated_at); ?>
					<?php endif; ?>
				</span>
			</div>
			<div class="card-body">
				<?= form_open('konten_landing/update_block/' . (int) $block->id, array('method' => 'post')); ?>
					<div class="form-group">
						<label for="judul_<?= (int) $block->id; ?>">Judul blok</label>
						<input type="text" class="form-control" id="judul_<?= (int) $block->id; ?>"
							name="judul" value="<?= html_escape((string) $block->judul); ?>" required maxlength="200">
					</div>
					<div class="form-group">
						<label for="isi_<?= (int) $block->id; ?>">Isi</label>
						<textarea class="form-control" id="isi_<?= (int) $block->id; ?>" name="isi" rows="4" required><?= html_escape((string) $block->isi); ?></textarea>
					</div>
					<div class="dl-adm-toolbar">
						<div class="dl-adm-toolbar-field">
							<label for="urutan_<?= (int) $block->id; ?>" class="dl-adm-toolbar-label">Urutan tampil</label>
							<input type="number" class="form-control" id="urutan_<?= (int) $block->id; ?>"
								name="urutan" value="<?= (int) $block->urutan; ?>" min="0" max="999">
						</div>
						<div class="dl-adm-toolbar-field">
							<div class="custom-control custom-checkbox">
								<input type="checkbox" class="custom-control-input" id="aktif_<?= (int) $block->id; ?>"
									name="aktif" value="1" <?= (int) $block->aktif === 1 ? 'checked' : ''; ?>>
								<label class="custom-control-label" for="aktif_<?= (int) $block->id; ?>">Tampilkan di halaman</label>
							</div>
						</div>
						<div class="dl-adm-toolbar-actions">
							<button type="submit" class="btn btn-primary dl-adm-action">Simpan blok</button>
						</div>
					</div>
				<?= form_close(); ?>
			</div>
		</div>
	<?php endforeach; ?>

	<div class="card shadow-sm mb-4">
		<div class="card-header d-flex align-items-center justify-content-between">
			<h2 class="h6 mb-0 font-weight-bold">Tanya-jawab (<?= number_format(count($faqs)); ?>)</h2>
			<span class="text-muted small">Ditampilkan berurutan pada bagian pertanyaan umum</span>
		</div>
		<div class="card-body">
			<?= form_open('konten_landing/create_faq', array('class' => 'dl-adm-toolbar mb-4', 'method' => 'post')); ?>
				<div class="dl-adm-toolbar-field">
					<label for="pertanyaan_baru" class="dl-adm-toolbar-label">Pertanyaan</label>
					<input type="text" class="form-control" id="pertanyaan_baru" name="pertanyaan" required maxlength="255">
				</div>
				<div class="dl-adm-toolbar-field">
					<label for="jawaban_baru" class="dl-adm-toolbar-label">Jawaban</label>
					<input type="text" class="form-control" id="jawaban_baru" name="jawaban" required>
				</div>
				<div class="dl-adm-toolbar-actions">
					<button type="submit" class="btn btn-primary dl-adm-action">
						<i class="fas fa-plus" aria-hidden="true"></i> Tambah
					</button>
				</div>
			<?= form_close(); ?>

			<?php if (empty($faqs)): ?>
				<p class="text-muted mb-0">
					Belum ada tanya-jawab. Bagian ini sengaja dikosongkan pada isi awal supaya tidak ada
					pertanyaan yang dikarang; isilah dengan pertanyaan yang benar-benar ditanyakan warga.
				</p>
			<?php else: ?>
				<?php foreach ($faqs as $faq): ?>
					<div class="border rounded p-3 mb-3">
						<?= form_open('konten_landing/update_faq/' . (int) $faq->id, array('class' => 'dl-adm-toolbar', 'method' => 'post')); ?>
							<div class="dl-adm-toolbar-field">
								<label for="pertanyaan_<?= (int) $faq->id; ?>" class="dl-adm-toolbar-label">Pertanyaan</label>
								<input type="text" class="form-control" id="pertanyaan_<?= (int) $faq->id; ?>"
									name="pertanyaan" value="<?= html_escape((string) $faq->pertanyaan); ?>" required maxlength="255">
							</div>
							<div class="dl-adm-toolbar-field">
								<label for="jawaban_<?= (int) $faq->id; ?>" class="dl-adm-toolbar-label">Jawaban</label>
								<input type="text" class="form-control" id="jawaban_<?= (int) $faq->id; ?>"
									name="jawaban" value="<?= html_escape((string) $faq->jawaban); ?>" required>
							</div>
							<div class="dl-adm-toolbar-field">
								<label for="urutan_faq_<?= (int) $faq->id; ?>" class="dl-adm-toolbar-label">Urutan</label>
								<input type="number" class="form-control" id="urutan_faq_<?= (int) $faq->id; ?>"
									name="urutan" value="<?= (int) $faq->urutan; ?>" min="0" max="999">
							</div>
							<div class="dl-adm-toolbar-actions">
								<div class="custom-control custom-checkbox">
									<input type="checkbox" class="custom-control-input" id="aktif_faq_<?= (int) $faq->id; ?>"
										name="aktif" value="1" <?= (int) $faq->aktif === 1 ? 'checked' : ''; ?>>
									<label class="custom-control-label" for="aktif_faq_<?= (int) $faq->id; ?>">Tampilkan</label>
								</div>
								<button type="submit" class="btn btn-primary dl-adm-action">Simpan</button>
							</div>
						<?= form_close(); ?>
						<?= form_open('konten_landing/delete_faq/' . (int) $faq->id, array(
							'class' => 'd-inline',
							'method' => 'post',
							'data-doclinc-confirm' => 'Hapus tanya-jawab ini?',
						)); ?>
							<button type="submit" class="btn btn-sm btn-outline-danger">
								<i class="fas fa-trash" aria-hidden="true"></i> Hapus
							</button>
						<?= form_close(); ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<div class="card shadow-sm mb-4">
		<div class="card-header">
			<h2 class="h6 mb-0 font-weight-bold">Pratinjau isi</h2>
		</div>
		<div class="card-body">
			<p class="text-muted small">
				Pratinjau memakai blok berstatus tampil. Halaman publik belum memakai konten ini.
			</p>

			<?php if (isset($active_blocks['hero_judul'])): ?>
				<h3 class="h4 font-weight-bold"><?= html_escape((string) $active_blocks['hero_judul']->isi); ?></h3>
			<?php endif; ?>
			<?php if (isset($active_blocks['hero_ringkasan'])): ?>
				<p><?= nl2br(html_escape((string) $active_blocks['hero_ringkasan']->isi)); ?></p>
			<?php endif; ?>

			<div class="row my-3">
				<?php foreach ($stats as $stat): ?>
					<div class="col-md-3 col-6 mb-2">
						<div class="border rounded p-3 h-100">
							<div class="text-muted small text-uppercase font-weight-bold"><?= html_escape($stat['label']); ?></div>
							<?php if ($stat['value'] === null): ?>
								<div class="text-muted">belum tersedia</div>
							<?php else: ?>
								<div class="h4 mb-0 font-weight-bold"><code><?= number_format((int) $stat['value']); ?></code></div>
							<?php endif; ?>
							<div class="text-muted small">dari <?= html_escape($stat['source']); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if (isset($active_blocks['tentang'])): ?>
				<h3 class="h6 font-weight-bold mt-3">Tentang layanan</h3>
				<p><?= nl2br(html_escape((string) $active_blocks['tentang']->isi)); ?></p>
			<?php endif; ?>

			<?php if (isset($active_blocks['kontak'])): ?>
				<h3 class="h6 font-weight-bold mt-3">Kontak</h3>
				<?php if (stripos((string) $active_blocks['kontak']->isi, 'belum diisi') !== false): ?>
					<div class="alert alert-warning mb-2" role="status">
						Kontak resmi belum diisi. Lengkapi sebelum halaman ini dipublikasikan.
					</div>
				<?php endif; ?>
				<p><?= nl2br(html_escape((string) $active_blocks['kontak']->isi)); ?></p>
			<?php endif; ?>

			<h3 class="h6 font-weight-bold mt-3">Pertanyaan umum</h3>
			<?php
			$active_faqs = array();
			foreach ($faqs as $faq) {
				if ((int) $faq->aktif === 1) {
					$active_faqs[] = $faq;
				}
			}
			?>
			<?php if (empty($active_faqs)): ?>
				<p class="text-muted mb-0">Belum ada pertanyaan yang ditampilkan.</p>
			<?php else: ?>
				<dl class="mb-0">
					<?php foreach ($active_faqs as $faq): ?>
						<dt><?= html_escape((string) $faq->pertanyaan); ?></dt>
						<dd><?= nl2br(html_escape((string) $faq->jawaban)); ?></dd>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>

<script>
	(function($) {
		$(function() {
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
