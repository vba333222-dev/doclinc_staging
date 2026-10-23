'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
let passed = 0;
let failed = 0;
function expect(condition, label) {
  if (condition) { passed += 1; process.stdout.write(`PASS ${label}\n`); return; }
  failed += 1; process.stdout.write(`FAIL ${label}\n`);
}

const routes = read('application/config/routes.php');
const login = read('application/modules/login/controllers/Login.php');
const nakesLogin = read('application/modules/login_nakes/controllers/Login_nakes.php');
const loginView = read('application/modules/login/views/login_v.php');
const loginModel = read('application/modules/login/models/Login_m.php');
const activationView = read('application/modules/login_nakes/views/activate_nakes_v.php');
const changeView = read('application/modules/login_nakes/views/change_password_nakes_v.php');
const passwordGate = read('application/hooks/Password_change_gate.php');
const adminController = read('admin_menu/application/modules/kelola_staff_puskesmas/controllers/Kelola_staff_puskesmas.php');
const adminModel = read('admin_menu/application/modules/kelola_staff_puskesmas/models/Kelola_staff_puskesmas_m.php');
const adminView = read('admin_menu/application/modules/kelola_staff_puskesmas/views/kelola_staff_puskesmas_v.php');
const policy = read('application/libraries/Nakes_personal_account_policy.php');
const presentation = read('application/helpers/nakes_account_presentation_helper.php');
const config = read('application/config/config.php');
const tokenPolicy = read('application/libraries/First_login_token_policy.php');
const inventory = read('tools/personal_nakes/staging_readonly_inventory.sql.txt');
const dbRunner = read('tools/personal_nakes/tests/disposable_db_integration.php');
const stripPhp = source => source.replace(/<\?[\s\S]*?\?>/g, '');
const methodBody = (source, name, nextName) => {
  const start = source.indexOf(`function ${name}`);
  const end = nextName ? source.indexOf(`function ${nextName}`, start + 1) : source.length;
  return start < 0 ? '' : source.slice(start, end < 0 ? source.length : end);
};

expect(routes.includes("$route['login/activate']") && routes.includes("$route['login/activate/auth']")
  && routes.includes("$route['login-nakes']") && routes.includes("$route['login-nakes/activate/auth']"), 'activation_routes_are_explicit');
expect(nakesLogin.includes('public function activate_auth()') && nakesLogin.includes("method(TRUE) !== 'POST'"), 'activation_auth_is_post_only');
expect(nakesLogin.includes("'credential_activation_user_id'") && nakesLogin.includes("'password_change_session_binding'") && nakesLogin.includes('sess_regenerate(TRUE)'), 'activation_session_is_bound_and_regenerated');
expect(nakesLogin.includes("'id', 'username', 'nama', 'email', 'role', 'remark', 'picture', 'logged_in'") && !nakesLogin.match(/credential_activation_user_id[\s\S]{0,250}'logged_in'\s*=>\s*TRUE/), 'activation_session_is_not_full_runtime_session');
expect(nakesLogin.includes("first_login_password_change_enabled') !== true") && nakesLogin.includes("nakes_credential_enforcement_enabled') === true"), 'workflow_availability_remains_separate_from_enforcement');
expect(loginModel.includes('get_personal_activation_identity') && loginModel.includes('Nakes_personal_account_policy'), 'activation_uses_canonical_identity_policy');
expect(loginModel.includes('FOR UPDATE') && loginModel.includes('hash_equals($expected_current_hash') && loginModel.includes("'password_changed_at' => date"), 'credential_completion_is_atomic_and_compare_and_swap');
expect(nakesLogin.includes("activation_failure('credential_mismatch')") && !nakesLogin.includes("metadata_json' => $password"), 'activation_failure_does_not_enumerate_or_log_credentials');
expect(loginModel.includes("'must_change_password' => 0") && loginModel.includes("'password_changed_at' => date"), 'successful_enrollment_transitions_to_active_raw_state');
expect(activationView.includes('password sementara') && activationView.includes("site_url('login-nakes/activate/auth')"), 'activation_ui_has_controlled_credential_boundary');
expect(changeView.includes('password_change_token') && changeView.includes("site_url('login-nakes/change-password/submit')")
  && nakesLogin.includes("unset_userdata('password_change_token_state')") && nakesLogin.includes('first_login_token_policy->consume'), 'password_change_consumes_single_use_form_token');

/*
 * Pemisahan permukaan masuk nakes dari permukaan warga.
 *
 * Permintaan pemilik produk 2026-10-24: cara verifikasi dan masuk akun nakes
 * tidak boleh berada di halaman yang sama dengan warga/pasien.
 */
expect(!loginView.includes("site_url('login/activate')") && loginView.includes("site_url('login-nakes')"), 'warga_login_has_no_nakes_activation_entry');
expect(!loginView.includes('sign_up') || loginView.includes("site_url('sign_up')"), 'warga_login_keeps_warga_registration_only');
expect(!activationView.includes("site_url('sign_up')") && !changeView.includes("site_url('sign_up')"), 'nakes_surface_never_offers_self_registration');
expect(routes.includes("$route['login/activate'] = 'login_nakes/activate'")
  && routes.includes("$route['login/change-password'] = 'login_nakes/change_password'"), 'legacy_nakes_routes_alias_to_nakes_module');
