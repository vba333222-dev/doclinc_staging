const { test, expect } = require('@playwright/test');
const { fixtureData } = require('./fixtures');
const { actorPage, enroll } = require('../support/lifecycle');
const { surface } = require('./fixtures');

test('NonVisit workflow surface is clinical and has no physical Visit state', async ({ browser }) => {
  const data = fixtureData(); const requestId = data.requests.nonvisit; const rd = await actorPage(browser, data, 'RD');
  await enroll(rd.page, requestId, `nonvisit-disp-${data.suffix}`, 'Perawat', 'non_visit'); const command = await actorPage(browser, data, 'COMMAND_CENTER'); const result = await surface(command.page, 'COMMAND_CENTER', requestId); expect(result.response.status()).toBe(200); const payload = result.json.data || {};
  expect(payload.request.consultation_mode).toMatch(/non_visit/i); expect(payload.visit_progress || null).toBeTruthy(); await rd.context.close(); await command.context.close();
});
