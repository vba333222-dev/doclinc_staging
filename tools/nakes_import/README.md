# Reset data staging dan import Nakes UAT

Tool ini hanya untuk reset staging yang telah direview. Mode default adalah inspeksi atau plan tanpa write. File sumber harus berada di luar repository dan web root, dimiliki akun operasional yang diizinkan, tidak executable, dan tidak dapat diakses world.

## Kontrak keamanan

- Source harus persis 19.146 byte dengan SHA-256 `cd9cd8678065e89a4ab8f55049f479f73fe6294bc99a61312720edc0ecefca6c`.
- Password database hanya diberikan melalui environment proses sementara.
- Password awal tidak pernah ditulis ke output atau file. Hash dibuat di dalam transaksi apply.
- Apply membutuhkan database `doclinc-staging`, backup terverifikasi, aktor admin aktif, reference unik, named lock, dan seluruh confirmation.
- Reset memakai `DELETE` child-before-parent dalam satu transaksi `SERIALIZABLE`; tidak memakai `TRUNCATE`, `FOREIGN_KEY_CHECKS`, atau reset `AUTO_INCREMENT`.
- Seluruh 353 audit pengujian lama dihapus di dalam transaksi reset yang sama; post-state wajib `audit_logs=0`.
- File upload tidak dihapus. Output hanya memberi jumlah kandidat orphan, tanpa nama file.

## Source placement di Debian 12

Contoh layout terkontrol (placeholder harus diganti setelah path diverifikasi):

```bash
install -d -o <OPERATIONS_USER> -g <OPERATIONS_GROUP> -m 0750 /home/<OPERATIONS_USER>/doclinc-import-source
install -o <OPERATIONS_USER> -g <OPERATIONS_GROUP> -m 0640 <UPLOADED_XLSX> /home/<OPERATIONS_USER>/doclinc-import-source/nakes.xlsx
find /home/<OPERATIONS_USER>/doclinc-import-source -maxdepth 1 -type l -print
find /home/<OPERATIONS_USER>/doclinc-import-source -maxdepth 1 -type f -perm /0111 -print
sha256sum /home/<OPERATIONS_USER>/doclinc-import-source/nakes.xlsx
stat -c '%U %G %a %s %n' /home/<OPERATIONS_USER>/doclinc-import-source/nakes.xlsx
```

Jangan menjalankan perubahan permission recursive. Pastikan dua perintah `find` tidak menghasilkan output sebelum melanjutkan.

## Review lokal

```powershell
docker run --rm -v "C:\Project\Doclinc_staging\doclinc_staging:/app" -v "C:\Users\USER\Downloads\DATA TIM PELAYANAN DOKLINC.xlsx:/source/input.xlsx:ro" -w /app -e "DOCLINC_NAKES_TEST_XLSX=/source/input.xlsx" doclinc-php81-mysqli-test:latest php tools/nakes_import/tests/unit.php
$env:DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_ROOTS='C:\Users\USER\Downloads'
php tools/nakes_import/nakes_import.php inspect-source '--source-xlsx=C:\Users\USER\Downloads\DATA TIM PELAYANAN DOKLINC.xlsx' --format=json
```

## Kontrak command operasional

Migration plan berikut tidak membuka koneksi database:

```bash
php application/migrations/20260727000300_nakes_import_account_foundation.php
```

Setelah password schema writer diberikan ke environment proses melalui mekanisme secret yang disetujui (bukan argument atau history), akun tersebut harus memiliki tepat `SELECT, ALTER` pada `users` dan `puskesmas_staff` di database target. `SELECT` dibutuhkan untuk verifikasi signature/row preservation; migration menolak grant database-wide/global, object lain, privilege lain, atau `GRANT OPTION`. Migration apply memakai kontrak berikut:

```bash
php application/migrations/20260727000300_nakes_import_account_foundation.php \
  --apply \
  --confirm-database=doclinc-staging \
  --environment=staging \
  --backup-reference=<VERIFIED_BACKUP_REFERENCE> \
  --confirm-backup-sha256=0a383da6019e4e8f2a217f79cd601aa004783fdd6d16803b459ad0f570102bfa
```

Environment proses migration yang wajib adalah `DOCLINC_NAKES_SCHEMA_WRITE_ENABLED`, `DOCLINC_NAKES_SCHEMA_DB_HOST`, `DOCLINC_NAKES_SCHEMA_DB_PORT`, `DOCLINC_NAKES_SCHEMA_DB_NAME`, `DOCLINC_NAKES_SCHEMA_DB_USER`, `DOCLINC_NAKES_SCHEMA_DB_PASSWORD`, dan `DOCLINC_NAKES_SCHEMA_ALLOWED_USERS`.

Setelah schema terverifikasi dan akun reset/import sementara menerima grant table-level yang tepat, jalankan:

```bash
php tools/nakes_import/nakes_import.php inspect-source \
  --source-xlsx=<ABSOLUTE_CONTROLLED_XLSX> \
  --format=json

php tools/nakes_import/nakes_import.php plan-reset-import \
  --source-xlsx=<ABSOLUTE_CONTROLLED_XLSX> \
  --format=json

php tools/nakes_import/nakes_import.php apply-reset-import \
  --apply \
  --source-xlsx=<ABSOLUTE_CONTROLLED_XLSX> \
  --confirm-database=doclinc-staging \
  --confirm-source-sha256=cd9cd8678065e89a4ab8f55049f479f73fe6294bc99a61312720edc0ecefca6c \
  --confirm-source-record-count=26 \
  --confirm-preserved-puskesmas-count=9 \
  --confirm-preserved-command-center-count=9 \
  --confirm-backup-sha256=0a383da6019e4e8f2a217f79cd601aa004783fdd6d16803b459ad0f570102bfa \
  --reset-reference=<UNIQUE_UTC_REFERENCE> \
  --actor-admin-user-id=<VALIDATED_ADMIN_ID> \
  --format=json
```

