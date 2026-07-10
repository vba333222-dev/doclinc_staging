<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$nakes_photo = isset($profile['foto']) ? trim((string) $profile['foto']) : '';
$appbar_puskesmas_name = isset($nakes_puskesmas_name) ? trim((string) $nakes_puskesmas_name) : '';
$appbar_puskesmas_code = isset($nakes_puskesmas_code) ? trim((string) $nakes_puskesmas_code) : '';
$appbar_account_label = !empty($nakes_is_command_center)
	? 'Akun Koordinasi Puskesmas'
	: (!empty($nakes_is_personal) ? 'Akun Personal Nakes' : 'Akun Nakes');
$nakes_weak_value = static function ($value) {
	$value = trim(strip_tags((string) $value));
	return $value === '' || in_array(strtolower($value), array('n/a', 'na', '-', 'belum ditentukan', 'default'), true);
};
$nakes_puskesmas_display = !$nakes_weak_value($appbar_puskesmas_name) ? $appbar_puskesmas_name : (!$nakes_weak_value($appbar_puskesmas_code) ? $appbar_puskesmas_code : '');
if ($nakes_puskesmas_display !== '' && stripos($nakes_puskesmas_display, 'puskesmas') !== 0) {
	$nakes_puskesmas_display = 'Puskesmas ' . $nakes_puskesmas_display;
}
?>
			<div class="hero bg-success p-3 overflow-hidden dl-appbar dl-nakes-appbar">
				<div class="dl-nakes-appbar-top">
					<div class="dl-nakes-header-profile">
						<?php
						$this->load->view('partials/nakes_avatar_v', array(
							'avatar_name' => $nakes_name,
							'avatar_photo' => $nakes_photo,
							'avatar_alt' => 'Foto akun Puskesmas',
							'avatar_class' => 'nk-avatar--md nk-avatar--nakes',
							'avatar_icon' => 'fas fa-user-md',
						));
						?>
						<div class="min-w-0">
							<strong><?= html_escape($nakes_name); ?></strong>
							<small><?= html_escape($appbar_account_label); ?><?= $nakes_puskesmas_display !== '' ? ' · ' . html_escape($nakes_puskesmas_display) : ' · Belum dikonfigurasi'; ?></small>
						</div>
					</div>
					<a class="dl-nakes-icon-btn position-relative" href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNotif" aria-controls="offcanvasNotif" aria-label="Notifikasi">
						<i class="bi bi-bell-fill"></i>
						<span class="notify-number nk-notification-badge" id="badgeNotif" style="display: none;"></span>
					</a>
				</div>
				<span id="address_label" class="d-none"></span>
				<input type="hidden" id="id_user" value="<?= html_escape($this->session->userdata('id')); ?>">
				<input type="hidden" id="address" placeholder="Latitude">
				<input type="hidden" id="latitude" placeholder="Latitude">
				<input type="hidden" id="longitude" placeholder="Longitude">
				<div id="map"></div>
			</div>
