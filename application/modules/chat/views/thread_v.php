<?php
$request_id = isset($request_id) ? (int) $request_id : 0;
$can_send = !empty($can_send);
$current_user_id = isset($current_user_id) ? (int) $current_user_id : 0;
$request_status = isset($request->request_status) ? (string) $request->request_status : '';
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
			background: #eef5f2;
			color: #20342d;
			font-family: Arial, sans-serif;
			line-height: 1.45;
		}

		.chat-shell {
			max-width: 760px;
			margin: 0 auto;
			min-height: 100vh;
			min-height: 100dvh;
			display: flex;
			flex-direction: column;
			background: #f8fbfa;
			box-shadow: 0 0 28px rgba(24, 64, 48, 0.08);
		}

		.chat-header {
			background: #078f62;
			color: #fff;
			padding: 14px 16px;
			display: flex;
			align-items: center;
			gap: 12px;
			position: sticky;
			top: 0;
			z-index: 2;
			box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
		}

		.chat-back {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 36px;
			height: 36px;
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.15);
			color: #fff;
			font-size: 22px;
			line-height: 1;
			text-decoration: none;
		}

		.chat-back:focus-visible,
		.chat-send:focus-visible,
		.chat-upload:focus-visible,
		.chat-input:focus-visible {
			outline: 3px solid rgba(9, 173, 116, 0.32);
			outline-offset: 2px;
		}

		.chat-title {
			font-weight: 700;
			font-size: 16px;
		}

		.chat-subtitle {
			font-size: 12px;
			opacity: 0.9;
		}

		.chat-status {
			display: inline-flex;
			align-items: center;
			width: fit-content;
			margin-top: 4px;
			padding: 4px 9px;
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.2);
			font-size: 11px;
			font-weight: 700;
		}

		.chat-list {
			flex: 1;
			overflow-y: auto;
			padding: 18px 16px 22px;
		}

		.chat-bubble {
			max-width: min(78%, 560px);
			border-radius: 16px 16px 16px 6px;
			padding: 10px 12px 8px;
			margin-bottom: 12px;
			background: #fff;
			border: 1px solid #e1ece7;
			box-shadow: 0 4px 14px rgba(18, 58, 43, 0.08);
			white-space: pre-wrap;
			word-break: break-word;
		}

		.chat-bubble.mine {
			margin-left: auto;
			background: #dff7ee;
			border-color: #b8ead6;
			border-radius: 16px 16px 6px 16px;
		}

		.chat-image {
			display: block;
			max-width: 100%;
			max-height: 280px;
			border-radius: 12px;
			object-fit: contain;
			background: #f2f6f4;
		}

		.chat-image-link {
			display: block;
			line-height: 0;
		}

		.chat-sender {
			font-size: 11px;
			font-weight: 700;
			color: #527066;
			margin-bottom: 4px;
		}

		.chat-bubble.mine .chat-sender {
			color: #087e57;
		}

		.chat-time {
			font-size: 11px;
			color: #74877f;
			margin-top: 6px;
			text-align: right;
			white-space: normal;
		}

		.chat-form {
			background: #fff;
			padding: 12px;
			border-top: 1px solid #dde5e1;
			position: sticky;
			bottom: 0;
			box-shadow: 0 -8px 18px rgba(18, 58, 43, 0.08);
		}

		.chat-input-row {
			display: flex;
			gap: 8px;
			align-items: flex-end;
		}

		.chat-input {
			flex: 1;
			min-height: 48px;
			max-height: 132px;
			resize: vertical;
			border: 1px solid #c7d8d1;
			border-radius: 12px;
			padding: 11px 12px;
			font: inherit;
			background: #fbfdfc;
		}

		.chat-send {
			border: 0;
			border-radius: 12px;
			background: #09ad74;
			color: #fff;
			font-weight: 700;
			min-width: 76px;
			min-height: 48px;
			padding: 0 16px;
			cursor: pointer;
		}

		.chat-upload {
			border: 1px solid #09ad74;
			border-radius: 12px;
			background: #fff;
			color: #087e57;
			font-weight: 700;
			min-width: 56px;
			min-height: 48px;
			padding: 0 12px;
			cursor: pointer;
		}

		.chat-send:disabled {
			cursor: not-allowed;
			opacity: 0.65;
		}

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
			border: 1px solid #e1ece7;
			border-radius: 14px;
			padding: 12px;
		}

		.chat-error {
			color: #b42318;
		}

		.chat-readonly {
			margin: 0;
			padding: 12px;
			border-radius: 12px;
			background: #eef3f1;
			color: #455b53;
			border: 1px solid #d7e5df;
		}

		@media (max-width: 520px) {
			.chat-shell {
				max-width: none;
				box-shadow: none;
			}

			.chat-header {
				padding: 12px;
			}

			.chat-list {
				padding: 14px 10px 18px;
			}

			.chat-bubble {
				max-width: 88%;
			}

			.chat-input-row {
				gap: 6px;
			}

			.chat-upload {
				min-width: 50px;
				padding: 0 10px;
			}

			.chat-send {
				min-width: 64px;
				padding: 0 12px;
			}
		}
	</style>
</head>

<body>
	<div class="chat-shell">
		<header class="chat-header">
			<a href="<?= html_escape($back_url); ?>" class="chat-back" aria-label="Kembali">&larr;</a>
			<div>
				<div class="chat-title">Chat Konsultasi</div>
				<div class="chat-subtitle">Request #<?= html_escape($request_id); ?></div>
				<div class="chat-status"><?= html_escape($status_label); ?></div>
			</div>
		</header>

		<main class="chat-list" id="chatMessages">
			<div class="chat-muted">Memuat pesan...</div>
		</main>

		<form class="chat-form" id="chatForm">
			<?php if ($can_send) : ?>
				<div class="chat-input-row">
					<textarea class="chat-input" id="messageText" rows="2" maxlength="2000" placeholder="Tulis pesan..." required></textarea>
					<input type="file" id="imageInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" hidden>
					<button class="chat-upload" id="imageButton" type="button" aria-label="Kirim gambar">Foto</button>
					<button class="chat-send" type="submit">Kirim</button>
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
			return new Date(value.replace(' ', 'T')).toLocaleString('id-ID');
		}

		function isSafeImageUrl(value) {
			if (!value) {
				return false;
			}
			try {
				const parsed = new URL(value, window.location.origin);
				return parsed.origin === window.location.origin && parsed.pathname.indexOf('/uploads/chat_images/') !== -1;
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

			const bubble = document.createElement('div');
			const isMine = parseInt(message.sender_user_id, 10) === currentUserId;
			bubble.className = 'chat-bubble' + (isMine ? ' mine' : '');

			const sender = document.createElement('div');
			sender.className = 'chat-sender';
			sender.textContent = isMine ? 'Anda' : 'Lawan bicara';
			bubble.appendChild(sender);

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

			const time = document.createElement('div');
			time.className = 'chat-time';
			time.textContent = formatDate(message.created_at);
			bubble.appendChild(time);

			list.appendChild(bubble);
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
			if (imageButton && imageInput) {
				imageButton.addEventListener('click', function() {
					imageInput.click();
				});

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
							imageInput.value = '';
						});
				});
			}
		}
	</script>
</body>

</html>
