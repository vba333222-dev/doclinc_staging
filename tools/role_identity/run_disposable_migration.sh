#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
PHP81_BIN='/usr/bin/php8.1'
TEST_FILE="$SCRIPT_DIR/tests/migration_integration.php"
DB_PASSWORD="${DOCLINC_TEST_DB_ADMIN_PASSWORD:-}"

cleanup() {
	unset DB_PASSWORD DOCLINC_TEST_DB_ADMIN_PASSWORD
}
trap cleanup EXIT

[[ -x "$PHP81_BIN" ]] || { echo 'SAFE_ERROR_CODE=php81_runtime_not_found' >&2; exit 1; }
[[ -f "$TEST_FILE" ]] || { echo 'SAFE_ERROR_CODE=migration_integration_not_found' >&2; exit 1; }
if [[ -z "$DB_PASSWORD" ]]; then
	IFS= read -r -s -p 'Disposable MariaDB admin password: ' DB_PASSWORD
	echo
fi
[[ -n "$DB_PASSWORD" ]] || { echo 'SAFE_ERROR_CODE=disposable_database_password_missing' >&2; exit 1; }

export DOCLINC_TEST_DB_ADMIN_HOST='127.0.0.1'
export DOCLINC_TEST_DB_ADMIN_PORT='3306'
export DOCLINC_TEST_DB_ADMIN_USER='root'
export DOCLINC_TEST_DB_ADMIN_PASSWORD="$DB_PASSWORD"

"$PHP81_BIN" "$TEST_FILE"
