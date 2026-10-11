<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DocLink - Daftar</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
			height: 56px;
			width: auto;
			margin-bottom: 8px;
		}
		.auth-heading {
			color: #fff;
			font-weight: 800;
			font-size: 14px;
			letter-spacing: .08em;
			text-transform: uppercase;
			margin-bottom: 20px;
		}
		.auth-card {
			background: #fff;
			border: none;
			border-radius: 18px;
			box-shadow: 0 20px 60px rgba(0, 0, 0, .15);
			overflow: hidden;
		}
		.auth-card-body {
			padding: 28px 24px 24px;
			background: #f8fbfa;
		}
		.auth-field {
			position: relative;
			margin-bottom: 14px;
		}
		.auth-field label {
			color: #66756D;
			font-size: 12px;
			font-weight: 700;
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
		.auth-field input::placeholder {
			color: #a0b0a8;
			font-weight: 500;
		}
		.auth-pw-wrap {
			position: relative;
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
		.auth-hint {
			color: #66756D;
			font-size: 11px;
			font-weight: 600;
			margin-bottom: 14px;
		}
		.auth-btn {
			min-height: 46px;
			border-radius: 12px;
			background: #1F6F4B;
			border: none;
			color: #fff;
			font-family: 'Manrope', sans-serif;
			font-size: 14px;
			font-weight: 800;
			transition: background .2s, transform .1s;
		}
		.auth-btn:hover {
			background: #177a42;
		}
		.auth-btn:active {
			transform: scale(.98);
		}
		.auth-btn-outline {
			min-height: 46px;
			border-radius: 12px;
			background: transparent;
			border: 1.5px solid #8fa8a0;
			color: #1F6F4B;
			font-family: 'Manrope', sans-serif;
			font-size: 14px;
			font-weight: 700;
			transition: background .2s, border-color .2s;
		}
		.auth-btn-outline:hover {
			background: rgba(31, 111, 75, .08);
			border-color: #1F6F4B;
		}
	</style>
</head>
<body class="auth-body">
	<div class="container">
		<div class="row d-flex align-items-center justify-content-center vh-100">
			<div class="col-11 col-sm-8 col-md-5 col-lg-4">
				<div class="text-center">
					<img class="auth-logo" src="<?= base_url('assets/images/doklinc.png'); ?>" alt="DocLink" style="height:64px;width:auto;">
					<p class="auth-heading">Daftar</p>
				</div>
				<div class="auth-card">
					<div class="auth-card-body">
						<form id="signupForm">
							<div class="auth-field">
								<label for="nama_lengkap">Nama lengkap</label>
								<input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Nama lengkap" required>
							</div>
							<div class="auth-field">
								<label for="email">Email</label>
								<input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
							</div>
							<div class="auth-field">
								<label for="no_hp">Nomor HP</label>
								<input type="text" class="form-control" id="no_hp" name="no_hp" placeholder="Nomor HP" required>
							</div>
							<div class="auth-field">
								<label for="username">Nama pengguna</label>
								<input type="text" class="form-control" id="username" name="username" placeholder="Nama pengguna" autocomplete="off" required>
							</div>
							<div class="auth-field">
								<label for="password">Password</label>
								<div class="auth-pw-wrap">
									<input type="password" class="form-control" id="password" name="password" placeholder="Password" minlength="8" maxlength="72" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}" autocomplete="new-password" required style="padding-right: 44px;">
									<button type="button" class="auth-toggle-pw" tabindex="0" data-target="password" aria-label="Tampilkan password">
										<i class="fas fa-eye"></i>
									</button>
								</div>
							</div>
							<div class="auth-hint">Minimal 8 karakter: huruf besar, huruf kecil, angka, dan karakter khusus.</div>
							<div class="auth-field">
								<label for="k_password">Konfirmasi password</label>
								<div class="auth-pw-wrap">
									<input type="password" class="form-control" id="k_password" name="k_password" placeholder="Konfirmasi password" minlength="8" maxlength="72" autocomplete="new-password" required style="padding-right: 44px;">
									<button type="button" class="auth-toggle-pw" tabindex="0" data-target="k_password" aria-label="Tampilkan konfirmasi password">
										<i class="fas fa-eye"></i>
									</button>
								</div>
							</div>
							<div class="row g-2 mt-1">
								<div class="col">
									<div class="d-grid">
										<button type="submit" class="auth-btn">Daftar</button>
									</div>
								</div>
								<div class="col">
									<div class="d-grid">
										<a href="<?= site_url('login');?>" class="auth-btn-outline">Batal</a>
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
