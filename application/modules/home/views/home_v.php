<?php
$id_request = '';
$google_maps_api_key = $this->config->item('google_maps_api_key') ?: '';
$firebase_enabled = (bool) $this->config->item('firebase_enabled');
$legacy_superapp_url = $this->config->item('legacy_superapp_url') ?: '#';
$map_provider = $this->config->item('map_provider') ?: 'none';
$mapbox_public_token = $this->config->item('mapbox_public_token') ?: '';
foreach ($data_profile->result() as $x) {
	$usia = $x->usia;
}

$dokter_id = []; // siapkan array kosong
foreach ($dataDoctor->result() as $doc) {
	$dokter_id[] = $doc->professional_id; // tambahkan ke array
}

if (!function_exists('doclinc_history_safe_text')) {
	function doclinc_history_safe_text($value, $fallback = '-')
	{
		$text = trim((string) $value);
		return html_escape($text !== '' ? $text : $fallback);
	}
}

if (!function_exists('doclinc_history_safe_lines')) {
	function doclinc_history_safe_lines($value, $fallback = '-')
	{
		$text = trim((string) $value);
		return nl2br(html_escape($text !== '' ? $text : $fallback), false);
	}
}

if (!function_exists('doclinc_history_format_complaint')) {
	function doclinc_history_format_complaint($value)
	{
		$text = trim((string) $value);
		if ($text === '') {
			return '<p class="history-empty-text mb-0">Keluhan tersimpan</p>';
		}

		$lines = preg_split('/\R/u', $text);
		$html = '';
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			if (preg_match('/^\*\*(.+?)\*\*$/', $line, $match)) {
				$html .= '<div class="history-complaint-heading">' . html_escape(trim($match[1])) . '</div>';
				continue;
			}

			if (strpos($line, ':') !== false) {
				list($label, $content) = explode(':', $line, 2);
				$html .= '<div class="history-complaint-row">';
				$html .= '<span class="history-complaint-label">' . html_escape(trim($label)) . '</span>';
				$html .= '<span class="history-complaint-value">' . html_escape(trim($content) !== '' ? trim($content) : '-') . '</span>';
				$html .= '</div>';
				continue;
			}

			$html .= '<p class="history-free-text mb-1">' . html_escape($line) . '</p>';
		}

		return $html !== '' ? $html : nl2br(html_escape($text), false);
	}
}

$doclinc_active_request = isset($active_consultation_request) ? $active_consultation_request : null;
$doclinc_has_active_request = !empty($doclinc_active_request);
$doclinc_active_request_id = $doclinc_has_active_request && isset($doclinc_active_request->request_id) ? (int) $doclinc_active_request->request_id : 0;
?>

<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Doklinc (BETA) - Home</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" integrity="sha512-tS3S5qG0BlhnQROyJXvNjeEM4UpMXHrQfTGmbQ1gKmelCxlSEBUaxhRBj/EFTzpbP4RVSrpEikbmdJobCvhE3g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css" integrity="sha512-sMXtMNL1zRzolHYKEujM2AqCLUR9F2C4/05cdbxjjLSRvMQIciEPCQZo++nk7go3BtSuK9kfa/s+a4f4i5pLkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
	<style type="text/css">
		.content {
			display: none;
			padding: 15px;
		}

		.content.active {
			display: block;
		}

		.card-header {
			background-color: #09AD74;
			color: white;
		}

		.history-result-card {
			border: 0;
			border-radius: 20px;
			overflow: hidden;
			background: #fff;
		}

		.history-result-card .card-header {
			background: #fff;
			color: #333;
			border-bottom: 1px solid #e8f3ee;
			padding: 14px 16px;
		}

		.history-request-id {
			color: #379A69;
			font-weight: 700;
			font-size: 13px;
		}

		.history-meta {
			color: #6c757d;
			font-size: 12px;
			line-height: 1.5;
		}

		.history-status-badge {
			background: #dff4ec;
			color: #379A69;
			border: 1px solid rgba(55, 154, 105, 0.24);
			border-radius: 999px;
			font-size: 12px;
			font-weight: 700;
			padding: 6px 10px;
		}

		.history-section {
			border: 1px solid #e8f3ee;
			border-radius: 16px;
			padding: 13px;
			margin-bottom: 12px;
			background: #fff;
		}

		.history-section-title {
			color: #379A69;
			font-weight: 700;
			font-size: 13px;
			margin-bottom: 8px;
		}

		.history-result-label,
		.history-complaint-label {
			display: block;
			color: #6c757d;
			font-size: 12px;
			font-weight: 700;
			margin-bottom: 2px;
		}

		.history-result-value,
		.history-complaint-value,
		.history-free-text {
			color: #333;
			font-size: 14px;
			line-height: 1.5;
			word-break: break-word;
		}

		.history-complaint-heading {
			color: #379A69;
			font-weight: 700;
			font-size: 14px;
			margin: 10px 0 6px;
		}

		.history-complaint-heading:first-child {
			margin-top: 0;
		}

		.history-complaint-row,
		.history-result-row {
			padding: 8px 0;
			border-bottom: 1px solid #f0f0f0;
		}

		.history-complaint-row:last-child,
		.history-result-row:last-child {
			border-bottom: 0;
			padding-bottom: 0;
		}

		.history-therapy-list {
			display: flex;
			flex-direction: column;
			gap: 8px;
		}

		.history-therapy-item {
			border: 1px solid #e5e5e5;
			border-radius: 14px;
			padding: 10px;
			background: #fafafa;
		}

		.history-empty-text {
			color: #6c757d;
			font-size: 13px;
		}

		.doclinc-visit-map {
			width: 100%;
			height: 220px;
			border: 1px solid #d8eee5;
			border-radius: 12px;
			overflow: hidden;
			background: #eef5f2;
		}

		.doclinc-visit-status {
			font-size: 12px;
			color: #6c757d;
		}

		.doclinc-visit-marker {
			position: relative;
			width: 78px;
			height: 58px;
			pointer-events: auto;
		}

		.doclinc-visit-marker-pin {
			position: absolute;
			left: 50%;
			top: 24px;
			width: 18px;
			height: 18px;
			border: 3px solid #fff;
			border-radius: 50% 50% 50% 0;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.32);
			transform: translate(-50%, -50%) rotate(-45deg);
		}

		.doclinc-visit-marker-label {
			position: absolute;
			left: 50%;
			border-radius: 999px;
			color: #fff;
			font-size: 11px;
			font-weight: 700;
			line-height: 1;
			padding: 5px 8px;
			white-space: nowrap;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.22);
			transform: translateX(-50%);
		}

		.doclinc-visit-marker--patient .doclinc-visit-marker-pin,
		.doclinc-visit-marker--patient .doclinc-visit-marker-label {
			background: #0d6efd;
		}

		.doclinc-visit-marker--patient .doclinc-visit-marker-label {
			top: 39px;
		}

		.doclinc-visit-marker--nakes .doclinc-visit-marker-pin,
		.doclinc-visit-marker--nakes .doclinc-visit-marker-label {
			background: #dc3545;
		}

		.doclinc-visit-marker--nakes .doclinc-visit-marker-label {
			bottom: 39px;
		}

		.header {
			background-color: #09AD74;
			color: white;
			padding: 15px;
			text-align: center;
			font-size: 1.5rem;
			font-weight: bold;
		}

		/* Style untuk preloader */
		#preloader {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: #09AD74;
			display: flex;
			justify-content: center;
			align-items: center;
			z-index: 9999;
			font-family: Arial, sans-serif;
			color: white;
		}

		/* Style untuk konten utama */
		#content-wrapper,
		#nav-bottom-wrapper {
			display: none;
		}

		.article-list {
			display: flex;
			flex-direction: column;
			gap: 20px;
		}

		.article {
			display: flex;
			flex-direction: column;
			background-color: white;
			border-radius: 10px;
			overflow: hidden;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
		}

		.article img {
			width: 100%;
			height: auto;
		}

		.article-content {
			padding: 15px;
		}

		.article-title {
			font-size: 18px;
			font-weight: bold;
			margin: 0 0 10px;
		}

		.article-author {
			font-size: 14px;
			color: gray;
			margin-bottom: 10px;
		}

		.article-link {
			text-decoration: none;
			color: #3498db;
		}

		/* Mobile responsive */
		@media(min-width: 768px) {
			.article {
				flex-direction: row;
				max-width: 600px;
				margin: auto;
			}

			.article img {
				width: 150px;
				height: 150px;
				object-fit: cover;
			}

			.article-content {
				padding: 15px;
				display: flex;
				flex-direction: column;
				justify-content: space-between;
			}

			.article-title {
				font-size: 20px;
			}

			.article-author {
				font-size: 16px;
			}
		}

		.notification {
			position: relative;
			display: inline-block;
		}

		.notification-item {
			padding: 1px;
			border-bottom: 1px solid #f1f1f1;
			cursor: pointer;
		}

		.notification-item:last-child {
			border-bottom: none;
		}

		.notification-item:hover {
			background: #f9f9f9;
		}

		.hero-card.disabled {
			pointer-events: none !important;
			/* Tidak bisa diklik */
			opacity: 0.5;
			/* Buat terlihat redup */
			cursor: not-allowed;
			/* Ubah kursor menjadi tanda larangan */
		}

		.hero-card.disabled a,
		.hero-card.disabled button {
			pointer-events: none !important;
			opacity: 0.5;
		}

		.popup-alert {
			position: fixed;
			top: 20px;
			right: 20px;
			background-color: #d4edda;
			color: #155724;
			border-left: 6px solid #28a745;
			padding: 16px 20px;
			z-index: 9999;
			border-radius: 8px;
			font-family: Arial, sans-serif;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
			animation: fadeIn 0.5s ease;
		}

		.close-btn {
			margin-left: 15px;
			color: #155724;
			font-weight: bold;
			float: right;
			font-size: 20px;
			cursor: pointer;
		}

		@keyframes fadeIn {
			from {
				opacity: 0;
				top: 0;
			}

			to {
				opacity: 1;
				top: 20px;
			}
		}

		.glow-star {
			animation: glow 1.5s infinite alternate;
		}

		@keyframes glow {
			from {
				text-shadow: 0 0 5px gold, 0 0 10px gold, 0 0 15px orange;
			}

			to {
				text-shadow: 0 0 10px gold, 0 0 20px orange, 0 0 30px red;
			}
		}

		.rating-stars {
			display: flex;
			flex-direction: row-reverse;
			justify-content: center;
		}

		.rating-stars input[type="radio"] {
			display: none;
		}

		.rating-stars label {
			font-size: 2rem;
			color: #ccc;
			cursor: pointer;
		}

		.rating-stars input[type="radio"]:checked~label,
		.rating-stars label:hover,
		.rating-stars label:hover~label {
			color: #f5c518;
		}

		@media print {

			button,
			.card-header span {
				display: none !important;
			}

			.card {
				border: none !important;
				box-shadow: none !important;
			}
		}
	</style>
</head>

