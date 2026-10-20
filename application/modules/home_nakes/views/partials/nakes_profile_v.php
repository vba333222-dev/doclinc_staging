<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
require_once APPPATH . 'helpers/profile_readiness_presentation_helper.php';

$profile_name = (string) $this->session->userdata('nama');
$profile_email = isset($profile['email']) ? trim((string) $profile['email']) : (string) $this->session->userdata('email');
$profile_photo = isset($profile['foto']) ? trim((string) $profile['foto']) : '';
$profile_birthdate = isset($profile['tgl']) ? (string) $profile['tgl'] : '';
$profile_gender = isset($profile['gender']) ? (string) $profile['gender'] : '';
$profile_phone = isset($profile['no_hp']) ? (string) $profile['no_hp'] : '';
$profile_address = isset($profile['alamat']) ? (string) $profile['alamat'] : '';
$profile_puskesmas_name = isset($nakes_puskesmas_name) ? trim((string) $nakes_puskesmas_name) : '';
$profile_puskesmas_code = isset($nakes_puskesmas_code) ? trim((string) $nakes_puskesmas_code) : '';
$profile_puskesmas_address = isset($profile['assigned_puskesmas_address']) ? trim((string) $profile['assigned_puskesmas_address']) : '';
$profile_account_type = isset($nakes_account_type) ? (string) $nakes_account_type : 'unclassified';
$profile_is_command_center = $profile_account_type === 'command_center';
$profile_is_personal = $profile_account_type === 'personal';
$profile_identity_staff = isset($nakes_identity_staff) && is_array($nakes_identity_staff) ? $nakes_identity_staff : array();
$profile_staff_rows = isset($puskesmas_staff_list) && is_array($puskesmas_staff_list) ? $puskesmas_staff_list : array();
$profile_staff_count = isset($puskesmas_staff_count) ? (int) $puskesmas_staff_count : count($profile_staff_rows);
$profile_staff_account_states = array();
if (isset($puskesmas_staff_options) && is_array($puskesmas_staff_options)) {
	foreach ($puskesmas_staff_options as $staff_option) {
		$profile_staff_account_states[(int) $staff_option->staff_id] = isset($staff_option->personal_account_state) ? (string) $staff_option->personal_account_state : 'invalid';
	}
}

