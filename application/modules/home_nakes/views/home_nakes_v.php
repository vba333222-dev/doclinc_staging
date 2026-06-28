<?php
$google_maps_api_key = $this->config->item('google_maps_api_key') ?: '';
$firebase_enabled = (bool) $this->config->item('firebase_enabled');
$legacy_superapp_url = $this->config->item('legacy_superapp_url') ?: '#';
$map_provider = $this->config->item('map_provider') ?: 'none';

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

$nakes_name = strtoupper((string) $this->session->userdata('nama'));
$nakes_birthdate = !empty($profile['tgl']) ? $profile['tgl'] : null;
$nakes_age = '-';
if (!empty($nakes_birthdate)) {
	try {
		$birthDate = new DateTime($nakes_birthdate);
		$today = new DateTime();
		$nakes_age = $today->diff($birthDate)->y . ' tahun';
	} catch (Exception $e) {
		$nakes_age = '-';
	}
}
$nakes_pending_count = isset($data_request_new) ? (int) $data_request_new->num_rows() : 0;
$nakes_active_count = isset($data_request_accept) ? (int) $data_request_accept->num_rows() : 0;
$nakes_completed_count = isset($data_request_completed) ? (int) $data_request_completed->num_rows() : 0;
$nakes_today_total = $nakes_pending_count + $nakes_active_count;
?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Doc Link (BETA) - Home</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta3/css/bootstrap-select.min.css" integrity="sha512-g2SduJKxa4Lbn3GW+Q7rNz+pKP9AWMR++Ta8fgwsZRCUsawjPvF/BxSMkGS61VsR9yinGoEgrHPGPn2mrj8+4w==" crossorigin="anonymous" referrerpolicy="no-referrer">
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

		.history-empty-text {
			color: #6c757d;
			font-size: 13px;
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

		.chat-box {
			flex: 1;
			overflow-y: auto;
			padding: 15px;
			border: 1px solid #ddd;
			border-radius: 5px;
			background-color: #f9f9f9;
		}

		#chat-box {
			display: flex;
			flex-direction: column;
			gap: 8px;
			height: 750px;
			overflow-y: auto;
			border: 1px solid #ddd;
			border-radius: 5px;
			padding: 10px;
			background: #f9f9f9;
		}

		/* Base message styles */
		.message {
			display: flex;
			flex-direction: column;
			align-items: flex-start;
			position: relative;
			padding: 8px 12px;
			margin: 5px 0;
			border-radius: 8px;
			font-size: 14px;
			line-height: 1.5;
			max-width: 65%;
			word-wrap: break-word;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
		}

		/* Right (current user) message styling */
		.message-right {
			background: #dcf8c6;
			/* WhatsApp green bubble */
			align-self: flex-end;
			text-align: left;
			border-top-right-radius: 0px;
		}

		/* Left (other user) message styling */
		.message-left {
			background: #ffffff;
			/* White bubble */
			align-self: flex-start;
			text-align: left;
			border-top-left-radius: 0px;
		}

		/* Text and timestamp container */
		.message-content {
			display: flex;
			justify-content: space-between;
			align-items: flex-end;
		}

		/* Message text */
		.message-text {
			flex-grow: 1;
			font-size: 14px;
			color: #000000;
			word-break: break-word;
		}

		/* Timestamp styling */
		.timestamp {
			margin-left: 10px;
			font-size: 10px;
			color: #808080;
			white-space: nowrap;
		}

		.message-input {
			display: flex;
			align-items: center;
			border-top: 1px solid #ddd;
			padding: 10px;
			background-color: #fff;
		}

		.message-input input {
			flex: 1;
			border: none;
			border-radius: 5px;
			padding: 10px;
		}

		.message-input button {
			background-color: #09ad74;
			color: white;
			border: none;
			border-radius: 5px;
			padding: 10px;
			cursor: pointer;
		}
	</style>
</head>

