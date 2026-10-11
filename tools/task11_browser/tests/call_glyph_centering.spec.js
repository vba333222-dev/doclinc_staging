const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { fixtureData, loginActor } = require('./fixtures');

const outputRoot = path.resolve(process.env.VISUAL_REVIEW_OUTPUT_DIR || path.join(process.cwd(), 'test-results', 'visual-review', `call-glyph-centering-${Date.now()}`));
const measurementsByViewport = {};
const measurementsByState = {};
const screenshots = [];

async function openProductionCall(page, actor, requestId) {
  await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
  await loginActor(page, actor);
  await page.goto(`/chat?request_id=${encodeURIComponent(requestId)}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#doclincCallModal')).toHaveCount(1);
  await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
  const start = page.locator('.doclinc-call-start[data-call-mode="video"]');
  if (await start.count()) {
    await start.click();
  } else {
    // Warga is a callee and correctly has no initiation button. Open the
    // already-rendered production call shell through its test-only seam.
    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.openCall());
  }
  await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.setActive());
  await expect(page.locator('#doclincCallControls')).toBeVisible();
  await page.locator('#doclincCallControls img').evaluateAll(images => Promise.all(images.map(image => {
    if (image.complete && image.naturalWidth > 0) return Promise.resolve();
    return new Promise((resolve, reject) => { image.addEventListener('load', resolve, { once: true }); image.addEventListener('error', reject, { once: true }); });
  })));
}

/* Measure alpha bounds of the actual rendered SVG image, not its wrapper. */
async function measureVisibleGlyphs(page) {
  return page.locator('#doclincCallControls .doclinc-call-control').evaluateAll(buttons => buttons.map(button => {
    const image = button.querySelector('img');
    const buttonRect = button.getBoundingClientRect();
    const imageRect = image.getBoundingClientRect();
    const canvas = document.createElement('canvas');
    const size = Math.max(1, Math.round(image.naturalWidth || 24));
    canvas.width = size; canvas.height = size;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.clearRect(0, 0, size, size);
    context.drawImage(image, 0, 0, size, size);
    const pixels = context.getImageData(0, 0, size, size).data;
    let left = size, top = size, right = -1, bottom = -1, count = 0;
    for (let y = 0; y < size; y += 1) for (let x = 0; x < size; x += 1) {
      if (pixels[(y * size + x) * 4 + 3] > 12) { count += 1; left = Math.min(left, x); top = Math.min(top, y); right = Math.max(right, x); bottom = Math.max(bottom, y); }
    }
    const visible = count ? {
      left: imageRect.left + left / size * imageRect.width,
      top: imageRect.top + top / size * imageRect.height,
      right: imageRect.left + (right + 1) / size * imageRect.width,
      bottom: imageRect.top + (bottom + 1) / size * imageRect.height,
    } : null;
    const visibleCenter = visible ? { x: (visible.left + visible.right) / 2, y: (visible.top + visible.bottom) / 2 } : null;
    const buttonCenter = { x: buttonRect.left + buttonRect.width / 2, y: buttonRect.top + buttonRect.height / 2 };
    return {
      id: button.id,
      button: { left: buttonRect.left, top: buttonRect.top, width: buttonRect.width, height: buttonRect.height, centerX: buttonCenter.x, centerY: buttonCenter.y },
      visible,
      visiblePixelCount: count,
      visibleCenter,
      deltaX: visibleCenter ? visibleCenter.x - buttonCenter.x : null,
      deltaY: visibleCenter ? visibleCenter.y - buttonCenter.y : null,
    };
  }));
}

function assertCentered(measurements) {
  expect(measurements.every(item => item.visiblePixelCount > 0), `blank glyph: ${JSON.stringify(measurements)}`).toBe(true);
  expect(measurements.every(item => {
    if (!item.visible) return false;
    const radius = Math.min(item.button.width, item.button.height) / 2;
    return [
      [item.visible.left, item.visible.top], [item.visible.right, item.visible.top],
      [item.visible.left, item.visible.bottom], [item.visible.right, item.visible.bottom],
    ].every(([x, y]) => Math.hypot(x - item.button.centerX, y - item.button.centerY) <= radius + 0.5);
  }), `glyph outside control circle: ${JSON.stringify(measurements)}`).toBe(true);
  expect(measurements.every(item => Math.abs(item.deltaX) <= 0.5 && Math.abs(item.deltaY) <= 0.5), JSON.stringify(measurements)).toBe(true);
}

async function capture(page, file, state, actor, requestId) {
  fs.mkdirSync(path.dirname(path.join(outputRoot, file)), { recursive: true });
  const target = path.join(outputRoot, file);
  await page.screenshot({ path: target, animations: 'disabled', caret: 'hide' });
  const sha256 = crypto.createHash('sha256').update(fs.readFileSync(target)).digest('hex');
  screenshots.push({ file: file.replace(/\\/g, '/'), sha256, state, actor, request: requestId, viewport: await page.evaluate(() => `${innerWidth}x${innerHeight}`) });
  return sha256;
}

async function addCenterDebugOverlay(page, measurements) {
  await page.evaluate(items => {
    const layer = document.createElement('div');
    layer.id = 'call-centering-debug-overlay';
    layer.style.cssText = 'position:fixed;inset:0;z-index:2147483647;pointer-events:none;font:11px monospace;color:#ff2d55;';
    for (const item of items) {
      const b = item.button; const v = item.visibleCenter;
      for (const [point, color] of [[b, '#00a651'], [v, '#ff2d55']]) {
        const cross = document.createElement('span');
        cross.style.cssText = `position:fixed;left:${point.x - 5}px;top:${point.y - 5}px;width:10px;height:10px;border:1px solid ${color};border-radius:50%;`;
        layer.appendChild(cross);
      }
      const label = document.createElement('span');
      label.textContent = `${item.id} Δ${item.deltaX.toFixed(2)},${item.deltaY.toFixed(2)}`;
      label.style.cssText = `position:fixed;left:${b.centerX + 8}px;top:${b.centerY - 8}px;background:#fff;color:#111;padding:1px 3px;`;
      layer.appendChild(label);
    }
    document.body.appendChild(layer);
  }, measurements);
}

