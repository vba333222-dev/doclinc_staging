#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
REPO_ROOT="$(cd -- "$SCRIPT_DIR/../.." && pwd -P)"
MIGRATION="$REPO_ROOT/application/migrations/20260802000100_role_identity_foundation.php"
READINESS_RUNNER="$REPO_ROOT/tools/production_readiness/run_staging_readiness.sh"
DISPOSABLE_RUNNER="$REPO_ROOT/tools/role_identity/run_disposable_migration.sh"
PHP81_BIN='/usr/bin/php8.1'
DB_HOST='127.0.0.1'
DB_PORT='3306'
DB_NAME='doclinc-staging'
DB_USER='root'
BACKUP_ROOT='/root/.config/doclinc-runtime/backups/role-identity'

DB_PASSWORD=''
OPTION_FILE=''
BACKUP_TEMP=''

cleanup() {
	unset DB_PASSWORD DOCLINC_ROLE_IDENTITY_SCHEMA_DB_PASSWORD DOCLINC_READINESS_DB_PASSWORD
	unset DOCLINC_ROLE_IDENTITY_SCHEMA_WRITE_ENABLED DOCLINC_ROLE_IDENTITY_SCHEMA_ALLOWED_USERS
	unset DOCLINC_ROLE_IDENTITY_SCHEMA_DB_HOST DOCLINC_ROLE_IDENTITY_SCHEMA_DB_PORT DOCLINC_ROLE_IDENTITY_SCHEMA_DB_NAME DOCLINC_ROLE_IDENTITY_SCHEMA_DB_USER
	[[ -z "$OPTION_FILE" || ! -e "$OPTION_FILE" ]] || rm -f -- "$OPTION_FILE"
	[[ -z "$BACKUP_TEMP" || ! -e "$BACKUP_TEMP" ]] || rm -f -- "$BACKUP_TEMP"
}
trap cleanup EXIT

safe_fail() {
	echo 'DOCLINC_ROLE_IDENTITY_STAGING_MIGRATION=FAIL' >&2
	echo "SAFE_ERROR_CODE=$1" >&2
	exit 1
}

[[ "$EUID" -eq 0 ]] || safe_fail 'root_execution_required'
[[ -x "$PHP81_BIN" ]] || safe_fail 'php81_runtime_not_found'
[[ -f "$MIGRATION" ]] || safe_fail 'migration_not_found'
[[ -x "$READINESS_RUNNER" || -f "$READINESS_RUNNER" ]] || safe_fail 'readiness_runner_not_found'
[[ -x "$DISPOSABLE_RUNNER" || -f "$DISPOSABLE_RUNNER" ]] || safe_fail 'disposable_runner_not_found'
for command_name in mariadb mariadb-dump sha256sum grep awk mktemp; do
	command -v "$command_name" >/dev/null 2>&1 || safe_fail "${command_name//-/_}_not_found"
done

PLAN_OUTPUT="$($PHP81_BIN "$MIGRATION")" || safe_fail 'migration_plan_failed'
printf '%s\n' "$PLAN_OUTPUT"
grep -qx 'EXECUTION_MODE=PLAN' <<<"$PLAN_OUTPUT" || safe_fail 'migration_plan_contract_mismatch'
grep -qx 'PLANNED_COLUMN_COUNT=4' <<<"$PLAN_OUTPUT" || safe_fail 'migration_plan_contract_mismatch'
grep -qx 'PLANNED_UNIQUE_INDEX_COUNT=3' <<<"$PLAN_OUTPUT" || safe_fail 'migration_plan_contract_mismatch'

ROLE_FLAG_TRUE_COUNT=0
while IFS= read -r POOL_FILE; do
	TRUE_VALUES="$(grep -Eic "^[[:space:]]*env\[DOCLINC_ROLE_PREREQUISITES_ENABLED\][[:space:]]*=[[:space:]]*['\"]?(true|1|on|yes)['\"]?[[:space:]]*$" "$POOL_FILE" || true)"
	ROLE_FLAG_TRUE_COUNT=$((ROLE_FLAG_TRUE_COUNT + TRUE_VALUES))
