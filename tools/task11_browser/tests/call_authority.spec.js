const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const {
  fixtureData,
  loginActor,
  post,
  expectDenied,
} = require('./fixtures');
const { actorPage, enroll, assign } = require('../support/lifecycle');

let data;

test.beforeAll(() => {
  // One disposable fixture set is shared by this matrix. Recreating the same
  // run between tests races the single-process CI3 login/session store.
  data = fixtureData();
});

function decodeJwtPayload(token) {
  const encoded = String(token || '').split('.')[1] || '';
  const normalized = encoded.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(encoded.length / 4) * 4, '=');
  return JSON.parse(Buffer.from(normalized, 'base64').toString('utf8'));
}

async function trySpecialActorPage(browser, actor) {
  const context = await browser.newContext();
  const page = await context.newPage();
  try {
    await loginActor(page, actor);
    return { page, context };
  } catch (error) {
    await context.close();
    expect(String(error)).toMatch(/login_failed_/);
    return null;
  }
}

async function openChat(page, requestId) {
  const response = await page.goto(`/chat?request_id=${requestId}`, { waitUntil: 'domcontentloaded' });
  expect(response.status()).toBe(200);
  await expect(page.locator('body')).toBeVisible();
  return page.locator('.doclinc-call-start');
}

async function startCall(page, requestId, callType) {
  const result = await post(page, '/home_nakes/start_livekit_call', {
    request_id: requestId,
    call_type: callType,
  });
  expect(result.response.status(), `${callType} initiation response`).toBe(200);
  expect(result.json && result.json.success).toBeTruthy();
  expect(result.json.call_type).toBe(callType);
  expect(result.json.room).toBe(`doclinc-consultation-${requestId}`);
  return result.json;
}

async function incoming(page, requestId) {
  const response = await page.request.get(`/home/livekit_incoming_call?request_id=${requestId}`);
  expect(response.status()).toBe(200);
  const body = await response.json();
  expect(body.success).toBeTruthy();
  expect(body.has_incoming).toBeTruthy();
  expect(body.request_id).toBe(requestId);
  return body;
}

async function expectDeniedFor(page, actor, fields) {
  const result = await post(page, '/home_nakes/start_livekit_call', fields);
  try {
    return await expectDenied(result);
  } catch (error) {
    let body = '';
    try { body = (await result.response.text()).slice(0, 180); } catch (_) { /* already consumed */ }
    throw new Error(`${actor}: ${error.message}; response=${body}`);
  }
}

test('provider-only call UI exposes initiation only to request-authorized clinicians', async ({ browser }) => {
  const authorityRequest = data.requests.authority;
  const performerRequest = data.requests.doctor_performer;

  const warga = await actorPage(browser, data, 'WARGA');
  const wargaButtons = await openChat(warga.page, authorityRequest);
  await expect(wargaButtons).toHaveCount(0);
  await expect(warga.page.locator('#doclincIncomingCall')).toHaveCount(1);
  await expect(warga.page.locator('#doclincIncomingAnswer')).toHaveText('Terima');
  await expect(warga.page.locator('#doclincIncomingReject')).toHaveText('Tolak');

  const rd = await actorPage(browser, data, 'RD');
  await expect(await openChat(rd.page, authorityRequest)).toHaveCount(2);

  const doctor = await actorPage(browser, data, 'DOCTOR_PERFORMER');
  await expect(await openChat(doctor.page, performerRequest)).toHaveCount(2);

  const command = await actorPage(browser, data, 'COMMAND_CENTER');
  await command.page.goto(`/chat?request_id=${authorityRequest}`, { waitUntil: 'domcontentloaded' });
  await expect(command.page.locator('.doclinc-call-start')).toHaveCount(0);
  expect(command.page.url()).not.toContain('/chat');

  const unrelated = await actorPage(browser, data, 'UNRELATED_DOCTOR');
  await unrelated.page.goto(`/chat?request_id=${authorityRequest}`, { waitUntil: 'domcontentloaded' });
  await expect(unrelated.page.locator('.doclinc-call-start')).toHaveCount(0);

  await Promise.all([warga.context.close(), rd.context.close(), doctor.context.close(), command.context.close(), unrelated.context.close()]);
});

