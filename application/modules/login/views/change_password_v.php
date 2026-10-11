<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex,nofollow">
	<title>DocLink - Perbarui Password</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/style.css')); ?>">
	<style>
		.auth-body {
			background: #1F6F4B;
			font-family: 'Manrope', system-ui, -apple-system, sans-serif;
			min-height: 100vh;
		}
		.auth-card {
			background: #fff;
			border: none;
			border-radius: 18px;
			box-shadow: 0 20px 60px rgba(0, 0, 0, .15);
			overflow: hidden;
		}
		.auth-card-body {
			padding: 32px 28px 28px;
		}
		.auth-title {
			font-family: 'Manrope', sans-serif;
			font-size: 22px;
			font-weight: 800;
			color: #1f2a24;
			margin-bottom: 6px;
		}
		.auth-subtitle {
			color: #66756D;
			font-size: 13px;
			font-weight: 500;
			margin-bottom: 24px;
		}
		.auth-field {
			position: relative;
			margin-bottom: 18px;
		}
		.auth-field label {
			color: #66756D;
			font-size: 12px;
			font-weight: 700;
			margin-bottom: 6px;
		}
		.auth-pw-wrap {
			position: relative;
		}
		.auth-field input {
			min-height: 46px;
			border-radius: 12px;
			border: 1.5px solid #dce6e1;
			background: #fff;
			font-family: 'Manrope', sans-serif;
			font-size: 14px;
			font-weight: 600;
			color: #1f2a24;
			transition: border-color .2s, box-shadow .2s;
		}
		.auth-field input:focus {
			border-color: #1F6F4B;
			box-shadow: 0 0 0 3px rgba(31, 111, 75, .12);
		}
		.auth-hint {
			color: #66756D;
			font-size: 11px;
			font-weight: 600;
			margin-top: -10px;
			margin-bottom: 18px;
		}
		.auth-toggle-pw {
			position: absolute;
			top: 50%;
			right: 12px;
			transform: translateY(-50%);
			background: none;
			border: none;
			color: #8fa8a0;
			font-size: 16px;
			cursor: pointer;
			padding: 4px 6px;
			border-radius: 8px;
			transition: color .2s, background .2s;
			z-index: 2;
		}
		.auth-toggle-pw:hover {
			color: #1F6F4B;
			background: rgba(31, 111, 75, .06);
		}
		.auth-btn {
			min-height: 48px;
			border-radius: 12px;
			background: #1F6F4B;
			border: none;
			color: #fff;
			font-family: 'Manrope', sans-serif;
			font-size: 15px;
			font-weight: 800;
			transition: background .2s, transform .1s;
		}
		.auth-btn:hover {
			background: #177a42;
		}
		.auth-btn:active {
			transform: scale(.98);
		}
		.auth-link {
			color: #89f6da;
			font-weight: 700;
			text-decoration: none;
			transition: color .2s;
		}
		.auth-link:hover {
			color: #6de8c4;
		}
	</style>
</head>
<body class="auth-body">
	<div class="container">
		<div class="row d-flex align-items-center justify-content-center min-vh-100 py-4">
			<div class="col-11 col-sm-8 col-md-5 col-lg-4">
				<div class="auth-card">
					<div class="auth-card-body">
						<h1 class="auth-title">Perbarui Password</h1>
						<p class="auth-subtitle"><?= !empty($activation_mode) ? 'Buat password pribadi untuk menyelesaikan aktivasi akun.' : 'Buat password baru sebelum melanjutkan.'; ?></p>
						<?php if (!empty($error_message)) : ?>
							<div class="alert alert-danger" role="alert"><?= html_escape($error_message); ?></div>
						<?php endif; ?>
						<form method="post" action="<?= html_escape(site_url('login/change-password/submit')); ?>" autocomplete="off">
							<input type="hidden" name="password_change_token" value="<?= html_escape($form_token); ?>">
							<div class="auth-field">
								<label class="form-label" for="new_password">Password baru</label>
								<div class="auth-pw-wrap">
									<input class="form-control" type="password" id="new_password" name="new_password" minlength="8" maxlength="72" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}" autocomplete="new-password" required style="padding-right: 44px;">
									<button type="button" class="auth-toggle-pw" tabindex="-1" data-target="new_password" aria-label="Tampilkan password baru">
										<i class="fas fa-eye"></i>
									</button>
								</div>
								<div class="auth-hint">Minimal 8 karakter: huruf besar, huruf kecil, angka, dan karakter khusus. Jangan gunakan nomor telepon atau identitas login.</div>
							</div>
							<div class="auth-field">
								<label class="form-label" for="confirm_password">Konfirmasi password baru</label>
								<div class="auth-pw-wrap">
									<input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required style="padding-right: 44px;">
									<button type="button" class="auth-toggle-pw" tabindex="-1" data-target="confirm_password" aria-label="Tampilkan konfirmasi password">
										<i class="fas fa-eye"></i>
									</button>
								</div>
							</div>
							<button class="auth-btn w-100" type="submit">Simpan dan Lanjutkan</button>
						</form>
						<?php if (empty($activation_mode)): ?>
							<form method="post" action="<?= html_escape(site_url('login/logout')); ?>" class="mt-4 text-center">
								<button type="submit" class="auth-link" style="background:none;border:none;cursor:pointer;font-family:inherit;font-size:13px;">Keluar</button>
							</form>
						<?php else: ?>
							<form method="post" action="<?= html_escape(site_url('login/logout')); ?>" class="mt-4 text-center">
								<button type="submit" class="auth-link" style="background:none;border:none;cursor:pointer;font-family:inherit;font-size:13px;">Batalkan aktivasi</button>
							</form>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script>
		document.querySelectorAll('.auth-toggle-pw').forEach(function(btn) {
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