done < <(find /etc/php -type f -path '*/fpm/pool.d/*.conf' -print 2>/dev/null | sort)
echo "ROLE_PREREQUISITE_FLAG_TRUE_COUNT=$ROLE_FLAG_TRUE_COUNT"
[[ "$ROLE_FLAG_TRUE_COUNT" -eq 0 ]] || safe_fail 'role_prerequisite_feature_must_be_off'

IFS= read -r -s -p 'Staging database master password: ' DB_PASSWORD
echo
[[ -n "$DB_PASSWORD" ]] || safe_fail 'database_password_missing'

OPTION_FILE="$(mktemp)"
ESCAPED_PASSWORD="${DB_PASSWORD//\\/\\\\}"
ESCAPED_PASSWORD="${ESCAPED_PASSWORD//\"/\\\"}"
{
	printf '%s\n' '[client]'
	printf 'host=%s\n' "$DB_HOST"
	printf 'port=%s\n' "$DB_PORT"
	printf 'user=%s\n' "$DB_USER"
	printf 'password="%s"\n' "$ESCAPED_PASSWORD"
	printf '%s\n' 'protocol=tcp'
} >"$OPTION_FILE"
chmod 600 "$OPTION_FILE"
unset ESCAPED_PASSWORD

ACTUAL_DATABASE="$(mariadb --defaults-extra-file="$OPTION_FILE" --batch --skip-column-names "$DB_NAME" -e 'SELECT DATABASE()')" \
	|| safe_fail 'database_connection_failed'
[[ "$ACTUAL_DATABASE" == "$DB_NAME" ]] || safe_fail 'connected_database_mismatch'

DOCLINC_TEST_DB_ADMIN_PASSWORD="$DB_PASSWORD" bash "$DISPOSABLE_RUNNER" \
	|| safe_fail 'disposable_migration_gate_failed'
echo 'DISPOSABLE_MIGRATION_GATE=PASS'

BASE_TABLE_COUNT="$(mariadb --defaults-extra-file="$OPTION_FILE" --batch --skip-column-names "$DB_NAME" \
	-e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('users','puskesmas_staff')")" \
	|| safe_fail 'base_schema_check_failed'
[[ "$BASE_TABLE_COUNT" == '2' ]] || safe_fail 'base_schema_mismatch'

USERS_BEFORE="$(mariadb --defaults-extra-file="$OPTION_FILE" --batch --skip-column-names "$DB_NAME" -e 'SELECT COUNT(*) FROM users')" \
	|| safe_fail 'users_count_failed'
STAFF_BEFORE="$(mariadb --defaults-extra-file="$OPTION_FILE" --batch --skip-column-names "$DB_NAME" -e 'SELECT COUNT(*) FROM puskesmas_staff')" \
	|| safe_fail 'staff_count_failed'

mkdir -p -- "$BACKUP_ROOT"
chmod 700 "$BACKUP_ROOT"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_FILE="$BACKUP_ROOT/doclinc-staging-before-role-identity-$TIMESTAMP.sql"
BACKUP_TEMP="$BACKUP_FILE.tmp"
[[ ! -e "$BACKUP_FILE" && ! -e "$BACKUP_TEMP" ]] || safe_fail 'backup_path_collision'

mariadb-dump --defaults-extra-file="$OPTION_FILE" \
	--single-transaction --quick --routines --events --triggers --hex-blob \
	--default-character-set=utf8mb4 --databases "$DB_NAME" >"$BACKUP_TEMP" \
	|| safe_fail 'database_backup_failed'
[[ -s "$BACKUP_TEMP" ]] || safe_fail 'database_backup_empty'
grep -Fq 'CREATE TABLE `users`' "$BACKUP_TEMP" || safe_fail 'database_backup_users_missing'
grep -Fq 'CREATE TABLE `puskesmas_staff`' "$BACKUP_TEMP" || safe_fail 'database_backup_staff_missing'
mv -- "$BACKUP_TEMP" "$BACKUP_FILE"
BACKUP_TEMP=''
chmod 600 "$BACKUP_FILE"
BACKUP_SHA256="$(sha256sum "$BACKUP_FILE" | awk '{print $1}')"
[[ "$BACKUP_SHA256" =~ ^[a-f0-9]{64}$ ]] || safe_fail 'database_backup_checksum_failed'
[[ "$(sha256sum "$BACKUP_FILE" | awk '{print $1}')" == "$BACKUP_SHA256" ]] || safe_fail 'database_backup_checksum_mismatch'
echo "BACKUP_FILE=$BACKUP_FILE"
echo "BACKUP_SHA256=$BACKUP_SHA256"
echo 'BACKUP_VALIDATED=true'

