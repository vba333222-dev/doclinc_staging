<?php
$firebase_enabled = (bool) $this->config->item('firebase_enabled');
$legacy_superapp_url = $this->config->item('legacy_superapp_url') ?: '';
$session_role = $this->session->userdata('role') ?: '';
$session_nama = $this->session->userdata('nama') ?: '';
$session_id = $this->session->userdata('id') ?: '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Doklinc - Live Chat</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

	<style>
		.chat-container {
			display: flex;
			flex-direction: column;
			height: 100vh;
			max-width: 600px;
			margin: 0 auto;
		}

		.profile-header {
			display: flex;
			align-items: center;
			padding: 15px;
			border-bottom: 1px solid #ddd;
			background-color: #09ad74;
			color: white;
		}

		.profile-header img {
			border-radius: 50%;
			height: 60px;
			width: 60px;
			margin-right: 15px;
		}

		.profile-header h3 {
			margin: 0;
		}

		.chat-box {
			flex: 1;
			overflow-y: auto;
			padding: 15px;
			border: 1px solid #ddd;
			border-radius: 5px;
			background-color: #f9f9f9;
		}

		#chat-box {
			display: flex;
			flex-direction: column;
			gap: 8px;
			height: 400px;
			overflow-y: auto;
			border: 1px solid #ddd;
			border-radius: 5px;
			padding: 10px;
			background: #f9f9f9;
		}

		/* Base message styles */
		#message {
			width: 100%;
			min-height: 40px;
			max-height: 150px;
			overflow-y: hidden;
			resize: none;
			padding: 10px;
			font-size: 16px;
			border: 1px solid #ccc;
			border-radius: 8px;
		}

		.message {
			display: flex;
			flex-direction: column;
			align-items: flex-start;
			position: relative;
			padding: 8px 12px;
			margin: 5px 0;
			border-radius: 8px;
			font-size: 14px;
			line-height: 1.5;
			max-width: 65%;
			word-wrap: break-word;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
		}

		/* Right (current user) message styling */
		.message-right {
			background: #dcf8c6;
			/* WhatsApp green bubble */
			align-self: flex-end;
			text-align: left;
			border-top-right-radius: 0px;
		}

		/* Left (other user) message styling */
		.message-left {
			background: #ffffff;
			/* White bubble */
			align-self: flex-start;
			text-align: left;
			border-top-left-radius: 0px;
		}

		/* Text and timestamp container */
		.message-content {
			display: flex;
			justify-content: space-between;
			align-items: flex-end;
		}

		/* Message text */
		.message-text {
			flex-grow: 1;
			font-size: 14px;
			color: #000000;
			word-break: break-word;
		}

		/* Timestamp styling */
		.timestamp {
			margin-left: 10px;
			font-size: 10px;
			color: #808080;
			white-space: nowrap;
		}

		#message:focus {
			border-color: #2f855a;
			box-shadow: 0 2px 4px rgba(47, 133, 90, 0.2);
		}

		.message.user {
			align-items: flex-end;
		}

		.message .sender {
			font-weight: bold;
		}

		.message .content {
			border-radius: 10px;
			padding: 10px;
			max-width: 80%;
			line-height: 1.5;
			word-wrap: break-word;
		}

		.message.ustadz .content {
			background-color: #e1f5fe;
			color: #000;
		}

		.message.user .content {
			background-color: #09ad74;
			color: #fff;
		}

		.message-buttons {
			display: flex;
			flex-direction: column;
			margin-top: 10px;
		}

		.message-buttons button {
			background-color: #09ad74;
			color: white;
			border: none;
			border-radius: 5px;
			padding: 10px;
			cursor: pointer;
			margin-bottom: 5px;
		}

		.message-buttons button:hover {
			background-color: #00332d;
		}

		.message-input {
			display: flex;
			align-items: center;
			gap: 8px;
			border-top: 1px solid #ddd;
			padding: 10px;
			background-color: #fff;
		}

		.message-input input {
			flex: 1;
			border: none;
			border-radius: 5px;
			padding: 10px;
		}

		.message-input button {
			background-color: #09ad74;
			color: white;
			border: none;
			border-radius: 5px;
			padding: 10px;
			cursor: pointer;
		}

		.end-chat {
			text-align: center;
			padding: 10px;
		}

		.end-chat button {
			background-color: #d32f2f;
			color: white;
			border: none;
			border-radius: 5px;
			padding: 10px;
			cursor: pointer;
		}

		.attachment-wrapper {
			position: relative;
		}

		.message-input .media-button {
			background-color: rgb(255, 255, 255);
			color: black;
			border: none;
			border-radius: 5px;
			padding: 10px;
			cursor: pointer;
			font-size: 16px;
			transition: background-color 0.3s;
		}

		.message-input .media-button i {
			transition: transform 0.3s;
		}

		.media-popup {
			position: absolute;
			bottom: 120%;
			left: 0;
			background-color: white;
			border-radius: 10px;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
			width: 180px;
			display: none;
			flex-direction: column;
			padding: 8px 0;
			z-index: 999;
			opacity: 0;
			transform: translateY(10px);
			pointer-events: none;
			transition: opacity 0.2s ease, transform 0.2s ease;
		}

		.media-popup.show {
			display: flex;
			opacity: 1;
			transform: translateY(0);
			pointer-events: auto;
		}

		.popup-item {
			padding: 10px 15px;
			cursor: pointer;
			display: flex;
			align-items: center;
			gap: 10px;
			font-size: 14px;
			transition: background-color 0.2s ease;
		}

		.popup-item:hover {
			background-color: #f0f0f0;
		}

		.popup-item i {
			width: 20px;
			text-align: center;
		}

		#uploadProgress {
			color: #333;
			font-size: 12px;
		}

		.chat-image,
		.chat-video {
			display: block;
			margin-bottom: 5px;
		}

		.chat-file {
			color: #007bff;
			text-decoration: underline;
		}

		.media-modal {
			display: none;
			position: fixed;
			z-index: 9999;
			left: 0;
			top: 0;
			width: 100%;
			height: 100%;
			overflow: auto;
			background-color: rgba(0, 0, 0, 0.9);
			text-align: center;
			padding-top: 60px;
		}

		.media-modal-content {
			max-width: 90%;
			max-height: 80%;
			margin: auto;
		}

		.media-modal video,
		.media-modal img {
			max-width: 100%;
			max-height: 100%;
		}

		.media-modal .close {
			position: absolute;
			top: 20px;
			right: 30px;
			color: #fff;
			font-size: 40px;
			font-weight: bold;
			cursor: pointer;
		}
	</style>
