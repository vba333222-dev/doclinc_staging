<?php
/**
 * Dasbor Monitoring Layanan Kesehatan - panel admin Dinas Kesehatan.
 *
 * Semua angka datang dari `$monitoring` (hasil Home_m::monitoring_*). View ini
 * tidak menghitung apa pun dan tidak mengarang angka: kalau sumbernya kosong,
 * yang tampil adalah penjelasan kenapa kosong.
 *
 * Definisi setiap KPI, batas periode, dan aturan tingkat pengecualian ada di
 * Home_m (blok "MONITORING LAYANAN KESEHATAN"), bukan di sini.
 */
$request_status_labels = array(
	'Pending' => 'Menunggu konfirmasi Puskesmas',
	'Accepted' => 'Diterima',
	'in_service' => 'Sedang ditangani',
	'Completed' => 'Selesai',
	'Cancelled' => 'Dibatalkan',
);
$puskesmas_display_label = static function ($value) {
	$value = trim((string) $value);
	return $value === '' || in_array(strtoupper($value), array('DEFAULT', 'PUSKESMAS DEFAULT', 'LEGACY / BELUM TERKLASIFIKASI'), true)
		? 'Puskesmas belum tersedia'
		: $value;
};

$mon = isset($monitoring) && is_array($monitoring) ? $monitoring : array();
$period = isset($mon['periode']) && is_array($mon['periode']) ? $mon['periode'] : array();
$ringkasan = isset($mon['ringkasan']) && is_array($mon['ringkasan']) ? $mon['ringkasan'] : array();
$aktif = isset($mon['aktif']) && is_array($mon['aktif']) ? $mon['aktif'] : array();
$kesiapan = isset($mon['kesiapan']) && is_array($mon['kesiapan']) ? $mon['kesiapan'] : array();
$seri = isset($mon['seri']) && is_array($mon['seri']) ? $mon['seri'] : array();
$fasilitas = isset($mon['fasilitas']) && is_array($mon['fasilitas']) ? $mon['fasilitas'] : array();
$respons = isset($mon['respons']) && is_array($mon['respons']) ? $mon['respons'] : array();
$pengecualian = isset($mon['pengecualian']) && is_array($mon['pengecualian']) ? $mon['pengecualian'] : array();
$mutu = isset($mon['mutu_data']) && is_array($mon['mutu_data']) ? $mon['mutu_data'] : array();
$kesegaran = isset($mon['kesegaran']) && is_array($mon['kesegaran']) ? $mon['kesegaran'] : array();

$filters = isset($filters) && is_array($filters) ? $filters : array('periode' => '30', 'dari' => '', 'sampai' => '', 'puskesmas' => '');
$periode_options = isset($periode_options) && is_array($periode_options) ? $periode_options : array();
$puskesmas_options = isset($puskesmas_options) && is_array($puskesmas_options) ? $puskesmas_options : array();

$fasilitas_rows = isset($fasilitas['rows']) && is_array($fasilitas['rows']) ? $fasilitas['rows'] : array();
$mutu_checks = isset($mutu['checks']) && is_array($mutu['checks']) ? $mutu['checks'] : array();
/* Pemeriksaan tertentu dipakai panel lain sebagai catatan, misalnya kerangka
   jam yang bercampur pada seri tren. Dibuat sekali di sini supaya tidak ada
   pencarian berulang di tengah markup. */
$mutu_by_code = array();
foreach ($mutu_checks as $mutu_check) {
	if (isset($mutu_check['kode'])) {
		$mutu_by_code[(string) $mutu_check['kode']] = $mutu_check;
	}
}
$exception_rows = isset($pengecualian['rows']) && is_array($pengecualian['rows']) ? $pengecualian['rows'] : array();
/* Peta penanda tindak lanjut per permintaan. Kosong bila tabel penandanya belum
   ada; dalam keadaan itu tombolnya tidak ditampilkan sama sekali, bukan
   ditampilkan lalu gagal. */
$tindak_lanjut = isset($mon['tindak_lanjut']) && is_array($mon['tindak_lanjut']) ? $mon['tindak_lanjut'] : array();

$activity = isset($recent_activity) && is_array($recent_activity) ? $recent_activity : array();
$diagnosis_analytics = isset($diagnosis_analytics) && is_array($diagnosis_analytics) ? $diagnosis_analytics : array();
$top_diagnoses = isset($diagnosis_analytics['top_diagnoses']) && is_array($diagnosis_analytics['top_diagnoses']) ? $diagnosis_analytics['top_diagnoses'] : array();
$diagnosis_distribution = isset($diagnosis_analytics['puskesmas_distribution']) && is_array($diagnosis_analytics['puskesmas_distribution']) ? $diagnosis_analytics['puskesmas_distribution'] : array();
$diagnosis_period_days = (int) ($diagnosis_analytics['period_days'] ?? 30);
$max_top_diagnosis = (int) ($diagnosis_analytics['max_top_total'] ?? 0);
$max_puskesmas_diagnosis = (int) ($diagnosis_analytics['max_puskesmas_total'] ?? 0);
$nakes_presence_enabled = !empty($nakes_presence_enabled);

$severity_labels = array(
	'kritis' => 'Kritis',
	'peringatan' => 'Peringatan',
	'informasi' => 'Informasi',
);
$format_delta = static function ($value) {
	$value = (int) $value;
	if ($value === 0) {
		return 'Sama dengan periode sebelumnya';
	}
	return ($value > 0 ? 'Naik ' : 'Turun ') . number_format(abs($value)) . ' dibanding periode sebelumnya';
};

/* Berkas gaya khusus permukaan ini. Satu CSS per permukaan; tokennya dari
   assets/css/doclinc-reference-ui.css yang sudah dimuat header panel. */
$monitoring_css = 'assets/css/doclinc-admin-monitoring.css';
$monitoring_js = 'assets/js/doclinc-admin-monitoring.js';
$monitoring_css_version = (string) @filemtime(FCPATH . $monitoring_css);
$monitoring_js_version = (string) @filemtime(FCPATH . $monitoring_js);
?>
<link rel="stylesheet" href="<?= html_escape(base_url($monitoring_css) . '?v=' . rawurlencode($monitoring_css_version)); ?>">


<!-- Bagian "Konteks pelaporan" dipindah ke bawah formulir filter. -->

<div class="dl-mon">

<?php
/* Kepala halaman dari partial bersama. Modal `#dlMonHelp` yang sama masih dipakai
   tombol penjelasan di kepala panel diagnosis, jadi id dan isinya tidak berubah. */
$page_head = array(
	'title' => 'Monitoring Layanan Kesehatan',
	'sub' => 'Ringkasan pelayanan warga Kota Cilegon.',
	'actions' => array(
		array('kind' => 'help', 'label' => 'Cara membaca angka di halaman ini', 'target' => '#dlMonHelp'),
		array('kind' => 'link', 'label' => 'Pantau konsultasi', 'href' => site_url('konsultasi_kesehatan'), 'icon' => 'fas fa-stethoscope', 'variant' => 'outline-primary'),
	),
);
$this->load->view('commons/page_head', array('page_head' => $page_head));
?>

<?php if (!empty($period['notice'])): ?>
	<div class="alert alert-warning dl-mon-notice" role="status">
		<i class="fas fa-exclamation-triangle mr-2" aria-hidden="true"></i><?= html_escape((string) $period['notice']); ?>
	</div>
