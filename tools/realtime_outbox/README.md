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
DOCLINC_REALTIME_GATEWAY_URL=http://127.0.0.1:8000/api/publish
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

## Centrifugo transport

`CentrifugoTransport` implements the Centrifugo OSS v6.9 server API publish contract.
The dispatcher posts only to the exact `/api/publish` path with `Content-Type:
application/json` and the server-only API key in `X-API-Key`. The request body contains
the validated audience as `channel`, the canonical event envelope as `data`, and the
stable outbox `idempotency_key`. The same row therefore sends the same key after lease
recovery or retry.

The default endpoint is `http://127.0.0.1:8000/api/publish`. Plain HTTP is accepted only
for literal `127.0.0.1` or `localhost`. A non-loopback endpoint must use HTTPS and its
exact host must appear in `DOCLINC_REALTIME_GATEWAY_ALLOWED_HOSTS`. User information,
query parameters, fragments, alternate paths, unsupported schemes, and redirects are
rejected. Connection and total timeouts are bounded, as is the response body.

Successful delivery requires a 2xx response containing a JSON object with `result` and
without `error`. The transport does not expect a generic `success` property. Response
bodies and API error messages are never included in dispatcher output.

| Condition | Result | Safe code |
|---|---|---|
| Connection failure | retry | `centrifugo_connection_failed` |
| Connect or total timeout | retry | `centrifugo_connect_timeout` / `centrifugo_total_timeout` |
| HTTP 408 or 429 | retry | `centrifugo_request_timeout` / `centrifugo_rate_limited` |
| HTTP 5xx or API error 100 | retry | `centrifugo_server_unavailable` |
| HTTP 401 or 403 | permanent | `centrifugo_auth_rejected` |
| HTTP 404 or API error 102/104 | permanent | `centrifugo_channel_rejected` |
| HTTP redirect | permanent | `centrifugo_redirect_rejected` |
| Other HTTP 4xx | permanent | `centrifugo_request_rejected` |
| Missing/malformed/oversized response | bounded retry | `centrifugo_result_invalid`, `centrifugo_response_invalid`, or `centrifugo_response_too_large` |

`HttpRealtimeTransport` remains available for generic internal transport tests but is
not selected by the production dispatcher command. `InMemoryRealtimeTransport` is
limited to test use. No gateway is contacted by the test suite, and there is no
Firebase fallback.

Client subscription authorization, connection tokens, WebSocket proxying, and domain
call-site integration remain outside this package. Dispatcher activation requires a
pinned Centrifugo deployment and a separately verified release checksum.

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
