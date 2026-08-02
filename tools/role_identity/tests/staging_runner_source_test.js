'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
const runner = fs.readFileSync(path.join(root, 'tools/role_identity/run_staging_migration.sh'), 'utf8');
const disposableRunner = fs.readFileSync(path.join(root, 'tools/role_identity/run_disposable_migration.sh'), 'utf8');
const integration = fs.readFileSync(path.join(root, 'tools/role_identity/tests/migration_integration.php'), 'utf8');
let passed = 0;
let failed = 0;

function expect(condition, label) {
  if (condition) { passed += 1; process.stdout.write(`PASS ${label}\n`); return; }
  failed += 1; process.stdout.write(`FAIL ${label}\n`);
}

expect(runner.includes("DB_HOST='127.0.0.1'") && runner.includes("DB_PORT='3306'") && runner.includes("DB_NAME='doclinc-staging'") && runner.includes("DB_USER='root'"), 'staging_database_target_is_fixed');
expect(!runner.includes('DB_NAME:-') && !runner.includes('DB_HOST:-') && !runner.includes('DB_USER:-'), 'database_target_has_no_environment_override');
expect(runner.includes('[[ "$EUID" -eq 0 ]]') && runner.includes("root_execution_required"), 'root_execution_is_explicit');
expect(runner.includes("IFS= read -r -s -p 'Staging database master password: '") && runner.includes('unset DB_PASSWORD'), 'password_prompt_is_hidden_and_cleaned');
expect(runner.includes('OPTION_FILE="$(mktemp)"') && runner.includes('chmod 600 "$OPTION_FILE"') && runner.includes('rm -f -- "$OPTION_FILE"'), 'temporary_client_credentials_are_private_and_cleaned');
expect(runner.includes('ROLE_PREREQUISITE_FLAG_TRUE_COUNT') && runner.includes('role_prerequisite_feature_must_be_off'), 'migration_refuses_active_prerequisite_gate');
expect(runner.includes('run_disposable_migration.sh') && runner.indexOf('DISPOSABLE_MIGRATION_GATE=PASS') < runner.indexOf('mariadb-dump --defaults-extra-file='), 'disposable_migration_gate_precedes_staging_backup');
expect(runner.indexOf('mariadb-dump --defaults-extra-file=') < runner.indexOf("export DOCLINC_ROLE_IDENTITY_SCHEMA_WRITE_ENABLED='true'"), 'full_backup_precedes_schema_write_enable');
expect(runner.includes("grep -Fq 'CREATE TABLE `users`'") && runner.includes("grep -Fq 'CREATE TABLE `puskesmas_staff`'"), 'backup_contains_both_migration_targets');
expect(runner.includes('BACKUP_SHA256="$(sha256sum') && runner.includes('--confirm-backup-sha256="$BACKUP_SHA256"'), 'backup_checksum_is_independently_confirmed');
expect(runner.includes('--environment=staging') && runner.includes('--confirm-database="$DB_NAME"') && runner.includes('--backup-reference="$BACKUP_FILE"'), 'migration_receives_all_staging_confirmations');
expect(runner.includes('USERS_BEFORE=') && runner.includes('USERS_AFTER=') && runner.includes('BASE_ROW_COUNTS_UNCHANGED=true'), 'base_row_counts_are_preserved');
expect(runner.includes('SCHEMA_GAP_COUNT=0') && runner.includes('PASS_READINESS_BLOCKED_DATA'), 'post_migration_readiness_distinguishes_data_blocker');
expect(runner.includes('AUTOMATIC_RESTORE_EXECUTED=false') && !runner.includes('mariadb <') && !runner.includes('mysql <'), 'failed_ddl_never_triggers_automatic_restore');
expect(!runner.includes('systemctl') && !runner.includes('nginx -s') && !runner.includes('service '), 'runner_does_not_reload_runtime_services');
expect(disposableRunner.includes("DOCLINC_TEST_DB_ADMIN_HOST='127.0.0.1'") && disposableRunner.includes("DOCLINC_TEST_DB_ADMIN_USER='root'"), 'disposable_runner_uses_local_admin_target');
expect(integration.includes('doclinc_role_identity_test_') && integration.includes("DROP DATABASE IF EXISTS") && integration.includes('DISPOSABLE_DATABASES_REMAINING='), 'integration_uses_disposable_databases_with_verified_cleanup');
expect(integration.includes('actual_migration_apply_passes') && integration.includes('migration_rerun_is_idempotent'), 'integration_executes_apply_and_idempotency_paths');
expect(integration.includes('existing_rows_preserved_exactly') && integration.includes('new_identity_fields_are_nullable_without_backfill'), 'integration_proves_existing_row_preservation');
expect(integration.includes('invalid_backup_checksum_rejected') && integration.includes('partial_schema_fails_closed'), 'integration_covers_checksum_and_partial_schema_denials');

process.stdout.write(`ROLE_IDENTITY_STAGING_RUNNER_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`ROLE_IDENTITY_STAGING_RUNNER_SOURCE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