<?php endif; ?>

<form method="get" action="<?= html_escape(base_url('home')); ?>" class="dl-adm-toolbar mb-4" id="dlMonFilter">
	<div class="dl-adm-toolbar-field">
		<label class="dl-adm-toolbar-label" for="dlMonPeriode">Periode laporan</label>
		<select name="periode" id="dlMonPeriode" class="form-control" data-dl-mon-periode>
			<?php foreach ($periode_options as $value => $label): ?>
				<option value="<?= html_escape((string) $value); ?>" <?= ($filters['periode'] === (string) $value) ? 'selected' : ''; ?>><?= html_escape((string) $label); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="dl-adm-toolbar-field">
		<label class="dl-adm-toolbar-label" for="dlMonDari">Tanggal awal</label>
		<input type="date" name="dari" id="dlMonDari" class="form-control" data-dl-mon-custom
			value="<?= html_escape((string) ($period['dari_input'] ?? '')); ?>">
	</div>
	<div class="dl-adm-toolbar-field">
		<label class="dl-adm-toolbar-label" for="dlMonSampai">Tanggal akhir</label>
		<input type="date" name="sampai" id="dlMonSampai" class="form-control" data-dl-mon-custom
			value="<?= html_escape((string) ($period['sampai_input'] ?? '')); ?>">
	</div>
	<div class="dl-adm-toolbar-field">
		<label class="dl-adm-toolbar-label" for="dlMonPuskesmas">Puskesmas</label>
		<select name="puskesmas" id="dlMonPuskesmas" class="form-control">
			<option value="">Semua Puskesmas</option>
			<?php foreach ($puskesmas_options as $option): ?>
				<?php $option_code = (string) $option->kode_pkm; ?>
				<option value="<?= html_escape($option_code); ?>" <?= ($filters['puskesmas'] === $option_code) ? 'selected' : ''; ?>>
					<?= html_escape((string) $option->nama_puskesmas); ?><?= ((string) $option->status === 'aktif') ? '' : ' (nonaktif)'; ?>
				</option>
			<?php endforeach; ?>
			<option value="__legacy__" <?= ($filters['puskesmas'] === '__legacy__') ? 'selected' : ''; ?>>Puskesmas belum tersedia</option>
		</select>
	</div>
	<div class="dl-adm-toolbar-actions">
		<button type="submit" class="btn btn-primary dl-adm-action">Terapkan filter</button>
		<a href="<?= html_escape(site_url('home')); ?>" class="btn btn-outline-secondary dl-adm-action">Hapus filter</a>
	</div>
</form>

<section class="dl-mon-context" aria-label="Konteks pelaporan">
	<div class="dl-mon-context-item">
		<span class="dl-mon-context-key">Periode aktif</span>
		<span class="dl-mon-context-value"><?= html_escape((string) ($period['label'] ?? '-')); ?></span>
		<span class="dl-mon-context-note">
			<?= number_format((int) ($period['days'] ?? 0)); ?> hari &middot; agregasi <?= html_escape((string) ($period['bucket_label'] ?? 'harian')); ?>
		</span>
	</div>
	<div class="dl-mon-context-item">
		<span class="dl-mon-context-key">Pembanding</span>
		<span class="dl-mon-context-value"><?= html_escape((string) ($period['previous_label'] ?? '-')); ?></span>
	</div>
	<div class="dl-mon-context-item">
		<span class="dl-mon-context-key">Permintaan terakhir masuk</span>
		<span class="dl-mon-context-value"><?= html_escape((string) ($kesegaran['label'] ?? '-')); ?></span>
		<span class="dl-mon-context-note">
			<?php if (isset($kesegaran['menit_lalu']) && $kesegaran['menit_lalu'] !== null): ?>
				<?= number_format((int) $kesegaran['menit_lalu']); ?> menit lalu &middot; event terakhir <?= html_escape((string) ($kesegaran['terakhir_event'] ?? '-')); ?>
			<?php else: ?>
				Belum ada permintaan yang tercatat.
			<?php endif; ?>
		</span>
	</div>
	<div class="dl-mon-context-item">
		<span class="dl-mon-context-key">Mutu pelaporan</span>
		<span class="dl-mon-context-value">
			<?php if (!empty($mutu['ada_temuan'])): ?>
				<?= number_format((int) ($mutu['jumlah_kritis'] ?? 0)); ?> kritis &middot; <?= number_format((int) ($mutu['jumlah_peringatan'] ?? 0)); ?> peringatan
			<?php else: ?>
				Tidak ada temuan
			<?php endif; ?>
		</span>
	</div>
</section>

<?php
/* Kartu KPI periode: label, nilai, satuan, penanda pendek satu baris,
   pembanding (hanya bila pembandingnya sah), dan tautan tindak lanjut bila ada
   halaman detailnya. Tidak ada panah tren hiasan, dan tidak ada warna yang
   menyiratkan baik/buruk tanpa arah target yang jelas.

   Definisi lengkap tiap KPI tidak lagi dicetak di kartu, melainkan di modal
   penjelasan lewat kunci 'definisi'. Kartu hanya memuat penanda yang mencegah
   salah baca ('petunjuk'); sisanya ada satu klik dari tombol informasi. */
$kpi_cards = array();
$kpi_cards[] = array(
	'label' => 'Permintaan Masuk',
	'value' => number_format((int) ($ringkasan['masuk'] ?? 0)),
	'unit' => 'permintaan',
	'petunjuk' => 'Termasuk yang dibatalkan.',
	'definisi' => 'Dibuat pada periode terpilih. Permintaan yang dibatalkan ikut dihitung.',
	'delta' => $format_delta($ringkasan['delta_masuk'] ?? 0),
	'link' => '',
	'kosong' => false,
);
$kpi_cards[] = array(
	'label' => 'Layanan Selesai',
	'value' => number_format((int) ($ringkasan['selesai'] ?? 0)),
	'unit' => 'layanan',
	'petunjuk' => 'Dihitung dari waktu selesai.',
	'definisi' => 'Diukur dari waktu selesai (completed_at), bukan waktu masuk. '
		. number_format((int) ($ringkasan['selesai_dari_sebelumnya'] ?? 0))
		. ' di antaranya dimulai pada periode sebelumnya.',
	'delta' => $format_delta($ringkasan['delta_selesai'] ?? 0),
	'link' => '',
	'kosong' => false,
);
/* Dua angka berikut tidak lagi berdiri sebagai kartu kunci: "Permintaan Aktif"
   sudah muncul di jalur beban kerja tepat di bawah grid (keadaan sekarang,
   bukan angka periode), dan "Median Waktu Respons" pindah ke kepala panel alur
   30 hari karena satu periode dengan grafiknya. Definisinya tetap dikirim ke
   modal penjelasan lewat `$kpi_extra` supaya tidak ada istilah yang tampil
   tanpa arti. */
