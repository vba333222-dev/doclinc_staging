'use strict';

const assert = require('assert');

class FakeNode {
    constructor(tag, documentRef) {
        this.tagName = tag;
        this.ownerDocument = documentRef;
        this.children = [];
        this.attributes = {};
        this.className = '';
        this.textContent = '';
    }
    appendChild(node) { this.children.push(node); return node; }
    removeChild(node) { this.children.splice(this.children.indexOf(node), 1); return node; }
    get firstChild() { return this.children.length ? this.children[0] : null; }
    setAttribute(name, value) { this.attributes[name] = String(value); }
}

const documentListeners = {};
const windowListeners = {};
const clearedIntervals = [];
let intervalSequence = 0;
let fetchCalls = [];
const fakeDocument = {
    visibilityState: 'visible',
    createElement(tag) { return new FakeNode(tag, fakeDocument); },
    addEventListener(name, callback) { documentListeners[name] = callback; },
    removeEventListener(name, callback) { if (documentListeners[name] === callback) delete documentListeners[name]; },
    getElementById() { return container; }
};
const container = new FakeNode('div', fakeDocument);

global.document = fakeDocument;
global.addEventListener = (name, callback) => { windowListeners[name] = callback; };
global.removeEventListener = (name, callback) => { if (windowListeners[name] === callback) delete windowListeners[name]; };
global.setInterval = () => ++intervalSequence;
global.clearInterval = id => clearedIntervals.push(id);
global.fetch = (url, options) => {
    fetchCalls.push({ url, options });
    const data = options.method === 'POST'
        ? { persisted: true }
        : { rows: [{ user_id: 201, display_name: 'Nakes A', profession: 'Dokter', puskesmas_name: 'PKM A', is_online: true, last_seen_at: '2026-08-01 10:00:00' }], online_count: 1, offline_count: 0 };
    return Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true, data }) });
};

const client = require('../../../assets/js/doclinc-nakes-presence.js');
let passed = 0;
function check(condition, name) {
    assert.ok(condition, name);
    passed++;
    process.stdout.write(`PASS ${name}\n`);
}

(async function run() {
    const normalized = client.normalizedConfig({ enabled: true, mode: 'monitor', snapshotIntervalMs: 1 });
    check(normalized.snapshotIntervalMs === 30000, 'unsafe_interval_replaced');
    check(client.create({ enabled: false, mode: 'monitor' }).begin() === false, 'feature_off_no_start');

    fetchCalls = [];
    const monitor = client.create({ enabled: true, mode: 'monitor', snapshotUrl: '/snapshot', snapshotIntervalMs: 30000 });
    check(monitor.begin() === true, 'monitor_started');
    await new Promise(resolve => setImmediate(resolve));
    check(fetchCalls.length === 1 && fetchCalls[0].options.method === 'GET', 'monitor_snapshot_get');
    check(container.children.length === 2, 'snapshot_rendered_with_dom_nodes');
    check(container.children[1].children[0].attributes['data-user-id'] === '201', 'rendered_exact_user_identity');
    check(typeof windowListeners.pagehide === 'function', 'pagehide_listener_installed');
    check(windowListeners.pagehide() === true, 'pagehide_teardown');
    check(clearedIntervals.length >= 1, 'monitor_interval_cleared');
    check(monitor.teardown() === false, 'teardown_idempotent');

    fetchCalls = [];
    const heartbeat = client.create({ enabled: true, mode: 'heartbeat', heartbeatUrl: '/heartbeat', heartbeatIntervalMs: 30000 });
    check(heartbeat.begin() === true, 'heartbeat_started');
    await new Promise(resolve => setImmediate(resolve));
    check(fetchCalls.length === 1 && fetchCalls[0].options.method === 'POST', 'heartbeat_uses_post');
    fakeDocument.visibilityState = 'hidden';
    await heartbeat.heartbeat();
    check(fetchCalls.length === 1, 'hidden_page_does_not_heartbeat');
    fakeDocument.visibilityState = 'visible';
    heartbeat.teardown();

    const unsafe = new FakeNode('div', fakeDocument);
    client.render(unsafe, { rows: [{ user_id: 2, display_name: '<img onerror=alert(1)>', is_online: false }], online_count: 0, offline_count: 1 });
    check(unsafe.children[1].children[0].children[0].children[0].textContent === '<img onerror=alert(1)>', 'display_name_rendered_as_text');

    process.stdout.write(`NAKES_PRESENCE_CLIENT_PASSED=${passed}\n`);
})().catch(error => {
    process.stderr.write(`${error.stack || error}\n`);
    process.exit(1);
});
