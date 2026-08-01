# LiveKit browser audio controls

The call UI uses `assets/js/doclinc-livekit-audio.js` to keep remote playback and
speaker handling independent from the inline call lifecycle.

The runtime:

- calls LiveKit `room.startAudio()` after a user-initiated join and after reconnect;
- normalizes every attached remote audio/video element to audible, unmuted, full volume;
- exposes a loudspeaker control that uses `selectAudioOutput()`, `setSinkId()`, and
  LiveKit `switchActiveDevice('audiooutput', ...)` when the browser supports them;
- applies a bounded Web Audio gain fallback when explicit output selection is not
  available;
- releases the audio graph and selected output when the call ends.

Browser output routing remains capability-dependent. Android WebView may require a
native `AudioManager` bridge to force speakerphone routing; the web runtime reports that
limitation instead of claiming a successful output switch.

`assets/js/doclinc-livekit-ringtone.js` provides the incoming-call ringtone for both the
global Warga watcher and the chat call overlay. It prefers the local, looping
`assets/audio/doclinc-ringtone.wav` asset and keeps the synthesized three-note Web Audio
pattern only as a compatibility fallback. It deduplicates repeated polling for the same
call and stops on answer, reject, expiry, page navigation, or teardown. The first page
gesture silently primes playback. Hidden tabs continue best-effort polling and no longer
silence an active ringtone merely because `visibilityState` changed.

Every newly created call session also creates one durable `incoming_call` notification.
Its normal `notification.created` invalidation makes the Warga client poll the authorized
call endpoint immediately; reused call sessions do not create another notification.
Fully closed or OS-suspended browsers still require native/OS push and a call notification
channel for guaranteed ringing.

Run the deterministic browser-contract test with:

```text
node tools/livekit/tests/audio_test.js
node tools/livekit/tests/ringtone_test.js
```
