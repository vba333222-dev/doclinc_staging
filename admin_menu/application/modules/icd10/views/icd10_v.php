<?php
/**
 * Penjelajah master ICD-10 (baca saja).
 *
 * Halaman ini tidak menyalin data ke database dan tidak menerjemahkan judul WHO.
 * Status review yang tertulis di berkas master ditampilkan apa adanya, termasuk
 * fakta bahwa alias bahasa Indonesia masih draft dan master belum diimpor ke
 * pipeline `clinical_master_*`.
 */
$available = !empty($available);
$manifest = isset($manifest) && is_array($manifest) ? $manifest : array();
$chapters = isset($chapters) && is_array($chapters) ? $chapters : array();
$blocks = isset($blocks) && is_array($blocks) ? $blocks : array();
$aliases = isset($aliases) && is_array($aliases) ? $aliases : array();
$query = isset($query) ? (string) $query : '';
$search = isset($search) && is_array($search) ? $search : null;
$meta = $search !== null && isset($search['meta']) ? $search['meta'] : array();
?>
<?php
/* Kepala halaman dari partial bersama. Ikon judul dilepas: `.dl-adm-head-title`
   tidak punya slot ikon, dan tiga halaman lain sudah tampil tanpa ikon judul. */
$page_head = array(
	'title' => 'Master ICD-10',
	'sub' => 'Kode ICD-10 WHO 2019 beserta bab dan bloknya.',
	'actions' => array(
		array('kind' => 'link', 'label' => 'Infografis diagnosis', 'href' => site_url('diagnosis'), 'icon' => 'fas fa-chart-bar', 'variant' => 'outline-primary'),
	),
);
$this->load->view('commons/page_head', array('page_head' => $page_head));
?>

<?php if (!$available): ?>
	<div class="alert alert-warning" role="status">
		Berkas master ICD-10 tidak ditemukan di <code>database/master_data/clinical/v1/</code>.
		Penjelajah ini membaca berkas itu, bukan tabel database.
	</div>
