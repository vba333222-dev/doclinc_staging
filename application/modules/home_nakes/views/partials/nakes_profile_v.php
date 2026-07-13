<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$profile_name = (string) $this->session->userdata('nama');
$profile_photo = isset($profile['foto']) ? trim((string) $profile['foto']) : '';
$profile_birthdate = isset($profile['tgl']) ? (string) $profile['tgl'] : '';
$profile_gender = isset($profile['gender']) ? (string) $profile['gender'] : '';
$profile_phone = isset($profile['no_hp']) ? (string) $profile['no_hp'] : '';
$profile_address = isset($profile['alamat']) ? (string) $profile['alamat'] : '';
$profile_puskesmas_name = isset($nakes_puskesmas_name) ? trim((string) $nakes_puskesmas_name) : '';
$profile_puskesmas_code = isset($nakes_puskesmas_code) ? trim((string) $nakes_puskesmas_code) : '';
$profile_account_type = isset($nakes_account_type) ? (string) $nakes_account_type : 'unclassified';
$profile_is_command_center = $profile_account_type === 'command_center';
$profile_is_personal = $profile_account_type === 'personal';
$profile_identity_staff = isset($nakes_identity_staff) && is_array($nakes_identity_staff) ? $nakes_identity_staff : array();
$profile_weak_value = static function ($value) {
	$value = trim(strip_tags((string) $value));
	return $value === '' || in_array(strtolower($value), array('n/a', 'na', '-', 'belum ditentukan', 'default'), true);
};
$profile_puskesmas_display = !$profile_weak_value($profile_puskesmas_name) ? $profile_puskesmas_name : (!$profile_weak_value($profile_puskesmas_code) ? $profile_puskesmas_code : 'Belum dikonfigurasi');
$profile_staff_rows = isset($puskesmas_staff_list) && is_array($puskesmas_staff_list) ? $puskesmas_staff_list : array();
$profile_staff_count = isset($puskesmas_staff_count) ? (int) $puskesmas_staff_count : count($profile_staff_rows);
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
								'avatar_alt' => 'Foto akun Puskesmas',
								'avatar_class' => 'nk-avatar--lg nk-avatar--nakes',
								'avatar_icon' => 'fas fa-user-md',
							));
							?>
							<div class="min-w-0">
								<span><?= $profile_is_command_center ? 'Akun Puskesmas' : ($profile_is_personal ? 'Akun personal' : 'Akun perlu dicek'); ?></span>
								<strong><?= html_escape($profile_name); ?></strong>
								<small><?= $profile_is_command_center ? 'Akun Puskesmas' : ($profile_is_personal ? 'Akun personal' : 'Perlu dicek'); ?></small>
							</div>
						</div>

						<div class="nk-assignment-card">
							<div class="dl-profile-section-title">Konteks penugasan</div>
							<div class="nk-info-list">
								<div class="nk-info-row">
									<span class="nk-info-label">Puskesmas penugasan</span>
									<strong class="nk-info-value"><?= html_escape($profile_puskesmas_display); ?></strong>
								</div>
								<div class="nk-info-row">
									<span class="nk-info-label">Status akun</span>
									<strong class="nk-info-value"><?= !empty($nakes_identity_valid) ? 'Aktif' : 'Perlu pemeriksaan'; ?></strong>
								</div>
								<?php if ($profile_is_personal && !empty($profile_identity_staff['staff_profesi'])) : ?>
								<div class="nk-info-row">
									<span class="nk-info-label">Profesi staf</span>
									<strong class="nk-info-value"><?= html_escape($profile_identity_staff['staff_profesi']); ?></strong>
								</div>
								<?php endif; ?>
								<div class="nk-info-row">
									<span class="nk-info-label">Jadwal unit</span>
									<strong class="nk-info-value">Belum dikonfigurasi</strong>
								</div>
							</div>
						</div>

						<?php if ($profile_is_command_center) : ?>
						<div class="nk-staff-card nk-staff-card--profile">
							<div class="dl-profile-section-title">Staf Puskesmas</div>
							<?php if (empty($profile_staff_rows)) : ?>
								<div class="nk-staff-empty">Belum ada staf.</div>
							<?php else : ?>
								<div class="nk-staff-list">
									<?php foreach ($profile_staff_rows as $staff) :
										$staff_name = trim((string) (isset($staff->nama) ? $staff->nama : ''));
										$staff_profesi = trim((string) (isset($staff->profesi) ? $staff->profesi : ''));
										$staff_phone = trim((string) (isset($staff->no_hp) ? $staff->no_hp : ''));
										$staff_sip = trim((string) (isset($staff->nomor_sip) ? $staff->nomor_sip : ''));
									?>
										<div class="nk-staff-item">
											<div class="nk-staff-main">
												<strong><?= html_escape($staff_name !== '' ? $staff_name : 'Nama belum diisi'); ?></strong>
												<?php if ($staff_profesi !== '') : ?><span><?= html_escape($staff_profesi); ?></span><?php endif; ?>
											</div>
											<?php if ($staff_phone !== '' || $staff_sip !== '') : ?>
												<div class="nk-staff-meta">
													<?php if ($staff_phone !== '') : ?><span><?= html_escape($staff_phone); ?></span><?php endif; ?>
													<?php if ($staff_sip !== '') : ?><span>SIP <?= html_escape($staff_sip); ?></span><?php endif; ?>
												</div>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
								<div class="nk-staff-more"><?= html_escape((string) $profile_staff_count); ?> staf terdaftar.</div>
							<?php endif; ?>
						</div>
						<?php endif; ?>

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
								<i class="bi bi-pencil-fill me-2"></i>Edit profil
							</button>
							<button type="button" class="btn btn-outline-danger shadow-sm" id="btn-logout">
								<i class="bi bi-box-arrow-right me-2"></i>Keluar
							</button>
						</div>
					</div>
				</div>
