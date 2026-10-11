#!/usr/bin/env bash

set -Eeuo pipefail
umask 077
export LC_ALL=C
export LANG=C

readonly I3_EXPECTED_OS_USER='doclinc-dev'
readonly I3_EXPECTED_REPOSITORY='/home/doclinc-dev/htdocs/staging.doclinc.local'
readonly I3_EXPECTED_BRANCH='fix/config-session'
readonly I3_EXPECTED_COMMIT='17c6b2b05e478afc742f9cb8e71b347404c3d28c'
readonly I3_DATABASE='doclinc-staging'
readonly I3_RUNTIME_USER='doclinc-staging-user'
readonly I3_MIGRATOR_USER='doclink_clinical_migrator'
readonly I3_PACKAGE_KEY='doclink-clinical-master'
readonly I3_PACKAGE_VERSION='v1'
readonly I3_MANIFEST_CHECKSUM='92ab0175e3fcb8681963bf4a3f9fdd7cad7fef9ef8db680978124f206f1e5417'
readonly I3_PACKAGE_CHECKSUM='fa512de0909e5e1f4fe6d394ca3b8103f5cefd98389f39ae016c4438e992fc34'
readonly I3_CHECKSUM_PROFILE='doclink-package-jcs-v1'
readonly I3_PACKAGE_SNAPSHOT='e9373c66742caa172d83e05f265f76130e3343f298d13f9bb19cf16e22b6d493'
readonly I3_PACKAGE_FILE_COUNT='51'
readonly I3_DATASET_COUNT='47'
readonly I3_RECORD_COUNT='14869'
readonly I3_PACKAGE_ROWS='1'
readonly I3_PACKAGE_CAPABILITY_ROWS='8'
readonly I3_DATASET_ROWS='47'
readonly I3_DATASET_CAPABILITY_ROWS='376'
readonly I3_FIELD_CONTRACT_ROWS='458'
readonly I3_METADATA_ROWS='890'
readonly I3_BACKUP_FILE='/home/doclinc-dev/backups/manual/doclinc-staging-before-i3-20260725T194410Z.sql.gz'
readonly I3_BACKUP_SIZE='39162'
readonly I3_BACKUP_SHA256='5d936413998b9c090b18c19fc31da3894f83577e9556dcbc671665313c4dd9ee'

i3_phase='bootstrap'
i3_safe_error_code='unexpected_runner_failure'
i3_failure_emitted=false
readonly i3_root_bashpid="$BASHPID"
i3_temp_directory=''
i3_verifier_defaults=''
i3_writer_defaults=''
i3_verifier_password=''
i3_writer_password=''
I3_PHP_BINARY='php'

i3_cleanup() {
	[[ "$BASHPID" == "$i3_root_bashpid" ]] || return 0
	i3_verifier_password=''
	i3_writer_password=''
	unset i3_verifier_password i3_writer_password
	if [[ -n "${i3_verifier_defaults:-}" && -f "$i3_verifier_defaults" ]]; then
		rm -f -- "$i3_verifier_defaults"
	fi
	if [[ -n "${i3_writer_defaults:-}" && -f "$i3_writer_defaults" ]]; then
		rm -f -- "$i3_writer_defaults"
	fi
	if [[ -n "${i3_temp_directory:-}" && -d "$i3_temp_directory" ]]; then
		for i3_temporary_file in \
			"$i3_temp_directory/inspect.json" \
			"$i3_temp_directory/inspect-after.json" \
			"$i3_temp_directory/plan.json" \
			"$i3_temp_directory/dry-run.json" \
			"$i3_temp_directory/cli.stderr" \
			"$i3_temp_directory/writer-grants.actual" \
			"$i3_temp_directory/writer-grants.expected"; do
			[[ ! -e "$i3_temporary_file" ]] || rm -f -- "$i3_temporary_file"
		done
		rmdir -- "$i3_temp_directory" 2>/dev/null || true
	fi
}

i3_emit_failure() {
	local error_code="${1:-unexpected_runner_failure}"
	if [[ "$i3_failure_emitted" != true ]]; then
		printf 'PASS_2C1D1_I3=FAIL\n'
		printf 'FAILED_PHASE=%s\n' "$i3_phase"
		printf 'SAFE_ERROR_CODE=%s\n' "$error_code"
		i3_failure_emitted=true
	fi
}

i3_on_error() {
	local exit_code="${1:-1}"
	i3_emit_failure "$i3_safe_error_code"
	exit "$exit_code"
}

i3_fail() {
	i3_phase="$1"
	i3_emit_failure "$2"
	exit 1
}

trap 'i3_on_error "$?"' ERR
trap i3_cleanup EXIT

