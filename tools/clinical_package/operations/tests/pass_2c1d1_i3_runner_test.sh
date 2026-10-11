#!/usr/bin/env bash

set -Eeuo pipefail
umask 077
export LC_ALL=C
export LANG=C

readonly TEST_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd -P)"
readonly RUNNER="$TEST_ROOT/tools/clinical_package/operations/pass_2c1d1_i3_staging_dry_run.sh"
readonly PHP_BIN="${PHP_BINARY:-php}"

test_directory="$(mktemp -d "${TMPDIR:-/tmp}/doclink-i3-runner-test.XXXXXXXXXX")"
passes=0
failures=0

cleanup() {
	local file
	for file in inspection.json plan.json dry-run.json grants.actual grants.expected; do
		[[ ! -f "$test_directory/$file" ]] || rm -f -- "$test_directory/$file"
	done
	rmdir -- "$test_directory" 2>/dev/null || true
}
trap cleanup EXIT

record_pass() {
	passes=$((passes + 1))
	printf 'PASS %s\n' "$1"
}

record_failure() {
	failures=$((failures + 1))
	printf 'FAIL %s\n' "$1"
}

expect_failure() {
	local name="$1" expected_code="$2"
	shift 2
	local output status
	set +e
	output="$("$@" 2>&1)"
	status=$?
	set -e
	if [[ "$status" -ne 0 && "$output" == *"PASS_2C1D1_I3=FAIL"* && "$output" == *"SAFE_ERROR_CODE=$expected_code"* ]]; then
		record_pass "$name"
	else
		record_failure "$name"
	fi
}

write_inspection() {
	printf '%s\n' '{"command":"inspect","package_key":"doclink-clinical-master","package_version":"v1","manifest_checksum":"92ab0175e3fcb8681963bf4a3f9fdd7cad7fef9ef8db680978124f206f1e5417","package_checksum":"fa512de0909e5e1f4fe6d394ca3b8103f5cefd98389f39ae016c4438e992fc34","package_checksum_profile":"doclink-package-jcs-v1","package_file_count":51,"package_snapshot_sha256":"e9373c66742caa172d83e05f265f76130e3343f298d13f9bb19cf16e22b6d493","declared_dataset_count":47,"observed_dataset_count":47,"declared_record_count":14869,"observed_record_count":14869,"production_ready":false,"source_runtime_enabled":false,"dml_executed":false,"package_data_imported":false,"runtime_enabled":false,"validation_result":"PASS"}' >"$test_directory/inspection.json"
}

write_plan() {
	printf '%s\n' '{"command":"plan-registration","database_comparison":"NOT_REQUESTED","package_row_plan_count":1,"package_capability_plan_count":8,"dataset_row_plan_count":47,"dataset_capability_plan_count":376,"field_contract_plan_count":458,"canonical_record_plan_count":0,"import_item_plan_count":0,"dml_executed":false,"package_data_imported":false,"runtime_enabled":false,"validation_result":"PASS"}' >"$test_directory/plan.json"
}

write_dry_run() {
	printf '%s\n' '{"command":"register-metadata","registration_result":"new","dry_run":true,"planned_metadata_rows":890,"existing_metadata_rows":0,"conflict_count":0,"metadata_rows_written":0,"audit_rows_written":0,"transaction_committed":false,"write_executed":false,"dml_executed":false,"package_data_imported":false,"runtime_enabled":false,"validation_result":"PASS"}' >"$test_directory/dry-run.json"
}

expect_failure wrong_commit unexpected_repository_commit bash -c \
	'source "$1"; i3_assert_revision 0000000000000000000000000000000000000000 "$I3_EXPECTED_COMMIT"' _ "$RUNNER"

expect_failure dirty_worktree dirty_worktree bash -c \
	'source "$1"; i3_assert_worktree_clean " M user-owned-file"' _ "$RUNNER"

expect_failure missing_package package_root_unavailable bash -c \
	'source "$1"; i3_assert_package_present "$2/missing"' _ "$RUNNER" "$test_directory"

