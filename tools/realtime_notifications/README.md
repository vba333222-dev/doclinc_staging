# Realtime notifications

DocLink keeps the `notifications` table as the durable source of truth. A realtime publication carries only an invalidation envelope; the browser then reloads the authorized notification snapshot from CodeIgniter.

## Runtime contract

Both feature sets must resolve as enabled before the browser assets or outbox enqueue path are used:

- `DOCLINC_REALTIME_CLIENT_ENABLED`
- `DOCLINC_REALTIME_CLIENT_ENVIRONMENT`
- `DOCLINC_REALTIME_CLIENT_RUNTIME_ENVIRONMENT`
- `DOCLINC_REALTIME_PUBLIC_WEBSOCKET_URL`
- `DOCLINC_REALTIME_NOTIFICATIONS_ENABLED`
- `DOCLINC_REALTIME_NOTIFICATIONS_ENVIRONMENT`

Missing, malformed, mismatched, non-staging, and non-UAT values resolve to disabled. No value is supplied by the repository.

The notification service inserts the notification and its `notification.created` outbox row on the same database connection and transaction. Callers that already own a transaction use `createWithinTransaction()` and remain responsible for commit or rollback. Current notification call sites run after their domain transaction and use the service-owned notification transaction.

The event uses the recipient's authorized `user:<users.userId>` channel and contains only the canonical outbox invalidation fields. Notification title and message remain in MariaDB and are returned only by the authorized snapshot endpoint.

## Browser behavior

Authorized Warga, personal Nakes, and canonical command-center sessions load the local Centrifuge SDK, the shared realtime client, and the notification integration in that order. Admin remains on existing polling because no Admin realtime channel policy exists.

The existing 30-second polling remains available. Realtime publications and recovery gaps refresh `/notifications/snapshot`; payload data is never treated as notification state. Notification sound defaults on, has no page-level enable/disable control, is silently unlocked by the first page gesture, and uses the local `assets/audio/doclinc-notification.wav` asset with Web Audio only as a fallback. A legacy disabled browser preference is normalized back to enabled when the runtime starts. Initial notifications are silent, and a bounded browser-side notification ID history prevents repeated chimes after reconnect, recovery, or polling. New notification snapshots also emit the local `doclinc:notifications:new` event so incoming-call polling can react immediately without trusting realtime payload data as call state.

Hidden tabs keep their exact-channel subscription and can play a previously unlocked sound on a best-effort basis. A fully closed or OS-suspended browser has no executing JavaScript; reliable closed-app sound must be delivered by an OS push channel or native wrapper rather than claimed by this browser runtime.

## Tests

```text
php tools/realtime_notifications/tests/unit.php
node tools/realtime_notifications/tests/client_test.js
php tools/realtime_notifications/tests/integration.php
```

Deployment remains source-first with both flags off. Enable the existing realtime client contract and the notification flag only after dispatcher, gateway, WebSocket routing, privileges, and authorized snapshot checks have passed. Rollback is disabling the notification flag; durable notification polling continues unchanged.
