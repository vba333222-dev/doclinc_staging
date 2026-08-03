# Non-Admin Security Hardening

Focused regression checks for the non-admin security remediation batches:

- active-account login and session revalidation;
- secure/HttpOnly session cookie defaults;
- global CodeIgniter CSRF enforcement with no route exemptions;
- same-origin CSRF propagation for `fetch`, XHR, and native POST forms;
- retired Firebase route tombstones;
- retired duplicate consultation, location, and chat-upload routes;
- explicit POST-only mutation and GET-only read contracts;
- POST-only LiveKit mutations and read-only incoming polling;
- Nakes profile role guard, validation, and output escaping.

Run:

```bash
node tools/security_hardening/tests/source_test.js
node tools/security_hardening/tests/csrf_client_test.js
node tools/security_hardening/tests/route_inventory_test.js
```

These source/browser-contract checks complement PHP lint, authenticated integration, and UAT. They do not replace a runtime PHP 8.1 negative CSRF test that proves zero database mutation, a complete CI3 route inventory, or an active penetration test.
