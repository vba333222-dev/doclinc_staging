# Puskesmas operational workload

Read-only command-center snapshot for one canonical Puskesmas tenant. It combines
active staff linkage, Nakes presence, active request assignment, and visit state.

The browser contract intentionally excludes patient identity, contact details,
clinical content, coordinates, NIK/KK/BPJS/KIS, NIP, SIP, and raw storage keys.
Personal Nakes cannot access the snapshot or its navigation entry.

The feature defaults off and requires all three staging/uat gates:

```text
DOCLINC_PUSKESMAS_OPERATIONS_ENABLED=true
DOCLINC_PUSKESMAS_OPERATIONS_ENVIRONMENT=staging
DOCLINC_ROLE_PREREQUISITES_ENABLED=true
DOCLINC_NAKES_PRESENCE_ENABLED=true
```

No schema is introduced by this feature. It fails closed unless the existing
care-operations tables and role prerequisites are ready.

Run tests:

```bash
php8.1 tools/puskesmas_operations/tests/unit.php
node tools/puskesmas_operations/tests/client_test.js
node tools/puskesmas_operations/tests/source_test.js
```

Activation remains a separate rollout after disposable MariaDB integration,
cross-tenant HTTP tests, and authenticated command-center/personal-Nakes UAT.
