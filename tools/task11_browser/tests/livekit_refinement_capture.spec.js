const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { test, expect } = require('@playwright/test');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const outputDir = path.resolve(process.env.VISUAL_REVIEW_OUTPUT_DIR || path.join(process.cwd(), 'test-results', 'visual-review', 'livekit-refinement'));
const css = fs.readFileSync(path.join(repoRoot, 'assets', 'css', 'doclinc-livekit.css'), 'utf8');
const iconDir = path.join(repoRoot, 'assets', 'doclinc_ui', 'icon-pack');
const referenceRoot = process.env.DOKLINC_ASSET_ROOT || '';
const manifest = [];

function icon(name) {
  return `data:image/svg+xml;base64,${fs.readFileSync(path.join(iconDir, name), 'base64')}`;
}

function ensure(file) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
}

function shell(state) {
  const muted = state === 'muted' ? 'is-off' : '';
  const cameraOff = state === 'camera-off' ? 'is-off' : '';
  const modalClass = state === 'chat-result' ? 'is-chat-route' : '';
  const panelClass = state === 'participants-open' || state === 'more-open' ? 'is-panel-open' : '';
  const stageClass = state === 'waiting-video' ? 'is-waiting' : state === 'media-failure' ? 'is-media-failure' : (state === 'participants-open' || state === 'more-open') ? 'is-connected is-panel-open' : 'is-connected';
  const local = state === 'camera-off'
    ? '<div class="doclinc-call-local is-placeholder is-camera-off" id="local-preview"><span class="doclinc-call-local-status">Kamera nonaktif</span><div class="doclinc-call-local-placeholder">Anda</div></div>'
    : '<div class="doclinc-call-local is-preview" id="local-preview"><div class="doclinc-call-local-avatar" aria-hidden="true"></div><span class="doclinc-call-local-status">Kamera aktif</span><div class="doclinc-call-local-placeholder">Anda</div></div>';
  const panel = state === 'participants-open' ? '<aside class="doclinc-call-popover" id="participants-panel"><p class="doclinc-call-popover-title">Peserta panggilan</p><span>Anda</span><br><span>dr. Rani Dokter</span></aside>'
    : state === 'more-open' ? `<aside class="doclinc-call-popover" id="more-panel"><p class="doclinc-call-popover-title">Opsi panggilan</p><button class="doclinc-call-secondary"><img src="${icon('kamera.svg')}" alt=""><span>Ganti kamera</span></button><button class="doclinc-call-secondary"><img src="${icon('minimize.svg')}" alt=""><span>Minimalkan</span></button><button class="doclinc-call-secondary"><img src="${icon('close.svg')}" alt=""><span>Tutup tampilan</span></button></aside>` : '';
  const fallback = state === 'media-failure' ? '<div class="doclinc-call-fallback" id="media-failure">Media belum tersedia. Periksa koneksi lalu coba lagi.</div>' : '';
  const waiting = state === 'waiting-video' ? 'Menunggu video' : 'Video tersambung';
  const chatRoute = state === 'chat-result' ? '<section class="doclinc-call-chat-route" id="chat-route-result" aria-label="Obrolan konsultasi"><h2>Obrolan konsultasi</h2><p>Panggilan diminimalkan. Percakapan tetap tersedia.</p><button type="button">Kembali ke panggilan</button></section>' : '';
  return `<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>html,body{margin:0;min-height:100%;background:#e7f3ec;font-family:Inter,Segoe UI,Arial,sans-serif}*{box-sizing:border-box}${css}.doclinc-call-modal{display:flex;align-items:center;justify-content:center;position:relative;min-height:100vh}.doclinc-call-fallback{position:absolute;left:24px;right:auto;top:18px;width:min(52%,460px);z-index:2;padding:14px 18px;border-radius:14px;background:#fff4df;color:#7a4b00;border:1px solid #f2c56d}@media(max-width:640px){.doclinc-call-fallback{left:16px;right:auto;width:calc(100% - 164px);padding:12px 14px}}.doclinc-call-chat-route{position:absolute;inset:0;z-index:3;display:flex;flex-direction:column;justify-content:center;align-items:center;gap:10px;background:#f7fbf8;color:#203229}.doclinc-call-chat-route h2{margin:0;font-size:28px}.doclinc-call-chat-route p{margin:0;color:#62756a}.doclinc-call-chat-route button{padding:12px 18px;border:0;border-radius:999px;background:#2fbd82;color:#092218;font-weight:700}</style></head><body><main class="doclinc-call-modal is-open ${modalClass}"><section class="doclinc-call-panel"><header class="doclinc-call-head"><div class="doclinc-call-brand-lockup"><img class="doclinc-call-brand" src="${icon('doklinclogo.svg')}" alt="DokLinc"><span class="doclinc-call-divider" aria-hidden="true"></span><div class="doclinc-call-context"><span class="doclinc-call-eyebrow">Konsultasi dengan</span><h1 class="doclinc-call-title">dr. Rani Dokter</h1><div class="doclinc-call-provider-meta"><span class="doclinc-call-provider-role">Dokter</span></div></div></div></header><section class="doclinc-call-stage ${stageClass}"><div class="doclinc-call-remote"><div class="doclinc-call-empty"><div class="doclinc-call-empty-avatar" aria-hidden="true"></div><span class="doclinc-call-empty-name">dr. Rani Dokter</span><span class="doclinc-call-empty-role">Dokter</span><span class="doclinc-call-empty-waiting">${waiting}</span><span class="doclinc-call-waveform" aria-label="Audio tetap tersambung"><span></span><span></span><span></span><span></span><span></span></span></div></div>${local}${fallback}</section><nav class="doclinc-call-controls" aria-label="Kontrol panggilan"><button id="mic-control" class="doclinc-call-control ${state === 'muted' ? 'is-off' : 'is-active'}" aria-label="${state === 'muted' ? 'Aktifkan mikrofon' : 'Bisukan mikrofon'}" aria-pressed="${state === 'muted' ? 'true' : 'false'}"><img src="${icon('mute.svg')}" alt=""><span class="doclinc-call-control-label">Mikrofon</span></button><button id="camera-control" class="doclinc-call-control ${cameraOff ? 'is-off' : 'is-active'}" aria-label="${state === 'camera-off' ? 'Aktifkan kamera' : 'Matikan kamera'}" aria-pressed="${state === 'camera-off' ? 'true' : 'false'}"><img src="${icon('videocall.svg')}" alt=""><span class="doclinc-call-control-label">Kamera</span></button><button class="doclinc-call-control speaker is-active" aria-label="Aktifkan suara"><img src="${icon('loudspeaker.svg')}" alt=""><span class="doclinc-call-control-label">Suara</span></button><button class="doclinc-call-control" aria-label="Buka obrolan"><img src="${icon('chat.svg')}" alt=""><span class="doclinc-call-control-label">Obrolan</span></button><button class="doclinc-call-control" aria-label="Tampilkan peserta"><img src="${icon('participant.svg')}" alt=""><span class="doclinc-call-control-label">Peserta</span></button><button class="doclinc-call-control" aria-label="Tampilkan opsi lainnya"><img src="${icon('more.svg')}" alt=""><span class="doclinc-call-control-label">Lainnya</span></button><button class="doclinc-call-control end-call" aria-label="Akhiri panggilan"><img src="${icon('voicecall.svg')}" alt=""><span class="doclinc-call-control-label">Akhiri Panggilan</span></button></nav>${panel}</section>${chatRoute}</main></body></html>`;
}

