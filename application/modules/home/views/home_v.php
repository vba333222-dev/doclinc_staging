<?php
foreach ($data_profile->result() as $x) {
	$usia = $x->usia;
}

$dokter_id = []; // siapkan array kosong
foreach ($dataDoctor->result() as $doc) {
	$dokter_id[] = $doc->professional_id; // tambahkan ke array
}
?>

<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Doklinc (BETA) - Home</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" integrity="sha512-tS3S5qG0BlhnQROyJXvNjeEM4UpMXHrQfTGmbQ1gKmelCxlSEBUaxhRBj/EFTzpbP4RVSrpEikbmdJobCvhE3g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css" integrity="sha512-sMXtMNL1zRzolHYKEujM2AqCLUR9F2C4/05cdbxjjLSRvMQIciEPCQZo++nk7go3BtSuK9kfa/s+a4f4i5pLkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
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

		.notification {
			position: relative;
			display: inline-block;
		}

		.notification-item {
			padding: 1px;
			border-bottom: 1px solid #f1f1f1;
			cursor: pointer;
		}

		.notification-item:last-child {
			border-bottom: none;
		}

		.notification-item:hover {
			background: #f9f9f9;
		}

		.hero-card.disabled {
			pointer-events: none !important;
			/* Tidak bisa diklik */
			opacity: 0.5;
			/* Buat terlihat redup */
			cursor: not-allowed;
			/* Ubah kursor menjadi tanda larangan */
		}

		.hero-card.disabled a,
		.hero-card.disabled button {
			pointer-events: none !important;
			opacity: 0.5;
		}

		.popup-alert {
			position: fixed;
			top: 20px;
			right: 20px;
			background-color: #d4edda;
			color: #155724;
			border-left: 6px solid #28a745;
			padding: 16px 20px;
			z-index: 9999;
			border-radius: 8px;
			font-family: Arial, sans-serif;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
			animation: fadeIn 0.5s ease;
		}

		.close-btn {
			margin-left: 15px;
			color: #155724;
			font-weight: bold;
			float: right;
			font-size: 20px;
			cursor: pointer;
		}

		@keyframes fadeIn {
			from {
				opacity: 0;
				top: 0;
			}

			to {
				opacity: 1;
				top: 20px;
			}
		}

		.glow-star {
			animation: glow 1.5s infinite alternate;
		}

		@keyframes glow {
			from {
				text-shadow: 0 0 5px gold, 0 0 10px gold, 0 0 15px orange;
			}

			to {
				text-shadow: 0 0 10px gold, 0 0 20px orange, 0 0 30px red;
			}
		}

		.rating-stars {
			display: flex;
			flex-direction: row-reverse;
			justify-content: center;
		}

		.rating-stars input[type="radio"] {
			display: none;
		}

		.rating-stars label {
			font-size: 2rem;
			color: #ccc;
			cursor: pointer;
		}

		.rating-stars input[type="radio"]:checked~label,
		.rating-stars label:hover,
		.rating-stars label:hover~label {
			color: #f5c518;
		}

		@media print {

			button,
			.card-header span {
				display: none !important;
			}

			.card {
				border: none !important;
				box-shadow: none !important;
			}
		}
	</style>
</head>

