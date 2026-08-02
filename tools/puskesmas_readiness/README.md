# Puskesmas data readiness

Read-only readiness projection for the command-center dashboard. It summarizes the
current tenant facility prerequisite state, NIP/SIP readiness, active staff
completeness, and operational exception counts without
changing feature flags or database rows.

The projection deliberately does not expose NIP values and does not fabricate
missing addresses, NIP/SIP numbers, profile photos, or account links. The
command-center exception board is tenant-scoped and shows pending requests,
accepted requests without an active PIC, and readiness counts. It remains
informational while role prerequisite enforcement is disabled.

The Administrator (Dinas Kesehatan) master-data flow requires a complete
facility name, human-readable address, and bounded latitude/longitude before a
Puskesmas can be created, updated, or reactivated. The coordinates remain
administrative routing data; user-facing pages present a resolved address.

Run the unit test with PHP 8.1:

```bash
php tools/puskesmas_readiness/tests/unit.php
node tools/puskesmas_readiness/tests/admin_source_test.js
```
