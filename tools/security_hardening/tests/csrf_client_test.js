'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../../..');
const clientSource = fs.readFileSync(path.join(root, 'assets/js/doclinc-csrf.js'), 'utf8');
const tokenName = 'doclinc_csrf_token';
const token = '0123456789abcdef0123456789abcdef';
let passed = 0;
let failed = 0;

function expect(condition, label) {
  if (condition) {
    passed += 1;
    process.stdout.write(`PASS ${label}\n`);
    return;
  }
  failed += 1;
  process.stdout.write(`FAIL ${label}\n`);
}

class FakeHeaders {
  constructor(initial) {
    this.values = Object.create(null);
    if (initial instanceof FakeHeaders) {
      Object.keys(initial.values).forEach((key) => {
        this.values[key] = initial.values[key];
      });
      return;
    }
    Object.keys(initial || {}).forEach((key) => this.set(key, initial[key]));
  }

  set(name, value) {
    this.values[String(name).toLowerCase()] = String(value);
  }

  get(name) {
    return this.values[String(name).toLowerCase()] || null;
  }
}

class FakeElement {
  constructor(tagName) {
    this.tagName = String(tagName || '').toUpperCase();
    this.children = [];
    this.method = 'GET';
    this.action = '';
    this.hidden = false;
    this.name = '';
    this.value = '';
    this.type = '';
  }

  appendChild(child) {
    this.children.push(child);
    return child;
  }

  querySelector(selector) {
    const match = /^input\[name="(.+)"\]$/.exec(selector);
    return match ? this.children.find((child) => child.name === match[1]) || null : null;
  }
}

class FakeForm extends FakeElement {
  constructor() {
    super('form');
    this.nativeSubmitCount = 0;
  }

  submit() {
    this.nativeSubmitCount += 1;
  }
}

class FakeXhr {
  constructor() {
    this.headers = Object.create(null);
    this.sendCount = 0;
  }

  open(method, url) {
    this.openedMethod = method;
    this.openedUrl = url;
  }

  setRequestHeader(name, value) {
    this.headers[String(name).toLowerCase()] = String(value);
  }

  send() {
    this.sendCount += 1;
  }
}

function buildRuntime() {
  const fetchCalls = [];
  const bodyChildren = [];
  const documentListeners = Object.create(null);
  const forms = [new FakeForm()];
  forms[0].method = 'POST';

  const document = {
    readyState: 'complete',
    forms,
    body: {
      appendChild(node) {
        bodyChildren.push(node);
        return node;
      },
    },
    querySelector(selector) {
      if (selector === 'meta[name="doclinc-csrf-name"]') {
        return { getAttribute: () => tokenName };
      }
      if (selector === 'meta[name="doclinc-csrf-token"]') {
        return { getAttribute: () => token };
      }
      return null;
    },
    createElement(tagName) {
      return String(tagName).toLowerCase() === 'form' ? new FakeForm() : new FakeElement(tagName);
    },
    addEventListener(name, callback) {
      documentListeners[name] = callback;
    },
  };

  const window = {
    document,
    location: {
      href: 'https://doclinc.test/home',
      origin: 'https://doclinc.test',
    },
    URL,
    Headers: FakeHeaders,
    XMLHttpRequest: FakeXhr,
    HTMLFormElement: FakeForm,
    fetch(input, init) {
      fetchCalls.push({ input, init: init || {} });
      return Promise.resolve({ ok: true });
    },
  };

  const context = vm.createContext({ window, URL, Object, Array, String });
  vm.runInContext(clientSource, context, { filename: 'doclinc-csrf.js' });
  return { window, fetchCalls, bodyChildren, documentListeners, forms, context };
}

async function run() {
  const runtime = buildRuntime();
  const csrf = runtime.window.DoclincCsrf;

  expect(Boolean(csrf), 'client_bootstrap_created');
  expect(csrf.tokenName === tokenName && csrf.token === token, 'client_exposes_exact_token');
  expect(Object.isFrozen(csrf), 'client_api_is_frozen');
  expect(runtime.forms[0].querySelector(`input[name="${tokenName}"]`).value === token, 'existing_post_form_receives_token');

  await runtime.window.fetch('/chat/send', { method: 'POST' });
  const postHeaders = runtime.fetchCalls[0].init.headers;
  expect(postHeaders.get('X-CSRF-TOKEN') === token, 'same_origin_fetch_post_receives_token');
  expect(postHeaders.get('X-Requested-With') === 'XMLHttpRequest', 'same_origin_fetch_post_marks_ajax');

  await runtime.window.fetch('/chat/messages', { method: 'GET' });
  expect(!runtime.fetchCalls[1].init.headers, 'same_origin_fetch_get_unchanged');

  await runtime.window.fetch('https://outside.test/collect', { method: 'POST' });
  expect(!runtime.fetchCalls[2].init.headers, 'cross_origin_fetch_post_receives_no_token');

  const xhr = new runtime.window.XMLHttpRequest();
  xhr.open('POST', '/home/cancel_request');
  xhr.send('request_id=10');
  expect(xhr.headers['x-csrf-token'] === token, 'same_origin_xhr_post_receives_token');
  expect(xhr.headers['x-requested-with'] === 'XMLHttpRequest', 'same_origin_xhr_post_marks_ajax');

  const externalXhr = new runtime.window.XMLHttpRequest();
  externalXhr.open('POST', 'https://outside.test/collect');
  externalXhr.send('x=1');
  expect(!externalXhr.headers['x-csrf-token'], 'cross_origin_xhr_post_receives_no_token');

  const submitted = csrf.submitPost('/login/logout', { reason: 'manual' });
  const generatedForm = runtime.bodyChildren[0];
  expect(submitted === true && generatedForm.action === 'https://doclinc.test/login/logout', 'submit_post_uses_same_origin_absolute_action');
  expect(generatedForm.querySelector('input[name="reason"]').value === 'manual', 'submit_post_preserves_payload');
  expect(generatedForm.querySelector(`input[name="${tokenName}"]`).value === token, 'submit_post_adds_token');
  expect(generatedForm.nativeSubmitCount === 1, 'submit_post_submits_once');
  expect(csrf.submitPost('https://outside.test/logout') === false, 'submit_post_rejects_cross_origin_action');

  const originalFetch = runtime.window.fetch;
  vm.runInContext(clientSource, runtime.context, { filename: 'doclinc-csrf-second-load.js' });
  expect(runtime.window.fetch === originalFetch, 'second_load_is_idempotent');

  process.stdout.write(`CSRF_CLIENT_PASSED=${passed}\n`);
  process.stdout.write(`CSRF_CLIENT_FAILED=${failed}\n`);
  process.exit(failed === 0 ? 0 : 1);
}

run().catch((error) => {
  process.stderr.write(`${error.stack || error}\n`);
  process.exit(1);
});
