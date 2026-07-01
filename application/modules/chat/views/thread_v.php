<?php
$request_id = isset($request_id) ? (int) $request_id : 0;
$can_send = !empty($can_send);
$current_user_id = isset($current_user_id) ? (int) $current_user_id : 0;
$current_role = isset($current_role) ? (string) $current_role : '';
$request_status = isset($request->request_status) ? (string) $request->request_status : '';
$is_nakes_role = in_array($current_role, array('dokter', 'nakes'), true);
$can_start_call = $is_nakes_role
	&& $request_status === 'Accepted'
	&& function_exists('doclinc_request_is_handled_by_nakes')
	&& doclinc_request_is_handled_by_nakes($request, $current_user_id);
$call_token_url = base_url('home_nakes/livekit_token');
$queue_code = doclinc_request_queue_code($request);
$queue_display = 'No. Antrian: ' . $queue_code;
if ($current_role !== 'dokter') {
	$queue_display = doclinc_request_puskesmas_label($request) . ' · ' . doclinc_request_queue_number_label($request);
}
$back_url = ($current_role === 'dokter') ? base_url('home_nakes') : base_url('home#riwayat');
$status_label = 'Chat belum tersedia';
if ($request_status === 'Accepted') {
	$status_label = 'Chat aktif';
} elseif ($request_status === 'Completed') {
	$status_label = 'Riwayat chat';
} elseif ($request_status === 'Cancelled') {
	$status_label = 'Riwayat chat dibatalkan';
}
$readonly_message = in_array($request_status, array('Completed', 'Cancelled'), true)
	? 'Konsultasi sudah selesai. Riwayat chat hanya dapat dibaca.'
	: 'Chat ini hanya dapat dibaca.';
