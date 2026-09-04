const { test, expect } = require('@playwright/test');
const { fixtureData } = require('./fixtures');
const { completeVisit, actorPage, canonicalSurface } = require('../support/lifecycle');

test('Visit lifecycle completes through authenticated browser HTTP boundaries', async ({ browser }) => {
  const data = fixtureData(); const result = await completeVisit(browser, data, 'visit_happy');
  const warga = await actorPage(browser, data, 'WARGA'); const surface = await canonicalSurface(warga.page, 'WARGA', result.requestId);
  expect(String(surface.request.request_status || '')).toMatch(/Completed/i);
  expect(result.finalization.body).toBeTruthy(); expect(result.closure.body).toBeTruthy();
  await warga.context.close(); await result.rd.context.close(); await result.performer.context.close();
});
