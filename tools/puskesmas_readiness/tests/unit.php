<?php
define('BASEPATH', __DIR__ . '/');
require_once dirname(__DIR__, 3) . '/application/libraries/Puskesmas_data_readiness.php';

$passed = 0;
$failed = 0;
function readiness_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$label}\n";
		return;
	}
	$failed++;
	echo "FAIL {$label}\n";
}

function readiness_staff($changes = array())
{
	return (object) array_merge(array(
		'nama' => 'Nakes Contoh', 'gelar' => 'dr.',
		'no_hp' => '081234567890', 'status' => 'aktif',
		'profesi' => 'Dokter',
		'nomor_sip' => 'SIP-001',
		'sip_expired_at' => '2027-12-31',
		'account_name' => 'Nakes Contoh', 'account_phone' => '081234567890',
		'account_birthdate' => '1990-01-01', 'account_gender' => 'Laki-laki', 'account_status' => 'aktif',
		'personal_account_state' => 'linked',
	), $changes);
}

$service = new Puskesmas_data_readiness();
$complete = $service->summarize(array('managed_fields' => array()), array(readiness_staff()));
readiness_expect($complete['complete'] === true && $complete['staff_ready'] === 1, 'complete_tenant_ready');
readiness_expect($complete['attention_count'] === 0, 'complete_tenant_zero_attention');

$gaps = $service->summarize(
	array('managed_fields' => array('facility_address')),
	array(
		readiness_staff(array('nomor_sip' => '')),
		readiness_staff(array('personal_account_state' => 'unlinked')),
		readiness_staff(array('personal_account_state' => 'invalid', 'account_phone' => '')),
	)
);
readiness_expect($gaps['facility_complete'] === false && $gaps['facility_missing_count'] === 1, 'facility_gap_counted');
readiness_expect($gaps['staff_missing_sip'] === 1, 'missing_sip_counted');
readiness_expect($gaps['staff_unlinked'] === 1 && $gaps['staff_invalid_account'] === 1, 'account_states_counted');
readiness_expect($gaps['staff_missing_core_data'] === 1, 'missing_core_data_counted');
readiness_expect($gaps['staff_ready'] === 0 && $gaps['complete'] === false, 'incomplete_tenant_not_ready');
readiness_expect($gaps['attention_count'] === 5, 'attention_total_is_explicit');

$expiring = $service->summarize(array('managed_fields' => array()), array(readiness_staff(array('sip_expired_at' => date('Y-m-d', strtotime('+30 days'))))));
readiness_expect($expiring['staff_sip_expiring'] === 1 && $expiring['staff_ready'] === 1, 'expiring_sip_is_ready_with_warning');

$empty = $service->summarize(array('managed_fields' => array()), array());
readiness_expect($empty['complete'] === false && $empty['staff_total'] === 0, 'empty_staff_roster_not_complete');

echo "PUSKESMAS_READINESS_UNIT_PASSED={$passed}\n";
echo "PUSKESMAS_READINESS_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
