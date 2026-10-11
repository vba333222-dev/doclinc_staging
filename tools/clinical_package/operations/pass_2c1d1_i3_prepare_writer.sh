#!/usr/bin/env bash

set -Eeuo pipefail
umask 077
export LC_ALL=C
export LANG=C
export HISTFILE=/dev/null

readonly WRITER_DATABASE='doclinc-staging'
readonly RUNTIME_USER='doclinc-staging-user'
readonly MIGRATOR_USER='doclink_clinical_migrator'

phase='bootstrap'
safe_error_code='unexpected_writer_preparation_failure'
failure_emitted=false
readonly root_bashpid="$BASHPID"
temporary_directory=''
writer_password=''
sql_statement=''

cleanup() {
	[[ "$BASHPID" == "$root_bashpid" ]] || return 0
	writer_password=''
	sql_statement=''
	unset writer_password sql_statement
	if [[ -n "${temporary_directory:-}" && -d "$temporary_directory" ]]; then
		[[ ! -f "$temporary_directory/grants.actual" ]] || rm -f -- "$temporary_directory/grants.actual"
		[[ ! -f "$temporary_directory/grants.expected" ]] || rm -f -- "$temporary_directory/grants.expected"
		rmdir -- "$temporary_directory" 2>/dev/null || true
	fi
}

emit_failure() {
	if [[ "$failure_emitted" != true ]]; then
		printf 'PASS_2C1D1_I3_WRITER_PREPARATION=FAIL\n'
		printf 'FAILED_PHASE=%s\n' "$phase"
		printf 'SAFE_ERROR_CODE=%s\n' "${1:-unexpected_writer_preparation_failure}"
		failure_emitted=true
	fi
}

on_error() {
	local status="${1:-1}"
	emit_failure "$safe_error_code"
	exit "$status"
}

fail() {
	phase="$1"
	emit_failure "$2"
	exit 1
}

trap 'on_error "$?"' ERR
trap cleanup EXIT

usage() {
	printf '%s\n' 'Audit only (default):'
	printf '%s\n' '  pass_2c1d1_i3_prepare_writer.sh --admin-defaults-file=/root/secure.cnf --writer-user=NAME --writer-host=HOST'
	printf '%s\n' 'Provision missing account/grants:'
	printf '%s\n' '  ... --provision --confirm-provision=PROVISION:NAME@HOST:doclinc-staging'
	printf '%s\n' 'Optional explicit reset of excessive direct grants:'
	printf '%s\n' '  ... --provision --revoke-excessive --confirm-revoke=REVOKE:NAME@HOST:doclinc-staging'
}

mysql_query() {
	local sql="$1"
	"$MYSQL_BINARY" --defaults-extra-file="$admin_defaults_file" --batch --skip-column-names --raw --execute="$sql" 2>/dev/null
}

expected_grants() {
	printf 'global\t-\t-\t-\tUSAGE\tNO\n'
	local table privilege
	for table in \
		clinical_master_packages \
		clinical_master_package_capabilities \
		clinical_master_datasets \
		clinical_master_dataset_capabilities \
		clinical_master_dataset_field_contracts; do
		for privilege in INSERT SELECT; do
			printf 'table\t%s\t%s\t-\t%s\tNO\n' "$WRITER_DATABASE" "$table" "$privilege"
		done
	done
	printf 'table\t%s\tclinical_master_metadata_registration_events\t-\tINSERT\tNO\n' "$WRITER_DATABASE"
}

collect_grants() {
	local destination="$1"
	local grantee="CONCAT(CHAR(39),'$writer_user',CHAR(39),'@',CHAR(39),'$writer_host',CHAR(39))"
	local query
	query="SELECT privilege_surface,COALESCE(table_schema,'-'),COALESCE(table_name,'-'),COALESCE(column_name,'-'),privilege_type,is_grantable FROM (SELECT 'global' privilege_surface,NULL table_schema,NULL table_name,NULL column_name,privilege_type,is_grantable FROM information_schema.USER_PRIVILEGES WHERE grantee=$grantee UNION ALL SELECT 'schema',table_schema,NULL,NULL,privilege_type,is_grantable FROM information_schema.SCHEMA_PRIVILEGES WHERE grantee=$grantee UNION ALL SELECT 'table',table_schema,table_name,NULL,privilege_type,is_grantable FROM information_schema.TABLE_PRIVILEGES WHERE grantee=$grantee UNION ALL SELECT 'column',table_schema,table_name,column_name,privilege_type,is_grantable FROM information_schema.COLUMN_PRIVILEGES WHERE grantee=$grantee) p"
	mysql_query "$query" >"$destination" || fail audit privilege_inventory_query_failed
	sort "$destination" -o "$destination"
}

