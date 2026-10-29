const { test, expect } = require('@playwright/test');

/**
 * Alur pendaftaran nakes: satu aksi utama, menu Lainnya, form yang
 * mempertahankan isian, dan aktivasi dua jalur.
 *
 * Uji ini melengkapi sapuan visual: sapuan hanya membuka daftar, sedangkan
 * cacat alur paling sering muncul pada keadaan form, menu, dan modal.
 */
const base = String(process.env.TASK11_BASE_URL || 'http://127.0.0.1:8080/admin_menu').replace(/\/+$/, '');
const email = process.env.ADMIN_EMAIL || 'admin@lokal.test';
const password = process.env.ADMIN_PASSWORD || 'Lokal#2026!';

async function login(page) {
  const res = await page.request.get(`${base}/login`);
  const html = await res.text();
  const token = html.match(/name="csrf_test_name"\s+value="([^"]+)"/);
  if (!token) throw new Error('Token CSRF login tidak ditemukan');
  const post = await page.request.post(`${base}/login/ceklogin`, {
    form: { email, password, csrf_test_name: token[1] },
  });
  const body = (await post.text()).trim();
  if (body !== '1') throw new Error('Login admin gagal: ' + body.slice(0, 120));
}

test('halaman staf menampilkan stepper, satu aksi utama, dan menu Lainnya', async ({ page }) => {
  await login(page);
  await page.goto(`${base}/kelola_staff_puskesmas?kode_pkm=10280101`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.dl-adm-stepper').first()).toBeVisible();
  await expect(page.locator('.doclinc-account-card__footer .btn-primary, .doclinc-account-card__footer .dropdown-toggle').first()).toBeVisible();
  const nested = await page.evaluate(() => document.querySelectorAll('button form').length);
  expect(nested, 'tidak boleh ada form di dalam button').toBe(0);
  const toggle = page.locator('.doclinc-account-card__footer .dropdown-toggle').first();
  await toggle.click();
  await expect(page.locator('.doclinc-account-card__footer .dropdown-menu.show').first()).toBeVisible();
});

test('form tambah staf mempertahankan isian dan menampilkan error', async ({ page }) => {
  await login(page);
  await page.goto(`${base}/kelola_staff_puskesmas/create?kode_pkm=10280101`, { waitUntil: 'domcontentloaded' });
  await page.fill('#staff_nama', 'Uji Simpan');
  await page.fill('#staff_no_hp', 'abc');
  await page.click('#staffForm button[type="submit"]');
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('#staffFormErrorSummary')).toBeVisible();
  await expect(page.locator('#staff_nama')).toHaveValue('Uji Simpan');
});

test('aktivasi menampilkan dua jalur dan required mengikuti pilihan', async ({ page }) => {
  await page.goto('http://127.0.0.1:8080/login/activate', { waitUntil: 'domcontentloaded' });
  if (!(await page.locator('#activationForm').count())) {
    test.skip(true, 'halaman aktivasi tidak tersedia di lingkungan ini');
  }
  await expect(page.locator('#activationCodePanel')).toBeVisible();
  await expect(page.locator('#activationPasswordPanel')).toBeHidden();
  await page.check('input[name="activation_mode"][value="password"]');
  await expect(page.locator('#activationPasswordPanel')).toBeVisible();
  expect(await page.locator('#activation_code').isDisabled()).toBe(true);
  expect(await page.locator('#activation_username').getAttribute('required')).not.toBeNull();
});

test('aksi merusak memakai modal konfirmasi yang bisa dibatalkan', async ({ page }) => {
  await login(page);
  await page.goto(`${base}/kelola_staff_puskesmas?kode_pkm=10280101`, { waitUntil: 'domcontentloaded' });
  await page.locator('.doclinc-account-card__footer .dropdown-toggle').first().click();
  const trigger = page.locator('.doclinc-account-card__footer .dropdown-menu.show [data-confirm]').first();
  if (!(await trigger.count())) {
    test.skip(true, 'tidak ada aksi berkonfirmasi pada data uji');
  }
  const label = await trigger.getAttribute('data-confirm-label');
  await trigger.click();

  const modal = page.locator('#doclincConfirmModal');
  await expect(modal).toBeVisible();
  await expect(modal).toHaveAttribute('aria-modal', 'true');
  await expect(page.locator('#doclincConfirmModalTitle')).not.toBeEmpty();
  await expect(page.locator('#doclincConfirmModalAccept')).toHaveText(label);
  await expect(page.locator('#doclincConfirmModalAccept')).toBeFocused();

  await page.keyboard.press('Escape');
  await expect(modal).toBeHidden();
  const restored = () => page.evaluate(() => document.activeElement ? document.activeElement.className : '');
  await expect.poll(restored, { message: 'fokus kembali ke pemicu aksi' }).toMatch(/dropdown-(item|toggle)/);
  expect(page.url()).toContain('/kelola_staff_puskesmas');
});

