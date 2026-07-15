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
$auto_answer_call_id = isset($_GET['answer_call']) ? (int) $_GET['answer_call'] : 0;
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
			min-height: 72px;
			background: #ffffff;
			color: #333333;
			padding: 10px 12px;
			display: flex;
			align-items: center;
			gap: 10px;
			top: 0;
			z-index: 4;
			box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
		}

		.chat-back {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 34px;
			height: 34px;
			border: 0;
			background: transparent;
			color: #333333;
			line-height: 1;
			text-decoration: none;
			flex: 0 0 34px;
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
			color: #333333;
			font-weight: 700;
			font-size: 15px;
			line-height: 19px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.chat-title-subtext {
			margin: 2px 0 0;
			color: #6b7280;
			font-size: 11px;
			font-weight: 650;
			line-height: 15px;
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
			justify-content: flex-end;
			gap: 4px;
			flex: 0 0 auto;
		}

		.chat-call-button {
			border: 0;
			background: transparent;
			color: #315d0d;
			width: 38px;
			height: 38px;
			border-radius: 999px;
			padding: 8px;
			cursor: pointer;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			flex: 0 0 38px;
		}

		.chat-call-button:hover,
		.chat-call-button:focus-visible {
			background: #eef8e8;
			outline: 0;
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
				radial-gradient(circle at 50% 12%, rgba(69, 128, 92, 0.45) 0, rgba(69, 128, 92, 0) 33%),
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
			color: #fecaca;
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

		.doclinc-call-control.end-call {
			background: #dc2626;
			border-color: #dc2626;
			width: 64px;
			height: 64px;
		}

		.doclinc-active-call {
			position: fixed;
			left: 50%;
			bottom: calc(88px + env(safe-area-inset-bottom));
			z-index: 30;
			transform: translateX(-50%);
			width: min(360px, calc(100vw - 28px));
			border: 0;
			border-radius: 20px;
			background: linear-gradient(135deg, #14232d, #0d151c);
			color: #ffffff;
			padding: 12px 14px;
			box-shadow: 0 16px 42px rgba(0, 0, 0, 0.28);
			display: flex;
			align-items: center;
			gap: 12px;
			cursor: pointer;
		}

		.doclinc-active-call-icon {
			width: 38px;
			height: 38px;
			border-radius: 999px;
			background: #437a13;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			flex: 0 0 38px;
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
			padding: 18px;
			box-shadow: 0 20px 52px rgba(0, 0, 0, 0.28);
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
			align-items: center;
			gap: 10px;
		}

		.doclinc-incoming-action {
			flex: 1;
			min-height: 44px;
			border: 0;
			border-radius: 999px;
			color: #ffffff;
			font-weight: 850;
			cursor: pointer;
		}

		.doclinc-incoming-action.answer {
			background: #437a13;
		}

		.doclinc-incoming-action.reject {
			background: #dc2626;
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
				gap: 10px;
			}

			.doclinc-call-control {
				width: 52px;
				height: 52px;
			}

			.doclinc-call-control.end-call {
				width: 60px;
				height: 60px;
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
	</style>
</head>

<body>
	<div class="chat-shell">
		<header class="chat-header">
			<a href="<?= html_escape($back_url); ?>" class="chat-back" aria-label="Kembali">
				<img src="<?= html_escape($asset_base . 'icon-chat-back.svg'); ?>" alt="">
			</a>
			<img class="chat-avatar" src="<?= html_escape($asset_base . 'doctor-placeholder.jpg'); ?>" alt="Profil layanan">
			<div class="chat-title">
				<p class="chat-title-text"><?= html_escape($partner_name); ?></p>
				<p class="chat-title-subtext"><?= html_escape($status_label); ?> · <?= html_escape($queue_display); ?></p>
			</div>
			<?php if ($can_start_call) : ?>
				<div class="chat-call-actions" aria-label="Aksi panggilan">
					<button type="button" class="chat-call-button doclinc-call-start" data-call-mode="audio" data-request-id="<?= html_escape($request_id); ?>" aria-label="Mulai panggilan suara">
						<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
							<path d="M22 16.92v2.2a2 2 0 0 1-2.18 2 19.74 19.74 0 0 1-8.59-3.05 19.35 19.35 0 0 1-5.96-5.96 19.74 19.74 0 0 1-3.05-8.59A2 2 0 0 1 4.21 1.34h2.2a2 2 0 0 1 2 1.72c.13.96.35 1.9.65 2.8a2 2 0 0 1-.45 2.11L7.68 8.9a15.78 15.78 0 0 0 6.42 6.42l.93-.93a2 2 0 0 1 2.11-.45c.9.3 1.84.52 2.8.65A2 2 0 0 1 22 16.92Z" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</button>
					<button type="button" class="chat-call-button doclinc-call-start" data-call-mode="video" data-request-id="<?= html_escape($request_id); ?>" aria-label="Mulai panggilan video">
						<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
							<path d="M4.5 6.5h9.2a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H4.5a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Z" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
							<path d="m15.7 10 4.8-2.8v9.6L15.7 14" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</button>
				</div>
			<?php endif; ?>
		</header>

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

	<?php if ($call_ui_enabled) : ?>
		<div class="doclinc-call-modal" id="doclincCallModal" role="dialog" aria-modal="true" aria-labelledby="doclincCallTitle" aria-hidden="true">
			<div class="doclinc-call-panel">
				<div class="doclinc-call-head">
					<div class="doclinc-call-context">
						<img class="doclinc-call-avatar" src="<?= html_escape($asset_base . 'doctor-placeholder.jpg'); ?>" alt="Profil layanan">
						<div>
							<h2 class="doclinc-call-title" id="doclincCallTitle"><?= html_escape($partner_name); ?></h2>
							<div class="doclinc-call-status" id="doclincCallStatus">Siap bergabung</div>
						</div>
					</div>
					<button type="button" class="doclinc-call-close" id="doclincCallClose" aria-label="Minimalkan panggilan">&times;</button>
				</div>
				<div class="doclinc-call-stage">
					<div class="doclinc-call-remote" id="doclincCallRemote">
						<div class="doclinc-call-empty" id="doclincCallEmpty">
							<img class="doclinc-call-empty-avatar" src="<?= html_escape($asset_base . 'doctor-placeholder.jpg'); ?>" alt="">
							<span class="doclinc-call-empty-name"><?= html_escape($partner_name); ?></span>
							<span id="doclincCallEmptyText">Menunggu lawan bicara bergabung</span>
						</div>
					</div>
					<div class="doclinc-call-local is-hidden" id="doclincCallLocal"></div>
				</div>
				<div class="doclinc-call-prejoin" id="doclincCallPrejoin">
					<p class="doclinc-call-prejoin-title">Panggilan untuk konsultasi aktif</p>
					<p class="doclinc-call-prejoin-text">Pastikan kamera dan mikrofon perangkat dapat digunakan.</p>
					<button type="button" class="doclinc-call-join" id="doclincCallJoin">Gabung sekarang</button>
				</div>
				<div class="doclinc-call-controls is-hidden" id="doclincCallControls">
					<button type="button" class="doclinc-call-control" id="doclincCallMic" title="Mikrofon" aria-label="Mikrofon">
						<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
							<path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v5a3 3 0 0 0 3 3Z" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
							<path d="M19 11a7 7 0 0 1-14 0M12 18v4M8 22h8" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</button>
					<button type="button" class="doclinc-call-control" id="doclincCallCamera" title="Kamera" aria-label="Kamera">
						<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
							<path d="M4.5 6.5h9.2a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H4.5a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Z" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
							<path d="m15.7 10 4.8-2.8v9.6L15.7 14" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</button>
					<button type="button" class="doclinc-call-control" id="doclincCallSwitch" title="Ganti kamera" aria-label="Ganti kamera">
						<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
							<path d="M16 3h5v5M20.5 3.5 15 9M8 21H3v-5M3.5 20.5 9 15" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
							<path d="M21 12a9 9 0 0 1-9 9M3 12a9 9 0 0 1 9-9" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</button>
					<button type="button" class="doclinc-call-control" id="doclincCallMinimize" title="Minimalkan" aria-label="Minimalkan panggilan">
						<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
							<path d="M6 12h12" stroke-width="2.2" stroke-linecap="round" />
						</svg>
					</button>
					<button type="button" class="doclinc-call-control end-call" id="doclincCallEnd" title="Akhiri" aria-label="Akhiri">
						<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
							<path d="M5.2 15.1c4.5-2.7 9.1-2.7 13.6 0 .7.4 1.6.2 2-.5l.8-1.5c.4-.7.2-1.6-.5-2-5.9-3.5-12.3-3.5-18.2 0-.7.4-.9 1.3-.5 2l.8 1.5c.4.7 1.3.9 2 .5Z" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</button>
				</div>
			</div>
		</div>
		<button type="button" class="doclinc-active-call is-hidden" id="doclincActiveCall" aria-label="Buka panggilan aktif">
			<span class="doclinc-active-call-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" width="18" height="18">
					<path d="M22 16.92v2.2a2 2 0 0 1-2.18 2 19.74 19.74 0 0 1-8.59-3.05 19.35 19.35 0 0 1-5.96-5.96 19.74 19.74 0 0 1-3.05-8.59A2 2 0 0 1 4.21 1.34h2.2a2 2 0 0 1 2 1.72c.13.96.35 1.9.65 2.8a2 2 0 0 1-.45 2.11L7.68 8.9a15.78 15.78 0 0 0 6.42 6.42l.93-.93a2 2 0 0 1 2.11-.45c.9.3 1.84.52 2.8.65A2 2 0 0 1 22 16.92Z" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
			</span>
			<span class="doclinc-active-call-main">
				<span class="doclinc-active-call-title">Panggilan aktif</span>
				<span class="doclinc-active-call-meta" id="doclincCallElapsed">00:00</span>
			</span>
			<span class="doclinc-active-call-restore">Buka</span>
		</button>
		<script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
	<?php else : ?>
		<?php /* TODO #16D: render incoming call invitation here after reliable call invite signaling exists. */ ?>
	<?php endif; ?>

	<?php if ($can_receive_call) : ?>
		<div class="doclinc-incoming-call is-hidden" id="doclincIncomingCall" aria-live="polite">
			<p class="doclinc-incoming-kicker">Panggilan masuk</p>
			<p class="doclinc-incoming-name" id="doclincIncomingCaller">Nakes DocLink</p>
			<p class="doclinc-incoming-type" id="doclincIncomingType">Panggilan video</p>
			<div class="doclinc-incoming-actions">
				<button type="button" class="doclinc-incoming-action reject" id="doclincIncomingReject">Tolak</button>
				<button type="button" class="doclinc-incoming-action answer" id="doclincIncomingAnswer">Terima</button>
			</div>
		</div>
	<?php endif; ?>

	<?php if ($call_ui_enabled) : ?>
		<script>
			(function() {
				const requestId = <?= json_encode($request_id); ?>;
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
					incomingFailures: 0
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
					elements.emptyText = byId('doclincCallEmptyText');
					elements.local = byId('doclincCallLocal');
					elements.prejoin = byId('doclincCallPrejoin');
					elements.controls = byId('doclincCallControls');
					elements.join = byId('doclincCallJoin');
					elements.mic = byId('doclincCallMic');
					elements.camera = byId('doclincCallCamera');
					elements.switchCamera = byId('doclincCallSwitch');
					elements.end = byId('doclincCallEnd');
					elements.close = byId('doclincCallClose');
					elements.minimize = byId('doclincCallMinimize');
					elements.activeCall = byId('doclincActiveCall');
					elements.elapsed = byId('doclincCallElapsed');
					elements.incoming = byId('doclincIncomingCall');
					elements.incomingCaller = byId('doclincIncomingCaller');
					elements.incomingType = byId('doclincIncomingType');
					elements.incomingAnswer = byId('doclincIncomingAnswer');
					elements.incomingReject = byId('doclincIncomingReject');
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
					elements.empty.classList.toggle('is-hidden', !!elements.remote.querySelector('video'));
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
					state.callId = null;
					state.connecting = false;
					state.minimized = false;
					stopLocalTracks();
					stopElapsedTimer();
					stopStatusPolling();
					if (elements.remote) {
						Array.prototype.slice.call(elements.remote.querySelectorAll('video,audio')).forEach(function(node) {
							node.remove();
						});
					}
					updateEmptyState();
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
					state.micEnabled = true;
					state.cameraEnabled = state.mode === 'video';
					if (elements.camera) {
						elements.camera.classList.toggle('is-off', state.mode !== 'video');
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
						}).finally(function() {
							endLocalCall('Panggilan berakhir');
						});
						return;
					}
					endLocalCall('Panggilan berakhir');
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
						state.room = null;
						state.connecting = false;
						state.minimized = false;
						stopLocalTracks();
						if (elements.remote) {
							Array.prototype.slice.call(elements.remote.querySelectorAll('video,audio')).forEach(function(node) {
								node.remove();
							});
						}
						updateEmptyState();
						setStatus('Panggilan terputus');
						setCallActive(false);
						stopElapsedTimer();
						updateFloatingCall();
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
						return response.json().catch(function() {
							return {};
						});
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
						}).catch(function() {});
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
					attachTrack(track, elements.local);
					elements.local.classList.remove('is-hidden');
				}

				function showLocalPlaceholder(message) {
					if (!elements.local) {
						return;
					}
					clearNode(elements.local);
					elements.local.textContent = message || 'Kamera nonaktif';
					elements.local.classList.add('is-placeholder');
					elements.local.classList.remove('is-hidden');
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
						return createLocalTrack('audio').then(publishTrack).catch(function() {
							state.micEnabled = false;
							if (elements.mic) {
								elements.mic.classList.add('is-off');
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
							}
							showLocalPreview(track);
							return publishTrack(track);
						}).catch(function() {
							state.cameraEnabled = false;
							if (elements.camera) {
								elements.camera.classList.add('is-off');
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
						cleanupCall('Panggilan belum dapat tersambung. Coba lagi.');
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

				function callTypeLabel(callType) {
					return callType === 'audio' ? 'Panggilan suara' : 'Panggilan video';
				}

				function hideIncomingCall() {
					state.incomingCall = null;
					if (elements.incoming) {
						elements.incoming.classList.add('is-hidden');
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
						elements.incomingCaller.textContent = call.caller_name || 'Nakes DocLink';
					}
					if (elements.incomingType) {
						elements.incomingType.textContent = callTypeLabel(call.call_type);
					}
					elements.incoming.classList.remove('is-hidden');
				}

				function pollIncomingCall() {
					if (!canReceiveCall || state.room || state.connecting) {
						return;
					}
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
					if (!call || !call.call_id) {
						return;
					}
					hideIncomingCall();
					state.callId = call.call_id;
					state.mode = call.call_type === 'audio' ? 'audio' : 'video';
					showCallScreen();
					joinCall();
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
					const callId = state.incomingCall && state.incomingCall.call_id ? state.incomingCall.call_id : state.callId;
					hideIncomingCall();
					if (!callId) {
						return;
					}
					postForm(rejectCallUrl, {
						call_id: callId
					}).then(function(response) {
						if (response && response.message) {
							setStatus(response.message, !!response.expired);
						}
					}).catch(function() {});
				}

				document.addEventListener('DOMContentLoaded', function() {
					cacheElements();
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
					if (elements.switchCamera) {
						elements.switchCamera.addEventListener('click', switchCamera);
					}
					if (elements.end) {
						elements.end.addEventListener('click', endCall);
					}
					if (elements.close) {
						elements.close.addEventListener('click', minimizeCallScreen);
					}
					if (elements.minimize) {
						elements.minimize.addEventListener('click', minimizeCallScreen);
					}
					if (elements.activeCall) {
						elements.activeCall.addEventListener('click', showCallScreen);
					}
					if (elements.incomingAnswer) {
						elements.incomingAnswer.addEventListener('click', answerIncomingCall);
					}
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
					} else {
						startIncomingPolling();
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
						document.getElementById('chatMessages').innerHTML = '<div class="chat-error">Chat belum dapat dimuat. Coba lagi.</div>';
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
							appendMessage(data.message);
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
