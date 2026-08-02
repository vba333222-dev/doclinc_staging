# Role identity foundation

This package validates the mandatory identity fields used by the global Warga and
Nakes prerequisite gate:

- Warga: NIK (16 digits), Kartu Keluarga (16 digits), BPJS/KIS (13 digits).
- Personal Nakes: NIP (18 digits), managed only by Administrator Dinas Kesehatan.

The migration is plan-only unless all explicit write, environment, database,
backup, and writer identity confirmations are supplied. New columns are nullable
so existing rows are preserved; application enforcement is activated separately
only after the data-readiness audit passes.

Run:

```bash
php tools/role_identity/tests/unit.php
node tools/role_identity/tests/source_test.js
node tools/role_identity/tests/client_test.js
php application/migrations/20260802000100_role_identity_foundation.php
```

NIK, KK, BPJS/KIS, and NIP must not be copied to session state, realtime payloads,
notifications, Puskesmas dashboards, logs, or safe error bodies.
