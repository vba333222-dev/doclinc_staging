<?php
$map_provider = $this->config->item('map_provider') ?: 'none';
$google_maps_api_key = $this->config->item('google_maps_api_key') ?: '';
$firebase_enabled = (bool) $this->config->item('firebase_enabled');
$legacy_superapp_url = $this->config->item('legacy_superapp_url') ?: '';

function getDuration($latitudeA, $longitudeA, $latitudeB, $longitudeB, $mode, $apiKey, $mapProvider)
{
	if ($mapProvider !== 'google' || empty($apiKey) || empty($latitudeA) || empty($longitudeA) || empty($latitudeB) || empty($longitudeB)) {
		return '';
	}

	$url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=$latitudeA,$longitudeA&destinations=$latitudeB,$longitudeB&mode=$mode&key=$apiKey";

	// Inisialisasi cURL
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

	// Jalankan cURL dan dapatkan respons
	$response = curl_exec($curl);

	// Periksa error pada cURL
	if (curl_errno($curl)) {
		curl_close($curl);
		return '';
	}

	curl_close($curl);

	// Decode JSON respons
	$data = json_decode($response, true);

	// Mengambil durasi dari respons
	return $data['rows'][0]['elements'][0]['duration']['text'] ?? '';

	// Mengembalikan durasi dalam format yang diinginkan
	// return $duration;
}

// Mencari jarak antara pusat kesehatan dan pahlawan 1

$dokter = $_GET['nama'] ?? '';
$latitudeA = '';
$longitudeA = '';
$dokter_id = $dokter;
$nama = '';
$token = '';
$ui_asset_base = base_url('assets/doclinc_ui/konsultasi_nakes/');

$this->load->model('Konsultasi_m');
$coordinate = $this->Konsultasi_m->getAllDataLocations($dokter);
foreach ($coordinate as $coordinate_item) {
	$latitudeA = $coordinate_item['latitude'];
	$longitudeA = $coordinate_item['longitude'];
}

$mode = 'driving';

// Cek apakah ada request POST dari JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Ambil nilai latitude dan longitude dari request POST
	$latitudeB = $_POST['latitude'] ?? '';
	$longitudeB = $_POST['longitude'] ?? '';

	header('Content-Type: application/json'); // Set header untuk JSON
	json_encode(['status' => 'success', 'latitude' => htmlspecialchars($latitudeB), 'longitude' => htmlspecialchars($longitudeB)]);

	echo getDuration($latitudeA, $longitudeA, $latitudeB, $longitudeB, $mode, $google_maps_api_key, $map_provider);

	exit; // Hentikan proses eksekusi
}
?>

