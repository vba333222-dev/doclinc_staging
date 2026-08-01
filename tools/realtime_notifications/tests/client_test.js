'use strict';

const path = require('path');
const notifications = require(path.join(__dirname, '..', '..', '..', 'assets', 'js', 'doclinc-notifications.js'));

let passed = 0;
let failed = 0;
function expect(condition, label) {
	if (condition) {
		passed += 1;
		process.stdout.write(`PASS ${label}\n`);
		return;
	}
	failed += 1;
	process.stderr.write(`FAIL ${label}\n`);
}

class Storage {
	constructor() { this.values = new Map(); }
	getItem(key) { return this.values.has(key) ? this.values.get(key) : null; }
	setItem(key, value) { this.values.set(key, String(value)); }
}

class RealtimeClient {
	constructor(options) { this.options = options; this.subscription = null; this.started = false; this.stopped = false; this.owners = 0; this.releaseCount = 0; this.teardownCount = 0; }
	acquireSubscription(channel, options) {
		this.subscription = { channel, options }; this.owners += 1; let released = false; const self = this;
		return { release() { if (released) return false; released = true; self.owners -= 1; self.releaseCount += 1; return true; } };
	}
	hasSubscriptionOwners() { return this.owners > 0; }
	start() { this.started = true; return true; }
	teardown() { this.stopped = true; this.teardownCount += 1; }
}

class FakeEventTarget {
	constructor() { this.listeners = new Map(); }
	addEventListener(name, callback) { if (!this.listeners.has(name)) this.listeners.set(name, []); if (this.listeners.get(name).indexOf(callback) === -1) this.listeners.get(name).push(callback); }
	removeEventListener(name, callback) { if (this.listeners.has(name)) this.listeners.set(name, this.listeners.get(name).filter(item => item !== callback)); }
	dispatch(name) { (this.listeners.get(name) || []).slice().forEach(callback => callback({ type: name })); }
	count(name) { return (this.listeners.get(name) || []).length; }
}

let oscillatorStarts = 0;
class AudioContext {
	constructor() { this.state = 'suspended'; this.currentTime = 0; this.destination = {}; }
	async resume() { this.state = 'running'; }
	createGain() {
		return { gain: { setValueAtTime() {}, exponentialRampToValueAtTime() {} }, connect() {} };
	}
	createOscillator() {
		return { type: '', frequency: { value: 0 }, connect() {}, start() { oscillatorStarts += 1; }, stop() {} };
	}
	close() { this.state = 'closed'; }
}

class FakeAudio {
	constructor(url) {
		this.url = url;
		this.preload = '';
		this.volume = 1;
		this.currentTime = 0;
		this.muted = false;
		this.playCalls = 0;
		this.pauseCalls = 0;
	}
	play() { this.playCalls += 1; return Promise.resolve(); }
	pause() { this.pauseCalls += 1; }
}

function snapshot(ids, eventType) {
	return { success: true, data: { unread_count: ids.length, notifications: ids.map(id => ({
		notification_id: id,
		event_type: eventType || 'service_update'
	})) } };
}

function flush() {
	return new Promise(resolve => setImmediate(resolve));
}

const storage = new Storage();
const applied = [];
const lifecycleTarget = new FakeEventTarget();
const audioElements = [];
const newNotificationBatches = [];
const runtime = new notifications.NotificationRuntime({
	config: {
		enabled: true,
		websocket_url: 'wss://example.invalid/connection/websocket',
		connection_token_url: '/realtime/connection-token',
		subscription_token_url: '/realtime/subscription-token',
		snapshot_url: '/notifications/snapshot',
		sound_url: '/assets/audio/doclinc-notification.wav',
		channel: 'user:101',
		poll_interval_ms: 30000
	},
	RealtimeClient,
	Centrifuge: function () {},
	fetch: async () => ({}),
	storage,
	AudioContext,
	createAudio(url) { const audio = new FakeAudio(url); audioElements.push(audio); return audio; },
	onNewNotifications(items) { newNotificationBatches.push(items); },
	lifecycleTarget,
	ui: { applySnapshot(data, context) { applied.push({ data, context }); } }
});

