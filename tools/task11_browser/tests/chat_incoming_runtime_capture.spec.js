const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { fixtureData, loginActor, csrf } = require('./fixtures');

const outputDir = process.env.VISUAL_REVIEW_OUTPUT_DIR || path.resolve(__dirname, '..', 'test-results', 'visual-review', 'chat-incoming-final-runtime');

function ensureDir(file) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
}

async function sendMessage(page, requestId, text) {
  const response = await page.request.post('/chat/send', {
    form: { request_id: String(requestId), message_text: text, doclinc_csrf_token: await csrf(page) },
    headers: { Accept: 'application/json' },
  });
  expect(response.status()).toBe(200);
  const body = await response.json();
  expect(body.status).toBe('success');
}

async function settleChat(page) {
  await expect(page.locator('#chatMessages')).not.toContainText('Memuat pesan...', { timeout: 15000 });
  expect(await page.locator('#chatMessages .chat-message-row, #chatMessages .chat-muted, #chatMessages .chat-error').count()).toBeGreaterThan(0);
  await page.evaluate(() => document.fonts && document.fonts.ready);
}

test.describe('production DOM Chat and Warga incoming-call visual evidence', () => {
  test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');

  test('captures active Chat at screenshot-authoritative desktop and mobile sizes', async ({ page }) => {
    const data = fixtureData({ voiceTextOnly: true });
    await loginActor(page, data.actors.WARGA);
    await sendMessage(page, data.requests.visit_happy, 'Halo, saya siap melanjutkan konsultasi.');
    await sendMessage(page, data.requests.visit_happy, 'Silakan ceritakan keluhan yang sedang dirasakan.');
    await page.goto(`/chat?request_id=${encodeURIComponent(data.requests.visit_happy)}`, { waitUntil: 'domcontentloaded' });
    await settleChat(page);
    await expect(page.locator('.chat-shell.is-active')).toBeVisible();
    await expect(page.locator('.chat-info-banner')).toBeVisible();
    await expect(page.locator('.chat-date-separator')).toBeVisible();
    expect(await page.locator('.chat-bubble').count()).toBeGreaterThan(0);
    await expect(page.locator('#chatMessages')).not.toContainText('Memuat pesan...');

    const common = {
      spec: 'chat_incoming_runtime_capture.spec.js',
      test: 'captures active Chat at screenshot-authoritative desktop and mobile sizes',
      actor: 'WARGA',
      route: `/chat?request_id=${data.requests.visit_happy}`,
      state: 'active-chat',
      icons: ['konsultasi.svg', 'attachment.svg', 'camera.svg', 'send.svg'],
      purpose: 'Production Chat header, runtime date, bubbles, and composer',
      reference: 'active chat/screen.png',
      result: 'PASS',
      source: 'PRODUCTION_DOM',
    };
    for (const [width, height, name] of [[468, 1600, 'chat-active-468x1600.png'], [390, 844, 'chat-active-390x844.png']]) {
      await page.setViewportSize({ width, height });
      const file = path.join(outputDir, 'chat', name);
      ensureDir(file);
      await page.screenshot({ path: file, fullPage: false, animations: 'disabled', caret: 'hide' });
      screenshots.push({ ...common, file: path.relative(outputDir, file).replaceAll('\\', '/'), viewport: `${width}x${height}`, ui_state: 'messages-and-composer', visual_purpose: common.purpose });
    }
  });

  test('captures Warga incoming voice and video states through production presentation hooks', async ({ page }) => {
    const data = fixtureData({ voiceTextOnly: true });
    await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
    await loginActor(page, data.actors.WARGA);
    await page.goto(`/chat?request_id=${encodeURIComponent(data.requests.visit_happy)}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#doclincIncomingCall')).toHaveCount(1);
    await page.waitForFunction(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__ && typeof window.__DOCLINC_LIVEKIT_TEST_HOOKS__.showIncomingCall === 'function');
    const hook = { call_id: 990000001, caller_name: 'Task11 Dokter Sintetik', call_type: 'audio' };
    await page.evaluate(call => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.showIncomingCall(call), hook);
    await expect(page.locator('#doclincIncomingCall')).toBeVisible();
    await expect(page.locator('#doclincIncomingType')).toContainText('Panggilan suara');
    await expect(page.locator('#doclincIncomingWaveform')).toBeVisible();
    await expect.poll(() => page.locator('#doclincIncomingWaveform').evaluate((el) => el.classList.contains('is-fallback') || !!el.querySelector('svg'))).toBe(true);
    await page.waitForFunction(() => { const el = document.querySelector('#doclincIncomingModeIcon'); return !!el && el.complete && el.naturalWidth > 0 && el.naturalHeight > 0; }, null, { timeout: 15000 });
    await page.waitForFunction(() => { const el = document.querySelector('#doclincIncomingAnswer img'); return !!el && el.complete && el.naturalWidth > 0 && el.naturalHeight > 0; }, null, { timeout: 15000 });
    await expect(page.getByRole('button', { name: 'Terima panggilan' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tolak panggilan' })).toBeVisible();
    let file = path.join(outputDir, 'incoming', 'incoming-voice-390x844.png');
    ensureDir(file);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: file, fullPage: false, animations: 'disabled', caret: 'hide' });
    screenshots.push({ spec: 'chat_incoming_runtime_capture.spec.js', test: 'captures Warga incoming voice and video states through production presentation hooks', actor: 'WARGA', route: `/chat?request_id=${data.requests.visit_happy}`, viewport: '390x844', state: 'incoming-voice', local_media: 'not-started', remote_media: 'not-started', icons: ['voicecall.svg', 'soundwave.json'], visual_purpose: 'Incoming voice call with truthful caller identity and accessible answer/decline', reference: 'incoming call/screen.png', result: 'PASS', source: 'PRODUCTION_DOM', file: path.relative(outputDir, file).replaceAll('\\', '/') });

    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.showIncomingCall({ call_id: 990000002, caller_name: 'Task11 Dokter Sintetik', call_type: 'video' }));
    await expect(page.locator('#doclincIncomingType')).toContainText('Panggilan video');
    await expect(page.locator('#doclincIncomingWaveform')).toBeHidden();
    await expect(page.locator('#doclincIncomingModeIcon')).toHaveAttribute('src', /videocall\.svg/);
    await expect.poll(() => page.locator('#doclincIncomingModeIcon').evaluate((el) => el.complete && el.naturalWidth > 0 && el.naturalHeight > 0)).toBe(true);
    file = path.join(outputDir, 'incoming', 'incoming-video-390x844.png');
    ensureDir(file);
    await page.screenshot({ path: file, fullPage: false, animations: 'disabled', caret: 'hide' });
    screenshots.push({ spec: 'chat_incoming_runtime_capture.spec.js', test: 'captures Warga incoming voice and video states through production presentation hooks', actor: 'WARGA', route: `/chat?request_id=${data.requests.visit_happy}`, viewport: '390x844', state: 'incoming-video', local_media: 'not-started', remote_media: 'not-started', icons: ['videocall.svg'], visual_purpose: 'Incoming video call keeps camera mode distinct and does not show voice waveform', reference: 'incoming call/screen.png', result: 'PASS', source: 'PRODUCTION_DOM', file: path.relative(outputDir, file).replaceAll('\\', '/') });
  });
});

const screenshots = [];

test.afterAll(() => {
  if (!screenshots.length) return;
  const manifest = { generated_at: new Date().toISOString(), source: 'REAL_CI3_PRODUCTION_DOM', screenshots };
  fs.mkdirSync(outputDir, { recursive: true });
  fs.writeFileSync(path.join(outputDir, 'manifest.json'), JSON.stringify(manifest, null, 2));
  fs.writeFileSync(path.join(outputDir, 'visual-review.md'), '# Chat + Incoming Call production-DOM review\n\nScreenshots are captured from the real CI3 route with synthetic disposable fixtures. No reference images or patient data are embedded in production.\n');
  const cards = screenshots.map(item => `<figure><a href="${item.file}"><img src="${item.file}" loading="lazy"><figcaption><strong>${item.state}</strong><br>${item.viewport} · ${item.actor}<br>${item.icons.join(', ')}</figcaption></a></figure>`).join('');
  fs.writeFileSync(path.join(outputDir, 'index.html'), `<!doctype html><meta charset="utf-8"><title>DokLinc Chat + Incoming review</title><style>body{font-family:system-ui;background:#eef4f1;color:#18352b;margin:24px}main{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px}figure{margin:0;background:#fff;border-radius:16px;padding:10px;box-shadow:0 6px 24px #0001}img{display:block;width:100%;height:auto;border-radius:10px}figcaption{padding:10px 4px;line-height:1.45}</style><h1>Chat + Incoming Call</h1><main>${cards}</main>`);
});