<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>SehatGeh - Home</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" integrity="sha512-tS3S5qG0BlhnQROyJXvNjeEM4UpMXHrQfTGmbQ1gKmelCxlSEBUaxhRBj/EFTzpbP4RVSrpEikbmdJobCvhE3g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css" integrity="sha512-sMXtMNL1zRzolHYKEujM2AqCLUR9F2C4/05cdbxjjLSRvMQIciEPCQZo++nk7go3BtSuK9kfa/s+a4f4i5pLkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<style>
		:root {
			--dk-bg: #f7f7f7;
			--dk-text: #333333;
			--dk-muted: #8c8c8c;
			--dk-line: #cecece;
			--dk-green: #379a69;
			--dk-accent: #50bfa5;
		}

		* {
			box-sizing: border-box;
		}

		body.consultation-form-page {
			margin: 0;
			min-height: 100vh;
			background: #f0f5f2;
			color: var(--dk-text);
			font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		}

		.consult-shell {
			width: 100%;
			max-width: 414px;
			min-height: 100vh;
			margin: 0 auto;
			background: #f6faf7;
			overflow-x: hidden;
			position: relative;
			padding-bottom: 92px;
		}

		.consult-header {
			height: 64px;
			background: #ffffff;
			border-bottom: 1px solid #dce8e1;
			box-shadow: none;
			display: flex;
			align-items: center;
			justify-content: center;
			position: sticky;
			top: 0;
			z-index: 5;
		}

		.consult-back {
			position: absolute;
			left: 20px;
			top: 50%;
			transform: translateY(-50%);
			width: 32px;
			height: 32px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border: 0;
			background: transparent;
		}

		.consult-back img {
			width: 22px;
			height: 22px;
		}

		.consult-title {
			margin: 0;
			font-size: 21px;
			font-weight: 800;
			line-height: 28px;
			color: #004f2f;
		}

		.consult-main {
			padding: 22px 20px 24px;
		}

		.consult-card {
			background: #ffffff;
			border: 1px solid #dce8e1;
			border-radius: 18px;
			padding: 18px 16px;
			margin-bottom: 20px;
			box-shadow: 0 10px 24px rgba(10, 60, 38, 0.04);
		}

		.consult-card-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 18px;
		}

		.consult-card-title {
			margin: 0;
			font-size: 17px;
			line-height: 24px;
			font-weight: 700;
			color: #102018;
		}

		.consult-card-icon {
			width: 22px;
			height: 22px;
			object-fit: contain;
			flex: 0 0 auto;
		}

		.consult-field {
			width: 100%;
			min-height: 48px;
			border: 1px solid var(--dk-line);
			border-radius: 12px;
			padding: 12px 14px;
			background: #ffffff;
			color: var(--dk-text);
			font-size: 15px;
			line-height: 22px;
			box-shadow: none !important;
		}

		.consult-field::placeholder {
			color: var(--dk-muted);
			opacity: 1;
		}

		.consult-field:focus {
			border-color: var(--dk-accent);
			box-shadow: 0 0 0 3px rgba(80, 191, 165, 0.14) !important;
		}

		.consult-input-stack {
			display: grid;
			gap: 16px;
		}

		.consult-field-group {
			display: grid;
			gap: 8px;
		}

		.consult-field-label {
			margin: 0;
			color: var(--dk-text);
			font-size: 14px;
			font-weight: 600;
			line-height: 18px;
		}

		.consult-grid-two {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 20px;
		}

		.consult-field-large {
			height: 182px;
			resize: none;
		}

		.consult-field-keluhan {
			height: 126px;
			resize: none;
		}

		.dl-form-stepper {
			display: grid;
			grid-template-columns: minmax(58px, 1fr) 12px minmax(58px, 1fr) 12px minmax(58px, 1fr) 12px minmax(58px, 1fr) 12px minmax(58px, 1fr);
			align-items: start;
			gap: 6px;
			margin: 0 0 18px;
			overflow-x: auto;
			padding-bottom: 4px;
			scrollbar-width: none;
		}

		.dl-form-stepper::-webkit-scrollbar {
			display: none;
		}

		.dl-form-step {
			min-width: 58px;
			display: grid;
			justify-items: center;
			gap: 7px;
			color: #526158;
			font-size: 11px;
			font-weight: 700;
			line-height: 14px;
			text-align: center;
		}

		.dl-form-step span {
			width: 32px;
			height: 32px;
			border-radius: 50%;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			background: #dfe3e1;
			color: #526158;
			font-size: 12px;
			font-weight: 800;
		}

		.dl-form-step.is-active {
			color: #004f2f;
		}

		.dl-form-step.is-active span {
			background: #006a41;
			color: #ffffff;
		}

		.dl-form-step.is-complete {
			color: #006a41;
		}

		.dl-form-step.is-complete span {
			background: #004f2f;
			color: #ffffff;
		}

		.dl-form-step-line.is-complete {
			background: #006a41;
		}

		.dl-form-step-line {
			height: 2px;
			min-width: 12px;
			margin-top: 15px;
			background: #bec9bf;
			border-radius: 999px;
		}

		.dl-form-step-card {
			border-color: #dce8e1;
			padding: 18px 16px 16px;
		}

		.dl-form-step-heading {
			margin-bottom: 22px;
		}

		.dl-form-step-eyebrow {
			display: inline-flex;
			align-items: center;
			min-height: 28px;
			margin-bottom: 12px;
			padding: 5px 10px;
			border-radius: 999px;
			background: #e6f7ef;
			color: #006a41;
			font-size: 12px;
			font-weight: 800;
			line-height: 16px;
		}

		.dl-form-step-heading h2 {
			margin: 0 0 6px;
			color: #102018;
			font-size: 20px;
			font-weight: 800;
			line-height: 28px;
		}

		.dl-form-step-heading p {
			margin: 0;
			color: #526158;
			font-size: 14px;
			line-height: 20px;
		}

		.dl-form-inline-helper {
			display: flex;
			align-items: flex-start;
			gap: 8px;
			margin-top: 8px;
			color: #526158;
			font-size: 12px;
			line-height: 18px;
		}

		.dl-form-inline-helper i {
			margin-top: 2px;
			color: #006a41;
		}

		.dl-form-chip-row {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-top: 2px;
		}

		.dl-form-chip {
			min-height: 36px;
			display: inline-flex;
			align-items: center;
			padding: 8px 14px;
			border: 1px solid #dce8e1;
			border-radius: 999px;
			background: #ffffff;
			color: #526158;
			font-size: 13px;
			font-weight: 600;
			line-height: 18px;
		}

		.dl-form-privacy-card {
			position: relative;
			overflow: hidden;
			margin-bottom: 20px;
			padding: 18px 16px;
			border: 1px solid #d6f3e6;
			border-radius: 18px;
			background: #e8faf5;
		}

		.dl-form-privacy-card h3 {
			margin: 0 0 4px;
			color: #004f2f;
			font-size: 15px;
			font-weight: 800;
			line-height: 20px;
		}

		.dl-form-privacy-card p {
			max-width: 75%;
			margin: 0;
			color: #005142;
			font-size: 12px;
			line-height: 18px;
		}

		.dl-form-privacy-card i {
			position: absolute;
			right: -10px;
			bottom: -16px;
			color: rgba(0, 106, 65, 0.12);
			font-size: 82px;
		}

		.dl-form-next-note {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			margin: 0 0 10px;
			color: #526158;
			font-size: 12px;
			line-height: 16px;
			text-align: center;
		}

		.dl-form-next-note i {
			color: #006a41;
		}

		.dl-history-card {
			padding: 18px 16px 16px;
		}

		.dl-history-title-row {
			display: flex;
			align-items: flex-start;
			gap: 10px;
			margin-bottom: 20px;
		}

		.dl-history-title-icon {
			width: 36px;
			height: 36px;
			border-radius: 12px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			background: #e6f7ef;
			color: #006a41;
			flex: 0 0 auto;
		}

		.dl-history-title-row h2 {
			margin: 0 0 4px;
			color: #102018;
			font-size: 19px;
			font-weight: 800;
			line-height: 26px;
		}

		.dl-history-title-row p {
			margin: 0;
			color: #526158;
			font-size: 13px;
			line-height: 19px;
		}

		.dl-history-section {
			display: grid;
			gap: 14px;
			padding: 14px;
			border: 1px solid #e5eee8;
			border-radius: 16px;
			background: #fbfdfc;
		}

		.dl-history-section + .dl-history-section {
			margin-top: 14px;
		}

		.dl-history-section-title {
			display: flex;
			align-items: center;
			gap: 8px;
			margin: 0;
			color: #004f2f;
			font-size: 13px;
			font-weight: 800;
			line-height: 18px;
		}

		.dl-history-section-title i {
			color: #168a55;
		}

		.dl-history-helper-card {
			display: flex;
			align-items: flex-start;
			gap: 10px;
			margin-top: 14px;
			padding: 14px;
			border: 1px solid rgba(22, 138, 85, 0.12);
			border-radius: 16px;
			background: #e8faf5;
		}

		.dl-history-helper-card i {
			margin-top: 2px;
			color: #168a55;
		}

		.dl-history-helper-card strong {
			display: block;
			margin-bottom: 2px;
			color: #004f2f;
			font-size: 12px;
			line-height: 16px;
		}

		.dl-history-helper-card span {
			display: block;
			color: #526158;
			font-size: 12px;
			line-height: 18px;
		}

		.dl-location-card {
			padding: 18px 16px 16px;
		}

		.dl-location-map-panel {
			position: relative;
			min-height: 156px;
			margin: 0 -16px 16px;
			overflow: hidden;
			border-radius: 0 0 22px 22px;
			background:
				radial-gradient(circle at 50% 48%, rgba(0, 106, 65, 0.18) 0 16px, transparent 17px),
				linear-gradient(135deg, rgba(230, 247, 239, 0.9), rgba(220, 232, 225, 0.75));
			border-bottom: 1px solid #dce8e1;
		}

		.dl-location-map-panel::before,
		.dl-location-map-panel::after {
			content: "";
			position: absolute;
			inset: 18px -30px auto;
			height: 1px;
			background: rgba(111, 122, 113, 0.2);
			transform: rotate(-14deg);
			box-shadow: 0 42px 0 rgba(111, 122, 113, 0.18), 0 84px 0 rgba(111, 122, 113, 0.16);
		}

		.dl-location-map-panel::after {
			inset: 8px auto auto -40px;
			width: 130%;
			transform: rotate(24deg);
		}

		.dl-location-pin {
			position: absolute;
			left: 50%;
			top: 50%;
			width: 50px;
			height: 50px;
			border-radius: 50%;
			transform: translate(-50%, -50%);
			display: inline-flex;
			align-items: center;
			justify-content: center;
			background: rgba(0, 106, 65, 0.16);
			color: #006a41;
			z-index: 1;
		}

		.dl-location-pin::before {
			content: "";
			width: 16px;
			height: 16px;
			border: 3px solid #ffffff;
			border-radius: 50%;
			background: #006a41;
			box-shadow: 0 8px 18px rgba(0, 79, 47, 0.22);
		}

		.dl-location-sheet-handle {
			width: 42px;
			height: 4px;
			margin: -2px auto 14px;
			border-radius: 999px;
			background: #dce8e1;
		}

		.dl-location-status-card {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin-bottom: 16px;
		}

		.dl-location-status-pill {
			min-height: 30px;
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 6px 12px;
			border-radius: 999px;
			background: #e8faf5;
			color: #006a41;
			font-size: 12px;
			font-weight: 800;
			line-height: 16px;
		}

		.dl-location-status-pill.is-warning {
			background: #fff7e6;
			color: #8a5a00;
		}

		.dl-location-accuracy {
			color: #526158;
			font-size: 12px;
			line-height: 16px;
			text-align: right;
		}

		.dl-location-address-box {
			margin-bottom: 14px;
		}

		.dl-location-address-box h2 {
			margin: 0 0 6px;
			color: #102018;
			font-size: 19px;
			font-weight: 800;
			line-height: 26px;
		}

		.dl-location-address-box p {
			margin: 0;
			color: #526158;
			font-size: 13px;
			line-height: 19px;
		}

		.dl-location-puskesmas-card {
			display: flex;
			gap: 12px;
			margin: 16px 0;
			padding: 14px;
			border: 1px solid #dce8e1;
			border-radius: 16px;
			background: #f0f5f2;
		}

		.dl-location-puskesmas-icon {
			width: 40px;
			height: 40px;
			border-radius: 12px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			background: #e6f7ef;
			color: #006a41;
			flex: 0 0 auto;
		}

		.dl-location-puskesmas-card strong {
			display: block;
			margin-bottom: 3px;
			color: #004f2f;
			font-size: 14px;
			line-height: 20px;
		}

		.dl-location-puskesmas-card span {
			display: block;
			color: #526158;
			font-size: 12px;
			line-height: 18px;
		}

		.dl-location-helper {
			display: flex;
			gap: 10px;
			margin: 0 0 16px;
			color: #526158;
			font-size: 12px;
			line-height: 18px;
		}

		.dl-location-helper i {
			margin-top: 2px;
			color: #2563eb;
		}

		.dl-location-actions {
			display: grid;
			gap: 10px;
		}

		.dl-location-refresh {
			width: 100%;
			min-height: 48px;
			border: 1px solid #dce8e1;
			border-radius: 14px;
			background: #ffffff;
			color: #006a41;
			font-size: 15px;
			font-weight: 800;
			line-height: 20px;
		}

		.consult-field-address {
			height: 154px;
			resize: none;
			font-size: 16px;
			line-height: 24px;
		}

		.consult-backend-payload {
			position: absolute;
			left: -9999px;
			width: 1px;
			height: 1px;
			opacity: 0;
			pointer-events: none;
		}

		.consult-helper {
			display: block;
			margin: 8px 0 0;
			color: var(--dk-muted);
			font-size: 12px;
			line-height: 18px;
		}

		.consult-upload-stack {
			display: grid;
			gap: 20px;
		}

		.dl-media-card {
			padding: 18px 16px 16px;
		}

		.dl-media-intro {
			display: flex;
			gap: 10px;
			margin-bottom: 16px;
			padding: 14px;
			border: 1px solid #d6f3e6;
			border-radius: 16px;
			background: #e8faf5;
			color: #526158;
			font-size: 13px;
			line-height: 19px;
		}

		.dl-media-intro i {
			margin-top: 2px;
			color: #006a41;
		}

		.dl-media-upload-grid {
			display: grid;
			grid-template-columns: 1fr;
			gap: 14px;
		}

		.dl-upload-card {
			position: relative;
			min-height: 142px;
			border: 2px dashed #dce8e1;
			border-radius: 18px;
			background: #ffffff;
			display: grid;
			align-content: center;
			justify-items: center;
			gap: 8px;
			padding: 18px 14px;
			text-align: center;
			color: #004f2f;
			cursor: pointer;
			transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
		}

		.dl-upload-card input[type="file"] {
			position: absolute;
			inset: 0;
			width: 100%;
			height: 100%;
			opacity: 0;
			cursor: pointer;
		}

		.dl-upload-card:active {
			transform: scale(0.98);
		}

		.dl-upload-icon {
			width: 44px;
			height: 44px;
			border-radius: 14px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			background: #e6f7ef;
			color: #006a41;
			font-size: 19px;
		}

		.dl-upload-card strong {
			display: block;
			color: #102018;
			font-size: 15px;
			line-height: 20px;
		}

		.dl-upload-card span {
			display: block;
			color: #526158;
			font-size: 12px;
			line-height: 17px;
		}

		.dl-upload-action {
			min-height: 34px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			margin-top: 4px;
			padding: 7px 12px;
			border-radius: 999px;
			background: #006a41;
			color: #ffffff !important;
			font-size: 12px !important;
			font-weight: 800;
			line-height: 16px !important;
		}

		.dl-upload-file-name {
			margin: 8px 0 0;
			color: #526158;
			font-size: 12px;
			line-height: 18px;
		}

		.dl-media-helper {
			display: flex;
			gap: 10px;
			margin-top: 14px;
			color: #526158;
			font-size: 12px;
			line-height: 18px;
		}

		.dl-media-helper i {
			margin-top: 2px;
			color: #2563eb;
		}

		.dl-review-card {
			padding: 18px 16px 16px;
		}

		.dl-review-list {
			display: grid;
			gap: 12px;
		}

		.dl-review-item {
			display: grid;
			grid-template-columns: 38px 1fr auto;
			gap: 12px;
			align-items: start;
			padding: 14px;
			border: 1px solid #dce8e1;
			border-radius: 16px;
			background: #ffffff;
		}

		.dl-review-icon {
			width: 38px;
			height: 38px;
			border-radius: 12px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			background: #e6f7ef;
			color: #006a41;
		}

		.dl-review-item h3 {
			margin: 0 0 4px;
			color: #102018;
			font-size: 15px;
			font-weight: 800;
			line-height: 20px;
		}

		.dl-review-item p {
			margin: 0;
			color: #526158;
			font-size: 13px;
			line-height: 19px;
		}

		.dl-review-status {
			min-height: 28px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 5px 9px;
			border-radius: 999px;
			background: #f0f5f2;
			color: #526158;
			font-size: 11px;
			font-weight: 800;
			line-height: 14px;
			white-space: nowrap;
		}

		.dl-review-status.is-complete {
			background: #e8faf5;
			color: #006a41;
		}

		.dl-review-submit-note {
			display: flex;
			gap: 10px;
			margin-top: 16px;
			padding: 14px;
			border: 1px solid #d6f3e6;
			border-radius: 16px;
			background: #e8faf5;
			color: #005142;
			font-size: 12px;
			line-height: 18px;
		}

		.dl-review-submit-note i {
			margin-top: 2px;
			color: #006a41;
		}

		@media (min-width: 380px) {
			.dl-media-upload-grid {
				grid-template-columns: 1fr 1fr;
			}
		}

		.consult-upload-box {
			position: relative;
			min-height: 65px;
			border: 1px dashed #d0d0d0;
			border-radius: 10px;
			background: #eaeaea;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 14px;
			overflow: hidden;
			cursor: pointer;
		}

		.consult-upload-box input[type="file"] {
			position: absolute;
			inset: 0;
			width: 100%;
			height: 100%;
			opacity: 0;
			cursor: pointer;
		}

		.consult-upload-box img {
			width: 26px;
			height: 26px;
			object-fit: contain;
		}

		.consult-upload-text {
			color: var(--dk-text);
			font-size: 15px;
			line-height: 20px;
		}

		.consult-upload-text strong {
			color: #00796b;
			font-weight: 700;
		}

		.consult-preview {
			margin-top: 12px;
		}

		.consult-preview img,
		.consult-preview video {
			width: 100%;
			border-radius: 10px;
			border: 1px solid var(--dk-line);
		}

		.consult-consent {
			display: flex;
			align-items: center;
			gap: 15px;
			margin: 2px 0 30px;
			color: var(--dk-text);
			font-size: 16px;
			line-height: 22px;
		}

		.consult-consent .form-check-input {
			width: 30px;
			height: 30px;
			margin: 0;
			border: 2px solid var(--dk-line);
			border-radius: 5px;
			box-shadow: none;
			flex: 0 0 auto;
		}

		.consult-consent .form-check-input:checked {
			background-color: #ffffff;
			border-color: var(--dk-line);
			background-image: url("<?= html_escape($ui_asset_base . 'icon-check.svg'); ?>");
			background-size: 20px 20px;
		}

		.consult-submit {
			width: 100%;
			min-height: 52px;
			border: 0;
			border-radius: 999px;
			background: #006a41;
			color: #ffffff;
			font-size: 17px;
			font-weight: 700;
			line-height: 20px;
			box-shadow: none;
		}

		.consult-submit:disabled {
			opacity: 0.72;
		}

		.consult-bottom-nav {
			position: fixed;
			left: 50%;
			bottom: 0;
			transform: translateX(-50%);
			width: 100%;
			max-width: 414px;
			height: 69px;
			background: #ffffff;
			display: grid;
			grid-template-columns: 1fr 1fr auto 1fr;
			align-items: center;
			gap: 14px;
			padding: 8px 20px 11px;
			box-shadow: 0 -1px 8px rgba(0, 0, 0, 0.05);
			z-index: 10;
		}

		.consult-nav-link {
			min-width: 45px;
			min-height: 45px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			text-decoration: none;
			color: #606060;
		}

		.consult-nav-link img {
			width: 31px;
			height: 31px;
			object-fit: contain;
		}

		.consult-nav-link.active {
			min-width: 135px;
			height: 48px;
			padding: 0 17px;
			border-radius: 50px;
			background: #606060;
			color: #ffffff;
			gap: 8px;
			font-size: 15px;
			font-weight: 400;
		}

		.consult-nav-link.active img {
			width: 34px;
			height: 34px;
		}

		#map {
			display: none;
		}

		@media (max-width: 360px) {
			.consult-main {
				padding-left: 14px;
				padding-right: 14px;
			}

			.consult-card {
				padding-left: 16px;
				padding-right: 16px;
			}

			.consult-bottom-nav {
				padding-left: 12px;
				padding-right: 12px;
				gap: 8px;
			}

			.consult-nav-link.active {
				min-width: 118px;
				padding: 0 12px;
			}

			.consult-grid-two {
				grid-template-columns: 1fr;
				gap: 17px;
			}
		}
	</style>
