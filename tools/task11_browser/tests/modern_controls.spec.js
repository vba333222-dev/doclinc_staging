const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const thread = fs.readFileSync(path.join(repoRoot, 'application/modules/chat/views/thread_v.php'), 'utf8');
const css = fs.readFileSync(path.join(repoRoot, 'assets/css/doclinc-livekit.css'), 'utf8');

function icon(name) {
  const file = path.join(repoRoot, 'assets', 'doclinc_ui', 'icon-pack', name);
  return `data:image/svg+xml;base64,${fs.readFileSync(file, 'base64')}`;
}

test('LiveKit controls use circular lightweight geometry and retain distinct end-call hierarchy', async ({ page }) => {
  await page.setContent(`<style>${css}</style><main class="doclinc-call-panel"><nav class="doclinc-call-controls">
    <button id="more" class="doclinc-call-control"><img src="${icon('more.svg')}" alt=""></button>
    <button id="camera" class="doclinc-call-control is-active"><img src="${icon('videocall.svg')}" alt=""></button>
    <button id="speaker" class="doclinc-call-control is-active"><img src="${icon('loudspeaker.svg')}" alt=""></button>
    <button id="mic" class="doclinc-call-control is-off"><img src="${icon('mute.svg')}" alt=""></button>
    <button id="end" class="doclinc-call-control end-call"><img src="${icon('endcall.svg')}" alt=""></button>
  </nav></main>`);
  const geometry = await page.locator('.doclinc-call-control').evaluateAll(buttons => buttons.map(button => {
    const box = button.getBoundingClientRect();
    const style = getComputedStyle(button);
    return { width: box.width, height: box.height, radius: style.borderRadius, border: style.borderWidth, text: button.innerText, separator: getComputedStyle(button, '::before').content };
  }));
  for (const item of geometry) {
    expect(Math.abs(item.width - item.height)).toBeLessThanOrEqual(1);
    expect(item.radius).toMatch(/999px|50%/);
    expect(item.text).toBe('');
    expect(item.border).toBe('0px');
    expect(item.separator).toBe('none');
  }
  expect(geometry[4].width).toBeGreaterThanOrEqual(geometry[0].width);
});

test('More menu is compact, Indonesian, and contains no participant action', async ({ page }) => {
  await page.setContent(`<style>${css}</style><main class="doclinc-call-panel"><nav class="doclinc-call-controls"><button class="doclinc-call-control" id="more">More</button></nav>
    <aside class="doclinc-call-popover" id="more-panel"><button class="doclinc-call-secondary">Obrolan</button><button class="doclinc-call-secondary">Ganti Kamera</button><button class="doclinc-call-secondary">Minimalkan</button><button class="doclinc-call-secondary">Tutup Tampilan</button></aside></main>`);
  const panel = page.locator('#more-panel');
  await expect(panel).toBeVisible();
  const box = await panel.boundingBox();
  expect(box && box.width).toBeLessThanOrEqual(240);
  expect(box && box.height).toBeLessThanOrEqual(240);
  await expect(panel).toContainText('Obrolan');
  await expect(panel).toContainText('Ganti Kamera');
  await expect(panel).toContainText('Minimalkan');
  await expect(panel).toContainText('Tutup Tampilan');
  await expect(panel).not.toContainText('Peserta');
});

test('Chat composer is a pill with lightweight icon actions', async ({ page }) => {
  await page.setContent(`<style>${css}</style><form class="chat-input-row"><button class="chat-icon-button" aria-label="Lampirkan gambar"><img src="${icon('lampiran.svg')}" alt=""></button><textarea class="chat-input" placeholder="Tulis pesan..."></textarea><button class="chat-icon-button" aria-label="Kamera"><img src="${icon('kamera.svg')}" alt=""></button><button class="chat-send" aria-label="Kirim pesan"><img src="${icon('send.svg')}" alt=""></button></form>`);
  const row = page.locator('.chat-input-row');
  const rowStyle = await row.evaluate(node => { const s = getComputedStyle(node); const b = node.getBoundingClientRect(); return { height: b.height, radius: s.borderRadius }; });
  expect(rowStyle.height).toBeGreaterThanOrEqual(44);
  expect(rowStyle.radius).toMatch(/22px|24px|25px|26px/);
  for (const button of await page.locator('.chat-icon-button, .chat-send').all()) {
    const box = await button.boundingBox();
    expect(box.width).toBeGreaterThanOrEqual(39.5);
    expect(box.height).toBeGreaterThanOrEqual(39.5);
    expect(Math.abs(box.width - box.height)).toBeLessThanOrEqual(1);
    expect(await button.innerText()).toBe('');
  }
});
