const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

/**
 * Gerbang copy dan peta: dua hal yang tidak terlihat dari uji "halaman merender".
 *
 *   1. Label tombol. Aturan copy panel meminta label pendek yang tidak terlipat.
 *      Label bisa tetap pendek dan tetap pecah dua baris bila wadahnya sempit:
 *      kolom aksi tabel menyusut jauh di bawah lebar labelnya pada layar ponsel,
 *      dan tombol di kepala kartu ikut menyusut bersama flex induknya. Karena
 *      itu yang diukur bukan panjang teks, melainkan jumlah baris teks di dalam
 *      tombol DAN lebar isi yang terpotong, pada tiga lebar layar.
 *   2. Peta sebaran penyakit. Peta digambar dari GeoJSON lokal tanpa tile, jadi
 *      kegagalan yang paling mudah terjadi tidak terlihat sebagai galat: berkas
 *      batas wilayah tidak termuat, nama kecamatan tidak cocok, dan halaman
 *      tetap "hijau" sambil menampilkan kotak kosong. Uji ini memastikan
 *      poligonnya benar-benar tergambar, dan bahwa satu-satunya jalan membuka
 *      angka kecamatan - klik pada poligonnya - benar-benar membuka kartu
 *      infografik yang mengambang di atas peta.
 *
 * Daftar rute dibaca dari `admin_panel_visual.spec.js`, sama seperti gerbang
 * aksesibilitas, supaya halaman baru tidak bisa lolos dari sini hanya karena
 * daftarnya ditulis ulang di tempat lain.
 */

const VISUAL_SPEC = path.join(__dirname, 'admin_panel_visual.spec.js');

function routesFromVisualSpec() {
  const source = fs.readFileSync(VISUAL_SPEC, 'utf8');
  const found = [];
  const re = /route:\s*'([a-z0-9_]+)'/g;
  let match = re.exec(source);
  while (match) {
    found.push(match[1]);
    match = re.exec(source);
  }
  return found;
}

const ROUTES = routesFromVisualSpec();

/* Tiga lebar yang mewakili tiga titik patah tata letak: desktop penuh, tablet
   (tempat tabel mulai menyusut), dan ponsel 390px - lebar terkecil yang masih
   dipakai pengguna nyata dan yang paling dulu merusak label. */
const VIEWPORTS = [
  { label: 'desktop 1440px', width: 1440, height: 900 },
  { label: 'tablet 1024px', width: 1024, height: 900 },
  { label: 'ponsel 390px', width: 390, height: 844 },
];

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

/**
 * Dijalankan DI DALAM halaman; mengembalikan temuan, bukan melempar, supaya
 * satu kegagalan menyebutkan seluruh pelanggarnya sekaligus.
 */
function collectSplitButtons() {
  const label = (el) => (el.innerText || '').replace(/\s+/g, ' ').trim();
  /* Jumlah baris diukur per simpul teks, bukan dari tinggi elemen: `innerText`
     sudah dinormalkan menjadi satu baris oleh kode di atas, dan tinggi elemen
     tidak bisa membedakan "dua baris" dari "satu baris berpadding besar". */
  const lines = (el) => {
    let max = 0;
    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
    let node;
    while ((node = walker.nextNode())) {
      if (!node.nodeValue.trim()) continue;
      if (node.parentElement && node.parentElement.closest('.sr-only, .visually-hidden')) continue;
      const range = document.createRange();
      range.selectNodeContents(node);
      const rects = [...range.getClientRects()].filter((r) => r.width > 2 && r.height > 2);
      if (!rects.length) continue;
      max = Math.max(max, new Set(rects.map((r) => Math.round(r.top))).size);
    }
    return max;
  };
  const out = [];
  document.querySelectorAll('main a.btn, main button.btn, main .dl-adm-action').forEach((el) => {
    if (el.getClientRects().length === 0) return; // modal tertutup, tidak dirender
    const text = label(el);
    if (!text) return;
    const count = lines(el);
    const clipped = el.scrollWidth > el.clientWidth + 1;
    if (count > 1 || clipped) {
      const rect = el.getBoundingClientRect();
      out.push({ text, count, clipped, width: Math.round(rect.width) });
    }
  });
  return out;
}

