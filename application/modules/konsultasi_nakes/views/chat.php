<?php
$firebase_enabled = (bool) $this->config->item('firebase_enabled');
$legacy_superapp_url = $this->config->item('legacy_superapp_url') ?: '';
$chat_back_url = !empty($legacy_superapp_url) ? rtrim($legacy_superapp_url, '/') . '/sehat_geh' : base_url('home_nakes');
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DocLink - Chat</title>
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

		/* Add subtle animation to messages */
		/* .message {
			animation: fadeIn 0.3s ease-in-out;
		}

		@keyframes fadeIn {
			from {
				opacity: 0;
				transform: translateY(10px);
			}

			to {
				opacity: 1;
				transform: translateY(0);
			}
		} */

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
	</style>
</head>

<body>
	<div class="chat-container">
		<!-- Profil Ustadz -->
		<div class="profile-header">
			<a href="<?= html_escape($chat_back_url); ?>" style="text-decoration: none; color: white; font-size: 1.5rem;">
				<i class="fas fa-chevron-left icon"></i>
			</a>
			<img src="<?= base_url(); ?>assets/images/rahmat.jpg" alt="Pasien" id="ustadzProfileImage">
			<div>
				<h3 id="ustadzNameFull"></h3>
				<p id="ustadzSpecialization" class="mb-0"></p>
				<input type="text" id="username" value="pahlawan1" hidden>
			</div>
		</div>

		<?php
		$request_id = $_GET['reqId'] ?? '';
		$user_id = $_GET['userId'] ?? '';
		?>

		<input type="text" id="request_id" value="<?= $request_id ?>" hidden>
		<input type="text" id="user_id" value="<?= $user_id ?>" hidden>

		<div class="chat-box" id="chat-box">
			<!-- Pesan akan ditampilkan di sini -->
		</div>
		<div class="message-input">
			<input type="text" id="message" placeholder="Tulis pesan...">
			<button onclick="sendMessage()">Kirim</button>
		</div>
	</div>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>

	<?php if ($firebase_enabled) : ?>
		<script src="https://www.gstatic.com/firebasejs/8.6.1/firebase.js"></script>
		<?php if (!empty($legacy_superapp_url)) : ?>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/firebase-config.js'); ?>"></script>
		<?php endif; ?>
	<?php endif; ?>

	<script>
		const firebaseEnabled = <?= json_encode($firebase_enabled); ?>;

		function getFirebaseDatabase() {
			if (!firebaseEnabled || !window.firebase || !firebase.database) {
				return null;
			}

			try {
				return firebase.database();
			} catch (error) {
				return null;
			}
		}

		document.addEventListener("DOMContentLoaded", () => {
			if (Notification.permission !== "granted") {
				Notification.requestPermission();
			}

			if (!getFirebaseDatabase()) {
				const chatBox = document.getElementById("chat-box");
				if (chatBox) {
					chatBox.innerHTML = '<div class="text-muted small text-center p-3">Chat realtime belum tersedia pada konfigurasi ini.</div>';
				}
			}
		});

		// Mengambil parameter URL
		const params = new URLSearchParams(window.location.search);
		const ustadz = params.get('ustadz');
		const pasien = document.getElementById('request_id').value;

		const currentUser = document.getElementById('username').value;
		const chatWith = pasien;
		const firebaseDb = getFirebaseDatabase();

		if (firebaseDb) {
			firebaseDb.ref("messages").on("child_added", (snapshot) => {
				const message = snapshot.val();

				if (message.receiver === currentUser) {
					showNotification(`Message from ${message.sender}: ${message.text}`);
				}
			});

			// Listen for messages
			firebaseDb.ref("messages").on("value", (snapshot) => {
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

					// messageElement.innerHTML = `<div class="sender">${message.sender}:</div> <div class="content">${message}</div>`;

					// const sender = message.sender === currentUser ? "You" : message.sender;
					// messageElement.textContent = `${sender}: ${message.text}`;
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
		}

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
			const firebaseDb = getFirebaseDatabase();
			if (!firebaseDb) {
				const chatBox = document.getElementById("chat-box");
				if (chatBox) {
					chatBox.innerHTML = '<div class="text-muted small text-center p-3">Chat realtime belum tersedia pada konfigurasi ini.</div>';
				}
				return;
			}

			if (message.trim() !== "") {
				const newMessageRef = firebaseDb.ref("messages").push();
				newMessageRef.set({
					sender: currentUser,
					receiver: chatWith,
					text: message,
					timestamp: Date.now()
				});
				messageInput.value = "";

				const nama = 'pahlawan1'
				const userId = document.getElementById('user_id').value;
				const newMessageReff = firebaseDb.ref("notifications").push();
				newMessageReff.set({
					receiver: chatWith,
					userId: userId,
					text: 'Ada pesan dari Dokter ' + nama,
					timestamp: Date.now()
				});
			}
		}

		const ustadzProfileImage = document.getElementById('ustadzProfileImage');
		const ustadzNameFull = document.getElementById('ustadzNameFull');
		const ustadzSpecialization = document.getElementById('ustadzSpecialization');

		function endChat() {
			window.location.href = 'cari_ustadz'; // Mengarahkan kembali ke halaman home
		}

		if (ustadz) {
			ustadzNameFull.innerText = ustadz.charAt(0).toUpperCase() + ustadz.slice(1);
			ustadzSpecialization.innerText = 'Spesialisasi: Ahli Fiqih dan Tafsir.'; // Ganti dengan spesialisasi aktual
			ustadzProfileImage.src = '<?= base_url(); ?>assets/images/logo.png'; // Ganti dengan gambar profil aktual
		}
	</script>
</body>

</html>
