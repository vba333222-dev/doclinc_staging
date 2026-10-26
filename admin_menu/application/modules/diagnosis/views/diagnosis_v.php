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
/* Panel pengodean: daftar teks yang belum punya kode dan tombol untuk
 * merapikannya. Dihitung `Diagnosis_coding_m`; view hanya menampilkan. */
$coding = isset($coding) && is_array($coding) ? $coding : array();
$coding_available = !empty($coding['available']);
$coding_master = !empty($coding['master_available']);
$coding_groups = isset($coding['groups']) && is_array($coding['groups']) ? $coding['groups'] : array();
$coding_totals = isset($coding['totals']) && is_array($coding['totals']) ? $coding['totals'] : array(
	'groups' => 0, 'rows' => 0,
	'ready_groups' => 0, 'ready_rows' => 0,
	'review_groups' => 0, 'review_rows' => 0,
	'manual_groups' => 0, 'manual_rows' => 0,
);
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
	'coded' => 'Hanya yang sudah dikode',
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
	/* Total terkode seluruh kota: dipakai tooltip untuk menyebut porsi tiap kode
	 * ("21 diagnosis tercatat (23% dari diagnosis terkode)"), memakai angka yang
	 * sudah tampil di kartu atas halaman. */
	'totalCoded' => $coded,
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
/* Daftar 10 kode teratas per kecamatan ikut dipindahkan ke baris peta, supaya
 * panel yang terbuka saat kecamatan diklik memakai angka yang sama dengan tabel
 * di halaman ini - bukan hasil hitung ulang di browser. */
$top_by_name = array();
/* Kode ICD-10 berbeda per kecamatan untuk angka "jenis penyakit" di panel.
 * Sumbernya `by_kecamatan`, bukan panjang daftar 10 kode teratas. */
