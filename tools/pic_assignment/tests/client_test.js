'use strict';

const fs = require('fs');
const path = require('path');
const runtime = require('../../../assets/js/doclinc-pic-assignment.js');

let passed = 0;
let failed = 0;
function expect(condition, label) {
    if (condition) {
        passed += 1;
        process.stdout.write('PASS ' + label + '\n');
        return;
    }
    failed += 1;
    process.stderr.write('FAIL ' + label + '\n');
}

class ClassList {
    constructor(names) { this.names = new Set(names || []); }
    contains(name) { return this.names.has(name); }
    toggle(name, enabled) { enabled ? this.names.add(name) : this.names.delete(name); }
}

class Element {
    constructor() {
        this.textContent = '';
        this.hidden = false;
        this.disabled = false;
        this.value = '';
        this.classList = new ClassList();
        this.listeners = {};
        this.selectors = {};
        this.roles = [];
    }
    querySelector(selector) { return (this.selectors[selector] || [])[0] || null; }
    querySelectorAll(selector) { return this.selectors[selector] || []; }
    addEventListener(name, handler) { this.listeners[name] = handler; }
    removeEventListener(name, handler) { if (this.listeners[name] === handler) { delete this.listeners[name]; } }
    setAttribute(name, value) { if (name === 'role') { this.roles.push(value); } }
}

class Form extends Element {
    constructor(className, action, fields) {
        super();
        this.classList = new ClassList([className]);
        this.action = action;
        this.fields = Object.assign({}, fields);
        this.valid = true;
        this.reported = false;
    }
    checkValidity() { return this.valid; }
    reportValidity() { this.reported = true; }
    submit() {
        return this.listeners.submit({ preventDefault: () => {} });
    }
}

function formDataFactory(form) {
    return { get: name => form.fields[name] == null ? null : String(form.fields[name]) };
}

function buildFixture(requestId) {
    const nameOne = new Element();
    const nameTwo = new Element();
    nameOne.textContent = 'Belum ditentukan';
    nameTwo.textContent = 'Belum ditentukan';
    const contact = new Element();
    contact.hidden = true;
    const selectOne = new Element();
    const selectTwo = new Element();
    const submitOne = new Element();
    const submitTwo = new Element();
    const clearOne = new Element();
    const clearTwo = new Element();
    clearOne.hidden = true;
    clearTwo.hidden = true;
    const noteOne = new Element();
    const noteTwo = new Element();
    noteOne.value = 'catatan';
    noteTwo.value = 'catatan';
    const feedbackOne = new Element();
    const feedbackTwo = new Element();
    feedbackOne.hidden = true;
    feedbackTwo.hidden = true;

    const assignOne = new Form('nk-pic-form', '/home_nakes/assign_staff', { request_id: requestId, staff_id: 4 });
    const assignTwo = new Form('nk-pic-form', '/home_nakes/assign_staff', { request_id: requestId, staff_id: 4 });
    const clearFormOne = new Form('nk-pic-clear-form', '/home_nakes/clear_staff_assignment', { request_id: requestId });
    const clearFormTwo = new Form('nk-pic-clear-form', '/home_nakes/clear_staff_assignment', { request_id: requestId });

    const summary = new Element();
    summary.selectors['[data-pic-name]'] = [nameOne];
    summary.selectors['[data-pic-contact]'] = [];
    summary.selectors['.nk-pic-form select[name="staff_id"]'] = [];
    summary.selectors['[data-pic-submit]'] = [];
    summary.selectors['.nk-pic-clear-form'] = [];
    summary.selectors['.nk-pic-form input[name="note"]'] = [];
    summary.selectors['button, select, input'] = [];

    const panelOne = new Element();
    panelOne.selectors['[data-pic-name]'] = [nameTwo];
    panelOne.selectors['[data-pic-contact]'] = [contact];
    panelOne.selectors['.nk-pic-form select[name="staff_id"]'] = [selectOne];
    panelOne.selectors['[data-pic-submit]'] = [submitOne];
    panelOne.selectors['.nk-pic-clear-form'] = [clearOne];
    panelOne.selectors['.nk-pic-form input[name="note"]'] = [noteOne];
    panelOne.selectors['button, select, input'] = [submitOne, selectOne, noteOne];
    panelOne.selectors['[data-pic-feedback]'] = [feedbackOne];

    const panelTwo = new Element();
    panelTwo.selectors['[data-pic-name]'] = [];
    panelTwo.selectors['[data-pic-contact]'] = [];
    panelTwo.selectors['.nk-pic-form select[name="staff_id"]'] = [selectTwo];
    panelTwo.selectors['[data-pic-submit]'] = [submitTwo];
    panelTwo.selectors['.nk-pic-clear-form'] = [clearTwo];
    panelTwo.selectors['.nk-pic-form input[name="note"]'] = [noteTwo];
    panelTwo.selectors['button, select, input'] = [submitTwo, selectTwo, noteTwo];
    panelTwo.selectors['[data-pic-feedback]'] = [feedbackTwo];

    const forms = [assignOne, assignTwo, clearFormOne, clearFormTwo];
    const roots = [summary, panelOne, panelTwo];
    const documentRoot = {
        querySelectorAll: selector => selector === '.nk-pic-form, .nk-pic-clear-form'
            ? forms
            : (selector === '[data-request-id="' + requestId + '"]' ? roots : [])
    };
    return {
        documentRoot, forms, assignOne, clearFormOne, roots,
        nameOne, nameTwo, contact, selectOne, selectTwo, submitOne, submitTwo,
        clearOne, clearTwo, noteOne, noteTwo, feedbackOne, feedbackTwo
    };
}

