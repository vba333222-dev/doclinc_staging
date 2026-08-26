# Nakes facility placement and transfer

## Scope

Establish canonical placement history and an explicit scheduled transfer workflow while preserving legacy identity projections and historical request attribution.

## Implementation boundaries

- `nakes_facility_placements` stores effective active/ended placement history.
- `nakes_facility_transfers` stores scheduled/completed/cancelled intent.
- Activation revalidates active clinical ownership, closes the old placement, creates the new placement, updates required legacy projections, and records governance audit metadata in one transaction.
- The placement feature is default-off and ordinary staff profile updates cannot change facility while enabled.
- Command-center identities are not eligible as personal Nakes placement.
- Inventory output is read-only; no automatic backfill is included.

## Verification

- Policy and service tests pass with synthetic stores, including Admin actor binding, projection preservation, blocker handling, idempotent activation, cancellation, and audit-failure rollback.
- Disposable MariaDB 10.11 verification passed locally: both additive migrations applied in the same disposable server, generated-column uniqueness was enforced by the database, exact-schema reruns passed, and transaction rollback was verified. The disposable container and data were removed afterward.
- Staging migration was not executed. The placement feature remains disabled by default.
- Admin transfer actions are now narrowly guarded POST endpoints using the existing Admin session and provisioning CSRF token. Actor identity, current placement, and projection state are resolved server-side by the service/store.
- Remaining release blockers: endpoint-level browser/integration coverage and PHP 8.1 verification require the corresponding runtime; no staging enablement is authorized.
