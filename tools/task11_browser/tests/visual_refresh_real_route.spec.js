const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test.describe('visual refresh production-route integration', () => {
  test('authenticated chat route renders the production LiveKit shell and local assets', async ({ page }) => {
    test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires the disposable CI3 web container');

    const { fixtureData, loginActor } = require('./fixtures');
    const data = fixtureData();
    await loginActor(page, data.actors.WARGA);

    const failed = [];
    page.on('requestfailed', request => failed.push(request.url()));
    const response = await page.goto(`/chat?request_id=${encodeURIComponent(data.requests.authority)}`, { waitUntil: 'domcontentloaded' });
    expect(response && response.status()).toBe(200);
    await expect(page.locator('link[href*="doclinc-livekit.css"]')).toHaveCount(1);
    await expect(page.locator('link[href*="doclinc-livekit-call.css"]')).toHaveCount(1);
    await expect(page.locator('#doclincCallModal')).toHaveCount(1);
    await expect(page.locator('#doclincCallClose')).toHaveAttribute('aria-label', /Tutup/);
    await expect(page.locator('#doclincCallEnd')).toHaveAttribute('aria-label', /Akhiri/);
    await expect(page.locator('.doclinc-call-security, .doclinc-call-signal, .doclinc-call-head-more')).toHaveCount(0);
    await expect(page.locator('.dl-call-control')).toHaveCount(5);
    await expect(page.locator('.doclinc-call-control-label')).toHaveCount(0);
    await expect(page.locator('.doclinc-call-prejoin, #doclincCallPrejoin, #doclincCallJoin')).toHaveCount(0);
    await expect(page.locator('#doclincCallParticipants, #doclincCallParticipantsPanel')).toHaveCount(0);
    // Wait for the real chat poller to settle before collecting visual evidence;
    // a transient "Memuat pesan..." screen is not a valid final surface.
    await expect(page.locator('#chatMessages .chat-message-row, #chatMessages .chat-muted, #chatMessages .chat-error')).toHaveCount(1, { timeout: 10000 });
    await expect(page.locator('#chatMessages')).not.toContainText('Memuat pesan...');
    const visibleCopy = await page.locator('#doclincCallModal').evaluate(root => {
      const text = root.innerText;
      const labels = [...root.querySelectorAll('[aria-label], [title]')].flatMap(el => [el.getAttribute('aria-label') || '', el.getAttribute('title') || '']).join(' ');
      return `${text} ${labels}`;
    });
    for (const token of ['Mute', 'Camera', 'Speaker', 'Chat', 'Participants', 'More', 'End Call', 'Minimize', 'Waiting', 'Connected', 'Retry']) {
      expect(visibleCopy).not.toMatch(new RegExp(`\\b${token.replace(/[- ]/g, '[- ]')}\\b`, 'i'));
    }
    await expect(page.locator('#doclincCallClose i.fa-times')).toHaveCount(1);
    await expect(page.locator('#doclincCallEnd i.fa-phone-slash')).toHaveCount(1);
    expect(failed.filter(url => url.includes('doclinc-livekit.css') || url.includes('doclinc-livekit-call.css') || url.includes('/assets/doclinc_ui/'))).toEqual([]);
    if (process.env.VISUAL_REVIEW_OUTPUT_DIR) {
      const output = path.join(process.env.VISUAL_REVIEW_OUTPUT_DIR, 'desktop', 'chat', 'chat-production-livekit-shell.png');
      fs.mkdirSync(path.dirname(output), { recursive: true });
      await page.screenshot({ path: output, animations: 'disabled', caret: 'hide' });
    }
  });
});
