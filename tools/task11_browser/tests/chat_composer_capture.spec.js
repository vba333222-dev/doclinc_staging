const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const css = fs.readFileSync(path.join(repoRoot, 'assets', 'css', 'doclinc-livekit.css'), 'utf8');
const outputDir = path.resolve(process.env.VISUAL_REVIEW_OUTPUT_DIR || path.join(process.cwd(), 'test-results', 'visual-review', 'modern-controls', 'chat'));
const entries = [];

function icon(name) {
  return `data:image/svg+xml;base64,${fs.readFileSync(path.join(repoRoot, 'assets', 'doclinc_ui', 'icon-pack', name), 'base64')}`;
}

function markup(state) {
  const hasText = state === 'text-entered';
  const attachmentOpen = state === 'attachment-open';
  return `<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>html,body{margin:0;min-height:100%;background:#eef4f1;font-family:Inter,Segoe UI,Arial,sans-serif}*{box-sizing:border-box}${css}.chat-room{min-height:100vh;display:flex;flex-direction:column;max-width:900px;margin:auto;background:#f8fbf9}.chat-header{padding:18px 20px;background:#fff;border-bottom:1px solid #e4ece7}.chat-messages{flex:1;padding:24px}.chat-composer-wrap{padding:12px 16px 16px;background:#fff;position:relative}.attachment-menu{position:absolute;bottom:68px;left:16px;width:180px;padding:8px;background:#fff;border:1px solid #dce9e0;border-radius:16px;box-shadow:0 12px 30px rgba(28,64,35,.16)}.attachment-menu button{display:flex;align-items:center;gap:8px;width:100%;padding:9px;border:0;background:transparent;border-radius:10px;color:#244b37}.attachment-menu img{width:20px;height:20px}</style></head><body><main class="chat-room"><header class="chat-header"><strong>Konsultasi dengan dr. Rani Dokter</strong><div>Obrolan</div></header><section class="chat-messages"><p>Pesan konsultasi sintetis untuk tinjauan tampilan.</p></section><section class="chat-composer-wrap">${attachmentOpen ? '<aside class="attachment-menu" role="menu"><button role="menuitem"><img src="'+icon('lampiran.svg')+'" alt="">Dokumen</button><button role="menuitem"><img src="'+icon('kamera.svg')+'" alt="">Kamera</button></aside>' : ''}<form class="chat-input-row"><button class="chat-icon-button chat-attach" type="button" aria-label="Lampirkan gambar"><img src="${icon('lampiran.svg')}" alt=""></button><textarea class="chat-input" rows="1" placeholder="Tulis pesan...">${hasText ? 'Halo dokter, saya ingin bertanya.' : ''}</textarea><button class="chat-icon-button" type="button" aria-label="Kamera"><img src="${icon('kamera.svg')}" alt=""></button><button class="chat-send" type="button" aria-label="Kirim pesan"><img src="${icon('send.svg')}" alt=""></button></form></section></main></body></html>`;
}

async function capture(page, state, viewport, file) {
  await page.setViewportSize(viewport);
  await page.setContent(markup(state), { waitUntil: 'load' });
  const composer = page.locator('.chat-input-row');
  await expect(composer).toBeVisible();
  const box = await composer.boundingBox();
  expect(box.width).toBeGreaterThan(0);
  expect(box.height).toBeGreaterThanOrEqual(44);
  const target = path.join(outputDir, file);
  fs.mkdirSync(path.dirname(target), { recursive: true });
  await page.screenshot({ path: target, animations: 'disabled', caret: 'hide' });
  entries.push({ file: file.replace(/\\/g, '/'), actor: 'SYNTHETIC_CHAT_REVIEW', route: '/chat', viewport: `${viewport.width}x${viewport.height}`, state, visual_controls: ['lampiran.svg', 'kamera.svg', 'send.svg'], visual_purpose: 'Manual review of WhatsApp-inspired DokLinc composer geometry', result: 'PASS', sha256: crypto.createHash('sha256').update(fs.readFileSync(target)).digest('hex') });
}

test.describe.configure({ mode: 'serial' });
test('captures modern chat composer states', async ({ page }) => {
  await capture(page, 'empty', { width: 390, height: 844 }, 'mobile/chat-composer-empty-390x844.png');
  await capture(page, 'text-entered', { width: 390, height: 844 }, 'mobile/chat-composer-text-390x844.png');
  await capture(page, 'attachment-open', { width: 390, height: 844 }, 'mobile/chat-attachment-open-390x844.png');
  await capture(page, 'empty', { width: 1440, height: 900 }, 'desktop/chat-room-composer-1440x900.png');
});

test.afterAll(() => {
  if (!entries.length) return;
  fs.mkdirSync(outputDir, { recursive: true });
  fs.writeFileSync(path.join(outputDir, 'manifest.json'), JSON.stringify({ synthetic_data_only: true, screenshots: entries }, null, 2));
});
