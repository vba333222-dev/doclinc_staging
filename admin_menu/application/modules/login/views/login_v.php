<?php
define('SITE_KEY', '6Lf_m3AiAAAAAAZp-LaIetcNMbsWdNQx7_yKvCtV');
define('SECRET_KEY', '6Lf_m3AiAAAAACYyqHSHYMf9Bt5uMn8dnRDJyPhu');
$doclinc_admin_base_url = rtrim(base_url(), '/');
$doclinc_public_base_url = preg_replace('#/admin_menu$#', '', $doclinc_admin_base_url);
$doclinc_logo_url = $doclinc_public_base_url . '/assets/images/doklinc.png';
?>
<!DOCTYPE html>
<html lang="en">

<head>

	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="description" content="">
	<meta name="author" content="">
	<!-- Favicons -->
	<!-- <link rel="apple-touch-icon" sizes="57x57" href="<?php echo base_url(); ?>assets/img/apple-icon-57x57.png">
  <link rel="apple-touch-icon" sizes="60x60" href="<?php echo base_url(); ?>assets/img/apple-icon-60x60.png">
  <link rel="apple-touch-icon" sizes="72x72" href="<?php echo base_url(); ?>assets/img/apple-icon-72x72.png">
  <link rel="apple-touch-icon" sizes="76x76" href="<?php echo base_url(); ?>assets/img/apple-icon-76x76.png">
  <link rel="apple-touch-icon" sizes="114x114" href="<?php echo base_url(); ?>assets/img/apple-icon-114x114.png">
  <link rel="apple-touch-icon" sizes="120x120" href="<?php echo base_url(); ?>assets/img/apple-icon-120x120.png">
  <link rel="apple-touch-icon" sizes="144x144" href="<?php echo base_url(); ?>assets/img/apple-icon-144x144.png">
  <link rel="apple-touch-icon" sizes="152x152" href="<?php echo base_url(); ?>assets/img/apple-icon-152x152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="<?php echo base_url(); ?>assets/img/apple-icon-180x180.png">
  <link rel="icon" type="image/png" sizes="32x32" href="<?php echo base_url(); ?>assets/img/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="96x96" href="<?php echo base_url(); ?>assets/img/favicon-96x96.png">
  <link rel="icon" type="image/png" sizes="16x16" href="<?php echo base_url(); ?>assets/img/favicon-16x16.png"> -->
	<link rel="manifest" href="<?php echo base_url(); ?>assets/img/manifest.json">
	<meta name="msapplication-TileColor" content="#ffffff">
	<meta name="msapplication-TileImage" content="<?php echo base_url(); ?>assets/img/ms-icon-144x144.png">
	<meta name="theme-color" content="#ffffff">
	<title>Admin DocLink</title>
	<script>
		(function() {
			var storedTheme = null;
			try {
				storedTheme = localStorage.getItem('doclinc_admin_theme');
			} catch (error) {}
			document.documentElement.setAttribute('data-theme', storedTheme === 'dark' ? 'dark' : 'light');
		})();
	</script>
	<!-- Custom fonts for this template-->
	<link href="<?php echo base_url(); ?>assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
	<link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
	<!-- Custom styles for this template-->
	<link href="<?php echo base_url(); ?>assets/css/sb-admin-2.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
	<script src="<?php echo base_url(); ?>assets/vendor/jquery/jquery.min.js"></script>
	<!-- Sweet Alert -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<!-- Custom CSS -->
	<link href="<?php echo base_url(); ?>assets/css/custom.css" rel="stylesheet">
	<script src="https://www.google.com/recaptcha/api.js" async defer></script>
	<link rel="icon" type="image/png" href="<?php echo html_escape($doclinc_logo_url); ?>">
</head>

