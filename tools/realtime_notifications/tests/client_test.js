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
	constructor(options) { this.options = options; this.subscription = null; this.started = false; this.stopped = false; }
	subscribe(channel, options) { this.subscription = { channel, options }; }
	start() { this.started = true; return true; }
	teardown() { this.stopped = true; }
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
	ui: { applySnapshot(data, context) { applied.push({ data, context }); } }
});

expect(runtime.start() === true, 'runtime_starts_when_enabled');
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
	runtime.teardown();
	expect(runtime.client === null && runtime.soundPrimed === false, 'teardown_disconnects_and_releases_audio');
	process.stdout.write(`REALTIME_NOTIFICATION_CLIENT_PASSED=${passed}\n`);
	process.stdout.write(`REALTIME_NOTIFICATION_CLIENT_FAILED=${failed}\n`);
	process.exit(failed === 0 ? 0 : 1);
})().catch(() => {
	process.stderr.write('FAIL client_test_unhandled\n');
	process.exit(1);
});
