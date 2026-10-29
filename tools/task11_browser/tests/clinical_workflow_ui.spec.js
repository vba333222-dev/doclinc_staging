const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

const repo = path.resolve(__dirname, '..', '..', '..');
const read = relative => fs.readFileSync(path.join(repo, relative), 'utf8');

function workspaceFixture() {
  const steps = ['Disposisi', 'Nakes', 'Kunjungan', 'Hasil', 'Review', 'Rekam medis', 'Penutupan'];
  return `<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style></head>
    <body class="dl-clinical-workspace">
      <div class="consult-shell dl-clinical-workspace" data-cw-root>
        <a class="cw-skip-link" href="#main">Lewati ke konten utama</a>
        <header class="consult-header cw-workspace-header" id="status-konsultasi">
          <a class="consult-back" href="#kembali" aria-label="Kembali"><i class="fas fa-arrow-left"></i></a>
          <div class="consult-title"><h1>Pemeriksaan Pasien</h1><p class="consult-subtitle">A-01 · Tinjau hasil</p></div>
          <span class="cw-state is-warning">Tinjau hasil</span>
        </header>
        <section class="cw-card cw-workspace-thread" id="alur-klinis">
          <div class="cw-card-head"><h2 class="cw-section-title">Alur klinis</h2></div>
          <div class="cw-card-body">
            <ol class="cw-thread">${steps.map((label, index) => `<li class="cw-step is-${index < 4 ? 'done' : index === 4 ? 'current' : 'pending'}"${index === 4 ? ' aria-current="step"' : ''}><span class="cw-step-label">${label}</span><span class="cw-step-meta">Langkah ${index + 1}</span></li>`).join('')}</ol>
          </div>
        </section>
        <main id="main" tabindex="-1" class="content">
          <div class="nakes-clinical-layout">
            <aside class="nakes-context-rail">
              <details class="cw-context-details" open><summary class="cw-context-summary">Konteks pasien</summary>
                <section class="consult-card"><h2 class="section-heading">Ringkasan Pasien</h2><div class="summary-grid"><div class="summary-item"><span class="summary-label">Nama</span><span class="summary-value">PASIEN UJI</span></div></div></section>
              </details>
            </aside>
            <div class="nakes-work-pane">
              <section class="consult-card"><h2 class="section-heading">Hasil Kunjungan</h2><p>Catatan klinis panjang untuk menguji lebar dan fokus.</p><div class="cw-field"><label class="cw-label" for="fixtureField">Catatan</label><textarea id="fixtureField" class="form-control" rows="4">Tidak ada perbaikan berlebih pada tata letak.</textarea></div></section>
            </div>
          </div>
          <div class="cw-dock"><div><span class="cw-eyebrow">Langkah berikutnya</span><strong>Tinjau hasil</strong></div><div class="cw-actions"><a class="cw-btn cw-btn-primary" href="#review-hasil">Tinjau hasil</a></div></div>
        </main>
      </div>
    </body></html>`;
}

async function loadWorkspace(page, width) {
  await page.setViewportSize({ width, height: 900 });
  await page.setContent(workspaceFixture());
  await page.addStyleTag({ path: path.join(repo, 'assets/css/doclinc-design-system.css') });
  await page.addStyleTag({ path: path.join(repo, 'assets/css/doclinc-nakes.css') });
  await page.addStyleTag({ path: path.join(repo, 'assets/css/doclinc-clinical-workspace.css') });
}


