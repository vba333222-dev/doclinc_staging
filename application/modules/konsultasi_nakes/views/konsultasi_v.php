<?php
function getDuration($latitudeA, $longitudeA, $latitudeB, $longitudeB, $mode)
{
	$CI = get_instance();
	$apiKey = $CI->config->item('google_maps_api_key');
	$mapProvider = $CI->config->item('map_provider');

	if ($mapProvider !== 'google' || empty($apiKey)) {
		return '-';
	}

	$query = http_build_query([
		'origins' => $latitudeA . ',' . $longitudeA,
		'destinations' => $latitudeB . ',' . $longitudeB,
		'mode' => $mode,
		'key' => $apiKey,
	]);
	$url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . $query;

	// Inisialisasi cURL
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

	// Jalankan cURL dan dapatkan respons
	$response = curl_exec($curl);

	// Periksa error pada cURL
	if (curl_errno($curl)) {
		log_message('error', 'Curl error: ' . curl_error($curl));
		curl_close($curl);
		return '-';
	}

	curl_close($curl);

	// Decode JSON respons
	$data = json_decode($response, true);

	// Mengambil durasi dari respons
	return $data['rows'][0]['elements'][0]['duration']['text'] ?? '-';

	// Mengembalikan durasi dalam format yang diinginkan
	// return $duration;
}

$googleMapsApiKey = $this->config->item('google_maps_api_key');
$mapProvider = $this->config->item('map_provider');
$googleMapsEnabled = ($mapProvider === 'google' && !empty($googleMapsApiKey));

// Mencari jarak antara pusat kesehatan dan pahlawan 1
$this->load->model('Konsultasi_m');
$coordinate = $this->Konsultasi_m->getAllDataLocations();
foreach ($coordinate as $coordinate) {
	$latitudeA = $coordinate['latitude'];
	$longitudeA = $coordinate['longitude'];
}

$mode = 'driving';

// Cek apakah ada request POST dari JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Ambil nilai latitude dan longitude dari request POST
	$latitudeB = $_POST['latitude'] ?? '';
	$longitudeB = $_POST['longitude'] ?? '';

	header('Content-Type: application/json'); // Set header untuk JSON
	json_encode(['status' => 'success', 'latitude' => htmlspecialchars($latitudeB), 'longitude' => htmlspecialchars($longitudeB)]);

	echo getDuration($latitudeA, $longitudeA, $latitudeB, $longitudeB, $mode);

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
		<a href="<?= base_url('home'); ?>">
			<i class="fas fa-arrow-left"></i>
		</a>
		<span class="ms-auto">Konsultasi</span>
	</div>
	<div class="hero bg-success px-3 pb-3 overflow-hidden">
		<div class="d-flex align-items-center animate__animated animate__fadeInUp animate__faster">
			<img class="rounded-4 shadow" src="<?= base_url(); ?>assets/images/rahmat.jpg" width="80px" height="80px">
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
					$hasil = $this->Konsultasi_m->getLocation();
					?>
					<i class="fas fa-map-marker-alt me-2"></i><small><?= $hasil['location'] ?></small>
				</div>
			</div>
			<img class="rounded-4 shadow" src="<?= base_url(); ?>assets/images/pahlawan2.jpg" width="80px" height="80px">
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
		<form id="form_konsul">
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
				<input type="text" class="d-none" id="latitude" placeholder="Latitude" readonly>
				<input type="text" class="d-none" id="longitude" placeholder="Longitude" readonly>
				<div id="map"></div>
			</div>
			<div class="form-floating mb-2">
				<input type="text" class="form-control shadow border-success" id="tanggal" value="<?php echo date('d-m-Y'); ?>" placeholder="tanggal" readonly>

				<input type="hidden" id="id_user" value="<?= $this->session->userdata('id'); ?>">
				<label for="Tanggal">Tanggal </label>
			</div>
			<div class="d-grid">
				<button type="button" class="btn btn-success shadow" id="save_konsul">Kirim</button>
			</div>
		</form>
	</div>

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" integrity="sha512-bPs7Ae6pVvhOSiIcyUClR7/q2OAsRiovw4vAkX+zJbw3ShAeeqezq50RIIcIURq7Oa20rW2n2q+fyXBNcU9lrw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<?php if ($googleMapsEnabled) : ?>
		<script src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode($googleMapsApiKey); ?>"></script>
	<?php endif; ?>

	<script>
		$('#save_konsul').click(function() {
			var id_user = $('#id_user').val();
			var nama = $('#nama').val();
			var keluhan = $('#keluhan').val();
			var no_hp = $('#no_hp').val();
			var lat = $('#latitude').val();
			var lng = $('#longitude').val();
			var alamat = $('#address').val();
			var tanggal = $('#tanggal').val();

			// alert(nama);
			// alert(keluhan);
			// alert(lat);
			// alert(lng);
			// alert(alamat);
			// alert(tanggal);
			$.ajax({
				url: "<?php echo base_url(); ?>konsultasi/save_konsultasi",
				method: "POST",
				data: {
					id_user: id_user,
					keluhan: keluhan,
					alamat: alamat,
					lat: lat,
					lng: lng,
					tanggal: tanggal
				},
				async: false,
				dataType: 'json',
				success: function(response) {
					Swal.fire({
						title: "Berhasil!",
						icon: "success",
						// text: nama+", "+keluhan+", "+lat+", "+lng+", "+alamat+", "+tanggal,
						showConfirmButton: false,
						timer: 2500,
						timerProgressBar: true
					}).then((result) => {
						if (result.dismiss === Swal.DismissReason.timer) {
							window.location.href = 'home';
						}
					});
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
			if (!window.google || !google.maps) {
				document.getElementById("result").innerHTML = "-";
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
				alert("Geolocation is not supported by this browser.");
			}
		}

		function updateLocation(position) {
			const newLocation = {
				lat: position.coords.latitude,
				lng: position.coords.longitude,
			};

			// Tampilkan latitude dan longitude
			document.getElementById("latitude").value = newLocation.lat;
			document.getElementById("longitude").value = newLocation.lng;

			if (marker && map && geocoder) {
				// Update posisi marker dan pusat peta
				marker.setPosition(newLocation);
				map.setCenter(newLocation);

				// Mendapatkan alamat dengan Geocoder
				getAddress(newLocation);
			}
		}

		function sendData() {
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
			if (!geocoder) {
				return;
			}

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
				case error.PERMISSION_DENIED:
					alert("User denied the request for Geolocation.");
					break;
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
</body>

</html>
