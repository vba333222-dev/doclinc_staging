const { test, expect } = require('@playwright/test');
const { fixtureData, csrf } = require('./fixtures');
const { actorPage } = require('../support/lifecycle');

test.describe('real CI3 route smoke', () => {
  test('front controller serves login and protects actor surfaces', async ({ page }) => {
    const login = await page.goto('/login', { waitUntil: 'domcontentloaded' });
    expect(login && login.status()).toBe(200);
    await expect(page.locator('meta[name="doclinc-csrf-token"]')).toHaveCount(1);

    for (const route of ['/home', '/home_nakes/index', '/chat']) {
      const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
      expect(response && response.status()).toBeGreaterThanOrEqual(200);
      expect(page.url()).toContain('/login');
    }
    const commandCenter = await page.goto('/home_nakes/operations', { waitUntil: 'domcontentloaded' });
    expect(commandCenter && commandCenter.status()).toBeGreaterThanOrEqual(200);
    expect(page.url()).toMatch(/\/home_nakes\/operations|\/login/);
  });

  test('real route requests remain loopback and expose no server internals', async ({ page }) => {
    const failures = [];
    page.on('requestfailed', request => failures.push(request.url()));
    const response = await page.goto('/login', { waitUntil: 'networkidle' });
    expect(response && response.url()).toMatch(/^http:\/\/127\.0\.0\.1:/);
    expect(failures.filter(url => !url.startsWith('http://127.0.0.1:'))).toEqual([]);
    await expect(page.locator('body')).not.toContainText('SQLSTATE');
    await expect(page.locator('body')).not.toContainText('Stack trace');
  });

  test('Task 11 clinical adapters return a safe 405 for wrong methods', async ({ browser }) => {
    const data = fixtureData();
    const actor = await actorPage(browser, data, 'RD');
    const postOnly = ['/clinical/disposition', '/clinical/review', '/clinical/finalize', '/clinical/amend', '/clinical/close'];
    for (const path of postOnly) {
      const response = await actor.page.request.get(path, { headers: { Accept: 'application/json' } });
      expect(response.status(), path).toBe(405);
      expect(response.headers()['content-type'] || '', path).toContain('application/json');
      expect(await response.json(), path).toEqual(expect.objectContaining({ success: false, safe_error_code: 'method_not_allowed' }));
    }
    const token = await csrf(actor.page);
    const readResponse = await actor.page.request.post(`/home_nakes/clinical_workflow/${data.requests.authority}`, {
      headers: { Accept: 'application/json' },
      form: { doclinc_csrf_token: token },
    });
    expect(readResponse.status()).toBe(405);
    expect(readResponse.headers()['content-type'] || '').toContain('application/json');
    expect(await readResponse.json()).toEqual(expect.objectContaining({ success: false, safe_error_code: 'method_not_allowed' }));
    await actor.context.close();
  });
});
