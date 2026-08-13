'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
let passed = 0;
let failed = 0;
function expect(condition, name) {
  if (condition) { passed += 1; process.stdout.write(`PASS ${name}\n`); }
  else { failed += 1; process.stdout.write(`FAIL ${name}\n`); }
}

const tindakanController = read('admin_menu/application/modules/kelola_tindakan/controllers/Kelola_tindakan.php');
const tindakanModel = read('admin_menu/application/modules/kelola_tindakan/models/Kelola_tindakan_m.php');
const tindakanView = read('admin_menu/application/modules/kelola_tindakan/views/kelola_tindakan_v.php');
const recordController = read('admin_menu/application/modules/rekam_medis/controllers/Rekam_medis.php');
const recordModel = read('admin_menu/application/modules/rekam_medis/models/Rekam_medis_m.php');
const recordView = read('admin_menu/application/modules/rekam_medis/views/rekam_medis_v.php');
const service = read('application/libraries/Puskesmas_operations_service.php');
const partial = read('application/modules/home_nakes/views/partials/puskesmas_operations_v.php');
const css = read('assets/css/nakes-dashboard.css');
const client = read('assets/js/doclinc-puskesmas-operations.js');
const integration = read('tools/puskesmas_operations/tests/integration.php');

expect((tindakanController.match(/deny_clinical_mutation\(\)/g) || []).length >= 3 && !tindakanController.includes('->edit_tindakan($konsul_id') && !tindakanController.includes('->delete_tindakan($konsul_id'), 'admin_clinical_mutation_endpoints_fail_closed');
expect(!tindakanModel.includes("->update('konsultasi'") && /edit_tindakan[\s\S]*?return false;/.test(tindakanModel) && /delete_tindakan[\s\S]*?return false;/.test(tindakanModel), 'admin_clinical_model_mutations_retired');
expect(!/<form|modalEdit|modalDelete|Nonaktifkan|>Edit</.test(tindakanView), 'admin_clinical_mutation_ui_absent');
expect(recordController.includes("'Detail klinis tidak tersedia untuk Admin.'), 403") && !recordController.includes('get_record_detail('), 'admin_arbitrary_detail_endpoint_denied');
expect(/get_record_detail\([^)]*\)[\s\S]*?return null;/.test(recordModel), 'admin_record_model_detail_fails_closed');
expect(!/anamnesis|diagnosis|treatment|recommendations|patient_name/i.test(recordView) && !/recordDetailModal|record_id/.test(recordView), 'admin_view_contains_operational_metadata_only');
expect(!/AS diagnosis|AS anamnesis|AS treatment|AS recommendations|AS notes|AS patient_name/.test(recordModel), 'admin_query_excludes_clinical_and_patient_content');

const medicalRecordBlock = service.slice(service.indexOf('public function medicalRecord'), service.indexOf('private function operationalRows'));
expect(medicalRecordBlock.includes('mr.responsible_doctor_user_id') && medicalRecordBlock.includes('mr.recorded_by_user_id'), 'puskesmas_record_names_use_record_attribution');
expect(!/(^|[^m])r\.responsible_doctor_user_id/.test(medicalRecordBlock) && !/(^|[^m])r\.visit_performer_user_id/.test(medicalRecordBlock), 'current_request_assignment_not_used_for_record_attribution');
expect(medicalRecordBlock.includes("'Belum tercatat'") && client.includes("['Dicatat oleh', data.recorded_by_name]"), 'legacy_record_attribution_has_human_fallback');

expect(partial.includes('dl-puskesmas-operations') && client.includes("classList.add('dl-command-center')"), 'command_center_has_role_specific_shell_modifier');
expect(css.includes('.dl-nakes-dashboard.dl-command-center .dl-shell') && css.includes('max-width: 1280px') && css.includes('@media (min-width: 1024px)'), 'command_center_desktop_shell_uses_wide_bounded_layout');
expect(css.includes('.dl-command-center .dl-operations-runtime') && client.includes('dl-operations-section--requests'), 'operations_workboard_uses_responsive_grid_contract');
expect(!/\.dl-nakes-dashboard \.dl-shell\s*\{[^}]*max-width:\s*1280px/s.test(css), 'personal_nakes_shell_remains_compact');

const requestFixture = integration.slice(integration.indexOf('INSERT INTO requests VALUES'), integration.indexOf('INSERT INTO medicalrecords'));
const recordFixture = integration.slice(integration.indexOf('INSERT INTO medicalrecords'), integration.indexOf('INSERT INTO request_staff_assignments'));
expect(integration.includes("$test_timestamp = '2026-08-14 00:30:00'") && integration.includes("$test_date = '2026-08-14'"), 'test_clock_is_explicit_and_deterministic');
expect(!/NOW\(|CURRENT_DATE|date\('Y-m-d'\)/.test(requestFixture + recordFixture), 'operational_fixtures_do_not_use_database_or_wall_clock');
expect(integration.includes("SET time_zone = '+00:00'") && integration.includes('explicit_test_clock_survives_utc_database_jakarta_midnight_boundary'), 'utc_jakarta_boundary_is_executed');

process.stdout.write(`PHASE8A_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`PHASE8A_SOURCE_FAILED=${failed}\n`);
process.exit(failed > 0 ? 1 : 0);