$asset_base = base_url('assets/doclinc_ui/chat/');
$partner_name = 'Petugas Puskesmas';
$partner_subtitle = 'Konsultasi kesehatan';
if ($current_role === 'dokter') {
	$partner_name = isset($request->nama) && $request->nama !== '' ? $request->nama : 'Pasien';
	$partner_subtitle = isset($request->assigned_puskesmas_name) && $request->assigned_puskesmas_name !== '' ? $request->assigned_puskesmas_name : 'Permintaan konsultasi';
} else {
	$partner_name = isset($request->assigned_puskesmas_name) && $request->assigned_puskesmas_name !== '' ? $request->assigned_puskesmas_name : (isset($request->nama_dokter) && $request->nama_dokter !== '' ? $request->nama_dokter : 'Petugas Puskesmas');
	$partner_subtitle = 'Nakes akan membantu konsultasi Anda';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Chat Konsultasi</title>
	<style>
		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			background: #ececec;
			color: #333333;
			font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
			line-height: 1.45;
		}

		.chat-shell {
			max-width: 414px;
			margin: 0 auto;
			min-height: 100vh;
			min-height: 100dvh;
			display: flex;
			flex-direction: column;
			background: #f7f7f7;
			border-radius: 10px;
			overflow: hidden;
			box-shadow: 0 18px 40px rgba(0, 0, 0, 0.08);
		}

		.chat-header {
			height: 75px;
			background: #ffffff;
			color: #333333;
			padding: 0 20px;
			display: flex;
			align-items: center;
			justify-content: center;
			position: relative;
			top: 0;
			z-index: 4;
			box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
		}

		.chat-back {
			position: absolute;
			left: 20px;
			top: 38px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 22px;
			height: 22px;
			border: 0;
			background: transparent;
			color: #333333;
			line-height: 1;
			text-decoration: none;
		}

		.chat-back img {
			width: 15px;
			height: 12px;
			transform: rotate(180deg);
		}

		.chat-back:focus-visible,
		.chat-send:focus-visible,
		.chat-upload:focus-visible,
		.chat-attach:focus-visible,
		.chat-input:focus-visible {
			outline: 3px solid rgba(67, 122, 19, 0.24);
			outline-offset: 2px;
		}

		.chat-title {
			color: #333333;
			font-weight: 700;
			font-size: 20px;
			line-height: 24px;
		}

		.chat-profile {
			height: 80px;
			background: #d5f0e3;
			display: flex;
			align-items: center;
			gap: 13px;
			padding: 10px 20px;
			flex: 0 0 auto;
		}

		.chat-avatar {
			width: 61px;
			height: 60px;
			border-radius: 15px;
			object-fit: cover;
			background: #ffffff;
			flex: 0 0 auto;
		}

		.chat-profile-text {
			min-width: 0;
			flex: 1;
		}

		.chat-name {
			margin: 0 0 3px;
			color: #333333;
			font-size: 15px;
			font-weight: 700;
			line-height: 19px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.chat-subtitle {
			margin: 0;
			color: #8c8c8c;
			font-size: 13px;
			line-height: 17px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.chat-status {
			display: inline-flex;
			align-items: center;
			width: fit-content;
			margin-top: 5px;
			padding: 3px 8px;
			border-radius: 999px;
			background: rgba(67, 122, 19, 0.12);
			color: #437a13;
			font-size: 11px;
			font-weight: 700;
		}

		.chat-list {
			flex: 1;
			overflow-y: auto;
			padding: 25px 20px 18px;
			background: #f7f7f7;
		}

		.chat-message-row {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			gap: 14px;
			margin-bottom: 21px;
			width: 100%;
		}

		.chat-message-row.mine {
			justify-content: space-between;
		}

		.chat-bubble {
			max-width: min(300px, 86%);
			border-radius: 15px;
			padding: 12px 14px;
			background: #f9f0da;
			border: 0;
			box-shadow: none;
			white-space: pre-wrap;
			word-break: break-word;
			color: #333333;
			font-size: 14px;
			line-height: 20px;
		}

		.chat-bubble.mine {
			background: #e1f0d6;
		}

		.chat-image {
			display: block;
			max-width: 100%;
			max-height: 260px;
			border-radius: 13px;
			object-fit: contain;
			background: rgba(255, 255, 255, 0.45);
		}

		.chat-image-link {
			display: block;
			line-height: 0;
		}

		.chat-time {
			width: 52px;
			flex: 0 0 52px;
			font-size: 11px;
			color: #a8a8a8;
			margin-top: 3px;
			text-align: left;
			white-space: normal;
			line-height: 14px;
		}

		.chat-message-row:not(.mine) .chat-time {
			text-align: right;
		}

		.chat-form {
			min-height: 75px;
			background: #ffffff;
			padding: 17px 20px 18px;
			border-top: 0;
			position: relative;
			bottom: 0;
			box-shadow: 0 -1px 4px rgba(0, 0, 0, 0.04);
			flex: 0 0 auto;
		}

		.chat-input-row {
			display: flex;
			align-items: center;
			gap: 6px;
			min-height: 40px;
			border: 1px solid #a8a8a8;
			border-radius: 20px;
			padding: 0 9px 0 10px;
			background: #ffffff;
		}

		.chat-input {
			flex: 1;
			min-width: 0;
			height: 38px;
			min-height: 38px;
			max-height: 86px;
			resize: none;
			border: 0;
			border-radius: 0;
			padding: 9px 4px;
			font: inherit;
			font-size: 14px;
			line-height: 20px;
			background: transparent;
			color: #333333;
		}

		.chat-input:focus {
			outline: 0;
		}

		.chat-icon-button,
		.chat-send {
			border: 0;
			background: transparent;
			width: 28px;
			height: 28px;
			padding: 6px;
			cursor: pointer;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			flex: 0 0 auto;
		}

		.chat-icon-button img,
		.chat-send img {
			display: block;
			width: 16px;
			height: 16px;
		}

		.chat-upload img {
			width: 18px;
			height: 18px;
		}

		.chat-send:disabled,
		.chat-icon-button:disabled,
		.chat-upload:disabled {
			cursor: not-allowed;
			opacity: 0.65;
		}

		.chat-muted,
		.chat-error,
		.chat-readonly {
			text-align: center;
			font-size: 13px;
			margin-top: 24px;
		}

		.chat-muted {
			color: #6c757d;
			background: #fff;
			border: 1px solid #eeeeee;
			border-radius: 15px;
			padding: 12px;
		}

		.chat-error {
			color: #b42318;
		}

		.chat-readonly {
			margin: 0;
			padding: 12px;
			border-radius: 20px;
			background: #f7f7f7;
			color: #606060;
			border: 1px solid #e5e5e5;
		}

		.chat-call-actions {
			display: flex;
			align-items: center;
			gap: 8px;
			flex: 0 0 auto;
		}

		.chat-call-button {
			border: 1px solid rgba(67, 122, 19, 0.22);
			background: #ffffff;
			color: #315d0d;
			min-height: 38px;
			border-radius: 999px;
			padding: 8px 12px;
			font-size: 12px;
			font-weight: 800;
			cursor: pointer;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 7px;
			box-shadow: 0 8px 18px rgba(49, 93, 13, 0.08);
			white-space: nowrap;
		}

		.chat-call-button:hover,
		.chat-call-button:focus-visible {
			background: #eef8e8;
			outline: 0;
		}

		.chat-call-icon {
			font-size: 15px;
			line-height: 1;
		}

		.doclinc-call-modal {
			position: fixed;
			inset: 0;
			z-index: 40;
			display: none;
			align-items: flex-end;
			justify-content: center;
			background: rgba(17, 24, 39, 0.58);
			padding: 16px;
		}

		.doclinc-call-modal.is-open {
			display: flex;
		}

		.doclinc-call-panel {
			width: min(100%, 414px);
			max-height: min(92vh, 760px);
			overflow: hidden;
			border-radius: 28px 28px 18px 18px;
			background: #101820;
			color: #ffffff;
			box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
			display: flex;
			flex-direction: column;
		}

		.doclinc-call-head {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			gap: 14px;
			padding: 18px 18px 12px;
		}

		.doclinc-call-title {
			margin: 0;
			font-size: 18px;
			font-weight: 800;
		}

		.doclinc-call-status {
			margin-top: 4px;
			color: rgba(255, 255, 255, 0.72);
			font-size: 12px;
			line-height: 16px;
		}

		.doclinc-call-status.is-error {
			color: #fecaca;
		}

		.doclinc-call-close {
			width: 38px;
			height: 38px;
			border-radius: 999px;
			border: 1px solid rgba(255, 255, 255, 0.16);
			background: rgba(255, 255, 255, 0.08);
			color: #ffffff;
			font-size: 22px;
			line-height: 1;
			cursor: pointer;
		}

		.doclinc-call-stage {
			position: relative;
			min-height: 330px;
			background: radial-gradient(circle at top, #263445 0, #111827 54%, #0b1117 100%);
			margin: 0 14px;
			border-radius: 22px;
			overflow: hidden;
		}

		.doclinc-call-remote {
			position: absolute;
			inset: 0;
			display: flex;
			align-items: center;
			justify-content: center;
			background: rgba(255, 255, 255, 0.03);
		}

		.doclinc-call-remote video,
		.doclinc-call-remote audio {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		.doclinc-call-empty {
			max-width: 230px;
			text-align: center;
			color: rgba(255, 255, 255, 0.78);
			font-size: 14px;
			font-weight: 700;
			line-height: 20px;
			padding: 16px;
		}

		.doclinc-call-local {
			position: absolute;
			right: 14px;
			bottom: 14px;
			width: 96px;
			height: 132px;
			border-radius: 18px;
			overflow: hidden;
			background: #1f2937;
			border: 2px solid rgba(255, 255, 255, 0.88);
			box-shadow: 0 12px 28px rgba(0, 0, 0, 0.28);
		}

		.doclinc-call-local video {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		.doclinc-call-prejoin {
			padding: 18px;
			background: #ffffff;
			color: #1f2937;
		}

		.doclinc-call-prejoin-title {
			margin: 0 0 4px;
			font-size: 15px;
			font-weight: 800;
		}

		.doclinc-call-prejoin-text {
			margin: 0 0 14px;
			color: #6b7280;
			font-size: 13px;
			line-height: 18px;
		}

		.doclinc-call-join {
			width: 100%;
			min-height: 50px;
			border: 0;
			border-radius: 999px;
			background: #437a13;
			color: #ffffff;
			font-weight: 800;
			cursor: pointer;
		}

		.doclinc-call-controls {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 12px;
			padding: 16px;
			background: #101820;
		}

		.doclinc-call-control {
			width: 52px;
			height: 52px;
			border-radius: 999px;
			border: 1px solid rgba(255, 255, 255, 0.15);
			background: rgba(255, 255, 255, 0.11);
			color: #ffffff;
			font-size: 18px;
			cursor: pointer;
		}

		.doclinc-call-control.is-off {
			background: rgba(255, 255, 255, 0.9);
			color: #111827;
		}

		.doclinc-call-control.end-call {
			background: #dc2626;
			border-color: #dc2626;
		}

		.is-hidden {
			display: none !important;
		}

		@media (max-width: 520px) {
			.chat-shell {
				max-width: none;
				border-radius: 0;
				box-shadow: none;
			}

			.chat-bubble {
				max-width: min(300px, calc(100vw - 114px));
			}

			.chat-profile {
				height: auto;
				min-height: 80px;
				align-items: flex-start;
			}

			.chat-call-actions {
				flex-direction: column;
				align-items: stretch;
				gap: 6px;
			}

			.chat-call-button {
				min-height: 36px;
				padding: 7px 10px;
				font-size: 11px;
			}
		}
	</style>
</head>

<body>
	<div class="chat-shell">
		<header class="chat-header">
			<a href="<?= html_escape($back_url); ?>" class="chat-back" aria-label="Kembali">
				<img src="<?= html_escape($asset_base . 'icon-chat-back.svg'); ?>" alt="">
			</a>
			<div class="chat-title">Konsultasi</div>
		</header>

		<section class="chat-profile" aria-label="Informasi konsultasi">
			<img class="chat-avatar" src="<?= html_escape($asset_base . 'doctor-placeholder.jpg'); ?>" alt="Profil layanan">
			<div class="chat-profile-text">
				<p class="chat-name"><?= html_escape($partner_name); ?></p>
				<p class="chat-subtitle"><?= html_escape($partner_subtitle); ?></p>
				<div class="chat-status"><?= html_escape($status_label); ?> · <?= html_escape($queue_display); ?></div>
			</div>
			<?php if ($can_start_call) : ?>
				<div class="chat-call-actions" aria-label="Aksi panggilan">
					<button type="button" class="chat-call-button doclinc-call-start" data-call-mode="audio" data-request-id="<?= html_escape($request_id); ?>">
						<span>Panggilan Suara</span>
					</button>
					<button type="button" class="chat-call-button doclinc-call-start" data-call-mode="video" data-request-id="<?= html_escape($request_id); ?>">
						<span>Panggilan Video</span>
					</button>
				</div>
			<?php endif; ?>
		</section>

		<main class="chat-list" id="chatMessages">
			<div class="chat-muted">Memuat pesan...</div>
		</main>

		<form class="chat-form" id="chatForm">
			<?php if ($can_send) : ?>
				<div class="chat-input-row">
					<input type="file" id="imageInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" hidden>
					<button class="chat-icon-button chat-upload" id="imageButton" type="button" aria-label="Kirim gambar">
						<img src="<?= html_escape($asset_base . 'icon-chat-camera.svg'); ?>" alt="">
					</button>
					<button class="chat-icon-button chat-attach" id="attachmentButton" type="button" aria-label="Lampirkan gambar">
						<img src="<?= html_escape($asset_base . 'icon-chat-attach.svg'); ?>" alt="">
					</button>
					<textarea class="chat-input" id="messageText" rows="1" maxlength="2000" placeholder="Tulis pesan..." required></textarea>
					<button class="chat-send" type="submit" aria-label="Kirim pesan">
						<img src="<?= html_escape($asset_base . 'icon-chat-send.svg'); ?>" alt="">
					</button>
				</div>
			<?php else : ?>
				<div class="chat-readonly"><?= html_escape($readonly_message); ?></div>
			<?php endif; ?>
		</form>
	</div>

	<?php if ($can_start_call) : ?>
		<div class="doclinc-call-modal" id="doclincCallModal" role="dialog" aria-modal="true" aria-labelledby="doclincCallTitle" aria-hidden="true">
			<div class="doclinc-call-panel">
				<div class="doclinc-call-head">
					<div>
						<h2 class="doclinc-call-title" id="doclincCallTitle">Panggilan Konsultasi</h2>
						<div class="doclinc-call-status" id="doclincCallStatus">Siap bergabung</div>
					</div>
					<button type="button" class="doclinc-call-close" id="doclincCallClose" aria-label="Tutup">&times;</button>
				</div>
				<div class="doclinc-call-stage">
					<div class="doclinc-call-remote" id="doclincCallRemote">
						<div class="doclinc-call-empty" id="doclincCallEmpty">Menunggu lawan bicara bergabung</div>
					</div>
					<div class="doclinc-call-local is-hidden" id="doclincCallLocal"></div>
				</div>
				<div class="doclinc-call-prejoin" id="doclincCallPrejoin">
					<p class="doclinc-call-prejoin-title">Panggilan untuk konsultasi aktif</p>
					<p class="doclinc-call-prejoin-text">Pastikan kamera dan mikrofon perangkat dapat digunakan.</p>
					<button type="button" class="doclinc-call-join" id="doclincCallJoin">Gabung Sekarang</button>
				</div>
				<div class="doclinc-call-controls is-hidden" id="doclincCallControls">
					<button type="button" class="doclinc-call-control" id="doclincCallMic" title="Mikrofon" aria-label="Mikrofon">Mic</button>
					<button type="button" class="doclinc-call-control" id="doclincCallCamera" title="Kamera" aria-label="Kamera">Cam</button>
					<button type="button" class="doclinc-call-control" id="doclincCallSwitch" title="Ganti kamera" aria-label="Ganti kamera">Flip</button>
					<button type="button" class="doclinc-call-control end-call" id="doclincCallEnd" title="Akhiri" aria-label="Akhiri">End</button>
				</div>
			</div>
		</div>
		<script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
	<?php else : ?>
		<?php /* TODO #16D: render incoming call invitation here after reliable call invite signaling exists. */ ?>
	<?php endif; ?>

	<?php if ($can_start_call) : ?>
		<script>
			(function() {
				const tokenUrl = <?= json_encode($call_token_url); ?>;
				const requestId = <?= json_encode($request_id); ?>;
				const state = {
					room: null,
					mode: 'video',
					micEnabled: true,
					cameraEnabled: true,
					facingMode: 'user',
					localTracks: []
				};
				const elements = {};

				function byId(id) {
					return document.getElementById(id);
				}

				function sdk() {
					return window.LivekitClient || window.LiveKitClient || window.livekitClient || null;
				}

				function cacheElements() {
					elements.modal = byId('doclincCallModal');
					elements.status = byId('doclincCallStatus');
					elements.remote = byId('doclincCallRemote');
					elements.empty = byId('doclincCallEmpty');
					elements.local = byId('doclincCallLocal');
					elements.prejoin = byId('doclincCallPrejoin');
					elements.controls = byId('doclincCallControls');
					elements.join = byId('doclincCallJoin');
					elements.mic = byId('doclincCallMic');
					elements.camera = byId('doclincCallCamera');
					elements.switchCamera = byId('doclincCallSwitch');
					elements.end = byId('doclincCallEnd');
					elements.close = byId('doclincCallClose');
				}

				function setStatus(message, isError) {
					if (!elements.status) {
						return;
					}
					elements.status.textContent = message || '';
					elements.status.classList.toggle('is-error', !!isError);
				}

				function setCallActive(active) {
					if (elements.prejoin) {
						elements.prejoin.classList.toggle('is-hidden', !!active);
					}
					if (elements.controls) {
						elements.controls.classList.toggle('is-hidden', !active);
					}
				}

				function clearNode(node) {
					if (!node) {
						return;
					}
					while (node.firstChild) {
						node.removeChild(node.firstChild);
					}
				}

				function updateEmptyState() {
					if (!elements.empty || !elements.remote) {
						return;
					}
					elements.empty.classList.toggle('is-hidden', !!elements.remote.querySelector('video,audio'));
				}

				function stopLocalTracks() {
					state.localTracks.forEach(function(track) {
						try {
							track.stop();
						} catch (error) {}
					});
					state.localTracks = [];
					if (elements.local) {
						clearNode(elements.local);
						elements.local.classList.add('is-hidden');
					}
				}

				function cleanupCall(message) {
					if (state.room) {
						try {
							state.room.disconnect();
						} catch (error) {}
					}
					state.room = null;
					stopLocalTracks();
					if (elements.remote) {
						Array.prototype.slice.call(elements.remote.querySelectorAll('video,audio')).forEach(function(node) {
							node.remove();
						});
					}
					updateEmptyState();
					setCallActive(false);
					setStatus(message || 'Panggilan berakhir');
				}

				function openModal(mode) {
					cacheElements();
					if (!elements.modal) {
						return;
					}
					cleanupCall('Siap bergabung');
					state.mode = mode === 'audio' ? 'audio' : 'video';
					state.micEnabled = true;
					state.cameraEnabled = state.mode === 'video';
					if (elements.camera) {
						elements.camera.classList.toggle('is-off', state.mode !== 'video');
					}
					elements.modal.classList.add('is-open');
					elements.modal.setAttribute('aria-hidden', 'false');
				}

				function closeModal() {
					cleanupCall('Panggilan berakhir');
					if (elements.modal) {
						elements.modal.classList.remove('is-open');
						elements.modal.setAttribute('aria-hidden', 'true');
					}
				}

				function attachTrack(track, container) {
					if (!track || !container || typeof track.attach !== 'function') {
						return;
					}
					const element = track.attach();
					if (!element) {
						return;
					}
					element.autoplay = true;
					element.playsInline = true;
					container.appendChild(element);
					updateEmptyState();
				}

				function detachTrack(track) {
					if (!track || typeof track.detach !== 'function') {
						return;
					}
					track.detach().forEach(function(element) {
						if (element && element.parentNode) {
							element.parentNode.removeChild(element);
						}
					});
					updateEmptyState();
				}

				function wireRoom(room) {
					const LiveKit = sdk();
					const events = LiveKit && LiveKit.RoomEvent ? LiveKit.RoomEvent : {};
					room.on(events.TrackSubscribed || 'trackSubscribed', function(track) {
						attachTrack(track, elements.remote);
					});
					room.on(events.TrackUnsubscribed || 'trackUnsubscribed', detachTrack);
					room.on(events.ParticipantConnected || 'participantConnected', function() {
						setStatus('Terhubung');
					});
					room.on(events.ParticipantDisconnected || 'participantDisconnected', function() {
						setStatus('Menunggu lawan bicara bergabung');
						updateEmptyState();
					});
					room.on(events.Disconnected || 'disconnected', function() {
						setStatus('Panggilan terputus');
						setCallActive(false);
					});
					room.on(events.Reconnecting || 'reconnecting', function() {
						setStatus('Menyambungkan ulang...');
					});
					room.on(events.Reconnected || 'reconnected', function() {
						setStatus('Terhubung kembali');
					});
				}

				function publishExistingParticipants(room) {
					if (!room || !room.remoteParticipants) {
						return;
					}
					room.remoteParticipants.forEach(function(participant) {
						if (!participant || !participant.trackPublications) {
							return;
						}
						participant.trackPublications.forEach(function(publication) {
							if (publication && publication.track) {
								attachTrack(publication.track, elements.remote);
							}
						});
					});
				}

				function fetchToken() {
					const formData = new FormData();
					formData.append('request_id', requestId);
					return fetch(tokenUrl, {
						method: 'POST',
						body: formData,
						credentials: 'same-origin',
						headers: {
							'X-Requested-With': 'XMLHttpRequest'
						}
					}).then(function(response) {
						return response.json().catch(function() {
							return {};
						});
					});
				}

				function createLocalTrack(kind) {
					const LiveKit = sdk();
					if (!LiveKit) {
						return Promise.reject(new Error('SDK panggilan belum tersedia'));
					}
					if (kind === 'audio') {
						return LiveKit.createLocalAudioTrack();
					}
					return LiveKit.createLocalVideoTrack({
						facingMode: state.facingMode
					});
				}

				function publishTrack(track) {
					if (!track || !state.room || !state.room.localParticipant) {
						return Promise.resolve();
					}
					state.localTracks.push(track);
					return Promise.resolve(state.room.localParticipant.publishTrack(track));
				}

				function showLocalPreview(track) {
					if (!track || !elements.local || track.kind !== 'video') {
						return;
					}
					clearNode(elements.local);
					attachTrack(track, elements.local);
					elements.local.classList.remove('is-hidden');
				}

				function joinCall() {
					if (state.room) {
						return;
					}
					const LiveKit = sdk();
					if (!LiveKit || !LiveKit.Room) {
						setStatus('SDK panggilan belum tersedia', true);
						return;
					}
					if (!requestId) {
						setStatus('Data konsultasi tidak valid', true);
						return;
					}
					setStatus('Menyiapkan panggilan...');
					fetchToken().then(function(response) {
						if (!response || !response.success || !response.token || !response.ws_url) {
							setStatus(response && response.message ? response.message : 'Panggilan belum dapat dimulai', true);
							return null;
						}
						const room = new LiveKit.Room({
							adaptiveStream: true,
							dynacast: true
						});
						state.room = room;
						wireRoom(room);
						return room.connect(response.ws_url, response.token).then(function() {
							setCallActive(true);
							setStatus('Menunggu lawan bicara bergabung');
							publishExistingParticipants(room);
							return createLocalTrack('audio').then(publishTrack).catch(function() {
								state.micEnabled = false;
								if (elements.mic) {
									elements.mic.classList.add('is-off');
								}
								setStatus('Mikrofon tidak tersedia');
							});
						}).then(function() {
							if (state.mode !== 'video') {
								return null;
							}
							return createLocalTrack('video').then(function(track) {
								showLocalPreview(track);
								return publishTrack(track);
							}).catch(function() {
								state.cameraEnabled = false;
								if (elements.camera) {
									elements.camera.classList.add('is-off');
								}
								setStatus('Kamera tidak tersedia, panggilan suara aktif');
							});
						});
					}).catch(function(error) {
						cleanupCall(error && error.message ? error.message : 'Gagal tersambung');
					});
				}

				function findLocalTrack(kind) {
					for (let index = 0; index < state.localTracks.length; index++) {
						if (state.localTracks[index] && state.localTracks[index].kind === kind) {
							return state.localTracks[index];
						}
					}
					return null;
				}

				function toggleTrack(kind, button) {
					const track = findLocalTrack(kind);
					const enabledKey = kind === 'audio' ? 'micEnabled' : 'cameraEnabled';
					if (track) {
						state[enabledKey] = !state[enabledKey];
						if (state[enabledKey] && typeof track.unmute === 'function') {
							track.unmute();
						} else if (!state[enabledKey] && typeof track.mute === 'function') {
							track.mute();
						}
						if (button) {
							button.classList.toggle('is-off', !state[enabledKey]);
						}
						return;
					}
					createLocalTrack(kind).then(function(newTrack) {
						if (kind === 'video') {
							showLocalPreview(newTrack);
						}
						state[enabledKey] = true;
						if (button) {
							button.classList.remove('is-off');
						}
						return publishTrack(newTrack);
					}).catch(function() {
						setStatus(kind === 'audio' ? 'Mikrofon tidak tersedia' : 'Kamera tidak tersedia', true);
					});
				}

				function switchCamera() {
					const oldTrack = findLocalTrack('video');
					if (!oldTrack || !state.room || !state.room.localParticipant) {
						return;
					}
					state.facingMode = state.facingMode === 'user' ? 'environment' : 'user';
					try {
						state.room.localParticipant.unpublishTrack(oldTrack);
					} catch (error) {}
					state.localTracks = state.localTracks.filter(function(track) {
						return track !== oldTrack;
					});
					try {
						oldTrack.stop();
					} catch (error) {}
					createLocalTrack('video').then(function(track) {
						showLocalPreview(track);
						return publishTrack(track);
					}).catch(function() {
						setStatus('Tidak dapat mengganti kamera', true);
					});
				}

				document.addEventListener('DOMContentLoaded', function() {
					cacheElements();
					document.querySelectorAll('.doclinc-call-start').forEach(function(button) {
						button.addEventListener('click', function(event) {
							event.preventDefault();
							openModal(button.getAttribute('data-call-mode'));
						});
					});
					if (elements.join) {
						elements.join.addEventListener('click', joinCall);
					}
					if (elements.mic) {
						elements.mic.addEventListener('click', function() {
							toggleTrack('audio', elements.mic);
						});
					}
					if (elements.camera) {
						elements.camera.addEventListener('click', function() {
							toggleTrack('video', elements.camera);
						});
					}
					if (elements.switchCamera) {
						elements.switchCamera.addEventListener('click', switchCamera);
					}
					if (elements.end) {
						elements.end.addEventListener('click', closeModal);
					}
					if (elements.close) {
						elements.close.addEventListener('click', closeModal);
					}
					if (elements.modal) {
						elements.modal.addEventListener('click', function(event) {
							if (event.target === elements.modal) {
								closeModal();
							}
						});
					}
				});
			})();
		</script>
	<?php endif; ?>

	<script>
		const requestId = <?= json_encode($request_id); ?>;
		const currentUserId = <?= json_encode($current_user_id); ?>;
		const canSend = <?= json_encode($can_send); ?>;
		const isReadOnly = <?= json_encode(!$can_send); ?>;
		const messagesUrl = <?= json_encode(base_url('chat/messages')); ?>;
		const sendUrl = <?= json_encode(base_url('chat/send')); ?>;
		const imageUploadUrl = <?= json_encode(base_url('chat/foto')); ?>;
		const markReadUrl = <?= json_encode(base_url('chat/mark_read')); ?>;
		let lastMessageId = 0;
		let hasLoaded = false;

		function formatDate(value) {
			if (!value) {
				return '';
			}
			const date = new Date(value.replace(' ', 'T'));
			if (isNaN(date.getTime())) {
				return '';
			}
			return date.toLocaleTimeString('id-ID', {
				hour: '2-digit',
				minute: '2-digit'
			});
		}

		function isSafeImageUrl(value) {
			if (!value) {
				return false;
			}
			try {
				const parsed = new URL(value, window.location.origin);
				return parsed.origin === window.location.origin &&
					parsed.pathname.indexOf('/uploads/chat_images/') === 0 &&
					/\.(jpe?g|png|webp)$/i.test(parsed.pathname);
			} catch (error) {
				return false;
			}
		}

		function appendMessage(message) {
			const list = document.getElementById('chatMessages');
			if (!list) {
				return;
			}
			if (!hasLoaded) {
				list.innerHTML = '';
				hasLoaded = true;
			}

			const isMine = parseInt(message.sender_user_id, 10) === currentUserId;
			const row = document.createElement('div');
			row.className = 'chat-message-row' + (isMine ? ' mine' : '');

			const bubble = document.createElement('div');
			bubble.className = 'chat-bubble' + (isMine ? ' mine' : '');

			const time = document.createElement('div');
			time.className = 'chat-time';
			time.textContent = formatDate(message.created_at);

			if (message.message_type === 'image' && isSafeImageUrl(message.attachment_url)) {
				const link = document.createElement('a');
				link.className = 'chat-image-link';
				link.href = message.attachment_url;
				link.target = '_blank';
				link.rel = 'noopener';

				const image = document.createElement('img');
				image.className = 'chat-image';
				image.src = message.attachment_url;
				image.alt = 'Gambar konsultasi';
				link.appendChild(image);
				bubble.appendChild(link);
			} else {
				const text = document.createElement('div');
				text.textContent = message.message_text || '';
				bubble.appendChild(text);
			}

			if (isMine) {
				row.appendChild(time);
				row.appendChild(bubble);
			} else {
				row.appendChild(bubble);
				row.appendChild(time);
			}

			list.appendChild(row);
			list.scrollTop = list.scrollHeight;
			lastMessageId = Math.max(lastMessageId, parseInt(message.message_id, 10) || 0);
		}

		function loadMessages() {
			fetch(messagesUrl + '?request_id=' + encodeURIComponent(requestId) + '&after_id=' + encodeURIComponent(lastMessageId), {
					credentials: 'same-origin'
				})
				.then(function(response) {
					if (!response.ok) {
						throw new Error('failed');
					}
					return response.json();
				})
				.then(function(data) {
					if (!data || data.status !== 'success') {
						return;
					}
					if (!hasLoaded && (!data.messages || data.messages.length === 0)) {
						document.getElementById('chatMessages').innerHTML = '<div class="chat-muted">Belum ada pesan pada konsultasi ini.</div>';
						hasLoaded = true;
					}
					(data.messages || []).forEach(appendMessage);
				})
				.catch(function() {
					if (!hasLoaded) {
						document.getElementById('chatMessages').innerHTML = '<div class="chat-error">Chat tidak tersedia.</div>';
						hasLoaded = true;
					}
				});
		}

		function markRead() {
			const formData = new FormData();
			formData.append('request_id', requestId);
			fetch(markReadUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			});
		}

		document.addEventListener('DOMContentLoaded', function() {
			loadMessages();
			markRead();
			if (isReadOnly) {
				window.setTimeout(loadMessages, 30000);
			} else {
				setInterval(loadMessages, 7000);
				setInterval(markRead, 15000);
			}
		});

		const chatForm = document.getElementById('chatForm');
		if (chatForm && canSend) {
			chatForm.addEventListener('submit', function(event) {
				event.preventDefault();
				const input = document.getElementById('messageText');
				const sendButton = chatForm.querySelector('.chat-send');
				const text = input ? input.value.trim() : '';
				if (!text) {
					return;
				}

				if (sendButton) {
					sendButton.disabled = true;
				}

				const formData = new FormData();
				formData.append('request_id', requestId);
				formData.append('message_text', text);
				fetch(sendUrl, {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					})
					.then(function(response) {
						return response.json();
					})
					.then(function(data) {
						if (data && data.status === 'success' && data.message) {
							input.value = '';
							appendMessage(data.message);
						}
					})
					.finally(function() {
						if (sendButton) {
							sendButton.disabled = false;
						}
					});
			});

			const imageInput = document.getElementById('imageInput');
			const imageButton = document.getElementById('imageButton');
			const attachmentButton = document.getElementById('attachmentButton');
			if (imageButton && imageInput) {
				imageButton.addEventListener('click', function() {
					imageInput.click();
				});

				if (attachmentButton) {
					attachmentButton.addEventListener('click', function() {
						imageInput.click();
					});
				}

				imageInput.addEventListener('change', function() {
					const file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
					if (!file) {
						return;
					}

					const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
					if (allowedTypes.indexOf(file.type) === -1) {
						alert('Format gambar tidak didukung.');
						imageInput.value = '';
						return;
					}

					if (file.size > 4 * 1024 * 1024) {
						alert('Ukuran gambar maksimal 4 MB.');
						imageInput.value = '';
						return;
					}

					imageButton.disabled = true;
					if (attachmentButton) {
						attachmentButton.disabled = true;
					}
					const formData = new FormData();
					formData.append('request_id', requestId);
					formData.append('foto', file);
					fetch(imageUploadUrl, {
							method: 'POST',
							body: formData,
							credentials: 'same-origin'
						})
						.then(function(response) {
							return response.json();
						})
						.then(function(data) {
							if (data && data.status === 'success' && data.message) {
								appendMessage(data.message);
							} else {
								alert(data && data.message ? data.message : 'Gambar tidak dapat dikirim.');
							}
						})
						.catch(function() {
							alert('Gambar tidak dapat dikirim.');
						})
						.finally(function() {
							imageButton.disabled = false;
							if (attachmentButton) {
								attachmentButton.disabled = false;
							}
							imageInput.value = '';
						});
				});
			}
		}
	</script>
</body>

</html>
