'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const source = fs.readFileSync(path.resolve(__dirname, '../../../assets/js/doclinc-profile-completion.js'), 'utf8');
let passed = 0;
let failed = 0;
function expect(condition, label) {
  if (condition) { passed += 1; process.stdout.write(`PASS ${label}\n`); return; }
  failed += 1; process.stdout.write(`FAIL ${label}\n`);
}
function response(status, body) {
  return { status, json: () => Promise.resolve(body) };
}
function harness(options = {}) {
  const listeners = {};
  const button = { disabled: false };
  const form = {
    valid: options.valid !== false,
    _data: Object.assign({ foto: { size: 0 } }, options.data || {}),
    addEventListener: (name, callback) => { listeners[name] = callback; },
    checkValidity() { return this.valid; },
    reportValidityCalled: false,
    reportValidity() { this.reportValidityCalled = true; },
    querySelector: () => button,
  };
  const feedback = { textContent: '', classList: { toggle() {} } };
  const logout = { addEventListener() {} };
  const calls = [];
  const queued = (options.responses || []).slice();
  class FakeFormData {
    constructor(target) { this.values = Object.assign({}, target ? target._data : {}); }
    get(key) { return this.values[key]; }
    delete(key) { delete this.values[key]; }
    append(key, value) { this.values[key] = value; }
  }
  const location = { assigned: '', reloadCount: 0, assign(url) { this.assigned = url; }, reload() { this.reloadCount += 1; } };
  const window = {
    DOCLINC_PROFILE_COMPLETION: {
      role: options.role || 'warga', profileUpdateUrl: '/profile/update', photoUpdateUrl: '/home/update_profile_photo',
      statusUrl: '/profile/requirements', redirectUrl: '/home', logoutUrl: '/login/logout',
    },
    location,
    setTimeout(callback) { callback(); },
  };
  const document = {
    getElementById(id) { return id === 'profileCompletionForm' ? form : (id === 'profileCompletionFeedback' ? feedback : null); },
    querySelector(selector) { return selector === '[data-profile-logout]' ? logout : null; },
  };
  const context = {
    window, document, FormData: FakeFormData,
    fetch(url, request) { calls.push({ url, request }); return Promise.resolve(queued.shift()); },
    Promise,
  };
  vm.runInNewContext(source, context, { filename: 'doclinc-profile-completion.js' });
  return { listeners, form, feedback, button, calls, location };
}
async function settle() {
	for (let index = 0; index < 8; index += 1) await Promise.resolve();
	await new Promise(resolve => setImmediate(resolve));
	await new Promise(resolve => setImmediate(resolve));
}

(async () => {
  const invalid = harness({ valid: false });
  invalid.listeners.submit({ preventDefault() {} });
  await settle();
  expect(invalid.calls.length === 0 && invalid.form.reportValidityCalled, 'invalid_form_never_calls_server');

  const complete = harness({
    data: { nik: '3671010101010001', nomor_kk: '3671010101010002', nomor_bpjs_kis: '0001234567890', foto: { size: 0 } },
    responses: [response(200, { status: 'success' }), response(200, { success: true, data: { complete: true } })],
  });
  complete.listeners.submit({ preventDefault() {} });
  await settle();
  expect(complete.calls.map(call => call.url).join('|') === '/profile/update|/profile/requirements', 'warga_profile_then_authoritative_status');
  expect(complete.location.assigned === '/home', 'redirect_only_after_complete_status');

  const rejected = harness({ responses: [response(409, { status: 'error', message: 'Data identitas sudah terdaftar.' })] });
  rejected.listeners.submit({ preventDefault() {} });
  await settle();
  expect(rejected.calls.length === 1 && rejected.location.assigned === '', 'server_rejection_keeps_application_locked');
  expect(rejected.feedback.textContent === 'Data identitas sudah terdaftar.', 'safe_server_error_is_presented');

  const nakes = harness({ role: 'dokter', data: { foto: { size: 120 } }, responses: [response(200, { status: 'success' }), response(200, { success: true, data: { complete: false } })] });
  nakes.listeners.submit({ preventDefault() {} });
  await settle();
  expect(nakes.calls.length === 2 && nakes.calls[0].url === '/profile/update', 'nakes_uses_single_existing_multipart_profile_boundary');
  expect(nakes.location.assigned === '' && nakes.location.reloadCount === 1, 'managed_gap_remains_locked_and_refreshes_requirements');

  process.stdout.write(`ROLE_IDENTITY_CLIENT_PASSED=${passed}\n`);
  process.stdout.write(`ROLE_IDENTITY_CLIENT_FAILED=${failed}\n`);
  process.exit(failed === 0 ? 0 : 1);
})().catch(error => { process.stderr.write(String(error && error.stack ? error.stack : error)); process.exit(1); });
