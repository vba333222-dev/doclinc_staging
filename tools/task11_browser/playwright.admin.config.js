const base = require('./playwright.config');

/**
 * Konfigurasi khusus panel admin pada fixture lokal (Docker).
 *
 * Berbeda dari `playwright.static.config.js`, konfigurasi ini TIDAK menyentuh
 * `globalTeardown`: teardown bawaan menghapus run CI3 yang bisa dibuang, dan
 * fixture lokal ini justru harus bertahan antar-jalankan (seed 200 permintaan
 * memakan waktu, sedangkan tangkapan layar "sebelum" dan "sesudah" wajib
 * memakai data yang sama).
 *
 * Dijalankan manual:
 *   cd tools/task11_browser
 *   TASK11_BASE_URL=http://127.0.0.1:8080 BASELINE_DIR=/tmp/doclinc-baseline/before \
 *     npx playwright test --config=playwright.admin.config.js
 */
module.exports = {
  ...base,
  globalTeardown: undefined,
  testMatch: ['**/admin_panel_visual.spec.js', '**/admin_panel_a11y.spec.js', '**/admin_panel_copy_layout.spec.js'],
  projects: [
    {
      name: 'admin-desktop',
      use: { ...require('@playwright/test').devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
    },
    {
      name: 'admin-mobile',
      use: { ...require('@playwright/test').devices['Pixel 5'] },
    },
  ],
};
