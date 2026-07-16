<?php
$kriteria = isset($kriteria) ? $kriteria : '';
$map_provider = $this->config->item('map_provider') ?: 'none';
$google_maps_api_key = $this->config->item('google_maps_api_key') ?: '';
$firebase_enabled = (bool) $this->config->item('firebase_enabled');
$legacy_superapp_url = $this->config->item('legacy_superapp_url') ?: '';
$can_handle_request = !empty($can_handle_request);

if ((string) $kriteria === '0') {
	$kriteria = 'Selesai Konsultasi';
} elseif ((string) $kriteria === '1') {
	$kriteria = 'Kunjungan Nakes';
}

$keluhan_pasien = 'Keluhan tidak dapat ditampilkan.';
if (isset($keluhan) && $keluhan !== '') {
	try {
		$CI = &get_instance();
		$CI->load->library('encryption');
		$decoded_keluhan = base64_decode($keluhan);
		if ($decoded_keluhan !== FALSE) {
			$decrypted_keluhan = $CI->encryption->decrypt($decoded_keluhan);
			if ($decrypted_keluhan !== FALSE && trim((string) $decrypted_keluhan) !== '') {
				$keluhan_pasien = (string) $decrypted_keluhan;
			}
		}
	} catch (Exception $e) {
		$keluhan_pasien = 'Keluhan tidak dapat ditampilkan.';
	}
}

if (!function_exists('formatComplaintText')) {
	function formatComplaintText($text)
	{
		$lines = preg_split('/\r\n|\r|\n/', (string) $text);
		$groups = [];
		$current = null;

		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			if (preg_match('/^\*\*(.+?)\*\*$/', $line, $matches)) {
				if ($current !== null) {
					$groups[] = $current;
				}
				$current = [
					'title' => trim($matches[1]),
					'items' => []
				];
				continue;
			}

			if ($current === null) {
				$current = [
					'title' => '',
					'items' => []
				];
			}

			if (strpos($line, ':') !== false) {
				list($label, $value) = explode(':', $line, 2);
				$current['items'][] = [
					'type' => 'pair',
					'label' => trim($label),
					'value' => trim($value)
				];
			} else {
				$current['items'][] = [
					'type' => 'text',
					'value' => $line
				];
			}
		}

		if ($current !== null) {
			$groups[] = $current;
		}

		if (empty($groups)) {
			return '<p class="complaint-text">' . nl2br(html_escape((string) $text)) . '</p>';
		}

		$html = '<div class="complaint-groups">';
		foreach ($groups as $group) {
			$html .= '<div class="complaint-group">';
			if (!empty($group['title'])) {
				$html .= '<h3 class="complaint-group-title">' . html_escape($group['title']) . '</h3>';
			}
			if (!empty($group['items'])) {
				$html .= '<div class="complaint-items">';
				foreach ($group['items'] as $item) {
					if ($item['type'] === 'pair') {
						$html .= '<div class="complaint-item">';
						$html .= '<span class="complaint-label">' . html_escape($item['label']) . '</span>';
						$html .= '<span class="complaint-value">' . nl2br(html_escape($item['value'] !== '' ? $item['value'] : '-')) . '</span>';
						$html .= '</div>';
					} else {
						$html .= '<p class="complaint-free-text">' . nl2br(html_escape($item['value'])) . '</p>';
					}
				}
				$html .= '</div>';
			}
			$html .= '</div>';
		}
		$html .= '</div>';

		return $html;
	}
}

