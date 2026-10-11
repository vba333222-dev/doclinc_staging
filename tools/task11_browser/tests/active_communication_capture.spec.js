const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const css = fs.readFileSync(path.join(repoRoot, 'assets/css/doclinc-livekit.css'), 'utf8');
const outputDir = process.env.ACTIVE_COMMUNICATION_REVIEW_DIR || 'C:/Temp/active-communication-review';
const chatCss = `
  .chat-shell{max-width:760px;margin:0 auto;min-height:100vh;display:flex;flex-direction:column;background:#f3f7f8;overflow:hidden;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#19352b}
  .chat-header{min-height:82px;padding:14px 22px;display:flex;align-items:center;gap:12px;background:#fff;border-bottom:1px solid rgba(84,117,109,.12)}
  .chat-back{width:36px;height:36px;border:0;background:transparent;font-size:20px;color:#46675c}.chat-avatar{width:42px;height:42px;border-radius:50%;object-fit:contain;flex:0 0 42px}.chat-title{min-width:0}.chat-title-text{margin:0;font-weight:750;font-size:16px}.chat-title-subtext{margin:2px 0 0;color:#6b8178;font-size:12px}
  .chat-list{flex:1;overflow:auto;padding:28px 42px 24px;background:#f4f8f9}.chat-bubble{max-width:min(560px,86%);margin:0 0 14px;padding:14px 17px;border-radius:18px 18px 18px 6px;background:#fff;box-shadow:0 3px 10px rgba(45,76,70,.08);font-size:15px;line-height:1.5}.chat-bubble.mine{margin-left:auto;border-radius:18px 18px 6px 18px;background:#dff4e8}
  .chat-form{padding:12px 28px 14px;background:rgba(255,255,255,.92);border-top:1px solid rgba(84,117,109,.12)}.chat-input-row{display:flex;align-items:center;gap:6px;min-height:52px;padding:3px 5px 3px 8px;border:1px solid rgba(115,145,137,.24);border-radius:26px;background:#fff;box-shadow:0 6px 18px rgba(28,64,58,.1)}.chat-input{flex:1;min-width:0;height:38px;border:0;padding:9px 4px;font:inherit;font-size:15px;resize:none}.chat-icon-button,.chat-send{width:40px;height:40px;min-width:40px;padding:8px;border:0;border-radius:50%;background:transparent;display:inline-flex;align-items:center;justify-content:center}.chat-icon-button img,.chat-send img{width:20px;height:20px}.chat-send{background:#2f9c73}.chat-send img{filter:brightness(0) invert(1)}
  @media(max-width:640px){.chat-header{padding-inline:12px}.chat-list{padding-inline:14px}.chat-form{padding-inline:14px}}
`;