print_redacted_grants() {
	local grants
	grants="$(mysql_query "SHOW GRANTS FOR '$writer_user'@'$writer_host'")" || fail audit show_grants_failed
	printf '%s\n' "$grants" | sed -E "s/(IDENTIFIED BY PASSWORD ')[^']+(')/\1[REDACTED]\2/g; s/(IDENTIFIED BY ')[^']+(')/\1[REDACTED]\2/g; s/( USING ')[^']+(')/\1[REDACTED]\2/g"
}

verify_account_and_grants() {
	local account_count role_count identity
	account_count="$(mysql_query "SELECT COUNT(*) FROM mysql.user WHERE User='$writer_user' AND Host='$writer_host'")" || fail audit account_lookup_failed
	[[ "$account_count" == 1 ]] || fail audit writer_account_missing
	role_count="$(mysql_query "SELECT COUNT(*) FROM information_schema.APPLICABLE_ROLES WHERE grantee='$writer_user@$writer_host'")" || fail audit role_inventory_query_failed
	[[ "$role_count" == 0 ]] || fail audit writer_role_rejected
	collect_grants "$temporary_directory/grants.actual"
	expected_grants | sort >"$temporary_directory/grants.expected"
	cmp -s "$temporary_directory/grants.actual" "$temporary_directory/grants.expected" || fail audit excessive_or_incomplete_writer_grants
	identity="$(mysql_query "SELECT COUNT(*) FROM mysql.user WHERE User IN ('$RUNTIME_USER','$MIGRATOR_USER') AND User='$writer_user'")" || fail audit identity_separation_query_failed
	[[ "$identity" == 0 ]] || fail audit writer_identity_not_dedicated
	printf 'WRITER_ACCOUNT_EXISTS=true\n'
	printf 'WRITER_IDENTITY_DEDICATED=true\n'
	printf 'WRITER_ROLES_ASSIGNED=0\n'
	printf 'WRITER_GRANTS_BEGIN\n'
	print_redacted_grants
	printf 'WRITER_GRANTS_END\n'
	printf 'WRITER_GRANTS_READY=true\n'
}

