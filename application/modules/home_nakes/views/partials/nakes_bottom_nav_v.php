<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* Setiap ikon navigasi bawah memakai satu keluarga ikon saja: Font Awesome,
   sama persis dengan navigasi bawah beranda warga (home_v.php memakai
   fas fa-home / fa-user-md / fa-file-medical / fa-user) yang menjadi acuan
   desain aplikasi ini.

   Sebelumnya tiga item memakai berkas gambar dari icon-pack dan dua item
   memakai glyph, sehingga satu bilah navigasi memuat dua keluarga ikon yang
   berbeda sekaligus:
     - bentuk : gambar SVG penuh warna vs glyph monokrom
     - ukuran : .dl-semantic-icon--lg mematok 24px, sedangkan glyph diatur
                .dl-bottom-nav .menu > .menu-item i sebesar 20px
     - warna  : gambar SVG tidak mewarisi `currentColor`, jadi tetap hijau saat
                item tidak aktif, sedangkan glyph ikut berubah abu-abu/hijau
   Glyph di bawah diambil dari kosakata beranda warga; label yang sama
   ("Beranda", "Konsultasi", "Profil") memakai glyph yang sama persis dengan
   beranda warga. */
?>
	<div class="nav-bottom-wrapper dl-bottom-nav dl-puskesmas-mobile-nav" id="nav-bottom-wrapper">
		<nav class="dl-bottom-nav-menu menu" aria-label="Navigasi nakes">
			<a href="#beranda" id="beranda-tab" class="menu-item <?= (isset($nakes_initial_section) && $nakes_initial_section === 'operasional') ? '' : 'active'; ?>" onclick="showContent('beranda')" aria-label="Beranda" aria-current="<?= (isset($nakes_initial_section) && $nakes_initial_section === 'operasional') ? 'false' : 'true'; ?>">
				<i class="fas fa-home" aria-hidden="true"></i>
				<span>Beranda</span>
			</a>
			<?php if (!empty($nakes_is_command_center)) : ?>
			<a href="#req_konsul" id="req_konsul-tab" class="menu-item" onclick="showContent('req_konsul')" aria-label="Permintaan">
				<i class="fas fa-clipboard-list" aria-hidden="true"></i>
				<span>Permintaan</span>
			</a>
			<?php if (!empty($puskesmas_operations_enabled)) : ?>
			<a href="<?= html_escape(site_url('puskesmas/operations')); ?>" id="operasional-tab" class="menu-item <?= (isset($nakes_initial_section) && $nakes_initial_section === 'operasional') ? 'active' : ''; ?>" onclick="event.preventDefault(); navigateNakesSection('operasional', this.href)" aria-label="Operasional Puskesmas" aria-current="<?= (isset($nakes_initial_section) && $nakes_initial_section === 'operasional') ? 'true' : 'false'; ?>">
				<i class="fas fa-briefcase-medical" aria-hidden="true"></i>
				<span>Operasional</span>
			</a>
			<?php endif; ?>
			<?php endif; ?>
			<?php if (empty($nakes_is_unclassified)) : ?>
			<a href="#riwayat_konsul" id="riwayat_konsul-tab" class="menu-item" onclick="showContent('riwayat_konsul')" aria-label="Konsultasi">
				<i class="fas fa-user-md" aria-hidden="true"></i>
				<span>Konsultasi</span>
			</a>
			<?php endif; ?>
			<a href="#profile" id="profile-tab" class="menu-item" onclick="event.preventDefault(); showContent('profile')" aria-controls="profile" aria-label="Profil">
				<i class="fas fa-user" aria-hidden="true"></i>
				<span>Profil</span>
			</a>
		</nav>
	</div>
