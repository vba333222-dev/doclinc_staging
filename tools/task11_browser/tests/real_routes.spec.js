const { test, expect } = require('@playwright/test');

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
});
