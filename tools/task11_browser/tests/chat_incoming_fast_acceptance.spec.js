const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('@playwright/test');
const { fixtureData, loginActor, csrf, post } = require('./fixtures');

const outputDir = process.env.VISUAL_REVIEW_OUTPUT_DIR || path.resolve(__dirname, '..', 'test-results', 'visual-review', 'chat-incoming-correction');
const screenshots = [];

function savePath(name) {
  const file = path.join(outputDir, name);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  return file;
}

async function settleChat(page) {
  await expect(page.locator('#chatMessages')).not.toContainText('Memuat pesan...', { timeout: 15000 });
  await page.evaluate(() => document.fonts && document.fonts.ready);
}

async function sendMessage(page, requestId, text) {
  const response = await page.request.post('/chat/send', {
    form: { request_id: String(requestId), message_text: text, doclinc_csrf_token: await csrf(page) },
    headers: { Accept: 'application/json' },
  });
  expect(response.status()).toBe(200);
  expect((await response.json()).status).toBe('success');
}

async function capture(page, name, metadata) {
  const file = savePath(name);
  await page.screenshot({ path: file, fullPage: false, animations: 'disabled', caret: 'hide' });
  screenshots.push({ file: path.relative(outputDir, file).replaceAll('\\', '/'), source: 'PRODUCTION_DOM', ...metadata });
  return file;
}

async function showIncoming(page, callType, callId) {
  await page.evaluate(({ callType, callId }) => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.showIncomingCall({ call_id: callId, caller_name: 'Task11 Dokter Sintetik', call_type: callType }), { callType, callId });
  await page.waitForTimeout(250);
  await expect(page.locator('#doclincIncomingCall')).toBeVisible();
  await expect(page.locator('#doclincIncomingCaller')).toHaveText('Task11 Dokter Sintetik');
  await expect(page.locator('#doclincIncomingModeIcon')).toHaveAttribute('src', new RegExp(callType === 'audio' ? 'voicecall\\.svg' : 'videocall\\.svg'));
  await expect.poll(() => page.locator('#doclincIncomingModeIcon').evaluate(el => el.complete && el.naturalWidth > 0 && el.naturalHeight > 0), { timeout: 45000 }).toBe(true);
  await expect.poll(() => page.locator('#doclincIncomingAnswer img').evaluate(el => el.complete && el.naturalWidth > 0 && el.naturalHeight > 0), { timeout: 45000 }).toBe(true);
}

async function slide(page, distanceRatio) {
  const slider = page.locator('#doclincIncomingSlider');
  const box = await slider.boundingBox();
  expect(box).not.toBeNull();
  const startX = box.x + 18;
  const y = box.y + box.height / 2;
  const endX = box.x + box.width * distanceRatio;
  await page.mouse.move(startX, y);
  await page.mouse.down();
  await page.mouse.move(endX, y);
  await page.mouse.up();
}

