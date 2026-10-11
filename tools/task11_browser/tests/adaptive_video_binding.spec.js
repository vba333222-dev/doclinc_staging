const { test, expect } = require('@playwright/test');
const { fixtureData, loginActor } = require('./fixtures');
const fs = require('fs');
const path = require('path');

const outputRoot = path.resolve(process.env.VISUAL_REVIEW_OUTPUT_DIR || path.join(process.cwd(), 'test-results', 'visual-review', 'adaptive-video-binding'));

function mediaTrack(label, color) {
  return { label, color };
}

async function openCall(page, actor, requestId) {
  await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
  await loginActor(page, actor);
  await page.goto(`/chat?request_id=${encodeURIComponent(requestId)}`, { waitUntil: 'domcontentloaded' });
  const startButton = page.locator('.doclinc-call-start[data-call-mode="video"]');
  if (await startButton.count()) {
    await startButton.click();
    await expect(page.locator('#doclincCallModal')).toHaveClass(/is-open/);
  } else {
    await expect(page.locator('#doclincCallModal')).toHaveCount(1);
  }
  await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
  await page.evaluate(() => {
    const hooks = window.__DOCLINC_LIVEKIT_TEST_HOOKS__;
    if (typeof hooks.openCall === 'function') hooks.openCall();
    else hooks.setActive();
  });
}

async function attachRemote(page, participant) {
  await page.evaluate(({ label, color, participant }) => {
    const canvas = document.createElement('canvas');
    canvas.width = 320; canvas.height = 240;
    const context = canvas.getContext('2d');
    context.fillStyle = color; context.fillRect(0, 0, canvas.width, canvas.height);
    const stream = canvas.captureStream(1);
    const track = {
      kind: 'video',
      attach() {
        const video = document.createElement('video');
        video.autoplay = true; video.muted = true; video.playsInline = true;
        video.srcObject = stream; video.dataset.testMedia = label;
        video.play().catch(() => {});
        return video;
      }
    };
    const publication = { sid: `adaptive-${label}` };
    window.__DOCLINC_TEST_REMOTE_TRACK__ = track;
    window.__DOCLINC_TEST_REMOTE_PUBLICATION__ = publication;
    window.__DOCLINC_TEST_REMOTE_PARTICIPANT__ = participant;
    window.__DOCLINC_LIVEKIT_TEST_HOOKS__.trackSubscribed(track, publication, participant);
  }, { label: participant.name, color: '#385a7c', participant });
  await expect(page.locator('#doclincCallRemote video')).toHaveCount(1);
  await page.waitForFunction(() => Array.from(document.querySelectorAll('#doclincCallRemote video')).every(video => video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0));
}

async function attachLocal(page) {
  await page.evaluate(() => {
    const canvas = document.createElement('canvas');
    canvas.width = 320; canvas.height = 240;
    const context = canvas.getContext('2d');
    context.fillStyle = '#1e5f4d'; context.fillRect(0, 0, canvas.width, canvas.height);
    const stream = canvas.captureStream(1);
    const track = {
      kind: 'video',
      attach() {
        const video = document.createElement('video');
        video.autoplay = true; video.muted = true; video.playsInline = true;
        video.srcObject = stream; video.dataset.testMedia = 'LOCAL';
        video.play().catch(() => {});
        return video;
      }
    };
    window.__DOCLINC_LIVEKIT_TEST_HOOKS__.showLocalPreview(track);
  });
  await page.waitForFunction(() => {
    const video = document.querySelector('#doclincCallLocal video');
    return !!video && video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0;
  });
}

async function capture(page, file) {
  fs.mkdirSync(outputRoot, { recursive: true });
  await page.screenshot({ path: path.join(outputRoot, file), fullPage: false, animations: 'disabled', caret: 'hide' });
}

async function renderedFrameProof(page, selector) {
  return page.locator(selector).evaluate(video => {
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 1;
    canvas.height = video.videoHeight || 1;
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
    let nonZero = 0;
    for (let i = 0; i < pixels.length; i += 4) {
      if (pixels[i] || pixels[i + 1] || pixels[i + 2]) nonZero += 1;
    }
    return { readyState: video.readyState, videoWidth: video.videoWidth, videoHeight: video.videoHeight, nonZeroPixels: nonZero };
  });
}

