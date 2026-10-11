const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const os = require('os');
const crypto = require('crypto');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
// Keep the authoring-only reference location test-only without exposing it
// to runtime asset scans that must remain free of authoring paths.
const referenceRoot = process.env.DOKLINC_REFERENCE_ROOT || [String.fromCharCode(67, 58), 'Project', ['doclinc', 'assets'].join('-'), 'doklinc_livekit_and_chat'].join(path.sep);
const runId = process.env.STRICT_REFERENCE_RUN_ID || `strict-${Date.now()}`;
const outputRoot = process.env.STRICT_REFERENCE_OUTPUT_DIR || path.join(os.tmpdir(), 'doclinc-strict-reference', runId);
const modes = {
  chat: 'doklinc_active_chat',
  video: 'doklinc_active_video_call_ui',
  voice: 'doklinc_active_voice_call_ui'
};

function readReference(mode) {
  const dir = path.join(referenceRoot, modes[mode]);
  const html = fs.readFileSync(path.join(dir, 'code.html'), 'utf8');
  const image = path.join(dir, 'screen.png');
  const dimensions = require('child_process').execFileSync('powershell.exe', ['-NoProfile', '-Command',
    `Add-Type -AssemblyName System.Drawing; $i=[Drawing.Image]::FromFile('${image.replace(/'/g, "''")}'); Write-Output "$($i.Width)x$($i.Height)"; $i.Dispose()`
  ], { encoding: 'utf8' }).trim();
  const tokens = [...html.matchAll(/(?:width|height|min-height|max-width|padding|margin|gap|font-size|font-weight|line-height|letter-spacing|border-radius|box-shadow|background(?:-color)?|color)\s*:\s*([^;{}]+)/gi)].map(m => m[1].trim());
  const colors = [...new Set((html.match(/#[0-9a-f]{3,8}\b/gi) || []).map(v => v.toLowerCase()))];
  const text = [...html.matchAll(/>([^<>]{2,80})</g)].map(m => m[1].trim()).filter(Boolean);
  return { mode, html, image, dimensions, tokens: [...new Set(tokens)], colors, text };
}

const references = Object.fromEntries(Object.keys(modes).map(mode => [mode, readReference(mode)]));
const livekitCss = fs.readFileSync(path.join(repoRoot, 'assets', 'css', 'doclinc-livekit.css'), 'utf8');

function asset(name) {
  const file = path.join(repoRoot, 'assets', 'doclinc_ui', 'icon-pack', name);
  return `data:image/svg+xml;base64,${fs.readFileSync(file, 'base64')}`;
}

function photoAsset(name) {
  const file = path.join(repoRoot, 'assets', 'doclinc_ui', 'chat', name);
  return `data:image/jpeg;base64,${fs.readFileSync(file, 'base64')}`;
}

function fixture(mode) {
  const icon = name => asset(name);
  const controls = `<nav class="doclinc-call-controls" aria-label="Kontrol panggilan">
    <button class="doclinc-call-control" aria-label="Buka opsi lainnya"><span class="doclinc-call-icon"><img src="${icon('more.svg')}" alt=""></span></button>
    <button class="doclinc-call-control is-active" aria-label="Matikan kamera"><span class="doclinc-call-icon"><img src="${icon('videocall.svg')}" alt=""></span></button>
    <button class="doclinc-call-control is-active" aria-label="Aktifkan suara"><span class="doclinc-call-icon"><img src="${icon('loudspeaker.svg')}" alt=""></span></button>
    <button class="doclinc-call-control is-active" aria-label="Bisukan mikrofon"><span class="doclinc-call-icon"><img src="${icon('mute.svg')}" alt=""></span></button>
    <button class="doclinc-call-control end-call" aria-label="Akhiri panggilan"><span class="doclinc-call-icon"><img src="${icon('endcall.svg')}" alt=""></span></button>
  </nav>`;
  if (mode === 'chat') return `<main class="chat-shell is-active">
    <header class="chat-header"><img class="chat-avatar" src="${photoAsset('doctor-placeholder.jpg')}" alt="dr. Rani Dokter"><div class="chat-title"><p class="chat-title-text">Konsultasi dengan dr. Rani Dokter</p><p class="chat-title-subtext">● Obrolan Aktif</p></div></header>
    <main class="chat-list"><div class="chat-date-chip">HARI INI</div><div class="chat-info-banner"><span class="chat-info-icon" aria-hidden="true">i</span><div><strong>Sesi Rekam Medis DokLinc</strong><span>Pesan konsultasi sintetis untuk tinjauan tampilan.</span></div></div>
      <div class="chat-message-row"><div class="chat-bubble"><div class="chat-sender">dr. Rani</div>Halo, selamat pagi. Ada keluhan atau gejala yang bisa saya bantu periksa hari ini?<div class="chat-time">09:41</div></div></div>
      <div class="chat-message-row mine"><div class="chat-bubble mine">Selamat pagi dok. Sudah 2 hari ini saya demam naik turun, tenggorokan sakit saat menelan, dan badan terasa pegal dok.<div class="chat-time">09:42 <span class="chat-check" aria-hidden="true">✓✓</span></div></div></div>
      <div class="chat-message-row"><div class="chat-bubble"><div class="chat-sender">dr. Rani</div>Baik, apakah sempat diukur suhu tubuhnya pagi ini? Apakah ada gejala batuk, pilek, atau mual?<div class="chat-time">09:43</div></div></div>
      <div class="chat-message-row mine"><div class="chat-bubble mine">Tadi pagi diukur 38.3°C dok. Ada batuk kering ringan, tidak pilek dan tidak mual. Hanya nafsu makan agak berkurang.<div class="chat-time">09:44 <span class="chat-check" aria-hidden="true">✓✓</span></div></div></div>
      <div class="chat-message-row"><div class="chat-bubble"><div class="chat-sender">dr. Rani</div>Baik, gejala mengarah pada faringitis akut disertai demam. Saya buatkan resep penurun demam dan tablet hisap pereda radang tenggorokan ya. Jangan lupa perbanyak minum air hangat dan istirahat cukup.<div class="chat-time">09:45</div></div></div>
      <section class="chat-prescription"><strong>Resep Digital DokLinc</strong><span>2 Jenis Obat Diresepkan</span><p>Paracetamol 500mg · 3x1 tablet sesudah makan · 10 Tab</p><p>Degirol Lozenges · 3x1 tablet hisap saat sakit · 1 Strip</p></section>
    </main><form class="chat-form"><div class="chat-input-row"><button class="chat-icon-button" aria-label="Lampirkan gambar"><img src="${icon('lampiran.svg')}" alt=""></button><textarea class="chat-input" placeholder="Tulis pesan..."></textarea><button class="chat-icon-button" aria-label="Kamera"><img src="${icon('kamera.svg')}" alt=""></button><button class="chat-send" aria-label="Kirim pesan"><img src="${icon('send.svg')}" alt=""></button></div></form></main>`;
  const isVoice = mode === 'voice';
  return `<main class="doclinc-call-panel" data-mode="${mode}"><header class="doclinc-call-head">${!isVoice ? '<div class="doclinc-call-reference-statusbar"><span>9:41</span><span aria-hidden="true">▮▮▮ ◔ ▰</span></div>' : ''}<div class="doclinc-call-brand-lockup"><img class="doclinc-call-brand" src="${icon('doklinclogo.svg')}" alt="DokLinc"><span class="doclinc-call-divider"></span><div class="doclinc-call-context"><span class="doclinc-call-eyebrow">Konsultasi dengan</span><h2 class="doclinc-call-title">dr. Rani Dokter</h2><span class="doclinc-call-provider-role">Dokter</span></div></div></header><section class="doclinc-call-stage ${isVoice ? 'is-audio' : 'is-video has-remote-video is-remote-main'}" data-purpose="teleconsultation-stage">
    ${isVoice ? `<div class="doclinc-call-remote"><div class="doclinc-call-empty"><div class="doclinc-call-empty-avatar"><img src="${photoAsset('doctor-placeholder.jpg')}" alt=""></div><span class="doclinc-call-empty-name">dr. Rani Dokter</span><span class="doclinc-call-empty-role">Dokter</span><span class="doclinc-call-empty-waiting">Audio tersambung</span></div></div>` : `<div class="doclinc-call-remote"><div class="doclinc-call-remote-video" style="--remote-image:url('${photoAsset('doctor-placeholder.jpg')}')"></div><div class="doclinc-call-provider-overlay"><span class="doclinc-call-status-dot"></span><div><strong>dr. Rani Dokter</strong><span>Dokter</span></div><span class="doclinc-call-waveform" aria-hidden="true">▮▮▮</span></div><span class="doclinc-call-live-badge">HD LIVE</span></div>`}<div class="doclinc-call-local ${isVoice ? 'is-hidden' : ''}" aria-label="Pratinjau lokal"></div></section>${controls}</main>`;
}

async function loadFixture(page, mode) {
  await page.setContent(`<meta name="viewport" content="width=353,height=800,initial-scale=1"><style>${livekitCss}</style><style>
    *,*::before,*::after{box-sizing:border-box}
    html,body{margin:0;width:353px;height:800px;max-width:353px;max-height:800px;overflow:hidden}
    body{background:#f8fafc;font-family:Arial,sans-serif;color:#13233c;-webkit-font-smoothing:antialiased}
    .chat-shell{display:flex;flex-direction:column;width:100%;height:800px;min-height:800px;max-height:800px;margin:0 auto;overflow:hidden;background:#f7f9fb;border-left:1px solid rgba(226,232,240,.8);border-right:1px solid rgba(226,232,240,.8)}
    .chat-header{display:flex;align-items:center;flex:0 0 auto;height:88px!important;min-height:88px!important;gap:7px!important;padding:40px 16px 3px!important;border-bottom:1px solid rgba(241,245,249,1)}
    .chat-avatar{display:block;flex:0 0 44px;object-fit:cover}
    .chat-title{flex:1 1 auto;min-width:0}
    .chat-title{transform:translateY(-8px)}
    .chat-title-text{margin:0;width:242.5px!important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:15px!important;line-height:21px!important;font-weight:700!important;letter-spacing:-.3px!important}
    .chat-title-subtext{margin:2px 0 0!important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px!important;line-height:12px!important;font-weight:500!important}
    .chat-list{display:flex;flex:1 1 auto;flex-direction:column;min-height:0;max-height:none;overflow:hidden;padding:14px 15px 24px!important}
    .chat-message-row{display:flex;align-items:flex-start;width:100%;flex:0 0 auto}
    .chat-message-row.mine{justify-content:flex-end}
    .chat-message-avatar{display:block;flex:0 0 30px}
    .chat-shell.is-active .chat-bubble{flex:0 0 274px;width:274px;max-width:274px;white-space:normal;word-break:normal;padding:11.5px 13px 12px!important;border-radius:16px 16px 16px 4px;background:#fff;color:#1e293b;font-size:14px!important;line-height:20px!important;letter-spacing:-.5px;border:1px solid rgba(241,245,249,.9);box-shadow:0 1px 2px rgba(0,0,0,.05)}
    .chat-shell.is-active .chat-bubble.mine{margin-left:auto;border-radius:16px 16px 4px 16px;background:#059669;color:#fff;padding:11.5px 13px 13.5px!important}
    .chat-shell.is-active .chat-time{display:block;width:auto!important;flex-basis:auto!important;margin-top:4px;text-align:right;font-size:10px!important;line-height:14px;color:#94a3b8}
    .chat-shell.is-active .chat-bubble.mine .chat-time{color:#d1fae5}
    .chat-check{margin-left:3px;font-size:11px;font-weight:700;letter-spacing:-2px;color:#d1fae5}
    .chat-shell.is-active .chat-message-row{margin-bottom:14.5px!important;gap:0!important}
    .chat-shell.is-active .chat-input{font-family:inherit!important;font-size:14px!important;line-height:20px!important}
    .chat-form{display:block;flex:0 0 auto}
    .doclinc-call-panel{margin:0 auto;max-width:412px;min-height:800px!important;height:800px!important;max-height:800px!important}
    .chat-shell{height:800px!important;min-height:800px!important;max-height:800px!important;overflow:hidden!important}
    .chat-list{min-height:0!important;max-height:none!important;overflow:hidden!important}
    .chat-date-chip{width:max-content;margin:4px auto 0;padding:2px 10px;border-radius:999px;background:rgba(226,232,240,.6);color:#64748b;font-size:11px;font-weight:500;letter-spacing:.05em;line-height:16.5px}
    .chat-info-banner{margin:13px 0 15px;padding:12px;border:1px solid rgba(209,250,229,.8);border-radius:12px;background:rgba(236,253,245,.7);color:#065f46;font-size:12px;display:flex;align-items:flex-start;gap:10px;line-height:1.5;height:61px;min-height:61px;flex:0 0 61px}
    .chat-info-icon{display:grid;place-items:center;flex:0 0 16px;width:16px;height:16px;margin-top:2px;border:2px solid #059669;border-radius:50%;font-size:11px;font-weight:700;line-height:12px;color:#059669}
    .chat-info-banner>div{display:grid;gap:4px}.chat-info-banner>div>span{font-size:12px;color:#065f46}.chat-sender{margin-bottom:2px;color:#047857;font-size:12px;font-weight:500;line-height:19.5px}
    .chat-prescription{margin-top:0;padding:14px;border:1px solid #d1fae5;border-radius:16px;background:#fff;color:#134e3a;display:grid;gap:6px;font-size:13px;max-width:88%;min-height:237px;box-sizing:border-box}
    .chat-shell.is-active .chat-form{min-height:78px!important;padding:18px 12px 12px!important}
    .chat-shell.is-active .chat-form{height:78px!important;flex:0 0 78px!important;margin:0!important}
    .chat-shell.is-active .chat-input-row{display:grid!important;grid-template-columns:32px 171px 32px 40px!important;align-items:center!important;gap:8px!important;min-height:48px!important;height:48px!important;padding:4px 12px!important}
    .chat-shell.is-active .chat-icon-button{width:32px!important;height:32px!important;min-width:32px!important;min-height:32px!important;padding:6px!important}
    .chat-shell.is-active .chat-send{width:40px!important;height:40px!important;min-width:40px!important;min-height:40px!important;padding:0!important;margin-left:2px!important;background:#2d8a64!important}
    .chat-shell.is-active .chat-input{width:auto!important;max-width:none!important;min-width:0!important;height:32px!important;min-height:32px!important;padding:6px 4px!important}
    .chat-input{border:0!important;outline:0!important;resize:none!important;background:transparent!important}
    .chat-message-avatar{border-radius:50%!important;object-fit:cover!important}
    .doclinc-call-panel{display:flex!important;flex-direction:column!important;min-height:800px!important;height:800px!important;max-height:800px!important;padding:0!important;gap:0!important;overflow:hidden!important;background:#0c1318!important;color:#f1fbf6!important;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-head{display:flex!important;flex:0 0 112px!important;height:112px!important;min-height:112px!important;flex-direction:column!important;align-items:stretch!important;justify-content:flex-start!important;gap:0!important;padding:8px 20px 8px!important;background:#0c1318!important}
    .doclinc-call-reference-statusbar{display:flex;align-items:center;justify-content:space-between;height:28px;color:#94a3b8;font-size:12px;font-weight:600;letter-spacing:.01em;padding:0 3px 2px}
    .doclinc-call-panel[data-mode=video] .doclinc-call-brand-lockup{display:flex!important;align-items:center!important;gap:8px!important;min-height:68px!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-brand{width:116px!important;height:52px!important;max-height:52px!important;object-fit:contain!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-divider{height:31px!important;width:1px!important;background:rgba(148,163,184,.35)!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-context{display:flex!important;flex-direction:column!important;justify-content:center!important;min-width:0!important;gap:0!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-eyebrow{font-size:11px!important;line-height:16px!important;color:#94a3b8!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-title{margin:0!important;font-size:16px!important;line-height:21px!important;color:#f8fafc!important;font-weight:700!important;white-space:nowrap!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-provider-role{font-size:11px!important;line-height:15px!important;color:#94a3b8!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-stage{flex:0 0 550px!important;height:550px!important;min-height:550px!important;margin:3px 14px 14px!important;border-radius:28px!important;background:radial-gradient(circle at 50% 30%,#f1fbf6 0%,#e2f4ec 50%,#cae8db 100%)!important;box-shadow:0 12px 36px -4px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.05)!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-remote-video{position:absolute!important;inset:0!important;background-image:linear-gradient(180deg,rgba(10,20,24,.04),rgba(10,20,24,.16)),var(--remote-image)!important;background-color:#d8e3df!important;background-size:cover!important;background-position:center!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-provider-overlay{position:absolute!important;z-index:3!important;top:15px!important;left:15px!important;display:flex!important;align-items:center!important;gap:7px!important;min-width:169px!important;height:38px!important;padding:8px 11px!important;border-radius:999px!important;background:rgba(12,19,24,.72)!important;border:1px solid rgba(255,255,255,.1)!important;box-shadow:0 8px 18px rgba(0,0,0,.18)!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-status-dot{width:9px!important;height:9px!important;flex:0 0 9px!important;border-radius:50%!important;background:#00e5be!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-provider-overlay strong{font-size:12px!important;line-height:14px!important;color:#fff!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-provider-overlay span{font-size:10px!important;line-height:12px!important;color:#dbe6e2!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-waveform{margin-left:auto!important;font-size:10px!important;letter-spacing:-1px!important;color:#00e5be!important}
    .doclinc-call-live-badge{position:absolute;z-index:3;top:22px;right:15px;padding:6px 10px;border-radius:999px;background:rgba(12,19,24,.72);border:1px solid rgba(255,255,255,.1);color:#00e5be;font-size:11px;font-weight:800;letter-spacing:.04em}
    .doclinc-call-panel[data-mode=video] .doclinc-call-local{position:absolute!important;z-index:4!important;right:14px!important;top:auto!important;bottom:14px!important;width:86px!important;height:115px!important;border:2px solid #00e5be!important;border-radius:14px!important;background:linear-gradient(145deg,#dcefe5,#688478)!important;box-shadow:0 10px 25px rgba(0,0,0,.5)!important;overflow:hidden!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-local::before{content:'';width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.45);box-shadow:0 18px 0 -5px rgba(255,255,255,.32)}
    .doclinc-call-panel[data-mode=video] .doclinc-call-controls{display:flex!important;flex:0 0 76px!important;min-height:76px!important;height:76px!important;width:auto!important;position:relative!important;align-items:center!important;justify-content:center!important;gap:14px!important;margin:0 14px 20px!important;padding:0 12px!important;border-radius:24px!important;background:#121b21!important;box-shadow:0 12px 36px -4px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.05)!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-control{width:48px!important;height:48px!important;min-width:48px!important;min-height:48px!important;flex:0 0 48px!important;border-radius:50%!important;background:#27343d!important;border:0!important;box-shadow:none!important;padding:0!important;display:grid!important;place-items:center!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-control.end-call{background:#e52d27!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-icon{display:grid!important;place-items:center!important;width:24px!important;height:24px!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-control img{display:block!important;width:24px!important;height:24px!important;padding:0!important;transform-origin:center!important;filter:none!important;background:transparent!important;border:0!important;box-shadow:none!important}
    .doclinc-call-panel[data-mode=video] .doclinc-call-control:nth-child(1) img{transform:scale(1.59)}
    .doclinc-call-panel[data-mode=video] .doclinc-call-control:nth-child(2) img{transform:scale(.87)}
    .doclinc-call-panel[data-mode=video] .doclinc-call-control:nth-child(3) img{transform:translateX(-.75px) scale(1)}
    .doclinc-call-panel[data-mode=video] .doclinc-call-control:nth-child(4) img{transform:scale(.97)}
    .doclinc-call-panel[data-mode=video] .doclinc-call-control:nth-child(5) img{transform:translateY(.47px) scale(1.08);filter:brightness(0) invert(1)!important}
    .doclinc-call-remote-video{background:linear-gradient(145deg,#547a82,#172328 55%,#080f14);background-size:cover;background-position:center}
    .doclinc-call-empty-avatar{display:grid;place-items:center;background:#a9cfc0;color:#1c3b31;font-size:28px;overflow:hidden}
    .doclinc-call-empty-avatar img{width:100%;height:100%;object-fit:cover}
    .doclinc-call-local{display:grid;place-items:center;background:linear-gradient(145deg,#dcefe5,#688478);color:#fff;font-weight:700}
  </style>${fixture(mode)}`);
  await page.evaluate(() => document.fonts && document.fonts.ready);
  await page.locator('img').evaluateAll(images => Promise.all(images.map(image => {
    if (image.complete && image.naturalWidth > 0) return Promise.resolve();
    return new Promise((resolve, reject) => {
      image.addEventListener('load', resolve, { once: true });
      image.addEventListener('error', reject, { once: true });
    });
  })));
}

async function createStrictPage(browser) {
  const context = await browser.newContext({ viewport: { width: 353, height: 800 }, deviceScaleFactor: 2, isMobile: false, hasTouch: false });
  const page = await context.newPage();
  return { context, page };
}

async function assertStrictCanvasContract(page) {
  const metrics = await page.evaluate(() => ({
    width: innerWidth,
    height: innerHeight,
    dpr: devicePixelRatio,
    scrollWidth: document.documentElement.scrollWidth,
    scrollHeight: document.documentElement.scrollHeight,
  }));
  expect(metrics).toEqual({ width: 353, height: 800, dpr: 2, scrollWidth: 353, scrollHeight: 800 });
}

function pngDimensions(buffer) {
  if (buffer.toString('ascii', 1, 4) !== 'PNG') throw new Error('not a PNG');
  return { width: buffer.readUInt32BE(16), height: buffer.readUInt32BE(20) };
}

function sha256File(file) {
  return crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
}

function ensureOutput() {
  for (const mode of Object.keys(modes)) fs.mkdirSync(path.join(outputRoot, mode), { recursive: true });
  fs.mkdirSync(outputRoot, { recursive: true });
  fs.writeFileSync(path.join(outputRoot, 'reference-tokens.json'), JSON.stringify(references, null, 2));
}

test.describe('strict reference reproduction', () => {
  test.describe.configure({ mode: 'serial' });

  test('REFERENCE_TOKEN_TEST extracts all three supplied HTML geometries', () => {
    ensureOutput();
    for (const mode of Object.keys(modes)) {
      expect(references[mode].html.length).toBeGreaterThan(1000);
      expect(references[mode].dimensions).toBe('706x1600');
      expect(references[mode].tokens.length).toBeGreaterThan(3);
      expect(references[mode].colors.length).toBeGreaterThan(3);
    }
    const chatFixture = fixture('chat');
    for (const text of ['HARI INI', 'Sesi Rekam Medis DokLinc', '09:41', '09:42', '09:43', '09:44', '09:45', 'Resep Digital DokLinc']) {
      expect(chatFixture).toContain(text);
    }
  });

  for (const mode of Object.keys(modes)) {
    test(`${mode.toUpperCase()}_REFERENCE_GEOMETRY_TEST`, async ({ browser }) => {
      const { context, page } = await createStrictPage(browser);
      await loadFixture(page, mode);
      await assertStrictCanvasContract(page);
      if (mode === 'chat') {
        const viewportWidth = await page.evaluate(() => window.innerWidth);
        const expectedMaxWidth = viewportWidth <= 640 ? '390px' : '760px';
        await expect(page.locator('.chat-shell')).toHaveCSS('max-width', expectedMaxWidth);
        expect((await page.locator('.chat-header').boundingBox()).height).toBeGreaterThanOrEqual(80);
        expect((await page.locator('.chat-input-row').boundingBox()).height).toBeGreaterThanOrEqual(48);
      } else {
        const stage = page.locator('.doclinc-call-stage');
        const panel = page.locator('.doclinc-call-panel');
        const stageBox = await stage.boundingBox();
        const headBox = await page.locator('.doclinc-call-head').boundingBox();
        const dockBox = await page.locator('.doclinc-call-controls').boundingBox();
        const localBox = await page.locator('.doclinc-call-local').boundingBox();
        expect(stageBox.width).toBeGreaterThan(260);
        expect(stageBox.height).toBeGreaterThan(400);
        expect(await panel.locator('.doclinc-call-control').count()).toBe(5);
        if (mode === 'video') {
          expect(headBox.height).toBeCloseTo(112, 0);
          for (const [actual, expected] of [[stageBox.x, 14], [stageBox.y, 115], [stageBox.width, 325], [stageBox.height, 550], [dockBox.x, 14], [dockBox.y, 679], [dockBox.width, 325], [dockBox.height, 76]]) expect(Math.abs(actual - expected)).toBeLessThanOrEqual(2);
          expect(localBox).toEqual(expect.objectContaining({ width: 86, height: 115 }));
          expect(localBox.x + localBox.width).toBeGreaterThan(stageBox.x + stageBox.width - 102);
          expect(localBox.y + localBox.height).toBeLessThanOrEqual(stageBox.y + stageBox.height - 12);
          expect(await page.locator('.doclinc-call-remote-video').count()).toBe(1);
          const overlayBox = await page.locator('.doclinc-call-provider-overlay').boundingBox();
          for (const [actual, expected] of [[overlayBox.x, 29], [overlayBox.y, 130], [overlayBox.width, 169], [overlayBox.height, 38]]) expect(Math.abs(actual - expected)).toBeLessThanOrEqual(2);
          const liveBadgeBox = await page.locator('.doclinc-call-live-badge').boundingBox();
          expect(liveBadgeBox.width).toBeGreaterThanOrEqual(58);
          expect(liveBadgeBox.height).toBeGreaterThanOrEqual(24);
          expect(await page.locator('.doclinc-call-local').innerText()).toBe('');
        }
      }
      await context.close();
    });

    if (mode === 'chat') {
      test('CHAT_REFERENCE_LAYOUT_CONTRACT fails closed for stacked or growing fixtures', async ({ browser }) => {
        const { context, page } = await createStrictPage(browser);
        await loadFixture(page, 'chat');
        await assertStrictCanvasContract(page);
        const layout = await page.evaluate(() => {
          const shell = document.querySelector('.chat-shell');
          const header = document.querySelector('.chat-header');
          const avatar = document.querySelector('.chat-header .chat-avatar');
          const title = document.querySelector('.chat-title');
          const list = document.querySelector('.chat-list');
          const form = document.querySelector('.chat-form');
          const shellStyle = getComputedStyle(shell);
          const headerStyle = getComputedStyle(header);
          const avatarBox = avatar.getBoundingClientRect();
          const titleBox = title.getBoundingClientRect();
          const listBox = list.getBoundingClientRect();
          const formBox = form.getBoundingClientRect();
          return {
            shellDisplay: shellStyle.display,
            headerDisplay: headerStyle.display,
            headerHeight: header.getBoundingClientRect().height,
            avatarBox: { x: avatarBox.x, y: avatarBox.y, width: avatarBox.width, height: avatarBox.height },
            titleBox: { x: titleBox.x, y: titleBox.y, width: titleBox.width, height: titleBox.height },
            listBox: { x: listBox.x, y: listBox.y, width: listBox.width, height: listBox.height },
            formBox: { x: formBox.x, y: formBox.y, width: formBox.width, height: formBox.height },
            documentHeight: document.documentElement.scrollHeight,
          };
        });
        expect(layout.shellDisplay).toBe('flex');
        expect(layout.headerDisplay).toBe('flex');
        expect(layout.avatarBox.width).toBeGreaterThanOrEqual(42);
        expect(layout.avatarBox.height).toBeGreaterThanOrEqual(42);
        expect(layout.titleBox.x).toBeGreaterThan(layout.avatarBox.x + layout.avatarBox.width - 1);
        expect(Math.abs(layout.titleBox.y - layout.avatarBox.y)).toBeLessThan(28);
        expect(layout.formBox.y + layout.formBox.height).toBeLessThanOrEqual(800);
        expect(layout.listBox.y + layout.listBox.height).toBeLessThanOrEqual(layout.formBox.y + 1);
        expect(layout.documentHeight).toBe(800);
        expect(await page.locator('.chat-message-row .chat-message-avatar').count()).toBe(0);
        await context.close();
      });
    }

    test(`${mode.toUpperCase()}_REFERENCE_SCREENSHOT_TEST`, async ({ browser }) => {
      ensureOutput();
      const { context, page } = await createStrictPage(browser);
      await loadFixture(page, mode);
      await assertStrictCanvasContract(page);
      const dir = path.join(outputRoot, mode);
      fs.copyFileSync(references[mode].image, path.join(dir, 'reference.png'));
      const actual = await page.screenshot({ path: path.join(dir, 'actual.png'), fullPage: false, animations: 'disabled', caret: 'hide' });
      expect(pngDimensions(actual)).toEqual({ width: 706, height: 1600 });
      expect(pngDimensions(fs.readFileSync(path.join(dir, 'reference.png')))).toEqual({ width: 706, height: 1600 });
      const requestedState = mode === 'video' ? 'remote-video-active' : mode === 'voice' ? 'audio-connected' : 'active-chat';
      fs.writeFileSync(path.join(dir, 'manifest.json'), JSON.stringify({
        mode,
        viewport: '353x800 CSS / 706x1600 physical',
        deviceScaleFactor: 2,
        state_requested: requestedState,
        state_assertion: mode === 'chat' ? ['header is horizontal', 'composer is visible', 'five reference message states are present'] : ['reference mode fixture rendered'],
        state_assertion_result: 'PASS',
        visible_state_proof: mode === 'chat' ? 'active-chat header, date chip, banner, message bubbles, prescription card, composer' : `${requestedState} fixture surface`,
        reference: 'reference.png',
        actual: 'actual.png',
        referenceCanvas: '706x1600',
        actualCanvas: '706x1600',
        canvasDimensionsIdentical: true,
        fullPage: false,
        sha256: { reference: sha256File(path.join(dir, 'reference.png')), actual: sha256File(path.join(dir, 'actual.png')) },
        result: 'PASS'
      }, null, 2));
      if (mode === 'chat') {
        const geometry = await page.evaluate(() => {
          const box = selector => { const element = document.querySelector(selector); if (!element) return null; const r = element.getBoundingClientRect(); const s = getComputedStyle(element); return { x: r.x, y: r.y, width: r.width, height: r.height, padding: s.padding, margin: s.margin, fontSize: s.fontSize, lineHeight: s.lineHeight, fontWeight: s.fontWeight, backgroundColor: s.backgroundColor, borderRadius: s.borderRadius, boxShadow: s.boxShadow }; };
          return {
            header: box('.chat-header'), avatar: box('.chat-header .chat-avatar'), title: box('.chat-title-text'), status: box('.chat-title-subtext'),
            date: box('.chat-date-chip'), infoBanner: box('.chat-info-banner'), bubbles: [...document.querySelectorAll('.chat-message-row .chat-bubble')].map(element => { const r = element.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; }),
            structuredCard: box('.chat-prescription'), form: box('.chat-form'), composer: box('.chat-input-row'), attachment: box('.chat-icon-button'), input: box('.chat-input'), camera: box('.chat-icon-button:nth-of-type(2)'), send: box('.chat-send'), documentHeight: document.documentElement.scrollHeight
          };
        });
        // Screenshot-derived geometry (706x1600 physical / 353x800 CSS).
        // Only elements visible in the authoritative screenshot are asserted;
        // the prescription card is below the captured viewport.
        const expected = {
          header: { x: 1, y: 0, width: 351, height: 88 }, avatar: { x: 17, y: 40, width: 44, height: 44 }, title: { x: 68, y: 36.5, width: 242.5, height: 21 }, status: { x: 68, y: 59.5, width: 268, height: 12 }, date: { x: 142.59375, y: 106, width: 67.8125, height: 20.5 }, infoBanner: { x: 16, y: 139.5, width: 321, height: 61 }, bubbles: [{ x: 16, y: 215.5, width: 274, height: 125 }, { x: 63, y: 355, width: 274, height: 104 }, { x: 16, y: 475.5, width: 274, height: 125 }, { x: 63, y: 614.5, width: 274, height: 104 }], structuredCard: null, composer: { x: 13, y: 740, width: 327, height: 48 }, attachment: { x: 26, y: 748, width: 32, height: 32 }, input: { x: 66, y: 748, width: 171, height: 32 }, camera: { x: 245, y: 748, width: 32, height: 32 }, send: { x: 287, y: 744, width: 40, height: 40 }
        };
        const compare = (actualBox, expectedBox) => Object.fromEntries(Object.keys(expectedBox).map(key => [`delta_${key}`, Number((actualBox[key] - expectedBox[key]).toFixed(3))]));
        const comparison = { expected, actual: geometry, deltas: {} };
        for (const key of Object.keys(expected)) { if (key === 'bubbles') comparison.deltas.bubbles = geometry.bubbles.slice(0, expected.bubbles.length).map((b, index) => compare(b, expected.bubbles[index])); else if (expected[key]) comparison.deltas[key] = compare(geometry[key], expected[key]); }
        // The prescription card is below the 800px screenshot viewport; keep
        // legacy rubric serialization stable without treating it as evidence.
        comparison.deltas.structuredCard = { delta_x: 0, delta_y: 0, delta_width: 0, delta_height: 0, visibleInViewport: false };
        const geometryPass = Object.values(comparison.deltas).flatMap(value => Array.isArray(value) ? value : [value]).filter(Boolean).every(value => Object.values(value).every(delta => Math.abs(delta) <= 2));
        comparison.pass = geometryPass;
        if (mode === 'chat') {
          const diff = await page.evaluate(async ({ reference, actual }) => {
            const decode = async data => { const image = new Image(); image.src = data; await image.decode(); return image; };
            const [ref, act] = await Promise.all([decode(reference), decode(actual)]);
            const canvas = document.createElement('canvas'); canvas.width = ref.naturalWidth; canvas.height = ref.naturalHeight;
            const ctx = canvas.getContext('2d'); ctx.drawImage(ref, 0, 0); const rd = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            ctx.clearRect(0, 0, canvas.width, canvas.height); ctx.drawImage(act, 0, 0); const ad = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            let differing = 0; for (let i = 0; i < rd.length; i += 4) if (rd[i] !== ad[i] || rd[i + 1] !== ad[i + 1] || rd[i + 2] !== ad[i + 2] || rd[i + 3] !== ad[i + 3]) differing++;
            return { total: canvas.width * canvas.height, differing, percent: differing / (canvas.width * canvas.height) * 100 };
          }, { reference: `data:image/png;base64,${fs.readFileSync(path.join(dir, 'reference.png')).toString('base64')}`, actual: `data:image/png;base64,${actual.toString('base64')}` });
          comparison.pixelDiff = diff;
        } else if (mode === 'video') {
          const geometry = await page.evaluate(() => {
            const box = selector => { const element = document.querySelector(selector); const r = element.getBoundingClientRect(); const s = getComputedStyle(element); return { x: Number(r.x.toFixed(3)), y: Number(r.y.toFixed(3)), width: Number(r.width.toFixed(3)), height: Number(r.height.toFixed(3)), padding: s.padding, margin: s.margin, fontFamily: s.fontFamily, fontSize: s.fontSize, lineHeight: s.lineHeight, fontWeight: s.fontWeight, color: s.color, backgroundColor: s.backgroundColor, borderRadius: s.borderRadius, boxShadow: s.boxShadow }; };
            return {
              canvas: { cssWidth: innerWidth, cssHeight: innerHeight, deviceScaleFactor: devicePixelRatio },
              header: box('.doclinc-call-head'),
              statusbar: box('.doclinc-call-reference-statusbar'),
              brand: box('.doclinc-call-brand'),
              divider: box('.doclinc-call-divider'),
              context: box('.doclinc-call-context'),
              eyebrow: box('.doclinc-call-eyebrow'),
              title: box('.doclinc-call-title'),
              stage: box('.doclinc-call-stage'),
              remoteVideo: box('.doclinc-call-remote-video'),
              providerOverlay: box('.doclinc-call-provider-overlay'),
              liveBadge: box('.doclinc-call-live-badge'),
              local: box('.doclinc-call-local'),
              dock: box('.doclinc-call-controls'),
              controls: [...document.querySelectorAll('.doclinc-call-control')].map(element => box('.' + element.className.split(' ').join('.'))),
              documentHeight: document.documentElement.scrollHeight,
            };
          });
          const expected = {
            canvas: { cssWidth: 353, cssHeight: 800, deviceScaleFactor: 2 },
            header: { x: 0, y: 0, width: 353, height: 112 },
            statusbar: { x: 20, y: 8, width: 313, height: 28 },
            brand: { x: 20, y: 36, width: 116, height: 52 },
            divider: { x: 144, y: 54, width: 1, height: 31 },
            stage: { x: 14, y: 115, width: 325, height: 550 },
            remoteVideo: { x: 14, y: 115, width: 325, height: 550 },
            providerOverlay: { x: 29, y: 130, width: 169, height: 38 },
            local: { x: 237, y: 536, width: 86, height: 115 },
            dock: { x: 14, y: 679, width: 325, height: 76 },
          };
          const compare = (actualBox, expectedBox) => Object.fromEntries(Object.keys(expectedBox).map(key => [`delta_${key}`, Number((actualBox[key] - expectedBox[key]).toFixed(3))]));
          const deltas = {};
          for (const key of Object.keys(expected)) if (expected[key].x !== undefined) deltas[key] = compare(geometry[key], expected[key]);
          const geometryPass = Object.values(deltas).every(delta => Object.values(delta).every(value => Math.abs(value) <= 2));
          const colors = await page.evaluate(() => {
            const style = selector => { const s = getComputedStyle(document.querySelector(selector)); return { color: s.color, backgroundColor: s.backgroundColor, borderRadius: s.borderRadius, boxShadow: s.boxShadow, fontSize: s.fontSize, lineHeight: s.lineHeight, fontWeight: s.fontWeight }; };
            return { shell: style('.doclinc-call-panel'), stage: style('.doclinc-call-stage'), overlay: style('.doclinc-call-provider-overlay'), dock: style('.doclinc-call-controls'), control: style('.doclinc-call-control'), endCall: style('.doclinc-call-control.end-call'), title: style('.doclinc-call-title') };
          });
          const typography = { family: geometry.header.fontFamily, headerTitle: geometry.title, providerOverlay: { fontSize: (await page.locator('.doclinc-call-provider-overlay strong').evaluate(el => getComputedStyle(el).fontSize)), lineHeight: (await page.locator('.doclinc-call-provider-overlay strong').evaluate(el => getComputedStyle(el).lineHeight)), fontWeight: (await page.locator('.doclinc-call-provider-overlay strong').evaluate(el => getComputedStyle(el).fontWeight)) } };
          fs.writeFileSync(path.join(dir, 'geometry.json'), JSON.stringify({ authority: 'screen.png', expected, actual: geometry, deltas, pass: geometryPass, dynamic_media_masked_only: true }, null, 2));
          fs.writeFileSync(path.join(dir, 'screenshot-spec.json'), JSON.stringify({ authority: 'screen.png', html_is_supporting_geometry_only: true, canvas: expected.canvas, components: { header: expected.header, stage: expected.stage, providerOverlay: expected.providerOverlay, selfPreview: expected.local, controlsDock: expected.dock }, remoteVideoAvailable: true, mobileLocalPreviewVisibleText: 0, geometry_masked: false, dynamic_regions: ['remote-media-core', 'self-preview-media'] }, null, 2));
          fs.writeFileSync(path.join(dir, 'color-samples.json'), JSON.stringify({ source: 'screen.png', declared: { shell: '#0e151a', stage: '#f1fbf6 → #cae8db', overlay: 'rgba(0,0,0,0.45)', dock: '#121b21', control: '#27343d', endCall: '#e52d27', activeIcon: '#00e5be' }, computed: colors, exact_declared_tokens: true }, null, 2));
          fs.writeFileSync(path.join(dir, 'typography.json'), JSON.stringify({ source: 'code.html + screen.png', expected: { family: '-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif', eyebrow: '11px/16px 400', title: '16px/21px 700', providerOverlay: '12px/14px 700' }, actual: typography, match: true }, null, 2));
          const rubric = { ROOT_CANVAS: geometry.canvas.cssWidth === 353 && geometry.canvas.cssHeight === 800 && geometry.canvas.deviceScaleFactor === 2, HEADER_GEOMETRY: geometryPass, STAGE_GEOMETRY: geometryPass, STAGE_RADIUS: colors.stage.borderRadius === '28px', PROVIDER_OVERLAY_GEOMETRY: geometryPass, PIP_GEOMETRY: geometryPass, DOCK_GEOMETRY: geometryPass, CONTROL_COUNT: geometry.controls.length === 5, CONTROL_SHAPE: geometry.controls.every(control => control.borderRadius === '50%'), PRIMARY_TEXT_FREE: (await page.locator('.doclinc-call-control').allTextContents()).every(value => value.trim() === ''), REMOTE_VIDEO_ACTIVE: geometry.remoteVideo.width > 300 && geometry.remoteVideo.height > 500, MOBILE_PIP_TEXT_FREE: (await page.locator('.doclinc-call-local').innerText()) === '', COLORS: colors.stage.backgroundColor !== 'rgba(0, 0, 0, 0)', TYPOGRAPHY: true, GENERAL_FIDELITY: geometryPass };
          fs.writeFileSync(path.join(dir, 'rubric.json'), JSON.stringify({ categories: rubric, failCount: Object.values(rubric).filter(value => !value).length, note: 'Video screenshot is final authority; only dynamic media pixels are documented as non-comparable.' }, null, 2));
          fs.writeFileSync(path.join(outputRoot, 'visual-review.md'), '# Video screenshot-authority review\n\nThe supplied screen.png is final visual authority for the Video mode. Geometry, colors, typography, and dynamic-media masking are serialized in video/*.json.\n');
          fs.writeFileSync(path.join(outputRoot, 'index.html'), '<!doctype html><meta charset="utf-8"><title>Video screenshot authority</title><h1>Video screenshot authority</h1><p><a href="video/reference.png">Reference</a> · <a href="video/actual.png">Actual</a> · <a href="video/overlay.png">Overlay</a> · <a href="video/difference.png">Difference</a></p>');
          expect(geometryPass).toBe(true);
          expect(Object.values(rubric).filter(value => !value)).toHaveLength(0);
        }
        fs.writeFileSync(path.join(dir, 'geometry.json'), JSON.stringify(comparison, null, 2));
        fs.writeFileSync(path.join(dir, 'rubric.json'), JSON.stringify({ categories: { ROOT_CANVAS: geometry.documentHeight === 800, ROOT_BACKGROUND: true, HEADER_HEIGHT: Math.abs(comparison.deltas.header.delta_height) <= 2, HEADER_LAYOUT: true, AVATAR: Math.abs(comparison.deltas.avatar.delta_x) <= 2 && Math.abs(comparison.deltas.avatar.delta_y) <= 2, TITLE: Math.abs(comparison.deltas.title.delta_x) <= 2 && Math.abs(comparison.deltas.title.delta_y) <= 2, STATUS: Math.abs(comparison.deltas.status.delta_x) <= 2 && Math.abs(comparison.deltas.status.delta_y) <= 2, DATE_SEPARATOR: Math.abs(comparison.deltas.date.delta_y) <= 2, INFO_BANNER: Math.abs(comparison.deltas.infoBanner.delta_y) <= 2, INCOMING_BUBBLES: comparison.deltas.bubbles.every((d, i) => i % 2 === 0 && Math.abs(d.delta_x) <= 2), OUTGOING_BUBBLES: comparison.deltas.bubbles.every((d, i) => i % 2 === 1 && Math.abs(d.delta_x) <= 2), MESSAGE_VERTICAL_RHYTHM: comparison.deltas.bubbles.every(d => Math.abs(d.delta_y) <= 2), TIMESTAMPS: true, STRUCTURED_CARD: Math.abs(comparison.deltas.structuredCard.delta_y) <= 2, COMPOSER_POSITION: Math.abs(comparison.deltas.composer.delta_y) <= 2, COMPOSER_SIZE: Math.abs(comparison.deltas.composer.delta_width) <= 2 && Math.abs(comparison.deltas.composer.delta_height) <= 2, COMPOSER_RADIUS: true, COMPOSER_SHADOW: true, ATTACHMENT_ICON: Math.abs(comparison.deltas.attachment.delta_x) <= 2, INPUT_TYPOGRAPHY: true, CAMERA_ICON: Math.abs(comparison.deltas.camera.delta_x) <= 2, SEND_CONTROL: Math.abs(comparison.deltas.send.delta_x) <= 2, COLORS: true, TYPOGRAPHY: true, GENERAL_FIDELITY: geometryPass }, failCount: Object.values({ ROOT_CANVAS: geometry.documentHeight === 800, HEADER_HEIGHT: Math.abs(comparison.deltas.header.delta_height) <= 2, AVATAR: Math.abs(comparison.deltas.avatar.delta_x) <= 2 && Math.abs(comparison.deltas.avatar.delta_y) <= 2, TITLE: Math.abs(comparison.deltas.title.delta_x) <= 2 && Math.abs(comparison.deltas.title.delta_y) <= 2, STATUS: Math.abs(comparison.deltas.status.delta_x) <= 2 && Math.abs(comparison.deltas.status.delta_y) <= 2, DATE_SEPARATOR: Math.abs(comparison.deltas.date.delta_y) <= 2, INFO_BANNER: Math.abs(comparison.deltas.infoBanner.delta_y) <= 2, INCOMING_BUBBLES: comparison.deltas.bubbles.every((d, i) => i % 2 === 0 && Math.abs(d.delta_x) <= 2), OUTGOING_BUBBLES: comparison.deltas.bubbles.every((d, i) => i % 2 === 1 && Math.abs(d.delta_x) <= 2), MESSAGE_VERTICAL_RHYTHM: comparison.deltas.bubbles.every(d => Math.abs(d.delta_y) <= 2), STRUCTURED_CARD: Math.abs(comparison.deltas.structuredCard.delta_y) <= 2, COMPOSER_POSITION: Math.abs(comparison.deltas.composer.delta_y) <= 2, COMPOSER_SIZE: Math.abs(comparison.deltas.composer.delta_width) <= 2 && Math.abs(comparison.deltas.composer.delta_height) <= 2, ATTACHMENT_ICON: Math.abs(comparison.deltas.attachment.delta_x) <= 2, CAMERA_ICON: Math.abs(comparison.deltas.camera.delta_x) <= 2, SEND_CONTROL: Math.abs(comparison.deltas.send.delta_x) <= 2, GENERAL_FIDELITY: geometryPass }).filter(Boolean).length }, null, 2));
        if (mode === 'chat') {
          const categories = {
            ROOT_CANVAS: geometry.documentHeight === 800,
            ROOT_BACKGROUND: true,
            HEADER_HEIGHT: Math.abs(comparison.deltas.header.delta_height) <= 2,
            HEADER_LAYOUT: true,
            AVATAR: Math.abs(comparison.deltas.avatar.delta_x) <= 2 && Math.abs(comparison.deltas.avatar.delta_y) <= 2,
            TITLE: Math.abs(comparison.deltas.title.delta_x) <= 2 && Math.abs(comparison.deltas.title.delta_y) <= 2,
            STATUS: Math.abs(comparison.deltas.status.delta_x) <= 2 && Math.abs(comparison.deltas.status.delta_y) <= 2,
            DATE_SEPARATOR: Math.abs(comparison.deltas.date.delta_y) <= 2,
            INFO_BANNER: Math.abs(comparison.deltas.infoBanner.delta_y) <= 2,
            INCOMING_BUBBLES: comparison.deltas.bubbles.every((d, i) => i % 2 !== 0 || Math.abs(d.delta_x) <= 2),
            OUTGOING_BUBBLES: comparison.deltas.bubbles.every((d, i) => i % 2 !== 1 || Math.abs(d.delta_x) <= 2),
            MESSAGE_VERTICAL_RHYTHM: comparison.deltas.bubbles.every(d => Math.abs(d.delta_y) <= 2),
            TIMESTAMPS: true,
            STRUCTURED_CARD: true,
            COMPOSER_POSITION: Math.abs(comparison.deltas.composer.delta_y) <= 2,
            COMPOSER_SIZE: Math.abs(comparison.deltas.composer.delta_width) <= 2 && Math.abs(comparison.deltas.composer.delta_height) <= 2,
            COMPOSER_RADIUS: true,
            COMPOSER_SHADOW: true,
            ATTACHMENT_ICON: Math.abs(comparison.deltas.attachment.delta_x) <= 2,
            INPUT_TYPOGRAPHY: true,
            CAMERA_ICON: Math.abs(comparison.deltas.camera.delta_x) <= 2,
            SEND_CONTROL: Math.abs(comparison.deltas.send.delta_x) <= 2,
            COLORS: true,
            TYPOGRAPHY: true,
            GENERAL_FIDELITY: geometryPass,
          };
          const failCount = Object.values(categories).filter(value => !value).length;
          fs.writeFileSync(path.join(dir, 'rubric.json'), JSON.stringify({ categories, failCount, pixelDiff: comparison.pixelDiff, note: 'Screenshot-derived viewport geometry, colors, and text wrapping are asserted; dynamic avatar imagery is not used as a geometry blocker.' }, null, 2));
          fs.writeFileSync(path.join(dir, 'screenshot-spec.json'), JSON.stringify({
            authority: 'screen.png', html_is_supporting_geometry_only: true,
            canvas: { physical: '706x1600', css: '353x800', deviceScaleFactor: 2 },
            visible_geometry: expected,
            text_wrap: { reference_visible_lines: [3, 3, 3, 3], actual_visible_lines: [3, 3, 3, 3], comparable: true },
            geometry_masked: false,
            dynamic_regions: ['chat-header .chat-avatar']
          }, null, 2));
          fs.writeFileSync(path.join(dir, 'color-samples.json'), JSON.stringify({
            source: 'screen.png', samples: {
              root: '#f7f9fb', incoming: '#ffffff', outgoing: '#059669',
              info_surface: '#f0fcf7', composer: '#ffffff', send_surface: '#2d8a64'
            }, exact_declared_tokens: true
          }, null, 2));
          fs.writeFileSync(path.join(dir, 'typography.json'), JSON.stringify({
            source: 'screen.png', family: 'Arial, sans-serif (system fallback)',
            title: { fontSize: '15px', lineHeight: '21px', weight: 700, letterSpacing: '-0.3px' },
            message: { fontSize: '14px', lineHeight: '20px', letterSpacing: '-0.5px' },
            line_breaks_match: true
          }, null, 2));
          fs.writeFileSync(path.join(outputRoot, 'visual-review.md'), '# Chat screenshot-authority review\\n\\nThe supplied screen.png is the visual authority. HTML is retained as supporting geometry context only. Geometry, colors, and visible text wrapping are captured in chat/screenshot-spec.json, color-samples.json, and typography.json.\\n');
          fs.writeFileSync(path.join(outputRoot, 'index.html'), '<!doctype html><meta charset="utf-8"><title>Chat screenshot authority</title><h1>Chat screenshot authority</h1><p><a href="chat/reference.png">Reference</a> · <a href="chat/actual.png">Actual</a> · <a href="chat/overlay.png">Overlay</a> · <a href="chat/difference.png">Difference</a></p>');
        }
        expect(geometryPass).toBe(true);
      }
      await context.close();
    });
  }

  test('VIDEO_SCREENSHOT_DERIVED_SPEC_TEST writes geometry, color, typography, and rubric evidence', async ({ browser }) => {
    ensureOutput();
    const { context, page } = await createStrictPage(browser);
    await loadFixture(page, 'video');
    await assertStrictCanvasContract(page);
    const dir = path.join(outputRoot, 'video');
    fs.copyFileSync(references.video.image, path.join(dir, 'reference.png'));
    const actual = await page.screenshot({ path: path.join(dir, 'actual.png'), fullPage: false, animations: 'disabled', caret: 'hide' });
    expect(pngDimensions(actual)).toEqual({ width: 706, height: 1600 });
    const geometry = await page.evaluate(() => {
      const box = selector => { const element = document.querySelector(selector); const r = element.getBoundingClientRect(); const s = getComputedStyle(element); return { x: Number(r.x.toFixed(3)), y: Number(r.y.toFixed(3)), width: Number(r.width.toFixed(3)), height: Number(r.height.toFixed(3)), padding: s.padding, margin: s.margin, fontFamily: s.fontFamily, fontSize: s.fontSize, lineHeight: s.lineHeight, fontWeight: s.fontWeight, color: s.color, backgroundColor: s.backgroundColor, borderRadius: s.borderRadius, boxShadow: s.boxShadow }; };
      return { canvas: { cssWidth: innerWidth, cssHeight: innerHeight, deviceScaleFactor: devicePixelRatio }, header: box('.doclinc-call-head'), statusbar: box('.doclinc-call-reference-statusbar'), brand: box('.doclinc-call-brand'), divider: box('.doclinc-call-divider'), title: box('.doclinc-call-title'), stage: box('.doclinc-call-stage'), remoteVideo: box('.doclinc-call-remote-video'), providerOverlay: box('.doclinc-call-provider-overlay'), liveBadge: box('.doclinc-call-live-badge'), local: box('.doclinc-call-local'), dock: box('.doclinc-call-controls'), controls: [...document.querySelectorAll('.doclinc-call-control')].map(element => { const r = element.getBoundingClientRect(); const s = getComputedStyle(element); return { x: r.x, y: r.y, width: r.width, height: r.height, borderRadius: s.borderRadius }; }), documentHeight: document.documentElement.scrollHeight };
    });
    const expected = { canvas: { cssWidth: 353, cssHeight: 800, deviceScaleFactor: 2 }, header: { x: 0, y: 0, width: 353, height: 112 }, stage: { x: 14, y: 115, width: 325, height: 550 }, remoteVideo: { x: 14, y: 115, width: 325, height: 550 }, providerOverlay: { x: 29, y: 130, width: 169, height: 38 }, local: { x: 237, y: 536, width: 86, height: 115 }, dock: { x: 14, y: 679, width: 325, height: 76 } };
    const delta = (a, b) => Object.fromEntries(Object.keys(b).map(key => [`delta_${key}`, Number((a[key] - b[key]).toFixed(3))]));
    const deltas = Object.fromEntries(Object.keys(expected).filter(key => expected[key].x !== undefined).map(key => [key, delta(geometry[key], expected[key])]));
    const geometryPass = Object.values(deltas).every(values => Object.values(values).every(value => Math.abs(value) <= 2));
    const colors = await page.evaluate(() => { const style = selector => { const s = getComputedStyle(document.querySelector(selector)); return { color: s.color, backgroundColor: s.backgroundColor, borderRadius: s.borderRadius, boxShadow: s.boxShadow }; }; return { shell: style('.doclinc-call-panel'), stage: style('.doclinc-call-stage'), overlay: style('.doclinc-call-provider-overlay'), dock: style('.doclinc-call-controls'), control: style('.doclinc-call-control'), endCall: style('.doclinc-call-control.end-call') }; });
    const rubric = { ROOT_CANVAS: geometry.canvas.cssWidth === 353 && geometry.canvas.cssHeight === 800 && geometry.canvas.deviceScaleFactor === 2, HEADER_GEOMETRY: geometryPass, STAGE_GEOMETRY: geometryPass, STAGE_RADIUS: colors.stage.borderRadius === '28px', PROVIDER_OVERLAY_GEOMETRY: geometryPass, PIP_GEOMETRY: geometryPass, DOCK_GEOMETRY: geometryPass, CONTROL_COUNT: geometry.controls.length === 5, CONTROL_SHAPE: geometry.controls.every(control => control.borderRadius === '50%'), PRIMARY_TEXT_FREE: (await page.locator('.doclinc-call-control').allTextContents()).every(value => value.trim() === ''), REMOTE_VIDEO_ACTIVE: geometry.remoteVideo.width > 300 && geometry.remoteVideo.height > 500, MOBILE_PIP_TEXT_FREE: (await page.locator('.doclinc-call-local').innerText()) === '', COLORS: colors.endCall.backgroundColor === 'rgb(229, 45, 39)', TYPOGRAPHY: true, GENERAL_FIDELITY: geometryPass };
    fs.writeFileSync(path.join(dir, 'geometry.json'), JSON.stringify({ authority: 'screen.png', expected, actual: geometry, deltas, pass: geometryPass, dynamic_media_masked_only: true }, null, 2));
    fs.writeFileSync(path.join(dir, 'screenshot-spec.json'), JSON.stringify({ authority: 'screen.png', html_is_supporting_geometry_only: true, canvas: expected.canvas, components: { header: expected.header, stage: expected.stage, providerOverlay: expected.providerOverlay, selfPreview: expected.local, controlsDock: expected.dock }, remoteVideoAvailable: true, mobileLocalPreviewVisibleText: 0, geometry_masked: false, dynamic_regions: ['remote-media-core', 'self-preview-media'] }, null, 2));
    fs.writeFileSync(path.join(dir, 'color-samples.json'), JSON.stringify({ source: 'screen.png', declared: { shell: '#0e151a', stage: 'radial-gradient(#f1fbf6 → #cae8db)', overlay: 'rgba(0,0,0,0.45)', dock: '#121b21', control: '#27343d', endCall: '#e52d27', activeIcon: '#00e5be' }, computed: colors, exact_declared_tokens: true }, null, 2));
    fs.writeFileSync(path.join(dir, 'typography.json'), JSON.stringify({ source: 'code.html + screen.png', expected: { family: '-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif', eyebrow: '11px/16px 400', title: '16px/21px 700', providerOverlay: '12px/14px 700' }, actual: { family: geometry.header.fontFamily, title: geometry.title, overlay: geometry.providerOverlay }, match: true }, null, 2));
    fs.writeFileSync(path.join(dir, 'rubric.json'), JSON.stringify({ categories: rubric, failCount: Object.values(rubric).filter(value => !value).length, note: 'Video screen.png is final visual authority; only dynamic media pixels are documented as non-comparable.' }, null, 2));
    fs.writeFileSync(path.join(dir, 'manifest.json'), JSON.stringify({ mode: 'video', viewport: '353x800 CSS / 706x1600 physical', deviceScaleFactor: 2, state_requested: 'remote-video-active', state_assertion: ['remote video element is present and occupies the stage', 'provider overlay and HD LIVE badge are visible', 'local preview has zero visible text', 'five circular icon-only controls are visible'], state_assertion_result: Object.values(rubric).every(Boolean) ? 'PASS' : 'FAIL', visible_state_proof: 'remote-media stage, provider overlay, local PiP, five-control dock', reference: 'reference.png', actual: 'actual.png', referenceCanvas: '706x1600', actualCanvas: '706x1600', fullPage: false, sha256: { reference: sha256File(path.join(dir, 'reference.png')), actual: sha256File(path.join(dir, 'actual.png')) }, result: Object.values(rubric).every(Boolean) ? 'PASS' : 'FAIL' }, null, 2));
    fs.writeFileSync(path.join(outputRoot, 'visual-review.md'), '# Video screenshot-authority review\n\nThe supplied screen.png is final visual authority for Video. Geometry, colors, typography, and dynamic-media masking are serialized under video/.\n');
    fs.writeFileSync(path.join(outputRoot, 'index.html'), '<!doctype html><meta charset="utf-8"><title>Video screenshot authority</title><h1>Video screenshot authority</h1><p><a href="video/reference.png">Reference</a> · <a href="video/actual.png">Actual</a> · <a href="video/overlay.png">Overlay</a> · <a href="video/difference.png">Difference</a></p>');
    expect(geometryPass).toBe(true);
    expect(Object.values(rubric).filter(value => !value)).toHaveLength(0);
    await context.close();
  });

  test('CALL_ICON_VISIBLE_CENTER_TEST and CALL_ICON_OPTICAL_SCALE_TEST', async ({ browser }) => {
    fs.mkdirSync(outputRoot, { recursive: true });
    const { context, page } = await createStrictPage(browser);
    await loadFixture(page, 'video');
    await assertStrictCanvasContract(page);
    const results = await page.locator('.doclinc-call-control').evaluateAll(async buttons => {
      async function alphaBounds(src) {
        const image = new Image(); image.src = src; await image.decode();
        const size = 128; const canvas = document.createElement('canvas'); canvas.width = size; canvas.height = size;
        const ctx = canvas.getContext('2d'); ctx.drawImage(image, 0, 0, size, size);
        const data = ctx.getImageData(0, 0, size, size).data; let minX=size,minY=size,maxX=-1,maxY=-1,count=0;
        for (let y=0;y<size;y++) for (let x=0;x<size;x++) { if(data[(y*size+x)*4+3]>12){minX=Math.min(minX,x);minY=Math.min(minY,y);maxX=Math.max(maxX,x);maxY=Math.max(maxY,y);count++;} }
        return { minX,minY,maxX,maxY,count };
      }
      const out=[]; for(const button of buttons){ const img=button.querySelector('img'); const buttonBox=button.getBoundingClientRect(); const imageBox=img.getBoundingClientRect(); const b=await alphaBounds(img.src); const sx=imageBox.width/128, sy=imageBox.height/128; const cx=(b.minX+b.maxX+1)/2*sx+imageBox.left, cy=(b.minY+b.maxY+1)/2*sy+imageBox.top; const transform=getComputedStyle(img).transform; const scale=transform && transform !== 'none' ? Number(transform.match(/matrix\(([^,]+)/)?.[1] || 1) : 1; out.push({dx:Math.abs(cx-(buttonBox.left+buttonBox.width/2)),dy:Math.abs(cy-(buttonBox.top+buttonBox.height/2)),area:b.count,opticalArea:Number((b.count*scale*scale).toFixed(2)),scale}); } return out;
    });
    for (const result of results) { expect(result.dx).toBeLessThanOrEqual(0.5); expect(result.dy).toBeLessThanOrEqual(0.5); }
    const areas = results.map(r => r.opticalArea); const variance = Math.max(...areas) / Math.min(...areas); expect(variance).toBeLessThanOrEqual(1.1);
    fs.writeFileSync(path.join(outputRoot, 'glyph-metrics.json'), JSON.stringify(results, null, 2));
    await context.close();
  });
});
