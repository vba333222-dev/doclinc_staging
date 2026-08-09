<?php
define('BASEPATH', __DIR__ . '/');
require_once dirname(__DIR__, 3) . '/application/helpers/profile_readiness_presentation_helper.php';

$passed = 0;
$failed = 0;
function profile_presentation_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; echo "FAIL {$label}\n";
}

$profile_states = array(
	'SIP_MISSING' => 'Belum lengkap',
	'SIP_EXPIRING' => 'Lengkap, SIP mendekati kedaluwarsa',
	'SIP_EXPIRED' => 'Belum lengkap, SIP kedaluwarsa',
	'INCOMPLETE' => 'Belum lengkap',
	'COMPLETE' => 'Lengkap',
);
foreach ($profile_states as $raw => $label) {
	profile_presentation_expect(doclinc_profile_readiness_label($raw) === $label, 'profile_state_' . strtolower($raw) . '_is_presented');
}

$sip_states = array(
	'MISSING' => 'Belum tercatat',
	'ACTIVE' => 'Aktif',
	'EXPIRING' => 'Mendekati kedaluwarsa',
	'EXPIRED' => 'Kedaluwarsa',
	'INVALID' => 'Perlu diperiksa',
);
foreach ($sip_states as $raw => $label) {
	profile_presentation_expect(doclinc_sip_state_label($raw) === $label, 'sip_state_' . strtolower($raw) . '_is_presented');
}

profile_presentation_expect(doclinc_profile_readiness_label('UNEXPECTED') === 'Belum tersedia', 'unknown_profile_state_has_safe_label');
profile_presentation_expect(doclinc_sip_state_label('UNEXPECTED') === 'Belum tersedia', 'unknown_sip_state_has_safe_label');

echo "PROFILE_PRESENTATION_UNIT_PASSED={$passed}\n";
echo "PROFILE_PRESENTATION_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
