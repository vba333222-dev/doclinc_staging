const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('REFERENCE_SPEC_FILES are complete and machine-readable', () => {
  for (const mode of ['chat', 'video', 'voice']) {
    const file = path.join(__dirname, '..', 'reference_specs', `${mode}.json`);
    const spec = JSON.parse(fs.readFileSync(file, 'utf8'));
    expect(spec.mode).toBe(mode);
    expect(spec.viewport).toMatchObject({ cssWidth: 353, cssHeight: 800, deviceScaleFactor: 2, physicalWidth: 706, physicalHeight: 1600 });
    expect(spec.source.html).toMatch(/code\.html$/);
    expect(spec.source.screenshot).toMatch(/screen\.png$/);
    expect(Object.keys(spec.tokens).length).toBeGreaterThan(3);
    expect(spec.colors.length).toBeGreaterThan(3);
    expect(spec.fixtureContent).toBeTruthy();
    expect(Array.isArray(spec.dynamicMaskRegions)).toBe(true);
  }
});

test('CHAT_RENDERER_CONTRACT keeps message timestamps inside bubbles without repeated avatars', () => {
  const view = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'application', 'modules', 'chat', 'views', 'thread_v.php'), 'utf8');
  expect(view).toContain("bubble.appendChild(time);");
  expect(view).not.toContain("row.appendChild(avatar);");
  expect(view).not.toContain("row.appendChild(time);");
});
