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

const migration = read('application/migrations/20260802000100_role_identity_foundation.php');
const home = read('application/modules/home/controllers/Home.php');
const homeModel = read('application/modules/home/models/Home_m.php');
const adminController = read('admin_menu/application/modules/kelola_staff_puskesmas/controllers/Kelola_staff_puskesmas.php');
const adminModel = read('admin_menu/application/modules/kelola_staff_puskesmas/models/Kelola_staff_puskesmas_m.php');
const adminView = read('admin_menu/application/modules/kelola_staff_puskesmas/views/kelola_staff_puskesmas_v.php');
const dashboard = read('application/modules/home_nakes/views/partials/nakes_dashboard_v.php');
const commandCenterController = read('admin_menu/application/modules/kelola_dokter_nakes/controllers/Kelola_dokter_nakes.php');
const commandCenterModel = read('admin_menu/application/modules/kelola_dokter_nakes/models/Kelola_dokter_nakes_m.php');
const commandCenterView = read('admin_menu/application/modules/kelola_dokter_nakes/views/kelola_dokter_nakes_v.php');
const publicPasswordPolicy = read('application/libraries/Password_strength_policy.php');
const adminPasswordPolicy = read('admin_menu/application/libraries/Password_strength_policy.php');
const credentialPolicy = read('application/libraries/Nakes_credential_policy.php');
const credentialEnforcementPolicy = read('application/libraries/Nakes_credential_enforcement_policy.php');
const credentialEnforcementHelper = read('application/helpers/nakes_credential_enforcement_helper.php');
const config = read('application/config/config.php');
const loginController = read('application/modules/login/controllers/Login.php');
const loginModel = read('application/modules/login/models/Login_m.php');
const passwordGate = read('application/hooks/Password_change_gate.php');
const productionReadinessAudit = read('tools/production_readiness/audit.php');
const runtimeCredentialConsumers = [
	'application/controllers/Location_address.php',
	'application/controllers/Profile_media.php',
	'application/controllers/Puskesmas_operations.php',
	'application/controllers/Realtime_access.php',
	'application/controllers/Realtime_requests.php',
	'application/helpers/notification_helper.php',
	'application/helpers/request_realtime_helper.php',
	'application/libraries/Role_prerequisite_service.php',
	'application/modules/home_nakes/controllers/Home_nakes.php',
	'application/modules/home_nakes/models/Home_nakes_m.php',
	'application/modules/notifikasi/controllers/Notifikasi.php',
].map(read);
const schemaAwareRuntimeCredentialConsumers = [
	'application/controllers/Location_address.php',
	'application/controllers/Profile_media.php',
	'application/controllers/Puskesmas_operations.php',
	'application/controllers/Realtime_access.php',
	'application/controllers/Realtime_requests.php',
	'application/helpers/notification_helper.php',
	'application/helpers/request_realtime_helper.php',
	'application/modules/home_nakes/controllers/Home_nakes.php',
	'application/modules/home_nakes/models/Home_nakes_m.php',
	'application/modules/notifikasi/controllers/Notifikasi.php',
].map(read);
const resetPersonalMethod = adminModel.slice(
	adminModel.indexOf('public function reset_personal_password'),
	adminModel.indexOf('public function get_command_center_user_id')
);

