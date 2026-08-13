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
const bodyClasses = new Set();
const clearedIntervals = [];
let intervalSequence = 0;
let fetchCalls = [];
let deferredResolve = null;
const fakeDocument = {
  visibilityState: 'visible',
  body: { classList: { add(name) { bodyClasses.add(name); } } },
  createElement(tag) { return new FakeNode(tag, fakeDocument); },
  addEventListener(name, callback) { documentListeners[name] = callback; },
  removeEventListener(name, callback) { if (documentListeners[name] === callback) delete documentListeners[name]; },
  getElementById(id) { return id === 'doclincPuskesmasOperations' ? container : null; }
};
const container = new FakeNode('div', fakeDocument);

global.document = fakeDocument;
global.addEventListener = (name, callback) => { windowListeners[name] = callback; };
global.removeEventListener = (name, callback) => { if (windowListeners[name] === callback) delete windowListeners[name]; };
global.setInterval = () => ++intervalSequence;
global.clearInterval = id => clearedIntervals.push(id);
global.AbortController = class { constructor() { this.signal = {}; this.aborted = false; } abort() { this.aborted = true; } };
global.fetch = (url, options) => {
  fetchCalls.push({ url, options });
  const data = {
    summary: { pending_requests: 2, unassigned_requests: 1, available_staff: 1, accepted_requests: 3 },
    staff: [{ staff_id: 11, user_id: 201, display_name: '<img onerror=alert(1)>', profession: 'Dokter', is_online: true, last_seen_age_seconds: 12, workload_state: 'busy', active_request_count: 2 }],
    exceptions: [{ request_id: 99, type: 'unassigned_request', visit_status_label: 'Belum dimulai' }],
    requests: [{ request_id: 7, queue_number: 1, service_date: '2026-08-13', patient_name: '<script>alert(1)</script>', patient_nik_masked: '3671********0001', status_label: 'Dalam perjalanan', service_mode_label: 'Kunjungan', responsible_doctor_name: 'Dokter A', visit_performer_name: 'Perawat B', has_medical_record: false }],
    generated_at_epoch: 1785643200
  };
  return Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true, data }) });
};

const client = require('../../../assets/js/doclinc-puskesmas-operations.js');
let passed = 0;
function check(condition, name) {
  assert.ok(condition, name);
  passed += 1;
  process.stdout.write(`PASS ${name}\n`);
}

(async function run() {
  const normalized = client.normalizedConfig({ enabled: true, pollIntervalMs: 1 });
  check(normalized.pollIntervalMs === 30000, 'unsafe_poll_interval_replaced');
  check(client.create({ enabled: false, snapshotUrl: '/snapshot' }).begin() === false, 'feature_off_no_start');
  check(client.create({ enabled: true }).begin() === false, 'missing_endpoint_no_start');
  check(client.stateLabel('available', 0) === 'Siap menerima tugas', 'available_label_human_readable');
  check(client.stateLabel('attention', 2).includes('2 tugas aktif'), 'attention_label_has_workload');
  check(client.presenceLabel({ is_online: true, last_seen_age_seconds: 0 }) === 'Aktif sekarang', 'online_freshness_human_readable');
  check(client.presenceLabel({ is_online: false, last_seen_age_seconds: 125 }) === 'Terlihat 2 menit lalu', 'offline_freshness_human_readable');
  check(client.presenceLabel({ is_online: false, last_seen_age_seconds: null }) === 'Belum pernah aktif', 'missing_freshness_human_readable');

  fetchCalls = [];
  const runtime = client.create({ enabled: true, snapshotUrl: '/puskesmas/operations/snapshot', pollIntervalMs: 30000 });
  check(runtime.begin() === true, 'runtime_started');
  check(bodyClasses.has('dl-command-center'), 'command_center_shell_modifier_applied_by_operations_runtime');
  await new Promise(resolve => setImmediate(resolve));
  check(fetchCalls.length === 1 && fetchCalls[0].options.method === 'GET', 'snapshot_uses_get');
  check(fetchCalls[0].options.credentials === 'same-origin' && fetchCalls[0].options.cache === 'no-store', 'snapshot_transport_private');
  check(container.children.length === 4, 'summary_staff_exception_and_request_sections_rendered');
  const staffName = container.children[1].children[1].children[0].children[0].children[0].textContent;
  check(staffName === '<img onerror=alert(1)>', 'staff_name_rendered_as_text');
  const staffMeta = container.children[1].children[1].children[0].children[0].children[1].textContent;
  check(staffMeta === 'Dokter · Aktif sekarang', 'staff_freshness_rendered_without_raw_timestamp');
  check(container.children[2].children[1].children[0].attributes['data-request-id'] === '99', 'exception_request_identity_rendered');
  const requestIdentity = container.children[3].children[1].children[0].children[0];
  check(requestIdentity.children[0].textContent === 'Antrean 1', 'simple_queue_rendered');
  check(requestIdentity.children[1].textContent === '<script>alert(1)</script>', 'patient_name_rendered_as_text');
  check(requestIdentity.children[2].textContent === 'NIK 3671********0001', 'nik_remains_masked_in_list');
  check(typeof windowListeners.pagehide === 'function', 'pagehide_listener_installed');
  check(windowListeners.pagehide() === true, 'pagehide_teardown');
  check(clearedIntervals.length >= 1, 'poll_interval_cleared');
  check(runtime.teardown() === false, 'teardown_idempotent');

  fetchCalls = [];
  fakeDocument.visibilityState = 'hidden';
  const hidden = client.create({ enabled: true, snapshotUrl: '/snapshot', pollIntervalMs: 30000 });
  hidden.begin();
  await hidden.snapshot();
  check(fetchCalls.length === 0, 'hidden_page_does_not_poll');
  fakeDocument.visibilityState = 'visible';
  hidden.teardown();

  const lateContainerChildren = container.children.length;
  global.fetch = () => new Promise(resolve => { deferredResolve = resolve; });
  const late = client.create({ enabled: true, snapshotUrl: '/late', pollIntervalMs: 30000 });
  late.begin();
  late.teardown();
  deferredResolve({ ok: true, json: () => Promise.resolve({ success: true, data: { summary: {}, staff: [], exceptions: [] } }) });
  await new Promise(resolve => setImmediate(resolve));
  check(container.children.length === lateContainerChildren, 'late_snapshot_ignored_after_teardown');

  const safe = client.safeData({ staff: 'invalid', exceptions: null, summary: null });
  check(Array.isArray(safe.staff) && Array.isArray(safe.exceptions), 'malformed_arrays_rejected');

  process.stdout.write(`PUSKESMAS_OPERATIONS_CLIENT_PASSED=${passed}\n`);
})().catch(error => {
  process.stderr.write(`${error.stack || error}\n`);
  process.exit(1);
});