$kpi_extra = array();
$kpi_extra[] = array(
	'label' => 'Permintaan Aktif',
	'value' => number_format((int) ($aktif['aktif'] ?? 0)),
	'unit' => 'permintaan',
	'petunjuk' => 'Keadaan sekarang, bukan angka periode.',
	'definisi' => 'Beban berjalan saat ini (Pending + Accepted). Keadaan sekarang, bukan angka periode.',
	'delta' => '',
	'link' => '',
	'kosong' => false,
);
$median_seconds = $respons['median_seconds'] ?? null;
$kpi_extra[] = array(
	'label' => 'Median Waktu Respons',
	'value' => ($median_seconds === null) ? '—' : $this->Home_m->monitoring_duration_label($median_seconds),
	'unit' => ($median_seconds === null) ? '' : 'dari masuk ke diterima',
	'petunjuk' => ($median_seconds === null) ? '' : 'Hanya yang punya kedua jejak.',
	'definisi' => ($median_seconds === null)
		? 'Belum dapat dihitung: belum ada permintaan pada periode ini yang punya jejak masuk dan jejak diterima.'
		: 'Dari ' . number_format((int) ($respons['sampel'] ?? 0)) . ' permintaan yang punya kedua jejak. '
			. number_format((int) ($respons['tanpa_terima'] ?? 0)) . ' belum punya waktu terima sehingga tidak diikutkan.',
	'delta' => '',
	'link' => '',
	'kosong' => ($median_seconds === null),
);
$kpi_cards[] = array(
	'label' => 'Perlu Tindak Lanjut',
	'value' => number_format((int) ($pengecualian['total'] ?? 0)),
	'unit' => 'permintaan',
	'petunjuk' => '',
	'definisi' => 'Belum diterima melewati ' . (int) ($pengecualian['overdue_minutes'] ?? 20)
		. ' menit, atau Puskesmas tujuan belum ditetapkan, atau sudah diterima tanpa PIC aktif.',
	'delta' => '',
	'link' => '#dlMonPengecualian',
	'kosong' => false,
);
$kpi_cards[] = array(
	'label' => 'Puskesmas Beroperasi',
	'value' => number_format((int) ($kesiapan['beroperasi'] ?? 0)),
	'unit' => 'dari ' . number_format((int) ($kesiapan['terdaftar'] ?? 0)) . ' terdaftar',
	'petunjuk' => 'Staf aktif bertaut akun nakes aktif.',
	'definisi' => 'Fasilitas aktif yang punya minimal satu staf aktif bertaut akun nakes aktif. Akun aktif saja belum cukup.',
	'delta' => '',
	'link' => '',
	'kosong' => false,
);
?>

<section class="dl-mon-kpis" aria-labelledby="dlMonKpiTitle">
	<h2 class="dl-mon-section-title" id="dlMonKpiTitle">Ringkasan periode</h2>
	<div class="dl-mon-kpi-grid">
		<?php foreach ($kpi_cards as $card): ?>
			<article class="dl-mon-kpi<?= !empty($card['kosong']) ? ' is-kosong' : ''; ?>">
				<p class="dl-mon-kpi-label"><?= html_escape((string) $card['label']); ?></p>
				<p class="dl-mon-kpi-value">
					<span class="dl-mon-num"><?= html_escape((string) $card['value']); ?></span>
					<?php if ($card['unit'] !== ''): ?><span class="dl-mon-kpi-unit"><?= html_escape((string) $card['unit']); ?></span><?php endif; ?>
				</p>
				<?php if (($card['petunjuk'] ?? '') !== ''): ?>
					<p class="dl-mon-kpi-note"><?= html_escape((string) $card['petunjuk']); ?></p>
				<?php endif; ?>
				<?php if ($card['delta'] !== ''): ?>
					<p class="dl-mon-kpi-delta"><?= html_escape((string) $card['delta']); ?></p>
				<?php endif; ?>
				<?php if ($card['link'] !== ''): ?>
					<p class="dl-mon-kpi-link"><a href="<?= html_escape((string) $card['link']); ?>">Lihat daftarnya</a></p>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>

	<div class="dl-mon-strip" role="group" aria-label="Beban kerja saat ini">
		<p class="dl-mon-strip-title">Beban kerja saat ini (seluruh riwayat)</p>
		<?php /* Baris status yang jujur: menyebut mekanisme dan waktunya, bukan
		   berpura-pura angka ini mengalir seketika. */ ?>
		<p class="dl-mon-live" data-dl-mon-live-status role="status" aria-live="polite">
			<span class="dl-mon-live-dot" aria-hidden="true"></span>
			<span>Diperbarui tiap 30 detik &middot; terakhir <span class="dl-mon-num" data-dl-mon-live="jam">—</span></span>
		</p>
		<dl class="dl-mon-strip-items">
			<div class="dl-mon-strip-item">
				<dt>Menunggu konfirmasi Puskesmas</dt>
				<dd class="dl-mon-num" data-dl-mon-live="pending"><?= number_format((int) ($aktif['pending'] ?? 0)); ?></dd>
			</div>
			<div class="dl-mon-strip-item">
				<dt>Diterima</dt>
				<dd class="dl-mon-num" data-dl-mon-live="accepted"><?= number_format((int) ($aktif['accepted'] ?? 0)); ?></dd>
			</div>
			<div class="dl-mon-strip-item">
				<dt>Belum selesai (total)</dt>
				<dd class="dl-mon-num" data-dl-mon-live="aktif"><?= number_format((int) ($aktif['aktif'] ?? 0)); ?></dd>
			</div>
			<div class="dl-mon-strip-item">
				<dt>Selesai</dt>
				<dd class="dl-mon-num" data-dl-mon-live="kumulatif_selesai"><?= number_format((int) ($aktif['kumulatif_selesai'] ?? 0)); ?></dd>
			</div>
			<div class="dl-mon-strip-item">
				<dt>Dibatalkan</dt>
				<dd class="dl-mon-num" data-dl-mon-live="kumulatif_batal"><?= number_format((int) ($aktif['kumulatif_batal'] ?? 0)); ?></dd>
			</div>
			<div class="dl-mon-strip-item">
				<dt>Total permintaan</dt>
				<dd class="dl-mon-num" data-dl-mon-live="kumulatif_masuk"><?= number_format((int) ($aktif['kumulatif_masuk'] ?? 0)); ?></dd>
			</div>
		</dl>
	</div>
</section>

