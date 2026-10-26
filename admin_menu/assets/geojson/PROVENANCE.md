# kecamatan-cilegon.geojson

- What: boundary polygons for the eight kecamatan of Kota Cilegon, Banten
- Region codes: BPS `36.72.01`–`36.72.08`
- Geometry: `MultiPolygon`, WGS84 lon/lat; the third ordinate (`0`) of the
  sources is kept as-is
- CRS: the sources carry a `urn:ogc:def:crs:OGC:1.3:CRS84` member; it is omitted
  here because RFC 7946 fixes the coordinate reference system to WGS84
- File size: `66765` bytes, SHA-256 `c812211652563e128c79cd26e78b2c679e3ee55cef7a92f1d0010f31e4700d0e`
- Source: the eight tracked files `servicearea/Puskesmas*.geojson` in this
  repository (added in `5b92204`, 2026-06-28). Each source file holds exactly
  one kecamatan feature, named after the Puskesmas that serves it, but its
  geometry is the kecamatan boundary, not a service area. The `source` member
  inside this file names the same origin.

## Where each feature comes from

Features are stored in source-file order (alphabetical by Puskesmas name), not
sorted by `kode_kec`; the table below is sorted by code. Nothing reads the
order — the map joins by name.

| `kode_kec` | Kecamatan | Source file | Source vertices | Vertices here | Source parts | Parts here |
| --- | --- | --- | --- | --- | --- | --- |
| `36.72.01` | Cibeber | `PuskesmasCibeber.geojson` | 2068 | 269 | 1 | 1 |
| `36.72.02` | Cilegon | `PuskesmasCilegon.geojson` | 1957 | 300 | 1 | 1 |
| `36.72.03` | Pulomerak | `PuskesmasPulomerak.geojson` | 16394 | 789 | 5 | 5 |
| `36.72.04` | Ciwandan | `PuskesmasCiwandan.geojson` | 9848 | 408 | 2 | 2 |
| `36.72.05` | Jombang | `PuskesmasJombang.geojson` | 1216 | 181 | 1 | 1 |
| `36.72.06` | Gerogol | `PuskesmasGerogol.geojson` | 3353 | 225 | 1 | 1 |
| `36.72.07` | Purwakarta | `PuskesmasPurwakarta.geojson` | 1184 | 211 | 1 | 1 |
| `36.72.08` | Citangkil | `PuskesmasCitangkil.geojson` | 3029 | 251 | 1 | 1 |

Vertex counts include the closing duplicate of every ring.

## These are routing data, not just map data

`application/helpers/puskesmas_routing_helper.php` reads the eight source files
directly — at full resolution, not this simplified copy — and answers
`doclinc_find_puskesmas_by_service_area()` with a point-in-polygon test.
`doclinc_service_area_definitions()` maps each source file to its Puskesmas
codes, and `tools/platform_parity/tests/puskesmas_routing_coverage_test.php`
requires exactly eight definitions with every file present. The names follow the
Puskesmas that each polygon routes to; the geometry is the kecamatan boundary.

So replacing or renaming a source file changes where patients are routed, not
only how the map looks, and this merged copy must be regenerated whenever the
sources change or the map will disagree with routing.

## Derivation

Decimated at `0.0001`° (recorded in the file as `simplified_tolerance_deg`) and
rounded to five decimal places, about 1.1 m at this latitude: 39049 source
vertices become 2634. No polygon part and no ring was dropped, and every ring
is still closed.

The generator script is **not** in the repository, so what follows is verified
against the shipped file rather than a recipe to be trusted:

- every retained vertex equals a source vertex rounded to five decimals
  (2628 of 2634 match the nearest source vertex exactly; the other 6 are
  rounding ties at the fifth decimal)
- every ring is an ordered subsequence of its source ring, so vertices were
  only removed — none was moved along the boundary
- worst-case distance to the source boundary is `6.9e-6`° (mean `3.9e-6`°),
  i.e. less than one `1e-5` grid cell
