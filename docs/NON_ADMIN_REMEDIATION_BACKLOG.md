# Doclinc Non-Admin Remediation Backlog

Status: **OPEN / REQUIRED**
Baseline audit: `fix/config-session` at `f96b6e3aa453a95c2fe03f262030708350e39ff6`
Scope: Public/Guest, Warga, Nakes personal, dan command-center Puskesmas. Admin tidak termasuk.

Dokumen ini adalah gate pengembangan, bukan daftar saran opsional. Item tidak boleh diberi status selesai hanya karena tampilan tersedia atau happy path berjalan. Status `DONE` membutuhkan source remediation, regression test, role/authorization test, dan UAT sesuai acceptance criteria.

## Status definitions

- `OPEN`: belum dikerjakan atau belum dibuktikan.
- `IN PROGRESS`: implementasi berjalan, belum lulus seluruh gate.
- `BLOCKED`: membutuhkan keputusan produk, data, infrastruktur, atau akses UAT.
- `DONE`: implementasi dan seluruh acceptance criteria telah lulus.
- `DEFERRED`: hanya boleh digunakan dengan alasan, owner, risiko, dan target rilis yang tertulis.

## P0 — Security and access foundation

### SEC-01 — Session cookie dan CSRF hardening

Status: `IN PROGRESS`

Masalah:

- CSRF global sudah diaktifkan secara lokal pada batch 2 tanpa URI exemption.
- Bridge browser memasang token framework ke same-origin `fetch`, XHR, dan form POST; cross-origin request tidak menerima token.
- Session cookie sudah memakai HTTPS-aware `Secure`, `HttpOnly`, dan destructive rotation pada batch 1.
- Token tidak diregenerasi per request agar polling/tab AJAX paralel tidak saling membatalkan.
- SameSite `Lax` sudah diterapkan lokal pada cookie session/aplikasi dan cookie CSRF; runtime header verification dan negative integration test terhadap database disposable belum selesai.

Acceptance criteria:

- Semua mutasi state memakai POST/PUT/PATCH/DELETE dan lolos CSRF validation.
- Session cookie menggunakan `Secure`, `HttpOnly`, dan SameSite yang terdokumentasi.
- Session dirotasi saat login/perubahan privilege dan dicabut saat akun dinonaktifkan.
- Negative test membuktikan missing/invalid CSRF ditolak tanpa perubahan database.

### AUTH-01 — Actor status dan role prerequisites

Status: `IN PROGRESS`

Masalah:

- Login seluruh role dan session aktif sudah direvalidasi terhadap role/status database pada batch 1.
- Batch lokal menambahkan `Role_prerequisite_service`, endpoint status privat, safe error contract, banner perbaikan profil, dan gate default-off pada sepuluh entry point workflow sensitif.
- Gate sengaja tidak memblokir pembatalan, chat, riwayat, atau perbaikan profil agar pengguna tidak terjebak.
- Discovery schema staging read-only pada MariaDB 10.11 sudah memverifikasi field canonical `users`, `puskesmas_staff`, serta alamat/latitude/longitude `m_puskesmas`; tidak ada pembacaan row pasien atau write.
- Prasyarat kini membedakan data yang dapat diperbaiki sendiri (profil akun/foto) dari data yang dikelola pengelola (SIP, profesi, linkage staf, dan identitas/lokasi Puskesmas), sehingga CTA tidak mengarahkan pengguna ke form yang tidak mampu memperbaiki masalah.
- Warga dapat memperbaiki nama, email, nomor HP, tanggal lahir, gender, dan alamat melalui endpoint POST tervalidasi. Form command-center hanya meminta field akun bersama, sedangkan field personal tetap khusus Nakes personal.
- Aktivasi gate tetap menunggu audit kelengkapan agregat tanpa PII dan authenticated UAT; schema presence saja tidak membuktikan seluruh row operasional sudah lengkap.

Acceptance criteria:

- Anonymous, inactive, `must_change_password`, role mismatch, dan identity tidak lengkap ditolak konsisten.
- Setiap role mempunyai schema prerequisite yang eksplisit.
- Response HTML dan JSON mempunyai error code yang stabil dan ramah pengguna.
- Akun yang dinonaktifkan tidak dapat memakai session lama.
- Test mencakup seluruh public action penting per role.

