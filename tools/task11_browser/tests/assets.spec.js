const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

const repo = path.resolve(__dirname, '..', '..', '..');
const helper = path.join(repo, 'application', 'helpers', 'clinical_workflow_assets_helper.php');
const iconDir = path.join(repo, 'assets', 'doclinc_ui', 'clinical_workflow');

test('semantic registry exists and preserves icon meanings', () => {
  expect(fs.existsSync(helper)).toBeTruthy();
  const source = fs.readFileSync(helper, 'utf8');
  expect(source).toContain("participant.svg");
  expect(source).toContain("livekit.participants");
  expect(source).toContain("close.svg");
  expect(source).toContain("hangup");
  expect(source).not.toContain("participant.svg' => 'staff");
});

test('imported assets render locally without remote payloads', async ({ page }) => {
  for (const file of ['participant.svg', 'close.svg', 'voicecall.svg', 'videocall.svg']) {
    const content = fs.readFileSync(path.join(iconDir, file), 'utf8');
    expect(content).toContain('<svg');
    expect(content).not.toMatch(/<script|(?:href|xlink:href|src)\s*=\s*["']https?:\/\//i);
  }
  await page.setContent('<img id="participant" alt="Participants" src="file://' + path.join(iconDir, 'participant.svg').replace(/\\/g, '/') + '">');
  await expect(page.locator('#participant')).toHaveAttribute('alt', 'Participants');
});

test('lottie runtime is presentation-only and failure tolerant', async ({ page }) => {
  const runtime = path.join(repo, 'assets', 'js', 'doclinc-clinical-lottie.js');
  expect(fs.existsSync(runtime)).toBeTruthy();
  const source = fs.readFileSync(runtime, 'utf8');
  expect(source).toContain('prefers-reduced-motion');
  expect(source).toContain('catch');
  expect(source).toContain('fallback');
  await page.setContent('<div id="animation"></div>');
  await page.addScriptTag({ content: source });
  const fallback = await page.evaluate(() => window.DoclincClinicalLottie.play(document.querySelector('#animation'), '/missing.json', 'Animation unavailable'));
  expect(fallback).toBeFalsy();
  await expect(page.locator('#animation')).toHaveText('Animation unavailable');
});