test('call authority server matrix enforces request-bound Voice and Video initiation', async ({ browser }) => {
  const authorityRequest = data.requests.authority;
  const secondRequest = data.requests.visit_happy;
  const performerRequest = data.requests.doctor_performer;

  const rd = await actorPage(browser, data, 'RD');
  const warga = await actorPage(browser, data, 'WARGA');
  const command = await actorPage(browser, data, 'COMMAND_CENTER');
  const unrelated = await actorPage(browser, data, 'UNRELATED_DOCTOR');
  // Inactive/unclassified fixtures may be rejected at login (the first
  // server-side barrier) or, when the current login policy permits access,
  // must still be denied at call initiation.
  const specialPages = [];
  for (const actorName of ['INACTIVE', 'UNCLASSIFIED']) {
    const actorContext = await trySpecialActorPage(browser, data.special[actorName]);
    if (actorContext) specialPages.push({ actorName, ...actorContext });
  }

  const voice = await startCall(rd.page, authorityRequest, 'audio');
  const video = await startCall(rd.page, secondRequest, 'video');
  expect(voice.call_type).toBe('audio');
  expect(video.call_type).toBe('video');

  const deniedActors = [
    ['WARGA', warga.page], ['COMMAND_CENTER', command.page], ['UNRELATED_DOCTOR', unrelated.page],
    ...specialPages.map((entry) => [entry.actorName, entry.page]),
  ];
  for (const [actor, page] of deniedActors) {
    await expectDeniedFor(page, actor, {
      request_id: authorityRequest,
      call_type: 'audio',
      role: 'dokter',
      callerType: 'dokter',
    });
    await expectDeniedFor(page, actor, {
      request_id: authorityRequest,
      call_type: 'video',
      role: 'dokter',
      callerType: 'dokter',
    });
  }

  // A responsible doctor may not initiate against a separate request owned by
  // another responsible doctor/performer.
  await expectDenied(await post(rd.page, '/home_nakes/start_livekit_call', {
    request_id: performerRequest,
    call_type: 'video',
  }));

  // The browser/XHR path cannot elevate a Warga session by supplying a role.
  const spoofed = await warga.page.evaluate(async ({ requestId }) => {
    const token = document.querySelector('meta[name="doclinc-csrf-token"]')?.getAttribute('content') || '';
    const form = new URLSearchParams({ request_id: String(requestId), call_type: 'video', role: 'dokter', isDoctor: '1', doclinc_csrf_token: token });
    const response = await fetch('/home_nakes/start_livekit_call', { method: 'POST', credentials: 'same-origin', body: form, headers: { Accept: 'application/json' } });
    return { status: response.status, body: await response.json() };
  }, { requestId: authorityRequest });
  expect(spoofed.status).toBe(403);
  expect(spoofed.body.success).toBeFalsy();

  await Promise.all([rd.context.close(), warga.context.close(), command.context.close(), unrelated.context.close(), ...specialPages.map((entry) => entry.context.close())]);
});

