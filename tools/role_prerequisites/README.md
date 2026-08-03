# Role prerequisites

Boundary ini memusatkan pemeriksaan kelengkapan profil sebelum workflow sensitif dijalankan oleh Warga, Nakes personal, atau command-center Puskesmas.

Feature default-off dan hanya dapat aktif pada environment runtime yang sama dengan `DOCLINC_ROLE_PREREQUISITES_ENVIRONMENT`. Saat off, kekurangan data profil dilaporkan oleh `GET /profile/requirements` tanpa memblokir workflow lama. Actor nonaktif, wajib ganti password, role tidak didukung, dan identitas Nakes yang tidak canonical tetap ditolak.

Saat aktif, gate berlaku pada:

- pembuatan permintaan konsultasi Warga;
- penerimaan request dan koordinasi PIC Puskesmas;
- mulai panggilan oleh Nakes;
- perubahan status kunjungan;
- penyelesaian konsultasi.

Kontrak ini telah dicocokkan dengan schema staging Alibaba pada 2 Agustus 2026. Profil Warga memakai `users.nama`, `email`, `no_hp`, `alamat`, `tgl`, `gender`, `foto`, `nik`, `nomor_kk`, dan `nomor_bpjs_kis`. Nakes personal juga wajib memiliki link aktif `puskesmas_staff` dengan profesi, nomor SIP, dan NIP. Baik Nakes personal maupun command-center mensyaratkan `m_puskesmas` aktif dengan nama, alamat, latitude, dan longitude yang valid.

Respons membedakan `self_service_fields` dari `managed_fields`. Data profil pengguna dapat diperbaiki dari halaman profil; link staf dan data fasilitas harus diperbaiki pengelola sehingga API tidak memberikan CTA profil yang menyesatkan.

Kekurangan data tetap ditampilkan sebagai peringatan non-blocking ketika feature flag OFF. Command-center juga menerima ringkasan kesiapan tenant read-only untuk data fasilitas, SIP staf aktif, dan linkage akun personal. Ringkasan ini tidak mengubah row database dan tidak menggantikan validasi authoritative saat enforcement nantinya diaktifkan.

Saat enforcement aktif, seluruh penggunaan aplikasi diblokir kecuali halaman penyelesaian profil, endpoint penyimpanan profil/foto, pemeriksaan status persyaratan, pembacaan foto terotorisasi, dan logout. Password-change gate tetap berjalan lebih dahulu. Error JSON memiliki kode aman dan request ID tanpa memuat nilai NIK, KK, BPJS/KIS, atau NIP.

Jalankan kontrak source:

```bash
node tools/role_prerequisites/tests/source_test.js
```

Jalankan unit test bila PHP 8.1 tersedia:

```bash
php8.1 tools/role_prerequisites/tests/unit.php
```

Aktivasi staging dilakukan terpisah setelah schema discovery dan authenticated UAT:

```text
DOCLINC_ROLE_PREREQUISITES_ENABLED=true
DOCLINC_ROLE_PREREQUISITES_ENVIRONMENT=staging
```