</head>

<body class="consultation-form-page">
	<?php
	$this->load->model('Konsultasi_m');
	$hasil = $this->Konsultasi_m->getLocation($dokter);
	$foto = '';
	foreach ($getFotoDokter->result() as $row_foto) {
		$foto = $row_foto->foto ?? '';
	}
	foreach ($getDataDoctor->result() as $row) {
		$dokter_id = $row->userId;
		$nama = $row->nama;
	}
	?>
	<div class="consult-shell">
		<header class="consult-header">
			<a class="consult-back" href="<?= html_escape(base_url('home#konsultasi_kesehatan')); ?>" aria-label="Kembali">
				<img src="<?= html_escape($ui_asset_base . 'icon-back.svg'); ?>" alt="">
			</a>
			<h1 class="consult-title">Buat Konsultasi</h1>
		</header>
		<span id="result" class="d-none"></span>

		<main id="pahlawan_1" class="consult-main content animate__animated animate__fadeInUp animate__faster">
			<form id="form_konsul" action="<?= html_escape(base_url('konsultasi/save_konsultasi')); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" id="nama" value="<?= html_escape($_SESSION['username'] ?? ''); ?>" placeholder="Nama Lengkap" readonly>
				<input type="hidden" name="dokter_id" id="dokter_id" value="<?= html_escape($dokter_id); ?>" placeholder="Dokter ID" readonly>
				<input type="hidden" name="namadokter" id="namadokter" value="<?= html_escape($nama); ?>" placeholder="Dokter ID" readonly>

				<div class="dl-form-stepper" aria-label="Langkah pembuatan konsultasi">
					<div class="dl-form-step is-complete"><span><i class="fas fa-check" aria-hidden="true"></i></span>Keluhan</div>
					<div class="dl-form-step-line is-complete" aria-hidden="true"></div>
					<div class="dl-form-step is-complete"><span><i class="fas fa-check" aria-hidden="true"></i></span>Riwayat</div>
					<div class="dl-form-step-line is-complete" aria-hidden="true"></div>
					<div class="dl-form-step is-complete"><span><i class="fas fa-check" aria-hidden="true"></i></span>Lokasi</div>
					<div class="dl-form-step-line is-complete" aria-hidden="true"></div>
					<div class="dl-form-step is-complete"><span><i class="fas fa-check" aria-hidden="true"></i></span>Media</div>
					<div class="dl-form-step-line is-complete" aria-hidden="true"></div>
					<div class="dl-form-step is-active"><span>5</span>Review</div>
				</div>

				<section class="consult-card dl-form-step-card">
					<div class="dl-form-step-heading">
						<span class="dl-form-step-eyebrow">Langkah 1 dari 5</span>
						<h2>Apa keluhan Anda?</h2>
						<p>Ceritakan keluhan utama yang Anda rasakan agar petugas dapat memahami kondisi Anda.</p>
					</div>
					<div class="consult-input-stack">
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_keluhan_utama">Keluhan utama</label>
							<input type="text" id="ui_keluhan_utama" class="consult-field form-control" placeholder="Contoh: demam tinggi, sakit perut" required>
							<span class="dl-form-inline-helper"><i class="fas fa-info-circle" aria-hidden="true"></i>Tuliskan gejala yang paling terasa terlebih dahulu.</span>
						</div>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_lama_keluhan">Sejak kapan dirasakan?</label>
							<input type="text" id="ui_lama_keluhan" class="consult-field form-control" placeholder="Contoh: 2 hari yang lalu" required>
						</div>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_deskripsi_keluhan">Detail keluhan</label>
							<textarea id="ui_deskripsi_keluhan" class="consult-field consult-field-keluhan form-control" placeholder="Contoh: demam naik turun disertai mual dan nafsu makan menurun." required></textarea>
							<span class="dl-form-inline-helper"><i class="fas fa-pen" aria-hidden="true"></i>Gunakan bahasa sehari-hari. Lengkapi data yang ditandai sebelum mengirim.</span>
						</div>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_gejala_tambahan">Gejala tambahan <span class="fw-normal text-muted">(opsional)</span></label>
							<input type="text" id="ui_gejala_tambahan" class="consult-field form-control" placeholder="Contoh: mual, pusing, sesak">
							<div class="dl-form-chip-row" aria-hidden="true">
								<span class="dl-form-chip">Demam</span>
								<span class="dl-form-chip">Batuk</span>
								<span class="dl-form-chip">Nyeri</span>
								<span class="dl-form-chip">Mual</span>
								<span class="dl-form-chip">Sesak</span>
							</div>
						</div>
					</div>
					<textarea
						id="keluhan"
						name="keluhan"
						class="consult-backend-payload"
						readonly
						required></textarea>
				</section>

				<div class="dl-form-privacy-card">
					<h3>Privasi Terjamin</h3>
					<p>Keluhan Anda hanya dilihat oleh tenaga kesehatan resmi Doclinc untuk kebutuhan layanan.</p>
					<i class="fas fa-shield-alt" aria-hidden="true"></i>
				</div>

				<section class="consult-card dl-history-card">
					<div class="dl-form-step-heading">
						<span class="dl-form-step-eyebrow">Langkah 2 dari 5</span>
					</div>
					<div class="dl-history-title-row">
						<span class="dl-history-title-icon"><i class="fas fa-notes-medical" aria-hidden="true"></i></span>
						<div>
							<h2>Riwayat kesehatan</h2>
							<p>Informasi ini membantu petugas memahami kondisi Anda dengan lebih baik.</p>
						</div>
					</div>
					<div class="consult-input-stack">
						<div class="dl-history-section">
							<p class="dl-history-section-title"><i class="fas fa-heartbeat" aria-hidden="true"></i>Kondisi kesehatan</p>
							<div class="consult-field-group">
								<label class="consult-field-label" for="ui_penyakit_pernah">Riwayat penyakit</label>
								<input type="text" id="ui_penyakit_pernah" class="consult-field form-control" placeholder="Contoh: diabetes, hipertensi, asma, atau Tidak ada">
								<span class="dl-form-inline-helper"><i class="fas fa-info-circle" aria-hidden="true"></i>Tuliskan “Tidak ada” jika tidak memiliki riwayat penyakit.</span>
							</div>
							<div class="consult-field-group">
								<label class="consult-field-label" for="ui_riwayat_keluarga">Riwayat penyakit keluarga</label>
								<input type="text" id="ui_riwayat_keluarga" class="consult-field form-control" placeholder="Contoh: diabetes, jantung, atau Tidak ada">
							</div>
						</div>
						<div class="dl-history-section">
							<p class="dl-history-section-title"><i class="fas fa-pills" aria-hidden="true"></i>Alergi dan obat</p>
							<div class="consult-field-group">
								<label class="consult-field-label" for="ui_alergi">Alergi</label>
								<input type="text" id="ui_alergi" class="consult-field form-control" placeholder="Contoh: penisilin, telur, seafood, atau Tidak ada">
								<span class="dl-form-inline-helper"><i class="fas fa-shield-alt" aria-hidden="true"></i>Informasi alergi membantu petugas menghindari risiko pemberian obat.</span>
							</div>
							<div class="consult-field-group">
								<label class="consult-field-label" for="ui_obat_dikonsumsi">Obat yang sedang dikonsumsi</label>
								<input type="text" id="ui_obat_dikonsumsi" class="consult-field form-control" placeholder="Nama obat, vitamin, atau Tidak ada">
							</div>
						</div>
						<div class="dl-history-section">
							<p class="dl-history-section-title"><i class="fas fa-ruler-combined" aria-hidden="true"></i>Data tubuh</p>
							<div class="consult-grid-two">
								<div class="consult-field-group">
									<label class="consult-field-label" for="ui_tinggi_badan">Tinggi badan</label>
									<input type="text" id="ui_tinggi_badan" class="consult-field form-control" inputmode="decimal" placeholder="Cm">
								</div>
								<div class="consult-field-group">
									<label class="consult-field-label" for="ui_berat_badan">Berat badan</label>
									<input type="text" id="ui_berat_badan" class="consult-field form-control" inputmode="decimal" placeholder="Kg">
								</div>
							</div>
						</div>
						<div class="dl-history-helper-card">
							<i class="fas fa-info-circle" aria-hidden="true"></i>
							<div>
								<strong>Informasi Penting</strong>
								<span>Isi seperlunya. Jika tidak ada riwayat, alergi, atau obat, tuliskan “Tidak ada”.</span>
							</div>
						</div>
						<textarea
							id="data_penunjang"
							name="data_penunjang"
							class="consult-backend-payload"
							readonly
							required><?= !empty($getDataPenunjangById) ? html_escape($getDataPenunjangById) : ''; ?></textarea>
					</div>
				</section>

				<?php
				foreach ($getDataTokenDoctor->result() as $row) {
					$phone = $row->phone;
					$token = $row->token;
				}
				?>
				<input type="hidden" name="token" id="token" value="<?= html_escape($token); ?>" readonly>
				<input type="hidden" id="no_hp" value="<?= html_escape($_SESSION['no_hp'] ?? '') ?>" placeholder="Nomor HP" readonly>

				<section class="consult-card dl-media-card">
					<div class="dl-form-step-heading">
						<span class="dl-form-step-eyebrow">Langkah 4 dari 5</span>
						<h2>Tambahkan foto atau video</h2>
						<p>Lampiran membantu petugas memahami kondisi Anda. Lewati jika tidak diperlukan.</p>
					</div>
					<div class="dl-media-intro">
						<i class="fas fa-info-circle" aria-hidden="true"></i>
						<span>Gunakan lampiran untuk kondisi yang terlihat, misalnya ruam, luka, bengkak, atau gerakan yang sulit dijelaskan.</span>
					</div>
					<div class="consult-upload-stack">
						<div class="dl-media-upload-grid">
							<label class="dl-upload-card" for="file">
								<span class="dl-upload-icon"><i class="fas fa-camera" aria-hidden="true"></i></span>
								<strong>Foto kondisi/keluhan</strong>
								<span>Format gambar dari kamera atau galeri.</span>
								<span class="dl-upload-action">Pilih Foto</span>
								<input type="file" id="file" name="foto" accept="image/*">
							</label>

							<label class="dl-upload-card" for="file_video">
								<span class="dl-upload-icon"><i class="fas fa-video" aria-hidden="true"></i></span>
								<strong>Video pendukung</strong>
								<span>Tambahkan video jika membantu menjelaskan kondisi.</span>
								<span class="dl-upload-action">Pilih Video</span>
								<input type="file" id="file_video" name="video" accept="video/*">
							</label>
						</div>
						<p class="dl-upload-file-name" id="dl_photo_file_name">Foto: Belum ada file dipilih</p>
						<p class="dl-upload-file-name" id="dl_video_file_name">Video: Belum ada file dipilih</p>
						<input type="hidden" id="fileName" name="foto" class="form-control" readonly>
						<button type="button" onclick="window.flutter_inappwebview.callHandler('takePhoto')" hidden>
							Ambil Foto dari Kamera
						</button>
						<div id="previewContainer" class="consult-preview d-none">
							<img id="preview" src="" alt="Preview Foto">
						</div>

						<div id="previewVideoContainer" class="consult-preview d-none">
							<video id="previewVideo" controls></video>
						</div>
						<p class="dl-media-helper"><i class="fas fa-shield-alt" aria-hidden="true"></i><span>File bersifat pendukung dan tidak wajib. Pilih file lain jika file belum dapat digunakan.</span></p>
					</div>
				</section>

				<section class="consult-card dl-location-card">
					<div class="dl-form-step-heading">
						<span class="dl-form-step-eyebrow">Langkah 3 dari 5</span>
						<h2>Konfirmasi lokasi Anda</h2>
						<p>Lokasi digunakan untuk menentukan puskesmas dan petugas terdekat.</p>
					</div>
					<div class="dl-location-map-panel" aria-hidden="true">
						<span class="dl-location-pin"></span>
					</div>
					<div class="dl-location-sheet-handle" aria-hidden="true"></div>
					<div class="dl-location-status-card">
						<span class="dl-location-status-pill is-warning" id="dl_location_status"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i>Sedang mengambil lokasi...</span>
						<span class="dl-location-accuracy" id="dl_location_accuracy">Koordinat belum tersedia</span>
					</div>
					<div class="dl-location-address-box">
						<h2 id="dl_location_address_title">Lokasi belum tersedia</h2>
						<p id="dl_location_address_hint">Aktifkan izin lokasi agar petugas dapat menemukan alamat Anda.</p>
					</div>
					<div class="dl-location-puskesmas-card">
						<span class="dl-location-puskesmas-icon"><i class="fas fa-clinic-medical" aria-hidden="true"></i></span>
						<div>
							<strong>Puskesmas ditentukan otomatis</strong>
							<span>Berdasarkan area layanan dari lokasi Anda saat ini.</span>
						</div>
					</div>
					<p class="dl-location-helper"><i class="fas fa-info-circle" aria-hidden="true"></i><span>Pastikan titik lokasi sesuai alamat Anda. Jika belum tepat, tekan Perbarui Lokasi.</span></p>
					<label class="consult-field-label" for="address">Alamat terdeteksi</label>
					<textarea id="address" name="alamat" class="consult-field consult-field-address form-control" placeholder="Alamat akan terisi otomatis jika izin lokasi aktif"></textarea>
					<input type="text" class="d-none" name="lat" id="latitude" placeholder="Latitude">
					<input type="text" class="d-none" name="lng" id="longitude" placeholder="Longitude">
					<div id="map"></div>
					<div class="dl-location-actions">
						<button type="button" class="dl-location-refresh" id="dl_refresh_location"><i class="fas fa-location-arrow" aria-hidden="true"></i> Perbarui Lokasi</button>
					</div>
				</section>

				<input type="hidden" id="tanggal" name="tanggal" value="<?= html_escape(date('d-m-Y')); ?>" readonly>
				<input type="hidden" id="id_user" name="id_user" value="<?= html_escape($this->session->userdata('id')); ?>">

				<section class="consult-card dl-review-card">
					<div class="dl-form-step-heading">
						<span class="dl-form-step-eyebrow">Langkah 5 dari 5</span>
						<h2>Periksa kembali data Anda</h2>
						<p>Pastikan keluhan, riwayat, lokasi, dan lampiran sudah sesuai sebelum dikirim.</p>
					</div>
					<div class="dl-review-list">
						<div class="dl-review-item">
							<span class="dl-review-icon"><i class="fas fa-comment-medical" aria-hidden="true"></i></span>
							<div>
								<h3>Ringkasan keluhan</h3>
								<p id="dl_review_keluhan">Belum diisi</p>
							</div>
							<span class="dl-review-status" id="dl_review_keluhan_status">Belum diisi</span>
						</div>
						<div class="dl-review-item">
							<span class="dl-review-icon"><i class="fas fa-notes-medical" aria-hidden="true"></i></span>
							<div>
								<h3>Riwayat kesehatan</h3>
								<p id="dl_review_riwayat">Belum diisi</p>
							</div>
							<span class="dl-review-status" id="dl_review_riwayat_status">Belum diisi</span>
						</div>
						<div class="dl-review-item">
							<span class="dl-review-icon"><i class="fas fa-map-marker-alt" aria-hidden="true"></i></span>
							<div>
								<h3>Lokasi</h3>
								<p id="dl_review_lokasi">Lokasi belum tersedia</p>
							</div>
							<span class="dl-review-status" id="dl_review_lokasi_status">Belum siap</span>
						</div>
						<div class="dl-review-item">
							<span class="dl-review-icon"><i class="fas fa-paperclip" aria-hidden="true"></i></span>
							<div>
								<h3>Lampiran</h3>
								<p id="dl_review_media">Belum ada lampiran</p>
							</div>
							<span class="dl-review-status" id="dl_review_media_status">Opsional</span>
						</div>
					</div>
					<p class="dl-review-submit-note"><i class="fas fa-shield-alt" aria-hidden="true"></i><span>Konsultasi akan dikirim ke petugas berdasarkan lokasi Anda. Lengkapi data yang ditandai sebelum mengirim.</span></p>
				</section>

				<label class="consult-consent" for="kunjung">
					<input class="form-check-input" type="checkbox" role="switch" id="kunjung" name="kunjung">
					<span>Bersedia dikunjungi dokter</span>
				</label>

				<p class="dl-form-next-note"><i class="fas fa-lock" aria-hidden="true"></i>Data Anda aman digunakan untuk layanan kesehatan.</p>
				<button type="button" class="consult-submit" id="save_konsul">Kirim Konsultasi</button>
			</form>
		</main>

		<nav class="consult-bottom-nav" aria-label="Navigasi utama">
			<a class="consult-nav-link" href="<?= html_escape(base_url('home')); ?>" aria-label="Home">
				<img src="<?= html_escape($ui_asset_base . 'bottom-home.svg'); ?>" alt="">
			</a>
			<a class="consult-nav-link" href="<?= html_escape(base_url('home#riwayat')); ?>" aria-label="Medical Record">
				<img src="<?= html_escape($ui_asset_base . 'bottom-medical-record.svg'); ?>" alt="">
			</a>
			<a class="consult-nav-link active" href="<?= html_escape(base_url('home#konsultasi_kesehatan')); ?>" aria-label="Konsultasi">
				<img src="<?= html_escape($ui_asset_base . 'bottom-consultation.svg'); ?>" alt="">
				<span>Konsultasi</span>
			</a>
			<a class="consult-nav-link" href="<?= html_escape(base_url('home')); ?>" aria-label="Pasien">
				<img src="<?= html_escape($ui_asset_base . 'bottom-patient.svg'); ?>" alt="">
			</a>
		</nav>
	</div>

	<!-- Modal Bootstrap -->
	<div class="modal fade" id="modalDataPenunjang" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="modalLabel">Isi Data Penunjang</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<div class="modal-body">
					<label for="popupTextarea" class="form-label">Data Penunjang:</label>
					<textarea id="popupTextarea" class="form-control" style="height: 180px;">
