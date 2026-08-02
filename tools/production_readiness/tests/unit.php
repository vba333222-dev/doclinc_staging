<?php
require_once dirname(__DIR__) . '/ProductionReadinessReport.php';

$passed = 0;
$failed = 0;
function production_readiness_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; echo "FAIL {$label}\n";
}

$complete = array(
	array('complete' => true, 'actor_type' => 'warga', 'safe_error_code' => '', 'missing_fields' => array(), 'schema_gaps' => array(), 'remediation_mode' => 'none'),
	array('complete' => true, 'actor_type' => 'personal', 'safe_error_code' => '', 'missing_fields' => array(), 'schema_gaps' => array(), 'remediation_mode' => 'none'),
	array('complete' => true, 'actor_type' => 'command_center', 'safe_error_code' => '', 'missing_fields' => array(), 'schema_gaps' => array(), 'remediation_mode' => 'none'),
);
$operations_ready = array('facility_total' => 2, 'facility_complete' => 2, 'facility_command_center_ready' => 2, 'staff_total' => 3, 'staff_ready' => 3);
$ready = ProductionReadinessReport::compile($complete, array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), $operations_ready);
production_readiness_expect($ready['activation_ready'] === true && $ready['blocker_count'] === 0, 'all_complete_is_activation_ready');
production_readiness_expect($ready['actor_type_counts']['warga'] === 1 && $ready['actor_type_complete']['personal'] === 1, 'role_counts_exact');

$blocked = $complete;
$blocked[] = array(
	'complete' => false,
	'actor_type' => 'warga',
	'safe_error_code' => 'profile_prerequisites_missing',
	'missing_fields' => array('nik', 'photo', 'unexpected_private_value'),
	'schema_gaps' => array('users.nomor_kk', '../../unsafe'),
	'remediation_mode' => 'self_service',
);
$blocked[] = array(
	'complete' => false,
	'actor_type' => 'denied',
	'safe_error_code' => 'actor_denied',
	'missing_fields' => array('nakes_identity'),
	'schema_gaps' => array(),
	'remediation_mode' => 'managed',
);
$report = ProductionReadinessReport::compile($blocked, array('users.nik'), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), $operations_ready);
production_readiness_expect($report['activation_ready'] === false && $report['actor_incomplete'] === 2, 'incomplete_actor_blocks_activation');
production_readiness_expect($report['actor_denied'] === 1, 'denied_identity_counted');
production_readiness_expect($report['missing_field_counts']['nik'] === 1 && $report['missing_field_counts']['photo'] === 1, 'safe_missing_fields_aggregated');
production_readiness_expect($report['unknown_missing_field_count'] === 1, 'unknown_field_counted_without_value');
production_readiness_expect($report['schema_gaps'] === array('users.nik', 'users.nomor_kk'), 'schema_codes_sanitized_and_sorted');
$rendered = implode("\n", ProductionReadinessReport::lines($report));
production_readiness_expect(strpos($rendered, 'MISSING_NIK=1') !== false && strpos($rendered, 'unexpected_private_value') === false, 'rendered_output_is_aggregate_only');

$storage_blocked = ProductionReadinessReport::compile($complete, array(), array('ready' => true, 'private' => false, 'mode_0700' => true, 'owner_ready' => true), $operations_ready);
production_readiness_expect($storage_blocked['activation_ready'] === false && $storage_blocked['blocker_count'] === 1, 'public_storage_blocks_activation');
$owner_blocked = ProductionReadinessReport::compile($complete, array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => false), $operations_ready);
production_readiness_expect($owner_blocked['activation_ready'] === false && $owner_blocked['blocker_count'] === 1, 'wrong_storage_owner_blocks_activation');
$operations_blocked = ProductionReadinessReport::compile($complete, array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), array('facility_total' => 2, 'facility_complete' => 1, 'facility_command_center_ready' => 1, 'staff_total' => 3, 'staff_ready' => 2));
production_readiness_expect($operations_blocked['activation_ready'] === false && $operations_blocked['blocker_count'] === 3, 'facility_command_center_and_roster_gaps_block_activation');
$clamped = ProductionReadinessReport::compile($complete, array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), array('facility_total' => 1, 'facility_complete' => 9, 'facility_command_center_ready' => 9, 'staff_total' => 1, 'staff_ready' => 9));
production_readiness_expect($clamped['facility_complete'] === 1 && $clamped['facility_command_center_ready'] === 1 && $clamped['staff_ready'] === 1, 'operational_counts_are_clamped_to_totals');
$empty = ProductionReadinessReport::compile(array(), array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), array());
production_readiness_expect($empty['activation_ready'] === false, 'empty_actor_set_fails_closed');

echo "PRODUCTION_READINESS_UNIT_PASSED={$passed}\n";
echo "PRODUCTION_READINESS_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