### XSS-01 — Stored/output XSS pada profil dan view legacy

Status: `IN PROGRESS`

Masalah:

- Profil Nakes aktif sudah mendapat escaping, safe JavaScript serialization, role guard, dan validasi field pada batch 1.
- View legacy dan output context lain belum seluruhnya ditutup oleh dynamic XSS regression.

Acceptance criteria:

- Semua HTML output memakai context-appropriate escaping.
- Nilai yang masuk JavaScript diserialisasi dengan JSON encoder, bukan interpolasi string.
- Nama, telepon, alamat, tanggal lahir, gender, dan media profile divalidasi server-side.
- Dynamic stored-XSS regression test lulus.

### ROUTE-01 — Tutup route legacy dan implicit mutation surface

Status: `IN PROGRESS`

Masalah:

- Controller Firebase/notification lama dan sender token query sudah menjadi explicit 404 tombstone pada batch 1.
- Mutasi/status LiveKit sudah POST-only dan incoming GET dibuat read-only pada batch 1.
- Logout, signup, chat mutation/upload, notification mark-read, request mutation, presence heartbeat, dan profile update sudah POST-only pada batch 2.
- Chat message/list notification/incoming-call/clinical suggestion/snapshot dibuat explicit GET-only pada batch 2.
- Duplicate consultation, legacy location, legacy chat-upload, notification sender, dan service-worker method sudah menjadi 404 tombstone.
- Default CodeIgniter welcome page sudah menjadi 404; tiga migration controller terbukti CLI-only.
- Seluruh 18 controller non-Admin dan 86 public action saat ini terkunci oleh exact source manifest; controller/action baru akan menggagalkan route inventory test.
- CI3 implicit routing masih perlu diuji terhadap bootstrap/router HTTP sebenarnya sebelum gate dinyatakan selesai.

Acceptance criteria:

- Endpoint Firebase/test/service-worker legacy dihapus atau dilindungi explicit authorization.
- Start/answer/reject/end LiveKit hanya POST dengan CSRF.
- GET status/polling benar-benar read-only.
- Route non-admin menggunakan explicit allowlist dan method contract.
- Route inventory test menolak method legacy yang tidak diizinkan.

## P1 — Data, privacy, and functional correctness

### DATA-01 — Data wajib dan foto asli per role

Status: `IN PROGRESS`

Masalah:

- Profil Nakes dan pasien belum mempunyai kelengkapan data minimum yang konsisten.
- Media profil privat batch lokal sudah menambah upload Warga/Nakes, validasi MIME berbasis isi file, storage di luar web root, endpoint baca terotorisasi, dan fallback lokal setelah authorization. Rollout storage/Nginx serta dynamic HTTP authorization regression belum dijalankan.
- Dashboard, riwayat, chat, dan call sudah memakai endpoint foto berdasarkan subject identity. Assignment/monitoring yang belum membawa subject photo masih perlu ditutup saat read model datanya tersedia.
- Gate terpusat default-off sudah tersedia untuk pembuatan request, accept, assign/clear PIC, penerbitan token/mulai panggilan, heartbeat Nakes, pembaruan lokasi/status kunjungan, dan completion. Rollout/activation belum dilakukan.

Required role data minimum:

- Warga: identitas dasar, kontak terverifikasi, alamat, lokasi layanan yang valid, dan foto profil aktual bila diwajibkan kebijakan produk.
- Nakes personal: identitas, foto asli, Puskesmas terhubung, status aktif, profesi/kompetensi, nomor registrasi bila tersedia, dan link staff canonical.
- Command-center Puskesmas: identitas fasilitas, kode canonical, alamat, koordinat, status aktif, akun penanggung jawab, dan branding/foto fasilitas.

Acceptance criteria:

