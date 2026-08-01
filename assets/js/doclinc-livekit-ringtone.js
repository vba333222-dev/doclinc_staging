(function (root, factory) {
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    if (root) {
        root.DoclincLivekitRingtone = api;
    }
})(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    var DEFAULT_INTERVAL_MS = 2600;
    var TONES = [
        { frequency: 659.25, offset: 0, duration: 0.16, gain: 0.16 },
        { frequency: 783.99, offset: 0.2, duration: 0.18, gain: 0.18 },
        { frequency: 987.77, offset: 0.43, duration: 0.28, gain: 0.2 }
    ];

    function asPromise(value) {
        return value && typeof value.then === 'function' ? value : Promise.resolve(value);
    }

    function createController(options) {
        options = options || {};
        var windowObject = options.windowObject || (typeof window !== 'undefined' ? window : null);
        var setIntervalFunction = options.setInterval || (windowObject && windowObject.setInterval
            ? windowObject.setInterval.bind(windowObject)
            : setInterval);
        var clearIntervalFunction = options.clearInterval || (windowObject && windowObject.clearInterval
            ? windowObject.clearInterval.bind(windowObject)
            : clearInterval);
        var intervalMs = Number(options.intervalMs);
        if (!isFinite(intervalMs) || intervalMs < 1200 || intervalMs > 10000) {
            intervalMs = DEFAULT_INTERVAL_MS;
        }

        var context = null;
        var intervalId = null;
        var activeCallId = '';
        var activeNodes = [];
        var audible = false;
        var released = false;
        var gestureBound = false;
        var media = null;
        var mediaPlaying = false;
        var gestureEvents = ['pointerdown', 'touchstart', 'keydown'];

        function createMedia() {
            if (released || media || typeof options.audioUrl !== 'string' || options.audioUrl.trim() === '') {
                return media;
            }
            try {
                if (typeof options.createAudio === 'function') {
                    media = options.createAudio(options.audioUrl);
                } else if (windowObject && typeof windowObject.Audio === 'function') {
                    media = new windowObject.Audio(options.audioUrl);
                }
                if (media) {
                    media.preload = 'auto';
                    media.loop = true;
                    media.volume = 0.86;
                }
            } catch (error) {
                media = null;
            }
            return media;
        }

        function stopMedia() {
            if (!media) {
                return false;
            }
            var wasPlaying = mediaPlaying;
            try {
                if (typeof media.pause === 'function') {
                    media.pause();
                }
                media.currentTime = 0;
            } catch (error) {}
            mediaPlaying = false;
            return wasPlaying;
        }

        function playMedia() {
            var audio = createMedia();
            if (released || activeCallId === '' || !audio || typeof audio.play !== 'function') {
                return Promise.resolve(false);
            }
            if (mediaPlaying) {
                audible = true;
                return Promise.resolve(true);
            }
            try {
                audio.muted = false;
                audio.loop = true;
                audio.volume = 0.86;
                return asPromise(audio.play()).then(function () {
                    mediaPlaying = true;
                    audible = true;
                    return true;
                }, function () {
                    mediaPlaying = false;
                    return false;
                });
            } catch (error) {
                mediaPlaying = false;
                return Promise.resolve(false);
            }
        }

        function primeMedia() {
            var audio = createMedia();
            if (!audio || typeof audio.play !== 'function') {
                return Promise.resolve(false);
            }
            try {
                audio.muted = true;
                return asPromise(audio.play()).then(function () {
                    if (typeof audio.pause === 'function') {
                        audio.pause();
                    }
                    audio.currentTime = 0;
                    audio.muted = false;
                    mediaPlaying = false;
                    return true;
                }, function () {
                    audio.muted = false;
                    return false;
                });
            } catch (error) {
                audio.muted = false;
                return Promise.resolve(false);
            }
        }

        function contextConstructor() {
            if (typeof options.getAudioContextConstructor === 'function') {
                return options.getAudioContextConstructor();
            }
            if (!windowObject) {
                return null;
            }
            return windowObject.AudioContext || windowObject.webkitAudioContext || null;
        }

        function ensureContext() {
            if (released || context) {
                return context;
            }
            var Constructor = contextConstructor();
            if (typeof Constructor !== 'function') {
                return null;
            }
            try {
                context = new Constructor();
            } catch (error) {
                context = null;
            }
            return context;
        }

        function removeActiveNode(record) {
            activeNodes = activeNodes.filter(function (candidate) {
                return candidate !== record;
            });
        }

        function scheduleTone(tone) {
            var audioContext = context;
            if (!audioContext || audioContext.state !== 'running'
                || typeof audioContext.createOscillator !== 'function'
                || typeof audioContext.createGain !== 'function') {
                return false;
            }
            try {
                var oscillator = audioContext.createOscillator();
                var gain = audioContext.createGain();
                var startAt = Number(audioContext.currentTime || 0) + tone.offset;
                var stopAt = startAt + tone.duration;
                oscillator.type = 'sine';
                oscillator.frequency.value = tone.frequency;
                if (gain.gain && typeof gain.gain.setValueAtTime === 'function') {
                    gain.gain.setValueAtTime(0.0001, startAt);
                    if (typeof gain.gain.exponentialRampToValueAtTime === 'function') {
                        gain.gain.exponentialRampToValueAtTime(tone.gain, startAt + 0.025);
                        gain.gain.exponentialRampToValueAtTime(0.0001, stopAt);
                    } else {
                        gain.gain.value = tone.gain;
                    }
                } else if (gain.gain) {
                    gain.gain.value = tone.gain;
                }
                oscillator.connect(gain);
                gain.connect(audioContext.destination);
                var record = { oscillator: oscillator, gain: gain };
                activeNodes.push(record);
                oscillator.onended = function () {
                    try {
                        oscillator.disconnect();
                        gain.disconnect();
                    } catch (error) {}
                    removeActiveNode(record);
                };
                oscillator.start(startAt);
                oscillator.stop(stopAt);
                return true;
            } catch (error) {
                return false;
            }
        }

        function playPattern() {
            if (released || activeCallId === '' || !context || context.state !== 'running') {
                return false;
            }
            var scheduled = TONES.map(scheduleTone).filter(Boolean).length;
            audible = scheduled === TONES.length;
            return audible;
        }

        function beginLoop() {
            if (released || activeCallId === '' || !context || context.state !== 'running') {
                return false;
            }
            if (intervalId !== null) {
                return true;
            }
            playPattern();
            intervalId = setIntervalFunction(playPattern, intervalMs);
            return true;
        }

        function resume() {
            return playMedia().then(function (mediaStarted) {
                if (mediaStarted) {
                    return true;
                }
                var audioContext = ensureContext();
                if (!audioContext) {
                    return false;
                }
                var resumeResult = audioContext.state === 'running' || typeof audioContext.resume !== 'function'
                    ? Promise.resolve()
                    : asPromise(audioContext.resume());
                return resumeResult.then(function () {
                    return beginLoop();
                }, function () {
                    audible = false;
                    return false;
                });
            });
        }

        function stop(callId) {
            var normalizedCallId = callId === undefined || callId === null ? '' : String(callId);
            if (normalizedCallId !== '' && activeCallId !== normalizedCallId) {
                return false;
            }
            var hadActiveCall = activeCallId !== '' || intervalId !== null || activeNodes.length > 0 || mediaPlaying;
            activeCallId = '';
            audible = false;
            stopMedia();
            if (intervalId !== null) {
                clearIntervalFunction(intervalId);
                intervalId = null;
            }
            activeNodes.forEach(function (record) {
                try {
                    record.oscillator.onended = null;
                    record.oscillator.stop();
                    record.oscillator.disconnect();
                    record.gain.disconnect();
                } catch (error) {}
            });
            activeNodes = [];
            return hadActiveCall;
        }

        function start(callId) {
            var normalizedCallId = callId === undefined || callId === null ? '' : String(callId).trim();
            if (released || normalizedCallId === '') {
                return Promise.resolve({ active: false, audible: false, reason: released ? 'released' : 'call_id_required' });
            }
            if (activeCallId !== normalizedCallId) {
                stop();
                activeCallId = normalizedCallId;
            }
            return resume().then(function (started) {
                return {
                    active: activeCallId === normalizedCallId,
                    audible: started && audible,
                    reason: started ? 'playing' : 'awaiting_user_gesture'
                };
            });
        }

        function onUserGesture() {
            if (activeCallId !== '') {
                resume();
                return;
            }
            primeMedia();
            var audioContext = ensureContext();
            if (audioContext && audioContext.state !== 'running' && typeof audioContext.resume === 'function') {
                asPromise(audioContext.resume()).catch(function () {});
            }
        }

        function bindUserGesture() {
            if (gestureBound || !windowObject || typeof windowObject.addEventListener !== 'function') {
                return false;
            }
            gestureEvents.forEach(function (eventName) {
                windowObject.addEventListener(eventName, onUserGesture, true);
            });
            gestureBound = true;
            return true;
        }

        function unbindUserGesture() {
            if (!gestureBound || !windowObject || typeof windowObject.removeEventListener !== 'function') {
                return false;
            }
            gestureEvents.forEach(function (eventName) {
                windowObject.removeEventListener(eventName, onUserGesture, true);
            });
            gestureBound = false;
            return true;
        }

        function release() {
            if (released) {
                return false;
            }
            stop();
            released = true;
            unbindUserGesture();
            if (context && typeof context.close === 'function') {
                try {
                    context.close();
                } catch (error) {}
            }
            context = null;
            stopMedia();
            media = null;
            return true;
        }

        function state() {
            return {
                activeCallId: activeCallId,
                audible: audible,
                intervalActive: intervalId !== null,
                activeNodeCount: activeNodes.length,
                mediaActive: mediaPlaying,
                playbackMode: mediaPlaying ? 'media' : (intervalId !== null ? 'oscillator' : 'none'),
                gestureBound: gestureBound,
                released: released
            };
        }

        bindUserGesture();

        return {
            start: start,
            stop: stop,
            resume: resume,
            release: release,
            state: state
        };
    }

    return {
        createController: createController,
        DEFAULT_INTERVAL_MS: DEFAULT_INTERVAL_MS,
        TONES: TONES.slice()
    };
});