<body class="bg-light">
	<div id="preloader">
		<div class="text-center">
			<img class="animate__animated animate__bounceIn mb-3" src="<?= base_url(); ?>assets/images/doklincwhite.png" alt="" height="50px">
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
				<a href="https://idbcs.net/cilegon_bersatu" style="text-decoration: none; color: white; font-size: 1.5rem;">
					<i class="fas fa-chevron-left icon"></i>
				</a>
				<a class="notify" href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNotif" aria-controls="offcanvasNotif">
					<i class="bi bi-bell-fill fs-4"></i>
					<!-- kalo ada notif fetch datanya dari sini ya, bukan dari dalem elemen span nya -->
					<span class="notify-number" id="badgeNotif">9+</span>
					<!-- sampe sini -->
				</a>
				<div class="text-white mb-2">
					<i class="fas fa-map-marker-alt me-2"></i><small><label for="" id="address"></label></small>

					<input type="hidden" id="id_user" value="<?= $this->session->userdata('id'); ?>">
					<input type="hidden" id="id_kabupaten" value="<?= $this->session->userdata('remark'); ?>">
					<input type="hidden" id="address" placeholder="Latitude">
					<input type="hidden" id="latitude" placeholder="Latitude">
					<input type="hidden" id="longitude" placeholder="Longitude">
				</div>
				<div class="d-flex animate__animated animate__fadeInUp animate__faster">
					<?php
					foreach ($data_profile->result() as $x) {
						$foto = $x->foto;
					}
					?>
					<div class="flex-shrink-0">
						<?php if (!empty($foto)) : ?>
							<img class="rounded-4 shadow" id="previewFoto" src="<?= base_url(); ?>uploads/profile/<?= $foto ?>" alt="Foto Profil" class="rounded-circle border border-success shadow-sm" style="width: 100px; height: 100px; object-fit: cover;">
						<?php else : ?>
							<img class="rounded-4 shadow"
								src="https://static.vecteezy.com/system/resources/previews/020/765/399/non_2x/default-profile-account-unknown-icon-black-silhouette-free-vector.jpg"
								width="100px"
								height="100px">
						<?php endif; ?>
					</div>
					<div class="flex-grow-1 ms-3 text-white">
						<small>Hello,</small>
						<h3 class="mb-0"><?= $this->session->userdata('nama'); ?></h3>
						<p class="mb-0"><?= $usia ?></p>
						<p>Kota: <span id="kota">Memuat...</span></p>
					</div>
				</div>
			</div>
			<svg id="wave" style="transform:rotate(180deg); transition: 0.3s" viewBox="0 0 1440 120" version="1.1" xmlns="http://www.w3.org/2000/svg">
				<defs>
					<linearGradient id="sw-gradient-0" x1="0" x2="0" y1="1" y2="0">
						<stop stop-color="rgba(9, 173, 116, 1)" offset="0%"></stop>
						<stop stop-color="rgba(140.457, 255, 215.189, 1)" offset="100%"></stop>
					</linearGradient>
				</defs>
				<path style="transform:translate(0, 0px); opacity:1" fill="url(#sw-gradient-0)" d="M0,48L48,48C96,48,192,48,288,56C384,64,480,80,576,88C672,96,768,96,864,86C960,76,1056,56,1152,48C1248,40,1344,44,1440,42C1536,40,1632,32,1728,42C1824,52,1920,80,2016,78C2112,76,2208,44,2304,40C2400,36,2496,60,2592,76C2688,92,2784,100,2880,98C2976,96,3072,84,3168,74C3264,64,3360,56,3456,54C3552,52,3648,56,3744,54C3840,52,3936,44,4032,48C4128,52,4224,68,4320,64C4416,60,4512,36,4608,30C4704,24,4800,36,4896,44C4992,52,5088,56,5184,52C5280,48,5376,36,5472,44C5568,52,5664,80,5760,94C5856,108,5952,108,6048,98C6144,88,6240,68,6336,50C6432,32,6528,16,6624,18C6720,20,6816,40,6864,50L6912,60L6912,120L6864,120C6816,120,6720,120,6624,120C6528,120,6432,120,6336,120C6240,120,6144,120,6048,120C5952,120,5856,120,5760,120C5664,120,5568,120,5472,120C5376,120,5280,120,5184,120C5088,120,4992,120,4896,120C4800,120,4704,120,4608,120C4512,120,4416,120,4320,120C4224,120,4128,120,4032,120C3936,120,3840,120,3744,120C3648,120,3552,120,3456,120C3360,120,3264,120,3168,120C3072,120,2976,120,2880,120C2784,120,2688,120,2592,120C2496,120,2400,120,2304,120C2208,120,2112,120,2016,120C1920,120,1824,120,1728,120C1632,120,1536,120,1440,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"></path>
				<defs>
					<linearGradient id="sw-gradient-1" x1="0" x2="0" y1="1" y2="0">
						<stop stop-color="rgba(9, 173, 116, 1)" offset="0%"></stop>
						<stop stop-color="rgba(9, 173, 116, 1)" offset="100%"></stop>
					</linearGradient>
				</defs>
				<path style="transform:translate(0, 50px); opacity:0.9" fill="url(#sw-gradient-1)" d="M0,60L48,54C96,48,192,36,288,28C384,20,480,16,576,24C672,32,768,52,864,62C960,72,1056,72,1152,64C1248,56,1344,40,1440,30C1536,20,1632,16,1728,30C1824,44,1920,76,2016,86C2112,96,2208,84,2304,68C2400,52,2496,32,2592,34C2688,36,2784,60,2880,68C2976,76,3072,68,3168,58C3264,48,3360,36,3456,26C3552,16,3648,8,3744,18C3840,28,3936,56,4032,68C4128,80,4224,76,4320,74C4416,72,4512,72,4608,72C4704,72,4800,72,4896,66C4992,60,5088,48,5184,44C5280,40,5376,44,5472,46C5568,48,5664,48,5760,46C5856,44,5952,40,6048,46C6144,52,6240,68,6336,72C6432,76,6528,68,6624,60C6720,52,6816,44,6864,40L6912,36L6912,120L6864,120C6816,120,6720,120,6624,120C6528,120,6432,120,6336,120C6240,120,6144,120,6048,120C5952,120,5856,120,5760,120C5664,120,5568,120,5472,120C5376,120,5280,120,5184,120C5088,120,4992,120,4896,120C4800,120,4704,120,4608,120C4512,120,4416,120,4320,120C4224,120,4128,120,4032,120C3936,120,3840,120,3744,120C3648,120,3552,120,3456,120C3360,120,3264,120,3168,120C3072,120,2976,120,2880,120C2784,120,2688,120,2592,120C2496,120,2400,120,2304,120C2208,120,2112,120,2016,120C1920,120,1824,120,1728,120C1632,120,1536,120,1440,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"></path>
			</svg>
			<div class="position-relative">
				<div id="beranda" class="content active animate__animated animate__fadeInUp animate__faster">
					<div class="row g-3 mb-3">
						<div class="col-3 text-center">
							<a href="#" class="feature-menu" onclick="showContent('konsultasi_kesehatan')">
								<div class="icon-wrapper mx-auto">
									<i class="fas fa-user-md"></i>
									<span class="filler"></span>
								</div>
								<span class="small">Konsultasi Kesehatan</span>
							</a>
						</div>
						<div class="col-3 text-center">
							<a href="#" data-bs-toggle="modal" class="feature-menu" onclick="showContent('riwayat')">
								<div class="icon-wrapper mx-auto">
									<i class="fas fa-briefcase-medical"></i>
									<span class="filler"></span>
								</div>
								<span class="small">Catatan Kesehatan</span>
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
							<div class="mb-2 fw-bold position-relative d-flex align-items-center">
								<p class="mb-0 me-2">News & Feed</p>
								<div class="flex-grow-1">
									<hr class="m-0">
								</div>
							</div>
							<div class="owl-carousel owl-theme">
								<?php
								foreach ($data_feeds->result() as $x) {
								?>
									<div class="item">
										<!--<img src="<?= base_url('assets/images/feeds/' . $x->gambar); ?>">-->
										<img src="<?= base_url('admin_menu/uploads/feeds/' . $x->gambar); ?>">
									</div>
								<?php
								}
								?>
							</div>
						</div>
					</div>
				</div>
				<div id="konsultasi_kesehatan" class="content animate__animated animate__fadeInUp animate__faster">
					<h2 class="text-center mb-4">Konsultasi Kesehatan</h2>
					<?php
					foreach ($getAllDataDoctor->result() as $row) {
						$userIdPasien = $row->user_id;
						$userId = $row->userId;
						$nama_dokter = $row->nama;
						$tanggal = $row->date;
						$status = $row->request_status;
						$tanggal_loc = $row->create_date;
						$foto = $row->foto;
					?>
						<div class="card shadow mb-2 rounded-4 bg-white clickable-card"
							data-requestId="<?= $row->request_id ?>"
							data-userIdPasien="<?= $userIdPasien ?>"
							data-status="<?= $status ?>"
							data-tanggal="<?= $tanggal ?>"
							data-tanggal_loc="<?= $tanggal_loc ?>"
							data-link="<?= base_url('konsultasi'); ?>?nama=<?= $userId; ?>">
							<div class="card-body p-2">
								<div class="d-flex hero-card">
									<?php if (!empty($row->foto)) : ?>
										<img class="rounded-4" id="gambar" src="<?= base_url('uploads/profile/' . $row->foto); ?>" width="100px" height="auto" alt="Image Not Found">
									<?php else : ?>
										<img class="rounded-4" id="gambar" src="https://static.vecteezy.com/system/resources/previews/020/765/399/non_2x/default-profile-account-unknown-icon-black-silhouette-free-vector.jpg" width="100px" height="auto" alt="Default Image">
									<?php endif; ?>
									<div class="w-100 ms-2">
										<div class="d-flex">
											<p class="fw-bold mb-0 me-auto"><?= $nama_dokter; ?></p>
											<div class="end-content">
												<span class="badge rounded-pill status bg-success">Available</span>
											</div>
										</div>

										<!-- Rating Dokter -->
										<div class="mb-1">
											<?php
											// Cari rating dokter ini berdasarkan ID dokter
											$totalRating = 0;
											$totalData = 0;

											foreach ($getAllRating->result() as $rat) {
												if ($rat->id_dokter == $row->dokter_id) {
													$totalRating += $rat->rating;
													$totalData++;
												}
											}

											$rating = ($totalData > 0) ? $totalRating / $totalData : 0;
											// Hitung bintang
											$fullStars = floor($rating);
											$halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
											$emptyStars = 5 - $fullStars - $halfStar;

											// Tambahkan kelas animasi kalau 5 bintang
											$glowClass = ($rating == 5) ? 'glow-star' : '';

											// Tampilkan bintang
											for ($i = 0; $i < $fullStars; $i++) {
												echo '<i class="fas fa-star text-warning"></i> ';
											}
											if ($halfStar) {
												echo '<i class="fas fa-star-half-alt text-warning"></i> ';
											}
											for ($i = 0; $i < $emptyStars; $i++) {
												echo '<i class="far fa-star text-warning"></i> ';
											}
											?>

										</div>

										<p class="mb-0" small>Estimasi :</p>
										<!-- <input type="hidden" name="latitude" id="latitude" placeholder="Latitude" />
										<input type="hidden" name="longitude" id="longitude" placeholder="Longitude" /> -->
										<i class="far fa-clock"></i> <em class="hasil"></em>
									</div>
								</div>
								<div></div>
								<?php
								foreach ($getAllRequestJumlah->result() as $baris) {
									// $baris->jumlah;
									$baris->user_id;
								?>
									<input type="hidden" name="jumlah" id="jumlah" value="<?= $baris->jumlah; ?>" />
								<?php }
								?>
							</div>
						</div>

					<?php } ?>

					<div class=" card shadow mb-2 rounded-4 bg-white" hidden>
						<div class="card-body p-2">
							<div class="d-flex">
								<img class="rounded-4" src="<?= base_url('assets/images/ambulance.jpeg'); ?>" width="100px" height="auto">
								<div class="w-100 ms-2">
									<div class="d-flex">
										<p class="fw-bold mb-0 me-auto">AMBULANCE</p>
										<div class="end-content">
											<span class="badge rounded-pill bg-success">Available</span>
										</div>
									</div>
									<p class="mb-0" small>Ambulance Shuttle</p>
									<i class="far fa-clock"></i> <em>25 minutes from you</em>
								</div>
							</div>
							<a class="stretched-link" href="<?= base_url('konsultasi'); ?>"></a>
						</div>
					</div>
				</div>
				<div id="riwayat" class="content animate__animated animate__fadeInUp animate__faster">
					<h2 class="text-center mb-4">Konsultasi Saya</h2>
					<ul class="nav nav-tabs nav-justified mb-3" id="myTab" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" id="proses-tab" data-bs-toggle="tab" data-bs-target="#proses-tab-pane" type="button" role="tab" aria-controls="proses-tab-pane" aria-selected="false">Saat ini</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="selesai-tab" data-bs-toggle="tab" data-bs-target="#selesai-tab-pane" type="button" role="tab" aria-controls="selesai-tab-pane" aria-selected="false">Riwayat</button>
						</li>
					</ul>
					<!-- Tab Konsultasi -->
					<div class="tab-content" id="myTabContent">
						<div class="tab-pane fade show active" id="proses-tab-pane" role="tabpanel" aria-labelledby="proses-tab" tabindex="0">
							<?php
							foreach ($getAllDataRequests as $data) {
								$id_request = $data->request_id;
								$tanggal = $data->date;
								$keluhan = $data->request_description;
								$nama_dokter = $data->nama_dokter;
								$status = $data->request_status;
								$lat = $data->lattitude;
								$lng = $data->longitude;

								// jadikan tanggal di atas formatnya jadi 11 November 2024
								$tanggal = date('d F Y', strtotime($tanggal));

								if ($status == 'Pending') {
									$status = 'Menunggu Konfirmasi';
								} elseif ($status == 'Accepted') {
									$status = 'Nakes Menuju Lokasi';
								}
							?>

								<!-- Popup HTML -->
								<div id="acceptedPopup" class="popup-alert" style="display: none;">
									<span class="close-btn" onclick="closePopup()">&times;</span>
									✅ Nakes telah menerima permintaan konsultasi Anda.
								</div>

								<div class="card shadow mb-2">
									<div class="card-header d-flex align-items-center">
										<p class="mb-0"><em><?= $tanggal; ?></em></p>
										<span id="statusNotif" class="badge text-bg-warning ms-auto animate__animated animate__flash animate__infinite animate__slower"><?= $status; ?></span>
									</div>
									<div class="card-body">
										<p class="mb-0 small fw-bold"><i class="fas fa-notes-medical fa-fw"></i> Keluhan :</p>
										<textarea rows="4" class="form-control" readonly><?= $keluhan; ?></textarea>
										<p class="mb-0 small fw-bold"><i class="fas fa-stethoscope fa-fw"></i> Dokter :</p>
										<p class="mb-0"><?= $nama_dokter; ?></p>
										<p class="mb-0 small fw-bold"><i class="far fa-clock fa-fw"></i> Estimasi :</p>
										<input type="text" name="latitudes" id="latitudes" value="<?= $lat; ?>" hidden />
										<input type="text" name="longitudes" id="longitudes" value="<?= $lng; ?>" hidden />
										<span id="estimasi"></span>
									</div>
								</div>
							<?php
							}
							?>
						</div>
						<!-- Tab Riwayat -->
						<div class="tab-pane fade" id="selesai-tab-pane" role="tabpanel" aria-labelledby="selesai-tab" tabindex="0">
							<?php
							foreach ($getAllDataRequestsCompleted as $data) {
								$id_request = $data->request_id;
								$tanggal = $data->date;
								$keluhan = $data->request_description;
								$saran	 = $data->recommendations;
								$dokter_id = $data->dokter_id;
								$nama_dokter_riwayat = $data->nama_dokter;
								$diagnosa = $data->diagnosa;
								$saran_dokter = $data->saran;

								$tanggal_riwayat = date('d F Y', strtotime($tanggal));
							?>
								<div class="card shadow mb-2" id="card-<?= $data->konsul_id ?>">
									<div class="card-header d-flex align-items-center">
										<p class="mb-0"><em><?= $tanggal_riwayat; ?></em></p>
										<span class="badge text-bg-secondary ms-auto">Selesai</span>
									</div>
									<input type="hidden" id="reqIdRat" value="<?= $id_request ?>">
									<div class="card-body">
										<p class="mb-0 small fw-bold"><i class="fas fa-notes-medical fa-fw"></i> Keluhan :</p>
										<p class="mb-0"><?= $keluhan; ?></p>
										<p class="mb-0 small fw-bold"><i class="fas fa-stethoscope fa-fw"></i> Dokter :</p>
										<p class="mb-0"><?= $nama_dokter_riwayat; ?></p>
										<input type="hidden" id="doktId" value="<?= $dokter_id; ?>">
										<p class="mb-1 small">
											<span class="fw-bold"><i class="fas fa-user-md fs-4"></i> Diagnosa :</span><br><?php echo $diagnosa; ?>
										</p>
										<p class="mb-1 small">
											<span class="fw-bold"><i class='fas fa-comment-dots fs-4'></i> Saran :</span><br><?php echo $saran_dokter; ?>
										</p>
										<p class="mb-1 small fw-bold"><i class='fas fa-pills'></i> Obat :</p>
										<table class="table table-sm">
											<thead>
												<tr>
													<th>No</th>
													<th>Nama Terapi</th>
													<th>Keterangan</th>
												</tr>
											</thead>
											<tbody>
												<?php
												$no = 1;
												foreach ($data->terapi_list as $terapi) {
												?>
													<tr>
														<td><?= $no++; ?></td>
														<td><?= $terapi->terapi; ?></td>
														<td><?= $terapi->signa; ?></td>
													</tr>
												<?php } ?>
											</tbody>
										</table>
										<button class="btn btn-sm btn-outline-success mt-2" onclick="downloadCard('card-<?= $data->konsul_id ?>')">
											<i class="fas fa-file-download"></i> Download Resep
										</button>
									</div>
								</div>
							<?php } ?>
						</div>
					</div>
				</div>

				<div id="profile" class="content animate__animated animate__fadeInUp animate__faster">
					<?php
					$nama = '';
					$tgl = '';
					$jk = '';
					$no_hp = '';
					$alamat = '';
					foreach ($data_profile->result() as $x) {
						$nama = $x->nama;
						$tgl = $x->tgl;
						$jk = $x->gender;
						$no_hp = $x->no_hp;
						$alamat = $x->alamat;
						$role = $x->role;
					}
					?>
					<div class="card shadow-sm border-0 rounded-4">
						<div class="card-body">
							<h4 class="text-center text-success mb-4">Profil Pengguna</h4>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="nama_lengkap" value="<?= $nama; ?>" placeholder="Nama Lengkap" readonly>
								<label for="nama_lengkap"><i class="fas fa-user"></i> Nama Lengkap</label>
							</div>
							<div class="form-floating mb-3">
								<input type="date" class="form-control shadow-sm border-success" id="tgl" placeholder="Tanggal Lahir" value="<?= $tgl; ?>" readonly>
								<label for="tgl"><i class="fas fa-calendar-alt"></i> Tanggal Lahir</label>
							</div>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="jk" placeholder="Jenis Kelamin" value="<?= $jk; ?>" readonly>
								<label for="tgl"><i class="fas fa-calendar-alt"></i> Jenis Kelamin</label>
							</div>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="no_hp" value="<?= $no_hp; ?>" placeholder="Nomor HP" readonly>
								<label for="no_hp"><i class="fas fa-phone-alt"></i> Nomor HP</label>
							</div>
							<div class="form-floating mb-3">
								<textarea class="form-control shadow-sm border-success" placeholder="Alamat" id="alamat" readonly style="height: 100px"><?= $alamat; ?></textarea>
								<label for="alamat"><i class="fas fa-map-marker-alt"></i> Alamat</label>
							</div>
							<div class="d-grid mb-3">
								<button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalProfil" hidden>
									<i class="fas fa-edit"></i> Edit Profil
								</button>
							</div>
							<div class="d-grid">
								<button type="button" class="btn btn-outline-danger" id="btn-logout">
									<i class="fas fa-sign-out-alt"></i> Logout
								</button>
							</div>
						</div>
					</div>
				</div>

				<div id="notifikasi" class="content animate__animated animate__fadeInUp animate__faster">

				</div>
				<div id="pahlawan_1" class="content animate__animated animate__fadeInUp animate__faster">

					<form action="" method="post">
						<div class="form-floating mb-2">
							<input type="hidden" class="form-control shadow border-success" id="nama" value="<?php echo $_SESSION['username']; ?>" placeholder="Nama Lengkap" readonly>
						</div>
						<div class="form-floating mb-2">
							<textarea name="keluhan" id="keluhan" class="form-control shadow border-success" placeholder="keluhan"></textarea>
							<label for="keluhan">Keluhan</label>
						</div>
						<div class="form-floating mb-2 d-none">
							<input type="hidden" class="form-control shadow border-success" id="no_hp" value="087775587778" placeholder="Nomor HP" readonly>
							<label for="no_hp">Nomor HP</label>
						</div>
						<div class="form-floating mb-2">

							<textarea id="address" class="form-control shadow border-success" placeholder="Alamat: ..." readonly style="height: 100px"></textarea>
							<label for="alamat">Alamat</label>
							<input type="text" class="d-none" id="latitudex" placeholder="Latitude" readonly>
							<input type="text" class="d-none" id="longitudex" placeholder="Longitude" readonly>
							<div id="map"></div>
						</div>
						<div class="form-floating mb-2">
							<input type="text" class="form-control shadow border-success" id="tanggal" value="<?php echo date('d-m-Y'); ?>" placeholder="tanggal" readonly>
							<label for="Tanggal">Tanggal </label>
						</div>
						<div class="d-grid">
							<button type="submit" class="btn btn-outline-success" id="save_konsul">Kirim</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- menubar bottom -->
	<div class="nav-bottom-wrapper shadow-lg rounded-top-4" id="nav-bottom-wrapper">
		<div class="container-fluid px-0">
			<div class="row g-0 text-center p-2 menu animate__animated animate__slideInUp animate__faster">
				<a href="#" id="beranda-tab" class="col menu-item active" onclick="showContent('beranda')">
					<i class="fas fa-home fs-4"></i>
					<span class="d-block small mt-1">Home</span>
				</a>
				<a href="#" id="konsultasi_kesehatan-tab" class="col menu-item" onclick="showContent('konsultasi_kesehatan')">
					<i class="fas fa-user-md fs-4"></i>
					<span class="d-block small mt-1">Konsultasi</span>
				</a>
				<a href="#" id="riwayat-tab" class="col menu-item" onclick="showContent('riwayat')">
					<i class="fas fa-file-medical fs-4"></i>
					<span class="d-block small mt-1">Riwayat</span>
				</a>
				<a href="#" id="profile-tab" class="col menu-item" onclick="showContent('profile')">
					<i class="fas fa-user fs-4"></i>
					<span class="d-block small mt-1">Profile</span>
				</a>
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
							<textarea class="form-control shadow border-success" placeholder="Alamat" id="alamat_edit" style="height: 100px">BCS Logistics Center Jl. Raya Merak KM. 115, Cilegon Banten, Indonesia - 42436
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

	<div class="offcanvas offcanvas-top" tabindex="-1" id="offcanvasNotif" aria-labelledby="offcanvasNotifLabel">
		<div class="offcanvas-header">
			<i class="bi bi-bell-fill text-success"></i>
			<p class="offcanvas-title mx-2" id="offcanvasNotifLabel">Pusat Notifikasi</p>
			<span class="badge text-bg-danger" id="badgeNotif">9+</span>
			<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
		</div>
		<div class="offcanvas-body">
			<div id="notificationList" class="notification-list"></div>
		</div>
	</div>

	<div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="false">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content border-0 shadow-lg">
				<div class="modal-header bg-success text-white">
					<h5 class="modal-title" id="ratingModalLabel">Beri Rating Konsultasi</h5>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body text-center">
					<div class="mb-3">
						<img id="gambarDokter" src="" alt="Foto Dokter" class="rounded-circle border border-success shadow-sm" width="100" height="100">
					</div>
					<p class="fw-bold">Bagaimana pengalaman konsultasimu dengan <span id="namaDokter" class="text-success"></span>?</p>
					<!-- Form Rating -->
					<form id="ratingForm">
						<input type="hidden" name="iduser" value="<?= $this->session->userdata('id') ?>">
						<input type="hidden" name="id_dokter" id="dokIds">
						<div class="mb-3">
							<!-- Rating input -->
							<div class="rating-stars">
								<input type="radio" id="star5" name="rating" value="5" required />
								<label for="star5" title="Luar Biasa"><i class="fas fa-star"></i></label>
								<input type="radio" id="star4" name="rating" value="4" />
								<label for="star4" title="Sangat Baik"><i class="fas fa-star"></i></label>
								<input type="radio" id="star3" name="rating" value="3" />
								<label for="star3" title="Baik"><i class="fas fa-star"></i></label>
								<input type="radio" id="star2" name="rating" value="2" />
								<label for="star2" title="Cukup"><i class="fas fa-star"></i></label>
								<input type="radio" id="star1" name="rating" value="1" />
								<label for="star1" title="Buruk"><i class="fas fa-star"></i></label>
							</div>
						</div>
						<button type="submit" class="btn btn-success w-100">Kirim Rating</button>
					</form>
				</div>
			</div>
		</div>
	</div>


	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" integrity="sha512-bPs7Ae6pVvhOSiIcyUClR7/q2OAsRiovw4vAkX+zJbw3ShAeeqezq50RIIcIURq7Oa20rW2n2q+fyXBNcU9lrw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBTfv2in7EP1cLT71-bVC-66SZsrg4Kr5w"></script>

	<!-- firebase dan notifikasi -->

	<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
	<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script>
	<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
	<script src="https://idbcs.net/cilegon_bersatu/firebase/firebase-config.js"></script>
	<script src="https://idbcs.net/cilegon_bersatu/firebase/get-notif.js"></script>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

	<!-- popup jam operasional -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const lastPopupTime = localStorage.getItem('lastPopupTime');
			const currentTime = new Date().getTime();

			if (!lastPopupTime || (currentTime - lastPopupTime) > 3600000) { // 1 hour = 3600000 ms
				Swal.fire({
					title: 'Informasi',
					text: 'Jam operasional kunjungan nakes dari 08.00 s/d 17.00',
					icon: 'info',
					confirmButtonText: 'OK'
				}).then(() => {
					localStorage.setItem('lastPopupTime', currentTime.toString());
				});
			}
		});
	</script>

	<script>
		console.log('<?= base_url("assets/js/popup-jamops.js") ?>');
	</script>

	<!-- mencari kota cilegon -->
	<!-- <script>
		// Ambil posisi pengguna secara realtime
		let lokasi = {
			lat: -6.0176,
			lng: 106.0530
		}; // Default: Cilegon

		if (navigator.geolocation) {
			navigator.geolocation.getCurrentPosition(function(position) {
				lokasi = {
					lat: position.coords.latitude,
					lng: position.coords.longitude
				};
				// Jalankan geocoder setelah dapat lokasi
				const geocoders = new google.maps.Geocoder();
				geocoders.geocode({
					location: lokasi
				}, function(results, status) {
					if (status === 'OK') {
						console.log(results, status);

						if (results[0]) {
							const components = results[0].address_components;
							const city = components.find(c => c.types.includes("locality")) ||
								components.find(c => c.types.includes("administrative_area_level_2"));
							console.log(city);
							document.getElementById('kota').textContent = city ? city.long_name : "Tidak ditemukan";
						} else {
							document.getElementById('kota').textContent = "Tidak ada hasil geocoding";
						}
					} else {
						document.getElementById('kota').textContent = "Geocoder gagal: " + status;
					}
				});
			}, function(error) {
				alert(error);

				document.getElementById('kota').textContent = "Lokasi tidak tersedia";
			});
		} else {
			document.getElementById('kota').textContent = "Geolocation tidak didukung";
		}
		// return; // Agar kode di bawah tidak dijalankan dua kali

		// const geocoders = new google.maps.Geocoder();
		// geocoders.geocode({
		// 	location: lokasi
		// }, function(results, status) {
		// 	if (status === 'OK') {
		// 		if (results[0]) {
		// 			const components = results[0].address_components;
		// 			const city = components.find(c => c.types.includes("locality")) ||
		// 				components.find(c => c.types.includes("administrative_area_level_2"));
		// 			console.log(city);
		// 			document.getElementById('kota').textContent = city.long_name;
		// 		} else {
		// 			document.getElementById('kota').textContent = "Tidak ada hasil geocoding";
		// 		}
		// 	} else {
		// 		document.getElementById('kota').textContent = "Geocoder gagal: " + status;
		// 	}
		// });
	</script> -->

	<script>
		let doktId = document.getElementById('doktId').value;
		let dokId = document.getElementById('dokId');
		dokId.value = doktId;
		console.log('Dokter Id: ' + dokId);
	</script>

	<script>
		console.log('ini adalah base url: ' + '<?= base_url("assets/images/dokter_jaenul.jpeg") ?>');

		const gambar = document.getElementById('gambar');
		console.log(gambar['src']);
	</script>

	<!-- Webtoapk dan lain-lain -->
	<script>
		function exitApp() {
			Website2APK.exitApp();
		}
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

		document.addEventListener('DOMContentLoaded', function() {
			var hash = window.location.hash;
			if (hash) {
				showContent(hash.replace('#', ''));
			}
		});

		function showContent(tab) {
			let currentActiveContent = document.querySelector('.content.active');
			if (currentActiveContent) {
				currentActiveContent.classList.remove('active');
			}

			let targetContent = document.getElementById(tab);
			if (targetContent) {
				targetContent.classList.add('active');
			}

			let currentActiveMenu = document.querySelector('.nav-bottom-wrapper .menu a.active');
			if (currentActiveMenu) {
				currentActiveMenu.classList.remove('active');
			}

			let targetMenu = document.getElementById(tab + '-tab');
			if (targetMenu) {
				targetMenu.classList.add('active');
			}
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
							window.location.href = 'login/logout';
						}
					});
				}
			});
		});

		$('#save_konsul').click(function() {
			var nama = $('#nama').val();
			var keluhan = $('#keluhan').val();
			var no_hp = $('#no_hp').val();
			var lat = $('#latitude').val();
			var lng = $('#longitude').val();
			var alamat = $('#address').val();
			var tanggal = $('#tanggal').val();

			alert(nama);
			alert(keluhan);
			alert(lat);
			alert(lng);
			alert(alamat);
			alert(tanggal);
		});
		$('.owl-carousel').owlCarousel({
			loop: true,
			autoplay: true,
			autoplayTimeout: 2500,
			autoplayHoverPause: true,
			margin: 10,
			responsiveClass: true,
			responsive: {
				0: {
					items: 1
				},
				640: {
					items: 2
				},
				1024: {
					items: 3
				}
			}
		});
	</script>

	<!-- Menagtur Auto Scroll -->
	<script>
		// 		// Mengatur auto-scroll pada kontainer
		// 		const scrollContainer = document.getElementById('scrollContainer');
		// 		let scrollAmount = 0;

		// 		function autoScroll() {
		// 			scrollAmount += 380; // Jarak scroll dalam piksel
		// 			if (scrollAmount >= scrollContainer.scrollWidth - scrollContainer.clientWidth) {
		// 				scrollAmount = 0; // Kembali ke awal jika sudah mencapai akhir
		// 			}
		// 			scrollContainer.scrollTo({
		// 				left: scrollAmount,
		// 				behavior: 'smooth'
		// 			});
		// 		}

		// 		// Mengulangi scroll setiap 3 detik
		// 		setInterval(autoScroll, 3000);
		document.addEventListener("DOMContentLoaded", function() {
			const scrollContainer = document.getElementById('scrollContainer');

			if (!scrollContainer) {
				console.error("Elemen #scrollContainer tidak ditemukan.");
				return;
			}

			let scrollAmount = 0;

			function autoScroll() {
				if (!scrollContainer) return;

				scrollAmount += 380; // Jarak scroll dalam piksel

				if (scrollAmount >= scrollContainer.scrollWidth - scrollContainer.clientWidth) {
					scrollAmount = 0; // Kembali ke awal jika sudah mencapai akhir
				}

				scrollContainer.scrollTo({
					left: scrollAmount,
					behavior: 'smooth'
				});
			}

			// Mengulangi scroll setiap 3 detik
			setInterval(autoScroll, 3000);
		});
	</script>

	<!-- Mengirim lokasi -->
	<script>
		let map;
		let marker;
		let geocoder;

		function initMap() {
			// Inisialisasi peta
			const initialLocation = {
				lat: -6.1751,
				lng: 106.8650
			}; // Lokasi awal (Jakarta)
			map = new google.maps.Map(document.getElementById("map"), {
				zoom: 15,
				center: initialLocation,
			});

			marker = new google.maps.Marker({
				position: initialLocation,
				map: map,
			});

			geocoder = new google.maps.Geocoder();

			// Mendapatkan lokasi pengguna
			if (navigator.geolocation) {
				navigator.geolocation.watchPosition(updateLocation, showError);
			} else {
				Swal.fire({
					title: "Error",
					text: "Geolocation is not supported by this browser.",
					icon: "error",
					confirmButtonText: "OK"
				});
			}
		}

		function updateLocation(position) {
			const newLocation = {
				lat: position.coords.latitude,
				lng: position.coords.longitude,
			};

			// Update posisi marker dan pusat peta
			marker.setPosition(newLocation);
			map.setCenter(newLocation);

			// Tampilkan latitude dan longitude
			document.getElementById("latitude").value = newLocation.lat;
			document.getElementById("longitude").value = newLocation.lng;

			// Mendapatkan alamat dengan Geocoder
			getAddress(newLocation);

		}

		function sendData() {
			// Ambil nilai dari input
			const latitude = document.getElementById('latitude').value;
			const longitude = document.getElementById('longitude').value;

			// Kirim data ke PHP menggunakan fetch
			fetch('<?= base_url('home/getDuration') ?>', { // Kirim ke halaman yang sama
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded'
					},
					body: 'latitude=' + encodeURIComponent(latitude) + '&longitude=' + encodeURIComponent(longitude)
				})
				.then(response => response.json())
				.then(result => {
					console.log('hasilnya: ' + result);


					// Tampilkan respon dari PHP
					const results = document.querySelectorAll('.hasil');
					const heroCards = document.querySelectorAll('.hero-card'); // Pastikan setiap pahlawan ada di elemen dengan class ini
					const badges = document.querySelectorAll('.badge.rounded-pill.status'); // Ambil elemen badge "Available"

					// const times = result.split(/\s+/);
					// results.forEach((index) => {
					// 	index.textContent = result + ' From You';
					// });
					results.forEach((element, index) => {
						if (result[index]) { // Pastikan ada data untuk elemen ini
							// element.textContent = result[index].time + ' From You';
							const timeText = result[index].time; // Contoh: "22 mins"
							element.textContent = timeText + " From You";

							// Ambil angka durasi dari string (misalnya "22 mins" -> 22)
							const duration = parseInt(timeText);

							if (duration > 60) {
								setNotAvailable(heroCards[index], badges[index]);
							}
						} else {
							element.textContent = '-';
							setNotAvailable(heroCards[index], badges[index]);
						}
					});

					// document.getElementById('results').innerHTML = result + ' From You';
					console.log("Data telah dikirim.");

				})
				.catch(error => console.error("Error:", error));

			// Fungsi untuk mengubah status menjadi "Not Available"
			function setNotAvailable(heroCard, badge) {
				if (!heroCard || !badge) return;

				const link = heroCard.closest('.card').querySelector('.stretched-link');

				heroCard.classList.add('disabled');
				heroCard.style.pointerEvents = 'none';
				heroCard.style.opacity = '0.5';
				heroCard.style.cursor = 'not-allowed';

				// Ganti teks "Available" menjadi "Not Available"
				badge.textContent = "Not Available";
				badge.classList.remove('bg-success');
				badge.classList.add('bg-danger');

				// Hapus link agar tidak bisa diklik
				if (link) {
					link.remove();
				}

				// Tambahkan event agar user tidak bisa klik
				heroCard.addEventListener('click', function(event) {
					event.preventDefault();
					event.stopPropagation();
					Swal.fire({
						title: 'Pahlawan Tidak Tersedia',
						text: 'Pahlawan ini tidak bisa dipilih karena tidak memiliki durasi atau jaraknya lebih dari 20 menit.',
						icon: 'warning',
						confirmButtonText: 'OK'
					});
				}, true);
			}
		}

		function getAddress(location) {
			geocoder.geocode({
				location: location
			}, (results, status) => {
				if (status === "OK") {
					if (results[0]) {
						document.getElementById("address").innerHTML = results[0].formatted_address;

						const components = results[0].address_components;
						const city = components.find(c => c.types.includes("locality")) ||
							components.find(c => c.types.includes("administrative_area_level_2"));
						console.log('Kotanya: ', city);
						document.getElementById('kota').textContent = city ? city.long_name : "Tidak ditemukan";
					} else {
						document.getElementById("address").value = "No results found";
					}
				} else {
					document.getElementById("address").value = "Geocoder failed due to: " + status;
				}
			});
		}

		function showError(error) {
			switch (error.code) {
				// case error.PERMISSION_DENIED:
				// 	alert("User denied the request for Geolocation.");
				// 	break;
				case error.POSITION_UNAVAILABLE:
					Swal.fire({
						title: "Error",
						text: "Location information is unavailable.",
						icon: "error",
						confirmButtonText: "OK"
					});
					break;
				case error.TIMEOUT:
					Swal.fire({
						title: "Error",
						text: "The request to get user location timed out.",
						icon: "error",
						confirmButtonText: "OK"
					});
					break;
				case error.UNKNOWN_ERROR:
					Swal.fire({
						title: "Error",
						text: "An unknown error occurred.",
						icon: "error",
						confirmButtonText: "OK"
					});
					break;
			}
		}


		// window.onload = initMap;
		window.onload = function() {
			initMap();
			// sendData();
			// setInterval(function() {
			// 	sendData();
			// }, 2000);
			setTimeout(function() {
				sendData();
			}, 1000);
		}

		// Tambahkan ini di akhir file JavaScript Anda
		if (typeof Flutter !== 'undefined') {
			Flutter.postMessage('ready');
		}
	</script>

	<!-- validasi sebelum konsultasi -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const cards = document.querySelectorAll('.clickable-card');

			cards.forEach(card => {
				card.addEventListener('click', function() {
					const userIdWarga = <?= $this->session->userdata('id'); ?>;
					const userIdPasien = card.getAttribute('data-userIdPasien');
					const status = card.getAttribute('data-status');
					const tanggal = card.getAttribute('data-tanggal');
					const tanggal_loc = card.getAttribute('data-tanggal_loc');
					const link = card.getAttribute('data-link');
					const link_riwayat = "<?= base_url('home#riwayat') ?>";

					const jumlah = document.getElementById('jumlah').value;
					// const uid = document.getElementById('uid').value;

					const today = new Date().toLocaleDateString('id-ID', {
						timeZone: 'Asia/Jakarta',
						year: 'numeric',
						month: '2-digit',
						day: '2-digit'
					}).split('/').reverse().join('-'); // format YYYY-MM-DD
					console.log(today);
					console.log(tanggal);

					const uids = <?= json_encode(array_column($getAllRequestPendingAccept->result_array(), 'user_id')) ?>;
					console.log("UIDs:", uids);
					const tgl = <?= json_encode(array_column($getAllRequestPendingAccept->result_array(), 'date')) ?>;
					console.log("tanggal:", tgl);
					const stats = <?= json_encode(array_column($getAllRequestPendingAccept->result_array(), 'request_status')) ?>;
					console.log("status:", stats);
					const reqId = <?= json_encode(array_column($getAllRequestPendingAccept->result_array(), 'request_id')) ?>;
					const reqIds = String(reqId);
					console.log("requestId:", reqId);

					const userIdToCheck = String(userIdWarga);

					const kotaCilegon = document.getElementById('kota').textContent;
					console.log(kotaCilegon);
					if (!['Cilegon', 'Kota Cilegon'].includes(kotaCilegon)) {
						// buatkan alert yang dengan swal
						Swal.fire({
							title: 'Peringatan',
							text: 'Anda tidak bisa mengajukan konsultasi, karena posisi Anda saat ini berada di luar Kota Cilegon.',
							icon: 'warning',
							confirmButtonText: 'Tutup'
						}).then((result) => {
							if (result.isConfirmed) {
								// console.log('ok');
							}
						})
						return;
					}

					// jika tanggal tidak ada isinya
					if (tanggal_loc === null || tanggal_loc === '') {
						// Tanggal sudah lewat
						Swal.fire({
							title: 'Peringatan',
							text: 'Tidak bisa melakukan Konsultasi. Silakan pilih Tenaga Kesehatan yang lain.',
							icon: 'warning',
							confirmButtonText: 'Tutup'
						});
						return;
					}

					console.log("userIdWarga:", userIdWarga);
					console.log("userIdPasien:", userIdPasien);
					console.log("status:", status);
					console.log("tanggal:", tanggal);
					console.log("today:", today);
					console.log("tanggal_loc:", tanggal_loc);
					console.log("jumlah:", jumlah);

					// console.log("uid:", uid);
					console.log("User Id Check:",
						userIdToCheck);

					if (uids.includes(userIdToCheck) === false) {
						if (jumlah > 100) {
							// Jika userIdWarga tidak sama dengan userIdPasien
							Swal.fire({
								title: 'Peringatan',
								text: 'Anda tidak bisa melakukan konsultasi, karena permintaan konsultasi sudah melebihi batas.',
								icon: 'warning',
								confirmButtonText: 'Tutup'
							});
							return;
						} else {
							// Jika userIdPasien sama dengan id_user
							window.location.href = link;
							// return;
							// alert("ada");
						}
					} else if (uids.includes(userIdToCheck) === true && stats.includes('Completed')) {
						if (tgl == today) {
							Swal.fire({
								title: 'Peringatan',
								text: 'Anda tidak bisa melakukan konsultasi di hari yang sama.',
								icon: 'warning',
								confirmButtonText: 'Tutup'
							});
						} else {
							window.location.href = link;
						}
					} else if (String(userIdWarga) === userIdToCheck) {
						if (stats == 'Pending' || stats == 'Accepted') {
							if (tgl == today) {
								// Tidak bisa konsultasi lagi hari ini
								Swal.fire({
									title: 'Peringatan',
									text: 'Anda sudah melakukan konsultasi hari ini. Silakan tunggu sampai besok atau tunggu konsultasi ini selesai.',
									icon: 'warning',
									confirmButtonText: 'Tutup'
								});
							} else {
								// Boleh lanjut atau perbarui
								Swal.fire({
									title: 'Peringatan',
									text: 'Anda memiliki konsultasi sebelumnya yang belum selesai pada tanggal ' + tgl + '. Ingin memperbarui tanggal konsultasi?',
									icon: 'warning',
									showCancelButton: true,
									confirmButtonText: 'Perbarui',
									cancelButtonText: 'Hapus'
								}).then((result) => {
									if (result.isConfirmed) {
										// Arahkan ke endpoint untuk perbarui (opsional ganti endpoint-nya)
										$.ajax({
											url: '<?= base_url('home/updaterequestbyid') ?>',
											type: 'POST',
											data: {
												requestId: reqIds
											},
											success: function(response) {
												console.log(response);
												Swal.fire({
													title: 'Berhasil',
													text: 'Tanggal konsultasi berhasil diperbarui.',
													icon: 'success',
													confirmButtonText: 'OK'
												}).then(() => {
													window.location.href = "<?= base_url('home#riwayat') ?>";
												});
											}
										});
									} else if (result.dismiss === Swal.DismissReason.cancel) {
										// Tetap arahkan ke konsultasi
										// hapus data request
										$.ajax({
											url: '<?= base_url('home/deleterequestbyid') ?>',
											type: 'POST',
											data: {
												requestId: reqIds
											},
											success: function(response) {
												console.log("Data berhasil dihapus:", response);
											}
										});

										console.log("Id Warga:", userIdWarga);
										console.log("Request ID:", reqIds);

										window.location.href = link;
									}
								});
							}
						} else if (stats.includes('Completed')) {
							// Status aman, lanjut ke konsultasi
							window.location.href = link;
						} else if (uids.includes(userIdToCheck)) {
							// Jika status masih Pending atau Accepted
							Swal.fire({
								title: 'Peringatan',
								text: 'Anda tidak bisa melakukan konsultasi, karena konsultasi sebelumnya belum selesai.',
								icon: 'warning',
								confirmButtonText: 'Tutup'
							});
						} else {
							console.log("User ID tidak ditemukan.");
						}
					}
				});
			});
		});
	</script>

	<!-- Notifikasi Chat -->
	<script>
		// Referensi data notifikasi
		const notificationsRef = firebase.database().ref('notifications');

		const idUser = document.getElementById('id_user').value;
		console.log(idUser);
		var request_id = "<?php echo $id_request; ?>";


		const currentUserId = idUser; // ID pengguna

		// Element badge
		const badge = document.getElementById('badgeNotif');

		// Mendengarkan data notifikasi baru
		notificationsRef.on('value', (snapshot) => {
			const data = snapshot.val();

			if (!data) {
				badge.style.display = 'none';
				notificationList.innerHTML = '<p>Tidak ada notifikasi</p>';
				return;
			}

			// Filter hanya notifikasi dengan user_id = 1
			const filteredNotifications = Object.entries(data)
				.filter(([key, item]) => item.userId == currentUserId) // Filter berdasarkan user_id
				.map(([key, item]) => ({
					key,
					...item
				})); // Ubah ke format array

			const count = filteredNotifications.length;

			console.log('New notification detected:', data);

			// Tampilkan jumlah notifikasi
			if (count > 0) {
				badge.style.display = 'inline';
				badge.textContent = count > 9 ? '9+' : count;
			} else {
				badge.style.display = 'none';
			}

			// Update daftar notifikasi
			notificationList.innerHTML = ''; // Kosongkan daftar
			filteredNotifications.forEach((item) => {
				const notificationItem = document.createElement('div');
				notificationItem.className = 'notification-item d-flex align-items-center p-2 border-bottom';

				// Icon untuk notifikasi
				const icon = document.createElement('i');
				icon.className = 'bi bi-bell-fill text-success me-3 fs-4';
				notificationItem.appendChild(icon);

				// Konten notifikasi
				const content = document.createElement('div');
				content.className = 'flex-grow-1';
				const date = new Date(item.timestamp || Date.now()).toLocaleString('id-ID', {
					year: 'numeric',
					month: 'long',
					day: 'numeric',
					hour: '2-digit',
					minute: '2-digit'
				});
				content.innerHTML = `<strong>${item.title || 'Notifikasi'}</strong><br><small>${item.text || 'Tidak ada detail'}</small><br><small class="text-muted">${date}</small>`;
				notificationItem.appendChild(content);

				// Tambahkan event listener untuk membuka halaman chat
				notificationItem.addEventListener('click', () => {
					if (item.receiver) {
						window.location.href = `chat/chat?reqId=${item.receiver}&userId=${item.sender}`;
					}
				});

				// Icon hapus notifikasi
				const deleteIcon = document.createElement('i');
				deleteIcon.className = 'bi bi-trash text-danger ms-3 fs-5';
				deleteIcon.style.cursor = 'pointer';
				deleteIcon.addEventListener('click', (e) => {
					e.stopPropagation(); // Mencegah event click pada notifikasi
					Swal.fire({
						title: 'Hapus Notifikasi?',
						text: 'Apakah Anda yakin ingin menghapus notifikasi ini?',
						icon: 'warning',
						showCancelButton: true,
						confirmButtonText: 'Hapus',
						cancelButtonText: 'Batal'
					}).then((result) => {
						if (result.isConfirmed) {
							// Hapus notifikasi berdasarkan key
							firebase.database().ref(`notifications/${item.key}`).remove()
								.then(() => {
									Swal.fire('Berhasil', 'Notifikasi berhasil dihapus.', 'success');
								})
								.catch((error) => {
									Swal.fire('Error', 'Gagal menghapus notifikasi.', 'error');
									console.error('Error:', error);
								});
						}
					});
				});
				notificationItem.appendChild(deleteIcon);

				notificationList.appendChild(notificationItem);
			});
		});
	</script>

	<!-- simpan lokasi ke firebase -->
	<script>
		const waktu = firebase.database().ref('location');
		const lats = parseFloat(document.getElementById('latitudes').value);
		const lngs = parseFloat(document.getElementById('longitudes').value);

		let estimasiLat;
		let estimasiLng;

		waktu.on('value', (snapshot) => {
			const data = snapshot.val();
			estimasiLat = '';
			estimasiLng = '';

			for (const key in data) {
				estimasiLat += data[key].latitude;
				estimasiLng += data[key].longitude;
			}

			const origin = {
				lat: parseFloat(estimasiLat),
				lng: parseFloat(estimasiLng)
			};

			console.log(origin);

			const destination = new google.maps.LatLng(lats, lngs);
			const service = new google.maps.DistanceMatrixService();


			service.getDistanceMatrix({
				origins: [origin],
				destinations: [destination],
				travelMode: google.maps.TravelMode.DRIVING,
				avoidHighways: false,
				avoidTolls: false
			}, function(response, status) {
				if (status === "OK") {
					const result = response.rows[0].elements[0];
					document.getElementById("estimasi").innerHTML = result.duration.text;
				} else {
					alert("Error: " + status);
				}
			});


		});
	</script>

	<!-- popup untuk accepted-->
	<script>
		function closePopup() {
			const popup = document.getElementById("acceptedPopup");
			if (popup) popup.style.display = "none";
		}

		// Auto-close setelah 5 detik
		setTimeout(closePopup, 5000);
	</script>

	<!-- Download resep obat -->
	<!-- <script>
		function downloadCard(cardId) {
			const element = document.getElementById(cardId);
			const opt = {
				margin: 0.3,
				filename: 'resep-' + cardId + '.pdf',
				image: {
					type: 'jpeg',
					quality: 0.98
				},
				html2canvas: {
					scale: 2
				},
				jsPDF: {
					unit: 'cm',
					format: 'a5',
					orientation: 'portrait'
				}
			};
			html2pdf().set(opt).from(element).save();
		}
	</script> -->
	<script>
		function downloadCard(cardId) {
			// Ambil elemen resep
			var cardContent = document.getElementById(cardId).innerHTML;

			// Buat jendela baru untuk mencetak
			var printWindow = window.open('', '', 'height=600,width=800');

			printWindow.document.write(`
    <html>
    <head>
      <title>Resep Dokter</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          padding: 20px;
        }
        table {
          width: 100%;
          border-collapse: collapse;
        }
        th, td {
          padding: 8px;
          border: 1px solid #ccc;
        }
        th {
          background-color: #f8f8f8;
        }
        h5 {
          text-align: center;
        }
        .btn, .badge {
          display: none !important;
        }
      </style>
    </head>
    <body>
      ${cardContent}
    </body>
    </html>
  `);

			printWindow.document.close(); // Tutup dokumen
			printWindow.focus(); // Fokus ke jendela

			printWindow.print(); // Jalankan perintah print
			printWindow.close(); // Tutup jendela setelah cetak
		}
	</script>


	<!-- tampil notif dari nakes -->
	<script>
		var request_id = "<?php echo $id_request; ?>";

		// Ambil data dari node 'notif'
		const notifRef = firebase.database().ref("notiffromdoc");

		// Dengarkan perubahan data notifikasi
		notifRef.on("value", (snapshot) => {
			const data = snapshot.val();
			if (!data) return;

			for (let id in data) {
				const notif = data[id];
				const statusNotif = document.getElementById("statusNotif");
				if (notif.status === "Nakes Menuju Lokasi" && notif.id_req === request_id) {
					if (statusNotif) {
						statusNotif.innerHTML = notif.status;
					} else {
						console.error("Element with ID 'statusNotif' not found.");
					}
					// Tampilkan popup
					showPopup();
					break; // tampilkan hanya sekali jika ada
				} else if (notif.id_req !== request_id) {
					if (statusNotif) {
						statusNotif.innerHTML = "Menunggu Antrian";
					} else {
						console.error("Element with ID 'statusNotif' not found.");
					}
				}
			}
		});

		function showPopup() {
			const popup = document.getElementById("acceptedPopup");
			popup.style.display = "block";

			// Sembunyikan otomatis setelah 10 detik (opsional)
			setTimeout(() => {
				popup.style.display = "none";
			}, 10000);
		}

		function closePopup() {
			document.getElementById("acceptedPopup").style.display = "none";
		}
	</script>

	<!-- rating dokter -->
	<script>
		var idUsers = "<?php echo $_SESSION['id']; ?>";
		var request_id = document.getElementById("reqIdRat").value;
		const baseUrl = "<?= base_url('/uploads/profile/') ?>";
		console.log("id user: " + idUsers);
		console.log("id_request: " + request_id);

		// Ambil data dari node 'notif'
		const notifRate = firebase.database().ref("rating");

		let alreadyShown = false;

		notifRate.on("value", (snapshot) => {
			if (alreadyShown) return;

			const data = snapshot.val();
			if (!data) return;

			for (let id in data) {
				const notif = data[id];

				if (notif.idUser === idUsers && notif.idReq === request_id) {
					alreadyShown = true; // mencegah tampil ulang
					const idDokter = notif.idDokter;

					$.ajax({
						url: "<?= base_url('home/getDokterRating') ?>",
						type: "POST",
						data: {
							id_dokter: idDokter
						},
						dataType: "json",
						success: function(response) {
							const dataDokter = response[0];

							document.getElementById('gambarDokter').src = baseUrl + dataDokter.foto;
							document.getElementById('namaDokter').textContent = dataDokter.nama;
							document.getElementById('dokIds').value = idDokter;

							showPopupRate();
						}
					});

					break;
				}
			}
		});

		function showPopupRate() {
			const popupRatingModal = new bootstrap.Modal(document.getElementById('ratingModal'));
			popupRatingModal.show();
		}
	</script>

	<!-- submit rating -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.getElementById('ratingForm').addEventListener('submit', function(e) {
				e.preventDefault();

				// Lakukan submit rating ke server via AJAX
				const idUser = this.iduser.value;
				const idDokter = this.id_dokter.value;
				const rating = this.rating.value;
				console.log(idUser);
				console.log(idDokter);
				console.log(rating);

				$.ajax({
					url: '<?= base_url('home/submit_rating'); ?>',
					type: 'POST',
					data: {
						id_user: idUser,
						id_dokter: idDokter,
						rating: rating
					},
					dataType: 'json',
					success: function(data) {
						if (data.status === 'success') {

							const deleteRate = firebase.database().ref('rating');
							deleteRate.on('value', (snapshot) => {
								const data = snapshot.val();
								if (data) {
									for (const key in data) {
										const item = data[key];
										if (item.idUser === idUser && item.idReq === request_id) {
											deleteRate.child(key).remove();
										}
									}
								}
							});

							Swal.fire({
								title: 'Berhasil',
								text: 'Rating berhasil disimpan.',
								icon: 'success',
								confirmButtonText: 'OK'
							}).then(() => {
								const ratingModal = bootstrap.Modal.getInstance(document.getElementById('ratingModal'));
								ratingModal.hide();
								window.location.href = 'home#riwayat_konsul_selesai';
							});
						} else {
							Swal.fire({
								title: 'Error',
								text: 'Gagal menyimpan rating, coba lagi.',
								icon: 'error',
								confirmButtonText: 'OK'
							});
						}
					},
					error: function(xhr, status, error) {
						console.error('Error:', error);
						Swal.fire({
							title: 'Error',
							text: 'Terjadi kesalahan.',
							icon: 'error',
							confirmButtonText: 'OK'
						});
					}
				});

			});
		});
	</script>

	<!-- mendapatkan url -->
	<script>
		document.addEventListener("DOMContentLoaded", function() {
			const hash = window.location.hash;

			if (hash === "#riwayat" || hash === "#riwayat_konsul_selesai") {
				if (typeof showContent === 'function') {
					showContent('riwayat');
				}

				setTimeout(() => {
					let tabID = (hash === "#riwayat_konsul_selesai") ? "#selesai-tab" : "#proses-tab";
					const tabTrigger = document.querySelector(tabID);
					if (tabTrigger) {
						const tab = new bootstrap.Tab(tabTrigger);
						tab.show();
					}
				}, 300);
			}
		});
	</script>

</body>

</html>