function asset(name) {
  const file = path.join(repoRoot, 'assets', 'doclinc_ui', 'icon-pack', `${name}.svg`);
  return `data:image/svg+xml;base64,${fs.readFileSync(file).toString('base64')}`;
}

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function modeMarkup(mode) {
  const isAudio = mode === 'audio';
  return `<main class="doclinc-call-modal is-open ${isAudio ? 'is-audio' : 'is-video'}">
    <section class="doclinc-call-panel">
      <header class="doclinc-call-head"><div class="doclinc-call-brand-lockup"><img class="doclinc-call-brand" src="${asset('doklinclogo')}" alt="DokLinc"><span class="doclinc-call-divider"></span><div class="doclinc-call-context"><span class="doclinc-call-eyebrow">Konsultasi dengan</span><h1 class="doclinc-call-title">dr. Rani Dokter</h1><div class="doclinc-call-provider-meta"><span class="doclinc-call-provider-role">Dokter</span></div></div></div></header>
      <section class="doclinc-call-stage ${isAudio ? 'is-audio' : 'is-video'}"><div class="doclinc-call-remote">${isAudio ? '<div class="doclinc-call-empty"><div class="doclinc-call-empty-avatar" aria-hidden="true"></div><span class="doclinc-call-empty-name">dr. Rani Dokter</span><span class="doclinc-call-empty-role">Dokter</span><span class="doclinc-call-empty-waiting">Audio tersambung</span></div>' : '<div class="doclinc-call-empty"><div class="doclinc-call-empty-avatar" aria-hidden="true"></div><span class="doclinc-call-empty-name">dr. Rani Dokter</span><span class="doclinc-call-empty-role">Menunggu video</span></div>'}</div>${isAudio ? '' : '<div class="doclinc-call-local is-placeholder"></div>'}</section>
      <nav class="doclinc-call-controls"><button class="doclinc-call-control" aria-label="Buka opsi lainnya"><img src="${asset('more')}" alt=""></button><button class="doclinc-call-control is-active" aria-label="Matikan kamera"><img src="${asset('videocall')}" alt=""></button><button class="doclinc-call-control is-active" aria-label="Aktifkan suara"><img src="${asset('loudspeaker')}" alt=""></button><button class="doclinc-call-control is-active" aria-label="Bisukan mikrofon"><img src="${asset('mute')}" alt=""></button><button class="doclinc-call-control end-call" aria-label="Akhiri panggilan"><img src="${asset('endcall')}" alt=""></button></nav>
    </section>
  </main>`;
}

test('capture active video and voice reference states', async ({ page }) => {
  ensureDir(path.join(outputDir, 'video'));
  ensureDir(path.join(outputDir, 'voice'));
  await page.setContent(`<style>html,body{margin:0;min-height:100%;background:#eef4f1}*{box-sizing:border-box}${css}</style>${modeMarkup('video')}`);
  await page.setViewportSize({ width: 1440, height: 900 });
  await expect(page.locator('.doclinc-call-stage.is-video')).toBeVisible();
  await page.screenshot({ path: path.join(outputDir, 'video', 'video-main-1440x900.png'), fullPage: false });
  await page.setContent(`<style>html,body{margin:0;min-height:100%;background:#eef4f1}*{box-sizing:border-box}${css}</style>${modeMarkup('audio')}`);
  await expect(page.locator('.doclinc-call-stage.is-audio')).toBeVisible();
  await page.screenshot({ path: path.join(outputDir, 'voice', 'voice-main-1440x900.png'), fullPage: false });
});

test('capture active chat reference states at mobile and desktop', async ({ page }) => {
  ensureDir(path.join(outputDir, 'chat'));
  const markup = `<style>html,body{margin:0;min-height:100%;background:#e7eef0}*{box-sizing:border-box}${css}${chatCss}</style><main class="chat-shell is-active"><header class="chat-header"><button class="chat-back" aria-label="Kembali">‹</button><img class="chat-avatar" src="${asset('doklinclogo')}" alt="Dokter"><div class="chat-title"><p class="chat-title-text">dr. Rani Dokter</p><p class="chat-title-subtext">Chat aktif · Konsultasi</p></div></header><section class="chat-list"><div class="chat-bubble">Halo, ada yang bisa saya bantu hari ini?</div><div class="chat-bubble mine">Halo dok, saya ingin melanjutkan konsultasi.</div></section><form class="chat-form"><div class="chat-input-row"><button class="chat-icon-button" aria-label="Lampirkan gambar"><img src="${asset('lampiran')}" alt=""></button><textarea class="chat-input" placeholder="Tulis pesan..."></textarea><button class="chat-icon-button" aria-label="Kirim gambar"><img src="${asset('kamera')}" alt=""></button><button class="chat-send" aria-label="Kirim pesan"><img src="${asset('send')}" alt=""></button></div></form></main>`;
  await page.setContent(markup);
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.locator('.chat-input-row')).toBeVisible();
  await page.screenshot({ path: path.join(outputDir, 'chat', 'chat-active-390x844.png'), fullPage: false });
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.screenshot({ path: path.join(outputDir, 'chat', 'chat-active-1440x900.png'), fullPage: false });
});
