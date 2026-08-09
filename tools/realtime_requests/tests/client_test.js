'use strict';
const path = require('path');
const RealtimeClient = require(path.join(__dirname, '..', '..', '..', 'assets', 'js', 'doclinc-realtime-client.js'));
const requests = require(path.join(__dirname, '..', '..', '..', 'assets', 'js', 'doclinc-requests.js'));
const notifications = require(path.join(__dirname, '..', '..', '..', 'assets', 'js', 'doclinc-notifications.js'));

let passed = 0; let failed = 0;
function expect(value, label) { if (value) { passed++; process.stdout.write(`PASS ${label}\n`); } else { failed++; process.stderr.write(`FAIL ${label}\n`); } }
function response(body) { return { ok: true, status: 200, json: async () => body }; }
async function flush() { await new Promise(resolve => setImmediate(resolve)); await new Promise(resolve => setImmediate(resolve)); }

class MockSubscription {
	constructor(channel, options) { this.channel = channel; this.options = options; this.handlers = {}; this.unsubscribed = false; this.unsubscribeCount = 0; }
	on(name, callback) { this.handlers[name] = callback; }
	subscribe() { this.subscribed = true; }
	unsubscribe() { this.unsubscribed = true; this.unsubscribeCount++; }
	emit(name, value) { if (this.handlers[name]) this.handlers[name](value); }
}
class MockCentrifuge {
	constructor(url, options) { this.url = url; this.options = options; this.handlers = {}; this.subscriptions = []; this.removed = []; MockCentrifuge.instances.push(this); }
	on(name, callback) { this.handlers[name] = callback; }
	connect() { this.connected = true; }
	disconnect() { this.disconnected = true; this.disconnectCount = (this.disconnectCount || 0) + 1; }
	newSubscription(channel, options) {
		if (MockCentrifuge.failChannel === channel) { throw new Error('synthetic_acquisition_failure'); }
		const subscription = new MockSubscription(channel, options); this.subscriptions.push(subscription); return subscription;
	}
	removeSubscription(subscription) { this.removed.push(subscription); }
	emit(name, value) { if (this.handlers[name]) this.handlers[name](value); }
}
MockCentrifuge.instances = []; MockCentrifuge.failChannel = '';

class FakeEventTarget {
	constructor() { this.listeners = new Map(); }
	addEventListener(name, callback) {
		if (!this.listeners.has(name)) this.listeners.set(name, []);
		if (this.listeners.get(name).indexOf(callback) === -1) this.listeners.get(name).push(callback);
	}
	removeEventListener(name, callback) {
		if (!this.listeners.has(name)) return;
		this.listeners.set(name, this.listeners.get(name).filter(item => item !== callback));
	}
	dispatch(name, reverse) {
		const callbacks = (this.listeners.get(name) || []).slice();
		if (reverse) callbacks.reverse();
		callbacks.forEach(callback => callback({ type: name }));
	}
	count(name) { return (this.listeners.get(name) || []).length; }
}

const tokenResponse = { success: true, data: { token: 'synthetic-token' } };
const notificationSnapshot = { success: true, data: { unread_count: 0, notifications: [] } };

