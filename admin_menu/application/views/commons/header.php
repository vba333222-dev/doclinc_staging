<!DOCTYPE html>
<html lang="id">

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
	<?php /* Chart.js disajikan dari origin ini, bukan dari CDN. Panel dipakai di
	   jaringan Puskesmas yang tidak selalu punya internet; saat CDN tidak
	   terjangkau `window.Chart` tetap undefined dan grafik dasbor hanya
	   menyisakan kotak kosong tanpa penjelasan. Berkas vendor ini byte-identik
	   dengan build 3.6.2 di cdnjs (hash SRI lamanya tercatat di
	   assets/vendor/chartjs/PROVENANCE.md). */ ?>
	<script src="<?php echo base_url(); ?>assets/vendor/chartjs/chart.min.js"></script>
	<script src="<?php echo base_url(); ?>assets/vendor/jquery/jquery.min.js"></script>
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs4/jq-3.6.0/jszip-2.5.0/dt-1.11.3/b-2.0.1/b-colvis-2.0.1/b-html5-2.0.1/b-print-2.0.1/cr-1.5.5/date-1.1.1/fc-4.0.1/fh-3.2.0/kt-2.6.4/r-2.2.9/datatables.min.css" />
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/v/bs4/jq-3.6.0/jszip-2.5.0/dt-1.11.3/b-2.0.1/b-colvis-2.0.1/b-html5-2.0.1/b-print-2.0.1/cr-1.5.5/date-1.1.1/fc-4.0.1/fh-3.2.0/kt-2.6.4/r-2.2.9/datatables.min.js"></script>

	<!-- Sweet Alert -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<!-- Custom CSS -->
	<link href="<?php echo base_url(); ?>assets/css/custom.css" rel="stylesheet">
	<?php
	/* Lapisan visual panel hidup di folder aset aplikasi utama. Panel disajikan
	   dari /admin_menu/, jadi 'assets/...' menunjuk ke folder panel - dan di sana
	   berkasnya tidak ada. Permintaannya dulu dijawab CI dengan 200 berbadan
	   kosong (Content-Type text/html), sehingga token dan gaya .doclinc-admin-*
	   tidak pernah termuat. Pola '../assets/...' sama dengan berkas JS di footer. */
	$doclinc_reference_css = '../assets/css/doclinc-reference-ui.css';
	?>
	<link rel="stylesheet" href="<?= html_escape(base_url($doclinc_reference_css) . '?v=' . rawurlencode((string) @filemtime(FCPATH . $doclinc_reference_css))); ?>">
	<?php
	/* Konvensi bersama seluruh halaman panel: kepala halaman, tombol info,
	   aksi utama, dan isi modal penjelasan. Dimuat SETELAH CSS referensi supaya
	   aturannya menang atas nilai bawaan, dan hanya sekali per halaman. */
	$doclinc_admin_shell_css = 'assets/css/doclinc-admin-shell.css';
	?>
	<link rel="stylesheet" href="<?= html_escape(base_url($doclinc_admin_shell_css) . '?v=' . rawurlencode((string) @filemtime(FCPATH . $doclinc_admin_shell_css))); ?>">
	<!-- gijgo -->
	<script src="https://unpkg.com/gijgo@1.9.13/js/gijgo.min.js" type="text/javascript"></script>
	<link href="https://unpkg.com/gijgo@1.9.13/css/gijgo.min.css" rel="stylesheet" type="text/css" />

	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
	<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
	<link rel="icon" type="image/png" href="<?php echo html_escape($doclinc_logo_url); ?>">

</head>

