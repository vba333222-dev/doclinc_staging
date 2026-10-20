<?php
defined('BASEPATH') or exit('No direct script access allowed');
$profile_user = isset($profile_user) && is_array($profile_user) ? $profile_user : array();
$profile_state = isset($profile_state) && is_array($profile_state) ? $profile_state : array();
$profile_role = isset($profile_role) ? (string) $profile_role : '';
$profile_account_type = isset($profile_account_type) ? (string) $profile_account_type : 'unclassified';
$missing_fields = isset($profile_state['missing_fields']) && is_array($profile_state['missing_fields']) ? $profile_state['missing_fields'] : array();
$missing_labels = isset($profile_state['missing_labels']) && is_array($profile_state['missing_labels']) ? $profile_state['missing_labels'] : array();
$self_service_fields = isset($profile_state['self_service_fields']) && is_array($profile_state['self_service_fields']) ? $profile_state['self_service_fields'] : array();
$managed_fields = isset($profile_state['managed_fields']) && is_array($profile_state['managed_fields']) ? $profile_state['managed_fields'] : array();
$is_warga = $profile_role === 'warga';
$is_personal = $profile_account_type === 'personal';
$is_command_center = $profile_account_type === 'command_center';
$can_edit_profile = $is_warga || ($profile_role === 'dokter' && in_array($profile_account_type, array('personal', 'command_center'), true));
$profile_update_url = $is_warga ? base_url('profile/update') : base_url('home_nakes/updateprofile');
$profile_photo_url = base_url('profile/photo/update');
$redirect_url = $is_warga ? base_url('home') : base_url('home_nakes');
?>
<!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<?= doclinc_csrf_bootstrap_markup(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title>Lengkapi data - DokLinc</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-design-system.css')); ?>">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-profile-completion.css')); ?>">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-warga.css')); ?>">
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-reference-ui.css') . '?v=' . rawurlencode((string) @filemtime(FCPATH . 'assets/css/doclinc-reference-ui.css'))); ?>">
</head>
<body class="dl-warga-profile">
	<main class="completion-shell">
		<section class="completion-card" aria-labelledby="completionTitle">
			<header class="completion-head">
				<div class="completion-brand">DokLinc</div>
				<h1 id="completionTitle">Lengkapi data akun</h1>
				<p><?= $is_command_center ? 'Lengkapi kontak operasional Puskesmas. Data unit dan staf lainnya dikelola Admin Dinas Kesehatan.' : 'Isi data yang kosong, lalu simpan.'; ?></p>
			</header>
			<?php if (!empty($profile_state['schema_gaps'])) : ?>
			<div class="completion-notice" role="alert">
				<strong>Struktur data sedang disiapkan</strong>
				<span>Sebagian data belum dapat disimpan. Hubungi pengelola dan jangan mengirim nomor identitas melalui chat.</span>
			</div>
			<?php endif; ?>

			<?php if (!empty($missing_labels)) : ?>
			<section class="completion-gaps" aria-labelledby="missingTitle">
				<h2 id="missingTitle">Yang perlu dilengkapi</h2>
				<ul>
					<?php foreach ($missing_labels as $index => $label) :
						$field = isset($missing_fields[$index]) ? (string) $missing_fields[$index] : '';
						$owner = in_array($field, $self_service_fields, true) ? 'Bisa Anda isi' : 'Diisi Admin Dinas Kesehatan';
					?>
					<li><strong><?= html_escape($label); ?></strong><span><?= html_escape($owner); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</section>
			<?php endif; ?>

			<?php if (!empty($managed_fields)) : ?>
			<div class="completion-notice" role="status">
				<strong>Perlu tindakan Admin Dinas Kesehatan</strong>
				<span>Data kepegawaian, SIP, dan fasilitas dikelola Admin Dinas Kesehatan. NIP bersifat opsional.</span>
			</div>
			<?php endif; ?>

			<?php if ($can_edit_profile) : ?>
			<form id="profileCompletionForm" class="completion-form" enctype="multipart/form-data" novalidate>
				<div id="profileCompletionErrorSummary" class="completion-summary" role="alert" hidden></div>
				<?php if ($is_warga || $is_personal) : ?>
				<div class="completion-photo">
					<img id="completionPhotoPreview" src="<?= doclinc_profile_image_src((int) ($profile_user['userId'] ?? 0), (string) ($profile_user['foto'] ?? '')); ?>" alt="" width="72" height="72">
					<div class="completion-photo__field">
						<label class="completion-photo__label" for="completionPhoto">Foto profil <small>(opsional)</small></label>
						<input id="completionPhoto" class="completion-file-input" type="file" name="foto" accept="image/jpeg,image/png,image/webp" tabindex="-1" aria-describedby="completionPhotoHint">
						<div class="completion-photo__actions">
							<button type="button" class="completion-photo-trigger" id="completionPhotoTrigger">Pilih foto</button>
							<span class="completion-file-name" id="completionPhotoName">Belum ada file dipilih.</span>
						</div>
						<p class="completion-help" id="completionPhotoHint">JPG, PNG, atau WebP, maksimal 5 MB.</p>
					</div>
				</div>
				<?php else : ?>
				<div class="completion-notice" role="status"><strong>Akun fasilitas</strong><span>Akun Puskesmas tidak memerlukan foto pribadi, nama lengkap personal, tanggal lahir, atau jenis kelamin.</span></div>
				<?php endif; ?>

				<fieldset>
					<legend><?= $is_warga ? 'Data wajib' : 'Data profil'; ?></legend>
					<div class="completion-grid">
						<?php if ($is_warga || $is_personal) : ?>
						<div class="completion-field">
							<label for="completionNama">Nama lengkap</label>
							<input id="completionNama" type="text" name="nama_lengkap" minlength="2" maxlength="100" value="<?= html_escape((string) ($profile_user['nama'] ?? '')); ?>" aria-describedby="err-nama_lengkap" required>
							<p class="completion-field-error" id="err-nama_lengkap" hidden></p>
						</div>
						<?php endif; ?>
						<div class="completion-field">
							<label for="completionEmail"><?= $is_command_center ? 'Email operasional' : ($is_personal ? 'Email akun' : 'Email (opsional)'); ?></label>
							<input id="completionEmail" type="email" name="email" maxlength="100" value="<?= html_escape((string) ($profile_user['email'] ?? '')); ?>" aria-describedby="err-email" <?= ($is_command_center || $is_personal) ? 'required' : ''; ?>>
							<p class="completion-field-error" id="err-email" hidden></p>
						</div>
						<div class="completion-field">
							<label for="completionPhone"><?= $is_command_center ? 'Nomor kontak Puskesmas' : 'Nomor HP'; ?></label>
							<input id="completionPhone" type="tel" name="no_hp" maxlength="20" inputmode="tel" pattern="\+?[0-9]{8,20}" value="<?= html_escape((string) ($profile_user['no_hp'] ?? '')); ?>" aria-describedby="err-no_hp" data-expected="Gunakan 8–20 angka." required>
							<p class="completion-help">8–20 angka.</p>
							<p class="completion-field-error" id="err-no_hp" hidden></p>
						</div>
						<?php if ($is_warga || $is_personal) : ?>
						<div class="completion-field">
							<label for="completionBirth">Tanggal lahir</label>
							<input id="completionBirth" type="date" name="tgl_lahir" max="<?= html_escape(date('Y-m-d')); ?>" value="<?= html_escape((string) ($profile_user['tgl'] ?? '')); ?>" aria-describedby="err-tgl_lahir" required>
							<p class="completion-field-error" id="err-tgl_lahir" hidden></p>
						</div>
						<div class="completion-field">
							<label for="completionGender">Jenis kelamin</label>
							<select id="completionGender" name="jk" aria-describedby="err-jk" required><option value="">Pilih</option><option value="Laki-laki" <?= (string) ($profile_user['gender'] ?? '') === 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option><option value="Perempuan" <?= (string) ($profile_user['gender'] ?? '') === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option></select>
							<p class="completion-field-error" id="err-jk" hidden></p>
						</div>
						<?php endif; ?>
						<?php if ($is_warga) : ?>
						<div class="completion-field">
							<label for="completionNik">NIK</label>
							<input id="completionNik" type="text" name="nik" inputmode="numeric" pattern="[0-9]{16}" minlength="16" maxlength="16" value="<?= html_escape((string) ($profile_user['nik'] ?? '')); ?>" autocomplete="off" aria-describedby="err-nik" data-expected="NIK harus 16 angka." required>
							<p class="completion-help">16 angka.</p>
							<p class="completion-field-error" id="err-nik" hidden></p>
						</div>
						<?php endif; ?>
					</div>
				</fieldset>
				<?php if ($is_warga) : ?>
				<h2>Data tambahan (opsional)</h2>
				<p>Isi data berikut bila tersedia.</p>
				<div class="completion-grid">
					<div class="completion-field">
						<label for="completionKk">Nomor KK (opsional)</label>
						<input id="completionKk" type="text" name="nomor_kk" inputmode="numeric" pattern="[0-9]{16}" minlength="16" maxlength="16" value="<?= html_escape((string) ($profile_user['nomor_kk'] ?? '')); ?>" autocomplete="off" aria-describedby="err-nomor_kk" data-expected="Nomor KK harus 16 angka.">
						<p class="completion-help">16 angka.</p>
						<p class="completion-field-error" id="err-nomor_kk" hidden></p>
					</div>
					<div class="completion-field">
						<label for="completionBpjs">BPJS/JKN/Taspen (opsional)</label>
						<input id="completionBpjs" type="text" name="nomor_bpjs_kis" inputmode="numeric" pattern="[0-9]{13}" minlength="13" maxlength="13" value="<?= html_escape((string) ($profile_user['nomor_bpjs_kis'] ?? '')); ?>" autocomplete="off" aria-describedby="err-nomor_bpjs_kis" data-expected="Nomor BPJS/KIS harus 13 angka.">
						<p class="completion-help">13 angka.</p>
						<p class="completion-field-error" id="err-nomor_bpjs_kis" hidden></p>
					</div>
					<div class="completion-field full">
						<label for="completionAddress">Alamat (opsional)</label>
						<textarea id="completionAddress" name="alamat" maxlength="500" minlength="5" aria-describedby="err-alamat"></textarea>
						<p class="completion-field-error" id="err-alamat" hidden></p>
					</div>
				</div>
				<?php elseif ($is_personal) : ?>
				<h2>Data opsional</h2>
				<div class="completion-grid">
					<div class="completion-field full">
						<label for="completionAddress">Alamat (opsional)</label>
						<textarea id="completionAddress" name="alamat" maxlength="500" minlength="5" aria-describedby="err-alamat"></textarea>
						<p class="completion-field-error" id="err-alamat" hidden></p>
					</div>
				</div>
				<?php endif; ?>
				<div id="profileCompletionFeedback" class="completion-feedback" role="status" aria-live="polite"></div>
				<button type="submit" class="completion-primary" id="profileCompletionSubmit">Simpan profil</button>
			</form>
			<?php endif; ?>

			<footer class="completion-footer">
				<button type="button" data-profile-logout>Keluar dari akun</button>
			</footer>
		</section>
	</main>
	<script>
	window.DOCLINC_PROFILE_COMPLETION = <?= json_encode(array(
		'role' => $profile_role,
		'profileUpdateUrl' => $profile_update_url,
		'photoUpdateUrl' => $profile_photo_url,
		'statusUrl' => base_url('profile/requirements'),
		'redirectUrl' => $redirect_url,
		'logoutUrl' => base_url('login/logout'),
	), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	</script>
	<script src="<?= html_escape(base_url('assets/js/doclinc-csrf.js')); ?>"></script>
	<script src="<?= html_escape(base_url('assets/js/doclinc-profile-completion.js')); ?>"></script>
</body>
</html>
