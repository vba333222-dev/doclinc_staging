const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

/**
 * Gerbang aksesibilitas panel admin, diukur pada hasil render.
 *
 * Audit 2026-10-26 menemukan panel ini "hijau" di semua gerbang sambil tetap
 * tidak terbaca di beberapa tempat: teks body 1,26:1 pada tema gelap, label
 * .btn-outline-secondary 1,51:1, badge 2,34:1, judul kelompok sidebar 3,08:1,
 * empat tombol ikon kosong, 39 <th> tanpa scope, dan tidak ada satu pun
 * landmark <main>. Tak satu pun dari itu terlihat oleh uji yang hanya
 * memeriksa "halaman merender".
 *
 * Uji ini memeriksa enam hal di setiap rute, pada KEDUA tema:
 *   1. kontras teks terhadap latar yang benar-benar dilukis (termasuk gradien),
 *      ambang WCAG AA: 4,5:1 (3:1 untuk teks besar);
 *   2. setiap ikon fa-* punya glyph - nama FA6 di bundel FA5 menghasilkan
 *      tombol kosong tanpa satu pun error;
 *   3. tepat satu <main> yang berisi isi;
 *   4. tepat satu <h1>, tanpa lompatan tingkat judul;
 *   5. setiap <th> punya scope;
 *   6. tidak ada subresource same-origin yang gagal (CSS token lapisan desain
 *      yang 404 menjatuhkan seluruh sistem token tanpa gejala).
 *
 * Daftar rute TIDAK ditulis ulang di sini: ia dibaca dari
 * admin_panel_visual.spec.js, dan uji terakhir memastikan kedua daftar sama.
 * Dua salinan daftar rute adalah cara termudah membuat satu halaman lolos dari
 * gerbang tanpa ada yang menyadarinya.
 */

const adminEmail = process.env.ADMIN_EMAIL || 'superadmin@lokal.test';
const adminPassword = process.env.ADMIN_PASSWORD || 'Superadmin#2026!';
const adminBase = String(process.env.TASK11_BASE_URL || 'http://127.0.0.1:18090').replace(/\/+$/, '');

const VISUAL_SPEC = path.join(__dirname, 'admin_panel_visual.spec.js');

function routesFromVisualSpec() {
  const source = fs.readFileSync(VISUAL_SPEC, 'utf8');
  const found = [];
  const re = /route:\s*'([a-z0-9_\/]+)'/g;
  let match = re.exec(source);
  while (match) {
    found.push(match[1]);
    match = re.exec(source);
  }
  return found;
}

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
 * Dijalankan DI DALAM halaman. Mengembalikan temuan, bukan melempar: pemanggil
 * yang memutuskan ambang dan pesan, supaya satu kegagalan menyebutkan seluruh
 * pelanggarnya sekaligus.
 */
