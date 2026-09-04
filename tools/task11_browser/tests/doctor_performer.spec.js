const { test, expect } = require('@playwright/test');
const { fixtureData } = require('./fixtures');
const { completeVisit } = require('../support/lifecycle');

test('doctor performer completes the Visit without RD transfer', async ({ browser }) => {
  const data = fixtureData(); const result = await completeVisit(browser, data, 'doctor_performer', 'DOCTOR_PERFORMER', 'DOCTOR_PERFORMER');
  expect(result.closure.body).toBeTruthy(); await result.rd.context.close(); await result.performer.context.close();
});
