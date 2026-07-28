'use strict';

const assert = require('assert');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const RealtimeClient = require('../../../assets/js/doclinc-realtime-client.js');

const root = path.resolve(__dirname, '../../..');
const bundlePath = path.join(root, 'assets/vendor/centrifuge/5.7.0/centrifuge.js');
const licensePath = path.join(root, 'assets/vendor/centrifuge/5.7.0/LICENSE');
const bundle = fs.readFileSync(bundlePath);
const license = fs.readFileSync(licensePath);
const source = bundle.toString('utf8');
let passed = 0;

function check(condition, name) {
	assert.ok(condition, name);
	passed += 1;
}

check(crypto.createHash('sha256').update(bundle).digest('hex') === 'eab2d3d51ea48eb7f6919b97ecfc19986fe5f29a69b25851780cb586c0f38a20', 'bundle checksum');
check(crypto.createHash('sha256').update(license).digest('hex') === '8212f3cd2bcd2cab7e66f5337926e11c3f99822d2016ae41fb1b4e084e5c5128', 'license checksum');
check(!/^\s*</.test(source), 'bundle is not HTML');
check(!/https?:\/\//i.test(source), 'bundle has no remote URL');
check(!/\beval\s*\(/.test(source), 'bundle has no eval call');
check(!/\bimport\s*\(/.test(source), 'bundle has no dynamic import');

const context = {
	console: { log() {}, warn() {}, error() {} },
	setTimeout,
	clearTimeout,
	TextEncoder,
	TextDecoder,
	WebSocket: function WebSocket() {},
	fetch: async () => { throw new Error('network_not_allowed'); }
};
context.globalThis = context;
vm.createContext(context);
vm.runInContext(source, context, { filename: 'centrifuge.js', timeout: 5000 });

const Centrifuge = context.Centrifuge;
check(typeof Centrifuge === 'function', 'browser global constructor');
check(typeof context.UnauthorizedError === 'undefined', 'no standalone unauthorized global');
check(typeof Centrifuge.UnauthorizedError === 'function', 'static unauthorized error export');

const sdkClient = new Centrifuge('wss://example.invalid/connection/websocket', {
	getToken: async () => 'synthetic-token'
});
for (const event of ['connected', 'disconnected', 'publication', 'subscribed']) {
	check(source.includes(`"${event}"`), `event contract ${event}`);
}
for (const method of ['on', 'connect', 'disconnect', 'newSubscription', 'removeSubscription']) {
	check(typeof sdkClient[method] === 'function', `client API ${method}`);
}
const sdkSubscription = sdkClient.newSubscription('user:1', {
	getToken: async () => 'synthetic-token'
});
for (const method of ['on', 'subscribe', 'unsubscribe']) {
	check(typeof sdkSubscription[method] === 'function', `subscription API ${method}`);
}
sdkSubscription.unsubscribe();
sdkClient.removeSubscription(sdkSubscription);
sdkClient.disconnect();

const sharedClient = new RealtimeClient({
	enabled: false,
	Centrifuge,
	fetch: async () => ({ status: 403, ok: false, json: async () => ({}) })
});
check(sharedClient.Centrifuge === Centrifuge, 'shared client accepts SDK constructor');
check(sharedClient.UnauthorizedError === Centrifuge.UnauthorizedError, 'shared client uses static unauthorized error');

(async () => {
	let rejected = false;
	try {
		await sharedClient._requestToken('/realtime/connection-token', { method: 'GET' });
	} catch (error) {
		rejected = error instanceof Centrifuge.UnauthorizedError;
	}
	check(rejected, 'HTTP 403 maps to SDK unauthorized error');
	sharedClient.teardown();
	process.stdout.write(`REALTIME_VENDOR_TEST_PASSED=${passed}\n`);
	process.stdout.write('REALTIME_VENDOR_TEST_FAILED=0\n');
})().catch((error) => {
	process.stderr.write(`REALTIME_VENDOR_TEST_FAILED_SAFE=${error.name}\n`);
	process.exitCode = 1;
});