- Satu `RolePrerequisiteService` atau boundary ekuivalen mengembalikan field yang kurang dan safe error code.
- Foto disimpan privat/terkontrol, MIME tervalidasi, dan ditampilkan konsisten pada dashboard, detail request, chat, call, assignment, history, dan monitoring.
- Placeholder hanya digunakan saat foto memang belum tersedia dan diberi label yang jujur.
- Missing prerequisite mencegah aksi sensitif dengan CTA yang mengarah tepat ke perbaikan data.
- Authorization test membuktikan foto/data lintas user atau tenant tidak bocor.

### UX-LOCATION-01 — Alamat manusiawi, bukan koordinat mentah

Status: `IN PROGRESS`

Masalah:

- UI masih menampilkan `latitude`/`longitude` dan bahasa teknis sistem.
- Lokasi seharusnya ditampilkan sebagai alamat presisi yang mudah dipahami.
- Dashboard Warga kini memakai presenter alamat terpusat dengan Mapbox/Google, timeout, coalescing, cache, rate limit, fallback manusiawi, dan tautan peta. Koordinat canonical tetap berada pada field tersembunyi untuk routing/evidence dan tidak lagi menjadi fallback teks UI.
- Panel monitoring Nakes/Puskesmas kini mempresentasikan alamat pasien dan posisi Nakes melalui presenter yang sama, dengan tautan peta serta fallback aman. Authenticated Android UAT masih harus diselesaikan sebelum status dapat menjadi `DONE`.

Acceptance criteria:

- UI utama menampilkan nama jalan/alamat, kelurahan, kecamatan, dan label jarak/freshness yang manusiawi.
- Koordinat mentah hanya tersedia di panel teknis/audit untuk role berwenang.
- Reverse geocoding mempunyai timeout, cache, fallback, rate limit, dan provider abstraction.
- Bila alamat gagal ditemukan, tampilkan pesan aman seperti "Alamat belum dapat dikenali" serta opsi buka peta; jangan tampilkan error provider.
- Alamat hasil geocoding tidak digunakan sebagai bukti keamanan tunggal; koordinat canonical tetap tersimpan untuk perhitungan.

### UX-CLINICAL-01 — Bersihkan label suggestion ICD-10, diagnosis, dan obat

Status: `IN PROGRESS`

Masalah:

- Presenter lokal batch 3 sudah menghapus provenance/internal source dari response browser dan menolak label bermerek internal.
- Sumber lama `getICD_json` dikonfirmasi berasal dari konversi data WHO ICD-10. Data itu tetap dipakai melalui importer dan endpoint canonical `/clinical-suggestions`; endpoint lama tetap ditombstone agar tidak ada duplikasi auth, ranking, atau format respons.
- Diagnosis hanya dipresentasikan dari dataset WHO ICD-10 2019. Pencarian menerima kode, judul resmi WHO, dan alias Indonesia; alias hanya membantu pencarian, sedangkan kode dan judul WHO tetap terlihat dan menjadi nilai canonical yang dipilih.
- Obat hanya dipresentasikan dari dataset canonical `21_master_obat_fornas.json`; nama Fornas menjadi display dan kode internal `EFORNAS_*` tidak dirender.
- Keluhan dan gejala memakai human label sebagai display; kode internal tidak dirender.
- Ketika fitur suggestion canonical belum aktif, Nakes tetap dapat memasukkan diagnosis sebagai free text; aplikasi tidak menggantinya dengan istilah produk Doclinc/Doklinc/DocLink.
- Runtime PHP 8.1, package-root regression, dan authenticated browser UAT belum dijalankan untuk perubahan ini.
- Pengguna membutuhkan nama klinis yang ringkas, misalnya `Demam`, `Asma`, atau `Paracetamol 500 mg`.
- Audit intake `v1.zip` (SHA-256 `0811dfd62270e22ac6b3bee588419629f082a9aecc6accbb3f4d6b0673fc61b1`) menemukan 12.246 row diagnosis WHO, 10.658 row selectable, 53 alias Indonesia, dan 663 nama obat e-Fornas. Package menyatakan `production_ready=false` dan `runtime_enabled=false`.
- `SHA256SUMS.txt` pada ZIP menyebut `ICD10_SOURCE_REFERENCE.json` dan `schema_mysql_icd10_reference.sql`, tetapi kedua file tidak ada di archive. Package tidak boleh di-import sampai archive diregenerasi dengan inventory/checksum yang konsisten.