/**
 * Titik di dalam sebuah poligon yang benar-benar menerima klik.
 *
 * Titik tengah kotak pembatas tidak bisa dipakai: pada poligon cekung titik itu
 * bisa jatuh di luar bentuknya, dan Leaflet hanya meneruskan klik yang mengenai
 * isi path. `elementFromPoint` sekaligus memastikan titik itu memang milik
 * poligon yang dituju, bukan kartu atau legenda yang kebetulan menutupinya.
 *
 * Dijalankan setelah peta digulir ke dalam layar, karena koordinat yang
 * dikembalikan dipakai oleh `page.mouse.click` dan karenanya harus koordinat
 * layar, bukan koordinat halaman.
 */
async function districtPoint(page, index = 0) {
  const found = await page.evaluate((idx) => {
    const path = [...document.querySelectorAll('#doclincDiseaseMap path[role="button"]')][idx];
    if (!path) return null;
    const name = (path.getAttribute('aria-label') || '')
      .replace(/^Kecamatan /, '')
      .split(':')[0]
      .trim();
    /* Titik harus berada DI DALAM bentuk, bukan di tepinya: satu piksel di
       sisi batas bisa jatuh ke kecamatan tetangga setelah koordinat dibulatkan
       atau setelah poligon tetangga digambar ulang. Empat titik di sekitarnya
       ikut diuji, jadi titik yang lolos benar-benar milik poligon ini. */
    const inside = (x, y) => document.elementFromPoint(x, y) === path;
    const rect = path.getBoundingClientRect();
    for (let y = rect.top + 3; y < rect.bottom - 2; y += 3) {
      for (let x = rect.left + 3; x < rect.right - 2; x += 3) {
        if (inside(x, y) && inside(x - 3, y) && inside(x + 3, y)
          && inside(x, y - 3) && inside(x, y + 3)) {
          return { x: Math.round(x), y: Math.round(y), name };
        }
      }
    }
    return null;
  }, index);
  expect(found, 'poligon kecamatan harus punya titik yang bisa diklik').toBeTruthy();
  return found;
}

