<?php
/**
 * Pemilih kode ICD-10 untuk satu teks diagnosis yang belum dikode.
 *
 * Halaman ini hanya muncul dari panel "Pengodean diagnosis" (POST), dan sengaja
 * memakai alur dua langkah yang bisa dipahami tanpa JavaScript: cari kata kunci,
 * lalu tekan "Pilih kode ini" pada baris yang benar. Kandidat yang ditampilkan
 * sudah disaring ke kode yang boleh dipakai sebagai diagnosis.
 */
$pick_text = isset($text) ? (string) $text : '';
$pick_entries = isset($entries) ? (int) $entries : 0;
$pick_filters = isset($filters) && is_array($filters)
	? $filters
	: array('days' => 30, 'kecamatan_id' => 0, 'coding_state' => '');
$pick_search = isset($search) && is_array($search)
	? $search
	: array('available' => false, 'query' => '', 'rows' => array(), 'total' => 0);
$pick_rows = isset($pick_search['rows']) && is_array($pick_search['rows']) ? $pick_search['rows'] : array();
$pick_master = !empty($master_available);
$pick_back = site_url('diagnosis/index/' . (int) $pick_filters['days'] . '/' . (int) $pick_filters['kecamatan_id'] . '/' . (string) $pick_filters['coding_state']);
$analytics_css = 'assets/css/doclinc-admin-analytics.css';
?>
<link rel="stylesheet" href="<?= html_escape(base_url($analytics_css) . '?v=' . rawurlencode((string) @filemtime(FCPATH . $analytics_css))); ?>">