- the shoelace area of each kecamatan stays within `0.09`% of its source
  (worst case Cibeber, `0.087`%), so no polygon silently grew or shrank

A plain Douglas–Peucker pass at `0.0001`° in lon/lat did **not** reproduce the
file (0 of 13 rings matched), which is why no exact command is claimed here.

## Properties kept

`kecamatan`, `kode_kec`, `nama`. Dropped: `fid`, `kode_kk`, `kode_prov`,
`kab_kota`, `provinsi`, `label` — constant for the whole city, or derivable
from the fields that were kept. `assets/js/doclinc-disease-map.js` joins the
database rows to these features by `properties.kecamatan` (case-insensitive,
trimmed), so the spelling of the eight names is part of the contract.

## Why one merged file instead of eight

One same-origin request instead of eight, and `1.40 MB` of source instead of
`65 KB` on a panel that may run on a Puskesmas network. Both this file and the
vendored Leaflet are served from this origin, so the accessibility gate's "no
failed same-origin subresource" check covers them.

## Integrity of the sources

| Source file | Size | SHA-256 |
| --- | --- | --- |
| `PuskesmasCibeber.geojson` | 74560 | `061d81bbaea373d9987cb277a2d457adb432159fc76cea33c112947eb442aac6` |
| `PuskesmasCilegon.geojson` | 70558 | `022d3088c3e5dd4c3a8585cb1021fc484a02897345bc6f6cc1b997c42f8d5bb5` |
| `PuskesmasCitangkil.geojson` | 109186 | `8373509bc476e0a8e9ab8d8356bbd4991461d8302831d234fc57e0e0b66835da` |
| `PuskesmasCiwandan.geojson` | 353832 | `4ddc57f11481ae1c6e2d80a77d81886d8bc7a6fc22d6ab8532b3700578006b9f` |
| `PuskesmasGerogol.geojson` | 120810 | `677554ba6e2779879299e771ce92ab639433c6e438d6c7d6e71be0213021297e` |
| `PuskesmasJombang.geojson` | 44115 | `f0ed422835d829cc49285dc8288a4495aa15611e9efc3ce84bc0edc3f78d639f` |
| `PuskesmasPulomerak.geojson` | 588907 | `571ae5b96eb55f9498c2ebfb74cfa9c6abf04cdf0b023ea66bb66a7dbb0918d8` |
| `PuskesmasPurwakarta.geojson` | 42990 | `41d2865fcc3eea8e0f262650bf3f0e8f328da5ccdcf9d4d1f267f4e5f885512f` |

## Licence: stated by the repository owner (2026-10-26)

The boundaries are declared to be **open data from Indonesia Geospasial**
(Ina-Geoportal, Badan Informasi Geospasial). That statement replaces the earlier
"licence unknown" status: the data is no longer treated as unlicensed, so neither
the eight sources nor this derived copy is held back from being served by the
panel or shipped with the repository.

Because a statement is not a file, two things are still worth attaching before
F6 (staging and rollout), and this section is where they belong:

1. the exact Ina-Geoportal dataset or download page the eight
   `servicearea/Puskesmas*.geojson` came from, and
2. the licence or terms-of-use text that page applies — in particular the
   attribution wording, and whether redistribution and commercial use are
   allowed.

Until (2) is attached, the panel credits the source in the map panel
("Sumber batas kecamatan: Indonesia Geospasial") rather than claiming a licence
name it has not read.

## Updating

1. replace the affected `servicearea/Puskesmas*.geojson`
2. regenerate this file at the same tolerance and rounding
3. re-check the invariants above — in particular that every ring is still a
   subsequence of its source ring, so a regeneration cannot silently redraw a
   boundary
4. update the sizes and hashes in both tables
5. run the map gate, which fails if polygons stop drawing, a kecamatan loses its
   colour class, or the klik-to-10 list stops opening — what a half-copied or
   wrongly simplified upgrade looks like:

   ```
   cd tools/task11_browser
   NODE_PATH=$PWD/node_modules npx playwright test tests/admin_panel_copy_layout.spec.js
   ```
