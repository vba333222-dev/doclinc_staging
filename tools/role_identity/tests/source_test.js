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
expect(adminModel.includes('function readiness_issues') && adminModel.includes("'staff_registration_number'") && adminModel.includes("'staff_nip'"), 'staff_readiness_uses_safe_action_codes');
expect(adminModel.includes('staff_user.must_change_password AS akun_must_change_password')
  && adminModel.includes("'staff_password_change_required'")
  && adminView.includes('Menunggu Nakes mengganti password sementara. Fitur lain tetap terkunci.'), 'admin_queue_exposes_password_change_waiting_state');
expect(adminController.includes("'readiness' =>") && adminController.includes('staff_matches_readiness_filter') && adminController.includes("'missing_sip'") && adminController.includes("'missing_nip'"), 'admin_staff_queue_has_bounded_server_side_filters');
expect(adminController.includes("$filter !== '' && (string) ($staff->status ?? '') !== 'aktif'"), 'inactive_staff_excluded_from_readiness_filter');
expect(adminView.includes('staffFilterReadiness') && adminView.includes('Kesiapan staf aktif') && adminView.includes('Relasi akun personal perlu diperiksa'), 'admin_staff_view_exposes_actionable_readiness_without_raw_identity_export');
expect(adminView.includes('Gunakan data resmi; jangan mengisi nomor buatan.'), 'admin_staff_form_forbids_fabricated_nip');
expect(adminView.includes('Data staf, NIP, SIP, status, dan hubungan akun hanya dikelola Administrator Dinas Kesehatan.'), 'admin_ownership_is_explicit');
expect(adminModel.includes("'must_change_password' => 1") && adminModel.includes("'password_changed_at' => null")
  && adminModel.includes("'must_change_password', 'password_changed_at'"), 'personal_account_creation_forces_first_login_password_change');
expect(adminController.includes('Password_strength_policy') && adminModel.includes('Password_strength_policy')
  && adminView.includes('wajib membuat password baru saat login pertama')
  && (adminView.match(/minlength="8" maxlength="72"/g) || []).length === 2, 'personal_temporary_password_contract_is_explicit');
expect(commandCenterModel.includes("'must_change_password' => 1") && commandCenterModel.includes("'password_changed_at' => null")
  && commandCenterModel.includes("if (isset($allowed['password']))"), 'command_center_create_and_reset_force_password_change');
expect(commandCenterController.includes('required|min_length[8]|max_length[72]')
  && commandCenterController.includes('Password_strength_policy')
  && (commandCenterView.match(/minlength="8" maxlength="72"/g) || []).length === 4
  && commandCenterView.includes('Menunggu pengelola Puskesmas mengganti password sementara. Fitur lain tetap terkunci.'), 'command_center_temporary_password_is_bounded');
expect(publicPasswordPolicy.includes('strlen($password) < 8') && adminPasswordPolicy.includes('strlen($password) < 8')
  && publicPasswordPolicy.includes("preg_match('/[A-Z]/'")
  && publicPasswordPolicy.includes("preg_match('/[a-z]/'")
  && publicPasswordPolicy.includes("preg_match('/[0-9]/'")
  && publicPasswordPolicy.includes("preg_match('/[^A-Za-z0-9\\s]/'"), 'all_password_paths_share_minimum_complexity_contract');
expect(dashboard.includes('Nomor identitas dan data klinis tidak ditampilkan.') && !dashboard.includes('nomor_bpjs_kis'), 'puskesmas_dashboard_excludes_identity_values');

process.stdout.write(`ROLE_IDENTITY_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`ROLE_IDENTITY_SOURCE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
