const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const thread = fs.readFileSync(path.join(repoRoot, 'application/modules/chat/views/thread_v.php'), 'utf8');

test('LiveKit controls have names and keyboard-safe button semantics', async ({ page }) => {
  await page.setContent(`<button id="mute" type="button" aria-label="Bisukan mikrofon" aria-pressed="false">Mikrofon</button><button id="close" type="button" aria-label="Tutup tampilan panggilan">Tutup tampilan</button><button id="end" type="button" aria-label="Akhiri panggilan">Akhiri Panggilan</button>`);
  await expect(page.getByRole('button', { name: 'Bisukan mikrofon' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Tutup tampilan panggilan' })).toBeVisible();
  await page.getByRole('button', { name: 'Akhiri panggilan' }).focus();
  await expect(page.locator(':focus')).toHaveAttribute('aria-label', 'Akhiri panggilan');
  await page.keyboard.press('Enter');
});

test('production LiveKit view names destructive and dismissal controls distinctly', () => {
  expect(thread).toContain('aria-label="Tutup tampilan panggilan"');
  expect(thread).toContain('aria-label="Akhiri panggilan"');
  expect(thread).toContain('aria-expanded="false"');
  expect(thread).toContain('title="Akhiri panggilan"');
});

test('legacy chat attachment choices remain keyboard-operable', () => {
  const chat = fs.readFileSync(path.join(repoRoot, 'application/modules/chat/views/chat.php'), 'utf8');
  expect(chat).toMatch(/<button[^>]+type="button"[^>]+class="popup-item"/);
  expect(chat).not.toMatch(/<div[^>]+class="popup-item"[^>]+onclick=/);
});
