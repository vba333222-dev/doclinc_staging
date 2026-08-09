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
production_readiness_expect($ready['managed_data_ready'] === true && $ready['controlled_enforcement_ready'] === true, 'all_complete_is_ready_at_every_gate');
production_readiness_expect($ready['actor_type_counts']['warga'] === 1 && $ready['actor_type_complete']['personal'] === 1, 'role_counts_exact');

$self_service = $complete;
$self_service[] = array(
	'complete' => false, 'actor_type' => 'warga', 'safe_error_code' => 'profile_prerequisites_missing',
	'missing_fields' => array('nik'), 'schema_gaps' => array(), 'remediation_mode' => 'self_service',
	'self_service_fields' => array('nik'), 'managed_fields' => array(), 'audit_denial_reason' => '',
);
$self_service_report = ProductionReadinessReport::compile($self_service, array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), $operations_ready);
production_readiness_expect($self_service_report['activation_ready'] === false && $self_service_report['controlled_enforcement_ready'] === true, 'self_service_gap_allows_controlled_enforcement_only');
production_readiness_expect($self_service_report['actor_self_service_gap_count'] === 1 && $self_service_report['actor_managed_gap_count'] === 0, 'self_service_actor_gap_counted_exactly');

$password_gate = $complete;
$password_gate[] = array(
	'complete' => false, 'actor_type' => 'denied', 'safe_error_code' => 'actor_denied',
	'missing_fields' => array(), 'schema_gaps' => array(), 'remediation_mode' => 'none',
	'self_service_fields' => array(), 'managed_fields' => array(),
	'audit_denial_reason' => 'password_change_required', 'audit_credential_state' => 'first_login_pending',
);
$password_report = ProductionReadinessReport::compile($password_gate, array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), $operations_ready);
production_readiness_expect($password_report['controlled_enforcement_ready'] === true && $password_report['activation_ready'] === false, 'password_gate_allows_controlled_enforcement_only');
production_readiness_expect($password_report['actor_password_change_required'] === 1 && $password_report['actor_identity_denied'] === 0 && $password_report['actor_profile_evaluated'] === 3, 'password_gate_denial_classified_without_profile_distortion');
production_readiness_expect($password_report['actor_first_login_pending'] === 1 && $password_report['actor_password_reset_pending'] === 0, 'first_login_password_gate_counted_separately');

$password_reset_gate = $complete;
$password_reset_gate[] = array(
	'complete' => false, 'actor_type' => 'denied', 'safe_error_code' => 'actor_denied',
	'missing_fields' => array(), 'schema_gaps' => array(), 'remediation_mode' => 'none',
	'self_service_fields' => array(), 'managed_fields' => array(),
	'audit_denial_reason' => 'password_change_required', 'audit_credential_state' => 'admin_reset_pending',
);
$password_reset_report = ProductionReadinessReport::compile($password_reset_gate, array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), $operations_ready);
production_readiness_expect($password_reset_report['actor_password_reset_pending'] === 1 && $password_reset_report['actor_first_login_pending'] === 0, 'admin_reset_password_gate_counted_separately');

