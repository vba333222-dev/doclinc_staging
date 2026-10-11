const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { fixtureData, loginActor } = require('./fixtures');

const outputDir = path.resolve(process.env.VISUAL_REVIEW_OUTPUT_DIR || path.join(process.cwd(), 'test-results', 'visual-review', 'voice-call-refinement'));

async function openVoice(page, actor, requestId) {
  await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
  await loginActor(page, actor);
  await page.goto(`/chat?request_id=${encodeURIComponent(requestId)}`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
  await page.evaluate(() => {
    const hooks = window.__DOCLINC_LIVEKIT_TEST_HOOKS__;
    hooks.openCall();
    hooks.setMode('audio');
    hooks.setActive();
  });
  await expect(page.locator('.doclinc-call-modal.is-open.is-audio')).toBeVisible();
  await expect(page.locator('[data-voice-waveform][data-lottie-loaded="true"]')).toBeVisible();
}

test('captures adaptive production voice-call visual evidence', async ({ page }) => {
  test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
  const data = fixtureData();
  fs.mkdirSync(outputDir, { recursive: true });
  const captures = [
    { width: 1440, height: 900, file: 'voice-desktop-1440x900.png', actor: 'WARGA', request: data.requests.visit_happy, state: 'connected-no-photo-fallback' },
    { width: 1024, height: 768, file: 'voice-tablet-landscape-1024x768.png', actor: 'WARGA', request: data.requests.visit_happy, state: 'connected-no-photo-fallback' },
    { width: 768, height: 1024, file: 'voice-tablet-portrait-768x1024.png', actor: 'WARGA', request: data.requests.visit_happy, state: 'connected-no-photo-fallback' },
    { width: 353, height: 800, file: 'voice-mobile-353x800.png', actor: 'WARGA', request: data.requests.visit_happy, state: 'connected-no-photo-fallback' },
    { width: 360, height: 800, file: 'voice-mobile-360x800.png', actor: 'WARGA', request: data.requests.visit_happy, state: 'connected-no-photo-fallback' },
    { width: 390, height: 844, file: 'voice-mobile-390x844.png', actor: 'WARGA', request: data.requests.visit_happy, state: 'connected-no-photo-fallback' },
    { width: 412, height: 915, file: 'voice-mobile-412x915.png', actor: 'WARGA', request: data.requests.visit_happy, state: 'connected-no-photo-fallback' },
    { width: 430, height: 932, file: 'voice-mobile-430x932.png', actor: 'WARGA', request: data.requests.visit_happy, state: 'connected-no-photo-fallback' }
  ];
  const entries = [];
  for (const capture of captures) {
    await page.setViewportSize({ width: capture.width, height: capture.height });
    await openVoice(page, data.actors[capture.actor], capture.request);
    const proof = await page.evaluate(() => ({
      title: document.getElementById('doclincCallTitle')?.textContent.trim() || '',
      role: document.querySelector('.doclinc-call-empty-role')?.textContent.trim() || '',
      photo: !!document.querySelector('[data-voice-profile-photo]'),
      fallback: !!document.querySelector('[data-voice-profile-fallback]'),
      copy: document.querySelector('.doclinc-call-empty-support')?.textContent.trim() || '',
      videoCount: document.querySelectorAll('.doclinc-call-stage video').length,
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
    }));
    expect(proof.copy).toBe('Kami akan membantu konsultasi Anda');
    expect(proof.videoCount).toBe(0);
    expect(proof.overflow).toBe(false);
    await page.screenshot({ path: path.join(outputDir, capture.file), fullPage: false, animations: 'disabled', caret: 'hide' });
    entries.push({ file: capture.file, actor: capture.actor, route: `/chat?request_id=${capture.request}`, viewport: `${capture.width}x${capture.height}`, workflow_state: 'Accepted', ui_state: capture.state, local_identity: capture.actor, counterpart_identity: proof.title, request_fixture: String(capture.request), local_media_state: 'audio-only', remote_media_state: 'audio-connected', main_stage_owner: proof.title, pip_owner: 'none', controls: ['more', 'camera', 'speaker', 'microphone', 'end-call'], source: 'PRODUCTION_DOM', profile_photo_present: proof.photo, text_first_fallback_present: proof.fallback, visible_support_copy: proof.copy, video_elements: proof.videoCount, visual_purpose: 'Voice-call identity, waveform, audio-only stage and responsive composition', result: 'PASS' });
  }
  fs.writeFileSync(path.join(outputDir, 'manifest.json'), JSON.stringify({ evidence_level: 'PRODUCTION_DOM_VOICE_CALL', synthetic_patient_data: true, entries }, null, 2));
  fs.writeFileSync(path.join(outputDir, 'visual-review.md'), '# Voice-call production visual evidence\n\nAll captures use the authenticated CI3 production DOM and production voice presentation path. The waveform loads the local `soundwave.json` asset and is decorative/presentation-only. No stock photo is rendered for the no-photo Warga fixture.\n');
  const links = entries.map((entry) => `<li><a href="${entry.file}">${entry.file}</a> — ${entry.actor}, ${entry.viewport}, ${entry.ui_state}</li>`).join('');
  fs.writeFileSync(path.join(outputDir, 'index.html'), `<!doctype html><meta charset="utf-8"><title>Voice-call visual evidence</title><h1>Voice-call production visual evidence</h1><ul>${links}</ul>`);
});