<body class="bg-brown-100 doclinc-login-page">
	<div class="container">
		<div class="row justify-content-center align-items-center doclinc-login-shell">
			<div class="col-md-7 col-lg-5">
				<div class="card border-0 shadow-lg rounded-4 animate__animated animate__fadeInDown doclinc-login-card">
					<div class="card-body p-5">
						<div class="text-center mb-4">
							<img src="<?php echo html_escape($doclinc_logo_url); ?>" alt="DocLink" class="doclinc-login-logo">
							<h1 class="h4 text-primary font-weight-bold mb-2">Masuk ke Admin DocLink</h1>
						</div>
						<div class="info text-center mb-3">
							<?php echo $this->session->flashdata('info'); ?>
						</div>
						<form class="user">
							<div class="form-group mb-4">
								<label for="email" class="text-primary font-weight-bold">Email</label>
								<div class="input-group">
									<div class="input-group-prepend">
										<span class="input-group-text bg-white border-right-0"><i class="fas fa-envelope text-primary"></i></span>
									</div>
									<input type="email" class="form-control border-left-0 border-primary" id="email" name="email" placeholder="Masukkan alamat email">
								</div>
								<div class="invalid-feedback">Email tidak boleh kosong.</div>
							</div>
							<div class="form-group mb-4">
								<label for="password" class="text-primary font-weight-bold">Password</label>
								<div class="input-group">
									<div class="input-group-prepend">
										<span class="input-group-text bg-white border-right-0"><i class="fas fa-lock text-primary"></i></span>
									</div>
									<input type="password" class="form-control border-left-0 border-primary" id="password" name="password" placeholder="Masukkan password">
								</div>
								<div class="invalid-feedback">Password tidak boleh kosong.</div>
							</div>
							<button id="login" class="btn btn-primary btn-block rounded-pill py-2 font-weight-bold shadow-sm">
								<i class="fas fa-sign-in-alt mr-2"></i>Masuk
							</button>
						</form>
						<div class="text-center mt-3">
							<a href="#resetPassword" class="btn btn-link text-primary" data-toggle="modal">
								<i class="fas fa-question-circle"></i> Lupa password?
							</a>
						</div>
					</div>
				</div>
				<div class="text-center mt-4">
					<small class="text-muted">Copyright © DocLink <?php echo date('Y'); ?></small>
				</div>
			</div>
		</div>
	</div>
	<div class="modal fade" id="resetPassword" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Lupa password?</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<form action="<?php echo site_url('login/reset_password'); ?>" method="POST" id="lupa">
						<div id="pesan_cust"></div>
						<div class="form-group mb-3">
							<label for="email_cust">Email aktif</label>
							<input type="email" class="form-control" id="email_cust" name="email_cust" placeholder="name@example.com" required>
						</div>
						<div class="form-group mb-3">
							<label class="sr-only">Token</label>
							<input type="hidden" class="form-control" id="g-recaptcha-response" placeholder="Token" readonly required>
						</div>
						<center class="mb-3">
							<div class="g-recaptcha" id="g-recaptcha" data-sitekey="<?= SITE_KEY; ?>"></div>
							<div id="invalid-captcha"></div>
						</center>
						<button class="btn btn-primary btn-block" type="submit" id="lupa_submit">Kirim password baru</button>
					</form>
				</div>
			</div>
		</div>
	</div>
	<script type="text/javascript">
		$(document).ready(function() {
			$('#email').focus();
			$('#login').click(function(event) {
				var email = $('#email').val();
				var password = $('#password').val();
				if (email != '' && password != '') {
					$.ajax({
						type: "POST",
						url: "<?php echo site_url('login/ceklogin'); ?>",
						data: {
							email: email,
							password: password
						},
						success: function(result) {
							if (result == 1) {
								Swal.fire({
									icon: 'success',
									title: 'Yeay!',
									html: 'Kamu berhasil login',
									showConfirmButton: false,
									timer: 1500
								}).then((result) => {
									if (result.dismiss === Swal.DismissReason.timer) {
										window.location.reload(result);
									}
								});
							} else {
								Swal.fire({
									icon: 'error',
									title: 'Oops!',
									html: 'Akun kamu belum ada/terverifikasi di sistem kami',
									showConfirmButton: false,
									timer: 2500
								});
							}
						}
					});
				} else {
					Swal.fire({
						icon: 'error',
						title: 'Oops!',
						html: 'Sepertinya ada yang salah nih,<br>Coba periksa email atau password kamu',
						confirmButtonText: 'Yuk ulangi'
					})
				}
				return false;
			});
			$('form#lupa').submit(function(event) {
				if ($('#g-recaptcha-response').val() == '') {
					alert('Lengkapi captcha.');
					return false;
				} else {
					$('#lupa_submit').attr('disabled', true);
					$('#lupa_submit').html('Sedang diproses')
				}
			});
		});
	</script>
	<!-- Bootstrap core JavaScript-->
	<script src="<?php echo base_url(); ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

	<!-- Core plugin JavaScript-->
	<script src="<?php echo base_url(); ?>assets/vendor/jquery-easing/jquery.easing.min.js"></script>

	<!-- Custom scripts for all pages-->
	<script src="<?php echo base_url(); ?>assets/js/sb-admin-2.min.js"></script>

</body>

</html>
