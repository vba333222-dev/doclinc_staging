<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="description" content="">
	<meta name="author" content="">
	<!-- <title><?php echo $this->session->flashdata('active_tab_dashboard'); ?></title> -->
	<title><?php echo $this->session->flashdata('title'); ?></title>
	<!-- Custom fonts for this template-->
	<link href="<?php echo base_url(); ?>assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
	<link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
	<!-- Custom styles for this template-->
	<link href="<?php echo base_url(); ?>assets/css/sb-admin-2.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.6.2/chart.min.js" integrity="sha512-tMabqarPtykgDtdtSqCL3uLVM0gS1ZkUAVhRFu1vSEFgvB73niFQWJuvviDyBGBH22Lcau4rHB5p2K2T0Xvr6Q==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="<?php echo base_url(); ?>assets/vendor/jquery/jquery.min.js"></script>
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs4/jq-3.6.0/jszip-2.5.0/dt-1.11.3/b-2.0.1/b-colvis-2.0.1/b-html5-2.0.1/b-print-2.0.1/cr-1.5.5/date-1.1.1/fc-4.0.1/fh-3.2.0/kt-2.6.4/r-2.2.9/datatables.min.css" />
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/v/bs4/jq-3.6.0/jszip-2.5.0/dt-1.11.3/b-2.0.1/b-colvis-2.0.1/b-html5-2.0.1/b-print-2.0.1/cr-1.5.5/date-1.1.1/fc-4.0.1/fh-3.2.0/kt-2.6.4/r-2.2.9/datatables.min.js"></script>

	<!-- Sweet Alert -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<!-- Custom CSS -->
	<link href="<?php echo base_url(); ?>assets/css/custom.css" rel="stylesheet">
	<!-- gijgo -->
	<script src="https://unpkg.com/gijgo@1.9.13/js/gijgo.min.js" type="text/javascript"></script>
	<link href="https://unpkg.com/gijgo@1.9.13/css/gijgo.min.css" rel="stylesheet" type="text/css" />
	<link href="../../../assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
	<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
	<!-- buatkan untuk icon web -->
	<link rel="icon" type="image/png" href="<?php echo base_url('../../assets/images/logo/doklinc.png'); ?>">

	<!-- Custom Modern Hospital Dashboard Styles -->
	<style>
		body {
			background: #f4f6fb;
			font-family: 'Nunito', sans-serif;
		}

		.sidebar {
			background: linear-gradient(135deg, #2e8b57 0%, #38b6ff 100%);
		}

		.sidebar .sidebar-brand-icon img {
			max-width: 60px;
		}

		.sidebar .nav-item {
			margin-bottom: 8px;
		}

		.sidebar .nav-link {
			border-radius: 8px;
			transition: background 0.2s, color 0.2s;
			color: #fff;
		}

		.sidebar .nav-link.active,
		.sidebar .nav-link:hover {
			background: rgba(255, 255, 255, 0.15);
			color: #fff;
		}

		.sidebar .nav-link i {
			margin-right: 8px;
		}

		.topbar {
			background: #fff;
			border-bottom: 1px solid #e3e6f0;
			box-shadow: 0 2px 8px rgba(46, 139, 87, 0.05);
		}

		.topbar .navbar-nav .nav-link {
			color: #2e8b57;
		}

		.topbar .navbar-nav .nav-link:hover {
			color: #38b6ff;
		}

		.topbar .dropdown-menu {
			border-radius: 10px;
		}

		.container-fluid {
			padding: 30px 20px;
		}

		@media (max-width: 768px) {
			#map<?= $baru->request_id; ?> {
				height: 300px;
			}

			#directionsPanel<?= $baru->request_id; ?> {
				max-height: 300px;
			}

			.sidebar {
				background: #2e8b57;
			}
		}

		.print-signature {
			display: none;
		}

		@media print {
			.print-signature {
				display: block;
				margin-top: 60px;
				text-align: center;
			}
		}
	</style>

</head>

