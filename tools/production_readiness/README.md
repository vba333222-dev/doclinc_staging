# Production pre-activation readiness

Read-only staging gate for the role prerequisite rollout. It evaluates every
active Warga and Nakes through the production `Role_prerequisite_service`, using
the canonical command-center/personal identity rules and actual profile-image
storage validation. It also audits every active Puskesmas and staff roster row
independently, including facility data, canonical command-center coverage, and
the exact personal-staff link contract. A facility or staff row cannot disappear
from readiness merely because its account relationship is missing. Private
storage and every referenced photo must also be owned by the application runtime
owner, preventing a root-run audit from reporting a false positive.

The audit prints aggregate counts and safe field codes only. It never prints
user IDs, names, email addresses, phone numbers, NIK, KK, BPJS/KIS, NIP,
Puskesmas codes, profile keys, or other stored values.

The report separates three gates so self-service profile gaps are not confused
with data that only Dinas Kesehatan can repair:

- `MANAGED_DATA_READY`: every active Puskesmas, canonical command center, and
  staff roster row is complete, and there is no invalid role identity;
- `CONTROLLED_ENFORCEMENT_READY`: managed data, schema, private storage, and
  actor classification are safe enough to enable the completion gate. Actors
  still completing their own profile or changing a temporary password may
  remain incomplete and will be redirected by the existing gates;
- `PRODUCTION_ACTIVATION_READY`: strict final state; every active actor and all
  managed data are complete.

The audit also reports `ACTOR_PROFILE_EVALUATED`, password-change denials,
identity denials, self-service/managed actor-gap counts, and per-field managed
gap counts. These are aggregate counts only; they deliberately do not identify
the affected record.

The database target is fixed to local `127.0.0.1:3306/doclinc-staging` using the
CloudPanel root identity, revalidated after connect, and inspected inside
`START TRANSACTION READ ONLY`. Exit status meanings:

- `0`: `PRODUCTION_ACTIVATION_READY=true` (the strict final state);
- `3`: audit completed safely, but activation is blocked by data/readiness;
- `1`: the audit itself failed.

Exit `3` can therefore coexist with `CONTROLLED_ENFORCEMENT_READY=true`; that
means the gate may be enabled through a separate reviewed rollout, not that the
application is already fully production-ready.

The runner also inspects active PHP-FPM pool declarations for the role
prerequisite flag. If incomplete data is detected while the flag is already
true, it exits `4` with `FAIL_FEATURE_ENABLED_WHILE_BLOCKED`.

Run on staging as root, then enter the CloudPanel database master password when
prompted:

```bash
bash tools/production_readiness/run_staging_readiness.sh
```

Tests:

```bash
php8.1 tools/production_readiness/tests/unit.php
node tools/production_readiness/tests/source_test.js
bash -n tools/production_readiness/run_staging_readiness.sh
```

This gate does not execute migrations, backfill data, change feature flags,
reload services, or perform authenticated UAT.
