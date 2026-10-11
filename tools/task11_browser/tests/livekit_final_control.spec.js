const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const thread = fs.readFileSync(path.join(repoRoot, 'application/modules/chat/views/thread_v.php'), 'utf8');
const css = fs.readFileSync(path.join(repoRoot, 'assets/css/doclinc-livekit.css'), 'utf8');

function extractFunction(source, name) {
  const start = source.indexOf(`function ${name}(`);
  if (start < 0) return null;
  const open = source.indexOf('{', start);
  if (open < 0) return null;
  let depth = 0;
  for (let i = open; i < source.length; i += 1) {
    if (source[i] === '{') depth += 1;
    if (source[i] === '}') {
      depth -= 1;
      if (depth === 0) return source.slice(start, i + 1);
    }
  }
  return null;
}

test('LiveKit exposes exactly five icon-only primary controls and hides participant UI', () => {
  const controls = thread.match(/<button[^>]+class="doclinc-call-control[^>]*id="([^"]+)"[\s\S]*?<\/button>/g) || [];
  const ids = controls.map(control => (control.match(/id="([^"]+)"/) || [])[1]).filter(Boolean);
  expect(ids).toEqual(['doclincCallMore', 'doclincCallCamera', 'doclincCallSpeaker', 'doclincCallMic', 'doclincCallEnd']);
  expect(thread).not.toContain('doclincCallParticipants');
  expect(thread).not.toContain('livekit.participants');
  expect(thread).toMatch(/id="doclincCallMorePanel"[\s\S]*id="doclincCallChat"/);
  expect(thread).toMatch(/id="doclincCallMorePanel"[\s\S]*id="doclincCallSwitch"/);
  expect(thread).toMatch(/id="doclincCallMorePanel"[\s\S]*id="doclincCallMinimize"/);
  expect(thread).toMatch(/id="doclincCallMorePanel"[\s\S]*id="doclincCallClose"/);
  for (const label of ['Mikrofon', 'Kamera', 'Suara', 'Lainnya', 'Akhiri Panggilan']) {
    expect(thread).not.toMatch(new RegExp(`id="doclincCall(?:More|Camera|Speaker|Mic|End)"[\\s\\S]*>${label}<`));
  }
  expect(controls.every(control => !control.includes('doclinc-call-control-label'))).toBeTruthy();
});

test('LiveKit uses the canonical End Call asset and preserves Indonesian icon-only semantics', () => {
  expect(thread).toContain("doclinc_ui_asset('livekit.end_call')");
  const endButton = (thread.match(/id="doclincCallEnd"[\s\S]*?<\/button>/) || [''])[0];
  expect(endButton).not.toContain("doclinc_ui_asset('voice_call')");
  expect(thread).not.toContain('>Peserta<');
  expect(thread).not.toContain('>Participants<');
  expect(thread).not.toContain('>Mute<');
  expect(thread).not.toContain('>Camera<');
  expect(thread).not.toContain('>Speaker<');
  expect(thread).not.toContain('>Chat<');
  expect(thread).not.toContain('>More<');
  expect(thread).not.toContain('>End Call<');
});

test('counterpart capsules retain the adaptive role line used by production identity binding', () => {
  expect(thread).toContain('class="doclinc-call-empty-role"');
  expect(thread).toContain('id="doclincCallOverlayRole"');
});

test('production call controls reference every canonical visible glyph asset', () => {
  const expected = [
    ['doclincCallMore', "doclinc_ui_asset('more')"],
    ['doclincCallCamera', "doclinc_ui_asset('video_call')"],
    ['doclincCallSpeaker', "doclinc_ui_asset('speaker')"],
    ['doclincCallMic', "doclinc_ui_asset('mute')"],
    ['doclincCallEnd', "doclinc_ui_asset('livekit.end_call')"]
  ];
  for (const [id, asset] of expected) {
    const button = (thread.match(new RegExp(`id="${id}"[\\s\\S]*?<\\/button>`)) || [''])[0];
    expect(button, `${id} markup missing`).not.toBe('');
    expect(button, `${id} does not use canonical asset`).toContain(asset);
    expect(button, `${id} has no rendered img glyph`).toMatch(/<img[\s\S]*?src=/);
  }
});

test('LiveKit mobile primary controls stay in one row with readable icon hit targets', async ({ page }) => {
  await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1"><style>body{margin:0;background:#101718}*{box-sizing:border-box}${css}</style>
    <main class="doclinc-call-panel"><nav class="doclinc-call-controls">
      <button class="doclinc-call-control" aria-label="Lainnya"><img src="more.svg"></button>
      <button class="doclinc-call-control is-active" aria-label="Matikan kamera"><img src="video.svg"></button>
      <button class="doclinc-call-control is-active" aria-label="Aktifkan suara"><img src="speaker.svg"></button>
      <button class="doclinc-call-control is-active" aria-label="Bisukan mikrofon"><img src="mute.svg"></button>
      <button class="doclinc-call-control end-call" aria-label="Akhiri panggilan"><img src="endcall.svg"></button>
    </nav></main>`);
  for (const viewport of [{ width: 360, height: 800 }, { width: 390, height: 844 }, { width: 768, height: 1024 }, { width: 1024, height: 768 }]) {
    await page.setViewportSize(viewport);
    const info = await page.locator('.doclinc-call-control').evaluateAll(buttons => ({
      centers: buttons.map(button => {
        const box = button.getBoundingClientRect();
        return box.y + box.height / 2;
      }),
      minWidth: Math.min(...buttons.map(button => button.getBoundingClientRect().width)),
      minHeight: Math.min(...buttons.map(button => button.getBoundingClientRect().height)),
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
    }));
    expect(Math.max(...info.centers) - Math.min(...info.centers), `${viewport.width}px controls wrapped`).toBeLessThanOrEqual(2);
    expect(info.minWidth).toBeGreaterThanOrEqual(44);
    expect(info.minHeight).toBeGreaterThanOrEqual(44);
    expect(info.overflow).toBeFalsy();
  }
});

test('LiveKit camera-first presentation transitions local main to remote main', async ({ page }) => {
  expect(thread).toContain('function applyStagePresentation(hasLocalVideo, hasRemoteVideo)');
  expect(thread).toContain("elements.stage.classList.toggle('is-local-main', localMain)");
  expect(thread).toContain("elements.stage.classList.toggle('is-remote-main', !!hasRemoteVideo)");
  const functionSource = extractFunction(thread, 'applyStagePresentation');
  expect(functionSource).not.toBeNull();
  await page.setContent(`<style>${css}</style><main class="doclinc-call-stage">
    <div id="remote" class="doclinc-call-remote"></div><div id="local" class="doclinc-call-local"></div></main>
    <script></script>`);
  await page.evaluate(({ source }) => {
    const elements = { stage: document.querySelector('.doclinc-call-stage'), local: document.querySelector('#local') };
    const state = { mode: 'video' };
    window.applyStagePresentation = new Function('elements', 'state', `${source}; return applyStagePresentation;`)(elements, state);
  }, { source: functionSource });
  await page.evaluate(() => window.applyStagePresentation(true, false));
  await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-local-main/);
  await expect(page.locator('#local')).toHaveClass(/is-main/);
  await page.evaluate(() => window.applyStagePresentation(true, true));
  await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-remote-main/);
  await expect(page.locator('#local')).not.toHaveClass(/is-main/);
  await page.evaluate(() => window.applyStagePresentation(true, false));
  await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-local-main/);
});