expect(migration.includes("if (PHP_SAPI !== 'cli')") && migration.includes('EXECUTION_MODE=PLAN'), 'migration_is_cli_and_plan_by_default');
expect(migration.includes('schema_write_disabled') && migration.includes('backup_checksum_confirmation_mismatch') && migration.includes('GET_LOCK'), 'migration_requires_write_backup_and_lock');
expect(migration.includes('NULL') && migration.includes('EXISTING_ROWS_CHANGED=false'), 'migration_preserves_existing_rows');
expect(migration.includes('role_identity_default_is_null') && migration.includes("strtoupper(trim((string) $value)) === 'NULL'"), 'migration_accepts_mariadb_null_metadata_without_weakening_contract');
expect(home.includes('Role_identity_policy') && home.includes('profile_identity_conflict'), 'warga_identity_validation_and_conflict_contract');
expect(homeModel.includes("array('nik', 'nomor_bpjs_kis')") && homeModel.includes("where('userId !=', $user_id)"), 'warga_unique_lookup_is_scoped');
expect(home.includes('array_intersect_key') && !home.includes("set_userdata(array_merge($data"), 'identity_values_not_stored_in_session');
expect(adminController.includes("level') !== 'admin'") && adminController.includes('Role_identity_policy') && adminController.includes('nip_available'), 'nip_is_admin_validated');
expect(adminModel.includes('uq_puskesmas_staff_nip') === false && adminModel.includes('nip_available'), 'admin_model_uses_schema_uniqueness_without_ddl');
expect(adminController.includes("$status === 'aktif' && !$this->Kelola_staff_puskesmas_m->staff_is_operationally_complete($staff)") && adminModel.includes('function staff_is_operationally_complete'), 'incomplete_staff_cannot_be_activated');
expect(adminModel.includes("(string) ($staff->status ?? '') !== 'aktif' || !$this->staff_is_operationally_complete($staff)"), 'incomplete_or_inactive_staff_cannot_be_linked');
expect(adminModel.includes('function personal_account_state') && adminModel.includes('active_user_link_count') && adminModel.includes("return $valid ? 'linked' : 'invalid'"), 'staff_account_readiness_rejects_duplicate_mismatched_and_command_center_links');
expect(adminModel.includes('function readiness_issues') && adminModel.includes("'staff_registration_number'") && adminModel.includes("'staff_registration_expiry'") && adminModel.includes("'staff_sip_expired'"), 'staff_readiness_uses_safe_action_codes');
expect(adminModel.includes('staff_user.must_change_password AS akun_must_change_password')
	&& adminModel.includes('staff_user.password_changed_at AS akun_password_changed_at')
	&& adminModel.includes("'staff_first_login_pending'")
	&& adminModel.includes("'staff_password_reset_pending'")
	&& adminView.includes('Belum pernah mengganti password bawaan'), 'admin_queue_separates_first_activation_and_reset_waiting_states');
expect(adminController.includes("'readiness' =>") && adminController.includes('staff_matches_readiness_filter') && adminController.includes("'missing_sip'") && adminController.includes("'sip_expiring'") && adminController.includes("'sip_expired'"), 'admin_staff_queue_has_bounded_server_side_filters');
expect(adminController.includes("$filter !== '' && (string) ($staff->status ?? '') !== 'aktif'"), 'inactive_staff_excluded_from_readiness_filter');
expect(adminView.includes('staffFilterReadiness') && adminView.includes('Kesiapan staf aktif') && adminView.includes('Relasi akun personal perlu diperiksa'), 'admin_staff_view_exposes_actionable_readiness_without_raw_identity_export');
expect(adminView.includes('Kosongkan bila staf bukan ASN/tidak memerlukan NIP. Jangan membuat nomor pengganti.'), 'admin_staff_form_forbids_fabricated_nip');
expect(adminView.includes('Data staf, gelar, SIP, masa berlaku SIP, status, dan hubungan akun hanya dikelola Administrator Dinas Kesehatan.'), 'admin_ownership_is_explicit');
expect(adminModel.includes("'must_change_password' => 1") && adminModel.includes("'password_changed_at' => null")
  && adminModel.includes("'must_change_password', 'password_changed_at'"), 'personal_account_creation_forces_first_login_password_change');
expect(adminController.includes('Password_strength_policy') && adminModel.includes('Password_strength_policy')
	&& adminView.includes('wajib membuat password baru saat login pertama')
	&& adminController.includes('reset_personal_password')
	&& adminModel.includes('reset_personal_password')
	&& (adminView.match(/minlength="8" maxlength="72"/g) || []).length === 4, 'personal_create_and_reset_temporary_password_contract_is_explicit');
expect(commandCenterModel.includes("'must_change_password' => 1") && commandCenterModel.includes("'password_changed_at' => null")
	&& commandCenterModel.includes("if (isset($allowed['password']))")
	&& !commandCenterModel.includes("$allowed['password_changed_at'] = null"), 'command_center_creation_is_unactivated_but_admin_reset_preserves_prior_change_evidence');
expect(commandCenterController.includes('required|min_length[8]|max_length[72]')
	&& commandCenterController.includes('Password_strength_policy')
	&& (commandCenterView.match(/minlength="8" maxlength="72"/g) || []).length === 4
	&& commandCenterView.includes('Belum pernah mengganti password bawaan'), 'command_center_temporary_password_is_bounded');
expect(publicPasswordPolicy.includes('strlen($password) < 8') && adminPasswordPolicy.includes('strlen($password) < 8')
  && publicPasswordPolicy.includes("preg_match('/[A-Z]/'")
  && publicPasswordPolicy.includes("preg_match('/[a-z]/'")
  && publicPasswordPolicy.includes("preg_match('/[0-9]/'")
	&& publicPasswordPolicy.includes("preg_match('/[^A-Za-z0-9\\s]/'"), 'all_password_paths_share_minimum_complexity_contract');
