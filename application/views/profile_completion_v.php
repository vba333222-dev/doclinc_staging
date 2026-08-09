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
$photo_required = in_array('photo', $missing_fields, true);
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
	<title>Lengkapi data - DocLink</title>
	<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-profile-completion.css')); ?>">
</head>
<body>
	<main class="completion-shell">
		<section class="completion-card" aria-labelledby="completionTitle">
			<header class="completion-head">
				<div class="completion-brand">DocLink</div>
				<h1 id="completionTitle">Lengkapi data sebelum melanjutkan</h1>
				<p><?= $is_command_center ? 'Lengkapi kontak operasional Puskesmas. Data unit dan staf lainnya dikelola Administrator Dinas Kesehatan tanpa mengunci fungsi inti Puskesmas.' : 'Data ini dibutuhkan untuk keamanan identitas, penugasan, dan layanan kesehatan. Fitur aplikasi tetap terkunci sampai seluruh data wajib valid.'; ?></p>
			</header>
			<?php if (!empty($profile_state['schema_gaps'])) : ?>
			<div class="completion-notice" role="alert">
				<strong>Struktur data sedang disiapkan</strong>
				<span>Sebagian data belum dapat disimpan. Hubungi pengelola dan jangan mengirim nomor identitas melalui chat.</span>
			</div>
			<?php endif; ?>

			<?php if (!empty($missing_labels)) : ?>
			<section class="completion-gaps" aria-labelledby="missingTitle">
				<h2 id="missingTitle">Data yang masih dibutuhkan</h2>
				<ul>
					<?php foreach ($missing_labels as $index => $label) :
						$field = isset($missing_fields[$index]) ? (string) $missing_fields[$index] : '';
						$owner = in_array($field, $self_service_fields, true) ? 'Dapat Anda lengkapi' : 'Dikelola Admin Dinas Kesehatan';
					?>
					<li><strong><?= html_escape($label); ?></strong><span><?= html_escape($owner); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</section>
			<?php endif; ?>

			<?php if (!empty($managed_fields)) : ?>
			<div class="completion-notice" role="status">
				<strong>Perlu tindakan Admin Dinas Kesehatan</strong>
				<span>Data kepegawaian Nakes, NIP, SIP, profesi, linkage akun, dan data fasilitas tidak dapat diubah oleh Nakes atau Puskesmas.</span>
			</div>
			<?php endif; ?>

			<?php if ($can_edit_profile) : ?>
			<form id="profileCompletionForm" class="completion-form" enctype="multipart/form-data" novalidate>
				<h2>Data profil</h2>
				<?php if ($is_warga || $is_personal) : ?>
				<div class="completion-photo">
					<img src="<?= doclinc_profile_image_src((int) ($profile_user['userId'] ?? 0), (string) ($profile_user['foto'] ?? '')); ?>" alt="Foto profil saat ini">
					<label>Foto asli
						<input type="file" name="foto" accept="image/jpeg,image/png,image/webp" <?= $photo_required ? 'required' : ''; ?>>
					</label>
				</div>
				<?php else : ?>
				<div class="completion-notice" role="status"><strong>Akun fasilitas</strong><span>Akun Puskesmas tidak memerlukan foto pribadi, nama lengkap personal, tanggal lahir, atau jenis kelamin.</span></div>
				<?php endif; ?>
				<div class="completion-grid">
					<?php if ($is_warga || $is_personal) : ?>
					<label>Nama lengkap<input type="text" name="nama_lengkap" maxlength="100" value="<?= html_escape((string) ($profile_user['nama'] ?? '')); ?>" required></label>
					<?php endif; ?>
					<label><?= $is_command_center ? 'Email operasional' : 'Email'; ?><input type="email" name="email" maxlength="100" value="<?= html_escape((string) ($profile_user['email'] ?? '')); ?>" required></label>
					<label><?= $is_command_center ? 'Nomor kontak Puskesmas' : 'Nomor HP'; ?><input type="tel" name="no_hp" maxlength="20" inputmode="tel" value="<?= html_escape((string) ($profile_user['no_hp'] ?? '')); ?>" required></label>
					<?php if ($is_warga || $is_personal) : ?>
					<label>Tanggal lahir<input type="date" name="tgl_lahir" max="<?= html_escape(date('Y-m-d')); ?>" value="<?= html_escape((string) ($profile_user['tgl'] ?? '')); ?>" required></label>
					<label>Jenis kelamin<select name="jk" required><option value="">Pilih</option><option value="Laki-laki" <?= (string) ($profile_user['gender'] ?? '') === 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option><option value="Perempuan" <?= (string) ($profile_user['gender'] ?? '') === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option></select></label>
					<label class="full">Alamat<textarea name="alamat" maxlength="500" required><?= html_escape((string) ($profile_user['alamat'] ?? '')); ?></textarea></label>
					<?php endif; ?>
					<?php if ($is_warga) : ?>
					<label>NIK<input type="text" name="nik" inputmode="numeric" pattern="[0-9]{16}" minlength="16" maxlength="16" value="<?= html_escape((string) ($profile_user['nik'] ?? '')); ?>" autocomplete="off" required></label>
					<label>Nomor Kartu Keluarga<input type="text" name="nomor_kk" inputmode="numeric" pattern="[0-9]{16}" minlength="16" maxlength="16" value="<?= html_escape((string) ($profile_user['nomor_kk'] ?? '')); ?>" autocomplete="off" required></label>
					<label>Nomor kartu BPJS/KIS<input type="text" name="nomor_bpjs_kis" inputmode="numeric" pattern="[0-9]{13}" minlength="13" maxlength="13" value="<?= html_escape((string) ($profile_user['nomor_bpjs_kis'] ?? '')); ?>" autocomplete="off" required></label>
					<?php endif; ?>
				</div>
				<div id="profileCompletionFeedback" class="completion-feedback" role="alert" aria-live="polite"></div>
				<button type="submit" class="completion-primary">Simpan dan periksa kembali</button>
			</form>
			<?php endif; ?>

			<footer class="completion-footer">
				<p>Jika data yang dikelola Admin belum tepat, hubungi Dinas Kesehatan. Jangan kirim NIK, KK, BPJS/KIS, atau NIP melalui chat.</p>
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
