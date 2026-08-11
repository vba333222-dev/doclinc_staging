<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex,nofollow">
	<title>DocLink - Aktivasi Akun Nakes</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/style.css')); ?>">
</head>
<body class="bg-success">
	<div class="container">
		<div class="row d-flex align-items-center justify-content-center min-vh-100 py-4">
			<div class="col-md-6 col-lg-5">
				<div class="card shadow-lg border-0" style="border-radius: 12px;">
					<div class="card-body p-4">
						<h1 class="h3 mb-3">Aktivasi akun Nakes</h1>
						<p class="text-muted">Masukkan nama pengguna dan password sementara yang diberikan Admin Dinas Kesehatan.</p>
						<?php if (!empty($error_message)) : ?>
							<div class="alert alert-danger" role="alert"><?= html_escape($error_message); ?></div>
						<?php endif; ?>
						<form method="post" action="<?= html_escape(site_url('login/activate/auth')); ?>" autocomplete="off">
							<div class="mb-3">
								<label class="form-label" for="activation_username">Nama pengguna</label>
								<input class="form-control" type="text" id="activation_username" name="username" maxlength="100" autocomplete="username" required>
							</div>
							<div class="mb-4">
								<label class="form-label" for="activation_password">Password sementara</label>
								<input class="form-control" type="password" id="activation_password" name="password" maxlength="72" autocomplete="current-password" required>
							</div>
							<button class="btn btn-success w-100" type="submit">Lanjutkan aktivasi</button>
						</form>
						<div class="mt-3 text-center"><a href="<?= html_escape(site_url('login')); ?>">Kembali ke halaman masuk</a></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="<?= base_url('assets/js/doclinc-password-mask.js'); ?>"></script>
</body>
</html>
