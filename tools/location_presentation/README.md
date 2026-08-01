# Location presentation

This tooling covers the user-facing reverse-geocoding presenter used on the Warga dashboard and the Nakes/Puskesmas visit-monitoring panel. Canonical coordinates remain in hidden form fields or in-memory map state for routing and visit evidence, but are never used as fallback display text.

The browser presenter:

- supports the configured Mapbox or Google provider;
- applies a bounded timeout, request coalescing, minimum request interval, and an in-memory LRU-style cache;
- shows an Indonesian safe fallback when an address cannot be resolved;
- exposes an explicit OpenStreetMap link without rendering raw latitude/longitude text;
- never treats a reverse-geocoded address as security proof.

Run the browser contract:

```bash
node tools/location_presentation/tests/client_test.js
```

Run the view-wiring contract:

```bash
node tools/location_presentation/tests/view_test.js
```

Authenticated UAT must still cover permission denied, inaccurate GPS, provider timeout, movement between cache cells, and opening the map link on Android.
