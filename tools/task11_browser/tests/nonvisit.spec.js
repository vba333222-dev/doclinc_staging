const { test, expect } = require('@playwright/test');
const { fixtureData, post, expectDenied, stateProbe } = require('./fixtures');
const { actorPage, enroll, canonicalSurface, mutate } = require('../support/lifecycle');
const { surface } = require('./fixtures');

test('NonVisit completes through the clinical HTTP path without creating a Visit entity', async ({ browser }) => {
  const data = fixtureData(); const requestId = data.requests.nonvisit;
  const rd = await actorPage(browser, data, 'RD');
  const command = await actorPage(browser, data, 'COMMAND_CENTER');
  const performer = await actorPage(browser, data, 'DOCTOR_PERFORMER');

  const before = stateProbe(requestId);
  expect(before.disposition_count).toBe(0);
  await enroll(rd.page, requestId, `nonvisit-disp-${data.suffix}`, 'Perawat', 'non_visit');
  const afterDisposition = await canonicalSurface(rd.page, 'RD', requestId);
  expect(afterDisposition.request.consultation_mode).toMatch(/non_visit/i);
  expect(afterDisposition.workflow_state.state).not.toBe('WAITING_DOCTOR_DISPOSITION');

  const commandSurface = await surface(command.page, 'COMMAND_CENTER', requestId);
  expect(commandSurface.response.status()).toBe(200);
  const payload = commandSurface.json.data || {};
  expect(payload.request.consultation_mode).toMatch(/non_visit/i);
  expect(payload.visit_progress || null).toBeTruthy();
  expect(payload.available_actions || []).not.toEqual(expect.arrayContaining(['assign_visit_performer', 'start_visit', 'arrive_visit']));

  // Visit-performer authority must not leak into NonVisit clinical closure.
  await expectDenied(await post(performer.page, '/clinical/finalize', { request_id: requestId, idempotency_key: `nonvisit-performer-finalize-${data.suffix}` }));
  await expectDenied(await post(performer.page, '/clinical/close', { request_id: requestId, idempotency_key: `nonvisit-performer-close-${data.suffix}` }));

  const finalized = await mutate(rd.page, '/clinical/finalize', { request_id: requestId, idempotency_key: `nonvisit-finalize-${data.suffix}` });
  expect(finalized.body).toBeTruthy();
  const afterFinalization = await canonicalSurface(rd.page, 'RD', requestId);
  expect(afterFinalization.workflow_state.state).toMatch(/READY_FOR_CLOSURE|COMPLETED/);

  const closed = await mutate(rd.page, '/clinical/close', { request_id: requestId, idempotency_key: `nonvisit-close-${data.suffix}` });
  expect(closed.body).toBeTruthy();
  // Completed NonVisit remains readable from the operational canonical surface;
  // the responsible-doctor mutation authority is proven by the successful
  // finalization/closure calls above.
  const completed = await canonicalSurface(command.page, 'COMMAND_CENTER', requestId);
  expect(String(completed.request.request_status || '')).toMatch(/Completed/i);
  expect(stateProbe(requestId).visit_assignment_count).toBe(0);

  await rd.context.close(); await command.context.close(); await performer.context.close();
});
