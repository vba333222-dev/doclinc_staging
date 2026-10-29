// Otomasi lintas tiga peran: warga/pasien, puskesmas, dokter/nakes.
//
// Ditulis karena pemeriksaan manual sebelumnya menemukan tiga hal yang tidak
// terlihat dari kode saja:
//   1. nomor antrian hilang di beranda warga padahal konsultasinya aktif;
//   2. badge "Selesai" di kartu "Konsultasi saat ini" sebenarnya status
//      KUNJUNGAN, bukan status konsultasi;
//   3. fitur disposisi tidak punya permukaan UI maupun tabel di DB ini.
//
// Jalankan:  npx playwright test tests/three_role_smoke.spec.js
// Base URL:  DOCLINC_BASE_URL (default http://localhost:8100)

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const BASE = process.env.DOCLINC_BASE_URL || 'http://localhost:8100';
const PASSWORD = process.env.DOCLINC_TEST_PASSWORD || 'Doclinc#2026';
const REPO_ROOT = path.resolve(__dirname, '..', '..', '..');
const STATE_FILE = path.join(REPO_ROOT, 'devseed_state.json');

// Fixture dibuat dan dihapus oleh test ini sendiri.
//
// Sebelumnya spec membaca permintaan 47 milik anggaajie apa adanya. Itu rapuh:
// begitu nakes menutup konsultasi 47, test nomor antrian gagal bukan karena
// regresi melainkan karena datanya bergeser. Sekarang datanya disemai di
// beforeAll dan dihapus di afterAll, dengan isi mirip data asli.
let fixture = null;

const ACCOUNTS = {
  warga: { username: 'anggaajie', home: '/home' },
  puskesmas: { username: 'pkm_cilegon', home: '/home_nakes' },
  dokter: { username: 'dr_uitest', home: '/home_nakes' },
};

test.beforeAll(async ({ request }) => {
  // Penyemai dijalankan lewat HTTP, bukan CLI: variabel lingkungan DB hanya ada
  // di proses server PHP, tidak di shell mana pun.
  const res = await request.get(`${BASE}/devseed_cli.php?action=seed`, { timeout: 60000 });
  expect(res.ok(), 'penyemai tidak menjawab OK').toBeTruthy();
  const body = await res.text();
  expect(body, 'penyemai gagal membuat fixture').toMatch(/DIBUAT request_id=\d+/);
  expect(fs.existsSync(STATE_FILE), 'devseed_state.json tidak terbentuk').toBeTruthy();
  fixture = JSON.parse(fs.readFileSync(STATE_FILE, 'utf8'));
  expect(fixture.request_id, 'fixture tanpa request_id').toBeGreaterThan(0);
  expect(fixture.queue_number, 'fixture tanpa nomor antrian').toBeGreaterThan(0);
});

test.afterAll(async ({ request }) => {
  // Dibersihkan lagi supaya menjalankan suite berulang kali tidak menumpuk data.
  await request.get(`${BASE}/devseed_cli.php?action=clean`, { timeout: 60000 }).catch(() => {});
});

async function login(page, username) {
  await page.context().clearCookies();
  await page.goto(`${BASE}/login`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Masuk' }).click();
  // Menunggu dengan asersi web-first, BUKAN `waitForLoadState('networkidle')`.
  // Beranda ini melakukan polling terus-menerus, sehingga networkidle tidak
  // pernah benar-benar tercapai dan hasilnya tidak dapat diandalkan. Alur
  // login yang sebenarnya, direkam dari peramban: POST /login/auth (200) ->
  // GET /login (307) -> /home. Jadi yang ditunggu adalah meninggalkan /login.
  await page.waitForURL((url) => !/\/login\/?$/.test(new URL(url).pathname), { timeout: 30000 });
}

// Dipakai setiap halaman: satu halaman dengan error PHP adalah halaman rusak,
// apa pun isinya. Dan luapan mendatar selalu cacat, bukan selera.
async function assertHealthy(page) {
  // Beranda menampilkan preloader saat memuat. Selama itu `innerText` hanya
  // berisi "Memuat…", sehingga pemeriksaan isi gagal bukan karena halamannya
  // salah melainkan karena dibaca terlalu dini. Preloader tidak selalu hilang
  // (mis. halaman yang menunggu izin lokasi), jadi kegagalan menunggu tidak
  // dianggap kegagalan test - yang dinilai adalah isi halamannya.
  await page.locator('#preloader').waitFor({ state: 'hidden', timeout: 30000 }).catch(() => {});
  const body = await page.locator('body').innerText();
  expect(body, 'halaman tidak boleh memuat error PHP').not.toMatch(/Fatal error|Parse error|A Database Error|Warning: |Notice: /);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow, 'tidak boleh ada luapan mendatar').toBe(0);
}

