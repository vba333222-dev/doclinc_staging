<?php
/* Kepala halaman dari partial bersama. Tidak ada keterangan tambahan yang
   dikarang di sini: satu-satunya kalimat penjelas halaman ini sudah ada di kartu
   di bawah, dan mengulangnya di kepala halaman hanya menambah teks. */
$page_head = array('title' => 'Tindakan');
$this->load->view('commons/page_head', array('page_head' => $page_head));
?>
<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h6 font-weight-bold mb-2">Data klinis tidak dapat diubah di sini.</h2>
        <p class="text-muted mb-0">Perubahan dilakukan oleh tenaga kesehatan yang menangani pasien.</p>
    </div>
</div>
