const fs = require('fs');
const os = require('os');
const path = require('path');
const { test, expect } = require('@playwright/test');

/**
 * Sapuan visual panel admin pada fixture lokal.
 *
 * Tujuan: satu kali jalan menghasilkan tangkapan layar penuh untuk SETIAP rute
 * panel admin beserta metrik tata letak yang dipakai saat audit. Tangkapan
 * layar "sebelum" dan "sesudah" memakai data fixture yang sama, jadi
 * perbedaannya benar-benar berasal dari perubahan tata letak.
 *
 * Yang gagal keras (menghentikan uji):
 *   - status bukan 200
 *   - jejak galat PHP/MySQL di badan halaman
 *   - halaman terpantul kembali ke formulir login
 *
 * Yang hanya dicatat (metrik):
 *   - jumlah <h1> dan <h2>
 *   - luapan horizontal (scrollWidth > clientWidth)
 *   - tinggi dokumen
 * Dengan begitu jalan pertama pada kode lama tetap memberi laporan lengkap
 * tanpa gagal karena masalah yang memang sedang diperbaiki. Setel
 * STRICT_HEADINGS=1 untuk menegakkan kontrak satu <h1> per halaman.
 */

const ROUTES = [
  { route: 'home', label: 'Dasbor' },
  { route: 'kelola_warga', label: 'Kelola warga' },
  /* `kelola_dokter_nakes` kini hub Puskesmas: daftar sembilan Puskesmas inti
     dengan profil fasilitas, akun koordinator, dan staf di halaman detail.
     `master_puskesmas` tidak lagi dipotret karena rutenya hanya mengalihkan
     ke hub ini; `kelola_staff_puskesmas` tetap dipotret karena CRUD stafnya
     masih dipakai dan ditautkan dari halaman detail. */
  { route: 'kelola_dokter_nakes', label: 'Puskesmas' },
  { route: 'kelola_staff_puskesmas', label: 'Kelola staf Puskesmas' },
  { route: 'wilayah', label: 'Wilayah' },
  { route: 'rekam_medis', label: 'Rekam medis' },
  { route: 'diagnosis', label: 'Diagnosis' },
  { route: 'icd10', label: 'ICD-10' },
  { route: 'kelola_tindakan', label: 'Kelola tindakan' },
  { route: 'kelola_keluhan', label: 'Kelola keluhan' },
  { route: 'kelola_layanan_kesehatan', label: 'Kelola layanan kesehatan' },
  { route: 'konsultasi_kesehatan', label: 'Konsultasi kesehatan' },
  { route: 'kelola_news_feed', label: 'News feed' },
  { route: 'konten_landing', label: 'Konten landing' },
  { route: 'landing_page', label: 'Landing page' },
  { route: 'laporan', label: 'Laporan' },
  { route: 'kapabilitas', label: 'Kapabilitas admin' },
  { route: 'sesi', label: 'Sesi & keamanan' },
  { route: 'audit', label: 'Audit' },
];

const ERROR_NEEDLES = [
  'A PHP Error was encountered',
  '<p>Severity:',
  'Fatal error:',
  'Parse error:',
  'Database Error',
  'Unknown column',
  "doesn't exist",
  'Unable to connect to your database',
];

const adminEmail = process.env.ADMIN_EMAIL || 'admin@lokal.test';
const adminPassword = process.env.ADMIN_PASSWORD || 'Lokal#2026!';
const baselineDir = process.env.BASELINE_DIR || path.join(os.tmpdir(), 'doclinc-baseline');
const strictHeadings = process.env.STRICT_HEADINGS === '1';
/* URL dibangun dari TASK11_BASE_URL secara eksplisit, bukan lewat `baseURL`
   Playwright: panel disajikan dari subdirektori (`/admin_menu/`), dan `baseURL`
   yang berakhir tanpa slash membuat 'login' relatif melewati subdirektori itu.
   Bentuk eksplisit ini tetap benar untuk instalasi di akar maupun subdirektori. */
const adminBase = String(process.env.TASK11_BASE_URL || 'http://127.0.0.1:18090').replace(/\/+$/, '');

