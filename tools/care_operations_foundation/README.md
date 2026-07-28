# Care operations foundation

The foundation provides reusable security contracts, authorization policy, additive
schema, and operational tests for care-related realtime and visit workflows. Feature
flags remain disabled until each runtime integration is deployed and verified.

## Runtime compatibility

- Modern Nakes identity is `users.userId`. The active authorization helper classifies
  a command-center as the lowest active dokter account for one active Puskesmas with
  no `puskesmas_staff` link. Personal Nakes require one active staff link in the same
  tenant. No new `m_dokter` dependency is introduced.
- PIC assignment and clearing already use database transactions and row locks. Request
  access checks include lifecycle, tenant, and assigned personal identity.
- Notifications are durable MariaDB rows. Existing reads and read-marking remain
  unchanged; the additive schema supplies a transactional outbox contract.
- Visit state and current location are stored on `requests`. Existing location updates
  do not retain an append-only route history.
- Consultation completion writes request state, medical record, consultation, and
  therapy data in one database transaction. Existing upload handling occurs before
  that transaction, so media handling must use staging, pending metadata,
  post-commit finalization, and compensating cleanup.
- The active medical-record contract stores one legacy diagnosis text. The additive
  diagnosis table supports positions 1 through 5 while preserving the legacy field.
- Global CodeIgniter CSRF protection is disabled. `Mutation_token_policy` is therefore
  a reusable defense contract for new mutations; it does not retrofit legacy routes.
- The first-login hook blocks must-change-password sessions before protected HMVC
  routes. The care operations authorization policy also denies such sessions.

The disposable fixture preserves the expected cardinalities: nine active Puskesmas,
nine command-centers, 25 active linked personal Nakes, one intentionally inactive
unlinked staff row, one admin, 16 Warga, seven completed requests and medical records,
seven active assignment rows, 26 notifications, one suggestion batch, 11,450 terms,
701 aliases, and six applied clinical migrations. The inactive staff row remains
inactive and is not a presence target.

## Shared source contracts

`Doclinc_feature_flags` accepts only explicit true/false values. A feature can resolve
enabled only when both configured and runtime environments are the same and are
`staging` or `uat`. Missing, empty, malformed, mismatched, test, development, and
production values resolve disabled. No environment variable is read or enabled by the
library itself.

`Mutation_token_policy` issues a random opaque token with at least 256 bits of nonce
entropy. Only an HMAC digest and bounded metadata are stored in the session. Tokens
are bound to purpose, user, and session; expire in 60 to 900 seconds; use
`hash_equals`; and are consumed once even when validation fails. New mutation
controllers must clear their token store on logout and session regeneration.

`Care_operations_policy` is a pure authorization contract:

| Actor | Allowed contract |
| --- | --- |
| Warga | Read/mutate only an owned request; must-change sessions denied. |
| Personal Nakes | Active user plus active staff link, same tenant, Accepted request, and assigned/PIC identity. |
| Command-center | Active canonical command-center, same Puskesmas; coordination only. |
| Admin | Read-only visibility where a domain explicitly allows it; no implicit mutation. |
| Inactive/unlinked staff | Never a presence target or personal PIC. |

These methods consume identity and access contexts produced by the existing helper;
they do not replace database-backed ownership checks.

## Migration contract

Migration:

`application/migrations/20260728000100_care_operations_foundation.php`

It is standalone CLI-only. Calling it without `--apply` performs no database
connection and no write. Apply requires an explicit environment, exact database
confirmation, verified backup reference and SHA-256, a dedicated allowlisted schema
writer, and a named lock. Test mode accepts only a database named
`doclinc_care_operations_test_*` plus the disposable confirmation.

Target signatures are pinned as canonical MariaDB 10.11 `SHOW CREATE TABLE` SHA-256
values after stable whitespace and auto-increment-counter normalization. This verifies
all target columns, signedness, defaults, indexes, checks, engine, collation, and table
comments without granting database-wide SELECT. Base-table signatures continue to be
checked through exact `information_schema` metadata using table-level SELECT.

Tables:

- `realtime_outbox`: durable authoritative event handoff. Realtime history remains a
  cache; downstream publication state is explicit and idempotent.
- `consultation_visit_media`: pending/associated/ready lifecycle for staged media.
  Filesystem and MariaDB are deliberately not described as one atomic transaction.
- `medicalrecord_diagnoses`: ordered 1–5 diagnosis compatibility records; the
  suggestion identifier is a nullable logical reference, not a foreign key.
- `nakes_presence`: throttled `last_seen` persistence only. Redis/Centrifugo TTL is
  the online-state source when runtime integration is enabled.
- `visit_location_updates`: append-only, idempotent route samples linked logically to
  request and `users.userId`.

All target tables are InnoDB. The migration creates no foreign key to legacy or
clinical suggestion tables because signedness, collation, identifier stability, and
lifecycle compatibility are not locked. Logical references are indexed. It performs
no DML, does not write `clinical_schema_migrations`, and contains no destructive or
automatic rollback. If one to four target tables are present, or any target signature
differs, apply stops fail-closed. Because MariaDB DDL commits implicitly, an unexpected
server failure between `CREATE TABLE` statements can leave a partial schema; the next
run detects that state and requires DBA review rather than attempting cleanup.

### Plan

