<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex,nofollow">
	<title>DocLink - Perbarui Password</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/style.css')); ?>">
</head>
<body class="bg-success">
	<div class="container">
		<div class="row d-flex align-items-center justify-content-center min-vh-100 py-4">
			<div class="col-md-6 col-lg-5">
				<div class="card shadow-lg border-0" style="border-radius: 12px;">
					<div class="card-body p-4">
						<h1 class="h3 mb-3">Perbarui Password</h1>
						<p class="text-muted">Untuk keamanan akun dan data pasien, buat password baru sebelum melanjutkan.</p>
						<?php if (!empty($error_message)) : ?>
							<div class="alert alert-danger" role="alert"><?= html_escape($error_message); ?></div>
						<?php endif; ?>
						<form method="post" action="<?= html_escape(site_url('login/change-password/submit')); ?>" autocomplete="off">
							<input type="hidden" name="password_change_token" value="<?= html_escape($form_token); ?>">
							<div class="mb-3">
								<label class="form-label" for="new_password">Password baru</label>
								<input class="form-control" type="password" id="new_password" name="new_password" minlength="10" maxlength="72" autocomplete="new-password" required>
								<div class="form-text">Gunakan minimal 10 karakter dan jangan gunakan nomor telepon atau identitas login.</div>
							</div>
							<div class="mb-4">
								<label class="form-label" for="confirm_password">Konfirmasi password baru</label>
								<input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="10" maxlength="72" autocomplete="new-password" required>
							</div>
							<button class="btn btn-success w-100" type="submit">Simpan dan Lanjutkan</button>
						</form>
						<form method="post" action="<?= html_escape(site_url('login/logout')); ?>" class="mt-3 text-center">
							<button type="submit" class="btn btn-link text-secondary">Keluar</button>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
