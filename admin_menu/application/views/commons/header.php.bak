<!DOCTYPE html>
<html lang="en">

<head>
	<?php
	$doclinc_admin_base_url = rtrim(base_url(), '/');
	$doclinc_public_base_url = preg_replace('#/admin_menu$#', '', $doclinc_admin_base_url);
	$doclinc_logo_url = $doclinc_public_base_url . '/assets/images/doklinc.png';
	?>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="description" content="">
	<meta name="author" content="">
	<!-- <title><?php echo $this->session->flashdata('active_tab_dashboard'); ?></title> -->
	<title><?php echo $this->session->flashdata('title'); ?></title>
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

	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
	<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
	<link rel="icon" type="image/png" href="<?php echo html_escape($doclinc_logo_url); ?>">

</head>

<body id="page-top">
	<div id="wrapper">
		<?php
		$doclinc_admin_segment = $this->uri->segment(1) ?: 'home';
		$doclinc_active_dashboard = ($doclinc_admin_segment === 'home' || $this->session->flashdata('active_tab_dashboard')) ? 'active' : '';
		$doclinc_active_konsultasi = ($doclinc_admin_segment === 'konsultasi_kesehatan' || $this->session->flashdata('active_tab_konsultasi_kesehatan')) ? 'active' : '';
		$doclinc_active_master_puskesmas = ($doclinc_admin_segment === 'master_puskesmas' || $this->session->flashdata('active_tab_master_puskesmas')) ? 'active' : '';
		$doclinc_active_akun_puskesmas = ($doclinc_admin_segment === 'kelola_dokter_nakes' || $this->session->flashdata('active_tab_kelola_dokter_nakes')) ? 'active' : '';
		$doclinc_active_staff_puskesmas = ($doclinc_admin_segment === 'kelola_staff_puskesmas' || $this->session->flashdata('active_tab_kelola_staff_puskesmas')) ? 'active' : '';
		$doclinc_active_keluhan = ($doclinc_admin_segment === 'kelola_keluhan' || $this->session->flashdata('active_tab_keluhan')) ? 'active' : '';
		$doclinc_active_rekam_medis = ($doclinc_admin_segment === 'rekam_medis' || $this->session->flashdata('active_tab_rekam_medis')) ? 'active' : '';
		$doclinc_active_laporan = ($doclinc_admin_segment === 'laporan' || $this->session->flashdata('active_tab_laporan')) ? 'active' : '';
		$doclinc_active_news = ($doclinc_admin_segment === 'kelola_news_feed' || $this->session->flashdata('active_tab_news_feed')) ? 'active' : '';
		?>
		<!-- Sidebar -->
		<ul class="navbar-nav sidebar sidebar-dark accordion position-fixed vh-100 doclinc-sidebar" id="accordionSidebar">
			<!-- Sidebar - Brand -->
			<a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
				<div class="sidebar-brand-icon">
					<img src="<?php echo html_escape($doclinc_logo_url); ?>" alt="DocLink">
				</div>
			</a>

			<div class="doclinc-sidebar-section doclinc-sidebar-heading">Dashboard</div>
			<li class="nav-item <?= $doclinc_active_dashboard; ?>">
				<a class="nav-link" href="<?php echo site_url('home'); ?>">
					<i class="fas fa-tachometer-alt"></i>
					<span>Dashboard</span>
				</a>
			</li>

			<div class="doclinc-sidebar-section doclinc-sidebar-heading">Operasional</div>
			<li class="nav-item <?= $doclinc_active_konsultasi; ?>">
				<a class="nav-link" href="<?php echo site_url('konsultasi_kesehatan'); ?>">
					<i class="fas fa-stethoscope"></i>
					<span>Pantau konsultasi</span>
				</a>
			</li>

			<div class="doclinc-sidebar-section doclinc-sidebar-heading">Puskesmas</div>
			<li class="nav-item <?= $doclinc_active_master_puskesmas; ?>">
				<a class="nav-link" href="<?php echo site_url('master_puskesmas'); ?>">
					<i class="fas fa-hospital"></i>
					<span>Data Puskesmas</span>
				</a>
			</li>

			<li class="nav-item <?= $doclinc_active_akun_puskesmas; ?>">
				<a class="nav-link" href="<?php echo site_url('kelola_dokter_nakes'); ?>">
					<i class="fas fa-user-md"></i>
					<span>Akun Puskesmas</span>
				</a>
			</li>

			<li class="nav-item <?= $doclinc_active_staff_puskesmas; ?>">
				<a class="nav-link" href="<?php echo site_url('kelola_staff_puskesmas'); ?>">
					<i class="fas fa-users-cog"></i>
					<span>Staf Puskesmas</span>
				</a>
			</li>

			<div class="doclinc-sidebar-section doclinc-sidebar-heading">Data</div>
			<li class="nav-item <?= $doclinc_active_keluhan; ?>">
				<a class="nav-link" href="<?php echo site_url('kelola_keluhan'); ?>">
					<i class="fas fa-comments"></i>
					<span>Data keluhan</span>
				</a>
			</li>

			<div class="doclinc-sidebar-section doclinc-sidebar-heading">Laporan</div>
			<li class="nav-item <?= $doclinc_active_rekam_medis; ?>">
				<a class="nav-link" href="<?php echo site_url('rekam_medis'); ?>">
					<i class="fas fa-notes-medical"></i>
					<span>Pemantauan layanan</span>
				</a>
			</li>

			<li class="nav-item <?= $doclinc_active_laporan; ?>">
				<a class="nav-link" href="<?php echo site_url('laporan'); ?>">
					<i class="fas fa-file-alt"></i>
					<span>Laporan</span>
				</a>
			</li>

			<div class="doclinc-sidebar-section doclinc-sidebar-heading">Konten</div>
			<li class="nav-item <?= $doclinc_active_news; ?>">
				<a class="nav-link" href="<?php echo site_url('kelola_news_feed'); ?>">
					<i class="fas fa-newspaper"></i>
					<span>Berita</span>
				</a>
			</li>

			<div class="text-center d-none d-md-inline">
				<button class="rounded-circle border-0" id="sidebarToggle"></button>
			</div>
		</ul>
		<!-- End of Sidebar -->

		<!-- Content Wrapper -->
		<div id="content-wrapper" class="d-flex flex-column doclinc-content-wrapper">
			<div id="content">
				<!-- Topbar -->
				<nav class="navbar navbar-expand navbar-light topbar mb-4 static-top shadow doclinc-topbar">
					<button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
						<i class="fa fa-bars"></i>
					</button>
					<!-- Hospital Branding -->
					<div class="d-none d-md-flex align-items-center mr-auto">
						<img src="<?php echo html_escape($doclinc_logo_url); ?>" alt="DocLink" class="doclinc-topbar-logo mr-2">
						<span class="h5 mb-0 font-weight-bold doclinc-topbar-brand">Halaman Admin DocLink</span>
					</div>
					<ul class="navbar-nav ml-auto align-items-center">
						<li class="nav-item mx-2">
							<button type="button" class="btn btn-light btn-sm doclinc-theme-toggle" id="doclincThemeToggle" aria-label="Ubah tema" title="Ubah tema">
								<i class="fas fa-moon" aria-hidden="true"></i>
								<span class="d-none d-lg-inline ml-1">Mode</span>
							</button>
						</li>
						<!-- Notification Bell -->
						<li class="nav-item dropdown no-arrow mx-2">
							<a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								<i class="fas fa-bell fa-lg text-gray-600"></i>
								<span class="badge badge-danger badge-counter doclinc-notification-badge" id="doclincNotificationBadge" style="display:none;"></span>
							</a>
							<div class="dropdown-menu dropdown-menu-right shadow animated--grow-in doclinc-notification-menu" aria-labelledby="alertsDropdown">
								<div class="dropdown-header d-flex align-items-center justify-content-between">
									<span>Notifikasi</span>
									<button type="button" class="btn btn-link btn-sm p-0 doclinc-notification-mark-read" id="doclincNotificationMarkRead" style="display:none;">Tandai semua dibaca</button>
								</div>
								<div id="doclincNotificationList">
									<div class="dropdown-item text-center small text-muted py-3">Belum ada notifikasi baru.</div>
								</div>
							</div>
						</li>
						<!-- User Profile Dropdown -->
						<li class="nav-item dropdown no-arrow">
							<a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-toggle="dropdown">
								<img class="img-profile rounded-circle mr-2 doclinc-profile-image" src="https://c1.klipartz.com/pngpicture/823/765/sticker-png-login-icon-system-administrator-user-user-profile-icon-design-avatar-face-head-thumbnail.png">
								<span class="d-none d-lg-inline text-gray-700 small font-weight-bold"><?= html_escape($this->session->userdata('username') ?: '') ?></span>
							</a>
							<div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
								<a class="dropdown-item" href="#">
									<i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
									Profil
								</a>
								<a class="dropdown-item" href="#">
									<i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
									Pengaturan
								</a>
								<div class="dropdown-divider"></div>
								<form method="post" action="<?php echo site_url('login/logout'); ?>">
									<input type="hidden" name="_logout_token" value="<?= html_escape((string) $this->session->userdata('admin_logout_token')); ?>">
									<button type="submit" class="dropdown-item">
										<i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
										Keluar
									</button>
								</form>
							</div>
						</li>
					</ul>
				</nav>
				<!-- End of Topbar -->
				<!-- Begin Page Content -->
				<div class="container-fluid doclinc-admin-page">
					<script type="text/javascript">
						$(function() {
							if (window.doclincAdminNotificationsInitialized) {
								return;
							}
							window.doclincAdminNotificationsInitialized = true;

							var summaryUrl = "<?= site_url('notifikasi_admin/summary'); ?>";
							var markReadUrl = "<?= site_url('notifikasi_admin/mark_read'); ?>";
							var $badge = $('#doclincNotificationBadge');
							var $list = $('#doclincNotificationList');
							var $markRead = $('#doclincNotificationMarkRead');
							var emptyHtml = '<div class="dropdown-item text-center small text-muted py-3">Belum ada notifikasi baru.</div>';

							function escapeHtml(value) {
								return $('<div>').text(value || '').html();
							}

							function renderNeutral() {
								$badge.hide().text('');
								$markRead.hide();
								$list.html(emptyHtml);
							}

							function renderSummary(response) {
								if (!response || response.ok !== true) {
									renderNeutral();
									return;
								}

								var unreadCount = parseInt(response.unread_count || 0, 10);
								if (unreadCount > 0) {
									$badge.text(unreadCount > 99 ? '99+' : unreadCount).show();
									$markRead.show();
								} else {
									$badge.hide().text('');
									$markRead.hide();
								}

								var events = $.isArray(response.events) ? response.events : [];
								if (!events.length) {
									$list.html(emptyHtml);
									return;
								}

								var html = '';
								$.each(events, function(index, event) {
									var requestId = event.request_id ? '#REQ-' + escapeHtml(event.request_id) : 'Permintaan';
									var puskesmas = event.puskesmas ? '<span class="doclinc-notification-puskesmas">' + escapeHtml(event.puskesmas) + '</span>' : '';
									var message = event.message ? '<div class="doclinc-notification-message">' + escapeHtml(event.message) + '</div>' : '';
									html += '<div class="dropdown-item doclinc-notification-item">'
										+ '<div class="font-weight-bold">' + escapeHtml(event.title || 'Aktivitas konsultasi diperbarui') + '</div>'
										+ '<div class="doclinc-notification-meta">' + requestId + (puskesmas ? ' &middot; ' + puskesmas : '') + '</div>'
										+ message
										+ '<div class="doclinc-notification-time">' + escapeHtml(event.created_at_label || event.created_at || '') + '</div>'
										+ '</div>';
								});
								$list.html(html);
							}

							function loadNotifications() {
								$.ajax({
									url: summaryUrl,
									type: 'GET',
									dataType: 'json',
									cache: false,
									xhrFields: {
										withCredentials: true
									}
								}).done(renderSummary).fail(renderNeutral);
							}

							$markRead.off('click.doclincNotifications').on('click.doclincNotifications', function(event) {
								event.preventDefault();
								$.ajax({
									url: markReadUrl,
									type: 'POST',
									dataType: 'json',
									xhrFields: {
										withCredentials: true
									}
								}).done(loadNotifications).fail(renderNeutral);
							});

							loadNotifications();
							window.doclincAdminNotificationsTimer = window.setInterval(loadNotifications, 60000);
						});
					</script>