provision_account() {
	local account_count first_password second_password
	local confirm_expected="PROVISION:$writer_user@$writer_host:$WRITER_DATABASE"
	[[ "$confirm_provision" == "$confirm_expected" ]] || fail provision provision_confirmation_mismatch
	account_count="$(mysql_query "SELECT COUNT(*) FROM mysql.user WHERE User='$writer_user' AND Host='$writer_host'")" || fail provision account_lookup_failed
	if [[ "$account_count" == 0 ]]; then
		if [[ -n "$writer_password_file" ]]; then
			[[ -f "$writer_password_file" && ! -L "$writer_password_file" ]] || fail provision writer_password_file_unavailable
			[[ "$(stat -c '%U:%a' -- "$writer_password_file")" == 'root:600' ]] || fail provision writer_password_file_not_root_only
			mapfile -t password_lines <"$writer_password_file"
			[[ "${#password_lines[@]}" -eq 1 ]] || fail provision writer_password_invalid
			writer_password="${password_lines[0]}"
		else
			read -r -s -p 'New metadata writer password: ' first_password </dev/tty
			printf '\n' >/dev/tty
			read -r -s -p 'Repeat metadata writer password: ' second_password </dev/tty
			printf '\n' >/dev/tty
			[[ "$first_password" == "$second_password" ]] || fail provision writer_password_confirmation_mismatch
			writer_password="$first_password"
			first_password=''
			second_password=''
		fi
		[[ "$writer_password" =~ ^[A-Za-z0-9!@%+_=.,:?^-]{24,256}$ ]] || fail provision writer_password_invalid
		printf -v sql_statement "CREATE USER '%s'@'%s' IDENTIFIED BY '%s';" "$writer_user" "$writer_host" "$writer_password"
		printf '%s\n' "$sql_statement" | "$MYSQL_BINARY" --defaults-extra-file="$admin_defaults_file" --batch --skip-column-names >/dev/null 2>&1 || fail provision create_writer_failed
		writer_password=''
		sql_statement=''
		unset writer_password sql_statement password_lines
	elif [[ "$account_count" != 1 ]]; then
		fail provision writer_account_ambiguous
	fi

	if [[ "$revoke_excessive" == true ]]; then
		[[ "$confirm_revoke" == "REVOKE:$writer_user@$writer_host:$WRITER_DATABASE" ]] || fail provision revoke_confirmation_mismatch
		local role_count
		role_count="$(mysql_query "SELECT COUNT(*) FROM information_schema.APPLICABLE_ROLES WHERE grantee='$writer_user@$writer_host'")" || fail provision role_inventory_query_failed
		[[ "$role_count" == 0 ]] || fail provision writer_role_requires_manual_review
		mysql_query "REVOKE ALL PRIVILEGES, GRANT OPTION FROM '$writer_user'@'$writer_host'" >/dev/null || fail provision revoke_excessive_failed
	fi

	sql_statement="GRANT SELECT, INSERT ON \`$WRITER_DATABASE\`.\`clinical_master_packages\` TO '$writer_user'@'$writer_host';
GRANT SELECT, INSERT ON \`$WRITER_DATABASE\`.\`clinical_master_package_capabilities\` TO '$writer_user'@'$writer_host';
GRANT SELECT, INSERT ON \`$WRITER_DATABASE\`.\`clinical_master_datasets\` TO '$writer_user'@'$writer_host';
GRANT SELECT, INSERT ON \`$WRITER_DATABASE\`.\`clinical_master_dataset_capabilities\` TO '$writer_user'@'$writer_host';
GRANT SELECT, INSERT ON \`$WRITER_DATABASE\`.\`clinical_master_dataset_field_contracts\` TO '$writer_user'@'$writer_host';
GRANT INSERT ON \`$WRITER_DATABASE\`.\`clinical_master_metadata_registration_events\` TO '$writer_user'@'$writer_host';"
	printf '%s\n' "$sql_statement" | "$MYSQL_BINARY" --defaults-extra-file="$admin_defaults_file" --batch --skip-column-names >/dev/null 2>&1 || fail provision grant_writer_failed
	sql_statement=''
	unset sql_statement
}

admin_defaults_requested=''
writer_user=''
writer_host=''
writer_password_file=''
provision=false
revoke_excessive=false
confirm_provision=''
confirm_revoke=''

for argument in "$@"; do
	case "$argument" in
		--admin-defaults-file=*) admin_defaults_requested="${argument#*=}" ;;
		--writer-user=*) writer_user="${argument#*=}" ;;
		--writer-host=*) writer_host="${argument#*=}" ;;
		--writer-password-file=*) writer_password_file="${argument#*=}" ;;
		--confirm-provision=*) confirm_provision="${argument#*=}" ;;
		--confirm-revoke=*) confirm_revoke="${argument#*=}" ;;
		--provision) provision=true ;;
		--revoke-excessive) revoke_excessive=true ;;
		*) usage; fail bootstrap unknown_argument ;;
	esac
done

[[ -n "$admin_defaults_requested" && -n "$writer_user" && -n "$writer_host" ]] || { usage; fail bootstrap required_argument_missing; }
[[ "$writer_user" =~ ^[a-z][a-z0-9_-]{2,63}$ ]] || fail bootstrap writer_user_invalid
[[ "$writer_host" =~ ^[A-Za-z0-9_.:-]{1,255}$ ]] || fail bootstrap writer_host_invalid
[[ "$writer_user" != "$RUNTIME_USER" && "$writer_user" != "$MIGRATOR_USER" ]] || fail bootstrap writer_identity_not_dedicated
[[ "$revoke_excessive" != true || "$provision" == true ]] || fail bootstrap revoke_requires_provision

[[ -f "$admin_defaults_requested" && ! -L "$admin_defaults_requested" ]] || fail bootstrap admin_defaults_unavailable
admin_defaults_file="$(realpath -e -- "$admin_defaults_requested")"
[[ "$(realpath -s -- "$admin_defaults_requested")" == "$admin_defaults_file" ]] || fail bootstrap admin_defaults_symlink_rejected
[[ "$(stat -c '%U:%a' -- "$admin_defaults_file")" == 'root:600' ]] || fail bootstrap admin_defaults_not_root_only

for command in realpath stat sort cmp sed mktemp chmod rm rmdir; do
	command -v "$command" >/dev/null 2>&1 || fail bootstrap required_command_missing
done
if command -v mariadb >/dev/null 2>&1; then MYSQL_BINARY='mariadb';
elif command -v mysql >/dev/null 2>&1; then MYSQL_BINARY='mysql';
else fail bootstrap mysql_client_unavailable; fi

temporary_directory="$(mktemp -d /var/tmp/doclink-writer-preparation.XXXXXXXXXX)"
chmod 700 "$temporary_directory"

phase='audit'
account_exists="$(mysql_query "SELECT COUNT(*) FROM mysql.user WHERE User='$writer_user' AND Host='$writer_host'")" || fail audit account_lookup_failed
printf 'MODE=%s\n' "$([[ "$provision" == true ]] && printf provision || printf audit-only)"
printf 'WRITER_ACCOUNT=%s@%s\n' "$writer_user" "$writer_host"
printf 'TARGET_DATABASE=%s\n' "$WRITER_DATABASE"
printf 'WRITER_ACCOUNT_EXISTS_BEFORE=%s\n' "$([[ "$account_exists" == 1 ]] && printf true || printf false)"

if [[ "$provision" == true ]]; then
	phase='provision'
	provision_account
fi

phase='audit'
verify_account_and_grants
printf 'RUNTIME_USER_CHANGED=false\n'
printf 'MIGRATOR_USER_CHANGED=false\n'
printf 'SCHEMA_OR_DATA_CHANGED=false\n'
printf 'PASS_2C1D1_I3_WRITER_PREPARATION=PASS\n'
