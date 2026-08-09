# Role prerequisites

Boundary ini memusatkan pemeriksaan kelengkapan profil sebelum workflow sensitif dijalankan oleh Warga, Nakes personal, atau command-center Puskesmas.

Feature default-off dan hanya dapat aktif pada environment runtime yang sama dengan `DOCLINC_ROLE_PREREQUISITES_ENVIRONMENT`. Saat off, kekurangan data profil dilaporkan tanpa memblokir workflow lama. Actor nonaktif, role tidak didukung, dan identitas Nakes yang tidak canonical tetap ditolak. Credential lifecycle diperiksa oleh policy terpisah dan hanya memblokir bila feature credential enforcement tersendiri aktif.

Saat aktif, gate berlaku pada:

- pembuatan permintaan konsultasi Warga;
- penerimaan request dan koordinasi PIC Puskesmas;
- mulai panggilan oleh Nakes;
- perubahan status kunjungan;
- penyelesaian konsultasi.

Kontrak UAT 4 Agustus 2026 mewajibkan Warga memiliki NIK, nama, tanggal lahir, gender, dan nomor telepon. KK, BPJS/JKN/Taspen, alamat, email, dan foto tidak menentukan readiness. Nakes personal wajib memiliki link canonical `puskesmas_staff.user_id`, nama, gelar, tanggal lahir, gender, profesi, SIP beserta masa berlaku, nomor telepon, Puskesmas, dan status aktif. NIP nullable dan hanya divalidasi bila diisi. State SIP dibedakan menjadi `MISSING`, `ACTIVE`, `EXPIRING`, dan `EXPIRED`; ambang `EXPIRING` berada pada `Nakes_profile_readiness_policy::DEFAULT_EXPIRING_DAYS`. Command-center tetap merupakan akun fasilitas dan tidak mewarisi persyaratan profil personal.

Respons membedakan `self_service_fields` dari `managed_fields`. Data profil pengguna dapat diperbaiki dari halaman profil; link staf dan data fasilitas harus diperbaiki pengelola sehingga API tidak memberikan CTA profil yang menyesatkan.

Kekurangan data tetap ditampilkan sebagai peringatan non-blocking ketika feature flag OFF. Command-center juga menerima ringkasan kesiapan tenant read-only untuk data fasilitas, SIP staf aktif, dan linkage akun personal. Ringkasan ini tidak mengubah row database dan tidak menggantikan validasi authoritative saat enforcement nantinya diaktifkan.

Saat enforcement aktif, Warga dan Nakes personal yang belum lengkap diblokir kecuali halaman penyelesaian profil, endpoint penyimpanan profil/foto, pemeriksaan status persyaratan, pembacaan foto terotorisasi, dan logout. Command-center hanya terkunci bila identitas fasilitas canonical atau kontak operasional minimumnya tidak valid; readiness unit/staf lainnya tetap non-blocking. Password-change gate tetap berjalan lebih dahulu. Error JSON memiliki kode aman dan request ID tanpa memuat nilai NIK, KK, BPJS/KIS, atau NIP.

Jalankan kontrak source:

```bash
node tools/role_prerequisites/tests/source_test.js
```

Jalankan unit test bila PHP 8.1 tersedia:

```bash
php8.1 tools/role_prerequisites/tests/unit.php
```

Aktivasi staging dilakukan terpisah setelah kontrak profil, schema additive,
dan authenticated UAT diterima. NIK dan data profil wajib Warga dapat diperbaiki
sendiri; gelar, SIP, masa berlaku SIP, link staf, status, dan data fasilitas
dikelola Admin Dinas Kesehatan. NIP tidak boleh dibuat untuk memenuhi gate.

```text
DOCLINC_ROLE_PREREQUISITES_ENABLED=true
DOCLINC_ROLE_PREREQUISITES_ENVIRONMENT=staging
```