test('konfirmasi dari dalam modal mengembalikan isian saat dibatalkan', async ({ page }) => {
  await login(page);
  await page.goto(`${base}/kelola_staff_puskesmas?kode_pkm=10280101`, { waitUntil: 'domcontentloaded' });
  const openers = page.locator('[data-target^="#modalResetPersonalPassword"]');
  if (!(await openers.count())) {
    test.skip(true, 'tidak ada akun dengan aksi reset pada data uji');
  }
  await page.locator('.doclinc-account-card__footer .dropdown-toggle').first().click();
  await openers.first().click();
  const parent = page.locator('[id^="modalResetPersonalPassword"]').first();
  await expect(parent).toBeVisible();
  const password = parent.locator('input[name="password"]');
  await password.fill('Uji#2026!');
  await parent.locator('input[name="confirm_password"]').fill('Uji#2026!');
  await parent.locator('button[data-confirm]').click();

  await expect(page.locator('#doclincConfirmModal')).toBeVisible();
  await expect(page.locator('#doclincConfirmModalAccept')).toBeFocused();
  await expect(parent).toBeHidden();
  await page.keyboard.press('Escape');
  await expect(page.locator('#doclincConfirmModal')).toBeHidden();
  await expect(parent).toBeVisible();
  await expect(password).toHaveValue('Uji#2026!');
  const activeName = () => page.evaluate(() => document.activeElement ? (document.activeElement.id || document.activeElement.className) : '');
  await expect.poll(activeName, { message: 'fokus kembali ke modal asal' }).toMatch(/modalResetPersonalPassword|btn-outline-danger/);
});

test('menerima konfirmasi mengirim form sekali', async ({ page }) => {
  await login(page);
  await page.goto(`${base}/kelola_staff_puskesmas?kode_pkm=10280101`, { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '#';
    form.setAttribute('data-confirm', 'Kirim formulir uji?');
    form.setAttribute('data-confirm-label', 'Kirim');
    form.innerHTML = '<button type="submit">Kirim</button>';
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      window.__doclincConfirmedSubmit = (window.__doclincConfirmedSubmit || 0) + 1;
    });
    form.id = 'doclincConfirmProbe';
    document.body.appendChild(form);
  });
  await page.evaluate(() => document.querySelector('#doclincConfirmProbe button').click());
  await expect(page.locator('#doclincConfirmModal')).toBeVisible();
  await page.evaluate(() => { window.__doclincConfirmedSubmit = 0; });
  await page.click('#doclincConfirmModalAccept');
  await page.waitForFunction(() => window.__doclincConfirmedSubmit === 1);
  expect(await page.evaluate(() => window.__doclincConfirmedSubmit)).toBe(1);
});

test('detail Puskesmas membuka modal Kelola tanpa tautan ke halaman staf', async ({ page }) => {
  await login(page);
  await page.goto(`${base}/kelola_dokter_nakes/detail/10280101`, { waitUntil: 'domcontentloaded' });
  expect(await page.locator('a[href*="kelola_staff_puskesmas"]').count()).toBe(0);
  const rows = page.locator('#staf-puskesmas tbody tr');
  if (!(await rows.count())) {
    test.skip(true, 'tidak ada staf pada data uji');
  }
  await page.locator('#staf-puskesmas button:has-text("Kelola")').first().click();
  const modal = page.locator('.dl-staff-manage.show').first();
  await expect(modal).toBeVisible();
  await expect(modal.locator('.nav-link', { hasText: 'Data staf' })).toHaveText('Data staf');
  await expect(modal.locator('.nav-link', { hasText: 'Akun & akses' })).toBeVisible();
  await expect(modal.locator('.nav-link', { hasText: 'Status' })).toBeVisible();
  await expect(modal.locator('form[action*="/kelola_staff_puskesmas/update/"]')).toHaveCount(1);
  await expect(modal.locator('a[href*="kelola_staff_puskesmas/edit"]')).toHaveCount(0);
});

test('modal Kelola mempertahankan isian dan membuka tab Akun setelah error', async ({ page }) => {
  await login(page);
  await page.goto(`${base}/kelola_dokter_nakes/detail/10280101`, { waitUntil: 'domcontentloaded' });
  if (!(await page.locator('#staf-puskesmas button:has-text("Kelola")').count())) {
    test.skip(true, 'tidak ada staf pada data uji');
  }
  await page.locator('#staf-puskesmas button:has-text("Kelola")').first().click();
  const modal = page.locator('.dl-staff-manage.show').first();
  const name = modal.locator('form[action*="/update/"] input[name="nama"]');
  const phone = modal.locator('form[action*="/update/"] input[name="no_hp"]');
  await name.fill('Uji Modal');
  await phone.fill('abc');
  await modal.locator('form[action*="/update/"] button[type="submit"]').click();
  await page.waitForURL(/open_staff=/, { timeout: 15000 });
  await expect(page.locator('.dl-staff-manage.show')).toBeVisible();
  await expect(page.locator('[id$="FormErrorSummary"]')).toBeVisible();
  await expect(page.locator('.dl-staff-manage.show form[action*="/update/"] input[name="nama"]')).toHaveValue('Uji Modal');
  await page.locator('.dl-staff-manage.show .modal-header .close').click();
  const trigger = page.locator('#staf-puskesmas button:has-text("Kelola")').first();
  await expect(trigger).toBeFocused();
});

