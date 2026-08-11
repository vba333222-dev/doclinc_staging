const originalSetTimeout = global.setTimeout;
const originalClearTimeout = global.clearTimeout;
const timers = [];
global.setTimeout = function (callback) { timers.push(callback); return timers.length; };
global.clearTimeout = function (id) { if (id > 0 && id <= timers.length) timers[id - 1] = null; };
const mask = require('../../../assets/js/doclinc-password-mask.js');
let passed = 0;
let failed = 0;

function expect(condition, name) {
  if (condition) { passed++; process.stdout.write('PASS ' + name + '\n'); return; }
  failed++; process.stderr.write('FAIL ' + name + '\n');
}

function element(type) {
  return {
    type: type || '', style: {}, attributes: {}, listeners: {}, children: [], parentNode: null, textContent: '',
    setAttribute(name, value) { this.attributes[name] = value; },
    addEventListener(name, callback) { this.listeners[name] = callback; },
    appendChild(child) { child.parentNode = this; this.children.push(child); }
  };
}

const documentRef = { createElement: () => element(''), querySelectorAll: () => [] };
const parent = element('div');
const form = element('form');
const input = element('password');
input.ownerDocument = documentRef;
input.form = form;
parent.appendChild(input);
const attached = mask.attach(input, { delay: 600 });

input.listeners.beforeinput({ inputType: 'insertText', data: 'A' });
expect(attached.badge.textContent === 'A' && attached.badge.style.display === 'inline-block', 'newest_character_briefly_visible');
input.listeners.beforeinput({ inputType: 'insertText', data: 'b' });
expect(attached.badge.textContent === 'b' && attached.badge.textContent !== 'Ab', 'older_characters_never_mirrored');
const activeTimer = timers.length - 1;
timers[activeTimer]();
expect(attached.badge.textContent === '' && attached.badge.style.display === 'none', 'timer_masks_newest_character');
input.listeners.beforeinput({ inputType: 'insertText', data: 'C' });
input.listeners.blur();
expect(attached.badge.textContent === '', 'blur_masks_immediately');
input.listeners.beforeinput({ inputType: 'insertText', data: 'D' });
form.listeners.submit();
expect(attached.badge.textContent === '', 'submit_masks_immediately');
input.listeners.beforeinput({ inputType: 'insertFromPaste', data: 'FullSecret' });
expect(attached.badge.textContent === '', 'paste_never_reveals_password');
expect(input.type === 'password', 'input_never_switches_to_plain_text');

const source = require('fs').readFileSync(require('path').resolve(__dirname, '../../../assets/js/doclinc-password-mask.js'), 'utf8');
expect(!/localStorage|sessionStorage|console\./.test(source), 'password_not_logged_or_persisted');
expect(!/dataset|data-password|setAttribute\([^,]*value/.test(source), 'password_not_stored_in_attributes');

global.setTimeout = originalSetTimeout;
global.clearTimeout = originalClearTimeout;
process.stdout.write('PASSWORD_MASK_PASSED=' + passed + '\nPASSWORD_MASK_FAILED=' + failed + '\n');
process.exit(failed === 0 ? 0 : 1);
