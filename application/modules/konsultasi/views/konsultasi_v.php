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
</head>

<body class="bg-light">
	<div class="backtohome">
		<a href="<?= base_url('home#konsultasi_kesehatan'); ?>">
			<i class="fas fa-arrow-left"></i>
		</a>
		<span class="ms-auto">Konsultasi</span>
	</div>
	<div class="hero bg-success px-3 pb-3 overflow-hidden">
		<div class="d-flex align-items-center animate__animated animate__fadeInUp animate__faster">
			<div class="flex-shrink-0">
				<img class="rounded-4 shadow"
					src="<?= base_url('assets/images/default-profile.svg'); ?>"
					width="100px"
					height="100px">
			</div>
			<div class="px-2 mx-auto text-white">
				<div class="text-center fw-bold"><i class="far fa-clock"></i>
					<label id="result"></label>
				</div>
				<!-- <hr class="my-1 border-3 rounded-pill"> -->
				<div class="progress" role="progressbar" aria-label="Animated striped example" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="height: .4rem;">
					<div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 100%;"></div>
				</div>
				<div class="text-center">
					<?php
					$this->load->model('Konsultasi_m');
					$hasil = $this->Konsultasi_m->getLocation($dokter);
					?>
					<i class="fas fa-map-marker-alt me-2"></i><small><?= html_escape($hasil['location'] ?? 'Lokasi belum tersedia'); ?></small>
				</div>
			</div>
			<?php
			$foto = '';
			foreach ($getFotoDokter->result() as $row_foto) {
				$foto = $row_foto->foto ?? '';
			}
			?>
			<!-- buat syntax untuk menampilan nama dokter dan jadikan dibawah image -->
			<?php
			// ambil data dari controller konsultasi
			foreach ($getDataDoctor->result() as $row) {
				$dokter_id = $row->userId;
				$nama = $row->nama;

				// if ($nama == 'pahlawan1') {
				// 	$dokter_id = '1';
				// } elseif ($nama == 'pahlawan2') {
				// 	$dokter_id = '2';
				// } elseif ($nama == 'pahlawan3') {
				// 	$dokter_id = '3';
				// }
			}
			?>

			<?php if (!empty($foto)) : ?>
				<img class="rounded-4" id="gambar" src="<?= base_url('uploads/profile/' . html_escape($foto)); ?>" width="100px" height="auto" alt="Image Not Found">
			<?php else : ?>
				<img class="rounded-4" id="gambar" src="<?= base_url('assets/images/default-profile.svg'); ?>" width="100px" height="auto" alt="Default Image">
			<?php endif; ?>
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
	<div id="pahlawan_1" class="content animate__animated animate__fadeInUp animate__faster" style="padding: 15px;">
		<form id="form_konsul" enctype="multipart/form-data">
			<div class="form-floating mb-2">
				<input type="hidden" class="form-control shadow border-success" id="nama" value="<?php echo $_SESSION['username']; ?>" placeholder="Nama Lengkap" readonly>
			</div>
			<div class="form-floating mb-2">
				<input type="hidden" class="form-control shadow border-success" name="dokter_id" id="dokter_id" value="<?= html_escape($dokter_id); ?>" placeholder="Dokter ID" readonly>
				<input type="hidden" class="form-control shadow border-success" name="namadokter" id="namadokter" value="<?= html_escape($nama); ?>" placeholder="Dokter ID" readonly>
			</div>
			<div class="form-floating mb-2">
				<!-- Textarea utama -->
				<div class="mb-3">
					<label for="data_penunjang" class="form-label">Data Penunjang</label>
					<textarea
						id="data_penunjang"
						name="data_penunjang"
						class="form-control shadow border-success"
						placeholder="Klik untuk isi Data Penunjang"
						style="height: 180px;"
						readonly
						required>
						<?php echo !empty($getDataPenunjangById) ? htmlspecialchars($getDataPenunjangById) : '';
						?>
					</textarea>
				</div>
			</div>
			<div class="form-floating mb-2">
				<!-- Textarea Utama (Keluhan) -->
				<div class="mb-3">
					<label for="keluhan" class="form-label">Keluhan</label>
					<textarea
						id="keluhan"
						name="keluhan"
						class="form-control shadow border-success"
						placeholder="Klik untuk isi keluhan"
						style="height: 180px;"
						readonly
						required></textarea>
				</div>
			</div>
			<?php
			// ambil data dari controller konsultasi
			foreach ($getDataTokenDoctor->result() as $row) {
				$phone = $row->phone;
				$token = $row->token;
			}
			// echo "isi token: " . $token;
			?>
			<input type="text" name="token" id="token" value="<?php echo html_escape($token); ?>" style="display: none;" readonly>
			<div class="form-floating mb-2 d-none">
				<input type="hidden" class="form-control shadow border-success" id="no_hp" value="<?= html_escape($_SESSION['no_hp'] ?? '') ?>" placeholder="Nomor HP" readonly>
				<label for="no_hp">Nomor HP</label>
			</div>

			<div class="card shadow mb-3">
				<div class="card-header bg-success text-white">
					<i class="fas fa-paperclip"></i> Opsional untuk sakit luar
				</div>
				<div class="card-body">
					<!-- Upload Foto -->
					<div class="form-floating mb-3">
						<input type="file" class="form-control shadow border-success" id="file" name="foto" accept="image/*">
						<label for="file">Upload Foto</label>
						<!-- Nama File -->
						<input type="hidden" id="fileName" name="foto" class="form-control shadow border-success" readonly>
						<button type="button" class="form-control shadow border-success" onclick="window.flutter_inappwebview.callHandler('takePhoto')" hidden>
							Ambil Foto dari Kamera
						</button>
					</div>
					<!-- Preview Foto -->
					<div id="previewContainer" class="mb-3 d-none">
						<img id="preview" src="" alt="Preview Foto" class="img-fluid rounded border border-success" />
					</div>

					<!-- Upload Video -->
					<div class="form-floating mb-3">
						<input type="file" class="form-control shadow border-primary" id="file_video" name="video" accept="video/*">
						<label for="file_video">Upload Video</label>
					</div>
					<!-- Preview Video -->
					<div id="previewVideoContainer" class="d-none">
						<video id="previewVideo" controls width="100%" class="rounded border border-primary"></video>
					</div>
				</div>
			</div>


			<div class="form-floating mb-2">
				<textarea id="address" name="alamat" class="form-control shadow border-success" placeholder="Alamat: ..." style="height: 100px"></textarea>
				<label for="alamat">Alamat</label>
				<input type="text" class="d-none" name="lat" id="latitude" placeholder="Latitude">
				<input type="text" class="d-none" name="lng" id="longitude" placeholder="Longitude">
				<div id="map"></div>
			</div>
			<div class="form-floating mb-2">
				<input type="text" class="form-control shadow border-success" id="tanggal" name="tanggal" value="<?php echo date('d-m-Y'); ?>" placeholder="tanggal" readonly>

				<input type="hidden" id="id_user" name="id_user" value="<?= $this->session->userdata('id'); ?>">
				<label for="Tanggal">Tanggal </label>
			</div>
			<div class="form-check form-switch mb-2">
				<input class="form-check-input" type="checkbox" role="switch" id="kunjung" name="kunjung">
				<label class="form-check-label" for="kunjung">Bersedia dikunjungi dokter</label>
			</div>
			<div class="d-grid">
				<button type="button" class="btn btn-success shadow" id="save_konsul">Kirim</button>
			</div>
		</form>
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
		$('#save_konsul').click(function() {
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

			if (data_penunjang === '') {
				alert('Silakan isi data penunjang terlebih dahulu.');
				return;
			} else if (keluhan === '') {
				alert('Silakan isi keluhan terlebih dahulu.');
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
					$('#save_konsul').prop('disabled', false).text('Kirim');
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
			if (navigator.geolocation) {
				navigator.geolocation.watchPosition(updateLocation, showError);
			} else {
				alert("Geolocation is not supported by this browser.");
			}
		}

		function updateLocation(position) {
			if (!hasGoogleMaps() || !marker || !map) return;

			const newLocation = {
				lat: position.coords.latitude,
				lng: position.coords.longitude,
			};

			// Update posisi marker dan pusat peta
			marker.setPosition(newLocation);
			map.setCenter(newLocation);

			// Tampilkan latitude dan longitude
			document.getElementById("latitude").value = newLocation.lat;
			document.getElementById("longitude").value = newLocation.lng;

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

</body>

</html>