test('Warga receives, accepts, declines, and retains callee media for authorized calls', async ({ browser }) => {
  const voiceRequest = data.requests.stale;
  const videoRequest = data.requests.idempotency;
  const rejectRequest = data.requests.post_closure;

  const rd = await actorPage(browser, data, 'RD');
  const warga = await actorPage(browser, data, 'WARGA');

  const voice = await startCall(rd.page, voiceRequest, 'audio');
  const incomingVoice = await incoming(warga.page, voiceRequest);
  expect(incomingVoice.call_type).toBe('audio');
  const acceptedVoice = await post(warga.page, '/home/answer_livekit_call', { call_id: incomingVoice.call_id });
  expect(acceptedVoice.response.status()).toBe(200);
  expect(acceptedVoice.json.success).toBeTruthy();
  expect(acceptedVoice.json.call_type).toBe('audio');
  expect(decodeJwtPayload(acceptedVoice.json.token).video.canPublish).toBe(true);
  expect(decodeJwtPayload(acceptedVoice.json.token).video.canSubscribe).toBe(true);
  expect(voice.call_id).toBe(incomingVoice.call_id);

  const video = await startCall(rd.page, videoRequest, 'video');
  const incomingVideo = await incoming(warga.page, videoRequest);
  expect(incomingVideo.call_type).toBe('video');
  const acceptedVideo = await post(warga.page, '/home/answer_livekit_call', { call_id: incomingVideo.call_id });
  expect(acceptedVideo.response.status()).toBe(200);
  expect(acceptedVideo.json.success).toBeTruthy();
  expect(acceptedVideo.json.call_type).toBe('video');
  expect(decodeJwtPayload(acceptedVideo.json.token).video.canPublish).toBe(true);
  expect(decodeJwtPayload(acceptedVideo.json.token).video.canSubscribe).toBe(true);
  expect(video.call_id).toBe(incomingVideo.call_id);

  const rejectStart = await startCall(rd.page, rejectRequest, 'audio');
  const incomingReject = await incoming(warga.page, rejectRequest);
  expect(incomingReject.call_id).toBe(rejectStart.call_id);
  const rejected = await post(warga.page, '/home/reject_livekit_call', { call_id: incomingReject.call_id });
  expect(rejected.response.status()).toBe(200);
  expect(rejected.json.status).toBe('rejected');

  // The accepted callee can leave through the existing client room disconnect
  // path; the server-side status endpoint remains bound to the callee.
  const status = await post(warga.page, '/home/livekit_call_status', { call_id: acceptedVideo.json.call_id });
  expect(status.response.status()).toBe(200);
  expect(status.json.call_id).toBe(acceptedVideo.json.call_id);

  await Promise.all([rd.context.close(), warga.context.close()]);
});

test('assigned personal Nakes may originate without granting Command Center authority', async ({ browser }) => {
  const requestId = data.requests.nondoctor_performer;
  const rd = await actorPage(browser, data, 'RD');
  await enroll(rd.page, requestId, `call-auth-disposition-${data.suffix}`, 'Perawat');
  await assign(rd.page, requestId, data.actors.NONDOCTOR_PERFORMER.id, `call-auth-assignment-${data.suffix}`);

  const performer = await actorPage(browser, data, 'NONDOCTOR_PERFORMER');
  await expect(await openChat(performer.page, requestId)).toHaveCount(2);
  const initiated = await startCall(performer.page, requestId, 'video');
  expect(initiated.call_type).toBe('video');

  await Promise.all([rd.context.close(), performer.context.close()]);
});

test('call-initiation implementation uses canonical request authority and keeps callee media separate', () => {
  const repo = path.resolve(__dirname, '..', '..', '..');
  const controller = fs.readFileSync(path.join(repo, 'application/modules/home_nakes/controllers/Home_nakes.php'), 'utf8');
  const view = fs.readFileSync(path.join(repo, 'application/modules/chat/views/thread_v.php'), 'utf8');
  const helper = fs.readFileSync(path.join(repo, 'application/helpers/livekit_helper.php'), 'utf8');
  expect(controller).toContain('doclinc_request_is_handled_by_nakes');
  expect(controller).toContain('Call_session_m->start_or_reuse');
  expect(helper).toContain('doclinc_livekit_authorized_request');
  expect(view).toContain('$can_start_call');
  expect(view).toContain('$can_receive_call');
  expect(view).toContain('endLocalCall');
  expect(controller).not.toMatch(/role\s*===\s*["']warga["'][\s\S]{0,120}start_or_reuse/);
});