```bash
php application/migrations/20260728000100_care_operations_foundation.php
```

Expected markers include:

```text
EXECUTION_MODE=PLAN
DATABASE_CONNECTION_OPENED=false
DDL_EXECUTED=false
PLANNED_TABLE_COUNT=5
```

### Reviewed schema-writer privileges

| Scope | Privilege | Reason |
| --- | --- | --- |
| Global | `USAGE` only | Account existence; no global capability. |
| `doclinc-staging` schema | `CREATE` only | MariaDB cannot grant CREATE on tables that do not exist. |
| Eight base tables | `SELECT` | Exact engine, primary key, and relevant column signatures. |
| `information_schema` | implicit metadata visibility | Engine, column, index, constraint, and table-comment verification. |
| Session | `GET_LOCK` / `RELEASE_LOCK` | Advisory concurrency gate; no extra grant required. |

The exact eight SELECT targets are `users`, `m_puskesmas`, `puskesmas_staff`,
`requests`, `request_staff_assignments`, `medicalrecords`, `notifications`, and
`clinical_suggestion_terms`. The grant verifier rejects wildcard/global data grants,
duplicate or unrelated table grants, `GRANT OPTION`, and any privilege beyond this
matrix. The writer does not need INSERT, UPDATE, DELETE, ALTER, DROP, INDEX, or access
to application row contents beyond the reviewed signature tables.

### Apply

Values in angle brackets must be supplied from an approved server runbook. A password
must not be placed in command arguments or shell history.

```bash
read -r -s -p 'Schema writer password: ' CARE_OPERATIONS_SCHEMA_PASSWORD
printf '\n'
env \
  DOCLINC_CARE_OPERATIONS_SCHEMA_WRITE_ENABLED=true \
  DOCLINC_CARE_OPERATIONS_SCHEMA_DB_HOST=localhost \
  DOCLINC_CARE_OPERATIONS_SCHEMA_DB_PORT=3306 \
  DOCLINC_CARE_OPERATIONS_SCHEMA_DB_NAME=doclinc-staging \
  DOCLINC_CARE_OPERATIONS_SCHEMA_DB_USER='<DEDICATED_SCHEMA_WRITER>' \
  DOCLINC_CARE_OPERATIONS_SCHEMA_DB_PASSWORD="$CARE_OPERATIONS_SCHEMA_PASSWORD" \
  DOCLINC_CARE_OPERATIONS_SCHEMA_ALLOWED_USERS='<DEDICATED_SCHEMA_WRITER>' \
  php application/migrations/20260728000100_care_operations_foundation.php \
    --apply \
    --environment=staging \
    --confirm-database=doclinc-staging \
    --backup-reference='<VERIFIED_BACKUP_REFERENCE>' \
    --confirm-backup-sha256='<VERIFIED_BACKUP_SHA256>'
unset CARE_OPERATIONS_SCHEMA_PASSWORD
```

## Deployment and fail-safe order

1. Reconfirm branch, commit, clean server worktree, live schema signatures, aggregate
   baseline, and the intentionally inactive staff state.
2. Deploy source with every new domain flag absent/off.
3. Verify an off-server backup and isolated restore rehearsal.
4. Provision the temporary schema writer using only the privilege matrix above.
5. Run the zero-connection plan and review its markers.
6. Run apply during a controlled maintenance window, then verify all five exact table
   signatures and that they are empty.
7. Disable/remove the temporary writer credentials and retain the additive empty
   tables.
8. Enable a staging/UAT feature only after its data path, authorization, worker, and
   compensation tests pass.

Fail-safe rollback is to leave all care operations feature flags absent/off. Do not
drop the additive tables automatically. Runtime code must tolerate disabled features,
so retaining the tables preserves legacy behavior. If schema creation fails partially,
keep flags off, capture the exact schema inventory, and require DBA review or restore
from the verified backup.

## Runtime prerequisites

- Pin a reviewed Centrifugo Community release and artifact checksum; no PRO-only
  feature may be required.
- Create external secret delivery and internal-only Server API access. No secret may
  enter the repository.
- Configure client publish disabled and subscription default deny with short-lived,
  authorized subscription tokens.
- Implement an outbox worker that treats a JSON error in an HTTP 200 response as a
  failure and performs idempotent retry/claim recovery.
- Define authorized snapshot endpoints for `recovered=false`; MariaDB remains
  authoritative and realtime history is only cache.
- Define Redis TTL keys and tenant/user channel contracts shared by notification,
  assignment, presence, and route updates. MariaDB `last_seen` writes must be
  throttled, not heartbeat-frequency writes.
- Apply domain mutation tokens and database-backed authorization at every new mutation
  endpoint. The policy object does not replace authoritative database checks.

Firebase and clinical-package runtime activation are outside this foundation.

## Testing

Run the unit suite with PHP 8.1:

```bash
php tools/care_operations_foundation/tests/unit.php
```

The integration suite requires a disposable MariaDB 10.11 database and the
`DOCLINC_TEST_DB_ADMIN_*` environment contract:

```bash
php tools/care_operations_foundation/tests/migration_integration.php
```

The integration fixture contains synthetic values only. It verifies plan behavior,
apply, idempotency, exact target hashes, partial-schema rejection, base-signature
rejection, preservation of existing rows and passwords, and cleanup of test-only
databases and accounts.
