<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
	<div class="nav-bottom-wrapper dl-bottom-nav" id="nav-bottom-wrapper">
		<nav class="dl-bottom-nav-menu menu" aria-label="Navigasi nakes">
			<a href="#" id="beranda-tab" class="menu-item active" onclick="showContent('beranda')" aria-label="Home">
				<i class="fas fa-home"></i>
				<span>Beranda</span>
			</a>
			<?php if (!empty($nakes_is_command_center)) : ?>
			<a href="#req_konsul" id="req_konsul-tab" class="menu-item" onclick="showContent('req_konsul')" aria-label="Requests">
				<i class="fas fa-user-md"></i>
				<span>Permintaan</span>
			</a>
			<?php endif; ?>
			<?php if (empty($nakes_is_unclassified)) : ?>
			<a href="#riwayat_konsul" id="riwayat_konsul-tab" class="menu-item" onclick="showContent('riwayat_konsul')" aria-label="History">
				<i class="fas fa-history"></i>
				<span>Riwayat</span>
			</a>
			<?php endif; ?>
			<a href="#" id="profile-tab" class="menu-item" onclick="showContent('profile')" aria-label="Profile">
				<i class="fas fa-user"></i>
				<span>Profil</span>
			</a>
		</nav>
	</div>
