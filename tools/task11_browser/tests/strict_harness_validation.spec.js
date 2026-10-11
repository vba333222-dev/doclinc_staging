const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const os = require('os');
const { execFileSync } = require('child_process');

test.describe('strict visual harness validation', () => {
  test('strict contexts use 353x800 CSS pixels at DPR 2 and viewport screenshots', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 353, height: 800 }, deviceScaleFactor: 2, isMobile: false, hasTouch: false });
    const page = await context.newPage();
    await page.setContent('<meta name="viewport" content="width=353,height=800,initial-scale=1"><style>html,body{margin:0;width:353px;height:800px;overflow:hidden}#surface{width:353px;height:800px}</style><main id="surface"></main>');
    expect(await page.evaluate(() => ({ width: innerWidth, height: innerHeight, dpr: devicePixelRatio, sw: document.documentElement.scrollWidth, sh: document.documentElement.scrollHeight }))).toEqual({ width: 353, height: 800, dpr: 2, sw: 353, sh: 800 });
    const screenshot = await page.screenshot();
    expect(screenshot.readUInt32BE(16)).toBe(706);
    expect(screenshot.readUInt32BE(20)).toBe(1600);
    await context.close();
  });

  test('negative geometry control detects a four pixel translation', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 353, height: 800 }, deviceScaleFactor: 2, isMobile: false, hasTouch: false });
    const page = await context.newPage();
    await page.setContent('<style>#surface{position:absolute;left:4px;top:0;width:100px;height:100px}</style><main id="surface"></main>');
    const box = await page.locator('#surface').boundingBox();
    const withinTolerance = value => Math.abs(value) <= 1;
    expect(withinTolerance(box.x)).toBe(false);
    await page.setContent('<style>#surface{position:absolute;left:0;top:0;width:100px;height:100px}</style><main id="surface"></main>');
    const baseline = await page.locator('#surface').boundingBox();
    expect(withinTolerance(baseline.x)).toBe(true);
    await context.close();
  });

  test('negative color control detects a wrong flat token', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 353, height: 800 }, deviceScaleFactor: 2, isMobile: false, hasTouch: false });
    const page = await context.newPage();
    await page.setContent('<style>#surface{width:10px;height:10px;background:#ff00ff}</style><main id="surface"></main>');
    const matchesExpectedToken = value => value === 'rgb(248, 250, 252)';
    const wrong = await page.locator('#surface').evaluate(el => getComputedStyle(el).backgroundColor);
    expect(matchesExpectedToken(wrong)).toBe(false);
    await page.locator('#surface').evaluate(el => { el.style.backgroundColor = '#f8fafc'; });
    const baseline = await page.locator('#surface').evaluate(el => getComputedStyle(el).backgroundColor);
    expect(matchesExpectedToken(baseline)).toBe(true);
    await context.close();
  });

  test('diff contract is fail-closed and documents dynamic-only masks', () => {
    const source = fs.readFileSync(path.join(__dirname, 'generate_reference_diffs.js'), 'utf8');
    expect(source).toContain('referenceSize.width !== actualSize.width');
    expect(source).not.toContain('Math.min(ref.naturalWidth, act.naturalWidth)');
    expect(source).toContain('documented_dynamic_regions');
    expect(source).toContain('ui_geometry_masked: false');
    expect(source).toContain('mask: mask.toDataURL');
  });

  test('diff generator executes and emits exact-size overlay, difference, mask, and documentation', async ({ browser }) => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'strict-diff-contract-'));
    const { context, page } = await (async () => {
      const c = await browser.newContext({ viewport: { width: 353, height: 800 }, deviceScaleFactor: 2, isMobile: false, hasTouch: false });
      return { context: c, page: await c.newPage() };
    })();
    await page.setContent('<meta name="viewport" content="width=353,height=800,initial-scale=1"><style>html,body{margin:0;width:353px;height:800px;overflow:hidden}</style><main style="width:353px;height:800px;background:#f8fafc"></main>');
    const screenshot = await page.screenshot();
    for (const mode of ['chat', 'video', 'voice']) {
      const dir = path.join(root, mode); fs.mkdirSync(dir);
      fs.writeFileSync(path.join(dir, 'reference.png'), screenshot);
      fs.writeFileSync(path.join(dir, 'actual.png'), screenshot);
    }
    await context.close();
    execFileSync(process.execPath, [path.join(__dirname, 'generate_reference_diffs.js'), root], { stdio: 'pipe' });
    for (const mode of ['chat', 'video', 'voice']) {
      for (const name of ['overlay.png', 'difference.png', 'mask.png']) {
        const png = fs.readFileSync(path.join(root, mode, name));
        expect(png.readUInt32BE(16)).toBe(706);
        expect(png.readUInt32BE(20)).toBe(1600);
      }
      const maskContract = JSON.parse(fs.readFileSync(path.join(root, mode, 'mask.json'), 'utf8'));
      expect(maskContract.ui_geometry_masked).toBe(false);
      expect(maskContract.documented_dynamic_regions.length).toBeGreaterThan(0);
    }
  });
});
