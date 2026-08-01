'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ringtoneRuntime = require('../../../assets/js/doclinc-livekit-ringtone.js');

let passed = 0;
let failed = 0;

function expect(condition, label) {
    if (condition) {
        passed += 1;
        process.stdout.write('PASS ' + label + '\n');
        return;
    }
    failed += 1;
    process.stdout.write('FAIL ' + label + '\n');
}

class FakeWindow {
    constructor() {
        this.listeners = {};
    }

    addEventListener(name, callback) {
        this.listeners[name] = this.listeners[name] || [];
        this.listeners[name].push(callback);
    }

    removeEventListener(name, callback) {
        this.listeners[name] = (this.listeners[name] || []).filter((candidate) => candidate !== callback);
    }

    dispatch(name) {
        (this.listeners[name] || []).slice().forEach((callback) => callback({ type: name }));
    }

    listenerCount() {
        return Object.values(this.listeners).reduce((count, listeners) => count + listeners.length, 0);
    }
}

class FakeAudioContext {
    constructor() {
        this.state = 'suspended';
        this.currentTime = 10;
        this.destination = {};
        this.allowResume = false;
        this.resumeCalls = 0;
        this.closeCalls = 0;
        this.oscillators = [];
        this.gains = [];
    }

    resume() {
        this.resumeCalls += 1;
        if (!this.allowResume) {
            return Promise.reject(new Error('gesture_required'));
        }
        this.state = 'running';
        return Promise.resolve();
    }

    createOscillator() {
        const oscillator = {
            type: '',
            frequency: { value: 0 },
            startedAt: null,
            stoppedAt: null,
            stopCalls: 0,
            disconnected: false,
            onended: null,
            connect() {},
            disconnect() { this.disconnected = true; },
            start(time) { this.startedAt = time; },
            stop(time) { this.stopCalls += 1; this.stoppedAt = time; }
        };
        this.oscillators.push(oscillator);
        return oscillator;
    }

    createGain() {
        const gain = {
            disconnected: false,
            gain: {
                value: 0,
                events: [],
                setValueAtTime(value, time) { this.events.push(['set', value, time]); },
                exponentialRampToValueAtTime(value, time) { this.events.push(['ramp', value, time]); }
            },
            connect() {},
            disconnect() { this.disconnected = true; }
        };
        this.gains.push(gain);
        return gain;
    }

    close() {
        this.closeCalls += 1;
        this.state = 'closed';
        return Promise.resolve();
    }
}

class FakeAudio {
    constructor(url) {
        this.url = url;
        this.preload = '';
        this.loop = false;
        this.volume = 1;
        this.currentTime = 0;
        this.muted = false;
        this.allowPlay = false;
        this.playCalls = 0;
        this.pauseCalls = 0;
    }

    play() {
        this.playCalls += 1;
        return this.allowPlay ? Promise.resolve() : Promise.reject(new Error('gesture_required'));
    }

    pause() {
        this.pauseCalls += 1;
    }
}

async function flush() {
    await Promise.resolve();
    await Promise.resolve();
}