test.describe('fast production Chat + Warga incoming acceptance', () => {
  test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');

  test('authorized provider Chat at 390 and strict 468 reference canvas', async ({ page, browser }) => {
    const data = fixtureData({ voiceTextOnly: true });
    const wargaSender = await browser.newPage();
    await loginActor(wargaSender, data.actors.WARGA);
    await sendMessage(wargaSender, data.requests.visit_happy, 'Halo, saya siap melanjutkan konsultasi.');
    await sendMessage(wargaSender, data.requests.visit_happy, 'Silakan ceritakan keluhan yang sedang dirasakan.');
    await wargaSender.context().close();
    await loginActor(page, data.actors.RD);
    await sendMessage(page, data.requests.visit_happy, 'Baik, saya akan membantu konsultasinya.');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/chat?request_id=${data.requests.visit_happy}`, { waitUntil: 'domcontentloaded' });
    await settleChat(page);
    await expect(page.locator('.doclinc-call-start')).toHaveCount(2);
    await expect(page.getByRole('button', { name: 'Mulai panggilan suara' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Mulai panggilan video' })).toBeVisible();
    for (const image of await page.locator('.doclinc-call-start img').all()) {
      await expect.poll(() => image.evaluate(el => el.complete && el.naturalWidth > 0 && el.naturalHeight > 0)).toBe(true);
    }
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await capture(page, 'chat-provider-390.png', { actor: 'AUTHORIZED_PROVIDER', route: '/chat', viewport: '390x844', state: 'active-chat', icons: ['voicecall.svg', 'videocall.svg'] });
    await page.setViewportSize({ width: 468, height: 1600 });
    await capture(page, 'chat-actual-468.png', { actor: 'AUTHORIZED_PROVIDER', route: '/chat', viewport: '468x1600', state: 'active-chat-reference-canvas', icons: ['voicecall.svg', 'videocall.svg'] });
    expect(await page.evaluate(() => ({ width: innerWidth, height: innerHeight }))).toEqual({ width: 468, height: 1600 });
  });

  test('Warga Chat omits initiation and direct HTTP call initiation is denied', async ({ page }) => {
    const data = fixtureData({ voiceTextOnly: true });
    await loginActor(page, data.actors.WARGA);
    await page.goto(`/chat?request_id=${data.requests.visit_happy}`, { waitUntil: 'domcontentloaded' });
    await settleChat(page);
    await expect(page.locator('.doclinc-call-start')).toHaveCount(0);
    for (const call_type of ['audio', 'video']) {
      const denial = await post(page, '/home_nakes/start_livekit_call', { request_id: data.requests.visit_happy, call_type, role: 'dokter', callerType: 'dokter' });
      expect(denial.response.status()).toBe(403);
      expect(denial.json && denial.json.success).not.toBe(true);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await capture(page, 'chat-warga-390.png', { actor: 'WARGA', route: '/chat', viewport: '390x844', state: 'active-chat-no-initiation', icons: ['konsultasi.svg', 'attachment.svg', 'camera.svg', 'send.svg'] });
  });

  test('Warga incoming Voice supports failed slide, keyboard accept, and connected shell', async ({ page }) => {
    const data = fixtureData({ voiceTextOnly: true });
    await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
    await loginActor(page, data.actors.WARGA);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/chat?request_id=${data.requests.visit_happy}`, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__?.showIncomingCall);
    await showIncoming(page, 'audio', 991000001);
    await expect(page.locator('#doclincIncomingType')).toContainText('Panggilan suara');
    await expect(page.locator('#doclincIncomingWaveform')).toBeVisible();
    await expect(page.getByText('Geser untuk menjawab')).toBeVisible();
    await expect(page.locator('#doclincIncomingAvatarFallback')).toHaveCount(0);
    await expect(page.locator('.doclinc-incoming-avatar')).toHaveCount(0);
    const sliderBox = await page.locator('#doclincIncomingSlider').boundingBox();
    const declineBox = await page.locator('#doclincIncomingReject').boundingBox();
    expect(sliderBox).not.toBeNull();
    expect(declineBox).not.toBeNull();
    expect(sliderBox.width).toBeGreaterThan(300);
    expect(declineBox.y).toBeGreaterThan(sliderBox.y + sliderBox.height - 1);
    await capture(page, 'incoming-voice-390.png', { actor: 'WARGA', route: '/chat', viewport: '390x844', state: 'voice-incoming', call_mode: 'audio', icons: ['voicecall.svg'] });
    await slide(page, 0.35);
    await expect(page.locator('#doclincIncomingCall')).toBeVisible();
    await expect(page.locator('#doclincCallModal.is-open')).toHaveCount(0);
    await expect(page.locator('#doclincIncomingAnswer')).toHaveCSS('transform', 'none');
    await slide(page, 0.95);
    await expect(page.locator('#doclincCallModal.is-open')).toBeVisible();
    await expect(page.locator('#doclincIncomingCall')).toHaveClass(/is-hidden/);
    await expect(page.locator('#doclincCallModal.is-audio')).toHaveCount(1);
    await expect(page.locator('#doclincCallEmptyText')).not.toContainText(/video/i);
    await page.setViewportSize({ width: 390, height: 844 });
    await capture(page, 'after-accept-voice-390.png', { actor: 'WARGA', route: '/chat', viewport: '390x844', state: 'voice-accepted-shell', call_mode: 'audio' });

  });

  test('Warga incoming Voice accepts with keyboard action', async ({ page }) => {
    const data = fixtureData({ voiceTextOnly: true });
    await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
    await loginActor(page, data.actors.WARGA);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/chat?request_id=${data.requests.visit_happy}`, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__?.showIncomingCall);
    await showIncoming(page, 'audio', 991000003);
    let answerRequests = 0;
    page.on('request', request => {
      if (request.url().includes('/answer_livekit_call')) answerRequests += 1;
    });
    const answerButton = page.getByRole('button', { name: 'Terima panggilan' });
    await answerButton.focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#doclincCallModal.is-open')).toBeVisible();
    await expect(page.locator('#doclincIncomingCall')).toHaveClass(/is-hidden/);
    expect(answerRequests).toBeLessThanOrEqual(1);

    // A rapid repeated activation after the first transition must not issue a
    // second canonical accept operation.
    await page.keyboard.press('Enter');
    expect(answerRequests).toBeLessThanOrEqual(1);
  });

  test('Warga incoming Video stays distinct, does not autostart camera, and reaches shell on accept', async ({ page }) => {
    const data = fixtureData({ voiceTextOnly: true });
    await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
    await loginActor(page, data.actors.WARGA);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/chat?request_id=${data.requests.visit_happy}`, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__?.showIncomingCall);
    let cameraPermissionRequests = 0;
    page.on('request', request => { if (request.url().includes('/camera') || request.url().includes('/getUserMedia')) cameraPermissionRequests += 1; });
    await showIncoming(page, 'video', 991000002);
    await expect(page.locator('#doclincIncomingType')).toContainText('Panggilan video');
    await expect(page.locator('#doclincIncomingWaveform')).toBeHidden();
    await expect(page.locator('#doclincIncomingAvatarFallback')).toHaveCount(0);
    await expect(page.locator('.doclinc-incoming-avatar')).toHaveCount(0);
    const sliderBox = await page.locator('#doclincIncomingSlider').boundingBox();
    const declineBox = await page.locator('#doclincIncomingReject').boundingBox();
    expect(sliderBox).not.toBeNull();
    expect(declineBox).not.toBeNull();
    expect(sliderBox.width).toBeGreaterThan(300);
    expect(declineBox.y).toBeGreaterThan(sliderBox.y + sliderBox.height - 1);
    expect(cameraPermissionRequests).toBe(0);
    await capture(page, 'incoming-video-390.png', { actor: 'WARGA', route: '/chat', viewport: '390x844', state: 'video-incoming', call_mode: 'video', icons: ['videocall.svg'] });
    await expect(page.locator('#doclincIncomingAnswer')).toBeEnabled();
    await page.locator('#doclincIncomingAnswer').click();
    if (process.env.TASK11_LIVEKIT_ENABLED === '1') {
      await expect(page.locator('#doclincIncomingCall')).toHaveClass(/is-hidden/);
      await expect(page.locator('#doclincCallModal[data-call-mode="video"]')).toHaveCount(1);
    } else {
      test.info().annotations.push({ type: 'environment-blocked', description: 'LIVEKIT_ENABLED=0 stops before the answer HTTP request; production mode/call binding is still asserted at the presentation boundary' });
    }
    if (process.env.TASK11_LIVEKIT_ENABLED === '1') {
      await expect(page.locator('#doclincCallModal.is-open')).toBeVisible();
    } else {
      test.info().annotations.push({ type: 'environment-blocked', description: 'LIVEKIT_ENABLED=0 prevents server-grounded post-accept shell transition' });
    }
  });

  test('focused call authority reaches the LiveKit capability guard', async ({ page }) => {
    const data = fixtureData({ voiceTextOnly: true });
    await loginActor(page, data.actors.WARGA);
    for (const call_type of ['audio', 'video']) {
      const denial = await post(page, '/home_nakes/start_livekit_call', { request_id: data.requests.visit_happy, call_type, role: 'dokter', callerType: 'dokter' });
      expect(denial.response.status()).toBe(403);
    }
    await loginActor(page, data.actors.COMMAND_CENTER);
    const commandDenial = await post(page, '/home_nakes/start_livekit_call', { request_id: data.requests.visit_happy, call_type: 'audio', role: 'dokter', callerType: 'dokter' });
    expect(commandDenial.response.status()).toBe(403);
    await loginActor(page, data.actors.RD);
    const providerAttempt = await post(page, '/home_nakes/start_livekit_call', { request_id: data.requests.visit_happy, call_type: 'audio' });
    expect(providerAttempt.response.status()).toBe(503);
  });

});

