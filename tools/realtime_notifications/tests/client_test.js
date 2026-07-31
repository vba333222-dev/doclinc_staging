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

function snapshot(ids) {
	return { success: true, data: { unread_count: ids.length, notifications: ids.map(id => ({ notification_id: id })) } };
}

const storage = new Storage();
const applied = [];
const lifecycleTarget = new FakeEventTarget();
const runtime = new notifications.NotificationRuntime({
	config: {
		enabled: true,
		websocket_url: 'wss://example.invalid/connection/websocket',
		connection_token_url: '/realtime/connection-token',
		subscription_token_url: '/realtime/subscription-token',
		snapshot_url: '/notifications/snapshot',
		channel: 'user:101',
		poll_interval_ms: 30000
	},
	RealtimeClient,
	Centrifuge: function () {},
	fetch: async () => ({}),
	storage,
	AudioContext,
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
	expect(await runtime.toggleSound() === true, 'sound_enabled_after_user_gesture');
	expect(runtime.soundPrimed === true && storage.getItem('doclinc.notification.sound.enabled') === '1', 'audio_preference_and_priming_saved');
	runtime.applySnapshot(snapshot([3, 2, 1]), { reason: 'invalidation' });
	expect(oscillatorStarts === 2, 'new_notification_plays_one_chime');
	runtime.applySnapshot(snapshot([3, 2, 1]), { reason: 'recovery_unavailable' });
	runtime.applySnapshot(snapshot([3, 2, 1]), { reason: 'poll' });
	expect(oscillatorStarts === 2, 'duplicate_recovery_and_poll_silent');
	runtime.applySnapshot(snapshot([4, 3, 2]), { reason: 'poll' });
	expect(oscillatorStarts === 4, 'polling_fallback_new_notification_chimes_once');
	expect(applied.length === 5, 'snapshots_forwarded_to_existing_ui');
	expect(await runtime.toggleSound() === false, 'sound_control_disables_audio');
	runtime.applySnapshot(snapshot([5, 4]), { reason: 'invalidation' });
	expect(oscillatorStarts === 4, 'disabled_sound_remains_silent');
	expect(notifications.positiveId('0') === '' && notifications.positiveId('-1') === '' && notifications.positiveId('7') === '7', 'notification_id_validation');
	expect(notifications.snapshotData({ success: false }) === null, 'invalid_snapshot_rejected');
	const off = new notifications.NotificationRuntime({ config: { enabled: false }, RealtimeClient, ui: {} });
	expect(off.start() === false && off.client === null, 'feature_off_zero_connection');
	const activeClient = runtime.client;
	lifecycleTarget.dispatch('pagehide');
	lifecycleTarget.dispatch('pagehide');
	expect(runtime.client === null && runtime.soundPrimed === false, 'pagehide_teardown_disconnects_and_releases_audio');
	expect(activeClient.releaseCount === 1 && activeClient.teardownCount === 1 && lifecycleTarget.count('pagehide') === 0, 'repeated_pagehide_is_idempotent');
	runtime.teardown();
	expect(activeClient.releaseCount === 1 && activeClient.teardownCount === 1, 'manual_teardown_after_pagehide_is_idempotent');
	process.stdout.write(`REALTIME_NOTIFICATION_CLIENT_PASSED=${passed}\n`);
	process.stdout.write(`REALTIME_NOTIFICATION_CLIENT_FAILED=${failed}\n`);
	process.exit(failed === 0 ? 0 : 1);
})().catch(() => {
	process.stderr.write('FAIL client_test_unhandled\n');
	process.exit(1);
});
