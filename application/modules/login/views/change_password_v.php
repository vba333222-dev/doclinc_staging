<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex,nofollow">
	<title>DokLinc - Perbarui Password</title>
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
					<img class="dl-auth-logo" src="<?= base_url('assets/images/doklinc.png'); ?>" alt="DokLinc" width="3167" height="1396" decoding="async">
				</div>
				<div class="dl-auth-card">
					<div class="dl-auth-card-body">
						<h1 class="dl-auth-title">Perbarui Password</h1>
						<p class="dl-auth-subtitle"><?= !empty($activation_mode) ? 'Buat password pribadi untuk menyelesaikan aktivasi akun.' : 'Buat password baru sebelum melanjutkan.'; ?></p>
						<?php if (!empty($error_message)) : ?>
							<div class="alert alert-danger" role="alert"><?= html_escape($error_message); ?></div>
						<?php endif; ?>
						<form method="post" action="<?= html_escape(site_url('login/change-password/submit')); ?>" autocomplete="off">
							<input type="hidden" name="password_change_token" value="<?= html_escape($form_token); ?>">
							<div class="dl-auth-field">
								<label class="form-label" for="new_password">Password baru</label>
								<div class="dl-auth-control">
									<i class="fas fa-lock dl-auth-icon" aria-hidden="true"></i>
									<input class="form-control" type="password" id="new_password" name="new_password" minlength="8" maxlength="72" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}" autocomplete="new-password" aria-describedby="new_password_hint" required>
									<button type="button" class="dl-auth-toggle-pw" data-target="new_password" aria-label="Tampilkan password baru">
										<i class="fas fa-eye" aria-hidden="true"></i>
									</button>
								</div>
								<div class="dl-auth-hint" id="new_password_hint">Minimal 8 karakter: huruf besar, huruf kecil, angka, dan karakter khusus. Jangan gunakan nomor telepon atau identitas login.</div>
							</div>
							<div class="dl-auth-field">
								<label class="form-label" for="confirm_password">Konfirmasi password baru</label>
								<div class="dl-auth-control">
									<i class="fas fa-lock dl-auth-icon" aria-hidden="true"></i>
									<input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required>
									<button type="button" class="dl-auth-toggle-pw" data-target="confirm_password" aria-label="Tampilkan konfirmasi password">
										<i class="fas fa-eye" aria-hidden="true"></i>
									</button>
								</div>
							</div>
							<button class="dl-auth-btn w-100" type="submit">Simpan dan Lanjutkan</button>
						</form>
						<?php if (empty($activation_mode)): ?>
							<form method="post" action="<?= html_escape(site_url('login/logout')); ?>" class="mt-4 text-center">
								<button type="submit" class="dl-auth-linkbtn">Keluar</button>
							</form>
						<?php else: ?>
							<form method="post" action="<?= html_escape(site_url('login/logout')); ?>" class="mt-4 text-center">
								<button type="submit" class="dl-auth-linkbtn">Batalkan aktivasi</button>
							</form>
						<?php endif; ?>
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