write_inspection
"$PHP_BIN" -r '$p=$argv[1];$j=json_decode(file_get_contents($p),true);$j["package_checksum"]=str_repeat("0",64);file_put_contents($p,json_encode($j));' "$test_directory/inspection.json"
expect_failure wrong_checksum package_checksum_mismatch bash -c \
	'source "$1"; I3_PHP_BINARY="$2"; i3_assert_inspection_contract "$3"' _ "$RUNNER" "$PHP_BIN" "$test_directory/inspection.json"

write_inspection
"$PHP_BIN" -r '$p=$argv[1];$j=json_decode(file_get_contents($p),true);$j["package_snapshot_sha256"]=str_repeat("0",64);file_put_contents($p,json_encode($j));' "$test_directory/inspection.json"
expect_failure wrong_snapshot package_snapshot_mismatch bash -c \
	'source "$1"; I3_PHP_BINARY="$2"; i3_assert_inspection_contract "$3"' _ "$RUNNER" "$PHP_BIN" "$test_directory/inspection.json"

write_plan
"$PHP_BIN" -r '$p=$argv[1];$j=json_decode(file_get_contents($p),true);$j["field_contract_plan_count"]=457;file_put_contents($p,json_encode($j));' "$test_directory/plan.json"
expect_failure wrong_entity_count field_contract_plan_mismatch bash -c \
	'source "$1"; I3_PHP_BINARY="$2"; i3_assert_plan_contract "$3"' _ "$RUNNER" "$PHP_BIN" "$test_directory/plan.json"

bash -c 'source "$1"; i3_expected_writer_grants' _ "$RUNNER" >"$test_directory/grants.actual"
printf 'table\tdoclinc-staging\tclinical_master_packages\t-\tUPDATE\tNO\n' >>"$test_directory/grants.actual"
expect_failure excessive_writer_grants excessive_or_incomplete_writer_grants bash -c \
	'source "$1"; i3_assert_writer_privilege_rows "$2" "$3"' _ "$RUNNER" "$test_directory/grants.actual" "$test_directory/grants.expected"

expect_failure metadata_not_zero database_zero_state_violated bash -c \
	'source "$1"; i3_assert_zero_state_tuple "1 0 0 0 0 0 0 0 0 0 0 0 0 0" database' _ "$RUNNER"

expect_failure audit_not_zero database_zero_state_violated bash -c \
	'source "$1"; i3_assert_zero_state_tuple "0 0 0 0 0 1 0 0 0 0 0 0 0 0" database' _ "$RUNNER"

expect_failure runtime_active database_zero_state_violated bash -c \
	'source "$1"; i3_assert_zero_state_tuple "0 0 0 0 0 0 0 0 0 0 0 0 0 1" database' _ "$RUNNER"

expect_failure package_digest_changed package_changed_during_dry_run bash -c \
	'source "$1"; i3_assert_digest_unchanged before after' _ "$RUNNER"

write_dry_run
"$PHP_BIN" -r '$p=$argv[1];$j=json_decode(file_get_contents($p),true);$j["metadata_rows_written"]=1;$j["write_executed"]=true;file_put_contents($p,json_encode($j));' "$test_directory/dry-run.json"
expect_failure dry_run_write dry_run_metadata_write_detected bash -c \
	'source "$1"; I3_PHP_BINARY="$2"; i3_assert_dry_run_contract "$3"' _ "$RUNNER" "$PHP_BIN" "$test_directory/dry-run.json"

if grep -Fq -- '--apply' "$RUNNER"; then
	record_failure runner_contains_apply_argument
else
	record_pass runner_contains_no_apply_argument
fi

printf 'TESTS_PASSED=%s\n' "$passes"
printf 'TESTS_FAILED=%s\n' "$failures"
printf 'VALIDATION_RESULT=%s\n' "$([[ "$failures" -eq 0 ]] && printf PASS || printf FAIL)"
[[ "$failures" -eq 0 ]]
