#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
PHP81_BIN="${DOCLINC_PHP81_BIN:-/usr/bin/php8.1}"
DB_PASSWORD="${DOCLINC_READINESS_DB_PASSWORD:-}"

cleanup() {
	unset DB_PASSWORD DOCLINC_READINESS_DB_PASSWORD
}
trap cleanup EXIT

[[ -x "$PHP81_BIN" ]] || { echo 'SAFE_ERROR_CODE=php81_runtime_not_found' >&2; exit 1; }
[[ -f "$SCRIPT_DIR/audit.php" ]] || { echo 'SAFE_ERROR_CODE=readiness_audit_not_found' >&2; exit 1; }
if [[ -z "$DB_PASSWORD" ]]; then
	read -r -s -p 'Read-only readiness DB password: ' DB_PASSWORD
	echo
fi
[[ -n "$DB_PASSWORD" ]] || { echo 'SAFE_ERROR_CODE=database_password_missing' >&2; exit 1; }

export DOCLINC_READINESS_DB_HOST='127.0.0.1'
export DOCLINC_READINESS_DB_PORT='3306'
export DOCLINC_READINESS_DB_NAME='doclinc-staging'
export DOCLINC_READINESS_DB_USER='root'
export DOCLINC_READINESS_ALLOWED_USERS='root'
export DOCLINC_READINESS_DB_PASSWORD="$DB_PASSWORD"
export DOCLINC_READINESS_PROFILE_STORAGE='/home/doclinc-dev/private/profile-images'

ROLE_FLAG_REFERENCE_COUNT=0
ROLE_FLAG_TRUE_COUNT=0
while IFS= read -r POOL_FILE; do
	REFERENCES="$(grep -Ec '^[[:space:]]*env\[DOCLINC_ROLE_PREREQUISITES_ENABLED\][[:space:]]*=' "$POOL_FILE" || true)"
	TRUE_VALUES="$(grep -Eic "^[[:space:]]*env\[DOCLINC_ROLE_PREREQUISITES_ENABLED\][[:space:]]*=[[:space:]]*['\"]?(true|1|on|yes)['\"]?[[:space:]]*$" "$POOL_FILE" || true)"
	ROLE_FLAG_REFERENCE_COUNT=$((ROLE_FLAG_REFERENCE_COUNT + REFERENCES))
	ROLE_FLAG_TRUE_COUNT=$((ROLE_FLAG_TRUE_COUNT + TRUE_VALUES))
done < <(find /etc/php -type f -path '*/fpm/pool.d/*.conf' -print 2>/dev/null | sort)

echo "ROLE_PREREQUISITE_FLAG_REFERENCE_COUNT=$ROLE_FLAG_REFERENCE_COUNT"
echo "ROLE_PREREQUISITE_FLAG_TRUE_COUNT=$ROLE_FLAG_TRUE_COUNT"

set +e
"$PHP81_BIN" "$SCRIPT_DIR/audit.php"
AUDIT_EXIT=$?
set -e

echo "READINESS_AUDIT_EXIT=$AUDIT_EXIT"
if [[ "$AUDIT_EXIT" -eq 3 ]]; then
	if [[ "$ROLE_FLAG_TRUE_COUNT" -gt 0 ]]; then
		echo 'DOCLINC_PREACTIVATION_GATE=FAIL_FEATURE_ENABLED_WHILE_BLOCKED'
		exit 4
	fi
	echo 'DOCLINC_PREACTIVATION_GATE=BLOCKED_DATA'
	exit 3
fi
if [[ "$AUDIT_EXIT" -ne 0 ]]; then
	echo 'DOCLINC_PREACTIVATION_GATE=FAIL'
	exit "$AUDIT_EXIT"
fi
echo 'DOCLINC_PREACTIVATION_GATE=PASS'
