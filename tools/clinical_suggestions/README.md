# Clinical autocomplete UAT foundation

This directory contains the isolated UAT/staging reference importer for complaint, symptom, selectable ICD-10 diagnosis, and Fornas medicine-name autocomplete. It is lexical search only. It does not infer a diagnosis, recommend medicine, set dosing, import patient data, register clinical package metadata, or enable the 47-dataset clinical runtime.

## Architecture and governance

The existing canonical reference schema is intentionally not used for this pass. Its source links depend on record revisions and the controlled metadata/import chain, while its runtime constraints require approved governance. The package is explicitly draft, `production_ready=false`, and `runtime_enabled=false`. Treating those records as canonical approved runtime data would be inaccurate.

The legacy `keluhan` table covers only one domain. Patient `terapi`, diagnosis, medical record, and consultation history tables are prohibited suggestion sources. The additive tables created by the migration are therefore a compatibility foundation scoped to `uat` or `staging`:

- `clinical_suggestion_import_batches`
- `clinical_suggestion_terms`
- `clinical_suggestion_aliases`

The browser endpoint reads only those tables. JSON is used only by the CLI importer. Legacy diagnosis, consultation description, `saran`, and therapy strings remain the persistence contract. A selected ICD-10 value is stored as its display string; a selected medicine stores only its name. Anamnesis uses the separate nullable `medicalrecords.anamnesis` foundation and is never encoded into `saran`, diagnosis, criteria, complaint, therapy, or suggestion tables. There is no automatic dose, frequency, route, duration, or instruction.

## Feature flag

Both values are required. Any other or missing environment value leaves the feature off.

```text
DOCLINC_CLINICAL_SUGGESTIONS_ENABLED=true
DOCLINC_CLINICAL_SUGGESTIONS_ENVIRONMENT=uat
```

Accepted environment scopes are `uat` and `staging`. When off, existing forms and legacy free-text/save behavior remain in place.

## Import controls

The importer accepts exactly five package datasets:

```text
01_master_keluhan.json
02_master_gejala.json
20b_master_diagnosis_icd10_who_2019.json
20c_master_diagnosis_alias_indonesia_starter.json
21_master_obat_fornas.json
```

It runs the official package inspector first. Import is a dry-run unless `--apply` is supplied. Apply additionally requires database, checksum, and snapshot confirmations plus `DOCLINC_CLINICAL_SUGGESTION_WRITE_ENABLED=true`. Credentials are environment-only; do not pass passwords as arguments.

Every import uses a new reference generated immediately before the dry-run and then reused unchanged for apply and rollback, for example `uat-ac1-20260727T031522Z-a1b2c3d4`. The required form is `<purpose>-<UTC YYYYMMDDTHHMMSSZ>-<8..32 lowercase hex random characters>`; a fixed or reused reference is rejected. Record the final value in the controlled UAT change record. Rollback apply repeats official package inspection and requires the same checksum and snapshot confirmations, so it cannot target an unrelated package or batch.

Required CLI database environment:

```text
DOCLINC_CLINICAL_SUGGESTION_DB_HOST
DOCLINC_CLINICAL_SUGGESTION_DB_PORT
DOCLINC_CLINICAL_SUGGESTION_DB_NAME
DOCLINC_CLINICAL_SUGGESTION_DB_USER
DOCLINC_CLINICAL_SUGGESTION_DB_PASSWORD
DOCLINC_CLINICAL_SUGGESTION_ALLOWED_USERS
DOCLINK_CLINICAL_PACKAGE_ALLOWED_ROOTS
```

The schema migration uses the same database variables and requires `DOCLINC_CLINICAL_SUGGESTION_SCHEMA_WRITE_ENABLED=true` for its explicit `--apply` mode. Its default mode is plan-only. Migration rollback is: disable the feature flag, roll back the named import batch, and retain the empty additive tables. Dropping tables is neither required nor automated.

The separate anamnesis migration is also plan-only by default. Apply requires `DOCLINC_CLINICAL_ANAMNESIS_SCHEMA_WRITE_ENABLED=true`, exact database confirmation, `staging`/`uat`, and a verified backup reference:

```text
php application/migrations/20260727000200_medicalrecords_anamnesis_foundation.php
php application/migrations/20260727000200_medicalrecords_anamnesis_foundation.php --apply --confirm-database=doclinc-staging --environment=staging --backup-reference=<VERIFIED_BACKUP_REFERENCE>
```

It verifies the legacy `medicalrecords` signature, obtains a named lock, and adds only `anamnesis TEXT NULL`. It does not backfill or change existing rows. Operational rollback disables the feature flag and retains the nullable column; the migration never drops a column that may contain clinical documentation.

Minimum post-migration privileges are deliberately separate:

- the application runtime account needs `SELECT` on the three suggestion tables;
- a dedicated UAT importer needs `SELECT`, `INSERT`, `UPDATE`, and `DELETE` only on the three suggestion tables (`UPDATE` records batch counts/state; `DELETE` is used only by confirmed batch rollback);
- the schema executor needs temporary DDL authority for the additive migration and must not be reused as the runtime/import account.

No suggestion account needs access to `requests`, patient diagnosis, medical records, patient therapy, package metadata, or canonical clinical record tables beyond the application's already-existing request authorization query path.

## Endpoint and authorization

`GET /clinical-suggestions` accepts `type`, `q`, and the minimum authorization context `request_id`. The query is limited to 80 characters and results to 10.

### User-facing terminology contract

