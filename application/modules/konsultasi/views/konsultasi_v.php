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
			background: #e9e9e9;
			color: var(--dk-text);
			font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		}

		.consult-shell {
			width: 100%;
			max-width: 414px;
			min-height: 100vh;
			margin: 0 auto;
			background: var(--dk-bg);
			overflow-x: hidden;
			position: relative;
			padding-bottom: 92px;
		}

		.consult-header {
			height: 75px;
			background: #ffffff;
			box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
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
			font-size: 20px;
			font-weight: 700;
			line-height: 24px;
			color: var(--dk-text);
		}

		.consult-main {
			padding: 38px 20px 24px;
		}

		.consult-card {
			background: #ffffff;
			border: 1px solid var(--dk-accent);
			border-radius: 20px;
			padding: 24px 20px 25px;
			margin-bottom: 30px;
		}

		.consult-card-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 26px;
		}

		.consult-card-title {
			margin: 0;
			font-size: 20px;
			line-height: 24px;
			font-weight: 700;
			color: var(--dk-green);
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
			border-radius: 10px;
			padding: 12px 20px;
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
			gap: 17px;
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
			height: 154px;
			resize: none;
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
			height: 51px;
			border: 0;
			border-radius: 10px;
			background: var(--dk-green);
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
			<h1 class="consult-title">Konsultasi</h1>
		</header>
		<span id="result" class="d-none"></span>

		<main id="pahlawan_1" class="consult-main content animate__animated animate__fadeInUp animate__faster">
			<form id="form_konsul" action="<?= html_escape(base_url('konsultasi/save_konsultasi')); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" id="nama" value="<?= html_escape($_SESSION['username'] ?? ''); ?>" placeholder="Nama Lengkap" readonly>
				<input type="hidden" name="dokter_id" id="dokter_id" value="<?= html_escape($dokter_id); ?>" placeholder="Dokter ID" readonly>
				<input type="hidden" name="namadokter" id="namadokter" value="<?= html_escape($nama); ?>" placeholder="Dokter ID" readonly>

				<section class="consult-card">
					<div class="consult-card-header">
						<h2 class="consult-card-title">Riwayat Kesehatan</h2>
						<img class="consult-card-icon" src="<?= html_escape($ui_asset_base . 'icon-minus.svg'); ?>" alt="">
					</div>
					<div class="consult-input-stack">
						<small class="consult-helper">Puskesmas tujuan akan ditentukan otomatis dari lokasi Anda.</small>
						<div class="consult-field-group">
							<label class="consult-field-label" for="ui_penyakit_pernah">Penyakit yang pernah diderita</label>
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
							<label class="consult-field-label" for="ui_obat_dikonsumsi">Obat yang sedang dikonsumsi</label>
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
							<label class="consult-field-label" for="ui_keluhan_utama">Keluhan utama</label>
							<input type="text" id="ui_keluhan_utama" class="consult-field form-control" placeholder="Contoh: demam, batuk, nyeri perut" required>
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
							<label class="consult-field-label" for="ui_deskripsi_keluhan">Deskripsi keluhan sakit anda</label>
							<textarea id="ui_deskripsi_keluhan" class="consult-field consult-field-keluhan form-control" placeholder="Ceritakan kondisi yang dirasakan dengan bahasa sehari-hari" required></textarea>
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
						<h2 class="consult-card-title">Foto / Video <span class="d-inline-block">(untuk sakit luar)</span></h2>
						<img class="consult-card-icon" src="<?= html_escape($ui_asset_base . 'icon-minus.svg'); ?>" alt="">
					</div>
					<div class="consult-upload-stack">
						<label class="consult-upload-box" for="file">
							<img src="<?= html_escape($ui_asset_base . 'icon-upload.svg'); ?>" alt="">
							<span class="consult-upload-text">Pilih file <strong>Foto</strong></span>
							<input type="file" id="file" name="foto" accept="image/*">
						</label>
						<input type="hidden" id="fileName" name="foto" class="form-control" readonly>
						<button type="button" onclick="window.flutter_inappwebview.callHandler('takePhoto')" hidden>
							Ambil Foto dari Kamera
						</button>
						<div id="previewContainer" class="consult-preview d-none">
							<img id="preview" src="" alt="Preview Foto">
						</div>

						<label class="consult-upload-box" for="file_video">
							<img src="<?= html_escape($ui_asset_base . 'icon-upload.svg'); ?>" alt="">
							<span class="consult-upload-text">Pilih file <strong>Foto</strong></span>
							<input type="file" id="file_video" name="video" accept="video/*">
						</label>
						<div id="previewVideoContainer" class="consult-preview d-none">
							<video id="previewVideo" controls></video>
						</div>
					</div>
				</section>

				<section class="consult-card">
					<div class="consult-card-header">
						<h2 class="consult-card-title">Data Diri</h2>
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
					<span>Bersedia dikunjungi dokter</span>
				</label>

				<button type="button" class="consult-submit" id="save_konsul">Kirim Form</button>
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
						.text('Kirim Form')
						.trigger('click');
				}, function() {
					$('#save_konsul')
						.data('skipGeolocationRetry', true)
						.prop('disabled', false)
						.text('Kirim Form')
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

			setTimeout(() => {
				sendData();
			}, 100);
		}
	</script>

	<!-- ambil foto dari kamera dengan flutter native -->
	<script>
		let photoBlob = null;
		let photoFileName = '';

		function onImageCaptured(dataUrl, fileName) {
			const preview = document.getElementById('preview');
			const container = document.getElementById('previewContainer');
			const nameElement = document.getElementById('fileName');

			preview.src = dataUrl;
			container.classList.remove('d-none');
			nameElement.value = fileName;
			nameElement.classList.remove('d-none');

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
			}
		});
	</script>

	<?php if ($this->session->userdata('role') === 'warga') : ?>
		<script>
			window.DoclincIncomingCallWatcher = {
				enabled: true,
				incomingUrl: <?= json_encode(base_url('home/livekit_incoming_call')); ?>,
				rejectUrl: <?= json_encode(base_url('home/reject_livekit_call')); ?>,
				chatUrl: <?= json_encode(base_url('chat')); ?>,
				pollMs: 3000
			};
		</script>
		<script src="<?= html_escape(base_url('assets/js/doclinc-livekit-incoming-watcher.js')); ?>"></script>
	<?php endif; ?>

</body>

</html>
