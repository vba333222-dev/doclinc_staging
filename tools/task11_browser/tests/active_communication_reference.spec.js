const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const thread = fs.readFileSync(path.join(repoRoot, 'application/modules/chat/views/thread_v.php'), 'utf8');
const css = fs.readFileSync(path.join(repoRoot, 'assets/css/doclinc-livekit.css'), 'utf8');

test('active communication source exposes distinct chat, video, and voice presentation hooks', () => {
  expect(thread).toContain('data-call-mode="audio"');
  expect(thread).toContain('data-call-mode="video"');
  expect(thread).toContain("classList.toggle('is-audio'");
  expect(thread).toContain("classList.toggle('is-video'");
  expect(thread).toContain('doclinc-call-stage');
  expect(thread).toContain('chat-list');
  expect(thread).toContain('chat-input-row');
  expect(thread).toContain("doclinc_ui_asset('attachment')");
  expect(thread).toContain("doclinc_ui_asset('camera')");
  expect(thread).toContain("doclinc_ui_asset('send')");
});

test('communication CSS has explicit video and voice reference treatments', () => {
  expect(css).toMatch(/\.doclinc-call-modal\.is-video[\s\S]*\.doclinc-call-stage/);
  expect(css).toMatch(/\.doclinc-call-modal\.is-audio[\s\S]*\.doclinc-call-stage/);
  expect(css).toMatch(/\.doclinc-call-stage\.is-video/);
  expect(css).toMatch(/\.doclinc-call-stage\.is-audio/);
  expect(css).toMatch(/\.chat-shell\.is-active/);
  expect(css).toMatch(/\.chat-input-row[\s\S]*border-radius:\s*(?:2[0-9]|3[0-9])px/);
});

test('voice presentation does not force a video rectangle while video keeps a remote hero', async ({ page }) => {
  await page.setContent(`<style>body{margin:0;background:#fff}${css}</style>
    <section class="doclinc-call-modal is-video"><div class="doclinc-call-panel"><div class="doclinc-call-stage is-video"><div class="doclinc-call-remote"><video></video></div><div class="doclinc-call-local"></div></div></div></section>
    <section class="doclinc-call-modal is-audio"><div class="doclinc-call-panel"><div class="doclinc-call-stage is-audio"><div class="doclinc-call-remote"><div class="doclinc-call-empty"><span class="doclinc-call-empty-name">Dokter</span></div></div><div class="doclinc-call-local"></div></div></div></section>`);
  const modes = await page.evaluate(() => [...document.querySelectorAll('.doclinc-call-modal')].map(modal => {
    const stage = modal.querySelector('.doclinc-call-stage');
    const remote = modal.querySelector('.doclinc-call-remote');
    const local = modal.querySelector('.doclinc-call-local');
    return {
      mode: modal.classList.contains('is-audio') ? 'audio' : 'video',
      stageBackground: getComputedStyle(stage).backgroundImage,
      remoteDisplay: getComputedStyle(remote).display,
      localDisplay: getComputedStyle(local).display,
      stageHeight: stage.getBoundingClientRect().height
    };
  }));
  expect(modes[0].stageHeight).toBeGreaterThan(0);
  expect(modes[1].stageHeight).toBeGreaterThan(0);
  expect(modes[0].stageBackground).not.toBe(modes[1].stageBackground);
  expect(modes[1].localDisplay).toBe('none');
});

test('provider overlay is scoped to remote video and chat retains desktop width', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.setContent(`<style>body{margin:0}${css}</style>
    <section class="doclinc-call-modal is-audio"><div class="doclinc-call-stage is-audio"><div class="doclinc-call-provider-overlay">Dokter</div></div></section>
    <main class="chat-shell is-active"></main>`);
  await expect(page.locator('.doclinc-call-modal.is-audio .doclinc-call-provider-overlay')).toBeHidden();
  expect(await page.locator('.chat-shell.is-active').evaluate(el => getComputedStyle(el).maxWidth)).toBe('760px');
});

test('active chat composer is a usable pill with lightweight icon actions', async ({ page }) => {
  await page.setContent(`<style>body{margin:0}${css}</style>
    <main class="chat-shell is-active"><section class="chat-list"><div class="chat-bubble">Pesan aktif</div></section><form class="chat-form"><div class="chat-input-row"><button class="chat-icon-button" aria-label="Lampirkan gambar"><img src="attachment.svg"></button><textarea class="chat-input" placeholder="Tulis pesan..."></textarea><button class="chat-icon-button" aria-label="Kirim gambar"><img src="camera.svg"></button><button class="chat-send" aria-label="Kirim pesan"><img src="send.svg"></button></div></form></main>`);
  const info = await page.locator('.chat-input-row').evaluate(row => {
    const box = row.getBoundingClientRect();
    const style = getComputedStyle(row);
    const buttons = [...row.querySelectorAll('button')].map(button => button.getBoundingClientRect());
    return { width: box.width, height: box.height, radius: style.borderRadius, buttons };
  });
  expect(info.height).toBeGreaterThanOrEqual(44);
  expect(parseFloat(info.radius)).toBeGreaterThanOrEqual(20);
  expect(info.buttons.every(box => box.width >= 36 && box.height >= 36)).toBeTruthy();
});
