#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
TEST_FILE="$SCRIPT_DIR/tests/integration.php"

PHP81_BIN="${DOCLINC_PHP81_BIN:-}"
if [[ -z "$PHP81_BIN" ]]; then
	if command -v php8.1 >/dev/null 2>&1; then
		PHP81_BIN="$(command -v php8.1)"
	elif [[ -x /usr/bin/php8.1 ]]; then
		PHP81_BIN='/usr/bin/php8.1'
	else
		echo 'SAFE_ERROR_CODE=php81_runtime_not_found' >&2
		exit 1
	fi
fi

if [[ ! -x "$PHP81_BIN" || ! -f "$TEST_FILE" ]]; then
	echo 'SAFE_ERROR_CODE=integration_runtime_unavailable' >&2
	exit 1
fi

DB_PASSWORD="${DOCLINC_TEST_DB_ADMIN_PASSWORD:-}"
cleanup() {
	unset DB_PASSWORD DOCLINC_TEST_DB_ADMIN_PASSWORD
}
trap cleanup EXIT

if [[ -z "$DB_PASSWORD" ]]; then
	read -r -s -p 'Disposable MariaDB admin password: ' DB_PASSWORD
	echo
fi
if [[ -z "$DB_PASSWORD" ]]; then
	echo 'SAFE_ERROR_CODE=disposable_database_password_missing' >&2
	exit 1
fi

export DOCLINC_TEST_DB_ADMIN_HOST="${DOCLINC_TEST_DB_ADMIN_HOST:-127.0.0.1}"
export DOCLINC_TEST_DB_ADMIN_PORT="${DOCLINC_TEST_DB_ADMIN_PORT:-3306}"
export DOCLINC_TEST_DB_ADMIN_USER="${DOCLINC_TEST_DB_ADMIN_USER:-root}"
export DOCLINC_TEST_DB_ADMIN_PASSWORD="$DB_PASSWORD"

"$PHP81_BIN" "$TEST_FILE"