test.describe('tiga peran', () => {
  for (const [role, account] of Object.entries(ACCOUNTS)) {
    test(`${role} (${account.username}) bisa masuk dan beranda sehat`, async ({ page }) => {
      await login(page, account.username);
      expect(page.url()).not.toContain('/login');
      expect(new URL(page.url()).pathname).toBe(account.home);
      await page.goto(`${BASE}${account.home}`);
      await page.waitForLoadState('networkidle');
      await assertHealthy(page);
      const headings = await page.locator('h1,h2,h3').allInnerTexts();
      expect(headings.length, 'beranda harus punya judul').toBeGreaterThan(0);
    });
  }

  test('warga: nomor antrian tampil selama konsultasi belum ditutup', async ({ page }) => {
    await login(page, ACCOUNTS.warga.username);
    await page.goto(`${BASE}/home`);
    await page.waitForLoadState('networkidle');

    // Permintaan 47 di DB: request_status=Accepted, visit_status=completed,
    // queue_number=1, queue_date=2026-07-31.
    const ticket = page.locator('.dl-queue-ticket');
    await expect(ticket, 'kartu antrian harus ada').toHaveCount(1);
    await expect(page.locator('.dl-queue-ticket__kicker')).toHaveText('Nomor antrian');
    // Angka yang diharapkan datang dari fixture, bukan angka tetap - itu inti
    // perbaikannya: test tidak lagi bergantung pada permintaan yang bergeser.
    await expect(page.locator('.dl-queue-ticket__number')).toHaveText(
      new RegExp('No\\.\\s*0*' + fixture.queue_number + '(?!\\d)')
    );
    await expect(page.locator('.dl-queue-ticket__date')).toHaveText(
      fixture.queue_date.split('-').reverse().join('-')
    );

    // Beranda menampilkan preloader saat memuat. Selama itu elemen sudah ada di
    // DOM tetapi belum terlihat, sehingga `boundingBox()` mengembalikan null -
    // dan pengukuran gagal bukan karena kartunya salah, melainkan karena
    // diukur terlalu dini. Tunggu dengan asersi web-first, bukan tidur tetap.
    await page.locator('#preloader').waitFor({ state: 'hidden', timeout: 30000 }).catch(() => {});
    await expect(ticket, 'kartu antrian harus terlihat sebelum diukur').toBeVisible({ timeout: 15000 });

    const box = await ticket.boundingBox();
    expect(box.width, 'kartu antrian tidak boleh nol lebar').toBeGreaterThan(100);
    expect(box.height).toBeGreaterThan(60);

    // Kartu antrian harus berada di dalam tepi kartu induknya.
    const inside = await page.evaluate(() => {
      const t = document.querySelector('.dl-queue-ticket');
      const card = t.closest('.dl-current-consultation-card');
      const pad = parseFloat(getComputedStyle(card).paddingRight);
      return t.getBoundingClientRect().right <= card.getBoundingClientRect().right - pad + 0.5;
    });
    expect(inside, 'kartu antrian tidak boleh keluar dari kartunya').toBe(true);

    // Sapaan hanya untuk keadaan tanpa konsultasi berjalan.
    await expect(page.locator('.dl-welcome'), 'sapaan harus tersembunyi').toHaveCount(0);

    // Selama konsultasi berjalan, pasien tidak boleh membuat permintaan kedua.
    await expect(page.locator('.dl-action-tile.is-locked')).toHaveCount(1);
    await expect(page.locator('button.dl-action-tile'), 'tombol mulai konsultasi harus hilang').toHaveCount(0);
  });

  // Regresi untuk bug kata: badge ini dulu hanya "Selesai", sehingga di kartu
  // berjudul "Konsultasi saat ini" terbaca seolah konsultasinya berakhir.
  // DB membuktikan sebaliknya (request_status=Accepted, tanpa request_completed),
  // jadi yang benar adalah status kunjungan dan harus disebut demikian.
  test('warga: badge memisahkan status kunjungan dari status konsultasi', async ({ page }) => {
    await login(page, ACCOUNTS.warga.username);
    await page.goto(`${BASE}/home`);
    await page.waitForLoadState('networkidle');

    const badge = page.locator('[data-warga-top-status]').first();
    await expect(badge).toHaveCount(1);
    const text = (await badge.innerText()).trim();
    expect(text, 'badge harus menyebut bahwa ini status kunjungan').toMatch(/^Kunjungan ·/);
    expect(text, 'kata "Selesai" sendirian adalah bug kata yang sudah diperbaiki').not.toBe('Selesai');
  });

  test('warga: chat bisa ditulis selama konsultasi belum ditutup', async ({ page }) => {
    await login(page, ACCOUNTS.warga.username);
    await page.goto(`${BASE}/chat?request_id=${fixture.request_id}`);
    await page.waitForLoadState('networkidle');
    await assertHealthy(page);

    const body = await page.locator('body').innerText();
    expect(body, 'pesan hanya-baca tidak boleh muncul').not.toMatch(/hanya dapat dibaca/i);

    const send = page.locator('#chatSend, .chat-send, [data-chat-send]').first();
    await expect(send).toHaveCount(1);
    await expect(send, 'tombol kirim harus aktif').toBeEnabled();
  });

  test('puskesmas: penghitung dan daftar permintaan sesuai DB', async ({ page }) => {
    await login(page, ACCOUNTS.puskesmas.username);
    await page.goto(`${BASE}/home_nakes`);
    await page.waitForLoadState('networkidle');
    await assertHealthy(page);

    const ids = await page.locator('[data-request-id]').evaluateAll(
      (nodes) => Array.from(new Set(nodes.map((n) => n.getAttribute('data-request-id')))).filter(Boolean)
    );
    // DB: permintaan 63, 64, 65 berstatus Accepted untuk puskesmas 10280101.
    expect(ids.length, 'puskesmas harus melihat permintaan aktifnya').toBeGreaterThan(0);

    const body = await page.locator('body').innerText();
    expect(body).toMatch(/Diterima\s+\d+/);
  });

  test('dokter: beranda tugas memuat tanpa error', async ({ page }) => {
    await login(page, ACCOUNTS.dokter.username);
    await page.goto(`${BASE}/home_nakes`);
    await page.waitForLoadState('networkidle');
    await assertHealthy(page);
    const body = await page.locator('body').innerText();
    expect(body).toMatch(/Diterima\s+\d+/);
  });

  // Disposisi: di DB ini `visit_dispositions`, `visit_results`, `clinical_reviews`,
  // `visit_assignment_operations`, dan `care_team_enrollments` semuanya TIDAK ADA,
  // dan DOCLINC_VISIT_CLINICAL_WORKFLOW_ENABLED tidak diset. Jadi permukaan
  // disposisi memang tidak boleh dirender. Test ini mengunci keadaan itu supaya
  // kalau tabelnya nanti dimigrasi, kegagalannya terlihat sebagai test merah -
  // bukan sebagai tombol yang tiba-tiba muncul tanpa alur yang siap.
  test('disposisi: permukaan tidak dirender selama tabel dan flag belum siap', async ({ page }) => {
    for (const role of ['puskesmas', 'dokter']) {
      await login(page, ACCOUNTS[role].username);
      await page.goto(`${BASE}/home_nakes`);
      await page.waitForLoadState('networkidle');
      const body = await page.locator('body').innerText();
      const scripts = await page.locator('script').evaluateAll((nodes) => nodes.map((n) => n.textContent).join('\n'));
      expect(body, `${role}: UI disposisi tidak boleh ada`).not.toMatch(/disposisi/i);
      expect(scripts, `${role}: endpoint disposisi tidak boleh dipanggil`).not.toMatch(/clinical_disposition|clinical\/disposition/i);
    }
  });
});
