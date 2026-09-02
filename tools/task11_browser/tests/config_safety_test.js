const assert = require('node:assert/strict');
const { validateBaseUrl } = require('../playwright.config');

assert.equal(validateBaseUrl('http://127.0.0.1:18090'), 'http://127.0.0.1:18090');
for (const value of ['https://127.0.0.1:18090', 'http://localhost:18090', 'http://192.168.1.5:18090', 'http://127.0.0.1', 'not-a-url']) {
  assert.throws(() => validateBaseUrl(value), /TASK11_BASE_URL/);
}
console.log('PLAYWRIGHT_LOOPBACK_CONFIG=PASS');