$profile_weak_value = static function ($value) {
	$value = trim(strip_tags((string) $value));
	return $value === '' || in_array(strtolower($value), array('n/a', 'na', '-', 'belum ditentukan', 'default'), true);
};
$profile_show = static function ($value, $fallback = 'Belum diisi') {
	$value = trim((string) $value);
	return $value !== '' ? $value : $fallback;
};
$profile_date_label = static function ($value) {
	$value = trim((string) $value);
	if ($value === '' || $value === '0000-00-00') {
		return '';
	}
	$timestamp = strtotime($value);
	if ($timestamp === false) {
		return $value;
	}
	$months = array(1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember');
	return date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
};
$profile_gender_label = static function ($value) {
	$value = strtolower(trim((string) $value));
	if (in_array($value, array('l', 'laki-laki', 'male'), true)) {
		return 'Laki-laki';
	}
	if (in_array($value, array('p', 'perempuan', 'female'), true)) {
		return 'Perempuan';
	}
	return trim((string) $value);
};
$profile_staff_state_label = static function ($state) {
	$state = strtolower(trim((string) $state));
	if (in_array($state, array('active', 'aktif'), true)) {
		return 'Akun aktif';
	}
	if (in_array($state, array('invalid', 'nonaktif', 'inactive'), true)) {
		return 'Akun belum valid';
	}
	return '';
};

$profile_puskesmas_display = !$profile_weak_value($profile_puskesmas_name) ? $profile_puskesmas_name : (!$profile_weak_value($profile_puskesmas_code) ? $profile_puskesmas_code : 'Belum dikonfigurasi');
$profile_display_name = $profile_is_command_center ? $profile_puskesmas_display : $profile_name;
$profile_profesi = isset($profile_identity_staff['staff_profesi']) ? trim((string) $profile_identity_staff['staff_profesi']) : '';
$profile_gelar = isset($profile_identity_staff['staff_gelar']) ? trim((string) $profile_identity_staff['staff_gelar']) : '';
$profile_sip = isset($profile_identity_staff['staff_nomor_sip']) ? trim((string) $profile_identity_staff['staff_nomor_sip']) : '';
$profile_sip_expiry = $profile_date_label(isset($profile_identity_staff['staff_sip_expired_at']) ? $profile_identity_staff['staff_sip_expired_at'] : '');
$profile_birthdate_label = $profile_date_label($profile_birthdate);
$profile_gender_label_value = $profile_gender_label($profile_gender);
$profile_readiness_state = '';
if (isset($role_prerequisite_state) && is_array($role_prerequisite_state) && array_key_exists('complete', $role_prerequisite_state)) {
	$profile_readiness_state = !empty($role_prerequisite_state['complete']) ? 'COMPLETE' : 'INCOMPLETE';
}
$profile_readiness_label = $profile_readiness_state !== '' ? doclinc_profile_readiness_label($profile_readiness_state) : 'Belum tersedia';
$profile_role_label = $profile_is_command_center ? 'Puskesmas' : ($profile_is_personal ? 'Nakes' : 'Status belum tersedia');
$profile_eyebrow = ($profile_is_personal && $profile_profesi !== '') ? $profile_role_label . ' · ' . $profile_profesi : $profile_role_label;
$profile_title = $profile_is_command_center ? 'Profil Puskesmas' : ($profile_is_personal ? 'Profil nakes' : 'Data akun');
$profile_edit_label = $profile_is_command_center ? 'Ubah kontak' : 'Ubah profil';
$profile_readiness_badge = '';
if ($profile_readiness_state === 'COMPLETE') {
	$profile_readiness_badge = 'Profil lengkap';
} elseif ($profile_readiness_state === 'INCOMPLETE') {
	$profile_readiness_badge = 'Perlu dilengkapi';
}
$profile_lede = $profile_is_command_center
	? 'Kontak operasional dan ringkasan tim Puskesmas.'
	: ($profile_is_personal ? 'Data ini dipakai saat menangani permintaan warga.' : 'Hubungi Admin Dinas Kesehatan untuk melengkapi penugasan akun.');
$profile_foot_note = $profile_is_command_center
	? 'Profil ini hanya mengubah kontak unit.'
	: ($profile_is_personal
		? 'Perubahan di sini memperbarui data profil dan foto Anda. Data kepegawaian, SIP, dan fasilitas tetap dikelola Admin.'
		: 'Hubungi Admin Dinas Kesehatan untuk penugasan.');
?>
<div id="profile" class="content dl-account-surface">
	<div class="dl-account">
		<header class="dl-account__head">
			<h2 class="dl-account__title"><?= html_escape($profile_title); ?></h2>
			<p class="dl-account__lede"><?= html_escape($profile_lede); ?></p>
		</header>

		<div class="dl-account__layout">
			<section class="dl-account-id dl-account-id--nakes<?= $profile_is_command_center ? ' dl-account-id--unit' : ''; ?>" aria-labelledby="nakesAccountSummary">
				<h3 class="visually-hidden" id="nakesAccountSummary">Ringkasan akun</h3>
				<?php $this->load->view('partials/nakes_avatar_v', array(
'avatar_name' => $profile_display_name,
					'avatar_photo' => $profile_is_personal ? $profile_photo : '',
					'avatar_user_id' => (int) $this->session->userdata('id'),
					'avatar_alt' => $profile_is_personal ? 'Foto profil' : 'Ikon akun Puskesmas',
					'avatar_class' => 'nk-avatar--lg nk-avatar--nakes',
					'avatar_icon' => $profile_is_command_center ? 'fas fa-hospital' : 'fas fa-user-md',
				)); ?>
				<span class="dl-account-id__eyebrow"><?= html_escape($profile_eyebrow); ?></span>
				<p class="dl-account-id__name"><?= html_escape($profile_show($profile_display_name)); ?></p>
				<?php if ($profile_email !== '') : ?>
				<p class="dl-account-id__meta"><?= html_escape($profile_email); ?></p>
				<?php endif; ?>
				<?php if ($profile_phone !== '') : ?>
				<p class="dl-account-id__meta dl-account-id__meta--mono"><?= html_escape($profile_phone); ?></p>
				<?php endif; ?>
				<?php if ($profile_readiness_badge !== '') : ?>
				<span class="dl-account-id__status <?= $profile_readiness_state === 'COMPLETE' ? 'is-ready' : 'is-incomplete'; ?>"><?= html_escape($profile_readiness_badge); ?></span>
				<?php endif; ?>
				<?php if ($profile_is_personal || $profile_is_command_center) : ?>
				<button type="button" class="btn btn-success dl-account-id__edit" data-bs-toggle="modal" data-bs-target="#modalProfil"><?= html_escape($profile_edit_label); ?></button>
				<?php endif; ?>
			</section>

			<div class="dl-account-record">
				<?php if ($profile_is_personal) : ?>
				<section class="dl-account-section" aria-labelledby="nakesIdentityTitle">
					<h3 class="dl-account-section__title" id="nakesIdentityTitle">Identitas</h3>
					<dl class="dl-record-grid">
						<div class="dl-record"><dt>Nama lengkap</dt><dd><?= html_escape($profile_show($profile_name)); ?></dd></div>
						<div class="dl-record"><dt>Tanggal lahir</dt><dd class="dl-record__value--mono"><?= html_escape($profile_show($profile_birthdate_label)); ?></dd></div>
						<div class="dl-record"><dt>Jenis kelamin</dt><dd><?= html_escape($profile_show($profile_gender_label_value)); ?></dd></div>
						<?php if ($profile_profesi !== '') : ?><div class="dl-record"><dt>Profesi</dt><dd><?= html_escape($profile_profesi); ?></dd></div><?php endif; ?>
						<?php if ($profile_gelar !== '') : ?><div class="dl-record"><dt>Gelar</dt><dd><?= html_escape($profile_gelar); ?></dd></div><?php endif; ?>
					</dl>
				</section>

				<section class="dl-account-section" aria-labelledby="nakesContactTitle">
					<h3 class="dl-account-section__title" id="nakesContactTitle">Kontak</h3>
					<dl class="dl-record-grid">
						<div class="dl-record"><dt>Email</dt><dd><?= html_escape($profile_show($profile_email)); ?></dd></div>
						<div class="dl-record"><dt>Nomor HP</dt><dd class="dl-record__value--mono"><?= html_escape($profile_show($profile_phone)); ?></dd></div>
						<div class="dl-record dl-record--full"><dt>Alamat</dt><dd><?= html_escape($profile_show($profile_address)); ?></dd></div>
					</dl>
				</section>

				<section class="dl-account-section dl-account-section--assignment" aria-labelledby="nakesAssignmentTitle">
					<h3 class="dl-account-section__title" id="nakesAssignmentTitle">Penugasan</h3>
					<dl class="dl-record-grid">
						<div class="dl-record"><dt>Puskesmas</dt><dd><?= html_escape($profile_show($profile_puskesmas_display)); ?></dd></div>
						<div class="dl-record"><dt>Kode Puskesmas</dt><dd class="dl-record__value--mono"><?= html_escape($profile_show($profile_puskesmas_code)); ?></dd></div>
						<div class="dl-record"><dt>Nomor SIP</dt><dd class="dl-record__value--mono"><?= html_escape($profile_show($profile_sip)); ?></dd></div>
						<div class="dl-record"><dt>SIP berlaku sampai</dt><dd class="dl-record__value--mono"><?= html_escape($profile_show($profile_sip_expiry)); ?></dd></div>
						<?php if ($profile_readiness_badge === '') : ?>
						<div class="dl-record"><dt>Kelengkapan profil</dt><dd><?= html_escape($profile_readiness_label); ?></dd></div>
						<?php endif; ?>
					</dl>
					<p class="dl-account-section__note">Data kepegawaian, SIP, dan fasilitas dikelola Admin Dinas Kesehatan.</p>
				</section>
				<?php elseif ($profile_is_command_center) : ?>
				<section class="dl-account-section dl-account-section--unit" aria-labelledby="nakesUnitTitle">
					<h3 class="dl-account-section__title" id="nakesUnitTitle">Unit</h3>
					<dl class="dl-record-grid">
						<div class="dl-record"><dt>Nama unit</dt><dd><?= html_escape($profile_show($profile_puskesmas_display)); ?></dd></div>
						<div class="dl-record"><dt>Kode Puskesmas</dt><dd class="dl-record__value--mono"><?= html_escape($profile_show($profile_puskesmas_code)); ?></dd></div>
						<div class="dl-record"><dt>Email operasional</dt><dd><?= html_escape($profile_show($profile_email)); ?></dd></div>
						<div class="dl-record"><dt>Nomor kontak</dt><dd class="dl-record__value--mono"><?= html_escape($profile_show($profile_phone)); ?></dd></div>
						<div class="dl-record dl-record--full"><dt>Alamat layanan</dt><dd><?= html_escape($profile_show($profile_puskesmas_address)); ?></dd></div>
					</dl>
					<p class="dl-account-section__note">Data unit dan staf dikelola Admin Dinas Kesehatan.</p>
				</section>
				<?php else : ?>
				<section class="dl-account-section" aria-labelledby="nakesContactTitle">
					<h3 class="dl-account-section__title" id="nakesContactTitle">Kontak</h3>
					<dl class="dl-record-grid">
						<div class="dl-record"><dt>Email</dt><dd><?= html_escape($profile_show($profile_email)); ?></dd></div>
						<div class="dl-record"><dt>Nomor HP</dt><dd class="dl-record__value--mono"><?= html_escape($profile_show($profile_phone)); ?></dd></div>
					</dl>
					<p class="dl-account-section__note">Akun ini belum terhubung ke Puskesmas. Hubungi Admin Dinas Kesehatan untuk penugasan.</p>
				</section>
				<?php endif; ?>
			</div>
		</div>

		<?php if ($profile_is_command_center) : ?>
		<section class="dl-account-record" aria-labelledby="nakesTeamTitle">
			<div class="dl-account-section">
				<h3 class="dl-account-section__title" id="nakesTeamTitle">Tim Puskesmas</h3>
				<?php if (empty($profile_staff_rows)) : ?>
				<p class="dl-account-section__note">Belum ada staf.</p>
				<?php else : ?>
				<div class="nk-staff-list">
					<?php foreach ($profile_staff_rows as $staff) :
						$staff_name = trim((string) (isset($staff->nama) ? $staff->nama : ''));
						$staff_profesi = trim((string) (isset($staff->profesi) ? $staff->profesi : ''));
						$staff_phone = trim((string) (isset($staff->no_hp) ? $staff->no_hp : ''));
						$staff_sip = trim((string) (isset($staff->nomor_sip) ? $staff->nomor_sip : ''));
						$staff_state = isset($profile_staff_account_states[(int) $staff->staff_id]) ? $profile_staff_account_states[(int) $staff->staff_id] : 'invalid';
						$staff_state_label = $profile_staff_state_label($staff_state);
					?>
					<div class="nk-staff-item">
						<div class="nk-staff-main">
							<strong><?= html_escape($profile_show($staff_name, 'Nama belum diisi')); ?></strong>
							<?php if ($staff_profesi !== '') : ?><span><?= html_escape($staff_profesi); ?></span><?php endif; ?>
						</div>
						<div class="nk-staff-meta">
							<?php if ($staff_phone !== '') : ?><span><?= html_escape($staff_phone); ?></span><?php endif; ?>
							<?php if ($staff_sip !== '') : ?><span>SIP <?= html_escape($staff_sip); ?></span><?php endif; ?>
							<?php if ($staff_state_label !== '') : ?><span><?= html_escape($staff_state_label); ?></span><?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				<p class="dl-account-section__note"><?= html_escape((string) $profile_staff_count); ?> staf terdaftar.</p>
				<?php endif; ?>
			</div>
		</section>
		<?php endif; ?>

		<footer class="dl-account-foot">
			<p><?= html_escape($profile_foot_note); ?></p>
			<button type="button" class="dl-account-logout" id="btn-logout">Keluar dari akun</button>
		</footer>
	</div>
</div>
