<?php
/**
 * Keadaan jujur modul `landing_page`.
 *
 * Modul ini berasal dari template lain (pengelola konten profil perusahaan:
 * berita, layanan, harga, tim, portofolio) dan membaca tabel `tbl_*` yang tidak
 * ada di database DocLinc. Sebelum pagar ini dipasang, setiap halamannya berhenti
 * dengan error 500 tanpa penjelasan apa pun.
 *
 * Halaman ini tidak menampilkan data palsu dan tidak mengarang skema tabel. Yang
 * tampil hanya keadaan modul dan daftar tabel yang benar-benar hilang.
 *
 * Pilihan yang tersedia untuk pemilik produk (dulu ditulis sebagai kartu di
 * halaman; dipindah ke sini supaya tidak memenuhi layar pengguna harian):
 *   1. Bangun model konten baru untuk DocLinc, beserta halaman pengelolaannya.
 *      Ini pekerjaan fitur, bukan perbaikan.
 *   2. Biarkan dorman: modul tetap ada, halaman ini menjadi penandanya.
 *   3. Hapus modul beserta menunya bila konten profil perusahaan tidak diperlukan.
 */
$missing_tables = isset($missing_tables) && is_array($missing_tables) ? $missing_tables : array();
?>
<?php
/* Kepala halaman dari partial bersama. */
$page_head = array(
	'title' => 'Landing page',
	'sub' => 'Editor ini tidak aktif di database ini.',
	'actions' => array(
		array('kind' => 'link', 'label' => 'Kembali ke dasbor', 'href' => site_url('home'), 'icon' => 'fas fa-tachometer-alt', 'variant' => 'outline-primary'),
	),
);
$this->load->view('commons/page_head', array('page_head' => $page_head));
?>

<div class="alert alert-warning" role="status">
	<h2 class="h6 font-weight-bold">Editor landing page tidak aktif</h2>
	<p class="mb-2">
		Modul ini berasal dari template lain dan membaca
		<strong><?= number_format(count($missing_tables)); ?></strong> tabel yang belum ada di
		database DocLinc, jadi halamannya tidak bisa dibuka.
	</p>
	<details>
		<summary>Tabel yang belum ada</summary>
		<ul class="mb-0 mt-2">
			<?php foreach ($missing_tables as $table): ?>
				<li><code><?= html_escape($table); ?></code></li>
			<?php endforeach; ?>
		</ul>
	</details>
</div>

