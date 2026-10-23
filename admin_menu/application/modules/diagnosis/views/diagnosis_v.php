<?php
/**
 * Infografis penyakit terbanyak per kecamatan dan seluruh Kota Cilegon.
 *
 * Semua angka datang dari `$infographic` (hasil Diagnosis_infographic_m).
 * View ini tidak menghitung apa pun dan tidak mengarang angka: kalau sumbernya
 * kosong, yang tampil adalah penjelasan kenapa kosong.
 */
$payload = isset($infographic) && is_array($infographic) ? $infographic : array();
$city = isset($payload['city']) && is_array($payload['city']) ? $payload['city'] : array();
$period = isset($payload['period']) && is_array($payload['period']) ? $payload['period'] : array();
$by_kecamatan = isset($payload['by_kecamatan']) && is_array($payload['by_kecamatan']) ? $payload['by_kecamatan'] : array();
$unknown = isset($payload['unknown_wilayah']) && is_array($payload['unknown_wilayah']) ? $payload['unknown_wilayah'] : array('entries' => 0, 'note' => '');
$top_codes = isset($city['top_codes']) && is_array($city['top_codes']) ? $city['top_codes'] : array();
$uncoded_top = isset($city['uncoded_top']) && is_array($city['uncoded_top']) ? $city['uncoded_top'] : array();
$kecamatan_options = isset($kecamatan_options) && is_array($kecamatan_options) ? $kecamatan_options : array();

$source_available = !empty($payload['source_available']);
$wilayah_available = !empty($payload['wilayah_available']);
$total_entries = (int) ($city['total_entries'] ?? 0);
$distinct_records = (int) ($city['distinct_records'] ?? 0);
$coded = (int) ($city['coded'] ?? 0);
$uncoded = (int) ($city['uncoded'] ?? 0);
$distinct_codes = (int) ($city['distinct_codes'] ?? 0);
$max_kecamatan_entries = (int) ($payload['max_kecamatan_entries'] ?? 0);
$max_city_code_entries = (int) ($payload['max_city_code_entries'] ?? 0);
$coded_percent = $total_entries > 0 ? (int) round($coded * 100 / $total_entries) : 0;

$filters = isset($filters) && is_array($filters) ? $filters : array('days' => 30, 'kecamatan_id' => 0, 'coding_state' => '');
$period_options = array(
	7 => '7 hari terakhir',
	30 => '30 hari terakhir',
	90 => '90 hari terakhir',
	0 => 'Seluruh periode',
);
$coding_options = array(
	'' => 'Semua diagnosis',
	'coded' => 'Hanya yang terkode',
	'uncoded' => 'Hanya yang belum dikode',
);
$filter_segments = (int) $filters['days'] . '/' . (int) $filters['kecamatan_id'] . '/' . (string) $filters['coding_state'];
$export_url = site_url('diagnosis/export/' . $filter_segments);
$analytics_css = 'assets/css/doclinc-admin-analytics.css';
$analytics_js = 'assets/js/doclinc-diagnosis-infographic.js';
$analytics_css_version = (string) @filemtime(FCPATH . $analytics_css);
$analytics_js_version = (string) @filemtime(FCPATH . $analytics_js);
$chart_payload = array(
	'canvasId' => 'doclincDiagnosisChart',
	'drillUrl' => site_url('diagnosis/code_distribution'),
	'filterSegments' => $filter_segments,
	'labels' => array(),
	'values' => array(),
	'codes' => array(),
);
/* Dikirim apa adanya (menurun) karena Chart.js 3 pada sumbu kategori horizontal
 * menaruh kategori pertama di paling atas - sama seperti urutan tabel di bawah. */
foreach ($top_codes as $row) {
	$chart_payload['labels'][] = $row['code'] . ($row['label'] === '' ? '' : ' - ' . $row['label']);
	$chart_payload['values'][] = (int) $row['entries'];
	$chart_payload['codes'][] = $row['code'];
}
?>
<link rel="stylesheet" href="<?= html_escape(base_url($analytics_css) . '?v=' . rawurlencode($analytics_css_version)); ?>">

