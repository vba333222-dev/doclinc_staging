const { test, expect } = require('@playwright/test');
const { fixtureData, loginActor } = require('./fixtures');

async function openVoiceCall(page, actor, requestId) {
  await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
  await loginActor(page, actor);
  await page.goto(`/chat?request_id=${encodeURIComponent(requestId)}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#doclincCallModal')).toHaveCount(1);
  await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
  await page.evaluate(() => {
    const hooks = window.__DOCLINC_LIVEKIT_TEST_HOOKS__;
    hooks.openCall();
    hooks.setMode('audio');
    hooks.setActive();
  });
  await expect(page.locator('#doclincCallModal')).toHaveClass(/is-open/);
  await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-audio/);
}

test.describe('production voice-call refinement contract', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
  });

  test('audio mode is adaptive, text-first when photo is unavailable, and uses animated waveform support copy', async ({ page }) => {
    const data = fixtureData();
    await openVoiceCall(page, data.actors.WARGA, data.requests.visit_happy);

    await expect(page.locator('.doclinc-call-stage')).toHaveAttribute('data-call-mode', 'audio');
    await expect(page.locator('.doclinc-call-stage video')).toHaveCount(0);
    await expect(page.locator('#doclincCallEmptyText')).toHaveText('Audio tersambung');
    await expect(page.locator('.doclinc-call-empty-name')).not.toHaveText(/undefined|doctor-placeholder/i);
    await expect(page.locator('.doclinc-call-empty-support')).toHaveText('Kami akan membantu konsultasi Anda');
    await expect(page.locator('[data-voice-waveform]')).toHaveAttribute('data-lottie-src', /soundwave\.json$/);
    await expect(page.locator('[data-voice-waveform]')).toHaveAttribute('aria-hidden', 'true');
    await expect(page.locator('[data-voice-waveform]')).toHaveAttribute('data-lottie-loaded', 'true');
    await expect(page.locator('[data-voice-waveform] svg')).toBeVisible();
    await expect(page.locator('[data-voice-waveform] svg rect')).toHaveCount(5);
    const waveformGeometry = await page.locator('[data-voice-waveform] svg rect').evaluateAll((rects) => rects.map((rect) => {
      const box = rect.getBoundingClientRect();
      return { width: box.width, height: box.height, x: rect.getAttribute('x'), y: rect.getAttribute('y') };
    }));
    expect(waveformGeometry.every((rect) => rect.width > 0 && rect.height > 0 && !/NaN/.test(`${rect.x}${rect.y}`))).toBe(true);
    await expect(page.locator('.doclinc-call-empty-avatar')).toHaveCount(0);
    await expect(page.locator('.doclinc-call-empty-fallback')).toHaveCount(0);
    const name = (await page.locator('.doclinc-call-empty-name').textContent()).trim();
    const role = (await page.locator('.doclinc-call-empty-role').count()) ? (await page.locator('.doclinc-call-empty-role').textContent()).trim() : '';
    expect(name).not.toBe(role);
  });

  test('voice mobile surface remains bounded and controls remain accessible', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const data = fixtureData();
    await openVoiceCall(page, data.actors.WARGA, data.requests.visit_happy);
    const metrics = await page.evaluate(() => {
      const stage = document.querySelector('.doclinc-call-stage');
      const empty = document.querySelector('.doclinc-call-empty');
      const controls = document.querySelectorAll('#doclincCallControls > button');
      return {
        stage: stage ? stage.getBoundingClientRect().toJSON() : null,
        empty: empty ? empty.getBoundingClientRect().toJSON() : null,
        controls: Array.from(controls).map((button) => ({
          box: button.getBoundingClientRect().toJSON(),
          label: button.getAttribute('aria-label')
        })),
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
      };
    });
    expect(metrics.stage.width).toBeGreaterThan(300);
    expect(metrics.empty.width).toBeGreaterThan(0);
    expect(metrics.controls.length).toBe(5);
    expect(metrics.controls.every((control) => control.label)).toBe(true);
    expect(metrics.overflow).toBe(false);
  });
});