test('modal Kelola menampilkan tab Akun dan mengembalikan tab setelah konfirmasi dibatalkan', async ({ page }) => {
  await login(page);
  await page.goto(`${base}/kelola_dokter_nakes/detail/10280101`, { waitUntil: 'domcontentloaded' });
  if (!(await page.locator('#staf-puskesmas button:has-text("Kelola")').count())) {
    test.skip(true, 'tidak ada staf pada data uji');
  }
  await page.locator('#staf-puskesmas button:has-text("Kelola")').first().click();
  const modal = page.locator('.dl-staff-manage.show').first();
  await modal.locator('.nav-link', { hasText: 'Akun & akses' }).click();
  await expect(modal.locator('form[action*="reset_personal_password"]')).toBeVisible();
  await expect(modal.locator('form[action*="issue_activation_code"]')).toBeVisible();
  const password = modal.locator('form[action*="reset_personal_password"] input[name="password"]');
  await password.fill('Uji#2026!');
  await modal.locator('form[action*="reset_personal_password"] input[name="confirm_password"]').fill('Uji#2026!');
  await modal.locator('form[action*="reset_personal_password"] button[type="submit"]').click();
  await expect(page.locator('#doclincConfirmModal')).toBeVisible();
  await page.locator('#doclincConfirmModal .modal-footer [data-dismiss="modal"]').click();
  await expect(modal).toBeVisible();
  await expect(modal.locator('.nav-link.active')).toHaveText('Akun & akses');
  await expect(password).toHaveValue('Uji#2026!');
});

test('modal Kelola dan tabel staf tidak meluap di 390px', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page);
  await page.goto(`${base}/kelola_dokter_nakes/detail/10280101`, { waitUntil: 'domcontentloaded' });
  if (!(await page.locator('#staf-puskesmas button:has-text("Kelola")').count())) {
    test.skip(true, 'tidak ada staf pada data uji');
  }
  const height = await page.locator('#staf-puskesmas button:has-text("Kelola")').first().evaluate((el) => el.getBoundingClientRect().height);
  expect(height).toBeGreaterThanOrEqual(43.5);
  await page.locator('#staf-puskesmas button:has-text("Kelola")').first().click();
  const modal = page.locator('.dl-staff-manage.show').first();
  await expect(modal).toBeVisible();
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  expect(overflow).toBe(false);
  const tabHeight = await modal.locator('.nav-link').first().evaluate((el) => el.getBoundingClientRect().height);
  expect(tabHeight).toBeGreaterThanOrEqual(43.5);
});

test('login dan aktivasi nakes tetap utuh tanpa CDN', async ({ page }) => {
  await page.route('**/*', (route) => {
    const url = route.request().url();
    if (url.startsWith('http://127.0.0.1:8080')) {
      return route.continue();
    }
    return route.abort();
  });
  let loginSwalAvailable = false;
  for (const [path, selector] of [['/login', '.dl-auth-card'], ['/login/activate', '.dl-nakes-auth-card']]) {
    await page.goto(`http://127.0.0.1:8080${path}`, { waitUntil: 'networkidle' });
    if (path === '/login/activate' && !(await page.locator('#activationForm').count())) {
      continue;
    }
    await expect(page.locator(selector)).toBeVisible();
    if (path === '/login') {
      await expect(page.getByText('Aktivasi akun Nakes')).toHaveCount(0);
      loginSwalAvailable = await page.evaluate(() => typeof window.Swal === 'function');
    }
    const state = await page.evaluate((cardSelector) => {
      const card = document.querySelector(cardSelector);
      return {
        radius: card ? getComputedStyle(card).borderRadius : '0px',
        fonts: card ? getComputedStyle(card).fontFamily : '',
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
      };
    }, selector);
    expect(state.radius).not.toBe('0px');
    expect(state.fonts).toContain('Manrope');
    expect(state.overflow).toBe(false);
  }
  expect(loginSwalAvailable).toBe(true);
});

test('hub Puskesmas menjadi kartu bertumpuk di 390px', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page);
  await page.goto(`${base}/kelola_dokter_nakes`, { waitUntil: 'domcontentloaded' });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  expect(overflow, 'tidak boleh ada luapan mendatar').toBe(false);
  const layout = await page.evaluate(() => {
    const table = document.querySelector('.dl-hub-table');
    const head = table ? table.querySelector('thead') : null;
    const row = table ? table.querySelector('tbody tr') : null;
    const cell = row ? row.querySelector('td') : null;
    return {
      headPosition: head ? getComputedStyle(head).position : '',
      rowDisplay: row ? getComputedStyle(row).display : '',
      label: cell ? getComputedStyle(cell, '::before').content : '',
    };
  });
  expect(layout.headPosition).toBe('absolute');
  expect(layout.rowDisplay).toBe('block');
  expect(layout.label).toContain('Kode');
});

test('halaman staf tidak bergeser mendatar di 390px', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page);
  await page.goto(`${base}/kelola_staff_puskesmas?kode_pkm=10280101`, { waitUntil: 'domcontentloaded' });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  expect(overflow, 'tidak boleh ada luapan mendatar').toBe(false);
});
