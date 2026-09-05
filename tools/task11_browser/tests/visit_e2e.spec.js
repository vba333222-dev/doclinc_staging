const { test, expect } = require('@playwright/test');
const { fixtureData } = require('./fixtures');
const { completeVisit, actorPage, canonicalSurface } = require('../support/lifecycle');

test('Visit lifecycle completes through authenticated browser HTTP boundaries', async ({ browser }) => {
  const data = fixtureData(); const result = await completeVisit(browser, data, 'visit_happy');
  const warga = await actorPage(browser, data, 'WARGA'); const surface = await canonicalSurface(warga.page, 'WARGA', result.requestId);
  expect(result.timeline).toEqual([
    { operation: 'disposition', state: 'WAITING_PERFORMER_ASSIGNMENT' },
    { operation: 'assignment', state: 'VISIT_NOT_STARTED' },
    { operation: 'physical:en_route', state: 'VISIT_EN_ROUTE' },
    { operation: 'physical:arrived', state: 'VISIT_ARRIVED' },
    { operation: 'physical:in_service', state: 'VISIT_IN_SERVICE' },
    { operation: 'vitals', state: 'VISIT_IN_SERVICE' },
    { operation: 'physical:completed', state: 'WAITING_VISIT_RESULT' },
    { operation: 'result_save', state: 'WAITING_VISIT_RESULT' },
    { operation: 'result_submit', state: 'WAITING_DOCTOR_REVIEW' },
    { operation: 'review', state: 'WAITING_CLINICAL_FINALIZATION' },
    { operation: 'finalization', state: 'READY_FOR_CLOSURE' },
    { operation: 'closure', state: 'COMPLETED' },
  ]);
  expect(String(surface.request.request_status || '')).toMatch(/Completed/i);
  expect(result.finalization.body).toBeTruthy(); expect(result.closure.body).toBeTruthy();
  await warga.context.close(); await result.rd.context.close(); await result.performer.context.close(); await result.observer.context.close();
});