<body class="bg-light dl-dashboard-body">
	<div id="preloader">
		<div class="text-center">
			<img class="animate__animated animate__bounceIn mb-3" src="<?= base_url(); ?>assets/images/doklincwhite.png" alt="" height="50px">
			<p class="mb-0">
			<div class="spinner-border spinner-border-sm text-light" role="status">
				<span class="visually-hidden">Loading...</span>
			</div>
			Memuat...
			</p>
		</div>
	</div>

	<div class="content-wrapper dl-shell" id="content-wrapper">
		<div class="contents">
			<div class="hero bg-success p-3 overflow-hidden dl-appbar">
				<a class="dl-back-link" href="<?= html_escape($legacy_superapp_url); ?>" style="text-decoration: none; color: white; font-size: 1.5rem;" aria-label="Kembali">
					<i class="fas fa-chevron-left icon"></i>
				</a>
				<a class="notify" href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNotif" aria-controls="offcanvasNotif">
					<i class="bi bi-bell-fill fs-4"></i>
					<!-- kalo ada notif fetch datanya dari sini ya, bukan dari dalem elemen span nya -->
					<span class="notify-number" id="badgeNotif">9+</span>
					<!-- sampe sini -->
				</a>
				<div class="text-white mb-2 dl-location-row">
					<i class="fas fa-map-marker-alt me-2"></i><small><label for="" id="address"></label></small>

					<input type="hidden" id="id_user" value="<?= $this->session->userdata('id'); ?>">
					<input type="hidden" id="id_kabupaten" value="<?= $this->session->userdata('remark'); ?>">
					<input type="hidden" id="address" placeholder="Latitude">
					<input type="hidden" id="latitude" placeholder="Latitude">
					<input type="hidden" id="longitude" placeholder="Longitude">
				</div>
				<div class="d-flex animate__animated animate__fadeInUp animate__faster dl-profile-row">
					<?php
					foreach ($data_profile->result() as $x) {
						$foto = $x->foto;
					}
					?>
					<div class="flex-shrink-0">
						<img class="rounded-4 shadow dl-profile-photo" id="previewFoto" src="<?= doclinc_safe_profile_image_src($foto); ?>" alt="Foto Profil" style="width: 100px; height: 100px; object-fit: cover;">
					</div>
					<div class="flex-grow-1 ms-3 text-white dl-profile-copy">
						<small>Selamat Datang</small>
						<h3 class="mb-0"><?= html_escape($this->session->userdata('nama')); ?></h3>
						<p class="mb-0"><?= html_escape($usia); ?></p>
						<p>Kota: <span id="kota">Memuat...</span></p>
					</div>
				</div>
			</div>
			<svg id="wave" style="transform:rotate(180deg); transition: 0.3s" viewBox="0 0 1440 120" version="1.1" xmlns="http://www.w3.org/2000/svg">
				<defs>
					<linearGradient id="sw-gradient-0" x1="0" x2="0" y1="1" y2="0">
						<stop stop-color="rgba(9, 173, 116, 1)" offset="0%"></stop>
						<stop stop-color="rgba(140.457, 255, 215.189, 1)" offset="100%"></stop>
					</linearGradient>
				</defs>
				<path style="transform:translate(0, 0px); opacity:1" fill="url(#sw-gradient-0)" d="M0,48L48,48C96,48,192,48,288,56C384,64,480,80,576,88C672,96,768,96,864,86C960,76,1056,56,1152,48C1248,40,1344,44,1440,42C1536,40,1632,32,1728,42C1824,52,1920,80,2016,78C2112,76,2208,44,2304,40C2400,36,2496,60,2592,76C2688,92,2784,100,2880,98C2976,96,3072,84,3168,74C3264,64,3360,56,3456,54C3552,52,3648,56,3744,54C3840,52,3936,44,4032,48C4128,52,4224,68,4320,64C4416,60,4512,36,4608,30C4704,24,4800,36,4896,44C4992,52,5088,56,5184,52C5280,48,5376,36,5472,44C5568,52,5664,80,5760,94C5856,108,5952,108,6048,98C6144,88,6240,68,6336,50C6432,32,6528,16,6624,18C6720,20,6816,40,6864,50L6912,60L6912,120L6864,120C6816,120,6720,120,6624,120C6528,120,6432,120,6336,120C6240,120,6144,120,6048,120C5952,120,5856,120,5760,120C5664,120,5568,120,5472,120C5376,120,5280,120,5184,120C5088,120,4992,120,4896,120C4800,120,4704,120,4608,120C4512,120,4416,120,4320,120C4224,120,4128,120,4032,120C3936,120,3840,120,3744,120C3648,120,3552,120,3456,120C3360,120,3264,120,3168,120C3072,120,2976,120,2880,120C2784,120,2688,120,2592,120C2496,120,2400,120,2304,120C2208,120,2112,120,2016,120C1920,120,1824,120,1728,120C1632,120,1536,120,1440,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"></path>
				<defs>
					<linearGradient id="sw-gradient-1" x1="0" x2="0" y1="1" y2="0">
						<stop stop-color="rgba(9, 173, 116, 1)" offset="0%"></stop>
						<stop stop-color="rgba(9, 173, 116, 1)" offset="100%"></stop>
					</linearGradient>
				</defs>
				<path style="transform:translate(0, 50px); opacity:0.9" fill="url(#sw-gradient-1)" d="M0,60L48,54C96,48,192,36,288,28C384,20,480,16,576,24C672,32,768,52,864,62C960,72,1056,72,1152,64C1248,56,1344,40,1440,30C1536,20,1632,16,1728,30C1824,44,1920,76,2016,86C2112,96,2208,84,2304,68C2400,52,2496,32,2592,34C2688,36,2784,60,2880,68C2976,76,3072,68,3168,58C3264,48,3360,36,3456,26C3552,16,3648,8,3744,18C3840,28,3936,56,4032,68C4128,80,4224,76,4320,74C4416,72,4512,72,4608,72C4704,72,4800,72,4896,66C4992,60,5088,48,5184,44C5280,40,5376,44,5472,46C5568,48,5664,48,5760,46C5856,44,5952,40,6048,46C6144,52,6240,68,6336,72C6432,76,6528,68,6624,60C6720,52,6816,44,6864,40L6912,36L6912,120L6864,120C6816,120,6720,120,6624,120C6528,120,6432,120,6336,120C6240,120,6144,120,6048,120C5952,120,5856,120,5760,120C5664,120,5568,120,5472,120C5376,120,5280,120,5184,120C5088,120,4992,120,4896,120C4800,120,4704,120,4608,120C4512,120,4416,120,4320,120C4224,120,4128,120,4032,120C3936,120,3840,120,3744,120C3648,120,3552,120,3456,120C3360,120,3264,120,3168,120C3072,120,2976,120,2880,120C2784,120,2688,120,2592,120C2496,120,2400,120,2304,120C2208,120,2112,120,2016,120C1920,120,1824,120,1728,120C1632,120,1536,120,1440,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"></path>
			</svg>
			<div class="position-relative">
				<div id="beranda" class="content active animate__animated animate__fadeInUp animate__faster">
					<section class="dl-hero">
						<div class="dl-hero-content">
							<h2 class="dl-hero-title">Mulai Konsultasi</h2>
							<p class="dl-hero-text">Ceritakan keluhan Anda agar nakes dapat membantu.</p>
							<?php if ($doclinc_has_active_request) : ?>
								<a href="<?= html_escape(base_url('home#riwayat')); ?>" class="dl-btn-secondary" onclick="openActiveConsultation(<?= html_escape($doclinc_active_request_id); ?>); return false;">
									Lihat Konsultasi Aktif <i class="fas fa-clipboard-list"></i>
								</a>
								<p class="history-empty-text mt-2 mb-0">Anda masih memiliki konsultasi aktif. Selesaikan atau batalkan konsultasi tersebut sebelum membuat permintaan baru.</p>
							<?php else : ?>
								<a href="#" class="dl-btn-secondary" onclick="showContent('konsultasi_kesehatan')">
									Buat Konsultasi <i class="fas fa-plus-circle"></i>
								</a>
							<?php endif; ?>
						</div>
					</section>
					<div class="dl-feature-grid">
						<a href="<?= html_escape($doclinc_has_active_request ? base_url('home#riwayat') : '#'); ?>" class="feature-menu dl-feature-card<?= $doclinc_has_active_request ? ' disabled' : ''; ?>" onclick="<?= $doclinc_has_active_request ? 'openActiveConsultation(' . html_escape($doclinc_active_request_id) . '); return false;' : "showContent('konsultasi_kesehatan'); return false;"; ?>">
							<div class="icon-wrapper">
								<i class="fas fa-user-md"></i>
								<span class="filler"></span>
							</div>
							<span class="small"><?= $doclinc_has_active_request ? 'Konsultasi Aktif' : 'Konsultasi Kesehatan'; ?></span>
						</a>
						<a href="#" data-bs-toggle="modal" class="feature-menu dl-feature-card" onclick="showContent('riwayat')">
							<div class="icon-wrapper">
								<i class="fas fa-briefcase-medical"></i>
								<span class="filler"></span>
							</div>
							<span class="small">Catatan Kesehatan</span>
						</a>
						<a href="#" class="feature-menu dl-feature-card">
							<div class="icon-wrapper bg-secondary">
								<i class="fas fa-bars"></i>
								<span class="filler"></span>
							</div>
							<span class="text-muted small">Lainnya</span>
						</a>
					</div>
					<section>
						<div class="dl-section-header">
							<h3 class="dl-section-title">Konsultasi Saat Ini</h3>
							<a href="<?= html_escape(base_url('home#riwayat')); ?>" class="history-meta text-decoration-none" onclick="openActiveConsultation(<?= html_escape($doclinc_active_request_id); ?>); return false;">Lihat Semua</a>
						</div>
						<?php if (!empty($getAllDataRequests)) : ?>
							<?php foreach ($getAllDataRequests as $dlCurrentRequest) :
								$dl_current_status = !empty($dlCurrentRequest->request_status) ? $dlCurrentRequest->request_status : '-';
								$dl_status_label = $dl_current_status === 'Accepted' ? 'Diterima' : ($dl_current_status === 'Pending' ? 'Menunggu' : $dl_current_status);
								$dl_current_date = !empty($dlCurrentRequest->date) ? date('d-m-Y', strtotime($dlCurrentRequest->date)) : '-';
								$dl_current_keluhan = !empty($dlCurrentRequest->request_description) ? $dlCurrentRequest->request_description : 'Keluhan tersimpan';
								$dl_puskesmas_label = doclinc_request_puskesmas_label($dlCurrentRequest);
								$dl_queue_number_label = doclinc_request_queue_number_label($dlCurrentRequest);
							?>
								<div class="dl-card p-3" data-request-id="<?= (int) $dlCurrentRequest->request_id; ?>">
									<div class="d-flex justify-content-between align-items-start gap-3 mb-3">
										<div>
											<div class="d-flex align-items-center gap-2 mb-1">
												<strong><?= html_escape($dl_puskesmas_label); ?></strong>
												<span class="dl-badge px-2 py-1"><?= html_escape($dl_status_label); ?></span>
											</div>
											<div class="history-meta fw-bold"><?= html_escape($dl_queue_number_label); ?></div>
											<div class="history-meta"><i class="far fa-calendar-alt me-1"></i><?= html_escape($dl_current_date); ?></div>
										</div>
										<div class="icon-wrapper">
											<i class="fas fa-clipboard-list"></i>
											<span class="filler"></span>
										</div>
									</div>
									<div class="history-section mb-3">
										<span class="history-result-label">Keluhan</span>
										<div class="history-result-value"><?= doclinc_history_safe_lines($dl_current_keluhan); ?></div>
									</div>
									<div class="d-grid gap-2">
										<a href="<?= html_escape(base_url('home#riwayat')); ?>" class="dl-btn-secondary w-100" onclick="openActiveConsultation(<?= html_escape((int) $dlCurrentRequest->request_id); ?>); return false;">Lihat Detail</a>
										<?php if ($dl_current_status === 'Pending') : ?>
											<button type="button" class="btn btn-sm btn-outline-danger rounded-pill cancel-warga-request" data-request-id="<?= html_escape((int) $dlCurrentRequest->request_id); ?>">
												<i class="fas fa-times-circle me-1"></i> Batalkan
											</button>
										<?php endif; ?>
									</div>
								</div>
							<?php break;
							endforeach; ?>
						<?php else : ?>
							<div class="dl-empty-state text-center">
								<p class="history-empty-text mb-0">Belum ada konsultasi aktif.</p>
							</div>
						<?php endif; ?>
					</section>
					<div class="row">
						<div class="col">
							<div class="dl-section-header">
								<p class="dl-section-title">News & Feed</p>
								<div class="flex-grow-1">
									<hr class="m-0">
								</div>
							</div>
							<div class="owl-carousel owl-theme">
								<?php
								foreach ($data_feeds->result() as $x) {
								?>
									<div class="item">
										<!--<img src="<?= base_url('assets/images/feeds/' . $x->gambar); ?>">-->
										<img src="<?= base_url('admin_menu/uploads/feeds/' . $x->gambar); ?>">
									</div>
								<?php
								}
								?>
							</div>
						</div>
					</div>
				</div>
				<div id="konsultasi_kesehatan" class="content animate__animated animate__fadeInUp animate__faster">
					<h2 class="dl-section-title mb-3">Konsultasi Kesehatan</h2>
					<?php if ($doclinc_has_active_request) : ?>
						<div class="dl-empty-state text-center">
							<p class="history-empty-text mb-3">Anda masih memiliki konsultasi aktif. Selesaikan atau batalkan konsultasi tersebut sebelum membuat permintaan baru.</p>
							<a href="<?= html_escape(base_url('home#riwayat')); ?>" class="dl-btn-secondary" onclick="openActiveConsultation(<?= html_escape($doclinc_active_request_id); ?>); return false;">Lihat Konsultasi Aktif</a>
						</div>
					<?php else : ?>
					<div class="card shadow mb-2 rounded-4 bg-white clickable-card dl-card"
						data-requestId=""
						data-userIdPasien=""
						data-status=""
						data-tanggal="<?= date('Y-m-d'); ?>"
						data-tanggal_loc="<?= date('Y-m-d H:i:s'); ?>"
						data-link="<?= base_url('konsultasi'); ?>">
						<div class="card-body p-3">
							<div class="d-flex hero-card">
								<img class="rounded-4" src="<?= html_escape(base_url('assets/doclinc/img/default-profile.png')); ?>" width="100px" height="auto" alt="Puskesmas">
								<div class="w-100 ms-2">
									<div class="d-flex">
										<p class="fw-bold mb-0 me-auto">Puskesmas Terdekat</p>
										<div class="end-content">
											<span class="badge rounded-pill status bg-success">Available</span>
										</div>
									</div>
									<p class="mb-0 small">Konsultasi akan diarahkan otomatis ke puskesmas aktif sesuai lokasi Anda.</p>
									<i class="far fa-clock"></i> <em>Siap menerima konsultasi</em>
									<?php foreach ($getAllRequestJumlah->result() as $baris) { ?>
										<input type="hidden" name="jumlah" id="jumlah" value="<?= $baris->jumlah; ?>" />
									<?php } ?>
								</div>
							</div>
						</div>
					</div>
					<?php endif; ?>
					<?php
					if (!$doclinc_has_active_request) :
					foreach ($getAllDataDoctor->result() as $row) {
						$userIdPasien = $row->user_id;
						$userId = $row->userId;
						$nama_dokter = $row->nama;
						$tanggal = $row->date;
						$status = $row->request_status;
						$tanggal_loc = $row->create_date;
						$foto = $row->foto;
					?>
						<div class="card shadow mb-2 rounded-4 bg-white clickable-card dl-card"
							data-requestId="<?= $row->request_id ?>"
							data-userIdPasien="<?= $userIdPasien ?>"
							data-status="<?= $status ?>"
							data-tanggal="<?= $tanggal ?>"
							data-tanggal_loc="<?= $tanggal_loc ?>"
							data-link="<?= base_url('konsultasi'); ?>?nama=<?= $userId; ?>">
							<div class="card-body p-3">
								<div class="d-flex hero-card">
									<img class="rounded-4" id="gambar" src="<?= doclinc_safe_profile_image_src($row->foto ?? ''); ?>" width="100px" height="auto" alt="Foto Profil">
									<div class="w-100 ms-2">
										<div class="d-flex">
											<p class="fw-bold mb-0 me-auto"><?= $nama_dokter; ?></p>
											<div class="end-content">
												<span class="badge rounded-pill status bg-success">Available</span>
											</div>
										</div>

										<!-- Rating Dokter -->
										<div class="mb-1">
											<?php
											// Cari rating dokter ini berdasarkan ID dokter
											$totalRating = 0;
											$totalData = 0;

											foreach ($getAllRating->result() as $rat) {
												if ($rat->id_dokter == $row->dokter_id) {
													$totalRating += $rat->rating;
													$totalData++;
												}
											}

											$rating = ($totalData > 0) ? $totalRating / $totalData : 0;
											// Hitung bintang
											$fullStars = floor($rating);
											$halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
											$emptyStars = 5 - $fullStars - $halfStar;

											// Tambahkan kelas animasi kalau 5 bintang
											$glowClass = ($rating == 5) ? 'glow-star' : '';

											// Tampilkan bintang
											for ($i = 0; $i < $fullStars; $i++) {
												echo '<i class="fas fa-star text-warning"></i> ';
											}
											if ($halfStar) {
												echo '<i class="fas fa-star-half-alt text-warning"></i> ';
											}
											for ($i = 0; $i < $emptyStars; $i++) {
												echo '<i class="far fa-star text-warning"></i> ';
											}
											?>

										</div>

										<p class="mb-0" small>Estimasi :</p>
										<!-- <input type="hidden" name="latitude" id="latitude" placeholder="Latitude" />
										<input type="hidden" name="longitude" id="longitude" placeholder="Longitude" /> -->
										<i class="far fa-clock"></i> <em class="hasil"></em>
									</div>
								</div>
								<div></div>
								<?php
								foreach ($getAllRequestJumlah->result() as $baris) {
									// $baris->jumlah;
									$baris->user_id;
								?>
									<input type="hidden" name="jumlah" id="jumlah" value="<?= $baris->jumlah; ?>" />
								<?php }
								?>
							</div>
						</div>

					<?php }
					endif; ?>

					<div class=" card shadow mb-2 rounded-4 bg-white" hidden>
						<div class="card-body p-2">
							<div class="d-flex">
								<img class="rounded-4" src="<?= base_url('assets/images/ambulance.jpeg'); ?>" width="100px" height="auto">
								<div class="w-100 ms-2">
									<div class="d-flex">
										<p class="fw-bold mb-0 me-auto">AMBULANCE</p>
										<div class="end-content">
											<span class="badge rounded-pill bg-success">Available</span>
										</div>
									</div>
									<p class="mb-0" small>Ambulance Shuttle</p>
									<i class="far fa-clock"></i> <em>25 minutes from you</em>
								</div>
							</div>
							<a class="stretched-link" href="<?= base_url('konsultasi'); ?>"></a>
						</div>
					</div>
				</div>
				<div id="riwayat" class="content animate__animated animate__fadeInUp animate__faster">
					<h2 class="dl-section-title mb-3">Konsultasi Saya</h2>
					<ul class="nav nav-tabs nav-justified mb-3 dl-tabs" id="myTab" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" id="proses-tab" data-bs-toggle="tab" data-bs-target="#proses-tab-pane" type="button" role="tab" aria-controls="proses-tab-pane" aria-selected="false">Saat ini</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="selesai-tab" data-bs-toggle="tab" data-bs-target="#selesai-tab-pane" type="button" role="tab" aria-controls="selesai-tab-pane" aria-selected="false">Riwayat</button>
						</li>
					</ul>
					<!-- Tab Konsultasi -->
					<div class="tab-content" id="myTabContent">
						<div class="tab-pane fade show active" id="proses-tab-pane" role="tabpanel" aria-labelledby="proses-tab" tabindex="0">
							<?php
							if (empty($getAllDataRequests)) :
							?>
								<div class="text-center py-4 dl-empty-state">
									<p class="history-empty-text mb-0">Belum ada konsultasi aktif.</p>
								</div>
							<?php
							endif;
							foreach ($getAllDataRequests as $data) {
								$id_request = $data->request_id;
								$tanggal = $data->date;
								$keluhan = $data->request_description;
								$nama_dokter = $data->nama_dokter;
								$status = $data->request_status;
								$request_status = $status;
								$lat = $data->lattitude;
								$lng = $data->longitude;
								$puskesmas_label = doclinc_request_puskesmas_label($data);
								$queue_number_label = doclinc_request_queue_number_label($data);
								$visit_status = isset($data->visit_status) ? doclinc_normalize_visit_status($data->visit_status) : '';
								$visit_label = $visit_status !== '' ? doclinc_visit_status_label($visit_status) : '';
								$consultation_mode = isset($data->consultation_mode) ? trim((string) $data->consultation_mode) : '';
								$mode_label = doclinc_consultation_mode_label($consultation_mode);
								$handling_nakes_name = doclinc_request_handling_nakes_name($data);
								$handling_nakes_label = $handling_nakes_name !== '' ? 'Ditangani oleh: ' . $handling_nakes_name : 'Menunggu nakes menerima konsultasi';

								// jadikan tanggal di atas formatnya jadi 11 November 2024
								$tanggal = date('d F Y', strtotime($tanggal));

								if ($status == 'Pending') {
									$status = 'Menunggu Konfirmasi';
								} elseif ($status == 'Accepted') {
									$status = 'Nakes Menuju Lokasi';
								}
							?>

								<!-- Popup HTML -->
								<div id="acceptedPopup" class="popup-alert" style="display: none;">
									<span class="close-btn" onclick="closePopup()">&times;</span>
									✅ Nakes telah menerima permintaan konsultasi Anda.
								</div>

								<div class="card shadow mb-2 dl-card" data-request-id="<?= (int) $id_request; ?>">
									<div class="card-header d-flex align-items-center">
										<div>
											<p class="mb-0 fw-bold"><?= html_escape($puskesmas_label); ?></p>
											<p class="mb-0 history-meta"><?= html_escape($queue_number_label); ?> · <?= html_escape($tanggal); ?></p>
										</div>
										<span class="badge text-bg-warning ms-auto animate__animated animate__flash animate__infinite animate__slower"><?= html_escape($status); ?></span>
									</div>
									<div class="card-body">
										<div class="mb-2">
											<span class="badge text-bg-light border"><?= html_escape($mode_label); ?></span>
											<?php if ($visit_label !== '') : ?>
												<span class="badge text-bg-light border"><?= html_escape($visit_label); ?></span>
											<?php endif; ?>
										</div>
										<div class="history-meta mb-2"><?= html_escape($handling_nakes_label); ?></div>
										<p class="mb-0 small fw-bold"><i class="fas fa-notes-medical fa-fw"></i> Keluhan :</p>
										<textarea rows="4" class="form-control" readonly><?= html_escape($keluhan); ?></textarea>
										<p class="mb-0 small fw-bold"><i class="fas fa-stethoscope fa-fw"></i> Nakes :</p>
										<p class="mb-0"><?= html_escape($handling_nakes_name !== '' ? $handling_nakes_name : 'Menunggu nakes menerima konsultasi'); ?></p>
										<p class="mb-0 small fw-bold"><i class="far fa-clock fa-fw"></i> Estimasi :</p>
										<input type="text" name="latitudes" id="latitudes" value="<?= $lat; ?>" hidden />
										<input type="text" name="longitudes" id="longitudes" value="<?= $lng; ?>" hidden />
										<span id="estimasi"></span>
										<?php if ($request_status === 'Accepted') : ?>
											<div class="mt-3">
												<a href="<?= html_escape(base_url('chat?request_id=' . (int) $id_request)); ?>" class="btn btn-success btn-sm rounded-pill dl-btn-primary">
													<i class="fas fa-comments me-1"></i> Chat Konsultasi
												</a>
												<button type="button" class="btn btn-outline-success btn-sm rounded-pill ms-1 visit-location-toggle" data-request-id="<?= html_escape((int) $id_request); ?>" data-map-id="visit-map-<?= html_escape((int) $id_request); ?>">
													<i class="fas fa-map-marker-alt me-1"></i> Lihat Lokasi Nakes
												</button>
												<div class="doclinc-visit-status mt-2" data-visit-status="<?= html_escape((int) $id_request); ?>"></div>
												<div id="visit-map-<?= html_escape((int) $id_request); ?>" class="doclinc-visit-map mt-2 d-none"></div>
											</div>
										<?php elseif ($request_status === 'Pending') : ?>
											<div class="mt-3">
												<button type="button" class="btn btn-outline-danger btn-sm rounded-pill cancel-warga-request" data-request-id="<?= html_escape((int) $id_request); ?>">
													<i class="fas fa-times-circle me-1"></i> Batalkan
												</button>
											</div>
										<?php endif; ?>
									</div>
						</div>
					<?php
					}
					?>
				</div>
						<!-- Tab Riwayat -->
						<div class="tab-pane fade" id="selesai-tab-pane" role="tabpanel" aria-labelledby="selesai-tab" tabindex="0">
							<?php
							if (empty($getAllDataRequestsCompleted)) {
							?>
								<div class="text-center py-4 dl-empty-state">
									<img src="<?= html_escape(base_url('assets/images/not found.svg')); ?>" width="180" alt="Tidak ada data">
									<p class="mb-0 mt-3 text-muted">Belum ada riwayat konsultasi selesai.</p>
								</div>
							<?php
							}
							foreach ($getAllDataRequestsCompleted as $data) {
								$id_request = (int) $data->request_id;
								$tanggal = !empty($data->date) ? $data->date : '';
								$keluhan = !empty($data->request_description) ? $data->request_description : 'Keluhan tersimpan';
								$saran	 = !empty($data->recommendations) ? $data->recommendations : '-';
								$dokter_id = $data->dokter_id;
								$nama_dokter_riwayat = !empty($data->nama_dokter) ? $data->nama_dokter : 'Dokter';
								$handling_nakes_name = doclinc_request_handling_nakes_name($data);
								$handling_nakes_label = $handling_nakes_name !== '' ? 'Ditangani oleh: ' . $handling_nakes_name : 'Menunggu nakes menerima konsultasi';
								$mode_label = doclinc_consultation_mode_label(isset($data->consultation_mode) ? $data->consultation_mode : '');
								$diagnosa = !empty($data->diagnosa) ? $data->diagnosa : (!empty($data->diagnosis) ? $data->diagnosis : '-');
								$saran_dokter = !empty($data->saran) ? $data->saran : $saran;
								$card_id = !empty($data->konsul_id) ? $data->konsul_id : $id_request;
								$treatment = !empty($data->treatment) ? $data->treatment : '';
								$puskesmas = !empty($data->assigned_puskesmas_name) ? $data->assigned_puskesmas_name : '';
								$terapi_list = !empty($data->terapi_list) && is_array($data->terapi_list) ? $data->terapi_list : [];
								$tanggal_riwayat = !empty($tanggal) ? date('d F Y', strtotime($tanggal)) : '-';
								$puskesmas_label = doclinc_request_puskesmas_label($data);
								$queue_number_label = doclinc_request_queue_number_label($data);
							?>
								<div class="card shadow mb-3 history-result-card dl-card" id="card-<?= html_escape($card_id); ?>" data-request-id="<?= $id_request; ?>">
									<div class="card-header d-flex align-items-start gap-3">
										<div class="flex-grow-1">
											<div class="history-request-id"><?= html_escape($puskesmas_label); ?></div>
											<div class="history-meta fw-bold"><?= html_escape($queue_number_label); ?></div>
											<div class="history-meta">
												<?= doclinc_history_safe_text($tanggal_riwayat); ?><br>
												<?= html_escape($handling_nakes_label); ?><br>
												Mode: <?= html_escape($mode_label); ?>
											</div>
										</div>
										<span class="history-status-badge">Completed</span>
									</div>
									<input type="hidden" id="reqIdRat" value="<?= $id_request; ?>">
									<div class="card-body">
										<div class="history-section">
											<div class="history-section-title"><i class="fas fa-notes-medical me-1"></i> Keluhan Awal</div>
											<?= doclinc_history_format_complaint($keluhan); ?>
										</div>
										<div class="history-section">
											<div class="history-section-title"><i class="fas fa-file-medical-alt me-1"></i> Hasil Konsultasi</div>
											<div class="history-result-row">
												<span class="history-result-label">Diagnosa</span>
												<div class="history-result-value"><?= doclinc_history_safe_lines($diagnosa); ?></div>
											</div>
											<div class="history-result-row">
												<span class="history-result-label">Terapi / Tindakan / Obat</span>
												<?php if (!empty($terapi_list)) : ?>
													<div class="history-therapy-list">
														<?php foreach ($terapi_list as $index => $terapi) : ?>
															<div class="history-therapy-item">
																<div class="fw-bold"><?= html_escape(($index + 1) . '. ' . trim((string) ($terapi->terapi ?? '-'))); ?></div>
																<div class="history-meta"><?= doclinc_history_safe_text($terapi->signa ?? '-'); ?></div>
																<?php if (!empty($terapi->keterangan)) : ?>
																	<div class="history-result-value mt-1"><?= doclinc_history_safe_lines($terapi->keterangan); ?></div>
																<?php endif; ?>
															</div>
														<?php endforeach; ?>
													</div>
												<?php elseif ($treatment !== '') : ?>
													<div class="history-result-value"><?= doclinc_history_safe_lines($treatment); ?></div>
												<?php else : ?>
													<p class="history-empty-text mb-0">Belum ada terapi/tindakan yang tercatat.</p>
												<?php endif; ?>
											</div>
											<div class="history-result-row">
												<span class="history-result-label">Rekomendasi / Saran</span>
												<div class="history-result-value"><?= doclinc_history_safe_lines($saran_dokter); ?></div>
											</div>
										</div>
										<input type="hidden" id="doktId" value="<?= html_escape($dokter_id); ?>">
										<button class="btn btn-sm btn-outline-success mt-2 dl-btn-secondary" onclick="downloadCard('card-<?= html_escape($card_id); ?>')">
											<i class="fas fa-file-download"></i> Download Resep
										</button>
										<a href="<?= html_escape(base_url('chat?request_id=' . (int) $id_request)); ?>" class="btn btn-sm btn-outline-secondary mt-2 dl-btn-secondary">
											<i class="fas fa-comments"></i> Lihat Chat
										</a>
									</div>
								</div>
							<?php } ?>
						</div>
					</div>
				</div>

				<div id="profile" class="content animate__animated animate__fadeInUp animate__faster">
					<?php
					$nama = '';
					$tgl = '';
					$jk = '';
					$no_hp = '';
					$alamat = '';
					foreach ($data_profile->result() as $x) {
						$nama = $x->nama;
						$tgl = $x->tgl;
						$jk = $x->gender;
						$no_hp = $x->no_hp;
						$alamat = $x->alamat;
						$role = $x->role;
					}
					?>
					<div class="card shadow-sm border-0 rounded-4 dl-card">
						<div class="card-body">
							<h4 class="dl-section-title text-center mb-4">Profil Pengguna</h4>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="nama_lengkap" value="<?= $nama; ?>" placeholder="Nama Lengkap" readonly>
								<label for="nama_lengkap"><i class="fas fa-user"></i> Nama Lengkap</label>
							</div>
							<div class="form-floating mb-3">
								<input type="date" class="form-control shadow-sm border-success" id="tgl" placeholder="Tanggal Lahir" value="<?= $tgl; ?>" readonly>
								<label for="tgl"><i class="fas fa-calendar-alt"></i> Tanggal Lahir</label>
							</div>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="jk" placeholder="Jenis Kelamin" value="<?= $jk; ?>" readonly>
								<label for="tgl"><i class="fas fa-calendar-alt"></i> Jenis Kelamin</label>
							</div>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="no_hp" value="<?= $no_hp; ?>" placeholder="Nomor HP" readonly>
								<label for="no_hp"><i class="fas fa-phone-alt"></i> Nomor HP</label>
							</div>
							<div class="form-floating mb-3">
								<textarea class="form-control shadow-sm border-success" placeholder="Alamat" id="alamat" readonly style="height: 100px"><?= $alamat; ?></textarea>
								<label for="alamat"><i class="fas fa-map-marker-alt"></i> Alamat</label>
							</div>
							<div class="d-grid mb-3">
								<button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalProfil" hidden>
									<i class="fas fa-edit"></i> Edit Profil
								</button>
							</div>
							<div class="d-grid">
								<button type="button" class="btn btn-outline-danger" id="btn-logout">
									<i class="fas fa-sign-out-alt"></i> Logout
								</button>
							</div>
						</div>
					</div>
				</div>

				<div id="notifikasi" class="content animate__animated animate__fadeInUp animate__faster">

				</div>
				<div id="pahlawan_1" class="content animate__animated animate__fadeInUp animate__faster">

					<form action="" method="post">
						<div class="form-floating mb-2">
							<input type="hidden" class="form-control shadow border-success" id="nama" value="<?php echo $_SESSION['username']; ?>" placeholder="Nama Lengkap" readonly>
						</div>
						<div class="form-floating mb-2">
							<textarea name="keluhan" id="keluhan" class="form-control shadow border-success" placeholder="keluhan"></textarea>
							<label for="keluhan">Keluhan</label>
						</div>
						<div class="form-floating mb-2 d-none">
							<input type="hidden" class="form-control shadow border-success" id="no_hp" value="087775587778" placeholder="Nomor HP" readonly>
							<label for="no_hp">Nomor HP</label>
						</div>
						<div class="form-floating mb-2">

							<textarea id="address" class="form-control shadow border-success" placeholder="Alamat: ..." readonly style="height: 100px"></textarea>
							<label for="alamat">Alamat</label>
							<input type="text" class="d-none" id="latitudex" placeholder="Latitude" readonly>
							<input type="text" class="d-none" id="longitudex" placeholder="Longitude" readonly>
							<div id="map"></div>
						</div>
						<div class="form-floating mb-2">
							<input type="text" class="form-control shadow border-success" id="tanggal" value="<?php echo date('d-m-Y'); ?>" placeholder="tanggal" readonly>
							<label for="Tanggal">Tanggal </label>
						</div>
						<div class="d-grid">
							<button type="submit" class="btn btn-outline-success" id="save_konsul">Kirim</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- menubar bottom -->
	<div class="nav-bottom-wrapper shadow-lg rounded-top-4 dl-bottom-nav" id="nav-bottom-wrapper">
		<div class="container-fluid px-0">
			<div class="row g-0 text-center p-2 menu animate__animated animate__slideInUp animate__faster">
				<a href="#" id="beranda-tab" class="col menu-item active" onclick="showContent('beranda')">
					<i class="fas fa-home fs-4"></i>
					<span class="d-block small mt-1">Beranda</span>
				</a>
				<a href="#" id="konsultasi_kesehatan-tab" class="col menu-item" onclick="showContent('konsultasi_kesehatan')">
					<i class="fas fa-user-md fs-4"></i>
					<span class="d-block small mt-1">Konsultasi</span>
				</a>
				<a href="#" id="riwayat-tab" class="col menu-item" onclick="showContent('riwayat')">
					<i class="fas fa-file-medical fs-4"></i>
					<span class="d-block small mt-1">Riwayat</span>
				</a>
				<a href="#" id="profile-tab" class="col menu-item" onclick="showContent('profile')">
					<i class="fas fa-user fs-4"></i>
					<span class="d-block small mt-1">Profile</span>
				</a>
			</div>
		</div>
	</div>

	<div class="modal fade" id="modalProfil" tabindex="-1" aria-labelledby="modalProfilLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h1 class="modal-title fs-5" id="modalProfilLabel">Edit Profil</h1>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form action="" method="post">
					<div class="modal-body">
						<div class="form-floating mb-2">
							<input type="text" class="form-control shadow border-success" id="nama_lengkap_edit" value="Muhammad Bani Husni" placeholder="Nama Lengkap">
							<label for="nama_lengkap_edit">Nama Lengkap</label>
						</div>
						<div class="form-floating mb-2">
							<input type="date" class="form-control shadow border-success" id="tgl_edit" value="20/07/1993" placeholder="Tanggal Lahir">
							<label for="tgl_edit">Tanggal Lahir</label>
						</div>
						<div class="form-floating mb-2">
							<input type="text" class="form-control shadow border-success" id="jk_edit" value="Laki-laki" placeholder="Jenis Kelamin">
							<label for="jk_edit">Jenis Kelamin</label>
						</div>
						<div class="form-floating mb-2">
							<input type="text" class="form-control shadow border-success" id="no_hp_edit" value="087775587778" placeholder="Nomor HP">
							<label for="no_hp_edit">Nomor HP</label>
						</div>
						<div class="form-floating mb-2">
							<textarea class="form-control shadow border-success" placeholder="Alamat" id="alamat_edit" style="height: 100px">BCS Logistics Center Jl. Raya Merak KM. 115, Cilegon Banten, Indonesia - 42436
	                </textarea>
							<label for="alamat_edit">Alamat</label>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						<button type="submit" class="btn btn-success">Save changes</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="offcanvas offcanvas-top" tabindex="-1" id="offcanvasNotif" aria-labelledby="offcanvasNotifLabel">
		<div class="offcanvas-header">
			<i class="bi bi-bell-fill text-success"></i>
			<p class="offcanvas-title mx-2" id="offcanvasNotifLabel">Pusat Notifikasi</p>
			<span class="badge text-bg-danger" id="badgeNotif">9+</span>
			<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
		</div>
		<div class="offcanvas-body">
			<div id="notificationList" class="notification-list"></div>
		</div>
	</div>

	<div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="false">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content border-0 shadow-lg">
				<div class="modal-header bg-success text-white">
					<h5 class="modal-title" id="ratingModalLabel">Beri Rating Konsultasi</h5>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body text-center">
					<div class="mb-3">
						<img id="gambarDokter" src="" alt="Foto Dokter" class="rounded-circle border border-success shadow-sm" width="100" height="100">
					</div>
					<p class="fw-bold">Bagaimana pengalaman konsultasimu dengan <span id="namaDokter" class="text-success"></span>?</p>
					<!-- Form Rating -->
					<form id="ratingForm">
						<input type="hidden" name="iduser" value="<?= $this->session->userdata('id') ?>">
						<input type="hidden" name="id_dokter" id="dokIds">
						<div class="mb-3">
							<!-- Rating input -->
							<div class="rating-stars">
								<input type="radio" id="star5" name="rating" value="5" required />
								<label for="star5" title="Luar Biasa"><i class="fas fa-star"></i></label>
								<input type="radio" id="star4" name="rating" value="4" />
								<label for="star4" title="Sangat Baik"><i class="fas fa-star"></i></label>
								<input type="radio" id="star3" name="rating" value="3" />
								<label for="star3" title="Baik"><i class="fas fa-star"></i></label>
								<input type="radio" id="star2" name="rating" value="2" />
								<label for="star2" title="Cukup"><i class="fas fa-star"></i></label>
								<input type="radio" id="star1" name="rating" value="1" />
								<label for="star1" title="Buruk"><i class="fas fa-star"></i></label>
							</div>
						</div>
						<button type="submit" class="btn btn-success w-100">Kirim Rating</button>
					</form>
				</div>
			</div>
		</div>
	</div>


	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" integrity="sha512-bPs7Ae6pVvhOSiIcyUClR7/q2OAsRiovw4vAkX+zJbw3ShAeeqezq50RIIcIURq7Oa20rW2n2q+fyXBNcU9lrw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
	<?php if ($map_provider === 'google' && !empty($google_maps_api_key)) : ?>
		<script src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode($google_maps_api_key); ?>"></script>
	<?php endif; ?>

	<!-- firebase dan notifikasi -->

	<?php if ($firebase_enabled) : ?>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
		<?php if ($legacy_superapp_url !== '#') : ?>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/firebase-config.js'); ?>"></script>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/get-notif.js'); ?>"></script>
		<?php endif; ?>
	<?php endif; ?>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

	<script>
		(function(window, $) {
			const ns = window.doclincVisitTracking = window.doclincVisitTracking || {};
			const visitMapboxToken = <?= json_encode($mapbox_public_token); ?>;
			const visitLocationUrl = <?= json_encode(base_url('home/visit_location')); ?>;
			const pollIntervalMs = 12000;
			const maps = ns.maps = ns.maps || {};
			const timers = ns.timers = ns.timers || {};

			function parseLocation(location) {
				if (!location) return null;
				const lat = parseFloat(location.latitude);
				const lng = parseFloat(location.longitude);
				if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
				return {
					latitude: lat,
					longitude: lng,
					updated_at: location.updated_at || ''
				};
			}

			function setVisitStatus(requestId, message, isError) {
				const element = document.querySelector('[data-visit-status="' + requestId + '"]');
				if (!element) return;
				element.textContent = message || '';
				element.classList.toggle('text-danger', !!isError);
				element.classList.toggle('text-success', !isError && !!message);
			}

			function getVisitStatusMessage(response, fallback) {
				const label = response && response.visit_status_label ? response.visit_status_label : '';
				if (!label) return fallback;
				return fallback ? label + ' - ' + fallback : label;
			}

			function stopPolling(requestId) {
				if (timers[requestId]) {
					clearTimeout(timers[requestId]);
					delete timers[requestId];
				}
			}

			function createVisitMarkerIcon(type, label) {
				return L.divIcon({
					className: '',
					html: '<div class="doclinc-visit-marker doclinc-visit-marker--' + type + '">' +
						'<span class="doclinc-visit-marker-label">' + label + '</span>' +
						'<span class="doclinc-visit-marker-pin"></span>' +
						'</div>',
					iconSize: [78, 58],
					iconAnchor: [39, 24],
					popupAnchor: [0, -26]
				});
			}

			ns.initVisitMap = function(containerId, patientLocation, nakesLocation) {
				if (!visitMapboxToken || !window.L) return null;
				const container = document.getElementById(containerId);
				if (!container) return null;

				let state = maps[containerId];
				const center = nakesLocation || patientLocation || {
					latitude: -6.0176,
					longitude: 106.0530
				};

				if (!state) {
					const map = L.map(container).setView([center.latitude, center.longitude], 14);
					L.tileLayer('https://api.mapbox.com/styles/v1/mapbox/streets-v11/tiles/{z}/{x}/{y}?access_token=' + encodeURIComponent(visitMapboxToken), {
						maxZoom: 19,
						tileSize: 512,
						zoomOffset: -1,
						attribution: '&copy; OpenStreetMap contributors &copy; Mapbox'
					}).addTo(map);
					state = maps[containerId] = {
						map: map,
						markers: {}
					};
					setTimeout(function() {
						map.invalidateSize();
					}, 0);
				}

				setTimeout(function() {
					state.map.invalidateSize();
				}, 50);

				return state;
			};

			ns.updateVisitMapMarkers = function(containerId, patientLocation, nakesLocation) {
				const state = maps[containerId];
				if (!state) return;
				const bounds = [];

				function upsertMarker(name, location, label) {
					if (!location) return;
					const latLng = [location.latitude, location.longitude];
					const isNakes = name === 'nakes';
					if (!state.markers[name]) {
						state.markers[name] = L.marker(latLng, {
							icon: createVisitMarkerIcon(name, isNakes ? 'Nakes' : 'Pasien'),
							zIndexOffset: isNakes ? 1000 : 0
						}).addTo(state.map).bindPopup(label);
					} else {
						state.markers[name].setLatLng(latLng);
					}
					bounds.push(latLng);
				}

				upsertMarker('patient', patientLocation, 'Pasien');
				upsertMarker('nakes', nakesLocation, 'Nakes');

				if (bounds.length > 1) {
					state.map.fitBounds(bounds, {
						padding: [48, 48],
						maxZoom: 16
					});
				} else if (bounds.length === 1) {
					state.map.setView(bounds[0], Math.max(state.map.getZoom(), 14));
				}

				setTimeout(function() {
					state.map.invalidateSize();
				}, 0);
			};

			ns.pollVisitLocation = function(requestId, mapContainerId) {
				stopPolling(requestId);

				if (!visitMapboxToken) {
					setVisitStatus(requestId, 'Konfigurasi peta belum tersedia', true);
					return;
				}

				function poll() {
					$.ajax({
						url: visitLocationUrl,
						type: 'GET',
						dataType: 'json',
						data: {
							request_id: requestId
						},
						success: function(response) {
							if (typeof response === 'string') {
								try {
									response = JSON.parse(response);
								} catch (error) {}
							}

							if (response && response.request_status && response.request_status !== 'Accepted') {
								setVisitStatus(requestId, 'Tracking lokasi dihentikan');
								stopPolling(requestId);
								return;
							}

							if (response && response.status === 'success') {
								const patientLocation = parseLocation(response.patient);
								const nakesLocation = parseLocation(response.nakes);
								const state = ns.initVisitMap(mapContainerId, patientLocation, nakesLocation);
								if (state) {
									ns.updateVisitMapMarkers(mapContainerId, patientLocation, nakesLocation);
								}
								setVisitStatus(requestId, getVisitStatusMessage(response, nakesLocation ? 'Lokasi nakes tersedia' : 'Lokasi nakes belum tersedia'));
							} else if (response && response.status === 'pending') {
								setVisitStatus(requestId, getVisitStatusMessage(response, response.message || 'Lokasi nakes belum tersedia'));
							} else {
								setVisitStatus(requestId, response && response.message ? response.message : 'Gagal memuat lokasi nakes', true);
							}

							timers[requestId] = setTimeout(poll, pollIntervalMs);
						},
						error: function(xhr) {
							const response = xhr.responseJSON || {};
							setVisitStatus(requestId, response.message || 'Gagal memuat lokasi nakes', true);
							if (xhr.status === 403 || xhr.status === 404 || xhr.status === 405) {
								stopPolling(requestId);
								return;
							}
							timers[requestId] = setTimeout(poll, pollIntervalMs);
						}
					});
				}

				poll();
			};

			$(document).on('click', '.visit-location-toggle', function(event) {
				event.preventDefault();
				const button = $(this);
				const requestId = button.data('request-id');
				const mapContainerId = button.data('map-id');
				if (!requestId || !mapContainerId) return;

				$('#' + mapContainerId).removeClass('d-none');
				if (maps[mapContainerId] && maps[mapContainerId].map) {
					setTimeout(function() {
						maps[mapContainerId].map.invalidateSize();
					}, 0);
				}
				ns.pollVisitLocation(requestId, mapContainerId);
			});
		})(window, jQuery);
	</script>

	<!-- popup jam operasional -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const lastPopupTime = localStorage.getItem('lastPopupTime');
			const currentTime = new Date().getTime();

			if (!lastPopupTime || (currentTime - lastPopupTime) > 3600000) { // 1 hour = 3600000 ms
				Swal.fire({
					title: 'Informasi',
					text: 'Jam operasional kunjungan nakes dari 08.00 s/d 17.00',
					icon: 'info',
					confirmButtonText: 'OK'
				}).then(() => {
					localStorage.setItem('lastPopupTime', currentTime.toString());
				});
			}
		});
	</script>

	<script>
		console.log('<?= base_url("assets/js/popup-jamops.js") ?>');
	</script>

	<!-- mencari kota cilegon -->
	<!-- <script>
		// Ambil posisi pengguna secara realtime
		let lokasi = {
			lat: -6.0176,
			lng: 106.0530
		}; // Default: Cilegon

		if (navigator.geolocation) {
			navigator.geolocation.getCurrentPosition(function(position) {
				lokasi = {
					lat: position.coords.latitude,
					lng: position.coords.longitude
				};
				// Jalankan geocoder setelah dapat lokasi
				const geocoders = new google.maps.Geocoder();
				geocoders.geocode({
					location: lokasi
				}, function(results, status) {
					if (status === 'OK') {
						console.log(results, status);

						if (results[0]) {
							const components = results[0].address_components;
							const city = components.find(c => c.types.includes("locality")) ||
								components.find(c => c.types.includes("administrative_area_level_2"));
							console.log(city);
							document.getElementById('kota').textContent = city ? city.long_name : "Tidak ditemukan";
						} else {
							document.getElementById('kota').textContent = "Tidak ada hasil geocoding";
						}
					} else {
						document.getElementById('kota').textContent = "Geocoder gagal: " + status;
					}
				});
			}, function(error) {
				alert(error);

				document.getElementById('kota').textContent = "Lokasi tidak tersedia";
			});
		} else {
			document.getElementById('kota').textContent = "Geolocation tidak didukung";
		}
		// return; // Agar kode di bawah tidak dijalankan dua kali

		// const geocoders = new google.maps.Geocoder();
		// geocoders.geocode({
		// 	location: lokasi
		// }, function(results, status) {
		// 	if (status === 'OK') {
		// 		if (results[0]) {
		// 			const components = results[0].address_components;
		// 			const city = components.find(c => c.types.includes("locality")) ||
		// 				components.find(c => c.types.includes("administrative_area_level_2"));
		// 			console.log(city);
		// 			document.getElementById('kota').textContent = city.long_name;
		// 		} else {
		// 			document.getElementById('kota').textContent = "Tidak ada hasil geocoding";
		// 		}
		// 	} else {
		// 		document.getElementById('kota').textContent = "Geocoder gagal: " + status;
		// 	}
		// });
	</script> -->

	<script>
		let doktIdEl = document.getElementById('doktId');
		let dokId = document.getElementById('dokId');
		if (doktIdEl && dokId) {
			dokId.value = doktIdEl.value;
			console.log('Dokter Id: ' + dokId);
		}
	</script>

	<script>
		console.log('ini adalah base url: ' + '<?= base_url("assets/images/dokter_jaenul.jpeg") ?>');

		const gambar = document.getElementById('gambar');
		if (gambar) {
			console.log(gambar['src']);
		}
	</script>

	<!-- Webtoapk dan lain-lain -->
	<script>
		function exitApp() {
			Website2APK.exitApp();
		}
		window.addEventListener('load', function() {
			setTimeout(function() {
				$('#preloader').fadeOut('fast');
				document.getElementById('content-wrapper').style.display = 'block';
				document.getElementById('nav-bottom-wrapper').style.display = 'block';
			}, 1500);
		});
		if ($('#notify-number').text() !== '') {
			$('a.notify > i').addClass('animate__animated animate__tada animate__infinite');
		}

		document.addEventListener('DOMContentLoaded', function() {
			var hash = window.location.hash;
			if (hash) {
				showContent(hash.replace('#', ''));
			}
		});

		function showContent(tab) {
			let currentActiveContent = document.querySelector('.content.active');
			if (currentActiveContent) {
				currentActiveContent.classList.remove('active');
			}

			let targetContent = document.getElementById(tab);
			if (targetContent) {
				targetContent.classList.add('active');
			}

			let currentActiveMenu = document.querySelector('.nav-bottom-wrapper .menu a.active');
			if (currentActiveMenu) {
				currentActiveMenu.classList.remove('active');
			}

			let targetMenu = document.getElementById(tab + '-tab');
			if (targetMenu) {
				targetMenu.classList.add('active');
			}
		}

		function openActiveConsultation(requestId) {
			showContent('riwayat');

			const prosesTab = document.getElementById('proses-tab');
			if (prosesTab && typeof bootstrap !== 'undefined') {
				const tab = new bootstrap.Tab(prosesTab);
				tab.show();
			}

			window.location.hash = 'riwayat';
			setTimeout(function() {
				const selector = requestId ? '.dl-card[data-request-id="' + requestId + '"]' : '#proses-tab-pane';
				const target = document.querySelector(selector) || document.getElementById('proses-tab-pane');
				if (target) {
					target.scrollIntoView({
						behavior: 'smooth',
						block: 'start'
					});
				}
			}, 100);
		}

		$('#btn-logout').click(function(event) {
			Swal.fire({
				title: "Logout?",
				text: "Kamu yakin ingin keluar?",
				icon: "warning",
				showCancelButton: true,
				confirmButtonText: "Ya",
				confirmButtonColor: "#09AD74",
				cancelButtonText: "Tidak"
			}).then((result) => {
				if (result.isConfirmed) {
					Swal.fire({
						title: "See you!",
						icon: "success",
						showConfirmButton: false,
						timer: 1500,
						timerProgressBar: true
					}).then((result) => {
						if (result.dismiss === Swal.DismissReason.timer) {
							window.location.href = 'login/logout';
						}
					});
				}
			});
		});

		$('#save_konsul').click(function() {
			var nama = $('#nama').val();
			var keluhan = $('#keluhan').val();
			var no_hp = $('#no_hp').val();
			var lat = $('#latitude').val();
			var lng = $('#longitude').val();
			var alamat = $('#address').val();
			var tanggal = $('#tanggal').val();

			alert(nama);
			alert(keluhan);
			alert(lat);
			alert(lng);
			alert(alamat);
			alert(tanggal);
		});
		$('.owl-carousel').owlCarousel({
			loop: true,
			autoplay: true,
			autoplayTimeout: 2500,
			autoplayHoverPause: true,
			margin: 10,
			responsiveClass: true,
			responsive: {
				0: {
					items: 1
				},
				640: {
					items: 2
				},
				1024: {
					items: 3
				}
			}
		});

		$(document).on('click', '.cancel-warga-request', function(event) {
			event.preventDefault();
			event.stopPropagation();

			const button = $(this);
			const requestId = button.data('request-id');
			if (!requestId) {
				Swal.fire('Gagal', 'Data request tidak ditemukan.', 'error');
				return;
			}

			Swal.fire({
				title: 'Batalkan konsultasi?',
				text: 'Permintaan konsultasi yang dibatalkan tidak dapat dilanjutkan.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Ya, batalkan',
				confirmButtonColor: '#dc3545',
				cancelButtonText: 'Tidak'
			}).then((result) => {
				if (!result.isConfirmed) {
					return;
				}

				button.prop('disabled', true).addClass('disabled');
				$.ajax({
					url: '<?= base_url('home/cancel_request'); ?>',
					type: 'POST',
					dataType: 'json',
					data: {
						request_id: requestId
					},
					success: function(response) {
						if (typeof response === 'string') {
							try {
								response = JSON.parse(response);
							} catch (error) {}
						}
						if (response && response.status === 'success') {
							Swal.fire({
								title: 'Berhasil',
								text: response.message || 'Konsultasi berhasil dibatalkan.',
								icon: 'success',
								showConfirmButton: false,
								timer: 1200,
								timerProgressBar: true
							}).then(() => {
								window.location.reload();
							});
							return;
						}

						button.prop('disabled', false).removeClass('disabled');
						Swal.fire('Gagal', response && response.message ? response.message : 'Konsultasi tidak dapat dibatalkan.', 'error');
					},
					error: function(xhr) {
						button.prop('disabled', false).removeClass('disabled');
						const response = xhr.responseJSON || {};
						Swal.fire('Gagal', response.message || 'Konsultasi tidak dapat dibatalkan.', 'error');
					}
				});
			});
		});
	</script>

	<!-- Menagtur Auto Scroll -->
	<script>
		// 		// Mengatur auto-scroll pada kontainer
		// 		const scrollContainer = document.getElementById('scrollContainer');
		// 		let scrollAmount = 0;

		// 		function autoScroll() {
		// 			scrollAmount += 380; // Jarak scroll dalam piksel
		// 			if (scrollAmount >= scrollContainer.scrollWidth - scrollContainer.clientWidth) {
		// 				scrollAmount = 0; // Kembali ke awal jika sudah mencapai akhir
		// 			}
		// 			scrollContainer.scrollTo({
		// 				left: scrollAmount,
		// 				behavior: 'smooth'
		// 			});
		// 		}

		// 		// Mengulangi scroll setiap 3 detik
		// 		setInterval(autoScroll, 3000);
		document.addEventListener("DOMContentLoaded", function() {
			const scrollContainer = document.getElementById('scrollContainer');

			if (!scrollContainer) {
				console.error("Elemen #scrollContainer tidak ditemukan.");
				return;
			}

			let scrollAmount = 0;

			function autoScroll() {
				if (!scrollContainer) return;

				scrollAmount += 380; // Jarak scroll dalam piksel

				if (scrollAmount >= scrollContainer.scrollWidth - scrollContainer.clientWidth) {
					scrollAmount = 0; // Kembali ke awal jika sudah mencapai akhir
				}

				scrollContainer.scrollTo({
					left: scrollAmount,
					behavior: 'smooth'
				});
			}

			// Mengulangi scroll setiap 3 detik
			setInterval(autoScroll, 3000);
		});
	</script>

	<!-- Mengirim lokasi -->
	<script>
		const mapProvider = <?= json_encode($map_provider); ?>;
		const firebaseEnabled = <?= json_encode($firebase_enabled); ?>;
		const mapboxPublicToken = <?= json_encode($mapbox_public_token); ?>;

		function getFirebaseDatabase() {
			const noopRef = {
				on: function() {},
				child: function() {
					return noopRef;
				},
				remove: function() {
					return Promise.resolve();
				},
				push: function() {
					return noopRef;
				},
				set: function() {
					return Promise.resolve();
				}
			};

			if (!firebaseEnabled || !window.firebase || !firebase.database) {
				return {
					ref: function() {
						return noopRef;
					}
				};
			}

			try {
				return firebase.database();
			} catch (error) {
				return {
					ref: function() {
						return noopRef;
					}
				};
			}
		}

		let map;
		let marker;
		let geocoder;

		function setLocationFields(location) {
			const latitudeEl = document.getElementById("latitude");
			const longitudeEl = document.getElementById("longitude");
			const latitudexEl = document.getElementById("latitudex");
			const longitudexEl = document.getElementById("longitudex");

			if (latitudeEl) latitudeEl.value = location.lat;
			if (longitudeEl) longitudeEl.value = location.lng;
			if (latitudexEl) latitudexEl.value = location.lat;
			if (longitudexEl) longitudexEl.value = location.lng;
		}

		function setLocationText(address, kota) {
			const addressEl = document.getElementById("address");
			const kotaEl = document.getElementById('kota');

			if (addressEl) addressEl.innerHTML = address || "Lokasi belum tersedia";
			if (kotaEl) kotaEl.textContent = kota || "Lokasi belum tersedia";
		}

		function getMapboxCity(feature) {
			if (!feature) return '';

			if (feature.place_type && feature.place_type.includes('place')) {
				return feature.text || '';
			}

			const context = feature.context || [];
			const city = context.find(item => item.id && item.id.indexOf('place.') === 0) ||
				context.find(item => item.id && item.id.indexOf('district.') === 0);

			return city ? city.text : '';
		}

		function getMapboxAddress(location) {
			if (mapProvider !== 'mapbox' || !mapboxPublicToken || !window.fetch) {
				setLocationText(location.lat + ', ' + location.lng, '');
				return;
			}

			const url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' +
				encodeURIComponent(location.lng + ',' + location.lat) +
				'.json?access_token=' + encodeURIComponent(mapboxPublicToken);

			fetch(url)
				.then(response => {
					if (!response.ok) {
						throw new Error('Mapbox geocoding failed');
					}
					return response.json();
				})
				.then(data => {
					const feature = data.features && data.features.length ? data.features[0] : null;
					setLocationText(feature ? feature.place_name : '', getMapboxCity(feature));
				})
				.catch(() => {
					setLocationText(location.lat + ', ' + location.lng, '');
				});
		}

		function initMap() {
			if (mapProvider !== 'google' || !window.google || !google.maps) {
				setLocationText('', '');

				if (navigator.geolocation) {
					navigator.geolocation.watchPosition(function(position) {
						const newLocation = {
							lat: position.coords.latitude,
							lng: position.coords.longitude,
						};

						setLocationFields(newLocation);
						getAddress(newLocation);
					}, showError);
				}
				return;
			}

			// Inisialisasi peta
			const initialLocation = {
				lat: -6.1751,
				lng: 106.8650
			}; // Lokasi awal (Jakarta)
			map = new google.maps.Map(document.getElementById("map"), {
				zoom: 15,
				center: initialLocation,
			});

			marker = new google.maps.Marker({
				position: initialLocation,
				map: map,
			});

			geocoder = new google.maps.Geocoder();

			// Mendapatkan lokasi pengguna
			if (navigator.geolocation) {
				navigator.geolocation.watchPosition(updateLocation, showError);
			} else {
				Swal.fire({
					title: "Error",
					text: "Geolocation is not supported by this browser.",
					icon: "error",
					confirmButtonText: "OK"
				});
			}
		}

		function updateLocation(position) {
			const newLocation = {
				lat: position.coords.latitude,
				lng: position.coords.longitude,
			};

			// Update posisi marker dan pusat peta
			marker.setPosition(newLocation);
			map.setCenter(newLocation);

			// Tampilkan latitude dan longitude
			setLocationFields(newLocation);

			// Mendapatkan alamat dengan Geocoder
			getAddress(newLocation);

		}

		function sendData() {
			if (mapProvider !== 'google') {
				document.querySelectorAll('.hasil').forEach((element) => {
					element.textContent = '-';
				});
				return;
			}

			// Ambil nilai dari input
			const latitude = document.getElementById('latitude').value;
			const longitude = document.getElementById('longitude').value;

			// Kirim data ke PHP menggunakan fetch
			fetch('<?= base_url('home/getDuration') ?>', { // Kirim ke halaman yang sama
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded'
					},
					body: 'latitude=' + encodeURIComponent(latitude) + '&longitude=' + encodeURIComponent(longitude)
				})
				.then(response => response.json())
				.then(result => {
					console.log('hasilnya: ' + result);


					// Tampilkan respon dari PHP
					const results = document.querySelectorAll('.hasil');
					const heroCards = document.querySelectorAll('.hero-card'); // Pastikan setiap pahlawan ada di elemen dengan class ini
					const badges = document.querySelectorAll('.badge.rounded-pill.status'); // Ambil elemen badge "Available"

					// const times = result.split(/\s+/);
					// results.forEach((index) => {
					// 	index.textContent = result + ' From You';
					// });
					results.forEach((element, index) => {
						if (result[index]) { // Pastikan ada data untuk elemen ini
							// element.textContent = result[index].time + ' From You';
							const timeText = result[index].time; // Contoh: "22 mins"
							element.textContent = timeText + " From You";

							// Ambil angka durasi dari string (misalnya "22 mins" -> 22)
							const duration = parseInt(timeText);

							if (duration > 60) {
								setNotAvailable(heroCards[index], badges[index]);
							}
						} else {
							element.textContent = '-';
							setNotAvailable(heroCards[index], badges[index]);
						}
					});

					// document.getElementById('results').innerHTML = result + ' From You';
					console.log("Data telah dikirim.");

				})
				.catch(error => console.error("Error:", error));

			// Fungsi untuk mengubah status menjadi "Not Available"
			function setNotAvailable(heroCard, badge) {
				if (!heroCard || !badge) return;

				const link = heroCard.closest('.card').querySelector('.stretched-link');

				heroCard.classList.add('disabled');
				heroCard.style.pointerEvents = 'none';
				heroCard.style.opacity = '0.5';
				heroCard.style.cursor = 'not-allowed';

				// Ganti teks "Available" menjadi "Not Available"
				badge.textContent = "Not Available";
				badge.classList.remove('bg-success');
				badge.classList.add('bg-danger');

				// Hapus link agar tidak bisa diklik
				if (link) {
					link.remove();
				}

				// Tambahkan event agar user tidak bisa klik
				heroCard.addEventListener('click', function(event) {
					event.preventDefault();
					event.stopPropagation();
					Swal.fire({
						title: 'Pahlawan Tidak Tersedia',
						text: 'Pahlawan ini tidak bisa dipilih karena tidak memiliki durasi atau jaraknya lebih dari 20 menit.',
						icon: 'warning',
						confirmButtonText: 'OK'
					});
				}, true);
			}

		}

		function getAddress(location) {
			if (mapProvider === 'mapbox') {
				getMapboxAddress(location);
				return;
			}

			if (!geocoder) {
				setLocationText(location.lat + ', ' + location.lng, '');
				return;
			}

			geocoder.geocode({
				location: location
			}, (results, status) => {
				if (status === "OK") {
					if (results[0]) {
						document.getElementById("address").innerHTML = results[0].formatted_address;

						const components = results[0].address_components;
						const city = components.find(c => c.types.includes("locality")) ||
							components.find(c => c.types.includes("administrative_area_level_2"));
						console.log('Kotanya: ', city);
						document.getElementById('kota').textContent = city ? city.long_name : "Tidak ditemukan";
					} else {
						document.getElementById("address").value = "No results found";
					}
				} else {
					document.getElementById("address").value = "Geocoder failed due to: " + status;
				}
			});
		}

		function showError(error) {
			switch (error.code) {
				// case error.PERMISSION_DENIED:
				// 	alert("User denied the request for Geolocation.");
				// 	break;
				case error.POSITION_UNAVAILABLE:
					setLocationText('', '');
					break;
				case error.TIMEOUT:
					setLocationText('', '');
					break;
				case error.UNKNOWN_ERROR:
					setLocationText('', '');
					break;
			}
		}


		// window.onload = initMap;
		window.onload = function() {
			initMap();
			// sendData();
			// setInterval(function() {
			// 	sendData();
			// }, 2000);
			setTimeout(function() {
				sendData();
			}, 1000);
		}

		// Tambahkan ini di akhir file JavaScript Anda
		if (typeof Flutter !== 'undefined') {
			Flutter.postMessage('ready');
		}
	</script>

	<!-- validasi sebelum konsultasi -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const cards = document.querySelectorAll('.clickable-card');

			cards.forEach(card => {
				card.addEventListener('click', function() {
					const userIdWarga = <?= $this->session->userdata('id'); ?>;
					const userIdPasien = card.getAttribute('data-userIdPasien');
					const status = card.getAttribute('data-status');
					const tanggal = card.getAttribute('data-tanggal');
					const tanggal_loc = card.getAttribute('data-tanggal_loc');
					const link = card.getAttribute('data-link');
					const link_riwayat = "<?= base_url('home#riwayat') ?>";

					const jumlah = document.getElementById('jumlah').value;
					// const uid = document.getElementById('uid').value;

					const today = new Date().toLocaleDateString('id-ID', {
						timeZone: 'Asia/Jakarta',
						year: 'numeric',
						month: '2-digit',
						day: '2-digit'
					}).split('/').reverse().join('-'); // format YYYY-MM-DD
					console.log(today);
					console.log(tanggal);

					const uids = <?= json_encode(array_column($getAllRequestPendingAccept->result_array(), 'user_id')) ?>;
					console.log("UIDs:", uids);
					const tgl = <?= json_encode(array_column($getAllRequestPendingAccept->result_array(), 'date')) ?>;
					console.log("tanggal:", tgl);
					const stats = <?= json_encode(array_column($getAllRequestPendingAccept->result_array(), 'request_status')) ?>;
					console.log("status:", stats);
					const reqId = <?= json_encode(array_column($getAllRequestPendingAccept->result_array(), 'request_id')) ?>;
					const reqIds = String(reqId);
					console.log("requestId:", reqId);

					const userIdToCheck = String(userIdWarga);

					const kotaCilegon = document.getElementById('kota').textContent;
					console.log(kotaCilegon);
					if (!['Cilegon', 'Kota Cilegon'].includes(kotaCilegon)) {
						// buatkan alert yang dengan swal
						Swal.fire({
							title: 'Peringatan',
							text: 'Anda tidak bisa mengajukan konsultasi, karena posisi Anda saat ini berada di luar Kota Cilegon.',
							icon: 'warning',
							confirmButtonText: 'Tutup'
						}).then((result) => {
							if (result.isConfirmed) {
								// console.log('ok');
							}
						})
						return;
					}

					// jika tanggal tidak ada isinya
					if (tanggal_loc === null || tanggal_loc === '') {
						// Tanggal sudah lewat
						Swal.fire({
							title: 'Peringatan',
							text: 'Tidak bisa melakukan Konsultasi. Silakan pilih Tenaga Kesehatan yang lain.',
							icon: 'warning',
							confirmButtonText: 'Tutup'
						});
						return;
					}

					console.log("userIdWarga:", userIdWarga);
					console.log("userIdPasien:", userIdPasien);
					console.log("status:", status);
					console.log("tanggal:", tanggal);
					console.log("today:", today);
					console.log("tanggal_loc:", tanggal_loc);
					console.log("jumlah:", jumlah);

					// console.log("uid:", uid);
					console.log("User Id Check:",
						userIdToCheck);

					if (uids.includes(userIdToCheck) === false) {
						if (jumlah > 100) {
							// Jika userIdWarga tidak sama dengan userIdPasien
							Swal.fire({
								title: 'Peringatan',
								text: 'Anda tidak bisa melakukan konsultasi, karena permintaan konsultasi sudah melebihi batas.',
								icon: 'warning',
								confirmButtonText: 'Tutup'
							});
							return;
						} else {
							// Jika userIdPasien sama dengan id_user
							window.location.href = link;
							// return;
							// alert("ada");
						}
					} else if (uids.includes(userIdToCheck) === true && stats.includes('Completed')) {
						if (tgl == today) {
							Swal.fire({
								title: 'Peringatan',
								text: 'Anda tidak bisa melakukan konsultasi di hari yang sama.',
								icon: 'warning',
								confirmButtonText: 'Tutup'
							});
						} else {
							window.location.href = link;
						}
					} else if (String(userIdWarga) === userIdToCheck) {
						if (stats == 'Pending' || stats == 'Accepted') {
							if (tgl == today) {
								// Tidak bisa konsultasi lagi hari ini
								Swal.fire({
									title: 'Peringatan',
									text: 'Anda sudah melakukan konsultasi hari ini. Silakan tunggu sampai besok atau tunggu konsultasi ini selesai.',
									icon: 'warning',
									confirmButtonText: 'Tutup'
								});
							} else {
								// Boleh lanjut atau perbarui
								Swal.fire({
									title: 'Peringatan',
									text: 'Anda memiliki konsultasi sebelumnya yang belum selesai pada tanggal ' + tgl + '. Ingin memperbarui tanggal konsultasi?',
									icon: 'warning',
									showCancelButton: true,
									confirmButtonText: 'Perbarui',
									cancelButtonText: 'Hapus'
								}).then((result) => {
									if (result.isConfirmed) {
										// Arahkan ke endpoint untuk perbarui (opsional ganti endpoint-nya)
										$.ajax({
											url: '<?= base_url('home/updaterequestbyid') ?>',
											type: 'POST',
											data: {
												requestId: reqIds
											},
											success: function(response) {
												console.log(response);
												Swal.fire({
													title: 'Berhasil',
													text: 'Tanggal konsultasi berhasil diperbarui.',
													icon: 'success',
													confirmButtonText: 'OK'
												}).then(() => {
													window.location.href = "<?= base_url('home#riwayat') ?>";
												});
											}
										});
									} else if (result.dismiss === Swal.DismissReason.cancel) {
										// Tetap arahkan ke konsultasi
										// hapus data request
										$.ajax({
											url: '<?= base_url('home/deleterequestbyid') ?>',
											type: 'POST',
											data: {
												requestId: reqIds
											},
											success: function(response) {
												console.log("Data berhasil dihapus:", response);
											}
										});

										console.log("Id Warga:", userIdWarga);
										console.log("Internal request id:", reqIds);

										window.location.href = link;
									}
								});
							}
						} else if (stats.includes('Completed')) {
							// Status aman, lanjut ke konsultasi
							window.location.href = link;
						} else if (uids.includes(userIdToCheck)) {
							// Jika status masih Pending atau Accepted
							Swal.fire({
								title: 'Peringatan',
								text: 'Anda tidak bisa melakukan konsultasi, karena konsultasi sebelumnya belum selesai.',
								icon: 'warning',
								confirmButtonText: 'Tutup'
							});
						} else {
							console.log("User ID tidak ditemukan.");
						}
					}
				});
			});
		});
	</script>

	<!-- Notifikasi Chat -->
	<script>
		const notificationListJsonUrl = <?= json_encode(base_url('notifikasi/list_json')); ?>;
		const notificationMarkReadUrl = <?= json_encode(base_url('notifikasi/mark_read')); ?>;
		const notificationPollIntervalMs = 30000;

		function getNotificationBadge() {
			return document.getElementById('badgeNotif') || document.getElementById('badgeNotifs');
		}

		function setNotificationCount(count) {
			const badge = getNotificationBadge();
			if (!badge) {
				return;
			}
			if (count > 0) {
				badge.style.display = 'inline';
				badge.textContent = count > 9 ? '9+' : count;
			} else {
				badge.style.display = 'none';
				badge.textContent = '';
			}
		}

		function renderNotificationItem(item) {
			const notificationItem = document.createElement('div');
			notificationItem.className = 'notification-item d-flex align-items-center p-2 border-bottom';
			notificationItem.style.cursor = 'pointer';

			const icon = document.createElement('i');
			icon.className = 'bi bi-bell-fill text-success me-3 fs-4';
			notificationItem.appendChild(icon);

			const content = document.createElement('div');
			content.className = 'flex-grow-1';

			const title = document.createElement('strong');
			title.textContent = item.title || 'Notifikasi';
			content.appendChild(title);
			content.appendChild(document.createElement('br'));

			const message = document.createElement('small');
			message.textContent = item.message || 'Tidak ada detail';
			content.appendChild(message);
			content.appendChild(document.createElement('br'));

			const timestamp = document.createElement('small');
			timestamp.className = 'text-muted';
			timestamp.textContent = item.created_at ? new Date(item.created_at.replace(' ', 'T')).toLocaleString('id-ID') : '';
			content.appendChild(timestamp);

			notificationItem.appendChild(content);
			notificationItem.addEventListener('click', function() {
				markNotificationRead(item);
			});

			return notificationItem;
		}

		function renderDatabaseNotifications(items, count) {
			const notificationList = document.getElementById('notificationList');
			if (!notificationList) {
				return;
			}
			setNotificationCount(count);
			notificationList.innerHTML = '';
			if (!items || !items.length) {
				notificationList.innerHTML = '<p class="text-muted mb-0">Tidak ada notifikasi</p>';
				return;
			}
			items.forEach(function(item) {
				notificationList.appendChild(renderNotificationItem(item));
			});
		}

		function loadDatabaseNotifications() {
			if (document.visibilityState === 'hidden') {
				return;
			}
			fetch(notificationListJsonUrl, {
					credentials: 'same-origin'
				})
				.then(function(response) {
					if (!response.ok) {
						throw new Error('notification_load_failed');
					}
					return response.json();
				})
				.then(function(data) {
					if (!data || data.status !== 'success') {
						return;
					}
					renderDatabaseNotifications(data.notifications || [], parseInt(data.unread_count || 0, 10));
				})
				.catch(function() {});
		}

		function startDatabaseNotificationPolling() {
			if (window.doclincNotificationPollingStarted) {
				return;
			}
			window.doclincNotificationPollingStarted = true;
			loadDatabaseNotifications();
			window.setInterval(loadDatabaseNotifications, notificationPollIntervalMs);
			document.addEventListener('visibilitychange', function() {
				if (document.visibilityState === 'visible') {
					loadDatabaseNotifications();
				}
			});
		}

		function markNotificationRead(notification) {
			const notificationId = notification && notification.notification_id ? notification.notification_id : notification;
			const actionUrl = notification && notification.action_url ? notification.action_url : '';
			if (!notificationId) {
				return;
			}
			const formData = new FormData();
			formData.append('notification_id', notificationId);
			fetch(notificationMarkReadUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
				.then(function(response) {
					if (response.ok) {
						if (actionUrl) {
							window.location.href = actionUrl;
							return;
						}
						loadDatabaseNotifications();
					}
				});
		}

		document.addEventListener('DOMContentLoaded', startDatabaseNotificationPolling);
		document.addEventListener('DOMContentLoaded', function() {
			const highlightRequestId = new URLSearchParams(window.location.search).get('highlight_request_id');
			if (!highlightRequestId) {
				return;
			}
			document.querySelectorAll('[data-request-id]').forEach(function(card) {
				if (card.getAttribute('data-request-id') === highlightRequestId) {
					card.classList.add('border', 'border-success', 'border-2');
					card.scrollIntoView({
						behavior: 'smooth',
						block: 'center'
					});
				}
			});
		});
	</script>

	<!-- simpan lokasi ke firebase -->
	<script>
		const waktu = getFirebaseDatabase().ref('location');
		const latitudesEl = document.getElementById('latitudes');
		const longitudesEl = document.getElementById('longitudes');
		const lats = latitudesEl ? parseFloat(latitudesEl.value) : null;
		const lngs = longitudesEl ? parseFloat(longitudesEl.value) : null;

		let estimasiLat;
		let estimasiLng;

		waktu.on('value', (snapshot) => {
			if (lats === null || lngs === null) {
				return;
			}

			const data = snapshot.val();
			estimasiLat = '';
			estimasiLng = '';

			for (const key in data) {
				estimasiLat += data[key].latitude;
				estimasiLng += data[key].longitude;
			}

			const origin = {
				lat: parseFloat(estimasiLat),
				lng: parseFloat(estimasiLng)
			};

			console.log(origin);

			if (mapProvider !== 'google' || !window.google || !google.maps) {
				return;
			}

			const destination = new google.maps.LatLng(lats, lngs);
			const service = new google.maps.DistanceMatrixService();


			service.getDistanceMatrix({
				origins: [origin],
				destinations: [destination],
				travelMode: google.maps.TravelMode.DRIVING,
				avoidHighways: false,
				avoidTolls: false
			}, function(response, status) {
				if (status === "OK") {
					const result = response.rows[0].elements[0];
					document.getElementById("estimasi").innerHTML = result.duration.text;
				} else {
					alert("Error: " + status);
				}
			});


		});
	</script>

	<!-- popup untuk accepted-->
	<script>
		function closePopup() {
			const popup = document.getElementById("acceptedPopup");
			if (popup) popup.style.display = "none";
		}

		// Auto-close setelah 5 detik
		setTimeout(closePopup, 5000);
	</script>

	<!-- Download resep obat -->
	<!-- <script>
		function downloadCard(cardId) {
			const element = document.getElementById(cardId);
			const opt = {
				margin: 0.3,
				filename: 'resep-' + cardId + '.pdf',
				image: {
					type: 'jpeg',
					quality: 0.98
				},
				html2canvas: {
					scale: 2
				},
				jsPDF: {
					unit: 'cm',
					format: 'a5',
					orientation: 'portrait'
				}
			};
			html2pdf().set(opt).from(element).save();
		}
	</script> -->
	<script>
		function downloadCard(cardId) {
			// Ambil elemen resep
			var cardContent = document.getElementById(cardId).innerHTML;

			// Buat jendela baru untuk mencetak
			var printWindow = window.open('', '', 'height=600,width=800');

			printWindow.document.write(`
    <html>
    <head>
      <title>Resep Dokter</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          padding: 20px;
        }
        table {
          width: 100%;
          border-collapse: collapse;
        }
        th, td {
          padding: 8px;
          border: 1px solid #ccc;
        }
        th {
          background-color: #f8f8f8;
        }
        h5 {
          text-align: center;
        }
        .btn, .badge {
          display: none !important;
        }
      </style>
    </head>
    <body>
      ${cardContent}
    </body>
    </html>
  `);

			printWindow.document.close(); // Tutup dokumen
			printWindow.focus(); // Fokus ke jendela

			printWindow.print(); // Jalankan perintah print
			printWindow.close(); // Tutup jendela setelah cetak
		}
	</script>


	<!-- tampil notif dari nakes -->
	<script>
		var request_id = <?= json_encode($id_request ?? ''); ?>;

		// Ambil data dari node 'notif'
		const notifRef = getFirebaseDatabase().ref("notiffromdoc");

		// Dengarkan perubahan data notifikasi
		notifRef.on("value", (snapshot) => {
			const data = snapshot.val();
			if (!data) return;

			for (let id in data) {
				const notif = data[id];
				const statusNotif = document.getElementById("statusNotif");
				if (notif.status === "Nakes Menuju Lokasi" && notif.id_req === request_id) {
					if (statusNotif) {
						statusNotif.innerHTML = notif.status;
					} else {
						console.error("Element with ID 'statusNotif' not found.");
					}
					// Tampilkan popup
					showPopup();
					break; // tampilkan hanya sekali jika ada
				} else if (notif.id_req !== request_id) {
					if (statusNotif) {
						statusNotif.innerHTML = "Menunggu Antrian";
					} else {
						console.error("Element with ID 'statusNotif' not found.");
					}
				}
			}
		});

		function showPopup() {
			const popup = document.getElementById("acceptedPopup");
			popup.style.display = "block";

			// Sembunyikan otomatis setelah 10 detik (opsional)
			setTimeout(() => {
				popup.style.display = "none";
			}, 10000);
		}

		function closePopup() {
			document.getElementById("acceptedPopup").style.display = "none";
		}
	</script>

	<!-- rating dokter -->
	<script>
		var idUsers = "<?php echo $_SESSION['id']; ?>";
		const reqIdRat = document.getElementById("reqIdRat");
		var request_id = reqIdRat ? reqIdRat.value : "";
		const profileUploadUrl = <?= json_encode(base_url('uploads/profile/')); ?>;
		const defaultProfileUrl = <?= json_encode(base_url('assets/doclinc/img/default-profile.png')); ?>;

		function safeProfileImageUrl(path) {
			path = String(path || '').trim();

			if (!path || /[<>"']/.test(path) || /(?:javascript|data)\s*:/i.test(path)) {
				return defaultProfileUrl;
			}

			path = path.replace(/\\/g, '/').replace(/^\/+/, '');

			if (path.indexOf('..') !== -1 || !/^[A-Za-z0-9._/-]+$/.test(path)) {
				return defaultProfileUrl;
			}

			return profileUploadUrl + path.split('/').map(encodeURIComponent).join('/');
		}
		console.log("id user: " + idUsers);
		console.log("id_request: " + request_id);

		// Ambil data dari node 'notif'
		const notifRate = getFirebaseDatabase().ref("rating");

		let alreadyShown = false;

		notifRate.on("value", (snapshot) => {
			if (alreadyShown) return;

			const data = snapshot.val();
			if (!data) return;

			for (let id in data) {
				const notif = data[id];

				if (notif.idUser === idUsers && notif.idReq === request_id) {
					alreadyShown = true; // mencegah tampil ulang
					const idDokter = notif.idDokter;

					$.ajax({
						url: "<?= base_url('home/getDokterRating') ?>",
						type: "POST",
						data: {
							id_dokter: idDokter
						},
						dataType: "json",
						success: function(response) {
							const dataDokter = response[0];

							document.getElementById('gambarDokter').src = safeProfileImageUrl(dataDokter.foto);
							document.getElementById('namaDokter').textContent = dataDokter.nama;
							document.getElementById('dokIds').value = idDokter;

							showPopupRate();
						}
					});

					break;
				}
			}
		});

		function showPopupRate() {
			const popupRatingModal = new bootstrap.Modal(document.getElementById('ratingModal'));
			popupRatingModal.show();
		}
	</script>

	<!-- submit rating -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.getElementById('ratingForm').addEventListener('submit', function(e) {
				e.preventDefault();

				// Lakukan submit rating ke server via AJAX
				const idUser = this.iduser.value;
				const idDokter = this.id_dokter.value;
				const rating = this.rating.value;
				console.log(idUser);
				console.log(idDokter);
				console.log(rating);

				$.ajax({
					url: '<?= base_url('home/submit_rating'); ?>',
					type: 'POST',
					data: {
						id_user: idUser,
						id_dokter: idDokter,
						rating: rating
					},
					dataType: 'json',
					success: function(data) {
						if (data.status === 'success') {

							const deleteRate = getFirebaseDatabase().ref('rating');
							deleteRate.on('value', (snapshot) => {
								const data = snapshot.val();
								if (data) {
									for (const key in data) {
										const item = data[key];
										if (item.idUser === idUser && item.idReq === request_id) {
											deleteRate.child(key).remove();
										}
									}
								}
							});

							Swal.fire({
								title: 'Berhasil',
								text: 'Rating berhasil disimpan.',
								icon: 'success',
								confirmButtonText: 'OK'
							}).then(() => {
								const ratingModal = bootstrap.Modal.getInstance(document.getElementById('ratingModal'));
								ratingModal.hide();
								window.location.href = 'home#riwayat_konsul_selesai';
							});
						} else {
							Swal.fire({
								title: 'Error',
								text: 'Gagal menyimpan rating, coba lagi.',
								icon: 'error',
								confirmButtonText: 'OK'
							});
						}
					},
					error: function(xhr, status, error) {
						console.error('Error:', error);
						Swal.fire({
							title: 'Error',
							text: 'Terjadi kesalahan.',
							icon: 'error',
							confirmButtonText: 'OK'
						});
					}
				});

			});
		});
	</script>

	<!-- mendapatkan url -->
	<script>
		document.addEventListener("DOMContentLoaded", function() {
			const hash = window.location.hash;

			if (hash === "#riwayat" || hash === "#riwayat_konsul_selesai") {
				if (typeof showContent === 'function') {
					showContent('riwayat');
				}

				setTimeout(() => {
					let tabID = (hash === "#riwayat_konsul_selesai") ? "#selesai-tab" : "#proses-tab";
					const tabTrigger = document.querySelector(tabID);
					if (tabTrigger) {
						const tab = new bootstrap.Tab(tabTrigger);
						tab.show();
					}
				}, 300);
			}
		});
	</script>

</body>

</html>
