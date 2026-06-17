# Doclinc Master Development Prompt

> Internal working instruction for the Doclinc repository. Do not commit this file to the repository and do not include it in public or client-facing documentation.

You are working only on the Doclinc / Doklinc legacy PHP CodeIgniter 3 HMVC codebase.

Use `doclinc_development_brief_v2_2.json` as private technical context if it is available in the working directory or attached to the session. Do not commit this brief file, do not mention internal tooling in commit messages, and do not create public documentation that refers to internal development assistance or automation details.

## Scope Boundary

- The repository contains Doclinc only.
- Do not inspect, modify, refactor, document, or make assumptions about any external host/launcher application.
- Do not create tasks that require changes outside the Doclinc repository.
- Do not block Doclinc staging on unavailable external login, native shell, push, or location bridge implementation.
- If external entry is needed later, implement only a Doclinc-side adapter/stub and keep it optional.
- Existing Doclinc login/session behavior may be kept as staging/fallback unless a formal replacement contract exists.

## Project Context

- Doclinc is a healthcare consultation application built on legacy PHP CodeIgniter 3 HMVC.
- Current database direction is MariaDB/MySQL.
- Current goal is not a total rewrite. Stabilize the existing application first.
- Long-term direction is separated Doclinc service with clean Doclinc-side adapters, LiveKit for voice/video calls, LeafletJS for visit tracking map UI, Redis/WebSocket for realtime app events, and MinIO/S3-compatible storage later.
- Firebase will be removed only after inventory and replacement components are implemented safely.

## Hard Constraints

- Do not rewrite the whole application.
- Do not break existing controller/model/view flows.
- Do not rename existing form inputs unless the backend is updated and verified.
- Do not commit credentials, tokens, API keys, database passwords, patient data, or private environment files.
- Do not use real patient data in development.
- Do not scrape PCare web.
- Do not remove Firebase blindly before mapping usage and replacement behavior.
- Do not modify or require changes in external host/launcher systems.
- Do not introduce login by raw query string `user_id` or `role`.
- Do not mention internal development tooling or automation details in commit messages, reports, README, changelog, public documentation, or code comments.

## Working Mode

1. Inspect before editing.
2. Produce a concise technical plan before changing files.
3. Make small, reviewable diffs.
4. Preserve existing behavior unless the task explicitly changes it.
5. Keep all changes scoped to Doclinc.
6. After every change, provide:
   - Summary
   - Files changed
   - Manual test steps
   - Known risks
   - Next recommended step

## Immediate Priorities

### 1. Stabilize Existing CI3 Runtime

Focus on:

- session path
- cache/log path
- base_url
- database config through environment variables
- upload folder safety
- missing default profile image
- invalid href/src output such as `<div style=`
- visible PHP warnings/notices
- debug SQL/raw error output

### 2. Remove Direct Firebase Dependency Safely

Inventory all Firebase usage first:

- firebase
- firebase-messaging-sw
- messaging
- FCM/fcm
- getToken
- serverKey

Classify each usage:

- auth/session
- push notification
- realtime data/chat
- WebRTC signaling
- storage
- analytics

Rules:

- Do not delete working code before replacement is ready.
- Replace notification behavior with Doclinc backend notification records and realtime events.
- If background push is required, add only a Doclinc-side interface/adapter and document the required external contract. Do not modify external systems.

### 3. Database Direction

- Use MariaDB/MySQL as short-term primary database.
- Keep Doclinc data isolated from unrelated apps.
- Add or prepare tables for:
  - notifications
  - chat_messages if needed
  - device_sessions if needed
  - consultation_calls
  - consultation_call_events
  - doctor_visits
  - doctor_visit_location_logs
- Do not hardcode credentials.
- Add migration SQL files or documented SQL changes only if project structure supports it safely.

### 4. LiveKit Voice/Video Call Direction

- Use LiveKit for voice call and video call.
- Do not implement custom WebRTC offer/answer/ICE signaling unless explicitly required.
- Backend must generate LiveKit access tokens.
- Frontend must never receive `LIVEKIT_API_SECRET`.
- Add call lifecycle states:
  - created
  - ringing
  - accepted
  - rejected
  - missed
  - ended
  - failed
- Add backend endpoints progressively:
  - `POST /calls/create`
  - `POST /calls/{call_id}/token`
  - `POST /calls/{call_id}/accept`
  - `POST /calls/{call_id}/reject`
  - `POST /calls/{call_id}/end`
  - `POST /livekit/webhook`
- Log important call events.

### 5. Realtime Doctor Visit Tracking

- Use LeafletJS for MVP map rendering.
- LeafletJS is only the UI renderer, not routing/geocoding/tile infrastructure.
- Do not depend on Google Maps API for MVP.
- Mapbox is optional, not mandatory.
- Use OSM-compatible tile provider for staging/MVP.
- Do not rely on public OpenStreetMap tile servers for high-traffic production.
- Use backend endpoint for doctor location updates.
- Use Redis for last known location and WebSocket for active realtime broadcast.
- Use MariaDB for visit status and important location audit logs.
- Stop tracking after visit completion/cancellation.
- Do not expose location stream publicly.
- Doctor can only update location for assigned active visits.
- Patient can only subscribe to their own active visit.
- Do not assume background location tracking is reliable in plain WebView/PWA.
- If native/background location is required later, document it as an external contract outside this repository.

### 6. Optional External Entry Adapter

- External login/launcher systems are outside repository scope.
- Keep Doclinc staging runnable without external systems.
- If an external signed entry token contract is provided later, implement only the Doclinc-side consumer endpoint and validation adapter.
- Never implement login by raw `user_id` or `role` query parameter.

### 7. PCare/SATUSEHAT

- Use adapter pattern.
- Support mock/sandbox/production modes by environment variable.
- Do not use production credentials in source.
- Do not use real patient data in development.
- Do not scrape PCare web.

## Recommended First Action

Inspect current repository status and structure. Run safe inventory commands. Do not edit files yet until findings and the first small patch are proposed.

Suggested commands:

```bash
git status
git branch --show-current
find application/modules -maxdepth 3 -type f | sort | head -200
grep -Rni "firebase\|firebase-messaging-sw\|FCM\|fcm\|getToken\|serverKey" application assets admin_menu 2>/dev/null | head -200
grep -Rni "base_url\|sess_save_path\|log_path\|cache_path" application/config admin_menu/application/config 2>/dev/null
grep -Rni "last_query\|echo.*query\|var_dump\|print_r" application admin_menu/application 2>/dev/null | head -200
find uploads admin_menu/uploads -maxdepth 3 \( -name "*.php" -o -name "*.phtml" -o -name "*.phar" \) 2>/dev/null
```

## Expected First Response

- Current branch and working tree status.
- Confirmation that the work is scoped only to Doclinc.
- Important risks found.
- Firebase usage inventory summary.
- Runtime config summary.
- Suggested first patch with exact files to modify.
- Do not modify files until the plan is approved.

