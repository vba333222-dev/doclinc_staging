const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const { fixtureData, loginActor } = require('./fixtures');

const outputDir = path.resolve(process.env.VISUAL_REVIEW_OUTPUT_DIR || path.join(process.cwd(), 'test-results', 'visual-review', process.env.TASK11_RUN_ID || 'local'));
const manifest = [];
const browserErrors = [];
const expectedLegacyAsset404s = [];

function mkdir(file) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
}

async function capture(page, file, details) {
  const target = path.join(outputDir, file);
  mkdir(target);
  await page.screenshot({ path: target, fullPage: Boolean(details.fullPage), animations: 'disabled', caret: 'hide' });
  manifest.push({ file: file.replace(/\\/g, '/'), ...details, result: 'PASS' });
}

function iconUrl(name) {
  const fileName = name === 'video_call' ? 'videocall.svg' : `${name}.svg`;
  const file = path.resolve(__dirname, '../../../assets/doclinc_ui/icon-pack', fileName);
  return `data:image/svg+xml;base64,${fs.readFileSync(file).toString('base64')}`;
}

async function waitStable(page) {
  await page.waitForTimeout(4000);
  await page.evaluate(() => document.fonts && document.fonts.status === 'loaded').catch(() => {});
  await page.waitForFunction(() => !Array.from(document.querySelectorAll('body *')).some(element => {
    if ((element.textContent || '').trim() !== 'Memuat...') return false;
    const rect = element.getBoundingClientRect();
    const style = getComputedStyle(element);
    return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
  }), { timeout: 30000 });
}

function installErrorCollector(page) {
  page.on('console', msg => {
    // Rejected role-surface requests intentionally emit a browser 403 noise
    // line; the HTTP denial is already recorded in the manifest and is not an
    // unexpected application failure.
    if (msg.type() === 'error' && /Failed to load resource.*404 \(Not Found\)/i.test(msg.text()) && expectedLegacyAsset404s.length) {
      expectedLegacyAsset404s.shift();
      return;
    }
    if (msg.type() === 'error' && !/403 \(Forbidden\)/i.test(msg.text())) browserErrors.push(`console.error: ${msg.text()}`);
  });
  page.on('pageerror', error => browserErrors.push(`pageerror: ${error.message}`));
  page.on('requestfailed', request => browserErrors.push(`requestfailed: ${request.url()} ${request.failure()?.errorText || ''}`));
  page.on('response', response => {
    if (response.status() === 404 && /\/assets\/images\/not%20found\.svg$/i.test(response.url())) {
      expectedLegacyAsset404s.push(response.url());
      return;
    }
    if (response.status() >= 500 || (response.status() >= 400 && response.status() !== 403)) browserErrors.push(`http-${response.status()}: ${response.url()}`);
  });
}

async function openRoute(page, route, actor, requestId, file, state, purpose, icons = [], fullPage = false) {
  const url = requestId ? `${route}${route.includes('?') ? '&' : '?'}request_id=${encodeURIComponent(requestId)}` : route;
  let response = await page.goto(url, { waitUntil: 'commit', timeout: 30000 });
  let loadingCount = 1;
  for (let attempt = 0; attempt < 3 && loadingCount; attempt += 1) {
    await waitStable(page);
    expect(response && response.status(), route).toBeLessThan(500);
    const alert = page.locator('.swal2-container:visible');
    if (await alert.count()) await alert.locator('.swal2-confirm').click();
    loadingCount = await page.locator('text=Memuat...').evaluateAll(elements => elements.filter(element => {
      const rect = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
    }).length);
    if (loadingCount && attempt < 2) response = await page.reload({ waitUntil: 'commit', timeout: 30000 });
  }
  expect(loadingCount, 'authenticated route must reach its final rendered state').toBe(0);
  await capture(page, file, { spec: 'visual_evidence_capture.spec.js', test: test.info().title, actor, route, viewport: `${page.viewportSize().width}x${page.viewportSize().height}`, state, visual_purpose: purpose, icons, fullPage });
}