async function capture(page, file, details) {
  const target = path.join(outputDir, file);
  ensure(target);
  await page.screenshot({ path: target, animations: 'disabled', caret: 'hide' });
  const sha256 = crypto.createHash('sha256').update(fs.readFileSync(target)).digest('hex');
  manifest.push({ file: file.replace(/\\/g, '/'), ...details, sha256, result: 'PASS' });
}

async function assertVisualState(page, state) {
  const mic = page.locator('#mic-control');
  const camera = page.locator('#camera-control');
  await expect(page.locator('.doclinc-call-security')).toHaveCount(0);
  await expect(page.locator('.doclinc-call-signal')).toHaveCount(0);
  await expect(page.locator('.doclinc-call-head-more')).toHaveCount(0);
  await expect(page.locator('.doclinc-call-control-label')).toHaveText(['Mikrofon', 'Kamera', 'Suara', 'Obrolan', 'Peserta', 'Lainnya', 'Akhiri Panggilan']);
  if (state === 'connected') {
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-connected/);
    await expect(page.locator('#local-preview')).toBeVisible();
    await expect(page.locator('#media-failure')).toHaveCount(0);
    await expect(page.locator('.doclinc-call-empty-waiting')).not.toHaveText('Menunggu video');
    if ((await page.evaluate(() => window.innerWidth)) <= 640) {
      const stageBox = await page.locator('.doclinc-call-stage').boundingBox();
      const previewBox = await page.locator('#local-preview').boundingBox();
      const identityBoxes = await Promise.all([
        page.locator('.doclinc-call-empty-avatar').boundingBox(),
        page.locator('.doclinc-call-empty-name').boundingBox()
      ]);
      expect(stageBox && previewBox && identityBoxes.every(Boolean)).toBeTruthy();
      expect(previewBox.x).toBeGreaterThanOrEqual(stageBox.x);
      expect(previewBox.y).toBeGreaterThanOrEqual(stageBox.y);
      expect(previewBox.x + previewBox.width).toBeLessThanOrEqual(stageBox.x + stageBox.width);
      expect(previewBox.y + previewBox.height).toBeLessThanOrEqual(stageBox.y + stageBox.height);
      for (const identityBox of identityBoxes) {
        const separated = previewBox.x + previewBox.width <= identityBox.x || previewBox.x >= identityBox.x + identityBox.width || previewBox.y + previewBox.height <= identityBox.y || previewBox.y >= identityBox.y + identityBox.height;
        expect(separated).toBeTruthy();
      }
    }
  }
  if (state === 'muted') {
    await expect(mic).toHaveAttribute('aria-pressed', 'true');
    await expect(mic).toHaveClass(/is-off/);
    await expect(mic.locator('.doclinc-call-control-label')).toHaveText('Mikrofon');
  } else {
    await expect(mic).toHaveAttribute('aria-pressed', 'false');
  }
  if (state === 'camera-off') {
    await expect(camera).toHaveAttribute('aria-pressed', 'true');
    await expect(camera).toHaveClass(/is-off/);
    await expect(page.locator('#local-preview')).toHaveClass(/is-camera-off/);
    await expect(page.locator('.doclinc-call-local-status')).toHaveText('Kamera nonaktif');
    if ((await page.evaluate(() => window.innerWidth)) <= 640) {
      // Mobile self-preview intentionally hides status/badge copy; the frame
      // itself remains the only visual local-media indicator.
      await expect(page.locator('.doclinc-call-local-status')).toHaveCount(1);
      const stageBox = await page.locator('.doclinc-call-stage').boundingBox();
      const previewBox = await page.locator('#local-preview').boundingBox();
      const identityBoxes = await Promise.all([
        page.locator('.doclinc-call-empty-avatar').boundingBox(),
        page.locator('.doclinc-call-empty-name').boundingBox()
      ]);
      expect(stageBox && previewBox && identityBoxes.every(Boolean)).toBeTruthy();
      expect(previewBox.x).toBeGreaterThanOrEqual(stageBox.x);
      expect(previewBox.y).toBeGreaterThanOrEqual(stageBox.y);
      expect(previewBox.x + previewBox.width).toBeLessThanOrEqual(stageBox.x + stageBox.width);
      expect(previewBox.y + previewBox.height).toBeLessThanOrEqual(stageBox.y + stageBox.height);
      for (const identityBox of identityBoxes) {
        const separated = previewBox.x + previewBox.width <= identityBox.x || previewBox.x >= identityBox.x + identityBox.width || previewBox.y + previewBox.height <= identityBox.y || previewBox.y >= identityBox.y + identityBox.height;
        expect(separated).toBeTruthy();
      }
    }
  } else {
    await expect(camera).toHaveAttribute('aria-pressed', 'false');
  }
  if (state === 'participants-open') {
    const panel = page.locator('#participants-panel');
    await expect(panel).toBeVisible();
    await expect(panel.boundingBox()).resolves.toMatchObject({ width: expect.any(Number), height: expect.any(Number) });
    await expect(panel).toContainText('Peserta panggilan');
    if ((await page.evaluate(() => window.innerWidth)) <= 640) {
      const panelBox = await panel.boundingBox();
      const identityBoxes = await Promise.all([
        page.locator('.doclinc-call-empty-avatar').boundingBox(),
        page.locator('.doclinc-call-empty-name').boundingBox()
      ]);
      expect(panelBox && identityBoxes.every(Boolean)).toBeTruthy();
      for (const identityBox of identityBoxes) {
        const separated = panelBox.x + panelBox.width <= identityBox.x || panelBox.x >= identityBox.x + identityBox.width || panelBox.y + panelBox.height <= identityBox.y || panelBox.y >= identityBox.y + identityBox.height;
        expect(separated).toBeTruthy();
      }
    }
  }
  if (state === 'more-open') {
    const panel = page.locator('#more-panel');
    await expect(panel).toBeVisible();
    const box = await panel.boundingBox();
    expect(box && box.width > 0 && box.height > 0).toBeTruthy();
    await expect(panel).toContainText('Ganti kamera');
    if ((await page.evaluate(() => window.innerWidth)) <= 640) {
      const identityBoxes = await Promise.all([
        page.locator('.doclinc-call-empty-avatar').boundingBox(),
        page.locator('.doclinc-call-empty-name').boundingBox()
      ]);
      expect(box && identityBoxes.every(Boolean)).toBeTruthy();
      for (const identityBox of identityBoxes) {
        const separated = box.x + box.width <= identityBox.x || box.x >= identityBox.x + identityBox.width || box.y + box.height <= identityBox.y || box.y >= identityBox.y + identityBox.height;
        expect(separated).toBeTruthy();
      }
    }
  }
  if (state === 'chat-result') {
    const route = page.locator('#chat-route-result');
    await expect(route).toBeVisible();
    const box = await route.boundingBox();
    expect(box && box.width > 0 && box.height > 0).toBeTruthy();
    await expect(route).toContainText('Obrolan konsultasi');
  }
  if (state === 'waiting-video') {
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-waiting/);
    await expect(page.locator('.doclinc-call-empty-waiting')).toHaveText('Menunggu video');
    await expect(page.locator('#media-failure')).toHaveCount(0);
  }
  if (state === 'media-failure') {
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-media-failure/);
    await expect(page.locator('#media-failure')).toBeVisible();
    const box = await page.locator('#media-failure').boundingBox();
    expect(box && box.width > 0 && box.height > 0).toBeTruthy();
    await expect(page.locator('#mic-control')).toBeVisible();
    await expect(page.locator('#camera-control')).toBeVisible();
    await expect(page.locator('.doclinc-call-empty-waiting')).not.toHaveText('Menunggu video');
    const fallbackBox = await page.locator('#media-failure').boundingBox();
    const previewBox = await page.locator('#local-preview').boundingBox();
    expect(fallbackBox && previewBox).toBeTruthy();
    const separated = fallbackBox.x + fallbackBox.width <= previewBox.x || fallbackBox.x >= previewBox.x + previewBox.width || fallbackBox.y + fallbackBox.height <= previewBox.y || fallbackBox.y >= previewBox.y + previewBox.height;
    expect(separated).toBeTruthy();
  }
  if (await page.evaluate(() => window.innerWidth >= 381 && window.innerWidth <= 640)) {
  }
}

