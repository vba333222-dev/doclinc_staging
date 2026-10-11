const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { fixtureData, loginActor } = require('./fixtures');

const outputRoot = path.resolve(process.env.VISUAL_REVIEW_OUTPUT_DIR || path.join(process.cwd(), 'test-results', 'visual-review', 'video-production-fidelity'));

function ensureOutput() {
  fs.mkdirSync(outputRoot, { recursive: true });
}

async function openProductionCall(page, actor, requestId) {
  await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
  await loginActor(page, actor);
  await page.goto(`/chat?request_id=${encodeURIComponent(requestId)}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#doclincCallModal')).toHaveCount(1);
  await page.locator('.doclinc-call-start[data-call-mode="video"]').click();
  await expect(page.locator('#doclincCallModal')).toHaveClass(/is-open/);
  await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
  await page.locator('#doclincCallControls img').evaluateAll(images => Promise.all(images.map(image => {
    if (image.complete && image.naturalWidth > 0) return Promise.resolve();
    return new Promise((resolve, reject) => {
      image.addEventListener('load', resolve, { once: true });
      image.addEventListener('error', reject, { once: true });
    });
  })));
  await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.setActive());
}

async function applyMediaState(page, state) {
  await page.evaluate(async (requestedState) => {
    const hooks = window.__DOCLINC_LIVEKIT_TEST_HOOKS__;
    if (!hooks) throw new Error('production_livekit_test_hooks_missing');
    hooks.clearRemoteTracks();

    function mediaTrack(label, color) {
      const canvas = document.createElement('canvas');
      canvas.width = 640; canvas.height = 480;
      const context = canvas.getContext('2d');
      context.fillStyle = color; context.fillRect(0, 0, canvas.width, canvas.height);
      const stream = canvas.captureStream(1);
      return {
        kind: 'video',
        attach() {
          const video = document.createElement('video');
          video.autoplay = true; video.muted = true; video.playsInline = true;
          video.srcObject = stream;
          video.dataset.testMedia = label;
          video.play().catch(() => {});
          return video;
        }
      };
    }

    if (requestedState === 'local-only') {
      hooks.showLocalPreview(mediaTrack('LOCAL', '#1e5f4d'));
      return;
    }
    if (requestedState === 'remote-active') {
      hooks.showLocalPreview(mediaTrack('LOCAL', '#1e5f4d'));
      hooks.attachRemoteTrack(mediaTrack('REMOTE', '#385a7c'), { sid: 'test-remote-video' }, { identity: 'test-remote' });
      return;
    }
    if (requestedState === 'camera-off') {
      hooks.showLocalPlaceholder('Kamera nonaktif');
      hooks.attachRemoteTrack(mediaTrack('REMOTE', '#385a7c'), { sid: 'test-remote-video-off' }, { identity: 'test-remote-off' });
      return;
    }
    if (requestedState === 'remote-disconnect') {
      hooks.showLocalPreview(mediaTrack('LOCAL', '#1e5f4d'));
      hooks.attachRemoteTrack(mediaTrack('REMOTE', '#385a7c'), { sid: 'test-remote-video-disconnect' }, { identity: 'test-remote-disconnect' });
      hooks.clearRemoteTracks();
      return;
    }
    if (requestedState === 'permission-failure') {
      hooks.showLocalPlaceholder('Kamera nonaktif');
    }
  }, state);
  await page.waitForTimeout(100);
}

async function snapshot(page, file) {
  ensureOutput();
  await page.screenshot({ path: path.join(outputRoot, file), fullPage: false, animations: 'disabled', caret: 'hide' });
}

test.describe('production DOM Video Call media states', () => {
  test('production presentation functions drive deterministic local/remote states', async ({ page }) => {
    test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
    const data = fixtureData();
    await openProductionCall(page, data.actors.DOCTOR_PERFORMER, data.requests.doctor_performer);

    await applyMediaState(page, 'local-only');
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-local-main/);
    await expect(page.locator('.doclinc-call-stage')).not.toHaveClass(/is-remote-main/);
    await expect(page.locator('#doclincCallRemote video')).toHaveCount(0);
    await expect(page.locator('#doclincCallLocal video')).toHaveCount(1);
    await snapshot(page, 'production-local-main.png');

    await applyMediaState(page, 'remote-active');
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-remote-main/);
    await expect(page.locator('.doclinc-call-stage')).not.toHaveClass(/is-local-main/);
    await expect(page.locator('#doclincCallRemote video')).toHaveCount(1);
    await expect(page.locator('#doclincCallLocal video')).toHaveCount(1);
    await expect(page.locator('#doclincCallLocal')).not.toHaveClass(/is-placeholder/);
    await expect(page.locator('#doclincCallLocal video')).toHaveAttribute('data-test-media', 'LOCAL');
    await expect(page.locator('.doclinc-call-provider-overlay')).toBeVisible();
    const cropStyles = await page.evaluate(() => Array.from(document.querySelectorAll('#doclincCallRemote video, #doclincCallLocal video')).map(video => {
      const style = getComputedStyle(video);
      return { objectFit: style.objectFit, objectPosition: style.objectPosition };
    }));
    expect(cropStyles.length).toBe(2);
    expect(cropStyles.every(style => style.objectFit === 'cover' && style.objectPosition === '50% 50%')).toBe(true);
    await snapshot(page, 'production-remote-main-local-pip.png');

    await applyMediaState(page, 'camera-off');
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-remote-main/);
    await expect(page.locator('#doclincCallLocal')).toHaveClass(/is-placeholder/);
    await expect(page.locator('#doclincCallRemote video')).toHaveCount(1);
    await snapshot(page, 'production-camera-off.png');

    await applyMediaState(page, 'remote-disconnect');
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-local-main/);
    await expect(page.locator('#doclincCallRemote video')).toHaveCount(0);
    await expect(page.locator('#doclincCallLocal video')).toHaveCount(1);
    await snapshot(page, 'production-remote-disconnect.png');

    await applyMediaState(page, 'permission-failure');
    await expect(page.locator('#doclincCallLocal')).toHaveClass(/is-placeholder/);
    await expect(page.locator('#doclincCallLocal')).toContainText('Kamera nonaktif');

    const manifest = [
      ['production-local-main.png', 'local-only', 'local video is main stage; PiP hidden'],
      ['production-remote-main-local-pip.png', 'remote-active', 'remote video is main stage; deterministic local video is PiP'],
      ['production-camera-off.png', 'camera-off', 'remote video remains main stage; local camera-off fallback is visible'],
      ['production-remote-disconnect.png', 'remote-disconnect', 'remote media clears and local video returns to main stage']
    ].map(([file, state, proof]) => ({
      file,
      actor: 'DOCTOR_PERFORMER',
      route: '/chat?request_id=<disposable-doctor-performer>',
      viewport: '1440x900',
      workflow_state: 'active video call',
      ui_state: state,
      test_spec: 'production_dom_video_state.spec.js',
      test_title: 'production presentation functions drive deterministic local/remote states',
      state_requested: state,
      state_assertion_result: 'PASS',
      visible_state_proof: proof,
      icons_visible: ['doklinclogo.svg', 'more.svg', 'videocall.svg', 'loudspeaker.svg', 'mute.svg', 'endcall.svg'],
      reference_used: 'doklinc_active_video_call_ui/screen.png for shell composition; media pixels synthetic',
      result: 'PASS'
    }));
    fs.writeFileSync(path.join(outputRoot, 'manifest.json'), JSON.stringify({ evidence_level: 'PRODUCTION_DOM_CLIENT_MEDIA_STATE', duplicate_video_ui_fixture_dom: false, entries: manifest }, null, 2));
    fs.writeFileSync(path.join(outputRoot, 'visual-review.md'), '# Production DOM Video Call evidence\n\nThis evidence uses the authenticated CI3 production DOM and production presentation functions. Deterministic canvas media is attached through the explicit test seam; no duplicate call markup is injected.\n\nStates: local-only, remote-active with local PiP, camera-off, remote-disconnect.\n');
    fs.writeFileSync(path.join(outputRoot, 'index.html'), '<!doctype html><meta charset="utf-8"><title>Production DOM Video evidence</title><h1>Production DOM Video evidence</h1><ul><li><a href="production-local-main.png">Local main</a></li><li><a href="production-remote-main-local-pip.png">Remote main + local PiP</a></li><li><a href="production-camera-off.png">Camera off</a></li><li><a href="production-remote-disconnect.png">Remote disconnect</a></li></ul>');
  });

  test('production DOM mobile PiP keeps camera media visible without text overlays', async ({ page }) => {
    test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
    await page.setViewportSize({ width: 390, height: 844 });
    const data = fixtureData();
    await openProductionCall(page, data.actors.DOCTOR_PERFORMER, data.requests.doctor_performer);
    await applyMediaState(page, 'remote-active');
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-remote-main/);
    await expect(page.locator('#doclincCallRemote video')).toHaveCount(1);
    await expect(page.locator('#doclincCallLocal video')).toHaveCount(1);
    await expect(page.locator('#doclincCallLocal')).toHaveText('');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    expect(overflow).toBe(false);
  });
});
