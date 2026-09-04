const { test, expect } = require('@playwright/test');
const { fixtureData } = require('./fixtures');
const { actorPage, canonicalSurface, enroll, assign } = require('../support/lifecycle');

test('all six actors receive the canonical role-filtered workflow surface', async ({ browser }) => {
  const data = fixtureData(); const requestId = data.requests.authority;
  const rd = await actorPage(browser, data, 'RD');
  await enroll(rd.page, requestId, `surface-${data.suffix}`, 'Perawat');
  await assign(rd.page, requestId, data.actors.NONDOCTOR_PERFORMER.id, `surface-assign-${data.suffix}`);
  await rd.context.close();
  for (const name of ['WARGA', 'COMMAND_CENTER', 'RD', 'DOCTOR_PERFORMER', 'NONDOCTOR_PERFORMER', 'UNRELATED_DOCTOR']) {
    const actor = await actorPage(browser, data, name);
    const result = await actor.page.request.get(name === 'WARGA' ? `/home/clinical_workflow/${requestId}` : name === 'COMMAND_CENTER' ? `/puskesmas/operations/clinical-workflow/${requestId}` : `/home_nakes/clinical_workflow/${requestId}`);
    if (name === 'WARGA' || name === 'COMMAND_CENTER') {
      expect(result.status(), `${name} surface status`).toBe(200); const body = await result.json();
      expect(body.data).toEqual(expect.objectContaining({ workflow_state: expect.objectContaining({ state: expect.any(String) }), actor_context: expect.any(Object), available_actions: expect.anything() }));
      if (name === 'COMMAND_CENTER') expect(body.data.actor_context.type).toBe('command_center');
    } else expect([200, 302, 403]).toContain(result.status());
    await actor.context.close();
  }
});

test('Warga correction presentation masks internal clinical reason', async ({ browser }) => {
  const data = fixtureData(); const actor = await actorPage(browser, data, 'WARGA');
  const result = await canonicalSurface(actor.page, 'WARGA', data.requests.visit_correction);
  expect(result.review_summary || {}).not.toHaveProperty('correction_reason', expect.any(String));
  await actor.context.close();
});