expect(runtime.start() === true, 'runtime_starts_when_enabled');
expect(runtime.start() === true && lifecycleTarget.count('pagehide') === 1, 'repeat_start_does_not_duplicate_pagehide_listener');
expect(runtime.client.subscription.channel === 'user:101', 'user_channel_subscribed');
expect(runtime.client.subscription.options.snapshotUrl === '/notifications/snapshot', 'authorized_snapshot_configured');
expect(runtime.applySnapshot(snapshot([1, 2]), { reason: 'poll' }), 'initial_snapshot_accepted');
expect(oscillatorStarts === 0, 'initial_notifications_silent');

(async () => {
	expect(runtime.soundEnabled === true, 'sound_defaults_enabled_without_manual_toggle');
	lifecycleTarget.dispatch('pointerdown');
	await flush();
	expect(runtime.soundPrimed === true && audioElements.length === 1
		&& audioElements[0].url === '/assets/audio/doclinc-notification.wav', 'first_global_gesture_primes_local_natural_sound');
	runtime.applySnapshot(snapshot([3, 2, 1], 'incoming_call'), { reason: 'invalidation' });
	expect(audioElements[0].playCalls === 2 && oscillatorStarts === 0, 'new_notification_prefers_local_audio_over_oscillator');
	expect(newNotificationBatches.length === 1 && newNotificationBatches[0].length === 1
		&& newNotificationBatches[0][0].event_type === 'incoming_call', 'new_notification_event_exposes_exact_incoming_call');
	runtime.applySnapshot(snapshot([3, 2, 1]), { reason: 'recovery_unavailable' });
	runtime.applySnapshot(snapshot([3, 2, 1]), { reason: 'poll' });
	expect(audioElements[0].playCalls === 2, 'duplicate_recovery_and_poll_silent');
	runtime.applySnapshot(snapshot([4, 3, 2], 'service_update'), { reason: 'poll' });
	expect(audioElements[0].playCalls === 3, 'service_notification_plays_natural_chime');
	runtime.applySnapshot(snapshot([6, 4, 3], 'chat_message'), { reason: 'invalidation' });
	expect(audioElements[0].playCalls === 4, 'messaging_notification_plays_natural_chime');
	expect(applied.length === 6, 'snapshots_forwarded_to_existing_ui');
	expect(await runtime.toggleSound() === false && storage.getItem('doclinc.notification.sound.enabled') === '0', 'sound_control_disables_audio');
	runtime.applySnapshot(snapshot([5, 4]), { reason: 'invalidation' });
	expect(audioElements[0].playCalls === 4, 'disabled_sound_remains_silent');
	expect(await runtime.toggleSound() === true && storage.getItem('doclinc.notification.sound.enabled') === '1', 'sound_control_can_reenable_and_persist_audio');
	expect(notifications.positiveId('0') === '' && notifications.positiveId('-1') === '' && notifications.positiveId('7') === '7', 'notification_id_validation');
	expect(notifications.snapshotData({ success: false }) === null, 'invalid_snapshot_rejected');
	const off = new notifications.NotificationRuntime({ config: { enabled: false }, RealtimeClient, ui: {} });
	expect(off.start() === false && off.client === null, 'feature_off_zero_connection');
	const activeClient = runtime.client;
	lifecycleTarget.dispatch('pagehide');
	lifecycleTarget.dispatch('pagehide');
	expect(runtime.client === null && runtime.soundPrimed === false, 'pagehide_teardown_disconnects_and_releases_audio');
	expect(activeClient.releaseCount === 1 && activeClient.teardownCount === 1 && lifecycleTarget.count('pagehide') === 0
		&& lifecycleTarget.count('pointerdown') === 0 && lifecycleTarget.count('touchstart') === 0
		&& lifecycleTarget.count('keydown') === 0, 'repeated_pagehide_is_idempotent_and_removes_audio_unlock');
	runtime.teardown();
	expect(activeClient.releaseCount === 1 && activeClient.teardownCount === 1, 'manual_teardown_after_pagehide_is_idempotent');
	process.stdout.write(`REALTIME_NOTIFICATION_CLIENT_PASSED=${passed}\n`);
	process.stdout.write(`REALTIME_NOTIFICATION_CLIENT_FAILED=${failed}\n`);
	process.exit(failed === 0 ? 0 : 1);
})().catch(() => {
	process.stderr.write('FAIL client_test_unhandled\n');
	process.exit(1);
});
