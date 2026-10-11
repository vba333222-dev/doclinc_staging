const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const { fixtureData, loginActor } = require('./fixtures');

const outputDir = process.env.VISUAL_REVIEW_OUTPUT_DIR || path.resolve(__dirname, '..', 'test-results', 'visual-review', 'chat-strict-geometry-final');

async function sendMessage(page, requestId, text) {
  const token = await page.locator('meta[name="doclinc-csrf-token"]').getAttribute('content');
  const response = await page.request.post('/chat/send', {
    form: { request_id: String(requestId), message_text: text, doclinc_csrf_token: token },
    headers: { Accept: 'application/json' },
  });
  if (response.status() !== 200) throw new Error(`chat_send_failed_${response.status()}_${(await response.text()).slice(0, 240)}`);
}

function box(node) {
  const rect = node.getBoundingClientRect();
  return { x: rect.x, y: rect.y, right: rect.right, bottom: rect.bottom, width: rect.width, height: rect.height };
}

test.describe('strict Chat production geometry', () => {
  test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');

  test('matches the supplied 468x1600 Chat geometry and content contract', async ({ page, browser }) => {
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
    await page.clock.install({ time: new Date('2024-05-24T12:00:00+07:00') });
    await page.route('**/chat/messages*', async route => {
      const response = await route.fetch();
      const payload = JSON.parse(await response.text());
      ['09:41:00', '09:42:00', '09:43:00', '09:44:00', '09:45:00'].forEach((time, index) => {
        if (payload.messages && payload.messages[index]) payload.messages[index].created_at = `2024-05-24 ${time}`;
      });
      await route.fulfill({ response, body: JSON.stringify(payload) });
    });
    await page.goto(`/chat?request_id=${requestId}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#chatMessages')).not.toContainText('Memuat pesan...', { timeout: 15000 });
    await page.evaluate(() => document.fonts && document.fonts.ready);
    // The supplied strict reference is a full conversation frame. Reset the
    // production list to its stable top position after the poller has rendered
    // all fixture messages; this is scroll-state control, not DOM injection.
    await page.locator('#chatMessages').evaluate(node => { node.scrollTop = 0; });

    await expect(page.locator('.chat-message-row')).toHaveCount(5);
    await expect(page.locator('.chat-avatar[data-chat-photo-state="photo"]')).toHaveCount(1);
    await expect(page.locator('.chat-avatar[data-chat-photo-state="photo"]')).toHaveJSProperty('complete', true);
    await expect(page.locator('.chat-title-subtext')).toContainText('Online');
    await expect(page.locator('.chat-title-subtext')).not.toContainText('No. Antrian');
    await expect(page.locator('#chatDateSeparator')).toContainText('Hari ini, 24 Mei 2024');
    await expect(page.locator('.chat-time')).toHaveText(['09:41', '09:42', '09:43', '09:44', '09:45']);

    const measured = await page.evaluate(() => {
      const box = node => {
        const rect = node.getBoundingClientRect();
        return { x: rect.x, y: rect.y, right: rect.right, bottom: rect.bottom, width: rect.width, height: rect.height };
      };
      const get = selector => {
        const node = document.querySelector(selector);
        return node ? box(node) : null;
      };
      const bubbles = [...document.querySelectorAll('.chat-bubble')].map(node => box(node));
      const lineCounts = [...document.querySelectorAll('.chat-bubble')].map(node => {
        const textNode = node.querySelector('.chat-message-text') || [...node.childNodes].find(child => child.nodeType === Node.TEXT_NODE && child.textContent.trim() !== '');
        if (!textNode) return 0;
        const range = document.createRange();
        range.selectNodeContents(textNode);
        return new Set([...range.getClientRects()].map(rect => Math.round(rect.top))).size;
      });
      const rows = [...document.querySelectorAll('.chat-message-row')].map(node => box(node));
      const form = get('.chat-form');
      const inputRow = get('.chat-input-row');
      const input = get('.chat-input');
      const style = node => node ? getComputedStyle(node) : null;
      const formStyle = style(document.querySelector('.chat-form'));
      const dateStyle = style(document.querySelector('.chat-date-separator'));
      const inputRowStyle = style(document.querySelector('.chat-input-row'));
      const inputPillStyle = style(document.querySelector('.chat-input'));
      return {
        shell: get('.chat-shell'), header: get('.chat-header'), avatar: get('.chat-avatar'),
        list: get('.chat-list'), dateStyle: dateStyle && { marginTop: dateStyle.marginTop, marginBottom: dateStyle.marginBottom, paddingTop: dateStyle.paddingTop, display: dateStyle.display },
        name: get('#chatPartnerName'), status: get('.chat-presence-status'), date: get('.chat-date-separator'),
        bubbles, lineCounts, rows, form, inputRow, input,
        composer: {
          formBackground: formStyle && formStyle.backgroundColor,
          inputRowBackground: inputRowStyle && inputRowStyle.backgroundColor,
          inputBackground: inputPillStyle && inputPillStyle.backgroundColor,
          inputRadius: inputPillStyle && inputPillStyle.borderRadius,
          directChildren: [...document.querySelector('.chat-form').children].map(node => node.className),
        },
        icons: {
          attachment: get('#attachmentButton'), camera: get('#imageButton'), send: get('.chat-send'),
        },
      };
    });
    console.log(`CHAT_GEOMETRY_MEASURED ${JSON.stringify(measured)}`);

    expect(measured.shell.width).toBeCloseTo(390, 0);
    expect(measured.shell.height).toBeCloseTo(1078, 0);
    expect(measured.header.height).toBeCloseTo(72, 0);
    expect(measured.avatar.width).toBeCloseTo(42, 0);
    expect(measured.status.width).toBeLessThan(100);
    expect(measured.bubbles).toHaveLength(5);
    expect(measured.lineCounts).toEqual([3, 3, 3, 3, 6]);
    expect(measured.bubbles[0].width).toBeGreaterThanOrEqual(268);
    expect(measured.bubbles[1].width).toBeGreaterThanOrEqual(310);
    expect(measured.bubbles[2].width).toBeGreaterThanOrEqual(268);
    expect(measured.bubbles[3].width).toBeGreaterThanOrEqual(310);
    expect(measured.bubbles[4].width).toBeGreaterThanOrEqual(282);
    expect(measured.rows[4].bottom).toBeGreaterThan(measured.shell.y + measured.shell.height * 0.72);
    expect(measured.composer.formBackground).toBe('rgb(255, 255, 255)');
    expect(measured.composer.inputRowBackground).toBe('rgba(0, 0, 0, 0)');
    expect(measured.composer.inputBackground).toBe('rgb(241, 245, 249)');
    expect(measured.composer.directChildren).toContain('chat-input-row');
    expect(measured.composer.inputRadius).toMatch(/2[0-9]px|999px/);
    expect(measured.input.width).toBeGreaterThanOrEqual(210);
    expect(measured.icons.attachment.x).toBeLessThan(measured.input.x);
    expect(measured.icons.camera.x).toBeLessThan(measured.input.x);
    expect(measured.icons.send.x).toBeGreaterThan(measured.input.right);

    fs.mkdirSync(outputDir, { recursive: true });
    await page.screenshot({ path: path.join(outputDir, 'chat-actual-468.png'), animations: 'disabled', caret: 'hide' });
  });
});
