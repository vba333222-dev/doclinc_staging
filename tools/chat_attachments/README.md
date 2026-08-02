# Private chat attachments

Lampiran gambar baru disimpan di luar web root dan hanya dibaca melalui
`GET /chat/attachment/<message_id>`. Endpoint melakukan authorization terhadap
request yang sama sebelum membaca file, memvalidasi MIME dari isi file, membatasi
ukuran, dan mengirim header `private, no-store` serta `nosniff`.

## Configuration

- `DOCLINC_CHAT_ATTACHMENT_STORAGE_PATH` dapat menunjuk direktori absolut privat.
- Bila kosong, aplikasi memakai sibling private directory milik site user:
  `<site-user-home>/private/chat-attachments`.
- Storage yang berada di dalam `FCPATH` ditolak.

## Tests

```bash
php8.1 tools/chat_attachments/tests/unit.php
php8.1 tools/chat_attachments/tests/migration_unit.php
node tools/chat_attachments/tests/source_test.js
```

Disposable MariaDB integration (database name wajib prefix test):

```bash
DOCLINC_CHAT_MIGRATION_DISPOSABLE_TEST=true \
DOCLINC_CHAT_MIGRATION_TEST_DB_NAME="doclinc_chat_attachment_test_$(date +%s)" \
DOCLINC_CHAT_MIGRATION_TEST_DB_ADMIN_PASSWORD='<protected-test-password>' \
php8.1 tools/chat_attachments/tests/migration_integration.php
```

## Legacy migration

Runner bersifat fail-closed. `--inspect` hanya membaca database dan filesystem.
`--apply` membutuhkan nama database yang dikonfirmasi, identity allowlist, backup
directory absolut di luar web root, dan environment `staging` atau disposable
`test`.

```bash
export DOCLINC_CHAT_MIGRATION_DB_HOST='127.0.0.1'
export DOCLINC_CHAT_MIGRATION_DB_PORT='3306'
export DOCLINC_CHAT_MIGRATION_DB_NAME='doclinc-staging'
export DOCLINC_CHAT_MIGRATION_DB_USER='<maintenance-user>'
export DOCLINC_CHAT_MIGRATION_DB_PASSWORD='<read-from-protected-secret-file>'
export DOCLINC_CHAT_MIGRATION_ALLOWED_USERS='<maintenance-user>'
export DOCLINC_CHAT_ATTACHMENT_STORAGE_PATH='/home/doclinc-dev/private/chat-attachments'
export DOCLINC_CHAT_MIGRATION_BACKUP_ROOT='/home/doclinc-dev/backups/chat-attachments'

install -d -m 0700 "$DOCLINC_CHAT_MIGRATION_BACKUP_ROOT"

php8.1 tools/chat_attachments/migrate_legacy.php \
  --inspect \
  --environment=staging \
  --max-rows=1000

php8.1 tools/chat_attachments/migrate_legacy.php \
  --apply \
  --environment=staging \
  --confirm-database=doclinc-staging \
  --backup-dir=/home/doclinc-dev/backups/chat-attachments/<timestamp> \
  --max-rows=1000
```

Migration mempertahankan nama acak terenkripsi, membuat backup terverifikasi
SHA-256, memindahkan file ke private storage, dan memperbarui kedua compatibility
field dalam satu transaksi. Jika update database gagal, file dikembalikan ke web
root dan row database di-rollback. Manifest tidak memuat isi chat atau identitas
pasien.

Crash proses atau koneksi putus tepat saat commit merupakan keadaan ambigu karena
database dan filesystem tidak mendukung satu distributed transaction. Jangan
menghapus backup. Periksa `manifest.json`, row database, source file, dan private
file sebelum rerun; status `rollback_failed` harus dihentikan untuk recovery
manual, bukan dipaksa lanjut. Error `migration_commit_state_verification_required`
berarti hasil commit perlu diperiksa karena koneksi putus atau finalisasi manifest
gagal di sekitar commit; runner sengaja tidak menebak state dan tidak mengembalikan
file ke public root pada kondisi ambigu tersebut.
`--inspect` melaporkan `LEGACY_RECOVERABLE_FILES` bila row masih menunjuk legacy
tetapi file sudah berada di private storage; apply berikutnya dapat menyelesaikan
row tersebut setelah operator memverifikasi hash dan backup location.

Setelah `LEGACY_ROWS_REMAINING=0`, pasang
`tools/chat_attachments/nginx/doclinc-chat-legacy-deny.conf` pada setiap public
server block, jalankan `nginx -t`, lalu reload. Jangan memasang deny rule sebelum
migration dan backup selesai diverifikasi.

## Rollout gate

Row lama dengan prefix `uploads/chat_images/` tetap dapat dibaca lewat endpoint
berizin untuk kompatibilitas. `PRIV-01` belum boleh dinyatakan selesai sampai:

1. runner migration menghasilkan manifest `committed`, row legacy nol, file
   privat terbaca, dan backup SHA-256 terverifikasi;
2. Nginx menolak akses langsung ke `/uploads/chat_images/`;
3. anonymous, cross-owner, cross-request, cross-tenant, dan expired-session HTTP
   regression membuktikan file tidak dapat dibaca;
4. authenticated Android/desktop UAT membuktikan preview tetap bekerja.
