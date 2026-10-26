const { test, expect } = require('@playwright/test');

/**
 * Gerbang panel "Diagnosis belum dikode" pada halaman Infografis penyakit terbanyak.
 *
 * Yang dijaga di sini bukan angka, melainkan janji panelnya:
 *   1. Panel menyebut dengan bahasa biasa apa arti "belum dikode" dan mengapa
 *      daftarnya tidak mengikuti filter periode/kecamatan - tanpa itu admin
 *      melihat dua angka berbeda di satu halaman dan tidak tahu mana yang benar.
 *   2. Setiap baris punya status yang terbaca dan tindakan yang sesuai: baris
 *      yang bisa dikode otomatis TIDAK diberi tombol sendiri (satu tombol untuk
 *      semua), baris yang tidak bisa selalu punya tombol "Pilih kode".
 *   3. Angka di tombol sama dengan angka di panel. Tombol "kodekan 12 baris" di
 *      sebelah panel yang menulis 3 baris akan membuat admin berhenti percaya
 *      panel ini.
 *   4. Halaman pemilih kode hanya menawarkan kode yang boleh dipakai sebagai
 *      diagnosis dan memakai tombol yang menyebut kodenya.
 *
 * Uji ini bisa dijalankan pada database yang sudah bersih (semua terkode)
 * maupun yang masih punya tunggakan: keadaan kosong diuji sebagai keadaan yang
 * sah, bukan dilewati.
 */

const adminEmail = process.env.ADMIN_EMAIL || 'admin@lokal.test';
const adminPassword = process.env.ADMIN_PASSWORD || 'Lokal#2026!';
const adminBase = String(process.env.TASK11_BASE_URL || 'http://127.0.0.1:18090').replace(/\/+$/, '');

async function login(page) {
  const loginPage = await page.request.get(`${adminBase}/login`);
  expect(loginPage.status(), 'halaman login harus dapat dibuka').toBe(200);
  const html = await loginPage.text();
  const token = (html.match(/name="csrf_test_name"\s+value="([^"]+)"/) || [])[1];
  expect(token, 'token CSRF formulir login harus ada').toBeTruthy();
  const response = await page.request.post(`${adminBase}/login/ceklogin`, {
    form: { email: adminEmail, password: adminPassword, csrf_test_name: token },
  });
  expect((await response.text()).trim(), 'login harus dijawab "1"').toBe('1');
}

const BADGES = ['Bisa dikode otomatis', 'Perlu diperiksa', 'Perlu dipilih manual'];