<body id="page-top">
	<div id="wrapper">
		<!-- Sidebar -->
		<ul class="navbar-nav sidebar sidebar-dark accordion position-fixed vh-100" id="accordionSidebar" style="top:0; left:0; z-index:1030; width: 220px;">
			<!-- Sidebar - Brand -->
			<a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
				<div class="sidebar-brand-icon">
					<img src="../../assets/images/logo/doklincwhite.png" alt="Logo">
				</div>
			</a>

			<li class="nav-item <?= $this->session->flashdata('active_tab_dashboard'); ?>">
				<a class="nav-link" href="<?php echo site_url('home'); ?>">
					<i class="fas fa-tachometer-alt"></i>
					<span>Dashboard</span>
				</a>
			</li>

			<li class="nav-item <?= $this->session->flashdata('active_tab_keluhan'); ?>">
				<a class="nav-link" href="<?php echo site_url('kelola_keluhan'); ?>">
					<i class="fas fa-comments"></i>
					<span>Kelola Keluhan</span>
				</a>
			</li>

			<li class="nav-item">
				<a class="nav-link" href="<?php echo site_url('kelola_news_feed'); ?>">
					<i class="fas fa-newspaper"></i>
					<span>Kelola News & Feed</span>
				</a>
			</li>

			<li class="nav-item">
				<a class="nav-link" href="<?php echo site_url('kelola_dokter_nakes'); ?>">
					<i class="fas fa-user-md"></i>
					<span>Kelola Dokter/Nakes</span>
				</a>
			</li>

			<li class="nav-item">
				<a class="nav-link" href="<?php echo site_url('konsultasi_kesehatan'); ?>">
					<i class="fas fa-stethoscope"></i>
					<span>Laporan Detail</span>
				</a>
			</li>

			<li class="nav-item">
				<a class="nav-link" href="<?php echo site_url('kelola_layanan_kesehatan'); ?>">
					<i class="fas fa-clinic-medical"></i>
					<span>Kelola Layanan Kesehatan</span>
				</a>
			</li>

			<li class="nav-item">
				<a class="nav-link" href="<?php echo site_url('kelola_tindakan'); ?>">
					<i class="fas fa-procedures"></i>
					<span>Kelola Tindakan</span>
				</a>
			</li>

			<li class="nav-item">
				<a class="nav-link" href="<?php echo site_url('laporan'); ?>">
					<i class="fas fa-file-alt"></i>
					<span>Resume</span>
				</a>
			</li>

			<div class="text-center d-none d-md-inline">
				<button class="rounded-circle border-0" id="sidebarToggle"></button>
			</div>
		</ul>
		<!-- End of Sidebar -->

		<!-- Content Wrapper -->
		<div id="content-wrapper" class="d-flex flex-column" style="margin-left:220px;">
			<div id="content">
				<!-- Topbar -->
				<nav class="navbar navbar-expand navbar-light topbar mb-4 static-top shadow" style="background: #f8fafc; position:sticky; top:0; z-index:1020;">
					<button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
						<i class="fa fa-bars"></i>
					</button>
					<!-- Hospital Branding -->
					<div class="d-none d-md-flex align-items-center mr-auto">
						<span class="h5 mb-0 font-weight-bold" style="color:#2e8b57;">Halaman Admin Doklinc</span>
					</div>
					<ul class="navbar-nav ml-auto align-items-center">
						<!-- Notification Bell -->
						<li class="nav-item dropdown no-arrow mx-2">
							<a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								<i class="fas fa-bell fa-lg text-gray-600"></i>
								<span class="badge badge-danger badge-counter" style="font-size:0.7rem;">3</span>
							</a>
							<div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="alertsDropdown">
								<h6 class="dropdown-header">Notifications</h6>
								<a class="dropdown-item d-flex align-items-center" href="#">
									<div class="mr-3">
										<div class="icon-circle bg-primary">
											<i class="fas fa-file-alt text-white"></i>
										</div>
									</div>
									<div>
										<span class="small text-gray-500">New report available</span>
									</div>
								</a>
								<a class="dropdown-item d-flex align-items-center" href="#">
									<div class="mr-3">
										<div class="icon-circle bg-success">
											<i class="fas fa-user-md text-white"></i>
										</div>
									</div>
									<div>
										<span class="small text-gray-500">Doctor added</span>
									</div>
								</a>
								<a class="dropdown-item text-center small text-gray-500" href="#">Show All Alerts</a>
							</div>
						</li>
						<!-- User Profile Dropdown -->
						<li class="nav-item dropdown no-arrow">
							<a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-toggle="dropdown">
								<img class="img-profile rounded-circle mr-2" src="https://c1.klipartz.com/pngpicture/823/765/sticker-png-login-icon-system-administrator-user-user-profile-icon-design-avatar-face-head-thumbnail.png" style="width:36px; height:36px; object-fit:cover;">
								<span class="d-none d-lg-inline text-gray-700 small font-weight-bold"><?= htmlspecialchars($_SESSION['username']) ?></span>
							</a>
							<div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
								<a class="dropdown-item" href="#">
									<i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
									Profile
								</a>
								<a class="dropdown-item" href="#">
									<i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
									Settings
								</a>
								<div class="dropdown-divider"></div>
								<a class="dropdown-item" href="<?php echo site_url('login/logout'); ?>">
									<i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
									Logout
								</a>
							</div>
						</li>
					</ul>
				</nav>
				<!-- End of Topbar -->
				<!-- Begin Page Content -->
				<div class="container-fluid">
