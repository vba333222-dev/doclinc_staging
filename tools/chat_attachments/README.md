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
node tools/chat_attachments/tests/source_test.js
```

## Rollout gate

Row lama dengan prefix `uploads/chat_images/` tetap dapat dibaca lewat endpoint
berizin untuk kompatibilitas. `PRIV-01` belum boleh dinyatakan selesai sampai:

1. seluruh file legacy dipindahkan ke private storage dan path database diperbarui
   dengan runner idempotent serta backup;
2. Nginx menolak akses langsung ke `/uploads/chat_images/`;
3. anonymous, cross-owner, cross-request, cross-tenant, dan expired-session HTTP
   regression membuktikan file tidak dapat dibaca;
4. authenticated Android/desktop UAT membuktikan preview tetap bekerja.
