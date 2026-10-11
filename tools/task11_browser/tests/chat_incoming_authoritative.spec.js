const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const thread = fs.readFileSync(path.join(repoRoot, 'application/modules/chat/views/thread_v.php'), 'utf8');
const chatCss = fs.readFileSync(path.join(repoRoot, 'assets/css/style.css'), 'utf8')
  + fs.readFileSync(path.join(repoRoot, 'assets/css/doclinc-livekit.css'), 'utf8')
  + thread;

test('active chat production template exposes screenshot-authoritative surface hooks', () => {
  expect(thread).toContain('class="chat-info-banner"');
  expect(thread).toContain('class="chat-date-separator"');
  expect(thread).toContain('data-chat-role');
  expect(thread).toContain('data-chat-photo-state');
  expect(thread).toContain('placeholder="Ketik pesan..."');
  expect(thread).toContain("doclinc_ui_asset('attachment')");
  expect(thread).toContain("doclinc_ui_asset('camera')");
  expect(thread).toContain("doclinc_ui_asset('send')");
  expect(thread).not.toContain('doctor-placeholder.jpg');
  expect(thread).not.toContain('Rekam Medis Terenkripsi DokLinc');
  expect(chatCss).toMatch(/\.chat-shell\.is-active/);
  expect(chatCss).toMatch(/\.chat-info-banner/);
  expect(chatCss).toMatch(/\.chat-date-separator/);
});

test('incoming call production template is light, adaptive, and mode-specific', () => {
  expect(thread).toContain('class="doclinc-incoming-surface"');
  expect(thread).toContain('class="doclinc-incoming-badge"');
  expect(thread).toContain('class="doclinc-incoming-avatar"');
  expect(thread).toContain('class="doclinc-incoming-mode"');
  expect(thread).toContain('data-incoming-waveform');
  expect(thread).toContain('class="doclinc-incoming-slider"');
  expect(thread).toContain('aria-label="Terima panggilan"');
  expect(thread).toContain('aria-label="Tolak panggilan"');
  expect(thread).not.toContain('Terenkripsi End-to-End');
  expect(thread).not.toContain('home-indicator');
  expect(chatCss).toMatch(/\.doclinc-incoming-call/);
  expect(chatCss).toMatch(/\.doclinc-incoming-surface/);
  expect(chatCss).toMatch(/\.doclinc-incoming-slider/);
});

test('incoming voice and video copy remain distinct and truthful', () => {
  expect(thread).toContain("callTypeLabel(callType) {");
  expect(thread).toContain("return callType === 'audio' ? 'Panggilan suara' : 'Panggilan video';");
  expect(thread).toContain('Panggilan suara');
  expect(thread).toContain('Panggilan video');
});

test('reference screenshots are the explicit visual authority for this pass', async () => {
  const references = [
    path.join('C:', 'Project', 'doclinc-assets', 'incoming_call_&_chat', 'active chat', 'screen.png'),
    path.join('C:', 'Project', 'doclinc-assets', 'incoming_call_&_chat', 'incoming call', 'screen.png'),
  ];
  for (const reference of references) {
    expect(fs.existsSync(reference)).toBeTruthy();
  }
  await expect.poll(() => fs.statSync(references[0]).size).toBeGreaterThan(0);
  await expect.poll(() => fs.statSync(references[1]).size).toBeGreaterThan(0);
});
