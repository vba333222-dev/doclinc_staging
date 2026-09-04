const { test, expect } = require('@playwright/test');
const { fixtureData, post, expectDenied } = require('./fixtures');
const { actorPage, enroll, assign, canonicalSurface } = require('../support/lifecycle');

test('stale browser tabs converge on canonical workflow state', async ({ browser }) => {
  const data = fixtureData(); const id = data.requests.stale; const rdA = await actorPage(browser, data, 'RD'); const rdB = await actorPage(browser, data, 'RD'); const warga = await actorPage(browser, data, 'WARGA');
  const staleA = await canonicalSurface(rdA.page, 'RD', id);
  const staleB = await canonicalSurface(rdB.page, 'RD', id);
  expect(staleA.workflow_state.state).toBe('WAITING_DOCTOR_DISPOSITION');
  expect(staleB.workflow_state).toEqual(staleA.workflow_state);
  await enroll(rdA.page, id, `stale-${data.suffix}`);
  // Both tabs are authorized RD writers; the second tab is stale, not
  // unauthorized. Its conflicting disposition must lose safely.
  await expectDenied(await post(rdB.page, '/clinical/disposition', { request_id: id, decision: 'non_visit', idempotency_key: `stale-loser-${data.suffix}` }));
  const first = await canonicalSurface(warga.page, 'WARGA', id);
  const second = await canonicalSurface(rdB.page, 'RD', id);
  expect(first.workflow_state).toEqual(second.workflow_state);
  expect(first.workflow_state.state).toBe('WAITING_PERFORMER_ASSIGNMENT');
  expect(first.workflow_state.state).not.toBe(staleA.workflow_state.state);
  await warga.context.close(); await rdA.context.close(); await rdB.context.close();
});
