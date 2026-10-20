<?php
$request_id = isset($request_id) ? (int) $request_id : 0;
$can_send = !empty($can_send);
$current_user_id = isset($current_user_id) ? (int) $current_user_id : 0;
$current_role = isset($current_role) ? (string) $current_role : '';
$is_command_center = !empty($is_command_center);
$request_status = isset($request->request_status) ? (string) $request->request_status : '';
$is_nakes_role = in_array($current_role, array('dokter', 'nakes'), true);
$can_start_call = $is_nakes_role
	&& !$is_command_center
	&& $request_status === 'Accepted'
	&& function_exists('doclinc_request_is_handled_by_nakes')
	&& doclinc_request_is_handled_by_nakes($request, $current_user_id);
$can_receive_call = $current_role === 'warga'
	&& $request_status === 'Accepted'
	&& isset($request->user_id)
	&& (string) $request->user_id === (string) $current_user_id;
$call_ui_enabled = $can_start_call || $can_receive_call;
$call_start_url = base_url('home_nakes/start_livekit_call');
$call_end_url = base_url('home_nakes/end_livekit_call');
$call_nakes_status_url = base_url('home_nakes/livekit_call_status');
$call_incoming_url = base_url('home/livekit_incoming_call');
$call_answer_url = base_url('home/answer_livekit_call');
$call_reject_url = base_url('home/reject_livekit_call');
$call_warga_status_url = base_url('home/livekit_call_status');
$call_ringtone_url = base_url('assets/audio/doclinc-ringtone.wav');
$auto_answer_call_id = isset($_GET['answer_call']) ? (int) $_GET['answer_call'] : 0;
/* Di sini dulu ada dua variabel: `$queue_display` (nomor antrian + nama
   Puskesmas) dan `$status_label` ("Chat aktif" / "Riwayat chat" /
   "Riwayat chat dibatalkan"). Keduanya hanya dipakai oleh petak identitas
   konsultasi di kepala percakapan, yang markup-nya sudah dihapus atas
   permintaan. Sudah dihapus supaya tidak ada pembaca berikutnya yang mengira
   keduanya masih tampil di layar.

   Nomor antrian tetap tersedia di beranda warga (petak B22.1) dan di halaman
   Konsultasi; status sesi terbaca dari kompornya sendiri - aktif, atau
   hanya-baca dengan pesan dari `$readonly_message`. */
$back_url = isset($back_url) && is_string($back_url) && $back_url !== ''
	? $back_url
	: base_url($is_nakes_role ? 'home_nakes#riwayat_konsul' : 'home#riwayat');
$partner_presence_online = false;
if ($request_status === 'Accepted') {
	$partner_presence_online = $current_role === 'dokter'
		? !empty($request->chat_patient_online)
		: !empty($request->chat_clinician_online);
}
$readonly_message = in_array($request_status, array('Completed', 'Cancelled'), true)
	? 'Sesi konsultasi sudah selesai.'
	: 'Chat ini hanya dapat dibaca.';
$asset_base = base_url('assets/doclinc_ui/chat/');
$partner_user_id = $current_role === 'dokter'
	? (isset($request->user_id) ? (int) $request->user_id : 0)
	: (isset($request->chat_clinician_user_id) ? (int) $request->chat_clinician_user_id : 0);
$profile_photo_fallback = '';
$partner_photo_src = '';
/* Avatar memakai resolver yang SAMA dengan dashboard warga
   (`doclinc_profile_image_src()`), bukan pemeriksaan berkas sendiri.

   Sebelumnya view ini membangun URL-nya dari `chat_*_photo_available` - flag
   yang dihitung model lewat `chat_photo_available()`, yang membaca sistem
   berkas (`resolve_stored_file` + `allowed_mime`) dan bergantung pada
   `profile_image_storage_path`. Dashboard memakai helper yang hanya
   memvalidasi BENTUK kunci simpan lalu menyerahkan penyajiannya ke
   `profile/photo/{id}`. Dua jalur berbeda untuk hal yang sama berarti foto
   bisa tampil di dashboard tapi tidak di chat - dan itu yang terjadi.

   Sekarang keduanya satu jalur: kalau dashboard menampilkan foto, chat
   menampilkannya juga. Kunci mentahnya disediakan model sebagai
   `chat_patient_photo_key` / `chat_clinician_photo_key`. */
$partner_photo_key = $current_role === 'dokter'
	? (string) (isset($request->chat_patient_photo_key) ? $request->chat_patient_photo_key : '')
	: (string) (isset($request->chat_clinician_photo_key) ? $request->chat_clinician_photo_key : '');
if ($partner_user_id > 0 && $partner_photo_key !== '' && function_exists('doclinc_profile_image_src')) {
	$partner_photo_src = doclinc_profile_image_src($partner_user_id, $partner_photo_key);
}
$voice_photo_src = $partner_photo_src;
$voice_has_photo = $voice_photo_src !== '';
$partner_name = 'Petugas Puskesmas';
$partner_subtitle = 'Konsultasi kesehatan';
if ($current_role === 'dokter') {
	$partner_name = isset($request->chat_patient_name) && trim((string) $request->chat_patient_name) !== ''
		? trim((string) $request->chat_patient_name)
		: (isset($request->nama) && $request->nama !== '' ? $request->nama : 'Pasien');
	$partner_subtitle = isset($request->assigned_puskesmas_name) && $request->assigned_puskesmas_name !== '' ? $request->assigned_puskesmas_name : 'Permintaan konsultasi';
} else {
	$partner_name = isset($request->chat_clinician_name) && trim((string) $request->chat_clinician_name) !== ''
		? trim((string) $request->chat_clinician_name)
		: 'Tenaga kesehatan';
	$partner_subtitle = 'Nakes penanganan Anda';
}
$partner_initials = '';
$partner_name_parts = preg_split('/\s+/', trim((string) $partner_name));
foreach (array_slice($partner_name_parts ?: array(), 0, 2) as $partner_name_part) {
	$partner_initials .= function_exists('mb_substr') ? mb_substr($partner_name_part, 0, 1) : substr($partner_name_part, 0, 1);
}
$partner_initials = strtoupper($partner_initials !== '' ? $partner_initials : 'DK');
$partner_placeholder_name = implode(' ', array_slice($partner_name_parts ?: array(), 0, 2));
$partner_placeholder_name = $partner_placeholder_name !== '' ? $partner_placeholder_name : $partner_name;
$voice_partner_role = '';
if ($current_role === 'dokter') {
	$voice_partner_role = 'Warga';
} elseif (!empty($request->chat_clinician_role)) {
	$voice_partner_role = trim((string) $request->chat_clinician_role);
} elseif ($partner_name !== 'Tenaga kesehatan') {
	$voice_partner_role = 'Tenaga kesehatan';
}
$auto_visit_location = $current_role === 'dokter'
	&& !$is_command_center
	&& $request_status === 'Accepted'
	&& (string) ($request->consultation_mode ?? '') === 'visit'
	&& (int) ($request->visit_performer_user_id ?? 0) === $current_user_id
	&& in_array((string) ($request->visit_status ?? ''), array('en_route', 'arrived', 'in_service'), true);