<div class="doclinc-analytics">
	<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-4 px-4 mt-n4 mx-n4 you-are-here doclinc-page-header">
		<div>
			<h1 class="h3 mb-1 font-weight-bold doclinc-page-title">
				<i class="fas fa-chart-bar" aria-hidden="true"></i> Penyakit terbanyak
			</h1>
			<p class="mb-0 doclinc-page-subtitle">
				Diagnosis ICD-10 per kecamatan dan seluruh Kota Cilegon &middot; <?= html_escape((string) ($period['label'] ?? 'Seluruh periode')); ?>
			</p>
		</div>
		<a href="<?= html_escape($export_url); ?>" class="btn btn-outline-primary btn-sm shadow-sm">
			<i class="fas fa-file-csv" aria-hidden="true"></i> Unduh CSV
		</a>
	</div>

	<?php if (!$source_available): ?>
		<div class="doclinc-analytics-note doclinc-analytics-note-warning" role="status">
			<span class="doclinc-analytics-note-title">Tabel diagnosis belum tersedia</span>
			Tabel <code>medicalrecord_diagnoses</code> belum ada di database ini, jadi belum ada yang bisa dihitung.
		</div>
	<?php else: ?>
		<?php if (!$wilayah_available): ?>
			<div class="doclinc-analytics-note doclinc-analytics-note-warning" role="status">
				<span class="doclinc-analytics-note-title">Rincian per kecamatan belum bisa dihitung</span>
				Master wilayah (tabel <code>kecamatan</code> dan <code>puskesmas</code>) belum diisi. Angka seluruh kota tetap ditampilkan apa adanya.
			</div>
		<?php endif; ?>
		<?php if ($total_entries === 0): ?>
			<div class="doclinc-analytics-note" role="status">
				<span class="doclinc-analytics-note-title">Belum ada diagnosis yang bisa dihitung pada periode ini</span>
				Angka baru muncul setelah tenaga kesehatan mengisi diagnosis lewat pemilih ICD-10. Selama itu, keranjang &ldquo;belum dikode&rdquo; di bawah berisi catatan yang menunggu pengodean.
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	/* Formulir filter memakai POST, bukan GET: panel ini memakai `index.php?` sebagai
	   index_page sehingga CI menempelkan query string ke URI, dan karakter '=' / '&'
	   tidak diizinkan `permitted_uri_chars` (permintaan dijawab 400). form_open()
	   sekaligus menyertakan token CSRF karena csrf_protection = TRUE. */
	?>
	<?= form_open('diagnosis/filter', array('class' => 'doclinc-analytics-toolbar', 'method' => 'post')); ?>
		<div class="doclinc-analytics-field">
			<label for="doclincDiagnosisDays">Periode</label>
			<select id="doclincDiagnosisDays" name="days">
				<?php foreach ($period_options as $value => $label): ?>
					<option value="<?= (int) $value; ?>" <?= (int) $filters['days'] === (int) $value ? 'selected' : ''; ?>><?= html_escape($label); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="doclinc-analytics-field">
			<label for="doclincDiagnosisKecamatan">Kecamatan</label>
			<select id="doclincDiagnosisKecamatan" name="kecamatan_id" <?= empty($kecamatan_options) ? 'disabled' : ''; ?>>
				<option value="0">Seluruh Kota Cilegon</option>
				<?php foreach ($kecamatan_options as $option): ?>
					<option value="<?= (int) $option['id_kecamatan']; ?>" <?= (int) $filters['kecamatan_id'] === (int) $option['id_kecamatan'] ? 'selected' : ''; ?>>
						<?= html_escape($option['nama_kecamatan']); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="doclinc-analytics-field">
			<label for="doclincDiagnosisCoding">Status pengodean</label>
			<select id="doclincDiagnosisCoding" name="coding_state">
				<?php foreach ($coding_options as $value => $label): ?>
					<option value="<?= html_escape($value); ?>" <?= (string) $filters['coding_state'] === (string) $value ? 'selected' : ''; ?>><?= html_escape($label); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="doclinc-analytics-actions">
			<button type="submit" class="btn btn-primary btn-sm">
				<i class="fas fa-filter" aria-hidden="true"></i> Terapkan
			</button>
			<a href="<?= html_escape(site_url('diagnosis')); ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
		</div>
	<?= form_close(); ?>

	<div class="doclinc-analytics-grid">
		<div class="doclinc-analytics-card">
			<span class="doclinc-analytics-card-label">Diagnosis tercatat</span>
			<span class="doclinc-analytics-card-value"><?= number_format($total_entries); ?></span>
			<span class="doclinc-analytics-card-hint">Satu baris = satu diagnosis pada satu rekam medis.</span>
		</div>
		<div class="doclinc-analytics-card">
			<span class="doclinc-analytics-card-label">Rekam medis terpisah</span>
			<span class="doclinc-analytics-card-value"><?= number_format($distinct_records); ?></span>
			<span class="doclinc-analytics-card-hint">Menunjukkan angkanya datang dari banyak kunjungan atau satu kunjungan dengan banyak diagnosis.</span>
		</div>
		<div class="doclinc-analytics-card">
			<span class="doclinc-analytics-card-label">Terkode ICD-10</span>
			<span class="doclinc-analytics-card-value"><?= number_format($coded); ?></span>
			<span class="doclinc-analytics-card-hint"><?= number_format($coded_percent); ?>% dari diagnosis tercatat.</span>
		</div>
		<div class="doclinc-analytics-card">
			<span class="doclinc-analytics-card-label">Belum dikode</span>
			<span class="doclinc-analytics-card-value"><?= number_format($uncoded); ?></span>
			<span class="doclinc-analytics-card-hint">Belum masuk peringkat karena kodenya belum ditetapkan.</span>
		</div>
		<div class="doclinc-analytics-card">
			<span class="doclinc-analytics-card-label">Kode berbeda</span>
			<span class="doclinc-analytics-card-value"><?= number_format($distinct_codes); ?></span>
			<span class="doclinc-analytics-card-hint">Kode unik dari diagnosis yang sudah dikode.</span>
		</div>
		<div class="doclinc-analytics-card">
			<span class="doclinc-analytics-card-label">Kecamatan berisi data</span>
			<span class="doclinc-analytics-card-value"><?= number_format((int) ($city['kecamatan_with_data'] ?? 0)); ?></span>
			<span class="doclinc-analytics-card-hint">Dari <?= number_format(count($kecamatan_options)); ?> kecamatan di master wilayah.</span>
		</div>
	</div>

	<div class="doclinc-analytics-panel">
		<h2 class="doclinc-analytics-panel-title">Kode ICD-10 terbanyak</h2>
		<p class="doclinc-analytics-panel-note">
			Sumber: <code>medicalrecord_diagnoses</code>. Waktu mengikuti urutan
			<code>clinical_finalized_at</code> &rarr; <code>visit_completed_at</code> &rarr; <code>completed_at</code>;
			<code>created_at</code> tidak dipakai karena itu waktu permintaan dibuat, bukan waktu layanan selesai.
			Peringkat hanya menghitung diagnosis yang sudah dikode; baris yang belum dikode dilaporkan terpisah di bawah.
		</p>
		<?php if (empty($top_codes)): ?>
			<div class="doclinc-analytics-empty">
				Belum ada diagnosis terkode pada periode ini. Peringkat diisi otomatis begitu tenaga kesehatan memilih kode ICD-10;
				selama itu, catatan yang belum dikode bisa dilihat pada panel &ldquo;Isi keranjang belum dikode&rdquo; di bawah.
			</div>
		<?php else: ?>
			<div class="doclinc-analytics-chart">
				<canvas id="doclincDiagnosisChart" role="img" aria-label="Grafik batang kode ICD-10 terbanyak pada periode terpilih"></canvas>
			</div>
			<div class="doclinc-analytics-tablewrap mt-3">
				<table class="doclinc-analytics-table doclinc-analytics-table-wide">
					<caption>Tabel ini berisi angka yang sama dengan grafik di atas, supaya bisa dibaca tanpa grafik.</caption>
					<thead>
						<tr>
							<th scope="col">Kode</th>
							<th scope="col">Label</th>
							<th scope="col" class="doclinc-analytics-number">Diagnosis</th>
							<th scope="col" class="doclinc-analytics-number">Rekam medis</th>
							<th scope="col">Porsi</th>
							<th scope="col">Sebaran</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($top_codes as $row): ?>
							<tr>
								<td><span class="doclinc-analytics-code"><?= html_escape($row['code']); ?></span></td>
								<td><?= html_escape($row['label'] === '' ? 'Tanpa label' : $row['label']); ?></td>
								<td class="doclinc-analytics-number"><?= number_format((int) $row['entries']); ?></td>
								<td class="doclinc-analytics-number"><?= number_format((int) $row['records']); ?></td>
								<td>
									<progress class="doclinc-analytics-bar" max="<?= max(1, $max_city_code_entries); ?>" value="<?= (int) $row['entries']; ?>"
										aria-label="Porsi <?= html_escape($row['code']); ?>: <?= (int) $row['entries']; ?> dari <?= (int) $max_city_code_entries; ?> pada kode terbanyak"></progress>
								</td>
								<td>
									<button type="button" class="doclinc-analytics-drill" data-doclinc-drill="<?= html_escape($row['code']); ?>" aria-expanded="false">
										<i class="fas fa-map-marked-alt" aria-hidden="true"></i> Per kecamatan
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="doclinc-analytics-panel">
		<h2 class="doclinc-analytics-panel-title">Ringkasan per kecamatan</h2>
		<p class="doclinc-analytics-panel-note">
			Wilayah ditentukan dari kode puskesmas pada permintaan layanan
			(<code>requests.assigned_puskesmas_code</code> &rarr; <code>puskesmas</code> &rarr; <code>kecamatan</code>),
			bukan dari alamat teks bebas.
		</p>
		<?php if (empty($by_kecamatan)): ?>
			<div class="doclinc-analytics-empty">
				<?= $wilayah_available
					? 'Belum ada diagnosis dengan wilayah yang bisa dipetakan pada periode ini.'
					: 'Master wilayah belum diisi, jadi ringkasan per kecamatan belum tersedia.'; ?>
			</div>
		<?php else: ?>
			<div class="doclinc-analytics-tablewrap">
				<table class="doclinc-analytics-table doclinc-analytics-table-wide">
					<caption>Jumlah baris per kecamatan; kode terbanyak dihitung di dalam kecamatan itu sendiri.</caption>
					<thead>
						<tr>
							<th scope="col">Kecamatan</th>
							<th scope="col" class="doclinc-analytics-number">Diagnosis</th>
							<th scope="col" class="doclinc-analytics-number">Rekam medis</th>
							<th scope="col">Pengodean</th>
							<th scope="col">Kode terbanyak</th>
							<th scope="col">Porsi</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($by_kecamatan as $row): ?>
							<tr>
								<td><?= html_escape($row['kecamatan']); ?></td>
								<td class="doclinc-analytics-number"><?= number_format((int) $row['entries']); ?></td>
								<td class="doclinc-analytics-number"><?= number_format((int) $row['records']); ?></td>
								<td>
									<span class="doclinc-analytics-badge doclinc-analytics-badge-coded"><?= number_format((int) $row['coded']); ?> terkode</span>
									<span class="doclinc-analytics-badge doclinc-analytics-badge-uncoded"><?= number_format((int) $row['uncoded']); ?> belum</span>
								</td>
								<td>
									<?php if ($row['top_code'] === ''): ?>
										<span class="doclinc-analytics-panel-note">Belum ada kode</span>
									<?php else: ?>
										<span class="doclinc-analytics-code"><?= html_escape($row['top_code']); ?></span>
										<?= html_escape($row['top_label'] === '' ? '' : ' - ' . $row['top_label']); ?>
										<span class="doclinc-analytics-number">(<?= number_format((int) $row['top_entries']); ?>)</span>
									<?php endif; ?>
								</td>
								<td>
									<progress class="doclinc-analytics-bar" max="<?= max(1, $max_kecamatan_entries); ?>" value="<?= (int) $row['entries']; ?>"
										aria-label="Porsi <?= html_escape($row['kecamatan']); ?>: <?= (int) $row['entries']; ?> dari <?= (int) $max_kecamatan_entries; ?> pada kecamatan terbesar"></progress>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		<?php if ($wilayah_available): ?>
			<p class="doclinc-analytics-panel-note mt-3 mb-0">
				Tanpa pemetaan wilayah: <span class="doclinc-analytics-number"><?= number_format((int) $unknown['entries']); ?></span> baris.
				<?= html_escape((string) $unknown['note']); ?>
			</p>
		<?php endif; ?>
	</div>

	<?php if (!empty($uncoded_top)): ?>
		<div class="doclinc-analytics-panel">
			<h2 class="doclinc-analytics-panel-title">Isi keranjang &ldquo;belum dikode&rdquo;</h2>
			<p class="doclinc-analytics-panel-note">
				Ditampilkan apa adanya supaya terlihat apa yang menunggu pengodean. Baris ini belum masuk peringkat kode ICD-10.
			</p>
			<div class="doclinc-analytics-tablewrap">
				<table class="doclinc-analytics-table">
					<caption>Keterangan diagnosis yang belum punya kode ICD-10 pada periode ini.</caption>
					<thead>
						<tr>
							<th scope="col">Keterangan</th>
							<th scope="col" class="doclinc-analytics-number">Diagnosis</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($uncoded_top as $row): ?>
							<tr>
								<td><?= html_escape($row['label']); ?></td>
								<td class="doclinc-analytics-number"><?= number_format((int) $row['entries']); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>

	<div class="doclinc-analytics-panel doclinc-analytics-drill-target" id="doclincDiagnosisDrill" hidden>
		<h2 class="doclinc-analytics-panel-title" id="doclincDiagnosisDrillTitle">Sebaran per kecamatan</h2>
		<p class="doclinc-analytics-panel-note" id="doclincDiagnosisDrillStatus" role="status">Pilih &ldquo;Per kecamatan&rdquo; pada salah satu kode.</p>
		<div class="doclinc-analytics-tablewrap" id="doclincDiagnosisDrillBody"></div>
	</div>
</div>

<script id="doclincDiagnosisBootstrap" type="application/json"><?= json_encode($chart_payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="<?= html_escape(base_url($analytics_js) . '?v=' . rawurlencode($analytics_js_version)); ?>"></script>
