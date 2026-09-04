const { test } = require('@playwright/test');
const { fixtureData, post, expectDenied } = require('./fixtures');
const { actorPage, enroll, assign, physical, vitals, resultDraft, saveDraft, submitResult } = require('../support/lifecycle');

test('non-doctor performer has performer access but not doctor actions', async ({ browser }) => {
  const data = fixtureData(); const requestId = data.requests.nondoctor_performer;
  const rd = await actorPage(browser, data, 'RD'); const performer = await actorPage(browser, data, 'NONDOCTOR_PERFORMER');
  await enroll(rd.page, requestId, `nurse-disp-${data.suffix}`); await assign(rd.page, requestId, data.actors.NONDOCTOR_PERFORMER.id, `nurse-ass-${data.suffix}`);
  for (const state of ['en_route', 'arrived', 'in_service']) await physical(performer.page, requestId, state);
  await vitals(performer.page, requestId); await physical(performer.page, requestId, 'completed');
  const draft = await resultDraft(performer.page, requestId); await saveDraft(performer.page, draft); await submitResult(performer.page, draft, `nurse-submit-${data.suffix}`);
  for (const path of ['/clinical/review', '/clinical/finalize', '/clinical/close', '/clinical/amend']) await expectDenied(await post(performer.page, path, { request_id: requestId, visit_result_id: draft.visit_result_id, decision: 'approved', idempotency_key: `nurse-deny-${data.suffix}` }));
  await rd.context.close(); await performer.context.close();
});