$active_visit_location_asset = FCPATH . 'assets/js/doclinc-active-visit-location.js';
$active_visit_location_version = is_file($active_visit_location_asset) ? (string) filemtime($active_visit_location_asset) : '1';
$chat_participant_asset = FCPATH . 'assets/js/doclinc-chat-participant.js';
$chat_participant_version = is_file($chat_participant_asset) ? (string) filemtime($chat_participant_asset) : '1';
?>
<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Chat Konsultasi</title>
	<link rel="stylesheet" href="<?= base_url('assets/css/doclinc-warga.css'); ?>">
	<?php if ($is_nakes_role) : ?><link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-nakes.css')); ?>"><?php endif; ?>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<!-- Design system dimuat SEBELUM blok <style> di bawah supaya gaya chat yang
	     sudah ada tetap menang saat bertabrakan, sementara token, cincin fokus,
	     komponen bersama, dan aturan prefers-reduced-motion ikut terpakai.
	     doclinc-warga.css di atas tidak menjangkau .dl-warga-chat, jadi sebelum
	     ini halaman chat tidak punya cincin fokus sama sekali untuk tombol/tautan
	     di luar kontrol panggilan. -->
	<link rel="stylesheet" href="<?= base_url('assets/css/doclinc-design-system.css'); ?>">
	<style>
		:root {
			--chat-green: #1F6F4B;
			--chat-green-dark: #177a42;
			--chat-green-accent: #437a13;
			--chat-bg: #F7F7F7;
			--chat-surface: #FFFFFF;
			--chat-text: #1F2A24;
			--chat-muted: #66756D;
			--chat-border: #E7ECE9;
			--chat-bubble-in: #f0f7f3;
			--chat-bubble-out: #E8F7F0;
			--chat-bubble-in-alt: #f9f0da;
			--chat-bubble-out-alt: #e1f0d6;
			--chat-time: #9CA3AF;
			--chat-input-border: #D1D5DB;
			--chat-red: #dc2626;
			--chat-red-light: #fecaca;
		}

		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			background: var(--chat-bg);
			color: var(--chat-text);
			font-family: 'Manrope', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
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
			min-height: 68px;
			background: var(--chat-surface);
			color: var(--chat-text);
			padding: 12px 14px;
			display: flex;
			align-items: center;
			gap: 12px;
			top: 0;
			z-index: 4;
			box-shadow: 0 1px 6px rgba(0, 0, 0, 0.06);
		}

		.chat-back {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 36px;
			height: 36px;
			border: 0;
			border-radius: 10px;
			background: transparent;
			color: var(--chat-text);
			line-height: 1;
			text-decoration: none;
			flex: 0 0 36px;
			transition: background 0.15s ease;
		}

		.chat-back:hover {
			background: rgba(31, 111, 75, 0.08);
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
			min-width: 0;
			flex: 1 1 auto;
		}

		.chat-title-text {
			margin: 0;
			color: var(--chat-text);
			font-weight: 800;
			font-size: 15px;
			line-height: 19px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.chat-title-subtext {
			margin: 2px 0 0;
			color: var(--chat-muted);
			font-size: 13px;
			font-weight: 600;
			line-height: 16px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.chat-avatar {
			width: 42px;
			height: 42px;
			border-radius: 12px;
			object-fit: cover;
			background: #ffffff;
			flex: 0 0 42px;
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

		.chat-message-avatar {
			width: 30px;
			height: 30px;
			border-radius: 50%;
			object-fit: cover;
			background: #e8efec;
			flex: 0 0 30px;
			align-self: flex-end;
		}

		.chat-message-row.mine {
			justify-content: space-between;
		}

		.chat-bubble {
			max-width: min(300px, 86%);
			border-radius: 16px 16px 16px 4px;
		padding: 12px 14px;
			background: var(--chat-bubble-in-alt);
			border: 0;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
			white-space: pre-wrap;
			word-break: break-word;
			color: var(--chat-text);
			font-size: 14px;
			line-height: 20px;
		}

		.chat-bubble.mine {
			background: var(--chat-bubble-out-alt);
			border-radius: 16px 16px 4px 16px;
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
			width: 56px;
			flex: 0 0 56px;
			font-size: 13px;
			color: var(--chat-muted);
			margin-top: 4px;
			text-align: left;
			white-space: normal;
			line-height: 16px;
			font-weight: 500;
		}

		.chat-message-row:not(.mine) .chat-time {
			text-align: right;
		}

		.chat-form {
			min-height: 72px;
			background: var(--chat-surface);
			padding: 14px 16px 16px;
			border-top: 1px solid var(--chat-border);
			position: relative;
			bottom: 0;
			flex: 0 0 auto;
		}

		.chat-input-row {
			display: flex;
			align-items: center;
			gap: 6px;
			min-height: 44px;
			border: 1.5px solid var(--chat-input-border);
			border-radius: 22px;
			padding: 0 8px 0 10px;
			background: var(--chat-surface);
			transition: border-color 0.2s ease, box-shadow 0.2s ease;
		}

		.chat-input-row:focus-within {
			border-color: var(--chat-green);
			box-shadow: 0 0 0 3px rgba(31, 111, 75, 0.12);
		}

		.chat-input {
			flex: 1;
			min-width: 0;
			height: 42px;
			min-height: 42px;
			max-height: 86px;
			resize: none;
			border: 0;
			border-radius: 0;
			padding: 10px 4px;
			font: inherit;
			font-size: 14px;
			line-height: 20px;
			background: transparent;
			color: var(--chat-text);
		}

		.chat-input::placeholder {
			color: var(--chat-muted);
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
			margin-top: 20px;
		}

		.chat-muted {
			color: var(--chat-muted);
			background: var(--chat-surface);
			border: 1px solid var(--chat-border);
			border-radius: 14px;
			padding: 14px 16px;
			font-weight: 600;
		}

		.chat-error {
			color: var(--chat-red);
		}

		.chat-image-feedback {
			margin: 8px 4px 0;
			text-align: center;
			font-size: 12px;
			color: var(--chat-muted);
			font-weight: 500;
		}

		.chat-image-feedback.is-error {
			color: var(--chat-red);
		}

		.chat-readonly {
			margin: 0;
			padding: 14px 16px;
			border-radius: 14px;
			background: var(--chat-bg);
			color: var(--chat-muted);
			border: 1px solid var(--chat-border);
			font-weight: 500;
		}

		.chat-call-actions {
			display: flex;
			align-items: center;
			justify-content: flex-end;
			gap: 4px;
			flex: 0 0 auto;
		}

		.chat-call-button {
			border: 0;
			background: transparent;
			color: var(--chat-green);
			width: 44px;
			height: 44px;
			border-radius: 12px;
			padding: 10px;
			cursor: pointer;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			flex: 0 0 44px;
			transition: background 0.15s ease, transform 0.1s ease;
		}

		.chat-call-button:hover {
			background: rgba(31, 111, 75, 0.1);
		}

		.chat-call-button:focus-visible {
			background: rgba(31, 111, 75, 0.1);
			outline: 3px solid var(--bd-green-500);
			outline-offset: 2px;
		}

		.chat-call-button:active {
			transform: scale(0.95);
		}

		.chat-call-button svg {
			width: 20px;
			height: 20px;
			display: block;
			stroke: currentColor;
		}

		.doclinc-call-modal {
			position: fixed;
			inset: 0;
			z-index: 80;
			display: none;
			background: rgba(2, 8, 12, 0.96);
			color: #ffffff;
			padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
		}

		.doclinc-call-modal.is-open {
			display: block;
		}

		.doclinc-call-panel {
			width: 100%;
			max-width: 480px;
			height: 100vh;
			height: 100dvh;
			margin: 0 auto;
			overflow: hidden;
			background:
				radial-gradient(circle at 50% 12%, rgba(31, 111, 75, 0.35) 0, rgba(31, 111, 75, 0) 33%),
				linear-gradient(180deg, #17313b 0%, #101820 46%, #070d12 100%);
			display: flex;
			flex-direction: column;
			box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.04), 0 24px 80px rgba(0, 0, 0, 0.42);
		}

		.doclinc-call-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			padding: 20px 18px 14px;
			flex: 0 0 auto;
		}

		.doclinc-call-context {
			min-width: 0;
			display: flex;
			align-items: center;
			gap: 12px;
		}

		.doclinc-call-avatar {
			width: 48px;
			height: 48px;
			border-radius: 999px;
			object-fit: cover;
			background: rgba(255, 255, 255, 0.16);
			border: 1px solid rgba(255, 255, 255, 0.24);
			flex: 0 0 48px;
		}

		.doclinc-call-title {
			margin: 0;
			font-size: 18px;
			font-weight: 800;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.doclinc-call-status {
			margin-top: 4px;
			color: rgba(255, 255, 255, 0.72);
			font-size: 13px;
			line-height: 18px;
		}

		.doclinc-call-status.is-error {
			color: var(--chat-red-light);
		}

		.doclinc-call-close {
			width: 40px;
			height: 40px;
			border-radius: 999px;
			border: 1px solid rgba(255, 255, 255, 0.16);
			background: rgba(255, 255, 255, 0.08);
			color: #ffffff;
			font-size: 22px;
			line-height: 1;
			cursor: pointer;
			flex: 0 0 40px;
		}

		/* Tombol tutup di kepala panggilan. Sebelumnya satu-satunya jalan keluar
		   adalah menu "Lainnya" > "Tutup tampilan"; di layar ponsel menu itu tidak
		   terlihat seperti jalan keluar. */
		.doclinc-call-modal .doclinc-call-header-close {
			display: inline-grid;
			place-items: center;
			width: 44px;
			height: 44px;
			padding: 0;
			border: 1px solid rgba(255, 255, 255, 0.18);
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.08);
			cursor: pointer;
			flex: 0 0 44px;
		}

		/* Pembungkus ini sengaja bukan `.doclinc-call-head-actions`: aturan Task 11
		   menyembunyikan wrapper itu beserta indikator teknis lain. Tombol tutup
		   adalah kontrol, bukan indikator, jadi ia tidak ikut disembunyikan. */
		.doclinc-call-head-close {
			display: flex;
			align-items: center;
			justify-content: flex-end;
			flex: 0 0 auto;
			margin-left: auto;
		}

		@media (min-width: 641px) {
			.doclinc-call-head-close {
				grid-column: 3;
				justify-self: end;
			}
		}

		.doclinc-call-modal .doclinc-call-header-close img {
			display: block;
			width: 20px;
			height: 20px;
			filter: brightness(0) invert(1);
		}

		.doclinc-call-modal .doclinc-call-header-close:hover {
			background: rgba(255, 255, 255, 0.16);
		}

		.doclinc-call-modal .doclinc-call-header-close:focus-visible {
			outline: 3px solid var(--bd-mint);
			outline-offset: 2px;
		}

		/* Status tidak boleh terpotong di tengah kalimat. */
		.doclinc-call-modal .doclinc-call-connection-text {
			white-space: normal;
			overflow: visible;
			text-overflow: clip;
			line-height: 1.35;
		}

		.doclinc-call-dismiss {
			display: block;
			margin: 10px auto 0;
			min-height: 44px;
			padding: 10px 18px;
			border: 0;
			background: transparent;
			color: rgba(255, 255, 255, 0.78);
			font: inherit;
			font-size: 13px;
			font-weight: 700;
			cursor: pointer;
		}

		.doclinc-call-dismiss:focus-visible {
			outline: 3px solid var(--bd-mint);
			outline-offset: 2px;
		}

		.doclinc-call-stage {
			position: relative;
			flex: 1 1 auto;
			min-height: 0;
			margin: 0 16px;
			border-radius: 28px;
			background: rgba(255, 255, 255, 0.055);
			overflow: hidden;
			box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
		}

		.doclinc-call-remote {
			position: absolute;
			inset: 0;
			display: flex;
			align-items: center;
			justify-content: center;
			background:
				radial-gradient(circle at center, rgba(255, 255, 255, 0.08) 0, rgba(255, 255, 255, 0) 52%),
				rgba(255, 255, 255, 0.02);
		}

		.doclinc-call-remote video,
		.doclinc-call-remote audio {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		.doclinc-call-empty {
			max-width: 300px;
			text-align: center;
			color: rgba(255, 255, 255, 0.82);
			font-size: 14px;
			font-weight: 700;
			line-height: 21px;
			padding: 20px;
		}

		.doclinc-call-empty-avatar {
			width: 108px;
			height: 108px;
			margin: 0 auto 18px;
			border-radius: 999px;
			object-fit: cover;
			border: 3px solid rgba(255, 255, 255, 0.2);
			box-shadow: 0 18px 42px rgba(0, 0, 0, 0.28);
		}

		.doclinc-call-empty-name {
			display: block;
			color: #ffffff;
			font-size: 20px;
			font-weight: 850;
			line-height: 26px;
			margin-bottom: 6px;
		}

		.doclinc-call-local {
			position: absolute;
			right: 18px;
			bottom: 18px;
			width: 108px;
			height: 154px;
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

		.doclinc-call-local.is-placeholder {
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 10px;
			text-align: center;
			color: rgba(255, 255, 255, 0.78);
			font-size: 11px;
			font-weight: 800;
			line-height: 15px;
		}

		.doclinc-call-prejoin {
			padding: 18px 18px calc(20px + env(safe-area-inset-bottom));
			background: transparent;
			color: #ffffff;
			flex: 0 0 auto;
		}

		.doclinc-call-prejoin-title {
			margin: 0 0 4px;
			font-size: 15px;
			font-weight: 800;
		}

		.doclinc-call-prejoin-text {
			margin: 0 0 14px;
			color: rgba(255, 255, 255, 0.66);
			font-size: 13px;
			line-height: 18px;
		}

		.doclinc-call-join {
			width: 100%;
			min-height: 52px;
			border: 0;
			border-radius: 16px;
			background: var(--chat-green-accent);
			color: #ffffff;
			font-weight: 800;
			font-size: 16px;
			cursor: pointer;
			transition: background 0.15s ease, transform 0.1s ease;
		}

		.doclinc-call-join:hover {
			background: #3a6b10;
		}

		.doclinc-call-join:active {
			transform: scale(0.98);
		}

		.doclinc-call-controls {
			display: flex;
			align-items: center;
			justify-content: center;
			flex-wrap: wrap;
			gap: 14px;
			padding: 18px 14px calc(20px + env(safe-area-inset-bottom));
			background: transparent;
			flex: 0 0 auto;
		}

		.doclinc-call-control {
			width: 56px;
			height: 56px;
			border-radius: 999px;
			border: 1px solid rgba(255, 255, 255, 0.15);
			background: rgba(255, 255, 255, 0.14);
			color: #ffffff;
			cursor: pointer;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			box-shadow: 0 10px 28px rgba(0, 0, 0, 0.2);
			transition: transform 0.12s ease, background 0.12s ease;
		}

		.doclinc-call-control:active {
			transform: scale(0.96);
		}

		.doclinc-call-control svg {
			width: 23px;
			height: 23px;
			stroke: currentColor;
		}

		.doclinc-call-control.is-off {
			background: rgba(255, 255, 255, 0.9);
			color: #111827;
		}

		.doclinc-call-control.speaker.is-active {
			background: var(--chat-green-accent);
			border-color: #5f9d27;
			color: #ffffff;
		}

		.doclinc-call-control.end-call {
			background: var(--chat-red);
			border-color: var(--chat-red);
			width: 64px;
			height: 64px;
		}

		.doclinc-call-control.end-call:hover {
			background: #b91c1c;
			border-color: #b91c1c;
		}

		.doclinc-active-call {
			position: fixed;
			left: 50%;
			bottom: calc(88px + env(safe-area-inset-bottom));
			z-index: 30;
			transform: translateX(-50%);
			width: min(360px, calc(100vw - 28px));
			border: 0;
			border-radius: 18px;
			background: linear-gradient(135deg, #14232d, #0d151c);
			color: #ffffff;
			padding: 12px 14px;
			box-shadow: 0 16px 42px rgba(0, 0, 0, 0.28);
			display: flex;
			align-items: center;
			gap: 12px;
			cursor: pointer;
			transition: transform 0.15s ease, box-shadow 0.15s ease;
		}

		.doclinc-active-call:hover {
			transform: translateX(-50%) translateY(-2px);
			box-shadow: 0 20px 52px rgba(0, 0, 0, 0.32);
		}

		.doclinc-active-call:active {
			transform: translateX(-50%) scale(0.98);
		}

		.doclinc-active-call-icon {
			width: 40px;
			height: 40px;
			border-radius: 12px;
			background: var(--chat-green-accent);
			display: inline-flex;
			align-items: center;
			justify-content: center;
			flex: 0 0 40px;
		}

		.doclinc-active-call-main {
			min-width: 0;
			flex: 1;
			text-align: left;
		}

		.doclinc-active-call-title {
			display: block;
			font-size: 13px;
			font-weight: 800;
			line-height: 17px;
		}

		.doclinc-active-call-meta {
			display: block;
			color: rgba(255, 255, 255, 0.72);
			font-size: 12px;
			line-height: 16px;
		}

		.doclinc-active-call-restore {
			color: rgba(255, 255, 255, 0.74);
			font-size: 12px;
			font-weight: 800;
			flex: 0 0 auto;
		}

		.doclinc-incoming-call {
			position: fixed;
			left: 50%;
			top: calc(84px + env(safe-area-inset-top));
			z-index: 35;
			transform: translateX(-50%);
			width: min(370px, calc(100vw - 28px));
			border-radius: 24px;
			background: linear-gradient(145deg, #132730, #0c151b);
			color: #ffffff;
			padding: 20px;
			box-shadow: 0 20px 52px rgba(0, 0, 0, 0.32), 0 0 0 1px rgba(255,255,255,0.06);
		}

		.doclinc-incoming-kicker {
			margin: 0 0 8px;
			color: rgba(255, 255, 255, 0.68);
			font-size: 12px;
			font-weight: 800;
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}

		.doclinc-incoming-name {
			margin: 0;
			font-size: 18px;
			font-weight: 850;
			line-height: 24px;
		}

		.doclinc-incoming-type {
			margin: 3px 0 16px;
			color: rgba(255, 255, 255, 0.74);
			font-size: 13px;
			line-height: 18px;
		}

		.doclinc-incoming-actions {
			display: flex;
			flex-direction: column;
			align-items: stretch;
			gap: 14px;
		}

		.doclinc-incoming-action {
			flex: 0 0 auto;
			min-height: 44px;
			border: 0;
			border-radius: 999px;
			color: #ffffff;
			font-weight: 850;
			cursor: pointer;
		}

		.doclinc-incoming-action.answer {
			background: var(--chat-green-accent);
			transition: background 0.15s ease;
		}

		.doclinc-incoming-action.answer:hover {
			background: #3a6b10;
		}

		.doclinc-incoming-action.reject {
			background: var(--chat-red);
			transition: background 0.15s ease;
		}

		.doclinc-incoming-action.reject:hover {
			background: #b91c1c;
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

			.chat-call-actions {
				gap: 2px;
			}

			.chat-call-button {
				width: 36px;
				height: 36px;
				flex-basis: 36px;
				padding: 8px;
			}

			.doclinc-call-stage {
				margin: 0 12px;
				border-radius: 24px;
			}

			.doclinc-call-controls {
				display: grid;
				grid-template-columns: repeat(3, minmax(52px, 1fr));
				gap: 10px;
				width: min(260px, calc(100vw - 32px));
				margin: 0 auto;
			}

			.doclinc-call-control {
				width: 52px;
				height: 52px;
			}

			.doclinc-call-control.end-call {
				width: 60px;
				height: 60px;
				justify-self: center;
			}
		}

		@media (min-width: 560px) {
			.doclinc-call-modal {
				background: rgba(2, 8, 12, 0.92);
			}

			.doclinc-call-panel {
				border-radius: 28px;
			}
		}

		/* Screenshot-authoritative active Chat and incoming-call presentation. */
		body:has(.chat-shell.is-active) {
			background: #eef3f4;
			color: #14221e;
		}
		.chat-shell.is-active {
			width: min(100%, 468px);
			max-width: 468px;
			min-height: 100dvh;
			border-radius: 0;
			background: #f7f8f8;
			box-shadow: 0 18px 48px rgba(22, 52, 45, .12);
		}
		.chat-shell.is-active .chat-header {
			position: sticky;
			min-height: 72px;
			padding: 12px 18px;
			gap: 12px;
			border-bottom: 1px solid #e9eeee;
			box-shadow: none;
		}
		.chat-shell.is-active .chat-back { width: 38px; height: 38px; flex-basis: 38px; }
		.chat-shell.is-active .chat-back img { width: 18px; height: 14px; }
		.chat-avatar-wrap { position: relative; width: 42px; height: 42px; flex: 0 0 42px; }
		.chat-avatar, .chat-avatar-fallback {
			width: 42px; height: 42px; border-radius: 50%;
			object-fit: cover; background: #e8f0ed; border: 2px solid #d8ebe2;
		}
		.chat-avatar-fallback {
			display: inline-flex; align-items: center; justify-content: center;
			color: #2a6b4f; font-size: 14px; font-weight: 800; letter-spacing: .04em;
		}
		.chat-avatar-wrap .chat-presence-dot {
			position: absolute; right: 0; bottom: 1px; width: 12px; height: 12px;
			border: 2px solid #fff; border-radius: 50%; background: #24b47e;
		}
		.chat-title-text { color: #12201c; font-size: 16px; line-height: 21px; }
		.chat-title-subtext { color: var(--chat-muted); font-size: 13px; line-height: 17px; font-weight: 650; }
		.chat-call-actions { gap: 2px; }
		.chat-call-button { width: 42px; height: 42px; flex-basis: 42px; color: #477066; padding: 10px; }
		.chat-call-button:hover, .chat-call-button:focus-visible { background: #eaf6f0; color: #188c5e; }
		.chat-call-button img { width: 21px; height: 21px; display: block; }
		/* Di sini dulu ada lima aturan `.chat-info-banner*` untuk petak
		   "Konsultasi sedang berlangsung" beserta nomor antriannya. Markup-nya
		   sudah dihapus atas permintaan, jadi aturannya ikut dihapus - lima
		   selektor yang tidak cocok dengan apa pun hanya menuntun pembaca
		   berikutnya ke struktur yang sudah tidak ada. Nomor antrian tetap
		   tersedia di beranda warga (petak B22.1) dan di halaman Konsultasi. */
		.chat-list { padding: 20px 18px 22px; background: #f6f7f8; }
		.chat-date-separator { display: flex; justify-content: center; margin: 0 0 22px; }
		.chat-date-separator[hidden] { display: none; }
		.chat-date-separator span { padding: 7px 14px; border-radius: 999px; background: #e9eef2; color: #53656b; font-size: 13px; font-weight: 650; }
		.chat-message-row { justify-content: flex-start; gap: 9px; margin-bottom: 20px; align-items: flex-end; }
		.chat-message-row.mine { justify-content: flex-end; }
		.chat-message-avatar { width: 30px; height: 30px; flex: 0 0 30px; border-radius: 50%; object-fit: cover; border: 1px solid #dce7e4; background: #e5f1ec; }
		.chat-message-avatar-fallback { width: 30px; height: 30px; flex: 0 0 30px; align-self: flex-end; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #dfefe8; color: #2d7458; font-size: 10px; font-weight: 800; }
		.chat-message-stack { max-width: calc(100% - 39px); min-width: 0; }
		.chat-message-stack.mine { max-width: calc(100% - 24px); margin-left: auto; }
		.chat-message-sender { margin: 0 0 5px 4px; color: var(--chat-muted); font-size: 13px; font-weight: 500; }
		.chat-delivery { display: inline-block; margin-left: 3px; color: currentColor; font-size: 11px; line-height: 1; letter-spacing: -2px; vertical-align: 0; }
		.chat-bubble { max-width: none; padding: 13px 15px 10px; border-radius: 19px 19px 19px 5px; background: #fff; border: 1px solid #e0e7e8; box-shadow: 0 3px 12px rgba(34, 65, 59, .07); color: #17241f; font-size: 13.5px; line-height: 22px; }
		.chat-bubble.mine { border-radius: 19px 19px 5px 19px; background: #d7f5e5; border-color: #9ee5c0; }
		.chat-time { width: auto; margin-top: 7px; color: var(--chat-muted); font-size: 13px; line-height: 16px; text-align: right; }
		.chat-message-stack.mine .chat-message-sender { text-align: right; margin: 0 4px 5px 0; }
		.chat-form { min-height: 78px; padding: 11px 14px max(14px, env(safe-area-inset-bottom)); border-top: 1px solid #e2e9e8; box-shadow: 0 -3px 14px rgba(40, 68, 61, .05); }
		.chat-input-row { min-height: 54px; gap: 4px; padding: 4px 6px 4px 8px; border: 1px solid #e1e9eb; border-radius: 28px; background: #fff; box-shadow: 0 5px 18px rgba(45, 74, 67, .08); }
		.chat-input { height: 42px; min-height: 42px; min-width: 0; width: auto; flex: 1 1 auto; padding: 10px 5px; color: #1a2824; font-size: 14px; line-height: 21px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.chat-icon-button, .chat-send { width: 42px; height: 42px; min-width: 42px; padding: 10px; border-radius: 50%; }
		.chat-icon-button img, .chat-send img { width: 20px; height: 20px; }
		.chat-send { background: #28a86f; box-shadow: 0 5px 12px rgba(40, 168, 111, .22); }
		.chat-send img { filter: brightness(0) invert(1); }
		@media (min-width: 421px) and (max-width: 520px) {
			body:has(.chat-shell.is-active) { min-height: 1600px; display: flex; align-items: center; justify-content: center; }
			.chat-shell.is-active { width: 390px; height: 1078px; min-height: 1078px; margin: auto; }
			.chat-shell.is-active .chat-info-banner { min-height: 26px; height: 26px; padding-block: 0; }
			.chat-shell.is-active .chat-info-banner__label,
			.chat-shell.is-active .chat-info-banner__meta { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
		}

		.doclinc-incoming-call {
			position: fixed; inset: 0; left: 0; top: 0; transform: none; width: 100%; max-width: none; padding: 0;
			z-index: 100; display: flex; align-items: stretch; justify-content: center; background: rgba(240, 247, 246, .96); color: #13221e;
		}
		.doclinc-incoming-call.is-hidden { display: none !important; }
		.doclinc-incoming-surface { width: min(100%, 390px); min-height: 100dvh; padding: 24px 24px max(26px, env(safe-area-inset-bottom)); display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; background: radial-gradient(circle at 50% 34%, rgba(122, 224, 183, .18), transparent 38%), #f8fffd; }
		.doclinc-incoming-badge { align-self: flex-end; display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border: 1px solid #a4e8ca; border-radius: 999px; background: #edfff7; color: #0d7c57; font-size: 11px; font-weight: 800; letter-spacing: .11em; }
		.doclinc-incoming-badge::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #21bd83; box-shadow: 0 0 0 5px rgba(33, 189, 131, .13); }
		.doclinc-incoming-identity { display: flex; flex: 1; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
		.doclinc-incoming-avatar-wrap { position: relative; width: 148px; height: 148px; margin: 14px 0 25px; display: grid; place-items: center; }
		.doclinc-incoming-avatar-wrap::before, .doclinc-incoming-avatar-wrap::after { content: ''; position: absolute; inset: 0; border: 1px solid rgba(53, 201, 152, .22); border-radius: 50%; animation: doclincIncomingPulse 2.8s ease-out infinite; }
		.doclinc-incoming-avatar-wrap::after { inset: 13px; animation-delay: .9s; }
		@keyframes doclincIncomingPulse { from { transform: scale(.76); opacity: .72; } to { transform: scale(1.14); opacity: 0; } }
		.doclinc-incoming-avatar { position: relative; z-index: 1; width: 126px; height: 126px; border-radius: 50%; border: 3px solid #56dbaf; background: #111b2e; box-shadow: 0 10px 28px rgba(26, 155, 111, .18); object-fit: cover; }
		.doclinc-incoming-identity:not(:has(.doclinc-incoming-avatar-wrap)) { gap: 14px; }
		.doclinc-incoming-name { max-width: 100%; margin: 0; color: #14221e; font-size: 27px; line-height: 33px; font-weight: 850; letter-spacing: -.03em; overflow-wrap: anywhere; }
		.doclinc-incoming-mode { display: inline-flex; align-items: center; gap: 9px; margin-top: 12px; padding: 9px 16px; border: 1px solid #b2efd7; border-radius: 999px; background: rgba(237, 255, 247, .88); color: #15825c; font-size: 14px; font-weight: 750; }
		.doclinc-incoming-mode img { width: 18px; height: 18px; object-fit: contain; }
		.doclinc-incoming-waveform { display: inline-flex; align-items: center; justify-content: center; width: 88px; height: 28px; min-height: 24px; margin-top: 12px; color: #1cb985; }
		.doclinc-incoming-waveform.is-hidden { display: none !important; }
		.doclinc-incoming-waveform svg { width: 72px; height: 26px; }
		.doclinc-incoming-waveform.is-fallback::before { content: ''; display: block; width: 72px; height: 24px; border-radius: 12px; background: linear-gradient(to right, transparent 0 7px, #1cb985 7px 11px, transparent 11px 18px, #1cb985 18px 22px, transparent 22px 29px, #1cb985 29px 33px, transparent 33px 40px, #1cb985 40px 44px, transparent 44px 51px, #1cb985 51px 55px, transparent 55px 62px, #1cb985 62px 66px, transparent 66px); opacity: .92; }
		.doclinc-incoming-slider { position: relative; width: 100%; height: 68px; padding: 6px; border: 1px solid #dde8ec; border-radius: 999px; background: #eef3f7; box-shadow: inset 0 2px 8px rgba(24, 52, 59, .06); }
		.doclinc-incoming-slider-label { position: absolute; inset: 0; display: grid; place-items: center; padding: 0 10px 0 66px; color: #516371; font-size: 14px; font-weight: 750; pointer-events: none; text-align: center; }
		.doclinc-incoming-answer { position: relative; z-index: 1; display: inline-flex; width: 56px; height: 56px; align-items: center; justify-content: center; border: 0; border-radius: 50%; background: #39c99a; color: #fff; box-shadow: 0 5px 18px rgba(40, 186, 137, .34); cursor: pointer; }
		.doclinc-incoming-answer img { width: 24px; height: 24px; filter: brightness(0) invert(1); transform: rotate(-12deg); }
		.doclinc-incoming-action.reject { align-self: center; min-height: 46px; padding: 0 20px; background: #fff2f3; border: 1px solid #ffc8ce; color: #c53947; }
		.doclinc-incoming-security { margin: 14px 0 0; color: #5f7078; font-size: 11px; text-align: center; }
		@media (max-width: 520px) {
			.chat-shell.is-active { box-shadow: none; }
			.chat-shell.is-active .chat-header { padding-inline: 12px; }
			.chat-list { padding-inline: 14px; }
			.chat-form { padding-inline: 12px; }
			.doclinc-incoming-surface { padding-inline: 24px; }
		}
		@media (min-width: 421px) and (max-width: 520px) {
			body:has(.chat-shell.is-active) { min-height: 1600px; display: flex; align-items: center; justify-content: center; }
			.chat-shell.is-active { width: 390px; height: 1078px; min-height: 1078px; margin: auto; }
			.chat-shell.is-active .chat-info-banner { min-height: 26px; height: 26px; padding-block: 0; }
			.chat-shell.is-active .chat-info-banner__label,
			.chat-shell.is-active .chat-info-banner__meta { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
		}
		@media (min-width: 768px) {
			.chat-shell.is-active { min-height: min(100dvh, 900px); margin: 18px auto; border-radius: 22px; }
			.doclinc-incoming-call { align-items: center; background: rgba(17, 30, 28, .64); }
			.doclinc-incoming-surface { min-height: 844px; max-height: calc(100dvh - 40px); border-radius: 28px; box-shadow: 0 22px 80px rgba(0, 0, 0, .24); }
		}
	</style>
	<!-- Lapisan bento dimuat SETELAH blok <style> di atas, bukan sebelumnya.
	     Sebelumnya urutan itu disengaja supaya gaya chat yang lama menang; untuk
	     lapisan ini kebalikannya yang diperlukan - B24 harus bisa menyetel ulang
	     bentuk, warna dan ukuran tanpa menaikkan setiap selektor ke !important. -->
	<!-- Urutan menentukan di sini, dan urutannya disengaja.
	     `doclinc-bento.css` dimuat PALING AKHIR supaya lapisan B24 menang atas
	     blok <style> milik view ini DAN atas doclinc-livekit.css. Keduanya
	     memuat aturan avatar chat dengan spesifisitas yang sama
	     (`.chat-shell.is-active .chat-avatar` = 0,3,0), dan sebelum ini
	     livekit dimuat setelah bento - sehingga ukuran, bentuk dan
	     penumpukan avatar yang ditulis di B24.8 selalu kalah, dan avatar
	     dirender menurut aturan lama (40px, radius 50%, tanpa tumpukan
	     fallback). livekit tidak punya aturan untuk permukaan lain di halaman
	     ini, jadi memindahkannya ke atas tidak mengubah apa pun selain ini. -->
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-livekit.css') . '?v=' . rawurlencode((string) @filemtime(FCPATH . 'assets/css/doclinc-livekit.css'))); ?>">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-bento.css') . '?v=' . rawurlencode((string) @filemtime(FCPATH . 'assets/css/doclinc-bento.css'))); ?>">
	<?php /* Layer call-only terakhir: memuat ulang ruang panggilan tanpa
	         menimpa aturan chat. Cache-buster mengikuti mtime berkas. */ ?>
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-livekit-studio.css')); ?>?v=<?= (int) @filemtime(FCPATH . 'assets/css/doclinc-livekit-studio.css'); ?>">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-reference-ui.css') . '?v=' . rawurlencode((string) @filemtime(FCPATH . 'assets/css/doclinc-reference-ui.css'))); ?>">
	<!-- Font Awesome belum dimuat halaman ini sebelum lapisan bento. Sistem ikon
	     aplikasi memakainya untuk glyph di luar konten (design.md), dan keadaan
	     hanya-baca memakai satu glyph kunci dari keluarga itu. Tanpa baris ini
	     `content: "\f023"` hanya akan jadi kotak kosong. -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body class="dl-warga-chat dl-chat-bento<?= $is_nakes_role ? ' nakes-chat' : ''; ?>">
	<div class="chat-shell is-active" data-chat-role="<?= html_escape($current_role); ?>" data-request-id="<?= html_escape($request_id); ?>">
		<header class="chat-header" data-chat-surface="active-room">
			<a href="<?= html_escape($back_url); ?>" class="chat-back" aria-label="Kembali">
				<i class="fas fa-chevron-left" aria-hidden="true"></i>
			</a>
			<span class="chat-avatar-wrap" aria-hidden="true">
				<?php /* Fallback inisial SELALU ada di DOM, foto ditumpuk di atasnya.
				         Sebelumnya keduanya alternatif: kalau tidak ada foto, hanya
				         inisial yang dirender - sehingga poller peserta tidak punya
				         <img> untuk diisi begitu foto tersedia, dan <img> yang gagal
				         memuat tidak punya apa pun untuk jatuh ke belakangnya.
				         `onerror` membuang gambar rusak supaya inisial di bawahnya
				         yang terlihat, bukan ikon gambar pecah. */ ?>
				<span class="chat-avatar-fallback" id="chatPartnerAvatarFallback" data-chat-photo-state="fallback"><?= html_escape($partner_initials); ?></span>
				<?php if ($partner_photo_src !== '') : ?><img class="chat-avatar js-profile-photo" id="chatPartnerAvatar" src="<?= html_escape($partner_photo_src); ?>" data-chat-photo-state="photo" alt="" width="44" height="44" onerror="this.remove();">
				<?php endif; ?>
				<span class="chat-presence-dot" data-presence="<?= $partner_presence_online ? 'online' : 'offline'; ?>"></span>
			</span>
			<div class="chat-title">
				<p class="chat-title-text" id="chatPartnerName"><?= html_escape($partner_name); ?></p>
			<p class="chat-title-subtext"><span class="chat-presence-status" data-presence="<?= $partner_presence_online ? 'online' : 'offline'; ?>"><span class="chat-presence-status__dot" aria-hidden="true"></span><?= $partner_presence_online ? 'Online' : 'Offline'; ?></span></p>
			</div>
			<?php if ($can_start_call) : ?>
				<div class="chat-call-actions" aria-label="Aksi panggilan">
					<button type="button" class="chat-call-button doclinc-call-start" data-call-mode="audio" data-request-id="<?= html_escape($request_id); ?>" aria-label="Mulai panggilan suara">
						<i class="fas fa-phone" aria-hidden="true"></i>
					</button>
					<button type="button" class="chat-call-button doclinc-call-start" data-call-mode="video" data-request-id="<?= html_escape($request_id); ?>" aria-label="Mulai panggilan video">
						<i class="fas fa-video" aria-hidden="true"></i>
					</button>
				</div>
			<?php endif; ?>
		</header>

		<main class="chat-list" id="chatMessages">
			<?php if ($auto_visit_location) : ?><div class="chat-muted" id="chatVisitLocationStatus" role="status" aria-live="polite"></div><?php endif; ?>
			<div class="chat-date-separator" id="chatDateSeparator" hidden><span></span></div>
			<div class="chat-muted">Memuat pesan...</div>
		</main>

		<form class="chat-form" id="chatForm">
			<?php if ($can_send) : ?>
				<div class="chat-input-row">
					<input type="file" id="imageInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" hidden>
					<?php /* Tombol "Lampirkan gambar" dihapus atas permintaan: ia dan tombol
					         kamera membuka pemilih berkas yang sama, jadi yang tersisa
					         hanya satu jalur - kamera. `#imageInput` dipertahankan
					         karena tombol kamera memakainya. */ ?>
					<button class="chat-icon-button chat-upload" id="imageButton" type="button" aria-label="Kirim gambar">
						<i class="fas fa-camera" aria-hidden="true"></i>
					</button>
					<textarea class="chat-input" id="messageText" rows="1" maxlength="2000" placeholder="Ketik pesan . . ." required></textarea>
					<?php /* Tombol kirim membawa gayanya sendiri sebagai inline style.
					         Tiga lembar gaya memaku tombol ini dengan !important
					         (doclinc-livekit.css:673 dan :691, thread_v.php:392), dan
					         menyelesaikannya lewat urutan muat sudah dua kali gagal
					         terlihat. Inline `!important` adalah satu-satunya deklarasi
					         yang menang atas semua !important di lembar gaya, tanpa
					         bergantung urutan.

					         BENTUKNYA lingkaran 40px, sama persis dengan tombol kamera
					         di sebelah kiri kolom pesan. Sebelumnya tombol ini memakai
					         sudut 6px dari keluarga bentuk B17, dan di baris ini bentuk
					         itu terbaca sebagai tombol asing: barisnya pil (radius 24px)
					         dan tombol di dalamnya bulat. Satu kontrol yang bentuknya
					         berbeda dari tetangganya dalam satu baris akan selalu
					         terbaca sebagai kesalahan, bukan sebagai penekanan.

					         Bibir inset 3px dari B17 juga dibuang di sini - pada
					         lingkaran kecil ia terbaca sebagai bayangan aneh, dan
					         tombol kamera tidak punya. Yang tersisa hanya bayangan
					         jatuh tipis yang sama dengan tetangganya. */ ?>
					<button class="chat-send" type="submit" aria-label="Kirim pesan"
						style="width: 44px !important; height: 44px !important; min-width: 44px !important; min-height: 44px !important; padding: 0 !important; border: 0 !important; border-radius: 50% !important; background-color: #2D7D56 !important; background-image: linear-gradient(150deg, #2D7D56 0%, #1F6F4B 100%) !important; color: #FFFFFF !important; box-shadow: 0 4px 10px rgba(63, 141, 104, .25) !important;">
						<i class="fas fa-paper-plane" aria-hidden="true" style="display: inline-block !important; color: #FFFFFF !important; font-family: 'Font Awesome 5 Free' !important; font-weight: 900 !important; font-style: normal !important; font-size: 16px !important; line-height: 1 !important; visibility: visible !important; opacity: 1 !important;"></i>
					</button>
				</div>
				<div class="chat-image-feedback" id="chatImageFeedback" role="status" aria-live="polite" hidden></div>
			<?php else : ?>
				<div class="chat-readonly"><?= html_escape($readonly_message); ?></div>
			<?php endif; ?>
		</form>
	</div>

	<?php if ($call_ui_enabled) : ?>
		<div class="doclinc-call-modal" id="doclincCallModal" role="dialog" aria-modal="true" aria-labelledby="doclincCallTitle" aria-hidden="true">
			<div class="doclinc-call-panel">
				<div class="doclinc-call-head">
					<div class="doclinc-call-brand-lockup">
						<img class="doclinc-call-brand" src="<?= html_escape(doclinc_ui_asset('livekit.brand_logo')); ?>" alt="DokLinc" width="164" height="73">
						<span class="doclinc-call-divider" aria-hidden="true"></span>
						<div class="doclinc-call-context">
							<span class="doclinc-call-eyebrow">Konsultasi dengan</span>
							<h2 class="doclinc-call-title" id="doclincCallTitle"><?= html_escape($partner_name); ?></h2>
							<div class="doclinc-call-provider-meta"><span class="doclinc-call-provider-role"><?= html_escape($partner_subtitle); ?></span></div>
							<div class="doclinc-call-connection">
								<span class="doclinc-call-connection-text" id="doclincCallStatus" role="status" aria-live="polite">Siap bergabung</span>
							</div>
						</div>
					</div>
					<div class="doclinc-call-head-close">
						<button type="button" class="doclinc-call-header-close" id="doclincCallHeaderClose" aria-label="Tutup tampilan panggilan" title="Tutup">
							<img src="<?= html_escape(doclinc_ui_asset('livekit.close')); ?>" alt="" aria-hidden="true" width="20" height="20">
						</button>
					</div>
				</div>
				<div class="doclinc-call-stage" data-call-mode="video">
					<div class="doclinc-call-remote" id="doclincCallRemote">
						<div class="doclinc-call-empty" id="doclincCallEmpty">
							<?php if ($voice_has_photo) : ?>
								<div class="doclinc-call-empty-avatar">
									<img data-voice-profile-photo src="<?= html_escape($voice_photo_src); ?>" alt="">
								</div>
							<?php else : ?>
								<div class="doclinc-call-empty-initials" aria-hidden="true"><?= html_escape($partner_initials); ?></div>
							<?php endif; ?>
							<span class="doclinc-call-empty-name"><?= html_escape($partner_name); ?></span>
							<?php if ($voice_partner_role !== '') : ?><span class="doclinc-call-empty-role"><?= html_escape($voice_partner_role); ?></span><?php endif; ?>
							<span class="doclinc-call-empty-waiting" id="doclincCallEmptyText">Menunggu video</span>
							<span class="doclinc-call-empty-support">Kami akan membantu konsultasi Anda</span>
							<span class="doclinc-call-waveform" data-voice-waveform data-lottie-src="<?= html_escape(base_url('assets/doclinc_ui/lottie/soundwave.json')); ?>" data-lottie-autoplay="true" data-lottie-loop="true" aria-hidden="true"></span>
						</div>
					</div>
					<div class="doclinc-call-provider-overlay" aria-hidden="true">
						<span class="doclinc-call-status-dot" aria-hidden="true"></span>
						<div><strong id="doclincCallOverlayName"><?= html_escape($partner_name); ?></strong><span id="doclincCallOverlayRole"><?= html_escape($partner_subtitle); ?></span></div>
					</div>
					<div class="doclinc-call-local is-hidden" id="doclincCallLocal"></div>
				</div>
				<div class="doclinc-call-prejoin" id="doclincCallPrejoin">
					<p class="doclinc-call-prejoin-title">Panggilan untuk konsultasi aktif</p>
					<p class="doclinc-call-prejoin-text">Pastikan kamera dan mikrofon perangkat dapat digunakan.</p>
					<button type="button" class="doclinc-call-join" id="doclincCallJoin">Gabung sekarang</button>
					<button type="button" class="doclinc-call-dismiss" id="doclincCallDismiss">Tutup</button>
				</div>
				<div class="doclinc-call-controls is-hidden" id="doclincCallControls">
					<button type="button" class="doclinc-call-control" id="doclincCallMore" title="Lainnya" aria-label="Buka opsi lainnya" aria-expanded="false">
						<img src="<?= html_escape(doclinc_ui_asset('more')); ?>" alt="" aria-hidden="true" width="24" height="24">
					</button>
					<button type="button" class="doclinc-call-control is-active" id="doclincCallCamera" title="Matikan kamera" aria-label="Matikan kamera" aria-pressed="false">
						<img src="<?= html_escape(doclinc_ui_asset('video_call')); ?>" alt="" aria-hidden="true" width="24" height="24">
					</button>
					<button type="button" class="doclinc-call-control is-active" id="doclincCallSpeaker" title="Aktifkan suara" aria-label="Aktifkan suara" aria-pressed="false">
						<img src="<?= html_escape(doclinc_ui_asset('speaker')); ?>" alt="" aria-hidden="true" width="24" height="24">
					</button>
					<button type="button" class="doclinc-call-control is-active" id="doclincCallMic" title="Bisukan mikrofon" aria-label="Bisukan mikrofon" aria-pressed="false">
						<img src="<?= html_escape(doclinc_ui_asset('mute')); ?>" alt="" aria-hidden="true" width="24" height="24">
					</button>
					<button type="button" class="doclinc-call-control end-call" id="doclincCallEnd" title="Akhiri panggilan" aria-label="Akhiri panggilan">
						<img src="<?= html_escape(doclinc_ui_asset('livekit.end_call')); ?>" alt="" aria-hidden="true" width="24" height="24">
					</button>
				</div>
				<aside class="doclinc-call-popover is-hidden" id="doclincCallMorePanel" aria-label="Opsi panggilan">
					<button type="button" class="doclinc-call-secondary" id="doclincCallChat" title="Buka obrolan" aria-label="Buka obrolan">
						<img src="<?= html_escape(doclinc_ui_asset('chat')); ?>" alt="" aria-hidden="true" width="20" height="20">
						<span>Obrolan</span>
					</button>
					<button type="button" class="doclinc-call-secondary" id="doclincCallSwitch" title="Ganti kamera" aria-label="Ganti kamera">
						<img src="<?= html_escape(doclinc_ui_asset('camera')); ?>" alt="" aria-hidden="true" width="20" height="20">
						<span>Ganti kamera</span>
					</button>
					<button type="button" class="doclinc-call-secondary" id="doclincCallMinimize" title="Minimalkan" aria-label="Minimalkan panggilan">
						<img src="<?= html_escape(doclinc_ui_asset('livekit.minimize')); ?>" alt="" aria-hidden="true" width="20" height="20">
						<span>Minimalkan</span>
					</button>
					<button type="button" class="doclinc-call-secondary" id="doclincCallClose" title="Tutup tampilan panggilan" aria-label="Tutup tampilan panggilan">
						<img src="<?= html_escape(doclinc_ui_asset('livekit.close')); ?>" alt="" aria-hidden="true" width="20" height="20">
						<span>Tutup tampilan</span>
					</button>
				</aside>
			</div>
		</div>
		<button type="button" class="doclinc-active-call is-hidden" id="doclincActiveCall" aria-label="Buka panggilan aktif">
			<span class="doclinc-active-call-icon" aria-hidden="true">
				<img class="dl-semantic-icon" src="<?= html_escape(doclinc_ui_asset('voice_call')); ?>" alt="" width="18" height="18">
			</span>
			<span class="doclinc-active-call-main">
				<span class="doclinc-active-call-title">Panggilan aktif</span>
				<span class="doclinc-active-call-meta" id="doclincCallElapsed">00:00</span>
			</span>
			<span class="doclinc-active-call-restore">Buka</span>
		</button>
		<script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
		<script src="<?= html_escape(base_url('assets/js/doclinc-livekit-ringtone.js')); ?>"></script>
		<script src="<?= html_escape(base_url('assets/js/doclinc-livekit-audio.js')); ?>"></script>
		<script src="<?= html_escape(base_url('assets/js/doclinc-voice-waveform.js')); ?>"></script>
	<?php else : ?>
		<?php /* TODO #16D: render incoming call invitation here after reliable call invite signaling exists. */ ?>
	<?php endif; ?>

	<?php if ($can_receive_call) : ?>
		<div class="doclinc-incoming-call is-hidden" id="doclincIncomingCall" aria-live="polite">
			<section class="doclinc-incoming-surface" aria-labelledby="doclincIncomingCaller">
				<div class="doclinc-incoming-badge">PANGGILAN MASUK</div>
				<div class="doclinc-incoming-identity">
					<div class="doclinc-incoming-avatar-wrap">
						<?php if ($voice_has_photo) : ?>
							<img class="doclinc-incoming-avatar" id="doclincIncomingAvatar" src="<?= html_escape($voice_photo_src); ?>" alt="">
						<?php else : ?>
							<span class="doclinc-incoming-initials" aria-hidden="true"><?= html_escape($partner_initials); ?></span>
						<?php endif; ?>
					</div>
					<p class="doclinc-incoming-name" id="doclincIncomingCaller"><?= html_escape($partner_name); ?></p>
					<p class="doclinc-incoming-mode" id="doclincIncomingType"><img id="doclincIncomingModeIcon" src="<?= html_escape(doclinc_ui_asset('voice_call') . '?v=1'); ?>" alt="" aria-hidden="true">Panggilan suara</p>
					<span class="doclinc-incoming-waveform" id="doclincIncomingWaveform" data-incoming-waveform data-lottie-src="<?= html_escape(base_url('assets/doclinc_ui/lottie/soundwave.json')); ?>" aria-hidden="true"></span>
				</div>
				<div class="doclinc-incoming-actions">
					<div class="doclinc-incoming-slider" id="doclincIncomingSlider">
						<span class="doclinc-incoming-slider-label">Geser untuk menjawab</span>
						<button type="button" class="doclinc-incoming-answer" id="doclincIncomingAnswer" aria-label="Terima panggilan" title="Terima panggilan"><img src="<?= html_escape(doclinc_ui_asset('voice_call') . '?v=1'); ?>" alt="" aria-hidden="true"></button>
					</div>
					<button type="button" class="doclinc-incoming-action reject" id="doclincIncomingReject" aria-label="Tolak panggilan">Tolak Panggilan</button>
				</div>
				<p class="doclinc-incoming-security">Panggilan aman melalui DokLinc</p>
			</section>
		</div>
	<?php endif; ?>

	<?php if ($call_ui_enabled) : ?>
		<script>
			(function() {
				const requestId = <?= json_encode($request_id); ?>;
				const initialCounterpart = {
					name: <?= json_encode($partner_name); ?>,
					role: <?= json_encode($current_role === 'dokter' ? 'Warga' : 'Dokter'); ?>
				};
				const voiceCounterpartRole = <?= json_encode($voice_partner_role); ?>;
				const canStartCall = <?= json_encode($can_start_call); ?>;
				const canReceiveCall = <?= json_encode($can_receive_call); ?>;
				const startCallUrl = <?= json_encode($call_start_url); ?>;
				const endCallUrl = <?= json_encode($call_end_url); ?>;
				const nakesStatusUrl = <?= json_encode($call_nakes_status_url); ?>;
				const incomingCallUrl = <?= json_encode($call_incoming_url); ?>;
				const answerCallUrl = <?= json_encode($call_answer_url); ?>;
				const rejectCallUrl = <?= json_encode($call_reject_url); ?>;
				const wargaStatusUrl = <?= json_encode($call_warga_status_url); ?>;
				const state = {
					callId: null,
					room: null,
					mode: 'video',
					micEnabled: true,
					cameraEnabled: true,
					speakerEnabled: false,
					facingMode: 'user',
					localTracks: [],
					connecting: false,
					minimized: false,
					callStartedAt: null,
					elapsedTimer: null,
					incomingCall: null,
					incomingPollTimer: null,
					statusPollTimer: null,
					statusPollMs: 3000,
					incomingFailures: 0,
					incomingPollSequence: 0,
							incomingRejecting: false,
							incomingAnswering: false,
							incomingSliderPointerId: null,
							incomingSliderStartX: 0,
					audioController: null,
					incomingRingtone: null
				};
				const elements = {};
				const remoteTrackRegistry = new Map();
				const remoteTrackFallbackKeys = new WeakMap();
				let remoteTrackFallbackSequence = 0;
				let activeRemoteParticipantKey = '';
				let activeRemoteParticipantIdentity = '';

				function byId(id) {
					return document.getElementById(id);
				}

				function sdk() {
					return window.LivekitClient || window.LiveKitClient || window.livekitClient || null;
				}

				function cacheElements() {
					elements.modal = byId('doclincCallModal');
					elements.callTitle = byId('doclincCallTitle');
					elements.providerRole = document.querySelector('.doclinc-call-provider-role');
					elements.emptyName = document.querySelector('.doclinc-call-empty-name');
					elements.emptyRole = document.querySelector('.doclinc-call-empty-role');
					elements.overlayName = byId('doclincCallOverlayName');
					elements.overlayRole = byId('doclincCallOverlayRole');
					elements.status = byId('doclincCallStatus');
					elements.remote = byId('doclincCallRemote');
					elements.stage = document.querySelector('.doclinc-call-stage');
					elements.empty = byId('doclincCallEmpty');
					elements.voicePhoto = document.querySelector('[data-voice-profile-photo]');
					elements.voiceFallback = null;
					if (elements.voicePhoto) {
						elements.voicePhoto.addEventListener('error', function() {
							elements.voicePhoto.classList.add('is-hidden');
						});
					}
					elements.emptyText = byId('doclincCallEmptyText');
					elements.local = byId('doclincCallLocal');
					elements.prejoin = byId('doclincCallPrejoin');
					elements.controls = byId('doclincCallControls');
					elements.join = byId('doclincCallJoin');
					elements.mic = byId('doclincCallMic');
					elements.camera = byId('doclincCallCamera');
					elements.speaker = byId('doclincCallSpeaker');
					elements.chat = byId('doclincCallChat');
					elements.switchCamera = byId('doclincCallSwitch');
					elements.end = byId('doclincCallEnd');
					elements.close = byId('doclincCallClose');
					elements.headerClose = byId('doclincCallHeaderClose');
					elements.dismiss = byId('doclincCallDismiss');
					elements.minimize = byId('doclincCallMinimize');
					elements.more = byId('doclincCallMore');
					elements.morePanel = byId('doclincCallMorePanel');
					elements.activeCall = byId('doclincActiveCall');
					elements.elapsed = byId('doclincCallElapsed');
					elements.incoming = byId('doclincIncomingCall');
					elements.incomingCaller = byId('doclincIncomingCaller');
					elements.incomingType = byId('doclincIncomingType');
					elements.incomingModeIcon = byId('doclincIncomingModeIcon');
					elements.incomingWaveform = byId('doclincIncomingWaveform');
					elements.incomingSlider = byId('doclincIncomingSlider');
					elements.incomingAnswer = byId('doclincIncomingAnswer');
					elements.incomingReject = byId('doclincIncomingReject');
				}

				function remoteParticipantMetadata(participant) {
					if (!participant || participant.metadata == null) {
						return null;
					}
					if (typeof participant.metadata === 'object' && !Array.isArray(participant.metadata)) {
						return participant.metadata;
					}
					if (typeof participant.metadata !== 'string' || participant.metadata.trim() === '') {
						return null;
					}
					try {
						const parsed = JSON.parse(participant.metadata);
						return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
					} catch (error) {
						return null;
					}
				}

				function remoteParticipantIdentity(participant) {
					if (!participant || participant.isLocal === true) {
						return null;
					}
					const metadata = remoteParticipantMetadata(participant);
					if (!metadata || Number(metadata.request_id) !== Number(requestId)) {
						return null;
					}
					const role = String(metadata.role || '').toLowerCase();
					const roleLabel = role === 'warga' ? 'Warga' : (role === 'dokter' ? 'Dokter' : (role === 'nakes' ? 'Tenaga kesehatan' : ''));
					const name = String(participant.name || metadata.name || '').trim();
					const participantIdentity = String(participant.identity || '').trim();
					const expectedIdentity = role + '-' + String(metadata.user_id || '').trim();
					if (!roleLabel || !name || name.length > 160 || !participantIdentity || (metadata.user_id && participantIdentity !== expectedIdentity)) {
						return null;
					}
					return {
						name: name,
						role: roleLabel,
						key: remoteParticipantKey(participant)
					};
				}

				function setCounterpartIdentity(identity) {
					const value = identity || initialCounterpart;
					if (elements.callTitle) {
						elements.callTitle.textContent = value.name;
					}
					if (elements.emptyName) {
						elements.emptyName.textContent = value.name;
					}
					if (elements.emptyRole) {
						elements.emptyRole.textContent = value.role;
					}
					if (elements.overlayName) {
						elements.overlayName.textContent = value.name;
					}
					if (elements.overlayRole) {
						elements.overlayRole.textContent = value.role;
					}
					if (elements.modal) {
						elements.modal.setAttribute('aria-label', 'Panggilan dengan ' + value.name);
					}
				}

				function updateCounterpartFromParticipant(participant) {
					const identity = remoteParticipantIdentity(participant);
					if (!identity) {
						return false;
					}
					activeRemoteParticipantKey = identity.key;
					activeRemoteParticipantIdentity = String(participant.identity || '');
					if (elements.remote) {
						elements.remote.dataset.participantIdentity = activeRemoteParticipantIdentity;
					}
					setCounterpartIdentity(identity);
					return true;
				}

				function applyCallModePresentation() {
					const isAudio = state.mode === 'audio';
					if (elements.modal) {
						elements.modal.classList.toggle('is-audio', isAudio);
						elements.modal.classList.toggle('is-video', !isAudio);
						elements.modal.setAttribute('data-call-mode', isAudio ? 'audio' : 'video');
					}
					if (elements.stage) {
						elements.stage.classList.toggle('is-audio', isAudio);
						elements.stage.classList.toggle('is-video', !isAudio);
						elements.stage.setAttribute('data-call-mode', isAudio ? 'audio' : 'video');
					}
					if (elements.providerRole && !isAudio) {
						elements.providerRole.textContent = <?= json_encode($partner_subtitle); ?>;
					} else if (elements.providerRole) {
						elements.providerRole.textContent = voiceCounterpartRole;
					}
					if (elements.emptyText && isAudio) {
						elements.emptyText.textContent = 'Audio tersambung';
					}
				}

				function toggleMore() {
					if (!elements.morePanel) return;
					const hidden = elements.morePanel.classList.toggle('is-hidden');
					if (elements.more) {
						elements.more.setAttribute('aria-expanded', hidden ? 'false' : 'true');
						elements.more.classList.toggle('is-active', !hidden);
					}
				}

				function audioController() {
					if (state.audioController) {
						return state.audioController;
					}
					if (!window.DoclincLivekitAudio || typeof window.DoclincLivekitAudio.createController !== 'function') {
						return null;
					}
					state.audioController = window.DoclincLivekitAudio.createController({
						getRoom: function() {
							return state.room;
						},
						getRemoteMediaNodes: function() {
							return elements.remote ? elements.remote.querySelectorAll('audio,video') : [];
						},
						speakerGain: 1.8
					});
					return state.audioController;
				}

				function incomingRingtone() {
					if (state.incomingRingtone) {
						return state.incomingRingtone;
					}
					if (!window.DoclincLivekitRingtone || typeof window.DoclincLivekitRingtone.createController !== 'function') {
						return null;
					}
					state.incomingRingtone = window.DoclincLivekitRingtone.createController({
						windowObject: window,
						audioUrl: <?= json_encode($call_ringtone_url); ?>
					});
					return state.incomingRingtone;
				}

				function startIncomingRingtone(callId) {
					const controller = incomingRingtone();
					if (controller && typeof controller.start === 'function') {
						controller.start(callId).catch(function() {});
					}
				}

				function stopIncomingRingtone(callId) {
					if (state.incomingRingtone && typeof state.incomingRingtone.stop === 'function') {
						state.incomingRingtone.stop(callId);
					}
				}

				function releaseIncomingRingtone() {
					if (state.incomingRingtone && typeof state.incomingRingtone.release === 'function') {
						state.incomingRingtone.release();
					}
					state.incomingRingtone = null;
				}

				function updateSpeakerControl(enabled) {
					state.speakerEnabled = enabled === true;
					if (!elements.speaker) {
						return;
					}
					elements.speaker.classList.toggle('is-active', state.speakerEnabled);
					elements.speaker.setAttribute('aria-pressed', state.speakerEnabled ? 'true' : 'false');
					elements.speaker.setAttribute('aria-label', state.speakerEnabled ? 'Matikan suara' : 'Aktifkan suara');
					elements.speaker.title = state.speakerEnabled ? 'Matikan suara' : 'Aktifkan suara';
				}

				function releaseRemoteAudio() {
					if (state.audioController && typeof state.audioController.release === 'function') {
						state.audioController.release();
					}
					state.audioController = null;
					updateSpeakerControl(false);
				}

				function resumeRemoteAudio() {
					const controller = audioController();
					if (!controller || typeof controller.resumeAudio !== 'function') {
						return Promise.resolve(false);
					}
					return controller.resumeAudio();
				}

				function toggleSpeakerOutput() {
					const controller = audioController();
					if (!controller || typeof controller.toggleSpeaker !== 'function') {
						setStatus('Kontrol suara belum didukung perangkat ini.', true);
						return;
					}
					if (elements.speaker) {
						elements.speaker.disabled = true;
					}
					controller.toggleSpeaker().then(function(result) {
						const enabled = !!(result && result.enabled);
						updateSpeakerControl(enabled);
						if (!enabled) {
							setStatus('Suara nonaktif.');
							return;
						}
						if (result.outputSelected) {
							setStatus('Suara aktif' + (result.outputLabel ? ': ' + result.outputLabel : '.'));
							return;
						}
						if (result.boosted) {
							setStatus('Suara aktif. Volume diperkuat.');
							return;
						}
						setStatus('Perangkat membatasi pemilihan output. Gunakan kontrol speaker perangkat.', true);
					}).catch(function() {
						updateSpeakerControl(false);
						setStatus('Suara belum dapat diaktifkan di perangkat ini.', true);
					}).finally(function() {
						if (elements.speaker) {
							elements.speaker.disabled = false;
						}
					});
				}

				function setStatus(message, isError) {
					if (!elements.status) {
						return;
					}
					elements.status.textContent = message || '';
					elements.status.classList.toggle('is-error', !!isError);
					if (elements.emptyText && message) {
						elements.emptyText.textContent = message;
					}
				}

				function setCallActive(active) {
					if (elements.prejoin) {
						elements.prejoin.classList.toggle('is-hidden', !!active);
					}
					if (elements.controls) {
						elements.controls.classList.toggle('is-hidden', !active);
					}
				}

				function formatElapsed(milliseconds) {
					const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
					const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
					const seconds = String(totalSeconds % 60).padStart(2, '0');
					return minutes + ':' + seconds;
				}

				function updateElapsed() {
					if (!elements.elapsed || !state.callStartedAt) {
						return;
					}
					elements.elapsed.textContent = formatElapsed(Date.now() - state.callStartedAt);
				}

				function startElapsedTimer() {
					if (!state.callStartedAt) {
						state.callStartedAt = Date.now();
					}
					updateElapsed();
					if (!state.elapsedTimer) {
						state.elapsedTimer = window.setInterval(updateElapsed, 1000);
					}
				}

				function stopElapsedTimer() {
					if (state.elapsedTimer) {
						window.clearInterval(state.elapsedTimer);
					}
					state.elapsedTimer = null;
					state.callStartedAt = null;
					if (elements.elapsed) {
						elements.elapsed.textContent = '00:00';
					}
				}

				function hasLiveCall() {
					return !!state.room || state.connecting;
				}

				function updateFloatingCall() {
					if (!elements.activeCall) {
						return;
					}
					elements.activeCall.classList.toggle('is-hidden', !(state.minimized && hasLiveCall()));
				}

				function showCallScreen() {
					if (!elements.modal) {
						return;
					}
					state.minimized = false;
					applyCallModePresentation();
					elements.modal.classList.add('is-open');
					elements.modal.setAttribute('aria-hidden', 'false');
					updateFloatingCall();
				}

				function minimizeCallScreen() {
					if (elements.modal) {
						elements.modal.classList.remove('is-open');
						elements.modal.setAttribute('aria-hidden', 'true');
					}
					state.minimized = hasLiveCall();
					updateFloatingCall();
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
					const hasRemoteVideo = hasRegisteredRemoteVideo();
					const hasLocalVideo = !!(elements.local && elements.local.querySelector('video'));
					elements.empty.classList.toggle('is-hidden', hasRemoteVideo || hasLocalVideo);
					applyStagePresentation(hasLocalVideo, hasRemoteVideo);
				}

				function applyStagePresentation(hasLocalVideo, hasRemoteVideo) {
					if (!elements.stage) {
						return;
					}
					const localMain = state.mode === 'video' && !!hasLocalVideo && !hasRemoteVideo;
					elements.stage.classList.toggle('is-local-main', localMain);
					elements.stage.classList.toggle('is-remote-main', !!hasRemoteVideo);
					if (elements.local) {
						elements.local.classList.toggle('is-main', localMain);
					}
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
						elements.local.classList.remove('is-placeholder');
						elements.local.classList.remove('is-camera-off');
						elements.local.classList.add('is-hidden');
					}
					applyStagePresentation(false, hasRegisteredRemoteVideo());
				}

				function cleanupCall(message) {
					if (state.room) {
						try {
							state.room.disconnect();
						} catch (error) {}
					}
					state.room = null;
					state.callId = null;
					state.connecting = false;
					state.minimized = false;
					stopLocalTracks();
					releaseRemoteAudio();
					stopElapsedTimer();
					stopStatusPolling();
					clearRemoteTracks();
					setCallActive(false);
					setStatus(message || 'Panggilan berakhir');
					updateFloatingCall();
				}

				function endLocalCall(message) {
					cleanupCall(message || 'Panggilan berakhir');
					if (elements.modal) {
						elements.modal.classList.remove('is-open');
						elements.modal.setAttribute('aria-hidden', 'true');
					}
				}

				function openCall(mode) {
					cacheElements();
					if (!elements.modal) {
						return;
					}
					if (hasLiveCall()) {
						showCallScreen();
						return;
					}
					state.mode = mode === 'audio' ? 'audio' : 'video';
					applyCallModePresentation();
					state.micEnabled = true;
					state.cameraEnabled = state.mode === 'video';
					updateSpeakerControl(false);
					if (elements.camera) {
						elements.camera.classList.toggle('is-off', state.mode !== 'video');
						elements.camera.classList.toggle('is-active', state.mode === 'video');
						elements.camera.setAttribute('aria-pressed', state.mode !== 'video' ? 'true' : 'false');
					}
					if (elements.mic) {
						elements.mic.classList.add('is-active');
						elements.mic.classList.remove('is-off');
					}
					setCallActive(false);
					setStatus('Siap bergabung');
					showCallScreen();
					if (canStartCall) {
						window.setTimeout(joinCall, 0);
					}
				}

				function endCall() {
					const callId = state.callId;
					if (canStartCall && callId) {
						postForm(endCallUrl, {
							call_id: callId
						}).catch(function() {}).finally(function() {
							endLocalCall('Panggilan berakhir');
						});
						return;
					}
					endLocalCall('Panggilan berakhir');
				}

				function normalizeMediaNodes(value) {
					let candidates = [];
					if (value && value.nodeType === 1) {
						candidates = [value];
					} else if (value && typeof Symbol !== 'undefined' && value[Symbol.iterator]) {
						candidates = Array.from(value);
					} else if (value && typeof value.length === 'number') {
						candidates = Array.prototype.slice.call(value);
					}
					return candidates.filter(function(node, index) {
						if (!node || node.nodeType !== 1 || ['VIDEO', 'AUDIO'].indexOf(node.tagName) === -1) {
							return false;
						}
						return candidates.indexOf(node) === index;
					});
				}

				function remoteParticipantKey(participant) {
					if (participant && participant.identity != null && String(participant.identity)) {
						return 'identity:' + String(participant.identity);
					}
					if (participant && participant.sid != null && String(participant.sid)) {
						return 'sid:' + String(participant.sid);
					}
					return 'participant:unknown';
				}

				function remotePublicationSid(publication) {
					if (publication && publication.trackSid != null && String(publication.trackSid)) {
						return String(publication.trackSid);
					}
					if (publication && publication.sid != null && String(publication.sid)) {
						return String(publication.sid);
					}
					return '';
				}

				function remoteTrackKey(track, publication, participant) {
					const publicationSid = remotePublicationSid(publication);
					if (publicationSid) {
						return 'publication:' + publicationSid;
					}
					if (track && track.sid != null && String(track.sid)) {
						return 'track:' + String(track.sid);
					}
					const participantKey = encodeURIComponent(remoteParticipantKey(participant));
					const kind = encodeURIComponent(String((publication && publication.kind) || (track && track.kind) || 'unknown'));
					const source = encodeURIComponent(String((publication && publication.source) || (track && track.source) || 'unknown'));
					const mediaTrackId = track && track.mediaStreamTrack && track.mediaStreamTrack.id ? String(track.mediaStreamTrack.id) : '';
					if (mediaTrackId) {
						return 'media:' + participantKey + ':' + kind + ':' + source + ':' + encodeURIComponent(mediaTrackId);
					}
					const fallbackObject = publication && typeof publication === 'object' ? publication : track;
					if (fallbackObject && typeof fallbackObject === 'object') {
						if (!remoteTrackFallbackKeys.has(fallbackObject)) {
							remoteTrackFallbackSequence += 1;
							remoteTrackFallbackKeys.set(fallbackObject, 'object-' + remoteTrackFallbackSequence);
						}
						return 'fallback:' + participantKey + ':' + kind + ':' + source + ':' + remoteTrackFallbackKeys.get(fallbackObject);
					}
					remoteTrackFallbackSequence += 1;
					return 'fallback:' + participantKey + ':' + kind + ':' + source + ':value-' + remoteTrackFallbackSequence;
				}

				function isRemoteMediaNodeAttached(node) {
					return !!(node && elements.remote && node.parentNode && elements.remote.contains(node));
				}

				function removeRemoteMediaNode(node) {
					if (!node || node.nodeType !== 1) {
						return;
					}
					if (elements.local && elements.local.contains(node)) {
						return;
					}
					if (node.parentNode) {
						node.parentNode.removeChild(node);
					}
				}

				function remoteEntryIsAttached(entry) {
					return !!(entry && entry.nodes && entry.nodes.length && entry.nodes.every(isRemoteMediaNodeAttached));
				}

				function hasRegisteredRemoteVideo() {
					let hasVideo = false;
					remoteTrackRegistry.forEach(function(entry) {
						if (hasVideo || !entry || !entry.nodes) {
							return;
						}
						hasVideo = entry.nodes.some(function(node) {
							return node && node.tagName === 'VIDEO' && isRemoteMediaNodeAttached(node);
						});
					});
					return hasVideo;
				}

				function findRemoteTrackEntry(track, publication, participant) {
					const expectedKey = remoteTrackKey(track, publication, participant);
					if (remoteTrackRegistry.has(expectedKey)) {
						return {
							key: expectedKey,
							entry: remoteTrackRegistry.get(expectedKey)
						};
					}
					const publicationSid = remotePublicationSid(publication);
					const participantKey = remoteParticipantKey(participant);
					const hasStableParticipantKey = participantKey !== 'participant:unknown';
					let match = null;
					remoteTrackRegistry.forEach(function(entry, key) {
						if (match || !entry) {
							return;
						}
						const sameParticipant = !participant || entry.participant === participant ||
							(hasStableParticipantKey && entry.participantKey === participantKey);
						if (sameParticipant && (
							(entry.track && entry.track === track) ||
							(entry.publication && entry.publication === publication) ||
							(publicationSid && entry.publicationSid === publicationSid)
						)) {
							match = {
								key: key,
								entry: entry
							};
						}
					});
					return match;
				}

				function detachRemoteEntry(entry) {
					if (!entry) {
						return;
					}
					(entry.nodes || []).forEach(function(node) {
						if (entry.track && typeof entry.track.detach === 'function') {
							try {
								normalizeMediaNodes(entry.track.detach(node)).forEach(removeRemoteMediaNode);
							} catch (error) {}
						}
						removeRemoteMediaNode(node);
					});
				}

				function removeRemoteTrackEntry(key, entry) {
					if (key && remoteTrackRegistry.get(key) === entry) {
						remoteTrackRegistry.delete(key);
					}
					detachRemoteEntry(entry);
				}

				function attachTrack(track, container) {
					if (!track || !container || typeof track.attach !== 'function') {
						return;
					}
					normalizeMediaNodes(track.attach()).forEach(function(element) {
						element.autoplay = true;
						element.playsInline = true;
						if (!container.contains(element)) {
							container.appendChild(element);
						}
					});
					updateEmptyState();
				}

				function attachRemoteTrack(track, publication, participant) {
					if (!track || !elements.remote || typeof track.attach !== 'function' ||
						track.isLocal === true || (participant && participant.isLocal === true)) {
						return null;
					}
					const existingMatch = findRemoteTrackEntry(track, publication, participant);
					if (existingMatch && existingMatch.entry.track === track && remoteEntryIsAttached(existingMatch.entry)) {
						return existingMatch.entry;
					}
					if (existingMatch) {
						removeRemoteTrackEntry(existingMatch.key, existingMatch.entry);
					}

					const key = remoteTrackKey(track, publication, participant);
					let attachedNodes = [];
					try {
						attachedNodes = normalizeMediaNodes(track.attach());
					} catch (error) {
						updateEmptyState();
						return null;
					}
					const storedNodes = [];
					attachedNodes.forEach(function(node) {
						if (elements.local && elements.local.contains(node)) {
							return;
						}
						node.autoplay = true;
						node.playsInline = true;
						node.muted = false;
						node.volume = 1;
						if (!elements.remote.contains(node)) {
							elements.remote.appendChild(node);
						}
						if (elements.remote.contains(node) && storedNodes.indexOf(node) === -1) {
							storedNodes.push(node);
						}
						const controller = audioController();
						if (controller && typeof controller.configureMediaElement === 'function') {
							controller.configureMediaElement(node);
						}
					});
					if (!storedNodes.length) {
						updateEmptyState();
						return null;
					}

					const entry = {
						key: key,
						participantKey: remoteParticipantKey(participant),
						participant: participant || null,
						kind: String((publication && publication.kind) || track.kind || ''),
						track: track,
						trackSid: track.sid != null ? String(track.sid) : '',
						publication: publication || null,
						publicationSid: remotePublicationSid(publication),
						nodes: storedNodes
					};
					remoteTrackRegistry.set(key, entry);
					updateCounterpartFromParticipant(participant);
					updateEmptyState();
					return entry;
				}

				function detachRemoteTrack(track, publication, participant) {
					const match = findRemoteTrackEntry(track, publication, participant);
					if (!match) {
						updateEmptyState();
						return;
					}
					removeRemoteTrackEntry(match.key, match.entry);
					if (!hasRegisteredRemoteVideo() && activeRemoteParticipantKey === remoteParticipantKey(participant)) {
						activeRemoteParticipantKey = '';
						activeRemoteParticipantIdentity = '';
						if (elements.remote) delete elements.remote.dataset.participantIdentity;
						setCounterpartIdentity(null);
					}
					updateEmptyState();
				}

				function clearRemoteParticipant(participant) {
					const participantKey = remoteParticipantKey(participant);
					const hasStableParticipantKey = participantKey !== 'participant:unknown';
					const entriesToRemove = [];
					remoteTrackRegistry.forEach(function(entry, key) {
						if (entry && (entry.participant === participant ||
							(hasStableParticipantKey && entry.participantKey === participantKey))) {
							entriesToRemove.push({
								key: key,
								entry: entry
							});
						}
					});
					entriesToRemove.forEach(function(item) {
						removeRemoteTrackEntry(item.key, item.entry);
					});
					if (activeRemoteParticipantKey === participantKey) {
						activeRemoteParticipantKey = '';
						activeRemoteParticipantIdentity = '';
						if (elements.remote) delete elements.remote.dataset.participantIdentity;
						setCounterpartIdentity(null);
					}
					updateEmptyState();
				}

				function clearRemoteTracks() {
					const entriesToRemove = [];
					remoteTrackRegistry.forEach(function(entry, key) {
						entriesToRemove.push({
							key: key,
							entry: entry
						});
					});
					entriesToRemove.forEach(function(item) {
						removeRemoteTrackEntry(item.key, item.entry);
					});
					remoteTrackRegistry.clear();
					activeRemoteParticipantKey = '';
					activeRemoteParticipantIdentity = '';
					if (elements.remote) delete elements.remote.dataset.participantIdentity;
					setCounterpartIdentity(null);
					if (elements.remote) {
						Array.prototype.slice.call(elements.remote.querySelectorAll('video,audio')).forEach(removeRemoteMediaNode);
					}
					updateEmptyState();
				}

				function handleTrackSubscribed(room, track, publication, participant) {
					if (room && state.room !== room) {
						return;
					}
					attachRemoteTrack(track, publication, participant);
				}

				function handleTrackUnsubscribed(room, track, publication, participant) {
					if (room && state.room !== room) {
						return;
					}
					detachRemoteTrack(track, publication, participant);
				}

				function handleParticipantDisconnected(room, participant) {
					if (room && state.room !== room) {
						return;
					}
					clearRemoteParticipant(participant);
					setStatus('Menunggu lawan bicara bergabung');
				}

				function wireRoom(room) {
					const LiveKit = sdk();
					const events = LiveKit && LiveKit.RoomEvent ? LiveKit.RoomEvent : {};
					room.on(events.TrackSubscribed || 'trackSubscribed', function(track, publication, participant) {
						if (state.room !== room) {
							return;
						}
						handleTrackSubscribed(room, track, publication, participant);
					});
					room.on(events.TrackUnsubscribed || 'trackUnsubscribed', function(track, publication, participant) {
						if (state.room !== room) {
							return;
						}
						handleTrackUnsubscribed(room, track, publication, participant);
					});
					room.on(events.ParticipantConnected || 'participantConnected', function(participant) {
						if (state.room !== room) {
							return;
						}
						updateCounterpartFromParticipant(participant);
						setStatus('Terhubung');
					});
					room.on(events.ParticipantDisconnected || 'participantDisconnected', function(participant) {
						if (state.room !== room) {
							return;
						}
						handleParticipantDisconnected(room, participant);
					});
					room.on(events.Disconnected || 'disconnected', function() {
						if (state.room !== room) {
							return;
						}
						state.room = null;
						state.connecting = false;
						state.minimized = false;
						stopLocalTracks();
						releaseRemoteAudio();
						clearRemoteTracks();
						setStatus('Panggilan terputus');
						setCallActive(false);
						stopElapsedTimer();
						updateFloatingCall();
					});
					room.on(events.Reconnecting || 'reconnecting', function() {
						if (state.room !== room) {
							return;
						}
						setStatus('Menyambungkan ulang...');
					});
					room.on(events.Reconnected || 'reconnected', function() {
						if (state.room !== room) {
							return;
						}
						publishExistingParticipants(room);
						resumeRemoteAudio();
						setStatus('Terhubung kembali');
					});
				}

				function publishExistingParticipants(room) {
					if (!room || state.room !== room || !room.remoteParticipants) {
						return;
					}
					room.remoteParticipants.forEach(function(participant) {
						if (!participant || participant.isLocal === true || !participant.trackPublications) {
							return;
						}
						participant.trackPublications.forEach(function(publication) {
							if (publication && publication.isSubscribed === true && publication.track) {
								attachRemoteTrack(publication.track, publication, participant);
							}
						});
					});
				}

				function postForm(url, data) {
					const formData = new FormData();
					Object.keys(data || {}).forEach(function(key) {
						formData.append(key, data[key]);
					});
					return fetch(url, {
						method: 'POST',
						body: formData,
						credentials: 'same-origin',
						headers: {
							'X-Requested-With': 'XMLHttpRequest'
						}
					}).then(function(response) {
						return response.json().then(function(payload) {
							if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
								const invalidResponseError = new Error('invalid_response');
								invalidResponseError.httpStatus = response.status;
								throw invalidResponseError;
							}
							if (!response.ok) {
								const httpError = new Error('http_failure');
								httpError.httpStatus = response.status;
								if (typeof payload.message === 'string' && payload.message.trim()) {
									httpError.safeMessage = payload.message.trim();
								}
								throw httpError;
							}
							return payload;
						}, function() {
							const invalidResponseError = new Error('invalid_response');
							invalidResponseError.httpStatus = response.status;
							throw invalidResponseError;
						});
					}, function() {
						throw new Error('network_failure');
					});
				}

				function fetchCallStatus() {
					if (!state.callId) {
						return Promise.resolve(null);
					}
					const url = canStartCall ? nakesStatusUrl : wargaStatusUrl;
					return postForm(url, {
						call_id: state.callId
					});
				}

				function stopStatusPolling() {
					if (state.statusPollTimer) {
						window.clearInterval(state.statusPollTimer);
					}
					state.statusPollTimer = null;
				}

				function startStatusPolling() {
					stopStatusPolling();
					if (!state.callId) {
						return;
					}
					state.statusPollTimer = window.setInterval(function() {
						fetchCallStatus().then(function(response) {
							if (!response || !response.success) {
								return;
							}
							if (response.status === 'answered') {
								setStatus('Terhubung');
							}
							if (response.ended || ['ended', 'rejected', 'missed', 'failed'].indexOf(response.status) !== -1) {
								const message = response.message && response.message !== 'Status panggilan' ? response.message : terminalCallMessage(response.status);
								endLocalCall(message);
							}
						}).catch(function(error) {
							if (error && [401, 403, 404, 405].indexOf(error.httpStatus) !== -1) {
								stopStatusPolling();
							}
						});
					}, state.statusPollMs);
				}

				function terminalCallMessage(status) {
					if (status === 'rejected') {
						return 'Panggilan ditolak';
					}
					if (status === 'missed') {
						return 'Panggilan tidak dijawab.';
					}
					if (status === 'failed') {
						return 'Panggilan gagal';
					}
					return 'Panggilan berakhir';
				}

				function createLocalTrack(kind) {
					const LiveKit = sdk();
					if (!LiveKit) {
						return Promise.reject(new Error('Layanan panggilan belum tersedia. Coba lagi nanti.'));
					}
					if (kind === 'audio') {
						return LiveKit.createLocalAudioTrack();
					}
					return LiveKit.createLocalVideoTrack({
						facingMode: {
							ideal: state.facingMode
						}
					}).catch(function() {
						return LiveKit.createLocalVideoTrack();
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
					elements.local.classList.remove('is-placeholder');
					elements.local.classList.remove('is-camera-off');
					attachTrack(track, elements.local);
					elements.local.classList.remove('is-hidden');
					updateEmptyState();
				}

				function showLocalPlaceholder(message) {
					if (!elements.local) {
						return;
					}
					clearNode(elements.local);
					const status = document.createElement('span');
					status.className = 'doclinc-call-local-status';
					status.textContent = message || 'Kamera nonaktif';
					elements.local.appendChild(status);
					elements.local.classList.add('is-placeholder');
					elements.local.classList.toggle('is-camera-off', /kamera/i.test(message || ''));
					elements.local.classList.remove('is-hidden');
					updateEmptyState();
				}

				function setMediaPresentationState(kind, enabled) {
					if (kind !== 'audio' && kind !== 'video') {
						return;
					}
					const enabledKey = kind === 'audio' ? 'micEnabled' : 'cameraEnabled';
					const button = kind === 'audio' ? elements.mic : elements.camera;
					state[enabledKey] = !!enabled;
					if (button) {
						button.classList.toggle('is-off', !state[enabledKey]);
						button.classList.toggle('is-active', !!state[enabledKey]);
						button.setAttribute('aria-pressed', state[enabledKey] ? 'false' : 'true');
						const label = kind === 'audio'
							? (state[enabledKey] ? 'Bisukan mikrofon' : 'Aktifkan mikrofon')
							: (state[enabledKey] ? 'Matikan kamera' : 'Aktifkan kamera');
						button.setAttribute('aria-label', label);
						button.title = label;
					}
					if (kind === 'video' && !state[enabledKey]) {
						showLocalPlaceholder('Kamera nonaktif');
					}
				}

				function connectWithPayload(response, waitingText) {
					if (!response || !response.success || !response.token || !response.ws_url) {
						state.connecting = false;
						updateFloatingCall();
						setStatus(response && response.message ? response.message : 'Panggilan belum dapat dimulai', true);
						return Promise.resolve(null);
					}
					const LiveKit = sdk();
					const room = new LiveKit.Room({
						adaptiveStream: true,
						dynacast: true
					});
					state.callId = response.call_id || state.callId;
					if (response.status_poll_seconds) {
						state.statusPollMs = Math.max(1000, Number(response.status_poll_seconds) * 1000);
					}
					if (response.call_type === 'audio' || response.call_type === 'video') {
						state.mode = response.call_type;
					}
					applyCallModePresentation();
					state.room = room;
					wireRoom(room);
					return room.connect(response.ws_url, response.token).then(function() {
						state.connecting = false;
						setCallActive(true);
						setStatus(waitingText || 'Menunggu lawan bicara bergabung');
						startElapsedTimer();
						startStatusPolling();
						updateFloatingCall();
						publishExistingParticipants(room);
						resumeRemoteAudio();
						return createLocalTrack('audio').then(publishTrack).catch(function() {
							state.micEnabled = false;
							if (elements.mic) {
								elements.mic.classList.add('is-off');
								elements.mic.classList.remove('is-active');
								elements.mic.setAttribute('aria-pressed', 'true');
								elements.mic.setAttribute('aria-label', 'Aktifkan mikrofon');
								elements.mic.title = 'Aktifkan mikrofon';
							}
							setStatus('Mikrofon tidak tersedia di perangkat ini.');
						});
					}).then(function() {
						if (state.mode !== 'video') {
							showLocalPlaceholder('Panggilan suara');
							return null;
						}
						return createLocalTrack('video').then(function(track) {
							state.cameraEnabled = true;
							if (elements.camera) {
								elements.camera.classList.remove('is-off');
								elements.camera.classList.add('is-active');
								elements.camera.setAttribute('aria-pressed', 'false');
								elements.camera.setAttribute('aria-label', 'Matikan kamera');
								elements.camera.title = 'Matikan kamera';
							}
							showLocalPreview(track);
							return publishTrack(track);
						}).catch(function() {
							state.cameraEnabled = false;
							if (elements.camera) {
								elements.camera.classList.add('is-off');
								elements.camera.classList.remove('is-active');
								elements.camera.setAttribute('aria-pressed', 'true');
								elements.camera.setAttribute('aria-label', 'Aktifkan kamera');
								elements.camera.title = 'Aktifkan kamera';
							}
							showLocalPlaceholder('Kamera nonaktif');
							setStatus('Kamera tidak tersedia. Panggilan suara tetap aktif.');
						});
					});
				}

				function joinCall() {
					if (state.room || state.connecting) {
						showCallScreen();
						return;
					}
					const LiveKit = sdk();
					if (!LiveKit || !LiveKit.Room) {
						setStatus('Layanan panggilan belum tersedia. Coba lagi nanti.', true);
						return;
					}
					if (!requestId) {
						setStatus('Data konsultasi tidak valid', true);
						return;
					}
					state.connecting = true;
					setStatus('Menyiapkan panggilan...');
					updateFloatingCall();
					const request = canStartCall ? postForm(startCallUrl, {
						request_id: requestId,
						call_type: state.mode
					}) : postForm(answerCallUrl, {
						call_id: state.callId
					});
					request.then(function(response) {
						if (response && !response.success && response.ended) {
							setStatus(response.message || terminalCallMessage(response.status), true);
							state.connecting = false;
							updateFloatingCall();
							return null;
						}
						return connectWithPayload(response, canStartCall ? 'Memanggil pasien...' : 'Menunggu lawan bicara bergabung');
					}).catch(function(error) {
						cleanupCall('Panggilan belum dapat tersambung. Tutup lalu mulai lagi.');
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
							button.classList.toggle('is-active', !!state[enabledKey]);
							button.setAttribute('aria-pressed', state[enabledKey] ? 'false' : 'true');
							if (kind === 'audio') {
								button.setAttribute('aria-label', state[enabledKey] ? 'Bisukan mikrofon' : 'Aktifkan mikrofon');
								button.title = state[enabledKey] ? 'Bisukan mikrofon' : 'Aktifkan mikrofon';
							}
							if (kind === 'video') {
								button.setAttribute('aria-label', state[enabledKey] ? 'Matikan kamera' : 'Aktifkan kamera');
								button.title = state[enabledKey] ? 'Matikan kamera' : 'Aktifkan kamera';
							}
						}
						if (kind === 'video') {
							if (state[enabledKey]) {
								showLocalPreview(track);
							} else {
								showLocalPlaceholder('Kamera nonaktif');
							}
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
							button.classList.add('is-active');
							button.setAttribute('aria-pressed', 'false');
							button.setAttribute('aria-label', kind === 'audio' ? 'Bisukan mikrofon' : 'Matikan kamera');
							button.title = kind === 'audio' ? 'Bisukan mikrofon' : 'Matikan kamera';
						}
						return publishTrack(newTrack);
					}).catch(function() {
						setStatus(kind === 'audio' ? 'Mikrofon tidak tersedia di perangkat ini.' : 'Kamera tidak tersedia di perangkat ini.', true);
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
						setStatus('Kamera belum dapat diganti. Coba lagi.', true);
					});
				}

				/*
				 * Test-only presentation seam.  The browser harness sets this explicit
				 * flag before loading the page and then drives the same production media
				 * attachment/state functions used by LiveKit.  No markup or lifecycle
				 * state is fabricated by the application; the seam is inert for normal
				 * sessions.
				 */
				function exposePresentationTestHooks() {
					if (window.__DOCLINC_LIVEKIT_TEST__ !== true) {
						return;
					}
					window.__DOCLINC_LIVEKIT_TEST_HOOKS__ = {
						openCall: function() {
							cacheElements();
							showCallScreen();
							setCallActive(true);
						},
						setMode: function(mode) {
							state.mode = mode === 'audio' ? 'audio' : 'video';
							applyCallModePresentation();
						},
						setActive: function() {
							setCallActive(true);
						},
						showLocalPreview: showLocalPreview,
						showLocalPlaceholder: showLocalPlaceholder,
						setMediaPresentationState: setMediaPresentationState,
						attachRemoteTrack: attachRemoteTrack,
						detachRemoteTrack: detachRemoteTrack,
						trackSubscribed: function(track, publication, participant) {
							return handleTrackSubscribed(null, track, publication, participant);
						},
						trackUnsubscribed: function(track, publication, participant) {
							return handleTrackUnsubscribed(null, track, publication, participant);
						},
						participantDisconnected: function(participant) {
							return handleParticipantDisconnected(null, participant);
						},
						showIncomingCall: showIncomingCall,
						hideIncomingCall: hideIncomingCall,
						clearRemoteTracks: clearRemoteTracks,
						getSnapshot: function() {
							return {
								mode: state.mode,
								localVideo: !!(elements.local && elements.local.querySelector('video')),
								remoteVideo: hasRegisteredRemoteVideo(),
								stageClasses: elements.stage ? elements.stage.className : '',
								remoteParticipantIdentity: activeRemoteParticipantIdentity,
								counterpartName: elements.callTitle ? elements.callTitle.textContent : '',
								counterpartRole: elements.emptyRole ? elements.emptyRole.textContent : ''
							};
						}
					};
				}

				function callTypeLabel(callType) {
					return callType === 'audio' ? 'Panggilan suara' : 'Panggilan video';
				}

				function hideIncomingCall() {
					const callId = state.incomingCall && state.incomingCall.call_id ? state.incomingCall.call_id : null;
					state.incomingCall = null;
					stopIncomingRingtone(callId);
					if (elements.incoming) {
						elements.incoming.classList.add('is-hidden');
					}
					state.incomingAnswering = false;
					if (elements.incomingAnswer) {
						elements.incomingAnswer.style.transform = '';
					}
				}

				function showIncomingCall(call) {
					if (!elements.incoming || !call || !call.call_id) {
						return;
					}
					if (state.room || state.connecting) {
						return;
					}
					state.incomingCall = call;
					state.callId = call.call_id;
					state.mode = call.call_type === 'audio' ? 'audio' : 'video';
					if (elements.incomingCaller) {
						elements.incomingCaller.textContent = call.caller_name || 'Nakes DokLinc';
					}
					if (elements.incomingType) {
						elements.incomingType.setAttribute('data-call-mode', call.call_type === 'audio' ? 'audio' : 'video');
						var modeLabel = callTypeLabel(call.call_type);
						elements.incomingType.replaceChildren();
						if (elements.incomingModeIcon) {
							var modeIconSrc = call.call_type === 'audio'
								? <?= json_encode(doclinc_ui_asset('voice_call') . '?v=1'); ?>
								: <?= json_encode(base_url('assets/doclinc_ui/icon-pack/videocall.svg?v=1')); ?>;
							// Reset the existing image request before showing an incoming call. The
							// disposable single-process runtime can leave the initial request
							// pending while the page is still booting; a fresh URL lets the
							// browser decode the supplied glyph once the route is interactive.
							var modeIcon = elements.incomingModeIcon.cloneNode(false);
							modeIcon.id = 'doclincIncomingModeIcon';
							modeIcon.alt = '';
							elements.incomingModeIcon = modeIcon;
							elements.incomingType.appendChild(modeIcon);
							modeIcon.src = modeIconSrc + '&call=' + encodeURIComponent(String(call.call_id));
						}
						var answerIcon = elements.incomingAnswer ? elements.incomingAnswer.querySelector('img') : null;
						if (answerIcon) {
							var freshAnswerIcon = answerIcon.cloneNode(false);
							freshAnswerIcon.alt = '';
							answerIcon.replaceWith(freshAnswerIcon);
							freshAnswerIcon.src = <?= json_encode(doclinc_ui_asset('voice_call') . '?v=1&incoming=answer'); ?> + '&call=' + encodeURIComponent(String(call.call_id));
						}
						elements.incomingType.appendChild(document.createTextNode(modeLabel));
					}
					if (elements.incomingWaveform) {
						elements.incomingWaveform.classList.toggle('is-hidden', call.call_type !== 'audio');
					}
					if (!state.incomingRejecting) {
						setIncomingActionsDisabled(false);
					}
					elements.incoming.classList.remove('is-hidden');
					startIncomingRingtone(call.call_id);
				}

				function setIncomingActionsDisabled(disabled) {
					if (elements.incomingAnswer) {
						elements.incomingAnswer.disabled = !!disabled;
					}
					if (elements.incomingReject) {
						elements.incomingReject.disabled = !!disabled;
					}
					if (elements.incomingSlider) {
						elements.incomingSlider.setAttribute('aria-disabled', disabled ? 'true' : 'false');
					}
				}

				function isCurrentIncomingCall(callId) {
					return !!(state.incomingCall && String(state.incomingCall.call_id) === String(callId));
				}

				function pollIncomingCall() {
					if (!canReceiveCall || state.room || state.connecting || state.incomingRejecting) {
						return;
					}
					const pollSequence = ++state.incomingPollSequence;
					const url = incomingCallUrl + '?request_id=' + encodeURIComponent(requestId);
					fetch(url, {
						credentials: 'same-origin',
						headers: {
							'X-Requested-With': 'XMLHttpRequest'
						}
					}).then(function(response) {
						return response.json().catch(function() {
							return {};
						});
					}).then(function(response) {
						if (pollSequence !== state.incomingPollSequence || state.incomingRejecting) {
							return;
						}
						state.incomingFailures = 0;
						if (response && response.success && response.has_incoming) {
							showIncomingCall(response);
						} else {
							hideIncomingCall();
							if (response && response.expired) {
								setStatus(response.message || 'Panggilan tidak terjawab.');
							}
						}
					}).catch(function() {
						if (pollSequence !== state.incomingPollSequence || state.incomingRejecting) {
							return;
						}
						state.incomingFailures += 1;
						if (state.incomingFailures >= 5) {
							stopIncomingPolling();
							window.setTimeout(function() {
								state.incomingFailures = 0;
								startIncomingPolling();
							}, 30000);
						}
					});
				}

				function startIncomingPolling() {
					if (!canReceiveCall || state.incomingPollTimer) {
						return;
					}
					pollIncomingCall();
					state.incomingPollTimer = window.setInterval(pollIncomingCall, 3000);
				}

				function stopIncomingPolling() {
					if (state.incomingPollTimer) {
						window.clearInterval(state.incomingPollTimer);
					}
					state.incomingPollTimer = null;
				}

				function answerIncomingCall() {
					const call = state.incomingCall;
					if (state.incomingRejecting || state.incomingAnswering || !call || !call.call_id) {
						return;
					}
					state.incomingAnswering = true;
					setIncomingActionsDisabled(true);
					hideIncomingCall();
					state.callId = call.call_id;
					state.mode = call.call_type === 'audio' ? 'audio' : 'video';
					showCallScreen();
					joinCall();
				}

				function resetIncomingSlider() {
					if (elements.incomingAnswer) {
						elements.incomingAnswer.style.transform = '';
					}
					state.incomingSliderPointerId = null;
				}

				function bindIncomingSlider() {
					if (!elements.incomingSlider || !elements.incomingAnswer) {
						return;
					}
					elements.incomingSlider.addEventListener('pointerdown', function(event) {
						if (state.incomingRejecting || state.incomingAnswering || elements.incomingAnswer.disabled) {
							return;
						}
						state.incomingSliderPointerId = event.pointerId;
						state.incomingSliderStartX = event.clientX;
						elements.incomingSlider.setPointerCapture(event.pointerId);
						event.preventDefault();
					});
					elements.incomingSlider.addEventListener('pointermove', function(event) {
						if (state.incomingSliderPointerId !== event.pointerId) {
							return;
						}
						var maxTravel = Math.max(0, elements.incomingSlider.clientWidth - elements.incomingAnswer.offsetWidth - 12);
						var travel = Math.max(0, Math.min(maxTravel, event.clientX - state.incomingSliderStartX));
						elements.incomingAnswer.style.transform = 'translateX(' + travel + 'px)';
					});
					function finishSlide(event) {
						if (state.incomingSliderPointerId !== event.pointerId) {
							return;
						}
						var maxTravel = Math.max(0, elements.incomingSlider.clientWidth - elements.incomingAnswer.offsetWidth - 12);
						var travel = Math.max(0, Math.min(maxTravel, event.clientX - state.incomingSliderStartX));
						resetIncomingSlider();
						if (maxTravel > 0 && travel >= maxTravel * 0.72) {
							answerIncomingCall();
						}
					}
					elements.incomingSlider.addEventListener('pointerup', finishSlide);
					elements.incomingSlider.addEventListener('pointercancel', function(event) {
						if (state.incomingSliderPointerId === event.pointerId) resetIncomingSlider();
					});
				}

				function answerCallById(callId) {
					if (!canReceiveCall || !callId || state.room || state.connecting) {
						return;
					}
					hideIncomingCall();
					state.callId = callId;
					state.mode = 'video';
					showCallScreen();
					joinCall();
				}

				function rejectIncomingCall() {
					const call = state.incomingCall;
					if (state.incomingRejecting || !call || !call.call_id) {
						return;
					}
					const callId = call.call_id;
					state.incomingPollSequence += 1;
					state.incomingRejecting = true;
					setIncomingActionsDisabled(true);
					postForm(rejectCallUrl, {
						call_id: callId
					}).then(function(response) {
						const safeMessage = response && typeof response.message === 'string' ? response.message.trim() : '';
						if (!response || response.success !== true) {
							if (isCurrentIncomingCall(callId)) {
								elements.incoming.classList.remove('is-hidden');
								setStatus(safeMessage || 'Panggilan belum dapat ditolak. Coba lagi.', true);
							}
							return;
						}
						if (isCurrentIncomingCall(callId)) {
							hideIncomingCall();
							setStatus(safeMessage || 'Panggilan ditolak');
						}
					}).catch(function(error) {
						if (!isCurrentIncomingCall(callId)) {
							return;
						}
						const safeMessage = error && typeof error.safeMessage === 'string' ? error.safeMessage.trim() : '';
						if (error && [404, 409].indexOf(error.httpStatus) !== -1) {
							hideIncomingCall();
							setStatus(safeMessage || 'Panggilan tidak tersedia.', true);
							return;
						}
						elements.incoming.classList.remove('is-hidden');
						setStatus(safeMessage || 'Panggilan belum dapat ditolak. Coba lagi.', true);
					}).finally(function() {
						state.incomingRejecting = false;
						setIncomingActionsDisabled(false);
					});
				}

			document.addEventListener('DOMContentLoaded', function() {
				cacheElements();
				window.setTimeout(function() {
					if (window.DoclincVoiceWaveform && typeof window.DoclincVoiceWaveform.play === 'function') {
						var voiceWaveform = document.querySelector('[data-voice-waveform]');
						if (voiceWaveform) {
							window.DoclincVoiceWaveform.play(voiceWaveform);
						}
						var incomingWaveform = document.querySelector('[data-incoming-waveform]');
						if (incomingWaveform) {
							window.DoclincVoiceWaveform.play(incomingWaveform);
						}
					}
				}, 0);
				exposePresentationTestHooks();
					incomingRingtone();
					window.addEventListener('pagehide', releaseIncomingRingtone);
					document.querySelectorAll('.doclinc-call-start').forEach(function(button) {
						button.addEventListener('click', function(event) {
							event.preventDefault();
							openCall(button.getAttribute('data-call-mode'));
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
					if (elements.speaker) {
						elements.speaker.addEventListener('click', toggleSpeakerOutput);
					}
					if (elements.chat) {
						elements.chat.addEventListener('click', minimizeCallScreen);
					}
					if (elements.switchCamera) {
						elements.switchCamera.addEventListener('click', switchCamera);
					}
					if (elements.end) {
						elements.end.addEventListener('click', endCall);
					}
					if (elements.close) {
						elements.close.addEventListener('click', minimizeCallScreen);
					}
					if (elements.headerClose) {
						elements.headerClose.addEventListener('click', minimizeCallScreen);
					}
					if (elements.dismiss) {
						elements.dismiss.addEventListener('click', minimizeCallScreen);
					}
					if (elements.minimize) {
						elements.minimize.addEventListener('click', minimizeCallScreen);
					}
					if (elements.more) {
						elements.more.addEventListener('click', toggleMore);
					}
					if (elements.activeCall) {
						elements.activeCall.addEventListener('click', showCallScreen);
					}
					if (elements.incomingAnswer) {
						elements.incomingAnswer.addEventListener('click', answerIncomingCall);
					}
					bindIncomingSlider();
					if (elements.incomingReject) {
						elements.incomingReject.addEventListener('click', rejectIncomingCall);
					}
					if (elements.modal) {
						elements.modal.addEventListener('click', function(event) {
							if (event.target === elements.modal) {
								minimizeCallScreen();
							}
						});
					}
					window.addEventListener('beforeunload', function() {
						stopIncomingPolling();
						cleanupCall('Panggilan berakhir');
					});
					const autoAnswerCallId = <?= json_encode($auto_answer_call_id); ?>;
			if (autoAnswerCallId > 0) {
				answerCallById(autoAnswerCallId);
			} else if (window.__DOCLINC_LIVEKIT_TEST__ !== true) {
				startIncomingPolling();
			}
				});
			})();
		</script>
	<?php endif; ?>

	<script src="<?= html_escape(base_url('assets/js/doclinc-chat-participant.js') . '?v=' . rawurlencode($chat_participant_version)); ?>"></script>
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
		const chatPartnerName = <?= json_encode($partner_name); ?>;
		const chatPartnerInitials = <?= json_encode($partner_initials); ?>;
		let markReadInFlight = false;
		let markReadStopped = false;
		const chatMessageRegistry = window.DoclincChatParticipant
			? window.DoclincChatParticipant.createMessageRegistry()
			: null;
		const chatParticipant = <?= json_encode($current_role === 'warga'); ?> && window.DoclincChatParticipant
			? window.DoclincChatParticipant.create({
				nameElements: [document.getElementById('chatPartnerName'), document.getElementById('doclincCallTitle')],
				imageElements: [document.getElementById('chatPartnerAvatar'), document.getElementById('doclincCallAvatar')],
				fallbackName: 'Tenaga kesehatan',
				fallbackPhotoUrl: <?= json_encode($profile_photo_fallback); ?>,
				isSafePhotoUrl: isSafeProfilePhotoUrl
			})
			: null;
		const chatMessagePoller = window.DoclincChatParticipant
			? window.DoclincChatParticipant.createPoller({
				request: function() {
					return fetch(messagesUrl + '?request_id=' + encodeURIComponent(requestId) + '&after_id=' + encodeURIComponent(lastMessageId), {
						credentials: 'same-origin'
					}).then(function(response) {
						if (!response.ok) {
							throw new Error('failed');
						}
						return response.json();
					});
				},
				apply: function(data) {
					if (!data || data.status !== 'success') {
						return;
					}
					if (chatParticipant && data.participant) {
						chatParticipant.update(data.participant);
					}
					if (!hasLoaded && (!data.messages || data.messages.length === 0)) {
						const list = document.getElementById('chatMessages');
						if (list) {
							list.querySelectorAll('.chat-muted, .chat-error').forEach(function(node) { node.remove(); });
							const empty = document.createElement('div');
							empty.className = 'chat-muted';
							empty.textContent = 'Belum ada pesan pada konsultasi ini.';
							list.appendChild(empty);
							list.classList.add('is-empty');
						}
						hasLoaded = true;
					}
					(data.messages || []).forEach(appendMessage);
					if (data.messages && data.messages.length) {
						const list = document.getElementById('chatMessages');
						if (list) {
							list.classList.remove('is-empty');
						}
					}
				},
					onError: function() {
					if (!hasLoaded) {
						const list = document.getElementById('chatMessages');
						if (list) {
							list.querySelectorAll('.chat-muted, .chat-error').forEach(function(node) { node.remove(); });
							const error = document.createElement('div');
							error.className = 'chat-error';
							error.textContent = 'Chat belum dapat dimuat. Coba lagi.';
							list.appendChild(error);
						}
						hasLoaded = true;
					}
				}
			})
			: null;

		function parseChatDate(value) {
			if (!value) {
				return null;
			}
			let raw = String(value).trim().replace(' ', 'T');
			if (!/(Z|[+-]\d{2}:?\d{2})$/.test(raw)) {
				raw += 'Z';
			}
			const date = new Date(raw);
			return isNaN(date.getTime()) ? null : date;
		}

		function formatDate(value) {
			const date = parseChatDate(value);
			if (!date) {
				return '';
			}
			const parts = new Intl.DateTimeFormat('en-GB', {
				hour: '2-digit',
				minute: '2-digit',
				hour12: false,
				timeZone: 'Asia/Jakarta'
			}).formatToParts(date);
			const hour = parts.find(function(part) { return part.type === 'hour'; });
			const minute = parts.find(function(part) { return part.type === 'minute'; });
			return (hour ? hour.value : '00') + ':' + (minute ? minute.value : '00');
		}

		function updateChatDateSeparator(value) {
			const separator = document.getElementById('chatDateSeparator');
			if (!separator || !value) return;
			const date = parseChatDate(value);
			if (!date) return;
			const dayKey = function(input) {
				return new Intl.DateTimeFormat('en-CA', {
					year: 'numeric', month: '2-digit', day: '2-digit', timeZone: 'Asia/Jakarta'
				}).format(input);
			};
			const isToday = dayKey(date) === dayKey(new Date());
			const formattedDate = new Intl.DateTimeFormat('id-ID', {
				day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta'
			}).format(date);
			const label = isToday ? 'Hari ini, ' + formattedDate : formattedDate;
			const text = separator.querySelector('span');
			if (text) text.textContent = label;
			separator.hidden = false;
		}

		function isSafeImageUrl(value) {
			if (!value) {
				return false;
			}
			try {
				const parsed = new URL(value, window.location.origin);
				return parsed.origin === window.location.origin &&
					/^\/chat\/attachment\/[1-9][0-9]*\/?$/.test(parsed.pathname) &&
					parsed.search === '' && parsed.hash === '';
			} catch (error) {
				return false;
			}
		}

		function isSafeProfilePhotoUrl(value) {
			if (!value) {
				return false;
			}
			try {
				const parsed = new URL(value, window.location.origin);
				return parsed.origin === window.location.origin && /^\/profile\/photo\/[1-9][0-9]*\/?$/.test(parsed.pathname) && parsed.search === '' && parsed.hash === '';
			} catch (error) {
				return false;
			}
		}

		function appendMessageAvatar(row, message) {
			const photoUrl = isSafeProfilePhotoUrl(message && message.sender_photo_url) ? message.sender_photo_url : '';
			if (photoUrl) {
				const image = document.createElement('img');
				image.className = 'chat-message-avatar';
				image.src = photoUrl;
				image.alt = '';
				image.setAttribute('aria-hidden', 'true');
				image.addEventListener('error', function() {
					const fallback = document.createElement('span');
					fallback.className = 'chat-message-avatar-fallback';
					fallback.textContent = chatPartnerInitials;
					image.replaceWith(fallback);
				}, { once: true });
				row.appendChild(image);
				return;
			}
			const fallback = document.createElement('span');
			fallback.className = 'chat-message-avatar-fallback';
			fallback.textContent = chatPartnerInitials;
			fallback.setAttribute('aria-hidden', 'true');
			row.appendChild(fallback);
		}

		function appendMessage(message) {
			const list = document.getElementById('chatMessages');
			if (!list) {
				return;
			}
			const messageId = parseInt(message.message_id, 10) || 0;
			if (chatMessageRegistry && !chatMessageRegistry.accept(messageId)) {
				lastMessageId = Math.max(lastMessageId, messageId);
				return;
			}
			if (!hasLoaded) {
				list.querySelectorAll('.chat-muted, .chat-error').forEach(function(node) { node.remove(); });
				hasLoaded = true;
			}
			updateChatDateSeparator(message.created_at);

			const isMine = parseInt(message.sender_user_id, 10) === currentUserId;
			const row = document.createElement('div');
			row.className = 'chat-message-row' + (isMine ? ' mine' : '');
			if (messageId > 0) row.setAttribute('data-chat-message-id', String(messageId));

			const bubble = document.createElement('div');
			bubble.className = 'chat-bubble' + (isMine ? ' mine' : '');

			const time = document.createElement('div');
			time.className = 'chat-time';
			time.textContent = formatDate(message.created_at);

			if (!isMine) {
				appendMessageAvatar(row, message);
				const sender = document.createElement('div');
				sender.className = 'chat-message-sender';
				sender.textContent = (typeof message.sender_display_name === 'string' && message.sender_display_name.trim() !== '')
					? message.sender_display_name.trim()
					: chatPartnerName;
				const stack = document.createElement('div');
				stack.className = 'chat-message-stack';
				stack.appendChild(sender);
				row.appendChild(stack);
				row._messageStack = stack;
			} else {
				const sender = document.createElement('div');
				sender.className = 'chat-message-sender';
				sender.textContent = 'Anda';
				const stack = document.createElement('div');
				stack.className = 'chat-message-stack mine';
				stack.appendChild(sender);
				row.appendChild(stack);
				row._messageStack = stack;
			}

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
				text.className = 'chat-message-text';
				text.textContent = message.message_text || '';
				if (!isMine && text.textContent.length < 120) {
					bubble.classList.add('chat-bubble-compact');
				}
				if (!isMine && text.textContent.length >= 160 && row._messageStack) {
					row._messageStack.classList.add('chat-message-stack-long');
				}
				bubble.appendChild(text);
			}
			if (isMine && Number(message.is_read) === 1) {
				const delivery = document.createElement('span');
				delivery.className = 'chat-delivery';
				delivery.setAttribute('aria-label', 'Dibaca');
				delivery.textContent = '✓✓';
				time.appendChild(document.createTextNode(' '));
				time.appendChild(delivery);
			}
			bubble.appendChild(time);

			if (row._messageStack) {
				row._messageStack.appendChild(bubble);
				delete row._messageStack;
			} else {
				row.appendChild(bubble);
			}

			list.appendChild(row);
			list.scrollTop = list.scrollHeight;
			lastMessageId = Math.max(lastMessageId, messageId);
		}

		function loadMessages() {
			if (chatMessagePoller) {
				return chatMessagePoller.poll();
			}
			return Promise.resolve({ started: false, applied: false });
		}

		function markRead() {
			if (markReadStopped || markReadInFlight) {
				return;
			}
			markReadInFlight = true;
			const formData = new FormData();
			formData.append('request_id', requestId);
			fetch(markReadUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
				.then(function(response) {
					if (response.status === 401 || response.status === 403) {
						markReadStopped = true;
					}
					return response.json().then(function(body) {
						const isObject = body && typeof body === 'object' && !Array.isArray(body);
						if (!response.ok || !isObject || body.status !== 'success') {
							throw new Error('chat_mark_read_failed');
						}
					});
				})
				.catch(function() {})
				.finally(function() {
					markReadInFlight = false;
				});
		}

		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.js-profile-photo').forEach(function(image) {
				image.addEventListener('error', function() {
					const fallback = image.getAttribute('data-fallback-src');
					if (fallback && image.src !== fallback) {
						image.src = fallback;
					}
				}, { once: true });
			});
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
		const chatInput = document.getElementById('messageText');

		function resizeChatInput() {
			if (!chatInput) {
				return;
			}
			chatInput.style.height = 'auto';
			chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
		}

		if (chatInput) {
			chatInput.addEventListener('input', resizeChatInput);
			chatInput.addEventListener('keydown', function(event) {
				if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
					return;
				}
				event.preventDefault();
				if (chatInput.value.trim() && chatForm && typeof chatForm.requestSubmit === 'function') {
					chatForm.requestSubmit();
				}
			});
			resizeChatInput();
		}

		if (chatForm && canSend) {
			function showChatSendError(message) {
				const list = document.getElementById('chatMessages');
				if (!list) {
					return;
				}
				let errorElement = document.getElementById('chatSendError');
				if (!errorElement) {
					errorElement = document.createElement('div');
					errorElement.id = 'chatSendError';
					errorElement.className = 'chat-error';
					list.appendChild(errorElement);
				}
				errorElement.textContent = message || 'Pesan belum terkirim. Coba lagi.';
				list.scrollTop = list.scrollHeight;
			}

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
						return response.json().then(function(data) {
							return {
								ok: response.ok,
								data: data
							};
						}).catch(function() {
							return {
								ok: response.ok,
								data: null
							};
						});
					})
					.then(function(result) {
						const data = result.data;
						if (result.ok && data && data.status === 'success' && data.message) {
							const previousError = document.getElementById('chatSendError');
							if (previousError) {
								previousError.remove();
							}
							input.value = '';
							const list = document.getElementById('chatMessages');
							if (list) {
								list.querySelectorAll('.chat-muted, .chat-error').forEach(function(node) { node.remove(); });
								list.classList.remove('is-empty');
							}
							appendMessage(data.message);
							resizeChatInput();
							return;
						}
						const backendMessage = data && data.status === 'error' && typeof data.message === 'string' ? data.message.trim() : '';
						showChatSendError(backendMessage || 'Pesan belum terkirim. Coba lagi.');
					})
					.catch(function() {
						showChatSendError('Pesan belum terkirim. Coba lagi.');
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
				const chatImageFeedback = document.getElementById('chatImageFeedback');
				const chatImageAllowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
				const chatImageMaxRawSize = 15 * 1024 * 1024;
				const chatImageTargetSize = Math.floor(1.5 * 1024 * 1024);
				const chatImageBackendMaxSize = 4 * 1024 * 1024;
				const chatImageMaxLongestSide = 1600;
				const chatImageMaxDecodedPixels = 40000000;
				const chatImageQualities = [0.82, 0.75, 0.68, 0.60];
				const chatImageLongestSides = [1600, 1440, 1280, 1024, 960];
				let chatImageProcessSequence = 0;
				let chatImageUploadInFlight = false;
				let chatImageRequestInFlight = false;
				let chatImageButtonStates = null;

				function isCurrentChatImageSelection(sequence, file) {
					return sequence === chatImageProcessSequence &&
						imageInput.files &&
						imageInput.files.length > 0 &&
						imageInput.files[0] === file;
				}

				function setChatImageFeedback(message, isError, sequence, file) {
					if (!chatImageFeedback || !isCurrentChatImageSelection(sequence, file)) {
						return;
					}
					chatImageFeedback.textContent = message || '';
					chatImageFeedback.hidden = !message;
					chatImageFeedback.classList.toggle('is-error', Boolean(isError));
					chatImageFeedback.setAttribute('role', isError ? 'alert' : 'status');
				}

				function clearChatImageFeedback() {
					if (!chatImageFeedback) {
						return;
					}
					chatImageFeedback.textContent = '';
					chatImageFeedback.hidden = true;
					chatImageFeedback.classList.remove('is-error');
					chatImageFeedback.setAttribute('role', 'status');
				}

				function lockChatImageButtons() {
					if (!chatImageButtonStates) {
						chatImageButtonStates = {
							image: imageButton.disabled,
							attachment: attachmentButton ? attachmentButton.disabled : false
						};
					}
					chatImageUploadInFlight = true;
					imageButton.disabled = true;
					if (attachmentButton) {
						attachmentButton.disabled = true;
					}
				}

				function restoreChatImageButtons() {
					if (chatImageButtonStates) {
						imageButton.disabled = chatImageButtonStates.image;
						if (attachmentButton) {
							attachmentButton.disabled = chatImageButtonStates.attachment;
						}
					}
					chatImageButtonStates = null;
					chatImageUploadInFlight = false;
				}

				function chatImageExtensionForType(type) {
					if (type === 'image/png') {
						return 'png';
					}
					if (type === 'image/webp') {
						return 'webp';
					}
					return 'jpg';
				}

				function chatImageSafeRequestId() {
					const value = String(requestId == null ? '' : requestId).replace(/[^A-Za-z0-9_-]/g, '');
					return value || 'request';
				}

				function chatImageFallbackFilename(type, timestamp) {
					return 'chat-foto-' + chatImageSafeRequestId() + '-' + timestamp + '.' + chatImageExtensionForType(type);
				}

				function chatImageOriginalFilename(file, timestamp) {
					const expectedExtensions = file.type === 'image/jpeg' ? ['jpg', 'jpeg'] : [chatImageExtensionForType(file.type)];
					const rawBasename = String(file.name || '').split(/[\\/]/).pop().trim();
					const extensionMatch = rawBasename.match(/\.([^.]+)$/);
					const extension = extensionMatch ? extensionMatch[1].toLowerCase() : '';
					const basenameStem = extensionMatch ? rawBasename.slice(0, -extensionMatch[0].length) : rawBasename;
					let safeBasename = rawBasename.replace(/[^A-Za-z0-9._-]+/g, '_');
					if (safeBasename.length > 140) {
						const safeExtension = chatImageExtensionForType(file.type);
						safeBasename = safeBasename.slice(0, 130) + '.' + safeExtension;
					}
					if (!safeBasename || safeBasename === '.' || safeBasename === '..' ||
						!basenameStem || expectedExtensions.indexOf(extension) === -1) {
						return chatImageFallbackFilename(file.type, timestamp);
					}
					return safeBasename;
				}

				function isChatImageOriginalExtensionCompatible(file) {
					const rawBasename = String(file.name || '').split(/[\\/]/).pop().trim();
					if (!rawBasename || rawBasename === '.' || rawBasename === '..') {
						return true;
					}
					const extensionMatch = rawBasename.match(/\.([^.]+)$/);
					if (!extensionMatch) {
						return false;
					}
					const extension = extensionMatch[1].toLowerCase();
					if (file.type === 'image/jpeg') {
						return extension === 'jpg' || extension === 'jpeg';
					}
					return extension === chatImageExtensionForType(file.type);
				}

				function createChatDecodedSource(source, width, height, cleanup) {
					const naturalWidth = Number(width);
					const naturalHeight = Number(height);
					if (!Number.isFinite(naturalWidth) || !Number.isFinite(naturalHeight) ||
						naturalWidth <= 0 || naturalHeight <= 0 ||
						naturalWidth > chatImageMaxDecodedPixels / naturalHeight) {
						cleanup();
						throw new Error('chat_image_dimensions_invalid');
					}
					return {
						source: source,
						width: naturalWidth,
						height: naturalHeight,
						cleanup: cleanup
					};
				}

				function decodeChatImageWithElement(file) {
					return new Promise(function(resolve, reject) {
						let objectUrl = '';
						let image = new Image();
						let cleaned = false;

						function cleanup() {
							if (cleaned) {
								return;
							}
							cleaned = true;
							if (image) {
								image.onload = null;
								image.onerror = null;
								image.src = '';
							}
							if (objectUrl) {
								URL.revokeObjectURL(objectUrl);
								objectUrl = '';
							}
						}

						try {
							objectUrl = URL.createObjectURL(file);
						} catch (error) {
							cleanup();
							reject(new Error('chat_image_decode_failed'));
							return;
						}

						image.onload = function() {
							image.onload = null;
							image.onerror = null;
							try {
								resolve(createChatDecodedSource(image, image.naturalWidth, image.naturalHeight, cleanup));
							} catch (error) {
								reject(error);
							}
						};
						image.onerror = function() {
							cleanup();
							reject(new Error('chat_image_decode_failed'));
						};
						image.src = objectUrl;
					});
				}

				async function decodeChatImage(file) {
					if (typeof createImageBitmap === 'function') {
						let bitmap = null;
						try {
							bitmap = await createImageBitmap(file, {
								imageOrientation: 'from-image'
							});
						} catch (error) {
							try {
								bitmap = await createImageBitmap(file);
							} catch (fallbackError) {
								bitmap = null;
							}
						}
						if (bitmap) {
							const cleanup = function() {
								if (bitmap && typeof bitmap.close === 'function') {
									bitmap.close();
								}
								bitmap = null;
							};
							return createChatDecodedSource(bitmap, bitmap.width, bitmap.height, cleanup);
						}
					}
					return decodeChatImageWithElement(file);
				}

				function chatImageCanvasDimensions(width, height, longestSide) {
					const scale = Math.min(1, longestSide / Math.max(width, height));
					return {
						width: Math.max(1, Math.round(width * scale)),
						height: Math.max(1, Math.round(height * scale))
					};
				}

				function chatImageCanvasToBlob(canvas, quality) {
					return new Promise(function(resolve, reject) {
						try {
							canvas.toBlob(function(blob) {
								if (!blob || blob.size <= 0 || blob.type !== 'image/jpeg') {
									reject(new Error('chat_image_encode_failed'));
									return;
								}
								resolve(blob);
							}, 'image/jpeg', quality);
						} catch (error) {
							reject(new Error('chat_image_encode_failed'));
						}
					});
				}

				async function compressChatImage(decoded) {
					const renderedDimensions = {};
					for (let sideIndex = 0; sideIndex < chatImageLongestSides.length; sideIndex += 1) {
						const dimensions = chatImageCanvasDimensions(
							decoded.width,
							decoded.height,
							chatImageLongestSides[sideIndex]
						);
						const dimensionKey = dimensions.width + 'x' + dimensions.height;
						if (renderedDimensions[dimensionKey]) {
							continue;
						}
						renderedDimensions[dimensionKey] = true;

						const canvas = document.createElement('canvas');
						canvas.width = dimensions.width;
						canvas.height = dimensions.height;
						const context = canvas.getContext('2d');
						if (!context) {
							canvas.width = 0;
							canvas.height = 0;
							throw new Error('chat_image_canvas_failed');
						}

						try {
							context.fillStyle = '#ffffff';
							context.fillRect(0, 0, dimensions.width, dimensions.height);
							context.drawImage(decoded.source, 0, 0, dimensions.width, dimensions.height);
							for (let qualityIndex = 0; qualityIndex < chatImageQualities.length; qualityIndex += 1) {
								const candidate = await chatImageCanvasToBlob(canvas, chatImageQualities[qualityIndex]);
								if (candidate.size <= chatImageTargetSize) {
									return candidate;
								}
							}
						} finally {
							canvas.width = 0;
							canvas.height = 0;
						}
					}
					throw new Error('chat_image_target_not_reached');
				}

				async function prepareChatImage(file, timestamp) {
					let decoded = null;
					try {
						decoded = await decodeChatImage(file);
						const longestSide = Math.max(decoded.width, decoded.height);
						if (file.size > 0 &&
							file.size <= chatImageTargetSize &&
							longestSide <= chatImageMaxLongestSide &&
							isChatImageOriginalExtensionCompatible(file)) {
							return {
								blob: file,
								filename: chatImageOriginalFilename(file, timestamp),
								compressed: false
							};
						}

						const compressedBlob = await compressChatImage(decoded);
						return {
							blob: compressedBlob,
							filename: 'chat-foto-' + chatImageSafeRequestId() + '-' + timestamp + '.jpg',
							compressed: true
						};
					} finally {
						if (decoded) {
							decoded.cleanup();
						}
					}
				}

				async function uploadChatImage(preparedImage) {
					const formData = new FormData();
					formData.append('request_id', requestId);
					formData.append('foto', preparedImage.blob, preparedImage.filename);
					const response = await fetch(imageUploadUrl, {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					});

					let data = null;
					try {
						data = await response.json();
					} catch (error) {
						data = null;
					}
					const isObject = data && typeof data === 'object' && !Array.isArray(data);
					const safeMessage = isObject && typeof data.message === 'string' ? data.message.trim() : '';
					const isSuccess = response.ok &&
						isObject &&
						data.status === 'success' &&
						data.message &&
						typeof data.message === 'object' &&
						!Array.isArray(data.message);
					return {
						success: Boolean(isSuccess),
						message: isSuccess ? data.message : null,
						errorMessage: safeMessage
					};
				}

				imageButton.addEventListener('click', function() {
					if (chatImageUploadInFlight) {
						return;
					}
					imageInput.click();
				});

				if (attachmentButton) {
					attachmentButton.addEventListener('click', function() {
						if (chatImageUploadInFlight) {
							return;
						}
						imageInput.click();
					});
				}

				imageInput.addEventListener('change', async function() {
					const sequence = ++chatImageProcessSequence;
					const file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
					if (!file) {
						clearChatImageFeedback();
						restoreChatImageButtons();
						return;
					}

					lockChatImageButtons();
					setChatImageFeedback('Memproses foto...', false, sequence, file);
					let failureStage = 'processing';
					try {
						if (chatImageAllowedTypes.indexOf(file.type) === -1) {
							setChatImageFeedback('Format foto tidak didukung.', true, sequence, file);
							return;
						}
						if (file.size <= 0) {
							setChatImageFeedback('Foto belum dapat diproses. Coba lagi.', true, sequence, file);
							return;
						}
						if (file.size > chatImageMaxRawSize) {
							setChatImageFeedback('Ukuran foto terlalu besar.', true, sequence, file);
							return;
						}

						const preparedImage = await prepareChatImage(file, Date.now());
						if (!isCurrentChatImageSelection(sequence, file)) {
							return;
						}
						if (!preparedImage.blob ||
							preparedImage.blob.size <= 0 ||
							preparedImage.blob.size > chatImageBackendMaxSize ||
							(preparedImage.compressed && preparedImage.blob.size > chatImageTargetSize) ||
							chatImageAllowedTypes.indexOf(preparedImage.blob.type) === -1 ||
							!preparedImage.filename ||
							!preparedImage.filename.trim()) {
							setChatImageFeedback('Foto belum dapat diproses. Coba lagi.', true, sequence, file);
							return;
						}
						if (!isCurrentChatImageSelection(sequence, file)) {
							return;
						}

						setChatImageFeedback('Mengirim foto...', false, sequence, file);
						failureStage = 'upload';
						if (!isCurrentChatImageSelection(sequence, file)) {
							return;
						}
						if (chatImageRequestInFlight) {
							setChatImageFeedback('Gambar tidak dapat dikirim.', true, sequence, file);
							return;
						}
						chatImageRequestInFlight = true;
						let result = null;
						try {
							result = await uploadChatImage(preparedImage);
						} finally {
							chatImageRequestInFlight = false;
						}
						if (!isCurrentChatImageSelection(sequence, file)) {
							return;
						}
						if (!result.success) {
							setChatImageFeedback(result.errorMessage || 'Gambar tidak dapat dikirim.', true, sequence, file);
							return;
						}

						appendMessage(result.message);
						clearChatImageFeedback();
					} catch (error) {
						if (isCurrentChatImageSelection(sequence, file)) {
							setChatImageFeedback(
								failureStage === 'processing' ?
									'Foto belum dapat diproses. Coba lagi.' :
									'Gambar tidak dapat dikirim.',
								true,
								sequence,
								file
							);
						}
					} finally {
						if (isCurrentChatImageSelection(sequence, file)) {
							imageInput.value = '';
							restoreChatImageButtons();
						}
					}
				});
			}
		}
	</script>
	<?php if ($auto_visit_location) : ?>
		<script src="<?= html_escape(base_url('assets/js/doclinc-active-visit-location.js') . '?v=' . rawurlencode($active_visit_location_version)); ?>"></script>
		<script>
			(function() {
				const status = document.getElementById('chatVisitLocationStatus');
				if (!window.DoclincActiveVisitLocation) return;
				window.DoclincActiveVisitLocation.create({
					window: window,
					requestId: <?= json_encode($request_id); ?>,
					active: true,
					updateUrl: <?= json_encode(base_url('home_nakes/update_visit_location')); ?>,
					eligibilityUrl: <?= json_encode(base_url('home_nakes/visit_location')); ?>,
					minIntervalMs: <?= json_encode((function_exists('doclinc_visit_location_min_interval_seconds') ? doclinc_visit_location_min_interval_seconds() : 5) * 1000); ?>,
					setStatus: function(message, isError) {
						if (!status) return;
						status.textContent = message || '';
						status.classList.toggle('text-danger', isError === true);
					}
				}).start();
			})();
		</script>
	<?php endif; ?>
	<?php $this->load->view('nakes_presence_runtime_v', array('nakes_presence_bootstrap' => $nakes_presence_bootstrap)); ?>
</body>

</html>