/** Login lewat jalur yang sama dengan pengguna: formulir + token CSRF. */
async function login(page) {
  const loginPage = await page.request.get(`${adminBase}/login`);
  expect(loginPage.status(), 'halaman login harus dapat dibuka').toBe(200);
  const html = await loginPage.text();
  const token = (html.match(/name="csrf_test_name"\s+value="([^"]+)"/) || [])[1];
  expect(token, 'token CSRF formulir login harus ada').toBeTruthy();
  const response = await page.request.post(`${adminBase}/login/ceklogin`, {
    form: { email: adminEmail, password: adminPassword, csrf_test_name: token },
  });
  const body = (await response.text()).trim();
  expect(body, 'login harus dijawab "1"').toBe('1');
}

/** Metrik per rute ditulis bertahap supaya laporan tetap utuh bila satu rute gagal. */
function writeManifest(projectName, entry) {
  const dir = path.join(baselineDir, projectName);
  fs.mkdirSync(dir, { recursive: true });
  const file = path.join(dir, 'manifest.json');
  let manifest = { project: projectName, generated_at: new Date().toISOString(), routes: [] };
  if (fs.existsSync(file)) {
    try { manifest = JSON.parse(fs.readFileSync(file, 'utf8')); } catch (error) { /* tulis ulang */ }
  }
  manifest.routes = manifest.routes.filter((row) => row.route !== entry.route);
  manifest.routes.push(entry);
  manifest.routes.sort((a, b) => a.route.localeCompare(b.route));
  fs.writeFileSync(file, `${JSON.stringify(manifest, null, 2)}\n`);
}

test.describe.configure({ mode: 'serial' });

for (const { route, label } of ROUTES) {
  test(`${label} (${route}) tampil tanpa galat`, async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page);

    const response = await page.goto(`${adminBase}/${route}`, { waitUntil: 'load' });
    expect(response, 'navigasi harus menghasilkan respons').not.toBeNull();
    const status = response.status();

    /* Animasi dimatikan sebelum tangkapan layar: kartu statistik memakai
       animasi masuk, dan tanpa ini setiap tangkapan menangkap fase berbeda. */
    await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}' });
    await page.waitForTimeout(400);

    const html = await page.content();
    const dir = path.join(baselineDir, testInfo.project.name);
    fs.mkdirSync(dir, { recursive: true });
    await page.screenshot({ path: path.join(dir, `${route}.png`), fullPage: true });

    const metrics = await page.evaluate(() => ({
      h1: document.querySelectorAll('h1').length,
      h2: document.querySelectorAll('h2').length,
      title: (document.title || '').trim(),
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
      scrollHeight: document.documentElement.scrollHeight,
      /* Token desain wajib terdefinisi. Bila `../assets/css/doclinc-reference-ui.css`
         gagal dimuat (mis. DocumentRoot menunjuk langsung ke `admin_menu/`),
         permintaannya dijawab halaman login HTTP 200, setiap `var(--dl-ref-*)`
         menjadi kosong, dan ukuran teks jatuh ke nilai warisan - halaman tetap
         "tampil", jadi tanpa pemeriksaan ini kegagalan itu tidak terlihat. */
      tokensLoaded: getComputedStyle(document.documentElement).getPropertyValue('--dl-ref-control').trim() !== '',
      /* Hanya tombol yang benar-benar terlukis. Dua tombol peta memang
         disembunyikan sampai keadaannya berlaku (`hidden` pada tombol layar
         penuh, `display:none` pada bar keluar layar penuh), jadi tinggi 0 di
         sana adalah desain, bukan sasaran sentuh yang mengecil. Tanpa saringan
         ini rute diagnosis selalu gagal karena tombol tersembunyi. */
      actionHeights: Array.from(document.querySelectorAll('.dl-adm-action'))
        .filter((el) => el.getClientRects().length > 0)
        .map((el) => Math.round(el.getBoundingClientRect().height)),
    }));

    /* `overflow` dihitung sekali di sini. Sebelumnya nilai yang sama ditulis
       langsung ke manifest, sementara assertion di bawah membaca
       `metrics.overflow` - yang tidak pernah ada - sehingga mode
       STRICT_HEADINGS selalu gagal di rute pertama dengan `undefined`,
       berapa pun lebar halamannya. */
    const overflow = metrics.scrollWidth > metrics.clientWidth + 1;

    writeManifest(testInfo.project.name, {
      route,
      label,
      status,
      ...metrics,
      overflow,
      screenshot: path.join(dir, `${route}.png`),
    });

    expect(status, `${route} harus HTTP 200`).toBe(200);
    for (const needle of ERROR_NEEDLES) {
      expect(html.includes(needle), `${route} memuat jejak galat "${needle}"`).toBe(false);
    }
    /* Penanda login memakai placeholder formulir login, bukan
       `input[name="email"]`: halaman modul juga punya kolom email di dalam
       modal (Kelola dokter & nakes punya dua), dan penanda lama membuat
       halaman yang sehat dianggap terpantul ke login. Teks ini sama dengan
       yang dipakai `tools/admin_panel/local/sweep.php`. */
    expect(
      await page.locator('input[placeholder="Masukkan alamat email"]').count(),
      `${route} tidak boleh terpantul ke login`,
    ).toBe(0);
    expect(metrics.title.length, `${route} harus punya judul dokumen`).toBeGreaterThan(0);

    if (strictHeadings) {
      expect(metrics.h1, `${route} harus punya tepat satu <h1>`).toBe(1);
      expect(overflow, `${route} tidak boleh meluap horizontal`).toBe(false);
      expect(metrics.tokensLoaded, `${route} harus memuat lapisan token desain`).toBe(true);
      /* Ambang sasaran sentuh dipegang lapisan token (44px untuk `.btn`, 48px
         untuk primary/success). Uji ini menegakkannya pada hasil render, bukan
         pada isi berkas CSS: deklarasi di berkas lain bisa kalah oleh
         `!important`, dan itulah yang dulu terjadi pada varian 34px. */
      for (const height of metrics.actionHeights) {
        expect(height, `${route} tombol aksi minimal 44px`).toBeGreaterThanOrEqual(44);
      }
    }
  });
}

