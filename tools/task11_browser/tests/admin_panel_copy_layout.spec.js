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
 *      poligonnya benar-benar tergambar dan peringkat di sampingnya memuat satu
 *      baris per kecamatan.
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

test('peta sebaran penyakit tergambar dan peringkatnya lengkap', async ({ page }) => {
  await login(page);
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(String(error)));
  const failedRequests = [];
  page.on('response', (response) => {
    if (response.status() >= 400) failedRequests.push(`${response.status()} ${response.url()}`);
  });

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });

  /* Peta dirakit setelah GeoJSON diambil, jadi tunggu sampai barisnya ada. */
  await page.waitForSelector('#doclincMapRanking li');

  const map = await page.evaluate(() => {
    const host = document.getElementById('doclincDiseaseMap');
    /* Leaflet dengan renderer SVG menggambar circleMarker sebagai <path> berisi
       perintah busur, bukan <circle>. Membedakannya dari poligon karena itu
       dilakukan lewat bentuk `d`-nya: poligon hanya berisi garis, gelembung
       selalu memuat perintah busur. Tanpa pembeda ini uji akan melaporkan nol
       gelembung pada peta yang sebenarnya benar. */
    const paths = host ? [...host.querySelectorAll('path.leaflet-interactive')] : [];
    const bubbles = paths.filter((path) => /[aA]\s*[-\d.]/.test(path.getAttribute('d') || ''));
    return {
      host: Boolean(host),
      polygons: paths.length - bubbles.length,
      bubbles: bubbles.length,
      bubblesVisible: bubbles.filter((path) => {
        const style = getComputedStyle(path);
        return Number(style.opacity) > 0 && Number(style.fillOpacity) > 0;
      }).length,
      height: host ? Math.round(host.getBoundingClientRect().height) : 0,
      legend: (() => {
        const scale = document.querySelector('.doclinc-map-legend-scale');
        return scale ? getComputedStyle(scale).backgroundImage : '';
      })(),
      ranking: document.querySelectorAll('#doclincMapRanking li').length,
      tableRows: document.querySelectorAll('.doclinc-map-table tbody tr').length,
      status: (document.getElementById('doclincMapStatus') || {}).textContent || '',
    };
  });

  expect(map.host, 'kontainer peta harus ada di halaman diagnosis').toBe(true);
  expect(map.polygons, 'batas kecamatan harus tergambar dari GeoJSON lokal').toBeGreaterThan(0);
  expect(map.height, 'kontainer peta tidak boleh runtuh menjadi nol').toBeGreaterThan(120);
  expect(map.tableRows, 'tabel angka peta harus memuat kecamatan').toBeGreaterThan(0);
  expect(map.ranking, 'peringkat harus memuat satu baris per kecamatan di tabel').toBe(map.tableRows);
  expect(map.bubbles, 'setiap kecamatan di peringkat harus punya gelembung angka')
    .toBeGreaterThanOrEqual(map.ranking);
  expect(map.bubblesVisible, 'gelembung harus terlihat untuk kecamatan berisi data').toBeGreaterThan(0);
  expect(map.legend, 'skala warna harus dihitung dari data, bukan gradien kosong').toContain('gradient');
  expect(map.status, 'status peta harus menyebut jumlah diagnosis').toContain('diagnosis');
  expect(failedRequests, 'tidak boleh ada subresource peta yang gagal dimuat').toEqual([]);
  expect(pageErrors, 'skrip peta tidak boleh melempar galat').toEqual([]);
});

test('peta tetap terbaca pada layar ponsel', async ({ page }) => {
  await login(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${adminBase}/diagnosis`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#doclincMapRanking li');

  const layout = await page.evaluate(() => {
    const host = document.getElementById('doclincDiseaseMap');
    const ranking = document.querySelector('.doclinc-map-ranking');
    const rect = host ? host.getBoundingClientRect() : null;
    const rankRect = ranking ? ranking.getBoundingClientRect() : null;
    return {
      mapWidth: rect ? Math.round(rect.width) : 0,
      mapHeight: rect ? Math.round(rect.height) : 0,
      rankingWidth: rankRect ? Math.round(rankRect.width) : 0,
      documentOverflow: document.documentElement.scrollWidth - window.innerWidth,
    };
  });

  expect(layout.mapWidth, 'peta harus memakai lebar penuh kolomnya di ponsel').toBeGreaterThan(200);
  expect(layout.mapHeight, 'peta harus punya tinggi yang terbaca di ponsel').toBeGreaterThan(120);
  expect(layout.rankingWidth, 'peringkat turun ke bawah peta, bukan nol lebar').toBeGreaterThan(200);
  expect(layout.documentOverflow, 'peta tidak boleh membuat halaman bergeser mendatar')
    .toBeLessThanOrEqual(1);
});

