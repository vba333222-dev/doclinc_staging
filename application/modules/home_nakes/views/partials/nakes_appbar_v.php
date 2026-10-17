<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$nakes_photo = isset($profile['foto']) ? trim((string) $profile['foto']) : '';
$appbar_puskesmas_name = isset($nakes_puskesmas_name) ? trim((string) $nakes_puskesmas_name) : '';
$appbar_puskesmas_code = isset($nakes_puskesmas_code) ? trim((string) $nakes_puskesmas_code) : '';
$appbar_account_label = !empty($nakes_is_command_center)
	? 'Puskesmas'
	: (!empty($nakes_identity_staff['staff_profesi']) ? (string) $nakes_identity_staff['staff_profesi'] : 'Nakes');
$nakes_weak_value = static function ($value) {
	$value = trim(strip_tags((string) $value));
	return $value === '' || in_array(strtolower($value), array('n/a', 'na', '-', 'belum ditentukan', 'default'), true);
};
$nakes_puskesmas_display = !$nakes_weak_value($appbar_puskesmas_name) ? $appbar_puskesmas_name : (!$nakes_weak_value($appbar_puskesmas_code) ? $appbar_puskesmas_code : '');
if ($nakes_puskesmas_display !== '' && stripos($nakes_puskesmas_display, 'puskesmas') !== 0) {
	$nakes_puskesmas_display = 'Puskesmas ' . $nakes_puskesmas_display;
}
$nakes_header_name = !empty($nakes_is_command_center) && $nakes_puskesmas_display !== ''
	? $nakes_puskesmas_display
	: $nakes_name;
/* Subjudul tidak boleh mengulang kata yang sudah ada di dalam nama Puskesmas.
   Untuk akun command centre, $appbar_account_label bernilai "Puskesmas" dan
   $nakes_puskesmas_display bernilai "Puskesmas Cilegon", sehingga bentuk lama
   ("label · display") selalu terender sebagai "Puskesmas · Puskesmas Cilegon". */
$appbar_subtitle = $nakes_puskesmas_display;
if ($appbar_subtitle === '') {
	$appbar_subtitle = 'Belum dikonfigurasi';
} elseif (stripos($appbar_subtitle, $appbar_account_label) !== 0) {
	$appbar_subtitle = $appbar_account_label . ' · ' . $appbar_subtitle;
}
/* Subjudul hanya berguna bila menambah informasi. Pada akun command centre
   keduanya bernilai sama ("Puskesmas Cilegon"), sehingga baris kedua hanya
   mengulang nama unit persis di bawahnya. Akun nakes tetap menampilkannya
   karena di sana subjudul memuat profesi dan unit ("Dokter · Puskesmas X"). */
$appbar_show_subtitle = $appbar_subtitle !== '' && $appbar_subtitle !== $nakes_header_name;
?>
			<div class="hero bg-success p-3 overflow-hidden dl-appbar dl-nakes-appbar">
				<div class="dl-nakes-appbar-top">
					<div class="dl-nakes-header-profile">
						<?php
						$this->load->view('partials/nakes_avatar_v', array(
							'avatar_name' => $nakes_header_name,
							'avatar_photo' => !empty($nakes_is_personal) ? $nakes_photo : '',
							'avatar_user_id' => (int) $this->session->userdata('id'),
							'avatar_alt' => !empty($nakes_is_personal) ? 'Foto Nakes' : 'Identitas Puskesmas',
							'avatar_class' => 'nk-avatar--md nk-avatar--nakes',
							'avatar_icon' => !empty($nakes_is_personal) ? 'fas fa-user-md' : 'fas fa-hospital',
						));
						?>
						<div class="min-w-0">
							<strong><?= html_escape($nakes_header_name); ?></strong>
							<?php if (!empty($appbar_show_subtitle)) : ?>
								<small><?= html_escape($appbar_subtitle); ?></small>
							<?php endif; ?>
						</div>
					</div>
					<a class="dl-nakes-icon-btn position-relative" href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNotif" aria-controls="offcanvasNotif" aria-label="Notifikasi">
						<i class="bi bi-bell-fill fs-4" aria-hidden="true"></i>
						<span class="notify-number nk-notification-badge" id="badgeNotif" style="display: none;"></span>
					</a>
				</div>
				<?php if (!empty($nakes_is_command_center)) : ?>
				<nav class="dl-puskesmas-desktop-nav" aria-label="Navigasi Puskesmas">
					<a href="#beranda" onclick="showContent('beranda'); return false;">Beranda</a>
					<a href="#req_konsul" onclick="showContent('req_konsul'); return false;">Permintaan</a>
					<a href="<?= html_escape(site_url('puskesmas/operations')); ?>" class="is-active" aria-current="page">Operasional</a>
					<a href="#riwayat_konsul" onclick="showContent('riwayat_konsul'); return false;">Konsultasi</a>
					<a href="#profile" onclick="showContent('profile'); return false;">Profil</a>
				</nav>
				<?php else : ?>
				<nav class="dl-nakes-desktop-nav" aria-label="Navigasi utama nakes">
					<!-- aria-current disamakan dengan pola navigasi Puskesmas di atas.
					     Tautan ini memang bertanda is-active tetap (tidak ada skrip yang
					     memindahkannya), jadi aria-current juga tetap dan tidak menyesatkan. -->
					<a href="#beranda" class="is-active" data-nakes-nav="beranda" onclick="showContent('beranda'); return false;" aria-current="page">Beranda</a>
					<a href="#riwayat_konsul" data-nakes-nav="riwayat_konsul" onclick="showContent('riwayat_konsul'); return false;">Konsultasi</a>
					<a href="#profil" data-nakes-nav="profil" data-bs-toggle="modal" data-bs-target="#modalProfil">Profil</a>
				</nav>
				<?php endif; ?>
				<span id="address_label" class="d-none"></span>
				<input type="hidden" id="id_user" value="<?= html_escape($this->session->userdata('id')); ?>">
				<input type="hidden" id="address" placeholder="Latitude">
				<input type="hidden" id="latitude" placeholder="Latitude">
				<input type="hidden" id="longitude" placeholder="Longitude">
				<div id="map"></div>
			</div>
