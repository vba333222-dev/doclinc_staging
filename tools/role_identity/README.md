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
php tools/role_identity/tests/credential_policy_unit.php
node tools/role_identity/tests/source_test.js
node tools/role_identity/tests/client_test.js
node tools/role_identity/tests/staging_runner_source_test.js
php application/migrations/20260802000100_role_identity_foundation.php
bash tools/role_identity/run_disposable_migration.sh
```

NIK, KK, BPJS/KIS, and NIP must not be copied to session state, realtime payloads,
notifications, Puskesmas dashboards, logs, or safe error bodies.

For Nakes and Puskesmas accounts, `password_changed_at` is the evidence that the
user has completed at least one private password change. A null or malformed
value fails closed even when a legacy `must_change_password` flag is zero.
First activation and post-Admin-reset states remain separate; resetting a
previously activated account does not erase its last user-change evidence.

## Staging migration runner

After the read-only production-readiness gate reports only the identity schema
foundation as missing, run the official staging wrapper as root:

```bash
bash tools/role_identity/run_staging_migration.sh
```

The wrapper fixes the database target to local `doclinc-staging`, refuses to run
while the role-prerequisite feature is active, prompts for the database password
without echo, runs the actual migration against disposable MariaDB resources,
creates and validates a mode-`0600` full database backup, and then
passes every explicit confirmation to the migration. It verifies that base row
counts did not change and runs the read-only production-readiness audit again.

Expected exit statuses:

- `0`: migration and readiness pass; controlled activation may be planned;
- `3`: migration passes and schema is exact, but data backfill is still required;
- `1`: backup, migration, schema verification, or readiness execution failed.

MariaDB DDL is not treated as transactionally reversible. The wrapper never
performs an automatic restore after a partial DDL failure; it preserves the
validated backup and requires manual recovery review.
