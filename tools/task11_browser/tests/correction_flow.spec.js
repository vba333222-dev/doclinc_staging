const { test, expect } = require('@playwright/test');
const { fixtureData } = require('./fixtures');
const { actorPage, enroll, assign, physical, vitals, resultDraft, saveDraft, submitResult, mutate, canonicalSurface } = require('../support/lifecycle');

test('correction workflow reaches Completed through browser HTTP boundaries and masks Warga detail', async ({ browser }) => {
  const data = fixtureData(); const requestId = data.requests.visit_correction;
  const rd = await actorPage(browser, data, 'RD'); const performer = await actorPage(browser, data, 'NONDOCTOR_PERFORMER'); const warga = await actorPage(browser, data, 'WARGA');
  const checkpoint = async expected => expect((await canonicalSurface(warga.page, 'WARGA', requestId)).workflow_state.state).toBe(expected);
  await enroll(rd.page, requestId, `corr-disp-${data.suffix}`); await checkpoint('WAITING_PERFORMER_ASSIGNMENT');
  await assign(rd.page, requestId, data.actors.NONDOCTOR_PERFORMER.id, `corr-ass-${data.suffix}`); await checkpoint('VISIT_NOT_STARTED');
  for (const [state, expected] of [['en_route', 'VISIT_EN_ROUTE'], ['arrived', 'VISIT_ARRIVED'], ['in_service', 'VISIT_IN_SERVICE']]) { await physical(performer.page, requestId, state); await checkpoint(expected); }
  await vitals(performer.page, requestId); await checkpoint('VISIT_IN_SERVICE');
  await physical(performer.page, requestId, 'completed'); await checkpoint('WAITING_VISIT_RESULT');
  const draft = await resultDraft(performer.page, requestId); expect(draft.visit_result_id).toBeTruthy();
  const saved = await saveDraft(performer.page, draft); expect(saved.body).toBeTruthy(); await checkpoint('WAITING_VISIT_RESULT');
  const submitted = await submitResult(performer.page, draft, `corr-submit-${data.suffix}`); expect(submitted.body).toBeTruthy(); await checkpoint('WAITING_DOCTOR_REVIEW');
  const reviewedForCorrection = await mutate(rd.page, '/clinical/review', { request_id: requestId, visit_result_id: draft.visit_result_id, decision: 'correction_required', correction_reason: 'Internal clinical reason', review_notes: 'Correct values', idempotency_key: `corr-review-${data.suffix}` });
  expect(reviewedForCorrection.body).toBeTruthy(); await checkpoint('CORRECTION_REQUIRED');
  const wargaSurface = await canonicalSurface(warga.page, 'WARGA', requestId);
  expect(wargaSurface.review_summary || {}).not.toHaveProperty('correction_reason', expect.any(String));
  const assertWargaMasked = async () => {
    const current = await canonicalSurface(warga.page, 'WARGA', requestId);
    expect(current.review_summary || {}).not.toHaveProperty('correction_reason', expect.any(String));
    expect(current.review_summary || {}).not.toHaveProperty('internal_reason', expect.any(String));
  };
  const correction = await mutate(performer.page, '/konsultasi_nakes/visit_result_correction', { request_id: requestId, superseded_result_id: draft.visit_result_id, idempotency_key: `corr-draft-${data.suffix}` });
  expect(correction.body).toBeTruthy(); await checkpoint('CORRECTION_REQUIRED');
  const correctedDraft = correction.body.data || {};
  expect(correctedDraft.visit_result_id).toBeTruthy();
  const correctedSave = await saveDraft(performer.page, correctedDraft); expect(correctedSave.body).toBeTruthy(); await checkpoint('CORRECTION_REQUIRED');
  const correctedSubmit = await submitResult(performer.page, correctedDraft, `corr-submit-corrected-${data.suffix}`); expect(correctedSubmit.body).toBeTruthy(); await checkpoint('WAITING_DOCTOR_REVIEW'); await assertWargaMasked();
  const approved = await mutate(rd.page, '/clinical/review', { request_id: requestId, visit_result_id: correctedDraft.visit_result_id, decision: 'approved', review_notes: 'Corrected values approved', idempotency_key: `corr-review-approved-${data.suffix}` });
  expect(approved.body).toBeTruthy(); await checkpoint('WAITING_CLINICAL_FINALIZATION'); await assertWargaMasked();
  const finalized = await mutate(rd.page, '/clinical/finalize', { request_id: requestId, idempotency_key: `corr-finalize-${data.suffix}` });
  expect(finalized.body).toBeTruthy(); await checkpoint('READY_FOR_CLOSURE'); await assertWargaMasked();
  const closed = await mutate(rd.page, '/clinical/close', { request_id: requestId, idempotency_key: `corr-close-${data.suffix}` });
  expect(closed.body).toBeTruthy(); await checkpoint('COMPLETED');
  const completed = await canonicalSurface(warga.page, 'WARGA', requestId);
  expect(String(completed.request.request_status || '')).toMatch(/Completed/i);
  await assertWargaMasked();
  await warga.context.close(); await rd.context.close(); await performer.context.close();
});
