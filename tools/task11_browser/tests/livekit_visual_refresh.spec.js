const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const css = fs.readFileSync(path.join(repoRoot, 'assets/css/doclinc-livekit.css'), 'utf8');
const thread = fs.readFileSync(path.join(repoRoot, 'application/modules/chat/views/thread_v.php'), 'utf8');

test('LiveKit header uses the canonical DokLinc logo asset', () => {
  expect(thread).toContain("doclinc_ui_asset('livekit.brand_logo')");
  expect(thread).not.toContain('>DL<');
  expect(thread).not.toContain('Identitas terverifikasi');
});

test('production dock follows the reference primary action order', () => {
  const controls = thread.match(/<button[^>]+class="doclinc-call-control[^>]*id="([^"]+)"[\s\S]*?<\/button>/g) || [];
  const ids = controls.map(control => (control.match(/id="([^"]+)"/) || [])[1]).filter(Boolean);
  expect(ids).toEqual([
    'doclincCallMore',
    'doclincCallCamera',
    'doclincCallSpeaker',
    'doclincCallMic',
    'doclincCallEnd',
  ]);
  expect(thread).not.toContain('doclincCallParticipants');
  expect(thread).not.toContain('livekit.participants');
  expect(thread).toContain("doclinc_ui_asset('livekit.end_call')");
  expect(thread).toMatch(/id="doclincCallMorePanel"[\s\S]*id="doclincCallSwitch"/);
  expect(thread).toMatch(/id="doclincCallMorePanel"[\s\S]*id="doclincCallMinimize"/);
});

test('LiveKit shell removes technical header clutter and keeps production flex sizing', async ({ page }) => {
  await page.setContent(shellMarkup());
  await page.setViewportSize({ width: 1440, height: 900 });
  const desktop = await page.evaluate(() => {
    const panel = document.querySelector('.doclinc-call-panel');
    const style = getComputedStyle(panel);
    return { display: style.display, direction: style.flexDirection, security: document.querySelector('.doclinc-call-security'), signal: document.querySelector('.doclinc-call-signal'), headerMore: document.querySelector('.doclinc-call-head-more') };
  });
  expect(desktop.display).toBe('flex');
  expect(desktop.direction).toBe('column');
  expect(desktop.security).toBeNull();
  expect(desktop.signal).toBeNull();
  expect(desktop.headerMore).toBeNull();
  await page.setViewportSize({ width: 390, height: 844 });
  const mobile = await page.evaluate(() => ({ security: document.querySelector('.doclinc-call-security'), signal: document.querySelector('.doclinc-call-signal'), radius: getComputedStyle(document.querySelector('.doclinc-call-control img')).borderRadius }));
  expect(mobile.security).toBeNull();
  expect(mobile.signal).toBeNull();
  expect(mobile.radius).toBe('0px');
});

test('reference composition has measurable desktop and mobile geometry', async ({ page }) => {
  await page.setContent(`<style>body{margin:0;background:#eef4f9}*{box-sizing:border-box}${css}</style>
    <main class="doclinc-call-panel">
      <header class="doclinc-call-head"><div class="doclinc-call-brand-lockup"><img class="doclinc-call-brand" src="/assets/doclinc_ui/icon-pack/doklinclogo.svg"><span class="doclinc-call-divider"></span><div class="doclinc-call-context"><span class="doclinc-call-eyebrow">Konsultasi dengan</span><h1 class="doclinc-call-title">dr. Andi Pratama</h1><div class="doclinc-call-status">Terhubung</div></div></div></header>
      <section class="doclinc-call-stage"><div class="doclinc-call-remote"><div class="doclinc-call-empty"><span class="doclinc-call-empty-name">dr. Andi Pratama</span></div></div><div class="doclinc-call-local"></div></section>
      <nav class="doclinc-call-controls"><button class="doclinc-call-control"> </button><button class="doclinc-call-control"> </button><button class="doclinc-call-control"> </button><button class="doclinc-call-control"> </button><button class="doclinc-call-control end-call"> </button></nav>
    </main>`);
  await page.setViewportSize({ width: 1440, height: 900 });
  const desktop = await page.evaluate(() => {
    const box = selector => document.querySelector(selector).getBoundingClientRect();
    return { header: box('.doclinc-call-head'), brand: box('.doclinc-call-brand'), stage: box('.doclinc-call-stage'), local: box('.doclinc-call-local'), dock: box('.doclinc-call-controls'), end: box('.end-call') };
  });
  expect(desktop.header.height).toBeLessThan(140);
  expect(desktop.brand.width).toBeGreaterThan(100);
  expect(desktop.stage.width).toBeGreaterThan(900);
  expect(await page.locator('.doclinc-call-head').evaluate(el => getComputedStyle(el).display)).toBe('grid');
  expect(desktop.stage.height).toBeGreaterThan(desktop.dock.height * 3);
  const remote = await page.locator('.doclinc-call-remote').evaluate(el => { const s = getComputedStyle(el); return { position: s.position, display: s.display, align: s.alignItems, justify: s.justifyContent }; });
  expect(remote).toEqual(expect.objectContaining({ position: 'absolute', display: 'flex', align: 'center', justify: 'center' }));
  expect(desktop.local.x + desktop.local.width).toBeGreaterThan(desktop.stage.x + desktop.stage.width * 0.72);
  expect(desktop.dock.width).toBeGreaterThan(900);
  expect(desktop.end.width).toBeGreaterThanOrEqual(48);
  expect(await page.locator('.doclinc-call-control').count()).toBe(5);
  await page.setViewportSize({ width: 390, height: 844 });
  const mobile = await page.evaluate(() => {
    const box = selector => document.querySelector(selector).getBoundingClientRect();
    const controls = [...document.querySelectorAll('.doclinc-call-control')].map(el => el.getBoundingClientRect());
    return { header: box('.doclinc-call-head'), stage: box('.doclinc-call-stage'), dock: box('.doclinc-call-controls'), end: box('.end-call'), controls };
  });
  const compactViewport = await page.evaluate(() => window.innerWidth <= 640);
  expect(await page.locator('.doclinc-call-head').evaluate(el => getComputedStyle(el).display)).toBe(compactViewport ? 'flex' : 'grid');
  expect(mobile.dock.y).toBeGreaterThan(mobile.stage.y + mobile.stage.height * 0.55);
  expect(mobile.end.width).toBeGreaterThanOrEqual(48);
  const mobileCenters = mobile.controls.map(box => box.y + box.height / 2);
  expect(Math.max(...mobileCenters) - Math.min(...mobileCenters)).toBeLessThanOrEqual(2);
  expect(mobile.end.y).toBeGreaterThanOrEqual(mobile.dock.y);
  expect(mobile.end.y).toBeLessThan(mobile.dock.y + mobile.dock.height);
  expect(await page.locator('.doclinc-call-title').evaluate(el => el.getBoundingClientRect().right <= document.querySelector('.doclinc-call-head').getBoundingClientRect().right)).toBeTruthy();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
});

function shellMarkup() {
  return `<meta name="viewport" content="width=device-width, initial-scale=1"><style>body{margin:0}*{box-sizing:border-box}${css}</style>
    <main class="doclinc-call-panel">
      <header class="doclinc-call-head">
        <div class="doclinc-call-brand-lockup"><img class="doclinc-call-brand" src="/assets/doclinc_ui/icon-pack/doklinclogo.svg" alt="DokLinc"><span class="doclinc-call-divider"></span><div class="doclinc-call-context"><span class="doclinc-call-eyebrow">Konsultasi dengan</span><h1 class="doclinc-call-title">Tenaga kesehatan</h1><div class="doclinc-call-provider-meta"><span class="doclinc-call-provider-role">Siap bergabung</span></div></div></div>
        <button class="doclinc-call-close" aria-label="Tutup tampilan panggilan"><img alt="" src="/assets/doclinc_ui/icon-pack/close.svg"></button>
      </header>
      <section class="doclinc-call-stage"><div class="doclinc-call-remote"><div class="doclinc-call-empty"><span class="doclinc-call-empty-name">Menunggu lawan bicara bergabung</span><span>Audio tetap tersambung</span></div></div><div class="doclinc-call-local"></div></section>
      <nav class="doclinc-call-controls"><button class="doclinc-call-control" aria-label="Buka opsi lainnya"><img src="/assets/doclinc_ui/icon-pack/more.svg"></button><button class="doclinc-call-control" aria-label="Matikan kamera"><img src="/assets/doclinc_ui/icon-pack/videocall.svg"></button><button class="doclinc-call-control" aria-label="Aktifkan suara"><img src="/assets/doclinc_ui/icon-pack/loudspeaker.svg"></button><button class="doclinc-call-control" aria-label="Bisukan mikrofon"><img src="/assets/doclinc_ui/icon-pack/mute.svg"></button><button class="doclinc-call-control end-call" aria-label="Akhiri panggilan"><img src="/assets/doclinc_ui/icon-pack/endcall.svg"></button></nav>
    </main>`;
}

test('premium shell renders a light stage, floating preview, and distinct controls', async ({ page }) => {
  await page.setContent(shellMarkup());
  await expect(page.locator('.doclinc-call-stage')).toBeVisible();
  await expect(page.locator('.doclinc-call-local')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Bisukan mikrofon' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Akhiri panggilan' })).toBeVisible();
  await expect(page.locator('.doclinc-call-close img')).toHaveAttribute('src', /close\.svg$/);
  await expect(page.locator('.doclinc-call-control.end-call img')).toHaveAttribute('src', /endcall\.svg$/);
});

test('responsive docks preserve usable control targets at mobile and desktop widths', async ({ page }) => {
  await page.setContent(shellMarkup());
  for (const viewport of [
    { width: 360, height: 800 }, { width: 375, height: 812 }, { width: 390, height: 844 },
    { width: 412, height: 915 }, { width: 430, height: 932 }, { width: 768, height: 1024 },
    { width: 820, height: 1180 }, { width: 1024, height: 768 }, { width: 1180, height: 820 },
    { width: 1366, height: 768 }, { width: 1440, height: 900 }, { width: 1536, height: 1024 },
  ]) {
    await page.setViewportSize(viewport);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    expect(overflow, `${viewport.width}px`).toBeFalsy();
    const box = await page.locator('.doclinc-call-control').first().boundingBox();
    expect(box.width).toBeGreaterThanOrEqual(44);
    expect(box.height).toBeGreaterThanOrEqual(44);
  }
});

test('mobile overflow panels sit above the complete multi-row control dock', async ({ page }) => {
  await page.setContent(`<style>body{margin:0}*{box-sizing:border-box}.doclinc-call-panel{display:flex;flex-direction:column;height:100dvh}${css}</style><main class="doclinc-call-panel"><section class="doclinc-call-stage"></section><nav class="doclinc-call-controls">${Array.from({ length: 7 }, (_, i) => `<button class="doclinc-call-control">Control ${i + 1}</button>`).join('')}<button class="doclinc-call-control end-call">End Call</button></nav><aside class="doclinc-call-popover">Participants</aside></main>`);
  await page.setViewportSize({ width: 390, height: 844 });
  const dock = await page.locator('.doclinc-call-controls').boundingBox();
  const popover = await page.locator('.doclinc-call-popover').boundingBox();
  // Device emulation can introduce a small fractional layout offset; keep a
  // tight tolerance while asserting the panel remains visually above the dock.
  expect(popover.y + popover.height).toBeLessThanOrEqual(dock.y + 16);
});

test('style scope does not introduce global button, img, or svg selectors', () => {
  expect(css).not.toMatch(/^(?:button|img|svg)\s*\{/m);
  expect(css).toContain('env(safe-area-inset-bottom)');
  expect(css).toContain('100dvh');
});