i3_usage() {
	printf '%s\n' 'Usage:'
	printf '%s\n' '  pass_2c1d1_i3_staging_dry_run.sh \'
	printf '%s\n' '    --repository-root=/home/doclinc-dev/htdocs/staging.doclinc.local \'
	printf '%s\n' '    --package-root=/controlled/path/v1 \'
	printf '%s\n' '    --package-owner=root --package-group=doclinc-dev \'
	printf '%s\n' '    --verifier-user=clinical-i3-verifier \'
	printf '%s\n' '    --verifier-password-file=/secure/owner-only/file \'
	printf '%s\n' '    --writer-user=doclink-clinical-metadata-writer \'
	printf '%s\n' '    --writer-account-host=localhost \'
	printf '%s\n' '    --writer-password-file=/secure/owner-only/file \'
	printf '%s\n' '    --registration-reference=PASS_2C1D1_I3-YYYYMMDDTHHMMSSZ'
}

i3_assert_equal() {
	local actual="$1" expected="$2" phase="$3" code="$4"
	[[ "$actual" == "$expected" ]] || i3_fail "$phase" "$code"
}

i3_assert_revision() {
	local head="$1" origin_head="$2"
	i3_assert_equal "$head" "$I3_EXPECTED_COMMIT" repository unexpected_repository_commit
	i3_assert_equal "$origin_head" "$I3_EXPECTED_COMMIT" repository unexpected_origin_commit
	i3_assert_equal "$head" "$origin_head" repository repository_origin_diverged
}

i3_assert_worktree_clean() {
	[[ -z "$1" ]] || i3_fail repository dirty_worktree
}

i3_assert_package_present() {
	[[ -d "$1" ]] || i3_fail package package_root_unavailable
}

i3_assert_digest_unchanged() {
	i3_assert_equal "$1" "$2" zero_write package_changed_during_dry_run
}

i3_json_value() {
	local json_file="$1" field="$2"
	"$I3_PHP_BINARY" -r '
$document=json_decode(file_get_contents($argv[1]),true,128,JSON_THROW_ON_ERROR);
if(!array_key_exists($argv[2],$document)){exit(3);}
$value=$document[$argv[2]];
if($value===true){echo "true";}elseif($value===false){echo "false";}elseif($value===null){echo "null";}elseif(is_scalar($value)){echo (string)$value;}else{exit(4);}
' "$json_file" "$field"
}

i3_assert_json() {
	local json_file="$1" field="$2" expected="$3" phase="$4" code="$5" actual
	i3_safe_error_code="$code"
	actual="$(i3_json_value "$json_file" "$field")" || i3_fail "$phase" malformed_cli_output
	i3_assert_equal "$actual" "$expected" "$phase" "$code"
}

i3_assert_inspection_contract() {
	local json_file="$1"
	i3_assert_json "$json_file" command inspect inspection inspection_command_mismatch
	i3_assert_json "$json_file" package_key "$I3_PACKAGE_KEY" package package_identity_mismatch
	i3_assert_json "$json_file" package_version "$I3_PACKAGE_VERSION" package package_version_mismatch
	i3_assert_json "$json_file" manifest_checksum "$I3_MANIFEST_CHECKSUM" package manifest_checksum_mismatch
	i3_assert_json "$json_file" package_checksum "$I3_PACKAGE_CHECKSUM" package package_checksum_mismatch
	i3_assert_json "$json_file" package_checksum_profile "$I3_CHECKSUM_PROFILE" package package_checksum_profile_mismatch
	i3_assert_json "$json_file" package_file_count "$I3_PACKAGE_FILE_COUNT" package package_file_count_mismatch
	i3_assert_json "$json_file" package_snapshot_sha256 "$I3_PACKAGE_SNAPSHOT" package package_snapshot_mismatch
	i3_assert_json "$json_file" declared_dataset_count "$I3_DATASET_COUNT" package declared_dataset_count_mismatch
	i3_assert_json "$json_file" observed_dataset_count "$I3_DATASET_COUNT" package observed_dataset_count_mismatch
	i3_assert_json "$json_file" declared_record_count "$I3_RECORD_COUNT" package declared_record_count_mismatch
	i3_assert_json "$json_file" observed_record_count "$I3_RECORD_COUNT" package observed_record_count_mismatch
	i3_assert_json "$json_file" production_ready false package package_production_policy_mismatch
	i3_assert_json "$json_file" source_runtime_enabled false package package_runtime_policy_mismatch
	i3_assert_json "$json_file" dml_executed false inspection inspection_reported_dml
	i3_assert_json "$json_file" package_data_imported false inspection inspection_reported_import
	i3_assert_json "$json_file" runtime_enabled false inspection inspection_reported_runtime
	i3_assert_json "$json_file" validation_result PASS inspection package_inspection_failed
}