<section class="dl-mon-panel" id="dlMonPengecualian" aria-labelledby="dlMonPengecualianTitle">
	<div class="card doclinc-dashboard-panel dl-mon-alerts">
		<div class="card-header dl-mon-panel-head">
			<div>
				<h2 class="h6 mb-1 font-weight-bold" id="dlMonPengecualianTitle">Perlu tindak lanjut</h2>
			</div>
			<span class="dl-mon-badge"><span data-dl-mon-live="eksepsi_total"><?= number_format((int) ($pengecualian['total'] ?? 0)); ?></span> temuan</span>
		</div>
		<div class="card-body">
			<?php if (!$pengecualian['sumber_tersedia']): ?>
				<?php
				$empty_state = array(
					'title' => 'Data permintaan belum tersedia; pengecualian belum bisa diperiksa.',
				);
				$this->load->view('commons/empty_state', array('empty_state' => $empty_state));
				?>
			<?php else: ?>
				<dl class="dl-mon-severity">
					<div class="dl-mon-severity-item dl-mon-severity-item--kritis">
						<dt>Kritis</dt>
						<dd class="dl-mon-num" data-dl-mon-live="eksepsi_kritis"><?= number_format((int) ($pengecualian['jumlah_kritis'] ?? 0)); ?></dd>
						<span>Tujuan belum ditetapkan, atau terlambat diterima.</span>
					</div>
					<div class="dl-mon-severity-item dl-mon-severity-item--peringatan">
						<dt>Peringatan</dt>
						<dd class="dl-mon-num" data-dl-mon-live="eksepsi_peringatan"><?= number_format((int) ($pengecualian['jumlah_peringatan'] ?? 0)); ?></dd>
						<span>Belum diterima, sudah melewati ambang pengingat.</span>
					</div>
					<div class="dl-mon-severity-item dl-mon-severity-item--informasi">
						<dt>Informasi</dt>
						<dd class="dl-mon-num" data-dl-mon-live="eksepsi_informasi"><?= number_format((int) ($pengecualian['jumlah_informasi'] ?? 0)); ?></dd>
						<span>Sudah diterima tetapi belum ada PIC aktif.</span>
					</div>
				</dl>

				<?php /* Angka kemajuan pengawasan. Dihitung atas SELURUH pengecualian,
				   bukan hanya sepuluh baris yang ditampilkan, supaya angkanya tidak
				   berubah mengikuti panjang daftar. */ ?>
				<?php if ((int) ($pengecualian['sudah_ditandai'] ?? 0) > 0): ?>
					<p class="dl-mon-followup-progress">
						<i class="fas fa-check-circle mr-1" aria-hidden="true"></i>
						<span class="dl-mon-num"><?= number_format((int) $pengecualian['sudah_ditandai']); ?></span>
						dari <span class="dl-mon-num"><?= number_format((int) ($pengecualian['total'] ?? 0)); ?></span>
						pengecualian sudah ditandai ditindaklanjuti.
					</p>
				<?php endif; ?>

				<?php if (empty($exception_rows)): ?>
					<?php
					$empty_state = array(
						'title' => 'Tidak ada permintaan yang memenuhi aturan eskalasi.',
						'message' => 'Keadaan ini diukur pada saat halaman dibuka, bukan pada periode laporan.',
					);
					$this->load->view('commons/empty_state', array('empty_state' => $empty_state));
					?>
				<?php else: ?>
					<?php
					/* Empat temuan teratas cukup untuk membaca keadaan; sisanya (sampai
					   batas sepuluh baris dari model) dibuka satu klik. Tanpa pemisahan
					   ini daftar pengecualian yang panjang mendorong grafik alur dan
					   tabel fasilitas jauh di bawah lipatan, padahal pertanyaan pertama
					   halaman ini ada di bagian atas. `<details>` dipakai supaya daftar
					   tetap lengkap di DOM dan tetap terbaca tanpa JavaScript. */
					$exception_visible = array_slice($exception_rows, 0, 4);
					$exception_rest = array_slice($exception_rows, 4);
					$security = $this->security;
					$render_exception = function ($row) use ($tindak_lanjut, $severity_labels, $request_status_labels, $puskesmas_display_label, $security) {
						$tingkat = (string) ($row->tingkat ?? 'informasi');
						$exception_request_id = (int) ($row->request_id ?? 0);
						$sudah_ditandai = isset($tindak_lanjut[$exception_request_id]);
						ob_start();
						?>
						<li class="dl-mon-exception dl-mon-exception--<?= html_escape($tingkat); ?><?= $sudah_ditandai ? ' is-ditandai' : ''; ?>">
							<div class="dl-mon-exception-head">
								<span class="dl-mon-tag dl-mon-tag--<?= html_escape($tingkat); ?>"><?= html_escape($severity_labels[$tingkat] ?? 'Informasi'); ?></span>
								<span class="dl-mon-exception-id dl-mon-num">REQ-<?= html_escape((string) ($row->request_id ?? '-')); ?></span>
								<span class="dl-mon-exception-wait dl-mon-num"><?= html_escape((string) ($row->menit_label ?? '-')); ?></span>
							</div>
							<p class="dl-mon-exception-reason"><?= html_escape((string) ($row->alasan ?? 'Perlu dipantau')); ?></p>
							<p class="dl-mon-exception-meta">
								<?= html_escape($puskesmas_display_label($row->puskesmas ?? '')); ?>
								&middot; <?= html_escape((string) ($request_status_labels[$row->status ?? ''] ?? 'Status belum tersedia')); ?>
								&middot; dibuat <?= html_escape((string) ($row->created_at_label ?? '-')); ?>
							</p>
							<?php /* Dua aksi satu temuan berdampingan, bukan bertumpuk: tautan
							   "Buka di Pantau konsultasi" dan tombol tandai adalah satu
							   keputusan, dan menumpuknya menambah ~50px per kartu tanpa
							   menambah arti. */ ?>
							<div class="dl-mon-exception-actions">
								<p class="dl-mon-exception-action">
									<a href="<?= html_escape(site_url('konsultasi_kesehatan')); ?>">Buka di Pantau konsultasi</a>
								</p>
								<?php if ($exception_request_id > 0): ?>
									<?php /* Formulir biasa, bukan tombol JavaScript saja: penandaan tetap
									   dapat dilakukan walau skrip gagal dimuat. Skrip hanya menambah
									   kenyamanan dengan menghindari muat ulang halaman. */ ?>
									<form method="post" class="dl-mon-followup"
										action="<?= html_escape(site_url($sudah_ditandai ? 'home/followup_unmark' : 'home/followup_mark')); ?>"
										data-dl-mon-followup="<?= $sudah_ditandai ? 'unmark' : 'mark'; ?>"
										data-request-id="<?= html_escape((string) $exception_request_id); ?>">
										<input type="hidden" name="request_id" value="<?= html_escape((string) $exception_request_id); ?>">
										<input type="hidden" name="<?= html_escape($security->get_csrf_token_name()); ?>" value="<?= html_escape($security->get_csrf_hash()); ?>">
										<button type="submit" class="btn btn-sm dl-mon-followup-btn <?= $sudah_ditandai ? 'btn-outline-secondary' : 'btn-outline-primary'; ?>">
											<i class="fas <?= $sudah_ditandai ? 'fa-undo' : 'fa-check'; ?> mr-1" aria-hidden="true"></i>
											<?= $sudah_ditandai ? 'Batalkan tanda' : 'Tandai sudah ditindaklanjuti'; ?>
										</button>
									</form>
								<?php endif; ?>
							</div>
							<?php if ($sudah_ditandai): ?>
								<p class="dl-mon-followup-state">
									<i class="fas fa-check-circle mr-1" aria-hidden="true"></i>
									Ditandai sudah ditindaklanjuti pada
									<span class="dl-mon-num"><?= html_escape((string) ($tindak_lanjut[$exception_request_id]['ditandai_label'] ?? '-')); ?></span>.
								</p>
							<?php endif; ?>
						</li>
						<?php
						return ob_get_clean();
					};
					?>
					<ul class="dl-mon-exception-list">
						<?php foreach ($exception_visible as $row) { echo $render_exception($row); } ?>
					</ul>
					<?php if (!empty($exception_rest)): ?>
						<details class="dl-mon-more">
							<summary>Lihat <?= number_format(count($exception_rest)); ?> temuan lainnya</summary>
							<ul class="dl-mon-exception-list">
								<?php foreach ($exception_rest as $row) { echo $render_exception($row); } ?>
							</ul>
						</details>
					<?php endif; ?>
					<?php if (!empty($pengecualian['terpotong'])): ?>
						<p class="dl-mon-hint mb-0">Daftar dipotong pada 10 baris teratas. Angka di atas tetap jumlah keseluruhan.</p>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ((int) ($pengecualian['sudah_dieskalasi'] ?? 0) > 0): ?>
					<p class="dl-adm-panel-note">
						<?= number_format((int) $pengecualian['sudah_dieskalasi']); ?> permintaan pernah dieskalasi otomatis
						oleh pengawas permintaan.
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
</section>
<section class="dl-mon-panel" aria-labelledby="dlMonTrenTitle">
	<div class="card doclinc-dashboard-panel">
		<div class="card-header dl-mon-panel-head">
			<div>
				<h2 class="h6 mb-1 font-weight-bold" id="dlMonTrenTitle">Tren layanan <?= html_escape((string) ($period['bucket_label'] ?? 'harian')); ?></h2>
				<?php /* Median waktu respons tinggal di sini, bukan sebagai kartu kunci:
				   ia satu periode dengan grafiknya, dan angka itu hanya berarti bila
				   disebut bersama rentangnya. Kalau belum dapat dihitung, kalimatnya
				   menyebut sebabnya - bukan mencetak tanda pisah tanpa penjelasan. */ ?>
				<p class="dl-mon-panel-sub">
					<?php if ($median_seconds === null): ?>
						Median waktu respons belum dapat dihitung pada periode ini: belum ada permintaan yang punya jejak masuk dan jejak diterima.
					<?php else: ?>
						Median waktu respons <span class="dl-mon-num"><?= html_escape($this->Home_m->monitoring_duration_label($median_seconds)); ?></span>
						dari <span class="dl-mon-num"><?= number_format((int) ($respons['sampel'] ?? 0)); ?></span> permintaan yang punya kedua jejak.
					<?php endif; ?>
				</p>
			</div>
			<span class="dl-mon-badge"><?= number_format((int) ($period['days'] ?? 0)); ?> hari</span>
		</div>
		<div class="card-body">
			<?php if (empty($seri['ada_data'])): ?>
				<?php
				/* Keadaan kosong dari partial bersama: judul menyebut keadaan,
				   keterangan menyebut langkah berikutnya. */
				$empty_state = array(
					'title' => 'Belum ada permintaan maupun layanan selesai pada periode ini.',
					'message' => 'Pilih periode lain, atau periksa apakah tanggalnya memang di luar rentang ini.',
				);
				$this->load->view('commons/empty_state', array('empty_state' => $empty_state));
				?>
			<?php else: ?>
				<div class="dl-mon-chart-wrap">
					<canvas id="dlMonTrendChart" role="img"
						aria-label="Grafik garis jumlah permintaan masuk dan layanan selesai per <?= html_escape((string) ($seri['bucket_label'] ?? 'hari')); ?> pada periode terpilih. Tabel di bawah memuat angka yang sama."></canvas>
				</div>
			<?php endif; ?>

			<div class="dl-mon-table-wrap">
				<table class="table table-sm dl-mon-table">
					<caption class="dl-mon-caption">Angka yang sama dengan grafik.</caption>
					<thead>
						<tr>
							<th scope="col"><?= html_escape(($seri['bucket'] ?? 'day') === 'week' ? 'Pekan' : 'Tanggal'); ?></th>
							<th scope="col" class="dl-mon-num-col">Permintaan masuk</th>
							<th scope="col" class="dl-mon-num-col">Layanan selesai</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach (($seri['labels'] ?? array()) as $index => $label): ?>
							<tr>
								<th scope="row"><?= html_escape((string) ($seri['range_labels'][$index] ?? $label)); ?></th>
								<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) ($seri['masuk'][$index] ?? 0)); ?></td>
								<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) ($seri['selesai'][$index] ?? 0)); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th scope="row">Total</th>
							<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) ($seri['total_masuk'] ?? 0)); ?></td>
							<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) ($seri['total_selesai'] ?? 0)); ?></td>
						</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>
