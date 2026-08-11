<?php
define('BASEPATH', __DIR__ . '/');

$root = dirname(__DIR__, 3);
$public_source = file_get_contents($root . '/application/libraries/Password_strength_policy.php');
$admin_source = file_get_contents($root . '/admin_menu/application/libraries/Password_strength_policy.php');
require_once $root . '/application/libraries/Password_strength_policy.php';

$passed = 0;
$failed = 0;
function password_expect($condition, $label)
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

$policy = new Password_strength_policy();
password_expect(is_string($public_source)
	&& is_string($admin_source)
	&& strpos($admin_source, "require_once dirname(APPPATH, 2) . '/application/libraries/Password_strength_policy.php';") !== false
	&& strpos($admin_source, 'class Password_strength_policy') === false,
	'admin_policy_delegates_to_single_canonical_policy');
password_expect($policy->validate('Abcdef1!', 'Abcdef1!') === null, 'minimum_valid_password');
password_expect($policy->validate('Ab1!defghijklmnopqrstuvwxyz', 'Ab1!defghijklmnopqrstuvwxyz') === null, 'long_valid_password');
password_expect($policy->validate('Abcde1!', 'Abcde1!') === 'invalid_length', 'seven_characters_rejected');
password_expect($policy->validate(str_repeat('A', 73), str_repeat('A', 73)) === 'invalid_length', 'seventy_three_characters_rejected');
password_expect($policy->validate('abcdef1!', 'abcdef1!') === 'uppercase_required', 'uppercase_required');
password_expect($policy->validate('ABCDEF1!', 'ABCDEF1!') === 'lowercase_required', 'lowercase_required');
password_expect($policy->validate('Abcdefg!', 'Abcdefg!') === 'number_required', 'number_required');
password_expect($policy->validate('Abcdef12', 'Abcdef12') === 'special_required', 'special_character_required');
password_expect($policy->validate('Abc 12!!', 'Abc 12!!') === 'whitespace_not_allowed', 'whitespace_rejected');
password_expect($policy->validate("Abc\n12!!", "Abc\n12!!") === 'whitespace_not_allowed', 'control_character_rejected');
password_expect($policy->validate('Abcdef1!', 'Abcdef2!') === 'confirmation_mismatch', 'confirmation_must_match');
password_expect($policy->validate(array('invalid'), 'Abcdef1!') === 'invalid_payload', 'non_string_payload_rejected');
password_expect($policy->message('uppercase_required') === 'Password harus memiliki setidaknya satu huruf besar.', 'safe_specific_message');

echo "PASSWORD_POLICY_UNIT_PASSED={$passed}\n";
echo "PASSWORD_POLICY_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