for (const viewport of VIEWPORTS) {
  test(`label tombol utuh - ${viewport.label}`, async ({ page }, testInfo) => {
    /* Lebar diuji eksplisit di sini, jadi profil perangkat tidak menambah
       cakupan - hanya menggandakan 20 pemuatan halaman. Proyek ponsel tetap
       menguji 390px sekali; sisanya cukup di proyek desktop. */
    test.skip(testInfo.project.name === 'admin-mobile' && viewport.width !== 390,
      'lebar 1440/1024 sudah diuji di proyek desktop');
    await login(page);
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    const offenders = [];
    for (const route of ROUTES) {
      await page.goto(`${adminBase}/${route}`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(150);
      const found = await page.evaluate(collectSplitButtons);
      for (const item of found) {
        offenders.push(
          `${route}: "${item.text}" ${item.count} baris` +
            `${item.clipped ? ' terpotong' : ''} pada ${item.width}px`
        );
      }
    }
    expect(offenders, 'label tombol tidak boleh terlipat atau terpotong').toEqual([]);
  });
}

test('peta sebaran penyakit tergambar, berkelas warna, dan tanpa gelembung', async ({ page }) => {
  await login(page);
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(String(error)));
  const failedRequests = [];
  page.on('response', (response) => {
    if (response.status() >= 400) failedRequests.push(`${response.status()} ${response.url()}`);
  });

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });

  /* Peta dirakit setelah GeoJSON diambil, jadi tunggu sampai poligonnya ada. */
  await page.waitForSelector('#doclincDiseaseMap path[role="button"]');

  const map = await page.evaluate(() => {
    const host = document.getElementById('doclincDiseaseMap');
    /* Leaflet dengan renderer SVG menggambar circleMarker sebagai <path> berisi
       perintah busur, bukan <circle>. Uji ini menghitungnya untuk MEMASTIKAN
       tidak ada gelembung lagi: penanda nilai di peta sekarang hanya warna
       kelas, dan seleksi hanya mengubah warna poligon. */
    const paths = host ? [...host.querySelectorAll('path.leaflet-interactive')] : [];
    const bubbles = paths.filter((path) => /[aA]\s*[-\d.]/.test(path.getAttribute('d') || ''));
    return {
      host: Boolean(host),
      polygons: paths.length - bubbles.length,
      bubbles: bubbles.length,
      height: host ? Math.round(host.getBoundingClientRect().height) : 0,
      swatchColors: [...document.querySelectorAll('.doclinc-map-swatch')]
        .map((element) => getComputedStyle(element).backgroundColor),
      legendRanges: [...document.querySelectorAll('.doclinc-map-legend-range')]
        .map((element) => (element.textContent || '').trim()),
      /* Peringkat di samping peta sudah dihapus: angka kecamatan hanya ada di
         kartu yang dibuka lewat klik pada poligonnya. Kalau daftar itu kembali,
         peta punya dua jalan masuk dan uji ini menolaknya. */
      sideLists: document.querySelectorAll('#doclincMapRanking, .doclinc-map-ranking').length,
      cardHidden: (document.getElementById('doclincMapSelection') || {}).hidden,
      tableRows: document.querySelectorAll('.doclinc-map-table tbody tr').length,
      status: (document.getElementById('doclincMapStatus') || {}).textContent || '',
      focusable: paths.filter((path) => path.getAttribute('tabindex') === '0').length,
      labelled: paths.filter((path) => /^Kecamatan /.test(path.getAttribute('aria-label') || '')).length,
      fills: [...new Set(paths.map((path) => path.getAttribute('fill')))],
    };
  });

  expect(map.host, 'kontainer peta harus ada di halaman diagnosis').toBe(true);
  expect(map.polygons, 'batas kecamatan harus tergambar dari GeoJSON lokal').toBeGreaterThan(0);
  expect(map.bubbles, 'peta tidak boleh lagi memakai gelembung angka').toBe(0);
  expect(map.height, 'kontainer peta tidak boleh runtuh menjadi nol').toBeGreaterThan(120);
  expect(map.tableRows, 'tabel angka peta harus memuat kecamatan').toBeGreaterThan(0);
  expect(map.sideLists, 'daftar kecamatan di samping peta sudah dihapus').toBe(0);
  expect(map.cardHidden, 'kartu infografik harus tersembunyi sebelum ada pilihan').toBe(true);
  expect(new Set(map.swatchColors).size, 'legenda harus punya warna kelas yang berbeda-beda')
    .toBeGreaterThanOrEqual(5);
  expect(map.legendRanges[0], 'kelas nol harus berlabel 0').toBe('0');
  expect(map.legendRanges.filter((text) => text !== '0' && text !== '…').length,
    'setiap kelas nilai harus berlabel rentang angka').toBeGreaterThanOrEqual(3);
  expect(map.focusable, 'setiap poligon harus dapat difokus papan ketik').toBeGreaterThanOrEqual(map.polygons);
  expect(map.labelled, 'setiap poligon harus punya label aksesibel').toBeGreaterThanOrEqual(map.polygons);
  expect(map.fills.length, 'warna peta harus berbeda antar-kelas nilai').toBeGreaterThanOrEqual(2);
  expect(map.status, 'status peta harus menyebut jumlah diagnosis').toContain('diagnosis');
  expect(failedRequests, 'tidak boleh ada subresource peta yang gagal dimuat').toEqual([]);
  expect(pageErrors, 'skrip peta tidak boleh melempar galat').toEqual([]);
});