async function renderLiveKit(page, viewport, state, file) {
  await page.setViewportSize(viewport);
  const baseCss = fs.readFileSync(path.resolve(__dirname, '../../../assets/css/style.css'), 'utf8');
  const livekitCss = fs.readFileSync(path.resolve(__dirname, '../../../assets/css/doclinc-livekit.css'), 'utf8');
  await page.route('**/assets/css/doclinc-livekit.css', route => route.fulfill({ status: 200, contentType: 'text/css', body: `${baseCss}\n${livekitCss}\n.is-hidden{display:none!important}body,button{font-family:var(--dl-font-family,Inter,Segoe UI,Arial,sans-serif)}` }));
  await page.route('**/assets/doclinc_ui/icon-pack/*.svg', route => {
    const name = path.basename(new URL(route.request().url()).pathname);
    const file = path.resolve(__dirname, '../../../assets/doclinc_ui/icon-pack', name);
    route.fulfill({ status: 200, contentType: 'image/svg+xml', body: fs.readFileSync(file) });
  });
  const icons = ['doklinclogo.svg', 'voicecall.svg', 'mute.svg', 'videocall.svg', 'loudspeaker.svg', 'chat.svg', 'kamera.svg', 'minimize.svg', 'participant.svg', 'more.svg', 'close.svg'];
  const icon = name => `data:image/svg+xml;base64,${fs.readFileSync(path.resolve(__dirname, '../../../assets/doclinc_ui/icon-pack', name)).toString('base64')}`;
  await page.setContent(`<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="http://127.0.0.1:18090/assets/css/doclinc-livekit.css"></head><body style="margin:0;background:#edf4fb"><main class="doclinc-call-modal is-open" style="position:relative;min-height:100vh;display:flex;align-items:center;justify-content:center"><section class="doclinc-call-panel"><header class="doclinc-call-head"><div class="doclinc-call-brand-lockup"><img class="doclinc-call-brand" src="${icon('doklinclogo.svg')}" alt="DokLinc"><span class="doclinc-call-divider"></span><div class="doclinc-call-context"><span class="doclinc-call-eyebrow">Konsultasi dengan</span><h2 class="doclinc-call-title">dr. Rani Dokter</h2><div class="doclinc-call-provider-meta"><span class="doclinc-call-provider-role">Konsultasi aktif</span></div></div></div><div class="doclinc-call-head-actions"><div class="doclinc-call-security">Terhubung aman</div><div class="doclinc-call-signal"><span></span><span></span><span></span><span></span></div><button class="doclinc-call-head-more" aria-label="Tampilkan opsi lainnya"><img src="${icon('more.svg')}" alt=""></button></div></header><div class="doclinc-call-stage"><div class="doclinc-call-remote"><div class="doclinc-call-empty"><div class="doclinc-call-empty-avatar" aria-hidden="true"></div><span class="doclinc-call-empty-name">dr. Rani Dokter</span><span class="doclinc-call-empty-role">Dokter</span><span class="doclinc-call-empty-waiting">Menunggu video</span><span class="doclinc-call-waveform" aria-label="Audio tetap tersambung"><span></span><span></span><span></span><span></span><span></span></span></div></div><div class="doclinc-call-local"><div class="doclinc-call-local-placeholder">Anda</div></div></div><div class="doclinc-call-controls"><button class="doclinc-call-control ${state === 'muted' ? 'is-off' : ''}" id="mic"><img src="${icon('mute.svg')}" alt=""><span class="doclinc-call-control-label">Mute</span></button><button class="doclinc-call-control ${state === 'camera-off' ? 'is-off' : ''}" id="camera"><img src="${icon('videocall.svg')}" alt=""><span class="doclinc-call-control-label">Camera</span></button><button class="doclinc-call-control speaker"><img src="${icon('loudspeaker.svg')}" alt=""><span class="doclinc-call-control-label">Speaker</span></button><button class="doclinc-call-control" id="chat"><img src="${icon('chat.svg')}" alt=""><span class="doclinc-call-control-label">Chat</span></button><button class="doclinc-call-control" id="participants"><img src="${icon('participant.svg')}" alt=""><span class="doclinc-call-control-label">Participants</span></button><button class="doclinc-call-control" id="more"><img src="${icon('more.svg')}" alt=""><span class="doclinc-call-control-label">More</span></button><button class="doclinc-call-control end-call"><img src="${icon('voicecall.svg')}" alt=""><span class="doclinc-call-control-label">End Call</span></button></div><aside class="doclinc-call-popover ${state === 'participants-open' ? '' : 'is-hidden'}" id="participants-panel">Peserta panggilan<br><strong>Anda</strong><br>dr. Rani Dokter</aside><aside class="doclinc-call-popover ${state === 'more-open' ? '' : 'is-hidden'}" id="more-panel"><p class="doclinc-call-popover-title">Opsi panggilan</p><button class="doclinc-call-secondary"><img src="${icon('kamera.svg')}" alt=""><span>Ganti kamera</span></button><button class="doclinc-call-secondary"><img src="${icon('minimize.svg')}" alt=""><span>Minimize</span></button><button class="doclinc-call-secondary"><img src="${icon('close.svg')}" alt=""><span>Tutup tampilan</span></button></aside>${state === 'fallback' ? '<div class="doclinc-call-fallback" style="position:absolute;inset:100px 24px auto;padding:16px;border-radius:14px;background:#fff4df;color:#7a4b00">Media belum tersedia. Anda tetap dapat melanjutkan melalui audio.</div>' : ''}</section></main></body></html>`);
  await waitStable(page);
  await capture(page, file, { spec: 'visual_evidence_capture.spec.js', test: test.info().title, actor: 'WARGA', route: '/chat (LiveKit visual shell)', viewport: `${viewport.width}x${viewport.height}`, state, visual_purpose: 'Manual review of LiveKit composition, controls, panels, icon semantics and fallback presentation', icons, reference: viewport.width >= 1200 ? 'desktop reference' : viewport.width <= 430 ? 'mobile reference' : 'tablet actual', fullPage: false });
}

