# DocLink realtime outbox

This package provides a transaction-owned application enqueue API and a bounded,
one-shot dispatcher for `realtime_outbox`. It does not connect notification, PIC,
presence, route, media, or diagnosis call sites. It does not expose browser publish or
subscription APIs.

## Transaction ownership

`Realtime_outbox_writer` receives the existing CodeIgniter database object from its
caller. It opens no connection and never begins, commits, or rolls back a transaction.
The domain service must begin its transaction first, perform its domain mutation,
enqueue the event through the same database object, and roll back both when enqueue
returns `success=false`.

The writer requires a resolved `Realtime_outbox_feature_flags::writer()` state. Missing,
empty, malformed, disabled, mismatched, or production values remain disabled. The
writer performs no query when disabled.

Accepted event types and matching aggregate/invalidation values are exact:

| Event type | Aggregate | Invalidation |
|---|---|---|
| `notification.created` | `notification` | `notifications` |
| `request.assignment.changed` | `request` | `assignment` |
| `nakes.presence.changed` | `nakes` | `presence` |
| `visit.route.changed` | `visit` | `route` |
| `visit.media.changed` | `visit` | `media` |
| `medicalrecord.diagnoses.changed` | `medicalrecord` | `diagnoses` |

Audience values are `user:<positive-id>`, `request:<positive-id>`, or
`puskesmas:<known-code>:ops`. Puskesmas validation receives an exact allowlist from the
authorized caller and does not query a database. Browser input must never be passed
through as a channel.

The canonical payload contains only `aggregate_id`, `audience`, `event_id`,
`event_type`, `invalidation`, and `version`. Additional or nested keys are rejected.
The SHA-256 idempotency key is derived from the canonical payload and aggregate type;
callers do not supply a raw key.

## Dispatcher lifecycle

The dispatcher is a single one-shot process:

1. Validate environment, database confirmation, worker identity, exact grants, and the
   deployed canonical table signature.
2. Acquire the session-scoped named lock.
3. Recover a bounded set of expired claims and claim a bounded pending batch inside a
   short transaction.
4. Commit the claim before calling the transport.
5. Mark success as `published`; schedule a bounded exponential retry as `pending`; or
   mark permanent/exhausted work as `failed`.
6. Release the lock and exit.

The worker never holds a transaction while performing HTTP. A crash after gateway
acceptance but before the published update can cause another delivery after lease
expiry. The gateway must therefore honor the supplied idempotency key. MariaDB remains
authoritative; gateway history is only delivery infrastructure.

Retry delays start at five seconds, double with each attempt, and stop growing at five
minutes. Batch size is limited to 1–100, lease duration to 30–900 seconds, and maximum
attempts to 1–20. State `failed` is the safe terminal state; rows are not deleted.

## Privileges

| Account | Object | Required privileges | Forbidden |
|---|---|---|---|
| PHP application | `doclinc-staging.realtime_outbox` | `INSERT` | `SELECT`, `UPDATE`, `DELETE`, schema or global privileges |
| Dispatcher | `doclinc-staging.realtime_outbox` | `SELECT`, `UPDATE` | `INSERT`, `DELETE`, `ALTER`, `DROP`, `CREATE`, `INDEX`, `GRANT OPTION`, wildcard/global privileges |

`GET_LOCK` and `RELEASE_LOCK` are session functions and require no additional table
privilege. The dispatcher rejects missing, duplicate, unrelated, wildcard, or excessive
grants before it claims rows.

## Commands and environment

Plan mode opens no database connection and publishes nothing:

```text
php tools/realtime_outbox/realtime_outbox.php
```

Dispatch is explicit:

```text
php tools/realtime_outbox/realtime_outbox.php --dispatch \
  --environment=staging \
  --confirm-database=doclinc-staging \
  --batch-size=20 \
  --lease-seconds=120 \
  --max-attempts=5
```

Required server-only environment contract:

```text
DOCLINC_REALTIME_DISPATCH_ENABLED=true
DOCLINC_REALTIME_DISPATCH_WRITE_ENABLED=true
DOCLINC_REALTIME_DISPATCH_ENVIRONMENT=staging
DOCLINC_REALTIME_DISPATCH_DB_HOST=<INTERNAL_DB_HOST>
DOCLINC_REALTIME_DISPATCH_DB_PORT=3306
DOCLINC_REALTIME_DISPATCH_DB_NAME=doclinc-staging
DOCLINC_REALTIME_DISPATCH_DB_USER=<DEDICATED_WORKER>
DOCLINC_REALTIME_DISPATCH_DB_PASSWORD=<SECRET_FROM_PROTECTED_ENVIRONMENT>
DOCLINC_REALTIME_DISPATCH_ALLOWED_USERS=<DEDICATED_WORKER>
DOCLINC_REALTIME_GATEWAY_URL=<HTTPS_OR_LOOPBACK_INTERNAL_ENDPOINT>
DOCLINC_REALTIME_GATEWAY_ALLOWED_HOSTS=<EXACT_INTERNAL_HOST_ALLOWLIST>
DOCLINC_REALTIME_GATEWAY_SECRET=<SERVER_ONLY_SECRET>
DOCLINC_REALTIME_GATEWAY_CONNECT_TIMEOUT_MS=1500
DOCLINC_REALTIME_GATEWAY_TOTAL_TIMEOUT_MS=4000
```

Application enqueue activation uses the same staging/UAT-only resolver with
`DOCLINC_REALTIME_OUTBOX_ENABLED` and `DOCLINC_REALTIME_OUTBOX_ENVIRONMENT`. Source can
be deployed while both writer and dispatcher flags remain off.

Secrets belong in a root-readable service environment, not the repository, command
arguments, URL, logs, or process output. A future service should use `Type=oneshot` and
a timer with a non-overlapping interval. PHP must not daemonize or busy-loop.

## Transport boundary

`HttpRealtimeTransport` posts a generic server-only request containing channel, data,
and idempotency key. Only HTTPS endpoints or explicit loopback HTTP endpoints are
accepted. Non-loopback hosts must also appear in the exact environment allowlist.
Redirects are disabled and connection/total timeouts are bounded. A 2xx
response is successful only when valid JSON contains `success=true` and no logical
error. Timeout, malformed JSON, non-2xx status, and logical errors receive safe
retryable/permanent classification without exposing response bodies or secrets.

No realtime gateway is installed or contacted by this package. The concrete internal
gateway API, pinned server version, verified release checksum, subscription token
service, and deployment configuration remain prerequisites before dispatcher flags can
be enabled. There is no Firebase fallback.

## Tests

```text
php tools/realtime_outbox/tests/unit.php
php tools/realtime_outbox/tests/integration.php
```

The integration suite uses only a disposable MariaDB database and synthetic values. It
verifies caller commit/rollback ownership, idempotency, lease and retry behavior,
concurrency exclusion, terminal state, privilege rejection, canonical deployed schema,
and preservation of unrelated baseline tables.

Operational rollback is feature-based: disable dispatcher write first, disable enqueue,
stop the one-shot timer, and retain all outbox rows for reconciliation. This package
contains no reverse-delete or schema rollback.
