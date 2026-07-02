<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$nakes_photo = isset($profile['foto']) ? trim((string) $profile['foto']) : '';
$nakes_initials = '';
if (!empty($nakes_name)) {
	$nakes_name_parts = preg_split('/\s+/', trim((string) $nakes_name));
	foreach ($nakes_name_parts as $name_part) {
		if ($name_part === '') {
			continue;
		}
		$nakes_initials .= function_exists('mb_substr') ? mb_substr($name_part, 0, 1, 'UTF-8') : substr($name_part, 0, 1);
		if ((function_exists('mb_strlen') ? mb_strlen($nakes_initials, 'UTF-8') : strlen($nakes_initials)) >= 2) {
			break;
		}
	}
	$nakes_initials = strtoupper($nakes_initials);
}
?>
			<div class="hero bg-success p-3 overflow-hidden dl-appbar dl-nakes-appbar">
				<div class="dl-nakes-appbar-top">
					<div class="dl-nakes-header-profile">
						<div class="dl-user-avatar dl-user-avatar-nakes">
							<?php if ($nakes_photo !== '') : ?>
								<img src="<?= html_escape(doclinc_safe_profile_image_src($nakes_photo)); ?>" alt="Foto Profil">
							<?php elseif ($nakes_initials !== '') : ?>
								<span><?= html_escape($nakes_initials); ?></span>
							<?php else : ?>
								<i class="fas fa-user-md"></i>
							<?php endif; ?>
						</div>
						<div class="min-w-0">
							<strong><?= html_escape($nakes_name); ?></strong>
							<small>Nakes aktif<?= !empty($nakes_age) ? ' · ' . html_escape($nakes_age) : ''; ?></small>
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