<body class="bg-light dl-dashboard-body dl-nakes-dashboard">
	<div id="preloader">
		<div class="text-center">
			<img class="animate__animated animate__bounceIn mb-3" src="<?= base_url(); ?>assets/images/doklincwhite.png" alt="" height="50px">
			<p class="mb-0">
			<div class="spinner-border spinner-border-sm text-light" role="status">
				<span class="visually-hidden">Loading...</span>
			</div> Memuat... </p>
		</div>
	</div>
	<div class="content-wrapper dl-shell" id="content-wrapper">
		<div class="contents">
			<div class="hero bg-success p-3 overflow-hidden dl-appbar dl-nakes-appbar">
				<a href="<?= html_escape($legacy_superapp_url); ?>" class="dl-back-link" aria-label="Kembali">
					<i class="fas fa-chevron-left icon"></i>
				</a>
				<a class="notify" href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNotif" aria-controls="offcanvasNotif">
					<i class="bi bi-bell-fill fs-4"></i>
					<!-- kalo ada notif fetch datanya dari sini ya, bukan dari dalem elemen span nya -->
					<span class="notify-number" id="badgeNotif">9+</span>
					<!-- sampe sini -->
				</a>
				<div class="text-white me-4 dl-location-row">
					<p class="mb-2" style="line-height:1;">
						<i class="fas fa-map-marker-alt me-2"></i><span class="small" id="address_label"></span>
					</p>
					<input type="hidden" id="id_user" value="<?= $this->session->userdata('id'); ?>">
					<input type="hidden" id="address" placeholder="Latitude">
					<input type="hidden" id="latitude" placeholder="Latitude">
					<input type="hidden" id="longitude" placeholder="Longitude">
					<div id="map"></div>
				</div>
				<div class="d-flex animate__animated animate__fadeInUp animate__faster dl-profile-row">
					<div class="flex-shrink-0">
						<img class="rounded-4 shadow dl-profile-photo" src="<?= doclinc_safe_profile_image_src($profile['foto'] ?? ''); ?>" width="100px" height="100px" alt="Foto Profil">
					</div>
					<div class="flex-grow-1 ms-3 text-white dl-profile-copy">
						<small>Selamat Pagi,</small>
						<h3 class="mb-0"><?= html_escape($nakes_name); ?></h3>
						<p class="mb-0"><?= html_escape($nakes_age); ?></p>
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
					<div class="dl-nakes-segment">
						<a href="#req_konsul" class="active" onclick="showContent('req_konsul')">Permintaan</a>
						<a href="#riwayat_konsul" onclick="showContent('riwayat_konsul')">Aktif</a>
					</div>
					<div class="dl-nakes-stats">
						<div class="dl-nakes-stat-card">
							<span>Total Hari Ini</span>
							<strong><?= html_escape(str_pad((string) $nakes_today_total, 2, '0', STR_PAD_LEFT)); ?></strong>
						</div>
						<div class="dl-nakes-stat-card">
							<span>Menunggu</span>
							<strong><?= html_escape(str_pad((string) $nakes_pending_count, 2, '0', STR_PAD_LEFT)); ?></strong>
						</div>
					</div>
					<div class="dl-section-header">
						<h2 class="dl-section-title">Ringkasan Layanan</h2>
						<a href="#riwayat_konsul" class="dl-nakes-link" onclick="showContent('riwayat_konsul')">Lihat Riwayat</a>
					</div>
					<div class="dl-feature-grid">
						<a href="#req_konsul" class="dl-feature-card" onclick="showContent('req_konsul')">
							<div class="icon-wrapper">
								<i class="fas fa-user-md"></i>
								<span class="filler"></span>
							</div>
							<span class="small">Permintaan Konsultasi</span>
						</a>
						<a href="#riwayat_konsul" class="dl-feature-card" onclick="showContent('riwayat_konsul')">
							<div class="icon-wrapper">
								<i class="fas fa-history"></i>
								<span class="filler"></span>
							</div>
							<span class="small">Riwayat Konsultasi</span>
						</a>
						<a href="#" class="dl-feature-card" onclick="showContent('profile')">
							<div class="icon-wrapper">
								<i class="fas fa-user"></i>
								<span class="filler"></span>
							</div>
							<span class="small">Profil Nakes</span>
						</a>
						<div class="dl-feature-card dl-nakes-summary-card">
							<div class="icon-wrapper">
								<i class="fas fa-check-circle"></i>
								<span class="filler"></span>
							</div>
							<span class="small"><?= html_escape((string) $nakes_completed_count); ?> selesai</span>
						</div>
					</div>
				</div>
				<div id="req_konsul" class="content animate__animated animate__fadeInUp animate__faster">
					<div class="dl-section-header dl-nakes-page-header">
						<h2 class="dl-section-title">Permintaan Masuk</h2>
						<span class="dl-nakes-link"><?= html_escape((string) $nakes_pending_count); ?> menunggu</span>
					</div>
					<div class="dl-nakes-request-list">
						<?php
						$i = 1;
						if ($data_request_new->num_rows() < 1) {
						?>
							<div class="dl-empty-state text-center">
								<img src="<?= html_escape(base_url('assets/images/not found.svg')); ?>" width="150" alt="Tidak ada data">
								<p class="mb-0 mt-3">Belum ada permintaan konsultasi baru.</p>
							</div>
							<?php
						} else {
							$CI = &get_instance();
							$CI->load->library('encryption');
							foreach ($data_request_new->result() as $y => $x) {
								$keluhan = $CI->encryption->decrypt(base64_decode($x->request_description));
								$riwayat = $CI->encryption->decrypt(base64_decode($x->riwayat));
							?>
								<div class="dl-nakes-request-item">
									<div class="card shadow request-card dl-nakes-request-card" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
										<div class="card-header d-flex align-items-start gap-3">
											<div class="dl-nakes-avatar-icon">
												<i class="fas fa-user"></i>
											</div>
											<div class="flex-grow-1">
												<strong><?= html_escape(strtoupper((string) $x->nama)); ?></strong>
												<span>ID: #REQ-<?= html_escape((int) $x->request_id); ?></span>
											</div>
											<span class="dl-badge animate__animated animate__flash animate__infinite animate__slower">Baru</span>
										</div>
										<div id="cekStatus"></div>
										<div class="card-body">
											<div class="dl-nakes-complaint-box">
												<span>Keluhan</span>
												<p><?= doclinc_history_safe_lines($keluhan); ?></p>
											</div>
											<div class="dl-nakes-meta-list">
												<div><i class="fas fa-file fa-fw"></i><span>Riwayat</span><strong><?= doclinc_history_safe_text($riwayat); ?></strong></div>
												<div><i class="fas fa-map-marker-alt fa-fw"></i><span>Alamat</span><strong><?= doclinc_history_safe_text($x->location); ?></strong></div>
												<div><i class="fas fa-motorcycle fa-fw"></i><span>Jarak</span><strong class="distance">Menghitung...</strong></div>
												<div><i class="far fa-clock fa-fw"></i><span>Estimasi</span><strong class="duration">Menghitung...</strong></div>
											</div>
											<!-- <a class="btn btn-info btn-sm">Lihat Foto</a> <a class="btn btn-info btn-sm">Lihat Video</a> -->
											<?php if (!empty($x->photos)) : ?>
												<button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#fotoModal_<?php echo $x->user_id; ?>">Lihat Foto</button>

											<?php endif; ?>

											<?php if (!empty($x->video)) : ?>
												<button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#videoModal_<?php echo $x->user_id; ?>">Lihat Video</button>
											<?php endif; ?>
											<div class="dl-nakes-visit-toggle">
												<i class="far fa-question-circle fa-fw"></i> Konfirmasikan kunjungan Anda:
											</div>
											<div class="form-check form-switch mb-0">
												<label class="form-check-label" for="kunjung" id="labelKunjung">Tidak</label>
												<input class="form-check-input" type="checkbox" role="switch" id="kunjung" name="kunjung">
											</div>
										</div>
										<div class="card-footer">
											<div class="row g-2 dl-nakes-actions">
												<div class="col d-grid">
													<button type="button" class="btn btn-outline-success shadow-sm rounded-pill lihat-map" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMapTujuan" aria-controls="offcanvasMapTujuan" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
														<i class="fas fa-map-marker-alt me-2"></i> Lihat Lokasi
													</button>
												</div>
												<div class="col d-grid">
													<button type="button" class="btn btn-outline-danger shadow-sm rounded-pill cancel-nakes-request" data-request-id="<?= html_escape((int) $x->request_id); ?>">
														<i class="fas fa-times-circle me-2"></i> Tolak
													</button>
												</div>
												<div class="col d-grid">
													<input type="hidden" id="id_request<?php echo $i; ?>" value="<?= html_escape((int) $x->request_id); ?>">
													<button type="button" class="btn btn-success shadow-sm rounded-pill start-chat"
														id="terimaKonsul<?php echo $i; ?>"
														data-reqid="<?= html_escape((int) $x->request_id); ?>"
														data-userid="<?= html_escape((int) $x->user_id); ?>"
														data-dokterid="<?= html_escape((int) $x->dokter_id); ?>"
														data-namapasien="<?= html_escape($x->nama); ?>"
														data-riwayat="<?= html_escape($riwayat); ?>"
														data-keluhan="<?= html_escape($keluhan); ?>">
														<i class="fa fa-comment-medical me-2"></i> Terima Konsultasi
													</button>
												</div>
											</div>
										</div>
									</div>
								</div>
								<!-- sampai sini -->
						<?php
								$i++;
							}
						}
						?>
						<input type="hidden" value="<?php echo $i - 1; ?>" id="jumlah_request">
					</div>
				</div>
				<div id="riwayat_konsul" class="content animate__animated animate__fadeInUp animate__faster">
					<div class="dl-section-header dl-nakes-page-header">
						<h2 class="dl-section-title">Riwayat Konsultasi</h2>
						<span class="dl-nakes-link"><?= html_escape((string) $nakes_active_count); ?> aktif</span>
					</div>
					<ul class="nav nav-tabs nav-justified mb-3 dl-tabs" id="myTab" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" id="proses-tab" data-bs-toggle="tab" data-bs-target="#proses-tab-pane" type="button" role="tab" aria-controls="proses-tab-pane" aria-selected="false">Saat ini</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="selesai-tab" data-bs-toggle="tab" data-bs-target="#selesai-tab-pane" type="button" role="tab" aria-controls="selesai-tab-pane" aria-selected="false">Riwayat</button>
						</li>
					</ul>
					<div class="tab-content" id="myTabContent">
						<div class="tab-pane fade show active" id="proses-tab-pane" role="tabpanel" aria-labelledby="proses-tab" tabindex="0">
							<?php
							$CI = &get_instance();
							$CI->load->library('encryption');
							foreach ($data_request_new->result() as $x) {
								$keluhan = $CI->encryption->decrypt(base64_decode($x->request_description));
								$riwayat = $CI->encryption->decrypt(base64_decode($x->riwayat));
							?>
								<div class="card shadow mb-2 dl-nakes-request-card" data-request-id="<?= (int) $x->request_id; ?>">
									<div class="card-header d-flex align-items-start gap-3">
										<div class="dl-nakes-avatar-icon">
											<i class="fas fa-user"></i>
										</div>
										<div class="flex-grow-1">
											<strong><?= html_escape(strtoupper((string) $x->nama)); ?></strong>
											<span><?= html_escape(date('d-m-Y', strtotime($x->created_at))); ?> · #REQ-<?= html_escape((int) $x->request_id); ?></span>
										</div>
										<span class="dl-badge animate__animated animate__flash animate__infinite animate__slower" id="status-konsul">Baru</span>
									</div>
									<div class="card-body">
										<div class="dl-nakes-complaint-box">
											<span>Keluhan</span>
											<p><?= doclinc_history_safe_lines($keluhan); ?></p>
										</div>
										<div class="dl-nakes-meta-list">
											<div><i class="far fa-clock fa-fw"></i><span>Estimasi</span><strong><?= doclinc_history_safe_text($x->duration); ?></strong></div>
											<div><i class="fas fa-motorcycle fa-fw"></i><span>Jarak</span><strong><?= doclinc_history_safe_text($x->distance); ?></strong></div>
										</div>
									</div>
									<div class="card-footer d-flex">
										<button type="button" class="btn btn-outline-success shadow-sm rounded-pill lihat-map" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMapTujuan" aria-controls="offcanvasMapTujuan" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
											<i class="fas fa-map-marker-alt me-2"></i> Lihat Lokasi
										</button>
										<button type="button" class="btn btn-outline-danger shadow-sm rounded-pill ms-2 cancel-nakes-request" data-request-id="<?= html_escape((int) $x->request_id); ?>">
											<i class="fas fa-times-circle me-2"></i> Tolak
										</button>
										<!-- <button class="btn btn-success shadow-sm rounded-pill ms-auto" id="tombolSaran">Berikan Saran</button> -->
									</div>
								</div>
							<?php
							}
							$i = 1;

							$CI = &get_instance();
							$CI->load->library('encryption');
							foreach ($data_request_accept->result() as $x) {
								$keluhan = $CI->encryption->decrypt(base64_decode($x->request_description));
								$riwayat = $CI->encryption->decrypt(base64_decode($x->riwayat));
								$visit_status = isset($x->visit_status) ? doclinc_normalize_visit_status($x->visit_status) : '';
								$visit_status = $visit_status !== '' ? $visit_status : 'not_started';
								$visit_next_status = array(
									'not_started' => 'en_route',
									'en_route' => 'arrived',
									'arrived' => 'in_service',
									'in_service' => 'completed',
									'completed' => '',
								);
							?>
								<div class="card shadow mb-2 dl-nakes-request-card" data-request-id="<?= (int) $x->request_id; ?>">
									<div class="card-header d-flex align-items-start gap-3">
										<div class="dl-nakes-avatar-icon">
											<i class="fas fa-user-check"></i>
										</div>
										<div class="flex-grow-1">
											<strong><?= html_escape(strtoupper((string) $x->nama)); ?></strong>
											<span><?= html_escape(date('d-m-Y', strtotime($x->created_at))); ?> · #REQ-<?= html_escape((int) $x->request_id); ?></span>
										</div>
										<span class="dl-badge animate__animated animate__flash animate__infinite animate__slower" id="status-konsul">Accepted</span>
									</div>
									<div class="card-body">
										<div class="dl-nakes-complaint-box">
											<span>Keluhan</span>
											<p><?= doclinc_history_safe_lines($keluhan); ?></p>
										</div>
										<input type="text" id="lat-des-acc" value="<?= html_escape($x->lattitude); ?>" hidden>
										<input type="text" id="lng-des-acc" value="<?= html_escape($x->longitude); ?>" hidden>
										<div class="dl-nakes-meta-list">
											<div><i class="fas fa-motorcycle fa-fw"></i><span>Jarak</span><strong id="distances"></strong></div>
											<div><i class="far fa-clock fa-fw"></i><span>Estimasi</span><strong id="durations"></strong></div>
										</div>
										<div class="mt-3 visit-workflow-control" data-visit-workflow="<?= html_escape((int) $x->request_id); ?>" data-current-status="<?= html_escape($visit_status); ?>">
											<div class="small text-muted mb-2">Status kunjungan: <span class="fw-bold visit-workflow-label"><?= html_escape(doclinc_visit_status_label($visit_status)); ?></span></div>
											<div class="d-flex flex-wrap gap-2">
												<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="en_route" <?= $visit_next_status[$visit_status] === 'en_route' ? '' : 'disabled'; ?>>Mulai Perjalanan</button>
												<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="arrived" <?= $visit_next_status[$visit_status] === 'arrived' ? '' : 'disabled'; ?>>Tiba di Lokasi</button>
												<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="in_service" <?= $visit_next_status[$visit_status] === 'in_service' ? '' : 'disabled'; ?>>Mulai Penanganan</button>
												<button type="button" class="btn btn-outline-primary btn-sm rounded-pill visit-status-update" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-status="completed" <?= $visit_next_status[$visit_status] === 'completed' ? '' : 'disabled'; ?>>Kunjungan Selesai</button>
											</div>
											<div class="small mt-2 visit-workflow-message" data-visit-workflow-message="<?= html_escape((int) $x->request_id); ?>"></div>
										</div>
									</div>
									<div class="card-footer d-flex flex-wrap gap-2 dl-nakes-actions">
										<button type="button" class="btn btn-outline-success shadow-sm rounded-pill lihat-map" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMapTujuan" aria-controls="offcanvasMapTujuan" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">
											<i class="fas fa-map-marker-alt me-2"></i> Lihat Lokasi
										</button>
										<a href="<?= html_escape(base_url('konsultasi_nakes/konsultasi/' . (int) $x->request_id) . '?kriteria=1'); ?>" class="btn btn-success shadow-sm rounded-pill">
											<i class="fas fa-notes-medical me-2"></i> Lanjut Konsultasi
										</a>
										<a href="<?= html_escape(base_url('chat?request_id=' . (int) $x->request_id)); ?>" class="btn btn-outline-success shadow-sm rounded-pill">
											<i class="fas fa-comments me-2"></i> Chat Konsultasi
										</a>
										<button type="button" class="btn btn-outline-primary shadow-sm rounded-pill start-nakes-visit-tracking" data-request-id="<?= html_escape((int) $x->request_id); ?>">
											<i class="fas fa-location-arrow me-2"></i> Aktifkan Lokasi Visit
										</button>
										<button type="button" class="btn btn-outline-danger shadow-sm rounded-pill cancel-nakes-request" data-request-id="<?= html_escape((int) $x->request_id); ?>">
											<i class="fas fa-times-circle me-2"></i> Batalkan
										</button>
										<span class="small text-muted w-100 visit-tracking-status" data-tracking-status="<?= html_escape((int) $x->request_id); ?>"></span>
									</div>
								</div>
							<?php
								$i++;
							}
							?>
							<input type="hidden" id="jumlah_accepted" value="<?php echo $i - 1; ?>">
						</div>
						<div class="tab-pane fade" id="selesai-tab-pane" role="tabpanel" aria-labelledby="selesai-tab" tabindex="0">
							<?php
							$CI = &get_instance();
							$CI->load->library('encryption');
							if ($data_request_completed->num_rows() < 1) {
							?>
								<div class="text-center py-4">
									<img src="<?= html_escape(base_url('assets/images/not found.svg')); ?>" width="180" alt="Tidak ada data">
									<p class="mb-0 mt-3 text-muted">Belum ada riwayat konsultasi selesai.</p>
								</div>
							<?php
							}
							foreach ($data_request_completed->result() as $x) {
								try {
									$keluhan = $CI->encryption->decrypt(base64_decode($x->request_description));
								} catch (Exception $e) {
									$keluhan = 'Keluhan tersimpan';
								}
								try {
									$riwayat = $CI->encryption->decrypt(base64_decode($x->riwayat));
								} catch (Exception $e) {
									$riwayat = '';
								}
								$diagnosa = !empty($x->diagnosa) ? $x->diagnosa : (!empty($x->diagnosis) ? $x->diagnosis : '-');
								$saran = !empty($x->saran) ? $x->saran : (!empty($x->recommendations) ? $x->recommendations : '-');
								$treatment = !empty($x->treatment) ? $x->treatment : '';
								$puskesmas = !empty($x->assigned_puskesmas_name) ? $x->assigned_puskesmas_name : '';
								$tanggal_selesai = !empty($x->created_at) ? date('d-m-Y', strtotime($x->created_at)) : '-';
							?>
								<div class="card shadow mb-3 history-result-card" data-request-id="<?= (int) $x->request_id; ?>">
									<div class="card-header d-flex align-items-start gap-3">
										<div class="flex-grow-1">
											<div class="history-request-id">Request #<?= html_escape((int) $x->request_id); ?></div>
											<div class="history-meta">
												<?= doclinc_history_safe_text($tanggal_selesai); ?><br>
												Pasien: <?= doclinc_history_safe_text(strtoupper((string) $x->nama)); ?>
												<?php if ($puskesmas !== '') : ?>
													<br><?= doclinc_history_safe_text($puskesmas); ?>
												<?php endif; ?>
											</div>
										</div>
										<span class="history-status-badge">Completed</span>
									</div>
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
												<span class="history-result-label">Terapi / Tindakan</span>
												<?php if ($treatment !== '') : ?>
													<div class="history-result-value"><?= doclinc_history_safe_lines($treatment); ?></div>
												<?php else : ?>
													<p class="history-empty-text mb-0">Belum ada terapi/tindakan yang tercatat.</p>
												<?php endif; ?>
											</div>
											<div class="history-result-row">
												<span class="history-result-label">Rekomendasi / Saran</span>
												<div class="history-result-value"><?= doclinc_history_safe_lines($saran); ?></div>
											</div>
										</div>
									</div>
									<div class="card-footer">
										<a href="<?= html_escape(base_url('chat?request_id=' . (int) $x->request_id)); ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
											<i class="fas fa-comments me-1"></i> Riwayat Chat
										</a>
									</div>
								</div>
							<?php } ?>
						</div>
					</div>
				</div>
				<div id="profile" class="content animate__animated animate__fadeInUp animate__faster">
					<div class="card shadow border-0 rounded-4">
						<div class="card-body">
							<h4 class="card-title text-center text-success mb-4">Profil Pengguna</h4>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="nama_lengkap" value="<?= $this->session->userdata('nama'); ?>" placeholder="Nama Lengkap" readonly>
								<label for="nama_lengkap"><i class="bi bi-person-fill me-2"></i>Nama Lengkap</label>
							</div>
							<div class="form-floating mb-3">
								<input type="date" class="form-control shadow-sm border-success" id="tgl" value="<?= $profile['tgl'] ?>" placeholder="Tanggal Lahir" readonly>
								<label for="tgl"><i class="bi bi-calendar-event-fill me-2"></i>Tanggal Lahir</label>
							</div>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="jk" value="<?= $profile['gender'] ?>" placeholder="Jenis Kelamin" readonly>
								<label for="jk"><i class="bi bi-gender-ambiguous me-2"></i>Jenis Kelamin</label>
							</div>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="no_hp" value="<?= $profile['no_hp'] ?>" placeholder="Nomor HP" readonly>
								<label for="no_hp"><i class="bi bi-telephone-fill me-2"></i>Nomor HP</label>
							</div>
							<div class="form-floating mb-3">
								<textarea class="form-control shadow-sm border-success" placeholder="Alamat" id="alamat" readonly style="height: 100px"><?= $profile['alamat'] ?></textarea>
								<label for="alamat"><i class="bi bi-geo-alt-fill me-2"></i>Alamat</label>
							</div>
							<div class="d-grid gap-2">
								<button type="button" class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#modalProfil">
									<i class="bi bi-pencil-fill me-2"></i>Edit Profil
								</button>
								<button type="button" class="btn btn-danger shadow-sm" id="btn-logout">
									<i class="bi bi-box-arrow-right me-2"></i>Logout
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="nav-bottom-wrapper shadow-lg rounded-top-4 dl-bottom-nav" id="nav-bottom-wrapper">
		<div class="container-fluid px-0">
			<div class="row g-0 text-center p-2 menu animate__animated animate__slideInUp animate__faster">
				<a href="#" id="beranda-tab" class="col menu-item active" onclick="showContent('beranda')" aria-label="Home">
					<i class="fas fa-home fs-4"></i>
					<span class="d-block small">Beranda</span>
				</a>
				<a href="#req_konsul" id="req_konsul-tab" class="col menu-item" onclick="showContent('req_konsul')" aria-label="Requests">
					<i class="fas fa-user-md fs-4"></i>
					<span class="d-block small">Permintaan</span>
				</a>
				<a href="#riwayat_konsul" id="riwayat_konsul-tab" class="col menu-item" onclick="showContent('riwayat_konsul')" aria-label="History">
					<i class="fas fa-history fs-4"></i>
					<span class="d-block small">Riwayat</span>
				</a>
				<a href="#" id="profile-tab" class="col menu-item" onclick="showContent('profile')" aria-label="Profile">
					<i class="fas fa-user fs-4"></i>
					<span class="d-block small">Profil</span>
				</a>
			</div>
		</div>
	</div>
	<div class="modal fade" id="modalProfil" tabindex="-1" aria-labelledby="modalProfilLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content border-0 shadow-lg rounded-4">
				<div class="modal-header bg-success text-white">
					<h5 class="modal-title" id="modalProfilLabel"><i class="bi bi-pencil-fill me-2"></i>Edit Profil</h5>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form id="formEditProfile" enctype="multipart/form-data">
					<div class="modal-body bg-light">
						<div class="text-center mb-3">
							<label for="uploadFoto" class="d-inline-block position-relative" style="cursor: pointer;">
								<img id="previewFoto" src="<?= doclinc_safe_profile_image_src($profile['foto'] ?? ''); ?>" alt="Foto Profil" class="rounded-circle border border-success shadow-sm" style="width: 100px; height: 100px; object-fit: cover;">
								<input type="file" id="uploadFoto" name="foto" accept="image/*" class="d-none" onchange="previewImage(event)">
							</label>
							<small class="text-muted d-block mt-2">Klik untuk mengubah foto</small>
						</div>
						<div class="form-floating mb-3">
							<input type="text" class="form-control shadow-sm border-success" id="nama_lengkap_edit" name="nama_lengkap" value="<?= $this->session->userdata('nama'); ?>" placeholder="Nama Lengkap">
							<label for="nama_lengkap_edit"><i class="bi bi-person-fill me-2"></i>Nama Lengkap</label>
						</div>
						<div class="form-floating mb-3">
							<input type="date" class="form-control shadow-sm border-success" id="tgl_edit" name="tgl_lahir" value="<?= $profile['tgl'] ?>" placeholder="Tanggal Lahir">
							<label for="tgl_edit"><i class="bi bi-calendar-event-fill me-2"></i>Tanggal Lahir</label>
						</div>
						<div class="form-floating mb-3">
							<select class="form-select shadow-sm border-success" name="jk" id="jk_edit">
								<option value="Laki-laki" <?= $this->session->userdata('jk') == 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
								<option value="Perempuan" <?= $this->session->userdata('jk') == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
							</select>
							<label for="jk_edit"><i class="bi bi-gender-ambiguous me-2"></i>Jenis Kelamin</label>
						</div>
						<div class="form-floating mb-3">
							<input type="text" class="form-control shadow-sm border-success" id="no_hp_edit" name="no_hp" value="<?= $profile['no_hp'] ?>" placeholder="Nomor HP">
							<label for="no_hp_edit"><i class="bi bi-telephone-fill me-2"></i>Nomor HP</label>
						</div>
						<div class="form-floating mb-3">
							<textarea class="form-control shadow-sm border-success" placeholder="Alamat" name="alamat" id="alamat_edit" style="height: 100px"><?= $profile['alamat'] ?></textarea>
							<label for="alamat_edit"><i class="bi bi-geo-alt-fill me-2"></i>Alamat</label>
						</div>
					</div>
					<div class="modal-footer bg-light border-0">
						<button type="button" class="btn btn-secondary shadow-sm" data-bs-dismiss="modal"><i class="bi bi-x-circle me-2"></i>Close</button>
						<button type="submit" class="btn btn-success shadow-sm"><i class="bi bi-check-circle me-2"></i>Save Changes</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="offcanvas offcanvas-top" tabindex="-1" id="offcanvasNotif" aria-labelledby="offcanvasNotifLabel">
		<div class="offcanvas-header">
			<i class="bi bi-bell-fill text-success"></i>
			<p class="offcanvas-title mx-2" id="offcanvasNotifLabel">Pusat Notifikasi</p>
			<span class="badge text-bg-danger" id="badgeNotifs"></span>
			<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
		</div>
		<div class="offcanvas-body">
			<div id="notificationList" class="notification-list"></div>
		</div>
	</div>
	<div class="offcanvas offcanvas-top" style="height: 100vh;" tabindex="-1" id="offcanvasMapTujuan" aria-labelledby="offcanvasMapTujuanLabel">
		<div class="offcanvas-header bg-success text-white">
			<h5 class="offcanvas-title d-flex align-items-center" id="offcanvasMapTujuanLabel">
				<i class="bi bi-geo-alt-fill me-2 fs-4"></i>Rute Anda ke Pasien
			</h5>
			<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
		</div>
		<div class="offcanvas-body p-0">
			<div id="maps" class="w-100 h-100 position-relative">
				<div class="position-absolute top-0 start-50 translate-middle-x mt-3 bg-white shadow rounded-pill px-4 py-2 d-flex align-items-center" style="z-index: 1000;">
					<i class="bi bi-pin-map-fill text-success me-2 fs-5"></i>
					<span class="text-muted small">Menampilkan rute ke lokasi pasien...</span>
				</div>
			</div>
		</div>
	</div>
	</div>
	</div>

	<!-- chatting dokter dengan pasien -->
	<div class="modal fade" id="chat" tabindex="-1" aria-labelledby="modalProfilLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h1 class="modal-title fs-5" id="modalProfilLabel">Chat with Patient</h1>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>

				<div class="modal-body">
					<div class="form-floating mb-2">
						<input type="hidden" class="form-control shadow border-success" id="username" value="<?= $this->session->userdata('nama'); ?>" placeholder="Nama Lengkap">
						<input type="hidden" id="modal-pasien">
					</div>
					<div class="chat-box" id="chat-box">
						<!-- Pesan akan ditampilkan di sini -->
					</div>
					<div class="message-input">
						<input type="text" id="message" placeholder="Tulis pesan...">
						<button onclick="sendMessage()">Kirim</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!-- end chatting dokter dengan pasien -->

	<?php foreach ($data_request_new->result() as $y => $x) {
		$photo_file = isset($x->photos) ? trim((string) $x->photos) : '';
		$video_file = isset($x->video) ? trim((string) $x->video) : '';
	?>
		<?php if ($photo_file !== '') : ?>
			<!-- Modal Foto -->
			<div class="modal fade" id="fotoModal_<?php echo $x->user_id; ?>" tabindex="-1" aria-labelledby="fotoModalLabel_<?php echo $x->user_id; ?>" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered modal-lg">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title" id="fotoModalLabel_<?php echo $x->user_id; ?>">Preview Foto</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body text-center">
							<img src="<?php echo html_escape(base_url('uploads/' . rawurlencode($photo_file))); ?>" alt="Foto" class="img-fluid rounded border">
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ($video_file !== '') : ?>
			<!-- Modal Video -->
			<div class="modal fade" id="videoModal_<?php echo $x->user_id; ?>" tabindex="-1" aria-labelledby="videoModalLabel_<?php echo $x->user_id; ?>" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered modal-lg">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title" id="videoModalLabel_<?php echo $x->user_id; ?>">Preview Video</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body text-center">
							<video controls width="100%" class="rounded border">
								<source src="<?php echo html_escape(base_url('uploads/' . rawurlencode($video_file))); ?>" type="video/mp4">
								Browser Anda tidak mendukung tag video.
							</video>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

	<?php } ?>


	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

	<!-- Javascript unutk webtoapk dan lain-lain -->
	<script>
		const mapProvider = <?= json_encode($map_provider); ?>;
		const firebaseEnabled = <?= json_encode($firebase_enabled); ?>;

		function getFirebaseDatabase() {
			const noopRef = {
				on: function() {},
				once: function() {
					return Promise.resolve({
						exists: function() {
							return false;
						},
						val: function() {
							return null;
						},
						forEach: function() {}
					});
				},
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
				},
				orderByChild: function() {
					return noopRef;
				},
				equalTo: function() {
					return noopRef;
				}
			};

			if (!firebaseEnabled || !window.firebase || !firebase.database) {
				return {
					ref: function() {
						return noopRef;
					},
					ServerValue: {
						TIMESTAMP: Date.now()
					}
				};
			}

			try {
				return firebase.database();
			} catch (error) {
				return {
					ref: function() {
						return noopRef;
					},
					ServerValue: {
						TIMESTAMP: Date.now()
					}
				};
			}
		}

		const db = getFirebaseDatabase();

		function getFirebaseServerTimestamp() {
			return firebaseEnabled && window.firebase && firebase.database && firebase.database.ServerValue ?
				firebase.database.ServerValue.TIMESTAMP :
				Date.now();
		}

		function hasGoogleMaps() {
			return mapProvider === 'google' && window.google && window.google.maps;
		}

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
			var highlightRequestId = new URLSearchParams(window.location.search).get('highlight_request_id');
			if (highlightRequestId) {
				var cards = document.querySelectorAll('[data-request-id]');
				cards.forEach(function(card) {
					if (card.getAttribute('data-request-id') === highlightRequestId) {
						card.classList.add('border', 'border-success', 'border-2');
						card.scrollIntoView({
							behavior: 'smooth',
							block: 'center'
						});
					}
				});
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
		var jumlah_request = $('#jumlah_request').val();
		for (let i = 1; i <= jumlah_request; i++) {
			// alert(i);
			$('#terimaKonsul' + i).click(function(event) {
				const acceptButton = this;
				var id = $('#id_request' + i).val();
				var id_user = $('#id_user').val();
				var latitude = $('#latitude').val();
				var longitude = $('#longitude').val();

				const reqId = this.dataset.reqid;
				const userId = this.dataset.userid;
				const dokterId = this.dataset.dokterid;
				const namaPasien = this.dataset.namapasien;
				const riwayat = this.dataset.riwayat;
				const keluhan = this.dataset.keluhan;
				const namaDokter = "<?= $this->session->userdata('nama') ?>";

				console.log('request id ' + reqId);
				console.log('user id ' + userId);
				console.log('dokter id ' + dokterId);
				console.log('nama pasien ' + namaPasien);
				console.log('keluhan ' + keluhan);
				console.log('nama dokter ' + namaDokter);

				Swal.fire({
					title: "Anda yakin ingin melanjutkan konsultasi?",
					showCancelButton: true,
					confirmButtonText: "Iya",
					confirmButtonColor: "#09AD74",
					denyButtonText: "Batal"
				}).then((result) => {
					/* Read more about isConfirmed, isDenied below */
					if (result.isConfirmed) {
						$(acceptButton).prop('disabled', true).addClass('disabled');
						// Hanya kirim AJAX jika user menekan "Iya"
						$.ajax({
							url: '<?php echo base_url(); ?>home_nakes/accept_request',
							dataType: 'json',
							type: 'POST',
							data: {
								id: id,
								id_user: id_user,
								latitude: latitude,
								longitude: longitude
							},
							success: function(response) {
								if (typeof response === 'string') {
									try {
										response = JSON.parse(response);
									} catch (e) {}
								}
								if (!(response == 1 || response === true || (response && response.status === 'success'))) {
									const message = response && response.message ? response.message : 'Request gagal diterima';
									Swal.fire("Gagal", message, "error");
									$(acceptButton).prop('disabled', false).removeClass('disabled');
									return;
								}
								const redirectUrl = response && response.redirect_url ? response.redirect_url : `<?= base_url('konsultasi_nakes/konsultasi/'); ?>${reqId}?kriteria=1`;

								const runLegacyAcceptNotification = function() {
									try {
										if (typeof db === 'undefined' || !db || typeof db.ref !== 'function') {
											return;
										}

										const newMessageRef = db.ref("notiffromdoc").push();
										newMessageRef.set({
											id_req: id,
											id_user: id_user,
											status: 'Nakes Menuju Lokasi',
											text: 'Nakes sedang Menyiapkan obat dan Kendaraan',
											timestamp: Date.now()
										});

										const waktuSalam = () => {
											const jam = new Date().getHours();
											if (jam < 11) return "pagi";
											if (jam < 15) return "siang";
											if (jam < 18) return "sore";
											return "malam";
										};

										const salam = waktuSalam();
										const message = `Selamat ${salam} <b>${namaPasien}</b>,<br><br>
					Saya <b>Dr. ${namaDokter}</b>, dokter yang akan menangani Anda berdasarkan permintaan konsultasi sebelumnya.
					<br><br>
					Berdasarkan informasi yang kami terima, Anda mengalami:<br>
					<b>${keluhan}</b>.<br><br>
					<b>Riwayat Penyakit</b> : <br>
					<b>${riwayat}</b>
					<br><br>
					Saya siap membantu Anda dengan penanganan medis yang tepat.<br><br>
					Silakan sampaikan pertanyaan atau keluhan yang Anda rasakan saat ini.`;

										const chatData = {
											receiver: reqId,
											sender: namaDokter,
											text: message,
											timestamp: Date.now()
										};

										const chatNotif = {
											receiver: reqId,
											sender: namaDokter,
											userId: userId,
											text: "Ada pesan masuk dari " + namaDokter,
											timestamp: Date.now()
										};

										db.ref("messages")
											.orderByChild("receiver")
											.equalTo(reqId)
											.once("value", snapshot => {
												let alreadySent = false;

												snapshot.forEach(child => {
													const data = child.val();
													if (data.sender === namaDokter && data.text.includes("Saya <b>Dr.")) {
														alreadySent = true;
													}
												});

												if (!alreadySent) {
													db.ref("messages").push(chatData);
													db.ref("notifications").push(chatNotif);
												}
											}).catch(() => {});
									} catch (error) {}
								};

								runLegacyAcceptNotification();

								Swal.fire({
									title: "Berhasil",
									text: response && response.message ? response.message : "Anda menerima konsultasi",
									icon: "success",
									showConfirmButton: false,
									timer: 800,
									timerProgressBar: true
								});
								window.setTimeout(function() {
									window.location.href = redirectUrl;
								}, 300);
							},
							error: function() {
								Swal.fire("Gagal", "Request gagal diterima", "error");
								$(acceptButton).prop('disabled', false).removeClass('disabled');
							}
						});
					}
				});
			});
		}
		var jumlah_accepted = $('#jumlah_accepted').val();
		for (let i = 1; i <= jumlah_accepted; i++) {
			$('#tombolSaran' + i).click(function(event) {
				Swal.fire({
					title: "Selesai memeriksa?",
					text: "Apakah Anda sudah memeriksa pasien? Berikan saran Anda untuk pasien.",
					input: "text",
					showCancelButton: true,
					confirmButtonText: "Selesai",
					confirmButtonColor: "#09AD74",
					denyButtonText: "Batal"
				}).then((result) => {
					/* Read more about isConfirmed, isDenied below */
					if (result.isConfirmed) {
						Swal.fire({
							title: "Berhasil!",
							text: "Anda telah menyelesaikan pemeriksaan pasien",
							icon: "success",
							showConfirmButton: false,
							timer: 2000,
							timerProgressBar: true
						}).then((result) => {
							if (result.dismiss === Swal.DismissReason.timer) {
								window.location.href = 'home_nakes';
							}
						});
					}
				});
			});
		}

		$(document).on('click', '.cancel-nakes-request', function(event) {
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
				text: 'Permintaan konsultasi akan ditandai dibatalkan.',
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
					url: '<?= base_url('home_nakes/cancel_request'); ?>',
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

	<script>
		(function(window, $) {
			const ns = window.doclincVisitTracking = window.doclincVisitTracking || {};
			const updateVisitLocationUrl = <?= json_encode(base_url('home_nakes/update_visit_location')); ?>;
			const updateVisitStatusUrl = <?= json_encode(base_url('home_nakes/update_visit_status')); ?>;
			const minPostIntervalMs = 10000;
			const watches = ns.watches = ns.watches || {};
			const nextVisitStatuses = {
				not_started: 'en_route',
				en_route: 'arrived',
				arrived: 'in_service',
				in_service: 'completed',
				completed: ''
			};

			function setTrackingStatus(requestId, message, isError) {
				const element = document.querySelector('[data-tracking-status="' + requestId + '"]');
				if (!element) return;
				element.textContent = message || '';
				element.classList.toggle('text-danger', !!isError);
				element.classList.toggle('text-success', !isError && !!message);
			}

			function setVisitWorkflowMessage(requestId, message, isError) {
				const element = document.querySelector('[data-visit-workflow-message="' + requestId + '"]');
				if (!element) return;
				element.textContent = message || '';
				element.classList.toggle('text-danger', !!isError);
				element.classList.toggle('text-success', !isError && !!message);
			}

			function refreshVisitWorkflowControl(requestId, visitStatus, label) {
				const container = document.querySelector('[data-visit-workflow="' + requestId + '"]');
				if (!container) return;
				container.setAttribute('data-current-status', visitStatus);
				const labelElement = container.querySelector('.visit-workflow-label');
				if (labelElement) {
					labelElement.textContent = label || visitStatus;
				}
				const nextStatus = nextVisitStatuses[visitStatus] || '';
				container.querySelectorAll('.visit-status-update').forEach(function(button) {
					button.disabled = button.getAttribute('data-visit-status') !== nextStatus;
				});
			}

			function stopTracking(requestId) {
				const state = watches[requestId];
				if (state && navigator.geolocation && state.watchId !== null) {
					navigator.geolocation.clearWatch(state.watchId);
				}
				delete watches[requestId];
			}

			function postVisitLocation(requestId, position) {
				const state = watches[requestId];
				if (!state) return;

				const now = Date.now();
				if (now - state.lastSentAt < minPostIntervalMs) return;
				state.lastSentAt = now;

				$.ajax({
					url: updateVisitLocationUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						request_id: requestId,
						latitude: position.coords.latitude,
						longitude: position.coords.longitude,
						accuracy: position.coords.accuracy || ''
					},
					success: function(response) {
						if (typeof response === 'string') {
							try {
								response = JSON.parse(response);
							} catch (error) {}
						}

						if (response && response.status === 'success') {
							setTrackingStatus(requestId, 'Lokasi berhasil diperbarui');
							return;
						}

						setTrackingStatus(requestId, response && response.message ? response.message : 'Gagal mengirim lokasi', true);
					},
					error: function(xhr) {
						const response = xhr.responseJSON || {};
						setTrackingStatus(requestId, response.message || 'Gagal mengirim lokasi', true);
						if (xhr.status === 400 || xhr.status === 403 || xhr.status === 404 || xhr.status === 405) {
							stopTracking(requestId);
						}
					}
				});
			}

			ns.startNakesVisitTracking = function(requestId) {
				if (!navigator.geolocation) {
					setTrackingStatus(requestId, 'Izin lokasi ditolak', true);
					return;
				}

				if (watches[requestId]) {
					setTrackingStatus(requestId, 'Tracking lokasi aktif');
					return;
				}

				setTrackingStatus(requestId, 'Tracking lokasi aktif');
				const watchId = navigator.geolocation.watchPosition(
					function(position) {
						postVisitLocation(requestId, position);
					},
					function(error) {
						const message = error && error.code === 1 ? 'Izin lokasi ditolak' : 'Gagal mengirim lokasi';
						setTrackingStatus(requestId, message, true);
						stopTracking(requestId);
					}, {
						enableHighAccuracy: true,
						maximumAge: 30000,
						timeout: 10000
					}
				);

				watches[requestId] = {
					watchId: watchId,
					lastSentAt: 0
				};
			};

			$(document).on('click', '.start-nakes-visit-tracking', function(event) {
				event.preventDefault();
				const requestId = $(this).data('request-id');
				if (!requestId) return;
				ns.startNakesVisitTracking(requestId);
			});

			$(document).on('click', '.visit-status-update', function(event) {
				event.preventDefault();
				const button = $(this);
				const requestId = button.data('request-id');
				const visitStatus = button.data('visit-status');
				if (!requestId || !visitStatus) return;

				button.prop('disabled', true);
				setVisitWorkflowMessage(requestId, 'Memperbarui status kunjungan...');
				$.ajax({
					url: updateVisitStatusUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						request_id: requestId,
						visit_status: visitStatus
					},
					success: function(response) {
						if (typeof response === 'string') {
							try {
								response = JSON.parse(response);
							} catch (error) {}
						}

						if (response && response.status === 'success') {
							refreshVisitWorkflowControl(requestId, response.visit_status, response.visit_status_label);
							setVisitWorkflowMessage(requestId, response.message || 'Status kunjungan diperbarui');
							return;
						}

						button.prop('disabled', false);
						setVisitWorkflowMessage(requestId, response && response.message ? response.message : 'Status kunjungan tidak dapat diperbarui', true);
					},
					error: function(xhr) {
						const response = xhr.responseJSON || {};
						button.prop('disabled', false);
						setVisitWorkflowMessage(requestId, response.message || 'Status kunjungan tidak dapat diperbarui', true);
					}
				});
			});

			$(window).on('beforeunload', function() {
				Object.keys(watches).forEach(stopTracking);
			});
		})(window, jQuery);
	</script>

	<script>
		const userName = '<?= $_SESSION['username'] ?>';
		console.log("Usernamenya: " + userName);
	</script>

	<!-- maps -->
	<script>
		let map, userMarker, destinationMarker, userLocation;
		let geocoder, directionsService, directionsRenderer;
		let firstLoad = true;

		function initMap() {
			if (!hasGoogleMaps()) return;

			map = new google.maps.Map(document.getElementById("maps"), {
				zoom: 12,
				center: {
					lat: -6.1751,
					lng: 106.8650
				}, // Lokasi default sebelum mendapatkan posisi pengguna
			});

			userMarker = new google.maps.Marker({
				map: map,
				icon: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png"
			});

			destinationMarker = new google.maps.Marker({
				map: map,
				icon: "https://maps.google.com/mapfiles/ms/icons/red-dot.png"
			});

			geocoder = new google.maps.Geocoder();
			directionsService = new google.maps.DirectionsService();
			directionsRenderer = new google.maps.DirectionsRenderer({
				map: map
			});

			if (navigator.geolocation) {
				navigator.geolocation.getCurrentPosition((position) => {
					userLocation = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
					map.setCenter(userLocation);
					userMarker.setPosition(userLocation);
					// Set tujuan awal berdasarkan lokasi pengguna
					if (firstLoad) {
						updateDestination(userLocation.lat(), userLocation.lng());
						firstLoad = false;
					}
				}, showError);

				navigator.geolocation.watchPosition(updateLocation, showError);
			} else {
				alert("Geolocation tidak didukung oleh browser ini.");
			}

			document.querySelectorAll(".lihat-map").forEach(button => {
				button.addEventListener("click", function() {
					const lat = parseFloat(this.getAttribute("data-lat"));
					const lng = parseFloat(this.getAttribute("data-lng"));
					updateDestination(lat, lng);
				});
			});
		}

		function updateLocation(position) {
			if (!hasGoogleMaps()) return;

			userLocation = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);

			userMarker.setPosition(userLocation);
			map.setCenter(userLocation);

			const newLocation = {
				lat: position.coords.latitude,
				lng: position.coords.longitude,
			};

			document.getElementById("latitude").value = newLocation.lat;
			document.getElementById("longitude").value = newLocation.lng;

			saveLocationToFirebase(position.coords.latitude, position.coords.longitude);

			getAddress(userLocation);
			hitungJarak(destinationMarker.getPosition());
			hitungJarakAcc(destinationMarker.getPosition());
		}

		function saveLocationToFirebases(lat, lng) {
			const userId = document.getElementById("username").value;
			const location = db.ref('location').push();
			location.set({
				userId: userId,
				latitude: lat,
				longitude: lng,
				timestamp: getFirebaseServerTimestamp(),
			});
		}

		function saveLocationToFirebase(lat, lng) {
			const userName = '<?= $_SESSION['id'] ?>';
			const userId = document.getElementById("username").value;
			const locationRef = db.ref('location/' + userName);

			locationRef.once('value').then((snapshot) => {
				if (snapshot.exists()) {
					console.log("Data sudah ada, melakukan update.");
				} else {
					console.log("Data belum ada, menyimpan baru.");
				}
				locationRef.set({
					userId: userId,
					latitude: lat,
					longitude: lng,
					timestamp: getFirebaseServerTimestamp(),
				});
			});
		}


		function getAddress(location) {
			if (!hasGoogleMaps() || !geocoder) return;

			geocoder.geocode({
				location: location
			}, (results, status) => {
				if (status === "OK") {
					if (results[0]) {
						document.getElementById("address").value = results[0].formatted_address;
						document.getElementById("address_label").innerText = results[0].formatted_address;
					}
				}
			});
		}

		function updateDestination(lat, lng) {
			if (!hasGoogleMaps() || !destinationMarker) return;

			const destination = new google.maps.LatLng(lat, lng);
			destinationMarker.setPosition(destination);
			// hitungJarak(destination);
			calculateRoute(destination);
			hitungJarakAcc();
		}

		function hitungJarak() {
			if (!hasGoogleMaps() || !userLocation) return;

			const service = new google.maps.DistanceMatrixService();

			document.querySelectorAll(".request-card").forEach((card) => {
				const lat = parseFloat(card.dataset.lat);
				const lng = parseFloat(card.dataset.lng);

				const request = {
					origins: [userLocation], // Lokasi dokter
					destinations: [{
						lat,
						lng
					}], // Lokasi pasien
					travelMode: google.maps.TravelMode.DRIVING
				};

				service.getDistanceMatrix(request, function(response, status) {
					if (status === "OK") {
						const result = response.rows[0].elements[0];

						// Update hasil di elemen yang sesuai
						card.querySelector(".distance").innerText = result.distance.text;
						card.querySelector(".duration").innerText = result.duration.text;
					}
				});
			});
		}

		function hitungJarakAcc() {
			if (!hasGoogleMaps() || !userLocation) return;

			// buatkan code untuk destination
			const destinations = {
				lat: parseFloat(document.getElementById("lat-des-acc").value),
				lng: parseFloat(document.getElementById("lng-des-acc").value)
			}

			console.log(destinations);


			const requests = {
				origins: [userLocation],
				destinations: [destinations],
				travelMode: google.maps.TravelMode.DRIVING
			};

			console.log(requests);


			const services = new google.maps.DistanceMatrixService();
			services.getDistanceMatrix(requests, function(response, status) {
				if (status === "OK") {
					const results = response.rows[0].elements[0];
					document.getElementById("distances").innerText = results.distance.text;
					document.getElementById("durations").innerText = results.duration.text;
				}
			});

			// calculateRoute(destination);
		}

		function calculateRoute(destination) {
			if (!hasGoogleMaps() || !userLocation || !directionsService) return;

			const request = {
				origin: userLocation,
				destination: destination,
				travelMode: google.maps.TravelMode.DRIVING
			};

			directionsService.route(request, function(result, status) {
				if (status === google.maps.DirectionsStatus.OK) {
					directionsRenderer.setDirections(result);
				} else {
					alert("Gagal mendapatkan rute: " + status);
				}
			});
		}

		function showError(error) {
			switch (error.code) {
				// case error.PERMISSION_DENIED:
				// 	alert("Izin lokasi ditolak.");
				// 	break;
				case error.POSITION_UNAVAILABLE:
					Swal.fire({
						title: "Error",
						text: "Informasi lokasi tidak tersedia.",
						icon: "error",
						confirmButtonText: "OK"
					});
					break;
				case error.TIMEOUT:
					Swal.fire({
						title: "Error",
						text: "Permintaan lokasi timeout.",
						icon: "error",
						confirmButtonText: "OK"
					});
					break;
			}
		}

		if (hasGoogleMaps()) {
			window.addEventListener('load', initMap);
		}
	</script>

	<!-- Chat yang sudah tidak di pakai karena untuk chat sendiri ada di module chat -->
	<script>
		let nama_pasien;
		let pasien;
		let button;

		document.addEventListener("DOMContentLoaded", () => {
			if (Notification.permission !== "granted") {
				Notification.requestPermission();
			}
		});

		// Mengambil parameter URL
		const params = new URLSearchParams(window.location.search);
		// const dokter = prompt("Masukkan Dokter");
		// const pasien = params.get('pasien');

		const currentUser = document.getElementById('username').value;

		const chatModal = document.getElementById('chat');
		chatModal.addEventListener('show.bs.modal', function(event) {
			button = event.relatedTarget;
			pasien = button.getAttribute('data-pasien');
			nama_pasien = button.getAttribute('data-nama');
			const modal = this;
			modal.querySelector('.modal-title').textContent = `Chat with ${nama_pasien}`;
			modal.querySelector('#modal-pasien').value = pasien;

			const chatWith = pasien;


			db.ref("messages").on("child_added", (snapshot) => {
				const message = snapshot.val();
				console.log("New message detected:", message);

				if (message.receiver === currentUser) {
					console.log("Message is for current user:", message);
					showNotification(`Message from ${message.sender}: ${message.text}`);
				}
			});

			// Listen for messages
			db.ref("messages").on("value", (snapshot) => {
				const chatBox = document.getElementById("chat-box");
				chatBox.innerHTML = ""; // Clear the chat box
				snapshot.forEach((childSnapshot) => {
					const message = childSnapshot.val();

					if (
						(message.sender === currentUser && message.receiver === chatWith) ||
						(message.sender === chatWith && message.receiver === currentUser)
					) {
						const messageElement = document.createElement("div");
						const messageContent = document.createElement("div");
						const textElement = document.createElement("span");
						const timestampElement = document.createElement("span");


						messageElement.classList.add("message");
						if (message.sender === currentUser) {
							messageElement.classList.add("message-right");
						} else {
							messageElement.classList.add("message-left");
						}
						// Message text
						textElement.classList.add("message-text");
						textElement.textContent = message.text;

						// Timestamp
						timestampElement.classList.add("timestamp");
						timestampElement.textContent = new Date(message.timestamp).toLocaleTimeString([], {
							hour: "2-digit",
							minute: "2-digit",
						});

						// Combine message text and timestamp
						messageContent.classList.add("message-content");
						messageContent.appendChild(textElement);
						messageContent.appendChild(timestampElement);

						messageElement.appendChild(messageContent);
						chatBox.appendChild(messageElement);

						// chatBox.appendChild(messageContainer);

						// Scroll to the bottom
						chatBox.scrollTop = chatBox.scrollHeight;
					}
				});
			});

			function showNotification(message) {
				console.log("Attempting to show notification with message:", message);

				if (!("Notification" in window)) {
					console.error("This browser does not support notifications.");
				} else if (Notification.permission === "granted") {
					const notification = new Notification("New Message", {
						body: message
					});
					console.log("Notification displayed:", notification);
				} else if (Notification.permission !== "denied") {
					Notification.requestPermission().then((permission) => {
						console.log("Permission request result:", permission);
						if (permission === "granted") {
							const notification = new Notification("New Message", {
								body: message
							});
							console.log("Notification displayed after granting permission:", notification);
						}
					});
				} else {
					console.error("Notification permission denied.");
				}
			}

			// Send message
			function sendMessage() {
				const messageInput = document.getElementById("message");
				const message = messageInput.value;

				if (message.trim() !== "") {
					const newMessageRef = db.ref("messages").push();
					newMessageRef.set({
						sender: currentUser,
						receiver: chatWith,
						text: message,
						timestamp: Date.now()
					});
					messageInput.value = "";
				}
			}
		});
	</script>

	<!-- Notifikasi -->
	<script>
		const notificationListJsonUrl = <?= json_encode(base_url('notifikasi/list_json')); ?>;
		const notificationMarkReadUrl = <?= json_encode(base_url('notifikasi/mark_read')); ?>;
		const notificationPollIntervalMs = 30000;

		function getNotificationBadge() {
			return document.getElementById('badgeNotifs') || document.getElementById('badgeNotif');
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
			icon.className = 'bi bi-bell-fill text-success me-2';
			icon.style.fontSize = '1.5rem';
			notificationItem.appendChild(icon);

			const textContainer = document.createElement('div');
			textContainer.className = 'flex-grow-1';

			const title = document.createElement('p');
			title.className = 'mb-0 fw-bold';
			title.textContent = item.title || 'Notifikasi';
			textContainer.appendChild(title);

			const message = document.createElement('small');
			message.className = 'd-block';
			message.textContent = item.message || 'Tidak ada detail';
			textContainer.appendChild(message);

			const timestamp = document.createElement('small');
			timestamp.className = 'text-muted';
			timestamp.textContent = item.created_at ? new Date(item.created_at.replace(' ', 'T')).toLocaleString('id-ID') : '';
			textContainer.appendChild(timestamp);

			notificationItem.appendChild(textContainer);
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
	</script>

	<!-- switch -->
	<script>
		document.getElementById("kunjung").addEventListener("change", function() {
			let label = document.getElementById("labelKunjung");
			label.textContent = this.checked ? "Iya" : "Tidak";
		});
	</script>

	<!-- tampil request dari pasien melalui firebase -->
	<script>
		// Mendapatkan referensi ke database Firebase
		// Mendapatkan referensi ke node "request"
		const requestRef = db.ref("request");

		// Mendengarkan perubahan pada data "request"
		requestRef.on("value", (snapshot) => {
			const data = snapshot.val();

			const cekStatus = document.getElementById("cekStatus");

			if (data) {
				// buat halaman menjadi reload
				window.location.reload();

				// hapus data request di firebase
				requestRef.remove();
			}
		});
	</script>

	<!-- mendapatkan url -->
	<script>
		document.addEventListener("DOMContentLoaded", function() {
			const hash = window.location.hash;

			if (hash === "#riwayat_konsul" || hash === "#riwayat_konsul_selesai") {
				if (typeof showContent === 'function') {
					showContent('riwayat_konsul');
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

	<!-- upload dan preview foto atau gambar -->
	<script>
		function previewImage(event) {
			const input = event.target;
			const reader = new FileReader();

			reader.onload = function() {
				const preview = document.getElementById('previewFoto');
				preview.src = reader.result;
			};

			if (input.files && input.files[0]) {
				reader.readAsDataURL(input.files[0]);
			}
		}
	</script>

	<!-- edit profile -->
	<script>
		document.getElementById('formEditProfile').addEventListener('submit', function(e) {
			e.preventDefault(); // Cegah form submit biasa

			let form = document.getElementById('formEditProfile');
			let formData = new FormData(form); // Ambil semua input termasuk file

			fetch("<?= base_url('home_nakes/updateprofile') ?>", {
					method: "POST",
					body: formData
				})
				.then(response => response.json())
				.then(result => {
					if (result.status === 'success') {
						alert("Profil berhasil diperbarui!");
						// Misalnya reload data user:
						location.reload();
					} else {
						alert("Gagal menyimpan: " + result.message);
					}
				})
				.catch(error => {
					console.error("Error:", error);
					alert("Terjadi kesalahan saat menyimpan.");
				});
		});
	</script>

</body>

</html>
