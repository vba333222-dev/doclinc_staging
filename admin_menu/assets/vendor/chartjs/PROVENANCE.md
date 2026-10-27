# Chart.js 3.6.2

- Package: `chart.js`
- Version: `3.6.2` (per the `@preserve` header in `chart.min.js`)
- License: MIT (see `LICENSE` in this folder)
- Source repository: `https://github.com/chartjs/Chart.js`
- Distribution file: `dist/chart.min.js`, as published on
  `https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.6.2/chart.min.js`
- `chart.min.js` size: `193925` bytes, SHA-256
  `0f6b6487fdc444eabe5ee0c49a0c4e2d6d683717f4acb3653b0b0f2145fe728e`
- SHA-512 (base64) of the same file:
  `tMabqarPtykgDtdtSqCL3uLVM0gS1ZkUAVhRFu1vSEFgvB73niFQWJuvviDyBGBH22Lcau4rHB5p2K2T0Xvr6Q==`

That SHA-512 value is exactly the Subresource Integrity hash the admin header
used while the file was still loaded from cdnjs, so the vendored copy is
byte-identical to the build the browser was already trusting. Nothing was
minified, bundled, or patched here; only the browser bundle and the license are
kept (no source map, no TypeScript sources, no examples, no package metadata).

## Why it is vendored instead of loaded from a CDN

The admin panel is used on Puskesmas networks that do not always reach the
public internet, and the same reason already applies to
`assets/vendor/leaflet/` and `assets/geojson/`.

On a machine without outbound access the CDN `<script>` fails, `window.Chart`
stays `undefined`, and both chart surfaces used to return silently: the
dashboard showed two empty 260px boxes and nothing in the page said why. Serving
the library from this origin removes the network from that path entirely, so the
trend chart, the per-Puskesmas chart, and the diagnosis bar chart render the same
way online and offline.

## Degrading when the library still cannot be loaded

The surfaces keep their guard (`typeof window.Chart !== 'function'`). When it
trips, they hide the canvas and write one sentence into the wrapper naming where
the same numbers can be read, instead of leaving an unexplained blank box:

- `assets/js/doclinc-admin-monitoring.js` → `.dl-mon-chart-note`
- `assets/js/doclinc-diagnosis-infographic.js` → `.doclinc-analytics-chart-note`

## Updating

Replace `chart.min.js` with the `dist/` file of the new version, update the size
and hashes above, and re-run:

```
php tools/admin_panel/tests/monitoring_dashboard_unit.php
cd tools/task11_browser && TASK11_BASE_URL=http://127.0.0.1:8080/admin_menu \
  npx playwright test --config=playwright.admin.config.js -g "grafik"
```

Those tests fail if the canvas stops being drawn, which is what a half-copied
upgrade looks like.