<body id="page-top" class="doclinc-admin-shell">
	<meta name="csrf-token-name" content="<?= html_escape($this->security->get_csrf_token_name()); ?>">
	<meta name="csrf-token-hash" content="<?= html_escape($this->security->get_csrf_hash()); ?>">
	<script>
		(function($) {
			if (!$) return;
			$(function() {
			var name = document.querySelector('meta[name="csrf-token-name"]')?.content || '';
			var hash = document.querySelector('meta[name="csrf-token-hash"]')?.content || '';
			$('form[method="post"], form[method="POST"]').each(function() {
				if (name && !this.querySelector('input[name="' + name + '"]')) {
					$('<input>', {type: 'hidden', name: name, value: hash}).appendTo(this);
				}
			});
			$.ajaxSetup({ data: (function(original) {
				return function() {
					var data = typeof original === 'function' ? original.apply(this, arguments) : original;
					if (!name) return data;
					if (typeof data === 'string') return data + (data ? '&' : '') + encodeURIComponent(name) + '=' + encodeURIComponent(hash);
					data = data || {};
					if (typeof data === 'object' && data[name] === undefined) data[name] = hash;
					return data;
				};
			})($.ajaxSettings.data) });
			});
		})(window.jQuery);
	</script>
	<div id="wrapper">
		<?php
		$doclinc_admin_segment = $this->uri->segment(1) ?: 'home';
		/* Penanda menu aktif hanya dari segmen URL. Sebelumnya setiap modul juga
		   menitipkan flashdata 'active_tab_*' yang dibaca di sini; karena flashdata
		   masih terbaca pada permintaan berikutnya, beberapa menu tampak aktif
		   sekaligus (terukur: 6 menu aktif bersamaan) dan penandanya bergantung pada
		   halaman yang terakhir dibuka, bukan halaman yang sedang dilihat. Segmen
		   URL selalu tepat dan tidak perlu dirawat dari sisi modul. */
		/* Navigasi dikelompokkan menurut pekerjaan, bukan menurut tabel basis
		   data. Sebelumnya tujuh judul untuk dua puluh tautan - lima di antaranya
		   berisi satu butir - sehingga judul berhenti menolong dan hanya menambah
		   tinggi. Empat kelompok membuat setiap tujuan menjawab satu pertanyaan:
		   sedang merawat layanan, mengurus data, menulis konten, atau mengawasi
		   tata kelola.
		
		   Daftar ini satu-satunya sumber: <li> yang dulu ditulis tangan
		   diganti perulangan, dan penanda aktif dihitung dari rute butir itu
		   sendiri - jadi tautan dan penandanya tidak bisa menyimpang lagi.

		   Delapan belas tautan, bukan dua puluh: "Akun Puskesmas", "Staf
		   Puskesmas", dan "Data Puskesmas" adalah tiga pintu ke pekerjaan yang
		   sama, dan masing-masing menampilkan Puskesmas dengan cara berbeda.
		   Ketiganya digabung menjadi satu butir "Puskesmas" yang menuju
		   `kelola_dokter_nakes`: daftar sembilan Puskesmas inti, dengan profil
		   fasilitas, akun, dan staf dikelola dari halaman detailnya. Dua rute
		   lama tetap hidup - `master_puskesmas` dialihkan ke daftar itu - jadi
		   tautan luar dan uji tata kelola tidak putus. */
		$doclinc_sidebar_groups = array(
			'Operasional' => array(
				array('route' => 'home', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard'),
				array('route' => 'rekam_medis', 'icon' => 'fa-notes-medical', 'text' => 'Pemantauan layanan'),
				array('route' => 'konsultasi_kesehatan', 'icon' => 'fa-stethoscope', 'text' => 'Pantau konsultasi'),
				array('route' => 'kelola_tindakan', 'icon' => 'fa-procedures', 'text' => 'Tindakan'),
			),
			'Data induk' => array(
				array('route' => 'kelola_warga', 'icon' => 'fa-users', 'text' => 'Data warga'),
				array('route' => 'kelola_dokter_nakes', 'icon' => 'fa-hospital', 'text' => 'Puskesmas'),
				array('route' => 'wilayah', 'icon' => 'fa-map-marked-alt', 'text' => 'Master wilayah'),
				array('route' => 'icd10', 'icon' => 'fa-book-medical', 'text' => 'Master ICD-10'),
				array('route' => 'diagnosis', 'icon' => 'fa-chart-bar', 'text' => 'Infografik'),
				array('route' => 'kelola_keluhan', 'icon' => 'fa-comments', 'text' => 'Data keluhan'),
			),
			'Konten' => array(
				array('route' => 'kelola_layanan_kesehatan', 'icon' => 'fa-hand-holding-heart', 'text' => 'Layanan kesehatan'),
				array('route' => 'kelola_news_feed', 'icon' => 'fa-newspaper', 'text' => 'Berita'),
				array('route' => 'konten_landing', 'icon' => 'fa-window-maximize', 'text' => 'Konten landing'),
				array('route' => 'landing_page', 'icon' => 'fa-globe', 'text' => 'Landing page'),
			),
			'Tata kelola' => array(
				array('route' => 'laporan', 'icon' => 'fa-file-alt', 'text' => 'Laporan'),
				array('route' => 'kapabilitas', 'icon' => 'fa-user-shield', 'text' => 'Kapabilitas admin'),
				array('route' => 'audit', 'icon' => 'fa-clipboard-list', 'text' => 'Penampil audit'),
				array('route' => 'sesi', 'icon' => 'fa-shield-alt', 'text' => 'Sesi & keamanan'),
			),
		);
		?>
		<!-- Latar gelap saat panel geser terbuka di layar sempit. Panel menutupi
		     tombol menunya sendiri, jadi tanpa sasaran ketuk di luar panel, menu
		     yang sudah terbuka tidak bisa ditutup. -->
		<div class="doclinc-sidebar-backdrop" id="sidebarBackdrop" hidden></div>
		<!-- Sidebar -->
		<ul class="navbar-nav sidebar sidebar-dark accordion position-fixed vh-100 doclinc-sidebar" id="accordionSidebar">
			<!-- Sidebar - Brand -->
			<!-- Dulu href="#" sehingga mengklik logo tidak melakukan apa pun. Sekarang
			     menuju dasbor, sesuai kebiasaan umum panel admin. Tautannya dibungkus
			     <li> karena <ul> hanya boleh berisi <li> (bawaan SB Admin menaruh <a>
			     langsung di dalam <ul>). -->
			<li class="doclinc-sidebar-brand-item">
				<a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?php echo site_url('home'); ?>" aria-label="DocLink, kembali ke dasbor">
					<div class="sidebar-brand-icon">
						<img src="<?php echo html_escape($doclinc_logo_url); ?>" alt="DocLink" width="3167" height="1396">
					</div>
				</a>
			</li>

			<?php foreach ($doclinc_sidebar_groups as $doclinc_group_label => $doclinc_group_items): ?>
				<li class="doclinc-sidebar-section doclinc-sidebar-heading"><?= html_escape($doclinc_group_label); ?></li>
				<?php foreach ($doclinc_group_items as $doclinc_item): ?>
					<?php $doclinc_item_active = ($doclinc_admin_segment === $doclinc_item['route']); ?>
					<li class="nav-item<?= $doclinc_item_active ? ' active' : ''; ?>">
						<a class="nav-link" href="<?php echo site_url($doclinc_item['route']); ?>"<?= $doclinc_item_active ? ' aria-current="page"' : ''; ?>>
							<i class="fas <?= html_escape($doclinc_item['icon']); ?>" aria-hidden="true"></i>
							<span><?= html_escape($doclinc_item['text']); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			<?php endforeach; ?>

			<li class="text-center d-none d-md-inline">
				<!-- Tombol ini tadinya benar-benar kosong (tanpa teks maupun aria-label),
				     sehingga pembaca layar mengumumkan "tombol" tanpa keterangan.
				     Pembungkusnya dulu <div> di dalam <ul> - tidak sah menurut HTML
				     dan membuat sebagian pembaca layar mengumumkan daftar yang
				     strukturnya rusak. -->
				<button type="button" class="rounded-circle border-0" id="sidebarToggle" aria-label="Sembunyikan atau tampilkan menu samping" aria-controls="accordionSidebar"></button>
			</li>
		</ul>
		<!-- End of Sidebar -->
		<script>
			/* Sidebar tetap lebih tinggi dari layar 900px: empat kelompok penuh
			   tidak muat sekaligus. Tanpa ini, pada halaman yang butirnya jatuh
			   di bawah lipatan
			   (mis. Penampil audit), penanda "halaman ini" tidak terlihat sama
			   sekali - jadi pengguna kehilangan satu-satunya petunjuk posisinya.
			   Yang digeser hanya wadah sidebar, bukan halaman, dan tanpa animasi
			   supaya tidak terasa seperti lompatan. */
			(function() {
				var sidebar = document.getElementById('accordionSidebar');
				if (!sidebar) {
					return;
				}

				var active = sidebar.querySelector('.nav-item.active');
				if (active) {
					/* 120px di atas butir aktif supaya judul kelompoknya ikut terlihat. */
					var offset = active.offsetTop - 120;
					sidebar.scrollTop = offset > 0 ? offset : 0;
				}

				/* Di layar <=768px sidebar adalah panel geser. SB Admin hanya memasang
				   keadaan tertutup di bawah 480px, sedangkan CSS menjadikannya panel
				   geser sampai 768px - jadi antara 480 dan 768px panel itu menutupi
				   isi halaman tanpa cara membukanya. Ambang di sini disamakan dengan
				   CSS, dan keadaan terbuka dibaca dari kelas yang sama. */
				var body = document.body;
				var backdrop = document.getElementById('sidebarBackdrop');
				var narrow = window.matchMedia ? window.matchMedia('(max-width: 768px)') : null;

				function drawerOpen() {
					return body.classList.contains('doclinc-nav-open');
				}

				function sync() {
					var narrowScreen = !!(narrow && narrow.matches);
					var open = narrowScreen
						? !body.classList.contains('sidebar-toggled')
						: !sidebar.classList.contains('toggled');
					body.classList.toggle('doclinc-nav-open', narrowScreen && open);
					if (backdrop) {
						backdrop.hidden = !(narrowScreen && open);
					}
					/* Dicari ulang tiap kali: tombol di topbar baru ada di DOM setelah
					   blok ini dijalankan (skrip ini berada di atas topbar). */
					var toggles = document.querySelectorAll('#sidebarToggle, #sidebarToggleTop');
					Array.prototype.forEach.call(toggles, function(button) {
						button.setAttribute('aria-expanded', open ? 'true' : 'false');
					});
				}

				function close() {
					body.classList.add('sidebar-toggled');
					sidebar.classList.add('toggled');
					sync();
				}

				/* Dua sasaran tutup yang masih terjangkau: latar gelap dan Escape. */
				if (backdrop) {
					backdrop.addEventListener('click', close);
				}
				document.addEventListener('keydown', function(event) {
					if (event.key === 'Escape' && drawerOpen()) {
						close();
					}
				});

				/* Kedua tombol mengubah kelas lewat SB Admin; sinkronkan sesudahnya.
				   Delegasi dipakai supaya tombol yang dirender belakangan tetap ikut. */
				document.addEventListener('click', function(event) {
					var target = event.target;
					if (target && target.closest && target.closest('#sidebarToggle, #sidebarToggleTop')) {
						window.setTimeout(sync, 0);
					}
				});

				if (narrow && narrow.addEventListener) {
					narrow.addEventListener('change', function(event) {
						if (event.matches) {
							close();
						} else {
							sync();
						}
					});
				}
				if (narrow && narrow.matches) {
					close();
				}
				sync();
			})();
		</script>

		<!-- Content Wrapper -->
		<div id="content-wrapper" class="d-flex flex-column doclinc-content-wrapper">
			<div id="content">
				<!-- Topbar -->
				<nav class="navbar navbar-expand navbar-light topbar mb-4 static-top shadow doclinc-topbar">
					<button type="button" id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3" aria-label="Buka menu samping" aria-controls="accordionSidebar" aria-expanded="false">
						<i class="fa fa-bars" aria-hidden="true"></i>
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
							<!-- Tautan ini hanya berisi ikon (badge-nya tersembunyi saat kosong),
							     jadi tanpa aria-label pembaca layar tidak mengumumkan apa pun. -->
							<a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Notifikasi">
								<i class="fas fa-bell fa-lg text-gray-600" aria-hidden="true"></i>
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
							<!-- Nama pengguna disembunyikan di bawah breakpoint lg (d-none d-lg-inline),
							     jadi tanpa aria-label tombol ini tidak punya keterangan apa pun di
							     layar kecil. -->
							<a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Menu akun">
								<img class="img-profile rounded-circle mr-2 doclinc-profile-image" src="<?= html_escape(base_url("assets/images/admin-avatar.svg")); ?>" alt="">
								<span class="d-none d-lg-inline text-gray-700 small font-weight-bold"><?= html_escape($this->session->userdata('username') ?: '') ?></span>
							</a>
							<div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
								<!-- "Profil" dan "Pengaturan" tadinya href="#" — tidak ada halaman
								     maupun route untuk keduanya di panel ini, jadi mengkliknya tidak
								     melakukan apa pun dan hanya menyesatkan. Diganti dengan keterangan
								     akun yang sedang masuk, yang memang informasinya berguna. -->
								<div class="dropdown-item-text doclinc-account-summary">
									<span class="d-block text-muted small">Masuk sebagai</span>
									<strong class="d-block text-truncate"><?= html_escape($this->session->userdata('username') ?: 'Pengguna admin') ?></strong>
								</div>
								<!-- Modal "Ubah password" (#modalPassword di commons/footer.php) sudah
								     lengkap sejak lama dan endpoint Home::change_password() memang ada,
								     tetapi TIDAK ADA satu pun pemicu di seluruh panel — jadi fitur ini
								     tidak bisa diakses sama sekali. Tautan ini memulihkannya. -->
								<button type="button" class="dropdown-item" data-toggle="modal" data-target="#modalPassword">
									<i class="fas fa-key fa-sm fa-fw mr-2 text-gray-400" aria-hidden="true"></i>
									Ubah password
								</button>
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
				<?php
				/* Landmark isi halaman. Tanpa satu pun landmark, pembaca layar tidak
				   punya cara melompat ke isi — setiap rute panel ini sebelumnya
				   hanya div. Dibuka di sini (setelah topbar) dan ditutup di
				   commons/footer.php tepat setelah .container-fluid, supaya footer
				   dan modal tidak ikut masuk landmark isi. */
				?>
				<main id="main-content">

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
									// CI3 query-string routing treats an appended jQuery cache
									// key as part of the route and returns 400.
									cache: true,
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
