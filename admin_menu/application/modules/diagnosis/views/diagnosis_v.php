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
/* Peta sebaran: data, aset, dan payload bootstrap. Leaflet dan GeoJSON disajikan
 * dari panel ini sendiri (assets/vendor/leaflet, assets/geojson), bukan CDN,
 * karena panel dipakai di jaringan Puskesmas yang tidak selalu punya internet. */
$map_geojson = 'assets/geojson/kecamatan-cilegon.geojson';
$leaflet_css = 'assets/vendor/leaflet/leaflet.css';
$leaflet_js = 'assets/vendor/leaflet/leaflet.js';
$map_js = 'assets/js/doclinc-disease-map.js';
$map_data = isset($payload['map']) && is_array($payload['map']) ? $payload['map'] : array('codes' => array(), 'kecamatan' => array());
$map_codes = isset($map_data['codes']) && is_array($map_data['codes']) ? $map_data['codes'] : array();
$map_kecamatan = isset($map_data['kecamatan']) && is_array($map_data['kecamatan']) ? $map_data['kecamatan'] : array();
$map_payload = array(
	'geojsonUrl' => base_url($map_geojson),
	'codes' => array(),
	'kecamatan' => $map_kecamatan,
	/* 1.700 ms per kode: cukup untuk membaca satu keadaan sebelum berganti, tanpa
	 * membuat orang menunggu seluruh putaran selesai. */
	'stepMs' => 1700,
);
foreach ($map_codes as $row) {
	$map_payload['codes'][] = array(
		'code' => (string) $row['code'],
		'label' => (string) $row['label'],
		'entries' => (int) $row['entries'],
	);
}
$map_max = 0;
foreach ($map_kecamatan as $row) {
	$map_max = max($map_max, (int) ($row['max_entries'] ?? 0));
}

/* Angka "semua kode" memakai jumlah diagnosis terkode dari SELURUH kode, bukan
 * jumlah delapan kode teratas. Tanpa ini, kecamatan yang diagnosisnya jatuh di
 * luar delapan kode teratas akan berlabel "0 diagnosis" padahal datanya ada -
 * persis kesalahan yang paling mudah dipercaya karena tampil sebagai angka. */
$coded_by_name = array();
foreach ($by_kecamatan as $row) {
	$coded_by_name[strtolower(trim((string) $row['kecamatan']))] = (int) $row['coded'];
}
$max_coded = 0;
foreach ($map_kecamatan as $index => $row) {
	$key = strtolower(trim((string) $row['kecamatan']));
	$map_kecamatan[$index]['coded'] = isset($coded_by_name[$key]) ? $coded_by_name[$key] : 0;
	$max_coded = max($max_coded, (int) $map_kecamatan[$index]['coded']);
}
$map_payload['kecamatan'] = $map_kecamatan;
$map_payload['maxEntries'] = $map_max;
$map_payload['maxCoded'] = $max_coded;
?>
<link rel="stylesheet" href="<?= html_escape(base_url($leaflet_css) . '?v=' . rawurlencode((string) @filemtime(FCPATH . $leaflet_css))); ?>">
<link rel="stylesheet" href="<?= html_escape(base_url($analytics_css) . '?v=' . rawurlencode($analytics_css_version)); ?>">

<div class="doclinc-analytics">
<?php
/* Kepala halaman dari partial bersama. `&middot;` diganti karakter `·` karena
   partial meng-escape teks: entitas HTML akan tampil harfiah kalau tetap
   ditulis sebagai entitas. */
