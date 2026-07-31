<?php
$google_maps_api_key = $this->config->item('google_maps_api_key') ?: '';
$firebase_enabled = (bool) $this->config->item('firebase_enabled');
$legacy_superapp_url = $this->config->item('legacy_superapp_url') ?: '#';
$map_provider = $this->config->item('map_provider') ?: 'none';
$mapbox_public_token = $this->config->item('mapbox_public_token') ?: '';
$nakes_account_type = isset($nakes_account_type) ? (string) $nakes_account_type : 'unclassified';
$nakes_is_command_center = $nakes_account_type === 'command_center';
$nakes_is_personal = $nakes_account_type === 'personal';
$nakes_is_unclassified = !$nakes_is_command_center && !$nakes_is_personal;
$can_coordinate_staff = !empty($can_coordinate_staff) && $nakes_is_command_center;

if (!function_exists('doclinc_history_safe_text')) {
	function doclinc_history_safe_text($value, $fallback = '-')
	{
		$text = trim((string) $value);
		return html_escape($text !== '' ? $text : $fallback);
	}
}

if (!function_exists('doclinc_history_safe_lines')) {
	function doclinc_history_safe_lines($value, $fallback = '-')
	{
		$text = trim((string) $value);
		return nl2br(html_escape($text !== '' ? $text : $fallback), false);
	}
}

if (!function_exists('doclinc_history_format_complaint')) {
	function doclinc_history_format_complaint($value)
	{
		$text = trim((string) $value);
		if ($text === '') {
			return '<p class="history-empty-text mb-0">Keluhan tersimpan</p>';
		}

		$lines = preg_split('/\R/u', $text);
		$html = '';
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			if (preg_match('/^\*\*(.+?)\*\*$/', $line, $match)) {
				$html .= '<div class="history-complaint-heading">' . html_escape(trim($match[1])) . '</div>';
				continue;
			}

			if (strpos($line, ':') !== false) {
				list($label, $content) = explode(':', $line, 2);
				$html .= '<div class="history-complaint-row">';
				$html .= '<span class="history-complaint-label">' . html_escape(trim($label)) . '</span>';
				$html .= '<span class="history-complaint-value">' . html_escape(trim($content) !== '' ? trim($content) : '-') . '</span>';
				$html .= '</div>';
				continue;
			}

			$html .= '<p class="history-free-text mb-1">' . html_escape($line) . '</p>';
		}

		return $html !== '' ? $html : nl2br(html_escape($text), false);
	}
}

$nakes_name = strtoupper((string) $this->session->userdata('nama'));
$nakes_birthdate = !empty($profile['tgl']) ? $profile['tgl'] : null;
$nakes_age = '-';
if (!empty($nakes_birthdate)) {
	try {
		$birthDate = new DateTime($nakes_birthdate);
		$today = new DateTime();
		$nakes_age = $today->diff($birthDate)->y . ' tahun';
	} catch (Exception $e) {
		$nakes_age = '-';
	}
}
$nakes_pending_count = isset($data_request_new) ? (int) $data_request_new->num_rows() : 0;
$nakes_active_count = isset($data_request_accept) ? (int) $data_request_accept->num_rows() : 0;
$nakes_completed_count = isset($data_request_completed) ? (int) $data_request_completed->num_rows() : 0;
$nakes_today_total = $nakes_pending_count + $nakes_active_count;
$nakes_pending_rows = isset($data_request_new) ? $data_request_new->result() : array();
$nakes_active_rows = isset($data_request_accept) ? $data_request_accept->result() : array();
$nakes_latest_pending_rows = array_slice($nakes_pending_rows, 0, 2);
$nakes_primary_active = !empty($nakes_active_rows) ? $nakes_active_rows[0] : null;

if (!function_exists('doclinc_nakes_queue_badge')) {
	function doclinc_nakes_queue_badge($request)
	{
		$label = doclinc_request_queue_number_label($request);
		if (preg_match('/(\d+)/', (string) $label, $match)) {
			return str_pad(substr($match[1], -2), 2, '0', STR_PAD_LEFT);
		}

		return '00';
	}
}

if (!function_exists('doclinc_nakes_short_text')) {
	function doclinc_nakes_short_text($value, $limit = 92)
	{
		$text = trim(preg_replace('/\s+/', ' ', (string) $value));
		if ($text === '') {
			return 'Keluhan tersimpan';
		}
		if (function_exists('mb_strlen') && mb_strlen($text) > $limit) {
			return mb_substr($text, 0, $limit - 1) . '...';
		}
		if (strlen($text) > $limit) {
			return substr($text, 0, $limit - 1) . '...';
		}

		return $text;
	}
}
?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DocLink - Nakes</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta3/css/bootstrap-select.min.css" integrity="sha512-g2SduJKxa4Lbn3GW+Q7rNz+pKP9AWMR++Ta8fgwsZRCUsawjPvF/BxSMkGS61VsR9yinGoEgrHPGPn2mrj8+4w==" crossorigin="anonymous" referrerpolicy="no-referrer">
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/nakes-dashboard.css">
</head>

