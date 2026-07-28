<?php

define('BASEPATH', __DIR__);

require_once dirname(__DIR__, 3) . '/application/libraries/Doclinc_feature_flags.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Mutation_token_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Care_operations_policy.php';

$passed = 0;
$failed = 0;

function care_operations_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo 'PASS ' . $label . "\n";
		return;
	}
	$failed++;
	fwrite(STDERR, 'FAIL ' . $label . "\n");
}

function care_operations_disabled($enabled, $feature_environment, $runtime_environment)
{
	return Doclinc_feature_flags::resolve($enabled, $feature_environment, $runtime_environment)['enabled'] === false;
}

care_operations_expect(care_operations_disabled(null, 'staging', 'staging'), 'flag_missing_disabled');
care_operations_expect(care_operations_disabled('', 'staging', 'staging'), 'flag_empty_disabled');
care_operations_expect(care_operations_disabled('sometimes', 'staging', 'staging'), 'flag_malformed_disabled');
care_operations_expect(care_operations_disabled('false', 'staging', 'staging'), 'flag_false_disabled');
care_operations_expect(care_operations_disabled('0', 'staging', 'staging'), 'flag_zero_disabled');
care_operations_expect(care_operations_disabled('off', 'staging', 'staging'), 'flag_off_disabled');
care_operations_expect(care_operations_disabled('no', 'staging', 'staging'), 'flag_no_disabled');
care_operations_expect(care_operations_disabled('true', 'production', 'production'), 'production_rejected');
care_operations_expect(care_operations_disabled('true', 'staging', 'production'), 'production_runtime_rejected');
care_operations_expect(care_operations_disabled('true', 'uat', 'staging'), 'environment_mismatch_rejected');
care_operations_expect(Doclinc_feature_flags::resolve('true', 'staging', 'staging')['enabled'] === true, 'staging_explicit_true_enabled');
care_operations_expect(Doclinc_feature_flags::resolve('1', 'uat', 'uat')['enabled'] === true, 'uat_explicit_true_enabled');

$policy = new Mutation_token_policy(str_repeat('k', 32));
$session = array();
$issued = $policy->issue($session, 101, 'session-a', 'visit.media', 60, 1000);
care_operations_expect(is_string($issued['token']) && strlen($issued['token']) === 63, 'mutation_token_random_shape');
care_operations_expect(strpos(serialize($session), $issued['token']) === false, 'raw_mutation_token_not_stored');
care_operations_expect($policy->consume($session, $issued['token'], 101, 'session-a', 'visit.media', 1059) === true, 'mutation_token_valid');
care_operations_expect($policy->consume($session, $issued['token'], 101, 'session-a', 'visit.media', 1059) === false, 'mutation_token_single_use');

$binding = $policy->issue($session, 101, 'session-a', 'assignment.pic', 60, 2000);
care_operations_expect($policy->consume($session, $binding['token'], 102, 'session-a', 'assignment.pic', 2001) === false, 'mutation_token_user_binding');
$binding = $policy->issue($session, 101, 'session-a', 'assignment.pic', 60, 2000);
care_operations_expect($policy->consume($session, $binding['token'], 101, 'session-b', 'assignment.pic', 2001) === false, 'mutation_token_session_binding');
$binding = $policy->issue($session, 101, 'session-a', 'assignment.pic', 60, 2000);
care_operations_expect($policy->consume($session, $binding['token'], 101, 'session-a', 'route.update', 2001) === false, 'mutation_token_purpose_binding');
$expired = $policy->issue($session, 101, 'session-a', 'route.update', 60, 3000);
care_operations_expect($policy->consume($session, $expired['token'], 101, 'session-a', 'route.update', 3060) === false, 'mutation_token_exact_expiry_rejected');
care_operations_expect($policy->consume($session, array('invalid'), 101, 'session-a', 'route.update', 3060) === false, 'mutation_token_array_rejected');

$warga = array('logged_in' => true, 'id' => 51, 'role' => 'warga', 'status' => 'aktif', 'must_change_password' => 0);
care_operations_expect(Care_operations_policy::wargaOwnsRequest($warga, array('user_id' => 51)), 'warga_owner_allowed');
care_operations_expect(!Care_operations_policy::wargaOwnsRequest($warga, array('user_id' => 52)), 'warga_other_owner_denied');
$warga['must_change_password'] = 1;
care_operations_expect(!Care_operations_policy::wargaOwnsRequest($warga, array('user_id' => 51)), 'must_change_warga_blocked');

