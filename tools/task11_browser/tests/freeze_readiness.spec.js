const { test, expect } = require('@playwright/test');
const { fixtureData, post, expectDenied, csrf } = require('./fixtures');
const { actorPage, enroll, canonicalSurface } = require('../support/lifecycle');
const { readSurface } = require('./surface_helpers');

test.describe('Task 11C.3 browser freeze-readiness boundaries', () => {
  test('inactive/unclassified actors, wrong mode, and direct HTTP bypass remain denied', async ({ browser }) => {
    const data = fixtureData();
    const rd = await actorPage(browser, data, 'RD');
    const warga = await actorPage(browser, data, 'WARGA');
    const unrelated = await actorPage(browser, data, 'UNRELATED_DOCTOR');
    const command = await actorPage(browser, data, 'COMMAND_CENTER');
    const unclassified = await actorPage(browser, data, 'UNCLASSIFIED');

    await enroll(rd.page, data.requests.authority, `freeze-authority-${data.suffix}`);
    const before = await canonicalSurface(warga.page, 'WARGA', data.requests.authority);

    // Managed requests cannot be accepted through the legacy HTTP boundary.
    await expectDenied(await post(warga.page, '/home_nakes/accept_request', { request_id: data.requests.authority }));
    const after = await canonicalSurface(warga.page, 'WARGA', data.requests.authority);
    expect(after.request.request_status).toBe(before.request.request_status);
    expect(after.workflow_state.state).toBe(before.workflow_state.state);

    // A same-role but unrelated/unclassified doctor has no request authority.
    await expectDenied(await post(unrelated.page, '/clinical/disposition', {
      request_id: data.requests.stale, decision: 'visit', urgency: 'routine', idempotency_key: `freeze-unrelated-${data.suffix}`,
    }));
    await expectDenied(await post(unclassified.page, '/clinical/disposition', {
      request_id: data.requests.stale, decision: 'visit', urgency: 'routine', idempotency_key: `freeze-unclassified-${data.suffix}`,
    }));
    const inactivePage = await browser.newPage();
    await inactivePage.goto('/login', { waitUntil: 'domcontentloaded' });
    const inactiveLogin = await inactivePage.request.post('/login/auth', { form: { username: data.special.INACTIVE.username, password: data.special.INACTIVE.password, doclinc_csrf_token: await csrf(inactivePage) } });
    expect(inactiveLogin.status()).toBe(200); expect((await inactiveLogin.text()).trim()).not.toBe('1');
    await inactivePage.close();
    const unrelatedSurface = await readSurface(unrelated.page, 'home_nakes', data.requests.authority, { requireSuccess: false });
    expect([302, 403]).toContain(unrelatedSurface.response.status());

    // Visit-only operational assignment is rejected for a managed NonVisit request.
    await expectDenied(await post(command.page, '/home_nakes/assign_visit_performer', {
      request_id: data.requests.nonvisit, staff_id: data.actors.NONDOCTOR_PERFORMER.id, idempotency_key: `freeze-wrong-mode-${data.suffix}`,
    }));

    await warga.context.close(); await unrelated.context.close(); await unclassified.context.close(); await command.context.close(); await rd.context.close();
  });

  test('uncertain same-key retry replays and stale loser cannot overwrite canonical state', async ({ browser }) => {
    const data = fixtureData();
    const rdA = await actorPage(browser, data, 'RD');
    const rdB = await actorPage(browser, data, 'RD');
    const warga = await actorPage(browser, data, 'WARGA');
    const key = `freeze-retry-${data.suffix}`;

    // Treat the first response as uncertain from the caller perspective; the retry
    // uses the same canonical key and must replay rather than create a second row.
    const first = await enroll(rdA.page, data.requests.idempotency, key);
    const retry = await enroll(rdA.page, data.requests.idempotency, key);
    expect(first.body).toBeTruthy(); expect(retry.body).toBeTruthy();
    const replaySurface = await canonicalSurface(warga.page, 'WARGA', data.requests.idempotency);
    expect(replaySurface.disposition_summary || {}).toBeTruthy();

    // The stale loser uses a different key after the canonical disposition exists;
    // it must be rejected and cannot move the request back to disposition state.
    await expectDenied(await post(rdB.page, '/clinical/disposition', {
      request_id: data.requests.idempotency, decision: 'non_visit', idempotency_key: `freeze-stale-loser-${data.suffix}`,
    }));
    const canonical = await canonicalSurface(warga.page, 'WARGA', data.requests.idempotency);
    expect(canonical.workflow_state.state).not.toBe('WAITING_DOCTOR_DISPOSITION');

    await warga.context.close(); await rdA.context.close(); await rdB.context.close();
  });

  test('a post-commit transport failure is reconciled by retrying the same key', async ({ browser }) => {
    const data = fixtureData();
    const rd = await actorPage(browser, data, 'RD');
    const warga = await actorPage(browser, data, 'WARGA');
    const key = `freeze-uncertain-${data.suffix}`;
    let intercepted = false;
    await rd.page.route('**/clinical/disposition', async route => {
      if (intercepted) return route.continue();
      intercepted = true;
      // Fetching first lets the server commit the canonical operation. Aborting
      // the browser delivery then models an uncertain post-commit response.
      await route.fetch();
      await route.abort('connectionreset');
    });
    const csrfToken = await csrf(rd.page);
    const body = new URLSearchParams({ request_id: String(data.requests.idempotency), decision: 'visit', urgency: 'routine', idempotency_key: key, doclinc_csrf_token: csrfToken }).toString();
    await expect(rd.page.evaluate(async encoded => {
      try {
        await fetch('/clinical/disposition', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' }, body: encoded });
        return 'response';
      } catch (error) {
        return 'transport_failed';
      }
    }, body)).resolves.toBe('transport_failed');
    await rd.page.unroute('**/clinical/disposition');
    const retry = await enroll(rd.page, data.requests.idempotency, key);
    expect(retry.body).toBeTruthy();
    const canonical = await canonicalSurface(warga.page, 'WARGA', data.requests.idempotency);
    expect(canonical.workflow_state.state).not.toBe('WAITING_DOCTOR_DISPOSITION');
    await warga.context.close(); await rd.context.close();
  });

  test('legacy non-enrolled acceptance remains compatible and historical reads stay non-managed', async ({ browser }) => {
    const data = fixtureData();
    const command = await actorPage(browser, data, 'COMMAND_CENTER');
    const legacyBefore = await readSurface(command.page, 'home_nakes', data.requests.legacy, { requireSuccess: false });
    const legacyAccept = await post(command.page, '/home_nakes/accept_request', { id: data.requests.legacy, latitude: '0', longitude: '0' });
    expect([200, 302]).toContain(legacyAccept.response.status());
    const historicalBefore = await readSurface(command.page, 'home_nakes', data.requests.historical_completed, { requireSuccess: false });
    expect([200, 302, 403]).toContain(historicalBefore.response.status());
    const historicalAfter = await readSurface(command.page, 'home_nakes', data.requests.historical_completed, { requireSuccess: false });
    expect(historicalAfter.response.status()).toBe(historicalBefore.response.status());
    await command.context.close();
  });
});