i3_assert_plan_contract() {
	local json_file="$1" total
	i3_assert_json "$json_file" command plan-registration package planning_command_mismatch
	i3_assert_json "$json_file" database_comparison NOT_REQUESTED package unexpected_planning_database_connection
	i3_assert_json "$json_file" package_row_plan_count "$I3_PACKAGE_ROWS" package package_row_plan_mismatch
	i3_assert_json "$json_file" package_capability_plan_count "$I3_PACKAGE_CAPABILITY_ROWS" package package_capability_plan_mismatch
	i3_assert_json "$json_file" dataset_row_plan_count "$I3_DATASET_ROWS" package dataset_row_plan_mismatch
	i3_assert_json "$json_file" dataset_capability_plan_count "$I3_DATASET_CAPABILITY_ROWS" package dataset_capability_plan_mismatch
	i3_assert_json "$json_file" field_contract_plan_count "$I3_FIELD_CONTRACT_ROWS" package field_contract_plan_mismatch
	total=$((I3_PACKAGE_ROWS + I3_PACKAGE_CAPABILITY_ROWS + I3_DATASET_ROWS + I3_DATASET_CAPABILITY_ROWS + I3_FIELD_CONTRACT_ROWS))
	i3_assert_equal "$total" "$I3_METADATA_ROWS" package metadata_entity_count_mismatch
	i3_assert_json "$json_file" canonical_record_plan_count 0 package canonical_record_plan_not_zero
	i3_assert_json "$json_file" import_item_plan_count 0 package import_item_plan_not_zero
	i3_assert_json "$json_file" dml_executed false package planning_reported_dml
	i3_assert_json "$json_file" package_data_imported false package planning_reported_import
	i3_assert_json "$json_file" runtime_enabled false package planning_reported_runtime
	i3_assert_json "$json_file" validation_result PASS package metadata_planning_failed
}

i3_assert_dry_run_contract() {
	local json_file="$1"
	i3_assert_json "$json_file" command register-metadata dry_run dry_run_command_mismatch
	i3_assert_json "$json_file" registration_result new dry_run unexpected_registration_state
	i3_assert_json "$json_file" dry_run true dry_run dry_run_flag_mismatch
	i3_assert_json "$json_file" planned_metadata_rows "$I3_METADATA_ROWS" dry_run planned_metadata_rows_mismatch
	i3_assert_json "$json_file" existing_metadata_rows 0 dry_run existing_metadata_rows_not_zero
	i3_assert_json "$json_file" conflict_count 0 dry_run metadata_conflict_detected
	i3_assert_json "$json_file" metadata_rows_written 0 dry_run dry_run_metadata_write_detected
	i3_assert_json "$json_file" audit_rows_written 0 dry_run dry_run_audit_write_detected
	i3_assert_json "$json_file" transaction_committed false dry_run dry_run_commit_detected
	i3_assert_json "$json_file" write_executed false dry_run dry_run_write_detected
	i3_assert_json "$json_file" dml_executed false dry_run dry_run_dml_detected
	i3_assert_json "$json_file" package_data_imported false dry_run dry_run_import_detected
	i3_assert_json "$json_file" runtime_enabled false dry_run dry_run_runtime_detected
	i3_assert_json "$json_file" validation_result PASS dry_run dry_run_failed
}

i3_assert_zero_state_tuple() {
	local tuple="$1" phase="${2:-zero_write}"
	local value
	for value in $tuple; do
		[[ "$value" =~ ^[0-9]+$ ]] || i3_fail "$phase" invalid_database_count_output
		[[ "$value" == 0 ]] || i3_fail "$phase" database_zero_state_violated
	done
}

i3_expected_writer_grants() {
	printf 'global\t-\t-\t-\tUSAGE\tNO\n'
	local table privilege
	for table in \
		clinical_master_packages \
		clinical_master_package_capabilities \
		clinical_master_datasets \
		clinical_master_dataset_capabilities \
		clinical_master_dataset_field_contracts; do
		for privilege in INSERT SELECT; do
			printf 'table\t%s\t%s\t-\t%s\tNO\n' "$I3_DATABASE" "$table" "$privilege"
		done
	done
	printf 'table\t%s\tclinical_master_metadata_registration_events\t-\tINSERT\tNO\n' "$I3_DATABASE"
}

i3_assert_writer_privilege_rows() {
	local actual_file="$1" expected_file="$2"
	i3_expected_writer_grants | sort >"$expected_file"
	sort "$actual_file" -o "$actual_file"
	cmp -s "$actual_file" "$expected_file" || i3_fail writer_grants excessive_or_incomplete_writer_grants
}

i3_validate_secret_file() {
	local requested="$1" expected_owner="$2" resolved lexical owner mode
	[[ -n "$requested" && -f "$requested" && ! -L "$requested" ]] || i3_fail bootstrap secret_file_unavailable
	resolved="$(realpath -e -- "$requested")" || i3_fail bootstrap secret_file_unavailable
	lexical="$(realpath -s -- "$requested")" || i3_fail bootstrap secret_file_unavailable
	i3_assert_equal "$lexical" "$resolved" bootstrap secret_file_symlink_rejected
	owner="$(stat -c '%U' -- "$resolved")"
	mode="$(stat -c '%a' -- "$resolved")"
	i3_assert_equal "$owner" "$expected_owner" bootstrap secret_file_owner_mismatch
	i3_assert_equal "$mode" 600 bootstrap secret_file_mode_mismatch
	printf '%s' "$resolved"
}