$page_head = array(
	'title' => 'Penyakit terbanyak',
	'sub' => 'ICD-10 per kecamatan · ' . (string) ($period['label'] ?? 'Seluruh periode'),
	'actions' => array(
		array('kind' => 'link', 'label' => 'Unduh CSV', 'href' => $export_url, 'icon' => 'fas fa-file-csv', 'variant' => 'outline-primary'),
	),
);
$this->load->view('commons/page_head', array('page_head' => $page_head));
?>

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
	<?= form_open('diagnosis/filter', array('class' => 'dl-adm-toolbar', 'method' => 'post')); ?>
		<div class="dl-adm-toolbar-field">
			<label class="dl-adm-toolbar-label" for="doclincDiagnosisDays">Periode</label>
			<select class="form-control" id="doclincDiagnosisDays" name="days">
				<?php foreach ($period_options as $value => $label): ?>
					<option value="<?= (int) $value; ?>" <?= (int) $filters['days'] === (int) $value ? 'selected' : ''; ?>><?= html_escape($label); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="dl-adm-toolbar-field">
			<label class="dl-adm-toolbar-label" for="doclincDiagnosisKecamatan">Kecamatan</label>
			<select class="form-control" id="doclincDiagnosisKecamatan" name="kecamatan_id" <?= empty($kecamatan_options) ? 'disabled' : ''; ?>>
				<option value="0">Seluruh Kota Cilegon</option>
				<?php foreach ($kecamatan_options as $option): ?>
					<option value="<?= (int) $option['id_kecamatan']; ?>" <?= (int) $filters['kecamatan_id'] === (int) $option['id_kecamatan'] ? 'selected' : ''; ?>>
						<?= html_escape($option['nama_kecamatan']); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="dl-adm-toolbar-field">
			<label class="dl-adm-toolbar-label" for="doclincDiagnosisCoding">Status pengodean</label>
			<select class="form-control" id="doclincDiagnosisCoding" name="coding_state">
				<?php foreach ($coding_options as $value => $label): ?>
					<option value="<?= html_escape($value); ?>" <?= (string) $filters['coding_state'] === (string) $value ? 'selected' : ''; ?>><?= html_escape($label); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="dl-adm-toolbar-actions">
			<button type="submit" class="btn btn-primary dl-adm-action">
				<i class="fas fa-filter" aria-hidden="true"></i> Terapkan filter
			</button>
			<a href="<?= html_escape(site_url('diagnosis')); ?>" class="btn btn-outline-secondary dl-adm-action">Hapus filter</a>
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
			<span class="doclinc-analytics-card-hint">Asalnya dari banyak kunjungan atau satu kunjungan.</span>
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

	<div class="doclinc-analytics-panel doclinc-map-panel">
		<div class="doclinc-map-head">
			<div>
				<h2 class="doclinc-analytics-panel-title">Peta sebaran penyakit</h2>
				<p class="doclinc-analytics-panel-note">Warna dan gelembung mengikuti kode yang sedang diputar.</p>
			</div>
			<div class="doclinc-map-controls">
				<div class="dl-adm-toolbar-field">
					<label class="dl-adm-toolbar-label" for="doclincMapCode">Kode</label>
					<select class="form-control" id="doclincMapCode">
						<option value="">Semua kode</option>
						<?php foreach ($map_codes as $row): ?>
							<option value="<?= html_escape((string) $row['code']); ?>"><?= html_escape((string) $row['code']); ?><?= $row['label'] === '' ? '' : ' - ' . html_escape((string) $row['label']); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="button" class="btn btn-primary dl-adm-action" id="doclincMapPlay" aria-pressed="false" <?= empty($map_codes) ? 'disabled' : ''; ?>>
					<i class="fas fa-play" aria-hidden="true"></i> <span>Putar</span>
				</button>
			</div>
		</div>
		<?php if (empty($map_kecamatan)): ?>
			<div class="doclinc-analytics-empty">
				Belum ada diagnosis terkode dengan wilayah pada periode ini.
			</div>
		<?php else: ?>
			<div class="doclinc-map-stage">
				<div class="doclinc-map-canvas">
					<div id="doclincDiseaseMap" class="doclinc-map" role="img" aria-label="Peta Kota Cilegon per kecamatan dengan jumlah diagnosis terkode"></div>
					<p class="doclinc-map-status" id="doclincMapStatus" role="status" aria-live="polite">Semua kode</p>
					<div class="doclinc-map-legend" aria-hidden="true">
						<span>Sedikit</span>
						<span class="doclinc-map-legend-scale"></span>
						<span>Banyak</span>
					</div>
				</div>
				<div class="doclinc-map-ranking">
					<h3 class="doclinc-map-ranking-title" id="doclincMapRankingTitle">Kecamatan</h3>
					<ol class="doclinc-map-ranking-list" id="doclincMapRanking"></ol>
				</div>
			</div>
			<details class="doclinc-map-table">
				<summary>Angka di peta</summary>
				<div class="doclinc-analytics-tablewrap mt-2">
					<table class="doclinc-analytics-table doclinc-analytics-table-wide">
						<caption>Jumlah diagnosis terkode per kecamatan untuk tiap kode.</caption>
						<thead>
							<tr>
								<th scope="col">Kecamatan</th>
								<?php foreach ($map_codes as $row): ?>
									<th scope="col" class="doclinc-analytics-number"><?= html_escape((string) $row['code']); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($map_kecamatan as $row): ?>
								<tr>
									<th scope="row"><?= html_escape((string) $row['kecamatan']); ?></th>
									<?php foreach ($map_codes as $code_row): ?>
										<td class="doclinc-analytics-number"><?= number_format((int) ($row['by_code'][$code_row['code']] ?? 0)); ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>
		<?php endif; ?>
	</div>

	<div class="doclinc-analytics-panel">
		<h2 class="doclinc-analytics-panel-title">Kode ICD-10 terbanyak</h2>
		<p class="doclinc-analytics-panel-note">
			Hanya diagnosis terkode yang masuk peringkat. Yang belum dikode ada di panel bawah.
		</p>
		<?php if (empty($top_codes)): ?>
			<div class="doclinc-analytics-empty">
				Belum ada diagnosis terkode pada periode ini. Catatan yang belum dikode ada di panel bawah.
			</div>
		<?php else: ?>
			<div class="doclinc-analytics-chart">
				<canvas id="doclincDiagnosisChart" role="img" aria-label="Grafik batang kode ICD-10 terbanyak pada periode terpilih"></canvas>
			</div>
			<div class="doclinc-analytics-tablewrap mt-3">
				<table class="doclinc-analytics-table doclinc-analytics-table-wide">
					<caption>Angka yang sama dengan grafik.</caption>
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
					<caption>Baris per kecamatan; kode terbanyak di dalam kecamatan itu.</caption>
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
				Belum masuk peringkat ICD-10; menunggu pengodean.
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
<script id="doclincDiseaseMapBootstrap" type="application/json"><?= json_encode($map_payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="<?= html_escape(base_url($leaflet_js) . '?v=' . rawurlencode((string) @filemtime(FCPATH . $leaflet_js))); ?>"></script>
<script src="<?= html_escape(base_url($map_js) . '?v=' . rawurlencode((string) @filemtime(FCPATH . $map_js))); ?>"></script>
<script src="<?= html_escape(base_url($analytics_js) . '?v=' . rawurlencode($analytics_js_version)); ?>"></script>
