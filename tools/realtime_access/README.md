# DocLink realtime client access

This component provides short-lived Centrifugo connection and subscription tokens for authenticated DocLink browser sessions. It also provides a shared browser client that treats realtime publications only as invalidation signals. Notification, assignment, presence, route, media, diagnosis, and ringtone behavior remain outside this component.

## Runtime contract

The feature is disabled unless all of these conditions hold:

- `DOCLINC_REALTIME_CLIENT_ENABLED` is one of `1`, `true`, `yes`, or `on`;
- `DOCLINC_REALTIME_CLIENT_ENVIRONMENT` is exactly `staging` or `uat`;
- `DOCLINC_REALTIME_CLIENT_RUNTIME_ENVIRONMENT` is the same allowed environment;
- `DOCLINC_REALTIME_PUBLIC_WEBSOCKET_URL` is a `wss` URL using the reviewed `/connection/websocket` path;
- the token HMAC configuration is valid.

The server environment must also provide:

- `DOCLINC_REALTIME_CLIENT_TOKEN_HMAC_SECRET`, matching Centrifugo `client.token.hmac_secret_key` without exposing it to browser code;
- `DOCLINC_REALTIME_CLIENT_TOKEN_TTL_SECONDS`, an integer from 60 through 300.

Missing, empty, malformed, mismatched, development, test, or production feature configuration leaves the feature disabled. Source deployment does not enable any setting.

## Token endpoints

`GET /realtime/connection-token` issues an HS256 JWT with exactly these claims:

```text
sub: authenticated users.userId as a decimal string
iat: issuance timestamp
exp: bounded expiration timestamp
```

`POST /realtime/subscription-token` accepts a JSON object containing only `channel` and issues an HS256 JWT with exactly:

```text
sub: authenticated users.userId as a decimal string
channel: the server-authorized channel
iat: issuance timestamp
exp: bounded expiration timestamp
```

Both endpoints return JSON with `Cache-Control: no-store`, revalidate the active account from `users`, and reject anonymous or password-change-blocked sessions. Tokens and secrets are never logged. The first-login hook classifies both endpoints as JSON while keeping them outside its route allowlist, so blocked accounts receive JSON 403 instead of an HTML redirect.

## Subscription authorization

Only the following channels are accepted:

| Channel | Authorized session |
| --- | --- |
| `user:<positive-user-id>` | The same active Warga or a classified active personal/command-center Nakes account |
| `request:<positive-request-id>` | The owning Warga; an assigned personal Nakes accepted by the existing request access helper; or the canonical command-center for the same Puskesmas under the existing lifecycle policy |
| `puskesmas:<validated-code>:ops` | The canonical command-center for that exact Puskesmas |

Admin access is not added. Unknown namespaces, arbitrary channels, cross-owner requests, cross-Puskesmas access, inactive staff, unlinked Nakes, and `DEFAULT` Puskesmas channels are denied. The controller derives identity from the authenticated session and live account row; request parameters never define the token subject.

## Browser client

The browser dependency is the official `centrifuge` npm package pinned to exact version `5.7.0`, compatible with Centrifugo server 6. It was obtained from the official npm registry for the `centrifugal/centrifuge-js` repository. Registry integrity, tarball hashes, retained-file hashes, and license information are recorded in `assets/vendor/centrifuge/5.7.0/PROVENANCE.md`.

Only these runtime files are retained:

```text
assets/vendor/centrifuge/5.7.0/centrifuge.js
assets/vendor/centrifuge/5.7.0/LICENSE
assets/vendor/centrifuge/5.7.0/PROVENANCE.md
```

The official publisher already minifies `dist/centrifuge.js`; the vendored bytes are unchanged. Runtime must not use a CDN. Load order for a future authorized page is:

```text
assets/vendor/centrifuge/5.7.0/centrifuge.js
assets/js/doclinc-realtime-client.js
```

The SDK exposes `Centrifuge` as a browser global and exposes its authorization error as `Centrifuge.UnauthorizedError`. The shared client resolves that exact SDK 5.7.0 export when converting HTTP 401/403 token responses into a permanent authorization failure.

`assets/js/doclinc-realtime-client.js` is reusable and has no automatic startup. A future authorized page loader must provide the public WebSocket URL and domain-specific snapshot URLs. It must not expose server secrets.

The client uses Centrifuge SDK token callbacks, automatic reconnect/backoff, and automatic connection/subscription token refresh. When recovery is unavailable or a new `event_id` arrives, it performs a same-origin authenticated GET against the configured snapshot URL. Publication data is never applied as domain state. Duplicate event IDs are bounded and ignored. Polling runs only as a fallback while the enabled client is disconnected. Teardown removes subscriptions, timers, dedupe state, and the connection.

The file is intentionally not loaded by any existing page yet. This keeps the feature inert and avoids changing domain behavior while the feature flag is off.

## Verification

Run the isolated contracts from the repository root:

```text
php tools/realtime_access/tests/unit.php
node --check assets/js/doclinc-realtime-client.js
node tools/realtime_access/tests/client_test.js
node tools/realtime_access/tests/vendor_test.js
```

Existing care operations, realtime outbox, clinical autocomplete, and anamnesis regressions must also pass before deployment.

## Deployment boundary

Deploy source with the feature disabled. Before enabling it in staging or UAT:

1. configure a protected HMAC secret that exactly matches the Centrifugo connection-token verifier;
2. verify the vendored SDK checksums and serve it from its local application asset path;
3. add and verify an authenticated public `wss` reverse-proxy route to the loopback Centrifugo listener;
4. keep direct client publish and unrestricted client subscription disabled;
5. attach the shared client only to authorized pages and supply existing authorized snapshot endpoints;
6. run owner, PIC, tenant, first-login, reconnect, refresh, and polling checks before enabling the flag.

Disabling the feature flag and omitting both JavaScript files from page loading is the application rollback. It stops token issuance and prevents new shared-client connections without changing database state. No database migration is part of this component.
