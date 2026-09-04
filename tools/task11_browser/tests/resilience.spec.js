const { test, expect } = require('@playwright/test');
const { fixtureData } = require('./fixtures');
const { actorPage, canonicalSurface } = require('../support/lifecycle');
const { installBrowserHygiene } = require('./console_network_helpers');
const path = require('path');

test('workflow remains safe when optional Lottie assets fail', async ({ browser }) => {
  const data = fixtureData(); const actor = await actorPage(browser, data, 'WARGA'); let aborted = 0;
  const hygiene = installBrowserHygiene(actor.page, value => /livekit|lottie|clinical_workflow\/pemberitahuan\.json|ERR_FAILED/.test(value));
  await actor.page.route('**/clinical_workflow/pemberitahuan.json', route => { aborted += 1; route.abort(); });
  await actor.page.route('**/realtime/**', route => route.abort());
  await actor.page.setContent('<span data-clinical-lottie="/assets/doclinc_ui/clinical_workflow/pemberitahuan.json" data-clinical-lottie-fallback="Status diperbarui" aria-live="polite"></span><main>Workflow tetap tersedia</main>');
  await actor.page.addScriptTag({ path: path.resolve(__dirname, '../../../assets/js/doclinc-clinical-lottie.js') });
  await actor.page.evaluate(() => {
    window.lottie = { loadAnimation: ({ path }) => fetch(path).then(() => ({})) };
    return window.DoclincClinicalLottie.play(document.querySelector('[data-clinical-lottie]'), '/assets/doclinc_ui/clinical_workflow/pemberitahuan.json', 'Status diperbarui');
  });
  await expect(actor.page.locator('[data-clinical-lottie]')).toHaveText('Status diperbarui');
  const surface = await canonicalSurface(actor.page, 'WARGA', data.requests.historical_completed);
  expect(surface.request).toBeTruthy();
  expect(aborted).toBe(1);
  await expect(actor.page.locator('body')).not.toContainText('SQLSTATE'); hygiene.assertClean(); await actor.context.close();
});