</head>

<body>
	<div class="chat-container">
		<!-- Profil Ustadz -->
		<div class="profile-header">
			<a href="https://idbcs.net/cilegon_bersatu/sehat_geh" style="text-decoration: none; color: white; font-size: 1.5rem;">
				<i class="fas fa-chevron-left icon"></i>
			</a>
			<?php
			if ($session_role == 'dokter') { ?>
				<!-- <img src="<?= base_url(); ?>uploads/profile/<?= $foto ?>" alt="Pasien" id="ustadzProfileImage"> -->
				<img src="<?= html_escape(base_url('assets/doclinc/img/default-profile.png')); ?>" alt="Pasien" id="ustadzProfileImage">
			<?php } elseif ($session_role == 'warga') { ?>
				<!-- <img src="<?= base_url(); ?>uploads/profile/<?= $foto ?>" alt="Dokter" id="ustadzProfileImage"> -->
				<img src="<?= html_escape(base_url('assets/doclinc/img/default-profile.png')); ?>" alt="Dokter" id="ustadzProfileImage">
			<?php } else { ?>
				<img class="rounded-4 shadow"
					src="<?= html_escape(base_url('assets/doclinc/img/default-profile.png')); ?>"
					width="100px"
					height="100px">
			<?php } ?>
			<div>
				<h3 id="ustadzNameFull"></h3>
				<p id="ustadzSpecialization" class="mb-0"></p>
				<?php
				$request_id = isset($_GET['reqId']) ? htmlspecialchars($_GET['reqId']) : '';
				$user_id = isset($_GET['userId']) ? htmlspecialchars($_GET['userId']) : '';
				$namaPasien = isset($_GET['namaPasien']) ? htmlspecialchars($_GET['namaPasien']) : '';

				if ($session_role == 'dokter') { ?>
					<input type="hidden" id="username" value="<?= html_escape($session_nama) ?>">
					<input type="hidden" id="request_id" value="<?= $request_id ?>">
					<input type="hidden" id="user_id" value="<?= $user_id ?>">
					<input type="hidden" id="uid" value="<?= $user_id ?>">
					<input type="hidden" id="uids" value="<?= $user_id ?>">
					<input type="hidden" id="namaVideo" value="<?= html_escape($session_nama) ?>">
				<?php } elseif ($session_role == 'warga') { ?>
					<input type="hidden" id="username" value="<?= $request_id ?>">
					<input type="hidden" id="request_id" value="<?= $user_id ?>">
					<input type="hidden" id="user_id" value="<?= $request_id ?>">
					<input type="hidden" id="uid" value="<?= html_escape($session_id) ?>">
					<input type="hidden" id="uids" value="<?= $user_id ?>">
					<input type="hidden" id="namaVideo" value="<?= html_escape($session_nama) ?>">
				<?php } else { ?>
					<input type="hidden" id="username" value="">
					<input type="hidden" id="request_id" value="">
					<input type="hidden" id="user_id" value="">
					<input type="hidden" id="uid" value="">
					<input type="hidden" id="uids" value="">
					<input type="hidden" id="namaVideo" value="">
				<?php } ?>
			</div>

			<!-- Video Call Section -->
			<div style="display: block; align-items: center; gap: 8px; margin-left: auto;">
				<!-- Tombol hanya untuk dokter -->
				<button id="startCall" style="display: none; padding: 6px 12px; border: none; border-radius: 6px; background: #28a745; color: white; cursor: pointer;">
					<i class="fas fa-video"></i> Mulai Panggilan
				</button>

				<!-- Tombol hanya untuk warga -->
				<button id="acceptCall" style="display: none; padding: 6px 12px; border: none; border-radius: 6px; background: #007bff; color: white; cursor: pointer;">
					<i class="fas fa-phone"></i> Terima Panggilan
				</button>
			</div>
		</div>

		<div class="chat-box" id="chat-box">
			<!-- Pesan akan ditampilkan di sini -->
		</div>
		<div class="message-input">

			<div class="attachment-wrapper">
				<button id="mediaButton" class="media-button">
					<i class="fas fa-plus"></i>
				</button>
				<div id="mediaPopup" class="media-popup">
					<?php if ($session_role == 'dokter') { ?>
						<div class="popup-item" onclick="handleAttachment('special')"><i class="fas fa-user-md"></i> Dokter Only</div>
					<?php } ?>
					<div class="popup-item" onclick="handleAttachment('file')"><i class="fas fa-folder"></i> File</div>
					<div class="popup-item" onclick="handleAttachment('photo')"><i class="fas fa-image"></i> Photos</div>
					<div class="popup-item" onclick="handleAttachment('video')"><i class="fas fa-video"></i> Videos</div>
				</div>
			</div>

			<!-- <input type="text" id="message" placeholder="Tulis pesan..."> -->
			<textarea id="message" placeholder="Tulis pesan..." rows="1"></textarea>
			<button id="sendButton" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
		</div>

		<!-- Disembunyikan -->
		<input type="file" id="files" name="file" accept="*/*" style="display: none;">
		<input type="file" id="file" name="foto" accept="image/*" style="display: none;">
		<input type="file" id="file_video" name="video" accept="video/*" style="display: none;">

		<!-- Progress bar (Foto & Video) -->
		<div id="uploadProgress" style="display: none; margin-top: 10px;">
			<progress id="progressBar" value="0" max="100" style="width: 100%;"></progress>
			<small id="progressStatus"></small>
		</div>

		<div id="mediaModal" class="media-modal" onclick="closeModal()">
			<span class="close">&times;</span>
			<div class="media-modal-content" id="modalContent"></div>
		</div>

	</div>

	<div id="videoPopups" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
		<div style="position:relative; width:90%; max-width:500px; background:white; padding:20px; border-radius:12px;">
			<h3 style="margin-top: 0;">Panggilan Video Aktif</h3>

			<!-- Local Video (Dokter) -->
			<div style="position:relative; margin-bottom: 10px;">
				<video id="localVideos" autoplay muted playsinline style="width: 100%; border-radius: 8px; background: black;">
				</video>
				<div id="labelDokters" style="position:absolute; top:8px; left:8px; background-color:rgba(0,0,0,0.6); color:white; padding:4px 8px; border-radius:6px; font-size:14px;">Dokter</div>
			</div>

			<!-- Remote Video (Warga) -->
			<div style="position:relative; margin-bottom: 10px;">
				<video id="remoteVideos" autoplay playsinline style="width: 100%; border-radius: 8px; background: black; margin-top: 10px;"></video>
				<div id="labelWargas" style="position:absolute; top:8px; left:8px; background-color:rgba(0,0,0,0.6); color:white; padding:4px 8px; border-radius:6px; font-size:14px;">Warga</div>
			</div>

			<button id="endCalls" style="margin-top:15px; background-color:#dc3545; color:white; padding:8px 16px; border:none; border-radius:6px; cursor:pointer;"><i class="fas fa-phone-slash"></i>Akhiri</button>
		</div>
	</div>

	<div id="videoPopup" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
		<div style="position:relative; width:90%; max-width:500px; background:white; padding:20px; border-radius:12px;">
			<h3 style="margin-top: 0;">Panggilan Video Aktif</h3>

			<!-- Remote Video -->
			<div id="remoteVideoContainer" style="position:relative; margin-bottom: 10px;">
				<video id="remoteVideo" autoplay playsinline style="width: 100%; border-radius: 8px; background: black;"></video>
				<div id="labelRemote" style="position:absolute; top:8px; left:8px; background-color:rgba(0,0,0,0.6); color:white; padding:4px 8px; border-radius:6px; font-size:14px;">Remote</div>
			</div>

			<!-- Local Video -->
			<div id="localVideoContainer" style="position:absolute; bottom:20px; right:20px; width:120px; height:90px; border-radius:8px; overflow:hidden; background:black;">
				<video id="localVideo" autoplay muted playsinline style="width: 100%; height: 100%;"></video>
				<div id="labelLocal" style="position:absolute; top:8px; left:8px; background-color:rgba(0,0,0,0.6); color:white; padding:4px 8px; border-radius:6px; font-size:12px;">Local</div>
			</div>

			<button id="endCall" style="margin-top:15px; background-color:#dc3545; color:white; padding:8px 16px; border:none; border-radius:6px; cursor:pointer;"><i class="fas fa-phone-slash"></i> Akhiri</button>
		</div>
	</div>

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>

	<?php if ($firebase_enabled) : ?>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script>
		<!-- <script src="https://www.gstatic.com/firebasejs/8.6.1/firebase.js"></script> -->
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
		<?php if ($legacy_superapp_url !== '') : ?>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/firebase-config.js'); ?>"></script>
		<?php endif; ?>
	<?php endif; ?>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	<script>
		const firebaseEnabled = <?= json_encode($firebase_enabled); ?>;

		function getFirebaseDatabase() {
			const noopRef = {
				on: function() {},
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
				}
			};

			if (!firebaseEnabled || !window.firebase || !firebase.database) {
				return {
					ref: function() {
						return noopRef;
					}
				};
			}

			try {
				return firebase.database();
			} catch (error) {
				return {
					ref: function() {
						return noopRef;
					}
				};
			}
		}
	</script>

	<!-- <script>
		const namaDokter = document.getElementById('request_id').value;
		const namaVideo = document.getElementById('namaVideo').value;
		const namaPasien = "<?= $namaPasien ?>";
		if (<?= json_encode($session_role === 'dokter') ?>) {
			document.getElementById("labelDokter").innerText = namaVideo;
			document.getElementById("labelWarga").innerText = namaPasien;
		} else if (<?= json_encode($session_role === 'warga') ?>) {
			document.getElementById("labelDokter").innerText = namaVideo;
			document.getElementById("labelWarga").innerText = namaDokter;
		}
	</script> -->

	<!-- pengaturan video call -->
	<script>
		document.addEventListener("DOMContentLoaded", () => {
			const role = <?= json_encode($session_role) ?>;
			const localVideoContainer = document.getElementById("localVideoContainer");
			const remoteVideoContainer = document.getElementById("remoteVideoContainer");
			const namaDokter = document.getElementById('request_id').value;
			const namaVideo = document.getElementById('namaVideo').value;
			const namaPasien = "<?= $namaPasien ?>";

			if (<?= json_encode($session_role === 'dokter') ?>) {
				// Dokter: Local video kecil, overlay ke remote video
				localVideoContainer.style.position = "absolute";
				localVideoContainer.style.bottom = "20px";
				localVideoContainer.style.right = "20px";
				localVideoContainer.style.width = "120px";
				localVideoContainer.style.height = "90px";
				document.getElementById("labelLocal").innerText = namaVideo;
				document.getElementById("labelRemote").innerText = namaPasien;
			} else if (<?= json_encode($session_role === 'warga') ?>) {
				// Warga: Local video kecil, overlay ke remote video
				localVideoContainer.style.position = "absolute";
				localVideoContainer.style.bottom = "20px";
				localVideoContainer.style.right = "20px";
				localVideoContainer.style.width = "120px";
				localVideoContainer.style.height = "90px";
				document.getElementById("labelLocal").innerText = namaVideo;
				document.getElementById("labelRemote").innerText = namaDokter;
			}
		});
	</script>

	<!-- kirim file ke chat -->
	<script>
		const currentUserId = document.getElementById('username').value;
		const userRequestId = document.getElementById('request_id').value;

		const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

		const mediaBtn = document.getElementById('mediaButton');
		const popup = document.getElementById('mediaPopup');
		const icon = mediaBtn.querySelector('i');

		mediaBtn.addEventListener('click', function(e) {
			e.stopPropagation();
			popup.classList.toggle('show');
			icon.classList.toggle('fa-plus');
			icon.classList.toggle('fa-times');
		});

		document.addEventListener('click', function() {
			if (popup.classList.contains('show')) {
				popup.classList.remove('show');
				icon.classList.remove('fa-times');
				icon.classList.add('fa-plus');
			}
		});

		function handleAttachment(type) {
			if (type === 'photo') {
				document.getElementById('file').click();
			} else if (type === 'video') {
				document.getElementById('file_video').click();
			} else if (type === 'special') {
				popUpNakes();
			} else {
				Swal.fire({
					icon: 'info',
					title: 'Oops...',
					text: 'Fitur belum tersedia!'
				});
			}
		}

		// Upload Foto
		document.getElementById('file').addEventListener('change', function() {
			const file = this.files[0];
			if (!file) return;
			if (file.size > MAX_FILE_SIZE) {
				Swal.fire({
					icon: 'error',
					title: 'Oops...',
					text: 'Ukuran foto melebihi 5 MB!'
				});
				return;
			}
			uploadFileWithXHR(file, "<?= base_url('chat/foto') ?>", 'foto');
		});

		// Upload Video
		document.getElementById('file_video').addEventListener('change', function() {
			const file = this.files[0];
			if (!file) return;
			if (file.size > MAX_FILE_SIZE) {
				Swal.fire({
					icon: 'error',
					title: 'Oops...',
					text: 'Ukuran video melebihi 5 MB!'
				});
				return;
			}
			uploadFileWithXHR(file, "<?= base_url('chat/video') ?>", 'video');
		});

		function uploadFileWithXHR(file, url, key) {
			const formData = new FormData();
			formData.append(key, file);
			formData.append('request_id', document.getElementById('request_id').value);

			const xhr = new XMLHttpRequest();
			xhr.open("POST", url, true);

			document.getElementById('uploadProgress').style.display = 'block';

			xhr.upload.onprogress = function(e) {
				if (e.lengthComputable) {
					const percent = Math.round((e.loaded / e.total) * 100);
					document.getElementById('progressBar').value = percent;
					document.getElementById('progressStatus').innerText = `Mengunggah... ${percent}%`;
				}
			};

			xhr.onload = function() {
				if (xhr.status === 200) {
					document.getElementById('progressStatus').innerText = 'Upload selesai!';
					const response = JSON.parse(xhr.responseText);

					if (response.status === 'success') {
						const fileUrl = response.file_url;
						const sender = document.getElementById('username').value;
						const receiver = document.getElementById('request_id').value;
						const type = key === 'foto' ? 'image' : 'video';

						console.log('filenya' + fileUrl);

						// Kirim ke Firebase
						setTimeout(() => {
							sendMediaMessage(fileUrl, type);
						}, 2000); // Delay 2 detik untuk menunggu file stabil
						// Tampilkan di chat
						// appendMediaMessage(fileUrl, 'right', type);
					} else {
						Swal.fire({
							icon: 'error',
							title: 'Upload Gagal',
							text: response.message
						});
					}
				} else {
					document.getElementById('progressStatus').innerText = 'Gagal mengunggah.';
				}

				setTimeout(() => {
					document.getElementById('uploadProgress').style.display = 'none';
					document.getElementById('progressBar').value = 0;
				}, 2000);
			};

			xhr.send(formData);
		}

		function sendMediaMessage(fileUrl, type = 'image') {
			const timestamp = Date.now();

			const message = {
				receiver: userRequestId,
				sender: currentUserId,
				text: fileUrl,
				type: type, // 'image' atau 'video'
				timestamp: timestamp
			};

			getFirebaseDatabase().ref("messages").push(message);
		}

		function popUpNakes() {
			const popupContainer = document.createElement('div');
			popupContainer.style.position = 'fixed';
			popupContainer.style.top = '0';
			popupContainer.style.left = '0';
			popupContainer.style.width = '100%';
			popupContainer.style.height = '100%';
			popupContainer.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
			popupContainer.style.display = 'flex';
			popupContainer.style.justifyContent = 'center';
			popupContainer.style.alignItems = 'center';
			popupContainer.style.zIndex = '9999';

			const popupContent = document.createElement('div');
			popupContent.style.backgroundColor = '#fff';
			popupContent.style.borderRadius = '12px';
			popupContent.style.padding = '20px';
			popupContent.style.textAlign = 'center';
			popupContent.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
			popupContent.style.maxWidth = '400px';
			popupContent.style.width = '90%';

			const title = document.createElement('h3');
			title.innerText = 'Pilih Aksi';
			title.style.marginBottom = '20px';
			title.style.color = '#333';

			const selesaiButton = document.createElement('button');
			selesaiButton.innerHTML = '<i class="fas fa-check-circle"></i> Selesai Konsultasi';
			selesaiButton.style.backgroundColor = '#d4edda';
			selesaiButton.style.color = '#155724';
			selesaiButton.style.border = 'none';
			selesaiButton.style.borderRadius = '8px';
			selesaiButton.style.padding = '10px 20px';
			selesaiButton.style.margin = '10px';
			selesaiButton.style.cursor = 'pointer';
			selesaiButton.style.fontSize = '16px';
			selesaiButton.style.transition = 'background-color 0.3s';
			selesaiButton.addEventListener('mouseover', () => selesaiButton.style.backgroundColor = '#c3e6cb');
			selesaiButton.addEventListener('mouseout', () => selesaiButton.style.backgroundColor = '#d4edda');
			selesaiButton.addEventListener('click', () => {
				window.location.href = "<?= base_url('konsultasi_nakes/konsultasi/' . $request_id . '?kriteria=0') ?>";
			});

			const kunjunganButton = document.createElement('button');
			kunjunganButton.innerHTML = '<i class="fas fa-user-md"></i> Kunjungan Nakes';
			kunjunganButton.style.backgroundColor = '#d1ecf1';
			kunjunganButton.style.color = '#0c5460';
			kunjunganButton.style.border = 'none';
			kunjunganButton.style.borderRadius = '8px';
			kunjunganButton.style.padding = '10px 20px';
			kunjunganButton.style.margin = '10px';
			kunjunganButton.style.cursor = 'pointer';
			kunjunganButton.style.fontSize = '16px';
			kunjunganButton.style.transition = 'background-color 0.3s';
			kunjunganButton.addEventListener('mouseover', () => kunjunganButton.style.backgroundColor = '#bee5eb');
			kunjunganButton.addEventListener('mouseout', () => kunjunganButton.style.backgroundColor = '#d1ecf1');
			kunjunganButton.addEventListener('click', () => {
				window.location.href = "<?= base_url('home_nakes#riwayat_konsul') ?>";
			});

			const closeButton = document.createElement('span');
			closeButton.innerHTML = '&times;';
			closeButton.style.position = 'absolute';
			closeButton.style.top = '10px';
			closeButton.style.right = '15px';
			closeButton.style.fontSize = '24px';
			closeButton.style.color = '#333';
			closeButton.style.cursor = 'pointer';
			closeButton.addEventListener('click', () => document.body.removeChild(popupContainer));

			popupContent.appendChild(closeButton);
			popupContent.appendChild(title);
			popupContent.appendChild(selesaiButton);
			popupContent.appendChild(kunjunganButton);
			popupContainer.appendChild(popupContent);
			document.body.appendChild(popupContainer);
		}
	</script>

	<!-- kirim chat -->
	<script>
		document.addEventListener("DOMContentLoaded", () => {
			if (Notification.permission !== "granted") {
				Notification.requestPermission();
			}
		});

		const messageText = document.getElementById("message");

		messageText.addEventListener("input", function() {
			this.style.height = "auto";
			this.style.height = this.scrollHeight + "px";
		});

		const sendButton = document.getElementById("sendButton");
		sendButton.addEventListener("click", function() {
			messageText.value = "";
			messageText.style.height = "50px";
		});

		const currentUser = document.getElementById('username').value;
		const userChat = document.getElementById('request_id').value;
		const chatWith = userChat;

		getFirebaseDatabase().ref("messages").on("child_added", (snapshot) => {
			const message = snapshot.val();
			if (message.receiver === currentUser) {
				showNotification(`Message from ${message.sender}: ${message.text}`);
			}
		});

		getFirebaseDatabase().ref("messages").on("value", (snapshot) => {
			const chatBox = document.getElementById("chat-box");
			chatBox.innerHTML = "";
			snapshot.forEach((childSnapshot) => {
				const message = childSnapshot.val();

				if (
					(message.sender === currentUser && message.receiver === chatWith) ||
					(message.sender === chatWith && message.receiver === currentUser)
				) {
					const messageElement = document.createElement("div");
					const messageContent = document.createElement("div");
					const timestampElement = document.createElement("span");

					messageElement.classList.add("message");
					messageElement.classList.add(
						message.sender === currentUser ? "message-right" : "message-left"
					);

					messageContent.classList.add("message-content");

					// ✅ MODIFIED: Buat elemen berdasarkan tipe pesan
					if (message.type === "image") {
						const img = document.createElement("img");
						img.src = message.text;
						img.alt = "Image";
						img.classList.add("chat-image");
						img.style.maxWidth = "150px";
						img.style.borderRadius = "8px";
						img.style.cursor = "pointer";
						img.onclick = () => openMediaModal(img.src, "image");
						messageContent.appendChild(img);
					} else if (message.type === "video") {
						const video = document.createElement("video");
						video.src = message.text;
						video.controls = true;
						video.classList.add("chat-video");
						video.style.maxWidth = "150px";
						video.style.borderRadius = "8px";
						video.style.cursor = "pointer";
						video.onclick = () => openMediaModal(video.src, "video");
						messageContent.appendChild(video);
					} else if (message.type === "file") {
						const fileLink = document.createElement("a");
						fileLink.href = message.text;
						fileLink.target = "_blank";
						fileLink.textContent = "Download File";
						fileLink.classList.add("chat-file");
						messageContent.appendChild(fileLink);
					} else {
						const textElement = document.createElement("span");
						textElement.classList.add("message-text");
						textElement.innerHTML = message.text;
						messageContent.appendChild(textElement);
					}

					// Timestamp
					timestampElement.classList.add("timestamp");
					timestampElement.innerHTML = new Date(message.timestamp).toLocaleTimeString([], {
						hour: "2-digit",
						minute: "2-digit",
					});
					messageContent.appendChild(timestampElement);

					messageElement.appendChild(messageContent);
					chatBox.appendChild(messageElement);

					// Auto scroll
					chatBox.scrollTop = chatBox.scrollHeight;
				}

			});
		});

		function showNotification(message) {
			console.log("Attempting to show notification with message:", message);

			if (!("Notification" in window)) {
				console.error("This browser does not support notifications.");
			} else if (Notification.permission === "granted") {
				const notification = new Notification("New Message", {
					body: message
				});
				console.log("Notification displayed:", notification);
			} else if (Notification.permission !== "denied") {
				Notification.requestPermission().then((permission) => {
					console.log("Permission request result:", permission);
					if (permission === "granted") {
						const notification = new Notification("New Message", {
							body: message
						});
						console.log("Notification displayed after granting permission:", notification);
					}
				});
			} else {
				console.error("Notification permission denied.");
			}
		}

		// Send message
		function sendMessage() {
			const messageInput = document.getElementById("message");
			const message = messageInput.value;

			const reqId = document.getElementById('request_id').value;
			console.log(reqId);


			if (message.trim() !== "") {
				const newMessageRef = getFirebaseDatabase().ref("messages").push();
				newMessageRef.set({
					sender: currentUser,
					receiver: chatWith,
					text: message,
					timestamp: Date.now()
				});
				messageInput.value = "";

				const nama = document.getElementById('username').value;
				const userId = document.getElementById('uids').value;
				const newMessageReff = getFirebaseDatabase().ref("notifications").push();
				newMessageReff.set({
					receiver: chatWith,
					sender: nama,
					userId: userId,
					text: 'Ada pesan masuk dari ' + <?= json_encode($session_nama) ?>,
					timestamp: Date.now()
				});
			}
		}
	</script>

	<!-- open modal file -->
	<script>
		function openMediaModal(src, type) {
			const modal = document.getElementById('mediaModal');
			const content = document.getElementById('modalContent');
			content.innerHTML = ''; // Kosongkan isi sebelumnya

			if (type === 'video') {
				const video = document.createElement('video');
				video.src = src;
				video.controls = true;
				video.autoplay = true;
				video.style.maxWidth = '100%';
				video.style.maxHeight = '80vh';
				video.style.borderRadius = '8px';
				content.appendChild(video);
			} else {
				const img = document.createElement('img');
				img.src = src;
				img.style.maxWidth = '100%';
				img.style.maxHeight = '80vh';
				img.style.borderRadius = '8px';
				content.appendChild(img);
			}

			modal.style.display = 'block';
		}

		function closeModal() {
			document.getElementById('mediaModal').style.display = 'none';
		}

		document.addEventListener('keydown', function(event) {
			if (event.key === "Escape") {
				closeModal();
			}
		});
	</script>

	<!-- video call -->
	<script>
		// Elemen HTML
		const localVideo = document.getElementById("localVideo");
		const remoteVideo = document.getElementById("remoteVideo");
		const startCall = document.getElementById("startCall");
		const acceptCall = document.getElementById("acceptCall");
		const endCallBtn = document.getElementById("endCall");
		const popupVideo = document.getElementById("videoPopup");

		// ID dari URL dan input
		const request_id = document.getElementById("request_id").value;
		const user_id = document.getElementById("user_id")?.value || "";
		const username = document.getElementById("username").value;

		const uid = document.getElementById("uid").value;

		console.log("Internal request id:", request_id);
		console.log("User ID:", user_id);
		console.log("Username:", username);
		console.log("Uid:", uid);

		// Tentukan role
		const role = (username === user_id) ? "warga" : "dokter";
		console.log("Role:", role);

		// Firebase reference
		const roomRef = getFirebaseDatabase().ref("calls/" + uid);

		// firebase.database().ref("calls").on("value", snap => {
		// 	console.log("Seluruh data calls:", snap.val());
		// });

		// roomRef.child("offer").on("value", snap => {
		// 	console.log("Seluruh data calls:", snap.val());
		// });

		// Stream & peer connection
		let localStream, remoteStream, pc;
		let isRemoteDescriptionSet = false;
		let candidateQueue = [];

		// Fungsi notifikasi sederhana
		function notify(msg) {
			Swal.fire({
				icon: 'info',
				title: 'Notification',
				text: msg
			});
		}

		// Bersihkan panggilan
		function cleanup() {
			if (localStream) localStream.getTracks().forEach(track => track.stop());
			if (pc) pc.close();
			pc = null;
			remoteVideo.srcObject = null;
			localVideo.srcObject = null;
			startCall.disabled = false;
			endCallBtn.disabled = true;
			acceptCall.disabled = false;
			isRemoteDescriptionSet = false;
			candidateQueue = [];
		}

		// Setup tampilan tombol sesuai role
		startCall.style.display = "none";
		acceptCall.style.display = "none";
		endCallBtn.style.display = "none";

		if (role === "warga") {
			console.log('hai');

			setTimeout(() => {
				roomRef.child("offer").on("value", snapshot => {
					const val = snapshot.val();
					console.log("Offer deteksi perubahan (setTimeout):", val);
					if (val !== null) {
						acceptCall.style.display = "inline-block";
						Swal.fire({
							title: "Panggilan Masuk",
							text: "Dokter menghubungi Anda. Klik 'Terima Panggilan'.",
							icon: "info",
							showCancelButton: true,
							confirmButtonText: "Terima Panggilan",
							cancelButtonText: "Tutup"
						}).then((result) => {
							if (result.isConfirmed) {
								acceptCall.click();
							}
						});
					}
				});
			}, 5000); // 500ms delay

		} else {
			console.log('Hello');
			startCall.style.display = "inline-block";
			endCallBtn.style.display = "inline-block";
		}

		// Fungsi bantu: setRemoteDescription dan flush antrian ICE
		async function setRemoteDescAndFlush(sdp) {
			await pc.setRemoteDescription(new RTCSessionDescription(sdp));
			isRemoteDescriptionSet = true;
			candidateQueue.forEach(c => {
				pc.addIceCandidate(new RTCIceCandidate(c)).catch(e => console.error("ICE error:", e));
			});
			candidateQueue = [];
		}

		function showVideoPopup() {
			popupVideo.style.display = "flex";
			if (role === "dokter" || role === "warga") endCallBtn.style.display = "inline-block";
		}

		function hideVideoPopup() {
			popupVideo.style.display = "none";
		}

		// Start Call (dokter)
		startCall.addEventListener("click", async () => {
			try {
				if (pc) pc.close();
				pc = new RTCPeerConnection({
					iceServers: [{
						urls: "stun:stun.l.google.com:19302"
					}]
				});

				pc.onicecandidate = e => {
					if (e.candidate) {
						roomRef.child("candidates").push().set(JSON.stringify(e.candidate));
					}
				};

				pc.ontrack = e => {
					console.log("Track diterima:", e.track.kind);
					if (!remoteStream) {
						remoteStream = new MediaStream();
						remoteVideo.srcObject = remoteStream;
					}
					remoteStream.addTrack(e.track);
				};

				localStream = await navigator.mediaDevices.getUserMedia({
					video: true,
					audio: true
				});
				localStream.getTracks().forEach(track => pc.addTrack(track, localStream));
				localVideo.srcObject = localStream;

				const offer = await pc.createOffer();
				await pc.setLocalDescription(offer);
				await roomRef.set({
					offer: JSON.stringify(offer)
				}).then(() => {
					console.log("Offer terkirim ke Firebase.");
				}).catch((err) => {
					console.error("Gagal mengirim offer:", err);
				});

				alert("Panggilan dimulai...");
				startCall.disabled = true;
				endCallBtn.disabled = false;
			} catch (error) {
				console.error("Gagal memulai panggilan:", error);
				notify("Gagal memulai panggilan." + error.message);
			}
			showVideoPopup();
		});

		// Accept Call (warga)
		acceptCall.addEventListener("click", async () => {
			try {
				if (pc) pc.close();
				pc = new RTCPeerConnection({
					iceServers: [{
						urls: "stun:stun.l.google.com:19302"
					}]
				});

				pc.onicecandidate = e => {
					if (e.candidate) {
						roomRef.child("candidates").push().set(JSON.stringify(e.candidate));
					}
				};

				pc.ontrack = e => {
					if (!remoteStream) {
						remoteStream = new MediaStream();
						remoteVideo.srcObject = remoteStream;
					}
					remoteStream.addTrack(e.track);
				};

				localStream = await navigator.mediaDevices.getUserMedia({
					video: true,
					audio: true
				});
				localStream.getTracks().forEach(track => pc.addTrack(track, localStream));
				localVideo.srcObject = localStream;

				const offerSnapshot = await roomRef.child("offer").once("value");
				if (!offerSnapshot.exists()) {
					return notify("Belum ada panggilan.");
				}

				const offer = JSON.parse(offerSnapshot.val());
				await setRemoteDescAndFlush(offer);

				const answer = await pc.createAnswer();
				await pc.setLocalDescription(answer);
				await roomRef.child("answer").set(JSON.stringify(answer));

				alert("Terhubung ke dokter");
				acceptCall.disabled = true;
			} catch (error) {
				console.error("Gagal menerima panggilan:", error);
				notify("Terjadi kesalahan saat menerima panggilan.");
			}

			showVideoPopup();
		});

		// Dokter menerima answer dari warga
		roomRef.child("answer").on("value", async snapshot => {
			if (role === 'dokter' && snapshot.exists() && pc) {
				const answer = JSON.parse(snapshot.val());
				await setRemoteDescAndFlush(answer);
			}
		});

		// Terima ICE candidates (dengan buffer)
		roomRef.child("candidates").on("child_added", snapshot => {
			const candidate = JSON.parse(snapshot.val());
			if (!isRemoteDescriptionSet) {
				candidateQueue.push(candidate);
			} else if (pc && pc.signalingState !== 'closed') {
				pc.addIceCandidate(new RTCIceCandidate(candidate)).catch(e => console.error("ICE error:", e));
			}
		});

		// Akhiri panggilan (hanya dokter)
		endCallBtn.addEventListener("click", async () => {
			try {
				await roomRef.remove();
				cleanup();
				notify("Panggilan diakhiri");
			} catch (error) {
				console.error("Gagal mengakhiri panggilan:", error);
				notify("Terjadi kesalahan saat mengakhiri panggilan.");
			}
			hideVideoPopup();
			acceptCall.style.display = "none";
		});

		window.addEventListener("beforeunload", () => {
			roomRef.remove();
		});
	</script>
</body>

</html>
