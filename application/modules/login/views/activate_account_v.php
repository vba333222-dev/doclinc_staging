<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex,nofollow">
	<title>DocLink - Aktivasi Akun Nakes</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/style.css')); ?>">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-design-system.css')); ?>">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-auth.css')); ?>">
</head>
<body class="dl-auth-body">
	<div class="container">
		<div class="row d-flex align-items-center justify-content-center min-vh-100 py-4">
			<div class="col-11 col-sm-8 col-md-6 col-lg-5 col-xl-4">
				<div class="text-center">
					<img class="dl-auth-logo" src="<?= base_url('assets/images/doklinc.png'); ?>" alt="DocLink" width="3167" height="1396" decoding="async" fetchpriority="high">
				</div>
				<div class="dl-auth-card">
					<div class="dl-auth-card-body">
						<h1 class="dl-auth-title">Aktivasi akun Nakes</h1>
						<p class="dl-auth-subtitle">Masukkan nama pengguna dan password sementara dari Admin Dinas Kesehatan.</p>
						<?php if (!empty($error_message)) : ?>
							<div class="alert alert-danger" role="alert"><?= html_escape($error_message); ?></div>
						<?php endif; ?>
						<p class="dl-auth-note">Jika data cocok, Anda akan diminta membuat password pribadi sebelum masuk ke beranda.</p>
						<form method="post" action="<?= html_escape(site_url('login/activate/auth')); ?>" autocomplete="off">
							<div class="dl-auth-field">
								<label class="form-label" for="activation_username">Nama pengguna</label>
								<div class="dl-auth-control">
									<i class="fas fa-user dl-auth-icon" aria-hidden="true"></i>
									<input class="form-control" type="text" id="activation_username" name="username" maxlength="100" autocomplete="username" autocapitalize="none" spellcheck="false" required>
								</div>
							</div>
							<div class="dl-auth-field">
								<label class="form-label" for="activation_password">Password sementara</label>
								<div class="dl-auth-control">
									<i class="fas fa-lock dl-auth-icon" aria-hidden="true"></i>
									<input class="form-control" type="password" id="activation_password" name="password" maxlength="72" autocomplete="current-password" required>
									<button type="button" class="dl-auth-toggle-pw" data-target="activation_password" aria-label="Tampilkan password sementara">
										<i class="fas fa-eye" aria-hidden="true"></i>
									</button>
								</div>
							</div>
							<div class="d-grid mb-3">
								<button class="dl-auth-btn" type="submit">Lanjutkan aktivasi</button>
							</div>
						</form>
						<hr class="dl-auth-divider">
						<div class="text-center">
							<p class="dl-auth-footer-text">Bukan akun Nakes? <a href="<?= html_escape(site_url('login')); ?>" class="dl-auth-link">Kembali ke halaman masuk</a></p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
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
