const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const thread = fs.readFileSync(path.join(repoRoot, 'application/modules/chat/views/thread_v.php'), 'utf8');
const chatCss = fs.readFileSync(path.join(repoRoot, 'assets/css/style.css'), 'utf8')
  + fs.readFileSync(path.join(repoRoot, 'assets/css/doclinc-livekit.css'), 'utf8')
  + fs.readFileSync(path.join(repoRoot, 'assets/css/doclinc-livekit-call.css'), 'utf8')
  + thread;

test('active chat production template exposes screenshot-authoritative surface hooks', () => {
  // Petak identitas konsultasi (`chat-info-banner`) dihapus atas permintaan:
  // isinya - nama Puskesmas dan nomor antrian - sudah tampil di beranda dan di
  // halaman Konsultasi, dan status sesi terbaca dari kompornya sendiri.
  // Tombol "Lampirkan gambar" juga dihapus: ia dan tombol kamera membuka
  // pemilih berkas yang sama. Ikonnya pindah dari paket SVG ke Font Awesome.
  // Yang diuji di sini adalah kait permukaan yang MASIH ada.
  expect(thread).toContain('class="chat-date-separator"');
  expect(thread).toContain('data-chat-role');
  expect(thread).toContain('data-chat-photo-state');
  expect(thread).toContain('placeholder="Ketik pesan . . ."');
  expect(thread).toContain('id="imageInput"');
  expect(thread).toContain('class="chat-icon-button chat-upload"');
  expect(thread).toContain('aria-label="Kirim gambar"');
  expect(thread).toContain('class="chat-send"');
  expect(thread).toContain('aria-label="Kirim pesan"');
  expect(thread).not.toContain('doctor-placeholder.jpg');
  expect(thread).not.toContain('Rekam Medis Terenkripsi DokLinc');
  expect(thread, 'petak identitas konsultasi sudah dihapus dari markup').not.toContain('class="chat-info-banner"');
  expect(thread, 'tombol lampiran sudah dihapus').not.toContain("doclinc_ui_asset('attachment')");
  expect(chatCss).toMatch(/\.chat-shell\.is-active/);
  expect(chatCss).toMatch(/\.chat-date-separator/);
});

test('incoming call production template is full-screen, adaptive, and mode-specific', () => {
  expect(thread).toContain('class="doclinc-incoming-call');
  expect(thread).toContain('class="dl-incoming-surface"');
  expect(thread).toContain('class="dl-incoming-badge"');
  expect(thread).toContain('class="dl-incoming-avatar"');
  expect(thread).toContain('class="dl-incoming-mode"');
  expect(thread).toContain('data-incoming-waveform');
  expect(thread).toContain('class="dl-incoming-actions"');
  expect(thread).toContain('class="dl-incoming-action is-answer"');
  expect(thread).toContain('class="dl-incoming-action is-reject"');
  expect(thread).not.toContain('<div class="doclinc-incoming-slider"');
  expect(thread).not.toContain('Geser untuk menjawab');
  expect(thread).toContain('aria-label="Terima panggilan"');
  expect(thread).toContain('aria-label="Tolak panggilan"');
  expect(thread).not.toContain('Terenkripsi End-to-End');
  expect(thread).not.toContain('home-indicator');
  expect(chatCss).toMatch(/\.doclinc-incoming-call/);
  expect(chatCss).toMatch(/\.dl-incoming-surface/);
  expect(chatCss).toMatch(/\.dl-incoming-action\.is-answer/);
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
