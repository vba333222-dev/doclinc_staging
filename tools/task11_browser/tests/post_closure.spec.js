const { test, expect } = require('@playwright/test');
const { fixtureData, post, expectDenied } = require('./fixtures');
const { completeVisit, actorPage } = require('../support/lifecycle');

test('post-closure amendment is limited to the clinical finalizer', async ({ browser }) => {
  const data = fixtureData(); const completed = await completeVisit(browser, data, 'post_closure');
  const warga = await actorPage(browser, data, 'WARGA');
  const before = await require('../support/lifecycle').canonicalSurface(warga.page, 'WARGA', completed.requestId);
  expect(String(before.request.request_status || '')).toMatch(/Completed/i);
  const recordId = completed.finalization.body.data.record_id;
  const items = JSON.stringify([{ field_key: 'diagnosis', corrected_value: 'Browser amended diagnosis' }]);
  const amend = await post(completed.rd.page, '/clinical/amend', { request_id: completed.requestId, record_id: recordId, reason: 'Browser amendment', items, idempotency_key: `amend-${data.suffix}` }); expect(amend.response.status()).toBe(200); expect(amend.json && amend.json.success).toBeTruthy();
  const after = await require('../support/lifecycle').canonicalSurface(warga.page, 'WARGA', completed.requestId);
  expect(String(after.request.request_status || '')).toMatch(/Completed/i);
  expect(before.finalization_summary).toBeTruthy();
  expect(after.finalization_summary).toEqual(before.finalization_summary);
  expect(before.closure_summary).toBeTruthy();
  expect(after.closure_summary).toEqual(before.closure_summary);
  const other = await actorPage(browser, data, 'COMMAND_CENTER'); await expectDenied(await post(other.page, '/clinical/amend', { request_id: completed.requestId, idempotency_key: `amend-deny-${data.suffix}` }));
  const unrelated = await actorPage(browser, data, 'UNRELATED_DOCTOR'); await expectDenied(await post(unrelated.page, '/clinical/amend', { request_id: completed.requestId, idempotency_key: `amend-deny-unrelated-${data.suffix}` }));
  const nonFinalizer = await actorPage(browser, data, 'DOCTOR_PERFORMER'); await expectDenied(await post(nonFinalizer.page, '/clinical/amend', { request_id: completed.requestId, idempotency_key: `amend-deny-performer-${data.suffix}` }));
  await unrelated.context.close(); await nonFinalizer.context.close(); await other.context.close(); await warga.context.close(); await completed.rd.context.close(); await completed.performer.context.close();
});
