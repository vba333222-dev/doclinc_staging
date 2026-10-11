const { expect } = require('@playwright/test');
const { loginActor, post, expectSuccess, surface } = require('../tests/fixtures');

async function actorPage(browser, data, name, viewport) {
  const context = await browser.newContext(viewport ? { viewport } : undefined);
  const page = await context.newPage();
  await loginActor(page, data.actors[name]);
  return { context, page };
}

async function mutate(page, path, fields) {
  const result = await post(page, path, fields);
  const body = await expectSuccess(result);
  return { ...result, body };
}

async function canonicalSurface(page, actor, requestId) {
  const result = await surface(page, actor, requestId);
  expect(result.response.status()).toBe(200);
  expect(result.json).toBeTruthy();
  return result.json.data || {};
}

async function enroll(page, requestId, key, profession = 'Perawat') {
  return mutate(page, '/clinical/disposition', {
    request_id: requestId, decision: 'visit', urgency: 'routine', required_profession: profession, idempotency_key: key,
  });
}

async function assign(page, requestId, staffId, key) {
  return mutate(page, '/home_nakes/assign_visit_performer', { request_id: requestId, staff_id: staffId, idempotency_key: key });
}

async function physical(page, requestId, status) {
  return mutate(page, '/home_nakes/update_visit_status', { request_id: requestId, visit_status: status });
}

async function vitals(page, requestId) {
  return mutate(page, '/konsultasi_nakes/save_vital_signs', {
    request_id: requestId, systolic: '120', diastolic: '80', pulse: '72', respiratory_rate: '16',
    temperature_c: '36.7', oxygen_saturation: '98', notes: 'Task11 browser synthetic',
  });
}

async function resultDraft(page, requestId) {
  const response = await page.request.get(`/konsultasi_nakes/visit_result_draft?request_id=${requestId}`);
  expect(response.status()).toBe(200);
  const json = await response.json();
  expect(json.success || json.ok || json.status === 'success').toBeTruthy();
  return json.data || {};
}

async function saveDraft(page, draft) {
  return mutate(page, '/konsultasi_nakes/visit_result_draft', {
    visit_result_id: draft.visit_result_id,
    expected_revision: draft.revision || draft.draft_revision || 0,
    observation_summary: 'Browser observed',
    findings: JSON.stringify([{ key: 'status', label: 'Status', value: 'Stable' }]),
    actions: JSON.stringify([{ key: 'advice', label: 'Advice', value: 'Routine' }]),
    performer_notes: 'Task11 browser',
  });
}

async function submitResult(page, draft, key) {
  return mutate(page, '/konsultasi_nakes/visit_result_submit', { visit_result_id: draft.visit_result_id, submission_key: key });
}

async function completeVisit(browser, data, requestKey, performerName = 'NONDOCTOR_PERFORMER', reviewerName = 'RD') {
  const rd = await actorPage(browser, data, reviewerName);
  const performer = await actorPage(browser, data, performerName);
  const requestId = data.requests[requestKey];
  const prefix = `browser-${requestKey}-${data.suffix}`;
  await enroll(rd.page, requestId, `${prefix}-disp`, performerName === 'DOCTOR_PERFORMER' ? 'dokter' : 'Perawat');
  await assign(rd.page, requestId, data.actors[performerName].id, `${prefix}-assign`);
  for (const state of ['en_route', 'arrived', 'in_service']) await physical(performer.page, requestId, state);
  await vitals(performer.page, requestId);
  await physical(performer.page, requestId, 'completed');
  const draft = await resultDraft(performer.page, requestId);
  await saveDraft(performer.page, draft);
  await submitResult(performer.page, draft, `${prefix}-submit`);
  await mutate(rd.page, '/clinical/review', { request_id: requestId, visit_result_id: draft.visit_result_id, decision: 'approved', review_notes: 'Browser approved', idempotency_key: `${prefix}-review` });
  const finalization = await mutate(rd.page, '/clinical/finalize', { request_id: requestId, idempotency_key: `${prefix}-finalize` });
  const closure = await mutate(rd.page, '/clinical/close', { request_id: requestId, idempotency_key: `${prefix}-close` });
  return { requestId, rd, performer, finalization, closure, draft };
}

module.exports = { actorPage, mutate, canonicalSurface, enroll, assign, physical, vitals, resultDraft, saveDraft, submitResult, completeVisit };