expect(credentialPolicy.includes('FIRST_LOGIN_PENDING') && credentialPolicy.includes('ADMIN_RESET_PENDING')
	&& credentialPolicy.includes('validChangedAt') && credentialPolicy.includes("(string) $role !== 'dokter'"), 'nakes_credential_state_has_one_fail_closed_policy');
expect(config.includes('DOCLINC_NAKES_CREDENTIAL_ENFORCEMENT_ENABLED')
	&& config.includes('DOCLINC_NAKES_CREDENTIAL_ENFORCEMENT_ENVIRONMENT')
	&& config.includes("$config['nakes_credential_enforcement_enabled']")
	&& config.includes('Doclinc_feature_flags::resolve')
	&& config.includes('$nakes_credential_enforcement_environment_env,\n\t$realtime_client_runtime_environment_env'), 'credential_enforcement_flag_uses_distinct_actual_runtime_environment');
expect(credentialEnforcementPolicy.includes('Nakes_credential_policy')
	&& credentialEnforcementPolicy.includes('$this->enabled')
	&& credentialEnforcementPolicy.includes('requiresChange')
	&& credentialEnforcementHelper.includes("nakes_credential_enforcement_enabled"), 'effective_enforcement_delegates_to_raw_credential_policy');
expect(loginController.includes('doclinc_nakes_effective_must_change_password') && passwordGate.includes('doclinc_nakes_effective_must_change_password')
	&& loginModel.includes('Nakes_credential_policy')
	&& loginModel.includes('must_change_password, password_changed_at'), 'login_and_request_gate_use_effective_credential_state');
expect(passwordGate.indexOf("status !== 'aktif'") < passwordGate.indexOf('doclinc_nakes_effective_must_change_password')
	&& passwordGate.indexOf('role !== $session_role') < passwordGate.indexOf('doclinc_nakes_effective_must_change_password'), 'session_identity_and_active_status_remain_independent_denials');
expect(loginController.includes("nakes_credential_enforcement_enabled') === true")
	&& loginController.includes("first_login_password_change_enabled') === true")
	&& loginController.includes("if ($this->config->item('nakes_credential_enforcement_enabled') === true)"), 'remediation_availability_is_not_the_master_enforcement_switch');
expect(runtimeCredentialConsumers.every(source => source.includes('doclinc_nakes_credential')
	|| source.includes('doclinc_nakes_password_change_blocked')
	|| source.includes('doclinc_nakes_effective_must_change_password')
	|| source.includes('credential_enforcement_policy')), 'ordinary_runtime_credential_consumers_use_effective_state');
expect(schemaAwareRuntimeCredentialConsumers.every(source => source.includes('doclinc_nakes_credential_schema_allows_runtime')
	&& source.includes('doclinc_nakes_password_changed_at_projection')), 'runtime_credential_schema_is_optional_only_when_enforcement_is_off');
expect(productionReadinessAudit.includes('Nakes_credential_policy')
	&& productionReadinessAudit.includes('must_change_password, password_changed_at')
	&& adminModel.includes('Nakes_credential_policy'), 'audit_and_admin_readiness_keep_raw_credential_state');
expect(loginController.includes('if ($must_change_password === 0)') && loginController.includes('save_location'), 'credential_remediation_login_does_not_write_location');
expect(adminController.includes('public function reset_personal_password')
	&& adminController.includes("$this->require_post()")
	&& adminController.includes("$policy->validate($password, $confirmation)"), 'personal_password_reset_is_post_only_and_uses_authoritative_policy');
expect(resetPersonalMethod.includes('FOR UPDATE')
	&& resetPersonalMethod.includes('trans_begin()')
	&& resetPersonalMethod.includes('trans_commit()')
	&& resetPersonalMethod.includes("'must_change_password' => 1")
	&& !resetPersonalMethod.includes("'password_changed_at' => null"), 'personal_password_reset_is_atomic_and_preserves_prior_change_evidence');
expect(resetPersonalMethod.includes('get_command_center_user_id')
	&& resetPersonalMethod.includes('account_linked_to_other_active_staff')
	&& resetPersonalMethod.includes('admin_reset_personal_nakes_password'), 'personal_password_reset_revalidates_identity_and_is_audited');
expect(adminView.includes('Gunakan password sementara unik untuk akun ini')
	&& adminView.includes("site_url('kelola_staff_puskesmas/reset_personal_password')")
	&& !adminView.includes('value="password"'), 'admin_reset_ui_never_replays_temporary_password');
expect(dashboard.includes('Nomor identitas dan data klinis tidak ditampilkan.') && !dashboard.includes('nomor_bpjs_kis'), 'puskesmas_dashboard_excludes_identity_values');

process.stdout.write(`ROLE_IDENTITY_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`ROLE_IDENTITY_SOURCE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
