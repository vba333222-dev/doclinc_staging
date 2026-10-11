const { test, expect } = require('@playwright/test');
const { fixtureData, loginActor } = require('./fixtures');

async function openVoice(page, actor, requestId, options = {}) {
  await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
  await loginActor(page, actor);
  await page.goto(`/chat?request_id=${encodeURIComponent(requestId)}`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
  await page.evaluate(() => {
    const hooks = window.__DOCLINC_LIVEKIT_TEST_HOOKS__;
    hooks.openCall();
    hooks.setMode('audio');
    hooks.setActive();
  });
  await expect(page.locator('.doclinc-call-modal.is-open.is-audio')).toBeVisible();
  await expect(page.locator('[data-voice-waveform]')).toHaveAttribute('data-lottie-loaded', options.waveformFailure ? 'false' : 'true', { timeout: 15000 });
}

test.describe('voice-call completion contracts', () => {
  test.beforeEach(async () => {
    test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
  });

  test('no-photo voice presentation is text-first without a fallback avatar or duplicate generic role', async ({ page }) => {
    const data = fixtureData({ voiceTextOnly: true });
    await openVoice(page, data.actors.WARGA, data.requests.visit_happy);
    await expect(page.locator('[data-voice-profile-photo]')).toHaveCount(0);
    await expect(page.locator('[data-voice-profile-fallback]')).toHaveCount(0);
    const identity = await page.evaluate(() => ({
      name: document.querySelector('.doclinc-call-empty-name')?.textContent.trim() || '',
      role: document.querySelector('.doclinc-call-empty-role')?.textContent.trim() || ''
    }));
    expect(identity.name).not.toBe('');
    expect(identity.name).not.toBe(identity.role);
  });

  test('waveform visibly advances after the first rendered frame', async ({ page }) => {
    const data = fixtureData({ voiceTextOnly: true });
    await openVoice(page, data.actors.WARGA, data.requests.visit_happy);
    const frame0 = await page.locator('[data-voice-waveform] svg rect').evaluateAll((rects) => rects.map((rect) => ({ transform: getComputedStyle(rect).transform, opacity: getComputedStyle(rect).opacity })));
    await page.waitForTimeout(550);
    const frame1 = await page.locator('[data-voice-waveform] svg rect').evaluateAll((rects) => rects.map((rect) => ({ transform: getComputedStyle(rect).transform, opacity: getComputedStyle(rect).opacity })));
    expect(JSON.stringify(frame1)).not.toBe(JSON.stringify(frame0));
    await page.waitForTimeout(1700);
    const loopFrame = await page.locator('[data-voice-waveform] svg rect').evaluateAll((rects) => rects.map((rect) => getComputedStyle(rect).animationIterationCount));
    expect(loopFrame.every((value) => value === 'infinite')).toBe(true);
  });

  test('legitimate provider photo is rendered for the matching provider identity', async ({ page }) => {
    const data = fixtureData({ voicePhoto: true });
    await openVoice(page, data.actors.WARGA, data.requests.visit_happy);
    await expect(page.locator('[data-voice-profile-photo]')).toBeVisible();
    await expect(page.locator('[data-voice-profile-photo]')).toHaveJSProperty('complete', true);
    const photoMetrics = await page.locator('[data-voice-profile-photo]').evaluate((image) => ({ naturalWidth: image.naturalWidth, naturalHeight: image.naturalHeight, currentSrc: image.currentSrc }));
    expect(photoMetrics.naturalWidth).toBeGreaterThan(1);
    expect(photoMetrics.naturalHeight).toBeGreaterThan(1);
    expect(photoMetrics.currentSrc).toMatch(/\/profile\/photo\/[1-9][0-9]*\/?$/);
    await expect(page.locator('.doclinc-call-empty-name')).toHaveText('dr. Bima Verifikasi');
    await expect(page.locator('.doclinc-call-empty-role')).toHaveText('Dokter');
  });

  test('invalid provider photo key remains text-first instead of rendering a default placeholder', async ({ page }) => {
    const data = fixtureData({ voiceInvalidPhoto: true });
    await openVoice(page, data.actors.WARGA, data.requests.visit_happy);
    await expect(page.locator('[data-voice-profile-photo]')).toHaveCount(0);
    await expect(page.locator('[data-voice-profile-fallback]')).toHaveCount(0);
    await expect(page.locator('.doclinc-call-empty-name')).toHaveText('dr. Bima Verifikasi');
  });

  test('long provider identity remains readable without horizontal overflow', async ({ page }) => {
    const data = fixtureData({ voiceLongName: true });
    for (const viewport of [{ width: 353, height: 800 }, { width: 390, height: 844 }, { width: 430, height: 932 }]) {
      await page.setViewportSize(viewport);
      await openVoice(page, data.actors.WARGA, data.requests.visit_happy);
      await expect(page.locator('.doclinc-call-empty-name')).toHaveText('dr. Muhammad Rizky Pratama');
      const metrics = await page.evaluate(() => ({
        bodyOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        nameOverflow: (() => {
          const node = document.querySelector('.doclinc-call-empty-name');
          return node ? node.scrollWidth > node.clientWidth : true;
        })(),
        nameBox: document.querySelector('.doclinc-call-empty-name')?.getBoundingClientRect().toJSON(),
        endCallIcon: (() => { const image = document.querySelector('#doclincCallEnd img'); const box = image?.getBoundingClientRect(); return { naturalWidth: image?.naturalWidth || 0, naturalHeight: image?.naturalHeight || 0, width: box?.width || 0, height: box?.height || 0 }; })()
      }));
      expect(metrics.bodyOverflow).toBe(false);
      expect(metrics.nameOverflow).toBe(false);
      expect(metrics.nameBox?.width || 0).toBeGreaterThan(0);
      expect(metrics.endCallIcon.naturalWidth).toBeGreaterThan(0);
      expect(metrics.endCallIcon.width).toBeGreaterThan(0);
    }
  });

  test('waveform load failure keeps the voice surface usable', async ({ page }) => {
    await page.route('**/assets/doclinc_ui/lottie/soundwave.json', (route) => route.abort());
    const data = fixtureData({ voiceTextOnly: true });
    await openVoice(page, data.actors.WARGA, data.requests.visit_happy, { waveformFailure: true });
    await expect(page.locator('.doclinc-call-modal.is-open.is-audio')).toBeVisible();
    await expect(page.locator('[data-voice-waveform]')).toHaveAttribute('data-lottie-loaded', 'false');
    await expect(page.locator('.doclinc-call-empty-name')).toBeVisible();
    await expect(page.locator('#doclincCallEnd')).toBeVisible();
  });

  test('authenticated Warga incoming voice surface renders provider identity and actions', async ({ page }) => {
    const data = fixtureData({ voiceTextOnly: true });
    await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
    await loginActor(page, data.actors.WARGA);
    await page.goto(`/chat?request_id=${encodeURIComponent(data.requests.visit_happy)}`, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.showIncomingCall({ call_id: 991001, call_type: 'audio', caller_name: 'dr. Bima Verifikasi' }));
    await expect(page.locator('#doclincIncomingCall')).toBeVisible();
    await expect(page.locator('#doclincIncomingCaller')).toHaveText('dr. Bima Verifikasi');
    await expect(page.locator('#doclincIncomingType')).toHaveText('Panggilan suara');
    await expect(page.locator('#doclincIncomingAnswer')).toHaveText('Terima');
    await expect(page.locator('#doclincIncomingReject')).toHaveText('Tolak');
  });
});