?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DocLink - Konsultasi Nakes</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" integrity="sha512-tS3S5qG0BlhnQROyJXvNjeEM4UpMXHrQfTGmbQ1gKmelCxlSEBUaxhRBj/EFTzpbP4RVSrpEikbmdJobCvhE3g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css" integrity="sha512-sMXtMNL1zRzolHYKEujM2AqCLUR9F2C4/05cdbxjjLSRvMQIciEPCQZo++nk7go3BtSuK9kfa/s+a4f4i5pLkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta3/css/bootstrap-select.min.css" integrity="sha512-g2SduJKxa4Lbn3GW+Q7rNz+pKP9AWMR++Ta8fgwsZRCUsawjPvF/BxSMkGS61VsR9yinGoEgrHPGPn2mrj8+4w==" crossorigin="anonymous" referrerpolicy="no-referrer">
	<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<style>
		:root {
			--doclinc-green: #379A69;
			--doclinc-mint: #50BFA5;
			--doclinc-bg: #F7F7F7;
			--doclinc-border: #CECECE;
			--doclinc-text: #1F2A24;
			--doclinc-muted: #66756D;
		}

		body {
			background: var(--doclinc-bg);
			color: var(--doclinc-text);
			font-size: 15px;
		}

		.consult-shell {
			max-width: 414px;
			min-height: 100vh;
			margin: 0 auto;
			background: var(--doclinc-bg);
			padding: 14px;
		}

		.consult-header {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 8px 0 16px;
		}

		.consult-back {
			width: 40px;
			height: 40px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border-radius: 14px;
			background: #fff;
			color: var(--doclinc-green);
			text-decoration: none;
			box-shadow: 0 8px 22px rgba(31, 42, 36, 0.08);
		}

		.consult-title {
			flex: 1;
			min-width: 0;
		}

		.consult-title h1 {
			margin: 0;
			font-size: 20px;
			font-weight: 700;
			letter-spacing: 0;
			color: var(--doclinc-text);
		}

		.consult-subtitle {
			margin: 2px 0 0;
			color: var(--doclinc-muted);
			font-size: 12px;
		}

		.status-badge {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 6px 10px;
			border-radius: 999px;
			background: rgba(80, 191, 165, 0.18);
			color: var(--doclinc-green);
			font-size: 12px;
			font-weight: 700;
			white-space: nowrap;
		}

		.consult-card {
			width: 100%;
			background: #fff;
			border: 1px solid rgba(80, 191, 165, 0.45);
			border-radius: 20px;
			box-shadow: 0 10px 28px rgba(31, 42, 36, 0.06);
			padding: 16px;
			margin-bottom: 14px;
		}

		.section-heading {
			display: flex;
			align-items: center;
			gap: 8px;
			margin: 0 0 12px;
			color: var(--doclinc-green);
			font-size: 15px;
			font-weight: 700;
		}

		.summary-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 10px;
		}

		.summary-item {
			padding: 10px;
			border: 1px solid #E7ECE9;
			border-radius: 14px;
			background: #FBFDFC;
			min-width: 0;
		}

		.summary-label {
			display: block;
			margin-bottom: 4px;
			color: var(--doclinc-muted);
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
		}

		.summary-value {
			display: block;
			color: var(--doclinc-text);
			font-size: 14px;
			font-weight: 600;
			overflow-wrap: anywhere;
		}

		.complaint-text {
			margin: 0;
			color: var(--doclinc-text);
			font-size: 15px;
			line-height: 1.6;
			overflow-wrap: anywhere;
		}

		.complaint-groups {
			display: grid;
			gap: 12px;
		}

		.complaint-group {
			padding: 12px;
			border: 1px solid #E7ECE9;
			border-radius: 16px;
			background: #FBFDFC;
		}

		.complaint-group-title {
			margin: 0 0 10px;
			color: var(--doclinc-green);
			font-size: 15px;
			font-weight: 800;
		}

		.complaint-items {
			display: grid;
			gap: 8px;
		}

		.complaint-item {
			display: grid;
			gap: 3px;
			padding-bottom: 8px;
			border-bottom: 1px solid #E7ECE9;
		}

		.complaint-item:last-child {
			padding-bottom: 0;
			border-bottom: 0;
		}

		.complaint-label {
			color: var(--doclinc-muted);
			font-size: 12px;
			font-weight: 700;
		}

		.complaint-value,
		.complaint-free-text {
			margin: 0;
			color: var(--doclinc-text);
			font-size: 14px;
			line-height: 1.45;
			overflow-wrap: anywhere;
		}

		.chat-card {
			background: linear-gradient(135deg, #379A69 0%, #50BFA5 100%);
			color: #fff;
			border: 0;
		}

		.chat-copy {
			margin: 0 0 12px;
			font-size: 13px;
			opacity: 0.92;
		}

		.chat-button,
		.primary-action {
			width: 100%;
			min-height: 48px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			border: 0;
			border-radius: 14px;
			font-weight: 700;
			text-decoration: none;
		}

		.chat-button {
			background: #fff;
			color: var(--doclinc-green);
		}

		.service-decision-card {
			border-color: rgba(9, 173, 116, 0.22);
		}

		.service-decision-helper {
			margin: 0 0 14px;
			color: var(--doclinc-muted);
			font-size: 13px;
			line-height: 1.5;
		}

		.service-decision-options {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 10px;
		}

		.service-decision-option {
			min-height: 74px;
			padding: 12px;
			border: 1px solid var(--doclinc-border);
			border-radius: 14px;
			background: #fff;
			color: var(--doclinc-text);
			text-align: left;
			transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
		}

		.service-decision-option strong,
		.service-decision-option span {
			display: block;
		}

		.service-decision-option strong {
			font-size: 14px;
			font-weight: 800;
		}

		.service-decision-option span {
			margin-top: 4px;
			color: var(--doclinc-muted);
			font-size: 12px;
			line-height: 1.35;
		}

		.service-decision-option.is-selected {
			border-color: var(--doclinc-green);
			background: #F0FBF6;
			box-shadow: 0 10px 24px rgba(9, 173, 116, 0.12);
		}

		.service-decision-state {
			margin: 12px 0 0;
			color: var(--doclinc-muted);
			font-size: 12px;
		}

		.primary-action {
			background: var(--doclinc-green);
			color: #fff;
		}

		.form-control,
		.form-select {
			width: 100%;
			border-color: var(--doclinc-border);
			border-radius: 10px;
		}

		.form-control:focus,
		.form-select:focus {
			border-color: var(--doclinc-mint);
			box-shadow: 0 0 0 0.2rem rgba(80, 191, 165, 0.18);
		}

		.form-floating>label {
			color: var(--doclinc-muted);
		}

		.optional-note {
			margin: -6px 0 10px;
			color: var(--doclinc-muted);
			font-size: 12px;
		}

		.documentation-group {
			display: grid;
			gap: 12px;
			margin-bottom: 16px;
		}

		.documentation-field {
			min-height: 118px;
			resize: vertical;
		}

		.backend-field {
			display: none;
		}

		.terapi-scroll {
			display: none;
		}

		#tabelTerapi {
			min-width: 680px;
			margin-bottom: 0;
		}

		#tabelTerapi th {
			color: var(--doclinc-green);
			font-size: 12px;
			white-space: nowrap;
		}

		#tabelTerapi td {
			vertical-align: middle;
		}

		#tabelTerapi .btn {
			border-radius: 10px;
		}

		.terapi-helper {
			margin: -4px 0 10px;
			color: var(--doclinc-muted);
			font-size: 12px;
			line-height: 1.5;
		}

		.terapi-row-message {
			display: none;
			margin: 0 0 10px;
			padding: 9px 11px;
			border-radius: 12px;
			background: #FFF7E6;
			color: #9A5B00;
			font-size: 13px;
			font-weight: 600;
		}

		.terapi-row-message.is-visible {
			display: block;
		}

		.mobile-terapi-editor {
			display: grid;
			gap: 12px;
		}

		.mobile-terapi-card {
			display: grid;
			gap: 12px;
			padding: 14px;
			border: 1px solid #E7ECE9;
			border-radius: 16px;
			background: #FBFDFC;
		}

		.mobile-terapi-fields {
			display: grid;
			gap: 10px;
		}

		.mobile-field-label {
			display: block;
			margin-bottom: 5px;
			color: var(--doclinc-muted);
			font-size: 12px;
			font-weight: 800;
		}

		.mobile-terapi-add {
			min-height: 46px;
			border: 0;
			border-radius: 14px;
			background: var(--doclinc-green);
			color: #fff;
			font-weight: 800;
		}

		.mobile-terapi-message {
			display: none;
			padding: 9px 11px;
			border-radius: 12px;
			background: #FFF7E6;
			color: #9A5B00;
			font-size: 13px;
			font-weight: 600;
		}

		.mobile-terapi-message.is-visible {
			display: block;
		}

		.mobile-terapi-list {
			display: grid;
			gap: 10px;
		}

		.mobile-terapi-empty {
			margin: 0;
			padding: 12px;
			border: 1px dashed #BFDCD2;
			border-radius: 14px;
			background: #fff;
			color: var(--doclinc-muted);
			font-size: 13px;
			line-height: 1.5;
		}

		.mobile-terapi-item {
			display: grid;
			gap: 8px;
			padding: 12px;
			border: 1px solid #DDEAE5;
			border-radius: 14px;
			background: #fff;
			box-shadow: 0 8px 18px rgba(31, 42, 36, 0.05);
		}

		.mobile-terapi-name {
			margin: 0;
			color: var(--doclinc-text);
			font-size: 14px;
			font-weight: 800;
			overflow-wrap: anywhere;
		}

		.mobile-terapi-meta {
			display: grid;
			gap: 5px;
			margin: 0;
			color: var(--doclinc-muted);
			font-size: 12px;
			line-height: 1.45;
		}

		.mobile-terapi-remove {
			min-height: 40px;
			border-radius: 12px;
			font-weight: 700;
		}

		.btn-terapi-action {
			min-width: 40px;
			min-height: 40px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
		}

		.ui-autocomplete {
			z-index: 1056 !important;
		}

		.terapi-autocomplete {
			max-width: 180px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			cursor: pointer;
		}

		.terapi-autocomplete:hover {
			white-space: normal;
			overflow: visible;
			position: relative;
			z-index: 1;
			background: #fff;
		}

		#preview-image {
			max-width: 100%;
			margin-top: 10px;
			border-radius: 14px;
		}

		@media (min-width: 768px) {
			.consult-shell {
				padding-top: 24px;
				padding-bottom: 24px;
			}
		}

		@media (max-width: 480px) {
			#tabelTerapi {
				min-width: 0;
				width: 100%;
				border-collapse: separate;
				border-spacing: 0 12px;
			}

			#tabelTerapi thead {
				display: none;
			}

			#tabelTerapi,
			#tabelTerapi tbody,
			#tabelTerapi tr,
			#tabelTerapi td {
				display: block;
				width: 100%;
			}

			#tabelTerapi tr {
				padding: 12px;
				border: 1px solid #E7ECE9;
				border-radius: 16px;
				background: #fff;
				box-shadow: 0 8px 20px rgba(31, 42, 36, 0.05);
			}

			#tabelTerapi td {
				padding: 0 0 10px;
				border: 0;
			}

			#tabelTerapi td:last-child {
				padding-bottom: 0;
			}

			#tabelTerapi td::before {
				content: attr(data-label);
				display: block;
				margin-bottom: 5px;
				color: var(--doclinc-muted);
				font-size: 11px;
				font-weight: 800;
				text-transform: uppercase;
			}

			#tabelTerapi td:first-child {
				color: var(--doclinc-green);
				font-weight: 800;
			}

			#tabelTerapi td:first-child::before {
				display: inline;
				margin-right: 6px;
			}

			#tabelTerapi .form-select,
			#tabelTerapi .terapi-autocomplete,
			#tabelTerapi td[contenteditable="true"] {
				width: 100%;
				max-width: none;
			}

			#tabelTerapi .terapi-autocomplete,
			#tabelTerapi td[contenteditable="true"] {
				min-height: 42px;
				padding: 9px 10px;
				border: 1px solid var(--doclinc-border);
				border-radius: 10px;
				background: #fff;
				white-space: normal;
			}

			#tabelTerapi td[data-label="Aksi"] {
				display: flex;
				gap: 8px;
				flex-wrap: wrap;
			}

			#tabelTerapi td[data-label="Aksi"]::before {
				flex-basis: 100%;
			}

			.btn-terapi-action {
				flex: 1 1 46%;
				min-height: 44px;
			}

			.service-decision-options {
				grid-template-columns: 1fr;
			}
		}
	</style>