i3_read_secret() {
	local secret_file="$1" -a lines=()
	mapfile -t lines <"$secret_file"
	[[ "${#lines[@]}" -eq 1 && -n "${lines[0]}" ]] || i3_fail bootstrap secret_file_content_invalid
	[[ ! "${lines[0]}" =~ [[:cntrl:]] ]] || i3_fail bootstrap secret_file_content_invalid
	printf '%s' "${lines[0]}"
}

i3_option_escape() {
	local value="$1"
	value="${value//\\/\\\\}"
	value="${value//\"/\\\"}"
	printf '%s' "$value"
}

i3_write_defaults_file() {
	local destination="$1" host="$2" port="$3" user="$4" password="$5"
	{
		printf '[client]\n'
		printf 'protocol=tcp\n'
		printf 'host="%s"\n' "$(i3_option_escape "$host")"
		printf 'port=%s\n' "$port"
		printf 'user="%s"\n' "$(i3_option_escape "$user")"
		printf 'password="%s"\n' "$(i3_option_escape "$password")"
		printf 'database="%s"\n' "$I3_DATABASE"
		printf 'default-character-set=utf8mb4\n'
	} >"$destination"
	chmod 600 "$destination"
}

i3_mysql_query() {
	local defaults_file="$1" sql="$2"
	"$I3_MYSQL_BINARY" --defaults-extra-file="$defaults_file" --batch --skip-column-names --raw --execute="$sql" 2>/dev/null
}

i3_query_scalar() {
	local defaults_file="$1" sql="$2" phase="$3" code="$4" result
	i3_safe_error_code="$code"
	result="$(i3_mysql_query "$defaults_file" "$sql")" || i3_fail "$phase" "$code"
	[[ "$result" =~ ^[0-9]+$ ]] || i3_fail "$phase" invalid_database_count_output
	printf '%s' "$result"
}

