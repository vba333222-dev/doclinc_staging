<?php
if (!defined('BASEPATH')) { define('BASEPATH', __DIR__ . '/'); }
require_once dirname(__DIR__, 3) . '/application/libraries/Nakes_credential_policy.php';

$passed = 0;
$failed = 0;
function credential_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; echo "FAIL {$label}\n";
}

$policy = new Nakes_credential_policy();
$changed = '2026-08-03 03:30:00';

credential_expect($policy->state('warga', 0, null) === Nakes_credential_policy::NOT_APPLICABLE, 'warga_not_subject_to_nakes_credential_gate');
credential_expect($policy->state('dokter', 1, null) === Nakes_credential_policy::FIRST_LOGIN_PENDING, 'temporary_password_never_changed_is_first_login_pending');
credential_expect($policy->state('dokter', 0, null) === Nakes_credential_policy::FIRST_LOGIN_PENDING, 'legacy_default_without_change_evidence_fails_closed');
credential_expect($policy->state('dokter', 1, $changed) === Nakes_credential_policy::ADMIN_RESET_PENDING, 'admin_reset_preserves_prior_change_evidence');
credential_expect($policy->state('dokter', 0, $changed) === Nakes_credential_policy::ACTIVE, 'verified_user_password_change_is_active');
credential_expect($policy->state('dokter', 0, 'invalid-time') === Nakes_credential_policy::FIRST_LOGIN_PENDING, 'malformed_change_evidence_fails_closed');
credential_expect($policy->requiresChange('dokter', 0, null) === true, 'unverified_legacy_nakes_must_change_password');
credential_expect($policy->requiresChange('dokter', 0, $changed) === false, 'verified_nakes_not_forced_again');

echo "NAKES_CREDENTIAL_POLICY_UNIT_PASSED={$passed}\n";
echo "NAKES_CREDENTIAL_POLICY_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
