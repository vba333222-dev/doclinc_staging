# Realtime request coordination

Request lifecycle and PIC updates remain authoritative in MariaDB. Realtime delivery carries only a sanitized invalidation envelope; an authorized browser fetches `/realtime/requests/snapshot` before changing the page.

The feature requires the existing realtime client and both request-specific environment values:

- `DOCLINC_REALTIME_REQUESTS_ENABLED`
- `DOCLINC_REALTIME_REQUESTS_ENVIRONMENT`

Only exact `staging` feature and runtime environments may enable it. Missing, malformed, mismatched, UAT, development, test, and production values resolve to disabled. The repository supplies no enabled value.

`Request_realtime_delivery` uses the caller's CodeIgniter database connection and never begins, commits, or rolls back a transaction. Lifecycle identity is the persisted request ID plus its one-way transition. PIC identity is the persisted assignment ID. Envelope version `1` identifies the payload contract; it is not an aggregate sequence.

The active mutation paths are request creation in `Konsultasi_m`, acceptance and command-center cancellation in `Home_nakes_m`, owner cancellation in `Home_m`, completion in `Konsultasi_nakes_m`, and PIC assignment/reassignment/clear in `Home_nakes_m`. Creation previously committed before its controller notification; acceptance and owner cancellation had no encompassing domain transaction; command-center cancellation, completion, and PIC changes committed before their external notification or invalidation side effect. When this feature is enabled those side effects now execute before the model-owned commit. The legacy path is retained when the flag is off.

Events and consumers are deliberately narrow:

- create, accept, cancel, and complete invalidate owner/user and same-tenant command-center snapshots;
- PIC assignment, reassignment, and clear invalidate the owner, affected personal staff users, and same-tenant command center;
- no Admin audience is created, no request payload is used as state, and no cross-tenant broadcast is permitted.

When enabled, the domain transaction contains the request mutation, durable notification where applicable, the existing `notification.created` outbox row when notification realtime is enabled, and request invalidation rows. Any enqueue failure causes the model-owned transaction to roll back. With the request feature disabled, existing post-commit notification behavior remains unchanged.

Audience use is limited to owner or personal Nakes user channels and the canonical command-center tenant channel. Admin has no new realtime policy. The browser reuses the existing shared realtime connection, coalesces snapshot reads, keeps bounded polling when disconnected, and never treats event data as domain state.

The shared browser client owns subscriptions per exact channel. Adapters acquire and release independent ownership handles, while one SDK subscription is retained until its last owner releases it. Observers do not create subscriptions. This allows notification and request adapters to share `user:<id>` while a command-center request adapter also owns `puskesmas:<kode>:ops`, without creating a second Centrifuge connection.

Notification and request adapters bind an idempotent `pagehide` handler to the browser window. Each adapter releases only its own channel ownership and ignores late snapshot callbacks after teardown. A dedicated BFCache `pageshow` resume path is not provided; restoration relies on the page's normal initialization lifecycle and remains a browser UAT item.

Command-center mutations revalidate the actor from locked `users`, active Puskesmas, canonical command-center, and personal-staff-link state after the request lock and before mutation. Assignment changes lock active rows in deterministic assignment order. More than one active assignment is treated as corrupt state and fails closed without implicit repair.

Tests:

```text
php tools/realtime_requests/tests/unit.php
node tools/realtime_requests/tests/client_test.js
php tools/realtime_requests/tests/readiness_test.php
php tools/realtime_requests/tests/integration.php
php tools/realtime_requests/tests/model_integration.php
php tools/realtime_requests/tests/controller_orchestration_integration.php
php tools/visit_proof/tests/unit.php
```

The MariaDB integration commands include their own bounded readiness gate. They authenticate with the disposable administrator account and require a successful `SELECT 1` before creating a fixture database. The default deadline is 45 seconds with a 500 ms retry interval; test-only overrides are available through `DOCLINC_TEST_DB_READY_TIMEOUT_MS` and `DOCLINC_TEST_DB_READY_INTERVAL_MS`. Authentication or readiness failure exits nonzero without printing connection credentials.

The model integration command exercises the production model methods and transition orchestrator. Its request-realtime-off matrix covers two distinct runtime contracts: client and notification realtime enabled with request coordination disabled, and the full legacy fallback with all three disabled. The first contract retains durable notifications plus `notification.created`; the second retains only durable notifications. Both reject request lifecycle outbox events and perform no direct publish. A separate enabled-state matrix invokes each lifecycle model followed by the production orchestrator and verifies exact notification, outbox, audience, and idempotency counts.

The controller orchestration command is an independent harness for the five public controller actions. It uses authenticated session, request input, model-result, orchestrator, and response collaborators to verify the ordered `model -> orchestrator -> response` success path and the `model -> response` failure path. It does not execute the model integration suite again. Controller notifications remain a post-model compatibility boundary when request realtime is disabled, so a notification insert failure does not reverse an already successful domain mutation.

The visit-proof extension is independent from request realtime. When its separate default-off flag is enabled, an on-site completion also requires a staged image, fresh accurate location within the patient radius, and an arrived/in-service visit state. The model integration verifies that proof media finalization and the append-only location sample commit with completion, while a downstream realtime failure rolls all transactional proof associations back.

Deployment is source-first with the request flag off. Rollback is disabling the request flag; durable requests and existing notification polling continue to operate.
