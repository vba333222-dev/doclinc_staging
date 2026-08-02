'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
let passed = 0;
let failed = 0;

function expect(condition, label) {
  if (condition) { passed += 1; process.stdout.write(`PASS ${label}\n`); return; }
  failed += 1; process.stdout.write(`FAIL ${label}\n`);
}
function read(relative) { return fs.readFileSync(path.join(root, relative), 'utf8'); }

const audit = read('tools/production_readiness/audit.php');
const report = read('tools/production_readiness/ProductionReadinessReport.php');
const resolver = read('tools/production_readiness/ReadinessIdentityResolver.php');
const runner = read('tools/production_readiness/run_staging_readiness.sh');
const roleService = read('application/libraries/Role_prerequisite_service.php');

expect(audit.includes("$database !== 'doclinc-staging'") && audit.includes('connected_database_mismatch'), 'database_target_is_exact_and_revalidated');
expect(audit.includes("START TRANSACTION READ ONLY") && audit.includes("$db->query('ROLLBACK')") && audit.includes('DATABASE_WRITE_EXECUTED=false'), 'database_session_is_read_only_and_rolled_back');
expect(audit.includes('new Role_prerequisite_service') && audit.includes("$service->evaluate((int) $actor->userId, true)"), 'audit_executes_production_prerequisite_service');
expect(audit.includes('Profile_image_storage') && audit.includes("$storage->allowed_mime($path) !== ''"), 'audit_validates_actual_profile_media');
expect(audit.includes('readiness_file_owner_ready') && report.includes('storage_owner_ready'), 'storage_and_photo_ownership_matches_application_runtime');
expect(audit.includes('information_schema.COLUMNS') && audit.includes('information_schema.STATISTICS'), 'identity_schema_and_indexes_verified');
expect(audit.includes("strtoupper(trim((string) $row['COLUMN_DEFAULT'])) === 'NULL'"), 'mariadb_null_default_metadata_is_normalized');
expect(audit.includes('readiness_operational_state') && audit.includes("where('status', 'aktif')->get('m_puskesmas')") && audit.includes("where('status', 'aktif')->get('puskesmas_staff')"), 'all_active_facilities_and_staff_are_counted_independently');
expect(audit.includes('$resolver->commandCenterReady($facility->kode_pkm)') && report.includes('facility_command_center_ready'), 'every_active_facility_requires_canonical_command_center');
expect(resolver.includes("account_type'] = 'command_center'") && resolver.includes("account_type'] = 'personal'") && resolver.includes("count($staff_rows) !== 1"), 'identity_projection_matches_canonical_roles');
expect(report.includes('SAFE_FIELDS') && report.includes('unknown_missing_field_count') && report.includes('safeCodes'), 'report_output_uses_safe_allowlists');
expect(!report.includes('missing_labels') && !audit.includes("select('nama") && !audit.includes("select('email"), 'report_does_not_project_identity_values');
expect(runner.includes("DOCLINC_READINESS_DB_HOST='127.0.0.1'") && runner.includes("DOCLINC_READINESS_DB_NAME='doclinc-staging'") && runner.includes("DOCLINC_READINESS_DB_USER='root'") && !runner.includes('DB_NAME:-'), 'runner_has_no_database_target_override');
expect(runner.includes('read -r -s -p') && runner.includes('unset DB_PASSWORD DOCLINC_READINESS_DB_PASSWORD'), 'runner_hides_and_cleans_password');
expect(runner.includes('BLOCKED_DATA') && audit.includes("$exit_code = $report['activation_ready'] ? 0 : 3") && audit.trim().endsWith('exit($exit_code);'), 'incomplete_data_has_distinct_blocked_exit_after_cleanup');
expect(runner.includes('ROLE_PREREQUISITE_FLAG_TRUE_COUNT') && runner.includes('FAIL_FEATURE_ENABLED_WHILE_BLOCKED'), 'blocked_data_rejects_premature_feature_activation');
expect(runner.includes("['\\\"]?(true|1|on|yes)['\\\"]?"), 'quoted_and_unquoted_true_feature_values_are_detected');
expect(roleService.includes("'nik', 'nomor_kk', 'nomor_bpjs_kis'") && roleService.includes("'nomor_sip', 'nip'"), 'gate_tracks_current_production_identity_contract');

process.stdout.write(`PRODUCTION_READINESS_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`PRODUCTION_READINESS_SOURCE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