test('peta bisa dibuka layar penuh lewat tombol dan ditutup lagi', async ({ page }) => {
  await login(page);
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(String(error)));

  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#doclincDiseaseMap path[role="button"]');

  /* Diukur dari layar, bukan dari kelas: tombol ini memakai Fullscreen API bila
     ada dan kelas `is-expanded` bila tidak. Kedua jalur harus sama-sama
     membesarkan PETA-nya, jadi yang diperiksa tinggi peta, bukan mekanismenya. */
  const measure = () => page.evaluate(() => {
    const canvas = document.querySelector('.doclinc-map-canvas');
    const host = document.getElementById('doclincDiseaseMap');
    const bar = document.getElementById('doclincMapFullBar');
    const exit = document.getElementById('doclincMapExit');
    const toggle = document.getElementById('doclincMapFullscreen');
    const fullscreen = document.fullscreenElement || document.webkitFullscreenElement || null;
    return {
      fullscreen: fullscreen === canvas,
      expanded: Boolean(canvas && canvas.classList.contains('is-expanded')),
      mapHeight: host ? Math.round(host.getBoundingClientRect().height) : 0,
      barHeight: bar ? Math.round(bar.getBoundingClientRect().height) : 0,
      exitVisible: Boolean(exit && exit.getClientRects().length > 0),
      pressed: toggle ? toggle.getAttribute('aria-pressed') : null,
      label: toggle ? (toggle.textContent || '').replace(/\s+/g, ' ').trim() : '',
      polygons: host ? host.querySelectorAll('path.leaflet-interactive').length : 0,
    };
  });

  const toggle = page.locator('#doclincMapFullscreen');
  await expect(toggle, 'tombol layar penuh harus tersedia begitu peta tergambar').toBeVisible();
  await expect(toggle).toHaveAttribute('aria-pressed', 'false');
  await expect(toggle).toContainText('Layar penuh');

  const before = await measure();
  expect(before.fullscreen || before.expanded, 'peta harus mulai dalam keadaan normal').toBe(false);
  expect(before.mapHeight, 'peta harus tergambar sebelum dibesarkan').toBeGreaterThan(120);
  expect(before.barHeight, 'bar keluar tidak boleh tampil sebelum peta dibesarkan').toBe(0);

  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-pressed', 'true');
  await expect(toggle).toContainText('Keluar layar penuh');

  const opened = await measure();
  expect(opened.fullscreen || opened.expanded, 'peta harus masuk mode layar penuh').toBe(true);
  expect(opened.barHeight, 'bar judul dan tombol keluar harus tampil di layar penuh').toBeGreaterThan(0);
  expect(opened.exitVisible, 'tombol keluar harus terlihat selama peta besar').toBe(true);
  expect(opened.mapHeight, 'peta harus ikut membesar, bukan hanya bingkainya')
    .toBeGreaterThan(before.mapHeight + 60);
  expect(opened.polygons, 'poligon tidak boleh hilang saat peta dibesarkan').toBe(before.polygons);

  await page.locator('#doclincMapExit').click();
  await expect(toggle).toHaveAttribute('aria-pressed', 'false');
  await expect.poll(
    () => page.evaluate(() => Boolean(document.fullscreenElement || document.webkitFullscreenElement)),
    { message: 'peramban harus keluar dari layar penuh' }
  ).toBe(false);

  const closed = await measure();
  expect(closed.expanded, 'kelas layar penuh harus dilepas').toBe(false);
  expect(closed.mapHeight, 'peta harus kembali ke tinggi panel').toBeLessThan(opened.mapHeight - 60);
  expect(closed.barHeight, 'bar keluar harus tersembunyi lagi').toBe(0);
  expect(closed.polygons, 'poligon harus tetap utuh setelah keluar').toBe(before.polygons);
  expect(pageErrors, 'skrip peta tidak boleh melempar galat saat peta dibesarkan').toEqual([]);
});

test('layar penuh peta tetap bekerja tanpa Fullscreen API', async ({ page }) => {
  await login(page);

  /* Meniru Safari iOS: elemen biasa tidak bisa masuk layar penuh. Jalur
     cadangannya (kanvas ditempel ke seluruh layar) harus tetap membesarkan
     peta - kalau tidak, tombol ini mati di ponsel. */
  await page.addInitScript(() => {
    Object.defineProperty(Element.prototype, 'requestFullscreen', { value: undefined, configurable: true });
    Object.defineProperty(Element.prototype, 'webkitRequestFullscreen', { value: undefined, configurable: true });
  });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#doclincDiseaseMap path[role="button"]');

  const mapHeight = () => page.evaluate(
    () => Math.round(document.getElementById('doclincDiseaseMap').getBoundingClientRect().height)
  );
  const before = await mapHeight();

  await page.locator('#doclincMapFullscreen').click();
  await page.waitForFunction(
    () => document.querySelector('.doclinc-map-canvas').classList.contains('is-expanded'),
    null,
    { timeout: 3000 }
  );
  await expect.poll(mapHeight, { message: 'peta harus membesar tanpa Fullscreen API' })
    .toBeGreaterThan(before + 40);

  /* Escape adalah satu-satunya jalan keluar di jalur ini: peramban tidak
     menyediakan Escape layar penuh saat kanvas hanya ditempel dengan CSS. */
  await page.keyboard.press('Escape');
  await page.waitForFunction(
    () => !document.querySelector('.doclinc-map-canvas').classList.contains('is-expanded'),
    null,
    { timeout: 3000 }
  );
  await expect.poll(mapHeight, { message: 'peta harus kembali ke tinggi panel' })
    .toBeLessThan(before + 4);
});