test.describe.configure({ mode: 'serial' });

test('production DOM glyph bounds are centered at every supported viewport', async ({ page }) => {
  test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
  const data = fixtureData();
  const actor = data.actors.WARGA;
  const requestId = data.requests.visit_happy;

  await page.setViewportSize({ width: 1440, height: 900 });
  await openProductionCall(page, actor, requestId);
  const connected1440 = await measureVisibleGlyphs(page);
  measurementsByViewport['1440x900'] = connected1440;
  console.log('CALL_GLYPH_MEASUREMENTS 1440x900', JSON.stringify(connected1440));
  assertCentered(connected1440);
  await capture(page, 'controls-1440x900.png', 'connected', 'WARGA', requestId);

  await page.evaluate(() => {
    const hooks = window.__DOCLINC_LIVEKIT_TEST_HOOKS__;
    hooks.showLocalPlaceholder('Kamera nonaktif');
    const button = document.querySelector('#doclincCallCamera');
    button.classList.remove('is-active'); button.classList.add('is-off'); button.setAttribute('aria-pressed', 'true');
  });
  await capture(page, 'controls-camera-off-1440x900.png', 'camera-off', 'WARGA', requestId);
  measurementsByState['camera-off'] = await measureVisibleGlyphs(page);
  assertCentered(measurementsByState['camera-off']);
  await page.locator('#doclincCallMore').click();
  await expect(page.locator('#doclincCallMorePanel')).toBeVisible();
  await capture(page, 'controls-more-open-1440x900.png', 'more-open', 'WARGA', requestId);
  measurementsByState['more-open'] = await measureVisibleGlyphs(page);
  assertCentered(measurementsByState['more-open']);
  await page.locator('#doclincCallMore').click();
  await page.evaluate(() => {
    const button = document.querySelector('#doclincCallMic');
    button.classList.remove('is-active'); button.classList.add('is-off'); button.setAttribute('aria-pressed', 'true');
  });
  measurementsByState['mic-muted'] = await measureVisibleGlyphs(page);
  assertCentered(measurementsByState['mic-muted']);

  for (const viewport of [{ width: 1366, height: 768 }, { width: 353, height: 800 }, { width: 360, height: 800 }, { width: 390, height: 844 }, { width: 412, height: 915 }, { width: 430, height: 932 }, { width: 768, height: 1024 }, { width: 1024, height: 768 }]) {
    await page.setViewportSize(viewport);
    const key = `${viewport.width}x${viewport.height}`;
    const measurements = await measureVisibleGlyphs(page);
    measurementsByViewport[key] = measurements;
    console.log(`CALL_GLYPH_MEASUREMENTS ${key}`, JSON.stringify(measurements));
    assertCentered(measurements);
    if (key === '1366x768') await capture(page, 'controls-1366x768.png', 'connected', 'WARGA', requestId);
    if (key === '390x844') await capture(page, 'controls-mobile-390x844.png', 'connected', 'WARGA', requestId);
  }

  await page.setViewportSize({ width: 1440, height: 900 });
  await addCenterDebugOverlay(page, connected1440);
  await capture(page, 'controls-centers-debug-1440.png', 'centers-debug', 'WARGA', requestId);
  await page.evaluate(() => document.querySelector('#call-centering-debug-overlay')?.remove());

  const negativeStyle = await page.addStyleTag({ content: '#doclincCallMic img { transform: translateX(-4px) !important; }' });
  const shifted = await measureVisibleGlyphs(page);
  expect(shifted.some(item => Math.abs(item.deltaX) > 0.5 || Math.abs(item.deltaY) > 0.5), 'negative control must be detected').toBe(true);
  await negativeStyle.evaluate(node => node.remove());
  assertCentered(await measureVisibleGlyphs(page));

  fs.mkdirSync(outputRoot, { recursive: true });
  fs.writeFileSync(path.join(outputRoot, 'measurements.json'), JSON.stringify({ measurementsByViewport, measurementsByState, screenshots }, null, 2));
  fs.writeFileSync(path.join(outputRoot, 'manifest.json'), JSON.stringify({ evidence_level: 'REAL_PRODUCTION_DOM_VISIBLE_GLYPH_BOUNDS', center_metric: 'signed visibleCenter - buttonCenter', screenshots, measurementsByViewport, measurementsByState }, null, 2));
  fs.writeFileSync(path.join(outputRoot, 'visual-review.md'), '# Call glyph centering review\n\nVisible SVG alpha bounds are measured from the production DOM image pixels. Green crosses mark button centers; pink crosses mark visible-glyph centers in the debug screenshot.\n\nAll canonical SVG files remain unchanged.\n');
  fs.writeFileSync(path.join(outputRoot, 'index.html'), `<!doctype html><meta charset="utf-8"><title>Call glyph centering</title><h1>Call glyph centering</h1><p>Production DOM evidence; signed center deltas are in manifest.json.</p><ul>${screenshots.map(s => `<li><a href="${s.file}">${s.state} ${s.viewport}</a> — ${s.sha256.slice(0, 12)}</li>`).join('')}</ul>`);
});

module.exports = { measureVisibleGlyphs };
