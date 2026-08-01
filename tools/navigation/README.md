# Context-aware request navigation

Request and chat pages resolve their return destination from trusted role and
persisted request status. They do not accept a raw `return_to`, referrer, query
URL, or POST URL.

Current destinations:

- Warga: `home#riwayat`;
- Nakes/Puskesmas with an active request: `home_nakes#riwayat_konsul`;
- Nakes/Puskesmas with a completed or cancelled request:
  `home_nakes#riwayat_konsul_selesai`.

Run the pure PHP contract when PHP is available:

```bash
php tools/navigation/tests/unit.php
```

Run the controller/view wiring contract:

```bash
node tools/navigation/tests/source_test.js
```

Authenticated browser UAT is still required for back/forward, reload, expired
session, opening from a notification, and both personal-Nakes and canonical
Puskesmas navigation.
