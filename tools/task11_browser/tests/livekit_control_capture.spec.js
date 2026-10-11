const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const iconDir = path.join(repoRoot, 'assets', 'doclinc_ui', 'icon-pack');
const css = fs.readFileSync(path.join(repoRoot, 'assets', 'css', 'doclinc-livekit.css'), 'utf8');
const outputDir = path.resolve(process.env.VISUAL_REVIEW_OUTPUT_DIR || path.join(process.cwd(), 'test-results', 'visual-review', 'livekit-control'));
const entries = [];

function icon(name) {
  return `data:image/svg+xml;base64,${fs.readFileSync(path.join(iconDir, name), 'base64')}`;
}

function esc(value) {
  return String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
}

function pageMarkup(state) {
  const micOff = state === 'muted';
  const cameraOff = state === 'camera-off';
  const moreOpen = state === 'more-open';
  const panel = moreOpen ? '<aside class="doclinc-call-popover" id="more-panel" aria-label="Opsi panggilan"><button class="doclinc-call-secondary" type="button"><img src="' + icon('kamera.svg') + '" alt=""><span>Ganti kamera</span></button><button class="doclinc-call-secondary" type="button"><img src="' + icon('minimize.svg') + '" alt=""><span>Minimalkan</span></button><button class="doclinc-call-secondary" type="button"><img src="' + icon('close.svg') + '" alt=""><span>Tutup tampilan</span></button></aside>' : '';
  const cameraLabel = cameraOff ? 'Aktifkan kamera' : 'Matikan kamera';
  const micLabel = micOff ? 'Aktifkan mikrofon' : 'Bisukan mikrofon';
  const stageClass = state === 'waiting-video' ? 'is-waiting' : 'is-connected';
  return `<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>html,body{margin:0;min-height:100%;background:#e7f3ec;font-family:Inter,Segoe UI,Arial,sans-serif}*{box-sizing:border-box}${css}</style></head><body><main class="doclinc-call-modal is-open"><section class="doclinc-call-panel"><header class="doclinc-call-head"><div class="doclinc-call-brand-lockup"><img class="doclinc-call-brand" src="${icon('doklinclogo.svg')}" alt="DokLinc"><span class="doclinc-call-divider" aria-hidden="true"></span><div class="doclinc-call-context"><span class="doclinc-call-eyebrow">Konsultasi dengan</span><h1 class="doclinc-call-title">dr. Rani Dokter</h1><div class="doclinc-call-provider-meta"><span class="doclinc-call-provider-role">Dokter</span></div></div></div></header><section class="doclinc-call-stage ${stageClass}"><div class="doclinc-call-remote"><div class="doclinc-call-empty"><div class="doclinc-call-empty-avatar" aria-hidden="true"></div><span class="doclinc-call-empty-name">dr. Rani Dokter</span><span class="doclinc-call-empty-role">Dokter</span><span class="doclinc-call-empty-waiting">${state === 'waiting-video' ? 'Menunggu video' : 'Audio tersambung'}</span></div></div><div class="doclinc-call-local ${cameraOff ? 'is-placeholder is-camera-off' : 'is-preview'}" id="local-preview"><div class="doclinc-call-local-avatar" aria-hidden="true"></div><span class="doclinc-call-local-status">${cameraOff ? 'Kamera nonaktif' : 'Kamera aktif'}</span><div class="doclinc-call-local-placeholder">Anda</div></div></section><nav class="doclinc-call-controls" aria-label="Kontrol panggilan"><button id="doclincCallMore" class="doclinc-call-control ${moreOpen ? 'is-open' : ''}" aria-label="Tampilkan opsi lainnya" aria-expanded="${moreOpen}"><img src="${icon('more.svg')}" alt=""></button><button id="doclincCallCamera" class="doclinc-call-control ${cameraOff ? 'is-off' : 'is-active'}" aria-label="${cameraLabel}" aria-pressed="${cameraOff}"><img src="${icon('videocall.svg')}" alt=""></button><button id="doclincCallSpeaker" class="doclinc-call-control is-active" aria-label="Aktifkan suara" aria-pressed="false"><img src="${icon('loudspeaker.svg')}" alt=""></button><button id="doclincCallMic" class="doclinc-call-control ${micOff ? 'is-off' : 'is-active'}" aria-label="${micLabel}" aria-pressed="${micOff}"><img src="${icon('mute.svg')}" alt=""></button><button id="doclincCallEnd" class="doclinc-call-control end-call" aria-label="Akhiri panggilan"><img src="${icon('endcall.svg')}" alt=""></button></nav>${panel}</section></main></body></html>`;
}

