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

test('imported assets are local valid payloads', () => {
  for (const file of ['participant.svg', 'close.svg', 'voicecall.svg', 'videocall.svg']) {
    const content = fs.readFileSync(path.join(iconDir, file), 'utf8');
    expect(content).toContain('<svg');
    expect(content).not.toMatch(/<script|(?:href|xlink:href|src)\s*=\s*["']https?:\/\//i);
  }
});

test('lottie runtime is presentation-only and failure tolerant', () => {
  const runtime = path.join(repo, 'assets', 'js', 'doclinc-clinical-lottie.js');
  expect(fs.existsSync(runtime)).toBeTruthy();
  const source = fs.readFileSync(runtime, 'utf8');
  expect(source).toContain('prefers-reduced-motion');
  expect(source).toContain('catch');
  expect(source).toContain('fallback');
});