test('klik kecamatan membuka 10 penyakit dan mewarnai kecamatan terpilih', async ({ page }) => {
  await login(page);
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(String(error)));

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#doclincDiseaseMap path[role="button"]');
  await page.locator('#doclincDiseaseMap').scrollIntoViewIfNeeded();

  const panel = page.locator('#doclincMapSelection');
  await expect(panel, 'kartu infografik harus tersembunyi sebelum ada pilihan').toBeHidden();

  /* Klik poligonnya sendiri. Tidak ada lagi daftar kecamatan di samping peta,
     jadi klik pada peta adalah satu-satunya jalan membuka angka kecamatan - dan
     itulah yang harus dibuktikan, bukan jalan pintas yang sudah dihapus. */
  const point = await districtPoint(page, 0);
  await page.mouse.click(point.x, point.y);

  await expect(panel).toBeVisible();
  await expect(page.locator('#doclincMapSelectionTitle')).toContainText(point.name);
  const rows = await page.locator('#doclincMapSelectionList li').count();
  expect(rows, 'daftar penyakit terbanyak tidak boleh lebih dari sepuluh baris').toBeLessThanOrEqual(10);
  expect(rows, 'kecamatan berisi data harus menampilkan minimal satu penyakit').toBeGreaterThan(0);

  const selected = await page.evaluate((name) => {
    const host = document.getElementById('doclincDiseaseMap');
    const paths = [...host.querySelectorAll('path[role="button"]')];
    const path = paths.find((element) => (element.getAttribute('aria-label') || '').indexOf('Kecamatan ' + name + ':') === 0);
    return {
      found: Boolean(path),
      fill: path ? String(path.getAttribute('fill') || '').toLowerCase() : '',
      pressed: path ? path.getAttribute('aria-pressed') : '',
      otherFills: paths.filter((element) => element !== path)
        .map((element) => String(element.getAttribute('fill') || '').toLowerCase()),
      expected: getComputedStyle(document.documentElement).getPropertyValue('--dl-ref-select').trim().toLowerCase(),
    };
  }, point.name);

  expect(selected.found, 'poligon kecamatan terpilih harus ditemukan lewat labelnya').toBe(true);
  expect(selected.pressed, 'poligon terpilih harus ditandai aria-pressed').toBe('true');
  expect(selected.fill, 'warna poligon terpilih harus warna seleksi, bukan warna kelas')
    .toBe(selected.expected);
  expect(selected.otherFills, 'kecamatan lain tidak boleh ikut berwarna seleksi')
    .not.toContain(selected.fill);

  /* Kartu harus benar-benar mengambang DI ATAS peta: posisi absolut, berada di
     dalam kotak peta, di atas pane Leaflet, dan dianimasikan masuk. Kartu yang
     jatuh kembali ke aliran dokumen akan menghidupkan lagi masalah yang
     dihapus: jawaban yang muncul di luar pandangan setelah orang mengklik. */
  const card = await page.evaluate(() => {
    const node = document.getElementById('doclincMapSelection');
    const mapNode = document.getElementById('doclincDiseaseMap');
    if (!node || !mapNode) return null;
    const box = node.getBoundingClientRect();
    const frame = mapNode.getBoundingClientRect();
    const style = getComputedStyle(node);
    return {
      position: style.position,
      animation: style.animationName,
      duration: style.animationDuration,
      zIndex: Number(style.zIndex),
      insideMap: box.top >= frame.top - 1 && box.bottom <= frame.bottom + 1
        && box.left >= frame.left - 1 && box.right <= frame.right + 1,
      coveredWidth: Math.round(Math.min(box.right, frame.right) - Math.max(box.left, frame.left)),
    };
  });

  expect(card, 'kartu harus ada di DOM setelah pilihan dibuat').toBeTruthy();
  expect(card.position, 'kartu harus mengambang di atas peta, bukan di aliran dokumen').toBe('absolute');
  expect(card.animation, 'kartu harus memakai animasi masuk').toContain('doclinc-card-in');
  expect(card.duration, 'durasi animasi kartu tidak boleh nol').not.toBe('0s');
  expect(card.insideMap, 'kartu harus berada di dalam kotak peta').toBe(true);
  expect(card.coveredWidth, 'kartu harus menutupi sebagian peta, bukan kolom kosong di sampingnya')
    .toBeGreaterThan(200);
  expect(card.zIndex, 'kartu harus berada di atas pane dan kontrol Leaflet').toBeGreaterThan(800);

  /* Papan ketik: fokus poligon lain lalu Enter harus memindahkan pilihan. */
  const secondLabel = await page.locator('#doclincDiseaseMap path[role="button"]').nth(1).getAttribute('aria-label');
  const secondName = String(secondLabel || '').replace(/^Kecamatan /, '').split(':')[0].trim();
  await page.locator('#doclincDiseaseMap path[role="button"]').nth(1).focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('#doclincMapSelectionTitle')).toContainText(secondName);

  /* Escape menutup kartu tanpa harus mencari tombol, dan mengembalikan fokus
     ke poligon supaya pengguna papan ketik tidak kehilangan tempatnya. */
  await page.keyboard.press('Escape');
  await expect(panel).toBeHidden();
  expect(await page.evaluate(() => ((document.activeElement || {}).tagName || '').toLowerCase()),
    'Escape harus mengembalikan fokus ke poligon').toBe('path');

  /* Tombol tutup di kepala kartu menutup kartu setelah seleksi dibuat lewat
     klik - jalur tetikus, bukan hanya papan ketik. */
  const third = await districtPoint(page, 2);
  await page.mouse.click(third.x, third.y);
  await expect(panel).toBeVisible();
  await page.locator('#doclincMapClear').click();
  await expect(panel).toBeHidden();

  expect(pageErrors, 'skrip peta tidak boleh melempar galat saat dipilih').toEqual([]);
});

