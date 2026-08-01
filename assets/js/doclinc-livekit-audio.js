(function (root, factory) {
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    if (root) {
        root.DoclincLivekitAudio = api;
    }
})(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    var DEFAULT_SPEAKER_GAIN = 1.8;

    function asPromise(value) {
        return value && typeof value.then === 'function' ? value : Promise.resolve(value);
    }

    function settled(promises) {
        return Promise.all(promises.map(function (promise) {
            return asPromise(promise).then(function (value) {
                return { status: 'fulfilled', value: value };
            }, function (reason) {
                return { status: 'rejected', reason: reason };
            });
        }));
    }

    function createController(options) {
        options = options || {};
        var speakerGain = Number(options.speakerGain);
        if (!isFinite(speakerGain) || speakerGain < 1 || speakerGain > 3) {
            speakerGain = DEFAULT_SPEAKER_GAIN;
        }

        var graphByNode = typeof WeakMap === 'function' ? new WeakMap() : null;
        var graphRecords = [];
        var audioContext = null;
        var speakerEnabled = false;
        var outputDeviceId = '';
        var outputDeviceLabel = '';
        var released = false;

        function getRoom() {
            return typeof options.getRoom === 'function' ? options.getRoom() : null;
        }

        function getMediaNodes() {
            var value = typeof options.getRemoteMediaNodes === 'function'
                ? options.getRemoteMediaNodes()
                : [];
            return Array.prototype.slice.call(value || []).filter(function (node) {
                return node && ['AUDIO', 'VIDEO'].indexOf(String(node.tagName || '').toUpperCase()) !== -1;
            });
        }

        function contextConstructor() {
            if (typeof options.getAudioContextConstructor === 'function') {
                return options.getAudioContextConstructor();
            }
            if (typeof window !== 'undefined') {
                return window.AudioContext || window.webkitAudioContext || null;
            }
            return null;
        }

        function ensureAudioContext() {
            if (released || audioContext) {
                return audioContext;
            }
            var Constructor = contextConstructor();
            if (typeof Constructor !== 'function') {
                return null;
            }
            try {
                audioContext = new Constructor();
            } catch (error) {
                audioContext = null;
            }
            return audioContext;
        }

        function graphForNode(node) {
            if (!node || !speakerEnabled) {
                return null;
            }
            if (graphByNode && graphByNode.has(node)) {
                return graphByNode.get(node);
            }
            var context = ensureAudioContext();
            if (!context || typeof context.createMediaElementSource !== 'function' || typeof context.createGain !== 'function') {
                return null;
            }
            try {
                var source = context.createMediaElementSource(node);
                var gain = context.createGain();
                source.connect(gain);
                gain.connect(context.destination);
                var record = { node: node, source: source, gain: gain };
                graphRecords.push(record);
                if (graphByNode) {
                    graphByNode.set(node, record);
                }
                return record;
            } catch (error) {
                return null;
            }
        }

        function applyGain(node) {
            var record = graphForNode(node);
            if (record && record.gain && record.gain.gain) {
                record.gain.gain.value = speakerEnabled ? speakerGain : 1;
                return true;
            }
            if (!speakerEnabled && graphByNode && graphByNode.has(node)) {
                record = graphByNode.get(node);
                if (record && record.gain && record.gain.gain) {
                    record.gain.gain.value = 1;
                }
            }
            return false;
        }

        function applySink(node) {
            if (!node || typeof node.setSinkId !== 'function') {
                return Promise.resolve(false);
            }
            return asPromise(node.setSinkId(outputDeviceId)).then(function () {
                return true;
            }, function () {
                return false;
            });
        }

        function playNode(node) {
            if (!node || typeof node.play !== 'function') {
                return Promise.resolve(false);
            }
            return asPromise(node.play()).then(function () {
                return true;
            }, function () {
                return false;
            });
        }

        function configureMediaElement(node) {
            if (released || !node) {
                return Promise.resolve({ played: false, sinkApplied: false, boosted: false });
            }
            node.autoplay = true;
            node.playsInline = true;
            node.muted = false;
            node.volume = 1;
            var boosted = applyGain(node);
            return settled([applySink(node), playNode(node)]).then(function (results) {
                return {
                    played: results[1].status === 'fulfilled' && results[1].value === true,
                    sinkApplied: results[0].status === 'fulfilled' && results[0].value === true,
                    boosted: boosted
                };
            });
        }

        function resumeAudio() {
            if (released) {
                return Promise.resolve(false);
            }
            var promises = [];
            var room = getRoom();
            if (room && typeof room.startAudio === 'function') {
                promises.push(asPromise(room.startAudio()));
            }
            var context = speakerEnabled ? ensureAudioContext() : audioContext;
            if (context && typeof context.resume === 'function') {
                promises.push(asPromise(context.resume()));
            }
            getMediaNodes().forEach(function (node) {
                promises.push(configureMediaElement(node));
            });
            return settled(promises).then(function (results) {
                return results.every(function (result) {
                    return result.status === 'fulfilled';
                });
            });
        }

        function selectOutputDevice() {
            var mediaDevices = typeof navigator !== 'undefined' ? navigator.mediaDevices : null;
            if (!mediaDevices || typeof mediaDevices.selectAudioOutput !== 'function') {
                return Promise.resolve(null);
            }
            return asPromise(mediaDevices.selectAudioOutput()).then(function (device) {
                if (!device || !device.deviceId) {
                    return null;
                }
                outputDeviceId = String(device.deviceId);
                outputDeviceLabel = device.label ? String(device.label) : '';
                return device;
            }, function () {
                return null;
            });
        }

        function switchRoomOutput() {
            var room = getRoom();
            if (!room || !outputDeviceId || typeof room.switchActiveDevice !== 'function') {
                return Promise.resolve(false);
            }
            return asPromise(room.switchActiveDevice('audiooutput', outputDeviceId, true)).then(function (result) {
                return result !== false;
            }, function () {
                return false;
            });
        }

        function setSpeakerEnabled(enabled, promptForOutput) {
            if (released) {
                return Promise.resolve({ enabled: false, released: true });
            }
            speakerEnabled = enabled === true;
            if (!speakerEnabled) {
                outputDeviceId = '';
                outputDeviceLabel = '';
                graphRecords.forEach(function (record) {
                    if (record && record.gain && record.gain.gain) {
                        record.gain.gain.value = 1;
                    }
                });
                return resumeAudio().then(function () {
                    return {
                        enabled: false,
                        outputSelected: false,
                        boosted: graphRecords.length > 0
                    };
                });
            }

            var selection = promptForOutput === true ? selectOutputDevice() : Promise.resolve(null);
            return selection.then(function (device) {
                return switchRoomOutput().then(function (roomSwitched) {
                    return resumeAudio().then(function () {
                        return {
                            enabled: true,
                            outputSelected: !!device,
                            outputLabel: outputDeviceLabel,
                            roomSwitched: roomSwitched,
                            boosted: graphRecords.length > 0
                        };
                    });
                });
            });
        }

        function toggleSpeaker() {
            return setSpeakerEnabled(!speakerEnabled, !speakerEnabled);
        }

        function release() {
            if (released) {
                return false;
            }
            released = true;
            graphRecords.forEach(function (record) {
                try {
                    if (record.source && typeof record.source.disconnect === 'function') {
                        record.source.disconnect();
                    }
                    if (record.gain && typeof record.gain.disconnect === 'function') {
                        record.gain.disconnect();
                    }
                } catch (error) {}
            });
            graphRecords = [];
            if (audioContext && typeof audioContext.close === 'function') {
                try {
                    audioContext.close();
                } catch (error) {}
            }
            audioContext = null;
            outputDeviceId = '';
            outputDeviceLabel = '';
            speakerEnabled = false;
            return true;
        }

        function state() {
            return {
                enabled: speakerEnabled,
                outputDeviceId: outputDeviceId,
                outputDeviceLabel: outputDeviceLabel,
                boostedNodeCount: graphRecords.length,
                released: released
            };
        }

        return {
            configureMediaElement: configureMediaElement,
            resumeAudio: resumeAudio,
            setSpeakerEnabled: setSpeakerEnabled,
            toggleSpeaker: toggleSpeaker,
            release: release,
            state: state
        };
    }

    return {
        createController: createController,
        DEFAULT_SPEAKER_GAIN: DEFAULT_SPEAKER_GAIN
    };
});
