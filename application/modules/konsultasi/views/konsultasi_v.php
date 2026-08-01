<?php
$map_provider = $this->config->item('map_provider') ?: 'none';
$google_maps_api_key = $this->config->item('google_maps_api_key') ?: '';
$firebase_enabled = (bool) $this->config->item('firebase_enabled');
$legacy_superapp_url = $this->config->item('legacy_superapp_url') ?: '';
$clinical_suggestions_enabled = (bool) $this->config->item('clinical_suggestions_enabled');
$clinical_suggestions_endpoint = base_url('clinical-suggestions');

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
$master_gejala_keluhan_options = isset($master_gejala_keluhan_options) && is_array($master_gejala_keluhan_options)
	? $master_gejala_keluhan_options
	: array();

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
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DocLink - Konsultasi</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
	<?php if ($clinical_suggestions_enabled) : ?>
		<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-clinical-suggestions.css')); ?>">
	<?php endif; ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" integrity="sha512-tS3S5qG0BlhnQROyJXvNjeEM4UpMXHrQfTGmbQ1gKmelCxlSEBUaxhRBj/EFTzpbP4RVSrpEikbmdJobCvhE3g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css" integrity="sha512-sMXtMNL1zRzolHYKEujM2AqCLUR9F2C4/05cdbxjjLSRvMQIciEPCQZo++nk7go3BtSuK9kfa/s+a4f4i5pLkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<style>
		:root {
			--dk-bg: var(--dl-bg, var(--dl-color-surface-soft, #f4f8f6));
			--dk-text: var(--dl-text, var(--dl-color-text, #1f2a24));
			--dk-muted: var(--dl-muted, var(--dl-color-muted, #66756d));
			--dk-line: var(--dl-line, var(--dl-color-border, #e7ece9));
			--dk-green: var(--dl-mint, var(--dl-color-primary, #379a69));
			--dk-accent: var(--dl-color-primary-soft, #e8f7f0);
			--dk-surface: var(--dl-surface, var(--dl-color-surface, #ffffff));
			--dk-soft: var(--dl-soft, var(--dl-color-surface-soft, #f4f8f6));
		}

		* {
			box-sizing: border-box;
		}

		body.consultation-form-page {
			margin: 0;
			min-height: 100vh;
			background: var(--dk-bg);
			color: var(--dk-text);
			font-family: var(--dl-font-family, 'Manrope', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
			font-size: var(--dl-font-size-md, 13px);
			line-height: var(--dl-line-height-normal, 1.45);
		}

		.consult-shell {
			width: 100%;
			max-width: 414px;
			min-height: 100vh;
			margin: 0 auto;
			background: var(--dk-bg);
			overflow-x: hidden;
			position: relative;
			padding-bottom: 88px;
		}

		.consult-header {
			min-height: 64px;
			background: #edfdf3;
			border-bottom: 1px solid var(--dk-line);
			box-shadow: 0 4px 16px rgba(24, 60, 47, .06);
			display: flex;
			align-items: center;
			justify-content: flex-start;
			position: sticky;
			top: 0;
			z-index: 5;
			padding: 10px 20px;
		}

		.consult-title-block {
			min-width: 0;
			display: grid;
			gap: 2px;
		}

		.consult-eyebrow {
			color: var(--dk-muted);
			font-size: var(--dl-font-size-sm, 12px);
			font-weight: 600;
			line-height: 1.25;
		}

		.consult-title {
			margin: 0;
			font-size: var(--dl-font-size-lg, 15px);
			font-weight: 800;
			line-height: 1.25;
			color: var(--dk-text);
		}

		.consult-main {
			padding: 16px 20px 24px;
		}

		.consult-card {
			background: var(--dk-surface);
			border: 1px solid var(--dk-line);
			border-radius: var(--dl-radius-xl, 16px);
			padding: 15px;
			margin-bottom: var(--dl-space-3, 12px);
			box-shadow: var(--dl-shadow-card, 0 2px 8px rgba(24, 60, 47, .05));
		}

		.consult-card-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 12px;
		}

		.consult-card-title {
			margin: 0;
			font-size: var(--dl-font-size-lg, 15px);
			line-height: 1.3;
			font-weight: 800;
			color: var(--dk-text);
		}

		.consult-card-icon {
			width: 24px;
			height: 24px;
			object-fit: contain;
			flex: 0 0 auto;
			border-radius: 999px;
			padding: 4px;
			background: var(--dk-soft);
		}

		.consult-field {
			width: 100%;
			min-height: 42px;
			border: 1px solid var(--dk-line);
			border-radius: var(--dl-radius-md, 12px);
			padding: 10px 12px;
			background: var(--dk-surface);
			color: var(--dk-text);
			font-size: var(--dl-font-size-md, 13px);
			font-weight: 500;
			line-height: var(--dl-line-height-normal, 1.45);
			box-shadow: none !important;
		}

		.consult-field::placeholder {
			color: var(--dk-muted);
			opacity: 1;
			font-weight: 500;
		}

		.consult-field:focus {
			border-color: var(--dk-accent);
			box-shadow: 0 0 0 3px rgba(80, 191, 165, 0.14) !important;
		}

		.consult-input-stack {
			display: grid;
			gap: 11px;
		}

		.consult-field-group {
			display: grid;
			gap: 5px;
		}

		.consult-field-label {
			margin: 0;
			color: var(--dk-muted);
			font-size: var(--dl-font-size-sm, 12px);
			font-weight: 700;
			line-height: 1.3;
		}

		.consult-grid-two {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 8px;
		}

		.consult-field-large {
			height: 182px;
			resize: none;
		}

		.consult-field-keluhan {
			min-height: 88px;
			height: 88px;
			resize: none;
		}

		.consult-field-address {
			min-height: 74px;
			height: 74px;
			resize: none;
			font-size: 13px;
			line-height: 1.4;
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
			margin: 0;
			color: var(--dk-muted);
			font-size: var(--dl-font-size-sm, 12px);
			font-weight: 500;
			line-height: 1.45;
		}

		.consult-consent {
			display: flex;
			align-items: center;
			gap: 15px;
			margin: 4px 0 12px;
			color: var(--dk-text);
			font-size: 13px;
			font-weight: 600;
			line-height: 1.35;
		}

		.consult-consent .form-check-input {
			width: 22px;
			height: 22px;
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
			min-height: 42px;
			border: 0;
			border-radius: var(--dl-radius-lg, 14px);
			background: var(--dk-green);
			color: #ffffff;
			font-size: var(--dl-font-size-md, 13px);
			font-weight: 800;
			line-height: 1.2;
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
			background: #ffffff;
			display: grid;
			grid-template-columns: repeat(4, minmax(0, 1fr));
			align-items: center;
			gap: 6px;
			padding: 8px 12px calc(8px + env(safe-area-inset-bottom, 0px));
			border-top: 1px solid #eef4f1;
			box-shadow: 0 -8px 20px rgba(24, 60, 47, .08);
			z-index: 10;
		}

		.consult-nav-link {
			min-width: 0;
			min-height: 54px;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			gap: 3px;
			border-radius: 14px;
			text-decoration: none;
			color: #6b7f76;
			font-size: 11px;
			font-weight: 700;
			line-height: 1.1;
		}

		.consult-nav-link i {
			font-size: 20px;
			line-height: 1;
		}

		.consult-nav-link.active {
			background: #89f6da;
			color: #00725e;
		}

		#map {
			display: none;
		}

		@media (max-width: 380px) {
			.consult-main {
				padding-left: 12px;
				padding-right: 12px;
			}

			.consult-card {
				padding-left: 14px;
				padding-right: 14px;
			}

			.consult-bottom-nav {
				padding-left: 8px;
				padding-right: 8px;
				gap: 4px;
			}

			.consult-grid-two {
				grid-template-columns: 1fr;
				gap: 10px;
			}
		}
	</style>
</head>

<body class="consultation-form-page dl-dashboard-body">
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
			<div class="consult-title-block">
				<span class="consult-eyebrow">Form konsultasi</span>
				<h1 class="consult-title">Konsultasi</h1>
			</div>
		</header>
		<span id="result" class="d-none"></span>

		<main id="pahlawan_1" class="consult-main content">
			<form id="form_konsul" action="<?= html_escape(base_url('konsultasi/save_konsultasi')); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" id="nama" value="<?= html_escape($_SESSION['username'] ?? ''); ?>" placeholder="Nama Lengkap" readonly>
				<input type="hidden" name="dokter_id" id="dokter_id" value="<?= html_escape($dokter_id); ?>" placeholder="Dokter ID" readonly>
				<input type="hidden" name="namadokter" id="namadokter" value="<?= html_escape($nama); ?>" placeholder="Dokter ID" readonly>

				<section class="consult-card">
					<div class="consult-card-header">
						<h2 class="consult-card-title">Riwayat kesehatan</h2>
						<img class="consult-card-icon" src="<?= html_escape($ui_asset_base . 'icon-minus.svg'); ?>" alt="">
					</div>
					<div class="consult-input-stack">
						<small class="consult-helper">Pastikan lokasi Anda aktif.</small>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_penyakit_pernah">Riwayat penyakit</label>
							<input type="text" id="ui_penyakit_pernah" class="consult-field form-control" placeholder="Contoh: asma, hipertensi, atau kosongkan jika tidak ada">
						</div>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_riwayat_keluarga">Riwayat penyakit keluarga</label>
							<input type="text" id="ui_riwayat_keluarga" class="consult-field form-control" placeholder="Contoh: diabetes, jantung, atau kosongkan">
						</div>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_alergi">Alergi</label>
							<input type="text" id="ui_alergi" class="consult-field form-control" placeholder="Contoh: obat, makanan, atau kosongkan">
						</div>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_obat_dikonsumsi">Obat saat ini</label>
							<input type="text" id="ui_obat_dikonsumsi" class="consult-field form-control" placeholder="Nama obat jika ada">
						</div>
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
						<textarea
							id="data_penunjang"
							name="data_penunjang"
							class="consult-backend-payload"
							readonly
							required><?= !empty($getDataPenunjangById) ? html_escape($getDataPenunjangById) : ''; ?></textarea>
					</div>
				</section>

				<section class="consult-card">
					<div class="consult-card-header">
						<h2 class="consult-card-title">Keluhan</h2>
						<img class="consult-card-icon" src="<?= html_escape($ui_asset_base . 'icon-minus.svg'); ?>" alt="">
					</div>
					<div class="consult-input-stack">
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_gejala_keluhan_utama">Keluhan utama</label>
							<?php if ($clinical_suggestions_enabled) : ?>
							<input type="text" id="ui_gejala_keluhan_utama" name="gejala_utama" class="consult-field form-control"
								placeholder="Ketik keluhan, minimal 2 karakter" data-clinical-suggestion
								data-clinical-suggestion-type="complaint"
								data-clinical-suggestion-endpoint="<?= html_escape($clinical_suggestions_endpoint); ?>">
							<?php else : ?>
							<select id="ui_gejala_keluhan_utama" name="gejala_utama" class="consult-field form-control">
								<option value="">Pilih keluhan</option>
								<?php foreach ($master_gejala_keluhan_options as $option): ?>
									<?php $option_name = trim((string) ($option->nama_keluhan ?? '')); ?>
									<?php if ($option_name === '') continue; ?>
									<option value="<?= html_escape($option_name); ?>"><?= html_escape($option_name); ?></option>
								<?php endforeach; ?>
							</select>
							<?php endif; ?>
						</div>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_keluhan_utama">Detail keluhan singkat</label>
							<input type="text" id="ui_keluhan_utama" class="consult-field form-control" placeholder="Contoh: demam, batuk, nyeri perut">
						</div>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_lama_keluhan">Lama keluhan</label>
							<input type="text" id="ui_lama_keluhan" class="consult-field form-control" placeholder="Contoh: 2 hari, 1 minggu" required>
						</div>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_gejala_tambahan">Gejala tambahan</label>
							<input type="text" id="ui_gejala_tambahan" class="consult-field form-control" placeholder="Contoh: mual, pusing, sesak, atau kosongkan">
						</div>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_deskripsi_keluhan">Detail keluhan</label>
							<textarea id="ui_deskripsi_keluhan" class="consult-field consult-field-keluhan form-control" placeholder="Ceritakan keluhan Anda" required></textarea>
						</div>
					</div>
					<textarea
						id="keluhan"
						name="keluhan"
						class="consult-backend-payload"
						readonly
						required></textarea>
				</section>

				<?php
				foreach ($getDataTokenDoctor->result() as $row) {
					$phone = $row->phone;
					$token = $row->token;
				}
				?>
				<input type="hidden" name="token" id="token" value="<?= html_escape($token); ?>" readonly>
				<input type="hidden" id="no_hp" value="<?= html_escape($_SESSION['no_hp'] ?? '') ?>" placeholder="Nomor HP" readonly>

				<section class="consult-card">
					<div class="consult-card-header">
						<h2 class="consult-card-title">Data diri</h2>
						<img class="consult-card-icon" src="<?= html_escape($ui_asset_base . 'icon-plus.svg'); ?>" alt="">
					</div>
					<textarea id="address" name="alamat" class="consult-field consult-field-address form-control" placeholder="Alamat"></textarea>
					<input type="text" class="d-none" name="lat" id="latitude" placeholder="Latitude">
					<input type="text" class="d-none" name="lng" id="longitude" placeholder="Longitude">
					<div id="map"></div>
				</section>

				<input type="hidden" id="tanggal" name="tanggal" value="<?= html_escape(date('d-m-Y')); ?>" readonly>
				<input type="hidden" id="id_user" name="id_user" value="<?= html_escape($this->session->userdata('id')); ?>">

				<label class="consult-consent" for="kunjung">
					<input class="form-check-input" type="checkbox" role="switch" id="kunjung" name="kunjung">
					<span>Bersedia dikunjungi Nakes</span>
				</label>

				<button type="button" class="consult-submit" id="save_konsul">Kirim</button>
			</form>
		</main>

		<nav class="consult-bottom-nav" aria-label="Navigasi utama">
			<a class="consult-nav-link" href="<?= html_escape(base_url('home')); ?>" aria-label="Beranda">
				<i class="fas fa-home"></i>
				<span>Beranda</span>
			</a>
			<a class="consult-nav-link active" href="<?= html_escape(base_url('home#konsultasi_kesehatan')); ?>" aria-label="Konsultasi">
				<i class="fas fa-user-md"></i>
				<span>Konsultasi</span>
			</a>
			<a class="consult-nav-link" href="<?= html_escape(base_url('home#riwayat')); ?>" aria-label="Riwayat">
				<i class="fas fa-file-medical"></i>
				<span>Riwayat</span>
			</a>
			<a class="consult-nav-link" href="<?= html_escape(base_url('home#profile')); ?>" aria-label="Profil">
				<i class="fas fa-user"></i>
				<span>Profil</span>
			</a>
		</nav>
	</div>

	<!-- Modal Bootstrap -->
	<div class="modal fade" id="modalDataPenunjang" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="modalLabel">Data penunjang</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<div class="modal-body">
					<label for="popupTextarea" class="form-label">Data penunjang:</label>
					<textarea id="popupTextarea" class="form-control" style="height: 180px;">
Riwayat Kesehatan
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
					<h5 class="modal-title" id="modalKeluhanLabel">Keluhan</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<div class="modal-body">
					<label for="popupKeluhan" class="form-label">Keluhan:</label>
					<textarea id="popupKeluhan" class="form-control" style="height: 180px;">
Anamnesa
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



		// Validasi waktu
		if (currentHour >= '16') {
			Swal.fire({
				title: 'Di luar jam layanan',
				text: 'Setelah pukul 16.00, kunjungan tidak tersedia.',
				icon: 'warning',
				allowOutsideClick: false, // Prevent clicking outside the modal
				allowEscapeKey: false, // Prevent closing with the escape key
				showCancelButton: true,
				confirmButtonText: 'Kembali',
				cancelButtonText: 'Lanjut mengisi'
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
				alert('Isi data penunjang.');
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
				alert('Masukkan keluhan.');
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
			const gejalaKeluhanUtama = getStructuredValue('ui_gejala_keluhan_utama');
			const keluhanUtama = getStructuredValue('ui_keluhan_utama');
			const lamaKeluhan = getStructuredValue('ui_lama_keluhan');
			const deskripsiKeluhan = getStructuredValue('ui_deskripsi_keluhan');

			if (gejalaKeluhanUtama === '' && keluhanUtama === '') {
				alert('Pilih atau tulis keluhan utama.');
				focusStructuredField('ui_gejala_keluhan_utama');
				return false;
			}

			if (lamaKeluhan === '') {
				alert('Masukkan lama keluhan.');
				focusStructuredField('ui_lama_keluhan');
				return false;
			}

			if (deskripsiKeluhan === '') {
				alert('Masukkan detail keluhan.');
				focusStructuredField('ui_deskripsi_keluhan');
				return false;
			}

			const keluhanPayload = [
				'Anamnesa',
				'Gejala/Keluhan utama: ' + (gejalaKeluhanUtama !== '' ? gejalaKeluhanUtama : keluhanUtama),
				'Keluhan utama: ' + optionalStructuredValue('ui_keluhan_utama'),
				'Lama keluhan: ' + lamaKeluhan,
				'Gejala tambahan: ' + optionalStructuredValue('ui_gejala_tambahan'),
				'Deskripsi keluhan: ' + deskripsiKeluhan
			].join("\n");

			const dataPenunjangPayload = [
				'Riwayat Kesehatan',
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
						.text('Kirim')
						.trigger('click');
				}, function() {
					$('#save_konsul')
						.data('skipGeolocationRetry', true)
						.prop('disabled', false)
						.text('Kirim')
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
				alert('Isi data penunjang.');
				return;
			} else if (keluhan === '') {
				alert('Masukkan keluhan.');
				return;
			} else if (lat === '' || lng === '') {
				Swal.fire("Lokasi diperlukan", "Pilih lokasi terlebih dahulu.", "error");
				return;
			}

			var formData = new FormData(document.getElementById('form_konsul'));

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
						const message = response && response.message ? response.message : 'Terjadi kesalahan. Coba lagi.';
						$('#save_konsul').prop('disabled', false).text('Kirim');
						Swal.fire("Permintaan belum terkirim", message, "error");
						return;
					}

					Swal.fire({
						title: "Permintaan dikirim.",
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
					$('#save_konsul').prop('disabled', false).text('Kirim');
					alert('Permintaan belum terkirim. Terjadi kesalahan. Coba lagi.');
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
		}

		function initPatientGeolocation() {
			if (!navigator.geolocation) {
				return;
			}

			navigator.geolocation.getCurrentPosition(updateLocation, function() {}, {
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
				})
				.catch(error  => {});
		}

		function getAddress(location) {
			if (!hasGoogleMaps() || !geocoder) return;

			geocoder.geocode({
				location: location
			}, (results, status) => {
				if (status === "OK") {
					if (results[0]) {
						document.getElementById("address").value = results[0].formatted_address;
					} else {
						document.getElementById("address").value = "Alamat belum ditemukan.";
					}
				} else {
					document.getElementById("address").value = "Alamat belum ditemukan.";
				}
			});
		}

		function showError(error) {
			switch (error.code) {
				// case error.PERMISSION_DENIED:
				// 	alert("User denied the request for Geolocation.");
				// 	break;
				case error.POSITION_UNAVAILABLE:
					alert("Lokasi belum tersedia.");
					break;
				case error.TIMEOUT:
					alert("Lokasi belum ditemukan. Coba lagi.");
					break;
				case error.UNKNOWN_ERROR:
					alert("Lokasi belum tersedia.");
					break;
			}
		}

		window.onload = function() {
			initPatientGeolocation();
			initMap();

			setTimeout(() => {
				sendData();
			}, 100);
		}
	</script>

	<?php if ($this->session->userdata('role') === 'warga') : ?>
		<script>
			window.DoclincIncomingCallWatcher = {
				enabled: true,
				incomingUrl: <?= json_encode(base_url('home/livekit_incoming_call')); ?>,
				rejectUrl: <?= json_encode(base_url('home/reject_livekit_call')); ?>,
				chatUrl: <?= json_encode(base_url('chat')); ?>,
				ringtoneUrl: <?= json_encode(base_url('assets/audio/doclinc-ringtone.wav')); ?>,
				pollMs: 3000
			};
		</script>
		<script src="<?= html_escape(base_url('assets/js/doclinc-livekit-ringtone.js')); ?>"></script>
		<script src="<?= html_escape(base_url('assets/js/doclinc-livekit-incoming-watcher.js')); ?>"></script>
	<?php endif; ?>
	<?php if ($clinical_suggestions_enabled) : ?>
		<script src="<?= html_escape(base_url('assets/js/doclinc-clinical-suggestions.js')); ?>"></script>
	<?php endif; ?>

</body>

</html>