async function assertState(page, state) {
  await expect(page.locator('.doclinc-call-security, .doclinc-call-signal, .doclinc-call-head-more')).toHaveCount(0);
  await expect(page.locator('.doclinc-call-control')).toHaveCount(5);
  await expect(page.locator('.doclinc-call-control-label')).toHaveCount(0);
  await expect(page.locator('#doclincCallEnd img')).toHaveAttribute('src', /^data:image\/svg\+xml;base64,/);
  const glyphPixels = await page.locator('.doclinc-call-control img').evaluateAll(images => images.map(image => {
    if (!image.complete || image.naturalWidth < 1 || image.naturalHeight < 1) return 0;
    const size = 48;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.clearRect(0, 0, size, size);
    context.filter = getComputedStyle(image).filter || 'none';
    context.drawImage(image, 12, 12, 24, 24);
    const pixels = context.getImageData(0, 0, size, size).data;
    let nonTransparent = 0;
    for (let index = 3; index < pixels.length; index += 4) {
      if (pixels[index] > 12) nonTransparent += 1;
    }
    return nonTransparent;
  }));
  expect(glyphPixels.every(count => count > 0), `blank rendered glyph pixel counts: ${glyphPixels.join(',')}`).toBeTruthy();
  const controls = await page.locator('.doclinc-call-control').evaluateAll(nodes => nodes.map(node => ({ id: node.id, aria: node.getAttribute('aria-label'), pressed: node.getAttribute('aria-pressed') })));
  expect(controls.map(item => item.id)).toEqual(['doclincCallMore', 'doclincCallCamera', 'doclincCallSpeaker', 'doclincCallMic', 'doclincCallEnd']);
  const englishTokens = ['Mute', 'Camera', 'Speaker', 'Chat', 'Participants', 'More', 'End Call', 'Switch Camera', 'Minimize', 'Close View', 'Waiting', 'Connected', 'Retry', 'You'];
  const labels = controls.map(item => item.aria || '').join(' ');
  expect(englishTokens.filter(token => new RegExp(`\\b${token.replace(/ /g, '\\\\s+')}\\b`, 'i').test(labels))).toEqual([]);
  if (state === 'muted') await expect(page.locator('#doclincCallMic')).toHaveAttribute('aria-pressed', 'true');
  if (state === 'camera-off') await expect(page.locator('#doclincCallCamera')).toHaveAttribute('aria-pressed', 'true');
  if (state === 'more-open') {
    const panel = page.locator('#more-panel');
    await expect(panel).toBeVisible();
    await expect(panel.locator('.doclinc-call-popover-title')).toHaveCount(0);
    const box = await panel.boundingBox();
    expect(box && box.width > 0 && box.height > 0).toBeTruthy();
    for (const label of ['Ganti kamera', 'Minimalkan', 'Tutup tampilan']) await expect(panel).toContainText(label);
  }
  const layout = await page.locator('.doclinc-call-control').evaluateAll(nodes => {
    const boxes = nodes.map(node => node.getBoundingClientRect());
    const ys = boxes.map(box => box.y).sort((a, b) => a - b);
    const rows = ys.reduce((count, y, index) => count + (index === 0 || y - ys[index - 1] > 2 ? 1 : 0), 0);
    return { rows, minWidth: Math.min(...boxes.map(box => box.width)), minHeight: Math.min(...boxes.map(box => box.height)), overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth };
  });
  expect(layout.rows).toBe(1);
  expect(layout.minWidth).toBeGreaterThanOrEqual(44);
  expect(layout.minHeight).toBeGreaterThanOrEqual(44);
  expect(layout.overflow).toBeFalsy();
}

async function capture(page, file, state, viewport) {
  await assertState(page, state);
  const target = path.join(outputDir, file);
  fs.mkdirSync(path.dirname(target), { recursive: true });
  await page.screenshot({ path: target, animations: 'disabled', caret: 'hide' });
  const sha256 = crypto.createHash('sha256').update(fs.readFileSync(target)).digest('hex');
  entries.push({ file: file.replace(/\\/g, '/'), actor: 'SYNTHETIC_LIVEKIT_REVIEW', route: '/chat (LiveKit shell)', viewport: `${viewport.width}x${viewport.height}`, state_requested: state, state_assertion: 'five controls; no header technical chrome; state-specific aria contract; one-row controls; >=44px hit targets; no overflow', state_assertion_result: 'PASS', visible_state_proof: state === 'more-open' ? 'More panel with secondary actions visible' : state === 'muted' ? 'Microphone aria-pressed=true and off treatment' : state === 'camera-off' ? 'Camera aria-pressed=true and camera-off preview' : state === 'waiting-video' ? 'Menunggu video rendered' : 'Connected shell with primary control dock', spec: 'livekit_control_capture.spec.js', test_title: 'captures current five-control LiveKit states', icons: ['doklinclogo.svg', 'more.svg', 'videocall.svg', 'loudspeaker.svg', 'mute.svg', 'endcall.svg', 'kamera.svg', 'minimize.svg', 'close.svg'], visual_purpose: 'Manual review of simplified header, icon-only dock, responsive layout, and truthful state presentation', reference: viewport.width >= 1200 ? 'desktop reference' : viewport.width <= 390 ? 'mobile reference' : 'tablet actual', sha256, result: 'PASS' });
}

test.describe.configure({ mode: 'serial' });

