# Location presentation

This tooling covers the user-facing reverse-geocoding presenter used on the Warga dashboard and the Nakes/Puskesmas visit-monitoring panel. Canonical coordinates remain in hidden form fields or in-memory map state for routing and visit evidence, but are never used as fallback display text.

The browser presenter:

- supports the configured Mapbox or Google provider and falls back to an authenticated same-origin reverse-geocoding endpoint;
- applies a bounded timeout, request coalescing, minimum request interval, and an in-memory LRU-style cache;
- shows an Indonesian safe fallback when an address cannot be resolved;
- exposes an explicit OpenStreetMap link without rendering raw latitude/longitude text;
- never treats a reverse-geocoded address as security proof.

The server fallback accepts coordinates only in a CSRF-protected POST body, never
in the route query string. It revalidates the active actor, contacts only the
allowlisted HTTPS OpenStreetMap Nominatim endpoint, enforces a global provider
interval, and stores bounded 24-hour cache entries in a private directory. It
returns address text only and performs no database write. The same resolver is
used when creating a consultation so `requests.location` receives the resolved
address instead of system fallback wording.

The public provider is called only from a single-shot, user-triggered location
lookup; dashboard/form address resolution does not poll it. The UI displays
OpenStreetMap attribution. Operators can disable or switch an allowlisted
Nominatim-compatible endpoint without a source deployment via
`DOCLINC_REVERSE_GEOCODING_ENABLED`, `DOCLINC_REVERSE_GEOCODING_ENDPOINT`, and
`DOCLINC_REVERSE_GEOCODING_ALLOWED_HOSTS`.

Run the browser contract:

```bash
node tools/location_presentation/tests/client_test.js
```

Run the view-wiring contract:

```bash
node tools/location_presentation/tests/view_test.js
```

Run the PHP service unit test when PHP 8.1 is available:

```bash
php8.1 tools/location_presentation/tests/server_unit.php
```

Authenticated UAT must still cover permission denied, inaccurate GPS, provider timeout, movement between cache cells, and opening the map link on Android.