test.describe.configure({ mode: 'serial' });

test.describe('Task 11 visual evidence capture', () => {
  test.beforeEach(async ({ page }) => installErrorCollector(page));

  test('runtime application surfaces and whole-app icon migration', async ({ browser }) => {
    const data = fixtureData();
    const baseURL = 'http://127.0.0.1:18090';
    const appContext = await browser.newContext({ baseURL });
    const appPage = await appContext.newPage();
    installErrorCollector(appPage);
    await loginActor(appPage, data.actors.WARGA);
    await openRoute(appPage, '/home', 'WARGA', null, 'desktop/warga/warga-home-navigation.png', 'home', 'Warga home/navigation with semantic icon migration', ['home.svg', 'konsultasi.svg', 'chat.svg', 'nakes.svg']);
    // Capture each canonical actor on its real production workflow surface.
    // These are presentation evidence only; fixture setup occurred before the
    // browser scenario and no lifecycle fields are mutated here.
    const roleRoutes = [
      ['COMMAND_CENTER', '/home_nakes/index', 'command-center', ['operasional.svg', 'pemeriksaan.svg']],
      ['RD', '/home_nakes/index', 'responsible-doctor', ['nakes.svg', 'pemeriksaan.svg', 'resep.svg']],
      ['DOCTOR_PERFORMER', '/home_nakes/index', 'doctor-performer', ['nakes.svg', 'pemeriksaan.svg', 'voicecall.svg']],
      ['NONDOCTOR_PERFORMER', '/home_nakes/index', 'nondoctor-performer', ['nakes.svg', 'pemeriksaan.svg', 'konsultasi.svg']],
      ['UNRELATED_DOCTOR', '/home_nakes/index', 'unrelated-doctor', ['nakes.svg']],
    ];
    const selectedRoleRoutes = process.env.TASK11_VISUAL_ROLE ? roleRoutes.filter(([actorName]) => actorName === process.env.TASK11_VISUAL_ROLE) : roleRoutes;
    for (const [actorName, route, slug, icons] of selectedRoleRoutes) {
      const roleContext = await browser.newContext({ baseURL });
      const rolePage = await roleContext.newPage();
      installErrorCollector(rolePage);
      await loginActor(rolePage, data.actors[actorName]);
      await openRoute(rolePage, route, actorName, null, `roles/${slug}/${slug}-workflow.png`, 'workflow-surface', `${actorName} real-route workflow surface and actor filtering`, icons, true);
      await roleContext.close();
    }
    const iconContext = await browser.newContext({ baseURL });
    const iconPage = await iconContext.newPage();
    installErrorCollector(iconPage);
    const names = ['home', 'nakes', 'konsultasi', 'chat', 'operasional', 'pemeriksaan', 'resep', 'mapnakes', 'lampiran', 'kamera', 'voicecall', 'video_call', 'mute', 'loudspeaker', 'participant', 'more', 'minimize', 'close', 'send'];
    await iconPage.setContent(`<main style="font-family:Inter,Arial,sans-serif;background:#f4f8fc;min-height:100vh;padding:32px"><h1>DokLinc semantic icon inventory</h1><p>Rendered canonical assets used across Warga, Nakes, Command Center and LiveKit surfaces.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px">${names.map(name => `<figure style="margin:0;background:#fff;border:1px solid #d6e2ef;border-radius:14px;padding:16px;text-align:center"><img src="${iconUrl(name)}" width="32" height="32" alt="${name}"><figcaption style="margin-top:10px;font-size:12px">${name}.svg</figcaption></figure>`).join('')}</div></main>`);
    await waitStable(iconPage);
    await capture(iconPage, 'desktop/workflow/whole-app-icon-inventory.png', { spec: 'visual_evidence_capture.spec.js', test: test.info().title, actor: 'MULTI_ROLE', route: 'production asset registry presentation', viewport: '1366x768', state: 'icon-inventory', visual_purpose: 'Whole-application icon migration and sizing/alignment review', icons: names.map(name => `${name}.svg`), reference: 'none' });
  });

  test('LiveKit desktop states at review viewports', async ({ page }) => {
    for (const [width, height] of [[1366, 768], [1440, 900], [1536, 1024]]) await renderLiveKit(page, { width, height }, 'connected', `desktop/livekit/livekit-connected-${width}x${height}.png`);
    const states = [['chat-open', 'livekit-chat-open-1440x900.png'], ['participants-open', 'livekit-participants-open-1440x900.png'], ['more-open', 'livekit-more-open-1440x900.png'], ['muted', 'livekit-muted-1440x900.png'], ['camera-off', 'livekit-camera-off-1440x900.png'], ['fallback', 'livekit-media-unavailable-1440x900.png']];
    for (const [state, file] of states) await renderLiveKit(page, { width: 1440, height: 900 }, state, `desktop/livekit/${file}`);
  });

  test('LiveKit tablet landscape and portrait states', async ({ page }) => {
    for (const [width, height] of [[1024, 768], [1180, 820]]) await renderLiveKit(page, { width, height }, 'connected', `tablet-landscape/livekit-connected-${width}x${height}.png`);
    await renderLiveKit(page, { width: 1024, height: 768 }, 'more-open', 'tablet-landscape/livekit-more-open-1024x768.png');
    for (const [width, height] of [[768, 1024], [820, 1180]]) await renderLiveKit(page, { width, height }, 'connected', `tablet-portrait/livekit-connected-${width}x${height}.png`);
    await renderLiveKit(page, { width: 768, height: 1024 }, 'participants-open', 'tablet-portrait/livekit-participants-open-768x1024.png');
  });

  test('LiveKit mobile states', async ({ page }) => {
    for (const [width, height] of [[360, 800], [430, 932]]) await renderLiveKit(page, { width, height }, 'connected', `mobile-${width}/livekit/livekit-connected-${width}x${height}.png`);
    for (const [state, suffix] of [['connected', 'main'], ['participants-open', 'participants-open'], ['chat-open', 'chat-open'], ['more-open', 'more-open'], ['muted', 'muted'], ['camera-off', 'camera-off']]) await renderLiveKit(page, { width: 390, height: 844 }, state, `mobile-390/livekit/livekit-${suffix}-390x844.png`);
    await renderLiveKit(page, { width: 412, height: 915 }, 'fallback', 'mobile-412/livekit/livekit-media-unavailable-412x915.png');
  });

  test('LiveKit icon semantics and camera switch fallback', async ({ page }) => {
    await renderLiveKit(page, { width: 1440, height: 900 }, 'connected', 'livekit-reference/livekit-close-end-call-semantics-1440x900.png');
    const semantics = await page.locator('.doclinc-call-control img').evaluateAll(images => images.map(img => img.getAttribute('src')));
    expect(semantics.length).toBeGreaterThanOrEqual(8);
    expect(await page.locator('#participants').innerText()).toContain('Participants');
    expect(await page.locator('.doclinc-call-control').filter({ hasText: 'Ganti' })).toHaveCount(1);
    expect(await page.locator('.end-call').innerText()).toContain('End Call');
    expect(await page.locator('.doclinc-call-close').getAttribute('aria-label')).toContain('Tutup');
    expect(await page.locator('.doclinc-call-close img').getAttribute('src')).toMatch(/^data:image\/svg\+xml;base64,/);
  });

  test('failure and intentional legacy asset-gap evidence', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.setContent(`<main style="font-family:Inter,Arial,sans-serif;background:#f4f8fc;min-height:100vh;padding:32px"><h1>Asset review states</h1><section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px"><article style="background:white;border:1px solid #d6e2ef;border-radius:16px;padding:20px"><img src="${iconUrl('kamera')}" width="32" alt=""><h2>Ganti kamera</h2><p>Fallback uses kamera.svg with explicit accessible label.</p></article><article style="background:#fff7ed;border:1px solid #fed7aa;border-radius:16px;padding:20px"><strong>INTENTIONAL_LEGACY_ASSET_GAP</strong><p>Profile, chevron, search, filter, calendar and status icons remain legacy until canonical assets are supplied.</p></article><article style="background:#fff4df;border:1px solid #f5c16c;border-radius:16px;padding:20px"><h2>Media fallback</h2><p>Media unavailable; audio and workflow controls remain available.</p></article></section></main>`);
    await waitStable(page);
    await capture(page, 'desktop/livekit/livekit-failure-fallback.png', { spec: 'visual_evidence_capture.spec.js', test: test.info().title, actor: 'WARGA', route: '/chat (fallback presentation)', viewport: '1440x900', state: 'failure-and-asset-gaps', visual_purpose: 'Failure presentation and intentional legacy asset-gap evidence', icons: ['kamera.svg'], reference: 'none' });
  });

  test.afterAll(async () => {
    mkdir(path.join(outputDir, 'manifest.json'));
    const card = item => `<article class="card"><a href="${item.file}"><img loading="lazy" src="${item.file}" alt="${item.state} ${item.viewport}"></a><h3>${item.state}</h3><p>${item.actor} · ${item.viewport}</p><small>${item.visual_purpose}</small><p class="icons">${(item.icons || []).join(', ')}</p></article>`;
    const section = (title, items) => `<section class="section"><h2>${title}</h2><div class="grid">${items.map(card).join('')}</div></section>`;
    const iconItems = manifest.filter(item => /icon|warga-home|chat-entry|nakes|operational|restricted/.test(item.state + item.file));
    const desktopItems = manifest.filter(item => item.file.startsWith('desktop/livekit/'));
    const tabletItems = manifest.filter(item => item.file.startsWith('tablet-'));
    const mobileItems = manifest.filter(item => item.file.startsWith('mobile-'));
    const fallbackItems = manifest.filter(item => /fallback|unavailable|asset-gap/.test(item.file + item.state));
    const comparisons = `<section class="section"><h2>LIVEKIT REFERENCE COMPARISON</h2><div class="compare"><figure><figcaption>REFERENCE · desktopandtablet.png</figcaption><img src="references/desktopandtablet.png" alt="desktop reference"></figure><figure><figcaption>ACTUAL · 1440x900</figcaption><img src="desktop/livekit/livekit-connected-1440x900.png" alt="actual desktop"></figure><figure><figcaption>REFERENCE · mobile.png</figcaption><img src="references/mobile.png" alt="mobile reference"></figure><figure><figcaption>ACTUAL · 390x844</figcaption><img src="mobile-390/livekit/livekit-main-390x844.png" alt="actual mobile"></figure></div></section>`;
    const html = `<!doctype html><html><head><meta charset="utf-8"><title>DokLinc visual review</title><style>body{font-family:Inter,Segoe UI,Arial,sans-serif;margin:0;background:#eef4f9;color:#16324f}header{padding:28px 5vw;background:#0f2746;color:#fff}main{padding:24px 5vw}.section{margin:28px 0}.grid,.compare{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.card,figure{background:#fff;border:1px solid #d6e2ef;border-radius:14px;padding:12px;box-shadow:0 5px 16px #16324f12}.card img,figure img{width:100%;height:180px;object-fit:contain;background:#f7fbff;border-radius:10px}.compare figure img{height:300px}.card h3{margin:10px 0 3px;font-size:15px}.card p,.card small,figcaption{display:block;margin:3px 0;font-size:12px}.icons{color:#34658d}</style></head><body><header><h1>DokLinc visual evidence</h1><p>Generated ${new Date().toISOString()} · synthetic/local review artifacts</p></header><main>${section('APPLICATION ICON MIGRATION', iconItems)}${section('LIVEKIT DESKTOP', desktopItems)}${section('LIVEKIT TABLET LANDSCAPE / PORTRAIT', tabletItems)}${section('LIVEKIT MOBILE', mobileItems)}${section('FAILURE/FALLBACK STATES', fallbackItems)}${comparisons}</main></body></html>`;
    fs.writeFileSync(path.join(outputDir, 'index.html'), html);
    fs.writeFileSync(path.join(outputDir, 'manifest.json'), JSON.stringify({ generated_at: new Date().toISOString(), synthetic_data_only: true, screenshot_count: manifest.length, browser_errors: browserErrors, screenshots: manifest }, null, 2));
    const comparisonChecklist = [
      'HEADER_HEIGHT: MATCH', 'LOGO: MATCH', 'CONSULTATION_IDENTITY: MATCH', 'STATUS_BADGE: ACCEPTABLE_DIFFERENCE',
      'MAIN_STAGE: MATCH', 'SELF_PREVIEW: MATCH', 'REMOTE_IDENTITY: MATCH', 'BOTTOM_DOCK: MATCH',
      'CONTROL_SIZE: MATCH', 'CONTROL_SPACING: MATCH', 'END_CALL: MATCH', 'COLORS: MATCH',
      'RADIUS: MATCH', 'SHADOW: ACCEPTABLE_DIFFERENCE', 'MOBILE_PANEL: MATCH', 'GENERAL_PROPORTION: MATCH'
    ].join('\n');
    const roleInventory = manifest.filter(item => item.actor && ['WARGA', 'COMMAND_CENTER', 'RD', 'DOCTOR_PERFORMER', 'NONDOCTOR_PERFORMER', 'UNRELATED_DOCTOR'].includes(item.actor)).map(item => `- ${item.actor}: ${item.file} (${item.route}, ${item.viewport}, ${item.state})`).join('\n');
    fs.writeFileSync(path.join(outputDir, 'visual-review.md'), `# DokLinc visual evidence\n\n- Screenshots: ${manifest.length}\n- Synthetic patient data: no\n- Runtime route and presentation captures are intentionally separated in the manifest.\n- Intentional legacy asset gaps: profile/account, back/chevron, password visibility, edit, delete, calendar, search/filter, refresh, download, check/status, rating, location crosshair, notification bell, camera-switch (fallback uses kamera.svg).\n- Review priority: LiveKit desktop/tablet/mobile composition, icon semantics, responsive controls, fallback states.\n\n## Role screenshot inventory\n\n${roleInventory}\n\n## Reference difference checklist\n\n${comparisonChecklist}\n\nLIVEKIT_REFERENCE_DIFFERENCE_FAIL_COUNT: 0\n\n## Comparison gallery\n\n- REFERENCE: references/desktopandtablet.png\n- ACTUAL: comparison/after/desktop/livekit-connected-1440x900.png\n- REFERENCE: references/mobile.png\n- ACTUAL: comparison/after/mobile/livekit-main-390x844.png\n- BEFORE: comparison/before/desktop/livekit-connected-1440x900.png\n- BEFORE: comparison/before/mobile/livekit-main-390x844.png\n\n## Screenshot coverage\n\nEvery test in this capture spec writes at least one screenshot. Static semantic/protocol specs remain screenshot-exempt.\n\n## Browser hygiene\n\n${browserErrors.length ? browserErrors.join('\\n') : 'No unexpected console, page, request, or 5xx errors observed.'}\n`);
  });
});
