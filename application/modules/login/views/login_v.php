<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Doc Linc (BETA)- Login</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC71j570q3iQGfOnd_YVHpsAl808HlV6j4"></script>
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
</head>

<body class="bg-success">
	<div class="container">
		<div class="row d-flex align-items-center justify-content-center vh-100">
			<div class="col-md-4">
				<center>
					<img class="p-2" src="<?= base_url(); ?>assets/images/doklincwhite.png" alt="" height="100px">
				</center>
				<div class="card shadow-lg mt-3" style="border-radius: 10px; overflow: hidden;">
					<div class="card-body" style="background-color: #e0f2f1;">

						<!-- Form Login Modern -->
						<form id="loginForm">
							<div class="form-group mb-3 position-relative">
								<label for="username" class="sr-only">Username</label>
								<input type="text" class="form-control form-control-lg ps-5" id="username" placeholder="Username" style="border-radius: 10px;" required>
								<i class="fas fa-user position-absolute" style="top: 50%; left: 15px; transform: translateY(-50%); color: #00796b;"></i>
							</div>

							<div class="form-group mb-3 position-relative">
								<label for="password" class="sr-only">Password</label>
								<input type="password" class="form-control form-control-lg ps-5" id="password" placeholder="Password" style="border-radius: 10px;" required>
								<i class="fas fa-lock position-absolute" style="top: 50%; left: 15px; transform: translateY(-50%); color: #00796b;"></i>
							</div>

							<div class="d-grid gap-2 mb-3">
								<button type="submit" class="btn btn-success btn-lg" style="background-color: #09AD74; border-radius: 10px;">Login</button>
							</div>
						</form>
						<hr>
						<div class="text-center">
							<p class="text-muted mb-0" style="color: #004d40;">Belum punya akun? <a href="<?= base_url('sign_up'); ?>" style="color: #00796b;">Daftar di sini</a></p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<input type="hidden" id="address" placeholder="Address" readonly>
	<input type="hidden" id="latitude" placeholder="Latitude" readonly>
	<input type="hidden" id="longitude" placeholder="Longitude" readonly>
	<div id="map"></div>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	<!-- Script untuk menangani login, alert sukses, dan redirect ke loading page -->
	<script>
		document.getElementById('loginForm').addEventListener('submit', function(event) {
			event.preventDefault(); // Mencegah form dikirim secara default

			// Ambil nilai dari input username dan password
			var username = document.getElementById('username').value;
			var password = document.getElementById('password').value;
			var location = document.getElementById('address').value;
			var lattitude = document.getElementById('latitude').value;
			var longitude = document.getElementById('longitude').value;
			$.ajax({
				url: '<?= base_url(); ?>login/auth/',
				type: 'POST',
				data: {
					username: username,
					password: password,
					location: location,
					lattitude: lattitude,
					longitude: longitude,
				},
				success: function(result) {
					// 	if (result == 'OK') {
					if (result == '1') {
						$('button[type="submit"]').attr("disabled", true);
						$('button[type="submit"]').html('<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' +
							'<span role="status"> Loading...</span>');
						setTimeout(function() {
							// 			window.location.href = 'home';
							window.location.reload(result);
						}, 1000); // Delay 1 detik setelah alert sebelum ke loading
					} else {
						Swal.fire({
							title: "Gagal!",
							text: "Username dan Password tidak sesuai",
							icon: "error"
						});
					}
				}
			});
		});

		let map;
		let marker;
		let geocoder;

		function initMap() {
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
			// Update posisi marker dan pusat peta
			marker.setPosition(newLocation);
			map.setCenter(newLocation);
			// Tampilkan latitude dan longitude
			document.getElementById("latitude").value = newLocation.lat;
			document.getElementById("longitude").value = newLocation.lng;
			// Mendapatkan alamat dengan Geocoder
			getAddress(newLocation);
		}

		function getAddress(location) {
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
		window.onload = initMap;
	</script>
</body>

</html>