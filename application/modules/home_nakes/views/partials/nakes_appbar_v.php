<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
			<div class="hero bg-success p-3 overflow-hidden dl-appbar dl-nakes-appbar">
				<div class="dl-nakes-appbar-top">
					<div class="dl-nakes-header-profile">
						<img src="<?= html_escape(doclinc_safe_profile_image_src($profile['foto'] ?? '')); ?>" alt="Foto Profil">
						<div class="min-w-0">
							<span>Selamat bertugas</span>
							<strong><?= html_escape($nakes_name); ?></strong>
							<small><?= html_escape($nakes_age); ?> · Nakes aktif</small>
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
