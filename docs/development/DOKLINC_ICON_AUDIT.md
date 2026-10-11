# DokLinc semantic icon audit

The supplied pack is copied byte-for-byte into `assets/doclinc_ui/icon-pack/` and exposed through `doclinc_ui_asset_registry()`.

## Applied replacements

- Home and Nakes navigation: `home.svg`, `nakes.svg`, `operasional.svg`.
- Consultation navigation and cards: `consultation.svg`, `nakes.svg`.
- Warga visit actions: `chat.svg`, `mapnakes.svg`.
- Clinical summaries and examination headings: `pemeriksaan.svg`.
- Prescription download and medication heading: `resep.svg`.
- Chat composer/call actions: `camera.svg`, `lampiran.svg`, `send.svg`, `videocall.svg`, `voicecall.svg`.
- LiveKit controls: `mute.svg`, `videocall.svg`, `loudspeaker.svg`, `kamera.svg`, `minimize.svg`, `participant.svg`, `more.svg`, `close.svg`.

## Explicit gaps retained

The pack has no semantically correct assets for account/profile, back/chevron, eye/password visibility, edit, delete, calendar, search/filter, refresh, download, check/status, rating stars, location crosshair, notification bell, or a distinct camera-switch glyph. Existing Font Awesome/Bootstrap representations remain for those actions until dedicated canonical assets are supplied; the LiveKit camera-switch control uses `kamera.svg` with an explicit “Ganti kamera” label as the least-ambiguous available fallback. Substituting a clinically unrelated icon would be misleading.

Third-party Bootstrap/Font Awesome assets remain only where the supplied pack has no equivalent or where the dependency is part of an existing layout contract. `participant.svg` and `close.svg` are reserved to the LiveKit shell and are not used for generic people, profile, or dialog semantics.

## Verification

`tools/task11_browser/tests/icon_registry.spec.js` verifies registry resolution and reserved mappings. `icon_usage.spec.js` verifies representative active surfaces, reserved filename confinement, and that no runtime source references the authoring-only `C:\Project\doclinc-assets` path.