<div class="doclinc-analytics">
<?php
$page_head = array(
	'title' => 'Pilih kode ICD-10',
	'sub' => 'Untuk teks diagnosis yang belum dikode',
	'actions' => array(
		array('kind' => 'link', 'label' => 'Kembali ke infografis', 'href' => $pick_back, 'icon' => 'fas fa-arrow-left', 'variant' => 'outline-secondary'),
	),
);
$this->load->view('commons/page_head', array('page_head' => $page_head));
?>

	<div class="doclinc-analytics-panel doclinc-coding-panel">
		<h2 class="doclinc-analytics-panel-title">Teks yang akan dikodekan</h2>
		<p class="doclinc-coding-target"><q><?= html_escape($pick_text); ?></q></p>
		<p class="doclinc-analytics-panel-note">
			<?php if ($pick_entries > 0): ?>
				<?= number_format($pick_entries); ?> baris masih memakai teks ini dan belum punya kode.
				Kode yang dipilih berlaku untuk semuanya; teks aslinya tidak diubah.
			<?php else: ?>
				Tidak ada lagi baris dengan teks ini yang belum dikode &mdash; mungkin sudah dikode di tab lain.
				Memilih kode sekarang tidak akan mengubah apa pun.
			<?php endif; ?>
		</p>

		<?php if (!$pick_master): ?>
			<div class="doclinc-analytics-note doclinc-analytics-note-warning" role="status">
				<span class="doclinc-analytics-note-title">Daftar kode ICD-10 tidak bisa dibaca</span>
				Daftar acuan tidak ditemukan, jadi tidak ada kode yang bisa ditawarkan dan tidak ada yang bisa disimpan.
			</div>
		<?php else: ?>
			<?= form_open('diagnosis/pilih', array('class' => 'dl-adm-toolbar', 'method' => 'post')); ?>
				<?= form_hidden('text', $pick_text); ?>
				<?= form_hidden('days', (int) $pick_filters['days']); ?>
				<?= form_hidden('kecamatan_id', (int) $pick_filters['kecamatan_id']); ?>
				<?= form_hidden('coding_state', (string) $pick_filters['coding_state']); ?>
				<div class="dl-adm-toolbar-field">
					<label class="dl-adm-toolbar-label" for="doclincCodingQuery">Kata kunci atau kode</label>
					<input type="search" class="form-control" id="doclincCodingQuery" name="q" maxlength="60"
						value="<?= html_escape((string) $pick_search['query']); ?>"
						placeholder="mis. dermatitis, demam, atau L20">
				</div>
				<div class="dl-adm-toolbar-actions">
					<button type="submit" class="btn btn-primary dl-adm-action">
						<i class="fas fa-search" aria-hidden="true"></i> Cari kode
					</button>
				</div>
			<?= form_close(); ?>

			<?php if ((string) $pick_search['query'] === ''): ?>
				<div class="doclinc-analytics-empty">Ketik kata kunci atau kode ICD-10, lalu tekan &ldquo;Cari kode&rdquo;.</div>
			<?php elseif (empty($pick_rows)): ?>
				<div class="doclinc-analytics-empty">
					<?php if (empty($pick_search['submitted'])): ?>
						Belum ada kode yang cocok dengan teks ini. Ketik kata kunci
						(mis. <em>demam</em>) atau kodenya (mis. R50.9), lalu tekan &ldquo;Cari kode&rdquo;.
					<?php else: ?>
						Tidak ada kode yang cocok dengan &ldquo;<?= html_escape((string) $pick_search['query']); ?>&rdquo;.
						Daftar kode ICD-10 memakai judul resmi berbahasa Inggris, jadi coba kata Inggrisnya
						(mis. <em>cough</em>, <em>fever</em>) atau tulis kodenya langsung (mis. R05).
					<?php endif; ?>
				</div>
			<?php else: ?>
				<p class="doclinc-analytics-panel-note">
					Menampilkan <?= number_format(count($pick_rows)); ?> dari <?= number_format((int) $pick_search['total']); ?> kode yang cocok
					&mdash; hanya kode yang boleh dipakai sebagai diagnosis.
				</p>
				<?php if (!empty($pick_search['weak'])): ?>
					<p class="doclinc-analytics-note doclinc-analytics-note-warning" role="status">
						<span class="doclinc-analytics-note-title">Hasil di bawah hanya cocok sebagian</span>
						&ldquo;<?= html_escape((string) $pick_search['query']); ?>&rdquo; tidak sama dengan awal judul atau kode mana pun.
						Periksa satu per satu, atau cari kata yang lebih spesifik (mis. &ldquo;infeksi saluran napas&rdquo;) atau kodenya (mis. J06.9).
					</p>
				<?php endif; ?>
				<div class="doclinc-analytics-tablewrap">
					<table class="doclinc-analytics-table doclinc-coding-table">
						<caption>Pilih satu kode. Judul resmi berbahasa Inggris; nama Indonesia (bila ada) masih berstatus draft.</caption>
						<thead>
							<tr>
								<th scope="col">Kode</th>
								<th scope="col">Judul resmi</th>
								<th scope="col">Bab</th>
								<th scope="col">Tindakan</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($pick_rows as $pick_row): ?>
								<tr>
									<td><span class="doclinc-analytics-code"><?= html_escape((string) $pick_row['code']); ?></span></td>
									<td>
										<span class="doclinc-coding-text"><?= html_escape((string) $pick_row['label']); ?></span>
										<?php if (($pick_row['label_id'] ?? '') !== ''): ?>
											<span class="doclinc-coding-sub"><?= html_escape((string) $pick_row['label_id']); ?></span>
										<?php endif; ?>
									</td>
									<td><span class="doclinc-coding-sub"><?= html_escape((string) $pick_row['chapter']); ?></span></td>
									<td>
										<?= form_open('diagnosis/assign', array('class' => 'doclinc-coding-pick')); ?>
											<?= form_hidden('text', $pick_text); ?>
											<?= form_hidden('code', (string) $pick_row['code']); ?>
											<?= form_hidden('days', (int) $pick_filters['days']); ?>
											<?= form_hidden('kecamatan_id', (int) $pick_filters['kecamatan_id']); ?>
											<?= form_hidden('coding_state', (string) $pick_filters['coding_state']); ?>
											<button type="submit" class="btn btn-primary dl-adm-action">
												Pilih <?= html_escape((string) $pick_row['code']); ?>
											</button>
										<?= form_close(); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