<body class="bg-light dl-dashboard-body dl-nakes-dashboard">
	<div id="preloader">
		<div class="text-center">
			<img class="mb-3" src="<?= base_url(); ?>assets/images/doklincwhite.png" alt="" height="50px">
			<p class="mb-0">
			<div class="spinner-border spinner-border-sm text-light" role="status">
				<span class="visually-hidden">Loading...</span>
			</div> Memuat... </p>
		</div>
	</div>
	<div class="content-wrapper dl-shell" id="content-wrapper">
		<div class="contents">
			<?php $this->load->view('partials/nakes_appbar_v', get_defined_vars()); ?>
			<div class="position-relative">
				<?php if ($nakes_is_unclassified) : ?>
					<?php $this->load->view('partials/nakes_access_state_v', get_defined_vars()); ?>
				<?php else : ?>
					<?php $this->load->view('partials/nakes_dashboard_v', get_defined_vars()); ?>
					<?php if ($nakes_is_command_center) : ?>
						<?php $this->load->view('partials/nakes_requests_v', get_defined_vars()); ?>
					<?php endif; ?>
					<?php $this->load->view('partials/nakes_history_v', get_defined_vars()); ?>
				<?php endif; ?>
				<?php $this->load->view('partials/nakes_profile_v', get_defined_vars()); ?>
			</div>
		</div>
	</div>
	<?php $this->load->view('partials/nakes_bottom_nav_v', get_defined_vars()); ?>
	<div class="modal fade nk-profile-modal" id="modalProfil" tabindex="-1" aria-labelledby="modalProfilLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header nk-profile-modal__header">
					<h5 class="modal-title" id="modalProfilLabel"><span><i class="bi bi-pencil-fill"></i></span>Ubah profil</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<form id="formEditProfile" enctype="multipart/form-data">
					<div class="modal-body nk-profile-modal__body">
						<div class="nk-profile-modal__photo">
							<label for="uploadFoto" class="nk-profile-modal__photo-picker">
								<img id="previewFoto" src="<?= doclinc_safe_profile_image_src($profile['foto'] ?? ''); ?>" alt="Foto Profil">
								<input type="file" id="uploadFoto" name="foto" accept="image/*" class="d-none" onchange="previewImage(event)">
							</label>
							<small>Klik untuk mengubah foto</small>
							<div id="profileImageFeedback" class="small text-danger mt-2 d-none" role="alert"></div>
						</div>
						<div class="form-floating nk-profile-modal__field">
							<input type="text" class="form-control" id="nama_lengkap_edit" name="nama_lengkap" value="<?= $this->session->userdata('nama'); ?>" placeholder="Nama Lengkap">
							<label for="nama_lengkap_edit"><i class="bi bi-person-fill me-2"></i>Nama lengkap</label>
						</div>
						<div class="form-floating nk-profile-modal__field">
							<input type="date" class="form-control" id="tgl_edit" name="tgl_lahir" value="<?= $profile['tgl'] ?>" placeholder="Tanggal Lahir">
							<label for="tgl_edit"><i class="bi bi-calendar-event-fill me-2"></i>Tanggal lahir</label>
						</div>
						<div class="form-floating nk-profile-modal__field">
							<select class="form-select" name="jk" id="jk_edit">
								<option value="Laki-laki" <?= $this->session->userdata('jk') == 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
								<option value="Perempuan" <?= $this->session->userdata('jk') == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
							</select>
							<label for="jk_edit"><i class="bi bi-gender-ambiguous me-2"></i>Jenis kelamin</label>
						</div>
						<div class="form-floating nk-profile-modal__field">
							<input type="text" class="form-control" id="no_hp_edit" name="no_hp" value="<?= $profile['no_hp'] ?>" placeholder="Nomor HP">
							<label for="no_hp_edit"><i class="bi bi-telephone-fill me-2"></i>Nomor HP</label>
						</div>
						<div class="form-floating nk-profile-modal__field">
							<textarea class="form-control" placeholder="Alamat" name="alamat" id="alamat_edit"><?= $profile['alamat'] ?></textarea>
							<label for="alamat_edit"><i class="bi bi-geo-alt-fill me-2"></i>Alamat</label>
						</div>
					</div>
					<div class="modal-footer nk-profile-modal__footer">
						<button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="bi bi-x-circle me-2"></i>Tutup</button>
						<button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-2"></i>Simpan</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="offcanvas offcanvas-top nk-notification-panel" tabindex="-1" id="offcanvasNotif" aria-labelledby="offcanvasNotifLabel">
		<div class="offcanvas-header nk-notification-header">
			<div class="nk-notification-title">
				<span class="nk-notification-title-icon"><i class="bi bi-bell-fill"></i></span>
				<span class="offcanvas-title" id="offcanvasNotifLabel">Pusat notifikasi</span>
				<span class="badge text-bg-danger nk-notification-badge" id="badgeNotifs" style="display: none;"></span>
			</div>
			<button type="button" class="btn-close nk-notification-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
		</div>
		<div class="offcanvas-body nk-notification-body">
			<div id="notificationList" class="notification-list nk-notification-list"></div>
		</div>
	</div>
	<div class="offcanvas offcanvas-top" style="height: 100vh;" tabindex="-1" id="offcanvasMapTujuan" aria-labelledby="offcanvasMapTujuanLabel">
		<div class="offcanvas-header bg-success text-white">
			<h5 class="offcanvas-title d-flex align-items-center" id="offcanvasMapTujuanLabel">
				<i class="bi bi-geo-alt-fill me-2 fs-4"></i>Rute Anda ke Pasien
			</h5>
			<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
		</div>
		<div class="offcanvas-body p-0">
			<div id="maps" class="w-100 h-100 position-relative">
				<div id="nakesVisitMapCanvas" class="w-100 h-100"></div>
				<div class="position-absolute top-0 start-0 mt-3 ms-3 bg-white shadow rounded-pill px-4 py-2 d-flex align-items-center" style="z-index: 1000;">
					<i class="bi bi-pin-map-fill text-success me-2 fs-5"></i>
					<span class="text-muted small" id="nakesVisitMapStatus">Menampilkan lokasi pasien...</span>
				</div>
				<div class="visit-map-toolbar">
					<button type="button" class="btn btn-light border" id="nakesVisitRecenter">
						<i class="fas fa-crosshairs me-1"></i> Pusatkan rute
					</button>
					<button type="button" class="btn btn-success" id="nakesVisitRefresh">
						<i class="fas fa-sync-alt me-1"></i> Perbarui
					</button>
				</div>
				<div class="visit-route-card">
					<div class="d-flex justify-content-between align-items-start gap-2 mb-1">
						<div class="visit-route-card-title">Rute Anda ke pasien</div>
						<span class="visit-route-status" id="nakesVisitRouteStatus">Menghitung...</span>
					</div>
					<div class="visit-route-card-row">
						<span>Jarak</span>
						<strong id="nakesVisitRouteDistance">Menghitung...</strong>
					</div>
					<div class="visit-route-card-row">
						<span>Estimasi perjalanan</span>
						<strong id="nakesVisitRouteEta">Menghitung...</strong>
					</div>
					<div class="visit-route-card-row">
						<span>Terakhir diperbarui</span>
						<strong id="nakesVisitRouteUpdated">-</strong>
					</div>
					<div class="visit-route-provider-note mt-1 d-none" id="nakesVisitRouteProviderNote"></div>
					<div class="alert alert-success py-2 px-3 mt-2 mb-0 d-none" id="nakesVisitArrivalNotice">
						<div class="small fw-bold" id="nakesVisitArrivalMessage"></div>
						<button type="button" class="btn btn-success btn-sm rounded-pill mt-2 visit-status-update" id="nakesVisitArrivalButton" data-request-id="" data-visit-status="arrived">
							Konfirmasi tiba di lokasi
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	</div>
	</div>

	<!-- chatting dokter dengan pasien -->
	<div class="modal fade" id="chat" tabindex="-1" aria-labelledby="modalProfilLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h1 class="modal-title fs-5" id="modalProfilLabel">Chat dengan pasien</h1>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>

				<div class="modal-body">
					<div class="form-floating mb-2">
						<input type="hidden" class="form-control shadow border-success" id="username" value="<?= $this->session->userdata('nama'); ?>" placeholder="Nama Lengkap">
						<input type="hidden" id="modal-pasien">
					</div>
					<div class="chat-box" id="chat-box">
						<!-- Pesan akan ditampilkan di sini -->
					</div>
					<div class="message-input">
						<input type="text" id="message" placeholder="Tulis pesan...">
						<button onclick="sendMessage()">Kirim</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!-- end chatting dokter dengan pasien -->

	<?php foreach ($data_request_new->result() as $y => $x) {
		$photo_file = isset($x->photos) ? trim((string) $x->photos) : '';
		$video_file = isset($x->video) ? trim((string) $x->video) : '';
	?>
		<?php if ($photo_file !== '') : ?>
			<!-- Modal Foto -->
			<div class="modal fade" id="fotoModal_<?php echo $x->user_id; ?>" tabindex="-1" aria-labelledby="fotoModalLabel_<?php echo $x->user_id; ?>" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered modal-lg">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title" id="fotoModalLabel_<?php echo $x->user_id; ?>">Pratinjau foto</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
						</div>
						<div class="modal-body text-center">
							<img src="<?php echo html_escape(base_url('uploads/' . rawurlencode($photo_file))); ?>" alt="Foto" class="img-fluid rounded border">
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ($video_file !== '') : ?>
			<!-- Modal Video -->
			<div class="modal fade" id="videoModal_<?php echo $x->user_id; ?>" tabindex="-1" aria-labelledby="videoModalLabel_<?php echo $x->user_id; ?>" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered modal-lg">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title" id="videoModalLabel_<?php echo $x->user_id; ?>">Pratinjau video</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
						</div>
						<div class="modal-body text-center">
							<video controls width="100%" class="rounded border">
								<source src="<?php echo html_escape(base_url('uploads/' . rawurlencode($video_file))); ?>" type="video/mp4">
								Browser Anda tidak mendukung tag video.
							</video>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

	<?php } ?>


	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
	<?php if ($map_provider === 'google' && !empty($google_maps_api_key)) : ?>
		<script src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode($google_maps_api_key); ?>"></script>
	<?php endif; ?>

	<!-- firebase dan notifikasi -->
	<?php if ($firebase_enabled) : ?>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
		<?php if ($legacy_superapp_url !== '#') : ?>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/firebase-config.js'); ?>"></script>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/get-notif.js'); ?>"></script>
		<?php endif; ?>
	<?php endif; ?>

	<!-- Javascript unutk webtoapk dan lain-lain -->
	<script>
		const mapProvider = <?= json_encode($map_provider); ?>;
		const firebaseEnabled = <?= json_encode($firebase_enabled); ?>;
		const wargaPinIconUrl = <?= json_encode(base_url('assets/doclinc_ui/konsultasi_nakes/warga-pin.svg')); ?>;
		const nakesPinIconUrl = <?= json_encode(base_url('assets/doclinc_ui/konsultasi_nakes/nakes-pin.svg')); ?>;

		function getFirebaseDatabase() {
			const noopRef = {
				on: function() {},
				once: function() {
					return Promise.resolve({
						exists: function() {
							return false;
						},
						val: function() {
							return null;
						},
						forEach: function() {}
					});
				},
				child: function() {
					return noopRef;
				},
				remove: function() {
					return Promise.resolve();
				},
				push: function() {
					return noopRef;
				},
				set: function() {
					return Promise.resolve();
				},
				orderByChild: function() {
					return noopRef;
				},
				equalTo: function() {
					return noopRef;
				}
			};

			if (!firebaseEnabled || !window.firebase || !firebase.database) {
				return {
					ref: function() {
						return noopRef;
					},
					ServerValue: {
						TIMESTAMP: Date.now()
					}
				};
			}

			try {
				return firebase.database();
			} catch (error) {
				return {
					ref: function() {
						return noopRef;
					},
					ServerValue: {
						TIMESTAMP: Date.now()
					}
				};
			}
		}

		const db = getFirebaseDatabase();

		function getFirebaseServerTimestamp() {
			return firebaseEnabled && window.firebase && firebase.database && firebase.database.ServerValue ?
				firebase.database.ServerValue.TIMESTAMP :
				Date.now();
		}

		function hasGoogleMaps() {
			return mapProvider === 'google' && window.google && window.google.maps;
		}

		function exitApp() {
			Website2APK.exitApp();
		}
		window.addEventListener('load', function() {
			setTimeout(function() {
				$('#preloader').hide();
				document.getElementById('content-wrapper').style.display = 'block';
				document.getElementById('nav-bottom-wrapper').style.display = 'block';
			}, 1500);
		});
		document.addEventListener('DOMContentLoaded', function() {
			var hash = window.location.hash;
			if (hash) {
				showContent(hash.replace('#', ''));
			}
			var highlightRequestId = new URLSearchParams(window.location.search).get('highlight_request_id');
			if (highlightRequestId) {
				var cards = document.querySelectorAll('[data-request-id]');
				cards.forEach(function(card) {
					if (card.getAttribute('data-request-id') === highlightRequestId) {
						card.classList.add('border', 'border-success', 'border-2');
						card.scrollIntoView({
							behavior: 'smooth',
							block: 'center'
						});
					}
				});
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
				title: "Keluar dari akun?",
				text: "Anda perlu masuk kembali.",
				icon: "warning",
				showCancelButton: true,
				confirmButtonText: "Keluar",
				confirmButtonColor: "#09AD74",
				cancelButtonText: "Batal"
			}).then((result) => {
				if (result.isConfirmed) {
					Swal.fire({
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
		var jumlah_request = $('#jumlah_request').val();
		for (let i = 1; i <= jumlah_request; i++) {
			// alert(i);
			$('#terimaKonsul' + i).click(function(event) {
				const acceptButton = this;
				var id = $('#id_request' + i).val();
				var id_user = $('#id_user').val();
				var latitude = $('#latitude').val();
				var longitude = $('#longitude').val();

				const reqId = this.dataset.reqid;
				const userId = this.dataset.userid;
				const dokterId = this.dataset.dokterid;
				const namaPasien = this.dataset.namapasien;
				const riwayat = this.dataset.riwayat;
				const keluhan = this.dataset.keluhan;
				const namaDokter = "<?= $this->session->userdata('nama') ?>";


				Swal.fire({
					title: "Terima konsultasi?",
					showCancelButton: true,
					confirmButtonText: "Terima konsultasi",
					confirmButtonColor: "#09AD74",
					denyButtonText: "Batal",
					cancelButtonText: "Batal"
				}).then((result) => {
					/* Read more about isConfirmed, isDenied below */
					if (result.isConfirmed) {
						$(acceptButton).prop('disabled', true).addClass('disabled');
						// Hanya kirim AJAX jika user menekan "Iya"
						$.ajax({
							url: '<?php echo base_url(); ?>home_nakes/accept_request',
							dataType: 'json',
							type: 'POST',
							data: {
								id: id,
								id_user: id_user,
								latitude: latitude,
								longitude: longitude
							},
							success: function(response) {
								if (typeof response === 'string') {
									try {
										response = JSON.parse(response);
									} catch (e) {}
								}
								if (!(response == 1 || response === true || (response && response.status === 'success'))) {
									const message = response && response.message ? response.message : 'Terjadi kesalahan. Coba lagi.';
									Swal.fire("Konsultasi belum diterima", message, "error");
									$(acceptButton).prop('disabled', false).removeClass('disabled');
									return;
								}
								const redirectUrl = response && response.redirect_url ? response.redirect_url : `<?= base_url('konsultasi_nakes/konsultasi/'); ?>${reqId}`;

								const runLegacyAcceptNotification = function() {
									try {
										if (typeof db === 'undefined' || !db || typeof db.ref !== 'function') {
											return;
										}

										const newMessageRef = db.ref("notiffromdoc").push();
										newMessageRef.set({
											id_req: id,
											id_user: id_user,
											status: 'Nakes menuju lokasi',
											text: 'Nakes sedang Menyiapkan obat dan Kendaraan',
											timestamp: Date.now()
										});

										const waktuSalam = () => {
											const jam = new Date().getHours();
											if (jam < 11) return "pagi";
											if (jam < 15) return "siang";
											if (jam < 18) return "sore";
											return "malam";
										};

										const salam = waktuSalam();
										const message = `Selamat ${salam} <b>${namaPasien}</b>,<br><br>
					Saya <b>Dr. ${namaDokter}</b>, dokter yang akan menangani Anda berdasarkan permintaan konsultasi sebelumnya.
					<br><br>
					Berdasarkan informasi yang kami terima, Anda mengalami:<br>
					<b>${keluhan}</b>.<br><br>
					<b>Riwayat penyakit</b> : <br>
					<b>${riwayat}</b>
					<br><br>
					Saya siap membantu Anda dengan penanganan medis yang tepat.<br><br>
					Silakan sampaikan pertanyaan atau keluhan yang Anda rasakan saat ini.`;

										const chatData = {
											receiver: reqId,
											sender: namaDokter,
											text: message,
											timestamp: Date.now()
										};

										const chatNotif = {
											receiver: reqId,
											sender: namaDokter,
											userId: userId,
											text: "Ada pesan masuk dari " + namaDokter,
											timestamp: Date.now()
										};

										db.ref("messages")
											.orderByChild("receiver")
											.equalTo(reqId)
											.once("value", snapshot => {
												let alreadySent = false;

												snapshot.forEach(child => {
													const data = child.val();
													if (data.sender === namaDokter && data.text.includes("Saya <b>Dr.")) {
														alreadySent = true;
													}
												});

												if (!alreadySent) {
													db.ref("messages").push(chatData);
													db.ref("notifications").push(chatNotif);
												}
											}).catch(() => {});
									} catch (error) {}
								};

								runLegacyAcceptNotification();

								Swal.fire({
									title: "Konsultasi diterima.",
									text: response && response.message ? response.message : "Konsultasi diterima.",
									icon: "success",
									showConfirmButton: false,
									timer: 800,
									timerProgressBar: true
								});
								window.setTimeout(function() {
									window.location.href = redirectUrl;
								}, 300);
							},
							error: function() {
								Swal.fire("Konsultasi belum diterima", "Terjadi kesalahan. Coba lagi.", "error");
								$(acceptButton).prop('disabled', false).removeClass('disabled');
							}
						});
					}
				});
			});
		}
		var jumlah_accepted = $('#jumlah_accepted').val();
		for (let i = 1; i <= jumlah_accepted; i++) {
			$('#tombolSaran' + i).click(function(event) {
				Swal.fire({
					title: "Selesai memeriksa?",
					text: "Apakah Anda sudah memeriksa pasien? Berikan saran Anda untuk pasien.",
					input: "text",
					showCancelButton: true,
					confirmButtonText: "Selesai",
					confirmButtonColor: "#09AD74",
					denyButtonText: "Batal"
				}).then((result) => {
					/* Read more about isConfirmed, isDenied below */
					if (result.isConfirmed) {
						Swal.fire({
							title: "Berhasil!",
							text: "Anda telah menyelesaikan pemeriksaan pasien",
							icon: "success",
							showConfirmButton: false,
							timer: 2000,
							timerProgressBar: true
						}).then((result) => {
							if (result.dismiss === Swal.DismissReason.timer) {
								window.location.href = 'home_nakes';
							}
						});
					}
				});
			});
		}

		$(document).on('click', '.cancel-nakes-request', function(event) {
			event.preventDefault();
			event.stopPropagation();

			const button = $(this);
			const requestId = button.data('request-id');
			if (!requestId) {
				Swal.fire('Gagal', 'Permintaan tidak ditemukan.', 'error');
				return;
			}

			Swal.fire({
				title: 'Batalkan permintaan?',
				text: 'Permintaan yang dibatalkan tidak dapat dilanjutkan.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Batalkan',
				confirmButtonColor: '#dc3545',
				cancelButtonText: 'Batal'
			}).then((result) => {
				if (!result.isConfirmed) {
					return;
				}

				button.prop('disabled', true).addClass('disabled');
				$.ajax({
					url: '<?= base_url('home_nakes/cancel_request'); ?>',
					type: 'POST',
					dataType: 'json',
					data: {
						request_id: requestId
					},
					success: function(response) {
						if (typeof response === 'string') {
							try {
								response = JSON.parse(response);
							} catch (error) {}
						}
						if (response && response.status === 'success') {
							Swal.fire({
								title: 'Permintaan dibatalkan.',
								text: response.message || 'Permintaan dibatalkan.',
								icon: 'success',
								showConfirmButton: false,
								timer: 1200,
								timerProgressBar: true
							}).then(() => {
								window.location.reload();
							});
							return;
						}

						button.prop('disabled', false).removeClass('disabled');
						Swal.fire('Permintaan belum dibatalkan', response && response.message ? response.message : 'Terjadi kesalahan. Coba lagi.', 'error');
					},
					error: function(xhr) {
						button.prop('disabled', false).removeClass('disabled');
						const response = xhr.responseJSON || {};
						Swal.fire('Permintaan belum dibatalkan', response.message || 'Terjadi kesalahan. Coba lagi.', 'error');
					}
				});
			});
		});
	</script>

	<script>
		(function(window, $) {
			const ns = window.doclincVisitTracking = window.doclincVisitTracking || {};
			const updateVisitLocationUrl = <?= json_encode(base_url('home_nakes/update_visit_location')); ?>;
			const nakesVisitLocationUrl = <?= json_encode(base_url('home_nakes/visit_location')); ?>;
			const updateVisitStatusUrl = <?= json_encode(base_url('home_nakes/update_visit_status')); ?>;
			const visitMapboxToken = <?= json_encode($mapbox_public_token); ?>;
			const minPostIntervalMs = <?= json_encode((function_exists('doclinc_visit_location_min_interval_seconds') ? doclinc_visit_location_min_interval_seconds() : 5) * 1000); ?>;
			const watches = ns.watches = ns.watches || {};
			const mapState = ns.nakesMapState = ns.nakesMapState || {
				leaflet: null,
				leafletMarkers: {},
				leafletRouteOutlineLine: null,
				leafletRouteLine: null,
				google: null,
				googleMarkers: {},
				googleRouteOutlineLine: null,
				googleRouteLine: null,
				lastLeafletBounds: null,
				lastGoogleBounds: null,
				currentRequestId: null,
				loadedRequests: {},
				fitModes: {}
			};
			const nextVisitStatuses = {
				not_started: 'en_route',
				en_route: 'arrived',
				arrived: 'in_service',
				in_service: 'completed',
				completed: ''
			};

			function setTrackingStatus(requestId, message, isError) {
				const element = document.querySelector('[data-tracking-status="' + requestId + '"]');
				if (!element) return;
				element.textContent = message || '';
				element.classList.toggle('text-danger', !!isError);
				element.classList.toggle('text-success', !isError && !!message);
			}

			function setVisitWorkflowMessage(requestId, message, isError) {
				const element = document.querySelector('[data-visit-workflow-message="' + requestId + '"]');
				if (!element) return;
				element.textContent = message || '';
				element.classList.toggle('text-danger', !!isError);
				element.classList.toggle('text-success', !isError && !!message);
			}

			function setRouteText(requestId, route) {
				const distanceElement = document.querySelector('[data-route-distance="' + requestId + '"]');
				const etaElement = document.querySelector('[data-route-eta="' + requestId + '"]');
				const providerNoteElement = document.querySelector('[data-route-provider-note="' + requestId + '"]');
				const distance = route && route.distance_text ? route.distance_text : 'Menghitung...';
				const eta = route && route.eta_text ? route.eta_text : 'Menghitung...';
				const isValhallaRoute = !!(route && route.provider === 'valhalla');
				if (distanceElement) distanceElement.textContent = distance;
				if (etaElement) etaElement.textContent = eta;
				if (providerNoteElement) {
					providerNoteElement.textContent = isValhallaRoute ? 'Estimasi berdasarkan rute jalan' : '';
					providerNoteElement.classList.toggle('d-none', !isValhallaRoute);
				}
			}

			function setTextById(id, text) {
				const element = document.getElementById(id);
				if (element) element.textContent = text;
			}

			function setNakesArrivalNotice(requestId, arrival) {
				const shouldPrompt = !!(arrival && arrival.should_prompt_arrival);
				const message = shouldPrompt ? (arrival.message || 'Anda sudah dekat dengan lokasi pasien. Konfirmasi tiba di lokasi.') : '';
				const inlineNotice = document.querySelector('[data-arrival-notice="' + requestId + '"]');
				const inlineMessage = document.querySelector('[data-arrival-message="' + requestId + '"]');
				if (inlineNotice) inlineNotice.classList.toggle('d-none', !shouldPrompt);
				if (inlineMessage) inlineMessage.textContent = message;

				const panelNotice = document.getElementById('nakesVisitArrivalNotice');
				const panelMessage = document.getElementById('nakesVisitArrivalMessage');
				const panelButton = document.getElementById('nakesVisitArrivalButton');
				if (panelNotice) panelNotice.classList.toggle('d-none', !shouldPrompt);
				if (panelMessage) panelMessage.textContent = message;
				if (panelButton && requestId) panelButton.setAttribute('data-request-id', requestId);
			}

			function setNakesRouteSummary(response, patientLocation, nakesLocation) {
				const route = response && response.route ? response.route : {};
				const hasDistance = !!(route && (route.distance_text || route.eta_text));
				const hasGeometry = !!(route && route.geometry && (route.geometry.type === 'polyline6' || route.geometry.type === 'LineString'));
				const patientAvailable = !!patientLocation;
				const nakesAvailable = !!nakesLocation;
				const isValhallaRoute = !!(route && route.provider === 'valhalla');
				let statusText = 'Menghitung...';

				if (!patientAvailable) {
					statusText = 'Lokasi pasien belum tersedia';
				} else if (!nakesAvailable) {
					statusText = 'Menunggu lokasi nakes';
				} else if (isValhallaRoute) {
					statusText = 'Estimasi berdasarkan rute jalan';
				} else if (!hasGeometry && hasDistance) {
					statusText = 'Rute dihitung';
				}

				setTextById('nakesVisitRouteDistance', route && route.distance_text ? route.distance_text : 'Menghitung...');
				setTextById('nakesVisitRouteEta', route && route.eta_text ? route.eta_text : 'Menghitung...');
				setTextById('nakesVisitRouteUpdated', route && route.calculated_at ? route.calculated_at : (response && response.status ? new Date().toLocaleString('id-ID') : '-'));
				setTextById('nakesVisitRouteStatus', statusText);
				const providerNoteElement = document.getElementById('nakesVisitRouteProviderNote');
				if (providerNoteElement) {
					providerNoteElement.textContent = isValhallaRoute ? 'Estimasi berdasarkan rute jalan' : '';
					providerNoteElement.classList.toggle('d-none', !isValhallaRoute);
				}
			}

			function parseLocation(location) {
				if (!location || location.available === false) return null;
				const lat = parseFloat(location.latitude);
				const lng = parseFloat(location.longitude);
				if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
				return {
					latitude: lat,
					longitude: lng
				};
			}

			function inlinePatientLocation(requestId) {
				const card = document.querySelector('[data-visit-request-id="' + requestId + '"]');
				if (!card) return null;
				const lat = parseFloat(card.getAttribute('data-patient-lat'));
				const lng = parseFloat(card.getAttribute('data-patient-lng'));
				if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
				return {
					latitude: lat,
					longitude: lng
				};
			}

			function setMapStatus(message, isError) {
				const element = document.getElementById('nakesVisitMapStatus');
				if (!element) return;
				element.textContent = message || '';
				element.classList.toggle('text-danger', !!isError);
				element.classList.toggle('text-muted', !isError);
			}

			function hasLoadedVisitMap(requestId) {
				return !!(requestId && mapState.loadedRequests && mapState.loadedRequests[requestId]);
			}

			function visitLatLng(location) {
				return [location.latitude, location.longitude];
			}

			function routeLatLngs(route) {
				if (!route || !route.geometry || !Array.isArray(route.geometry.coordinates)) {
					return [];
				}
				const isPolyline6 = route.geometry.type === 'polyline6';
				const isLineString = route.geometry.type === 'LineString';
				if (!isPolyline6 && !isLineString) return [];
				return route.geometry.coordinates
					.map(function(coordinate) {
						if (!Array.isArray(coordinate) || coordinate.length < 2) return null;
						const lat = parseFloat(isPolyline6 ? coordinate[0] : coordinate[1]);
						const lng = parseFloat(isPolyline6 ? coordinate[1] : coordinate[0]);
						if (!Number.isFinite(lat) || !Number.isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
						return [lat, lng];
					})
					.filter(Boolean);
			}

			function routeGooglePath(route) {
				return routeLatLngs(route).map(function(latLng) {
					return {
						lat: latLng[0],
						lng: latLng[1]
					};
				});
			}

			function leafletPinIcon(type) {
				return L.icon({
					iconUrl: type === 'nakes' ? nakesPinIconUrl : wargaPinIconUrl,
					iconSize: [44, 44],
					iconAnchor: [22, 44],
					popupAnchor: [0, -40]
				});
			}

			function googlePinIcon(type) {
				if (!hasGoogleMaps()) return null;
				return {
					url: type === 'nakes' ? nakesPinIconUrl : wargaPinIconUrl,
					scaledSize: new google.maps.Size(44, 44),
					anchor: new google.maps.Point(22, 44)
				};
			}

			function clearNakesRouteLayers() {
				if (mapState.leaflet && mapState.leafletRouteOutlineLine) {
					mapState.leaflet.removeLayer(mapState.leafletRouteOutlineLine);
					mapState.leafletRouteOutlineLine = null;
				}
				if (mapState.leaflet && mapState.leafletRouteLine) {
					mapState.leaflet.removeLayer(mapState.leafletRouteLine);
					mapState.leafletRouteLine = null;
				}
				if (mapState.googleRouteOutlineLine) {
					mapState.googleRouteOutlineLine.setMap(null);
					mapState.googleRouteOutlineLine = null;
				}
				if (mapState.googleRouteLine) {
					mapState.googleRouteLine.setMap(null);
					mapState.googleRouteLine = null;
				}
			}

			function renderLeafletMap(patientLocation, nakesLocation, route, shouldFitBounds) {
				const container = document.getElementById('nakesVisitMapCanvas') || document.getElementById('maps');
				if (!container || !window.L) return false;
				const center = nakesLocation || patientLocation || {
					latitude: -6.0176,
					longitude: 106.0530
				};

				if (!mapState.leaflet) {
					mapState.leaflet = L.map(container).setView(visitLatLng(center), 14);
					const tileUrl = visitMapboxToken ?
						'https://api.mapbox.com/styles/v1/mapbox/streets-v11/tiles/{z}/{x}/{y}?access_token=' + encodeURIComponent(visitMapboxToken) :
						'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
					L.tileLayer(tileUrl, {
						maxZoom: 19,
						tileSize: visitMapboxToken ? 512 : 256,
						zoomOffset: visitMapboxToken ? -1 : 0,
						attribution: visitMapboxToken ? '&copy; OpenStreetMap contributors &copy; Mapbox' : '&copy; OpenStreetMap contributors'
					}).addTo(mapState.leaflet);
				}

				const bounds = [];
				function upsertMarker(name, location, label) {
					if (!location) return;
					const latLng = visitLatLng(location);
					if (!mapState.leafletMarkers[name]) {
						mapState.leafletMarkers[name] = L.marker(latLng, {
							icon: leafletPinIcon(name)
						}).addTo(mapState.leaflet).bindPopup(label);
					} else {
						mapState.leafletMarkers[name].setLatLng(latLng);
					}
					bounds.push(latLng);
				}

				upsertMarker('patient', patientLocation, 'Pasien');
				upsertMarker('nakes', nakesLocation, 'Nakes');
				if (mapState.leafletRouteOutlineLine) {
					mapState.leaflet.removeLayer(mapState.leafletRouteOutlineLine);
					mapState.leafletRouteOutlineLine = null;
				}
				if (mapState.leafletRouteLine) {
					mapState.leaflet.removeLayer(mapState.leafletRouteLine);
					mapState.leafletRouteLine = null;
				}
				const routePoints = routeLatLngs(route);
				if (routePoints.length > 1) {
					mapState.leafletRouteOutlineLine = L.polyline(routePoints, {
						weight: 10,
						opacity: 0.32,
						color: '#052e1f',
						lineCap: 'round',
						lineJoin: 'round'
					}).addTo(mapState.leaflet);
					mapState.leafletRouteLine = L.polyline(routePoints, {
						weight: 6,
						opacity: 0.95,
						color: '#09AD74',
						lineCap: 'round',
						lineJoin: 'round'
					}).addTo(mapState.leaflet);
					routePoints.forEach(function(latLng) {
						bounds.push(latLng);
					});
				}
				mapState.lastLeafletBounds = bounds.slice();
				if (shouldFitBounds && bounds.length > 1) {
					mapState.leaflet.fitBounds(bounds, {
						padding: [58, 58],
						maxZoom: 17
					});
				} else if (shouldFitBounds && bounds.length === 1) {
					mapState.leaflet.setView(bounds[0], Math.max(mapState.leaflet.getZoom(), 14));
				}
				setTimeout(function() {
					mapState.leaflet.invalidateSize();
				}, 100);
				return true;
			}

			function renderGoogleMap(patientLocation, nakesLocation, route, shouldFitBounds) {
				if (!hasGoogleMaps()) return false;
				const container = document.getElementById('nakesVisitMapCanvas') || document.getElementById('maps');
				if (!container) return false;
				const center = nakesLocation || patientLocation || {
					latitude: -6.0176,
					longitude: 106.0530
				};
				if (!mapState.google) {
					mapState.google = new google.maps.Map(container, {
						zoom: 14,
						center: {
							lat: center.latitude,
							lng: center.longitude
						}
					});
				}

				const bounds = new google.maps.LatLngBounds();
				function upsertMarker(name, location, label) {
					if (!location) return;
					const latLng = new google.maps.LatLng(location.latitude, location.longitude);
					if (!mapState.googleMarkers[name]) {
						mapState.googleMarkers[name] = new google.maps.Marker({
							map: mapState.google,
							position: latLng,
							title: label,
							icon: googlePinIcon(name)
						});
					} else {
						mapState.googleMarkers[name].setPosition(latLng);
						mapState.googleMarkers[name].setIcon(googlePinIcon(name));
					}
					bounds.extend(latLng);
				}

				upsertMarker('patient', patientLocation, 'Pasien');
				upsertMarker('nakes', nakesLocation, 'Nakes');
				if (mapState.googleRouteOutlineLine) {
					mapState.googleRouteOutlineLine.setMap(null);
					mapState.googleRouteOutlineLine = null;
				}
				if (mapState.googleRouteLine) {
					mapState.googleRouteLine.setMap(null);
					mapState.googleRouteLine = null;
				}
				const routePath = routeGooglePath(route);
				if (routePath.length > 1) {
					mapState.googleRouteOutlineLine = new google.maps.Polyline({
						path: routePath,
						strokeWeight: 10,
						strokeOpacity: 0.32,
						strokeColor: '#052e1f',
						map: mapState.google
					});
					mapState.googleRouteLine = new google.maps.Polyline({
						path: routePath,
						strokeWeight: 6,
						strokeOpacity: 0.95,
						strokeColor: '#09AD74',
						map: mapState.google
					});
					routePath.forEach(function(latLng) {
						bounds.extend(latLng);
					});
				}
				mapState.lastGoogleBounds = bounds;
				if (shouldFitBounds && patientLocation && nakesLocation) {
					mapState.google.fitBounds(bounds);
				} else if (shouldFitBounds && (patientLocation || nakesLocation)) {
					mapState.google.setCenter(bounds.getCenter());
					mapState.google.setZoom(14);
				}
				return true;
			}

			function renderVisitMap(requestId, response, fallbackPatientLocation) {
				const patientLocation = parseLocation(response && response.patient) || fallbackPatientLocation || null;
				const nakesLocation = parseLocation(response && response.nakes);
				setNakesRouteSummary(response || {}, patientLocation, nakesLocation);
				setRouteText(requestId, response && response.route ? response.route : null);
				setNakesArrivalNotice(requestId, response && response.arrival ? response.arrival : null);

				if (!patientLocation) {
					setMapStatus('Lokasi pasien belum tersedia', true);
					return;
				}
				if (!nakesLocation) {
					setMapStatus('Aktifkan lokasi untuk menghitung jarak');
				} else {
					setMapStatus(response && response.message ? response.message : 'OK');
				}

				const route = response && response.route ? response.route : null;
				const routeHasGeometry = routeLatLngs(route).length > 1;
				const currentFitMode = requestId && mapState.fitModes ? (mapState.fitModes[requestId] || '') : '';
				const shouldFitBounds = !requestId || !mapState.loadedRequests[requestId] || (routeHasGeometry && currentFitMode !== 'route');
				const rendered = renderGoogleMap(patientLocation, nakesLocation, route, shouldFitBounds) || renderLeafletMap(patientLocation, nakesLocation, route, shouldFitBounds);
				if (!rendered) {
					setMapStatus('Peta belum tersedia', true);
					return false;
				}
				if (requestId) {
					mapState.loadedRequests[requestId] = true;
					mapState.fitModes[requestId] = routeHasGeometry ? 'route' : (currentFitMode || 'markers');
					mapState.currentRequestId = requestId;
				}
				return true;
			}

			function fetchNakesVisitLocation(requestId, updateDeviceLocation) {
				if (updateDeviceLocation === undefined) {
					updateDeviceLocation = true;
				}
				const fallbackPatientLocation = inlinePatientLocation(requestId);
				document.body.setAttribute('data-current-visit-request-id', requestId);
				mapState.currentRequestId = requestId;
				setMapStatus('Memuat lokasi pasien...');
				$.ajax({
					url: nakesVisitLocationUrl,
					type: 'GET',
					dataType: 'json',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest'
					},
					data: {
						request_id: requestId
					},
					success: function(response) {
						if (typeof response === 'string') {
							try {
								response = JSON.parse(response);
							} catch (error) {}
						}
						if (response && response.status) {
							if (response.tracking_active === false) {
								stopTracking(requestId);
								clearNakesRouteLayers();
								setNakesArrivalNotice(requestId, null);
								setRouteText(requestId, null);
								setMapStatus(response.message || 'Pelacakan kunjungan selesai.');
								setTrackingStatus(requestId, response.message || 'Pelacakan kunjungan selesai.');
								return;
							}
							renderVisitMap(requestId, response, fallbackPatientLocation);
							if (updateDeviceLocation) {
								refreshDeviceLocation(requestId);
							}
							return;
						}
						renderVisitMap(requestId, response || {}, fallbackPatientLocation);
						if (!fallbackPatientLocation) {
							setMapStatus(response && response.message ? response.message : 'Lokasi pasien belum tersedia', true);
						}
					},
					error: function(xhr) {
						const response = xhr.responseJSON || {};
						if (xhr.status === 403) {
							setMapStatus('Anda tidak memiliki akses.', true);
							return;
						}
						if (fallbackPatientLocation) {
							renderVisitMap(requestId, {}, fallbackPatientLocation);
						} else {
							setMapStatus(response.message || 'Data peta belum dapat dimuat. Coba lagi.', true);
						}
					}
				});
			}

			function refreshDeviceLocation(requestId) {
				if (!navigator.geolocation || !requestId) {
					return;
				}
				navigator.geolocation.getCurrentPosition(
					function(position) {
						postVisitLocation(requestId, position, true);
					},
					function(error) {
						const message = error && error.code === error.PERMISSION_DENIED ?
							'Izinkan akses lokasi di perangkat Anda.' :
							'Lokasi belum ditemukan. Coba lagi.';
						setTextById('nakesVisitRouteStatus', 'Lokasi belum ditemukan. Coba lagi.');
						if (hasLoadedVisitMap(requestId)) {
							setTrackingStatus(requestId, message, true);
						} else {
							setMapStatus(message, true);
						}
					}, {
						enableHighAccuracy: true,
						maximumAge: 30000,
						timeout: 8000
					}
				);
			}

			function refreshVisitWorkflowControl(requestId, visitStatus, label) {
				const container = document.querySelector('[data-visit-workflow="' + requestId + '"]');
				if (!container) return;
				container.setAttribute('data-current-status', visitStatus);
				const labelElement = container.querySelector('.visit-workflow-label');
				if (labelElement) {
					const visitStatusLabels = {
						not_started: 'Belum dimulai',
						en_route: 'Dalam perjalanan',
						arrived: 'Sudah tiba',
						in_service: 'Sedang ditangani',
						completed: 'Selesai'
					};
					labelElement.textContent = label || visitStatusLabels[visitStatus] || 'Status belum tersedia';
				}
				const nextStatus = nextVisitStatuses[visitStatus] || '';
				container.querySelectorAll('.visit-status-update').forEach(function(button) {
					button.disabled = button.getAttribute('data-visit-status') !== nextStatus;
				});
			}

			function stopTracking(requestId) {
				const state = watches[requestId];
				if (state && navigator.geolocation && state.watchId !== null) {
					navigator.geolocation.clearWatch(state.watchId);
				}
				delete watches[requestId];
			}

			function locationMetaValue(value) {
				const number = parseFloat(value);
				return Number.isFinite(number) ? number : '';
			}

			function postVisitLocation(requestId, position, forceSend) {
				const state = watches[requestId];
				if (!state && !forceSend) return;
				if (!position || !position.coords) {
					setTrackingStatus(requestId, 'Lokasi belum ditemukan. Coba lagi.', true);
					return;
				}

				const now = Date.now();
				if (state) {
					if (now - state.lastSentAt < minPostIntervalMs) return;
					state.lastSentAt = now;
				}

				$.ajax({
					url: updateVisitLocationUrl,
					type: 'POST',
					dataType: 'json',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest'
					},
					data: {
						request_id: requestId,
						latitude: position.coords.latitude,
						longitude: position.coords.longitude,
						accuracy_m: locationMetaValue(position.coords.accuracy),
						heading: locationMetaValue(position.coords.heading),
						speed_mps: locationMetaValue(position.coords.speed)
					},
					success: function(response) {
						if (typeof response === 'string') {
							try {
								response = JSON.parse(response);
							} catch (error) {}
						}

						if (response && response.tracking_active === false) {
							stopTracking(requestId);
							clearNakesRouteLayers();
							setNakesArrivalNotice(requestId, null);
							setTrackingStatus(requestId, response.message || 'Pelacakan kunjungan selesai.');
							return;
						}

						if (response && response.status === 'success') {
							setRouteText(requestId, response.route);
							renderVisitMap(requestId, response, inlinePatientLocation(requestId));
							setTrackingStatus(requestId, 'Lokasi berhasil diperbarui');
							if (response.request_status && response.request_status !== 'Accepted') {
								stopTracking(requestId);
							}
							if (response.visit_status === 'completed') {
								stopTracking(requestId);
							}
							return;
						}

						setTrackingStatus(requestId, response && response.message ? response.message : 'Gagal mengirim lokasi', true);
					},
					error: function(xhr) {
						const response = xhr.responseJSON || {};
						if (hasLoadedVisitMap(requestId)) {
							setTrackingStatus(requestId, xhr.status === 403 ? 'Aktifkan lokasi perangkat untuk memperbarui posisi Anda' : (response.message || 'Gagal memperbarui lokasi perangkat'), true);
						} else {
							setTrackingStatus(requestId, response.message || 'Gagal mengirim lokasi', true);
						}
						if (xhr.status === 400 || xhr.status === 403 || xhr.status === 404 || xhr.status === 405) {
							stopTracking(requestId);
						}
					}
				});
			}

			ns.startNakesVisitTracking = function(requestId) {
				if (!navigator.geolocation) {
					setTrackingStatus(requestId, 'Fitur lokasi tidak tersedia di perangkat ini.', true);
					return;
				}

				if (watches[requestId]) {
					setTrackingStatus(requestId, 'Pelacakan kunjungan aktif.');
					return;
				}

				setTrackingStatus(requestId, 'Pelacakan kunjungan aktif.');
				const watchId = navigator.geolocation.watchPosition(
					function(position) {
						postVisitLocation(requestId, position);
					},
					function(error) {
						const message = error && error.code === 1 ? 'Izinkan akses lokasi di perangkat Anda.' : 'Lokasi belum ditemukan. Coba lagi.';
						setTrackingStatus(requestId, message, true);
						stopTracking(requestId);
					}, {
						enableHighAccuracy: true,
						maximumAge: 30000,
						timeout: 10000
					}
				);

				watches[requestId] = {
					watchId: watchId,
					lastSentAt: 0
				};
			};

			$(document).on('click', '.start-nakes-visit-tracking', function(event) {
				event.preventDefault();
				const requestId = $(this).data('request-id');
				if (!requestId) return;
				ns.startNakesVisitTracking(requestId);
			});

			$(document).on('click', '.lihat-map', function() {
				const requestId = $(this).data('request-id');
				const lat = parseFloat($(this).data('lat'));
				const lng = parseFloat($(this).data('lng'));
				if (requestId) {
					fetchNakesVisitLocation(requestId);
					return;
				}
				renderVisitMap('', {}, Number.isFinite(lat) && Number.isFinite(lng) ? {
					latitude: lat,
					longitude: lng
				} : null);
			});

			$(document).on('click', '#nakesVisitRecenter', function(event) {
				event.preventDefault();
				if (mapState.leaflet && mapState.lastLeafletBounds && mapState.lastLeafletBounds.length) {
					mapState.leaflet.invalidateSize();
					if (mapState.lastLeafletBounds.length > 1) {
						mapState.leaflet.fitBounds(mapState.lastLeafletBounds, {
							padding: [58, 58],
							maxZoom: 17
						});
					} else {
						mapState.leaflet.setView(mapState.lastLeafletBounds[0], Math.max(mapState.leaflet.getZoom(), 14));
					}
					return;
				}
				if (mapState.google && mapState.lastGoogleBounds) {
					mapState.google.fitBounds(mapState.lastGoogleBounds);
				}
			});

			$(document).on('click', '#nakesVisitRefresh', function(event) {
				event.preventDefault();
				const requestId = mapState.currentRequestId || document.body.getAttribute('data-current-visit-request-id');
				if (!requestId) return;
				fetchNakesVisitLocation(requestId, false);
			});

			const nakesMapOffcanvas = document.getElementById('offcanvasMapTujuan');
			if (nakesMapOffcanvas) {
				nakesMapOffcanvas.addEventListener('shown.bs.offcanvas', function() {
					if (mapState.leaflet) {
						mapState.leaflet.invalidateSize();
					}
					const requestId = mapState.currentRequestId || document.body.getAttribute('data-current-visit-request-id');
					if (requestId) {
						$('#nakesVisitRecenter').trigger('click');
					}
				});
			}

			$(document).on('click', '.visit-status-update', function(event) {
				event.preventDefault();
				const button = $(this);
				const requestId = button.data('request-id');
				const visitStatus = button.data('visit-status');
				if (!requestId || !visitStatus) return;

				button.prop('disabled', true);
				setVisitWorkflowMessage(requestId, 'Memperbarui status kunjungan...');
				$.ajax({
					url: updateVisitStatusUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						request_id: requestId,
						visit_status: visitStatus
					},
					success: function(response) {
						if (typeof response === 'string') {
							try {
								response = JSON.parse(response);
							} catch (error) {}
						}

						if (response && response.status === 'success') {
							refreshVisitWorkflowControl(requestId, response.visit_status, response.visit_status_label);
							setNakesArrivalNotice(requestId, null);
							if (response.visit_status === 'completed') {
								stopTracking(requestId);
							}
							setVisitWorkflowMessage(requestId, response.message || 'Status kunjungan diperbarui');
							return;
						}

						button.prop('disabled', false);
						setVisitWorkflowMessage(requestId, response && response.message ? response.message : 'Terjadi kesalahan. Coba lagi.', true);
					},
					error: function(xhr) {
						const response = xhr.responseJSON || {};
						button.prop('disabled', false);
						setVisitWorkflowMessage(requestId, response.message || 'Terjadi kesalahan. Coba lagi.', true);
					}
				});
			});

			$(window).on('beforeunload', function() {
				Object.keys(watches).forEach(stopTracking);
			});
		})(window, jQuery);
	</script>

	<script>
		const userName = '<?= $_SESSION['username'] ?>';
	</script>

	<!-- maps -->
	<script>
		let map, userMarker, destinationMarker, userLocation;
		let geocoder, directionsService, directionsRenderer;
		let firstLoad = true;

		function initMap() {
			if (!hasGoogleMaps()) return;

			map = new google.maps.Map(document.getElementById("nakesVisitMapCanvas") || document.getElementById("maps"), {
				zoom: 12,
				center: {
					lat: -6.1751,
					lng: 106.8650
				}, // Lokasi default sebelum mendapatkan posisi pengguna
			});

			userMarker = new google.maps.Marker({
				map: map,
				icon: {
					url: nakesPinIconUrl,
					scaledSize: new google.maps.Size(44, 44),
					anchor: new google.maps.Point(22, 44)
				}
			});

			destinationMarker = new google.maps.Marker({
				map: map,
				icon: {
					url: wargaPinIconUrl,
					scaledSize: new google.maps.Size(44, 44),
					anchor: new google.maps.Point(22, 44)
				}
			});

			geocoder = new google.maps.Geocoder();
			directionsService = new google.maps.DirectionsService();
			directionsRenderer = new google.maps.DirectionsRenderer({
				map: map
			});

			if (navigator.geolocation) {
				navigator.geolocation.getCurrentPosition((position) => {
					userLocation = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
					map.setCenter(userLocation);
					userMarker.setPosition(userLocation);
					// Set tujuan awal berdasarkan lokasi pengguna
					if (firstLoad) {
						updateDestination(userLocation.lat(), userLocation.lng());
						firstLoad = false;
					}
				}, showError);

				navigator.geolocation.watchPosition(updateLocation, showError);
			} else {
				alert("Fitur lokasi tidak tersedia di perangkat ini.");
			}

			document.querySelectorAll(".lihat-map").forEach(button => {
				button.addEventListener("click", function() {
					const lat = parseFloat(this.getAttribute("data-lat"));
					const lng = parseFloat(this.getAttribute("data-lng"));
					updateDestination(lat, lng);
				});
			});
		}

		function updateLocation(position) {
			if (!hasGoogleMaps()) return;

			userLocation = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);

			userMarker.setPosition(userLocation);
			map.setCenter(userLocation);

			const newLocation = {
				lat: position.coords.latitude,
				lng: position.coords.longitude,
			};

			document.getElementById("latitude").value = newLocation.lat;
			document.getElementById("longitude").value = newLocation.lng;

			saveLocationToFirebase(position.coords.latitude, position.coords.longitude);

			getAddress(userLocation);
			hitungJarak(destinationMarker.getPosition());
			hitungJarakAcc(destinationMarker.getPosition());
		}

		function saveLocationToFirebases(lat, lng) {
			const userId = document.getElementById("username").value;
			const location = db.ref('location').push();
			location.set({
				userId: userId,
				latitude: lat,
				longitude: lng,
				timestamp: getFirebaseServerTimestamp(),
			});
		}

		function saveLocationToFirebase(lat, lng) {
			const userName = '<?= $_SESSION['id'] ?>';
			const userId = document.getElementById("username").value;
			const locationRef = db.ref('location/' + userName);

			locationRef.once('value').then((snapshot) => {
				if (snapshot.exists()) {
				} else {
				}
				locationRef.set({
					userId: userId,
					latitude: lat,
					longitude: lng,
					timestamp: getFirebaseServerTimestamp(),
				});
			});
		}


		function getAddress(location) {
			if (!hasGoogleMaps() || !geocoder) return;

			geocoder.geocode({
				location: location
			}, (results, status) => {
				if (status === "OK") {
					if (results[0]) {
						document.getElementById("address").value = results[0].formatted_address;
						document.getElementById("address_label").innerText = results[0].formatted_address;
					}
				}
			});
		}

		function updateDestination(lat, lng) {
			if (!hasGoogleMaps() || !destinationMarker || !Number.isFinite(lat) || !Number.isFinite(lng)) return;

			const destination = new google.maps.LatLng(lat, lng);
			destinationMarker.setPosition(destination);
			// hitungJarak(destination);
			calculateRoute(destination);
			hitungJarakAcc();
		}

		function hitungJarak() {
			if (!hasGoogleMaps() || !userLocation) return;

			const service = new google.maps.DistanceMatrixService();

			document.querySelectorAll(".request-card").forEach((card) => {
				const lat = parseFloat(card.dataset.lat);
				const lng = parseFloat(card.dataset.lng);

				const request = {
					origins: [userLocation], // Lokasi dokter
					destinations: [{
						lat,
						lng
					}], // Lokasi pasien
					travelMode: google.maps.TravelMode.DRIVING
				};

				service.getDistanceMatrix(request, function(response, status) {
					if (status === "OK") {
						const result = response.rows[0].elements[0];

						// Update hasil di elemen yang sesuai
						card.querySelector(".distance").innerText = result.distance.text;
						card.querySelector(".duration").innerText = result.duration.text;
					}
				});
			});
		}

		function hitungJarakAcc() {
			if (!hasGoogleMaps() || !userLocation) return;

			const currentRequestId = document.body.getAttribute('data-current-visit-request-id');
			const currentCard = currentRequestId ? document.querySelector('[data-visit-request-id="' + currentRequestId + '"]') : null;
			const destinations = {
				lat: currentCard ? parseFloat(currentCard.getAttribute('data-patient-lat')) : null,
				lng: currentCard ? parseFloat(currentCard.getAttribute('data-patient-lng')) : null
			}
			if (!Number.isFinite(destinations.lat) || !Number.isFinite(destinations.lng)) return;



			const requests = {
				origins: [userLocation],
				destinations: [destinations],
				travelMode: google.maps.TravelMode.DRIVING
			};



			const services = new google.maps.DistanceMatrixService();
			services.getDistanceMatrix(requests, function(response, status) {
				if (status === "OK") {
					const results = response.rows[0].elements[0];
					const distanceElement = document.getElementById("distances");
					const durationElement = document.getElementById("durations");
					if (distanceElement && !distanceElement.hasAttribute('data-route-distance')) {
						distanceElement.innerText = results.distance.text;
					}
					if (durationElement && !durationElement.hasAttribute('data-route-eta')) {
						durationElement.innerText = results.duration.text;
					}
				}
			});

			// calculateRoute(destination);
		}

		function calculateRoute(destination) {
			if (!hasGoogleMaps() || !userLocation || !directionsService) return;

			const request = {
				origin: userLocation,
				destination: destination,
				travelMode: google.maps.TravelMode.DRIVING
			};

			directionsService.route(request, function(result, status) {
				if (status === google.maps.DirectionsStatus.OK) {
					directionsRenderer.setDirections(result);
				} else {
					alert("Rute belum dapat dibuat. Coba lagi.");
				}
			});
		}

		function showError(error) {
			switch (error.code) {
				// case error.PERMISSION_DENIED:
				// 	alert("Izin lokasi ditolak.");
				// 	break;
				case error.POSITION_UNAVAILABLE:
					Swal.fire({
						title: "Lokasi belum ditemukan",
						text: "Lokasi belum ditemukan. Coba lagi.",
						icon: "error",
						confirmButtonText: "Tutup"
					});
					break;
				case error.TIMEOUT:
					Swal.fire({
						title: "Lokasi belum ditemukan",
						text: "Lokasi belum ditemukan. Coba lagi.",
						icon: "error",
						confirmButtonText: "Tutup"
					});
					break;
			}
		}

		if (hasGoogleMaps()) {
			window.addEventListener('load', initMap);
		}
	</script>

	<!-- Chat yang sudah tidak di pakai karena untuk chat sendiri ada di module chat -->
	<script>
		let nama_pasien;
		let pasien;
		let button;

		document.addEventListener("DOMContentLoaded", () => {
			if (Notification.permission !== "granted") {
				Notification.requestPermission();
			}
		});

		// Mengambil parameter URL
		const params = new URLSearchParams(window.location.search);
		// const dokter = prompt("Masukkan Dokter");
		// const pasien = params.get('pasien');

		const currentUser = document.getElementById('username').value;

		const chatModal = document.getElementById('chat');
		chatModal.addEventListener('show.bs.modal', function(event) {
			button = event.relatedTarget;
			pasien = button.getAttribute('data-pasien');
			nama_pasien = button.getAttribute('data-nama');
			const modal = this;
			modal.querySelector('.modal-title').textContent = `Chat dengan ${nama_pasien}`;
			modal.querySelector('#modal-pasien').value = pasien;

			const chatWith = pasien;


			db.ref("messages").on("child_added", (snapshot) => {
				const message = snapshot.val();

				if (message.receiver === currentUser) {
					showNotification(`Message from ${message.sender}: ${message.text}`);
				}
			});

			// Listen for messages
			db.ref("messages").on("value", (snapshot) => {
				const chatBox = document.getElementById("chat-box");
				chatBox.innerHTML = ""; // Clear the chat box
				snapshot.forEach((childSnapshot) => {
					const message = childSnapshot.val();

					if (
						(message.sender === currentUser && message.receiver === chatWith) ||
						(message.sender === chatWith && message.receiver === currentUser)
					) {
						const messageElement = document.createElement("div");
						const messageContent = document.createElement("div");
						const textElement = document.createElement("span");
						const timestampElement = document.createElement("span");


						messageElement.classList.add("message");
						if (message.sender === currentUser) {
							messageElement.classList.add("message-right");
						} else {
							messageElement.classList.add("message-left");
						}
						// Message text
						textElement.classList.add("message-text");
						textElement.textContent = message.text;

						// Timestamp
						timestampElement.classList.add("timestamp");
						timestampElement.textContent = new Date(message.timestamp).toLocaleTimeString([], {
							hour: "2-digit",
							minute: "2-digit",
						});

						// Combine message text and timestamp
						messageContent.classList.add("message-content");
						messageContent.appendChild(textElement);
						messageContent.appendChild(timestampElement);

						messageElement.appendChild(messageContent);
						chatBox.appendChild(messageElement);

						// chatBox.appendChild(messageContainer);

						// Scroll to the bottom
						chatBox.scrollTop = chatBox.scrollHeight;
					}
				});
			});

			function showNotification(message) {

				if (!("Notification" in window)) {
				} else if (Notification.permission === "granted") {
					const notification = new Notification("New Message", {
						body: message
					});
				} else if (Notification.permission !== "denied") {
					Notification.requestPermission().then((permission) => {
						if (permission === "granted") {
							const notification = new Notification("New Message", {
								body: message
							});
						}
					});
				} else {
				}
			}

			// Send message
			function sendMessage() {
				const messageInput = document.getElementById("message");
				const message = messageInput.value;

				if (message.trim() !== "") {
					const newMessageRef = db.ref("messages").push();
					newMessageRef.set({
						sender: currentUser,
						receiver: chatWith,
						text: message,
						timestamp: Date.now()
					});
					messageInput.value = "";
				}
			}
		});
	</script>

	<!-- Notifikasi -->
	<script>
		const notificationListJsonUrl = <?= json_encode(base_url('notifikasi/list_json')); ?>;
		const notificationMarkReadUrl = <?= json_encode(base_url('notifikasi/mark_read')); ?>;
		const notificationPollIntervalMs = 30000;
		const notificationMarkReadInFlight = new Set();

		function getNotificationOpenErrorElement() {
			let errorElement = document.getElementById('notificationOpenError');
			if (errorElement) {
				return errorElement;
			}

			const notificationList = document.getElementById('notificationList');
			if (!notificationList || !notificationList.parentNode) {
				return null;
			}

			errorElement = document.createElement('div');
			errorElement.id = 'notificationOpenError';
			errorElement.className = 'alert alert-danger py-2 px-3 mb-2 small';
			errorElement.setAttribute('role', 'alert');
			errorElement.hidden = true;
			notificationList.parentNode.insertBefore(errorElement, notificationList);
			return errorElement;
		}

		function setNotificationOpenError(message) {
			const errorElement = getNotificationOpenErrorElement();
			if (!errorElement) {
				return;
			}
			errorElement.textContent = message || '';
			errorElement.hidden = !message;
		}

		function getNotificationBadge() {
			return Array.prototype.slice.call(document.querySelectorAll('#badgeNotif, #badgeNotifs'));
		}

		function setNotificationCount(count) {
			const badges = getNotificationBadge();
			const safeCount = Math.max(0, parseInt(count || 0, 10));
			if (!badges.length) {
				return;
			}
			badges.forEach(function(badge) {
				if (safeCount > 0) {
					badge.style.display = 'inline-flex';
					badge.textContent = safeCount > 99 ? '99' + '+' : safeCount;
				} else {
					badge.style.display = 'none';
					badge.textContent = '';
				}
			});
		}

		function syncNotificationCountFromList(notificationList) {
			if (!notificationList) {
				return;
			}
			setNotificationCount(notificationList.querySelectorAll('.notification-item').length);
		}

		function renderNotificationItem(item) {
			const notificationItem = document.createElement('div');
			notificationItem.className = 'notification-item nk-notification-item';

			const icon = document.createElement('i');
			icon.className = 'bi bi-bell-fill nk-notification-icon';
			notificationItem.appendChild(icon);

			const textContainer = document.createElement('div');
			textContainer.className = 'nk-notification-content';

			const title = document.createElement('p');
			title.className = 'nk-notification-item-title';
			title.textContent = item.title || 'Notifikasi';
			textContainer.appendChild(title);

			const message = document.createElement('small');
			message.className = 'nk-notification-message';
			message.textContent = item.message || 'Tidak ada detail';
			textContainer.appendChild(message);

			const timestamp = document.createElement('small');
			timestamp.className = 'nk-notification-time';
			timestamp.textContent = item.created_at ? new Date(item.created_at.replace(' ', 'T')).toLocaleString('id-ID') : '';
			textContainer.appendChild(timestamp);

			notificationItem.appendChild(textContainer);
			notificationItem.addEventListener('click', function(event) {
				event.preventDefault();
				markNotificationRead(item, notificationItem);
			});

			return notificationItem;
		}

		function renderDatabaseNotifications(items, count) {
			const notificationList = document.getElementById('notificationList');
			if (!notificationList) {
				return;
			}
			notificationList.innerHTML = '';
			if (!items || !items.length) {
				notificationList.innerHTML = '<div class="nk-notification-empty"><i class="bi bi-bell"></i><span>Tidak ada notifikasi</span></div>';
				setNotificationCount(0);
				return;
			}
			items.forEach(function(item) {
				notificationList.appendChild(renderNotificationItem(item));
			});
			setNotificationCount(parseInt(count || 0, 10));
		}

		function loadDatabaseNotifications() {
			if (document.visibilityState === 'hidden') {
				return;
			}
			fetch(notificationListJsonUrl, {
					credentials: 'same-origin'
				})
				.then(function(response) {
					if (!response.ok) {
						throw new Error('notification_load_failed');
					}
					return response.json();
				})
				.then(function(data) {
					if (!data || data.status !== 'success') {
						return;
					}
					renderDatabaseNotifications(data.notifications || [], parseInt(data.unread_count || 0, 10));
				})
				.catch(function() {});
		}

		function startDatabaseNotificationPolling() {
			if (window.doclincNotificationPollingStarted) {
				return;
			}
			window.doclincNotificationPollingStarted = true;
			loadDatabaseNotifications();
			window.setInterval(loadDatabaseNotifications, notificationPollIntervalMs);
			document.addEventListener('visibilitychange', function() {
				if (document.visibilityState === 'visible') {
					loadDatabaseNotifications();
				}
			});
		}

		window.DoclincNotificationUi = {
			applySnapshot: function(data) {
				if (!data || !Array.isArray(data.notifications)) {
					return;
				}
				renderDatabaseNotifications(data.notifications, parseInt(data.unread_count || 0, 10));
			},
			refresh: loadDatabaseNotifications
		};

		function markNotificationRead(notification, notificationElement) {
			const notificationId = notification && notification.notification_id ? notification.notification_id : notification;
			const actionUrl = notification && notification.action_url ? notification.action_url : '';
			const notificationKey = notificationId === null || typeof notificationId === 'undefined' ? '' : String(notificationId).trim();
			const isValidNotificationId = (typeof notificationId === 'string' || typeof notificationId === 'number') && /^[1-9]\d*$/.test(notificationKey);
			if (!isValidNotificationId || notificationMarkReadInFlight.has(notificationKey)) {
				return;
			}
			notificationMarkReadInFlight.add(notificationKey);
			if (notificationElement) {
				notificationElement.setAttribute('aria-busy', 'true');
			}
			const formData = new FormData();
			formData.append('notification_id', notificationId);
			fetch(notificationMarkReadUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
				.then(function(response) {
					return response.json().then(function(body) {
						const isObject = body && typeof body === 'object' && !Array.isArray(body);
						if (!response.ok || !isObject || body.status !== 'success') {
							const failure = new Error('notification_mark_read_failed');
							if (isObject && typeof body.message === 'string' && body.message.trim()) {
								failure.safeMessage = body.message.trim();
							}
							throw failure;
						}

						setNotificationOpenError('');
						if (actionUrl) {
							window.location.href = actionUrl;
							return;
						}
						loadDatabaseNotifications();
					});
				})
				.catch(function(error) {
					const safeMessage = error && typeof error.safeMessage === 'string' && error.safeMessage.trim() ? error.safeMessage.trim() : '';
					setNotificationOpenError(safeMessage || 'Notifikasi belum dapat dibuka. Coba lagi.');
				})
				.finally(function() {
					notificationMarkReadInFlight.delete(notificationKey);
					if (notificationElement) {
						notificationElement.removeAttribute('aria-busy');
					}
				});
		}

		document.addEventListener('DOMContentLoaded', startDatabaseNotificationPolling);
	</script>

	<!-- tampil request dari pasien melalui firebase -->
	<script>
		// Mendapatkan referensi ke database Firebase
		// Mendapatkan referensi ke node "request"
		const requestRef = db.ref("request");

		// Mendengarkan perubahan pada data "request"
		requestRef.on("value", (snapshot) => {
			const data = snapshot.val();

			const cekStatus = document.getElementById("cekStatus");

			if (data) {
				// buat halaman menjadi reload
				window.location.reload();

				// hapus data request di firebase
				requestRef.remove();
			}
		});
	</script>

	<!-- mendapatkan url -->
	<script>
		document.addEventListener("DOMContentLoaded", function() {
			const hash = window.location.hash;

			if (hash === "#riwayat_konsul" || hash === "#riwayat_konsul_selesai") {
				if (typeof showContent === 'function') {
					showContent('riwayat_konsul');
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

	<!-- upload dan preview foto atau gambar -->
	<script>
		function setProfileImageFeedback(message) {
			const feedback = document.getElementById('profileImageFeedback');
			if (!feedback) {
				return;
			}

			feedback.textContent = message || '';
			feedback.classList.toggle('d-none', !message);
		}

		function previewImage(event) {
			const input = event.target;
			const file = input.files && input.files[0];

			if (!file) {
				setProfileImageFeedback('');
				return;
			}

			const reader = new FileReader();

			reader.onload = function() {
				if (!input.files || input.files[0] !== file) {
					return;
				}

				const preview = document.getElementById('previewFoto');
				preview.src = reader.result;
				setProfileImageFeedback('');
			};
			reader.onerror = function() {
				if (!input.files || input.files[0] !== file) {
					return;
				}

				setProfileImageFeedback('Gambar belum dapat dimuat. Coba lagi.');
			};
			reader.onabort = reader.onerror;
			reader.readAsDataURL(file);
		}
	</script>

	<!-- edit profile -->
	<script>
		let profileUpdateInFlight = false;

		document.getElementById('formEditProfile').addEventListener('submit', function(e) {
			e.preventDefault(); // Cegah form submit biasa

			if (profileUpdateInFlight) {
				return;
			}

			const form = document.getElementById('formEditProfile');
			if (!form.checkValidity()) {
				form.reportValidity();
				return;
			}

			const submitButton = form.querySelector('button[type="submit"]');
			const originalButtonDisabled = submitButton ? submitButton.disabled : false;
			const originalButtonContent = submitButton ? submitButton.innerHTML : '';
			const formData = new FormData(form); // Ambil semua input termasuk file
			let profileUpdateSucceeded = false;
			const restoreProfileSubmit = function() {
				profileUpdateInFlight = false;
				if (submitButton) {
					submitButton.innerHTML = originalButtonContent;
					submitButton.disabled = originalButtonDisabled;
				}
			};

			profileUpdateInFlight = true;
			if (submitButton) {
				submitButton.disabled = true;
			}

			fetch("<?= base_url('home_nakes/updateprofile') ?>", {
					method: "POST",
					body: formData
				})
				.then(response => {
					return response.json().then(result => {
						const isResultObject = result !== null &&
							typeof result === 'object' &&
							!Array.isArray(result);
						if (!isResultObject) {
							return Promise.reject({});
						}

						if (!response.ok) {
							return Promise.reject({
								safeMessage: result && typeof result.message === 'string' ? result.message.trim() : ''
							});
						}

						return result;
					});
				})
				.then(result => {
					if (result.status === 'success') {
						profileUpdateSucceeded = true;
						alert("Profil berhasil diperbarui!");
						// Misalnya reload data user:
						location.reload();
					} else {
						const safeMessage = typeof result.message === 'string' && result.message.trim() ?
							result.message.trim() :
							"Terjadi kesalahan. Coba lagi.";
						alert("Profil belum diperbarui. " + safeMessage);
					}
				})
				.catch(failure => {
					const safeMessage = failure && typeof failure.safeMessage === 'string' && failure.safeMessage ?
						failure.safeMessage :
						"Terjadi kesalahan. Coba lagi.";
					alert("Profil belum diperbarui. " + safeMessage);
				})
				.finally(() => {
					if (profileUpdateSucceeded) {
						return;
					}

					restoreProfileSubmit();
				});
		});
	</script>

	<?php $notification_realtime_bootstrap = doclinc_notification_realtime_bootstrap(); ?>
	<?php $request_realtime_bootstrap = doclinc_request_realtime_bootstrap(); ?>
	<?php if (is_array($notification_realtime_bootstrap)) : ?>
		<script id="doclincNotificationRealtimeConfig" type="application/json"><?= json_encode($notification_realtime_bootstrap, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
	<?php endif; ?>
	<?php if (is_array($request_realtime_bootstrap)) : ?>
		<script id="doclincRequestRealtimeConfig" type="application/json"><?= json_encode($request_realtime_bootstrap, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
	<?php endif; ?>
	<?php if (is_array($notification_realtime_bootstrap) || is_array($request_realtime_bootstrap)) : ?>
		<script src="<?= html_escape(base_url('assets/vendor/centrifuge/5.7.0/centrifuge.js')); ?>"></script>
		<script src="<?= html_escape(base_url('assets/js/doclinc-realtime-client.js')); ?>"></script>
	<?php endif; ?>
	<?php if (is_array($notification_realtime_bootstrap)) : ?>
		<script src="<?= html_escape(base_url('assets/js/doclinc-notifications.js')); ?>"></script>
	<?php endif; ?>
	<?php if (is_array($request_realtime_bootstrap)) : ?>
		<script src="<?= html_escape(base_url('assets/js/doclinc-requests.js')); ?>"></script>
	<?php endif; ?>

</body>

</html>
