# Native PIC assignment

Command-center users retain the existing HTML form fallback. When JavaScript is available, `doclinc-pic-assignment.js` submits assignment, reassignment, and clear operations as same-origin JSON requests and reconciles every visible panel for the same request without a form redirect.

The server remains authoritative: the JSON response re-reads the active assignment after the transactional model operation. The browser never constructs an assignment from untrusted POST values. Requests are locked per request ID, duplicate submits are ignored, safe server failures keep the previous UI state, and pending requests are aborted on `pagehide`.

Run:

```bash
node tools/pic_assignment/tests/client_test.js
node --check assets/js/doclinc-pic-assignment.js
```

Existing actual-model MariaDB coverage remains in `tools/realtime_requests/tests/model_integration.php` for assign, reassign, clear, ambiguous active assignments, same-PIC idempotency, rollback, and flag-off behavior.
