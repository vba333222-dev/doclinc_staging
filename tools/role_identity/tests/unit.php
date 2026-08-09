<?php
define('BASEPATH', __DIR__ . '/');
require_once dirname(__DIR__, 3) . '/application/libraries/Role_identity_policy.php';

$passed = 0;
$failed = 0;
function identity_expect($condition, $label)
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

$policy = new Role_identity_policy();
$valid = $policy->warga(array(
	'nik' => '3671 0101 0101 0001',
	'nomor_kk' => '3671010101010002',
	'nomor_bpjs_kis' => '0001234567890',
));
identity_expect($valid['valid'] === true, 'valid_warga_identity');
identity_expect($valid['values']['nik'] === '3671010101010001', 'nik_normalized');
identity_expect($valid['values']['nomor_kk'] === '3671010101010002', 'family_card_normalized');
identity_expect($valid['values']['nomor_bpjs_kis'] === '0001234567890', 'bpjs_leading_zero_preserved');

$optional = $policy->warga(array('nik' => '3671010101010001', 'nomor_kk' => '', 'nomor_bpjs_kis' => ''));
identity_expect($optional['valid'] === true && $optional['values']['nomor_kk'] === null && $optional['values']['nomor_bpjs_kis'] === null, 'optional_warga_identifiers_nullable');
$invalid = $policy->warga(array('nik' => '123', 'nomor_kk' => 'abc', 'nomor_bpjs_kis' => 'abc'));
identity_expect($invalid['valid'] === false && count($invalid['field_errors']) === 3, 'invalid_supplied_warga_fields_explicit');
identity_expect($policy->nip('198001012010011001')['valid'] === true, 'valid_nip');
identity_expect($policy->nip('19800101201001100')['valid'] === false, 'short_nip_rejected');
identity_expect($policy->mask('3671010101010001') === '••••••••••••0001', 'identifier_masked');

echo "ROLE_IDENTITY_UNIT_PASSED={$passed}\n";
echo "ROLE_IDENTITY_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
