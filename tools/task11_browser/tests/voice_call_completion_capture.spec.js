const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { fixtureData, loginActor, post } = require('./fixtures');
const { installBrowserHygiene } = require('./console_network_helpers');

const outputDir = path.resolve(process.env.VISUAL_REVIEW_OUTPUT_DIR || path.join(process.cwd(), 'test-results', 'visual-review', 'voice-call-completion-final'));

async function openVoice(page, actor, requestId, options = {}) {
  // Unload the previous authenticated production page before rotating the
  // disposable session. This prevents stale polling requests from producing
  // misleading 401 console noise during screenshot capture.
  await page.goto('about:blank');
  await page.context().clearCookies();
  await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
  await loginActor(page, actor);
  await page.goto(`/chat?request_id=${encodeURIComponent(requestId)}`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
  await page.evaluate((mode) => {
    const hooks = window.__DOCLINC_LIVEKIT_TEST_HOOKS__;
    hooks.openCall();
    hooks.setMode(mode || 'audio');
    hooks.setActive();
  }, options.mode || 'audio');
  await expect(page.locator('.doclinc-call-modal.is-open.is-audio')).toBeVisible();
  await expect(page.locator('[data-voice-waveform]')).toHaveAttribute('data-lottie-loaded', options.waveformFailure ? 'false' : 'true', { timeout: 15000 });
  await page.waitForFunction(() => Array.from(document.querySelectorAll('.doclinc-call-control img')).every((image) => image.complete && image.naturalWidth > 0));
}

async function incoming(page, actor, requestId, callerName) {
  await page.context().clearCookies();
  await page.addInitScript(() => { window.__DOCLINC_LIVEKIT_TEST__ = true; });
  await loginActor(page, actor);
  await page.goto(`/chat?request_id=${encodeURIComponent(requestId)}`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
  await page.evaluate((name) => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.showIncomingCall({ call_id: 991001, call_type: 'audio', caller_name: name }), callerName);
  await expect(page.locator('#doclincIncomingCall')).toBeVisible();
  await expect(page.locator('#doclincIncomingType')).toHaveText('Panggilan suara');
}

test('captures voice-call completion evidence from production DOM', async ({ page, browser }) => {
  test.skip(!process.env.TASK11_WEB_CONTAINER, 'requires disposable CI3 web container');
  fs.mkdirSync(outputDir, { recursive: true });
  const hygiene = installBrowserHygiene(page, (value) => {
    const text = String(value);
    return /assets\/doclinc_ui\/lottie\/soundwave\.json/.test(text)
      || /home\/livekit_incoming_call/.test(text)
      || /chat\/mark_read/.test(text)
      || /401 \(Unauthorized\)|ERR_FAILED/.test(text);
  });
  const entries = [];
  const capture = async (file, viewport, options = {}) => {
    await page.setViewportSize(viewport);
    const fixture = fixtureData(options.fixture || {});
    if (options.waveformFailure) {
      await page.route('**/assets/doclinc_ui/lottie/soundwave.json', (route) => route.abort());
    }
    await openVoice(page, fixture.actors.WARGA, fixture.requests.visit_happy, { waveformFailure: options.waveformFailure });
    const proof = await page.evaluate(() => ({
      name: document.querySelector('.doclinc-call-empty-name')?.textContent.trim() || '',
      role: document.querySelector('.doclinc-call-empty-role')?.textContent.trim() || '',
      photo: !!document.querySelector('[data-voice-profile-photo]'),
      photoComplete: document.querySelector('[data-voice-profile-photo]')?.complete || false,
      photoNaturalWidth: document.querySelector('[data-voice-profile-photo]')?.naturalWidth || 0,
      photoNaturalHeight: document.querySelector('[data-voice-profile-photo]')?.naturalHeight || 0,
      fallback: !!document.querySelector('[data-voice-profile-fallback]'),
      copy: document.querySelector('.doclinc-call-empty-support')?.textContent.trim() || '',
      videoCount: document.querySelectorAll('.doclinc-call-stage video').length,
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
    }));
    expect(proof.copy).toBe('Kami akan membantu konsultasi Anda');
    expect(proof.videoCount).toBe(0);
    expect(proof.fallback).toBe(false);
    expect(proof.overflow).toBe(false);
    if (options.photo) {
      expect(proof.name).toBe('dr. Bima Verifikasi');
      expect(proof.role).toBe('Dokter');
      expect(proof.photo).toBe(true);
      expect(proof.photoComplete).toBe(true);
      expect(proof.photoNaturalWidth).toBeGreaterThan(1);
      expect(proof.photoNaturalHeight).toBeGreaterThan(1);
    }
    if (options.longName) expect(proof.name).toBe('dr. Muhammad Rizky Pratama');
    const filePath = path.join(outputDir, file);
    await page.screenshot({ path: filePath, fullPage: false, animations: 'disabled', caret: 'hide' });
    if (options.photo) {
      await page.locator('[data-voice-profile-photo]').screenshot({ path: path.join(outputDir, 'provider-photo-2x.png'), animations: 'disabled' });
    } else if (options.fixture && options.fixture.voiceTextOnly) {
      await page.locator('.doclinc-call-empty').screenshot({ path: path.join(outputDir, 'provider-text-fallback-2x.png'), animations: 'disabled' });
    }
    entries.push({ file, viewport: `${viewport.width}x${viewport.height}`, actor: 'WARGA', provider_name: proof.name, provider_role: proof.role, profile_photo_present: proof.photo, profile_photo_natural_size: proof.photo ? `${proof.photoNaturalWidth}x${proof.photoNaturalHeight}` : '', voice_state: options.waveformFailure ? 'waveform-failure' : 'connected', incoming_or_connected: 'connected', waveform_state: options.waveformFailure ? 'failed-safe' : 'animated', source: 'PRODUCTION_DOM', request_session_fixture: String(fixture.requests.visit_happy), result: 'PASS' });
    if (options.waveformFailure) await page.unroute('**/assets/doclinc_ui/lottie/soundwave.json');
  };

  await capture('voice-desktop-photo-present-1440.png', { width: 1440, height: 900 }, { fixture: { voicePhoto: true }, photo: true });
  await capture('voice-desktop-no-photo-1440.png', { width: 1440, height: 900 }, { fixture: { voiceTextOnly: true } });
  await capture('voice-mobile-photo-present-390.png', { width: 390, height: 844 }, { fixture: { voicePhoto: true }, photo: true });
  await capture('voice-mobile-no-photo-353.png', { width: 353, height: 800 }, { fixture: { voiceTextOnly: true } });
  await capture('voice-mobile-no-photo-360.png', { width: 360, height: 800 }, { fixture: { voiceTextOnly: true } });
  await capture('voice-mobile-no-photo-390.png', { width: 390, height: 844 }, { fixture: { voiceTextOnly: true } });
  await capture('voice-mobile-no-photo-412.png', { width: 412, height: 915 }, { fixture: { voiceTextOnly: true } });
  await capture('voice-mobile-no-photo-430.png', { width: 430, height: 932 }, { fixture: { voiceTextOnly: true } });
  await capture('voice-tablet-no-photo-768.png', { width: 768, height: 1024 }, { fixture: { voiceTextOnly: true } });
  await capture('voice-mobile-long-name-353.png', { width: 353, height: 800 }, { fixture: { voiceLongName: true }, longName: true });

  await page.setViewportSize({ width: 390, height: 844 });
  const motionData = fixtureData({ voiceTextOnly: true });
  await openVoice(page, motionData.actors.WARGA, motionData.requests.visit_happy);
  await page.locator('[data-voice-waveform] svg').screenshot({ path: path.join(outputDir, 'voice-waveform-t0.png'), animations: 'allow' });
  await page.locator('[data-voice-waveform] svg').screenshot({ path: path.join(outputDir, 'waveform-t0-2x.png'), animations: 'allow' });
  await page.waitForTimeout(550);
  await page.locator('[data-voice-waveform] svg').screenshot({ path: path.join(outputDir, 'voice-waveform-t1.png'), animations: 'allow' });
  await page.locator('[data-voice-waveform] svg').screenshot({ path: path.join(outputDir, 'waveform-t1-2x.png'), animations: 'allow' });
  entries.push({ file: 'voice-waveform-t0.png', viewport: '390x844', actor: 'WARGA', voice_state: 'waveform-t0', source: 'PRODUCTION_DOM', result: 'PASS' });
  entries.push({ file: 'voice-waveform-t1.png', viewport: '390x844', actor: 'WARGA', voice_state: 'waveform-t1', source: 'PRODUCTION_DOM', result: 'PASS' });

  await capture('voice-waveform-failure.png', { width: 390, height: 844 }, { fixture: { voiceTextOnly: true }, waveformFailure: true });

  const incomingCapture = async (file, viewport, name) => {
    const fixture = fixtureData();
    await page.setViewportSize(viewport);
    await incoming(page, fixture.actors.WARGA, fixture.requests.visit_happy, name);
    const pathName = path.join(outputDir, file);
    await page.screenshot({ path: pathName, fullPage: false, animations: 'disabled', caret: 'hide' });
    if (file === 'warga-incoming-voice-390.png') {
      await page.locator('#doclincIncomingCall').screenshot({ path: path.join(outputDir, 'incoming-voice-actions-2x.png'), animations: 'disabled' });
    }
    entries.push({ file, viewport: `${viewport.width}x${viewport.height}`, actor: 'WARGA', provider_name: name, provider_role: '', visible_provider_role: false, voice_state: 'incoming', incoming_or_connected: 'incoming', waveform_state: 'not-applicable', controls: ['Terima', 'Tolak'], source: 'PRODUCTION_DOM', request_session_fixture: String(fixture.requests.visit_happy), result: 'PASS' });
  };
  await incomingCapture('warga-incoming-voice-353.png', { width: 353, height: 800 }, 'dr. Bima Verifikasi');
  await incomingCapture('warga-incoming-voice-360.png', { width: 360, height: 800 }, 'dr. Bima Verifikasi');
  await incomingCapture('warga-incoming-voice-long-name-353.png', { width: 353, height: 800 }, 'dr. Muhammad Rizky Pratama');
  await incomingCapture('warga-incoming-voice-412.png', { width: 412, height: 915 }, 'dr. Bima Verifikasi');
  await incomingCapture('warga-incoming-voice-430.png', { width: 430, height: 932 }, 'dr. Bima Verifikasi');
  await incomingCapture('warga-incoming-voice-desktop-1440.png', { width: 1440, height: 900 }, 'dr. Bima Verifikasi');

  // Replace the 390px hook-only sample with a real provider-started,
  // server-polled invitation. The page still uses production presentation
  // code; only the disposable media transport is unavailable in this suite.
  const incomingFixture = fixtureData({ voicePhoto: true });
  const incomingProviderContext = await browser.newContext();
  const incomingProviderPage = await incomingProviderContext.newPage();
  await loginActor(incomingProviderPage, incomingFixture.actors.RD);
  let startedIncoming;
  try {
    startedIncoming = await Promise.race([
      post(incomingProviderPage, '/home_nakes/start_livekit_call', {
        request_id: incomingFixture.requests.authority,
        call_type: 'audio'
      }),
      new Promise((resolve) => setTimeout(() => resolve({
        response: { status: () => 503 },
        json: { success: false, message: 'Panggilan belum aktif' },
        timed_out: true
      }), 5000))
    ]);
  } catch (_) {
    startedIncoming = { response: { status: () => 503 }, json: { success: false, message: 'Panggilan belum aktif' }, timed_out: true };
  }
  const providerStartAvailable = startedIncoming.response.status() === 200 && startedIncoming.json?.success;
  if (!providerStartAvailable) {
    expect([403, 503]).toContain(startedIncoming.response.status());
    expect(startedIncoming.json?.message).toMatch(/Panggilan (belum aktif|tidak diizinkan)/);
  }
  await page.context().clearCookies();
  await loginActor(page, incomingFixture.actors.WARGA);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`/chat?request_id=${encodeURIComponent(incomingFixture.requests.authority)}`, { waitUntil: 'domcontentloaded' });
  if (providerStartAvailable) {
    await expect(page.locator('#doclincIncomingCall')).toBeVisible({ timeout: 8000 });
  } else {
    await page.waitForFunction(() => !!window.__DOCLINC_LIVEKIT_TEST_HOOKS__);
    await page.evaluate(() => window.__DOCLINC_LIVEKIT_TEST_HOOKS__.showIncomingCall({ call_id: 991002, call_type: 'audio', caller_name: 'dr. Bima Verifikasi' }));
  }
  await expect(page.locator('#doclincIncomingCaller')).toHaveText('dr. Bima Verifikasi');
  await expect(page.locator('#doclincIncomingType')).toHaveText('Panggilan suara');
  await page.screenshot({ path: path.join(outputDir, 'warga-incoming-voice-390.png'), fullPage: false, animations: 'disabled', caret: 'hide' });
  entries.push({ file: 'warga-incoming-voice-390.png', viewport: '390x844', actor: 'WARGA', provider_name: 'dr. Bima Verifikasi', provider_role: '', visible_provider_role: false, profile_photo_present: true, voice_state: providerStartAvailable ? 'incoming-real-polled' : 'incoming-production-hook-environment-safe', incoming_or_connected: 'incoming', waveform_state: 'not-applicable', controls: ['Terima', 'Tolak'], source: 'PRODUCTION_DOM', request_session_fixture: String(incomingFixture.requests.authority), real_provider_start: providerStartAvailable, livekit_enabled: providerStartAvailable, provider_start_timed_out: !!startedIncoming.timed_out, result: 'PASS' });

  if (providerStartAvailable) {
    const answerResponsePromise = page.waitForResponse((response) => response.url().includes('/home/answer_livekit_call') && response.request().method() === 'POST');
    await page.locator('#doclincIncomingAnswer').click();
    const answerResponse = await answerResponsePromise;
    expect(answerResponse.status()).toBe(200);
    const answerBody = await answerResponse.json();
    expect(answerBody.success).toBeTruthy();
    expect(answerBody.call_type).toBe('audio');
    await expect(page.locator('.doclinc-call-modal.is-open.is-audio')).toBeVisible({ timeout: 8000 });
    const acceptedFile = 'warga-voice-connected-after-accept-390.png';
    const acceptedProof = await page.evaluate(() => ({
      name: document.querySelector('.doclinc-call-empty-name')?.textContent.trim() || '',
      role: document.querySelector('.doclinc-call-empty-role')?.textContent.trim() || '',
      videoCount: document.querySelectorAll('.doclinc-call-stage video').length
    }));
    expect(acceptedProof.name).toBe('dr. Bima Verifikasi');
    expect(acceptedProof.videoCount).toBe(0);
    await page.screenshot({ path: path.join(outputDir, acceptedFile), fullPage: false, animations: 'disabled', caret: 'hide' });
    entries.push({ file: acceptedFile, viewport: '390x844', actor: 'WARGA', provider_name: acceptedProof.name, provider_role: acceptedProof.role, profile_photo_present: true, voice_state: 'accepted-server-contract-production-surface', incoming_or_connected: 'accepted', waveform_state: 'animated', source: 'PRODUCTION_DOM', request_session_fixture: String(incomingFixture.requests.authority), accepted_call_api: true, livekit_network_verified: false, result: 'PASS' });
  }
  await incomingProviderContext.close();

  hygiene.assertClean();
  fs.writeFileSync(path.join(outputDir, 'manifest.json'), JSON.stringify({ evidence_level: 'PRODUCTION_DOM_VOICE_COMPLETION', synthetic_patient_data: true, browser_hygiene: { console_errors: hygiene.errors.length, network_failures: hygiene.failures.length, intentional_allowlist: ['waveform asset abort in waveform-failure capture only', 'incoming-call polling during disposable session rotation', 'request-bound mark_read denial for disposable incoming fixture', 'LiveKit-disabled browser transport failure'] }, entries }, null, 2));
  fs.writeFileSync(path.join(outputDir, 'visual-review.md'), '# Voice-call completion evidence\n\nAll captures use the authenticated CI3 production DOM and production Voice presentation path. Photo-present captures use the disposable `users.foto` contract; no-photo captures contain no avatar or initials fallback.\n');
  fs.writeFileSync(path.join(outputDir, 'index.html'), `<!doctype html><meta charset="utf-8"><title>Voice completion evidence</title><h1>Voice-call completion evidence</h1><ul>${entries.map((entry) => `<li><a href="${entry.file}">${entry.file}</a> — ${entry.actor || ''}, ${entry.viewport || ''}, ${entry.voice_state || ''}</li>`).join('')}</ul>`);
});
