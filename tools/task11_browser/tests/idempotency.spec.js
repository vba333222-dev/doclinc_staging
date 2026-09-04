const { test, expect } = require('@playwright/test');
const { fixtureData, post } = require('./fixtures');
const { actorPage, enroll } = require('../support/lifecycle');

test('browser retry preserves the canonical disposition key contract', async ({ browser }) => {
  const data = fixtureData(); const actor = await actorPage(browser, data, 'RD'); const id = data.requests.flag_off; const key = `browser-replay-${data.suffix}`;
  const first = await enroll(actor.page, id, key); const replay = await enroll(actor.page, id, key); expect(first.body).toBeTruthy(); expect(replay.body).toBeTruthy();
  const conflict = await post(actor.page, '/clinical/disposition', { request_id: data.requests.authority, decision: 'visit', urgency: 'routine', required_profession: 'Perawat', idempotency_key: key });
  expect([200, 422]).toContain(conflict.response.status());
  expect(conflict.json && conflict.json.success).toBeFalsy();
  await actor.context.close();
});
