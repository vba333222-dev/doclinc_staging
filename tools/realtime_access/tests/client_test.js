'use strict';

const assert = require('assert');
const RealtimeClient = require('../../../assets/js/doclinc-realtime-client.js');

class MockSubscription {
	constructor(channel, options) {
		this.channel = channel;
		this.options = options;
		this.handlers = {};
		this.subscribed = false;
		this.unsubscribed = false;
	}
	on(name, handler) { this.handlers[name] = handler; }
	subscribe() { this.subscribed = true; }
	unsubscribe() { this.unsubscribed = true; }
	emit(name, value) { if (this.handlers[name]) this.handlers[name](value); }
}

class MockCentrifuge {
	constructor(url, options) {
		this.url = url;
		this.options = options;
		this.handlers = {};
		this.subscriptions = [];
		this.connected = false;
		this.disconnected = false;
		MockCentrifuge.instances.push(this);
	}
	on(name, handler) { this.handlers[name] = handler; }
	connect() { this.connected = true; }
	disconnect() { this.disconnected = true; }
	newSubscription(channel, options) {
		if (MockCentrifuge.failChannel === channel) { throw new Error('synthetic_subscription_failure'); }
		const subscription = new MockSubscription(channel, options);
		this.subscriptions.push(subscription);
		return subscription;
	}
	removeSubscription(subscription) {
		this.removed = subscription;
	}
	emit(name, value) { if (this.handlers[name]) this.handlers[name](value); }
}
MockCentrifuge.instances = [];
MockCentrifuge.failChannel = '';

function response(status, body) {
	return {
		status,
		ok: status >= 200 && status < 300,
		json: async () => body
	};
}

async function flush() {
	await new Promise((resolve) => setImmediate(resolve));
	await new Promise((resolve) => setImmediate(resolve));
}

