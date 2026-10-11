const { expect } = require('@playwright/test');
const { surface } = require('./fixtures');

async function expectSurface(page, actor, requestId, expectedState) {
  const result = await surface(page, actor, requestId);
  expect(result.response.status()).toBe(200);
  expect(result.json).toBeTruthy();
  const data = result.json.data || {};
  for (const field of ['request', 'workflow_state', 'presentation', 'actor_context', 'available_actions']) {
    expect(data, `surface field ${field}`).toHaveProperty(field);
  }
  if (expectedState) expect(data.workflow_state).toBe(expectedState);
  return result;
}

module.exports = { expectSurface };
