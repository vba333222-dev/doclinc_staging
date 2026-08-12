<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
require_once dirname(__DIR__, 3) . '/application/libraries/Visit_vital_signs_policy.php';

$passed = 0;
$failed = 0;

function phase6_unit_expect($condition, $name)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$name}\n";
		return;
	}
	$failed++;
	fwrite(STDERR, "FAIL {$name}\n");
}

$policy = new Visit_vital_signs_policy();
$valid = $policy->normalize(array(
	'systolic' => '120',
	'diastolic' => '80',
	'pulse' => '72',
	'respiratory_rate' => '18',
	'temperature_c' => '36.7',
	'oxygen_saturation' => '98',
	'notes' => 'Kondisi stabil',
));
phase6_unit_expect(!empty($valid['valid']), 'complete_vital_signs_valid');
phase6_unit_expect($valid['values']['systolic'] === 120 && $valid['values']['temperature_c'] === 36.7, 'vital_signs_normalized');
phase6_unit_expect($valid['values']['notes'] === 'Kondisi stabil', 'observation_notes_preserved');

$partial = $policy->normalize(array('oxygen_saturation' => '97'));
phase6_unit_expect(!empty($partial['valid']) && $partial['values']['systolic'] === null, 'optional_values_may_be_null');
phase6_unit_expect(empty($policy->normalize(array())['valid']), 'empty_measurement_denied');
phase6_unit_expect(empty($policy->normalize(array('systolic' => '49'))['valid']), 'systolic_below_range_denied');
phase6_unit_expect(empty($policy->normalize(array('diastolic' => '201'))['valid']), 'diastolic_above_range_denied');
phase6_unit_expect(empty($policy->normalize(array('pulse' => '72.5'))['valid']), 'integer_measurement_fraction_denied');
phase6_unit_expect(empty($policy->normalize(array('temperature_c' => '45.1'))['valid']), 'temperature_above_range_denied');
phase6_unit_expect(empty($policy->normalize(array('oxygen_saturation' => 'abc'))['valid']), 'non_numeric_measurement_denied');
phase6_unit_expect(empty($policy->normalize(array('notes' => str_repeat('x', 1001)))['valid']), 'long_observation_denied');
phase6_unit_expect(!empty($policy->normalize(array('notes' => 'Tidak dapat diukur'))['valid']), 'observation_only_measurement_allowed');

echo "PHASE6_UNIT_ASSERTIONS=" . ($passed + $failed) . "\n";
echo "PHASE6_UNIT_PASS={$passed}\nPHASE6_UNIT_FAIL={$failed}\n";
exit($failed === 0 ? 0 : 1);
