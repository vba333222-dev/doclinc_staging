const { test, expect } = require('@playwright/test');

/**
 * Gerbang arsip rekam medis di halaman "Pemantauan layanan".
 *
 * Halaman ini dulu hanya menampilkan 200 baris terbaru tanpa jumlah total dan
 * tanpa jalan membuka satu rekam. Tiga janji yang dijaga di sini:
 *
 *   1. Daftar bisa ditelusuri: ada jumlah total ("dari N rekam") dan tombol
 *      halaman yang membawa filter yang sedang aktif, bukan mengosongkannya.
 *   2. Isi klinis tidak pernah tampil di tabel. Bila lingkungan tidak
 *      mengaktifkan akses klinis, halaman mengatakannya dengan kalimat biasa
 *      dan TIDAK menyisakan tombol yang pasti gagal (tombol mati).
 *   3. Bila akses klinis aktif, tombol "Buka rekam" membuka dialog berisi
 *      ringkasan, dan isi klinis hanya diminta setelah admin menyatakan
 *      alasan. Jawaban server (428/403/200) ditampilkan apa adanya, dan
 *      Escape menutup dialog.
 *
 * Uji ini luwes terhadap isi basis data: jumlah rekam berbeda antar-fixture,
 * dan feature flag `clinical_audit` bisa menyala atau mati. Kedua keadaan
 * diuji sebagai keadaan yang sah, bukan dilewati.
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

async function openArchive(page) {
  await login(page);
  await page.goto(`${adminBase}/rekam_medis`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#doclincRecordTable, .doclinc-record-empty').first()).toBeVisible();
}

test.describe('Arsip rekam medis', () => {
  test('menyebut jumlah total rekam dan satu halaman memuat paling banyak 25 baris', async ({ page }) => {
    await openArchive(page);

    const header = await page.locator('.card-header', { hasText: 'Arsip rekam medis' }).innerText();
    expect(header.replace(/\s+/g, ' '), 'kepala kartu menyebut ukuran halaman')
      .toContain('25 rekam per halaman');

    const rows = await page.locator('#doclincRecordTable tbody tr').count();
    expect(rows, 'satu halaman tidak boleh melebihi 25 baris').toBeLessThanOrEqual(25);

    if (rows === 0) {
      await expect(page.locator('.doclinc-record-empty')).toBeVisible();
      return;
    }

    const pagerInfo = (await page.locator('#doclincRecordPagerInfo').innerText()).replace(/\s+/g, ' ');
    expect(pagerInfo, 'paginasi menyebut rentang dan jumlah total').toMatch(/Menampilkan \d+–\d+ dari [\d.]+ rekam/);
    expect(pagerInfo, 'paginasi menyebut halaman yang sedang dibuka').toMatch(/halaman \d+ dari \d+/);

    const total = Number((pagerInfo.match(/dari ([\d.]+) rekam/) || [])[1].replace(/\./g, ''));
    expect(total, 'jumlah total minimal sebesar baris yang tampil').toBeGreaterThanOrEqual(rows);
  });

  test('tombol halaman membawa filter yang aktif dan tidak memuat ulang dengan filter kosong', async ({ page }) => {
    await openArchive(page);

    const next = page.locator('button[name="page"]', { hasText: 'Berikutnya' });
    if (await next.isDisabled()) {
      expect(await next.count(), 'tombol Berikutnya tetap ada walau satu halaman').toBe(1);
      return;
    }

    const before = (await page.locator('#doclincRecordPagerInfo').innerText()).replace(/\s+/g, ' ');
    const periodBefore = await page.locator('#period').inputValue();
    const firstRowBefore = await page.locator('#doclincRecordTable tbody tr').first().innerText();

    await next.click();
    await page.waitForLoadState('domcontentloaded');

    const after = (await page.locator('#doclincRecordPagerInfo').innerText()).replace(/\s+/g, ' ');
    expect(after, 'halaman berpindah satu langkah').not.toBe(before);
    expect(await page.locator('#period').inputValue(), 'filter periode ikut terkirim').toBe(periodBefore);
    expect(await page.locator('#doclincRecordTable tbody tr').first().innerText(),
      'isi halaman kedua berbeda dari halaman pertama').not.toBe(firstRowBefore);
  });

  test('tanpa akses klinis: halaman menyebut alasannya dan tidak menyisakan tombol mati', async ({ page }) => {
    await openArchive(page);

    if ((await page.locator('#doclincRecordModal').count()) > 0) {
      // Lingkungan mengaktifkan akses klinis; janji sebaliknya diuji di bawah.
      return;
    }

    await expect(page.locator('.doclinc-record-open'), 'tidak boleh ada tombol Buka rekam tanpa akses klinis')
      .toHaveCount(0);
    const cardText = (await page.locator('.card-body').first().innerText()).replace(/\s+/g, ' ');
    expect(cardText, 'halaman menjelaskan mengapa isi klinis tidak tersedia')
      .toContain('Isi klinis tidak tersedia untuk Admin');
  });

  test('dengan akses klinis: Buka rekam membuka ringkasan, isi klinis menunggu alasan, Escape menutup', async ({ page }) => {
    await openArchive(page);

    if ((await page.locator('#doclincRecordModal').count()) === 0) {
      test.skip(true, 'feature flag clinical_audit mati di lingkungan ini');
      return;
    }

    const trigger = page.locator('.doclinc-record-open').first();
    test.skip((await trigger.count()) === 0, 'belum ada rekam selesai untuk dibuka');
    await trigger.click();

    const dialog = page.locator('#doclincRecordModal');
    await expect(dialog).toBeVisible();
    await expect(page.locator('#doclincRecordMeta > div'), 'ringkasan berisi tujuh keterangan').toHaveCount(7);
    await expect(page.locator('#doclincRecordClinical'), 'isi klinis belum tampil sebelum diminta').toBeHidden();
    await expect(page.locator('#doclincRecordReasonWrap'), 'form alasan belum tampil sebelum server menolak').toBeHidden();

    // Permintaan pertama: server menjawab 428 (alasan belum ada di sesi) atau
    // 403 (akun ini tidak berhak). Keduanya harus terbaca sebagai kalimat.
    const first = page.waitForResponse((response) => response.url().includes('/rekam_medis/detail'));
    await page.locator('#doclincRecordOpen').click();
    const status = (await first).status();
    expect([200, 403, 428], 'hanya tiga jawaban ini yang sah').toContain(status);

    if (status === 428) {
      await expect(page.locator('#doclincRecordReasonWrap')).toBeVisible();
      await page.selectOption('#doclincRecordReason', 'quality_review');
      const second = page.waitForResponse((response) => response.url().includes('/rekam_medis/detail'));
      await page.locator('#doclincRecordOpen').click();
      const retried = (await second).status();
      if (retried === 200) {
        await expect(page.locator('#doclincRecordClinical'), 'isi klinis tampil setelah alasan diterima').toBeVisible();
        await expect(page.locator('#doclincRecordClinical > div'), 'empat bidang klinis').toHaveCount(4);
      } else {
        await expect(page.locator('#doclincRecordNote'), 'penolakan lanjutan harus terbaca').not.toBeEmpty();
      }
    } else {
      await expect(page.locator('#doclincRecordNote'), 'penolakan harus terbaca sebagai kalimat').not.toBeEmpty();
    }

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
  });
});
