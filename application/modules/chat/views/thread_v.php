<?php
$request_id = isset($request_id) ? (int) $request_id : 0;
$can_send = !empty($can_send);
$current_user_id = isset($current_user_id) ? (int) $current_user_id : 0;
$request_status = isset($request->request_status) ? (string) $request->request_status : '';
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

		@media (max-width: 520px) {
			.chat-shell {
				max-width: none;
				border-radius: 0;
				box-shadow: none;
			}

			.chat-bubble {
				max-width: min(300px, calc(100vw - 114px));
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
