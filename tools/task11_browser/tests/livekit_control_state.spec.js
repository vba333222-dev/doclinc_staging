const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const thread = fs.readFileSync(path.join(repoRoot, 'application/modules/chat/views/thread_v.php'), 'utf8');
const css = fs.readFileSync(path.join(repoRoot, 'assets/css/doclinc-livekit.css'), 'utf8');

test('LiveKit header is simplified and visible copy is Indonesian', () => {
  expect(thread).not.toContain('doclinc-call-security');
  expect(thread).not.toContain('doclinc-call-signal');
  expect(thread).not.toContain('doclinc-call-head-more');
  for (const label of ['Mute', 'Camera', 'Speaker', 'Chat', 'Participants', 'More', 'End Call', 'Minimize', 'loudspeaker']) {
    expect(thread).not.toContain(`>${label}<`);
  }
});

test('LiveKit control state uses icon color without a filled state overlay', async ({ page }) => {
  await page.setContent(`<style>${css}</style>
    <nav class="doclinc-call-controls">
      <button id="active" class="doclinc-call-control is-active"><img src="x.svg"><span class="doclinc-call-control-label">Mikrofon</span></button>
      <button id="inactive" class="doclinc-call-control is-off"><img src="x.svg"><span class="doclinc-call-control-label">Kamera</span></button>
    </nav>`);
  await page.mouse.move(500, 500);
  const states = await page.evaluate(() => {
    const read = id => {
      const button = document.getElementById(id);
      const icon = button.querySelector('img');
      const style = getComputedStyle(icon);
      const buttonStyle = getComputedStyle(button);
      return { filter: style.filter, background: style.backgroundColor, radius: style.borderRadius, buttonBackground: buttonStyle.backgroundColor, border: buttonStyle.borderWidth };
    };
    return { active: read('active'), inactive: read('inactive') };
  });
  expect(states.active.filter).not.toBe(states.inactive.filter);
  expect(states.active.background).toBe('rgba(0, 0, 0, 0)');
  expect(states.inactive.background).toBe('rgba(0, 0, 0, 0)');
  expect(states.active.radius).toBe('0px');
  expect(states.inactive.radius).toBe('0px');
  expect(states.active.buttonBackground).toBe(states.inactive.buttonBackground);
  expect(states.active.border).toBe(states.inactive.border);
});

test('LiveKit visible English-copy scan is fail-closed', async ({ page }) => {
  await page.setContent(`<main class="doclinc-call-panel">
    <header class="doclinc-call-head"><img alt="DokLinc"><span>Konsultasi dengan</span><strong>Dokter</strong></header>
    <nav class="doclinc-call-controls">
      <button aria-label="Bisukan mikrofon">Mikrofon</button><button aria-label="Matikan kamera">Kamera</button>
      <button aria-label="Aktifkan suara">Suara</button><button aria-label="Buka obrolan">Obrolan</button>
      <button aria-label="Tampilkan peserta">Peserta</button><button aria-label="Tampilkan opsi lainnya">Lainnya</button>
      <button aria-label="Akhiri panggilan">Akhiri Panggilan</button>
    </nav><aside aria-label="Opsi panggilan"><button>Ganti kamera</button><button>Minimalkan</button><button>Tutup tampilan</button></aside>
    <p>Menunggu video</p><p>Terhubung</p><p>Coba Lagi</p><p>Anda</p>
  </main>`);
  const copy = await page.locator('.doclinc-call-panel').evaluate(root => {
    const text = root.innerText;
    const attrs = [...root.querySelectorAll('[aria-label], [title]')].flatMap(el => [el.getAttribute('aria-label') || '', el.getAttribute('title') || '']).join(' ');
    return `${text} ${attrs}`;
  });
  const tokens = ['Mute', 'Camera', 'Speaker', 'Chat', 'Participants', 'More', 'End Call', 'Switch Camera', 'Minimize', 'Close View', 'Waiting', 'Connected', 'Retry', 'You'];
  const found = tokens.filter(token => new RegExp(`\\b${token.replace(/[- ]/g, '[- ]')}\\b`, 'i').test(copy));
  expect(found, `English visible copy: ${found.join(', ')}`).toEqual([]);
});
