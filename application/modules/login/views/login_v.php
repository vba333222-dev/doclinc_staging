<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DocLink - Masuk</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= base_url('assets/css/style.css'); ?>">
	<style>
		.auth-body {
			background: #1F6F4B;
			font-family: 'Manrope', system-ui, -apple-system, sans-serif;
			min-height: 100vh;
		}
		.auth-logo {
			height: 64px;
			width: auto;
			margin-bottom: 28px;
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
			background: #f8fbfa;
		}
		.auth-field {
			position: relative;
			margin-bottom: 18px;
		}
		.auth-field .auth-icon {
			position: absolute;
			top: 50%;
			left: 14px;
			transform: translateY(-50%);
			color: #8fa8a0;
			font-size: 15px;
			pointer-events: none;
			z-index: 2;
		}
		.auth-field input {
			min-height: 48px;
			border-radius: 12px;
			border: 1.5px solid #dce6e1;
			background: #fff;
			font-family: 'Manrope', sans-serif;
			font-size: 14px;
			font-weight: 600;
			color: #1f2a24;
			padding: 10px 44px 10px 42px;
			transition: border-color .2s, box-shadow .2s;
		}
		.auth-field input:focus {
			border-color: #1F6F4B;
			box-shadow: 0 0 0 3px rgba(31, 111, 75, .12);
		}
		.auth-field input::placeholder {
			color: #a0b0a8;
			font-weight: 500;
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
			padding: 10px;
			min-width: 44px;
			min-height: 44px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
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
			letter-spacing: .02em;
			transition: background .2s, transform .1s;
		}
		.auth-btn:hover {
			background: #177a42;
		}
		.auth-btn:active {
			transform: scale(.98);
		}
		.auth-btn:disabled {
			opacity: .7;
		}
		.auth-divider {
			border: none;
			border-top: 1px solid #e7ece9;
			margin: 20px 0;
		}
		.auth-link {
			color: #1F6F4B;
			font-weight: 700;
			text-decoration: none;
			transition: color .2s;
		}
		.auth-link:hover {
			color: #177a42;
		}
		.auth-footer-text {
			color: #66756D;
			font-size: 13px;
			font-weight: 500;
			margin-bottom: 0;
		}
	</style>
</head>

<body class="auth-body">
	<div class="container">
		<div class="row d-flex align-items-center justify-content-center vh-100">
			<div class="col-11 col-sm-8 col-md-5 col-lg-4">
				<div class="text-center">
					<img class="auth-logo" src="<?= base_url('assets/images/doklinc.png'); ?>" alt="DocLink" style="height:72px;width:auto;">
				</div>
				<div class="auth-card">
					<div class="auth-card-body">
						<?php if (!empty($success_message)): ?>
							<div class="alert alert-success" role="status"><?= html_escape($success_message); ?></div>
						<?php endif; ?>

						<form id="loginForm">
							<div class="auth-field">
								<label for="username" class="visually-hidden">Nama pengguna</label>
								<i class="fas fa-user auth-icon"></i>
								<input type="text" class="form-control" id="username" placeholder="Nama pengguna" required>
							</div>

							<div class="auth-field">
								<label for="password" class="visually-hidden">Password</label>
								<i class="fas fa-lock auth-icon"></i>
								<input type="password" class="form-control" id="password" placeholder="Password" required>
				<button type="button" class="auth-toggle-pw" tabindex="0" data-target="password" aria-label="Tampilkan password">
									<i class="fas fa-eye"></i>
								</button>
							</div>

							<div class="d-grid mb-3">
								<button type="submit" class="auth-btn">Masuk</button>
							</div>
						</form>

						<hr class="auth-divider">
						<div class="text-center">
							<p class="auth-footer-text">Belum punya akun? <a href="<?= site_url('sign_up'); ?>" class="auth-link">Daftar di sini</a></p>
							<?php if (!empty($activation_available)): ?>
								<p class="mt-2 mb-0"><a href="<?= html_escape(site_url('login/activate')); ?>" class="auth-link">Aktivasi akun Nakes</a></p>
							<?php endif; ?>
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
								'<span role="status"> Loading...</span>';
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

	</script>

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