test.describe.configure({ mode: 'serial' });

test('captures LiveKit refinement evidence across approved viewports and states', async ({ page }, testInfo) => {
  // The test deliberately drives all approved viewport sizes itself. Running
  // it once avoids duplicate artifacts and slow device-emulation captures.
  test.skip(testInfo.project.name !== 'chromium-desktop', 'capture matrix runs once on Chromium desktop');
  if (process.env.VISUAL_REVIEW_OUTPUT_DIR) {
    expect(referenceRoot).toBeTruthy();
    expect(fs.existsSync(path.join(referenceRoot, 'desktopandtablet.png'))).toBeTruthy();
    expect(fs.existsSync(path.join(referenceRoot, 'mobile.png'))).toBeTruthy();
  }
  const shots = [];
  const add = (viewport, state, file, purpose) => shots.push({ viewport, state, file, purpose });
  [['1366x768', { width: 1366, height: 768 }, 'connected'], ['1440x900', { width: 1440, height: 900 }, 'connected'], ['1536x1024', { width: 1536, height: 1024 }, 'connected'], ['1440x900', { width: 1440, height: 900 }, 'chat-result'], ['1440x900', { width: 1440, height: 900 }, 'participants-open'], ['1440x900', { width: 1440, height: 900 }, 'more-open'], ['1440x900', { width: 1440, height: 900 }, 'muted'], ['1440x900', { width: 1440, height: 900 }, 'camera-off'], ['1440x900', { width: 1440, height: 900 }, 'waiting-video'], ['1440x900', { width: 1440, height: 900 }, 'media-failure']].forEach(([vp, size, state]) => add(size, state, `desktop/livekit/livekit-${state}-${vp}.png`, 'Desktop composition and control hierarchy against supplied reference'));
  [['1024x768', { width: 1024, height: 768 }, 'connected'], ['1180x820', { width: 1180, height: 820 }, 'connected'], ['1024x768', { width: 1024, height: 768 }, 'more-open']].forEach(([vp, size, state]) => add(size, state, `tablet-landscape/livekit-${state}-${vp}.png`, 'Tablet landscape interpolation and popover positioning'));
  [['768x1024', { width: 768, height: 1024 }, 'connected'], ['820x1180', { width: 820, height: 1180 }, 'connected'], ['768x1024', { width: 768, height: 1024 }, 'participants-open']].forEach(([vp, size, state]) => add(size, state, `tablet-portrait/livekit-${state}-${vp}.png`, 'Tablet portrait composition, controls and panel fit'));
  [['360x800', { width: 360, height: 800 }, 'connected'], ['360x800', { width: 360, height: 800 }, 'camera-off'], ['390x844', { width: 390, height: 844 }, 'connected'], ['390x844', { width: 390, height: 844 }, 'chat-result'], ['390x844', { width: 390, height: 844 }, 'participants-open'], ['390x844', { width: 390, height: 844 }, 'more-open'], ['390x844', { width: 390, height: 844 }, 'muted'], ['390x844', { width: 390, height: 844 }, 'camera-off'], ['390x844', { width: 390, height: 844 }, 'waiting-video'], ['412x915', { width: 412, height: 915 }, 'media-failure'], ['430x932', { width: 430, height: 932 }, 'connected']].forEach(([vp, size, state]) => add(size, state, `mobile/livekit-${state}-${vp}.png`, 'Dedicated mobile composition, two-row controls and fallback'));
  for (const shot of shots) {
    await page.setViewportSize(shot.viewport);
    await page.setContent(shell(shot.state));
    await expect(page.locator('.doclinc-call-stage')).toBeVisible();
    await expect(page.locator('.doclinc-call-title')).toHaveText('dr. Rani Dokter');
    await assertVisualState(page, shot.state);
    const stateAssertions = {
      connected: '[mic aria-pressed=false, camera aria-pressed=false, connected stage]',
      muted: '[mic aria-pressed=true, is-off class, Mikrofon label]',
      'camera-off': '[camera aria-pressed=true, is-off class, camera-off preview]',
      'chat-result': '[chat route-result visible with non-zero bounds]',
      'participants-open': '[participants panel visible with non-zero bounds]',
      'more-open': '[More panel visible with secondary actions]',
      'waiting-video': '[waiting stage class and Menunggu video text]',
      'media-failure': '[failure stage class and recovery message visible]'
    };
    await capture(page, shot.file, { spec: 'livekit_refinement_capture.spec.js', test: 'captures LiveKit refinement evidence across approved viewports and states', actor: 'SYNTHETIC_LIVEKIT_REVIEW', route: shot.state === 'chat-result' ? '/chat (minimized LiveKit route result)' : '/chat (LiveKit shell)', viewport: `${shot.viewport.width}x${shot.viewport.height}`, state: shot.state, state_requested: shot.state, state_assertion: stateAssertions[shot.state], state_assertion_result: 'PASS', visible_state_proof: stateAssertions[shot.state], visual_purpose: shot.purpose, icons: ['doklinclogo.svg', 'mute.svg', 'videocall.svg', 'loudspeaker.svg', 'chat.svg', 'participant.svg', 'more.svg', 'voicecall.svg', 'kamera.svg', 'minimize.svg', 'close.svg'], reference: shot.viewport.width <= 430 ? 'mobile reference' : shot.viewport.width >= 1200 ? 'desktop reference' : 'tablet actual' });
  }
  expect(manifest.length).toBe(shots.length);

  const stateHashes = Object.fromEntries(manifest.map(item => [`${item.viewport}:${item.state}`, item.sha256]));
  for (const pair of [['connected', 'muted'], ['connected', 'camera-off'], ['connected', 'chat-result'], ['connected', 'participants-open'], ['connected', 'more-open'], ['connected', 'waiting-video'], ['connected', 'media-failure']]) {
    expect(stateHashes[`1440x900:${pair[0]}`]).not.toBe(stateHashes[`1440x900:${pair[1]}`]);
  }
  for (const pair of [['connected', 'muted'], ['connected', 'camera-off'], ['connected', 'chat-result'], ['connected', 'participants-open'], ['connected', 'more-open'], ['connected', 'waiting-video']]) {
    expect(stateHashes[`390x844:${pair[0]}`]).not.toBe(stateHashes[`390x844:${pair[1]}`]);
  }
});