test('mode penyakit terbanyak mewarnai peta dan klik membuka infografik kecamatan', async ({ page }) => {
  await login(page);
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(String(error)));

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#doclincDiseaseMap path[role="button"]');
  await page.locator('#doclincDiseaseMap').scrollIntoViewIfNeeded();

  /* Infografik kecamatan: empat angka, pita porsi, lalu daftar kode. Dibuka
     dengan mengklik poligonnya, satu-satunya jalan masuk yang tersisa. */
  const point = await districtPoint(page, 0);
  await page.mouse.click(point.x, point.y);
  await expect(page.locator('#doclincMapSelection')).toBeVisible();
  await expect(page.locator('#doclincMapFigures .doclinc-map-figure')).toHaveCount(4);

  const panel = await page.evaluate(() => {
    const figures = [...document.querySelectorAll('#doclincMapFigures .doclinc-map-figure')];
    const segments = [...document.querySelectorAll('#doclincMapShare .doclinc-map-share-seg')];
    const text = (selector) => {
      const node = document.querySelector(selector);
      return node ? (node.textContent || '').trim() : '';
    };
    return {
      title: text('#doclincMapSelectionTitle'),
      labels: figures.map((figure) => (figure.querySelector('.doclinc-map-figure-label') || {}).textContent || ''),
      values: figures.map((figure) => ((figure.querySelector('.doclinc-map-figure-value') || {}).textContent || '').trim()),
      segments: segments.length,
      width: segments.reduce((sum, segment) => sum + Math.round(segment.getBoundingClientRect().width), 0),
      note: text('#doclincMapShareNote'),
    };
  });

  expect(panel.title, 'panel harus menyebut kecamatan yang diklik').toMatch(/^Kecamatan \S/);
  expect(panel.labels, 'empat angka kecamatan harus berurutan dan bernama')
    .toEqual(['Sudah dikode', 'Jenis penyakit', 'Penyakit dominan', 'Belum dikode']);
  expect(panel.values[0], 'angka diagnosis sudah dikode tidak boleh nol untuk kecamatan berdata').not.toBe('0');
  expect(panel.values[1], 'jumlah jenis penyakit harus terisi').toMatch(/^\d/);
  expect(panel.values[2], 'penyakit dominan harus berupa kode ICD-10').toMatch(/^[A-Z][0-9]{2}/);
  expect(panel.segments, 'pita porsi harus punya satu segmen per kode').toBeGreaterThan(0);
  expect(panel.segments, 'pita porsi tidak boleh lebih dari sepuluh kode plus sisa').toBeLessThanOrEqual(11);
  expect(panel.width, 'pita porsi harus mengisi lebar panelnya').toBeGreaterThan(200);
  expect(panel.note, 'catatan pita harus menyebut angka yang sama dengan subjudul').toContain('diagnosis sudah dikode');

  /* Mode "Penyakit terbanyak": hue per kode, legenda kode, tanpa tangga nilai. */
  await page.selectOption('#doclincMapMode', 'dominant');
  const dominant = await page.evaluate(() => {
    const host = document.getElementById('doclincDiseaseMap');
    const paths = [...host.querySelectorAll('path[role="button"]')];
    const legend = document.getElementById('doclincMapLegendCats');
    return {
      fills: [...new Set(paths.map((path) => String(path.getAttribute('fill') || '').toLowerCase()))],
      pressed: paths.filter((path) => path.getAttribute('aria-pressed') === 'true').length,
      labelled: paths.filter((path) => /^Kecamatan /.test(path.getAttribute('aria-label') || '')).length,
      polygons: paths.length,
      swatches: document.querySelectorAll('#doclincMapLegendCats .doclinc-map-swatch').length,
      legendText: legend ? (legend.textContent || '') : '',
      sequentialHidden: (document.getElementById('doclincMapLegend') || {}).hidden,
      categoricalHidden: legend ? legend.hidden : true,
      /* Hue penyakit tidak hilang bersama poligonnya: saat poligon terpilih
         berubah biru, hue yang sama tetap ada di baris kode di dalam kartu. */
      cardSwatches: document.querySelectorAll('#doclincMapSelectionList .doclinc-map-selection-swatch').length,
      cardRows: document.querySelectorAll('#doclincMapSelectionList li').length,
      status: ((document.getElementById('doclincMapStatus') || {}).textContent || '').trim(),
    };
  });

  expect(dominant.categoricalHidden, 'legenda penyakit terbanyak harus tampil di mode ini').toBe(false);
  expect(dominant.sequentialHidden, 'legenda tangga nilai harus disembunyikan di mode ini').toBe(true);
  expect(dominant.fills.length, 'mode penyakit terbanyak harus memakai beberapa hue berbeda')
    .toBeGreaterThanOrEqual(2);
  expect(dominant.swatches, 'legenda harus punya swatch untuk tiap kode, plus tanpa data dan dipilih')
    .toBeGreaterThan(2);
  expect(dominant.legendText, 'legenda harus menulis kode ICD-10, bukan warna tanpa nama')
    .toMatch(/[A-Z][0-9]{2}/);
  expect(dominant.legendText, 'legenda harus menyebut berapa kecamatan memakai kode itu')
    .toContain('kec.');
  expect(dominant.pressed, 'pilihan yang sudah ada harus bertahan saat mode berganti').toBe(1);
  expect(dominant.labelled, 'setiap poligon harus tetap punya label aksesibel').toBe(dominant.polygons);
  expect(dominant.cardSwatches, 'hue penyakit harus tetap terbaca di baris kode dalam kartu')
    .toBe(dominant.cardRows);
  expect(dominant.cardSwatches, 'kartu harus memuat sedikitnya satu kode berhue').toBeGreaterThan(0);
  expect(dominant.status, 'status harus menjelaskan mode yang sedang tampil').toContain('Penyakit terbanyak');

  /* Memilih kode mengembalikan peta ke mode jumlah: pilihan di toolbar tidak
     boleh tampak tidak berpengaruh saat mode penyakit terbanyak aktif. */
  await page.selectOption('#doclincMapCode', { index: 1 });
  await expect(page.locator('#doclincMapMode')).toHaveValue('count');
  await expect(page.locator('#doclincMapLegendCats')).toBeHidden();

  expect(pageErrors, 'skrip peta tidak boleh melempar galat saat mode berganti').toEqual([]);
});