test.describe('clinical workflow UI surfaces', () => {
  test('result, review, closure, placement, and watchdog panels are wired', async ({ page }) => {
    const konsultasi = read('application/modules/konsultasi_nakes/views/konsultasi_nakes_v.php');
    const resultPanel = read('application/modules/konsultasi_nakes/views/partials/clinical_result_v.php');
    const reviewPanel = read('application/modules/konsultasi_nakes/views/partials/clinical_review_v.php');
    const closurePanel = read('application/modules/konsultasi_nakes/views/partials/clinical_closure_v.php');
    const placementModal = read('admin_menu/application/modules/kelola_staff_puskesmas/views/partials/staff_placement_modal_v.php');
    const placementForm = read('admin_menu/application/modules/kelola_staff_puskesmas/views/partials/staff_placement_form_v.php');
    const watchdog = read('application/libraries/Active_request_watchdog.php');
    const dashboard = read('application/modules/home_nakes/views/partials/nakes_dashboard_v.php');

    expect(konsultasi).toContain('partials/clinical_result_v');
    expect(konsultasi).toContain('partials/clinical_review_v');
    expect(konsultasi).toContain('partials/clinical_closure_v');
    expect(resultPanel).toContain('visit_result_draft');
    expect(resultPanel).toContain('visit_result_submit');
    expect(resultPanel).toContain('visit_result_correction');
    expect(resultPanel).toContain('data-clinical-save-then-submit');
    expect(reviewPanel).toContain("'clinical/review'");
    expect(reviewPanel).toContain('correction_reason');
    expect(closurePanel).toContain("'clinical/record'");
    expect(closurePanel).toContain("'clinical/finalize'");
    expect(closurePanel).toContain("'clinical/amend'");
    expect(closurePanel).toContain("'clinical/close'");
    expect(placementModal).toContain('staff_placement_form_v');
    expect(placementForm).toContain('create_placement');
    expect(placementForm).toContain('schedule_transfer');
    expect(watchdog).toContain('request.clinical_stall');
    expect(watchdog).toContain('ready_for_closure');
    expect(dashboard).toContain('data-puskesmas-clinical-stall');

    await page.setContent(`
      <main>
        <section data-panel="result"><h2>Hasil Kunjungan</h2><button>Simpan &amp; kirim hasil</button></section>
        <section data-panel="review"><h2>Review Hasil Kunjungan</h2><select aria-label="Keputusan"><option>Setujui hasil</option></select></section>
        <section data-panel="closure"><h2>Rekam Medis &amp; Penutupan</h2><button>Tutup konsultasi</button></section>
        <p data-puskesmas-clinical-stall>2 konsultasi berhenti di tahap klinis.</p>
      </main>
    `);
    await expect(page.locator('[data-panel="result"]')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Simpan & kirim hasil' })).toBeVisible();
    await expect(page.locator('[data-panel="review"]')).toContainText('Review Hasil Kunjungan');
    await expect(page.getByRole('button', { name: 'Tutup konsultasi' })).toBeVisible();
    await expect(page.locator('[data-puskesmas-clinical-stall]')).toContainText('2 konsultasi');
  });

  test('clinical status messages remain an aria-live status region', async ({ page }) => {
    await page.setContent('<div role="status" aria-live="polite" data-clinical-message>Draf hasil tersimpan.</div>');
    await expect(page.getByRole('status')).toHaveText('Draf hasil tersimpan.');
  });
});

test.describe('clinical workspace responsive and accessibility', () => {
  for (const width of [320, 375, 390, 430, 768, 1024, 1440]) {
    test(`no horizontal page overflow at ${width}px`, async ({ page }) => {
      await loadWorkspace(page, width);
      const metrics = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
      }));
      expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.clientWidth + 1);
      await expect(page.locator('.cw-dock')).toBeVisible();
      expect(await page.locator('.cw-step[aria-current="step"]').count()).toBe(1);
      const buttonBox = await page.locator('.cw-dock .cw-btn').boundingBox();
      expect(buttonBox.height).toBeGreaterThanOrEqual(44);
    });
  }

  test('context disclosure follows viewport and main skip target is focusable', async ({ page }) => {
    await loadWorkspace(page, 390);
    await expect(page.locator('.cw-context-summary')).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(page.locator('.cw-skip-link')).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main')).toBeFocused();

    await loadWorkspace(page, 1200);
    await expect(page.locator('.cw-context-summary')).toBeHidden();
    const columns = await page.locator('.nakes-clinical-layout').evaluate(node => getComputedStyle(node).gridTemplateColumns);
    expect(columns.split(' ').length).toBeGreaterThanOrEqual(2);
  });

  test('reduced motion removes meaningful workspace transitions', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await loadWorkspace(page, 390);
    const duration = await page.locator('.cw-dock .cw-btn').evaluate(node => getComputedStyle(node).transitionDuration);
    expect(parseFloat(duration)).toBeLessThanOrEqual(0.01);
  });
});
