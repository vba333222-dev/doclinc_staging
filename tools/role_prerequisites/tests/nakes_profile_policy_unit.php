<?php
define('BASEPATH', __DIR__ . '/');
require_once dirname(__DIR__, 3) . '/application/libraries/Nakes_profile_readiness_policy.php';

$passed = 0;
$failed = 0;
function nakes_profile_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; echo "FAIL {$label}\n";
}

function nakes_profile_fixture(array $changes = array())
{
	return array_merge(array(
		'name' => 'Nakes Uji', 'title' => 'dr.', 'birthdate' => '1990-01-01',
		'gender' => 'Perempuan', 'profession' => 'Dokter', 'phone' => '081234567890',
		'registration_number' => 'SIP-TEST', 'registration_expires_at' => '2026-12-31',
		'account_state' => 'linked', 'staff_status' => 'aktif', 'account_status' => 'aktif',
		'facility_status' => 'aktif',
	), $changes);
}

$policy = new Nakes_profile_readiness_policy('2026-08-10', 90);
$active = $policy->evaluate(nakes_profile_fixture());
nakes_profile_expect($active['state'] === Nakes_profile_readiness_policy::COMPLETE && $active['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_ACTIVE, 'active_sip_complete');

$unlinked = $policy->evaluate(nakes_profile_fixture(array('account_state' => 'unlinked')));
nakes_profile_expect($unlinked['state'] === Nakes_profile_readiness_policy::INCOMPLETE && in_array('staff_link', $unlinked['missing_fields'], true), 'personal_link_required');

$missing = $policy->evaluate(nakes_profile_fixture(array('registration_number' => '', 'registration_expires_at' => '')));
nakes_profile_expect($missing['state'] === Nakes_profile_readiness_policy::SIP_MISSING && $missing['operationally_ready'] === false, 'sip_missing_explicit');

$post_migration_nulls = $policy->evaluate(nakes_profile_fixture(array('title' => null, 'registration_expires_at' => null)));
nakes_profile_expect($post_migration_nulls['state'] === Nakes_profile_readiness_policy::SIP_MISSING
	&& $post_migration_nulls['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_MISSING
	&& $post_migration_nulls['operationally_ready'] === false
	&& in_array('title', $post_migration_nulls['missing_fields'], true)
	&& in_array('registration_expiry', $post_migration_nulls['missing_fields'], true), 'post_migration_null_personnel_data_remains_not_ready');

$expired = $policy->evaluate(nakes_profile_fixture(array('registration_expires_at' => '2026-08-09')));
nakes_profile_expect($expired['state'] === Nakes_profile_readiness_policy::SIP_EXPIRED && $expired['operationally_ready'] === false, 'sip_expired_explicit');

$expiring = $policy->evaluate(nakes_profile_fixture(array('registration_expires_at' => '2026-09-01')));
nakes_profile_expect($expiring['state'] === Nakes_profile_readiness_policy::SIP_EXPIRING && $expiring['warning'] === true && $expiring['operationally_ready'] === true, 'sip_expiring_warning');

$sip_boundaries = array(
	'yesterday' => array('2026-08-09', Nakes_profile_readiness_policy::SIP_STATE_EXPIRED, Nakes_profile_readiness_policy::SIP_EXPIRED, false),
	'today' => array('2026-08-10', Nakes_profile_readiness_policy::SIP_STATE_EXPIRING, Nakes_profile_readiness_policy::SIP_EXPIRING, true),
	'today_plus_1' => array('2026-08-11', Nakes_profile_readiness_policy::SIP_STATE_EXPIRING, Nakes_profile_readiness_policy::SIP_EXPIRING, true),
	'today_plus_89' => array('2026-11-07', Nakes_profile_readiness_policy::SIP_STATE_EXPIRING, Nakes_profile_readiness_policy::SIP_EXPIRING, true),
	'today_plus_90' => array('2026-11-08', Nakes_profile_readiness_policy::SIP_STATE_EXPIRING, Nakes_profile_readiness_policy::SIP_EXPIRING, true),
	'today_plus_91' => array('2026-11-09', Nakes_profile_readiness_policy::SIP_STATE_ACTIVE, Nakes_profile_readiness_policy::COMPLETE, true),
);
foreach ($sip_boundaries as $label => $expected) {
	$boundary = $policy->evaluate(nakes_profile_fixture(array('registration_expires_at' => $expected[0])));
	nakes_profile_expect($boundary['sip_state'] === $expected[1]
		&& $boundary['state'] === $expected[2]
		&& $boundary['operationally_ready'] === $expected[3], 'sip_boundary_' . $label);
}

$malformed = $policy->evaluate(nakes_profile_fixture(array('registration_expires_at' => '2026-02-30')));
nakes_profile_expect($malformed['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_INVALID
	&& $malformed['state'] === Nakes_profile_readiness_policy::INCOMPLETE
	&& $malformed['operationally_ready'] === false, 'malformed_sip_expiry_is_invalid');

$no_nip = nakes_profile_fixture();
$no_nip['nip'] = null;
nakes_profile_expect($policy->evaluate($no_nip)['complete'] === true, 'nip_not_part_of_universal_contract');

$inactive = $policy->evaluate(nakes_profile_fixture(array('staff_status' => 'nonaktif')));
nakes_profile_expect($inactive['complete'] === false && in_array('staff_status', $inactive['missing_fields'], true), 'inactive_staff_not_ready');

$mandatory_cases = array(
	'name' => array('name', ''),
	'title' => array('title', ''),
	'birthdate' => array('birthdate', ''),
	'gender' => array('gender', ''),
	'profession' => array('profession', ''),
	'phone' => array('phone', ''),
	'facility' => array('facility_status', 'nonaktif'),
	'account_status' => array('account_status', 'nonaktif'),
);
foreach ($mandatory_cases as $label => $case) {
	$mandatory = $policy->evaluate(nakes_profile_fixture(array($case[0] => $case[1])));
	nakes_profile_expect($mandatory['operationally_ready'] === false, 'mandatory_' . $label . '_blocks_readiness');
}

echo "NAKES_PROFILE_POLICY_UNIT_PASSED={$passed}\n";
echo "NAKES_PROFILE_POLICY_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
