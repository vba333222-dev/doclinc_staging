<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex,nofollow">
	<title>DokLinc - Masuk Nakes</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/style.css')); ?>">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-design-system.css')); ?>">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-nakes-auth.css')); ?>">
</head>

<body class="dl-nakes-auth-body">
	<div class="container">
		<div class="row d-flex align-items-center justify-content-center min-vh-100 py-4">
			<div class="col-11 col-sm-8 col-md-6 col-lg-5 col-xl-4">
				<div class="text-center">
					<img class="dl-nakes-auth-logo" src="<?= base_url('assets/images/doklinc.png'); ?>" alt="DokLinc" width="3167" height="1396" decoding="async" fetchpriority="high">
				</div>
				<div class="dl-nakes-auth-card">
					<div class="dl-nakes-auth-card-body">
						<p class="dl-nakes-auth-badge"><i class="fas fa-user-md" aria-hidden="true"></i> Tenaga kesehatan</p>
						<h1 class="dl-nakes-auth-title">Masuk Nakes</h1>
						<p class="dl-nakes-auth-subtitle">Gunakan akun yang diberikan Admin Dinas Kesehatan.</p>
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

						<form id="nakesLoginForm">
							<div class="dl-nakes-auth-field">
								<label class="form-label" for="username">Nama pengguna</label>
								<div class="dl-nakes-auth-control">
									<i class="fas fa-user dl-nakes-auth-icon" aria-hidden="true"></i>
									<input type="text" class="form-control" id="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required>
								</div>
							</div>

							<div class="dl-nakes-auth-field">
								<label class="form-label" for="password">Password</label>
								<div class="dl-nakes-auth-control">
									<i class="fas fa-lock dl-nakes-auth-icon" aria-hidden="true"></i>
									<input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
									<button type="button" class="dl-nakes-auth-toggle-pw" data-target="password" aria-label="Tampilkan password">
										<i class="fas fa-eye" aria-hidden="true"></i>
									</button>
								</div>
							</div>

							<div class="d-grid mb-3">
								<button type="submit" class="dl-nakes-auth-btn">Masuk</button>
							</div>
						</form>

						<hr class="dl-nakes-auth-divider">
						<div class="text-center">
							<?php if (!empty($activation_available)): ?>
								<p class="dl-nakes-auth-footer-text mb-2">Pertama kali masuk? <a href="<?= html_escape(site_url('login-nakes/activate')); ?>" class="dl-nakes-auth-link">Aktivasi akun Nakes</a></p>
							<?php endif; ?>
							<p class="dl-nakes-auth-footer-text">Bukan tenaga kesehatan? <a href="<?= html_escape(site_url('login')); ?>" class="dl-nakes-auth-link">Masuk sebagai warga</a></p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	<script>
		document.getElementById('nakesLoginForm').addEventListener('submit', function(event) {
			event.preventDefault();
			const loginButton = this.querySelector('button[type="submit"]');
			const originalButtonContent = loginButton ? loginButton.innerHTML : '';

			$.ajax({
				url: '<?= site_url('login-nakes/auth'); ?>',
				type: 'POST',
				data: {
					username: document.getElementById('username').value,
					password: document.getElementById('password').value,
				},
				success: function(result) {
					if (result == '1') {
						if (loginButton) {
							loginButton.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' +
								'<span role="status"> Memuat…</span>';
						}
						setTimeout(function() {
							window.location.href = '<?= site_url('home_nakes'); ?>';
						}, 1000);
						return;
					}
					if (loginButton) {
						loginButton.disabled = false;
						loginButton.innerHTML = originalButtonContent;
					}
					if (result == '2') {
						/* Akun ada dan password benar, tetapi bukan akun nakes.
						   Sebut apa adanya supaya tidak menyalahkan password. */
						Swal.fire({
							title: "Bukan akun Nakes",
							text: "Akun ini bukan akun tenaga kesehatan. Silakan masuk lewat halaman warga.",
							icon: "warning",
							confirmButtonText: "Mengerti"
						});
						return;
					}
					Swal.fire({
						title: "Masuk gagal",
						text: result == '0'
							? "Nama pengguna atau password salah."
							: "Terjadi kesalahan. Coba lagi.",
						icon: "error",
						confirmButtonText: "Mengerti"
					});
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

		document.querySelectorAll('.dl-nakes-auth-toggle-pw').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var input = document.getElementById(this.getAttribute('data-target'));
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
