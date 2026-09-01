const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const repo = path.resolve(__dirname, '..', '..', '..');
const read = (p) => fs.readFileSync(path.join(repo, p), 'utf8');

test('role-aware workflow surfaces retain progressive status presentation', () => {
  const warga = read('application/modules/home/views/partials/warga_visit_progress_v.php');
  const nakes = read('application/modules/home_nakes/views/partials/nakes_active_task_card_v.php');
  expect(warga).toContain('doclinc_warga_visit_timeline');
  expect(warga).toContain('Buka chat');
  expect(nakes).toContain('data-primary-next-action');
  expect(nakes).toContain('Dokter penanggung jawab');
  expect(nakes).toContain('Mulai perjalanan');
});

test('workflow code preserves canonical refresh and safe presentation boundaries', () => {
  const surface = read('application/libraries/Clinical_workflow_surface.php');
  const controller = read('application/modules/home_nakes/controllers/Home_nakes.php');
  expect(surface).toContain('private $resolver');
  expect(surface).toContain('private $presenter');
  expect(controller).toContain('clinical_workflow_surface->forRequest');
  expect(controller).toContain('safe_error_code');
  expect(controller).not.toMatch(/UPDATE\s+requests/i);
});

test('touched workflow surfaces keep actor-safe correction presentation', () => {
  const home = read('application/modules/home/views/home_v.php');
  const nakes = read('application/modules/home_nakes/views/partials/nakes_active_task_card_v.php');
  expect(home).toContain('warga_visit_progress_v');
  expect(nakes).toContain('primary_next_action');
  expect(nakes).not.toContain('correction_reason');
});
