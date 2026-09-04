const { test, expect } = require('@playwright/test');
const { fixtureData, post, stateProbe } = require('./fixtures');
const { actorPage, enroll } = require('../support/lifecycle');

test('browser retry preserves the canonical disposition key contract', async ({ browser }) => {
  const data = fixtureData(); const actor = await actorPage(browser, data, 'RD'); const id = data.requests.flag_off; const key = `browser-replay-${data.suffix}`;
  const first = await enroll(actor.page, id, key); const replay = await enroll(actor.page, id, key);
  const firstDisposition = first.body.data || first.body; const replayDisposition = replay.body.data || replay.body;
  expect(firstDisposition).toEqual(expect.objectContaining({ disposition_id: expect.any(Number) }));
  expect(replayDisposition).toEqual(expect.objectContaining({ disposition_id: firstDisposition.disposition_id, version_no: firstDisposition.version_no }));
  expect(stateProbe(id).disposition_count).toBe(1);
  const conflict = await post(actor.page, '/clinical/disposition', { request_id: data.requests.authority, decision: 'visit', urgency: 'routine', required_profession: 'Perawat', idempotency_key: key });
  expect(conflict.response.status()).toBe(422);
  expect(conflict.json).toEqual(expect.objectContaining({ safe_error_code: 'IDEMPOTENCY_KEY_CONFLICT' }));
  await actor.context.close();
});
