const { execFileSync } = require('child_process');

const WEB_CONTAINER = process.env.TASK11_WEB_CONTAINER || 'task11-browser-web-20260904';
let cached;

function fixtureData() {
  if (cached) return cached;
  const output = execFileSync('docker', ['exec', WEB_CONTAINER, 'php', 'tools/task11_browser/tests/fixture_setup.php'], {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
  cached = JSON.parse(output.split(/\r?\n/).filter(Boolean).pop());
  if (!cached.actors || !cached.requests) throw new Error('invalid_task11_browser_fixture');
  return cached;
}

async function loginActor(page, actor) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  const username = page.locator('input[name="username"]');
  const password = page.locator('input[name="password"]');
  await username.fill(actor.username);
  await password.fill(actor.password);
  await Promise.all([
    page.waitForResponse(r => r.url().includes('/login/auth')),
    page.locator('#loginForm button[type="submit"]').click(),
  ]);
  await page.waitForTimeout(1100);
  return page;
}

async function csrf(page) {
  return page.locator('meta[name="doclinc-csrf-token"]').getAttribute('content');
}

async function post(page, path, fields = {}) {
  const token = await csrf(page);
  const body = { ...fields, doclinc_csrf_token: token };
  const response = await page.request.post(path, { form: body });
  let json = null;
  try { json = await response.json(); } catch (_) { /* safe HTML/redirect is asserted by caller */ }
  return { response, json };
}

async function surface(page, actorName, requestId) {
  const path = actorName === 'WARGA'
    ? `/home/clinical_workflow/${requestId}`
    : actorName === 'COMMAND_CENTER'
      ? `/puskesmas/operations/clinical-workflow/${requestId}`
      : `/home_nakes/clinical_workflow/${requestId}`;
  const response = await page.request.get(path);
  let json = null;
  try { json = await response.json(); } catch (_) { /* denial may be non-JSON */ }
  return { response, json, path };
}

async function expectSuccess(result) {
  if (!result.response.ok() || !result.json || !(result.json.success || result.json.ok || result.json.status === 'success')) {
    throw new Error(`expected_success status=${result.response.status()} body=${JSON.stringify(result.json)}`);
  }
  return result.json;
}

async function expectDenied(result) {
  if (result.response.status() === 200 && result.json && (result.json.success || result.json.ok)) {
    throw new Error(`expected_denial body=${JSON.stringify(result.json)}`);
  }
  return result;
}

module.exports = { fixtureData, loginActor, csrf, post, surface, expectSuccess, expectDenied };