- Diagnosis suggestions are presented only from `20b_master_diagnosis_icd10_who_2019.json`.
- Diagnosis search accepts the canonical code, official WHO title, and Indonesian search aliases.
- When an Indonesian alias matches, it is shown as a search aid while the official WHO title, ICD-10 code, and `WHO 2019` remain visible; the canonical `CODE — WHO title` is the selected value.
- Selecting a diagnosis preserves the canonical `CODE — WHO title` value for the current legacy text storage contract.
- Database provenance fields such as `source_name`, `source_version`, package identity, or any Doclinc/Doklinc/DocLink branding are never returned as user-facing metadata.
- A diagnosis with an invalid ICD-10 code, a non-WHO dataset, or an internally branded label is omitted fail-closed.
- Complaint and symptom suggestions show the human clinical label only; internal codes are never used as display text.
- Medicine suggestions are accepted only from `21_master_obat_fornas.json`, show the canonical Fornas name, and never expose the internal `EFORNAS_*` code.
- The current Fornas snapshot is name-only reference data. It must not be presented as dosage, strength, route, restriction, insurance eligibility, or an automatic therapy recommendation.

| Type | Role | Request rule |
| --- | --- | --- |
| `complaint` | authenticated Warga | create flow, or own Pending request for edit |
| `symptom` | Nakes | Accepted request with `can_handle` ownership |
| `diagnosis` | Nakes | Accepted request with `can_handle` ownership |
| `medicine` | Nakes | Accepted request with `can_handle` ownership |

Admin, cross-Puskesmas Nakes, non-PIC Nakes, Completed/Cancelled requests, and Warga clinical domains are denied.

## Local test commands

```text
php tools/clinical_suggestions/tests/run.php --package-root=<ABSOLUTE_PACKAGE_ROOT>
php tools/clinical_suggestions/tests/migration_integration.php
php tools/clinical_suggestions/tests/anamnesis_migration_integration.php
php tools/clinical_suggestions/tests/integration.php --package-root=<ABSOLUTE_PACKAGE_ROOT>
node tools/clinical_suggestions/tests/anamnesis_js_test.js
```

The migration and importer integration tests are destructive only inside a database whose name contains a standalone `test` segment and only when `DOCLINC_CLINICAL_SUGGESTION_DISPOSABLE_TEST=true`. They prove plan zero-write, explicit apply, migration idempotency, partial-schema fail-closed behavior, one random UAT import, importer idempotency, and rollback isolation.

## Controlled package placement

On the UAT host, place the package outside both the repository and web root, using an approved owner and group. Replace the placeholders before execution; do not reuse the example names blindly.

```text
PACKAGE_ROOT=/srv/doclinc-uat/clinical-package/v1
EXPECTED_OWNER=<approved-package-owner>
EXPECTED_GROUP=<approved-package-group>
test "$(realpath -e "$PACKAGE_ROOT")" = "$PACKAGE_ROOT"
test "$PACKAGE_ROOT" = /srv/doclinc-uat/clinical-package/v1
test -z "$(find "$PACKAGE_ROOT" -xdev -type l -print -quit)"
test -z "$(find "$PACKAGE_ROOT" -xdev ! -type d ! -type f -print -quit)"
find "$PACKAGE_ROOT" -xdev -type d -exec chown "$EXPECTED_OWNER:$EXPECTED_GROUP" {} + -exec chmod 0750 {} +
find "$PACKAGE_ROOT" -xdev -type f -exec chown "$EXPECTED_OWNER:$EXPECTED_GROUP" {} + -exec chmod 0640 {} +
test -z "$(find "$PACKAGE_ROOT" -xdev ! -user "$EXPECTED_OWNER" -print -quit)"
test -z "$(find "$PACKAGE_ROOT" -xdev ! -group "$EXPECTED_GROUP" -print -quit)"
test -z "$(find "$PACKAGE_ROOT" -xdev -type f -perm /0111 -print -quit)"
test -z "$(find "$PACKAGE_ROOT" -xdev -perm -0007 -print -quit)"
```

Run the official inspection explicitly after placement. The importer repeats the same inspection before either dry-run or apply.

```text
php tools/clinical_package/clinical_package.php inspect --package-root="$PACKAGE_ROOT" --format=json
```

Stop if the owner/group, mode, symlink, containment, package checksum, or snapshot check differs from the reviewed values.

## Manual UAT checklist

1. With the flag off, confirm all three existing forms save free text and make no `/clinical-suggestions` request.
2. With the flag on and no reference data, confirm free text and submit remain usable after the safe availability message.
3. As Warga, search complaint on create and own Pending edit; confirm diagnosis and medicine direct URLs return 403.
4. As the assigned Nakes on an Accepted request, search symptom, ICD-10 code/label, and medicine name; confirm dose/signa/route/instruction remain manual.
5. Confirm another Nakes, another Puskesmas, admin, Completed, and Cancelled request contexts receive 403.
6. Type `%_<'\"` and more than 80 characters; confirm there is no raw exception or HTML execution.
7. Type rapidly and confirm stale results do not replace the latest query; verify Arrow keys, Enter, Escape, touch selection, loading, empty, and error states.
8. Enter free-text anamnesis, select one symptom after a new line, and confirm the selection preserves prior text and does not duplicate an existing term.
9. Save one consultation and reopen Warga, Nakes, and admin authorized history; confirm anamnesis is escaped and diagnosis, `saran`, and therapy legacy strings are unchanged.
