# Leaflet 1.9.4

- Package: `leaflet`
- Version: `1.9.4` (per the `@preserve` header in `leaflet.js`)
- License: BSD-2-Clause (see `LICENSE` in this folder)
- Source repository: `https://github.com/Leaflet/Leaflet`
- Distribution files: `dist/leaflet.js`, `dist/leaflet.css`
- `leaflet.js` size: `147552` bytes, SHA-256 `db49d009c841f5ca34a888c96511ae936fd9f5533e90d8b2c4d57596f4e5641a`
- `leaflet.css` size: `14806` bytes, SHA-256 `a7837102824184820dfa198d1ebcd109ff6d0ff9a2672a074b9a1b4d147d04c6`

The two distribution files are stored unmodified; no minifier, bundler, or
patch was applied. Only the browser bundle and the license are kept — the
source map, the TypeScript sources, the examples, and the package metadata are
not runtime assets and are not included.

## Why it is vendored instead of loaded from a CDN

The admin panel is used on Puskesmas networks that do not always reach the
public internet. The disease map is the only interactive view in the panel, and
it must render the same way on a machine with no outbound access. The same
reason applies to `assets/geojson/`: both are served from this origin, so the
accessibility gate's "no failed same-origin subresource" check covers them.

## Updating

Replace both files with the `dist/` files of the new version, update the sizes
and hashes above, and re-run
`tools/task11_browser/tests/admin_panel_copy_layout.spec.js` — it fails if the
map stops drawing polygons or bubbles, which is what a half-copied upgrade
looks like.
