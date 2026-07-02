<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$profile_name = (string) $this->session->userdata('nama');
$profile_photo = isset($profile['foto']) ? trim((string) $profile['foto']) : '';
$profile_birthdate = isset($profile['tgl']) ? (string) $profile['tgl'] : '';
$profile_gender = isset($profile['gender']) ? (string) $profile['gender'] : '';
$profile_phone = isset($profile['no_hp']) ? (string) $profile['no_hp'] : '';
$profile_address = isset($profile['alamat']) ? (string) $profile['alamat'] : '';
$profile_puskesmas_name = isset($profile['assigned_puskesmas_name']) ? trim((string) $profile['assigned_puskesmas_name']) : '';
$profile_puskesmas_code = isset($profile['remark']) ? trim((string) $profile['remark']) : trim((string) $this->session->userdata('remark'));
$profile_weak_value = static function ($value) {
	$value = trim(strip_tags((string) $value));
	return $value === '' || in_array(strtolower($value), array('n/a', 'na', '-', 'belum ditentukan'), true);
};
$profile_puskesmas_display = !$profile_weak_value($profile_puskesmas_name) ? $profile_puskesmas_name : (!$profile_weak_value($profile_puskesmas_code) ? $profile_puskesmas_code : 'Belum dikonfigurasi');
$profile_rows = array(
	array('id' => 'nama_lengkap', 'label' => 'Nama lengkap', 'icon' => 'bi bi-person-fill', 'value' => $profile_name, 'type' => 'text'),
	array('id' => 'tgl', 'label' => 'Tanggal lahir', 'icon' => 'bi bi-calendar-event-fill', 'value' => $profile_birthdate, 'type' => 'date'),
	array('id' => 'jk', 'label' => 'Jenis kelamin', 'icon' => 'bi bi-gender-ambiguous', 'value' => $profile_gender, 'type' => 'text'),
	array('id' => 'no_hp', 'label' => 'Nomor HP', 'icon' => 'bi bi-telephone-fill', 'value' => $profile_phone, 'type' => 'text'),
);
?>
				<div id="profile" class="content">
					<div class="dl-profile-stack">
						<div class="dl-profile-summary-card">
							<?php
							$this->load->view('partials/nakes_avatar_v', array(
								'avatar_name' => $profile_name,
								'avatar_photo' => $profile_photo,
								'avatar_alt' => 'Foto nakes',
								'avatar_class' => 'nk-avatar--lg nk-avatar--nakes',
								'avatar_icon' => 'fas fa-user-md',
							));
							?>
							<div class="min-w-0">
								<span>Profil nakes</span>
								<strong><?= html_escape($profile_name); ?></strong>
								<small>Nakes aktif</small>
							</div>
						</div>

						<div class="nk-assignment-card">
							<div class="dl-profile-section-title">Konteks Penugasan</div>
							<div class="nk-info-list">
								<div class="nk-info-row">
									<span class="nk-info-label">Puskesmas Penugasan</span>
									<strong class="nk-info-value"><?= html_escape($profile_puskesmas_display); ?></strong>
								</div>
								<div class="nk-info-row">
									<span class="nk-info-label">Status Nakes</span>
									<strong class="nk-info-value">Aktif</strong>
								</div>
								<div class="nk-info-row">
									<span class="nk-info-label">Shift</span>
									<strong class="nk-info-value">Belum dikonfigurasi</strong>
								</div>
							</div>
						</div>

						<div class="dl-profile-info-card">
							<div class="dl-profile-section-title">Informasi kontak</div>
							<?php foreach ($profile_rows as $profile_row) : ?>
								<div class="dl-profile-field">
									<div class="dl-profile-field-icon"><i class="<?= html_escape($profile_row['icon']); ?>"></i></div>
									<div class="dl-profile-field-body">
										<label for="<?= html_escape($profile_row['id']); ?>"><?= html_escape($profile_row['label']); ?></label>
										<input type="<?= html_escape($profile_row['type']); ?>" id="<?= html_escape($profile_row['id']); ?>" value="<?= html_escape($profile_row['value']); ?>" placeholder="<?= html_escape($profile_row['label']); ?>" readonly>
									</div>
								</div>
							<?php endforeach; ?>
							<div class="dl-profile-field dl-profile-field-address">
								<div class="dl-profile-field-icon"><i class="bi bi-geo-alt-fill"></i></div>
								<div class="dl-profile-field-body">
									<label for="alamat">Alamat</label>
									<textarea id="alamat" placeholder="Alamat" readonly><?= html_escape($profile_address); ?></textarea>
								</div>
							</div>
						</div>

						<div class="dl-profile-actions">
							<button type="button" class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#modalProfil">
								<i class="bi bi-pencil-fill me-2"></i>Edit Profil
							</button>
							<button type="button" class="btn btn-outline-danger shadow-sm" id="btn-logout">
								<i class="bi bi-box-arrow-right me-2"></i>Logout
							</button>
						</div>
					</div>
				</div>
