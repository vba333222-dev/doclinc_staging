<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
	<div class="nav-bottom-wrapper dl-bottom-nav" id="nav-bottom-wrapper">
		<nav class="dl-bottom-nav-menu menu" aria-label="Navigasi nakes">
			<a href="#beranda" id="beranda-tab" class="menu-item <?= (isset($nakes_initial_section) && $nakes_initial_section === 'operasional') ? '' : 'active'; ?>" onclick="showContent('beranda')" aria-label="Beranda">
				<img class="dl-semantic-icon dl-semantic-icon--lg" src="<?= html_escape(doclinc_ui_asset('home')); ?>" alt="" aria-hidden="true">
				<span>Beranda</span>
			</a>
			<?php if (!empty($nakes_is_command_center)) : ?>
			<a href="#req_konsul" id="req_konsul-tab" class="menu-item" onclick="showContent('req_konsul')" aria-label="Permintaan">
				<img class="dl-semantic-icon dl-semantic-icon--lg" src="<?= html_escape(doclinc_ui_asset('nakes')); ?>" alt="" aria-hidden="true">
				<span>Permintaan</span>
			</a>
			<?php if (!empty($puskesmas_operations_enabled)) : ?>
			<a href="<?= html_escape(site_url('puskesmas/operations')); ?>" id="operasional-tab" class="menu-item <?= (isset($nakes_initial_section) && $nakes_initial_section === 'operasional') ? 'active' : ''; ?>" onclick="event.preventDefault(); navigateNakesSection('operasional', this.href)" aria-label="Operasional Puskesmas">
				<img class="dl-semantic-icon dl-semantic-icon--lg" src="<?= html_escape(doclinc_ui_asset('operations')); ?>" alt="" aria-hidden="true">
				<span>Operasional</span>
			</a>
			<?php endif; ?>
			<?php endif; ?>
			<?php if (empty($nakes_is_unclassified)) : ?>
			<a href="#riwayat_konsul" id="riwayat_konsul-tab" class="menu-item" onclick="showContent('riwayat_konsul')" aria-label="Riwayat">
				<i class="fas fa-history"></i>
				<span>Riwayat</span>
			</a>
			<?php endif; ?>
			<a href="#profile" id="profile-tab" class="menu-item" onclick="event.preventDefault(); showContent('profile')" aria-controls="profile" aria-label="Profil">
				<i class="fas fa-user"></i>
				<span>Profil</span>
			</a>
		</nav>
	</div>
