const { expect } = require('@playwright/test');

function surfacePath(actor, requestId) {
  if (actor === 'WARGA') return `/home/clinical_workflow/${requestId}`;
  if (actor === 'COMMAND_CENTER') return `/puskesmas/operations/clinical-workflow/${requestId}`;
  return `/home_nakes/clinical_workflow/${requestId}`;
}

async function readSurface(page, actor, requestId, options = {}) {
  const response = await page.request.get(surfacePath(actor, requestId), {
    headers: { Accept: 'application/json,text/html' },
  });
  let json = null;
  try { json = await response.json(); } catch (_) { /* denial/non-JSON */ }
  if (options.requireSuccess !== false) {
    expect(response.status(), `${actor} surface status`).toBe(200);
    expect(json, `${actor} surface JSON`).toBeTruthy();
    expect(json.success === true || json.ok === true || json.status === 'success', `${actor} surface success envelope`).toBeTruthy();
    const data = json.data || json;
    expect(data).toEqual(expect.objectContaining({
      request: expect.any(Object),
      workflow_state: expect.any(Object),
      actor_context: expect.any(Object),
      available_actions: expect.anything(),
    }));
    const surfacedId = data.request.request_id || data.request.id;
    if (surfacedId !== undefined) expect(Number(surfacedId)).toBe(Number(requestId));
  }
  return { response, json, path: surfacePath(actor, requestId) };
}

module.exports = { surfacePath, readSurface };