test('grafik batang memakai warna bertingkat dan angka di ujung batang', async ({ page }) => {
  await login(page);
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(String(error)));

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#doclincDiagnosisChart');

  const chart = await page.evaluate(() => {
    const canvas = document.getElementById('doclincDiagnosisChart');
    const instance = window.Chart && window.Chart.getChart ? window.Chart.getChart(canvas) : null;
    if (!instance) {
      return { ready: false };
    }
    const colors = instance.data.datasets[0].backgroundColor || [];
    return {
      ready: true,
      values: instance.data.datasets[0].data.length,
      colors: Array.isArray(colors) ? colors : [colors],
      plugins: (instance.config.plugins || []).map((plugin) => plugin.id),
      barThickness: instance.data.datasets[0].maxBarThickness,
      autoSkip: instance.options.scales.y.ticks.autoSkip,
      height: Math.round(canvas.getBoundingClientRect().height),
    };
  });

  expect(chart.ready, 'Chart.js harus menggambar grafik pada canvas halaman').toBe(true);
  expect(chart.values, 'grafik harus memuat kode ICD-10 terbanyak').toBeGreaterThan(0);
  expect(chart.colors.length, 'tiap batang harus punya warna sendiri, bukan satu warna seragam')
    .toBe(chart.values);
  expect(new Set(chart.colors.map((color) => String(color).toLowerCase())).size,
    'warna batang harus berbeda antar-tingkat nilai').toBeGreaterThanOrEqual(2);
  expect(chart.plugins, 'plugin angka di ujung batang harus terpasang')
    .toContain('doclincBarValueLabels');
  expect(chart.barThickness, 'batang harus dibatasi supaya tidak menjadi balok gemuk').toBeLessThanOrEqual(30);
  expect(chart.autoSkip, 'semua kode harus tampil di sumbu, tidak dilewati otomatis').toBe(false);
  expect(chart.height, 'grafik harus cukup tinggi untuk sepuluh batang').toBeGreaterThanOrEqual(320);
  expect(pageErrors, 'skrip grafik tidak boleh melempar galat').toEqual([]);
});

