const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('@playwright/test');
const { fixtureData, loginActor, csrf } = require('./fixtures');

const outputDir = process.env.VISUAL_REVIEW_OUTPUT_DIR || path.resolve(__dirname, '..', 'test-results', 'visual-review', 'chat-strict-reference-fast');
const referenceRoot = 'C:/Project/doclinc-assets/incoming_call_&_chat';
const screenshots = [];

async function sendMessage(page, requestId, text) {
  const response = await page.request.post('/chat/send', {
    form: { request_id: String(requestId), message_text: text, doclinc_csrf_token: await csrf(page) },
    headers: { Accept: 'application/json' },
  });
  expect(response.status()).toBe(200);
  expect((await response.json()).status).toBe('success');
}

async function capture(page, name, metadata) {
  const file = path.join(outputDir, name);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  await page.screenshot({ path: file, fullPage: false, animations: 'disabled', caret: 'hide' });
  screenshots.push({ file: name, source: 'PRODUCTION_DOM', ...metadata });
}

test.describe('strict production Chat reference', () => {
  test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');

  test('reference-shaped conversation uses production Chat DOM at 468x1600', async ({ page, browser }) => {
    const data = fixtureData({ voiceTextOnly: true, chatReference: true });
    const warga = await browser.newPage();
    await loginActor(warga, data.actors.WARGA);
    const requestId = data.requests.visit_happy;
    const messages = [
      'Halo, selamat pagi. Ada keluhan atau gejala yang bisa saya bantu periksa hari ini?',
      'Selamat pagi dok. Sudah 2 hari ini saya demam naik turun, tenggorokan sakit saat menelan, dan badan terasa pegal dok.',
      'Baik, apakah sempat diukur suhu tubuhnya pagi ini? Apakah ada gejala batuk, pilek, atau mual?',
      'Tadi pagi diukur 38.3°C dok. Ada batuk kering ringan, tidak pilek dan tidak mual. Hanya nafsu makan agak berkurang.',
      'Baik, gejala mengarah pada faringitis akut disertai demam. Saya buatkan resep penurun demam dan tablet hisap pereda radang tenggorokan ya. Jangan lupa perbanyak minum air hangat dan istirahat cukup.',
    ];
    await sendMessage(warga, requestId, messages[0]);
    await loginActor(page, data.actors.RD);
    await sendMessage(page, requestId, messages[1]);
    await sendMessage(warga, requestId, messages[2]);
    await sendMessage(page, requestId, messages[3]);
    await sendMessage(warga, requestId, messages[4]);
    await warga.context().close();

    await page.setViewportSize({ width: 468, height: 1600 });
    await page.goto(`/chat?request_id=${requestId}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#chatMessages')).not.toContainText('Memuat pesan...', { timeout: 15000 });
    await page.evaluate(() => document.fonts && document.fonts.ready);

    await expect(page.locator('.chat-call-actions .doclinc-call-start')).toHaveCount(2);
    await expect(page.locator('.chat-message-row')).toHaveCount(5);
    await expect(page.locator('.chat-message-avatar, .chat-message-avatar-fallback')).toHaveCount(3);

    const geometry = await page.evaluate(() => {
      const rect = selector => {
        const node = document.querySelector(selector);
        if (!node) return null;
        const box = node.getBoundingClientRect();
        return { x: box.x, y: box.y, width: box.width, height: box.height, right: box.right, bottom: box.bottom };
      };
      const shell = rect('.chat-shell');
      const header = rect('.chat-header');
      const queue = rect('.chat-info-banner');
      const list = rect('#chatMessages');
      const composer = rect('.chat-form');
      const input = rect('.chat-input-row');
      const rows = [...document.querySelectorAll('.chat-message-row')].map(node => {
        const box = node.getBoundingClientRect();
        return { y: box.y, bottom: box.bottom, width: box.width };
      });
      return { viewport: { width: innerWidth, height: innerHeight }, shell, header, queue, list, composer, input, rows };
    });
    expect(geometry.viewport).toEqual({ width: 468, height: 1600 });
    expect(geometry.shell.width).toBeGreaterThanOrEqual(388);
    expect(geometry.shell.width).toBeLessThanOrEqual(392);
    expect(geometry.shell.height).toBeGreaterThanOrEqual(1076);
    expect(geometry.shell.height).toBeLessThanOrEqual(1080);
    expect(geometry.header.height).toBeGreaterThanOrEqual(68);
    expect(geometry.header.height).toBeLessThanOrEqual(76);
    expect(geometry.queue.height).toBeLessThanOrEqual(30);
    expect(geometry.rows[4].bottom).toBeGreaterThan(geometry.shell.y + geometry.shell.height * 0.72);
    expect(geometry.input.height).toBeGreaterThanOrEqual(50);
    expect(geometry.input.height).toBeLessThanOrEqual(62);
    expect(geometry.input.width).toBeGreaterThan(360);

    await capture(page, 'chat-actual-468.png', {
      actor: 'AUTHORIZED_PROVIDER_STRICT_REFERENCE_FIXTURE',
      route: '/chat',
      viewport: '468x1600',
      state: 'active-chat-reference-shaped',
      icons: ['voicecall.svg', 'videocall.svg', 'camera.svg', 'attachment.svg', 'send.svg'],
      reference: 'active chat/screen.png',
    });
  });
});

test.afterAll(() => {
  if (!screenshots.length) return;
  fs.mkdirSync(outputDir, { recursive: true });
  execFileSync('python', [path.join(__dirname, 'make_fast_visual_evidence.py'), outputDir,
    path.join(referenceRoot, 'active chat', 'screen.png'),
    path.join(referenceRoot, 'incoming call', 'screen.png')], { stdio: 'inherit' });
  fs.writeFileSync(path.join(outputDir, 'manifest.json'), JSON.stringify({
    source: 'REAL_CI3_PRODUCTION_DOM',
    reference: {
      screen: path.join(referenceRoot, 'active chat', 'screen.png'),
      html: path.join(referenceRoot, 'active chat', 'code.html'),
      design: path.join(referenceRoot, 'DESIGN.md'),
      viewport: '468x1600',
    },
    screenshots: [
      ...screenshots,
      { file: 'chat-reference-468.png', source: 'REFERENCE_INPUT_CANVAS', viewport: '468x1600', state: 'reference' },
      { file: 'chat-overlay-468.png', source: 'VISUAL_COMPARISON', viewport: '468x1600', state: 'reference-overlay' },
    ],
  }, null, 2));
  fs.writeFileSync(path.join(outputDir, 'visual-review.md'), '# Strict Chat reference recovery\n\nProduction DOM and production CSS with deterministic disposable conversation data. Incoming call surfaces are frozen for this pass.\n');
  fs.writeFileSync(path.join(outputDir, 'index.html'), '<!doctype html><meta charset="utf-8"><title>Strict Chat Reference</title><style>body{font-family:system-ui;background:#e8eef3;margin:24px;color:#17352b}main{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px}figure{background:#fff;margin:0;padding:10px;border-radius:14px}img{width:100%;display:block;border-radius:10px}figcaption{padding:8px 2px}</style><h1>Strict Chat Reference</h1><main><figure><a href="chat-reference-468.png"><img src="chat-reference-468.png"><figcaption>Reference · 468x1600</figcaption></a></figure><figure><a href="chat-actual-468.png"><img src="chat-actual-468.png"><figcaption>Actual production DOM · 468x1600</figcaption></a></figure><figure><a href="chat-overlay-468.png"><img src="chat-overlay-468.png"><figcaption>Overlay · 468x1600</figcaption></a></figure></main>');
});