(async function () {
    expect(runtime.positiveInteger('7') === 7 && runtime.positiveInteger('-1') === 0
        && runtime.positiveInteger('7junk') === 0 && runtime.positiveInteger(Number.MAX_SAFE_INTEGER + 1) === 0,
        'positive_request_identity');
    expect(runtime.assignmentLabel(null) === 'Belum ditentukan'
        && runtime.assignmentLabel({ staff_id: 4, staff_name: 'Dr. Sari', staff_profession: 'Dokter' }) === 'Dr. Sari · Dokter',
        'assignment_label_contract');

    const fixture = buildFixture(77);
    let request = null;
    const controller = runtime.create({
        documentRoot: fixture.documentRoot,
        windowObject: {},
        formDataFactory,
        fetchImpl: async function (url, options) {
            request = { url, options, locked: fixture.submitOne.disabled && fixture.submitTwo.disabled };
            return {
                ok: true,
                json: async () => ({
                    status: 'success', message: 'Penanggung jawab diperbarui.', request_id: 77,
                    assignment: { staff_id: 4, staff_name: 'Dr. Sari', staff_profession: 'Dokter', staff_contact: 'contact-ref' }
                })
            };
        }
    });
    expect(controller.start() === true && controller.start() === false, 'start_is_idempotent');
    expect(await fixture.assignOne.submit() === true, 'native_assignment_success');
    expect(request && request.url === '/home_nakes/assign_staff'
        && request.options.method === 'POST' && request.options.credentials === 'same-origin'
        && request.options.headers.Accept === 'application/json'
        && request.options.headers['X-Requested-With'] === 'XMLHttpRequest'
        && request.locked, 'exact_json_request_and_cross_panel_lock');
    expect(fixture.nameOne.textContent === 'Dr. Sari · Dokter'
        && fixture.nameTwo.textContent === 'Dr. Sari · Dokter', 'all_matching_pic_labels_updated');
    expect(fixture.contact.textContent === 'contact-ref' && !fixture.contact.hidden,
        'authorized_contact_updated_as_text');
    expect(fixture.selectOne.value === '4' && fixture.selectTwo.value === '4'
        && fixture.submitOne.textContent === 'Ganti penanggung jawab' && fixture.submitTwo.textContent === 'Ganti penanggung jawab',
        'all_matching_controls_reconciled');
    expect(!fixture.clearOne.hidden && !fixture.clearTwo.hidden
        && fixture.noteOne.value === '' && fixture.noteTwo.value === '', 'clear_action_shown_and_notes_reset');
    expect(!fixture.submitOne.disabled && !fixture.submitTwo.disabled
        && fixture.feedbackOne.textContent === 'Penanggung jawab diperbarui.' && !fixture.feedbackOne.hidden,
        'success_unlocks_and_announces');

    controller.release();
    const clearFixture = buildFixture(78);
    clearFixture.clearOne.hidden = false;
    clearFixture.clearTwo.hidden = false;
    const clearController = runtime.create({
        documentRoot: clearFixture.documentRoot,
        windowObject: {},
        formDataFactory,
        fetchImpl: async () => ({ ok: true, json: async () => ({ status: 'success', message: 'Penugasan dihapus.', request_id: 78, assignment: null }) })
    });
    clearController.start();
    expect(await clearFixture.clearFormOne.submit() === true, 'native_clear_success');
    expect(clearFixture.nameOne.textContent === 'Belum ditentukan'
        && clearFixture.selectOne.value === '' && clearFixture.clearOne.hidden
        && clearFixture.submitOne.textContent === 'Tetapkan penanggung jawab', 'clear_reconciles_exact_empty_state');

    const failureFixture = buildFixture(79);
    const failureController = runtime.create({
        documentRoot: failureFixture.documentRoot,
        windowObject: {},
        formDataFactory,
        fetchImpl: async () => ({ ok: false, json: async () => ({ status: 'error', message: 'Data penanggung jawab aktif perlu diperiksa.', request_id: 79 }) })
    });
    failureController.start();
    expect(await failureFixture.assignOne.submit() === false, 'server_failure_returns_false');
    expect(failureFixture.nameOne.textContent === 'Belum ditentukan'
        && failureFixture.feedbackOne.textContent === 'Data penanggung jawab aktif perlu diperiksa.'
        && failureFixture.feedbackOne.roles.includes('alert'), 'failure_preserves_state_and_announces_safe_message');

    expect(clearController.release() === true && clearController.release() === false,
        'release_is_idempotent');
    expect(!clearFixture.assignOne.listeners.submit && !clearFixture.clearFormOne.listeners.submit,
        'release_removes_all_form_handlers');

    const root = path.resolve(__dirname, '../../..');
    const controllerSource = fs.readFileSync(path.join(root, 'application/modules/home_nakes/controllers/Home_nakes.php'), 'utf8');
    const viewSource = fs.readFileSync(path.join(root, 'application/modules/home_nakes/views/home_nakes_v.php'), 'utf8');
    expect(controllerSource.indexOf('respond_staff_assignment_json') >= 0
        && controllerSource.indexOf('get_active_staff_assignment') >= 0,
        'controller_returns_authoritative_assignment_snapshot');
    expect(viewSource.indexOf('assets/js/doclinc-pic-assignment.js') >= 0,
        'active_nakes_view_loads_native_pic_runtime');

    process.stdout.write('PIC_ASSIGNMENT_CLIENT_PASSED=' + passed + '\n');
    process.stdout.write('PIC_ASSIGNMENT_CLIENT_FAILED=' + failed + '\n');
    process.exit(failed === 0 ? 0 : 1);
})().catch(error => {
    process.stderr.write(String(error && error.stack ? error.stack : error) + '\n');
    process.exit(1);
});