test.describe('Panel pengodean diagnosis', () => {
  test('menjelaskan arti "belum dikode" dan menyebut daftarnya tidak mengikuti filter', async ({ page }) => {
    await login(page);
    await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });

    const panel = page.locator('#doclincCodingPanel');
    await expect(panel).toBeVisible();
    await expect(panel.getByRole('heading', { name: 'Diagnosis belum dikode' })).toBeVisible();

    const text = (await panel.innerText()).replace(/\s+/g, ' ');
    expect(text, 'panel harus menyebut teks yang belum punya kode').toContain('belum punya kode');
    expect(text, 'panel harus menyebut daftarnya tidak mengikuti filter').toContain('tidak mengikuti filter');
    expect(text, 'panel harus menyebut data tidak dihapus').toContain('tidak dihapus');
    if ((await panel.locator('.doclinc-coding-action').count()) > 0) {
      expect(text, 'panel harus menyebut teks aslinya tidak diubah').toContain('tidak diubah');
    }

    /* Penjelasan "bagaimana panel memutuskan" harus bisa dibuka keyboard:
       <details>/<summary> asli, bukan div yang diprogram. Hanya ada saat memang
       ada tunggakan yang perlu diputuskan. */
    if ((await panel.locator('.doclinc-coding-table tbody tr').count()) > 0) {
      await expect(panel.locator('summary')).toHaveCount(1);
      await expect(panel.locator('summary')).toHaveText(/Bagaimana panel ini memutuskan/);
    }
  });

  test('memakai satu istilah untuk status kode di seluruh halaman', async ({ page }) => {
    await login(page);
    await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });
    await page.locator('#doclincCodingPanel').scrollIntoViewIfNeeded();

    /* Dua istilah untuk satu hal ("terkode" di kartu angka, "belum dikode" di
       panel) membuat admin mengira keduanya berbeda. Satu istilah saja:
       "sudah dikode" / "belum dikode". */
    const body = (await page.locator('body').innerText()).toLowerCase();
    expect(body, 'halaman tidak boleh memakai istilah "terkode"').not.toContain('terkode');
    expect(body, 'panel harus memakai istilah "belum dikode"').toContain('belum dikode');

    /* Pasangan kartu angka harus sejajar: "sudah dikode" vs "belum dikode". */
    const labels = (await page.locator('.doclinc-analytics-card-label').allInnerTexts())
      .map((text) => text.trim().toLowerCase());
    expect(labels, 'kartu angka harus menyebut "sudah dikode"').toContain('sudah dikode');
    expect(labels, 'kartu angka harus menyebut "belum dikode"').toContain('belum dikode');
  });

  test('setiap baris punya status terbaca dan tindakan yang sesuai', async ({ page }) => {
    await login(page);
    await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });

    const panel = page.locator('#doclincCodingPanel');
    const rows = panel.locator('.doclinc-coding-table tbody tr');
    const rowCount = await rows.count();

    if (rowCount === 0) {
      /* Tidak ada tunggakan: panel harus mengatakan itu dengan kalimat, bukan
         menyisakan tabel kosong tanpa keterangan. */
      await expect(panel.locator('.doclinc-analytics-empty')).toContainText('tidak ada diagnosis yang belum punya kode');
      return;
    }

    for (let index = 0; index < rowCount; index += 1) {
      const row = rows.nth(index);
      const badge = (await row.locator('.doclinc-coding-badge').first().innerText()).trim();
      expect(BADGES, `baris ${index} harus punya status yang dikenal`).toContain(badge);

      const pick = row.getByRole('button', { name: /^Pilih kode$/ });
      if (badge === 'Bisa dikode otomatis') {
        expect(await pick.count(), `baris ${index} siap dikode tidak butuh tombol sendiri`).toBe(0);
      } else {
        expect(await pick.count(), `baris ${index} (${badge}) harus punya tombol "Pilih kode"`).toBe(1);
      }
      expect((await row.innerText()).trim().length, `baris ${index} tidak boleh kosong`).toBeGreaterThan(10);
    }
  });

  test('angka pada tombol kodekan otomatis sama dengan angka di panel', async ({ page }) => {
    await login(page);
    await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });

    const figures = await page.locator('.doclinc-coding-figure').allInnerTexts();
    const numeric = figures.map((text) => Number((text.match(/\d[\d.]*/) || ['0'])[0].replace(/\./g, '')));
    const ready = Number.isFinite(numeric[1]) ? numeric[1] : 0;

    const button = page.locator('.doclinc-coding-action button');
    if (ready === 0) {
      await expect(button, 'tanpa baris siap, tombol kodekan otomatis tidak boleh muncul').toHaveCount(0);
      return;
    }
    await expect(button).toHaveCount(1);
    const label = (await button.innerText()).replace(/\s+/g, ' ');
    const onButton = Number((label.match(/(\d[\d.]*)\s*baris/) || ['0', '0'])[1].replace(/\./g, ''));
    expect(onButton, 'angka tombol harus sama dengan kartu "bisa dikode otomatis"').toBe(ready);

    const action = page.locator('.doclinc-coding-action form');
    await expect(action).toHaveCount(1);
    await expect(action.locator('input[name="csrf_test_name"]'), 'formulir tulis wajib membawa token CSRF').toHaveCount(1);
  });

  test('pemilih kode hanya menawarkan kode yang boleh dipakai', async ({ page }) => {
    await login(page);
    await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });

    const pick = page.locator('.doclinc-coding-table tbody tr').getByRole('button', { name: /^Pilih kode$/ }).first();
    test.skip((await pick.count()) === 0, 'tidak ada teks yang perlu dipilih manual pada database ini');

    await pick.click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByRole('heading', { name: 'Pilih kode ICD-10' })).toBeVisible();
    await expect(page.locator('.doclinc-coding-target')).not.toBeEmpty();
    await expect(page.locator('#doclincCodingQuery')).toBeVisible();

    /* Cari kata yang pasti ada di master, lalu periksa tombolnya. Kata kunci
       tidak memakai teks tunggakan karena isinya bebas dan bisa berbahasa
       Indonesia, sedangkan master memakai judul resmi berbahasa Inggris. */
    await page.locator('#doclincCodingQuery').fill('dermatitis');
    await page.getByRole('button', { name: /Cari kode/ }).click();
    await page.waitForLoadState('domcontentloaded');

    const buttons = page.locator('.doclinc-coding-table tbody tr button');
    const total = await buttons.count();
    expect(total, 'pencarian "dermatitis" harus menemukan kandidat').toBeGreaterThan(0);
    for (let index = 0; index < Math.min(total, 5); index += 1) {
      const text = (await buttons.nth(index).innerText()).trim();
      expect(text, 'tombol pilih harus menyebut kodenya').toMatch(/^Pilih [A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/);
    }
  });
});