(async function run() {
    const fakeWindow = new FakeWindow();
    const contexts = [];
    const intervals = [];
    const clearedIntervals = [];
    const controller = ringtoneRuntime.createController({
        windowObject: fakeWindow,
        getAudioContextConstructor: () => class extends FakeAudioContext {
            constructor() {
                super();
                contexts.push(this);
            }
        },
        setInterval(callback, delay) {
            const handle = { callback, delay };
            intervals.push(handle);
            return handle;
        },
        clearInterval(handle) {
            clearedIntervals.push(handle);
        }
    });

    expect(fakeWindow.listenerCount() === 3 && controller.state().gestureBound === true,
        'user_gesture_unlock_listeners_bound_once');

    const blocked = await controller.start('call-101');
    expect(blocked.active === true && blocked.audible === false && blocked.reason === 'awaiting_user_gesture',
        'incoming_call_waits_safely_for_browser_gesture');
    expect(contexts.length === 1 && contexts[0].oscillators.length === 0,
        'blocked_autoplay_produces_no_tone');

    contexts[0].allowResume = true;
    fakeWindow.dispatch('pointerdown');
    await flush();
    expect(controller.state().audible === true && controller.state().intervalActive === true,
        'first_user_gesture_starts_pending_ringtone');
    expect(contexts[0].oscillators.length === 3
        && contexts[0].oscillators.map((oscillator) => oscillator.frequency.value).join(',') === '659.25,783.99,987.77',
        'ringtone_uses_exact_three_note_pattern');
    expect(intervals.length === 1 && intervals[0].delay === ringtoneRuntime.DEFAULT_INTERVAL_MS,
        'ringtone_repeat_interval_is_bounded');

    const duplicate = await controller.start('call-101');
    expect(duplicate.active === true && contexts[0].oscillators.length === 3 && intervals.length === 1,
        'repeated_poll_does_not_duplicate_ringtone');

    const replacement = await controller.start('call-202');
    expect(replacement.active === true && controller.state().activeCallId === 'call-202'
        && contexts[0].oscillators.length === 6 && clearedIntervals.length === 1,
        'new_call_replaces_previous_ringtone');
    expect(controller.stop('call-101') === false && controller.state().activeCallId === 'call-202',
        'stale_call_cannot_stop_current_ringtone');
    expect(controller.stop('call-202') === true && controller.state().activeCallId === ''
        && controller.state().intervalActive === false && controller.state().activeNodeCount === 0,
        'matching_answer_or_reject_stops_all_audio');

    expect(controller.release() === true && controller.release() === false,
        'ringtone_release_is_idempotent');
    expect(fakeWindow.listenerCount() === 0 && contexts[0].closeCalls === 1 && controller.state().released === true,
        'release_removes_gesture_listeners_and_closes_context');

    const primedWindow = new FakeWindow();
    const primedContexts = [];
    const primedController = ringtoneRuntime.createController({
        windowObject: primedWindow,
        getAudioContextConstructor: () => class extends FakeAudioContext {
            constructor() {
                super();
                this.allowResume = true;
                primedContexts.push(this);
            }
        }
    });
    primedWindow.dispatch('pointerdown');
    await flush();
    expect(primedContexts.length === 1 && primedContexts[0].state === 'running'
        && primedContexts[0].oscillators.length === 0,
        'early_page_gesture_primes_audio_without_sound');
    const primedStart = await primedController.start('call-early-gesture');
    expect(primedStart.audible === true && primedContexts[0].oscillators.length === 3,
        'later_incoming_call_uses_previously_primed_context');
    primedController.release();

    const unsupported = ringtoneRuntime.createController({
        windowObject: new FakeWindow(),
        getAudioContextConstructor: () => null
    });
    const unsupportedResult = await unsupported.start('call-303');
    expect(unsupportedResult.active === true && unsupportedResult.audible === false,
        'unsupported_audio_context_fails_without_breaking_call_ui');
    unsupported.release();

    const mediaWindow = new FakeWindow();
    const mediaElements = [];
    const mediaIntervals = [];
    const mediaController = ringtoneRuntime.createController({
        windowObject: mediaWindow,
        audioUrl: '/assets/audio/doclinc-ringtone.wav',
        createAudio(url) {
            const audio = new FakeAudio(url);
            mediaElements.push(audio);
            return audio;
        },
        getAudioContextConstructor: () => null,
        setInterval(callback, delay) { mediaIntervals.push({ callback, delay }); return mediaIntervals.length; }
    });
    const mediaBlocked = await mediaController.start('call-natural');
    expect(mediaBlocked.active === true && mediaBlocked.audible === false && mediaElements.length === 1,
        'natural_ringtone_waits_for_browser_gesture_when_autoplay_blocked');
    mediaElements[0].allowPlay = true;
    mediaWindow.dispatch('pointerdown');
    await flush();
    expect(mediaController.state().audible === true && mediaController.state().mediaActive === true
        && mediaController.state().playbackMode === 'media', 'gesture_starts_natural_local_ringtone');
    expect(mediaElements[0].url === '/assets/audio/doclinc-ringtone.wav' && mediaElements[0].loop === true
        && mediaElements[0].volume === 0.86 && mediaIntervals.length === 0, 'natural_ringtone_loops_without_oscillator_timer');
    const mediaPlayCalls = mediaElements[0].playCalls;
    await mediaController.start('call-natural');
    expect(mediaElements[0].playCalls === mediaPlayCalls, 'repeated_poll_does_not_restart_natural_ringtone');
    expect(mediaController.stop('call-natural') === true && mediaElements[0].pauseCalls >= 1
        && mediaController.state().playbackMode === 'none', 'answer_or_reject_stops_natural_ringtone');
    mediaController.release();

    const root = path.resolve(__dirname, '../../..');
    const watcher = fs.readFileSync(path.join(root, 'assets/js/doclinc-livekit-incoming-watcher.js'), 'utf8');
    const thread = fs.readFileSync(path.join(root, 'application/modules/chat/views/thread_v.php'), 'utf8');
    const home = fs.readFileSync(path.join(root, 'application/modules/home/views/home_v.php'), 'utf8');
    const consultation = fs.readFileSync(path.join(root, 'application/modules/konsultasi/views/konsultasi_v.php'), 'utf8');
    const callModel = fs.readFileSync(path.join(root, 'application/modules/chat/models/Call_session_m.php'), 'utf8');
    const nakesController = fs.readFileSync(path.join(root, 'application/modules/home_nakes/controllers/Home_nakes.php'), 'utf8');
    const ringtoneAudio = fs.readFileSync(path.join(root, 'assets/audio/doclinc-ringtone.wav'));
    const notificationAudio = fs.readFileSync(path.join(root, 'assets/audio/doclinc-notification.wav'));

    expect(watcher.indexOf('startRingtone(call.call_id)') >= 0
        && watcher.indexOf('stopRingtone(callId)') >= 0
        && watcher.indexOf('releaseRingtone()') >= 0
        && watcher.indexOf('ringtoneController();\n\t\tpoll();') >= 0,
        'global_incoming_watcher_owns_full_ringtone_lifecycle');
    expect(watcher.indexOf("window.addEventListener('doclinc:notifications:new', onRealtimeNotification)") >= 0
        && watcher.indexOf("notification.event_type === 'incoming_call'") >= 0,
        'realtime_incoming_notification_triggers_immediate_call_poll');
    expect(watcher.indexOf("document.visibilityState && document.visibilityState !== 'visible'") === -1
        && watcher.indexOf("else {\n\t\t\tstopRingtone();") === -1,
        'hidden_tab_does_not_silence_or_skip_incoming_call_poll');
    expect(thread.indexOf('startIncomingRingtone(call.call_id)') >= 0
        && thread.indexOf('stopIncomingRingtone(callId)') >= 0
        && thread.indexOf('incomingRingtone();') >= 0
        && thread.indexOf("window.addEventListener('pagehide', releaseIncomingRingtone)") >= 0,
        'chat_call_overlay_owns_full_ringtone_lifecycle');

    [home, consultation].forEach(function (source, index) {
        const ringtoneAsset = source.indexOf('assets/js/doclinc-livekit-ringtone.js');
        const watcherAsset = source.indexOf('assets/js/doclinc-livekit-incoming-watcher.js');
        expect(ringtoneAsset >= 0 && watcherAsset > ringtoneAsset,
            'ringtone_asset_precedes_global_watcher_' + index);
    });
    expect(thread.indexOf('assets/js/doclinc-livekit-ringtone.js') >= 0
        && thread.indexOf('assets/js/doclinc-livekit-ringtone.js') < thread.indexOf('assets/js/doclinc-livekit-audio.js'),
        'ringtone_asset_precedes_chat_call_runtime');
    expect(!/https?:\/\/.+\.(mp3|wav|ogg)/i.test(watcher + thread),
        'ringtone_adds_no_remote_audio_dependency');
    expect(ringtoneAudio.subarray(0, 4).toString('ascii') === 'RIFF' && ringtoneAudio.length > 100000
        && notificationAudio.subarray(0, 4).toString('ascii') === 'RIFF' && notificationAudio.length > 50000,
        'natural_audio_assets_are_local_nonempty_wave_files');
    expect(callModel.indexOf('$existing->was_created = false;') >= 0
        && callModel.indexOf('$created->was_created = true;') >= 0
        && nakesController.indexOf("if (!empty($call->was_created))") >= 0
        && nakesController.indexOf("'incoming_call'") >= 0,
        'new_call_creates_one_idempotent_durable_notification');

    const watcherListeners = {};
    const documentListeners = {};
    let watcherFetches = 0;
    const watcherWindow = {
        DoclincIncomingCallWatcher: {
            enabled: true,
            incomingUrl: '/home/livekit_incoming_call',
            rejectUrl: '/home/reject_livekit_call',
            chatUrl: '/chat',
            ringtoneUrl: '/assets/audio/doclinc-ringtone.wav',
            pollMs: 3000
        },
        DoclincLivekitRingtone: { createController() { return { start() { return Promise.resolve(); }, stop() {}, release() {} }; } },
        addEventListener(name, callback) { watcherListeners[name] = callback; },
        removeEventListener(name, callback) { if (watcherListeners[name] === callback) delete watcherListeners[name]; },
        setInterval(callback, delay) { return { callback, delay }; },
        clearInterval() {},
        setTimeout() { return 1; },
        clearTimeout() {},
        location: { origin: 'https://example.invalid', href: '' }
    };
    const watcherDocument = {
        visibilityState: 'hidden',
        addEventListener(name, callback) { documentListeners[name] = callback; },
        removeEventListener(name, callback) { if (documentListeners[name] === callback) delete documentListeners[name]; },
        createElement() { throw new Error('unexpected_overlay'); },
        head: { appendChild() {} },
        body: { appendChild() {} }
    };
    vm.runInNewContext(watcher, {
        window: watcherWindow,
        document: watcherDocument,
        fetch() {
            watcherFetches += 1;
            return Promise.resolve({ ok: true, json() { return Promise.resolve({ success: true, has_incoming: false }); } });
        },
        FormData: class {},
        URL
    });
    await flush();
    expect(watcherFetches === 1, 'hidden_page_executes_initial_authorized_call_poll');
    watcherListeners['doclinc:notifications:new']({ detail: { notifications: [{ event_type: 'service_update' }] } });
    await flush();
    expect(watcherFetches === 1, 'non_call_notification_does_not_add_call_poll');
    watcherListeners['doclinc:notifications:new']({ detail: { notifications: [{ event_type: 'incoming_call' }] } });
    await flush();
    expect(watcherFetches === 2, 'incoming_call_notification_adds_immediate_hidden_page_poll');
    watcherListeners.pagehide();
    expect(!watcherListeners['doclinc:notifications:new'] && !watcherListeners.pagehide,
        'page_navigation_teardown_removes_realtime_call_listener');

    process.stdout.write('LIVEKIT_RINGTONE_TEST_PASSED=' + passed + '\n');
    process.stdout.write('LIVEKIT_RINGTONE_TEST_FAILED=' + failed + '\n');
    process.exit(failed === 0 ? 0 : 1);
})().catch(function (error) {
    process.stderr.write(String(error && error.stack ? error.stack : error) + '\n');
    process.exit(1);
});
