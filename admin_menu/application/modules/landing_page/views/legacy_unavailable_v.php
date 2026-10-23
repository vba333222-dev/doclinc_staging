<?php
/**
 * Keadaan jujur modul `landing_page`.
 *
 * Modul ini berasal dari template lain (pengelola konten profil perusahaan:
 * berita, layanan, harga, tim, portofolio) dan membaca tabel `tbl_*` yang tidak
 * ada di database DocLinc. Sebelum pagar ini dipasang, setiap halamannya berhenti
 * dengan error 500 tanpa penjelasan apa pun.
 *
 * Halaman ini TIDAK menampilkan data palsu dan tidak mengarang skema tabel:
 * yang ditampilkan adalah daftar tabel yang benar-benar hilang, sehingga
 * keputusan berikutnya (bangun model konten baru, dorman, atau hapus) bisa
 * diambil dengan informasi yang utuh.
 */
$missing_tables = isset($missing_tables) && is_array($missing_tables) ? $missing_tables : array();
?>
<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-4 px-4 mt-n4 mx-n4 you-are-here doclinc-page-header">
	<div>
		<h1 class="h3 mb-1 font-weight-bold doclinc-page-title">
			<i class="fas fa-globe" aria-hidden="true"></i> Landing page
		</h1>
		<p class="mb-0 doclinc-page-subtitle">
			Modul ini belum dapat dipakai pada database ini.
		</p>
	</div>
	<a href="<?= html_escape(site_url('home')); ?>" class="btn btn-outline-primary btn-sm shadow-sm">
		<i class="fas fa-tachometer-alt" aria-hidden="true"></i> Kembali ke dasbor
	</a>
</div>

<div class="alert alert-warning" role="status">
	<h2 class="h6 font-weight-bold">Mengapa editor landing page tidak tampil</h2>
	<p>
		Modul <code>landing_page</code> di panel ini adalah pengelola konten profil perusahaan dari
		template lain: berita, layanan, harga, tim, dan portofolio. Untuk itu ia membaca
		<strong><?= number_format(count($missing_tables)); ?></strong> tabel <code>tbl_*</code> yang tidak ada di
		database DocLinc, sehingga setiap halamannya gagal sebelum bisa menampilkan apa pun.
	</p>
	<p class="mb-1">Tabel yang dibutuhkan dan belum ada:</p>
	<ul class="mb-0">
		<?php foreach ($missing_tables as $table): ?>
			<li><code><?= html_escape($table); ?></code></li>
		<?php endforeach; ?>
	</ul>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header">
		<h2 class="h6 mb-0 font-weight-bold">Pilihan yang tersedia</h2>
	</div>
	<div class="card-body">
		<ol class="mb-3">
			<li class="mb-2">
				<strong>Bangun model konten baru untuk DocLinc.</strong>
				Artinya membuat tabel baru yang dirancang untuk kebutuhan DocLinc (bukan menebak skema
				template lama) beserta halaman pengelolaannya. Ini pekerjaan fitur, bukan perbaikan.
			</li>
			<li class="mb-2">
				<strong>Biarkan dorman.</strong>
				Modul tetap ada tetapi tidak dipakai; halaman ini menjadi penanda keadaannya. Tidak ada
				risiko dan tidak ada pekerjaan tambahan.
			</li>
			<li>
				<strong>Hapus modul.</strong>
				Menghilangkan menu dan direktori <code>landing_page</code> dari panel. Pilih ini bila
				konten profil perusahaan memang tidak diperlukan.
			</li>
		</ol>
		<p class="mb-0 text-muted">
			Modul yang bergantung pada tabel yang tidak ada tidak boleh dipaksa jalan: hasilnya bukan
			konten, melainkan halaman kosong atau error. Karena itu tidak ada tabel yang dibuat di sini
			tanpa keputusan pemilik produk.
		</p>
	</div>
</div>
