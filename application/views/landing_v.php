<?php
/**
 * Halaman depan publik DocLinc.
 *
 * Dokumen mandiri (tanpa shell aplikasi yang dipakai pengguna yang sudah masuk),
 * karena pengunjung belum tentu punya sesi. Isinya hanya berasal dari model
 * konten landing + angka nyata dari database: tidak ada teks maupun angka yang
 * dikarang di sini.
 *
 * Aturan tampilan yang dipegang: warna dan jarak hanya dari token --dl-*,
 * ikon abu-abu yang mewarisi currentColor, gradien hanya di area hero
 * (bukan pada kartu data), dan angka memakai huruf monospace.
 */
$blocks = isset($blocks) && is_array($blocks) ? $blocks : array();
$faqs = isset($faqs) && is_array($faqs) ? $faqs : array();
$stats = isset($stats) && is_array($stats) ? $stats : array();
$contact_placeholder = isset($contact_placeholder) ? (bool) $contact_placeholder : false;

$hero_judul = isset($blocks['hero_judul']) ? (string) $blocks['hero_judul']->isi : 'DocLinc';
$hero_ringkasan = isset($blocks['hero_ringkasan']) ? (string) $blocks['hero_ringkasan']->isi : '';
$tentang = isset($blocks['tentang']) ? (string) $blocks['tentang']->isi : '';
$kontak = isset($blocks['kontak']) ? (string) $blocks['kontak']->isi : '';

$design_system_css = 'assets/css/doclinc-design-system.css';
$landing_css = 'assets/css/doclinc-landing.css';
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= html_escape($hero_judul); ?> &middot; DocLinc</title>
	<meta name="description" content="<?= html_escape($hero_ringkasan); ?>">
	<link rel="stylesheet" href="<?= html_escape(base_url($design_system_css) . '?v=' . rawurlencode((string) @filemtime(FCPATH . $design_system_css))); ?>">
	<link rel="stylesheet" href="<?= html_escape(base_url($landing_css) . '?v=' . rawurlencode((string) @filemtime(FCPATH . $landing_css))); ?>">
</head>
<body class="dl-landing">
	<a class="dl-landing-skip" href="#konten-utama">Lewati ke konten utama</a>

	<header class="dl-landing-topbar">
		<span class="dl-landing-brand">DocLinc</span>
		<a class="dl-landing-cta dl-landing-cta-small" href="<?= html_escape(site_url('login')); ?>">
			Masuk
		</a>
	</header>

	<main id="konten-utama" class="dl-landing-main">
		<section class="dl-landing-hero">
			<h1 class="dl-landing-hero-title"><?= html_escape($hero_judul); ?></h1>
			<?php if ($hero_ringkasan !== ''): ?>
				<p class="dl-landing-hero-text"><?= nl2br(html_escape($hero_ringkasan)); ?></p>
			<?php endif; ?>
			<div class="dl-landing-hero-actions">
				<a class="dl-landing-cta" href="<?= html_escape(site_url('login')); ?>">Masuk ke DocLinc</a>
				<a class="dl-landing-cta dl-landing-cta-ghost" href="<?= html_escape(site_url('sign_up')); ?>">Daftar akun warga</a>
			</div>
		</section>

		<section class="dl-landing-stats" aria-labelledby="judul-statistik">
			<h2 id="judul-statistik" class="dl-landing-section-title">Data layanan saat ini</h2>
			<p class="dl-landing-section-note">
				Angka di bawah dihitung langsung dari basis data saat halaman ini dibuka, bukan disimpan
				sebagai teks. Bila sumbernya belum tersedia, yang tampil adalah keterangannya.
			</p>
			<ul class="dl-landing-stat-list">
				<?php foreach ($stats as $stat): ?>
					<li class="dl-landing-stat">
						<span class="dl-landing-stat-label"><?= html_escape($stat['label']); ?></span>
						<?php if ($stat['value'] === null): ?>
							<span class="dl-landing-stat-value dl-landing-stat-value-muted">belum tersedia</span>
						<?php else: ?>
							<span class="dl-landing-stat-value"><?= number_format((int) $stat['value']); ?></span>
						<?php endif; ?>
						<span class="dl-landing-stat-source">dari <?= html_escape($stat['source']); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<?php if ($tentang !== ''): ?>
			<section class="dl-landing-section" aria-labelledby="judul-tentang">
				<h2 id="judul-tentang" class="dl-landing-section-title">Tentang layanan</h2>
				<p class="dl-landing-section-text"><?= nl2br(html_escape($tentang)); ?></p>
			</section>
		<?php endif; ?>

		<?php if ($kontak !== ''): ?>
			<section class="dl-landing-section" aria-labelledby="judul-kontak">
				<h2 id="judul-kontak" class="dl-landing-section-title">Kontak</h2>
				<?php if ($contact_placeholder): ?>
					<p class="dl-landing-section-text dl-landing-section-text-muted">
						Kanal kontak resmi belum dilengkapi pengelola. Sementara itu, gunakan layanan
						yang tersedia setelah masuk.
					</p>
				<?php else: ?>
					<p class="dl-landing-section-text"><?= nl2br(html_escape($kontak)); ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<section class="dl-landing-section" aria-labelledby="judul-faq">
			<h2 id="judul-faq" class="dl-landing-section-title">Pertanyaan umum</h2>
			<?php if (empty($faqs)): ?>
				<p class="dl-landing-section-text dl-landing-section-text-muted">
					Belum ada pertanyaan umum yang ditampilkan. Pengelola dapat menambahkannya dari panel admin.
				</p>
			<?php else: ?>
				<div class="dl-landing-faq">
					<?php foreach ($faqs as $faq): ?>
						<details class="dl-landing-faq-item">
							<summary class="dl-landing-faq-question"><?= html_escape((string) $faq->pertanyaan); ?></summary>
							<div class="dl-landing-faq-answer"><?= nl2br(html_escape((string) $faq->jawaban)); ?></div>
						</details>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	</main>

	<footer class="dl-landing-footer">
		<p class="dl-landing-footer-text">
			DocLinc &middot; layanan konsultasi kesehatan daring untuk warga Kota Cilegon.
		</p>
		<a class="dl-landing-footer-link" href="<?= html_escape(site_url('login')); ?>">Masuk ke aplikasi</a>
	</footer>
</body>
</html>