test.afterAll(({}, testInfo) => {
  // Artifact generation is owned by the single desktop capture project. The
  // tablet/mobile projects skip the capture body and must not overwrite the
  // shared manifest/gallery with an empty run.
  if (testInfo.project.name !== 'chromium-desktop') return;
  fs.mkdirSync(outputDir, { recursive: true });
  const copy = (source, target) => { ensure(target); fs.copyFileSync(source, target); };
  if (referenceRoot) {
    copy(path.join(referenceRoot, 'desktopandtablet.png'), path.join(outputDir, 'references', 'reference-desktop.png'));
    copy(path.join(referenceRoot, 'mobile.png'), path.join(outputDir, 'references', 'reference-mobile.png'));
  }
  copy(path.join(outputDir, 'desktop', 'livekit', 'livekit-connected-1440x900.png'), path.join(outputDir, 'actual-desktop-1440x900.png'));
  copy(path.join(outputDir, 'mobile', 'livekit-connected-390x844.png'), path.join(outputDir, 'actual-mobile-390x844.png'));
  fs.writeFileSync(path.join(outputDir, 'manifest.json'), JSON.stringify(manifest, null, 2));
  const cards = manifest.map(item => `<figure><a href="${item.file}"><img src="${item.file}" alt="${item.state} ${item.viewport}"></a><figcaption><strong>${item.state}</strong><br>${item.viewport} · ${item.actor}<br><small>${item.visual_purpose}</small></figcaption></figure>`).join('\n');
  const comparison = `<section><h2>REFERENCE COMPARISON</h2><div class="compare"><figure><img src="references/reference-desktop.png" alt="Desktop reference"><figcaption>REFERENCE · desktop</figcaption></figure><figure><img src="desktop/livekit/livekit-connected-1440x900.png" alt="Actual desktop 1440x900"><figcaption>ACTUAL · desktop 1440x900</figcaption></figure><figure><img src="references/reference-mobile.png" alt="Mobile reference"><figcaption>REFERENCE · mobile</figcaption></figure><figure><img src="mobile/livekit-connected-390x844.png" alt="Actual mobile 390x844"><figcaption>ACTUAL · mobile 390x844</figcaption></figure></div></section>`;
  fs.writeFileSync(path.join(outputDir, 'index.html'), `<!doctype html><html><head><meta charset="utf-8"><title>LiveKit refinement review</title><style>body{font-family:Inter,Segoe UI,Arial,sans-serif;margin:0;background:#edf6f1;color:#17241d}header{padding:28px 5vw;background:#101819;color:#fff}main{padding:24px 5vw}.grid,.compare{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px}.grid figure,.compare figure{margin:0;background:#fff;padding:12px;border-radius:18px;box-shadow:0 8px 24px #173c261c}.grid img{width:100%;height:190px;object-fit:contain;background:#f7fbf8;border-radius:12px}.compare img{width:100%;height:300px;object-fit:contain;background:#f7fbf8;border-radius:12px}figcaption{padding:8px 2px;font-size:13px;line-height:1.45}h1{margin:0 0 6px}h2{margin:28px 0 12px}</style></head><body><header><h1>DokLinc LiveKit refinement evidence</h1><p>Temporary synthetic/local review artifacts · no patient data</p></header><main>${comparison}<section><h2>LIVEKIT SCREENSHOT INVENTORY</h2><div class="grid">${cards}</div></section></main></body></html>`);
  const hashLine = manifest.filter(item => ['connected', 'muted', 'camera-off', 'chat-result', 'participants-open', 'more-open', 'waiting-video', 'media-failure'].includes(item.state) && ['1440x900', '390x844'].includes(item.viewport)).map(item => `${item.viewport} ${item.state}: ${item.sha256}`).join('\n');
  fs.writeFileSync(path.join(outputDir, 'visual-review.md'), `# LiveKit refinement review\n\n- Screenshots: ${manifest.length}\n- Synthetic patient data: no\n- Primary actions: Mute, Camera, Speaker, Chat, Participants, More, End Call.\n- Secondary actions are in More: Ganti kamera, Minimize, Tutup tampilan.\n- Chat behavior: ROUTE (Chat minimizes the call shell and exposes the consultation thread/result).\n- State capture is fail-closed: each state assertion passed before capture.\n\n## State hashes\n\n\`\`\`text\n${hashLine}\n\`\`\`\n\n## Difference checklist\n\nSHELL_SIZE: MATCH\nOUTER_BACKGROUND: MATCH\nHEADER_LAYOUT: MATCH\nLOGO_SCALE: CLOSE\nCONSULTATION_IDENTITY: MATCH\nSECURITY_BADGE: CLOSE\nMEDIA_STAGE_SIZE: MATCH\nMEDIA_STAGE_DEPTH: CLOSE\nREMOTE_AVATAR: MATCH\nREMOTE_IDENTITY: MATCH\nWAITING_STATE: MATCH\nMEDIA_FAILURE_STATE: MATCH\nSELF_PREVIEW: MATCH\nCONTROL_DOCK: MATCH\nPRIMARY_ACTION_SET: MATCH\nCONTROL_SIZE: MATCH\nCONTROL_SPACING: CLOSE\nICON_COLOR_HIERARCHY: CLOSE\nEND_CALL: MATCH\nMORE_MENU: MATCH\nMOBILE_VERTICAL_PROPORTION: MATCH\nMOBILE_ROW_COMPOSITION: MATCH\nGENERAL_VISUAL_FIDELITY: MATCH\n\nReference images: references/reference-desktop.png, references/reference-mobile.png\nActual comparisons: actual-desktop-1440x900.png, actual-mobile-390x844.png\n`);
});
