<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DocLink - Daftar</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="<?= base_url('assets/css/style.css'); ?>">
</head>
<body class="bg-success">
	<div class="container">
		<div class="row d-flex align-items-center justify-content-center vh-100">
			<div class="col-md-4">
				<center>
					<img class="p-2" src="<?= base_url('assets/images/doklincwhite.png'); ?>" alt="" height="70px">
					<p class="fw-bold text-white text-uppercase">Daftar</p>
				</center>
				<div class="card shadow-lg" style="border-radius: 10px; overflow: hidden;">
					<div class="card-body" style="background-color: #e0f2f1;">
						<form id="signupForm">
							<div class="form-floating mb-2">
							  <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Nama lengkap">
							  <label for="nama_lengkap">Nama lengkap</label>
							</div>
							<div class="form-floating mb-2">
							  <input type="email" class="form-control" id="email" name="email" placeholder="Email">
							  <label for="email">Email</label>
							</div>
							<div class="form-floating mb-2">
							  <input type="text" class="form-control" id="no_hp" name="no_hp" placeholder="Nomor HP">
							  <label for="no_hp">Nomor HP</label>
							</div>
							<div class="form-floating mb-2">
							  <input type="text" class="form-control" id="username" name="username" placeholder="Nama pengguna" autocomplete="off">
							  <label for="username">Nama pengguna</label>
							</div>
							<div class="form-floating mb-2">
							  <input type="password" class="form-control" id="password" name="password" placeholder="Password" autocomplete="off">
							  <label for="password">Password</label>
							</div>
							<div class="form-floating mb-2">
							  <input type="password" class="form-control" id="k_password" name="k_password" placeholder="Konfirmasi password">
							  <label for="k_password">Konfirmasi password</label>
							</div>
							<div class="row g-2">
				                <div class="col">
				                    <div class="d-grid">

				                        <button type="submit" class="btn btn-success">Daftar</button>

				                    </div>

				                </div>

				                <div class="col">

				                    <div class="d-grid">

				                        <a href="<?= site_url('login');?>" class="btn btn-secondary">Batal</a>
				                    </div>
				                </div>
				            </div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>

	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>





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
					Swal.fire({
						title: "Akun belum dibuat",
						text: "Terjadi kesalahan. Coba lagi.",
						icon: "error",
						showConfirmButton: true
					});
				}
			});
		});
	</script>
</body>
</html>
