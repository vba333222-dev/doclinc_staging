'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
let passed = 0;
let failed = 0;
function expect(condition, label) {
  if (condition) { passed += 1; process.stdout.write(`PASS ${label}\n`); }
  else { failed += 1; process.stdout.write(`FAIL ${label}\n`); }
}

const config = read('application/config/config.php');
const routes = read('application/config/routes.php');
const controller = read('application/controllers/Puskesmas_operations.php');
const policy = read('application/libraries/Puskesmas_operations_policy.php');
const service = read('application/libraries/Puskesmas_operations_service.php');
const homeController = read('application/modules/home_nakes/controllers/Home_nakes.php');
const shell = read('application/modules/home_nakes/views/home_nakes_v.php');
const navigation = read('application/modules/home_nakes/views/partials/nakes_bottom_nav_v.php');
const partial = read('application/modules/home_nakes/views/partials/puskesmas_operations_v.php');
const client = read('assets/js/doclinc-puskesmas-operations.js');
const integration = read('tools/puskesmas_operations/tests/integration.php');
const integrationRunner = read('tools/puskesmas_operations/run_disposable_integration.sh');

expect(config.includes('DOCLINC_PUSKESMAS_OPERATIONS_ENABLED') && config.includes('DOCLINC_PUSKESMAS_OPERATIONS_ENVIRONMENT'), 'feature_flag_environment_bound');
expect(config.includes("$config['role_prerequisites_enabled'] === true") && config.includes("$config['nakes_presence_enabled'] === true"), 'feature_requires_prerequisites_and_presence');
expect(routes.includes("$route['puskesmas/operations'] = 'home_nakes/operations';") && routes.includes("$route['puskesmas/operations/snapshot'] = 'puskesmas_operations/snapshot';"), 'explicit_page_and_snapshot_routes');
expect(controller.includes("method(true) !== 'GET'") && controller.includes('query_not_allowed'), 'snapshot_get_only_without_filters');
expect(controller.includes("doclinc_dokter_identity_context($user_id, true)") && controller.includes("doclinc_role_prerequisite_state((int) $actor['user_id'], true)"), 'actor_and_prerequisites_refreshed');
expect(policy.includes("account_type'] ?? '') !== 'command_center'") && policy.includes("is_command_center") && policy.includes("identity['user_id']"), 'policy_is_command_center_only');
expect(
  service.includes("->where('ps.kode_pkm', $puskesmas_code)")
    && service.includes("->where('assigned_puskesmas_code', $puskesmas_code)")
    && service.includes("aps.kode_pkm = ' . $this->db->escape($puskesmas_code)"),
  'all_queries_tenant_scoped'
);
expect(service.includes("->where('request_status', 'Accepted')") && service.includes("->where('rsa.status', 'aktif')"), 'workload_uses_active_requests_and_assignments');
expect(service.includes('np.puskesmas_code COLLATE utf8mb4_unicode_ci = CONVERT(ps.kode_pkm USING utf8mb4) COLLATE utf8mb4_unicode_ci'), 'presence_join_normalizes_real_collation_boundary');
expect(service.includes("->limit($staff_limit + 1)") && service.includes('count($staff_rows) > $staff_limit'), 'staff_overflow_fails_closed_without_truncation');
expect(service.includes("->limit($request_limit + 1)") && service.includes("'code' => 'result_too_large'") && controller.includes("'result_too_large'"), 'bounded_snapshot_overflow_fails_closed');
expect(!/(nik|no_kk|bpjs|nip|nomor_sip|no_hp|latitude|longitude|diagnosis|treatment|request_description)/i.test(service), 'service_has_no_identity_clinical_or_location_projection');
expect(homeController.includes("$this->initial_section = 'operasional'") && homeController.includes("account_type'] ?? '') !== 'command_center'"), 'dedicated_page_rejects_non_command_center');
expect(shell.includes("partials/puskesmas_operations_v") && shell.includes('doclinc-puskesmas-operations.js'), 'command_center_shell_loads_operations_runtime');
expect(navigation.includes("site_url('puskesmas/operations')") && navigation.includes("navigateNakesSection('operasional'"), 'stable_operations_navigation');
expect(partial.includes('tidak menampilkan identitas pasien, isi klinis, atau koordinat') && partial.includes('Hanya pantau') && partial.includes('aria-live="polite"'), 'least_privilege_ui_contract');
expect(client.includes("textContent = text") && !client.includes('innerHTML'), 'browser_uses_text_nodes_only');
expect(client.includes("addEventListener('pagehide'") && client.includes('visibilityState') && client.includes('AbortController'), 'browser_lifecycle_is_bounded');
expect(integration.includes('MariaDbReadiness::wait') && integration.includes('doclinc_puskesmas_ops_test_') && integration.includes('DROP DATABASE IF EXISTS'), 'official_disposable_mariadb_integration_contract');
expect(integration.includes("define('FCPATH', dirname(__DIR__, 3) . '/')") && integration.indexOf("define('FCPATH'") < integration.indexOf("require_once BASEPATH . 'core/Common.php'"), 'integration_defines_front_controller_path_before_ci_bootstrap');
expect(integration.includes('$db->data_cache = array();') && integration.includes("throw new RuntimeException('tenant_a_snapshot_unavailable')"), 'integration_refreshes_schema_cache_and_stops_after_primary_failure');
expect(integration.includes('utf8mb4_general_ci') && integration.includes('utf8mb4_unicode_ci') && integration.includes('presence_fixture_uses_real_mixed_collations'), 'integration_reproduces_real_presence_collations');
expect(integration.includes('tenant_b_staff_absent_from_tenant_a') && integration.includes('tenant_identifier_injection_fails_closed') && integration.includes('zero_database_mutation'), 'integration_covers_tenant_privacy_and_zero_mutation');
expect(integrationRunner.includes("read -r -s -p 'Disposable MariaDB admin password: '") && integrationRunner.includes('unset DB_PASSWORD DOCLINC_TEST_DB_ADMIN_PASSWORD') && !integrationRunner.includes('DATABASE_NAME'), 'integration_runner_prompts_secret_and_rejects_database_target');

process.stdout.write(`PUSKESMAS_OPERATIONS_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`PUSKESMAS_OPERATIONS_SOURCE_FAILED=${failed}\n`);
process.exit(failed > 0 ? 1 : 0);
