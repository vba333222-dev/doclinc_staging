<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DocLink - Masuk</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="<?= base_url('assets/css/style.css'); ?>">
</head>

<body class="bg-success">
	<div class="container">
		<div class="row d-flex align-items-center justify-content-center vh-100">
			<div class="col-md-4">
				<center>
					<img class="p-2" src="<?= base_url('assets/images/doklincwhite.png'); ?>" alt="" height="100px">
				</center>
				<div class="card shadow-lg mt-3" style="border-radius: 10px; overflow: hidden;">
					<div class="card-body" style="background-color: #e0f2f1;">
						<?php if (!empty($success_message)): ?>
							<div class="alert alert-success" role="status"><?= html_escape($success_message); ?></div>
						<?php endif; ?>

						<!-- Form Login Modern -->
						<form id="loginForm">
							<div class="form-group mb-3 position-relative">
								<label for="username" class="sr-only">Nama pengguna</label>
								<input type="text" class="form-control form-control-lg ps-5" id="username" placeholder="Nama pengguna" style="border-radius: 10px;" required>
								<i class="fas fa-user position-absolute" style="top: 50%; left: 15px; transform: translateY(-50%); color: #00796b;"></i>
							</div>

							<div class="form-group mb-3 position-relative">
								<label for="password" class="sr-only">Password</label>
								<input type="password" class="form-control form-control-lg ps-5" id="password" placeholder="Password" style="border-radius: 10px;" required>
								<i class="fas fa-lock position-absolute" style="top: 50%; left: 15px; transform: translateY(-50%); color: #00796b;"></i>
							</div>

							<div class="d-grid gap-2 mb-3">
								<button type="submit" class="btn btn-success btn-lg" style="background-color: #09AD74; border-radius: 10px;">Masuk</button>
							</div>
						</form>
						<hr>
						<div class="text-center">
							<p class="text-muted mb-0" style="color: #004d40;">Belum punya akun? <a href="<?= site_url('sign_up'); ?>" style="color: #00796b;">Daftar di sini</a></p>
							<?php if (!empty($activation_available)): ?>
								<p class="text-muted mt-2 mb-0"><a href="<?= html_escape(site_url('login/activate')); ?>" style="color: #00796b;">Aktivasi akun Nakes</a></p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<input type="hidden" id="address" placeholder="Address" readonly>
	<input type="hidden" id="latitude" placeholder="Latitude" readonly>
	<input type="hidden" id="longitude" placeholder="Longitude" readonly>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	<!-- Script untuk menangani login, alert sukses, dan redirect ke loading page -->
	<script>
		const mapboxToken = <?= json_encode($this->config->item('mapbox_public_token') ?: ''); ?>;

		document.getElementById('loginForm').addEventListener('submit', function(event) {
			event.preventDefault(); // Mencegah form dikirim secara default
			const loginButton = this.querySelector('button[type="submit"]');
			const originalButtonContent = loginButton ? loginButton.innerHTML : '';

			// Ambil nilai dari input username dan password
			var username = document.getElementById('username').value;
			var password = document.getElementById('password').value;
			var location = document.getElementById('address').value;
			var lattitude = document.getElementById('latitude').value;
			var longitude = document.getElementById('longitude').value;
			if (loginButton) {
				loginButton.disabled = true;
			}
			$.ajax({
				url: '<?= site_url('login/auth'); ?>',
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
						if (loginButton) {
							loginButton.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' +
								'<span role="status"> Loading...</span>';
						}
						setTimeout(function() {
							// 			window.location.href = 'home';
							window.location.reload(result);
						}, 1000); // Delay 1 detik setelah alert sebelum ke loading
					} else if (result == '0') {
						if (loginButton) {
							loginButton.disabled = false;
							loginButton.innerHTML = originalButtonContent;
						}
						Swal.fire({
							title: "Gagal!",
							text: "Nama pengguna atau password salah.",
							icon: "error"
						});
					} else {
						if (loginButton) {
							loginButton.disabled = false;
							loginButton.innerHTML = originalButtonContent;
						}
						Swal.fire({
							title: "Masuk belum berhasil",
							text: "Terjadi kesalahan. Coba lagi.",
							icon: "error"
						});
					}
				},
				error: function() {
					if (loginButton) {
						loginButton.disabled = false;
						loginButton.innerHTML = originalButtonContent;
					}
					Swal.fire({
						title: "Masuk belum berhasil",
						text: "Terjadi kesalahan. Coba lagi.",
						icon: "error"
					});
				}
			});
		});

		function initLocation() {
			document.getElementById("address").value = "Location unavailable";

			if (!navigator.geolocation) {
				return;
			}

			navigator.geolocation.getCurrentPosition(updateLocation, function() {}, {
				enableHighAccuracy: true,
				timeout: 10000,
				maximumAge: 60000
			});
		}

		function updateLocation(position) {
			const latitude = position.coords.latitude;
			const longitude = position.coords.longitude;

			document.getElementById("latitude").value = latitude;
			document.getElementById("longitude").value = longitude;
			getAddress(latitude, longitude);
		}

		function getAddress(latitude, longitude) {
			if (!mapboxToken || !window.fetch) {
				return;
			}

			const url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' +
				encodeURIComponent(longitude + ',' + latitude) +
				'.json?access_token=' + encodeURIComponent(mapboxToken);

			fetch(url)
				.then(function(response) {
					if (!response.ok) {
						throw new Error('Mapbox geocoding failed');
					}
					return response.json();
				})
				.then(function(data) {
					if (data.features && data.features.length > 0 && data.features[0].place_name) {
						document.getElementById("address").value = data.features[0].place_name;
					}
				})
				.catch(function() {});
		}

		window.onload = initLocation;
	</script>
</body>

</html>
