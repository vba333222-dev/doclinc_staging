const { execFileSync } = require('child_process');
const WEB_CONTAINER = process.env.TASK11_WEB_CONTAINER;
if (!WEB_CONTAINER || !/^task11-[a-z0-9-]+$/i.test(WEB_CONTAINER)) throw new Error('task11_web_container_required');
process.env.TASK11_WEB_CONTAINER = WEB_CONTAINER;
const RUN_ID = process.env.TASK11_RUN_ID;
if (!RUN_ID) throw new Error('task11_run_id_required');
function fixtureData() {
  const output = execFileSync('docker', ['exec', '-e', `TASK11_RUN_ID=${RUN_ID}`, WEB_CONTAINER, 'php', 'tools/task11_browser/tests/fixture_setup.php'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  const data = JSON.parse(output.split(/\r?\n/).filter(Boolean).pop());
  if (!data.actors || !data.requests) throw new Error('invalid_task11_browser_fixture');
  return data;
}
function stateProbe(requestId) {
  const output = execFileSync('docker', ['exec', '-e', `TASK11_RUN_ID=${RUN_ID}`, WEB_CONTAINER, 'php', 'tools/task11_browser/tests/state_probe.php', String(requestId)], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  return JSON.parse(output.split(/\r?\n/).filter(Boolean).pop());
}
async function loginActor(page, actor) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  const token = await csrf(page);
  const response = await page.request.post('/login/auth', { form: { username: actor.username, password: actor.password, doclinc_csrf_token: token } });
  const loginBody = (await response.text()).trim();
  if (response.status() !== 200 || loginBody !== '1') throw new Error(`login_failed_${actor.username}_${response.status()}_${loginBody.slice(0,80)}`);
  if (!(await page.context().cookies()).some(cookie => /session|ci_session/i.test(cookie.name))) throw new Error(`login_session_cookie_missing_${actor.username}`);
  return page;
}
async function csrf(page) { return page.locator('meta[name="doclinc-csrf-token"]').getAttribute('content'); }
async function post(page, path, fields = {}) {
  const response = await page.request.post(path, { headers: { Accept: 'application/json,text/html' }, form: { ...fields, doclinc_csrf_token: await csrf(page) } });
  let json = null; try { json = await response.json(); } catch (_) { /* non-JSON denial */ }
  return { response, json };
}
async function surface(page, actorName, requestId) {
  const path = actorName === 'WARGA' ? `/home/clinical_workflow/${requestId}` : actorName === 'COMMAND_CENTER' ? `/puskesmas/operations/clinical-workflow/${requestId}` : `/home_nakes/clinical_workflow/${requestId}`;
  const response = await page.request.get(path, { headers: { Accept: 'application/json,text/html' } }); let json = null; try { json = await response.json(); } catch (_) { /* denial */ }
  return { response, json, path };
}
async function expectSuccess(result) {
  if (!result.response.ok() || !result.json || !(result.json.success || result.json.ok || result.json.status === 'success')) throw new Error(`expected_success status=${result.response.status()} content_type=${result.response.headers()['content-type'] || ''} body=${result.json ? JSON.stringify(result.json) : (await result.response.text()).slice(0, 240)}`);
  return result.json;
}
async function expectDenied(result) {
  const status = result.response.status();
  if (status >= 500) throw new Error(`unsafe_server_error status=${status} body=${result.json ? JSON.stringify(result.json) : (await result.response.text()).slice(0, 240)}`);
  if (status === 404) throw new Error(`expected_authorization_denial_but_route_missing status=${status}`);
  if (status === 200 && result.json && (result.json.success || result.json.ok || result.json.status === 'success')) throw new Error(`expected_denial body=${JSON.stringify(result.json)}`);
  if (result.json && result.json.safe_error_code && !/^[A-Z0-9_]+$/.test(result.json.safe_error_code)) throw new Error(`unsafe_error_code ${result.json.safe_error_code}`);
  if ([401, 403, 409, 422].includes(status) && (!result.json || (!/^[A-Z0-9_]+$/.test(result.json.safe_error_code || '') && !(result.json.status === 'error' && typeof result.json.message === 'string' && result.json.message.trim() !== '')))) throw new Error(`missing_safe_error_contract status=${status}`);
  return result;
}
module.exports = { fixtureData, stateProbe, loginActor, csrf, post, surface, expectSuccess, expectDenied };
