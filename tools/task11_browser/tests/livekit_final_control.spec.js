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

test('LiveKit exposes five primary controls, a direct-call header, and no participant UI', () => {
  const controls = thread.match(/<button[^>]+class="[^"]*dl-call-control[^>]*id="([^"]+)"[\s\S]*?<\/button>/g) || [];
  const ids = controls.map(control => (control.match(/id="([^"]+)"/) || [])[1]).filter(Boolean);
  expect(ids).toEqual(['doclincCallSpeaker', 'doclincCallCamera', 'doclincCallMic', 'doclincCallChat', 'doclincCallEnd']);
  expect(thread).not.toContain('doclincCallPrejoin');
  expect(thread).not.toContain('doclincCallJoin');
  expect(thread).not.toContain('Gabung sekarang');
  expect(thread).not.toContain('doclincCallParticipants');
  expect(thread).not.toContain('livekit.participants');
  expect(thread).toMatch(/id="doclincCallMorePanel"[\s\S]*id="doclincCallSwitch"/);
  expect(thread).toMatch(/id="doclincCallMorePanel"[\s\S]*id="doclincCallMinimize"/);
  expect(thread).toMatch(/id="doclincCallMorePanel"[\s\S]*id="doclincCallClose"/);
  for (const label of ['Mikrofon', 'Kamera', 'Suara', 'Lainnya', 'Akhiri Panggilan']) {
    expect(thread).not.toMatch(new RegExp(`id="doclincCall(?:More|Camera|Speaker|Mic|End)"[\\s\\S]*>${label}<`));
  }
  expect(controls.every(control => !control.includes('doclinc-call-control-label'))).toBeTruthy();
});

test('LiveKit uses a filled red end control and keeps Indonesian icon-only semantics', () => {
  const endButton = (thread.match(/id="doclincCallEnd"[\s\S]*?<\/button>/) || [''])[0];
  expect(endButton).toContain('fa-phone-slash');
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

test('counterpart identity is shown in the direct-call header and empty stage', () => {
  expect(thread).toContain('class="dl-call-person-name doclinc-call-title"');
  expect(thread).toContain('class="dl-call-empty-name doclinc-call-empty-name"');
  expect(thread).toContain('class="dl-call-empty-role doclinc-call-empty-role"');
});

test('production call controls render Font Awesome glyphs without image assets', () => {
  const expected = [
    ['doclincCallSpeaker', 'fa-volume-up'],
    ['doclincCallCamera', 'fa-video'],
    ['doclincCallMic', 'fa-microphone'],
    ['doclincCallChat', 'fa-comment-alt'],
    ['doclincCallEnd', 'fa-phone-slash']
  ];
  for (const [id, icon] of expected) {
    const button = (thread.match(new RegExp(`id="${id}"[\\s\\S]*?<\\/button>`)) || [''])[0];
    expect(button, `${id} markup missing`).not.toBe('');
    expect(button, `${id} does not use ${icon}`).toContain(icon);
    expect(button, `${id} should not use an img glyph`).not.toMatch(/<img[\s\S]*?src=/);
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
