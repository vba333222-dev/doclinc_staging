<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DokLinc - Daftar</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= base_url('assets/css/style.css'); ?>">
	<link rel="stylesheet" href="<?= base_url('assets/css/doclinc-design-system.css'); ?>">
	<link rel="stylesheet" href="<?= base_url('assets/css/doclinc-auth.css'); ?>">
</head>
<body class="dl-auth-body">
	<div class="container">
		<div class="row d-flex align-items-center justify-content-center min-vh-100 py-4">
			<div class="col-11 col-sm-9 col-md-7 col-lg-6 col-xl-5">
				<div class="text-center">
					<img class="dl-auth-logo" src="<?= base_url('assets/images/doklinc.png'); ?>" alt="DokLinc" width="3167" height="1396" decoding="async" fetchpriority="high">
				</div>
				<div class="dl-auth-card">
					<div class="dl-auth-card-body">
						<h1 class="dl-auth-title">Daftar akun DokLinc</h1>
						<p class="dl-auth-subtitle">Lengkapi data berikut</p>
						<form id="signupForm">
							<div class="dl-auth-field">
								<label class="form-label" for="nama_lengkap">Nama lengkap</label>
								<div class="dl-auth-control">
									<i class="fas fa-user dl-auth-icon" aria-hidden="true"></i>
									<input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Sesuai KTP" autocomplete="name" required>
								</div>
							</div>
							<div class="dl-auth-field">
								<label class="form-label" for="email">Email</label>
								<div class="dl-auth-control">
									<i class="fas fa-envelope dl-auth-icon" aria-hidden="true"></i>
									<input type="email" class="form-control" id="email" name="email" placeholder="nama@email.com" autocomplete="email" inputmode="email" required>
								</div>
							</div>
							<div class="dl-auth-field">
								<label class="form-label" for="no_hp">Nomor HP</label>
								<div class="dl-auth-control">
									<i class="fas fa-phone dl-auth-icon" aria-hidden="true"></i>
									<input type="tel" class="form-control" id="no_hp" name="no_hp" placeholder="08xxxxxxxxxx" autocomplete="tel" inputmode="tel" required>
								</div>
							</div>
							<div class="dl-auth-field">
								<label class="form-label" for="username">Username</label>
								<div class="dl-auth-control">
									<i class="fas fa-id-badge dl-auth-icon" aria-hidden="true"></i>
									<input type="text" class="form-control" id="username" name="username" autocomplete="off" autocapitalize="none" spellcheck="false" required>
								</div>
							</div>
							<div class="dl-auth-field">
								<label class="form-label" for="password">Password</label>
								<div class="dl-auth-control">
									<i class="fas fa-lock dl-auth-icon" aria-hidden="true"></i>
									<input type="password" class="form-control" id="password" name="password" minlength="8" maxlength="72" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}" autocomplete="new-password" aria-describedby="password-hint" required>
									<button type="button" class="dl-auth-toggle-pw" data-target="password" aria-label="Tampilkan password">
										<i class="fas fa-eye" aria-hidden="true"></i>
									</button>
								</div>
								<div class="dl-auth-hint" id="password-hint">Minimal 8 karakter: huruf besar, huruf kecil, angka, dan karakter khusus.</div>
							</div>
							<div class="dl-auth-field">
								<label class="form-label" for="k_password">Konfirmasi password</label>
								<div class="dl-auth-control">
									<i class="fas fa-lock dl-auth-icon" aria-hidden="true"></i>
									<input type="password" class="form-control" id="k_password" name="k_password" minlength="8" maxlength="72" autocomplete="new-password" required>
									<button type="button" class="dl-auth-toggle-pw" data-target="k_password" aria-label="Tampilkan konfirmasi password">
										<i class="fas fa-eye" aria-hidden="true"></i>
									</button>
								</div>
							</div>
							<div class="row g-2 mt-1">
								<div class="col">
									<div class="d-grid">
										<button type="submit" class="dl-auth-btn">Daftar</button>
									</div>
								</div>
								<div class="col">
									<div class="d-grid">
										<a href="<?= site_url('login');?>" class="dl-auth-btn-outline">Batal</a>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<script>
		document.querySelectorAll('.dl-auth-toggle-pw').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var targetId = this.getAttribute('data-target');
				var input = document.getElementById(targetId);
				if (!input) return;
				var icon = this.querySelector('i');
				if (input.type === 'password') {
					input.type = 'text';
					icon.classList.replace('fa-eye', 'fa-eye-slash');
				} else {
					input.type = 'password';
					icon.classList.replace('fa-eye-slash', 'fa-eye');
				}
			});
		});
	</script>
	<script>
		document.getElementById('signupForm').addEventListener('submit', function(event) {
			event.preventDefault();
			var formData = {
				nama_lengkap: $('#nama_lengkap').val(),
				email: $('#email').val(),
				no_hp: $('#no_hp').val(),
				username: $('#username').val(),
				password: $('#password').val(),
				k_password: $('#k_password').val()
			};
			$.ajax({
				type: 'POST',
				url: '<?= site_url('sign_up/save_user'); ?>',
				data: formData,
				dataType: 'json',
				success: function(response) {
					if (response.success) {
						Swal.fire({
							title: "Akun dibuat.",
							text: "Silakan masuk menggunakan akun Anda.",
							icon: "success",
							showConfirmButton: false,
							timer: 2000
						});
						setTimeout(function() {
							window.location.href = '<?= site_url('login'); ?>';
						}, 2000);
					} else {
						Swal.fire({
							title: "Akun belum dibuat",
							text: response.message,
							icon: "error",
							showConfirmButton: true
						});
					}
				},
				error: function(xhr, status, error) {
					var safeMessage = xhr && xhr.responseJSON && typeof xhr.responseJSON.message === 'string'
						? xhr.responseJSON.message.trim()
						: '';
					Swal.fire({
						title: "Akun belum dibuat",
						text: safeMessage || "Terjadi kesalahan. Coba lagi.",
						icon: "error",
						showConfirmButton: true
					});
				}
			});
		});
	</script>
	<script src="<?= base_url('assets/js/doclinc-password-mask.js'); ?>"></script>
</body>
</html>