</head>

<body>
	<div class="consult-shell">
		<header class="consult-header">
			<a href="<?= base_url('home_nakes'); ?>" class="consult-back" aria-label="Kembali ke beranda nakes">
				<i class="fas fa-arrow-left"></i>
			</a>
			<div class="consult-title">
				<h1>Pemeriksaan Pasien</h1>
				<p class="consult-subtitle">No. Antrian: <?= html_escape($queue_code) ?></p>
			</div>
			<span class="status-badge"><i class="bi bi-check-circle-fill"></i> Diterima</span>
		</header>

		<main class="content animate__animated animate__fadeInUp animate__faster">
			<section class="consult-card">
				<h2 class="section-heading"><i class="bi bi-person-vcard"></i> Ringkasan Pasien</h2>
				<div class="summary-grid">
					<div class="summary-item">
						<span class="summary-label">Nama Pasien</span>
						<span class="summary-value"><?= html_escape(strtoupper($nama_pasien)); ?></span>
					</div>
					<div class="summary-item">
						<span class="summary-label">Umur</span>
						<span class="summary-value"><?= html_escape($umur) ?> Tahun</span>
					</div>
					<div class="summary-item">
						<span class="summary-label">User ID</span>
						<span class="summary-value"><?= html_escape($userid) ?></span>
					</div>
					<div class="summary-item">
						<span class="summary-label">No. Antrian</span>
						<span class="summary-value"><?= html_escape($queue_code) ?></span>
					</div>
					<div class="summary-item">
						<span class="summary-label">Jenis Layanan</span>
						<span class="summary-value"><?= html_escape($kriteria !== '' ? $kriteria : 'Belum ditentukan') ?></span>
					</div>
					<div class="summary-item">
						<span class="summary-label">Konteks</span>
						<span class="summary-value">Puskesmas / Nakes</span>
					</div>
				</div>
			</section>

			<section class="consult-card">
				<h2 class="section-heading"><i class="bi bi-clipboard2-pulse"></i> Keluhan Pasien</h2>
				<?= formatComplaintText($keluhan_pasien); ?>
			</section>

			<section class="consult-card chat-card">
				<h2 class="section-heading text-white"><i class="bi bi-chat-dots"></i> Chat Konsultasi</h2>
				<p class="chat-copy">Lakukan komunikasi awal dengan warga sebelum menentukan Visit atau Non-Visit.</p>
				<a href="<?= html_escape(base_url('chat?request_id=' . (int) $request_id)); ?>" class="chat-button">
					<i class="bi bi-chat-dots-fill"></i> Buka chat
				</a>
			</section>

		<input type="hidden" name="userid" id="userId" value="<?= html_escape($userid) ?>">
		<input type="hidden" name="dokterid" id="dokterId" value="<?= html_escape($_SESSION['id']) ?>">
		<?php if (!$can_handle_request) : ?>
			<div class="alert alert-info" role="status">Permintaan ini dapat dilihat, tetapi tindakan klinis telah ditugaskan kepada Staff lain.</div>
		<?php endif; ?>
		<form id="form_konsul_nakes" enctype="multipart/form-data" data-can-handle="<?= $can_handle_request ? '1' : '0'; ?>">
			<input type="hidden" name="request_id" id="idReq" value="<?= html_escape($request_id) ?>">
			<fieldset <?= $can_handle_request ? '' : 'disabled'; ?>>
			<section class="consult-card service-decision-card">
				<h2 class="section-heading"><i class="bi bi-signpost-split"></i> Tentukan Jenis Layanan</h2>
				<p class="service-decision-helper">Pilih setelah komunikasi awal dengan warga selesai dilakukan.</p>
				<div class="service-decision-options" role="group" aria-label="Tentukan jenis layanan">
					<button type="button" class="service-decision-option" data-kriteria="Kunjungan Nakes" data-label="Visit / Kunjungan">
						<strong>Visit / Kunjungan</strong>
						<span>Nakes melakukan kunjungan langsung ke lokasi warga.</span>
					</button>
					<button type="button" class="service-decision-option" data-kriteria="Selesai Konsultasi" data-label="Non-Visit / Konsultasi Jarak Jauh">
						<strong>Non-Visit / Konsultasi Jarak Jauh</strong>
						<span>Konsultasi diselesaikan jarak jauh setelah asesmen awal.</span>
					</button>
				</div>
				<p class="service-decision-state">Pilihan: <strong id="selectedServiceLabel"><?= html_escape($kriteria !== '' ? $kriteria : 'Belum ditentukan'); ?></strong></p>
				<input type="hidden" id="kriteria" name="kriteria" value="<?= html_escape($kriteria) ?>">
			</section>
			<section class="consult-card">
				<h2 class="section-heading"><i class="bi bi-clipboard-check"></i> Selesaikan Konsultasi</h2>
				<div class="form-floating mb-3">
					<input type="text" id="diagnosa" name="diagnosa" class="form-control" placeholder="Diagnosa" required>
					<label for="diagnosa"><i class="bi bi-heart-pulse"></i> Diagnosa*</label>
				</div>
				<div class="mb-3">
					<h3 class="section-heading"><i class="bi bi-capsule"></i> Obat / Farmakoterapi</h3>
					<p class="terapi-helper">Bagian ini digunakan untuk mencatat obat/farmakoterapi bila diberikan. Kosongkan bila tidak ada obat.</p>
					<div id="terapiRowMessage" class="terapi-row-message" role="alert"></div>
					<div class="mobile-terapi-editor" aria-label="Editor farmakoterapi mobile">
						<div class="mobile-terapi-card">
							<div id="ui_mobile_terapi_message" class="mobile-terapi-message" role="alert"></div>
							<div class="mobile-terapi-fields">
								<div>
									<label class="mobile-field-label" for="ui_mobile_terapi_nama">Nama obat / terapi</label>
									<input type="text" id="ui_mobile_terapi_nama" class="form-control" placeholder="Tulis nama obat atau terapi">
								</div>
								<div>
									<label class="mobile-field-label" for="ui_mobile_terapi_jumlah">Frekuensi / Signa</label>
									<select id="ui_mobile_terapi_jumlah" class="form-select">
										<option value="" selected>Pilih frekuensi/signa</option>
										<option value="1x sehari">1x sehari</option>
										<option value="2x sehari">2x sehari</option>
										<option value="3x sehari">3x sehari</option>
										<option value="4x sehari">4x sehari</option>
									</select>
								</div>
								<div>
									<label class="mobile-field-label" for="ui_mobile_terapi_cara">Cara minum / cara pakai</label>
									<select id="ui_mobile_terapi_cara" class="form-select">
										<option value="" selected>Pilih cara pakai</option>
										<option value="Sesudah makan">Sesudah makan</option>
										<option value="Sebelum makan">Sebelum makan</option>
									</select>
								</div>
								<div>
									<label class="mobile-field-label" for="ui_mobile_terapi_keterangan">Keterangan</label>
									<textarea id="ui_mobile_terapi_keterangan" class="form-control" rows="3" placeholder="Opsional"></textarea>
								</div>
							</div>
							<button type="button" id="ui_mobile_terapi_add" class="mobile-terapi-add">
								<i class="bi bi-plus-circle"></i> Tambah obat
							</button>
						</div>
						<div id="ui_mobile_terapi_list" class="mobile-terapi-list">
							<p class="mobile-terapi-empty">Belum ada obat yang ditambahkan. Kosongkan jika tidak ada farmakoterapi.</p>
						</div>
					</div>
					<div class="terapi-scroll">
						<table class="table table-sm table-hover table-striped" id="tabelTerapi">
								<thead class="table-success">
									<tr>
										<th>No.</th>
										<th>Terapi</th>
										<th>Frekuensi / Signa</th>
										<th>Cara Minum</th>
										<th>Keterangan</th>
										<th>Aksi</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td data-label="No.">1</td>
										<td data-label="Terapi" contenteditable="true" class="terapi-autocomplete">Terapi*</td>
										<td data-label="Frekuensi / Signa">
											<select class="form-select form-select-sm border-success">
												<option value="" selected disabled>Pilih frekuensi/signa</option>
												<option value="1x sehari">1x sehari</option>
												<option value="2x sehari">2x sehari</option>
												<option value="3x sehari">3x sehari</option>
												<option value="4x sehari">4x sehari</option>
											</select>
										</td>
										<td data-label="Cara Minum / Cara Pakai">
											<select class="form-select form-select-sm border-success">
												<option value="" selected disabled>Pilih cara pakai</option>
												<option value="Sesudah makan">Sesudah makan</option>
												<option value="Sebelum makan">Sebelum makan</option>
											</select>
										</td>
										<td data-label="Keterangan" contenteditable="true">Masukkan keterangan</td>
										<td data-label="Aksi">
											<button type="button" class="btn btn-success btn-sm btn-terapi-action" onclick="tambahBaris(this)">
												<i class="bi bi-plus-circle"></i>
											</button>
										</td>
									</tr>
								</tbody>
							</table>
					</div>
				</div>
				<div class="documentation-group">
					<h3 class="section-heading mb-0"><i class="bi bi-journal-medical"></i> Dokumentasi Tindakan</h3>
					<div class="form-floating">
						<textarea id="ui_tindakan_non_obat" class="form-control documentation-field" placeholder="Edukasi pasien, anjuran istirahat, hidrasi/nutrisi, perawatan sederhana, observasi mandiri"></textarea>
						<label for="ui_tindakan_non_obat">Tindakan Non-Obat</label>
					</div>
					<div class="form-floating">
						<textarea id="ui_pemeriksaan_monitoring" class="form-control documentation-field" placeholder="Suhu, tekanan darah, nadi, saturasi, pemeriksaan fisik ringkas, pemeriksaan penunjang jika ada"></textarea>
						<label for="ui_pemeriksaan_monitoring">Pemeriksaan / Monitoring</label>
					</div>
					<div class="form-floating">
						<textarea id="ui_followup_edukasi" class="form-control documentation-field" placeholder="Kapan kontrol ulang, kondisi yang perlu segera diperiksa, edukasi singkat untuk pasien"></textarea>
						<label for="ui_followup_edukasi">Follow-up / Edukasi Tanda Bahaya</label>
					</div>
					<div class="form-floating">
						<textarea id="ui_catatan_tindakan_lain" class="form-control documentation-field" placeholder="Catatan tambahan tindakan atau observasi"></textarea>
						<label for="ui_catatan_tindakan_lain">Catatan Tindakan Lain</label>
					</div>
				</div>
				<div class="form-floating mb-3">
					<textarea id="ui_saran_utama" class="form-control" placeholder="Rekomendasi atau saran utama untuk pasien" style="height: 150px"></textarea>
					<label for="ui_saran_utama"><i class="bi bi-chat-dots"></i> Rekomendasi / Saran Utama*</label>
				</div>
				<textarea id="saran" name="saran" class="backend-field" aria-hidden="true"></textarea>
				<div class="form-floating mb-2">
					<input type="text" class="form-control" id="rujukan" name="rujukan" placeholder="Rujukan">
					<label for="rujukan"><i class="bi bi-arrow-right-circle"></i> Rujukan</label>
				</div>
				<p class="optional-note">Opsional jika pasien tidak memerlukan rujukan.</p>
				<div id="visitDocumentationField" class="<?= $kriteria === 'Kunjungan Nakes' ? '' : 'd-none'; ?>">
					<div class="form-floating mb-2">
						<input type="file" class="form-control" id="file" name="file" accept="image/*">
						<label for="file"><i class="bi bi-camera"></i> Foto Kunjungan</label>
						<img id="preview-image" src="#" alt="Preview Foto" style="display:none;" class="img-thumbnail" />
					</div>
					<div id="clinicalAttachmentFeedback" class="small text-danger d-none" role="alert"></div>
					<p class="optional-note">Opsional sesuai kebutuhan dokumentasi kunjungan.</p>
				</div>
			</fieldset>
			<?php if ($can_handle_request) : ?>
				<button type="button" class="primary-action shadow-sm" id="save_konsul_nakes">
					<i class="bi bi-check2-circle"></i> Selesaikan konsultasi
				</button>
			<?php endif; ?>
			</section>
		</form>
		</main>
	</div>

	<!-- Modal -->
	<div class="modal fade" id="terapiModal" tabindex="-1" aria-labelledby="terapiModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="terapiModalLabel">Tambahkan terapi</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<div class="modal-body">
					<input type="text" id="terapiInput" class="form-control" placeholder="Cari terapi...">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
					<button type="button" class="btn btn-primary" id="simpanTerapi">Simpan</button>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" integrity="sha512-bPs7Ae6pVvhOSiIcyUClR7/q2OAsRiovw4vAkX+zJbw3ShAeeqezq50RIIcIURq7Oa20rW2n2q+fyXBNcU9lrw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta3/js/bootstrap-select.min.js" integrity="sha512-yrOmjPdp8qH8hgLfWpSFhC/+R9Cj9USL8uJxYIveJZGAiedxyIxwNw4RsLDlcjNlIRR4kkHaDHSmNHAkxFTmgg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<?php if ($map_provider === 'google' && !empty($google_maps_api_key)) : ?>
		<script src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode($google_maps_api_key); ?>"></script>
	<?php endif; ?>

	<!-- firebase dan notifikasi -->
	<?php if ($firebase_enabled) : ?>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
		<?php if (!empty($legacy_superapp_url)) : ?>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/firebase-config.js'); ?>"></script>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/get-notif.js'); ?>"></script>
		<?php endif; ?>
	<?php endif; ?>

	<!-- save konsultasi -->
	<script>
		const firebaseEnabled = <?= json_encode($firebase_enabled); ?>;
		const mapProvider = <?= json_encode($map_provider); ?>;

		function getFirebaseDatabase() {
			if (!firebaseEnabled || !window.firebase || !firebase.database) {
				return null;
			}

			try {
				return firebase.database();
			} catch (error) {
				return null;
			}
		}

		function nakesDocumentationValue(selector) {
			const value = $(selector).val();
			const trimmed = value ? value.trim() : "";
			return trimmed !== "" ? trimmed : "-";
		}

		function syncNakesDocumentationFields() {
			const saranUtama = nakesDocumentationValue('#ui_saran_utama');
			const dokumentasi = [
				"--- Dokumentasi Tindakan ---",
				"Tindakan Non-Obat:",
				nakesDocumentationValue('#ui_tindakan_non_obat'),
				"",
				"Pemeriksaan / Monitoring:",
				nakesDocumentationValue('#ui_pemeriksaan_monitoring'),
				"",
				"Follow-up / Edukasi:",
				nakesDocumentationValue('#ui_followup_edukasi'),
				"",
				"Catatan Tindakan Lain:",
				nakesDocumentationValue('#ui_catatan_tindakan_lain')
			].join("\n");

			$('#saran').val([saranUtama, dokumentasi].join("\n\n"));
		}

		var mobileTerapiRows = [];

		function isMobileTerapiMode() {
			return true;
		}

		function getMobileTerapiValue(selector) {
			return normalizeTerapiText($(selector).val());
		}

		function showMobileTerapiMessage(message) {
			$('#ui_mobile_terapi_message').text(message).addClass('is-visible');
		}

		function clearMobileTerapiMessage() {
			$('#ui_mobile_terapi_message').text('').removeClass('is-visible');
		}

		function validateMobileTerapiEditor() {
			if (isTerapiPlaceholder(getMobileTerapiValue('#ui_mobile_terapi_nama'))) {
				showMobileTerapiMessage("Isi nama obat/terapi terlebih dahulu.");
				return false;
			}

			if (getMobileTerapiValue('#ui_mobile_terapi_jumlah') === "") {
				showMobileTerapiMessage("Pilih frekuensi/signa obat.");
				return false;
			}

			if (getMobileTerapiValue('#ui_mobile_terapi_cara') === "") {
				showMobileTerapiMessage("Pilih cara minum/cara pakai.");
				return false;
			}

			clearMobileTerapiMessage();
			return true;
		}

		function clearMobileTerapiEditor() {
			$('#ui_mobile_terapi_nama').val('');
			$('#ui_mobile_terapi_jumlah').val('');
			$('#ui_mobile_terapi_cara').val('');
			$('#ui_mobile_terapi_keterangan').val('');
		}

		function escapeMobileTerapiText(value) {
			return $('<div>').text(value || '').html();
		}

		function renderMobileTerapiList() {
			const $list = $('#ui_mobile_terapi_list');
			$list.empty();

			if (mobileTerapiRows.length === 0) {
				$list.append('<p class="mobile-terapi-empty">Belum ada obat yang ditambahkan. Kosongkan jika tidak ada farmakoterapi.</p>');
				return;
			}

			mobileTerapiRows.forEach(function(item, index) {
				const keterangan = item.keterangan ? escapeMobileTerapiText(item.keterangan) : 'Tanpa keterangan';
				$list.append(`
					<div class="mobile-terapi-item">
						<p class="mobile-terapi-name">${escapeMobileTerapiText(item.terapi)}</p>
						<div class="mobile-terapi-meta">
							<span><strong>Frekuensi / signa:</strong> ${escapeMobileTerapiText(item.jumlah)}</span>
							<span><strong>Cara minum / pakai:</strong> ${escapeMobileTerapiText(item.cara)}</span>
							<span><strong>Keterangan:</strong> ${keterangan}</span>
						</div>
						<button type="button" class="btn btn-outline-danger mobile-terapi-remove" onclick="removeMobileTerapiRow(${index})">
							<i class="bi bi-trash"></i> Hapus
						</button>
					</div>
				`);
			});
		}

		function buildTerapiSelect(options, placeholder, selectedValue) {
			const $select = $('<select class="form-select form-select-sm border-success"></select>');
			$select.append($('<option></option>').val('').text(placeholder).prop('disabled', true).prop('selected', selectedValue === ''));
			options.forEach(function(optionValue) {
				$select.append($('<option></option>').val(optionValue).text(optionValue).prop('selected', selectedValue === optionValue));
			});
			return $select;
		}

		function appendTerapiTableRow(rowData, index, isPlaceholder) {
			const $row = $('<tr></tr>');
			const $cellNo = $('<td data-label="No."></td>').text(index + 1);
			const $cellTerapi = $('<td data-label="Terapi" contenteditable="true" class="terapi-autocomplete"></td>').text(isPlaceholder ? 'Terapi*' : rowData.terapi);
			const $cellJumlah = $('<td data-label="Frekuensi / Signa"></td>').append(buildTerapiSelect(['1x sehari', '2x sehari', '3x sehari', '4x sehari'], 'Pilih frekuensi/signa', isPlaceholder ? '' : rowData.jumlah));
			const $cellCara = $('<td data-label="Cara Minum / Cara Pakai"></td>').append(buildTerapiSelect(['Sesudah makan', 'Sebelum makan'], 'Pilih cara pakai', isPlaceholder ? '' : rowData.cara));
			const $cellKeterangan = $('<td data-label="Keterangan" contenteditable="true"></td>').text(isPlaceholder ? 'Masukkan keterangan' : rowData.keterangan);
			const $cellAksi = $('<td data-label="Aksi"></td>');

			$row.append($cellNo, $cellTerapi, $cellJumlah, $cellCara, $cellKeterangan, $cellAksi);
			$('#tabelTerapi tbody').append($row);
		}

		function syncMobileTerapiRowsToTable() {
			const $tbody = $('#tabelTerapi tbody');
			$tbody.empty();

			if (mobileTerapiRows.length === 0) {
				appendTerapiTableRow({
					terapi: '',
					jumlah: '',
					cara: '',
					keterangan: ''
				}, 0, true);
				$tbody.find('td[data-label="Aksi"]').append('<button type="button" class="btn btn-success btn-sm btn-terapi-action" onclick="tambahBaris(this)"><i class="bi bi-plus-circle"></i></button>');
				return;
			}

			mobileTerapiRows.forEach(function(item, index) {
				appendTerapiTableRow(item, index, false);
			});
			updateTombolAksi();
		}

		function hydrateMobileTerapiRowsFromTable() {
			if (mobileTerapiRows.length > 0) {
				return;
			}

			$("#tabelTerapi tbody tr").each(function(index, row) {
				var terapi = getTerapiName(row);

				if (!isTerapiPlaceholder(terapi)) {
					mobileTerapiRows.push({
						terapi: terapi,
						jumlah: getTerapiJumlah(row),
						cara: getTerapiCara(row),
						keterangan: getTerapiKeterangan(row)
					});
				}
			});
		}

		function addMobileTerapiRow() {
			if (!validateMobileTerapiEditor()) {
				return;
			}

			mobileTerapiRows.push({
				terapi: getMobileTerapiValue('#ui_mobile_terapi_nama'),
				jumlah: getMobileTerapiValue('#ui_mobile_terapi_jumlah'),
				cara: getMobileTerapiValue('#ui_mobile_terapi_cara'),
				keterangan: getMobileTerapiValue('#ui_mobile_terapi_keterangan')
			});

			clearMobileTerapiEditor();
			renderMobileTerapiList();
			syncMobileTerapiRowsToTable();
		}

		function removeMobileTerapiRow(index) {
			mobileTerapiRows.splice(index, 1);
			renderMobileTerapiList();
			syncMobileTerapiRowsToTable();
		}

		$('#ui_mobile_terapi_add').on('click', addMobileTerapiRow);

		$(function() {
			hydrateMobileTerapiRowsFromTable();
			renderMobileTerapiList();

			const initialKriteria = $('#kriteria').val().trim();
			if (initialKriteria) {
				const initialButton = $('.service-decision-option[data-kriteria="' + initialKriteria + '"]');
				if (initialButton.length) {
					setServiceDecision(initialKriteria, initialButton.data('label'));
				}
			}
		});

		function setServiceDecision(kriteria, label) {
			$('#kriteria').val(kriteria);
			$('#selectedServiceLabel').text(label || kriteria || 'Belum ditentukan');
			$('.service-decision-option').removeClass('is-selected');
			$('.service-decision-option[data-kriteria="' + kriteria + '"]').addClass('is-selected');

			if (kriteria === 'Kunjungan Nakes') {
				$('#visitDocumentationField').removeClass('d-none');
			} else {
				$('#visitDocumentationField').addClass('d-none');
				$('#file').val('');
				$('#preview-image').hide().attr('src', '#');
				setClinicalAttachmentFeedback('');
			}
		}

		$('.service-decision-option').on('click', function() {
			setServiceDecision($(this).data('kriteria'), $(this).data('label'));
		});

		$('#save_konsul_nakes').click(function() {
			const userId = document.getElementById('userId').value;
			const dokterId = document.getElementById('dokterId').value;
			const idReq = document.getElementById('idReq').value;
			const diagnosa = $('#diagnosa').val().trim();
			const saranUtama = $('#ui_saran_utama').val().trim();
			syncNakesDocumentationFields();
			const saran = $('#saran').val().trim();
			const kriteria = $('#kriteria').val().trim();

			if (!idReq || !diagnosa || !saranUtama || !saran || !kriteria) {
				const validationMessage = !idReq
					? "Permintaan tidak valid."
					: (!diagnosa
						? "Masukkan diagnosis."
						: ((!saranUtama || !saran) ? "Tambahkan saran." : "Pilih jenis layanan."));
				Swal.fire("Data belum lengkap", validationMessage, "error");
				return;
			}

			var form = document.getElementById('form_konsul_nakes');
			var formData = new FormData(form);
			formData.set("request_id", idReq);
			formData.set("diagnosa", diagnosa);
			formData.set("saran", saran);
			formData.set("kriteria", kriteria);

			hydrateMobileTerapiRowsFromTable();
			syncMobileTerapiRowsToTable();

			// Hilangkan tombol kirim selama proses berlangsung
			$('#save_konsul_nakes').prop('disabled', true).text('Mengirim...');


			// Ambil terapi dari tabel
			var terapiData = [];
			$("#tabelTerapi tbody tr").each(function(index, row) {
				var terapi = getTerapiName(row);
				var signa = getTerapiJumlah(row);
				var caraMinum = getTerapiCara(row); // Cara Minum
				var keterangan = getTerapiKeterangan(row);

				if (!isTerapiPlaceholder(terapi)) {
					terapiData.push({
						terapi: terapi,
						jumlah: signa,
						cara: caraMinum,
						keterangan: keterangan
					});
				}
			});

			// Tambahkan data terapi dalam bentuk string JSON
			formData.set("terapi", JSON.stringify(terapiData));

			$.ajax({
				url: "<?php echo base_url(); ?>konsultasi_nakes/save_konsultasi_nakes",
				method: "POST",
				data: formData,
				processData: false, // Wajib
				contentType: false, // Wajib
				success: function(response) {
					if (typeof response === 'string') {
						try {
							response = JSON.parse(response);
						} catch (e) {}
					}
					if (response == 1 || (response && response.status === 'success')) {
						Swal.fire({
							title: "Konsultasi selesai.",
							icon: "success",
							allowOutsideClick: false, // Prevent closing by clicking outside
							allowEscapeKey: false, // Prevent closing with the escape key
							showConfirmButton: false,
							timer: 2500,
							timerProgressBar: true
						}).then((result) => {
							// kirim pesan ke warga untuk menampilkan rating dari nakes melalui firebase
							const firebaseDb = getFirebaseDatabase();
							if (!firebaseDb) {
								if (result.dismiss === Swal.DismissReason.timer) {
									window.location.href = '../../home_nakes#riwayat_konsul_selesai';
								}
								return;
							}

							const munculPopUpWarga = firebaseDb.ref('rating').push();
							munculPopUpWarga.set({
								idReq: idReq,
								idUser: userId,
								idDokter: dokterId,
								timestamp: Date.now()
							}).then(() => {
								if (result.dismiss === Swal.DismissReason.timer) {
									window.location.href = '../../home_nakes#riwayat_konsul_selesai';
								}
							}).catch(() => {
								if (result.dismiss === Swal.DismissReason.timer) {
									window.location.href = '../../home_nakes#riwayat_konsul_selesai';
								}
							})
						});
					} else {
						const message = response && response.message ? response.message : "Terjadi kesalahan. Coba lagi.";
						Swal.fire("Konsultasi belum diselesaikan", message, "error");
						$('#save_konsul_nakes').prop('disabled', false).html('<i class="bi bi-check2-circle"></i> Selesaikan konsultasi');
					}
				},
				error: function(xhr, status, error) {
					let message = "Terjadi kesalahan. Coba lagi.";
					if (xhr.responseText) {
						try {
							const response = JSON.parse(xhr.responseText);
							if (response && response.message) {
								message = response.message;
							}
						} catch (e) {}
					}
					Swal.fire("Konsultasi belum diselesaikan", message, "error");
					$('#save_konsul_nakes').prop('disabled', false).html('<i class="bi bi-check2-circle"></i> Selesaikan konsultasi');
				}
			});
		});
	</script>

	<!-- preview image -->
	<script>
		function setClinicalAttachmentFeedback(message) {
			const feedback = document.getElementById('clinicalAttachmentFeedback');
			if (!feedback) {
				return;
			}

			feedback.textContent = message || '';
			feedback.classList.toggle('d-none', !message);
		}

		$('#file').change(function() {
			const input = this;
			const file = input.files && input.files[0];

			$('#preview-image').hide().attr('src', '#');

			if (file) {
				let reader = new FileReader();
				reader.onload = function(e) {
					if (!input.files || input.files[0] !== file) {
						return;
					}

					$('#preview-image')
						.attr('src', e.target.result)
						.show();
					setClinicalAttachmentFeedback('');
				};
				reader.onerror = function() {
					if (!input.files || input.files[0] !== file) {
						return;
					}

					$('#preview-image').hide().attr('src', '#');
					setClinicalAttachmentFeedback('Gambar belum dapat dimuat. Coba lagi.');
				};
				reader.onabort = reader.onerror;
				reader.readAsDataURL(file);
			} else {
				setClinicalAttachmentFeedback('');
			}
		});
	</script>

	<!-- get ICD10 -->
	<script>
		$(document).ready(function() {
			$('#diagnosa').autocomplete({
				source: function(request, response) {
					$.ajax({
						url: "<?= base_url('konsultasi_nakes/getICD_json'); ?>",
						type: 'GET',
						dataType: 'json',
						data: {
							term: request.term
						},
						success: function(data) {
							response($.map(data, function(item) {
								return {
									label: item.id_keluhan + ' - ' + item.nama_keluhan,
									value: item.nama_keluhan
								}
							}));
						}
					});
				},
				minLength: 2,
			});
		})
	</script>

	<!-- tambah bari dan hapus bari -->
	<script>
		function normalizeTerapiText(value) {
			return (value || "").replace(/\s+/g, " ").trim();
		}

		function isTerapiPlaceholder(value) {
			const normalized = normalizeTerapiText(value).toLowerCase();
			return normalized === "" || normalized === "terapi*";
		}

		function getTerapiName(row) {
			return normalizeTerapiText($(row).find("td:eq(1)").text());
		}

		function getTerapiJumlah(row) {
			return normalizeTerapiText($(row).find("td:eq(2) select").val());
		}

		function getTerapiCara(row) {
			return normalizeTerapiText($(row).find("td:eq(3) select").val());
		}

		function getTerapiKeterangan(row) {
			const value = normalizeTerapiText($(row).find("td:eq(4)").text());
			return value.toLowerCase() === "masukkan keterangan" ? "" : value;
		}

		function showTerapiRowMessage(message) {
			$('#terapiRowMessage').text(message).addClass('is-visible');
		}

		function clearTerapiRowMessage() {
			$('#terapiRowMessage').text('').removeClass('is-visible');
		}

		function validateTerapiRow(row) {
			if (isTerapiPlaceholder(getTerapiName(row))) {
				showTerapiRowMessage("Isi nama obat/terapi terlebih dahulu.");
				return false;
			}
			if (getTerapiJumlah(row) === "") {
				showTerapiRowMessage("Pilih frekuensi/signa obat.");
				return false;
			}
			if (getTerapiCara(row) === "") {
				showTerapiRowMessage("Pilih cara minum/cara pakai.");
				return false;
			}

			clearTerapiRowMessage();
			return true;
		}

		function tambahBaris(button) {
			let row = button.closest("tr");

			if (!validateTerapiRow(row)) {
				return;
			}

			let table = document.getElementById("tabelTerapi");
			let rowCount = table.rows.length;

			// Insert row setelah baris terakhir
			let newRow = table.insertRow(rowCount);

			let cellNo = newRow.insertCell(0);
			let cellTerapi = newRow.insertCell(1);
			let cellJumlahObat = newRow.insertCell(2);
			let cellCaraMinum = newRow.insertCell(3);
			let cellKeterangan = newRow.insertCell(4);
			let cellAksi = newRow.insertCell(5);
			cellNo.setAttribute("data-label", "No.");
			cellTerapi.setAttribute("data-label", "Terapi");
			cellJumlahObat.setAttribute("data-label", "Frekuensi / Signa");
			cellCaraMinum.setAttribute("data-label", "Cara Minum / Cara Pakai");
			cellKeterangan.setAttribute("data-label", "Keterangan");
			cellAksi.setAttribute("data-label", "Aksi");

			// Nomor otomatis (tanpa menghitung header)
			cellNo.innerHTML = rowCount - 1;

			// Buat sel yang bisa diedit
			cellTerapi.classList.add("terapi-autocomplete");
			cellTerapi.contentEditable = "true";
			cellTerapi.innerText = "Terapi*";

			cellJumlahObat.innerHTML = `
				<select class="form-select form-select-sm border-success">
					<option value="" selected disabled>Pilih frekuensi/signa</option>
					<option value="1x sehari">1x sehari</option>
					<option value="2x sehari">2x sehari</option>
					<option value="3x sehari">3x sehari</option>
					<option value="4x sehari">4x sehari</option>
				</select>
			`;

			cellCaraMinum.innerHTML = `
				<select class="form-select form-select-sm border-success">
					<option value="" selected disabled>Pilih cara pakai</option>
					<option value="Sesudah makan">Sesudah makan</option>
					<option value="Sebelum makan">Sebelum makan</option>
				</select>
			`;

			cellKeterangan.contentEditable = "true";
			cellKeterangan.innerText = "";

			// Tambahkan tombol tambah & hapus di baris baru
			let addButton = document.createElement("button");
			addButton.innerHTML = '<i class="bi bi-plus-circle"></i>'; // Add icon
			addButton.className = "btn btn-success btn-sm btn-terapi-action";
			addButton.type = "button";
			addButton.setAttribute("onclick", "tambahBaris(this)");

			let removeButton = document.createElement("button");
			removeButton.innerHTML = '<i class="bi bi-dash-circle"></i>'; // Remove icon
			removeButton.className = "btn btn-danger btn-sm btn-terapi-action";
			removeButton.type = "button";
			removeButton.setAttribute("onclick", "hapusBaris(this)");

			cellAksi.appendChild(addButton);
			cellAksi.appendChild(removeButton);

			updateNomorUrut();
			// Update tombol aksi di baris sebelumnya
			updateTombolAksi();
		}

		function hapusBaris(button) {
			let table = document.getElementById("tabelTerapi");
			let row = button.parentElement.parentElement;

			// Cegah menghapus baris jika hanya satu yang tersisa
			if (table.rows.length > 2) {
				row.remove();
				updateNomorUrut();
				updateTombolAksi();
			} else {
				alert("Baris pertama tidak bisa dihapus jika hanya ada satu data!");
			}
		}

		function updateNomorUrut() {
			let table = document.getElementById("tabelTerapi");

			// Update nomor urut, mulai dari index 1 (karena index 0 adalah header)
			for (let i = 1; i < table.rows.length; i++) {
				table.rows[i].cells[0].innerHTML = i;
			}
		}

		function updateTombolAksi() {
			let table = document.getElementById("tabelTerapi");
			let rows = table.rows;

			for (let i = 1; i < rows.length; i++) {
				let aksiCell = rows[i].cells[5];
				aksiCell.innerHTML = "";

				if (i === rows.length - 1) {
					// Baris terakhir punya tombol tambah dan hapus
					let addButton = document.createElement("button");
					addButton.innerHTML = '<i class="bi bi-plus-circle"></i>'; // Add icon
					addButton.className = "btn btn-success btn-sm btn-terapi-action";
					addButton.type = "button";
					addButton.setAttribute("onclick", "tambahBaris(this)");
					aksiCell.appendChild(addButton);

					let removeButton = document.createElement("button");
					removeButton.innerHTML = '<i class="bi bi-dash-circle"></i>'; // Remove icon
					removeButton.className = "btn btn-danger btn-sm btn-terapi-action";
					removeButton.type = "button";
					removeButton.setAttribute("onclick", "hapusBaris(this)");
					aksiCell.appendChild(removeButton);
				} else {
					// Baris lainnya hanya memiliki tombol hapus
					let removeButton = document.createElement("button");
					removeButton.innerHTML = '<i class="bi bi-dash-circle"></i>'; // Remove icon
					removeButton.className = "btn btn-danger btn-sm btn-terapi-action";
					removeButton.type = "button";
					removeButton.setAttribute("onclick", "hapusBaris(this)");
					aksiCell.appendChild(removeButton);
				}
			}
		}
	</script>

	<!-- <script>
		$(document).ready(function() {
			$(document).on("click", ".terapi-autocomplete", function() {
				let tdElement = $(this);

				// Jika input sudah ada di dalam td, hentikan agar tidak berulang
				if (tdElement.find("input").length > 0) {
					return;
				}

				let currentText = tdElement.text().trim();

				// Buat input sementara
				let input = $("<input>", {
					type: "text",
					value: currentText,
					class: "temp-input",
				});

				// Kosongkan <td> dan tambahkan input
				tdElement.empty().append(input);
				input.focus();

				// Aktifkan autocomplete pada input
				input.autocomplete({
					source: function(request, response) {
						$.ajax({
							url: "<?= base_url('konsultasi_nakes/get_terapi') ?>",
							type: "GET",
							dataType: "json",
							data: {
								cari: request.term
							},
							success: function(data) {
								response(data);
							}
						});
					},
					minLength: 1, // Mulai autocomplete setelah mengetik 1 karakter
					select: function(event, ui) {
						tdElement.text(ui.item.value); // Simpan nilai yang dipilih ke <td>
						return false; // Mencegah perubahan default input
					}
				});

				// Saat kehilangan fokus, hapus input dan simpan nilai
				input.on("blur", function() {
					tdElement.text(input.val() || currentText); // Simpan teks di <td>
				});

				// Tangani enter agar langsung menyimpan tanpa keluar form
				input.on("keypress", function(e) {
					if (e.which === 13) { // Enter key
						tdElement.text(input.val());
						input.blur();
						return false;
					}
				});
			});
		});
	</script> -->

	<script>
		$(document).ready(function() {
			let selectedTd = null;

			$(document).on("click", ".terapi-autocomplete", function() {
				selectedTd = $(this); // Simpan referensi <td> yang diklik
				let currentText = selectedTd.text().trim();
				$("#terapiInput").val(currentText);
				$("#terapiModal").modal("show");
			});

			// Inisialisasi autocomplete saat input aktif
			$("#terapiInput").autocomplete({
				source: function(request, response) {
					$.ajax({
						url: "<?= base_url('konsultasi_nakes/get_terapi') ?>",
						type: "GET",
						dataType: "json",
						data: {
							cari: request.term
						},
						success: function(data) {
							response(data);
						}
					});
				},
				minLength: 1
			});

			// Simpan nilai dari modal ke <td>
			$("#simpanTerapi").on("click", function() {
				if (selectedTd !== null) {
					let newValue = $("#terapiInput").val();
					selectedTd.text(newValue);
					$("#terapiModal").modal("hide");
				}
			});
		});
	</script>
</body>

</html>
