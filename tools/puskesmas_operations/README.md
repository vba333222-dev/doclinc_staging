# Puskesmas operational workload

Read-only command-center snapshot for one canonical Puskesmas tenant. It combines
active staff linkage, Nakes presence, active request assignment, and visit state.

The browser contract intentionally excludes patient identity, contact details,
clinical content, coordinates, NIK/KK/BPJS/KIS, NIP, SIP, and raw storage keys.
Personal Nakes cannot access the snapshot or its navigation entry.

The feature defaults off and requires its own staging/uat gate plus Nakes
Presence:

```text
DOCLINC_PUSKESMAS_OPERATIONS_ENABLED=true
DOCLINC_PUSKESMAS_OPERATIONS_ENVIRONMENT=staging
DOCLINC_NAKES_PRESENCE_ENABLED=true
```

No schema is introduced by this feature. It fails closed unless the existing
care-operations tables and Nakes Presence are ready. Every Puskesmas Operations
page or API entry still evaluates the authenticated command-center account's
role prerequisites and requires a complete result even when the global Role
Prerequisites rollout is disabled.

Run tests:

```bash
php8.1 tools/puskesmas_operations/tests/unit.php
php8.1 tools/puskesmas_operations/tests/controller_access_test.php
node tools/puskesmas_operations/tests/client_test.js
node tools/puskesmas_operations/tests/source_test.js
```

Run the actual CI3 query-builder contract only against a disposable MariaDB
instance. The harness creates a randomly named database, never accepts the
application/staging database name, and drops the disposable database in
`finally`:

```bash
bash tools/puskesmas_operations/run_disposable_integration.sh
```

The official runner prompts for the MariaDB admin password without echoing it.
Host, port, user, and PHP 8.1 binary can be overridden with
`DOCLINC_TEST_DB_ADMIN_HOST`, `DOCLINC_TEST_DB_ADMIN_PORT`,
`DOCLINC_TEST_DB_ADMIN_USER`, and `DOCLINC_PHP81_BIN`. It deliberately has no
database-name input.

The integration matrix covers exact tenant isolation, inactive/personal/Admin
denial, safe payload fields, staff/request/assignment overflow without silent
truncation, ambiguous assignment, zero database mutation during reads, and
cleanup after success or failure.

Activation remains a separate rollout after disposable MariaDB integration,
cross-tenant HTTP tests, and authenticated command-center/personal-Nakes UAT.