</section>
<section class="dl-mon-panel" aria-labelledby="dlMonFasilitasTitle">
	<div class="card doclinc-dashboard-panel">
		<div class="card-header dl-mon-panel-head">
			<div>
				<h2 class="h6 mb-1 font-weight-bold" id="dlMonFasilitasTitle">Aktivitas per Puskesmas</h2>
			</div>
			<span class="dl-mon-badge"><?= number_format(count($fasilitas_rows)); ?> baris</span>
		</div>
		<div class="card-body">
			<?php if (empty($fasilitas_rows)): ?>
				<?php
				$empty_state = array(
					'title' => 'Master Puskesmas belum tersedia; perbandingan per fasilitas belum bisa disusun.',
				);
				$this->load->view('commons/empty_state', array('empty_state' => $empty_state));
				?>
			<?php else: ?>
				<?php if (!empty($fasilitas['ada_data'])): ?>
					<div class="dl-mon-chart-wrap dl-mon-chart-wrap--bar">
						<canvas id="dlMonFacilityChart" role="img"
							aria-label="Grafik batang horizontal jumlah permintaan masuk per Puskesmas pada periode terpilih. Tabel di bawah memuat angka yang sama."></canvas>
					</div>
				<?php else: ?>
					<?php
					$empty_state = array(
						'title' => 'Belum ada permintaan pada periode ini.',
						'message' => 'Fasilitas terdaftar tetap ditampilkan di tabel bawah dengan nilai nol.',
					);
					$this->load->view('commons/empty_state', array('empty_state' => $empty_state));
					?>
				<?php endif; ?>

				<?php if ((int) ($fasilitas['jumlah_fasilitas_aktif'] ?? 0) > 0): ?>
					<?php
					$cakupan = $fasilitas['cakupan_persen'];
					$belum_melapor = (int) $fasilitas['jumlah_fasilitas_aktif'] - (int) $fasilitas['fasilitas_melapor'];
					?>
					<dl class="dl-mon-strip-items dl-mon-coverage">
						<div class="dl-mon-strip-item">
							<dt>Puskesmas aktif yang melaporkan</dt>
							<dd class="dl-mon-num"><?= number_format((int) $fasilitas['fasilitas_melapor']); ?> dari <?= number_format((int) $fasilitas['jumlah_fasilitas_aktif']); ?></dd>
						</div>
						<div class="dl-mon-strip-item">
							<dt>Cakupan pelaporan</dt>
							<dd class="dl-mon-num"><?= $cakupan === null ? '—' : number_format((int) $cakupan) . '%'; ?></dd>
						</div>
						<div class="dl-mon-strip-item">
							<dt>Belum melaporkan</dt>
							<dd class="dl-mon-num"><?= number_format($belum_melapor); ?></dd>
						</div>
					</dl>
					<?php if ($cakupan !== null && $cakupan < 100): ?>
						<p class="dl-adm-panel-note">
							Angka di bawah hanya dari <?= number_format((int) $fasilitas['fasilitas_melapor']); ?> fasilitas yang melapor
							pada periode ini.
						</p>
					<?php endif; ?>
				<?php endif; ?>

				<div class="dl-mon-table-wrap">
					<table class="table table-sm dl-mon-table">
						<caption class="dl-mon-caption">Angka yang sama dengan grafik di atas.</caption>
						<thead>
							<tr>
								<th scope="col">Puskesmas</th>
								<th scope="col">Status</th>
								<th scope="col" class="dl-mon-num-col">Masuk</th>
								<th scope="col" class="dl-mon-num-col">Selesai</th>
								<th scope="col" class="dl-mon-num-col">Aktif</th>
								<th scope="col" class="dl-mon-num-col">Batal</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($fasilitas_rows as $row): ?>
								<tr>
									<th scope="row">
										<?= html_escape($puskesmas_display_label($row->nama ?? '')); ?>
										<span class="dl-mon-code"><?= html_escape((string) ($row->kode ?? '')); ?></span>
									</th>
									<td>
										<?php if (($row->status_fasilitas ?? '') === 'aktif'): ?>
											<span class="dl-mon-tag">Aktif</span>
										<?php elseif (($row->status_fasilitas ?? '') === 'nonaktif'): ?>
											<span class="dl-mon-tag dl-mon-tag--kritis">Nonaktif</span>
										<?php else: ?>
											<span class="dl-mon-tag dl-mon-tag--info">Tidak terdaftar</span>
										<?php endif; ?>
									</td>
									<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) $row->masuk); ?></td>
									<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) $row->selesai); ?></td>
									<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) $row->aktif); ?></td>
									<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) $row->batal); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th scope="row">Total</th>
								<td></td>
								<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) ($fasilitas['total_masuk'] ?? 0)); ?></td>
								<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) ($fasilitas['total_selesai'] ?? 0)); ?></td>
								<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) ($fasilitas['total_aktif'] ?? 0)); ?></td>
								<td></td>
							</tr>
						</tfoot>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<div class="card doclinc-dashboard-panel doclinc-diagnosis-panel mb-4">
	<div class="card-header d-flex flex-wrap align-items-center justify-content-between">
		<div>
			<h2 class="h6 mb-1 font-weight-bold">Diagnosis tercatat per Puskesmas</h2>
			<div class="doclinc-meta-text">
				<?= number_format($diagnosis_period_days); ?> hari terakhir, di luar filter di atas
			</div>
		</div>
		<div class="d-flex align-items-center">
			<span class="badge badge-light border mr-2">Rekam medis</span>
			<button type="button" class="dl-adm-info" data-toggle="modal" data-target="#dlMonHelp"
				aria-label="Penjelasan asal angka diagnosis pada panel ini">
				<i class="fas fa-info-circle" aria-hidden="true"></i>
			</button>
		</div>
	</div>
	<div class="card-body">
		<?php if (empty($top_diagnoses) && empty($diagnosis_distribution)): ?>
			<?php
			$empty_state = array(
				'title' => 'Belum ada data diagnosis pada periode ini.',
			);
			$this->load->view('commons/empty_state', array('empty_state' => $empty_state));
			?>
		<?php else: ?>
			<div class="row">
				<div class="col-xl-5 mb-3 mb-xl-0">
					<h3 class="doclinc-section-kicker">Top Diagnosis</h3>
					<?php foreach ($top_diagnoses as $row): ?>
						<?php
						$total = (int) ($row->total ?? 0);
						$percent = $max_top_diagnosis > 0 ? max(4, round(($total / $max_top_diagnosis) * 100)) : 0;
						?>
						<div class="doclinc-diagnosis-row">
							<div class="d-flex justify-content-between align-items-start">
								<strong><?= html_escape($row->diagnosis ?? '-'); ?></strong>
								<span><?= number_format($total); ?></span>
							</div>
							<div class="doclinc-diagnosis-bar"><div style="width: <?= (int) $percent; ?>%;"></div></div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="col-xl-7">
					<h3 class="doclinc-section-kicker">Distribusi per Puskesmas</h3>
					<?php foreach ($diagnosis_distribution as $row): ?>
						<?php
						$total = (int) ($row->total ?? 0);
						$percent = $max_puskesmas_diagnosis > 0 ? max(4, round(($total / $max_puskesmas_diagnosis) * 100)) : 0;
						?>
						<div class="doclinc-diagnosis-puskesmas">
							<div class="d-flex justify-content-between align-items-start">
								<div>
									<strong><?= html_escape($puskesmas_display_label($row->puskesmas ?? '')); ?></strong>
									<div class="doclinc-meta-text">
										Top: <?= html_escape($row->top_diagnosis ?? '-'); ?> · <?= number_format((int) ($row->top_diagnosis_total ?? 0)); ?> kasus
									</div>
								</div>
								<span class="font-weight-bold"><?= number_format($total); ?></span>
							</div>
							<div class="doclinc-diagnosis-bar"><div style="width: <?= (int) $percent; ?>%;"></div></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<section class="dl-mon-panel" aria-labelledby="dlMonMutuTitle">
	<div class="card doclinc-dashboard-panel">
		<div class="card-header dl-mon-panel-head">
			<div>
				<h2 class="h6 mb-1 font-weight-bold" id="dlMonMutuTitle">Mutu data pelaporan</h2>
			</div>
			<span class="dl-mon-badge"><?= number_format((int) ($mutu['total_temuan'] ?? 0)); ?> temuan</span>
		</div>
		<div class="card-body">
			<?php if ((int) ($mutu['kerangka_jam_detik'] ?? 0) !== 0): ?>
				<?php /* Penjaga kerangka jam mengoreksi batas periode secara otomatis.
				   Koreksi yang tidak terlihat adalah pergeseran yang tersembunyi,
				   jadi keadaannya dinyatakan di sini. */ ?>
				<div class="alert alert-warning dl-mon-notice" role="status">
					<i class="fas fa-clock mr-2" aria-hidden="true"></i>
					Kerangka jam PHP dan sesi basis data berselisih
					<span class="dl-mon-num"><?= number_format(abs((int) $mutu['kerangka_jam_detik'])); ?></span> detik.
					Batas periode digeser otomatis supaya rentangnya tetap benar, tetapi selisih ini perlu diperiksa:
					<span class="dl-mon-code">date_default_timezone_set</span> dan zona waktu sesi basis data.
				</div>
			<?php endif; ?>
			<?php /* Umur hasil dinyatakan apa adanya. Simpanan yang tidak terlihat
			   umurnya adalah kesegaran palsu: pembaca akan mengira angkanya baru
			   padahal ia dihitung beberapa menit lalu. */ ?>
			<?php if (!empty($mutu['dihitung_label'])): ?>
				<p class="dl-adm-panel-note mb-3">
					Pemeriksaan terakhir dijalankan
					<span class="dl-mon-num"><?= html_escape((string) $mutu['dihitung_label']); ?></span><?= !empty($mutu['dari_simpanan']) ? ', hasilnya dari simpanan' : ''; ?>.
				</p>
			<?php endif; ?>
			<?php if (empty($mutu['tersedia'])): ?>
				<?php
				$empty_state = array(
					'title' => 'Tabel permintaan belum tersedia; mutu data belum bisa diperiksa.',
				);
				$this->load->view('commons/empty_state', array('empty_state' => $empty_state));
				?>
			<?php elseif (empty($mutu['ada_temuan'])): ?>
				<?php
				$empty_state = array(
					'title' => 'Tidak ada temuan mutu data pada pemeriksaan ini.',
				);
				$this->load->view('commons/empty_state', array('empty_state' => $empty_state));
				?>
			<?php else: ?>
				<div class="dl-mon-table-wrap">
					<table class="table table-sm dl-mon-table">
						<caption class="dl-mon-caption">Pemeriksaan mutu data, baca-saja.</caption>
						<thead>
							<tr>
								<th scope="col">Temuan</th>
								<th scope="col">Tingkat</th>
								<th scope="col" class="dl-mon-num-col">Jumlah</th>
								<th scope="col">Tindakan</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($mutu_checks as $check): ?>
								<?php $tingkat = (string) $check['tingkat']; ?>
								<tr<?= ((int) $check['jumlah'] > 0) ? '' : ' class="dl-mon-row-nihil"'; ?>>
									<th scope="row"><?= html_escape((string) $check['label']); ?></th>
									<td><span class="dl-mon-tag dl-mon-tag--<?= html_escape($tingkat); ?>"><?= html_escape($severity_labels[$tingkat] ?? 'Informasi'); ?></span></td>
									<td class="dl-mon-num-col dl-mon-num"><?= number_format((int) $check['jumlah']); ?></td>
									<td><?= html_escape((string) $check['tindakan']); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="dl-adm-panel-note">
					Baris bernilai nol ikut ditampilkan supaya jelas diperiksa, bukan dilewati.
				</p>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php if ($nakes_presence_enabled): ?>
	<?php
	/* Panel status online nakes. Asetnya dimuat di sini karena panel ini hanya
	   ada di halaman ini; berkasnya dari aplikasi utama, bukan folder panel. */
	$admin_public_base = preg_replace('#/admin_menu$#', '', rtrim(base_url(), '/'));
	$admin_presence_bootstrap = array(
		'enabled' => true,
		'mode' => 'monitor',
		'snapshotUrl' => site_url('home/nakes_presence_snapshot'),
		'snapshotIntervalMs' => 30000,
		'containerId' => 'doclincNakesPresence',
	);
	?>
	<link rel="stylesheet" href="<?= html_escape($admin_public_base . '/assets/css/doclinc-nakes-presence.css'); ?>">
	<div class="card doclinc-dashboard-panel doclinc-presence-panel mb-4">
		<div class="card-header d-flex align-items-center justify-content-between">
			<div>
				<h2 class="h6 mb-1 font-weight-bold">Status Online Nakes</h2>
				<div class="doclinc-meta-text">Seluruh akun Nakes personal aktif, diperbarui otomatis</div>
			</div>
			<span class="badge badge-light border">Read-only</span>
		</div>
		<div class="card-body">
			<div id="doclincNakesPresence" aria-live="polite" aria-busy="true">
				<div class="doclinc-presence-empty">Memuat status Nakes...</div>
			</div>
		</div>
	</div>
	<script>window.DoclincNakesPresenceConfig = <?= json_encode($admin_presence_bootstrap, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
	<script src="<?= html_escape($admin_public_base . '/assets/js/doclinc-nakes-presence.js'); ?>"></script>
<?php endif; ?>

<div class="row">
	<div class="col-12 mb-4">
		<div class="card doclinc-dashboard-panel h-100">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0 font-weight-bold">Aktivitas Terbaru</h2>
				<span class="doclinc-meta-text">Dari request_events</span>
			</div>
			<div class="card-body p-0">
				<?php if (empty($activity)): ?>
					<?php
					$empty_state = array(
						'title' => 'Belum ada aktivitas konsultasi terbaru.',
					);
					$this->load->view('commons/empty_state', array('empty_state' => $empty_state));
					?>
				<?php else: ?>
					<div class="list-group list-group-flush">
						<?php foreach ($activity as $row): ?>
							<div class="list-group-item doclinc-activity-item">
								<div class="d-flex justify-content-between align-items-start">
									<div>
										<div class="font-weight-bold"><?= html_escape($row->event_label ?? 'Aktivitas konsultasi diperbarui'); ?></div>
										<div class="doclinc-meta-text">Request #<?= html_escape($row->request_id ?? '-'); ?> &middot; <?= html_escape($puskesmas_display_label($row->puskesmas ?? '')); ?></div>
									</div>
									<div class="doclinc-meta-text text-right"><?= html_escape($row->created_at_label ?? '-'); ?></div>
								</div>
								<div class="doclinc-meta-text mt-2"><?= html_escape($row->event_message ?? 'Status konsultasi diperbarui.'); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php
/* Muatan grafik dikirim sebagai JSON, bukan disisipkan ke dalam kode JavaScript,
   supaya nama fasilitas tidak perlu di-escape tangan dan tidak ada data yang
   bocor ke konsol. Grafik hanya pelengkap: tabel di tiap bagian memuat angka
   yang sama dan tetap terbaca tanpa JavaScript. */
$monitoring_chart_payload = array(
	'trendCanvasId' => 'dlMonTrendChart',
	'facilityCanvasId' => 'dlMonFacilityChart',
	'labels' => array_values(isset($seri['labels']) && is_array($seri['labels']) ? $seri['labels'] : array()),
	'rangeLabels' => array_values(isset($seri['range_labels']) && is_array($seri['range_labels']) ? $seri['range_labels'] : array()),
	'masuk' => array_values(isset($seri['masuk']) && is_array($seri['masuk']) ? $seri['masuk'] : array()),
	'selesai' => array_values(isset($seri['selesai']) && is_array($seri['selesai']) ? $seri['selesai'] : array()),
	'facilities' => array(),
	/* Pembaruan berkala. Endpoint-nya hanya mengembalikan irisan tipis yang
	   cepat berubah, bukan seluruh dasbor: agregat periode berubah lambat dan
	   tidak perlu dikirim setiap setengah menit. */
	'liveUrl' => base_url('home/live_snapshot'),
	'puskesmas' => (string) $filters['puskesmas'],
	'liveIntervalMs' => 30000,
	/* URL penandaan tindak lanjut dikirim dari sisi server supaya skrip tidak
	   perlu menyusun alamat sendiri. Formulirnya tetap bekerja tanpa skrip. */
	'followupMarkUrl' => site_url('home/followup_mark'),
	'followupUnmarkUrl' => site_url('home/followup_unmark'),
);
foreach (array_slice($fasilitas_rows, 0, 12) as $chart_row) {
	$monitoring_chart_payload['facilities'][] = array(
		'label' => (string) ($chart_row->nama ?? ''),
		'value' => (int) $chart_row->masuk,
	);
}
?>
<script id="doclincMonitoringBootstrap" type="application/json"><?= json_encode($monitoring_chart_payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="<?= html_escape(base_url($monitoring_js) . '?v=' . rawurlencode($monitoring_js_version)); ?>"></script>

<?php
/* Modal penjelasan halaman.
   Penjelasan metode, cakupan, dan pengecualian yang dulu tersebar di badan
   halaman dikumpulkan di sini. Halaman utama tinggal memuat data besar dan satu
   aksi utama, sesuai DESIGN.md §1.2. Yang dipindahkan hanya tempatnya, bukan
   faktanya: setiap kalimat yang dulu terbaca di halaman tetap terbaca di sini.
   Angka yang bergantung keadaan tetap dihitung, bukan ditulis tangan. */
?>
<div class="row">
	<div class="col-12 mb-4">
		<div class="card doclinc-dashboard-panel">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0 font-weight-bold">Pintasan admin</h2>
			</div>
			<div class="card-body">
				<div class="doclinc-shortcut-grid">
					<a href="<?= site_url('konsultasi_kesehatan'); ?>"><i class="fas fa-stethoscope" aria-hidden="true"></i><span>Pantau konsultasi</span></a>
					<a href="<?= site_url('kelola_dokter_nakes'); ?>"><i class="fas fa-hospital" aria-hidden="true"></i><span>Puskesmas</span></a>
					<a href="<?= site_url('laporan'); ?>"><i class="fas fa-file-alt" aria-hidden="true"></i><span>Laporan</span></a>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="dlMonHelp" tabindex="-1" role="dialog" aria-labelledby="dlMonHelpLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title h6 mb-0 font-weight-bold" id="dlMonHelpLabel">Cara membaca halaman ini</h2>
				<button type="button" class="close dl-adm-modal-close" data-dismiss="modal" aria-label="Tutup penjelasan">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body dl-adm-help-body">
				<p>
					Ringkasan pelayanan warga lewat DocLink di wilayah kerja Dinas Kesehatan: jumlah permintaan,
					layanan yang selesai, beban yang masih berjalan, dan keadaan yang perlu ditindak lanjuti.
				</p>

				<h2 class="h6">Ringkasan periode</h2>
				<dl class="dl-adm-help-defs">
					<?php foreach (array_merge($kpi_cards, $kpi_extra) as $card): ?>
						<div class="dl-adm-help-def">
							<dt><?= html_escape((string) $card['label']); ?></dt>
							<dd><?= html_escape((string) $card['definisi']); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>

				<h2 class="h6">Filter dan periode</h2>
				<ul>
					<li>Tanggal awal dan akhir hanya dipakai bila periode disetel ke <b>Rentang khusus</b>.</li>
					<li>Angka periode, tren, dan perbandingan Puskesmas memakai filter yang sama.</li>
					<li>Periode pembanding memakai definisi KPI yang sama.</li>
				</ul>

				<h2 class="h6">Tren layanan</h2>
				<ul>
					<li>Grafik ini menunjukkan besaran aktivitas, bukan mutu klinis.</li>
					<?php if (($ringkasan['tingkat_penyelesaian'] ?? null) !== null): ?>
						<li>
							Tingkat penyelesaian memakai kohort: pembilang dan penyebut sama-sama permintaan yang
							<b>masuk</b> pada periode ini, jadi angkanya tidak mencampur pekerjaan periode sebelumnya.
						</li>
					<?php endif; ?>
					<?php if ((int) ($seri['selesai_tanpa_waktu'] ?? 0) > 0): ?>
						<li>
							<?= number_format((int) $seri['selesai_tanpa_waktu']); ?> permintaan berstatus selesai tidak
							punya waktu selesai, jadi tidak dapat ditempatkan pada tanggal mana pun dan tidak ikut
							dihitung pada seri ini.
						</li>
					<?php endif; ?>
					<?php if ((int) ($mutu_by_code['selesai_beda_kerangka']['jumlah'] ?? 0) > 0): ?>
						<li>
							<?= number_format((int) $mutu_by_code['selesai_beda_kerangka']['jumlah']); ?> baris layanan
							selesai pada seluruh riwayat memakai kerangka jam yang berbeda dari jejak penyelesaiannya.
							Bila periode yang dipilih mencakup masa sebelum koreksi zona waktu, garis tren memuat
							lompatan yang merupakan artefak pencatatan, bukan perubahan perilaku warga.
						</li>
					<?php endif; ?>
				</ul>

				<h2 class="h6">Aktivitas per Puskesmas</h2>
				<ul>
					<li>
						Urutan mengikuti besaran permintaan, bukan peringkat mutu: kapasitas, jam kerja, dan
						kelengkapan data antar fasilitas tidak sama.
					</li>
					<li>
						Baris "Selesai" dihitung dari status permintaan saat ini, bukan dari waktu selesai,
						sehingga angkanya berbeda dari kartu "Layanan Selesai" yang berperiode.
					</li>
					<?php if ((int) ($fasilitas['jumlah_fasilitas_aktif'] ?? 0) > 0): ?>
						<?php $dl_belum_melapor = (int) $fasilitas['jumlah_fasilitas_aktif'] - (int) $fasilitas['fasilitas_melapor']; ?>
						<?php if ($dl_belum_melapor > 0): ?>
							<li>
								Tabel dan grafik hanya mencerminkan
								<?= number_format((int) $fasilitas['fasilitas_melapor']); ?> fasilitas yang punya aktivitas.
								<?= number_format($dl_belum_melapor); ?> fasilitas aktif belum melaporkan apa pun pada
								periode ini, jadi angka pada panel ini belum menggambarkan seluruh wilayah kerja.
							</li>
						<?php endif; ?>
					<?php endif; ?>
				</ul>

				<h2 class="h6">Perlu tindak lanjut</h2>
				<ul>
					<li>
						Keadaan sekarang yang memenuhi aturan eskalasi tertulis. Ambang pengingat
						<?= number_format((int) ($pengecualian['reminder_minutes'] ?? 10)); ?> menit dan ambang terlambat
						<?= number_format((int) ($pengecualian['overdue_minutes'] ?? 20)); ?> menit dibaca dari konfigurasi
						aplikasi yang sama dengan pengawas permintaan.
					</li>
					<?php if (!empty($pengecualian['terpotong'])): ?>
						<li>Ditampilkan 10 teratas; angka di atas tetap total.</li>
					<?php endif; ?>
					<?php if ((int) ($pengecualian['sudah_dieskalasi'] ?? 0) > 0): ?>
						<li>
							<?= number_format((int) $pengecualian['sudah_dieskalasi']); ?> permintaan pernah dieskalasi
							otomatis oleh pengawas permintaan. Jejaknya tersimpan sebagai event permintaan dan dapat
							ditelusuri lewat Penampil audit.
						</li>
					<?php endif; ?>
				</ul>

				<h2 class="h6">Mutu data pelaporan</h2>
				<ul>
					<li>Baca-saja, seluruh riwayat, di luar filter periode dan Puskesmas.</li>
					<li>Hasilnya disimpan sebentar, jadi bisa tertinggal beberapa menit.</li>
					<li>Baris bernilai nol tetap ditampilkan.</li>
				</ul>

				<?php if (!empty($mutu_checks)): ?>
					<?php /* Sebab tiap temuan dulu tercetak di dalam tabel dan membuat
					   barisnya setinggi paragraf. Sekarang definisinya di sini, dan
					   tabel hanya memuat nama, tingkat, jumlah, serta tindakan. */ ?>
					<dl class="dl-adm-help-defs">
						<?php foreach ($mutu_checks as $check): ?>
							<div class="dl-adm-help-def">
								<dt><?= html_escape((string) $check['label']); ?></dt>
								<dd><?= html_escape((string) $check['sebab']); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>

				<h2 class="h6">Diagnosis tercatat per Puskesmas</h2>
				<p>
					Diisi Nakes/Dokter saat menangani pasien, jadi hanya mencakup warga yang masuk lewat
					aplikasi. Angka ini bukan angka kesakitan seluruh wilayah, tidak boleh dibaca sebagai data
					surveilans maupun sebagai penyebut sasaran.
				</p>
				<p class="dl-adm-help-note">
					<?= number_format($diagnosis_period_days); ?> hari terakhir, tidak mengikuti filter periode di atas.
				</p>
			</div>
		</div>
	</div>
</div>

</div><!-- /.dl-mon -->