<?php else: ?>
	<div class="alert alert-info" role="status">
		<strong>Baca saja.</strong>
		Master ini belum diimpor ke database; menyalin langsung akan melewati pemeriksaan dataset.
	</div>

	<div class="row mb-4">
		<div class="col-md-3 col-6 mb-3">
			<div class="card shadow-sm h-100"><div class="card-body">
				<div class="text-muted small text-uppercase font-weight-bold">Paket</div>
				<div class="font-weight-bold"><?= html_escape((string) ($manifest['package'] ?? '-')); ?></div>
				<div class="text-muted small">versi <?= html_escape((string) ($manifest['version'] ?? '-')); ?></div>
			</div></div>
		</div>
		<div class="col-md-3 col-6 mb-3">
			<div class="card shadow-sm h-100"><div class="card-body">
				<div class="text-muted small text-uppercase font-weight-bold">Total catatan</div>
				<div class="h3 mb-0 font-weight-bold"><code><?= number_format((int) ($manifest['total_records'] ?? 0)); ?></code></div>
				<div class="text-muted small"><?= number_format((int) ($manifest['file_count'] ?? 0)); ?> berkas</div>
			</div></div>
		</div>
		<div class="col-md-3 col-6 mb-3">
			<div class="card shadow-sm h-100"><div class="card-body">
				<div class="text-muted small text-uppercase font-weight-bold">Bab / blok</div>
				<div class="h4 mb-0 font-weight-bold"><code><?= number_format(count($chapters)); ?></code> / <code><?= number_format(count($blocks)); ?></code></div>
				<div class="text-muted small">hierarki ICD-10</div>
			</div></div>
		</div>
		<div class="col-md-3 col-6 mb-3">
			<div class="card shadow-sm h-100"><div class="card-body">
				<div class="text-muted small text-uppercase font-weight-bold">Alias Indonesia</div>
				<div class="h3 mb-0 font-weight-bold"><code><?= number_format(count($aliases)); ?></code></div>
				<div class="text-muted small">statusnya masih draft</div>
			</div></div>
		</div>
	</div>

	<?= form_open('icd10/filter', array('class' => 'dl-adm-toolbar mb-4', 'method' => 'post')); ?>
		<div class="dl-adm-toolbar-field">
			<label class="dl-adm-toolbar-label" for="q">Cari kode, judul, atau kata kunci</label>
			<input type="text" class="form-control" id="q" name="q" value="<?= html_escape($query); ?>"
				maxlength="60" placeholder="Contoh: A09, kolera, tuberkulosis">
		</div>
		<div class="dl-adm-toolbar-actions">
			<button type="submit" class="btn btn-primary dl-adm-action">
				<i class="fas fa-search" aria-hidden="true"></i> Cari
			</button>
		</div>
	<?= form_close(); ?>

	<?php if ($search !== null): ?>
		<div class="card shadow-sm mb-4">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0 font-weight-bold">Hasil pencarian: <code><?= html_escape($query); ?></code></h2>
				<span class="text-muted small">
					<?= number_format((int) ($search['total'] ?? 0)); ?> kode cocok
					<?php if (($meta['from_cache'] ?? false) === true): ?>
						&middot; indeks dari cache
					<?php elseif (isset($meta['build_ms'])): ?>
						&middot; indeks dibangun <?= (int) $meta['build_ms']; ?> ms
					<?php endif; ?>
				</span>
			</div>
			<div class="card-body p-0">
				<?php if (empty($search['rows'])): ?>
					<p class="text-muted m-3 mb-0">
						Tidak ada kode yang cocok. Coba kata kunci lain, atau lihat daftar bab di bawah.
					</p>
				<?php else: ?>
					<div class="table-responsive">
						<table class="table table-sm align-middle mb-0">
							<caption class="px-3 pt-3">
								Judul resmi berbahasa Inggris; kolom Indonesia hanya terisi untuk kode yang punya alias.
							</caption>
							<thead>
								<tr>
									<th scope="col">Kode</th>
									<th scope="col">Judul resmi</th>
									<th scope="col">Indonesia</th>
									<th scope="col">Bab / blok</th>
									<th scope="col">Level</th>
									<th scope="col">Dapat dipilih</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($search['rows'] as $row): ?>
									<tr>
										<td><code><?= html_escape($row['kode']); ?></code></td>
										<td><?= html_escape($row['nama']); ?></td>
										<td>
											<?php if ($row['alias_indonesia'] !== ''): ?>
												<?= html_escape($row['alias_indonesia']); ?>
												<span class="badge badge-warning">draft</span>
											<?php elseif ($row['nama_indonesia'] !== ''): ?>
												<?= html_escape($row['nama_indonesia']); ?>
											<?php else: ?>
												<span class="text-muted">belum tersedia</span>
											<?php endif; ?>
										</td>
										<td>
											<code><?= html_escape($row['chapter_code']); ?></code>
											<span class="text-muted">/</span>
											<code><?= html_escape($row['block_code']); ?></code>
											<div class="text-muted small"><?= html_escape($row['block_name']); ?></div>
										</td>
										<td><code><?= html_escape($row['level']); ?></code></td>
										<td>
											<?php if ($row['dapat_dipilih']): ?>
												<span class="badge badge-success">ya</span>
											<?php else: ?>
												<span class="badge badge-light border">tidak</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php if ((int) $search['total'] > count($search['rows'])): ?>
						<div class="card-footer text-muted small">
							Menampilkan <?= number_format(count($search['rows'])); ?> kode teratas dari
							<?= number_format((int) $search['total']); ?> yang cocok. Persempit kata kunci untuk melihat sisanya.
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="card shadow-sm mb-4">
		<div class="card-header d-flex align-items-center justify-content-between">
			<h2 class="h6 mb-0 font-weight-bold">Bab ICD-10 (<?= number_format(count($chapters)); ?>)</h2>
			<span class="text-muted small">hierarki resmi WHO 2019</span>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-sm align-middle mb-0">
					<caption class="px-3 pt-3">Judul bab berbahasa Inggris, sesuai sumber WHO.</caption>
					<thead>
						<tr>
							<th scope="col">Kode</th>
							<th scope="col">Judul</th>
							<th scope="col">Blok</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($chapters as $chapter): ?>
							<?php
							$chapter_code = (string) ($chapter['kode'] ?? '');
							$chapter_blocks = 0;
							foreach ($blocks as $block) {
								if ((string) ($block['chapter_code'] ?? '') === $chapter_code) {
									$chapter_blocks++;
								}
							}
							?>
							<tr>
								<td><code><?= html_escape($chapter_code); ?></code></td>
								<td><?= html_escape((string) ($chapter['nama'] ?? '')); ?></td>
								<td><code><?= number_format($chapter_blocks); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
<?php endif; ?>