$personal = array(
	'valid' => true,
	'account_type' => 'personal',
	'user_id' => 101,
	'staff_id' => 31,
	'user_status' => 'aktif',
	'staff_status' => 'aktif',
	'puskesmas_code' => 'PKM01',
	'must_change_password' => 0,
);
$access = array('tenant_match' => true, 'can_handle' => true, 'ownership_source' => 'staff_assignment');
$accepted = array('request_status' => 'Accepted', 'assigned_nakes_user_id' => 101);
care_operations_expect(Care_operations_policy::personalNakesCanHandle($personal, $access, $accepted), 'personal_pic_allowed');
$access['ownership_source'] = null;
care_operations_expect(!Care_operations_policy::personalNakesCanHandle($personal, $access, $accepted), 'unproven_personal_pic_denied');
$access['ownership_source'] = 'staff_assignment_command_center_bridge';
care_operations_expect(Care_operations_policy::personalNakesCanHandle($personal, $access, $accepted), 'command_center_bridge_assignment_allowed');
$accepted['assigned_nakes_user_id'] = 101;
$access['tenant_match'] = false;
care_operations_expect(!Care_operations_policy::personalNakesCanHandle($personal, $access, $accepted), 'cross_puskesmas_denied');
$access['tenant_match'] = true;
$accepted['request_status'] = 'Completed';
care_operations_expect(!Care_operations_policy::personalNakesCanHandle($personal, $access, $accepted), 'completed_personal_mutation_denied');

$center = array(
	'valid' => true,
	'account_type' => 'command_center',
	'user_status' => 'aktif',
	'puskesmas_code' => 'PKM01',
	'must_change_password' => 0,
);
care_operations_expect(Care_operations_policy::commandCenterCanCoordinate($center, array('assigned_puskesmas_code' => 'PKM01', 'request_status' => 'Accepted')), 'command_center_same_tenant_allowed');
care_operations_expect(!Care_operations_policy::commandCenterCanCoordinate($center, array('assigned_puskesmas_code' => 'PKM02', 'request_status' => 'Accepted')), 'command_center_cross_tenant_denied');
care_operations_expect(!Care_operations_policy::commandCenterCanCoordinate($center, array('assigned_puskesmas_code' => 'PKM01', 'request_status' => 'Completed')), 'command_center_completed_denied');
care_operations_expect(Care_operations_policy::adminCanRead(array('logged_in' => true, 'id' => 1, 'role' => 'admin', 'status' => 'aktif'), true), 'admin_read_only_allowed');
care_operations_expect(!Care_operations_policy::adminCanRead(array('logged_in' => true, 'id' => 1, 'role' => 'admin', 'status' => 'aktif'), false), 'admin_mutation_denied');
care_operations_expect(Care_operations_policy::eligiblePresenceTarget($personal), 'active_personal_presence_target');
$personal['staff_status'] = 'nonaktif';
$personal['user_id'] = 0;
care_operations_expect(!Care_operations_policy::eligiblePresenceTarget($personal), 'inactive_unlinked_staff_not_presence_target');

$migration = dirname(__DIR__, 3) . '/application/migrations/20260728000100_care_operations_foundation.php';
$migration_source = file_get_contents($migration);
care_operations_expect($migration_source !== false, 'migration_source_readable');
care_operations_expect(stripos($migration_source, 'FOREIGN KEY') === false, 'care_operations_targets_have_no_foreign_keys');
care_operations_expect(stripos($migration_source, 'clinical_schema_migrations') === false, 'migration_does_not_touch_clinical_ledger');
care_operations_expect(preg_match('/\b(?:DROP|TRUNCATE|ALTER|RENAME)\b/i', $migration_source) === 0, 'migration_has_no_destructive_ddl');
care_operations_expect(preg_match('/(?:->query|prepare)\(\s*["\']\s*(?:INSERT|UPDATE|DELETE|REPLACE)\b/i', $migration_source) === 0, 'migration_has_no_dml');

$process = proc_open(
	array(PHP_BINARY, $migration),
	array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
	$pipes,
	dirname(__DIR__, 3),
	array_merge(getenv(), array(
		'DOCLINC_CARE_OPERATIONS_SCHEMA_DB_HOST' => 'invalid.invalid',
		'DOCLINC_CARE_OPERATIONS_SCHEMA_DB_PASSWORD' => 'not-used-by-plan',
	))
);
if (is_resource($process)) {
	fclose($pipes[0]);
	$plan_stdout = stream_get_contents($pipes[1]);
	$plan_stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$plan_exit = proc_close($process);
	care_operations_expect($plan_exit === 0, 'migration_plan_exit_success');
	care_operations_expect(strpos($plan_stdout, 'DATABASE_CONNECTION_OPENED=false') !== false
		&& strpos($plan_stdout, 'DDL_EXECUTED=false') !== false, 'migration_plan_zero_connection_zero_write');
	care_operations_expect($plan_stderr === '', 'migration_plan_safe_stderr');
} else {
	care_operations_expect(false, 'migration_plan_process_started');
}

echo 'UNIT_TESTS_PASSED=' . $passed . "\n";
echo 'UNIT_TESTS_FAILED=' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