/**
 * Menu samping di layar sempit.
 *
 * Sembilan belas rute di atas hanya memotret halaman dengan menu tertutup,
 * sehingga satu kerusakan lolos tanpa terlihat: keadaan "terbuka" dikaitkan ke
 * kelas `.show` yang tidak pernah dipasang skrip mana pun. Diukur pada 390px,
 * transform panel tetap -250px setelah tombol menu ditekan - artinya tidak ada
 * halaman yang bisa dicapai dari ponsel. Uji ini menekan tombolnya sungguhan,
 * memeriksa panel masuk layar, lalu menutupnya lewat latar gelap.
 */
test('menu samping bisa dibuka dan ditutup di layar sempit', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'admin-mobile', 'panel geser hanya berlaku sampai 768px');

  await page.emulateMedia({ reducedMotion: 'reduce' });
  await login(page);
  await page.goto(`${adminBase}/home`, { waitUntil: 'load' });

  const sidebar = page.locator('#accordionSidebar');
  const toggle = page.locator('#sidebarToggleTop');
  const backdrop = page.locator('#sidebarBackdrop');

  /* 1. Saat halaman dibuka panel harus sudah di luar layar. */
  await expect.poll(async () => {
    const box = await sidebar.boundingBox();
    return box ? Math.round(box.x + box.width) : 0;
  }, { timeout: 5000 }).toBeLessThanOrEqual(0);

  await expect(toggle).toBeVisible();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(backdrop).toBeHidden();

  /* 2. Tombol menu membuka panel: tepi kirinya menyentuh tepi layar. */
  await toggle.click();
  await expect.poll(async () => {
    const box = await sidebar.boundingBox();
    return box ? Math.round(box.x) : -999;
  }, { timeout: 5000 }).toBe(0);
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(backdrop).toBeVisible();

  /* 3. Empat kelompok ikut terbawa ke panel geser. */
  await expect(sidebar.locator('.doclinc-sidebar-heading')).toHaveCount(4);

  /* 4. Latar gelap menutup panel. Diketuk di luar lebar panel (250px). */
  await backdrop.click({ position: { x: 360, y: 400 } });
  await expect.poll(async () => {
    const box = await sidebar.boundingBox();
    return box ? Math.round(box.x + box.width) : 0;
  }, { timeout: 5000 }).toBeLessThanOrEqual(0);
  await expect(backdrop).toBeHidden();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
});

