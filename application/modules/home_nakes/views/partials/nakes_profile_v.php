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
$profile_weak_value = static function ($value) {
	$value = trim(strip_tags((string) $value));
	return $value === '' || in_array(strtolower($value), array('n/a', 'na', '-', 'belum ditentukan', 'default'), true);
};
$profile_puskesmas_display = !$profile_weak_value($profile_puskesmas_name) ? $profile_puskesmas_name : (!$profile_weak_value($profile_puskesmas_code) ? $profile_puskesmas_code : 'Belum dikonfigurasi');
$profile_staff_rows = isset($puskesmas_staff_list) && is_array($puskesmas_staff_list) ? $puskesmas_staff_list : array();
$profile_staff_count = isset($puskesmas_staff_count) ? (int) $puskesmas_staff_count : count($profile_staff_rows);
$profile_staff_account_states = array();
if (isset($puskesmas_staff_options) && is_array($puskesmas_staff_options)) {
	foreach ($puskesmas_staff_options as $staff_option) {
		$profile_staff_account_states[(int) $staff_option->staff_id] = isset($staff_option->personal_account_state) ? (string) $staff_option->personal_account_state : 'invalid';
	}
}
$profile_display_name = $profile_is_command_center ? $profile_puskesmas_display : $profile_name;
if ($profile_is_command_center) {
	$profile_rows = array(
		array('id' => 'unit_name', 'label' => 'Nama unit', 'icon' => 'fas fa-hospital', 'value' => $profile_puskesmas_display, 'type' => 'text'),
		array('id' => 'unit_code', 'label' => 'Kode Puskesmas', 'icon' => 'fas fa-id-card', 'value' => $profile_puskesmas_code, 'type' => 'text'),
		array('id' => 'email', 'label' => 'Email operasional', 'icon' => 'fas fa-envelope', 'value' => $profile_email, 'type' => 'email'),
		array('id' => 'no_hp', 'label' => 'Nomor kontak', 'icon' => 'fas fa-phone-alt', 'value' => $profile_phone, 'type' => 'text'),
	);
} else {
	$profile_rows = array(
		array('id' => 'nama_lengkap', 'label' => 'Nama lengkap', 'icon' => 'fas fa-user', 'value' => $profile_name, 'type' => 'text'),
		array('id' => 'email', 'label' => 'Email', 'icon' => 'fas fa-envelope', 'value' => $profile_email, 'type' => 'email'),
		array('id' => 'no_hp', 'label' => 'Nomor HP', 'icon' => 'fas fa-phone-alt', 'value' => $profile_phone, 'type' => 'text'),
	);
	array_splice($profile_rows, 2, 0, array(
		array('id' => 'tgl', 'label' => 'Tanggal lahir', 'icon' => 'fas fa-calendar-alt', 'value' => $profile_birthdate, 'type' => 'date'),
		array('id' => 'jk', 'label' => 'Jenis kelamin', 'icon' => 'fas fa-venus-mars', 'value' => $profile_gender, 'type' => 'text'),
	));
}
?>
				<div id="profile" class="content">
					<div class="dl-profile-stack">
						<div class="dl-profile-summary-card">
							<?php
						$this->load->view('partials/nakes_avatar_v', array(
								'avatar_name' => $profile_display_name,
								'avatar_photo' => $profile_is_personal ? $profile_photo : '',
								'avatar_user_id' => (int) $this->session->userdata('id'),
								'avatar_alt' => $profile_is_personal ? 'Foto Nakes' : 'Foto akun Puskesmas',
								'avatar_class' => 'nk-avatar--lg nk-avatar--nakes',
								'avatar_icon' => $profile_is_personal ? 'fas fa-user-md' : 'fas fa-hospital',
							));
							?>
							<div class="min-w-0">
								<span><?= $profile_is_command_center ? 'Puskesmas' : ($profile_is_personal ? 'Nakes' : 'Status belum tersedia'); ?></span>
								<strong><?= html_escape($profile_display_name); ?></strong>
								<?php
								/* Baris <small> dulu memuat string yang sama persis dengan <span>
								   di atasnya, sehingga kartu ini menulis "Puskesmas" dua kali
								   mengapit nama unit. Identitas unit yang lengkap sudah ada di
								   kartu identitas di bawahnya. */
								?>
							</div>
						</div>


						</div>

						<?php if ($profile_is_command_center) : ?>
						<div class="nk-staff-card nk-staff-card--profile">
							<h2 class="dl-profile-section-title">Staf Puskesmas</h2>
							<?php if (empty($profile_staff_rows)) : ?>
								<div class="nk-staff-empty">Belum ada staf.</div>
							<?php else : ?>
								<div class="nk-staff-list">
									<?php foreach ($profile_staff_rows as $staff) :
										$staff_name = trim((string) (isset($staff->nama) ? $staff->nama : ''));
										$staff_profesi = trim((string) (isset($staff->profesi) ? $staff->profesi : ''));
										$staff_phone = trim((string) (isset($staff->no_hp) ? $staff->no_hp : ''));
										$staff_sip = trim((string) (isset($staff->nomor_sip) ? $staff->nomor_sip : ''));
										$staff_account_state = isset($profile_staff_account_states[(int) $staff->staff_id]) ? $profile_staff_account_states[(int) $staff->staff_id] : 'invalid';
									?>
										<div class="nk-staff-item">
											<div class="nk-staff-main">
												<strong><?= html_escape($staff_name !== '' ? $staff_name : 'Nama belum diisi'); ?></strong>
												<?php if ($staff_profesi !== '') : ?><span><?= html_escape($staff_profesi); ?></span><?php endif; ?>
											</div>
											<div class="nk-staff-meta">
												<?php if ($staff_phone !== '') : ?><span><?= html_escape($staff_phone); ?></span><?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
								<div class="nk-staff-more"><?= html_escape((string) $profile_staff_count); ?> staf terdaftar.</div>
							<?php endif; ?>
						</div>
						<?php endif; ?>

						<div class="dl-profile-info-card">
							<h2 class="dl-profile-section-title"><?= $profile_is_command_center ? 'Identitas dan kontak unit' : 'Informasi kontak'; ?></h2>
							<?php foreach ($profile_rows as $profile_row) : ?>
								<div class="dl-profile-field">
									<div class="dl-profile-field-icon"><i class="<?= html_escape($profile_row['icon']); ?>"></i></div>
									<div class="dl-profile-field-body">
										<label for="<?= html_escape($profile_row['id']); ?>"><?= html_escape($profile_row['label']); ?></label>
										<input type="<?= html_escape($profile_row['type']); ?>" id="<?= html_escape($profile_row['id']); ?>" value="<?= html_escape($profile_row['value']); ?>" placeholder="<?= html_escape($profile_row['label']); ?>" readonly>
									</div>
								</div>
							<?php endforeach; ?>
							<?php if ($profile_is_personal) : ?>
							<div class="dl-profile-field dl-profile-field-address">
								<div class="dl-profile-field-icon"><i class="fas fa-map-marker-alt"></i></div>
								<div class="dl-profile-field-body">
									<label for="alamat">Alamat</label>
									<textarea id="alamat" placeholder="Alamat" readonly><?= html_escape($profile_address); ?></textarea>
								</div>
							</div>
							<?php elseif ($profile_is_command_center) : ?>
							<div class="dl-profile-field dl-profile-field-address">
								<div class="dl-profile-field-icon"><i class="fas fa-map-marker-alt"></i></div>
								<div class="dl-profile-field-body">
									<label for="unit_address">Alamat layanan</label>
									<textarea id="unit_address" placeholder="Dikelola Administrator Dinas Kesehatan" readonly><?= html_escape($profile_puskesmas_address !== '' ? $profile_puskesmas_address : 'Belum dilengkapi oleh Administrator Dinas Kesehatan'); ?></textarea>
								</div>
							</div>
							<?php endif; ?>
						</div>

						<div class="dl-profile-actions">
							<button type="button" class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#modalProfil">
								Edit
							</button>
							<button type="button" class="btn btn-outline-danger shadow-sm" id="btn-logout">
								Keluar
							</button>
						</div>
					</div>
				</div>