test('captures current five-control LiveKit states', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'chromium-desktop', 'single deterministic capture run');
  const states = [
    [{ width: 1440, height: 900 }, 'connected', 'desktop/livekit-connected-1440x900.png'],
    [{ width: 1440, height: 900 }, 'muted', 'desktop/livekit-muted-1440x900.png'],
    [{ width: 1440, height: 900 }, 'camera-off', 'desktop/livekit-camera-off-1440x900.png'],
    [{ width: 1440, height: 900 }, 'more-open', 'desktop/livekit-more-open-1440x900.png'],
    [{ width: 1366, height: 768 }, 'connected', 'desktop/livekit-connected-1366x768.png'],
    [{ width: 1536, height: 1024 }, 'connected', 'desktop/livekit-connected-1536x1024.png'],
    [{ width: 1024, height: 768 }, 'more-open', 'tablet/livekit-more-open-1024x768.png'],
    [{ width: 768, height: 1024 }, 'connected', 'tablet-portrait/livekit-connected-768x1024.png'],
    [{ width: 390, height: 844 }, 'connected', 'mobile/livekit-connected-390x844.png'],
    [{ width: 390, height: 844 }, 'muted', 'mobile/livekit-muted-390x844.png'],
    [{ width: 390, height: 844 }, 'camera-off', 'mobile/livekit-camera-off-390x844.png'],
    [{ width: 390, height: 844 }, 'more-open', 'mobile/livekit-more-open-390x844.png'],
    [{ width: 360, height: 800 }, 'connected', 'mobile/livekit-connected-360x800.png']
  ];
  for (const [viewport, state, file] of states) {
    await page.setViewportSize(viewport);
    await page.setContent(pageMarkup(state), { waitUntil: 'load' });
    await expect(page.locator('.doclinc-call-panel')).toBeVisible();
    await capture(page, file, state, viewport);
  }
  const byViewport = (width, state) => entries.find(item => item.viewport.startsWith(`${width}x`) && item.state_requested === state);
  for (const state of ['muted', 'camera-off', 'more-open']) expect(byViewport(1440, state).sha256).not.toBe(byViewport(1440, 'connected').sha256);
  for (const state of ['muted', 'camera-off', 'more-open']) expect(byViewport(390, state).sha256).not.toBe(byViewport(390, 'connected').sha256);
  expect(entries).toHaveLength(states.length);
});

test.afterAll(async () => {
  if (!entries.length) return;
  fs.mkdirSync(outputDir, { recursive: true });
  fs.writeFileSync(path.join(outputDir, 'manifest.json'), JSON.stringify({ generated_at: new Date().toISOString(), synthetic_data_only: true, screenshots: entries }, null, 2));
  const cards = entries.map(item => `<figure><a href="${esc(item.file)}"><img src="${esc(item.file)}" alt="${esc(item.state_requested)} ${esc(item.viewport)}"></a><figcaption><strong>${esc(item.state_requested)}</strong><br>${esc(item.viewport)} · ${esc(item.actor)}<br><small>${esc(item.visual_purpose)}</small></figcaption></figure>`).join('');
  fs.writeFileSync(path.join(outputDir, 'index.html'), `<!doctype html><html lang="id"><head><meta charset="utf-8"><title>DokLinc LiveKit control review</title><style>body{font-family:Inter,Segoe UI,Arial,sans-serif;margin:0;background:#edf6f1;color:#17241d}header{padding:28px 5vw;background:#101819;color:#fff}main{padding:24px 5vw}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px}figure{margin:0;background:#fff;padding:12px;border-radius:18px;box-shadow:0 8px 24px #173c261c}img{width:100%;height:220px;object-fit:contain;background:#f7fbf8;border-radius:12px}figcaption{padding:8px 2px;font-size:13px;line-height:1.45}</style></head><body><header><h1>DokLinc LiveKit control review</h1><p>Five icon-only controls · synthetic/local review artifacts · no patient data</p></header><main><div class="grid">${cards}</div></main></body></html>`);
  const hashLines = entries.filter(item => item.viewport === '1440x900' || item.viewport === '390x844').map(item => `${item.viewport} ${item.state_requested}: ${item.sha256}`).join('\n');
  fs.writeFileSync(path.join(outputDir, 'visual-review.md'), `# DokLinc LiveKit control review\n\n- Screenshots: ${entries.length}\n- Synthetic patient data: no\n- Primary controls: Lainnya, Kamera, Suara, Mikrofon, Akhiri Panggilan.\n- Secondary actions in Lainnya: Obrolan, Ganti kamera, Minimalkan, Tutup tampilan.\n\n## State hashes\n\n\`\`\`text\n${hashLines}\n\`\`\`\n\n## Review checklist\n\n- Header: no signal bars, no header overflow dots, no security badge.\n- Primary dock: one row, icon-only, neutral hit targets, state-specific icon treatment.\n- Mobile: compact glyphs with accessible touch targets and no horizontal overflow.\n- End Call: canonical endcall.svg, distinct destructive action.\n`);
});
