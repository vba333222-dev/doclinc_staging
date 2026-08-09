# Nakes presence monitoring

This feature provides a database-throttled presence heartbeat for active,
linked personal Nakes accounts and a bounded read-only snapshot for:

- the canonical command-center of the same Puskesmas; and
- an authenticated active Admin across Puskesmas.

It is default-off and requires both flags to match the runtime environment:

```text
DOCLINC_NAKES_PRESENCE_ENABLED=true
DOCLINC_NAKES_PRESENCE_ENVIRONMENT=staging
```

The browser heartbeat runs every 30 seconds, while MariaDB writes are limited
to once per 45 seconds. A Nakes is derived as offline after 90 seconds without
a persisted heartbeat. Snapshots contain only account/staff identity, profession,
tenant, status, and last-seen time; they exclude contact, location, and clinical
data. Monitoring uses bounded polling and does not publish directly to Centrifugo.
Pada dashboard Puskesmas, snapshot ini memperbarui indikator pada daftar staf
yang sama; tidak ada lagi kartu status Nakes terpisah. Foto berasal dari endpoint
profil privat yang terotorisasi, bukan dari payload presence.

Run the source-level tests:

```bash
php tools/nakes_presence/tests/unit.php
node tools/nakes_presence/tests/client_test.js
```

The official MariaDB integration entrypoint uses the same disposable database
environment variables as the realtime request suite:

```bash
php tools/nakes_presence/tests/integration.php
```

Manual browser/UAT validation remains part of the final cross-device test pass.
