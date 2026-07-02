<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$nakes_photo = isset($profile['foto']) ? trim((string) $profile['foto']) : '';
$nakes_area = isset($profile['remark']) ? trim((string) $profile['remark']) : '';
if ($nakes_area === '' || in_array(strtolower($nakes_area), array('n/a', 'na', '-', 'belum ditentukan'), true)) {
	$nakes_area = '';
}
?>
			<div class="hero bg-success p-3 overflow-hidden dl-appbar dl-nakes-appbar">
				<div class="dl-nakes-appbar-top">
					<div class="dl-nakes-header-profile">
						<?php
						$this->load->view('partials/nakes_avatar_v', array(
							'avatar_name' => $nakes_name,
							'avatar_photo' => $nakes_photo,
							'avatar_alt' => 'Foto nakes',
							'avatar_class' => 'nk-avatar--md nk-avatar--nakes',
							'avatar_icon' => 'fas fa-user-md',
						));
						?>
						<div class="min-w-0">
							<strong><?= html_escape($nakes_name); ?></strong>
							<small>Nakes aktif<?= $nakes_area !== '' ? ' · ' . html_escape($nakes_area) : ''; ?></small>
						</div>
					</div>
					<a class="dl-nakes-icon-btn position-relative" href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNotif" aria-controls="offcanvasNotif" aria-label="Notifikasi">
						<i class="bi bi-bell-fill"></i>
						<span class="notify-number" id="badgeNotif">9+</span>
					</a>
				</div>
				<span id="address_label" class="d-none"></span>
				<input type="hidden" id="id_user" value="<?= html_escape($this->session->userdata('id')); ?>">
				<input type="hidden" id="address" placeholder="Latitude">
				<input type="hidden" id="latitude" placeholder="Latitude">
				<input type="hidden" id="longitude" placeholder="Longitude">
				<div id="map"></div>
			</div>
