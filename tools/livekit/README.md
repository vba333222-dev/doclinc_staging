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

`assets/js/doclinc-livekit-ringtone.js` provides the foreground incoming-call ringtone
for both the global Warga watcher and the chat call overlay. It synthesizes a short
three-note pattern locally, deduplicates repeated polling for the same call, and stops on
answer, reject, expiry, page hide, or teardown. The runtime primes Web Audio on the next
user gesture when autoplay policy blocks a poll-triggered sound. Closed-app and true
background ringing still require native push/call integration.

Run the deterministic browser-contract test with:

```text
node tools/livekit/tests/audio_test.js
node tools/livekit/tests/ringtone_test.js
```
