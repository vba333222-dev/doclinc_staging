<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>SehatGeh - Home</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
	<style type="text/css">
		.content {
			display: none;
			padding: 15px;
		}

		.content.active {
			display: block;
		}

		.card-header {
			background-color: #09AD74;
			color: white;
		}

		.header {
			background-color: #09AD74;
			color: white;
			padding: 15px;
			text-align: center;
			font-size: 1.5rem;
			font-weight: bold;
		}

		/* Style untuk preloader */
		#preloader {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: #09AD74;
			display: flex;
			justify-content: center;
			align-items: center;
			z-index: 9999;
			font-family: Arial, sans-serif;
			color: white;
		}

		/* Style untuk konten utama */
		#content-wrapper,
		#nav-bottom-wrapper {
			display: none;
		}

        .article-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .article {
            display: flex;
            flex-direction: column;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .article img {
            width: 100%;
            height: auto;
        }

        .article-content {
            padding: 15px;
        }

        .article-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 10px;
        }

        .article-author {
            font-size: 14px;
            color: gray;
            margin-bottom: 10px;
        }

        .article-link {
            text-decoration: none;
            color: #3498db;
        }

        /* Mobile responsive */
        @media(min-width: 768px) {
            .article {
                flex-direction: row;
                max-width: 600px;
                margin: auto;
            }

            .article img {
                width: 150px;
                height: 150px;
                object-fit: cover;
            }

            .article-content {
                padding: 15px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            .article-title {
                font-size: 20px;
            }

            .article-author {
                font-size: 16px;
            }
        }
	</style>
</head>

<body class="bg-light">
	<div id="preloader">
		<div class="text-center">
			<img class="animate__animated animate__bounceIn" src="<?= base_url(); ?>assets/images/robinsar-fajar.png" alt="" height="100px">
			<p class="mb-0">
			<div class="spinner-border spinner-border-sm text-light" role="status">
				<span class="visually-hidden">Loading...</span>
			</div>
			Memuat...
			</p>
		</div>
	</div>
	<div class="content-wrapper" id="content-wrapper">
		<div class="contents">
			<div class="hero bg-success p-3 overflow-hidden">
				<a class="notify" href="#">
					<i class="bi bi-bell-fill fs-4"></i>
					<!-- kalo ada notif fetch datanya dari sini ya, bukan dari dalem elemen span nya -->
					<span class="notify-number" id="notify-number">9+</span>
					<!-- sampe sini -->
				</a>
				<div class="text-white mb-2">
					<i class="fas fa-map-marker-alt me-2"></i><small>Lambangsari, Bojonegara</small>
				</div>
				<div class="d-flex animate__animated animate__fadeInUp animate__faster">
					<div class="flex-shrink-0">
						<img class="rounded-4 shadow" src="<?= base_url(); ?>assets/images/gambar2.jpg" width="100px" height="100px">
					</div>
					<div class="flex-grow-1 ms-3 text-white">
						<small>Hello,</small>
						<h3 class="mb-0"><?= $this->session->userdata('nama');?>Pahlawan 1</h3>
						<p class="mb-0">35 Tahun</p>
					</div>
				</div>
			</div>
			<svg id="wave" style="transform:rotate(180deg); transition: 0.3s" viewBox="0 0 1440 120" version="1.1" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="sw-gradient-0" x1="0" x2="0" y1="1" y2="0"><stop stop-color="rgba(9, 173, 116, 1)" offset="0%"></stop><stop stop-color="rgba(140.457, 255, 215.189, 1)" offset="100%"></stop></linearGradient></defs><path style="transform:translate(0, 0px); opacity:1" fill="url(#sw-gradient-0)" d="M0,48L48,48C96,48,192,48,288,56C384,64,480,80,576,88C672,96,768,96,864,86C960,76,1056,56,1152,48C1248,40,1344,44,1440,42C1536,40,1632,32,1728,42C1824,52,1920,80,2016,78C2112,76,2208,44,2304,40C2400,36,2496,60,2592,76C2688,92,2784,100,2880,98C2976,96,3072,84,3168,74C3264,64,3360,56,3456,54C3552,52,3648,56,3744,54C3840,52,3936,44,4032,48C4128,52,4224,68,4320,64C4416,60,4512,36,4608,30C4704,24,4800,36,4896,44C4992,52,5088,56,5184,52C5280,48,5376,36,5472,44C5568,52,5664,80,5760,94C5856,108,5952,108,6048,98C6144,88,6240,68,6336,50C6432,32,6528,16,6624,18C6720,20,6816,40,6864,50L6912,60L6912,120L6864,120C6816,120,6720,120,6624,120C6528,120,6432,120,6336,120C6240,120,6144,120,6048,120C5952,120,5856,120,5760,120C5664,120,5568,120,5472,120C5376,120,5280,120,5184,120C5088,120,4992,120,4896,120C4800,120,4704,120,4608,120C4512,120,4416,120,4320,120C4224,120,4128,120,4032,120C3936,120,3840,120,3744,120C3648,120,3552,120,3456,120C3360,120,3264,120,3168,120C3072,120,2976,120,2880,120C2784,120,2688,120,2592,120C2496,120,2400,120,2304,120C2208,120,2112,120,2016,120C1920,120,1824,120,1728,120C1632,120,1536,120,1440,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"></path><defs><linearGradient id="sw-gradient-1" x1="0" x2="0" y1="1" y2="0"><stop stop-color="rgba(9, 173, 116, 1)" offset="0%"></stop><stop stop-color="rgba(9, 173, 116, 1)" offset="100%"></stop></linearGradient></defs><path style="transform:translate(0, 50px); opacity:0.9" fill="url(#sw-gradient-1)" d="M0,60L48,54C96,48,192,36,288,28C384,20,480,16,576,24C672,32,768,52,864,62C960,72,1056,72,1152,64C1248,56,1344,40,1440,30C1536,20,1632,16,1728,30C1824,44,1920,76,2016,86C2112,96,2208,84,2304,68C2400,52,2496,32,2592,34C2688,36,2784,60,2880,68C2976,76,3072,68,3168,58C3264,48,3360,36,3456,26C3552,16,3648,8,3744,18C3840,28,3936,56,4032,68C4128,80,4224,76,4320,74C4416,72,4512,72,4608,72C4704,72,4800,72,4896,66C4992,60,5088,48,5184,44C5280,40,5376,44,5472,46C5568,48,5664,48,5760,46C5856,44,5952,40,6048,46C6144,52,6240,68,6336,72C6432,76,6528,68,6624,60C6720,52,6816,44,6864,40L6912,36L6912,120L6864,120C6816,120,6720,120,6624,120C6528,120,6432,120,6336,120C6240,120,6144,120,6048,120C5952,120,5856,120,5760,120C5664,120,5568,120,5472,120C5376,120,5280,120,5184,120C5088,120,4992,120,4896,120C4800,120,4704,120,4608,120C4512,120,4416,120,4320,120C4224,120,4128,120,4032,120C3936,120,3840,120,3744,120C3648,120,3552,120,3456,120C3360,120,3264,120,3168,120C3072,120,2976,120,2880,120C2784,120,2688,120,2592,120C2496,120,2400,120,2304,120C2208,120,2112,120,2016,120C1920,120,1824,120,1728,120C1632,120,1536,120,1440,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"></path></svg>
			<div class="position-relative">
				<div id="beranda" class="content active animate__animated animate__fadeInUp animate__faster">
					<div class="row g-3 mb-3">
						<div class="col-3 text-center">
							<a href="<?= base_url('cari_ustadz'); ?>" class="feature-menu">
								<div class="icon-wrapper mx-auto">
									<i class="fas fa-user-md"></i>
									<span class="filler"></span>
								</div>
								<span class="small">Tindakan</span>
							</a>
						</div>
						<div class="col-3 text-center">
							<a href="#mesjid" data-bs-toggle="modal" class="feature-menu">
								<div class="icon-wrapper mx-auto">
									<i class="fas fa-briefcase-medical"></i>
									<span class="filler"></span>
								</div>
								<span class="small">Riwayat Kesehatan</span>
							</a>
						</div>
						<div class="col-3 text-center">
							<a href="<?= base_url('mengaji') ?>" class="feature-menu">
								<div class="icon-wrapper mx-auto">
									<i class="fas fa-heartbeat"></i>
									<span class="filler"></span>
								</div>
								<span class="small">Cek Tensi, Gula, Kolesterol</span>
							</a>
						</div>
						<div class="col-3 text-center">
							<a href="#" class="feature-menu">
								<div class="icon-wrapper mx-auto bg-secondary">
									<i class="fas fa-bars"></i>
									<span class="filler"></span>
								</div>
								<span class="text-muted small">Lainnya</span>
							</a>
						</div>
					</div>
					<div class="row">
						<div class="col">
							<small class="fw-bold">News & Feed</small>
							<div id="carouselArtikel" class="carousel slide" data-bs-ride="carousel">
							  	<div class="carousel-inner pb-3">
								    <?php
								        // URL API
								        $apiUrl = 'https://artikel-islam.netlify.app/.netlify/functions/api/fir';

								        // Mendapatkan data dari API
								        $apiResponse = file_get_contents($apiUrl);

								        // Mengubah JSON menjadi array PHP
								        $data = json_decode($apiResponse, true);
								        // echo $data;
								        $data2=substr($apiResponse, 113);
								        $lastchar = substr($data2,0, -2);
								        $result = json_decode($lastchar, true);
								        $active = 1;
								        $i = 1;
								        foreach ($result as $e) {
								    ?>
									    <div class="carousel-item <?php if ($i==$active) {echo "active";}?>">
									    	<div class="card shadow">
									    		<img src="<?php echo $e['thumbnail']; ?>" class="card-img-top" alt="Article Thumbnail">
									    		<div class="card-body">
									    			<h5 class="card-title"><?php echo $e['title']; ?></h5>
									    			<p class="card-text">Author: <?php echo $e['author']; ?> - <i><?php echo $e['date']; ?></i></p>
									    			<a href="<?php echo $e['url']; ?>" class="btn btn-success">Go somewhere</a>
									    		</div>
									    	</div>
									    </div>
								    <?php
								        $i++;}
								    ?>
							  	</div>
							  	<button class="carousel-control-prev" type="button" data-bs-target="#carouselArtikel" data-bs-slide="prev">
						            <i class="fas fa-chevron-left text-success fs-4"></i>
						            <span class="visually-hidden">Previous</span>
						        </button>
						        <button class="carousel-control-next" type="button" data-bs-target="#carouselArtikel" data-bs-slide="next">
						            <i class="fas fa-chevron-right text-success fs-4"></i>
						            <span class="visually-hidden">Next</span>
						        </button>
							</div>
						</div>
					</div>
				</div>
				<div id="artikel" class="content animate__animated animate__fadeInUp animate__faster">
					<h1>Artikel</h1>					
                    <div class="article-list">
                    </div>
				</div>
				<div id="riwayat" class="content animate__animated animate__fadeInUp animate__faster">
					<h2 class="text-center mb-4">Riwayat Hari Ini</h2>
					<div class="card shadow mb-2">
						<div class="card-header">
							Ngaji bersama Ust. Abdul Somad Al-Riawi
						</div>
						<div class="card-body">
							<p class="card-text mb-0">Surat: Al-Baqoroh</p>
							<p class="card-text mb-0">Ayat: 1-31</p>
							<p class="card-text mb-0">Jam Mengaji: 08:00 - 08:30</p>
							<p class="card-text mb-0">Tanggal Mengaji: 13 September 2024</p>
						</div>
					</div>
				</div>
				<div id="profile" class="content animate__animated animate__fadeInUp animate__faster">
					<div class="form-floating mb-2">
						<input type="text" class="form-control shadow border-success" id="nama_lengkap" value="Muhammad Bani Husni" placeholder="Nama Lengkap" readonly>
						<label for="nama_lengkap">Nama Lengkap</label>
					</div>
					<div class="form-floating mb-2">
						<input type="date" class="form-control shadow border-success" id="tgl" value="20/07/1993" placeholder="Tanggal Lahir" readonly>
						<label for="tgl">Tanggal Lahir</label>
					</div>
					<div class="form-floating mb-2">
						<input type="text" class="form-control shadow border-success" id="jk" value="Laki-laki" placeholder="Jenis Kelamin" readonly>
						<label for="jk">Jenis Kelamin</label>
					</div>
					<div class="form-floating mb-2">
						<input type="text" class="form-control shadow border-success" id="no_hp" value="087775587778" placeholder="Nomor HP" readonly>
						<label for="no_hp">Nomor HP</label>
					</div>
					<div class="form-floating mb-2">
						<textarea class="form-control shadow border-success" placeholder="Alamat" id="alamat" readonly style="height: 100px">
BCS Logistics Center
Jl. Raya Merak KM. 115, Cilegon
Banten, Indonesia - 42436
                        </textarea>
						<label for="alamat">Alamat</label>
					</div>
					<div class="d-grid mb-2">
						<button 
							type="button"
							class="btn btn-outline-success"
							data-bs-toggle="modal"
							data-bs-target="#modalProfil">Edit</button>
					</div>
					<div class="d-grid">
						<button type="button" class="btn btn-outline-danger" id="btn-logout">Logout</button>
					</div>
				</div>
			</div>
		</div>
	</div>


	<div class="nav-bottom-wrapper shadow-lg" id="nav-bottom-wrapper">
		<div class="container-fluid px-0">
			<div class="row g-0 text-center p-1 menu animate__animated animate__slideInUp animate__faster">
				<a href="#" id="beranda-tab" class="col menu-item active" onclick="showContent('beranda')"><i class="fas fa-home fs-4"></i></a>
				<a href="#" id="artikel-tab" class="col menu-item" onclick="showContent('artikel')"><i class="fas fa-user-md fs-4"></i></a>
				<a href="#" id="riwayat-tab" class="col menu-item" onclick="showContent('riwayat')"><i class="fas fa-file-medical fs-4"></i></a>
				<a href="#" id="profile-tab" class="col menu-item" onclick="showContent('profile')"><i class="fas fa-user fs-4"></i></a>
			</div>
		</div>
	</div>
	
	<div class="modal fade" id="jadwal" tabindex="-1" aria-labelledby="jadwalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h1 class="modal-title fs-5" id="jadwalLabel">Jadwal Mengajar</h1>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<!-- Jadwal 1 -->
					<div class="card mb-2">
						<div class="card-body p-2">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<div class="student-name">Ustadz Fikri Maulana</div>
									<div class="schedule-date"><i class="far fa-calendar-check"></i> 13 September 2024</div>
								</div>
								<div class="schedule-time"><i class="far fa-clock"></i> 08:00 - 08:30</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="modal fade" id="mesjid" tabindex="-1" aria-labelledby="mesjidLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h1 class="modal-title fs-5" id="jadwalLabel">Daftar Masjid</h1>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<iframe src="https://www.google.com/maps/embed?pb=!1m16!1m12!1m3!1d49207.35279659961!2d106.0216166623499!3d-6.00353089156085!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!2m1!1smasjid%20cilegon!5e1!3m2!1sen!2sid!4v1726542852582!5m2!1sen!2sid"
						width="100%"
						height="600"
						style="border:0;"
						allowfullscreen=""
						loading="lazy"
						referrerpolicy="no-referrer-when-downgrade"></iframe>
				</div>
			</div>
		</div>
	</div>
	<div class="modal fade" id="modalProfil" tabindex="-1" aria-labelledby="modalProfilLabel" aria-hidden="true">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <h1 class="modal-title fs-5" id="modalProfilLabel">Edit Profil</h1>
	        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	      </div>
	      <form action="" method="post">
		      <div class="modal-body">
		        <div class="form-floating mb-2">
					<input type="text" class="form-control shadow border-success" id="nama_lengkap_edit" value="Muhammad Bani Husni" placeholder="Nama Lengkap">
					<label for="nama_lengkap_edit">Nama Lengkap</label>
				</div>
				<div class="form-floating mb-2">
					<input type="date" class="form-control shadow border-success" id="tgl_edit" value="20/07/1993" placeholder="Tanggal Lahir">
					<label for="tgl_edit">Tanggal Lahir</label>
				</div>
				<div class="form-floating mb-2">
					<input type="text" class="form-control shadow border-success" id="jk_edit" value="Laki-laki" placeholder="Jenis Kelamin">
					<label for="jk_edit">Jenis Kelamin</label>
				</div>
				<div class="form-floating mb-2">
					<input type="text" class="form-control shadow border-success" id="no_hp_edit" value="087775587778" placeholder="Nomor HP">
					<label for="no_hp_edit">Nomor HP</label>
				</div>
				<div class="form-floating mb-2">
					<textarea class="form-control shadow border-success" placeholder="Alamat" id="alamat_edit" style="height: 100px">
	BCS Logistics Center
	Jl. Raya Merak KM. 115, Cilegon
	Banten, Indonesia - 42436
	                </textarea>
					<label for="alamat_edit">Alamat</label>
				</div>
		      </div>
		      <div class="modal-footer">
		        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
		        <button type="submit" class="btn btn-success">Save changes</button>
		      </div>
	      </form>
	    </div>
	  </div>
	</div>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<script>
		window.addEventListener('load', function() {
			setTimeout(function() {
				$('#preloader').fadeOut('fast');
				document.getElementById('content-wrapper').style.display = 'block';
				document.getElementById('nav-bottom-wrapper').style.display = 'block';
			}, 1500);
		});
		if ($('#notify-number').text() !== '') {
			$('a.notify > i').addClass('animate__animated animate__tada animate__infinite');
		}

		function showContent(tab) {
			document.querySelector('.content.active').classList.remove('active');
			document.getElementById(tab).classList.add('active');
			document.querySelector('.nav-bottom-wrapper .menu a.active').classList.remove('active');
			document.getElementById(tab + '-tab').classList.add('active');
		}

		$('#btn-logout').click(function(event) {
			Swal.fire({
				title: "Logout?",
				text: "Kamu yakin ingin keluar?",
				icon: "warning",
				showCancelButton: true,
				confirmButtonText: "Ya",
				confirmButtonColor: "#09AD74",
				cancelButtonText: "Tidak"
			}).then((result) => {
				if (result.isConfirmed) {
					Swal.fire({
						title: "See you!",
						icon: "success",
						showConfirmButton: false,
  						timer: 1500,
  						timerProgressBar: true
					}).then((result) => {
						if (result.dismiss === Swal.DismissReason.timer) {
							window.DoclincCsrf.submitPost(<?= json_encode(base_url('login/logout')); ?>);
						}
					});
				}
			});
		});
	</script>
</body>

</html>