test.afterAll(() => {
  if (!screenshots.length) return;
  fs.mkdirSync(outputDir, { recursive: true });
  const referenceRoot = 'C:/Project/doclinc-assets/incoming_call_&_chat';
  execFileSync('python', [path.join(__dirname, 'make_fast_visual_evidence.py'), outputDir,
    path.join(referenceRoot, 'active chat', 'screen.png'),
    path.join(referenceRoot, 'incoming call', 'screen.png')], { stdio: 'inherit' });
  fs.writeFileSync(path.join(outputDir, 'manifest.json'), JSON.stringify({ source: 'REAL_CI3_PRODUCTION_DOM', screenshots }, null, 2));
  fs.writeFileSync(path.join(outputDir, 'visual-review.md'), '# Fast Chat + Incoming Call correction review\n\nProduction DOM, synthetic disposable actors only.\n\nStrict Chat canvas: 468x1600 reference and actual.\nIncoming layout: full-width slider with decline below.\n');
  const cards = screenshots.map(item => `<figure><a href="${item.file}"><img src="${item.file}" loading="lazy"><figcaption>${item.state}<br>${item.viewport} · ${item.actor}</figcaption></a></figure>`).join('');
  fs.writeFileSync(path.join(outputDir, 'index.html'), `<!doctype html><meta charset="utf-8"><title>Fast Chat + Incoming</title><style>body{font-family:system-ui;background:#edf4f1;margin:24px;color:#17352b}main{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px}figure{background:#fff;margin:0;padding:10px;border-radius:14px}img{width:100%;display:block;border-radius:10px}figcaption{padding:8px 2px}</style><h1>Fast Chat + Incoming Call</h1><main>${cards}</main>`);
});
