const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const { fixtureData, loginActor } = require('./fixtures');

const outputDir = process.env.VISUAL_REVIEW_OUTPUT_DIR || path.resolve(__dirname, '..', 'test-results', 'visual-review', 'chat-strict-micro-final');

async function sendMessage(page, requestId, messageText) {
  const token = await page.locator('meta[name="doclinc-csrf-token"]').getAttribute('content');
  const response = await page.request.post('/chat/send', {
    form: { request_id: String(requestId), message_text: messageText, doclinc_csrf_token: token },
    headers: { Accept: 'application/json' },
  });
  if (response.status() !== 200) throw new Error(`chat_send_failed_${response.status()}_${(await response.text()).slice(0, 240)}`);
}

test.describe('Chat strict micro reference', () => {
  test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');

  test('renders the reference content and typography through production DOM', async ({ page, browser }) => {
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
      const times = ['09:41:00', '09:42:00', '09:43:00', '09:44:00', '09:45:00'];
      (payload.messages || []).forEach((message, index) => {
        if (!times[index]) return;
        message.created_at = `2024-05-24 ${times[index]}`;
        if (index === 0 || index === 2 || index === 4) message.sender_display_name = 'dr. Rani, Sp.PD';
        if (index === 1 || index === 3) message.is_read = 1;
      });
      await route.fulfill({ response, body: JSON.stringify(payload) });
    });
    await page.goto(`/chat?request_id=${requestId}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#chatMessages')).not.toContainText('Memuat pesan...', { timeout: 15000 });
    await page.evaluate(() => document.fonts && document.fonts.ready);
    await page.locator('#chatMessages').evaluate(node => { node.scrollTop = 0; });

    await expect(page.locator('.chat-message-row')).toHaveCount(5);
    await expect(page.locator('.chat-avatar[data-chat-photo-state="photo"]')).toHaveCount(1);
    await expect(page.locator('.chat-title-subtext')).toContainText('Online');
    await expect(page.locator('.chat-title-subtext')).not.toContainText('No. Antrian');
    await expect(page.locator('#chatDateSeparator')).toContainText('Hari ini, 24 Mei 2024');
    await expect.poll(async () => page.locator('.chat-time').evaluateAll(nodes => nodes.map(node => {
      const first = node.firstChild;
      return first ? first.textContent.trim() : node.textContent.trim();
    }))).toEqual(['09:41', '09:42', '09:43', '09:44', '09:45']);
    await expect(page.locator('.chat-message-sender')).toHaveText(['dr. Rani, Sp.PD', 'Anda', 'dr. Rani, Sp.PD', 'Anda', 'dr. Rani, Sp.PD']);
    await expect(page.locator('.chat-delivery')).toHaveCount(2);
    await expect(page.locator('.chat-input')).toHaveAttribute('placeholder', /^Ketik pesan untuk dr\. Rani/);

    const measured = await page.evaluate(() => {
      const get = selector => document.querySelector(selector);
      const box = node => { const r = node.getBoundingClientRect(); return { x: r.x, y: r.y, right: r.right, bottom: r.bottom, width: r.width, height: r.height }; };
      const lineCount = node => {
        const text = node.querySelector('.chat-message-text');
        const range = document.createRange();
        range.selectNodeContents(text);
        return new Set([...range.getClientRects()].map(rect => Math.round(rect.top))).size;
      };
      const bubbles = [...document.querySelectorAll('.chat-bubble')];
      const textStyle = getComputedStyle(get('.chat-message-text'));
      const senderStyle = getComputedStyle(get('.chat-message-sender'));
      const timeStyle = getComputedStyle(get('.chat-time'));
      return {
        shell: box(get('.chat-shell')),
        header: box(get('.chat-header')),
        status: box(get('.chat-title-subtext')),
        date: box(get('.chat-date-separator')),
        bubbles: bubbles.map(box),
        lineCounts: bubbles.map(lineCount),
        composer: box(get('.chat-form')),
        input: box(get('.chat-input')),
        attachment: box(get('#attachmentButton')),
        camera: box(get('#imageButton')),
        send: box(get('.chat-send')),
        typography: {
          family: textStyle.fontFamily,
          size: textStyle.fontSize,
          weight: textStyle.fontWeight,
          lineHeight: textStyle.lineHeight,
          letterSpacing: textStyle.letterSpacing,
          senderSize: senderStyle.fontSize,
          senderWeight: senderStyle.fontWeight,
          timeSize: timeStyle.fontSize,
        },
      };
    });
    console.log(`CHAT_MICRO_MEASURED ${JSON.stringify(measured)}`);
    expect(measured.lineCounts).toEqual([3, 3, 3, 3, 6]);
    expect(measured.typography.size).toBe('14px');
    expect(measured.typography.lineHeight).toBe('22px');
    expect(measured.composer.height).toBeGreaterThanOrEqual(78);
    expect(measured.input.right).toBeLessThan(measured.send.x);

    fs.mkdirSync(outputDir, { recursive: true });
    await page.screenshot({ path: path.join(outputDir, 'chat-actual-468.png'), animations: 'disabled', caret: 'hide' });
  });
});
