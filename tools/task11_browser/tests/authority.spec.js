const { test, expect } = require('@playwright/test');
const { fixtureData, post, expectDenied } = require('./fixtures');
const { actorPage } = require('../support/lifecycle');

test('browser authority matrix denies forbidden clinical disposition', async ({ browser }) => {
  const data = fixtureData(); const requestId = data.requests.authority;
  for (const name of ['WARGA', 'COMMAND_CENTER', 'DOCTOR_PERFORMER', 'NONDOCTOR_PERFORMER', 'UNRELATED_DOCTOR']) {
    const actor = await actorPage(browser, data, name); await expectDenied(await post(actor.page, '/clinical/disposition', { request_id: requestId, decision: 'visit', urgency: 'routine', idempotency_key: `deny-${name}-${data.suffix}` })); await actor.context.close();
  }
  expect(true).toBeTruthy();
});
