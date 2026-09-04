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
    if (name === 'WARGA' || name === 'COMMAND_CENTER' || name === 'RD') {
      expect(result.status(), `${name} surface status`).toBe(200); const body = await result.json();
      expect(body.data).toEqual(expect.objectContaining({ workflow_state: expect.objectContaining({ state: expect.any(String) }), actor_context: expect.any(Object), available_actions: expect.anything() }));
      const expectedType = { WARGA: 'warga', COMMAND_CENTER: 'command_center', RD: 'responsible_doctor' }[name];
      expect(body.data.actor_context.type, `${name} canonical actor context`).toBe(expectedType);
    } else if (name === 'NONDOCTOR_PERFORMER') {
      expect(result.status(), `${name} authorized surface status`).toBe(200); const body = await result.json();
      expect(body.data.actor_context).toEqual(expect.objectContaining({ type: 'performer' }));
    } else if (name === 'DOCTOR_PERFORMER') {
      // This request is assigned to the non-doctor performer above; the doctor
      // performer is therefore a request-unrelated clinician and must be denied.
      expect(result.status(), `${name} unrelated surface denial`).toBe(403);
    } else {
      expect(result.status(), `${name} unrelated surface denial`).toBe(403);
    }
    await actor.context.close();
  }
});

test('Warga correction presentation masks internal clinical reason', async ({ browser }) => {
  const data = fixtureData(); const actor = await actorPage(browser, data, 'WARGA');
  const result = await canonicalSurface(actor.page, 'WARGA', data.requests.visit_correction);
  expect(result.review_summary || {}).not.toHaveProperty('correction_reason', expect.any(String));
  await actor.context.close();
});
