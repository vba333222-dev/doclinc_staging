const { test, expect } = require('@playwright/test');
const { fixtureData, post, expectDenied } = require('./fixtures');
const { actorPage, enroll, assign, canonicalSurface } = require('../support/lifecycle');

test('stale browser tabs converge on canonical workflow state', async ({ browser }) => {
  const data = fixtureData(); const id = data.requests.stale; const rd = await actorPage(browser, data, 'RD'); const a = await actorPage(browser, data, 'WARGA'); const b = await actorPage(browser, data, 'WARGA');
  const staleA = await canonicalSurface(a.page, 'WARGA', id);
  const staleB = await canonicalSurface(b.page, 'WARGA', id);
  expect(staleA.workflow_state.state).toBe('WAITING_DOCTOR_DISPOSITION');
  expect(staleB.workflow_state).toEqual(staleA.workflow_state);
  await enroll(rd.page, id, `stale-${data.suffix}`);
  await assign(rd.page, id, data.actors.NONDOCTOR_PERFORMER.id, `stale-assign-${data.suffix}`);
  // A stale Warga tab cannot mutate and, after refetch, converges on the
  // canonical state committed by the clinical actor.
  await expectDenied(await post(b.page, '/clinical/disposition', { request_id: id, decision: 'non_visit', idempotency_key: `stale-loser-${data.suffix}` }));
  const first = await canonicalSurface(a.page, 'WARGA', id);
  const second = await canonicalSurface(b.page, 'WARGA', id);
  expect(first.workflow_state).toEqual(second.workflow_state);
  expect(first.workflow_state.state).toBe('VISIT_NOT_STARTED');
  expect(first.workflow_state.state).not.toBe(staleA.workflow_state.state);
  await a.context.close(); await b.context.close(); await rd.context.close();
});