(async () => {
	let passed = 0;
	const check = (condition, message) => { assert.ok(condition, message); passed += 1; };

	let disabledFetches = 0;
	const disabled = new RealtimeClient({
		enabled: false,
		fetch: async () => { disabledFetches += 1; return response(200, {}); },
		Centrifuge: MockCentrifuge
	});
	disabled.subscribe('user:1', { snapshotUrl: '/snapshot/user', pollIntervalMs: 5000 });
	check(disabled.start() === false, 'disabled client must not connect');
	check(disabledFetches === 0, 'disabled client must not fetch');
	disabled.teardown();

	const requests = [];
	const snapshots = [];
	const timers = new Map();
	let timerId = 0;
	const fakeFetch = async (url, options) => {
		requests.push({ url, options });
		if (url === '/realtime/connection-token') return response(200, { success: true, data: { token: 'connection-token' } });
		if (url === '/realtime/subscription-token') return response(200, { success: true, data: { token: 'subscription-token' } });
		if (url === '/snapshot/request/301') return response(200, { success: true, data: { revision: 2 } });
		return response(404, {});
	};
	const client = new RealtimeClient({
		enabled: true,
		websocketUrl: 'wss://staging.example.invalid/connection/websocket',
		fetch: fakeFetch,
		Centrifuge: MockCentrifuge,
		setTimeout: (callback, delay) => { timerId += 1; timers.set(timerId, { callback, delay }); return timerId; },
		clearTimeout: (id) => timers.delete(id)
	});
	client.subscribe('request:301', {
		snapshotUrl: '/snapshot/request/301',
		pollIntervalMs: 5000,
		onSnapshot: (data, context) => snapshots.push({ data, context })
	});
	check(client.start() === true, 'enabled client starts');
	const sdk = MockCentrifuge.instances[MockCentrifuge.instances.length - 1];
	check(sdk.url === 'wss://staging.example.invalid/connection/websocket', 'websocket URL passed exactly');
	check(sdk.options.minReconnectDelay === 500 && sdk.options.maxReconnectDelay === 10000, 'bounded SDK reconnect configured');
	check(await sdk.options.getToken() === 'connection-token', 'connection token callback refreshes');
	const subscription = sdk.subscriptions[0];
	check(subscription.subscribed, 'subscription started');
	check(await subscription.options.getToken() === 'subscription-token', 'subscription token callback refreshes');
	const subscriptionRequest = requests.find((request) => request.url === '/realtime/subscription-token');
	check(subscriptionRequest.options.method === 'POST', 'subscription token uses POST');
	check(JSON.parse(subscriptionRequest.options.body).channel === 'request:301', 'subscription sends exact channel only');

	sdk.emit('connected', {});
	check(client.connected === true && timers.size === 0, 'connected client stops polling fallback');
	subscription.emit('subscribed', { recovered: false });
	await flush();
	check(snapshots.length === 1 && snapshots[0].context.reason === 'recovery_unavailable', 'recovered false refreshes authorized snapshot');
	subscription.emit('publication', { data: { event_id: 'event-1', event_type: 'request.changed', ignored: 'not-state' } });
	await flush();
	check(snapshots.length === 2 && snapshots[1].context.reason === 'invalidation', 'publication refreshes snapshot');
	subscription.emit('publication', { data: { event_id: 'event-1' } });
	await flush();
	check(snapshots.length === 2, 'duplicate event is ignored');
	subscription.emit('publication', { data: { event_type: 'missing-id' } });
	await flush();
	check(snapshots.length === 2, 'event without id is ignored');

	sdk.emit('disconnected', {});
	check(client.connected === false && timers.size === 1, 'disconnect starts polling fallback');
	const nextTimer = timers.values().next().value;
	timers.clear();
	await nextTimer.callback();
	await flush();
	check(snapshots.some((entry) => entry.context.reason === 'poll'), 'polling fallback refreshes snapshot');

	client.teardown();
	check(subscription.unsubscribed && sdk.disconnected, 'teardown unsubscribes and disconnects');
	check(timers.size === 0, 'teardown clears timers');

	const ownershipClient = new RealtimeClient({
		enabled: true,
		websocketUrl: 'wss://staging.example.invalid/connection/websocket',
		fetch: fakeFetch,
		Centrifuge: MockCentrifuge
	});
	let observed = 0;
	ownershipClient.observe('puskesmas:PKM01:ops', { invalidation: () => { observed += 1; } });
	check(ownershipClient.entries.size === 0, 'observe alone does not acquire subscription');
	const userOwnerOne = ownershipClient.acquireSubscription('user:101');
	const userOwnerTwo = ownershipClient.acquireSubscription('user:101');
	const tenantOwner = ownershipClient.acquireSubscription('puskesmas:PKM01:ops');
	check(ownershipClient.start() === true, 'ownership client starts once');
	const ownershipSdk = MockCentrifuge.instances[MockCentrifuge.instances.length - 1];
	check(MockCentrifuge.instances.filter(instance => instance === ownershipSdk).length === 1, 'one Centrifuge instance owns all channels');
	check(ownershipSdk.subscriptions.length === 2, 'exact channels create one SDK subscription each');
	check(ownershipSdk.subscriptions.filter(item => item.channel === 'user:101').length === 1, 'same channel owners share SDK subscription');
	check(await ownershipSdk.subscriptions[0].options.getToken() === 'subscription-token'
		&& await ownershipSdk.subscriptions[1].options.getToken() === 'subscription-token', 'each exact channel retains token refresh callback');
	check(userOwnerOne.release() === true && ownershipSdk.subscriptions[0].unsubscribed === false, 'first shared owner release preserves subscription');
	check(userOwnerOne.release() === false, 'repeated release is idempotent');
	check(userOwnerTwo.release() === true && ownershipSdk.subscriptions[0].unsubscribed === true, 'last shared owner release unsubscribes');
	check(ownershipSdk.removed === ownershipSdk.subscriptions[0], 'last shared owner removes SDK subscription');
	check(ownershipClient.hasSubscriptionOwners() === true && ownershipSdk.disconnected === false, 'other channel owner preserves connection');
	tenantOwner.release();
	check(ownershipSdk.subscriptions[1].unsubscribed === true && ownershipClient.hasSubscriptionOwners() === false, 'last tenant owner releases exact channel');
	ownershipClient.teardown();

	const partialClient = new RealtimeClient({
		enabled: true,
		websocketUrl: 'wss://staging.example.invalid/connection/websocket',
		fetch: fakeFetch,
		Centrifuge: MockCentrifuge
	});
	const preservedOwner = partialClient.acquireSubscription('user:202');
	partialClient.start();
	const partialSdk = MockCentrifuge.instances[MockCentrifuge.instances.length - 1];
	MockCentrifuge.failChannel = 'puskesmas:PKM02:ops';
	let partialRejected = false;
	try { partialClient.acquireSubscription('puskesmas:PKM02:ops'); } catch (error) { partialRejected = error.message === 'subscription_acquisition_failed'; }
	MockCentrifuge.failChannel = '';
	check(partialRejected && partialClient.entries.has('user:202') && !partialClient.entries.has('puskesmas:PKM02:ops'), 'partial acquisition cleans only failed owner');
	check(partialSdk.subscriptions[0].unsubscribed === false, 'partial acquisition preserves existing owner');
	preservedOwner.release();
	partialClient.teardown();

	for (const channel of ['user:0', 'request:-1', 'admin:1', 'puskesmas:DEFAULT:ops', 'request:1 extra']) {
		let rejected = false;
		try {
			const validationClient = new RealtimeClient({ enabled: false });
			validationClient.subscribe(channel, { snapshotUrl: '/snapshot' });
		} catch (error) {
			rejected = error.message === 'subscription_configuration_invalid';
		}
		check(rejected, `invalid channel rejected: ${channel}`);
	}

	process.stdout.write(`REALTIME_CLIENT_TEST_PASSED=${passed}\n`);
	process.stdout.write('REALTIME_CLIENT_TEST_FAILED=0\n');
})().catch((error) => {
	process.stderr.write(`REALTIME_CLIENT_TEST_FAILED_SAFE=${error.name}\n`);
	process.exitCode = 1;
});