$codes_by_name = array();
foreach ($by_kecamatan as $row) {
	$key = strtolower(trim((string) $row['kecamatan']));
	$coded_by_name[$key] = (int) $row['coded'];
	$codes_by_name[$key] = (int) ($row['codes_coded'] ?? 0);
	$top_rows = isset($row['top_codes']) && is_array($row['top_codes']) ? $row['top_codes'] : array();
	/* Cadangan untuk bentuk payload lama (tanpa `top_codes`): satu entri dari
	 * kolom `top_code`. Lebih baik menampilkan satu baris yang benar daripada
	 * panel kosong yang terbaca seperti "tidak ada penyakit". */
	if (empty($top_rows) && (string) ($row['top_code'] ?? '') !== '') {
		$top_rows = array(array(
			'code' => (string) $row['top_code'],
			'label' => (string) ($row['top_label'] ?? ''),
			'entries' => (int) ($row['top_entries'] ?? 0),
		));
	}
	$top_by_name[$key] = array_slice($top_rows, 0, 10);
}
$max_coded = 0;
foreach ($map_kecamatan as $index => $row) {
	$key = strtolower(trim((string) $row['kecamatan']));
	$map_kecamatan[$index]['coded'] = isset($coded_by_name[$key]) ? $coded_by_name[$key] : 0;
	$map_kecamatan[$index]['codes_coded'] = isset($codes_by_name[$key]) ? $codes_by_name[$key] : 0;
	$map_kecamatan[$index]['top_codes'] = isset($top_by_name[$key]) ? $top_by_name[$key] : array();
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
				Angka baru muncul setelah tenaga kesehatan mengisi diagnosis lewat pemilih ICD-10. Selama itu, panel &ldquo;Diagnosis belum dikode&rdquo; di bawah memuat catatan yang menunggu dikode.
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
			<label class="dl-adm-toolbar-label" for="doclincDiagnosisCoding">Status kode</label>
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
			<span class="doclinc-analytics-card-label">Sudah dikode</span>
			<span class="doclinc-analytics-card-value"><?= number_format($coded); ?></span>
			<span class="doclinc-analytics-card-hint"><?= number_format($coded_percent); ?>% dari diagnosis tercatat.</span>
		</div>
		<div class="doclinc-analytics-card">
			<span class="doclinc-analytics-card-label">Belum dikode</span>
			<span class="doclinc-analytics-card-value"><?= number_format($uncoded); ?></span>
			<span class="doclinc-analytics-card-hint">
				<?php if ($uncoded > 0): ?>
					Belum masuk peringkat karena kodenya belum ditetapkan.
					<a href="#doclincCodingPanel">Rapikan di bawah</a>.
				<?php else: ?>
					Semua diagnosis pada periode ini sudah punya kode ICD-10.
				<?php endif; ?>
			</span>
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
				<p class="doclinc-analytics-panel-note">
					Warna menunjukkan jumlah diagnosis yang sudah dikode per kecamatan untuk kode yang dipilih,
					atau penyakit yang paling banyak di tiap kecamatan.
					Klik satu kecamatan di peta untuk membuka kartu infografik penyakit di sana.
				</p>
			</div>
			<div class="doclinc-map-controls">
				<div class="dl-adm-toolbar-field">
					<label class="dl-adm-toolbar-label" for="doclincMapMode">Warna peta</label>
					<select class="form-control" id="doclincMapMode">
						<option value="count" selected>Jumlah per kode</option>
						<option value="dominant">Penyakit terbanyak</option>
					</select>
				</div>
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
				Belum ada diagnosis yang sudah dikode dengan wilayah pada periode ini.
			</div>
		<?php else: ?>
			<p class="doclinc-map-hint" id="doclincMapHint">
				Klik kecamatan di peta (atau fokuskan lalu tekan Enter) untuk membuka kartu infografik penyakit di kecamatan itu; tutup dengan Escape, klik area kosong peta, atau tombol tutup di kartu.
			</p>
			<div class="doclinc-map-stage">
				<div class="doclinc-map-canvas">
					<?php
					/* Bingkai peta = peta + kartu. Kartu diposisikan absolut di
					   dalam bingkai ini, jadi "menempel di tepi bawah peta" tetap
					   berarti tepi bawah peta, bukan tepi bawah legenda di
					   bawahnya. Legenda dan status sengaja berada di luar bingkai
					   supaya tidak ikut menjadi acuan posisi kartu. */
					?>
					<div class="doclinc-map-frame">
						<div id="doclincDiseaseMap" class="doclinc-map" role="group" aria-label="Peta Kota Cilegon per kecamatan dengan jumlah diagnosis sudah dikode" aria-describedby="doclincMapHint"></div>
						<?php
						/* Kartu infografik kecamatan. Ia mengambang DI ATAS peta,
						   bukan panel di bawahnya: klik pada peta adalah pertanyaan
						   tentang tempat yang sedang dilihat, dan jawabannya harus
						   muncul di tempat yang sama - panel di bawah peta menuntut
						   pengguna menggulir setelah setiap klik, sehingga warna
						   poligon yang baru berubah biru sudah keluar layar.
						   Seluruh isinya diisi JS dari payload server; view ini tidak
						   menghitung apa pun. */
						?>
						<div class="doclinc-map-card" id="doclincMapSelection" role="dialog" aria-modal="false"
							aria-labelledby="doclincMapSelectionTitle" aria-describedby="doclincMapSelectionStatus"
							tabindex="-1" hidden>
							<div class="doclinc-map-card-head">
								<div class="doclinc-map-card-heading">
									<p class="doclinc-map-card-eyebrow">Infografik kecamatan</p>
									<h3 class="doclinc-map-card-title" id="doclincMapSelectionTitle">Kecamatan</h3>
									<p class="doclinc-map-selection-scope" id="doclincMapSelectionStatus" role="status" aria-live="polite"></p>
								</div>
								<button type="button" class="doclinc-map-card-close" id="doclincMapClear"
									aria-label="Tutup kartu infografik kecamatan" title="Tutup">
									<i class="fas fa-times" aria-hidden="true"></i>
								</button>
							</div>
							<div class="doclinc-map-card-body">
								<div class="doclinc-map-figures" id="doclincMapFigures"></div>
								<div class="doclinc-map-share" id="doclincMapShare" role="img" aria-label="Porsi tiap kode penyakit di kecamatan ini"></div>
								<p class="doclinc-map-share-note" id="doclincMapShareNote"></p>
								<h4 class="doclinc-map-selection-subtitle">Kode ICD-10 terbanyak</h4>
								<ol class="doclinc-map-selection-list" id="doclincMapSelectionList"></ol>
							</div>
						</div>
					</div>
					<p class="doclinc-map-status" id="doclincMapStatus" role="status" aria-live="polite">Semua kode</p>
					<?php
					/* Legenda memakai swab, bukan gradien: warna peta dibagi ke enam
					   kelas tetap, dan kelas tetap tidak bisa dibaca dari gradien.
					   Angka tiap kelas diisi JS karena batasnya bergantung pada nilai
					   terbesar kode yang sedang dipilih. */
					?>
					<div class="doclinc-map-legend" id="doclincMapLegend">
						<span class="doclinc-map-legend-title">Diagnosis</span>
						<?php for ($level = 0; $level <= 5; $level++): ?>
							<span class="doclinc-map-legend-item">
								<span class="doclinc-map-swatch doclinc-map-swatch-level-<?= (int) $level; ?>" aria-hidden="true"></span>
								<span class="doclinc-map-legend-range" data-level="<?= (int) $level; ?>"><?= $level === 0 ? '0' : '&hellip;'; ?></span>
							</span>
						<?php endfor; ?>
						<span class="doclinc-map-legend-item doclinc-map-legend-selected">
							<span class="doclinc-map-swatch doclinc-map-swatch-selected" aria-hidden="true"></span>
							<span>dipilih</span>
						</span>
					</div>
					<?php
					/* Legenda kedua untuk mode "Penyakit terbanyak". Isinya dibuat
					   JS karena hue-nya mengikuti peringkat kode kota pada periode
					   ini; dua legenda tidak pernah tampil bersamaan. */
					?>
					<div class="doclinc-map-legend doclinc-map-legend-cats" id="doclincMapLegendCats" hidden></div>
					<p class="doclinc-map-attribution">
						Sumber batas kecamatan: Indonesia Geospasial (Ina-Geoportal), data terbuka.
					</p>
				</div>
			</div>
			<details class="doclinc-map-table">
				<summary>Angka di peta</summary>
				<div class="doclinc-analytics-tablewrap mt-2">
					<table class="doclinc-analytics-table doclinc-analytics-table-wide">
						<caption>Jumlah diagnosis yang sudah dikode per kecamatan untuk tiap kode.</caption>
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
			Hanya diagnosis yang sudah dikode yang masuk peringkat. Yang belum dikode ada di bawah.
		</p>
		<?php if (empty($top_codes)): ?>
			<div class="doclinc-analytics-empty">
				Belum ada diagnosis yang sudah dikode pada periode ini. Yang belum dikode ada di bawah.
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
							<th scope="col">Status kode</th>
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
									<span class="doclinc-analytics-badge doclinc-analytics-badge-coded"><?= number_format((int) $row['coded']); ?> sudah dikode</span>
									<span class="doclinc-analytics-badge doclinc-analytics-badge-uncoded"><?= number_format((int) $row['uncoded']); ?> belum dikode</span>
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

	<div class="doclinc-analytics-panel doclinc-coding-panel" id="doclincCodingPanel">
		<h2 class="doclinc-analytics-panel-title">Diagnosis belum dikode</h2>
		<p class="doclinc-analytics-panel-note">
			Diagnosis hanya masuk peringkat dan peta kalau punya kode ICD-10. Yang belum punya kode tidak dihapus,
			hanya menunggu dikode, jadi tetap terhitung di total. Daftar di bawah sengaja tidak mengikuti filter
			periode/kecamatan karena satu teks bisa muncul di banyak kunjungan.
		</p>

		<?php if (!$coding_available): ?>
			<div class="doclinc-analytics-note" role="status">
				<span class="doclinc-analytics-note-title">Data diagnosis belum tersedia</span>
				Belum ada teks yang bisa dirapikan di database ini.
			</div>
		<?php elseif (!$coding_master): ?>
			<div class="doclinc-analytics-note doclinc-analytics-note-warning" role="status">
				<span class="doclinc-analytics-note-title">Daftar kode ICD-10 tidak bisa dibaca</span>
				Tanpa daftar acuan, kode tidak bisa diperiksa &mdash; jadi panel ini tidak mengubah apa pun.
			</div>
		<?php elseif (empty($coding_groups)): ?>
			<div class="doclinc-analytics-empty">
				Saat ini tidak ada diagnosis yang belum punya kode ICD-10. Tidak ada yang perlu dirapikan.
			</div>
		<?php else: ?>
			<div class="doclinc-coding-figures">
				<div class="doclinc-coding-figure">
					<span class="doclinc-coding-figure-value"><?= number_format((int) $coding_totals['rows']); ?></span>
					<span class="doclinc-coding-figure-label">baris belum dikode (semua waktu)</span>
				</div>
				<div class="doclinc-coding-figure">
					<span class="doclinc-coding-figure-value"><?= number_format((int) $coding_totals['ready_rows']); ?></span>
					<span class="doclinc-coding-figure-label">bisa dikode otomatis</span>
				</div>
				<div class="doclinc-coding-figure">
					<span class="doclinc-coding-figure-value"><?= number_format((int) $coding_totals['review_rows'] + (int) $coding_totals['manual_rows']); ?></span>
					<span class="doclinc-coding-figure-label">harus dipilih manual</span>
				</div>
			</div>

			<?php if (!empty($coding['truncated'])): ?>
				<p class="doclinc-analytics-panel-note">
					Yang tampil hanya <?= number_format((int) $coding_totals['groups']); ?> teks terbanyak dari
					<?= number_format((int) $coding['total_rows']); ?> baris belum dikode. Rapikan ini dulu, lalu muat ulang
					halaman untuk sisanya.
				</p>
			<?php endif; ?>

			<?php if ((int) $coding_totals['ready_rows'] > 0): ?>
				<div class="doclinc-coding-action">
					<div class="doclinc-coding-action-text">
						<strong>Baris yang teksnya sudah memuat kode, misalnya &ldquo;Dermatitis atopik (L20.9)&rdquo;.</strong>
						Satu klik menuliskannya sebagai kode; teks aslinya tidak diubah dan baris yang sudah punya kode tidak disentuh.
					</div>
					<?= form_open('diagnosis/repair/' . $filter_segments, array('class' => 'doclinc-coding-action-form')); ?>
						<button type="submit" class="btn btn-primary dl-adm-action">
							<i class="fas fa-check-double" aria-hidden="true"></i>
							Kodekan otomatis <?= number_format((int) $coding_totals['ready_rows']); ?> baris
						</button>
					<?= form_close(); ?>
				</div>
			<?php endif; ?>

			<p class="doclinc-coding-help">
				Teks tanpa kode sama sekali (misalnya &ldquo;ISPA&rdquo;) tidak bisa ditebak. Pakai tombol
				<em>Pilih kode</em> pada barisnya, lalu pilih satu kode dari daftar.
			</p>

			<div class="doclinc-analytics-tablewrap">
				<table class="doclinc-analytics-table doclinc-coding-table">
					<caption>Teks yang belum punya kode ICD-10, apa adanya seperti yang diketik tenaga kesehatan.</caption>
					<thead>
						<tr>
							<th scope="col">Teks yang diketik</th>
							<th scope="col" class="doclinc-analytics-number">Baris</th>
							<th scope="col">Kode yang terbaca</th>
							<th scope="col">Tindakan</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($coding_groups as $group): ?>
							<?php
							$coding_status = (string) $group['status'];
							if ($coding_status === 'ready') {
								$coding_badge = 'doclinc-coding-badge-ready';
								$coding_badge_text = 'Bisa dikode otomatis';
							} elseif ($coding_status === 'review') {
								$coding_badge = 'doclinc-coding-badge-review';
								$coding_badge_text = 'Perlu diperiksa';
							} else {
								$coding_badge = 'doclinc-coding-badge-manual';
								$coding_badge_text = 'Perlu dipilih manual';
							}
							?>
							<tr>
								<td>
									<span class="doclinc-coding-text"><?= html_escape((string) $group['text']); ?></span>
									<?php if ($coding_status === 'ready' && $group['label'] !== '' && $group['label'] !== $group['text']): ?>
										<span class="doclinc-coding-sub">Tanpa kode: <?= html_escape((string) $group['label']); ?></span>
									<?php endif; ?>
								</td>
								<td class="doclinc-analytics-number"><?= number_format((int) $group['entries']); ?></td>
								<td>
									<span class="doclinc-coding-badge <?= $coding_badge; ?>"><?= $coding_badge_text; ?></span>
									<?php if ($group['code'] !== null): ?>
										<span class="doclinc-analytics-code"><?= html_escape((string) $group['code']); ?></span>
										<?php if ($group['code_label'] !== ''): ?>
											<span class="doclinc-coding-code-label"><?= html_escape((string) $group['code_label']); ?></span>
										<?php endif; ?>
									<?php endif; ?>
									<span class="doclinc-coding-sub"><?= html_escape(Diagnosis_coding_m::reasonLabel($group['reason'], $group['code'])); ?></span>
								</td>
								<td>
									<?php if ($coding_status === 'ready'): ?>
										<span class="doclinc-coding-sub">Ikut tombol di atas.</span>
									<?php else: ?>
										<?= form_open('diagnosis/pilih', array('class' => 'doclinc-coding-pick')); ?>
											<?= form_hidden('text', (string) $group['text']); ?>
											<?= form_hidden('days', (int) $filters['days']); ?>
											<?= form_hidden('kecamatan_id', (int) $filters['kecamatan_id']); ?>
											<?= form_hidden('coding_state', (string) $filters['coding_state']); ?>
											<button type="submit" class="btn btn-outline-secondary dl-adm-action">Pilih kode</button>
										<?= form_close(); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<details class="doclinc-coding-details">
				<summary>Bagaimana panel ini memutuskan?</summary>
				<ul>
					<li><strong>Bisa dikode otomatis</strong> &mdash; kodenya sudah tertulis di teks dan ada di daftar ICD-10.</li>
					<li><strong>Perlu dipilih manual</strong> &mdash; teks tidak memuat kode, atau kodenya kode induk (misalnya <code>A09</code>) yang bukan diagnosis.</li>
					<li>Tidak ada kode yang dikarang: kalau daftar tidak bisa dibaca, panel berhenti dan tidak menulis apa pun.</li>
					<li>Baris yang sudah punya kode tidak pernah ditimpa dan tidak ada data yang dihapus. Menekan tombol dua kali tidak mengubah apa-apa.</li>
				</ul>
			</details>
		<?php endif; ?>
	</div>

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