**Riwayat Kesehatan**
Penyakit yang pernah diderita:
Alergi:
Obat yang sedang dikonsumsi:
Tinggi Badan:
Berat Badan:
        </textarea>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-success" id="simpanData">Simpan</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Modal Keluhan -->
	<div class="modal fade" id="modalKeluhan" tabindex="-1" aria-labelledby="modalKeluhanLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="modalKeluhanLabel">Isi Keluhan Pasien</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<div class="modal-body">
					<label for="popupKeluhan" class="form-label">Keluhan:</label>
					<textarea id="popupKeluhan" class="form-control" style="height: 180px;">
**Anamnesa**
Keluhan utama:
Riwayat penyakit keluarga:
Lama keluhan:
        </textarea>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-success" id="simpanKeluhan">Simpan</button>
				</div>
			</div>
		</div>
	</div>

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" integrity="sha512-bPs7Ae6pVvhOSiIcyUClR7/q2OAsRiovw4vAkX+zJbw3ShAeeqezq50RIIcIURq7Oa20rW2n2q+fyXBNcU9lrw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
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
		<?php if (!empty($legacy_superapp_url)) : ?>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/firebase-config.js'); ?>"></script>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/get-notif.js'); ?>"></script>
		<?php endif; ?>
	<?php endif; ?>

	<script>
		const mapProvider = <?= json_encode($map_provider); ?>;
		const firebaseEnabled = <?= json_encode($firebase_enabled); ?>;

		function hasGoogleMaps() {
			return mapProvider === 'google' && window.google && window.google.maps;
		}

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

		// validasi untuk mengecek bahwa jam kunjungan hanya dilakukan sebelum jam 17.00
		// Mendapatkan waktu saat ini
		const currentHour = new Date().toLocaleTimeString('id-ID', {
			timeZone: 'Asia/Jakarta',
			hour: '2-digit',
		})

		console.log(currentHour);


		// Validasi waktu
		if (currentHour >= '16') {
			Swal.fire({
				title: 'Peringatan',
				text: 'Konsultasi hanya dapat dilakukan via chat setelah jam 16.00. Kunjungan ke rumah tidak tersedia.',
				icon: 'warning',
				allowOutsideClick: false, // Prevent clicking outside the modal
				allowEscapeKey: false, // Prevent closing with the escape key
				showCancelButton: true,
				confirmButtonText: 'Batal',
				cancelButtonText: 'Lanjut'
			}).then((result) => {
				if (result.isConfirmed) {
					// alihkan ke halaman utama
					window.location.href = "<?= base_url('home#konsultasi_kesehatan') ?>";
				}
			})
		}
	</script>

	<script>
		const id = document.getElementById('nama').value;
		console.log('id nya adalah: ', id);
	</script>

	<!-- buatkan javascript untuk preview file yang diupload diatas -->
	<script>
		$(document).ready(function() {
			$('#file').change(function() {
				$('#preview').addClass('d-block');
				$('#preview').removeClass('d-none');
				var file = this.files[0];
				var reader = new FileReader();
				reader.onload = function(e) {
					$('#preview').attr('src', e.target.result);
				};
				reader.readAsDataURL(file);
			});
		});
	</script>

	<!-- popup isi data penungjang -->
	<script>
		// Ketika textarea utama diklik, buka modal
		document.getElementById('data_penunjang').addEventListener('click', function() {
			const modal = new bootstrap.Modal(document.getElementById('modalDataPenunjang'));
			modal.show();
		});

		// Ketika tombol simpan di modal ditekan
		document.getElementById('simpanData').addEventListener('click', function() {
			const data = document.getElementById('popupTextarea').value.trim();
			if (data === '') {
				alert('Silakan isi data penunjang terlebih dahulu.');
				return;
			}

			// Isi textarea utama
			document.getElementById('data_penunjang').value = data;

			// Tutup modal
			const modal = bootstrap.Modal.getInstance(document.getElementById('modalDataPenunjang'));
			modal.hide();
		});
	</script>

	<!-- popup isi data keluhan -->
	<script>
		// Buka modal saat textarea 'keluhan' diklik
		document.getElementById('keluhan').addEventListener('click', function() {
			const modal = new bootstrap.Modal(document.getElementById('modalKeluhan'));
			modal.show();
		});

		// Simpan isi popup ke textarea utama
		document.getElementById('simpanKeluhan').addEventListener('click', function() {
			const data = document.getElementById('popupKeluhan').value.trim();
			if (data === '') {
				alert('Silakan isi keluhan terlebih dahulu.');
				return;
			}

			document.getElementById('keluhan').value = data;

			// Tutup modal
			const modal = bootstrap.Modal.getInstance(document.getElementById('modalKeluhan'));
			modal.hide();
		});
	</script>

	<script>
		const namaDokter = document.getElementById('namadokter').value;
		console.log(namaDokter);
	</script>

	<!-- simpan konsultasi dan maps -->
	<script>
		function getStructuredValue(id) {
			const element = document.getElementById(id);
			return element ? element.value.trim() : '';
		}

		function optionalStructuredValue(id) {
			const value = getStructuredValue(id);
			return value !== '' ? value : '-';
		}

		function focusStructuredField(id) {
			const element = document.getElementById(id);
			if (!element) {
				return;
			}

			element.scrollIntoView({
				behavior: 'smooth',
				block: 'center'
			});
			element.focus();
		}

		function syncStructuredConsultationFields() {
			const keluhanUtama = getStructuredValue('ui_keluhan_utama');
			const lamaKeluhan = getStructuredValue('ui_lama_keluhan');
			const deskripsiKeluhan = getStructuredValue('ui_deskripsi_keluhan');

			if (keluhanUtama === '') {
				alert('Silakan isi keluhan utama.');
				focusStructuredField('ui_keluhan_utama');
				return false;
			}

			if (lamaKeluhan === '') {
				alert('Silakan isi lama keluhan.');
				focusStructuredField('ui_lama_keluhan');
				return false;
			}

			if (deskripsiKeluhan === '') {
				alert('Silakan isi deskripsi keluhan.');
				focusStructuredField('ui_deskripsi_keluhan');
				return false;
			}

			const keluhanPayload = [
				'**Anamnesa**',
				'Keluhan utama: ' + keluhanUtama,
				'Lama keluhan: ' + lamaKeluhan,
				'Gejala tambahan: ' + optionalStructuredValue('ui_gejala_tambahan'),
				'Deskripsi keluhan: ' + deskripsiKeluhan
			].join("\n");

			const dataPenunjangPayload = [
				'**Riwayat Kesehatan**',
				'Penyakit yang pernah diderita: ' + optionalStructuredValue('ui_penyakit_pernah'),
				'Riwayat penyakit keluarga: ' + optionalStructuredValue('ui_riwayat_keluarga'),
				'Alergi: ' + optionalStructuredValue('ui_alergi'),
				'Obat yang sedang dikonsumsi: ' + optionalStructuredValue('ui_obat_dikonsumsi'),
				'Tinggi badan: ' + optionalStructuredValue('ui_tinggi_badan') + ' Cm',
				'Berat badan: ' + optionalStructuredValue('ui_berat_badan') + ' Kg'
			].join("\n");

			document.getElementById('keluhan').value = keluhanPayload;
			document.getElementById('data_penunjang').value = dataPenunjangPayload;

			return true;
		}

		$('#save_konsul').click(function() {
			if (!syncStructuredConsultationFields()) {
				return;
			}

			var id_user = $('#id_user').val();
			var nama = $('#nama').val();
			var dokter_id = $('#dokter_id').val();
			var data_penunjang = $('#data_penunjang').val();
			var keluhan = $('#keluhan').val();
			var no_hp = $('#no_hp').val();
			var lat = $('#latitude').val();
			var lng = $('#longitude').val();
			var alamat = $('#address').val();
			var tanggal = $('#tanggal').val();
			var skipGeolocationRetry = $('#save_konsul').data('skipGeolocationRetry') === true;

			if ((lat === '' || lng === '') && !skipGeolocationRetry && navigator.geolocation) {
				$('#save_konsul').prop('disabled', true).text('Mengambil lokasi...');
				navigator.geolocation.getCurrentPosition(function(position) {
					setConsultationCoordinates(position.coords.latitude, position.coords.longitude);
					$('#save_konsul')
						.data('skipGeolocationRetry', true)
						.prop('disabled', false)
						.text('Kirim Konsultasi')
						.trigger('click');
				}, function() {
					$('#save_konsul')
						.data('skipGeolocationRetry', true)
						.prop('disabled', false)
						.text('Kirim Konsultasi')
						.trigger('click');
				}, {
					enableHighAccuracy: true,
					maximumAge: 30000,
					timeout: 5000
				});
				return;
			}

			$('#save_konsul').data('skipGeolocationRetry', false);

			if (data_penunjang === '') {
				alert('Silakan isi data penunjang terlebih dahulu.');
				return;
			} else if (keluhan === '') {
				alert('Silakan isi keluhan terlebih dahulu.');
				return;
			} else if (lat === '' || lng === '') {
				Swal.fire("Gagal!", "Lokasi pasien belum tersedia. Aktifkan izin lokasi lalu coba lagi.", "error");
				return;
			}

			var formData = new FormData(document.getElementById('form_konsul'));

			// Tambahkan foto dari kamera jika tersedia
			if (photoBlob) {
				formData.append('foto', photoBlob, photoFileName);
			}

			// Hilangkan tombol kirim selama proses berlangsung
			$('#save_konsul').prop('disabled', true).text('Mengirim...');

			$.ajax({
				url: "<?php echo base_url(); ?>konsultasi/save_konsultasi",
				method: "POST",
				data: formData,
				contentType: false,
				processData: false,
				async: false,
				dataType: 'json',
				success: function(response) {
					if (typeof response === 'string') {
						try {
							response = JSON.parse(response);
						} catch (e) {}
					}
					if (!(response == 1 || (response && response.status === 'success'))) {
						const message = response && response.message ? response.message : 'Konsultasi gagal dikirim';
						$('#save_konsul').prop('disabled', false).text('Kirim Form');
						Swal.fire("Gagal!", message, "error");
						return;
					}

					Swal.fire({
						title: "Berhasil!",
						icon: "success",
						allowOutsideClick: false, // Prevent closing by clicking outside
						allowEscapeKey: false, // Prevent closing with the escape key
						showConfirmButton: false,
						timer: 2500,
						timerProgressBar: true
					}).then((result) => {
						var token = $('#token').val();
						if (result.dismiss === Swal.DismissReason.timer) {
							const redirectUrl = token ? '<?php echo base_url(); ?>konsultasi/send?token=' + encodeURIComponent(token) : '<?php echo base_url(); ?>home#riwayat';

							const namaDokter = document.getElementById('namadokter').value;

							const nama = document.getElementById('nama');
							const keluhan = document.getElementById('keluhan');
							const firebaseDb = getFirebaseDatabase();
							if (!firebaseDb) {
								window.location.href = redirectUrl;
								return;
							}

							const newMessageRef = firebaseDb.ref("notifications").push();
							newMessageRef.set({
								sender: namaDokter,
								text: 'Ada Pasien yang membutuhkan bantuan atas nama ' + nama.value + ' dengan keluhan ' + keluhan.value,
								timestamp: Date.now()
							});

							const id_pasien = id_user;
							const id_dokter = dokter_id;
							const status = 'Pending';

							const newRequest = firebaseDb.ref("request").push();
							newRequest.set({
								id_pasien: id_pasien,
								id_dokter: id_dokter,
								keluhan: keluhan.value,
								lat: lat,
								lng: lng,
								alamat: alamat,
								tanggal: tanggal,
								status: status,
								timestamp: Date.now()
							}).then(() => {
								window.location.href = redirectUrl;
							}).catch(() => {
								window.location.href = redirectUrl;
							});
						}
					});
				},
				error: function() {
					// Jika terjadi error, munculkan kembali tombol kirim
					$('#save_konsul').prop('disabled', false).text('Kirim Form');
					alert('Terjadi kesalahan saat mengirim data. Silakan coba lagi.');
				}
			});

			// 			Swal.fire({
			// 				title: "Berhasil!",
			// 				icon: "success",
			// 				text: nama+", "+keluhan+", "+lat+", "+lng+", "+alamat+", "+tanggal,
			// 				showConfirmButton: false,
			// 				timer: 2500,
			// 				timerProgressBar: true
			// 			}).then((result) => {
			// 				if (result.dismiss === Swal.DismissReason.timer) {
			// 					window.location.href = 'home';
			// 				}
			// 			});
		});

		let map;
		let marker;
		let geocoder;

		function updateLocationStatus(state, message, detail) {
			const status = document.getElementById('dl_location_status');
			const accuracy = document.getElementById('dl_location_accuracy');
			const title = document.getElementById('dl_location_address_title');
			const hint = document.getElementById('dl_location_address_hint');

			if (!status) {
				return;
			}

			status.classList.toggle('is-warning', state !== 'success');
			if (state === 'success') {
				status.innerHTML = '<i class="fas fa-check-circle" aria-hidden="true"></i>Lokasi terdeteksi';
				if (title) title.textContent = 'Lokasi terdeteksi';
				if (hint) hint.textContent = message || 'Koordinat lokasi Anda sudah tersimpan.';
			} else {
				status.innerHTML = '<i class="fas fa-exclamation-circle" aria-hidden="true"></i>' + message;
				if (title) title.textContent = message;
				if (hint) hint.textContent = detail || 'Aktifkan izin lokasi agar petugas dapat menemukan alamat Anda.';
			}

			if (accuracy) {
				accuracy.textContent = detail || accuracy.textContent;
			}
		}

		function syncLocationDisplay(latitude, longitude) {
			const accuracy = document.getElementById('dl_location_accuracy');
			if (accuracy) {
				accuracy.textContent = Number(latitude).toFixed(5) + ', ' + Number(longitude).toFixed(5);
			}
		}

		function dlRefreshPatientLocation() {
			if (!navigator.geolocation) {
				updateLocationStatus('error', 'Lokasi belum tersedia', 'Browser ini belum mendukung akses lokasi.');
				return;
			}

			updateLocationStatus('loading', 'Sedang mengambil lokasi...', 'Mohon tunggu sebentar.');
			navigator.geolocation.getCurrentPosition(updateLocation, function(error) {
				if (error && error.code === error.PERMISSION_DENIED) {
					updateLocationStatus('error', 'Izin lokasi belum aktif', 'Aktifkan izin lokasi agar petugas dapat menemukan alamat Anda.');
					return;
				}
				updateLocationStatus('error', 'Lokasi belum tersedia', 'Coba perbarui lokasi atau pastikan sinyal perangkat stabil.');
			}, {
				enableHighAccuracy: true,
				maximumAge: 30000,
				timeout: 8000
			});
		}

		function setConsultationCoordinates(latitude, longitude) {
			if (!isFinite(latitude) || !isFinite(longitude)) {
				return;
			}

			const latitudeInput = document.getElementById('latitude');
			const longitudeInput = document.getElementById('longitude');
			if (latitudeInput) latitudeInput.value = latitude;
			if (longitudeInput) longitudeInput.value = longitude;
			document.querySelectorAll('[name="lat"]').forEach(function(input) {
				input.value = latitude;
			});
			document.querySelectorAll('[name="lng"]').forEach(function(input) {
				input.value = longitude;
			});
			syncLocationDisplay(latitude, longitude);
			if (typeof dlUpdateReviewSummary === 'function') {
				dlUpdateReviewSummary();
			}
		}

		function initPatientGeolocation() {
			if (!navigator.geolocation) {
				updateLocationStatus('error', 'Lokasi belum tersedia', 'Browser ini belum mendukung akses lokasi.');
				return;
			}

			updateLocationStatus('loading', 'Sedang mengambil lokasi...', 'Mohon tunggu sebentar.');

			navigator.geolocation.getCurrentPosition(updateLocation, function(error) {
				if (error && error.code === error.PERMISSION_DENIED) {
					updateLocationStatus('error', 'Izin lokasi belum aktif', 'Aktifkan izin lokasi agar petugas dapat menemukan alamat Anda.');
					return;
				}
				updateLocationStatus('error', 'Lokasi belum tersedia', 'Coba perbarui lokasi atau pastikan sinyal perangkat stabil.');
			}, {
				enableHighAccuracy: true,
				maximumAge: 30000,
				timeout: 8000
			});

			navigator.geolocation.watchPosition(updateLocation, function() {}, {
				enableHighAccuracy: true,
				maximumAge: 30000,
				timeout: 10000
			});
		}

		function initMap() {
			if (!hasGoogleMaps()) return;

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
		}

		function updateLocation(position) {
			const newLocation = {
				lat: position.coords.latitude,
				lng: position.coords.longitude,
			};

			setConsultationCoordinates(newLocation.lat, newLocation.lng);
			updateLocationStatus('success', 'Koordinat lokasi Anda sudah tersimpan.', Number(newLocation.lat).toFixed(5) + ', ' + Number(newLocation.lng).toFixed(5));

			if (!hasGoogleMaps() || !marker || !map) return;

			// Update posisi marker dan pusat peta
			marker.setPosition(newLocation);
			map.setCenter(newLocation);

			// Mendapatkan alamat dengan Geocoder
			getAddress(newLocation);
		}

		function sendData() {
			if (!hasGoogleMaps()) return;

			// Ambil nilai dari input
			const latitude = document.getElementById('latitude').value;
			const longitude = document.getElementById('longitude').value;

			// Kirim data ke PHP menggunakan fetch
			fetch('', { // Kirim ke halaman yang sama
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded'
					},
					body: 'latitude=' + encodeURIComponent(latitude) + '&longitude=' + encodeURIComponent(longitude)
				})
				.then(response => response.text())
				.then(result => {
					// Tampilkan respon dari PHP
					document.getElementById('result').innerHTML = result;
					console.log("Data telah dikirim.");
				})
				.catch(error => console.error("Error:", error));
		}

		function getAddress(location) {
			if (!hasGoogleMaps() || !geocoder) return;

			geocoder.geocode({
				location: location
			}, (results, status) => {
				if (status === "OK") {
					if (results[0]) {
						document.getElementById("address").value = results[0].formatted_address;
						const title = document.getElementById('dl_location_address_title');
						const hint = document.getElementById('dl_location_address_hint');
						if (title) title.textContent = results[0].formatted_address;
						if (hint) hint.textContent = 'Alamat ini digunakan untuk menentukan puskesmas dan petugas terdekat.';
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
					alert("Location information is unavailable.");
					break;
				case error.TIMEOUT:
					alert("The request to get user location timed out.");
					break;
				case error.UNKNOWN_ERROR:
					alert("An unknown error occurred.");
					break;
			}
		}

		window.onload = function() {
			initPatientGeolocation();
			initMap();

			const refreshLocationButton = document.getElementById('dl_refresh_location');
			if (refreshLocationButton) {
				refreshLocationButton.addEventListener('click', dlRefreshPatientLocation);
			}

			setTimeout(() => {
				sendData();
			}, 100);
		}

		function dlTextValue(id) {
			const element = document.getElementById(id);
			return element && element.value ? element.value.trim() : '';
		}

		function dlSetReviewStatus(textId, statusId, value, completeLabel) {
			const text = document.getElementById(textId);
			const status = document.getElementById(statusId);
			const isComplete = value !== '';

			if (text) {
				text.textContent = isComplete ? value : 'Belum diisi';
			}

			if (status) {
				status.textContent = isComplete ? completeLabel : 'Belum diisi';
				status.classList.toggle('is-complete', isComplete);
			}
		}

		function dlUpdateReviewSummary() {
			const keluhan = dlTextValue('ui_keluhan_utama');
			const lamaKeluhan = dlTextValue('ui_lama_keluhan');
			const riwayat = dlTextValue('ui_penyakit_pernah');
			const alergi = dlTextValue('ui_alergi');
			const alamat = dlTextValue('address');
			const latitude = dlTextValue('latitude');
			const longitude = dlTextValue('longitude');
			const foto = document.getElementById('file');
			const video = document.getElementById('file_video');

			const keluhanSummary = keluhan !== '' ? keluhan + (lamaKeluhan !== '' ? ' - ' + lamaKeluhan : '') : '';
			const riwayatSummary = riwayat !== '' || alergi !== '' ? 'Riwayat: ' + (riwayat || 'Tidak ada') + '. Alergi: ' + (alergi || 'Tidak ada') : '';
			const lokasiSummary = alamat !== '' ? alamat : (latitude !== '' && longitude !== '' ? latitude + ', ' + longitude : '');
			const mediaParts = [];

			if (foto && foto.files && foto.files[0]) {
				mediaParts.push('Foto: ' + foto.files[0].name);
			}

			if (video && video.files && video.files[0]) {
				mediaParts.push('Video: ' + video.files[0].name);
			}

			dlSetReviewStatus('dl_review_keluhan', 'dl_review_keluhan_status', keluhanSummary, 'Terisi');
			dlSetReviewStatus('dl_review_riwayat', 'dl_review_riwayat_status', riwayatSummary, 'Terisi');
			dlSetReviewStatus('dl_review_lokasi', 'dl_review_lokasi_status', lokasiSummary, 'Siap');

			const mediaText = document.getElementById('dl_review_media');
			const mediaStatus = document.getElementById('dl_review_media_status');
			if (mediaText) {
				mediaText.textContent = mediaParts.length ? mediaParts.join(' | ') : 'Belum ada lampiran';
			}
			if (mediaStatus) {
				mediaStatus.textContent = mediaParts.length ? 'Sudah ditambahkan' : 'Opsional';
				mediaStatus.classList.toggle('is-complete', mediaParts.length > 0);
			}
		}

		['ui_keluhan_utama', 'ui_lama_keluhan', 'ui_penyakit_pernah', 'ui_alergi', 'address', 'latitude', 'longitude'].forEach(function(id) {
			const element = document.getElementById(id);
			if (element) {
				element.addEventListener('input', dlUpdateReviewSummary);
				element.addEventListener('change', dlUpdateReviewSummary);
			}
		});

		document.addEventListener('DOMContentLoaded', dlUpdateReviewSummary);
	</script>

	<!-- ambil foto dari kamera dengan flutter native -->
	<script>
		let photoBlob = null;
		let photoFileName = '';

		function onImageCaptured(dataUrl, fileName) {
			const preview = document.getElementById('preview');
			const container = document.getElementById('previewContainer');
			const nameElement = document.getElementById('fileName');
			const displayName = document.getElementById('dl_photo_file_name');

			preview.src = dataUrl;
			container.classList.remove('d-none');
			nameElement.value = fileName;
			nameElement.classList.remove('d-none');
			if (displayName) {
				displayName.textContent = 'Foto: ' + fileName;
			}
			if (typeof dlUpdateReviewSummary === 'function') {
				dlUpdateReviewSummary();
			}

			photoFileName = fileName;

			// Ubah dataURL ke blob
			fetch(dataUrl)
				.then(res => res.blob())
				.then(blob => {
					photoBlob = new File([blob], fileName, {
						type: blob.type
					});
				});
		}
	</script>

	<!-- preview foto dan video -->
	<script>
		document.getElementById('file').addEventListener('change', function(event) {
			const preview = document.getElementById('preview');
			const container = document.getElementById('previewContainer');
			const file = event.target.files[0];

			if (file) {
				const reader = new FileReader();
				reader.onload = function(e) {
					preview.src = e.target.result;
					container.classList.remove('d-none');
				};
				reader.readAsDataURL(file);
				const displayName = document.getElementById('dl_photo_file_name');
				if (displayName) {
					displayName.textContent = 'Foto: ' + file.name;
				}
				if (typeof dlUpdateReviewSummary === 'function') {
					dlUpdateReviewSummary();
				}
			}
		});

		document.getElementById('file_video').addEventListener('change', function(event) {
			const preview = document.getElementById('previewVideo');
			const container = document.getElementById('previewVideoContainer');
			const file = event.target.files[0];

			if (file) {
				const reader = new FileReader();
				reader.onload = function(e) {
					preview.src = e.target.result;
					container.classList.remove('d-none');
				};
				reader.readAsDataURL(file);
				const displayName = document.getElementById('dl_video_file_name');
				if (displayName) {
					displayName.textContent = 'Video: ' + file.name;
				}
				if (typeof dlUpdateReviewSummary === 'function') {
					dlUpdateReviewSummary();
				}
			}
		});
	</script>

</body>

</html>