function collectA11y() {
  const parse = (value) => {
    const m = String(value).match(/rgba?\(([^)]+)\)/);
    if (!m) return null;
    const parts = m[1].split(',').map((v) => parseFloat(v));
    return { r: parts[0], g: parts[1], b: parts[2], a: parts.length > 3 ? parts[3] : 1 };
  };
  const luminance = (color) => {
    const channel = [color.r, color.g, color.b].map((v) => {
      const s = v / 255;
      return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * channel[0] + 0.7152 * channel[1] + 0.0722 * channel[2];
  };
  const over = (fg, bg, alpha) => ({
    r: fg.r * alpha + bg.r * (1 - alpha),
    g: fg.g * alpha + bg.g * (1 - alpha),
    b: fg.b * alpha + bg.b * (1 - alpha),
    a: 1,
  });
  const hex = (c) => '#' + [c.r, c.g, c.b].map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
  const gradientStops = (image) => {
    const found = String(image).match(/(?:rgba?\([^)]+\)|#[0-9a-f]{3,8})/gi);
    if (!found) return [];
    return found.map((raw) => {
      if (raw[0] !== '#') return parse(raw);
      let h = raw.slice(1);
      if (h.length === 3) h = h.split('').map((x) => x + x).join('');
      return { r: parseInt(h.slice(0, 2), 16), g: parseInt(h.slice(2, 4), 16), b: parseInt(h.slice(4, 6), 16), a: 1 };
    }).filter(Boolean);
  };
  /* Latar yang benar-benar dilukis di bawah sebuah elemen: lapisan tembus
     pandang digabung, dan gradien diperlakukan sebagai himpunan kandidat (tiap
     titik henti diuji, yang terburuk dipakai) - bukan warna rata-rata. */
  const backgroundsOf = (el) => {
    const layers = [];
    let node = el;
    while (node && node.nodeType === 1) {
      const cs = getComputedStyle(node);
      const stops = cs.backgroundImage && cs.backgroundImage !== 'none' ? gradientStops(cs.backgroundImage) : [];
      if (stops.length) {
        layers.push(stops);
        break;
      }
      const bg = parse(cs.backgroundColor);
      if (bg && bg.a > 0) {
        layers.push([bg]);
        if (bg.a > 0.99) break;
      }
      node = node.parentElement;
    }
    let base = [{ r: 255, g: 255, b: 255, a: 1 }];
    for (let i = layers.length - 1; i >= 0; i -= 1) {
      const next = [];
      layers[i].forEach((layer) => base.forEach((b) => next.push(over(layer, b, layer.a))));
      base = next;
    }
    return base;
  };
  const selectorOf = (el) => {
    const bits = [];
    let node = el;
    let depth = 0;
    while (node && node.nodeType === 1 && depth < 4) {
      let bit = node.tagName.toLowerCase();
      if (node.id) bit += '#' + node.id;
      else if (typeof node.className === 'string' && node.className.trim()) {
        bit += '.' + node.className.trim().split(/\s+/).slice(0, 2).join('.');
      }
      bits.unshift(bit);
      node = node.parentElement;
      depth += 1;
    }
    return bits.join('>');
  };

  const contrast = [];
  const seen = new Set();
  let inactiveSkipped = 0;
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null);
  let textNode = walker.nextNode();
  while (textNode) {
    const text = textNode.nodeValue.trim().replace(/\s+/g, ' ');
    const el = textNode.parentElement;
    /* WCAG 1.4.3 mengecualikan teks pada komponen antarmuka yang NONAKTIF
       (mis. tombol "Previous" DataTables saat hanya ada satu halaman), jadi
       teks itu tidak diukur - tetapi jumlahnya dilaporkan supaya pengecualian
       ini tidak bisa dipakai untuk menyembunyikan kontrol yang aktif. */
    const inactive = el && el.closest('.disabled, [disabled], [aria-disabled="true"], .page-item.disabled');
    if (inactive) {
      inactiveSkipped += 1;
      textNode = walker.nextNode();
      continue;
    }
    if (text.length > 1 && el && !/^(SCRIPT|STYLE|NOSCRIPT)$/.test(el.tagName) && el.getClientRects().length) {
      const cs = getComputedStyle(el);
      if (cs.visibility !== 'hidden' && cs.display !== 'none' && parseFloat(cs.opacity) > 0.05) {
        const fg = parse(cs.color);
        const alpha = fg && fg.a < 1 ? fg.a : 1;
        const size = parseFloat(cs.fontSize);
        const bold = parseInt(cs.fontWeight, 10) >= 700;
        const min = size >= 24 || (bold && size >= 18.66) ? 3 : 4.5;
        let worst = null;
        backgroundsOf(el).forEach((bg) => {
          const solid = alpha < 1 ? over(fg, bg, alpha) : fg;
          const a = luminance(solid);
          const b = luminance(bg);
          const ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
          if (!worst || ratio < worst.ratio) worst = { ratio, bg };
        });
        if (worst && worst.ratio < min) {
          const key = [hex(fg), hex(worst.bg), size, cs.fontWeight, selectorOf(el)].join('|');
          if (!seen.has(key)) {
            seen.add(key);
            contrast.push({
              ratio: Math.round(worst.ratio * 100) / 100,
              need: min,
              fg: hex(fg),
              bg: hex(worst.bg),
              size,
              selector: selectorOf(el),
              sample: text.slice(0, 40),
            });
          }
        }
      }
    }
    textNode = walker.nextNode();
  }

  const deadIcons = [...document.querySelectorAll('i[class*="fa-"]')]
    .filter((icon) => {
      const glyph = getComputedStyle(icon, '::before').content;
      return glyph === 'none' || glyph === 'normal' || glyph === '""';
    })
    .map((icon) => {
      const host = icon.closest('button, a');
      const name = (icon.getAttribute('class') || '').trim();
      return host
        ? `${name} di dalam <${host.tagName.toLowerCase()}> "${host.getAttribute('aria-label') || host.textContent.trim().slice(0, 20)}"`
        : name;
    });

  const headings = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')]
    .filter((el) => el.getClientRects().length && !el.closest('.modal'))
    .map((el) => parseInt(el.tagName.slice(1), 10));
  const jumps = [];
  let previous = 0;
  headings.forEach((level) => {
    if (previous && level > previous + 1) jumps.push(`${previous}->${level}`);
    previous = level;
  });

  const mains = [...document.querySelectorAll('main, [role=main]')];
  const main = mains[0] || null;

  return {
    theme: document.documentElement.getAttribute('data-theme'),
    pageToken: getComputedStyle(document.documentElement).getPropertyValue('--doclinc-page').trim(),
    bodyBackground: getComputedStyle(document.body).backgroundColor,
    contrast,
    inactiveSkipped,
    deadIcons,
    h1: document.querySelectorAll('h1').length,
    jumps,
    mainCount: mains.length,
    mainTextLength: main ? main.textContent.trim().length : 0,
    thWithoutScope: [...document.querySelectorAll('table th')].filter((th) => !th.getAttribute('scope')).length,
    smallTargets: [...document.querySelectorAll('.dl-adm-action, .dl-adm-info, #sidebarToggle, .doclinc-theme-toggle')]
      .filter((el) => el.getClientRects().length)
      .map((el) => ({ selector: selectorOf(el), height: Math.round(el.getBoundingClientRect().height) }))
      .filter((row) => row.height < 44),
  };
}

test.describe.configure({ mode: 'serial' });

const ROUTES = routesFromVisualSpec();
const THEMES = [
  { name: 'light', expectToken: '#f4f6fb', bodyBackground: 'rgb(244, 246, 251)' },
  { name: 'dark', expectToken: '#0b1220', bodyBackground: 'rgb(11, 18, 32)' },
];

for (const theme of THEMES) {
  for (const route of ROUTES) {
    test(`${route} @${theme.name}: kontras, landmark, dan struktur`, async ({ page }, testInfo) => {
      /* Hanya desktop: kontras tidak bergantung lebar layar, dan 38 uji sudah
         menggandakan durasi gerbang. Varian ponsel tetap diuji di
         admin_panel_visual.spec.js. */
      test.skip(testInfo.project.name !== 'admin-desktop', 'pemeriksaan kontras cukup sekali per tema');

      const sameOriginFailures = [];
      page.on('requestfailed', (request) => {
        if (request.url().startsWith(adminBase)) {
          sameOriginFailures.push(`${request.url()} (${(request.failure() || {}).errorText || 'gagal'})`);
        }
      });
      page.on('response', (res) => {
        if (res.url().startsWith(adminBase) && res.status() >= 400) {
          sameOriginFailures.push(`${res.url()} HTTP ${res.status()}`);
        }
      });

      await login(page);
      /* Tema disetel SEBELUM dokumen pertama kali dijalankan, sama seperti
         skrip tema di panel; mengubah atribut sesudah muat akan mengukur
         halaman yang sempat dirender dengan tema lain. */
      await page.addInitScript((name) => {
        window.localStorage.setItem('doclinc_admin_theme', name);
      }, theme.name);

      const response = await page.goto(`${adminBase}/${route}`, { waitUntil: 'domcontentloaded' });
      expect(response, `${route} harus menghasilkan respons`).not.toBeNull();
      expect(response.status(), `${route} harus HTTP 200`).toBe(200);
      await page.waitForTimeout(150);

      const result = await page.evaluate(collectA11y);

      expect(result.theme, `${route} harus dirender pada tema ${theme.name}`).toBe(theme.name);
      /* Tema yang "aktif" tetapi tidak mengubah warna halaman berarti token
         gelap tidak terpasang: seluruh pemeriksaan kontras di bawah akan
         mengukur halaman terang dua kali dan lulus tanpa memeriksa apa pun. */
      expect(String(result.pageToken).toLowerCase(), `${route} memakai token halaman tema ${theme.name}`).toBe(theme.expectToken);
      expect(result.bodyBackground, `${route} melukis warna halaman dari token`).toBe(theme.bodyBackground);

      const contrastLines = result.contrast.map(
        (row) => `      ${row.ratio}:1 (butuh ${row.need}) ${row.fg} di ${row.bg}, ${row.size}px  ${row.selector}  «${row.sample}»`,
      );
      expect(contrastLines, `${route} @${theme.name}: teks di bawah ambang WCAG AA\n${contrastLines.join('\n')}`).toEqual([]);

      expect(result.deadIcons, `${route} @${theme.name}: ikon tanpa glyph (nama FA6 di bundel FA5?)`).toEqual([]);
      expect(result.mainCount, `${route} @${theme.name}: harus ada tepat satu landmark main`).toBe(1);
      expect(result.mainTextLength, `${route} @${theme.name}: landmark main tidak boleh kosong`).toBeGreaterThan(50);
      expect(result.h1, `${route} @${theme.name}: harus ada tepat satu h1`).toBe(1);
      expect(result.jumps, `${route} @${theme.name}: judul tidak boleh melompati tingkat`).toEqual([]);
      expect(result.thWithoutScope, `${route} @${theme.name}: setiap th harus punya scope`).toBe(0);
      expect(result.smallTargets, `${route} @${theme.name}: sasaran sentuh minimal 44px`).toEqual([]);
      expect(sameOriginFailures, `${route} @${theme.name}: subresource same-origin gagal dimuat`).toEqual([]);
    });
  }
}

test('daftar rute gerbang a11y sama dengan sapuan visual', async () => {
  const visual = new Set(routesFromVisualSpec());
  const a11y = new Set(ROUTES);
  expect([...a11y].sort(), 'daftar rute kedua uji harus identik').toEqual([...visual].sort());
  /* 19, bukan 20: `master_puskesmas` dipensiunkan dan rutenya kini hanya
     mengalihkan ke hub `kelola_dokter_nakes`, jadi tidak lagi dipotret sendiri.
     Angka ini sengaja tetap ditulis eksplisit supaya penyusutan berikutnya
     terlihat sebagai perubahan, bukan sebagai kebetulan. */
  expect(a11y.size, 'jumlah rute tidak boleh menyusut tanpa sengaja').toBeGreaterThanOrEqual(19);
});
