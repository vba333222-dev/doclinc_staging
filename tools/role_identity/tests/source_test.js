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
expect(adminView.includes('Data staf, NIP, SIP, status, dan hubungan akun hanya dikelola Administrator Dinas Kesehatan.'), 'admin_ownership_is_explicit');
expect(dashboard.includes('Nomor identitas dan data klinis tidak ditampilkan.') && !dashboard.includes('nomor_bpjs_kis'), 'puskesmas_dashboard_excludes_identity_values');

process.stdout.write(`ROLE_IDENTITY_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`ROLE_IDENTITY_SOURCE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