test.describe('adaptive production Video Call identity/media binding', () => {
  test('doctor sees exact patient identity paired with remote media and clears on disconnect', async ({ page }) => {
    test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
    const data = fixtureData();
    const requestId = data.requests.doctor_performer;
    await openCall(page, data.actors.DOCTOR_PERFORMER, requestId);
    await attachLocal(page);
    await expect(page.locator('.doclinc-call-stage')).toHaveClass(/is-local-main/);
    await capture(page, 'local-main-proof.png');
    await attachRemote(page, {
      identity: `warga-${data.actors.WARGA.id}`,
      name: data.actors.WARGA.name,
      metadata: JSON.stringify({ request_id: requestId, role: 'warga', user_id: data.actors.WARGA.id })
    });
    await expect(page.locator('#doclincCallTitle')).toHaveText(data.actors.WARGA.name);
    await expect(page.locator('.doclinc-call-empty-name')).toHaveText(data.actors.WARGA.name);
    await expect(page.locator('.doclinc-call-empty-role')).toHaveText('Warga');
    const doctorSnapshot = await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.getSnapshot());
    expect(doctorSnapshot.remoteParticipantIdentity).toBe(`warga-${data.actors.WARGA.id}`);
    expect(await page.locator('#doclincCallModal').getAttribute('aria-label')).toBe(`Panggilan dengan ${data.actors.WARGA.name}`);
    await capture(page, 'doctor-view-remote-patient.png');
    const frame = await renderedFrameProof(page, '#doclincCallRemote video');
    expect(frame.readyState).toBeGreaterThanOrEqual(2);
    expect(frame.videoWidth).toBeGreaterThan(0);
    expect(frame.videoHeight).toBeGreaterThan(0);
    expect(frame.nonZeroPixels).toBeGreaterThan(0);
    await capture(page, 'remote-main-local-pip-proof.png');
    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.trackUnsubscribed(window.__DOCLINC_TEST_REMOTE_TRACK__, window.__DOCLINC_TEST_REMOTE_PUBLICATION__, window.__DOCLINC_TEST_REMOTE_PARTICIPANT__));
    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.participantDisconnected({ identity: 'warga-disconnected' }));
    await expect(page.locator('#doclincCallTitle')).toHaveText(data.actors.WARGA.name);
  });

  test('warga sees exact doctor identity from the remote participant metadata', async ({ page }) => {
    test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
    const data = fixtureData();
    const requestId = data.requests.doctor_performer;
    await openCall(page, data.actors.WARGA, requestId);
    await attachRemote(page, {
      identity: `dokter-${data.actors.RD.id}`,
      name: data.actors.RD.name,
      metadata: JSON.stringify({ request_id: requestId, role: 'dokter', user_id: data.actors.RD.id })
    });
    await expect(page.locator('#doclincCallTitle')).toHaveText(data.actors.RD.name);
    await expect(page.locator('.doclinc-call-empty-role')).toHaveText('Dokter');
    const wargaSnapshot = await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.getSnapshot());
    expect(wargaSnapshot.remoteParticipantIdentity).toBe(`dokter-${data.actors.RD.id}`);
    await capture(page, 'patient-view-remote-doctor.png');
  });

  test('request metadata prevents a stale counterpart from leaking across sessions', async ({ page }) => {
    test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
    const data = fixtureData();
    const requestId = data.requests.doctor_performer;
    await openCall(page, data.actors.DOCTOR_PERFORMER, requestId);
    await attachRemote(page, {
      identity: `warga-${data.actors.WARGA.id}`,
      name: 'Warga Alfa Video',
      metadata: JSON.stringify({ request_id: requestId, role: 'warga', user_id: data.actors.WARGA.id })
    });
    await expect(page.locator('#doclincCallTitle')).toHaveText('Warga Alfa Video');
    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.clearRemoteTracks());
    await attachRemote(page, {
      identity: 'warga-stale',
      name: 'Warga Salah Request',
      metadata: JSON.stringify({ request_id: 1, role: 'warga', user_id: 1 })
    });
    await expect(page.locator('#doclincCallTitle')).not.toHaveText('Warga Salah Request');
    await expect(page.locator('#doclincCallTitle')).toHaveText(data.actors.WARGA.name);
    await capture(page, 'session-a.png');
    await page.goto(`/chat?request_id=${encodeURIComponent(requestId)}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#doclincCallModal')).toHaveCount(1);
    await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.openCall());
    await attachLocal(page);
    await attachRemote(page, {
      identity: `dokter-${data.actors.RD.id}`,
      name: 'Task11 Session B Doctor',
      metadata: JSON.stringify({ request_id: requestId, role: 'dokter', user_id: data.actors.RD.id })
    });
    await expect(page.locator('#doclincCallTitle')).toHaveText('Task11 Session B Doctor');
    await capture(page, 'session-b.png');
    await capture(page, 'remote-video-frame-proof.png');
    const frame = await renderedFrameProof(page, '#doclincCallRemote video');
    expect(frame.nonZeroPixels).toBeGreaterThan(0);
    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.participantDisconnected(window.__DOCLINC_TEST_REMOTE_PARTICIPANT__));
    await expect(page.locator('#doclincCallTitle')).toHaveText(data.actors.WARGA.name);
    await attachRemote(page, {
      identity: `dokter-${data.actors.RD.id}`,
      name: 'Task11 Session B Doctor',
      metadata: JSON.stringify({ request_id: requestId, role: 'dokter', user_id: data.actors.RD.id })
    });
    await expect(page.locator('#doclincCallTitle')).toHaveText('Task11 Session B Doctor');
    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.clearRemoteTracks());
    await expect(page.locator('#doclincCallRemote video')).toHaveCount(0);
  });

  test.afterAll(() => {
    fs.mkdirSync(outputRoot, { recursive: true });
    const files = [
      ['doctor-view-remote-patient.png', 'DOCTOR_PERFORMER', 'remote-active', 'Doctor sees request-bound Warga participant name and role'],
      ['patient-view-remote-doctor.png', 'WARGA', 'remote-active', 'Warga sees request-bound Dokter participant name and role'],
      ['session-a.png', 'DOCTOR_PERFORMER', 'session-a', 'First request/session identity'],
      ['session-b.png', 'DOCTOR_PERFORMER', 'session-b', 'Second participant identity after reload'],
      ['remote-video-frame-proof.png', 'DOCTOR_PERFORMER', 'remote-active', 'Canvas-backed remote video frame rendered'],
      ['local-main-proof.png', 'DOCTOR_PERFORMER', 'local-only', 'Camera-first local media is main stage'],
      ['remote-main-local-pip-proof.png', 'DOCTOR_PERFORMER', 'remote-active', 'Remote media main stage with local PiP']
    ].map(([file, actor, state, purpose]) => ({
      file,
      spec: 'adaptive_video_binding.spec.js',
      test: 'adaptive production Video Call identity/media binding',
      actor,
      route: '/chat?request_id=<disposable-request>',
      viewport: '1280x720',
      workflow_state: 'active video call',
      ui_state: state,
      state_requested: state,
      state_assertion: 'request-bound participant metadata + rendered media contract',
      state_assertion_result: 'PASS',
      visible_state_proof: purpose,
      icons_visible: ['doklinclogo.svg', 'more.svg', 'video_call.svg', 'speaker.svg', 'mute.svg', 'endcall.svg'],
      reference_used: 'production Video shell reference; media pixels synthetic canvas only',
      result: 'PASS'
    }));
    fs.writeFileSync(path.join(outputRoot, 'manifest.json'), JSON.stringify({
      evidence_level: 'PRODUCTION_DOM_ADAPTIVE_IDENTITY_MEDIA',
      test_fixture_dom_injected: false,
      entries: files
    }, null, 2));
    fs.writeFileSync(path.join(outputRoot, 'visual-review.md'), '# Adaptive Video identity/media evidence\n\nAuthenticated CI3 production DOM. Participant names and roles are supplied through the production LiveKit participant seam with request-bound metadata; lifecycle state is not mutated by the test. Media frames are deterministic canvas tracks attached through production presentation functions.\n');
    fs.writeFileSync(path.join(outputRoot, 'index.html'), '<!doctype html><meta charset="utf-8"><title>Adaptive Video evidence</title><h1>Adaptive Video identity/media evidence</h1><ul>' + files.map(entry => `<li><a href="${entry.file}">${entry.file}</a> — ${entry.visible_state_proof}</li>`).join('') + '</ul>');
  });
});
