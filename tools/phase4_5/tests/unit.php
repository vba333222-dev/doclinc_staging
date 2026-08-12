<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }

require_once APPPATH . 'libraries/Session_binding_policy.php';
require_once APPPATH . 'libraries/Care_team_policy.php';

$passed = 0;
$failed = 0;

function phase45_expect($condition, $name)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$name}\n"; return; }
	$failed++; echo "FAIL {$name}\n";
}

$session = new Session_binding_policy();
$first = $session->issueToken();
$second = $session->issueToken();
phase45_expect(preg_match('/\A[a-f0-9]{64}\z/', $first) === 1, 'session_token_is_256_bit_hex');
phase45_expect(!hash_equals($first, $second), 'session_tokens_are_distinct');
phase45_expect($session->matches($first, hash('sha256', $first)), 'session_hash_matches');
phase45_expect(!$session->matches($second, hash('sha256', $first)), 'stale_session_hash_denied');
phase45_expect(!$session->matches('invalid', hash('sha256', $first)), 'malformed_session_token_denied');

$care = new Care_team_policy();
$personal_doctor = array('valid' => true, 'account_type' => 'personal', 'user_id' => 21, 'staff_id' => 31, 'user_status' => 'aktif', 'staff_status' => 'aktif', 'puskesmas_code' => 'PKM01', 'staff_profesi' => 'Dokter Umum');
$personal_nakes = array('valid' => true, 'account_type' => 'personal', 'user_id' => 22, 'staff_id' => 32, 'user_status' => 'aktif', 'staff_status' => 'aktif', 'puskesmas_code' => 'PKM01', 'staff_profesi' => 'Perawat');
$command = array('valid' => true, 'account_type' => 'command_center', 'is_command_center' => true, 'user_id' => 10, 'puskesmas_code' => 'PKM01');
phase45_expect($care->mode('visit') === Care_team_policy::VISIT, 'visit_mode_allowed');
phase45_expect($care->mode('non_visit') === Care_team_policy::NON_VISIT, 'non_visit_mode_allowed');
phase45_expect($care->mode('other') === null, 'unknown_mode_denied');
phase45_expect($care->responsibleDoctorEligible($personal_doctor, 'PKM01'), 'personal_doctor_responsible_allowed');
phase45_expect(!$care->responsibleDoctorEligible($personal_nakes, 'PKM01'), 'non_doctor_responsible_denied');
phase45_expect($care->visitPerformerEligible($personal_nakes, 'PKM01'), 'personal_nakes_performer_allowed');
phase45_expect(!$care->visitPerformerEligible($personal_doctor, 'PKM01'), 'personal_doctor_performer_denied');
phase45_expect(!$care->personalEligible($personal_nakes, 'PKM02'), 'cross_facility_personal_denied');
phase45_expect($care->commandCenterEligible($command, 'PKM01'), 'command_center_coordinator_allowed');
phase45_expect(!$care->personalEligible($command, 'PKM01'), 'command_center_performer_denied');
phase45_expect(!$care->visitPerformerEligible($command, 'PKM01'), 'command_center_visit_performer_denied');

$hook = array();
require APPPATH . 'config/hooks.php';
$gate_order = array_map(function ($entry) { return $entry['class']; }, $hook['post_controller_constructor']);

function phase45_gate_result(array $gate_order, array $state)
{
	foreach ($gate_order as $gate) {
		if ($gate === 'Session_binding_gate') {
			if (!empty($state['normal_session']) && !empty($state['session_feature']) && empty($state['binding_valid'])) {
				return 'session_rejected';
			}
			continue;
		}
		if ($gate === 'Password_change_gate') {
			if (!empty($state['normal_session']) && !empty($state['password_pending'])) {
				return 'password_remediation';
			}
			continue;
		}
		if ($gate === 'Role_prerequisite_gate' && !empty($state['normal_session'])
			&& !empty($state['prerequisite_feature']) && !empty($state['profile_incomplete'])) {
			return 'profile_remediation';
		}
	}
	return 'controller';
}

phase45_expect(phase45_gate_result($gate_order, array('normal_session' => true, 'session_feature' => true, 'binding_valid' => false, 'password_pending' => true)) === 'session_rejected', 'revoked_password_pending_session_rejected_first');
phase45_expect(phase45_gate_result($gate_order, array('normal_session' => true, 'session_feature' => true, 'binding_valid' => false, 'prerequisite_feature' => true, 'profile_incomplete' => true)) === 'session_rejected', 'revoked_profile_incomplete_session_rejected_first');
phase45_expect(phase45_gate_result($gate_order, array('normal_session' => true, 'session_feature' => true, 'binding_valid' => true, 'password_pending' => true)) === 'password_remediation', 'valid_binding_password_gate_runs');
phase45_expect(phase45_gate_result($gate_order, array('normal_session' => true, 'session_feature' => true, 'binding_valid' => true, 'prerequisite_feature' => true, 'profile_incomplete' => true)) === 'profile_remediation', 'valid_binding_prerequisite_gate_runs');
phase45_expect(phase45_gate_result($gate_order, array('normal_session' => false, 'session_feature' => true, 'binding_valid' => false, 'password_pending' => true, 'profile_incomplete' => true)) === 'controller', 'activation_only_session_unaffected');
phase45_expect(phase45_gate_result($gate_order, array('normal_session' => true, 'session_feature' => false, 'binding_valid' => false)) === 'controller', 'session_feature_off_preserves_legacy_flow');

echo "PHASE45_UNIT_PASSED={$passed}\nPHASE45_UNIT_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