$blocked = $complete;
$blocked[] = array(
	'complete' => false,
	'actor_type' => 'warga',
	'safe_error_code' => 'profile_prerequisites_missing',
	'missing_fields' => array('nik', 'photo', 'unexpected_private_value'),
	'schema_gaps' => array('users.nomor_kk', '../../unsafe'),
	'remediation_mode' => 'self_service',
	'self_service_fields' => array('nik', 'photo'),
	'managed_fields' => array(),
);
$blocked[] = array(
	'complete' => false,
	'actor_type' => 'denied',
	'safe_error_code' => 'actor_denied',
	'missing_fields' => array('nakes_identity'),
	'schema_gaps' => array(),
	'remediation_mode' => 'managed',
	'self_service_fields' => array(),
	'managed_fields' => array('nakes_identity'),
	'audit_denial_reason' => 'identity_invalid',
);
$report = ProductionReadinessReport::compile($blocked, array('users.nik'), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), $operations_ready);
production_readiness_expect($report['activation_ready'] === false && $report['actor_incomplete'] === 2, 'incomplete_actor_blocks_activation');
production_readiness_expect($report['actor_denied'] === 1, 'denied_identity_counted');
production_readiness_expect($report['actor_identity_denied'] === 1 && $report['controlled_enforcement_ready'] === false, 'identity_denial_blocks_controlled_enforcement');
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
$managed_gaps = ProductionReadinessReport::compile($complete, array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), array(
	'facility_total' => 2, 'facility_complete' => 1, 'facility_command_center_ready' => 1,
	'staff_total' => 3, 'staff_ready' => 2,
	'managed_gap_counts' => array('facility_address' => 1, 'facility_command_center' => 1, 'staff_title' => 9),
));
$managed_rendered = implode("\n", ProductionReadinessReport::lines($managed_gaps));
production_readiness_expect($managed_gaps['managed_data_ready'] === false && $managed_gaps['controlled_enforcement_ready'] === false, 'managed_operational_gap_blocks_both_early_gates');
production_readiness_expect($managed_gaps['managed_gap_counts']['staff_title'] === 3 && strpos($managed_rendered, 'MANAGED_GAP_FACILITY_ADDRESS=1') !== false, 'managed_gap_counts_are_safe_exact_and_clamped');
production_readiness_expect($managed_gaps['managed_gap_total'] === 5 && strpos($managed_rendered, 'MANAGED_GAP_TOTAL=5') !== false, 'managed_gap_total_is_exact_and_rendered');
$managed_actor = $complete;
$managed_actor[] = array(
	'complete' => false, 'actor_type' => 'command_center', 'safe_error_code' => 'profile_prerequisites_missing',
	'missing_fields' => array('facility_address'), 'schema_gaps' => array(), 'remediation_mode' => 'managed',
	'self_service_fields' => array(), 'managed_fields' => array('facility_address'), 'audit_denial_reason' => '',
);
$managed_actor_report = ProductionReadinessReport::compile($managed_actor, array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), $operations_ready);
production_readiness_expect($managed_actor_report['actor_managed_gap_count'] === 1 && $managed_actor_report['managed_data_ready'] === false, 'managed_actor_gap_blocks_managed_data_gate');
$unsafe_reason = $complete;
$unsafe_reason[] = array(
	'complete' => false, 'actor_type' => 'denied', 'safe_error_code' => 'actor_denied',
	'missing_fields' => array(), 'schema_gaps' => array(), 'remediation_mode' => 'none',
	'audit_denial_reason' => 'attacker-controlled-value',
);
$unsafe_reason_report = ProductionReadinessReport::compile($unsafe_reason, array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), $operations_ready);
production_readiness_expect($unsafe_reason_report['actor_identity_denied'] === 1 && $unsafe_reason_report['controlled_enforcement_ready'] === false, 'unknown_denial_reason_fails_closed');
production_readiness_expect($unsafe_reason_report['actor_profile_evaluated'] === 3, 'identity_denial_not_misreported_as_profile_evaluation');
$clamped = ProductionReadinessReport::compile($complete, array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), array('facility_total' => 1, 'facility_complete' => 9, 'facility_command_center_ready' => 9, 'staff_total' => 1, 'staff_ready' => 9));
production_readiness_expect($clamped['facility_complete'] === 1 && $clamped['facility_command_center_ready'] === 1 && $clamped['staff_ready'] === 1, 'operational_counts_are_clamped_to_totals');
$empty = ProductionReadinessReport::compile(array(), array(), array('ready' => true, 'private' => true, 'mode_0700' => true, 'owner_ready' => true), array());
production_readiness_expect($empty['activation_ready'] === false, 'empty_actor_set_fails_closed');

echo "PRODUCTION_READINESS_UNIT_PASSED={$passed}\n";
echo "PRODUCTION_READINESS_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
