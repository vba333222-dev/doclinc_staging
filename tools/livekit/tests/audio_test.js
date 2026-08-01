'use strict';

const fs = require('fs');
const path = require('path');
const audioRuntime = require('../../../assets/js/doclinc-livekit-audio.js');

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

function fakeMediaNode(tagName) {
    return {
        tagName: tagName || 'AUDIO',
        autoplay: false,
        playsInline: false,
        muted: true,
        volume: 0.1,
        playCalls: 0,
        sinkCalls: [],
        play() {
            this.playCalls += 1;
            return Promise.resolve();
        },
        setSinkId(deviceId) {
            this.sinkCalls.push(deviceId);
            return Promise.resolve();
        }
    };
}

class FakeAudioContext {
    constructor() {
        this.destination = {};
        this.resumeCalls = 0;
        this.closeCalls = 0;
        this.sourceCalls = 0;
        this.gains = [];
    }

    createMediaElementSource(node) {
        this.sourceCalls += 1;
        return {
            node,
            connected: null,
            disconnected: false,
            connect(target) { this.connected = target; },
            disconnect() { this.disconnected = true; }
        };
    }

    createGain() {
        const gain = {
            gain: { value: 1 },
            connected: null,
            disconnected: false,
            connect(target) { this.connected = target; },
            disconnect() { this.disconnected = true; }
        };
        this.gains.push(gain);
        return gain;
    }

    resume() {
        this.resumeCalls += 1;
        return Promise.resolve();
    }

    close() {
        this.closeCalls += 1;
        return Promise.resolve();
    }
}

(async function run() {
    const originalNavigator = global.navigator;
    const selectedDevice = { deviceId: 'speaker-device', label: 'Speakerphone' };
    Object.defineProperty(global, 'navigator', {
        configurable: true,
        value: {
            mediaDevices: {
                selectAudioOutput() {
                    return Promise.resolve(selectedDevice);
                }
            }
        }
    });

    const node = fakeMediaNode('VIDEO');
    const contexts = [];
    const room = {
        startAudioCalls: 0,
        switchCalls: [],
        startAudio() {
            this.startAudioCalls += 1;
            return Promise.resolve();
        },
        switchActiveDevice(kind, deviceId, exact) {
            this.switchCalls.push([kind, deviceId, exact]);
            return Promise.resolve(true);
        }
    };
    const controller = audioRuntime.createController({
        getRoom: () => room,
        getRemoteMediaNodes: () => [node],
        getAudioContextConstructor: () => class extends FakeAudioContext {
            constructor() {
                super();
                contexts.push(this);
            }
        },
        speakerGain: 1.8
    });

    const configured = await controller.configureMediaElement(node);
    expect(node.autoplay && node.playsInline && node.muted === false && node.volume === 1,
        'remote_media_normalized_full_volume');
    expect(configured.played === true && node.playCalls === 1,
        'remote_media_play_requested');
    expect(controller.state().boostedNodeCount === 0,
        'normal_playback_does_not_create_audio_graph');

    const enabled = await controller.toggleSpeaker();
    expect(enabled.enabled === true && enabled.outputSelected === true && enabled.outputLabel === 'Speakerphone',
        'speaker_toggle_selects_output_from_user_gesture');
    expect(room.switchCalls.length === 1
        && room.switchCalls[0][0] === 'audiooutput'
        && room.switchCalls[0][1] === 'speaker-device'
        && room.switchCalls[0][2] === true,
        'livekit_room_switches_exact_audio_output');
    expect(node.sinkCalls.indexOf('speaker-device') !== -1,
        'remote_media_receives_selected_sink');
    expect(contexts.length === 1 && contexts[0].sourceCalls === 1
        && contexts[0].gains.length === 1 && contexts[0].gains[0].gain.value === 1.8,
        'speaker_fallback_uses_single_bounded_gain_graph');
    expect(room.startAudioCalls >= 1 && contexts[0].resumeCalls >= 1,
        'speaker_toggle_unlocks_livekit_and_audio_context');

    await controller.resumeAudio();
    expect(contexts[0].sourceCalls === 1,
        'repeated_resume_does_not_duplicate_media_element_source');

    const disabled = await controller.toggleSpeaker();
    expect(disabled.enabled === false && contexts[0].gains[0].gain.value === 1,
        'speaker_disable_restores_unity_gain');
    expect(node.sinkCalls[node.sinkCalls.length - 1] === '',
        'speaker_disable_restores_default_sink');

    expect(controller.release() === true && controller.release() === false,
        'audio_controller_release_idempotent');
    expect(controller.state().released === true && contexts[0].closeCalls === 1,
        'audio_controller_release_closes_context');

    Object.defineProperty(global, 'navigator', {
        configurable: true,
        value: { mediaDevices: {} }
    });
    const fallbackNode = fakeMediaNode('AUDIO');
    const fallbackController = audioRuntime.createController({
        getRoom: () => ({ startAudio: () => Promise.resolve() }),
        getRemoteMediaNodes: () => [fallbackNode],
        getAudioContextConstructor: () => FakeAudioContext
    });
    const fallback = await fallbackController.toggleSpeaker();
    expect(fallback.enabled === true && fallback.outputSelected === false && fallback.boosted === true,
        'unsupported_output_selection_uses_gain_fallback');
    fallbackController.release();

    const failingController = audioRuntime.createController({
        getRoom: () => ({ startAudio: () => Promise.reject(new Error('blocked')) }),
        getRemoteMediaNodes: () => []
    });
    expect(await failingController.resumeAudio() === false,
        'autoplay_rejection_is_fail_safe');
    failingController.release();

    const root = path.resolve(__dirname, '../../..');
    const threadSource = fs.readFileSync(path.join(root, 'application/modules/chat/views/thread_v.php'), 'utf8');
    const livekitAsset = threadSource.indexOf('livekit-client/dist/livekit-client.umd.min.js');
    const audioAsset = threadSource.indexOf('assets/js/doclinc-livekit-audio.js');
    expect(livekitAsset >= 0 && audioAsset > livekitAsset,
        'audio_runtime_loaded_after_livekit_sdk');
    expect(threadSource.indexOf('id="doclincCallSpeaker"') >= 0
        && threadSource.indexOf("elements.speaker.addEventListener('click', toggleSpeakerOutput)") >= 0,
        'speaker_button_bound_to_runtime');
    expect(threadSource.indexOf('controller.configureMediaElement(node)') >= 0
        && threadSource.indexOf('resumeRemoteAudio();') >= 0,
        'remote_track_and_room_lifecycle_use_audio_runtime');

    if (originalNavigator === undefined) {
        delete global.navigator;
    } else {
        Object.defineProperty(global, 'navigator', { configurable: true, value: originalNavigator });
    }

    process.stdout.write('LIVEKIT_AUDIO_TEST_PASSED=' + passed + '\n');
    process.stdout.write('LIVEKIT_AUDIO_TEST_FAILED=' + failed + '\n');
    process.exit(failed === 0 ? 0 : 1);
})().catch(function (error) {
    process.stderr.write(String(error && error.stack ? error.stack : error) + '\n');
    process.exit(1);
});
