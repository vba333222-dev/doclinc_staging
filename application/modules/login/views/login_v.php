<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DokLinc - Masuk</title>
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
			<div class="col-11 col-sm-8 col-md-6 col-lg-5 col-xl-4">
				<div class="text-center">
					<img class="dl-auth-logo" src="<?= base_url('assets/images/doklinc.png'); ?>" alt="DokLinc" width="3167" height="1396" decoding="async" fetchpriority="high">
				</div>
				<div class="dl-auth-card">
					<div class="dl-auth-card-body">
						<h1 class="dl-auth-title">Masuk</h1>
						<p class="dl-auth-subtitle">Masukkan akun DokLinc Anda</p>
						<?php if (!empty($success_message)): ?>
							<?php
							/* Tingkat pesan dibatasi daftar putih: nilainya datang dari
							   query string lewat controller, jangan pernah dipakai
							   mentah sebagai kelas CSS. */
							$session_notice_level = isset($session_notice_level) && in_array($session_notice_level, array('success', 'info', 'warning', 'danger'), true)
								? $session_notice_level
								: 'success';
							?>
							<div class="alert alert-<?= html_escape($session_notice_level); ?>" role="status"><?= html_escape($success_message); ?></div>
						<?php endif; ?>

						<form id="loginForm">
							<div class="dl-auth-field">
								<label class="form-label" for="username">Username</label>
								<div class="dl-auth-control">
									<i class="fas fa-user dl-auth-icon" aria-hidden="true"></i>
									<input type="text" class="form-control" id="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required>
								</div>
							</div>

							<div class="dl-auth-field">
								<label class="form-label" for="password">Password</label>
								<div class="dl-auth-control">
									<i class="fas fa-lock dl-auth-icon" aria-hidden="true"></i>
									<input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
									<button type="button" class="dl-auth-toggle-pw" data-target="password" aria-label="Tampilkan password">
										<i class="fas fa-eye" aria-hidden="true"></i>
									</button>
								</div>
							</div>

							<div class="d-grid mb-3">
								<button type="submit" class="dl-auth-btn">Masuk</button>
							</div>
						</form>

						<hr class="dl-auth-divider">
						<div class="text-center">
							<p class="dl-auth-footer-text">Belum punya akun? <a href="<?= site_url('sign_up'); ?>" class="dl-auth-link">Daftar di sini</a></p>
							<p class="dl-auth-footer-text mt-2">Tenaga kesehatan? <a href="<?= html_escape(site_url('login-nakes')); ?>" class="dl-auth-link">Masuk lewat halaman Nakes</a></p>
						</div>
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
		document.getElementById('loginForm').addEventListener('submit', function(event) {
			event.preventDefault();
			const loginButton = this.querySelector('button[type="submit"]');
			const originalButtonContent = loginButton ? loginButton.innerHTML : '';

			var username = document.getElementById('username').value;
			var password = document.getElementById('password').value;
			if (loginButton) {
				loginButton.disabled = true;
			}
			$.ajax({
				url: '<?= site_url('login/auth'); ?>',
				type: 'POST',
				data: {
					username: username,
					password: password,
				},
				success: function(result) {
					if (result == '1') {
						if (loginButton) {
							loginButton.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' +
								'<span role="status"> Memuat…</span>';
						}
						setTimeout(function() {
							window.location.reload(result);
						}, 1000);
					} else if (result == '0') {
						if (loginButton) {
							loginButton.disabled = false;
							loginButton.innerHTML = originalButtonContent;
						}
						Swal.fire({
							title: "Masuk gagal",
							text: "Nama pengguna atau password salah.",
							icon: "error",
							confirmButtonText: "Mengerti"
						});
					} else {
						if (loginButton) {
							loginButton.disabled = false;
							loginButton.innerHTML = originalButtonContent;
						}
						Swal.fire({
							title: "Masuk belum berhasil",
							text: "Terjadi kesalahan. Coba lagi.",
							icon: "error",
							confirmButtonText: "Mengerti"
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
						icon: "error",
						confirmButtonText: "Mengerti"
					});
				}
			});
		});

	</script>

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
	<script src="<?= base_url('assets/js/doclinc-password-mask.js'); ?>"></script>
</body>

</html>
