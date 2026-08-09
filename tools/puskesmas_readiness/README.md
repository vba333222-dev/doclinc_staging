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

The Admin readiness queue distinguishes a linked personal account that has
never completed first activation (`password_changed_at=NULL`) from an account
waiting after an Admin reset (`must_change_password=1` with prior change
evidence). A missing or malformed change timestamp fails closed even when a
legacy flag says otherwise. New accounts start without change evidence; an
Admin reset preserves any prior user-change timestamp. The public application
then permits only password change and logout until the user sets a new private
password. Temporary passwords are unique per account, entered one at a time,
and never rendered back by the Admin UI.

The Administrator (Dinas Kesehatan) master-data flow requires a complete
facility name, human-readable address, and bounded latitude/longitude before a
Puskesmas can be created, updated, or reactivated. The coordinates remain
administrative routing data; user-facing pages present a resolved address.

Run the unit test with PHP 8.1:

```bash
php tools/puskesmas_readiness/tests/unit.php
php tools/puskesmas_readiness/tests/admin_unit.php
node tools/puskesmas_readiness/tests/admin_source_test.js
```