test('peta tetap terbaca pada layar ponsel', async ({ page }) => {
  await login(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#doclincDiseaseMap path[role="button"]');

  const layout = await page.evaluate(() => {
    const host = document.getElementById('doclincDiseaseMap');
    const rect = host ? host.getBoundingClientRect() : null;
    return {
      mapWidth: rect ? Math.round(rect.width) : 0,
      mapHeight: rect ? Math.round(rect.height) : 0,
      sideLists: document.querySelectorAll('#doclincMapRanking, .doclinc-map-ranking').length,
      documentOverflow: document.documentElement.scrollWidth - window.innerWidth,
    };
  });

  expect(layout.mapWidth, 'peta harus memakai lebar penuh kolomnya di ponsel').toBeGreaterThan(200);
  expect(layout.mapHeight, 'peta harus punya tinggi yang terbaca di ponsel').toBeGreaterThan(120);
  expect(layout.sideLists, 'daftar di samping peta tidak boleh kembali di ponsel').toBe(0);
  expect(layout.documentOverflow, 'peta tidak boleh membuat halaman bergeser mendatar')
    .toBeLessThanOrEqual(1);

  /* Kartu mengambang di ponsel menjadi lembar bawah: selebar peta, menempel di
     tepi bawahnya, dan tidak boleh menyeret halaman ke samping. */
  await page.locator('#doclincDiseaseMap').scrollIntoViewIfNeeded();
  const point = await districtPoint(page, 0);
  await page.mouse.click(point.x, point.y);
  await expect(page.locator('#doclincMapSelection')).toBeVisible();

  const card = await page.evaluate(() => {
    const node = document.getElementById('doclincMapSelection');
    const mapNode = document.getElementById('doclincDiseaseMap');
    if (!node || !mapNode) return null;
    const box = node.getBoundingClientRect();
    const frame = mapNode.getBoundingClientRect();
    return {
      width: Math.round(box.width),
      left: Math.round(box.left),
      right: Math.round(box.right),
      bottom: Math.round(box.bottom),
      mapBottom: Math.round(frame.bottom),
      documentOverflow: document.documentElement.scrollWidth - window.innerWidth,
    };
  });

  expect(card, 'klik kecamatan di ponsel harus membuka kartu').toBeTruthy();
  expect(card.width, 'kartu harus mengisi lebar peta di ponsel').toBeGreaterThan(300);
  expect(card.left, 'kartu tidak boleh keluar layar di kiri').toBeGreaterThanOrEqual(0);
  expect(card.right, 'kartu tidak boleh keluar layar di kanan').toBeLessThanOrEqual(390);
  expect(card.bottom, 'kartu menempel di tepi bawah peta, bukan meluber ke bawahnya')
    .toBeLessThanOrEqual(card.mapBottom + 1);
  expect(card.documentOverflow, 'kartu tidak boleh membuat halaman bergeser mendatar')
    .toBeLessThanOrEqual(1);
});