export DOCLINC_ROLE_IDENTITY_SCHEMA_WRITE_ENABLED='true'
export DOCLINC_ROLE_IDENTITY_SCHEMA_DB_HOST="$DB_HOST"
export DOCLINC_ROLE_IDENTITY_SCHEMA_DB_PORT="$DB_PORT"
export DOCLINC_ROLE_IDENTITY_SCHEMA_DB_NAME="$DB_NAME"
export DOCLINC_ROLE_IDENTITY_SCHEMA_DB_USER="$DB_USER"
export DOCLINC_ROLE_IDENTITY_SCHEMA_ALLOWED_USERS="$DB_USER"
export DOCLINC_ROLE_IDENTITY_SCHEMA_DB_PASSWORD="$DB_PASSWORD"

set +e
"$PHP81_BIN" "$MIGRATION" \
	--apply \
	--environment=staging \
	--confirm-database="$DB_NAME" \
	--backup-reference="$BACKUP_FILE" \
	--confirm-backup-sha256="$BACKUP_SHA256"
MIGRATION_EXIT=$?
set -e

unset DOCLINC_ROLE_IDENTITY_SCHEMA_WRITE_ENABLED DOCLINC_ROLE_IDENTITY_SCHEMA_DB_PASSWORD
echo "MIGRATION_EXIT=$MIGRATION_EXIT"
if [[ "$MIGRATION_EXIT" -ne 0 ]]; then
	echo 'AUTOMATIC_RESTORE_EXECUTED=false'
	echo 'MANUAL_RECOVERY_REVIEW_REQUIRED=true'
	safe_fail 'migration_apply_failed'
fi

USERS_AFTER="$(mariadb --defaults-extra-file="$OPTION_FILE" --batch --skip-column-names "$DB_NAME" -e 'SELECT COUNT(*) FROM users')" \
	|| safe_fail 'users_post_count_failed'
STAFF_AFTER="$(mariadb --defaults-extra-file="$OPTION_FILE" --batch --skip-column-names "$DB_NAME" -e 'SELECT COUNT(*) FROM puskesmas_staff')" \
	|| safe_fail 'staff_post_count_failed'
[[ "$USERS_AFTER" == "$USERS_BEFORE" && "$STAFF_AFTER" == "$STAFF_BEFORE" ]] || safe_fail 'base_row_count_changed'
echo 'BASE_ROW_COUNTS_UNCHANGED=true'

export DOCLINC_READINESS_DB_PASSWORD="$DB_PASSWORD"
set +e
READINESS_OUTPUT="$(bash "$READINESS_RUNNER" 2>&1)"
READINESS_EXIT=$?
set -e
printf '%s\n' "$READINESS_OUTPUT"
unset DOCLINC_READINESS_DB_PASSWORD

grep -qx 'PRODUCTION_READINESS_AUDIT=PASS' <<<"$READINESS_OUTPUT" || safe_fail 'post_migration_readiness_failed'
grep -qx 'SCHEMA_GAP_COUNT=0' <<<"$READINESS_OUTPUT" || safe_fail 'post_migration_schema_not_ready'
if [[ "$READINESS_EXIT" -eq 3 ]]; then
	echo 'DOCLINC_ROLE_IDENTITY_STAGING_MIGRATION=PASS_READINESS_BLOCKED_DATA'
	echo 'FEATURE_FLAG_CHANGED=false'
	echo 'AUTOMATIC_RESTORE_EXECUTED=false'
	exit 3
fi
[[ "$READINESS_EXIT" -eq 0 ]] || safe_fail 'post_migration_readiness_failed'
echo 'DOCLINC_ROLE_IDENTITY_STAGING_MIGRATION=PASS_READY_FOR_CONTROLLED_ACTIVATION'
echo 'FEATURE_FLAG_CHANGED=false'
echo 'AUTOMATIC_RESTORE_EXECUTED=false'