Acceptance criteria:

- Hasil diagnosis menampilkan istilah yang dapat dikenali pengguna serta judul resmi WHO, tanpa menyamarkan alias lokal sebagai judul resmi WHO.
- Kode ICD-10 tampil sebagai metadata dan nilai canonical, bukan prefix produk/internal.
- Nama obat canonical berasal dari Fornas dan dapat dicari dengan nama Indonesia/internasional tanpa menampilkan kode internal.
- Internal source/package identifier tidak pernah tampil kepada pengguna.
- Free-text fallback tetap tersedia dan tidak mengubah makna klinis.
- Regression test memverifikasi display label terpisah dari canonical code/value.

### UX-CLINICAL-02 — Structured therapy berbasis Fornas

Status: `OPEN / GOVERNANCE REQUIRED`

Masalah:

- Snapshot Fornas pada package saat ini hanya berisi 663 nama obat canonical.
- Dataset belum menyediakan sediaan, kekuatan, satuan, dosis, frekuensi, durasi, rute, restriksi Fornas, kontraindikasi, atau kewenangan resep yang tervalidasi.
- Nama obat canonical membantu pencarian, tetapi belum cukup untuk menyatakan terapi tepat atau prescription-ready.

Acceptance criteria:

- Versi/sumber resmi Fornas dikunci dan dapat diaudit; pembaruan memakai batch idempotent serta rollback.
- Obat dipilih sebagai structured identity; sediaan, kekuatan, dosis, frekuensi, durasi, rute, dan instruksi disimpan pada field terpisah.
- Restriksi Fornas, alergi, duplikasi bahan aktif, interaksi, kehamilan/menyusui, dan kewenangan prescriber menghasilkan decision support yang dapat dijelaskan, bukan auto-prescription.
- Nakes tetap membuat dan mengonfirmasi keputusan terapi; aplikasi tidak menyimpulkan diagnosis atau meresepkan obat secara otomatis.
- Free-text dicatat sebagai non-canonical dan tidak diam-diam dianggap sebagai item Fornas.
- Clinical-coder/pharmacy review dan authenticated UAT wajib sebelum production enablement.

### NAV-01 — Route tujuan dan return-path yang sesuai konteks

Status: `IN PROGRESS`

Masalah:

- Membuka riwayat konsultasi dapat kembali ke dashboard.
- Selesai chat mengembalikan pengguna ke dashboard, bukan konteks sebelumnya.
- Hash navigation dan `href="#"` membuat history/back behavior tidak konsisten.
- Chat modern dan halaman pemeriksaan Nakes kini memakai resolver server-side berdasarkan role serta status request. Request aktif kembali ke daftar konsultasi aktif, sedangkan request selesai/dibatalkan kembali ke riwayat selesai; Warga kembali ke riwayatnya.
- Detail riwayat masih berbasis hash dashboard dan belum menjadi URL detail mandiri, sehingga deep-link, back/forward, session expiry, serta notification-entry UAT masih terbuka.

Acceptance criteria:

- Detail riwayat mempunyai URL langsung yang stabil dan bisa direload/deep-link.
- Keluar dari chat kembali ke detail request/riwayat asal, bukan dashboard generik.
- Setelah create/accept/cancel/complete/PIC action, destination ditetapkan per transition.
- Return target tidak diterima mentah dari user; gunakan server-side allowlist/signed state.
- Browser back/forward, refresh, session expiry, dan opening from notification diuji.
- Tidak ada action utama yang bergantung pada `href="#"` dan inline `onclick`.

### PRIV-01 — Lampiran chat dan media profil harus privat

Status: `IN PROGRESS`

Masalah:

- Upload lampiran chat baru sudah diarahkan ke storage privat di luar `FCPATH`.
- Response message hanya mengembalikan URL controller berbasis `message_id`; storage key tidak dikirim ke browser.
- Download melakukan authorization ulang terhadap request, validasi MIME dari isi file, batas ukuran, dan header `private, no-store`/`nosniff`.
- Row legacy `uploads/chat_images/` tetap dapat dibaca melalui controller untuk kompatibilitas, tetapi file fisik lama belum dimigrasikan dan URL statis lama belum diblokir pada Nginx.
- Runner cutover legacy kini tersedia dengan mode inspect/apply, database allowlist, explicit confirmation, row lock, backup SHA-256, manifest, rollback database+filesystem, dan safe rerun. Runner belum dieksekusi terhadap staging.
- Template Nginx deny untuk `/uploads/chat_images/` sudah tersedia, tetapi belum boleh dipasang sebelum hasil migration menunjukkan zero legacy row dan backup telah diverifikasi.
- Media profil masih memakai jalur legacy dan belum termasuk remediation batch lampiran chat ini.

Acceptance criteria:

- File privat disimpan di luar public web root.
- Download melalui endpoint berizin atau `X-Accel-Redirect` setelah authorization.
- Cross-owner, cross-request, cross-tenant, anonymous, dan expired-session test ditolak.
- Audit log dan cache policy terdokumentasi.

### VISIT-01 — Bukti kunjungan dan anti-FakeGPS berlapis

Status: `OPEN`

Masalah:

- JPG/JPEG, MIME, hash, private storage, freshness, accuracy, dan radius sudah tersedia.
- Browser capture hint dan koordinat client belum dapat membuktikan lokasi secara absolut.
- Belum ada viewer/audit trail bukti yang lengkap untuk pihak berwenang.

Acceptance criteria:

- Server-issued short-lived challenge/nonce mengikat request, actor, waktu, dan upload.
- Server time, client time, accuracy, route continuity, speed anomaly, dan photo hash tersimpan.
- Deteksi mock/suspicious location menghasilkan risk marker, bukan klaim kepastian palsu.
- Command-center berwenang dapat meninjau bukti, jarak, waktu, dan audit trail; pasien hanya melihat data yang memang diizinkan.
- Konsultasi kunjungan tidak bisa ditutup tanpa bukti valid.
- Repeated submission, replay, cross-request media, dan manipulated metadata ditolak.

### ERROR-01 — Unified error handler dan prerequisite failures

Status: `OPEN`

Masalah:

- Bentuk response masih campuran: `1/0`, `status`, `success`, redirect, HTML error, dan JSON.
- Session expiry pada endpoint AJAX kadang menghasilkan redirect HTML.
- Kesalahan data wajib per role belum diarahkan ke tindakan perbaikan yang tepat.

Acceptance criteria:

- Satu error envelope JSON: `success`, `safe_error_code`, `message`, `field_errors`, `request_id`, dan optional `retryable`.
- HTTP status konsisten: 400 validation, 401 login, 403 authorization/prerequisite, 404 missing, 409 conflict, 422 domain validation, 429 rate limit, 500/503 server/dependency.
- Error production tidak membocorkan SQL, path, token, stack trace, atau PII.
- UI memiliki error boundary untuk network timeout, offline, stale state, conflict, dan session expiry.
- Setiap missing prerequisite memberikan CTA yang tepat tanpa membuka data role lain.
- Correlation/request ID masuk log terstruktur dan response aman.

### AUTH-02 — Signup/login abuse protection

Status: `OPEN`

Acceptance criteria:

- Rate limit per IP dan identity, progressive delay, dan audit event.
- Password policy diperkuat dan breach/common-password policy dipertimbangkan.
- Kontak diverifikasi sebelum akun mendapat seluruh privilege.
- Login location client tidak dianggap sebagai trust signal tanpa validasi tambahan.

### DEP-01 — External dependency hardening

Status: `OPEN`

Acceptance criteria:

- LiveKit SDK dan dependency kritis dipin exact version atau di-vendor lokal.
- Remote asset mempunyai SRI bila tetap memakai CDN.
- API klinis/routing/reverse geocoding mempunyai connect timeout, total timeout, retry bounded, cache, dan circuit breaker.
- Dependency failure tidak menggantung PHP worker dan menghasilkan fallback ramah pengguna.