Environment proses reset/import yang wajib adalah `DOCLINC_NAKES_RESET_WRITE_ENABLED`, `DOCLINC_NAKES_RESET_ENVIRONMENT`, `DOCLINC_NAKES_RESET_DB_HOST`, `DOCLINC_NAKES_RESET_DB_PORT`, `DOCLINC_NAKES_RESET_DB_NAME`, `DOCLINC_NAKES_RESET_DB_USER`, `DOCLINC_NAKES_RESET_DB_PASSWORD`, `DOCLINC_NAKES_RESET_ALLOWED_USERS`, `DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_ROOTS`, `DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_UIDS`, dan `DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_GIDS`. Password tidak boleh dipersistenkan. Output apply hanya berisi agregat dan dihasilkan setelah reconciliation internal berhasil sebelum commit.

## Urutan operasi setelah review

1. Pastikan source deployment berjalan dengan `DOCLINC_FIRST_LOGIN_PASSWORD_CHANGE_ENABLED=off`.
2. Verifikasi branch, commit, clean worktree, backup file, backup manifest, checksum backup, salinan off-server, dan restore rehearsal. Backup yang direview adalah `/home/doclinc-dev/backups/manual/doclinc-staging-before-data-reset-20260726T193124Z.sql.gz` dengan SHA-256 `0a383da6019e4e8f2a217f79cd601aa004783fdd6d16803b459ad0f570102bfa`.
3. Aktifkan maintenance mode atau hentikan traffic staging melalui prosedur yang telah disetujui; verifikasi tidak ada request aktif sebelum operasi destructive.
4. Jalankan migration tanpa argumen untuk plan. Plan tidak membuka database dan tidak menjalankan DDL.
5. Jalankan migration apply menggunakan environment schema writer sementara, confirmation database, environment staging, backup reference, dan checksum backup.
6. Provision akun reset/import sementara dengan grant table-level yang direview: `SELECT` untuk tabel yang diaudit; `DELETE` hanya untuk tabel reset; `INSERT` hanya untuk `users` dan `puskesmas_staff`. `locations`, `rating`, dan `puskesmas` adalah compatibility table optional: berikan `SELECT, DELETE` hanya jika tabel tersebut benar-benar ada saat preflight. Grant untuk optional table yang tidak ada ditolak. Jangan grant `UPDATE`, DDL, global privilege, atau `GRANT OPTION`.
7. Buat reference unik, misalnya `uat-nakes-reset-$(date -u +%Y%m%dT%H%M%SZ)-<CHANGE_ID_LOWERCASE>`, lalu catat nilai final di change record privat.
8. Export environment database, allowed user, allowed source root/UID/GID, dan password hanya pada shell proses operasional. Jangan menulisnya ke profile, `.env`, PHP-FPM, systemd, atau repository.
9. Jalankan `inspect-source`, lalu `plan-reset-import`. Review semua count, inference aggregate, rencana penghapusan tepat 353 audit dummy, command-center whitelist, upload orphan count, dan `write_executed=false`.
10. Jalankan `apply-reset-import --apply` dengan seluruh confirmation yang tercantum pada help/contract tool, termasuk reference unik dan actor admin aktif.
11. Jalankan ulang reconciliation read-only dan cocokkan semua post-count yang dilaporkan tool, termasuk sembilan distribusi staff, notification transaksi nol, clinical suggestion `1/11450/701`, enam clinical migration, dan tiga feed.
12. Nonaktifkan atau hapus akun database reset/import sementara. Aktifkan `DOCLINC_FIRST_LOGIN_PASSWORD_CHANGE_ENABLED=on` dan `DOCLINC_FIRST_LOGIN_PASSWORD_CHANGE_ENVIRONMENT=staging` hanya setelah reconciliation lulus, lalu reload konfigurasi runtime secara terkontrol dan akhiri maintenance mode.

Migration historis yang masih menyebut `m_dokter` tidak boleh dijalankan ulang. Runtime modern menggunakan `users.userId`; kemunculan kembali query runtime ke `m_dokter` adalah stop condition.

Password tidak boleh diberikan sebagai argument command. Nama file sumber tidak perlu dipertahankan; tool hanya bergantung pada isi, checksum, dan kontrak workbook.

## Fail-safe dan rollback

Jika plan atau apply gagal, jangan mengubah data manual. Apply gagal sebelum commit akan rollback penuh. Bila kegagalan ditemukan setelah commit:

1. Set `DOCLINC_FIRST_LOGIN_PASSWORD_CHANGE_ENABLED=off`; akun import dengan flag wajib akan ditolak login secara aman.
2. Hentikan traffic staging melalui prosedur maintenance yang sudah disetujui.
3. Restore backup terverifikasi ke database recovery terisolasi dan rekonsiliasi marker/count.
4. Setelah approval DBA, restore backup yang sama ke `doclinc-staging`; jangan menjalankan reverse-delete dan jangan drop column additive.
5. Deploy ulang source sebelumnya bila diperlukan, lalu verifikasi command-center, admin, clinical suggestion, dan login.

Tidak ada rollback schema destructive. Column additive dipertahankan agar data yang mungkin telah terisi tidak hilang.
