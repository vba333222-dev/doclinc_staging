const { test, expect } = require('@playwright/test');
const { fixtureData, loginActor, stateProbe, post, expectDenied } = require('./fixtures');
const { actorPage, enroll, canonicalSurface } = require('../support/lifecycle');

test('an enrolled request remains operable when new enrollment is disabled', async ({ browser }) => {
  const data = fixtureData();
  const rd = await actorPage(browser, data, 'RD');
  const requestId = data.requests.flag_off;

  await enroll(rd.page, requestId, `browser-flag-off-${data.suffix}`);
  const enrolled = await canonicalSurface(rd.page, 'RD', requestId);
  expect(enrolled.workflow_state.state).toBe('WAITING_PERFORMER_ASSIGNMENT');
  expect(stateProbe(requestId).disposition_count).toBe(1);
  await rd.context.close();

  const flagOffBaseUrl = process.env.TASK11_FLAG_OFF_BASE_URL;
  expect(flagOffBaseUrl, 'flag-off runtime must be explicitly configured').toBeTruthy();
  const parsedBaseUrl = new URL(flagOffBaseUrl);
  expect(parsedBaseUrl.protocol).toBe('http:');
  expect(parsedBaseUrl.hostname).toBe('127.0.0.1');
  expect(parsedBaseUrl.port).toMatch(/^\d+$/);
  const flagOffContext = await browser.newContext({ baseURL: parsedBaseUrl.origin });
  const rdFlagOffPage = await flagOffContext.newPage();
  const runtimeResponse = await rdFlagOffPage.request.get('/__task11/runtime');
  const runtime = await runtimeResponse.json();
  expect(runtimeResponse.status()).toBe(200);
  expect(runtime).toEqual(expect.objectContaining({
    db_host: 'task11-db',
    db_name: 'doclinc_visit_test',
    care_team_workflow_enabled: false,
    visit_clinical_workflow_enabled: false,
  }));
  await loginActor(rdFlagOffPage, data.actors.RD);

  // The same runtime must still reject a new enrollment while the existing
  // enrolled request remains operable.
  await expectDenied(await post(rdFlagOffPage, '/clinical/disposition', {
    request_id: data.requests.stale,
    decision: 'visit',
    urgency: 'routine',
    required_profession: 'Perawat',
    idempotency_key: `browser-flag-off-new-${data.suffix}`,
  }));
  expect(stateProbe(data.requests.stale).disposition_count).toBe(0);

  const csrf = await rdFlagOffPage.locator('meta[name="doclinc-csrf-token"]').getAttribute('content');
  const response = await rdFlagOffPage.request.post('/home_nakes/assign_visit_performer', {
    headers: { Accept: 'application/json' },
    form: {
      request_id: requestId,
      staff_id: data.actors.NONDOCTOR_PERFORMER.id,
      idempotency_key: `browser-flag-off-assignment-${data.suffix}`,
      doclinc_csrf_token: csrf,
    },
  });
  const body = await response.json();
  expect(response.status(), JSON.stringify(body)).toBe(200);
  expect(body).toEqual(expect.objectContaining({ status: 'success' }));

  const wargaFlagOffPage = await flagOffContext.newPage();
  await loginActor(wargaFlagOffPage, data.actors.WARGA);
  const flagOffSurface = await canonicalSurface(wargaFlagOffPage, 'WARGA', requestId);
  expect(flagOffSurface.workflow_state.state).toBe('VISIT_NOT_STARTED');

  const primaryObserver = await actorPage(browser, data, 'WARGA');
  const continued = await canonicalSurface(primaryObserver.page, 'WARGA', requestId);
  expect(continued.workflow_state.state).toBe('VISIT_NOT_STARTED');
  expect(stateProbe(requestId).visit_performer_user_id).toBe(data.actors.NONDOCTOR_PERFORMER.id);
  await primaryObserver.context.close();
  await flagOffContext.close();
});