## P1 — Product separation for Puskesmas

### PUSKESMAS-01 — Command-center harus berbeda dari Nakes personal

Status: `OPEN`

Masalah:

- UI dan fungsi command-center masih terlalu mirip dashboard Nakes personal.
- Command-center membutuhkan koordinasi operasional, bukan workflow klinis personal.

Target capability command-center:

- Live operational queue lintas Nakes dalam tenant yang sama.
- Assignment/reassignment/clear PIC dengan availability dan workload.
- Monitoring status perjalanan, arrival, service, overdue, dan completion.
- Presence/last seen Nakes dengan definisi freshness yang jelas.
- Shift/roster Nakes dan coverage area.
- Exception board: request belum diterima, tanpa PIC, SLA mendekati jatuh tempo, lokasi stale, bukti kunjungan gagal, dan call tidak terjawab.
- Read-only clinical summary sesuai least privilege; bukan akses penuh catatan pribadi.
- Audit trail keputusan command-center.
- Filter, pencarian, bulk-safe actions, dan export terbatas sesuai authorization.

Acceptance criteria:

- Command-center mempunyai route, navigation, dashboard, policy, dan test matrix sendiri.
- Personal Nakes tidak melihat kontrol koordinasi tenant.
- Command-center tidak dapat melakukan tindakan klinis atas nama Nakes.
- Seluruh query tenant-scoped dan cross-Puskesmas test ditolak.
- UI diuji dengan multi-Nakes, multi-shift, empty state, stale data, dan concurrent assignment.

## P2 — UI/UX and maintainability

### UI-01 — Hapus dead/dummy/legacy interface

Status: `OPEN`

Acceptance criteria:

- Menu `Lainnya` mempunyai tujuan nyata atau dihapus.
- Nomor telepon/nama dummy dan modal profil legacy dihapus.
- Duplicate HTML ID, hidden dead controls, dan legacy chat view dihapus.
- Tidak ada aksi menggunakan placeholder link `href="#"`.

### UI-02 — Accessibility dan Bahasa Indonesia

Status: `OPEN`

Acceptance criteria:

- Dokumen memakai `lang="id"`.
- Keyboard navigation, focus management, label, alt text, contrast, dan live region diuji.
- Loading, empty, offline, retry, success, dan error state menggunakan bahasa non-teknis.
- Istilah konsisten antara Warga, Nakes, dan command-center.

### ARCH-01 — Pecah active views dan standardisasi API

Status: `OPEN`

Acceptance criteria:

- Inline CSS/JS pada view besar dipindah ke asset/component teruji.
- Bootstrap data diserialisasi aman.
- API contract dan naming method konsisten.
- Controller orchestration dipisahkan dari rendering dan dependency call.

### NOTIF-01 — Background notification contract yang jujur

Status: `OPEN`

Masalah:

- Audio foreground/ringtone berjalan saat page runtime hidup.
- Browser tidak dapat menjamin custom audio ketika aplikasi benar-benar ditutup.

Acceptance criteria:

- Foreground realtime audio dan background Web Push dipisahkan sebagai dua capability.
- PWA/service worker menangani notification click, deep-link, deduplication, dan permission state.
- UI tidak menjanjikan suara ketika OS/browser melarangnya.
- Bila custom ringtone background wajib, buat keputusan native Android/iOS dan notification channel OS.

## Required role-specific route matrix

| Capability | Warga | Nakes personal | Command-center Puskesmas |
|---|---:|---:|---:|
| Membuat request | Ya | Tidak | Tidak |
| Melihat request | Milik sendiri | Assigned/authorized | Tenant scoped |
| Accept/cancel tenant | Tidak | Hanya bila policy mengizinkan | Ya |
| Assign PIC | Tidak | Tidak | Ya |
| Tindakan klinis | Tidak | Assigned request | Tidak |
| Monitoring operasional | Status milik sendiri | Tugas sendiri | Seluruh tenant |
| Review bukti kunjungan | Milik sendiri, terbatas | Bukti milik tugas | Tenant scoped |
| Presence Nakes | Tidak | Diri sendiri | Tenant scoped |
| Data klinis | Milik sendiri | Assigned request | Ringkasan least-privilege |

