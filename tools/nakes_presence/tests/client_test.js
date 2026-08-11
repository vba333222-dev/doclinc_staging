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
const intervals = new Map();
let intervalSequence = 0;
let fetchCalls = [];
let fetchHandler = null;
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
global.setInterval = callback => {
    const id = ++intervalSequence;
    intervals.set(id, callback);
    return id;
};
global.clearInterval = id => intervals.delete(id);
global.fetch = (url, options) => {
    fetchCalls.push({ url, options });
    if (fetchHandler) {
        return fetchHandler(url, options);
    }
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
function flush() {
    return new Promise(resolve => setImmediate(resolve));
}

(async function run() {
    const normalized = client.normalizedConfig({ enabled: true, mode: 'monitor', snapshotIntervalMs: 1 });
    check(normalized.snapshotIntervalMs === 30000, 'unsafe_interval_replaced');

    fetchCalls = [];
    check(client.create({ enabled: false, mode: 'heartbeat', heartbeatUrl: '/heartbeat' }).begin() === false, 'feature_off_no_start');
    await flush();
    check(fetchCalls.length === 0, 'feature_off_emits_nothing');

    fetchCalls = [];
    const monitor = client.create({ enabled: true, mode: 'monitor', snapshotUrl: '/snapshot', snapshotIntervalMs: 30000 });
    check(monitor.begin() === true, 'monitor_started');
    await flush();
    check(fetchCalls.length === 1 && fetchCalls[0].options.method === 'GET', 'monitor_snapshot_get_without_heartbeat');
    check(container.children.length === 2, 'snapshot_rendered_with_dom_nodes');
    check(container.children[1].children[0].attributes['data-user-id'] === '201', 'rendered_exact_user_identity');
    check(typeof windowListeners.pagehide === 'function', 'pagehide_listener_installed');
    check(windowListeners.pagehide() === true, 'monitor_pagehide_teardown');
    check(monitor.teardown() === false, 'teardown_idempotent');

    fetchCalls = [];
    fakeDocument.visibilityState = 'visible';
    const heartbeat = client.create({ enabled: true, mode: 'heartbeat', heartbeatUrl: '/heartbeat', heartbeatIntervalMs: 30000 });
    check(heartbeat.begin() === true, 'personal_heartbeat_started');
    await flush();
    check(fetchCalls.length === 1 && fetchCalls[0].options.method === 'POST', 'initial_heartbeat_is_immediate_post');
    const heartbeatInterval = Array.from(intervals.values())[0];
    check(typeof heartbeatInterval === 'function', 'periodic_heartbeat_scheduled');

    heartbeatInterval();
    await flush();
    check(fetchCalls.length === 2 && fetchCalls[1].options.method === 'POST', 'visible_periodic_heartbeat_continues');

    fakeDocument.visibilityState = 'hidden';
    heartbeatInterval();
    await flush();
    check(fetchCalls.length === 3 && fetchCalls[2].options.method === 'POST', 'hidden_periodic_heartbeat_continues');
    await heartbeat.heartbeat();
    check(fetchCalls.length === 4, 'hidden_state_does_not_suppress_direct_heartbeat');

    fakeDocument.visibilityState = 'visible';
    documentListeners.visibilitychange();
    await flush();
    check(fetchCalls.length === 5, 'visible_resume_triggers_immediate_heartbeat');

    let releasePending;
    fetchHandler = () => new Promise(resolve => {
        releasePending = () => resolve({ ok: true, json: () => Promise.resolve({ success: true, data: {} }) });
    });
    const firstPending = heartbeat.heartbeat();
    const secondPending = await heartbeat.heartbeat();
    check(fetchCalls.length === 6 && secondPending === false, 'duplicate_inflight_heartbeat_suppressed');
    releasePending();
    await firstPending;
    fetchHandler = null;

    const savedInterval = heartbeatInterval;
    const callsBeforePagehide = fetchCalls.length;
    check(windowListeners.pagehide() === true, 'heartbeat_pagehide_teardown');
    savedInterval();
    await flush();
    check(fetchCalls.length === callsBeforePagehide && intervals.size === 0, 'pagehide_stops_future_heartbeat');

    fetchCalls = [];
    const cachedHeartbeat = client.create({ enabled: true, mode: 'heartbeat', heartbeatUrl: '/heartbeat', heartbeatIntervalMs: 30000 });
    check(cachedHeartbeat.begin() === true, 'cached_personal_heartbeat_started');
    await flush();
    const cachedInterval = Array.from(intervals.values())[0];
    check(windowListeners.pagehide({ persisted: true }) === true && intervals.size === 0, 'bfcache_pagehide_pauses_personal_heartbeat');
    cachedInterval();
    await flush();
    check(fetchCalls.length === 1, 'bfcache_pause_stops_old_timer_work');
    check(windowListeners.pageshow({ persisted: true }) === true, 'bfcache_pageshow_resumes_personal_heartbeat');
    await flush();
    check(fetchCalls.length === 2 && fetchCalls[1].options.method === 'POST', 'bfcache_resume_emits_immediate_heartbeat');
    check(intervals.size === 1, 'bfcache_resume_creates_one_personal_interval');
    check(windowListeners.pageshow({ persisted: true }) === false && intervals.size === 1, 'repeated_bfcache_pageshow_is_idempotent');
    await flush();
    check(fetchCalls.length === 2, 'repeated_bfcache_pageshow_does_not_duplicate_request');
    check(windowListeners.pagehide({ persisted: true }) === true, 'second_bfcache_pagehide_pauses');
    check(windowListeners.pageshow({ persisted: true }) === true, 'second_bfcache_pageshow_resumes');
    await flush();
    check(fetchCalls.length === 3 && intervals.size === 1, 'repeated_bfcache_cycle_keeps_one_interval');
    check(windowListeners.pagehide({ persisted: false }) === true, 'ordinary_pagehide_after_cache_tears_down');
    check(!windowListeners.pageshow && intervals.size === 0, 'ordinary_pagehide_removes_cache_resume_listener');

    fetchCalls = [];
    const cachedMonitor = client.create({ enabled: true, mode: 'monitor', snapshotUrl: '/snapshot', snapshotIntervalMs: 30000 });
    check(cachedMonitor.begin() === true, 'cached_monitor_started');
    await flush();
    check(windowListeners.pagehide({ persisted: true }) === true, 'bfcache_pagehide_pauses_monitor');
    check(windowListeners.pageshow({ persisted: true }) === true, 'bfcache_pageshow_resumes_monitor');
    await flush();
    check(fetchCalls.length === 2 && fetchCalls.every(call => call.options.method === 'GET'), 'bfcache_monitor_resumes_without_heartbeat');
    check(intervals.size === 1, 'bfcache_monitor_resume_creates_one_interval');
    cachedMonitor.teardown();

    fetchCalls = [];
    const cachedDisabled = client.create({ enabled: false, mode: 'heartbeat', heartbeatUrl: '/heartbeat' });
    check(cachedDisabled.begin() === false && !windowListeners.pageshow, 'disabled_runtime_has_no_cache_resume_listener');
    check(fetchCalls.length === 0 && intervals.size === 0, 'disabled_runtime_stays_disabled');

    const unsafe = new FakeNode('div', fakeDocument);
    client.render(unsafe, { rows: [{ user_id: 2, display_name: '<img onerror=alert(1)>', is_online: false }], online_count: 0, offline_count: 1 });
    check(unsafe.children[1].children[0].children[0].children[0].textContent === '<img onerror=alert(1)>', 'display_name_rendered_as_text');

    process.stdout.write(`NAKES_PRESENCE_CLIENT_PASSED=${passed}\n`);
})().catch(error => {
    process.stderr.write(`${error.stack || error}\n`);
    process.exit(1);
});
