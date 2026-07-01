<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
	<div class="nav-bottom-wrapper shadow-lg rounded-top-4 dl-bottom-nav" id="nav-bottom-wrapper">
		<div class="container-fluid px-0">
			<div class="row g-0 text-center p-2 menu animate__animated animate__slideInUp animate__faster">
				<a href="#" id="beranda-tab" class="col menu-item active" onclick="showContent('beranda')" aria-label="Home">
					<i class="fas fa-home fs-4"></i>
					<span class="d-block small">Beranda</span>
				</a>
				<a href="#req_konsul" id="req_konsul-tab" class="col menu-item" onclick="showContent('req_konsul')" aria-label="Requests">
					<i class="fas fa-user-md fs-4"></i>
					<span class="d-block small">Permintaan</span>
				</a>
				<a href="#riwayat_konsul" id="riwayat_konsul-tab" class="col menu-item" onclick="showContent('riwayat_konsul')" aria-label="History">
					<i class="fas fa-history fs-4"></i>
					<span class="d-block small">Riwayat</span>
				</a>
				<a href="#" id="profile-tab" class="col menu-item" onclick="showContent('profile')" aria-label="Profile">
					<i class="fas fa-user fs-4"></i>
					<span class="d-block small">Profil</span>
				</a>
			</div>
		</div>
	</div>
