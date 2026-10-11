const { test, expect } = require('@playwright/test');
const { fixtureData, loginActor } = require('./fixtures');

test('fixture factory creates independent canonical actors and disposable requests', async ({ page }) => {
  const data = fixtureData();
  expect(Object.keys(data.actors).sort()).toEqual(['COMMAND_CENTER', 'DOCTOR_PERFORMER', 'NONDOCTOR_PERFORMER', 'RD', 'UNRELATED_DOCTOR', 'WARGA']);
  expect(new Set(Object.values(data.actors).map(a => a.id)).size).toBe(6);
  expect(new Set(Object.values(data.requests)).size).toBe(Object.keys(data.requests).length);
  await loginActor(page, data.actors.WARGA);
  await expect(page).toHaveURL(/\/(home|login)/);
});
