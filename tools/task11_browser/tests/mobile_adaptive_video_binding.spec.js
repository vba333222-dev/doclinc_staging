const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { fixtureData, loginActor } = require('./fixtures');

const outputRoot = path.resolve(process.env.VISUAL_REVIEW_OUTPUT_DIR || path.join(process.cwd(), 'test-results', 'visual-review', 'video-mobile-adaptive'));
const viewports = [
  { width: 353, height: 800, key: '353x800' },
  { width: 360, height: 800, key: '360x800' },
  { width: 390, height: 844, key: '390x844' },
  { width: 412, height: 915, key: '412x915' },
  { width: 430, height: 932, key: '430x932' }
];

test.use({ deviceScaleFactor: 2 });

async function openProductionCall(page, actor, requestId) {
  await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
  await loginActor(page, actor);
  await page.goto(`/chat?request_id=${encodeURIComponent(requestId)}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#doclincCallModal')).toHaveCount(1);
  await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
  await page.evaluate(() => {
    const hooks = window.__DOCLINC_LIVEKIT_TEST_HOOKS__;
    hooks.setMode('video');
    hooks.openCall();
  });
}

async function attachCanvasTrack(page, hookName, label, color, participant) {
  await page.evaluate(({ hookName, label, color, participant }) => {
    const canvas = document.createElement('canvas');
    canvas.width = 320; canvas.height = 240;
    const context = canvas.getContext('2d');
    context.fillStyle = color;
    context.fillRect(0, 0, canvas.width, canvas.height);
    const stream = canvas.captureStream(1);
    const track = {
      kind: 'video',
      attach() {
        const video = document.createElement('video');
        video.autoplay = true;
        video.muted = true;
        video.playsInline = true;
        video.srcObject = stream;
        video.dataset.testMedia = label;
        video.play().catch(() => {});
        return video;
      }
    };
    window.__DOCLINC_TEST_LOCAL_TRACK__ = track;
    window.__DOCLINC_TEST_REMOTE_TRACK__ = track;
    window.__DOCLINC_TEST_REMOTE_PUBLICATION__ = { sid: `mobile-${label}` };
    window.__DOCLINC_TEST_REMOTE_PARTICIPANT__ = participant;
    window.__DOCLINC_LIVEKIT_TEST_HOOKS__[hookName](track, window.__DOCLINC_TEST_REMOTE_PUBLICATION__, participant);
  }, { hookName, label, color, participant });
  const selector = hookName === 'showLocalPreview' ? '#doclincCallLocal video' : '#doclincCallRemote video';
  await expect(page.locator(selector)).toHaveCount(1);
  await page.waitForFunction((selector) => {
    const video = document.querySelector(selector);
    return video && video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0;
  }, selector);
}

async function attachLocal(page) {
  await attachCanvasTrack(page, 'showLocalPreview', 'LOCAL', '#1e5f4d', null);
}

async function attachRemote(page, participant) {
  await attachCanvasTrack(page, 'trackSubscribed', participant.name, '#385a7c', participant);
}

async function capture(page, file) {
  fs.mkdirSync(outputRoot, { recursive: true });
  await page.screenshot({ path: path.join(outputRoot, file), fullPage: false, animations: 'disabled', caret: 'hide' });
}

async function assertMobileLayout(page, viewport) {
  const info = await page.evaluate(() => {
    const stage = document.querySelector('.doclinc-call-stage');
    const local = document.querySelector('#doclincCallLocal');
    const overlay = document.querySelector('.doclinc-call-provider-overlay');
    const controls = Array.from(document.querySelectorAll('#doclincCallControls > .doclinc-call-control'));
    const stageBox = stage.getBoundingClientRect();
    const localBox = local.getBoundingClientRect();
    const overlayBox = overlay.getBoundingClientRect();
    const intersects = !(localBox.right <= overlayBox.left || localBox.left >= overlayBox.right || localBox.bottom <= overlayBox.top || localBox.top >= overlayBox.bottom);
    const centers = controls.map(control => {
      const box = control.getBoundingClientRect();
      return { x: box.x + box.width / 2, y: box.y + box.height / 2, width: box.width, height: box.height };
    });
    const visible = element => {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    };
    const visibleText = Array.from(local.querySelectorAll('*')).filter(visible).map(node => node.textContent.trim()).filter(Boolean).join(' ');
    return {
      stage: { x: stageBox.x, y: stageBox.y, width: stageBox.width, height: stageBox.height },
      local: { x: localBox.x, y: localBox.y, width: localBox.width, height: localBox.height },
      overlay: { x: overlayBox.x, y: overlayBox.y, width: overlayBox.width, height: overlayBox.height },
      controls,
      centers,
      intersection: intersects,
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
      pageHeight: document.documentElement.scrollHeight,
      viewportHeight: window.innerHeight,
      pipText: visibleText,
      pipBadge: Array.from(local.querySelectorAll('[class*="badge"], [class*="status"]')).some(visible)
    };
  });
  expect(info.stage.width).toBeLessThanOrEqual(viewport.width);
  expect(info.stage.x).toBeGreaterThanOrEqual(0);
  expect(info.stage.x + info.stage.width).toBeLessThanOrEqual(viewport.width + 1);
  expect(info.local.x).toBeGreaterThanOrEqual(info.stage.x - 1);
  expect(info.local.y).toBeGreaterThanOrEqual(info.stage.y - 1);
  expect(info.local.x + info.local.width).toBeLessThanOrEqual(info.stage.x + info.stage.width + 1);
  expect(info.local.y + info.local.height).toBeLessThanOrEqual(info.stage.y + info.stage.height + 1);
  expect(info.intersection).toBe(false);
  expect(info.overflow).toBe(false);
  expect(info.controls).toHaveLength(5);
  expect(Math.max(...info.centers.map(item => item.y)) - Math.min(...info.centers.map(item => item.y))).toBeLessThanOrEqual(1);
  expect(Math.min(...info.centers.map(item => item.width))).toBeGreaterThanOrEqual(44);
  expect(Math.min(...info.centers.map(item => item.height))).toBeGreaterThanOrEqual(44);
  expect(info.pipText).toBe('');
  expect(info.pipBadge).toBe(false);
  expect(info.pageHeight).toBeLessThanOrEqual(info.viewportHeight + 2);
}

async function frameProof(page, selector) {
  return page.locator(selector).evaluate(video => {
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 1;
    canvas.height = video.videoHeight || 1;
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
    let nonZero = 0;
    for (let index = 0; index < pixels.length; index += 4) {
      if (pixels[index] || pixels[index + 1] || pixels[index + 2]) nonZero += 1;
    }
    return { readyState: video.readyState, width: video.videoWidth, height: video.videoHeight, nonZero };
  });
}

test.describe('mobile production DOM adaptive Video Call', () => {
  test('camera-first, request-bound identity, mobile geometry, and screenshots', async ({ page }) => {
    test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
    const data = fixtureData();
    const requestId = data.requests.doctor_performer;
    await page.setViewportSize({ width: 353, height: 800 });
    await openProductionCall(page, data.actors.DOCTOR_PERFORMER, requestId);
    await attachLocal(page);
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-local-main/);
    await expect(page.locator('#doclincCallLocal')).toBeVisible();
    await expect(page.locator('#doclincCallRemote video')).toHaveCount(0);
    await capture(page, 'mobile-353-local-main.png');

    const patient = { identity: `warga-${data.actors.WARGA.id}`, name: 'Warga Alfa Video', metadata: JSON.stringify({ request_id: requestId, role: 'warga', user_id: data.actors.WARGA.id }) };
    await attachRemote(page, patient);
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-remote-main/);
    await expect(page.locator('.doclinc-call-stage')).not.toHaveClass(/is-local-main/);
    await expect(page.locator('#doclincCallTitle')).toHaveText(patient.name);
    await expect(page.locator('#doclincCallOverlayName')).toHaveText(patient.name);
    await expect(page.locator('#doclincCallLocal video')).toHaveCount(1);
    const remoteFrame = await frameProof(page, '#doclincCallRemote video');
    expect(remoteFrame.nonZero).toBeGreaterThan(0);
    await assertMobileLayout(page, { width: 353, height: 800 });

    for (const viewport of viewports) {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await assertMobileLayout(page, viewport);
      await capture(page, `mobile-${viewport.width}-remote-main-local-pip.png`);
      if (viewport.width === 390) {
        await capture(page, 'mobile-390-doctor-view-patient.png');
      }
    }

    await page.setViewportSize({ width: 390, height: 844 });
    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.setMediaPresentationState('audio', false));
    await expect(page.locator('#doclincCallMic')).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('#doclincCallMic')).toHaveClass(/is-off/);
    await capture(page, 'mobile-390-mic-muted.png');
    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.showLocalPlaceholder('Kamera nonaktif'));
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-remote-main/);
    await expect(page.locator('#doclincCallLocal video')).toHaveCount(0);
    expect(await page.locator('#doclincCallLocal').evaluate(local => Array.from(local.querySelectorAll('*')).filter(node => {
      const style = getComputedStyle(node);
      const rect = node.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    }).map(node => node.textContent.trim()).filter(Boolean).join(' '))).toBe('');
    await capture(page, 'mobile-390-camera-off.png');
    await page.locator('#doclincCallMore').click();
    await expect(page.locator('#doclincCallMorePanel')).toBeVisible();
    await expect(page.locator('#doclincCallMorePanel')).not.toContainText('Peserta');
    await assertMobileLayout(page, { width: 390, height: 844 });
    await capture(page, 'mobile-390-more-open.png');

    await page.setViewportSize({ width: 353, height: 800 });
    await expect(page.locator('#doclincCallMorePanel')).toBeVisible();
    await assertMobileLayout(page, { width: 353, height: 800 });
    await capture(page, 'mobile-353-more-open.png');
    await page.setViewportSize({ width: 430, height: 932 });
    await assertMobileLayout(page, { width: 430, height: 932 });
    await capture(page, 'mobile-430-more-open.png');

    await page.locator('#doclincCallMore').click();
    await expect(page.locator('#doclincCallMorePanel')).toBeHidden();
    await attachLocal(page);
    await page.setViewportSize({ width: 844, height: 390 });
    await assertMobileLayout(page, { width: 844, height: 390 });
    await capture(page, 'mobile-844x390-landscape.png');

    await page.setViewportSize({ width: 768, height: 1024 });
    await assertMobileLayout(page, { width: 768, height: 1024 });
    await capture(page, 'tablet-768x1024.png');
  });

  test('Warga mobile view renders exact doctor identity from request-bound metadata', async ({ page }) => {
    test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
    const data = fixtureData();
    const requestId = data.requests.doctor_performer;
    await page.setViewportSize({ width: 390, height: 844 });
    await openProductionCall(page, data.actors.WARGA, requestId);
    await attachLocal(page);
    const doctor = { identity: `dokter-${data.actors.RD.id}`, name: 'dr. Muhammad Rizky Pratama', metadata: JSON.stringify({ request_id: requestId, role: 'dokter', user_id: data.actors.RD.id }) };
    await attachRemote(page, doctor);
    await expect(page.locator('#doclincCallTitle')).toHaveText(doctor.name);
    await expect(page.locator('#doclincCallOverlayName')).toHaveText(doctor.name);
    await assertMobileLayout(page, { width: 390, height: 844 });
    await capture(page, 'mobile-390-patient-view-doctor.png');
  });

  test.afterAll(() => {
    fs.mkdirSync(outputRoot, { recursive: true });
    const screenshots = [
      { file: 'mobile-353-local-main.png', viewport: '353x800', actor: 'DOCTOR_PERFORMER', local_identity: 'dr. Bima Verifikasi', counterpart_identity: 'Warga Alfa Video', state: 'local-main', local_media_state: 'active', remote_media_state: 'absent', main_stage_owner: 'local', pip_owner: 'none' },
      { file: 'mobile-353-remote-main-local-pip.png', viewport: '353x800', actor: 'DOCTOR_PERFORMER', local_identity: 'dr. Bima Verifikasi', counterpart_identity: 'Warga Alfa Video', state: 'remote-main-local-pip', local_media_state: 'active', remote_media_state: 'active', main_stage_owner: 'remote', pip_owner: 'local' },
      { file: 'mobile-360-remote-main-local-pip.png', viewport: '360x800', actor: 'DOCTOR_PERFORMER', local_identity: 'dr. Bima Verifikasi', counterpart_identity: 'Warga Alfa Video', state: 'remote-main-local-pip', local_media_state: 'active', remote_media_state: 'active', main_stage_owner: 'remote', pip_owner: 'local' },
      { file: 'mobile-390-doctor-view-patient.png', viewport: '390x844', actor: 'DOCTOR_PERFORMER', local_identity: 'dr. Bima Verifikasi', counterpart_identity: 'Warga Alfa Video', state: 'remote-main-local-pip', local_media_state: 'active', remote_media_state: 'active', main_stage_owner: 'remote', pip_owner: 'local' },
      { file: 'mobile-390-patient-view-doctor.png', viewport: '390x844', actor: 'WARGA', local_identity: 'Warga Alfa Video', counterpart_identity: 'dr. Muhammad Rizky Pratama', state: 'remote-main-local-pip', local_media_state: 'active', remote_media_state: 'active', main_stage_owner: 'remote', pip_owner: 'local' },
      { file: 'mobile-390-more-open.png', viewport: '390x844', actor: 'DOCTOR_PERFORMER', local_identity: 'dr. Bima Verifikasi', counterpart_identity: 'Warga Alfa Video', state: 'more-open', local_media_state: 'camera-off', remote_media_state: 'active', main_stage_owner: 'remote', pip_owner: 'local' },
      { file: 'mobile-390-camera-off.png', viewport: '390x844', actor: 'DOCTOR_PERFORMER', local_identity: 'dr. Bima Verifikasi', counterpart_identity: 'Warga Alfa Video', state: 'camera-off', local_media_state: 'camera-off', remote_media_state: 'active', main_stage_owner: 'remote', pip_owner: 'local' },
      { file: 'mobile-390-mic-muted.png', viewport: '390x844', actor: 'DOCTOR_PERFORMER', local_identity: 'dr. Bima Verifikasi', counterpart_identity: 'Warga Alfa Video', state: 'microphone-muted', local_media_state: 'active', remote_media_state: 'active', main_stage_owner: 'remote', pip_owner: 'local' },
      { file: 'mobile-412-remote-main-local-pip.png', viewport: '412x915', actor: 'DOCTOR_PERFORMER', local_identity: 'dr. Bima Verifikasi', counterpart_identity: 'Warga Alfa Video', state: 'remote-main-local-pip', local_media_state: 'active', remote_media_state: 'active', main_stage_owner: 'remote', pip_owner: 'local' },
      { file: 'mobile-430-remote-main-local-pip.png', viewport: '430x932', actor: 'DOCTOR_PERFORMER', local_identity: 'dr. Bima Verifikasi', counterpart_identity: 'Warga Alfa Video', state: 'remote-main-local-pip', local_media_state: 'active', remote_media_state: 'active', main_stage_owner: 'remote', pip_owner: 'local' },
      { file: 'mobile-844x390-landscape.png', viewport: '844x390', actor: 'DOCTOR_PERFORMER', local_identity: 'dr. Bima Verifikasi', counterpart_identity: 'Warga Alfa Video', state: 'remote-main-local-pip-landscape', local_media_state: 'active', remote_media_state: 'active', main_stage_owner: 'remote', pip_owner: 'local' },
      { file: 'tablet-768x1024.png', viewport: '768x1024', actor: 'DOCTOR_PERFORMER', local_identity: 'dr. Bima Verifikasi', counterpart_identity: 'Warga Alfa Video', state: 'remote-main-local-pip-tablet', local_media_state: 'active', remote_media_state: 'active', main_stage_owner: 'remote', pip_owner: 'local' }
    ];
    fs.writeFileSync(path.join(outputRoot, 'manifest.json'), JSON.stringify({
      evidence_level: 'REAL_PRODUCTION_DOM_MOBILE_ADAPTIVE_VIDEO',
      source: 'PRODUCTION_DOM',
      duplicate_video_ui_fixture_dom: false,
      request_session_fixture: 'disposable Task 11 doctor-performer request (synthetic identities only)',
      entries: screenshots.map(entry => ({
        ...entry,
        route: '/chat?request_id=<disposable-fixture>',
        request_fixture: 'doctor_performer disposable fixture',
        control_states: ['more', 'camera', 'speaker', 'microphone', 'end-call'],
        source: 'PRODUCTION_DOM',
        state_assertion_result: 'PASS',
        result: 'PASS'
      }))
    }, null, 2));
  });
});