expect(nakesLogin.includes("(string) $user->role !== 'dokter'") && nakesLogin.includes('echo "2"'), 'nakes_login_refuses_non_nakes_accounts');
expect(nakesLogin.includes("'role' => $user->role,") && login.includes('public function auth()'), 'both_surfaces_keep_single_shared_identity_model');
expect(nakesLogin.includes("site_url('login-nakes") === false && nakesLogin.includes("redirect('login-nakes"), 'nakes_controller_redirects_to_nakes_surface');
expect(passwordGate.includes("redirect($role === 'dokter' ? 'login-nakes' : 'login')")
  && passwordGate.includes("redirect('login-nakes/change-password')"), 'password_gate_sends_nakes_to_nakes_surface');
expect(read('assets/css/doclinc-nakes-auth.css').includes('--dl-brand')
  && !read('application/modules/login/views/login_v.php').includes('doclinc-nakes-auth.css'), 'nakes_surface_has_its_own_stylesheet');
expect(tokenPolicy.includes('function consume') && tokenPolicy.includes('$state = null') && tokenPolicy.includes('hash_hmac'), 'token_policy_has_destructive_consume_and_session_binding');
expect(policy.includes('STAFF_ONLY') && policy.includes('STAFF_WITH_UNLINKED_ACCOUNT') && policy.includes('ADMIN_RESET_PENDING') && policy.includes('INVALID'), 'provisioning_domain_states_are_centralized');
expect(policy.includes("active_link_count") && policy.includes("is_command_center") && policy.includes("facility_status"), 'identity_conflicts_fail_closed_in_policy');
expect(adminController.includes("level') !== 'admin'") && adminController.includes('ensure_provisioning_csrf_token') && adminController.includes('hash_equals($expected, $submitted)'), 'admin_provisioning_is_admin_only_and_csrf_protected');
expect((adminView.match(/name="_provisioning_csrf_token"/g) || []).length >= 8, 'all_admin_mutation_forms_include_provisioning_csrf');
expect(adminController.includes("'birthdate' => $birthdate") && adminController.includes("'gender' => $gender"), 'admin_collects_required_personal_demographics');
expect(adminModel.includes("'tgl' => $birthdate") && adminModel.includes("'gender' => $gender") && adminModel.includes("'no_hp' => trim"), 'provisioning_persists_demographics_in_existing_users_fields');
expect(adminModel.includes("'role' => 'dokter'") && !adminModel.includes("'role' => 'nakes'"), 'legacy_role_enum_is_preserved');
expect(adminModel.includes('account_linked_to_other_active_staff') && adminModel.includes('get_command_center_user_id') && adminModel.includes('puskesmas_is_active'), 'admin_operations_revalidate_link_facility_and_command_center');
expect(adminModel.includes("SELECT GET_LOCK(?, 5) AS acquired") && adminModel.includes("WHERE user_id = ? AND status = ? FOR UPDATE"), 'link_creation_serializes_duplicate_active_link_check');
expect(['create_and_link_personal_account', 'reset_personal_password', 'update_linked_personal_profile', 'bind_staff_account', 'unbind_staff_account'].every((name, index, names) => {
  const body = methodBody(adminModel, name, names[index + 1]);
  return body.includes('trans_begin()') && body.includes('FOR UPDATE') && body.includes('trans_rollback()');
}), 'all_provisioning_mutations_have_transaction_lock_and_rollback');
expect(adminController.includes('update_personal_profile') && adminModel.includes('update_linked_personal_profile') && adminView.includes('Data personal Nakes'), 'admin_can_maintain_linked_personal_demographics');
expect(adminModel.includes("'must_change_password' => 1") && adminModel.includes("'password_changed_at' => null"), 'new_account_starts_first_login_pending');
expect(adminModel.includes("'must_change_password' => 1") && !adminModel.match(/reset_personal_password[\s\S]{0,5000}'password_changed_at'\s*=>\s*null/), 'admin_reset_preserves_prior_change_evidence');
expect(presentation.includes('Menunggu aktivasi pertama') && presentation.includes('Menunggu pembuatan password baru')
  && !adminView.includes('>FIRST_LOGIN_PENDING<') && !adminView.includes('>ADMIN_RESET_PENDING<'), 'machine_states_are_mapped_for_ui');
expect(!activationView.includes('password_hash') && !adminView.includes('value="password"'), 'password_hash_and_plaintext_are_not_rendered');
expect(config.includes("$config['nakes_credential_enforcement_enabled']") && !config.includes('PHASE2_ENABLE_ENFORCEMENT'), 'no_phase4_enforcement_switch_added');
expect(!/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE|CREATE)\b/i.test(inventory.replace(/^--.*$/gm, ''))
  && inventory.includes('active_personal_staff_rows') && inventory.includes('facility_mismatch') && inventory.includes('command_center_linked_as_staff'), 'staging_inventory_is_read_only_and_complete');
expect(dbRunner.includes("doclinc_phase23_test_") && dbRunner.includes("YES_DELETE_TEST_DATABASE")
  && dbRunner.includes("strpos($database, 'staging')") && dbRunner.includes("DROP DATABASE"), 'disposable_runner_is_allowlisted_and_self_cleaning');
const visibleUi = [adminView, activationView, changeView].map(source => stripPhp(source).replace(/<[^>]+>/g, ' ')).join('\n');
expect(!['STAFF_ONLY', 'STAFF_WITH_UNLINKED_ACCOUNT', 'FIRST_LOGIN_PENDING', 'ACTIVE', 'ADMIN_RESET_PENDING', 'INVALID'].some(code => visibleUi.includes(code)), 'raw_account_state_codes_not_visible');
expect(!/\b(schema|readiness|tenant|enforcement|credential state|CAS)\b/i.test(visibleUi), 'engineering_terms_not_visible_in_phase23_ui');

process.stdout.write(`PERSONAL_NAKES_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`PERSONAL_NAKES_SOURCE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
