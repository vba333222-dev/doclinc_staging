const { test, expect } = require('@playwright/test');
const { fixtureData } = require('./fixtures');
const { actorPage, enroll, assign, physical, vitals, resultDraft, saveDraft, submitResult, mutate, canonicalSurface } = require('../support/lifecycle');

test('correction workflow reaches Completed through browser HTTP boundaries and masks Warga detail', async ({ browser }) => {
  const data = fixtureData(); const requestId = data.requests.visit_correction;
  const rd = await actorPage(browser, data, 'RD'); const performer = await actorPage(browser, data, 'NONDOCTOR_PERFORMER'); const warga = await actorPage(browser, data, 'WARGA');
  await enroll(rd.page, requestId, `corr-disp-${data.suffix}`); await canonicalSurface(warga.page, 'WARGA', requestId);
  await assign(rd.page, requestId, data.actors.NONDOCTOR_PERFORMER.id, `corr-ass-${data.suffix}`); await canonicalSurface(warga.page, 'WARGA', requestId);
  for (const state of ['en_route', 'arrived', 'in_service']) { await physical(performer.page, requestId, state); await canonicalSurface(warga.page, 'WARGA', requestId); }
  await vitals(performer.page, requestId); await canonicalSurface(warga.page, 'WARGA', requestId);
  await physical(performer.page, requestId, 'completed'); await canonicalSurface(warga.page, 'WARGA', requestId);
  const draft = await resultDraft(performer.page, requestId); expect(draft.visit_result_id).toBeTruthy();
  const saved = await saveDraft(performer.page, draft); expect(saved.body).toBeTruthy(); await canonicalSurface(warga.page, 'WARGA', requestId);
  const submitted = await submitResult(performer.page, draft, `corr-submit-${data.suffix}`); expect(submitted.body).toBeTruthy(); await canonicalSurface(warga.page, 'WARGA', requestId);
  const reviewedForCorrection = await mutate(rd.page, '/clinical/review', { request_id: requestId, visit_result_id: draft.visit_result_id, decision: 'correction_required', correction_reason: 'Internal clinical reason', review_notes: 'Correct values', idempotency_key: `corr-review-${data.suffix}` });
  expect(reviewedForCorrection.body).toBeTruthy(); await canonicalSurface(warga.page, 'WARGA', requestId);
  const wargaSurface = await canonicalSurface(warga.page, 'WARGA', requestId);
  expect(wargaSurface.review_summary || {}).not.toHaveProperty('correction_reason', expect.any(String));
  const assertWargaMasked = async () => {
    const current = await canonicalSurface(warga.page, 'WARGA', requestId);
    expect(current.review_summary || {}).not.toHaveProperty('correction_reason', expect.any(String));
    expect(current.review_summary || {}).not.toHaveProperty('internal_reason', expect.any(String));
  };
  const correction = await mutate(performer.page, '/konsultasi_nakes/visit_result_correction', { request_id: requestId, superseded_result_id: draft.visit_result_id, idempotency_key: `corr-draft-${data.suffix}` });
  expect(correction.body).toBeTruthy(); await canonicalSurface(warga.page, 'WARGA', requestId);
  const correctedDraft = correction.body.data || {};
  expect(correctedDraft.visit_result_id).toBeTruthy();
  const correctedSave = await saveDraft(performer.page, correctedDraft); expect(correctedSave.body).toBeTruthy(); await canonicalSurface(warga.page, 'WARGA', requestId);
  const correctedSubmit = await submitResult(performer.page, correctedDraft, `corr-submit-corrected-${data.suffix}`); expect(correctedSubmit.body).toBeTruthy(); await assertWargaMasked();
  const approved = await mutate(rd.page, '/clinical/review', { request_id: requestId, visit_result_id: correctedDraft.visit_result_id, decision: 'approved', review_notes: 'Corrected values approved', idempotency_key: `corr-review-approved-${data.suffix}` });
  expect(approved.body).toBeTruthy(); await assertWargaMasked();
  const finalized = await mutate(rd.page, '/clinical/finalize', { request_id: requestId, idempotency_key: `corr-finalize-${data.suffix}` });
  expect(finalized.body).toBeTruthy(); await assertWargaMasked();
  const closed = await mutate(rd.page, '/clinical/close', { request_id: requestId, idempotency_key: `corr-close-${data.suffix}` });
  expect(closed.body).toBeTruthy();
  const completed = await canonicalSurface(warga.page, 'WARGA', requestId);
  expect(String(completed.request.request_status || '')).toMatch(/Completed/i);
  await assertWargaMasked();
  await warga.context.close(); await rd.context.close(); await performer.context.close();
});