## Release gates

Satu tahap remediation hanya boleh ditutup bila seluruh gate relevan lulus:

1. PHP lint dan JavaScript syntax.
2. Unit test dan integration test untuk positive/negative path.
3. Authorization matrix seluruh role non-Admin.
4. Method/CSRF/route inventory test.
5. Stored-XSS, file privacy, dan session invalidation regression.
6. Exact domain post-state dan transaction closure.
7. Browser UAT Android dan desktop untuk navigation, realtime, call, upload, location, dan offline behavior.
8. FakeGPS/anomaly UAT dengan hasil dicatat sebagai risk detection, bukan jaminan absolut.
9. No migration/schema change tanpa migration plan, backup, rollback, dan disposable integration evidence.
10. Commit, push, deploy, dan feature activation dilakukan sebagai gate terpisah.

## Current checkpoint

- Audit source non-Admin selesai.
- JavaScript regression inti pada baseline audit: 205 assertions lulus.
- P0 batch 1 implemented locally: all-role active login gate, active-session database revalidation, secure/HttpOnly cookie defaults, retired Firebase route tombstones, POST-only LiveKit mutation/status endpoints, read-only incoming-call polling, dan Nakes profile XSS/input hardening.
- P0 batch 1 source-contract regression: 39 assertions lulus; LiveKit/realtime browser regressions terkait: 115 assertions lulus.
- P0 batch 2 implemented locally: global CSRF, safe JSON CSRF failure, same-origin fetch/XHR/form bridge, POST-only logout/signup, GET-only read endpoints, dan tombstone tambahan untuk route legacy.
- P0 batch 2 source-contract regression: 77 assertions lulus; CSRF browser regression: 17 assertions lulus; exact non-Admin route inventory: 104 assertions lulus; existing realtime/notification/request/LiveKit/PIC/presence browser regression: 185 assertions lulus; 36 changed PHP files parsed successfully.
- SEC-01 tetap `IN PROGRESS` sampai runtime PHP 8.1 membuktikan header `Secure`, `HttpOnly`, dan `SameSite=Lax`, serta missing/invalid-token integration test membuktikan zero database mutation.
- ROUTE-01 tetap `IN PROGRESS` sampai manifest method contract dieksekusi terhadap bootstrap/router CI3 sebenarnya (bukan source contract saja).
- AUTH-01, XSS-01, dan ROUTE-01 belum `DONE` sampai seluruh controller/view/implicit route selesai diaudit dinamis.
- UX-CLINICAL batch 3 implemented locally: WHO ICD-10 2019 canonical presenter, alias Indonesia sebagai search aid, official WHO title/code sebagai canonical selection, Fornas-only medicine presenter, fail-closed non-canonical/internal-brand filtering, no internal provenance pada browser API, dan retirement endpoint diagnosis legacy yang digantikan endpoint canonical. Final regression count dicatat pada handoff batch.
- PRIV-01 batch 4 deployed: upload gambar chat baru berada di storage privat, browser hanya menerima endpoint download berizin berbasis message ID, dan storage key tidak lagi diekspos.
- PRIV-01 batch 5 implemented locally: runner inspect/apply untuk file legacy, backup+manifest SHA-256, filesystem/database rollback, safe rerun, dan template Nginx deny tersedia. Execution terhadap staging, pemasangan Nginx deny, dynamic cross-role HTTP regression, serta media profil masih menjadi gate terbuka.
- PRIV-01 batch 6 implemented locally: foto profil baru Warga/Nakes disimpan di private storage, akses foto memerlukan self/consultation/same-tenant relationship, Admin dan cross-tenant ditolak, dashboard/history/chat/call memakai endpoint terotorisasi, serta direct legacy URL mempunyai template Nginx deny. PHP 8.1 unit, authenticated HTTP matrix, deployment private directory, dan Nginx rollout masih menjadi gate terbuka.
- Authenticated browser UAT, penetration test aktif, dan database staging mutation tidak dilakukan dalam audit.
- Dokumen ini belum menyatakan remediation selesai.