i3_query_zero_state() {
	local defaults_file="$1"
	local table count result=''
	for table in \
		clinical_master_packages \
		clinical_master_package_capabilities \
		clinical_master_datasets \
		clinical_master_dataset_capabilities \
		clinical_master_dataset_field_contracts \
		clinical_master_metadata_registration_events \
		clinical_master_import_batches \
		clinical_master_import_items \
		clinical_master_record_revisions \
		clinical_reference_term_types \
		clinical_reference_terms \
		clinical_reference_term_labels \
		clinical_reference_term_sources; do
		count="$(i3_query_scalar "$defaults_file" "SELECT COUNT(*) FROM \`$table\`" database "${table}_count_query_failed")"
		result+="${result:+ }$count"
	done
	count="$(i3_query_scalar "$defaults_file" "SELECT (SELECT COUNT(*) FROM clinical_master_packages WHERE production_ready<>0 OR source_runtime_enabled<>0)+(SELECT COUNT(*) FROM clinical_master_datasets WHERE source_runtime_enabled<>0)+(SELECT COUNT(*) FROM clinical_master_package_capabilities WHERE capability='runtime_reference' AND decision_status<>'blocked')+(SELECT COUNT(*) FROM clinical_master_dataset_capabilities WHERE capability='runtime_reference' AND decision_status<>'blocked')+(SELECT COUNT(*) FROM clinical_reference_term_types WHERE runtime_reference_enabled<>0 OR runtime_selection_enabled<>0)+(SELECT COUNT(*) FROM clinical_reference_terms WHERE runtime_reference_enabled<>0 OR runtime_selection_enabled<>0)+(SELECT COUNT(*) FROM clinical_reference_term_labels WHERE runtime_search_enabled<>0)" database runtime-state-query-failed)"
	result+=" $count"
	printf '%s' "$result"
}

i3_verify_package_permissions() {
	local root="$1" expected_owner="$2" expected_group="$3"
	local path owner group mode type mode_number
	while IFS= read -r -d '' path; do
		[[ ! -L "$path" ]] || i3_fail package package_symlink_rejected
		owner="$(stat -c '%U' -- "$path")"
		group="$(stat -c '%G' -- "$path")"
		mode="$(stat -c '%a' -- "$path")"
		type="$(stat -c '%F' -- "$path")"
		i3_assert_equal "$owner" "$expected_owner" package package_owner_mismatch
		i3_assert_equal "$group" "$expected_group" package package_group_mismatch
		[[ "$mode" =~ ^[0-7]{3,4}$ ]] || i3_fail package package_mode_invalid
		mode_number=$((8#$mode))
		(( (mode_number & 0022) == 0 )) || i3_fail package package_writable_by_group_or_world
		(( (mode_number & 0007) == 0 )) || i3_fail package package_world_access_rejected
		if [[ -d "$path" ]]; then
			[[ "$type" == directory ]] || i3_fail package package_special_file_rejected
			[[ -r "$path" && -x "$path" ]] || i3_fail package package_directory_unreadable
		elif [[ -f "$path" ]]; then
			[[ "$type" == 'regular file' || "$type" == 'regular empty file' ]] || i3_fail package package_special_file_rejected
			(( (mode_number & 0111) == 0 )) || i3_fail package package_executable_file_rejected
			[[ -r "$path" ]] || i3_fail package package_file_unreadable
		else
			i3_fail package package_special_file_rejected
		fi
	done < <(find -P "$root" -xdev -print0)
}

i3_verify_backup_file() {
	local backup_file="$1" manifest_file="$2" hash size owner mode mode_number path
	for path in "$backup_file" "$manifest_file"; do
		[[ -f "$path" && ! -L "$path" ]] || i3_fail backup backup_artifact_unavailable
		owner="$(stat -c '%U' -- "$path")"
		mode="$(stat -c '%a' -- "$path")"
		[[ "$owner" == "$I3_EXPECTED_OS_USER" || "$owner" == root ]] || i3_fail backup backup_owner_mismatch
		[[ "$mode" =~ ^[0-7]{3,4}$ ]] || i3_fail backup backup_mode_invalid
		mode_number=$((8#$mode))
		(( (mode_number & 0027) == 0 )) || i3_fail backup backup_permissions_too_broad
	done
	size="$(stat -c '%s' -- "$backup_file")"
	i3_assert_equal "$size" "$I3_BACKUP_SIZE" backup backup_size_mismatch
	hash="$(sha256sum -- "$backup_file" | awk '{print $1}')"
	i3_assert_equal "$hash" "$I3_BACKUP_SHA256" backup backup_checksum_mismatch
	gzip -t -- "$backup_file" 2>/dev/null || i3_fail backup backup_gzip_integrity_failed
	grep -Fq -- "$(basename "$backup_file")" "$manifest_file" || i3_fail backup backup_manifest_filename_missing
	grep -Fq -- "$I3_BACKUP_SHA256" "$manifest_file" || i3_fail backup backup_manifest_checksum_missing
	grep -Fq -- "$I3_BACKUP_SIZE" "$manifest_file" || i3_fail backup backup_manifest_size_missing
	grep -Fq -- 'clinical_schema_migrations' "$manifest_file" || i3_fail backup backup_manifest_schema_marker_missing
	grep -Fq -- 'clinical_master_metadata_registration_events' "$manifest_file" || i3_fail backup backup_manifest_audit_marker_missing
	grep -Fq -- 'clinical_master_packages' "$manifest_file" || i3_fail backup backup_manifest_metadata_marker_missing
}

i3_run_package_cli() {
	local output_file="$1"
	shift
	i3_safe_error_code='clinical_package_cli_failed'
	if ! "$I3_PHP_BINARY" "$I3_REPOSITORY_ROOT/tools/clinical_package/clinical_package.php" "$@" >"$output_file" 2>"$i3_temp_directory/cli.stderr"; then
		local reported='clinical_package_cli_failed'
		reported="$(i3_json_value "$output_file" error_1 2>/dev/null || printf '%s' "$reported")"
		i3_fail "$i3_phase" "$reported"
	fi
}

i3_main() {
	local package_requested='' package_owner='' package_group=''
	local verifier_user='' verifier_password_requested=''
	local writer_user='' writer_account_host='' writer_password_requested=''
	local registration_reference=''
	local database_host='127.0.0.1' database_port='3306'
	local backup_file="$I3_BACKUP_FILE" backup_manifest="$I3_BACKUP_FILE.manifest"
	local repository_requested=''
	local argument key value current_user branch head origin_head status tracked
	local package_root package_lexical package_parent backup_before backup_after migration_state
	local writer_identity writer_roles writer_grants_sql

	for argument in "$@"; do
		[[ "$argument" == --*=* ]] || { i3_usage; i3_fail bootstrap malformed_argument; }
		key="${argument%%=*}"
		value="${argument#*=}"
		[[ -n "$value" ]] || i3_fail bootstrap empty_argument
		case "$key" in
			--repository-root) repository_requested="$value" ;;
			--package-root) package_requested="$value" ;;
			--package-owner) package_owner="$value" ;;
			--package-group) package_group="$value" ;;
			--verifier-user) verifier_user="$value" ;;
			--verifier-password-file) verifier_password_requested="$value" ;;
			--writer-user) writer_user="$value" ;;
			--writer-account-host) writer_account_host="$value" ;;
			--writer-password-file) writer_password_requested="$value" ;;
			--registration-reference) registration_reference="$value" ;;
			--database-host) database_host="$value" ;;
			--database-port) database_port="$value" ;;
			*) i3_usage; i3_fail bootstrap unknown_argument ;;
		esac
	done

	for value in "$repository_requested" "$package_requested" "$package_owner" "$package_group" "$verifier_user" "$verifier_password_requested" "$writer_user" "$writer_account_host" "$writer_password_requested" "$registration_reference"; do
		[[ -n "$value" ]] || { i3_usage; i3_fail bootstrap required_argument_missing; }
	done
	[[ "$package_owner" =~ ^[a-z_][a-z0-9_-]*$ && "$package_group" =~ ^[a-z_][a-z0-9_-]*$ ]] || i3_fail bootstrap package_identity_argument_invalid
	[[ "$verifier_user" =~ ^[A-Za-z0-9_.-]{1,64}$ ]] || i3_fail bootstrap verifier_user_invalid
	[[ "$writer_user" =~ ^[a-z][a-z0-9_-]{2,63}$ ]] || i3_fail bootstrap writer_user_invalid
	[[ "$writer_account_host" =~ ^[A-Za-z0-9_.:-]{1,255}$ ]] || i3_fail bootstrap writer_host_invalid
	[[ "$writer_user" != "$I3_RUNTIME_USER" && "$writer_user" != "$I3_MIGRATOR_USER" && "$writer_user" != "$verifier_user" ]] || i3_fail writer_grants writer_identity_not_dedicated
	[[ "$registration_reference" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{0,190}$ ]] || i3_fail bootstrap registration_reference_invalid
	[[ "$database_host" =~ ^[A-Za-z0-9_.:-]{1,255}$ ]] || i3_fail bootstrap database_host_invalid
	[[ "$database_port" =~ ^[0-9]{1,5}$ ]] && ((database_port >= 1 && database_port <= 65535)) || i3_fail bootstrap database_port_invalid

	current_user="$(id -un)"
	i3_assert_equal "$current_user" "$I3_EXPECTED_OS_USER" repository unexpected_os_user
	I3_REPOSITORY_ROOT="$(realpath -e -- "$repository_requested")" || i3_fail repository repository_root_unavailable
	[[ ! -L "$repository_requested" ]] || i3_fail repository repository_root_symlink_rejected
	i3_assert_equal "$(realpath -s -- "$repository_requested")" "$I3_REPOSITORY_ROOT" repository repository_root_symlink_rejected
	i3_assert_equal "$I3_REPOSITORY_ROOT" "$I3_EXPECTED_REPOSITORY" repository unexpected_repository_path
	cd "$I3_REPOSITORY_ROOT"

	for command in git realpath stat find sha256sum gzip awk grep sort cmp mktemp chmod rm rmdir id "$I3_PHP_BINARY"; do
		command -v "$command" >/dev/null 2>&1 || i3_fail bootstrap required_command_missing
	done
	if command -v mariadb >/dev/null 2>&1; then I3_MYSQL_BINARY='mariadb';
	elif command -v mysql >/dev/null 2>&1; then I3_MYSQL_BINARY='mysql';
	else i3_fail bootstrap mysql_client_unavailable; fi
	"$I3_PHP_BINARY" -r 'exit(PHP_VERSION_ID>=80100&&PHP_VERSION_ID<80200?0:1);' || i3_fail bootstrap php_81_required

	i3_phase='repository'
	branch="$(git branch --show-current)"
	head="$(git rev-parse HEAD)"
	origin_head="$(git rev-parse "origin/$I3_EXPECTED_BRANCH")"
	status="$(git status --porcelain=v1 --untracked-files=all)"
	i3_assert_equal "$branch" "$I3_EXPECTED_BRANCH" repository unexpected_repository_branch
	i3_assert_revision "$head" "$origin_head"
	i3_assert_worktree_clean "$status"
	tracked="$(git ls-files -- database/master_data/clinical/v1)"
	[[ -z "$tracked" ]] || i3_fail repository clinical_package_is_tracked
	[[ ! -e database/master_data/clinical/v1 ]] || i3_fail repository clinical_package_present_in_webroot
	printf 'PASS_2C1D1_I3_PHASE_REPOSITORY=PASS\n'

	i3_phase='backup'
	i3_assert_equal "$backup_file" "$I3_BACKUP_FILE" backup unexpected_backup_file
	i3_verify_backup_file "$backup_file" "$backup_manifest"
	printf 'PASS_2C1D1_I3_PHASE_BACKUP=PASS\n'

	i3_phase='package'
	i3_assert_package_present "$package_requested"
	package_root="$(realpath -e -- "$package_requested")"
	package_lexical="$(realpath -s -- "$package_requested")"
	i3_assert_equal "$package_lexical" "$package_root" package package_root_symlink_rejected
	case "$package_root/" in "$I3_REPOSITORY_ROOT/"*) i3_fail package package_inside_webroot_rejected ;; esac
	i3_verify_package_permissions "$package_root" "$package_owner" "$package_group"
	package_parent="$(dirname "$package_root")"

	i3_temp_directory="$(mktemp -d /var/tmp/doclink-pass-2c1d1-i3.XXXXXXXXXX)"
	chmod 700 "$i3_temp_directory"
	i3_verifier_defaults="$i3_temp_directory/verifier.cnf"
	i3_writer_defaults="$i3_temp_directory/writer.cnf"
	verifier_password_requested="$(i3_validate_secret_file "$verifier_password_requested" "$current_user")"
	writer_password_requested="$(i3_validate_secret_file "$writer_password_requested" "$current_user")"
	i3_verifier_password="$(i3_read_secret "$verifier_password_requested")"
	i3_writer_password="$(i3_read_secret "$writer_password_requested")"
	i3_write_defaults_file "$i3_verifier_defaults" "$database_host" "$database_port" "$verifier_user" "$i3_verifier_password"
	i3_write_defaults_file "$i3_writer_defaults" "$database_host" "$database_port" "$writer_user" "$i3_writer_password"
	i3_verifier_password=''
	unset i3_verifier_password

	migration_state="$(i3_mysql_query "$i3_verifier_defaults" "SELECT COUNT(*),SUM(state='applied'),SUM(state<>'applied') FROM clinical_schema_migrations")" || i3_fail database migration_state_query_failed
	i3_assert_equal "$migration_state" $'6\t6\t0' database migration_state_mismatch
	backup_before="$(i3_query_zero_state "$i3_verifier_defaults")"
	i3_assert_zero_state_tuple "$backup_before" database

	i3_phase='writer_grants'
	writer_identity="$(i3_mysql_query "$i3_writer_defaults" "SELECT DATABASE(),SUBSTRING_INDEX(USER(),'@',1),CURRENT_USER(),COALESCE(CURRENT_ROLE(),'NONE')")" || i3_fail writer_grants writer_connection_failed
	i3_assert_equal "$writer_identity" "$I3_DATABASE"$'\t'"$writer_user"$'\t'"$writer_user@$writer_account_host"$'\tNONE' writer_grants writer_identity_mismatch
	writer_roles="$(i3_mysql_query "$i3_verifier_defaults" "SELECT COUNT(*) FROM information_schema.APPLICABLE_ROLES WHERE grantee='$writer_user@$writer_account_host'")" || i3_fail writer_grants writer_role_query_failed
	i3_assert_equal "$writer_roles" 0 writer_grants writer_role_rejected
	writer_grants_sql="SELECT privilege_surface,COALESCE(table_schema,'-'),COALESCE(table_name,'-'),COALESCE(column_name,'-'),privilege_type,is_grantable FROM (SELECT 'global' privilege_surface,NULL table_schema,NULL table_name,NULL column_name,privilege_type,is_grantable FROM information_schema.USER_PRIVILEGES WHERE grantee=CONCAT(CHAR(39),'$writer_user',CHAR(39),'@',CHAR(39),'$writer_account_host',CHAR(39)) UNION ALL SELECT 'schema',table_schema,NULL,NULL,privilege_type,is_grantable FROM information_schema.SCHEMA_PRIVILEGES WHERE grantee=CONCAT(CHAR(39),'$writer_user',CHAR(39),'@',CHAR(39),'$writer_account_host',CHAR(39)) UNION ALL SELECT 'table',table_schema,table_name,NULL,privilege_type,is_grantable FROM information_schema.TABLE_PRIVILEGES WHERE grantee=CONCAT(CHAR(39),'$writer_user',CHAR(39),'@',CHAR(39),'$writer_account_host',CHAR(39)) UNION ALL SELECT 'column',table_schema,table_name,column_name,privilege_type,is_grantable FROM information_schema.COLUMN_PRIVILEGES WHERE grantee=CONCAT(CHAR(39),'$writer_user',CHAR(39),'@',CHAR(39),'$writer_account_host',CHAR(39))) p"
	i3_mysql_query "$i3_verifier_defaults" "$writer_grants_sql" >"$i3_temp_directory/writer-grants.actual" || i3_fail writer_grants writer_privilege_query_failed
	i3_assert_writer_privilege_rows "$i3_temp_directory/writer-grants.actual" "$i3_temp_directory/writer-grants.expected"
	printf 'PASS_2C1D1_I3_PHASE_WRITER_GRANTS=PASS\n'

	i3_phase='inspection'
	(
		export DOCLINK_CLINICAL_PACKAGE_ALLOWED_ROOTS="$package_parent"
		unset DOCLINK_CLINICAL_SCHEMA_DB_HOST DOCLINK_CLINICAL_SCHEMA_DB_PORT DOCLINK_CLINICAL_SCHEMA_DB_NAME DOCLINK_CLINICAL_SCHEMA_DB_USER DOCLINK_CLINICAL_SCHEMA_DB_PASSWORD DOCLINK_CLINICAL_SCHEMA_ALLOWED_USERS
		i3_run_package_cli "$i3_temp_directory/inspect.json" inspect "--package-root=$package_root" --format=json
		i3_run_package_cli "$i3_temp_directory/plan.json" plan-registration "--package-root=$package_root" --format=json
	)
	i3_assert_inspection_contract "$i3_temp_directory/inspect.json"
	i3_assert_plan_contract "$i3_temp_directory/plan.json"
	printf 'PASS_2C1D1_I3_PHASE_PACKAGE=PASS\n'
	printf 'PASS_2C1D1_I3_PHASE_INSPECTION=PASS\n'

	i3_phase='dry_run'
	(
		export DOCLINK_CLINICAL_PACKAGE_ALLOWED_ROOTS="$package_parent"
		export DOCLINK_CLINICAL_PACKAGE_WRITE_DB_HOST="$database_host"
		export DOCLINK_CLINICAL_PACKAGE_WRITE_DB_PORT="$database_port"
		export DOCLINK_CLINICAL_PACKAGE_WRITE_DB_NAME="$I3_DATABASE"
		export DOCLINK_CLINICAL_PACKAGE_WRITE_DB_USER="$writer_user"
		export DOCLINK_CLINICAL_PACKAGE_WRITE_DB_PASSWORD="$i3_writer_password"
		export DOCLINK_CLINICAL_PACKAGE_WRITE_ALLOWED_USERS="$writer_user"
		export DOCLINK_CLINICAL_PACKAGE_METADATA_WRITE_ENABLED='false'
		export DOCLINK_CLINICAL_PACKAGE_WRITE_LOCK_TIMEOUT_SECONDS='10'
		unset DOCLINK_CLINICAL_SCHEMA_DB_HOST DOCLINK_CLINICAL_SCHEMA_DB_PORT DOCLINK_CLINICAL_SCHEMA_DB_NAME DOCLINK_CLINICAL_SCHEMA_DB_USER DOCLINK_CLINICAL_SCHEMA_DB_PASSWORD DOCLINK_CLINICAL_SCHEMA_ALLOWED_USERS
		i3_run_package_cli "$i3_temp_directory/dry-run.json" register-metadata \
			"--package-root=$package_root" \
			--format=json \
			"--confirm-database=$I3_DATABASE" \
			"--confirm-package-checksum=$I3_PACKAGE_CHECKSUM" \
			"--confirm-package-snapshot=$I3_PACKAGE_SNAPSHOT" \
			"--confirm-metadata-entity-count=$I3_METADATA_ROWS" \
			"--registration-reference=$registration_reference"
	)
	i3_writer_password=''
	unset i3_writer_password
	i3_assert_dry_run_contract "$i3_temp_directory/dry-run.json"
	printf 'PASS_2C1D1_I3_PHASE_DRY_RUN=PASS\n'

	i3_phase='zero_write'
	backup_after="$(i3_query_zero_state "$i3_verifier_defaults")"
	i3_assert_zero_state_tuple "$backup_after" zero_write
	i3_assert_equal "$backup_after" "$backup_before" zero_write database_state_changed
	(
		export DOCLINK_CLINICAL_PACKAGE_ALLOWED_ROOTS="$package_parent"
		i3_run_package_cli "$i3_temp_directory/inspect-after.json" inspect "--package-root=$package_root" --format=json
	)
	i3_assert_inspection_contract "$i3_temp_directory/inspect-after.json"
	i3_assert_digest_unchanged "$(i3_json_value "$i3_temp_directory/inspect.json" package_snapshot_sha256)" "$(i3_json_value "$i3_temp_directory/inspect-after.json" package_snapshot_sha256)"
	i3_assert_digest_unchanged "$(i3_json_value "$i3_temp_directory/inspect.json" package_checksum)" "$(i3_json_value "$i3_temp_directory/inspect-after.json" package_checksum)"
	status="$(git status --porcelain=v1 --untracked-files=all)"
	i3_assert_worktree_clean "$status"
	printf 'PASS_2C1D1_I3_PHASE_ZERO_WRITE=PASS\n'

	printf 'PLANNED_METADATA_ROWS=890\n'
	printf 'METADATA_ROWS_WRITTEN=0\n'
	printf 'AUDIT_ROWS_WRITTEN=0\n'
	printf 'WRITE_EXECUTED=false\n'
	printf 'TRANSACTION_COMMITTED=false\n'
	printf 'PACKAGE_DATA_IMPORTED=false\n'
	printf 'RUNTIME_ENABLED=false\n'
	printf 'DECLARED_DATASET_COUNT=47\n'
	printf 'DECLARED_RECORD_COUNT=14869\n'
	printf 'STAGING_REGISTRATION_READY=true\n'
	printf 'APPLY_NOT_YET_EXECUTED=true\n'
	printf 'PASS_2C1D1_I3=PASS\n'
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
	i3_main "$@"
fi
