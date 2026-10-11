# DokLinc Visual Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Migrate active DokLinc UI surfaces to the supplied semantic icon pack and refine the LiveKit consultation shell for premium, responsive desktop/tablet/mobile presentation without changing backend authority or session behavior.

**Architecture:** Copy the supplied SVG assets into the existing static asset tree and expose semantic names through the existing PHP helper. Keep LiveKit markup and CSS scoped to the existing consultation/chat shells, with dynamic provider/session values preserved. Add focused Playwright checks for semantic asset use, responsive composition, accessibility, and failure-safe presentation; reuse the existing browser stack.

**Tech Stack:** CodeIgniter 3 PHP views/helpers, scoped CSS, browser JavaScript, Node.js `@playwright/test`.

**Spec:** `docs/development/TASK11_CLINICAL_WORKFLOW_SURFACE_IMPLEMENTATION_PLAN.md` plus the user-provided visual refresh brief.

## Global Constraints

- Base worktree from `78c9953232a82130c060a14ec94dab005b231906`.
- Browser tooling remains test-only under `tools/task11_browser/`; no root production dependency.
- `participant.svg` is reserved for LiveKit participants; `close.svg` is reserved for LiveKit shell dismissal and never End Call.
- No hard-coded `C:\Project\doclinc-assets` paths, staging/Alibaba data, Request 47/52, migrations, controller transactions, or backend LiveKit redesign.
- Preserve dynamic provider identity, existing LiveKit token/room/session endpoints, CSRF/session behavior, and healthcare-safe security copy.
- Preserve accessibility: named icon-only controls, visible focus, keyboard activation, and 44px minimum touch targets.

### Task 1: Inventory and semantic registry

**Files:**
- Create/modify: `application/helpers/clinical_workflow_assets_helper.php`
- Create: `assets/doclinc_ui/icon-pack/*.svg` (copied byte-identically from the supplied source)
- Test: `tools/task11_browser/tests/icon_registry.spec.js`

- [x] Inventory all active product icon references and classify canonical replacements, gaps, decorative instances, third-party dependencies, and LiveKit-reserved semantics.
- [x] Copy only the supplied canonical SVGs into the repository static asset tree and extend the helper with semantic keys for navigation, consultation, examination, prescription, Nakes, operations, map, attachment, camera, video, voice, mute, speaker, chat, send, more, LiveKit minimize, participants, and close.
- [x] Add a failing browser/static assertion for path resolution and reserved-icon misuse, run it to verify the expected RED, then implement the minimal registry and run it GREEN.
- [x] Record every remaining legacy icon gap with screen, semantic action, current representation, and rationale in the test report.

### Task 2: Whole-application icon migration

**Files:**
- Modify only audited active views under `application/modules/{login,home,home_nakes,konsultasi,konsultasi_nakes,chat}/views/` and `application/views/`.
- Modify: `assets/css/style.css`, `assets/css/styles.css`, `assets/css/nakes-dashboard.css` only for scoped icon sizing/focus rules.
- Test: `tools/task11_browser/tests/icon_usage.spec.js`

- [x] Replace each semantically valid Font Awesome/Bootstrap/glyph/icon-image instance on audited navigation, chat, consultation, map, and workflow-summary surfaces with the registry or supplied asset; retain a legacy representation only when the canonical pack has no correct semantic replacement.
- [x] Keep participant and close assets confined to LiveKit contexts, add `aria-hidden` for decorative icons, and add accessible names/title/focus styles for icon-only controls.
- [x] Add RED assertions for representative navigation and chat surfaces; run the focused spec before migration.
- [x] Run the focused icon suite and verify zero required asset 404s and zero non-LiveKit reserved-icon references.

### Task 3: LiveKit premium responsive shell

**Files:**
- Modify: `application/modules/chat/views/thread_v.php`, `application/modules/chat/views/chat.php`, `application/modules/konsultasi/views/chat.php`, `application/modules/konsultasi_nakes/views/chat.php`, and any already LiveKit-bearing consultation view identified by inventory.
- Modify: `assets/css/style.css` or a new scoped `assets/css/doclinc-livekit.css` loaded only by LiveKit-bearing views.
- Modify only if needed for presentation state: `assets/js/doclinc-livekit-audio.js`, `assets/js/doclinc-livekit-ringtone.js`, `assets/js/doclinc-livekit-incoming-watcher.js`.
- Test: `tools/task11_browser/tests/livekit_visual_refresh.spec.js`

- [x] Add RED checks against current LiveKit markup for header hierarchy, light media stage, floating self-preview, semantic controls, destructive End Call separation, and responsive docks.
- [x] Implement a semantic shell with dynamic consultation/provider identity, truthful neutral connection/security status, real media slots with resilient placeholder, self-preview, and scoped control dock. Use `participant.svg`, `more.svg`, `mute.svg`, `loudspeaker.svg`, `chat.svg`, `videocall.svg`, `voicecall.svg`, and `close.svg` only for their approved meanings.
- [x] Use CSS grid/flex, `dvh`/`svh`, safe-area insets, responsive breakpoints for desktop/tablet/mobile, and 44–48px touch targets without global `button`, `svg`, or `img` selectors.
- [x] Preserve existing LiveKit JS endpoints and room behavior; ensure unavailable media/realtime failure leaves the page usable and never emits an unverified E2EE claim.
- [x] Run visual/browser checks at 360x800, 390x844, 412x915, 768x1024, 820x1180, 1024x768, 1180x820, 1366x768, 1440x900, and 1536x1024.

### Task 4: Browser hygiene, accessibility, and regressions

**Files:**
- Modify: `tools/task11_browser/playwright.config.js` only if additional viewport projects are required.
- Create/modify: `tools/task11_browser/tests/visual_refresh_hygiene.spec.js`, `tools/task11_browser/tests/accessibility_visual_refresh.spec.js`.

- [x] Reuse the shared console/network collector and assert zero unexpected console errors, page errors, request failures, mixed content, and required asset 404s.
- [x] Verify accessible names, visible focus, keyboard activation, Escape behavior, responsive overflow, and modal/drawer focus behavior through focused static/browser checks.
- [x] Run existing Task 11 browser suites and affected PHP/backend regressions after UI changes; lint changed PHP files under PHP 8.1 and PHP 8.4.
- [x] Request code review, evaluate feedback with `receiving-code-review`, apply only valid in-scope fixes, and rerun the complete browser suite.

### Task 5: Evidence and handoff

- [x] Capture temporary desktop, tablet landscape, tablet portrait, 390px, and 412px screenshots outside committed artifacts.
- [x] Run `git diff --check`, `git status --short`, protected-file hashes, semantic scans, and production dependency scans.
- [x] Report asset map, justified gaps, responsive breakpoint table, LiveKit security-copy decision, test counts, review findings, changed files, and `DECISION=READY_FOR_USER_VISUAL_REVIEW`.
- [x] Do not push, merge, deploy, or freeze automatically; stop for user visual review.
