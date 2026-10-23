<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex,nofollow">
	<title>DokLinc - Aktivasi Akun Nakes</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
						<h1 class="dl-nakes-auth-title">Aktivasi akun Nakes</h1>
						<p class="dl-nakes-auth-subtitle">Langkah 1 dari 2: masukkan nama pengguna dan password sementara dari Admin Dinas Kesehatan.</p>
						<?php
						/* Sebab galat dipetakan dari kode, bukan kalimat yang dikirim
						   controller: satu kode, satu kalimat, dan tidak ada nilai dari
						   luar yang dipakai mentah. */
						$activation_messages = array(
							'workflow_unavailable' => 'Aktivasi akun belum tersedia. Hubungi Admin Dinas Kesehatan.',
							'credential_mismatch' => 'Nama pengguna atau password sementara belum sesuai. Periksa kembali tulisan pada ringkasan serah-terima, atau minta reset ke Admin Dinas Kesehatan.',
							'account_inactive' => 'Akun ini belum diaktifkan Admin Dinas Kesehatan, jadi aktivasi belum bisa dilanjutkan.',
							'already_activated' => 'Akun ini sudah diaktivasi. Masuk dengan password pribadi Anda.',
							'identity_incomplete' => 'Akun ini belum tertaut data staf Puskesmas, jadi aktivasi belum bisa dilanjutkan. Hubungi Admin Dinas Kesehatan.',
							'throttled' => 'Terlalu banyak percobaan. Tunggu beberapa menit, lalu coba lagi.',
						);
						$error_code = isset($error_code) ? (string) $error_code : '';
						$error_message = isset($activation_messages[$error_code]) ? $activation_messages[$error_code] : '';
						?>
						<?php if ($error_message !== '') : ?>
							<div class="alert alert-danger" role="alert">
								<?= html_escape($error_message); ?>
								<?php if ($error_code === 'already_activated') : ?>
									<div class="mt-2"><a href="<?= html_escape(site_url('login-nakes')); ?>" class="dl-nakes-auth-link">Masuk Nakes</a></div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<p class="dl-nakes-auth-note">Jika data cocok, Anda akan diminta membuat password pribadi sebelum masuk ke beranda.</p>
						<form method="post" action="<?= html_escape(site_url('login-nakes/activate/auth')); ?>" autocomplete="off">
							<div class="dl-nakes-auth-field">
								<label class="form-label" for="activation_username">Nama pengguna</label>
								<div class="dl-nakes-auth-control">
									<i class="fas fa-user dl-nakes-auth-icon" aria-hidden="true"></i>
									<input class="form-control" type="text" id="activation_username" name="username" maxlength="100" autocomplete="username" autocapitalize="none" spellcheck="false" required>
								</div>
							</div>
							<div class="dl-nakes-auth-field">
								<label class="form-label" for="activation_password">Password sementara</label>
								<div class="dl-nakes-auth-control">
									<i class="fas fa-lock dl-nakes-auth-icon" aria-hidden="true"></i>
									<input class="form-control" type="password" id="activation_password" name="password" maxlength="72" autocomplete="current-password" required>
									<button type="button" class="dl-nakes-auth-toggle-pw" data-target="activation_password" aria-label="Tampilkan password sementara">
										<i class="fas fa-eye" aria-hidden="true"></i>
									</button>
								</div>
							</div>
							<div class="d-grid mb-3">
								<button class="dl-nakes-auth-btn" type="submit">Lanjutkan aktivasi</button>
							</div>
						</form>
						<hr class="dl-nakes-auth-divider">
						<div class="text-center">
							<p class="dl-nakes-auth-footer-text">Sudah punya password pribadi? <a href="<?= html_escape(site_url('login-nakes')); ?>" class="dl-nakes-auth-link">Masuk Nakes</a></p>
							<p class="dl-nakes-auth-footer-text">Bukan tenaga kesehatan? <a href="<?= html_escape(site_url('login')); ?>" class="dl-nakes-auth-link">Masuk sebagai warga</a></p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script>
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