(async () => {
	let requestFetchCount = 0; let changes = 0;
	const requestSnapshots = [
		{ success: true, data: { requests: [{ request_id: 1 }], fingerprint: 'one' } },
		{ success: true, data: { requests: [{ request_id: 2 }], fingerprint: 'two' } },
		{ success: true, data: { requests: [{ request_id: 2 }], fingerprint: 'two' } }
	];
	const fetcher = async (url) => {
		if (url.indexOf('token') !== -1) return response(tokenResponse);
		if (url === '/notifications/snapshot') return response(notificationSnapshot);
		if (url === '/realtime/requests/snapshot') return response(requestSnapshots[Math.min(requestFetchCount++, requestSnapshots.length - 1)]);
		return { ok: false, status: 404, json: async () => ({}) };
	};
	const notificationRuntime = new notifications.NotificationRuntime({
		config: { enabled: true, websocket_url: 'wss://example.invalid/connection/websocket', connection_token_url: '/realtime/connection-token', subscription_token_url: '/realtime/subscription-token', snapshot_url: '/notifications/snapshot', channel: 'user:101', poll_interval_ms: 30000 },
		RealtimeClient, Centrifuge: MockCentrifuge, fetch: fetcher,
		ui: { applySnapshot() {} }
	});
	expect(notificationRuntime.start() === true, 'notification_runtime_starts_shared_connection');
	const shared = notificationRuntime.client;
	const sdk = MockCentrifuge.instances[0];
	expect(MockCentrifuge.instances.length === 1 && sdk.subscriptions.length === 1 && sdk.subscriptions[0].channel === 'user:101', 'notification_user_subscription_created');

	let observedOnly = 0;
	const observerOnly = shared.observe('request:999', { invalidation() { observedOnly++; } });
	expect(sdk.subscriptions.length === 1, 'observe_only_is_not_subscription');
	shared.unobserve(observerOnly);

	const commandRuntime = new requests.RequestRuntime({
		config: { enabled: true, channel: 'puskesmas:PKM01:ops', snapshot_url: '/realtime/requests/snapshot', poll_interval_ms: 30000 },
		RealtimeClient, sharedClient: shared, fetch: fetcher, onChange() { changes++; }
	});
	expect(commandRuntime.start() === true, 'command_center_request_runtime_starts');
	await flush();
	expect(MockCentrifuge.instances.length === 1 && sdk.subscriptions.length === 2, 'two_channels_share_one_Centrifuge_connection');
	expect(sdk.subscriptions.filter(item => item.channel === 'puskesmas:PKM01:ops').length === 1, 'tenant_channel_sdk_subscription_created_once');
	expect(await sdk.subscriptions[0].options.getToken() === 'synthetic-token' && await sdk.subscriptions[1].options.getToken() === 'synthetic-token', 'per_channel_subscription_token_refresh_retained');
	expect(commandRuntime.fingerprint === 'one' && changes === 0, 'initial_snapshot_does_not_reload');

	const tenantSubscription = sdk.subscriptions.find(item => item.channel === 'puskesmas:PKM01:ops');
	tenantSubscription.emit('publication', { data: { event_id: 'request-event-1', event_type: 'request.pic_assigned' } });
	tenantSubscription.emit('publication', { data: { event_id: 'request-event-1', event_type: 'request.pic_assigned' } });
	await flush();
	expect(changes === 1 && requestFetchCount === 2, 'duplicate_event_id_refreshes_once');
	tenantSubscription.emit('subscribed', { recovered: false });
	tenantSubscription.emit('subscribed', { recovered: false });
	await flush();
	expect(changes === 1 && requestFetchCount === 4, 'recovery_snapshot_identical_does_not_reload');
	expect(changes === 1, 'same_snapshot_change_schedules_single_reload');

	commandRuntime.teardown();
	expect(tenantSubscription.unsubscribed && sdk.subscriptions[0].unsubscribed === false, 'request_teardown_preserves_notification_subscription');
	expect(!sdk.disconnected && shared.hasSubscriptionOwners(), 'request_teardown_preserves_shared_connection');

	const sameChannelRuntime = new requests.RequestRuntime({
		config: { enabled: true, channel: 'user:101', snapshot_url: '/realtime/requests/snapshot', poll_interval_ms: 30000 },
		RealtimeClient, sharedClient: shared, fetch: fetcher, onChange() {}
	});
	expect(sameChannelRuntime.start() === true && sdk.subscriptions.length === 2, 'same_channel_second_owner_reuses_sdk_subscription');
	sameChannelRuntime.teardown();
	expect(sdk.subscriptions[0].unsubscribed === false, 'same_channel_request_release_preserves_notification_owner');

	MockCentrifuge.failChannel = 'puskesmas:PKM02:ops';
	const failedRuntime = new requests.RequestRuntime({
		config: { enabled: true, channel: 'puskesmas:PKM02:ops', snapshot_url: '/realtime/requests/snapshot', poll_interval_ms: 30000 },
		RealtimeClient, sharedClient: shared, fetch: fetcher
	});
	expect(failedRuntime.start() === false, 'partial_request_acquisition_fails_closed');
	MockCentrifuge.failChannel = '';
	expect(shared.entries.has('user:101') && !shared.entries.has('puskesmas:PKM02:ops') && sdk.subscriptions[0].unsubscribed === false, 'partial_failure_preserves_notification_owner');

	notificationRuntime.teardown();
	expect(sdk.subscriptions[0].unsubscribed && sdk.disconnected, 'notification_last_owner_teardown_disconnects_idle_client');

	const notificationRuntimeTwo = new notifications.NotificationRuntime({
		config: { enabled: true, websocket_url: 'wss://example.invalid/connection/websocket', connection_token_url: '/realtime/connection-token', subscription_token_url: '/realtime/subscription-token', snapshot_url: '/notifications/snapshot', channel: 'user:303', poll_interval_ms: 30000 },
		RealtimeClient, Centrifuge: MockCentrifuge, fetch: fetcher, ui: { applySnapshot() {} }
	});
	notificationRuntimeTwo.start();
	const reverseShared = notificationRuntimeTwo.client;
	const reverseSdk = MockCentrifuge.instances[MockCentrifuge.instances.length - 1];
	const reverseRequest = new requests.RequestRuntime({
		config: { enabled: true, channel: 'puskesmas:PKM03:ops', snapshot_url: '/realtime/requests/snapshot', poll_interval_ms: 30000 },
		RealtimeClient, sharedClient: reverseShared, fetch: fetcher, onChange() {}
	});
	reverseRequest.start(); await flush();
	notificationRuntimeTwo.teardown();
	expect(!reverseSdk.disconnected && reverseShared.hasSubscriptionOwners()
		&& reverseSdk.subscriptions.find(item => item.channel === 'puskesmas:PKM03:ops').unsubscribed === false,
		'notification_teardown_preserves_request_channel_owner');
	reverseRequest.teardown();
	expect(reverseSdk.disconnected, 'final_request_owner_disconnects_idle_shared_connection');
	const off = new requests.RequestRuntime({ config: { enabled: false }, RealtimeClient, fetch: fetcher });
	expect(off.start() === false, 'request_flag_off_zero_connection');

	let receiverTimer = null;
	let receiverTimerId = 0;
	let receiverSetCalls = 0;
	let receiverClearCalls = 0;
	let receiverObserver = null;
	const receiverSharedClient = {
		connected: false,
		acquireSubscription() { return { release() { return true; } }; },
		observe(channel, callbacks) { receiverObserver = callbacks; return 1; },
		unobserve() {},
		hasSubscriptionOwners() { return true; }
	};
	function receiverSensitiveSetTimer(callback, delay) {
		if (this !== undefined) { throw new TypeError('set_timer_receiver_invalid'); }
		receiverSetCalls += 1;
		receiverTimerId += 1;
		receiverTimer = { id: receiverTimerId, callback, delay };
		return receiverTimerId;
	}
	function receiverSensitiveClearTimer(id) {
		if (this !== undefined) { throw new TypeError('clear_timer_receiver_invalid'); }
		receiverClearCalls += 1;
		if (receiverTimer && receiverTimer.id === id) { receiverTimer = null; }
	}
	const receiverRequest = new requests.RequestRuntime({
		config: { enabled: true, channel: 'puskesmas:PKM09:ops', snapshot_url: '/realtime/requests/snapshot', poll_interval_ms: 30000 },
		RealtimeClient, sharedClient: receiverSharedClient, fetch: fetcher,
		setTimeout: receiverSensitiveSetTimer, clearTimeout: receiverSensitiveClearTimer
	});
	let receiverStartError = null;
	try { receiverRequest.start(); } catch (error) { receiverStartError = error; }
	expect(receiverStartError === null && receiverSetCalls === 1 && receiverTimer !== null,
		'request_polling_accepts_receiver_sensitive_injected_timer');
	receiverObserver.connected();
	expect(receiverClearCalls === 1 && receiverTimer === null, 'request_connected_state_clears_receiver_sensitive_timer');
	receiverObserver.disconnected();
	expect(receiverSetCalls === 2 && receiverTimer !== null, 'request_disconnected_state_restarts_receiver_sensitive_timer');
	receiverRequest.teardown();
	expect(receiverClearCalls === 2 && receiverTimer === null, 'request_teardown_clears_receiver_sensitive_timer');

	function notificationFor(target, channel, customFetch, ui) {
		return new notifications.NotificationRuntime({
			config: { enabled: true, websocket_url: 'wss://example.invalid/connection/websocket', connection_token_url: '/realtime/connection-token', subscription_token_url: '/realtime/subscription-token', snapshot_url: '/notifications/snapshot', channel, poll_interval_ms: 30000 },
			RealtimeClient, Centrifuge: MockCentrifuge, fetch: customFetch || fetcher,
			lifecycleTarget: target, ui: ui || { applySnapshot() {} }
		});
	}
	function requestFor(target, sharedClient, channel, customFetch, onChange) {
		return new requests.RequestRuntime({
			config: { enabled: true, websocket_url: 'wss://example.invalid/connection/websocket', connection_token_url: '/realtime/connection-token', subscription_token_url: '/realtime/subscription-token', channel, snapshot_url: '/realtime/requests/snapshot', poll_interval_ms: 30000 },
			RealtimeClient, Centrifuge: MockCentrifuge, sharedClient, fetch: customFetch || fetcher, lifecycleTarget: target,
			onChange: onChange || function () {}
		});
	}

	for (const reverse of [false, true]) {
		const target = new FakeEventTarget();
		const notification = notificationFor(target, reverse ? 'user:402' : 'user:401');
		notification.start();
		const pageShared = notification.client;
		const pageRequest = requestFor(target, pageShared, reverse ? 'puskesmas:PKM05:ops' : 'puskesmas:PKM04:ops');
		pageRequest.start(); await flush();
		const pageSdk = MockCentrifuge.instances[MockCentrifuge.instances.length - 1];
		expect(target.count('pagehide') === 2, reverse ? 'reverse_order_two_pagehide_handlers' : 'production_order_two_pagehide_handlers');
		target.dispatch('pagehide', reverse);
		expect(pageSdk.subscriptions.every(item => item.unsubscribeCount === 1) && pageSdk.removed.length === 2,
			reverse ? 'request_then_notification_pagehide_releases_exactly_once' : 'notification_then_request_pagehide_releases_exactly_once');
		expect(pageSdk.disconnectCount === 1 && target.count('pagehide') === 0,
			reverse ? 'reverse_pagehide_single_disconnect_and_listener_cleanup' : 'production_pagehide_single_disconnect_and_listener_cleanup');
		target.dispatch('pagehide', reverse);
		expect(pageSdk.disconnectCount === 1 && pageSdk.subscriptions.every(item => item.unsubscribeCount === 1),
			reverse ? 'reverse_repeated_pagehide_idempotent' : 'production_repeated_pagehide_idempotent');
	}

	const sharedTarget = new FakeEventTarget();
	const sharedNotification = notificationFor(sharedTarget, 'user:501');
	sharedNotification.start();
	const sharedRequest = requestFor(sharedTarget, sharedNotification.client, 'user:501');
	sharedRequest.start(); await flush();
	const sharedSdkPage = MockCentrifuge.instances[MockCentrifuge.instances.length - 1];
	expect(sharedSdkPage.subscriptions.length === 1 && sharedTarget.count('pagehide') === 2, 'shared_channel_page_has_two_owners_one_subscription');
	sharedNotification.teardown();
	expect(sharedSdkPage.subscriptions[0].unsubscribeCount === 0 && sharedTarget.count('pagehide') === 1, 'manual_notification_teardown_preserves_request_and_removes_listener');
	sharedTarget.dispatch('pagehide');
	expect(sharedSdkPage.subscriptions[0].unsubscribeCount === 1 && sharedSdkPage.removed.length === 1 && sharedSdkPage.disconnectCount === 1, 'manual_then_pagehide_final_release_exact');
	sharedRequest.teardown();
	expect(sharedSdkPage.subscriptions[0].unsubscribeCount === 1 && sharedSdkPage.disconnectCount === 1, 'pagehide_then_manual_teardown_idempotent');

	const partialTarget = new FakeEventTarget();
	const partialNotification = notificationFor(partialTarget, 'user:601');
	partialNotification.start();
	MockCentrifuge.failChannel = 'puskesmas:PKM06:ops';
	const partialRequest = requestFor(partialTarget, partialNotification.client, 'puskesmas:PKM06:ops');
	expect(partialRequest.start() === false && partialTarget.count('pagehide') === 1, 'partial_request_initialization_adds_no_pagehide_listener');
	MockCentrifuge.failChannel = '';
	const partialPageSdk = MockCentrifuge.instances[MockCentrifuge.instances.length - 1];
	partialTarget.dispatch('pagehide');
	expect(partialPageSdk.subscriptions[0].unsubscribeCount === 1 && partialPageSdk.disconnectCount === 1, 'partial_initialization_pagehide_releases_existing_owner_only');

	let deferredResolve;
	let deferredUiUpdates = 0;
	const deferredTarget = new FakeEventTarget();
	const deferredFetch = async (url) => {
		if (url.indexOf('token') !== -1) return response(tokenResponse);
		return new Promise(resolve => { deferredResolve = resolve; });
	};
	const deferredNotification = notificationFor(deferredTarget, 'user:701', deferredFetch, { applySnapshot() { deferredUiUpdates++; } });
	deferredNotification.start();
	const deferredSdk = MockCentrifuge.instances[MockCentrifuge.instances.length - 1];
	deferredSdk.emit('connected', {});
	deferredSdk.subscriptions[0].emit('publication', { data: { event_id: 'pending-event' } });
	await flush();
	deferredTarget.dispatch('pagehide');
	deferredResolve(response(notificationSnapshot));
	await flush();
	expect(deferredUiUpdates === 0, 'pending_notification_snapshot_after_pagehide_is_ignored');
	expect(deferredSdk.disconnectCount === 1 && deferredSdk.subscriptions[0].unsubscribeCount === 1, 'pending_snapshot_pagehide_cleanup_exact');

	let deferredRequestResolve;
	let deferredRequestChanges = 0;
	const deferredRequestTarget = new FakeEventTarget();
	const pendingRequest = requestFor(deferredRequestTarget, null, 'user:801', async () => new Promise(resolve => { deferredRequestResolve = resolve; }), function () { deferredRequestChanges++; });
	const pendingRequestStarted = pendingRequest.start();
	expect(pendingRequestStarted === true, 'pending_request_runtime_started');
	expect(deferredRequestTarget.count('pagehide') === 1, 'pending_request_runtime_pagehide_bound');
	await flush();
	deferredRequestTarget.dispatch('pagehide');
	deferredRequestResolve(response({ success: true, data: { requests: [{ request_id: 9 }], fingerprint: 'late' } }));
	await flush();
	expect(deferredRequestChanges === 0 && pendingRequest.fingerprint === '', 'pending_request_snapshot_after_pagehide_is_ignored');

	process.stdout.write(`REALTIME_REQUEST_CLIENT_PASSED=${passed}\nREALTIME_REQUEST_CLIENT_FAILED=${failed}\n`);
	process.exit(failed === 0 ? 0 : 1);
})().catch(() => { process.stderr.write('FAIL client_test_unhandled\n'); process.exit(1); });
