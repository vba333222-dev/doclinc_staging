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
			background: #f5f7f6;
			color: #20342d;
			font-family: Arial, sans-serif;
		}

		.chat-shell {
			max-width: 760px;
			margin: 0 auto;
			min-height: 100vh;
			display: flex;
			flex-direction: column;
		}

		.chat-header {
			background: #09ad74;
			color: #fff;
			padding: 14px 16px;
			display: flex;
			align-items: center;
			gap: 12px;
		}

		.chat-back {
			color: #fff;
			font-size: 24px;
			line-height: 1;
			text-decoration: none;
		}

		.chat-title {
			font-weight: 700;
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
			padding: 3px 8px;
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.2);
			font-size: 11px;
			font-weight: 700;
		}

		.chat-list {
			flex: 1;
			overflow-y: auto;
			padding: 16px;
		}

		.chat-bubble {
			max-width: 78%;
			border-radius: 8px;
			padding: 10px 12px;
			margin-bottom: 10px;
			background: #fff;
			box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
			white-space: pre-wrap;
			word-break: break-word;
		}

		.chat-bubble.mine {
			margin-left: auto;
			background: #dcf8ec;
		}

		.chat-image {
			display: block;
			max-width: 100%;
			max-height: 320px;
			border-radius: 8px;
			object-fit: contain;
		}

		.chat-time {
			font-size: 11px;
			color: #6c757d;
			margin-top: 4px;
		}

		.chat-form {
			background: #fff;
			padding: 12px;
			border-top: 1px solid #dde5e1;
		}

		.chat-input-row {
			display: flex;
			gap: 8px;
		}

		.chat-input {
			flex: 1;
			min-height: 48px;
			resize: vertical;
			border: 1px solid #c7d8d1;
			border-radius: 8px;
			padding: 10px;
			font: inherit;
		}

		.chat-send {
			border: 0;
			border-radius: 8px;
			background: #09ad74;
			color: #fff;
			font-weight: 700;
			min-width: 76px;
			padding: 0 14px;
			cursor: pointer;
		}

		.chat-upload {
			border: 1px solid #09ad74;
			border-radius: 8px;
			background: #fff;
			color: #087e57;
			font-weight: 700;
			min-width: 46px;
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
		}

		.chat-error {
			color: #b42318;
		}

		.chat-readonly {
			margin: 0;
			padding: 10px 12px;
			border-radius: 8px;
			background: #eef3f1;
			color: #455b53;
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
					<button class="chat-upload" id="imageButton" type="button" aria-label="Kirim gambar">Gambar</button>
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
			bubble.className = 'chat-bubble' + (parseInt(message.sender_user_id, 10) === currentUserId ? ' mine' : '');

			if (message.message_type === 'image' && isSafeImageUrl(message.attachment_url)) {
				const link = document.createElement('a');
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
