<?php
if (!defined('BASEPATH')) { define('BASEPATH', __DIR__ . '/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }

require_once APPPATH . 'libraries/Nakes_personal_account_policy.php';
require_once APPPATH . 'libraries/Password_strength_policy.php';
require_once APPPATH . 'libraries/First_login_password_policy.php';
require_once APPPATH . 'libraries/First_login_token_policy.php';
require_once APPPATH . 'helpers/nakes_account_presentation_helper.php';

$passed = 0;
$failed = 0;
function personal_nakes_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; echo "FAIL {$label}\n";
}

function personal_evidence(array $overrides = array())
{
	return array_merge(array(
		'staff_exists' => true,
		'staff_status' => 'aktif',
		'staff_facility' => 'PKM01',
		'facility_status' => 'aktif',
		'user_id' => 51,
		'user_exists' => true,
		'user_role' => 'dokter',
		'user_status' => 'aktif',
		'user_facility' => 'PKM01',
		'active_link_count' => 1,
		'is_command_center' => false,
		'must_change_password' => 0,
		'password_changed_at' => '2026-08-10 09:00:00',
		'unlinked_account_available' => false,
	), $overrides);
}

$account = new Nakes_personal_account_policy();
personal_nakes_expect($account->evaluate(personal_evidence(array('user_id' => 0, 'user_exists' => false)))['state'] === Nakes_personal_account_policy::STAFF_ONLY, 'staff_only_state');
personal_nakes_expect($account->evaluate(personal_evidence(array('user_id' => 0, 'user_exists' => false, 'unlinked_account_available' => true)))['state'] === Nakes_personal_account_policy::STAFF_WITH_UNLINKED_ACCOUNT, 'staff_with_unlinked_account_state');
personal_nakes_expect($account->evaluate(personal_evidence(array('must_change_password' => 1, 'password_changed_at' => null)))['state'] === Nakes_personal_account_policy::FIRST_LOGIN_PENDING, 'first_login_pending_state');
personal_nakes_expect($account->evaluate(personal_evidence(array('must_change_password' => 1)))['state'] === Nakes_personal_account_policy::ADMIN_RESET_PENDING, 'admin_reset_pending_state');
personal_nakes_expect($account->evaluate(personal_evidence())['state'] === Nakes_personal_account_policy::ACTIVE, 'active_state');
personal_nakes_expect($account->evaluate(personal_evidence(array('active_link_count' => 2)))['state'] === Nakes_personal_account_policy::INVALID, 'duplicate_active_link_fails_closed');
personal_nakes_expect($account->evaluate(personal_evidence(array('user_facility' => 'PKM02')))['state'] === Nakes_personal_account_policy::INVALID, 'facility_mismatch_fails_closed');
personal_nakes_expect($account->evaluate(personal_evidence(array('facility_status' => 'nonaktif')))['state'] === Nakes_personal_account_policy::INVALID, 'inactive_facility_fails_closed');
personal_nakes_expect($account->evaluate(personal_evidence(array('staff_status' => 'nonaktif')))['state'] === Nakes_personal_account_policy::INVALID, 'inactive_staff_fails_closed');
personal_nakes_expect($account->evaluate(personal_evidence(array('user_status' => 'nonaktif')))['state'] === Nakes_personal_account_policy::INVALID, 'inactive_user_fails_closed');
personal_nakes_expect($account->evaluate(personal_evidence(array('user_exists' => false)))['state'] === Nakes_personal_account_policy::INVALID, 'missing_linked_user_fails_closed');
personal_nakes_expect($account->evaluate(personal_evidence(array('is_command_center' => true)))['state'] === Nakes_personal_account_policy::INVALID, 'command_center_link_fails_closed');
personal_nakes_expect($account->evaluate(personal_evidence(array('user_role' => 'warga')))['state'] === Nakes_personal_account_policy::INVALID, 'non_dokter_link_fails_closed');
$created = $account->evaluate(personal_evidence(array('must_change_password' => 1, 'password_changed_at' => null)));
personal_nakes_expect($created['state'] === Nakes_personal_account_policy::FIRST_LOGIN_PENDING, 'transition_staff_only_to_first_login_pending');
$linked_existing = $account->evaluate(personal_evidence());
personal_nakes_expect($linked_existing['state'] === Nakes_personal_account_policy::ACTIVE, 'transition_unlinked_account_to_raw_credential_state');
$first_activated = $account->evaluate(personal_evidence(array('must_change_password' => 0, 'password_changed_at' => '2026-08-10 10:00:00')));
personal_nakes_expect($first_activated['state'] === Nakes_personal_account_policy::ACTIVE, 'transition_first_login_pending_to_active');
$admin_reset = $account->evaluate(personal_evidence(array('must_change_password' => 1, 'password_changed_at' => '2026-08-10 10:00:00')));
personal_nakes_expect($admin_reset['state'] === Nakes_personal_account_policy::ADMIN_RESET_PENDING, 'transition_active_to_admin_reset_pending');
$reset_completed = $account->evaluate(personal_evidence(array('must_change_password' => 0, 'password_changed_at' => '2026-08-10 11:00:00')));
personal_nakes_expect($reset_completed['state'] === Nakes_personal_account_policy::ACTIVE, 'transition_admin_reset_pending_to_active');
$first_login_reset = $account->evaluate(personal_evidence(array('must_change_password' => 1, 'password_changed_at' => null)));
personal_nakes_expect($first_login_reset['state'] === Nakes_personal_account_policy::FIRST_LOGIN_PENDING, 'admin_reset_before_first_activation_stays_first_login_pending');
$invalid_before = $account->evaluate(personal_evidence(array('active_link_count' => 2, 'must_change_password' => 1)));
$invalid_after = $account->evaluate(personal_evidence(array('active_link_count' => 2, 'must_change_password' => 0, 'password_changed_at' => '2026-08-10 12:00:00')));
personal_nakes_expect($invalid_before['state'] === Nakes_personal_account_policy::INVALID && $invalid_after['state'] === Nakes_personal_account_policy::INVALID, 'illegal_invalid_credential_transition_rejected');
personal_nakes_expect(doclinc_nakes_account_label(Nakes_personal_account_policy::FIRST_LOGIN_PENDING) === 'Menunggu aktivasi pertama', 'first_login_presentation_label');
personal_nakes_expect(doclinc_nakes_account_label(Nakes_personal_account_policy::ADMIN_RESET_PENDING) === 'Menunggu pembuatan password baru', 'admin_reset_presentation_label');
personal_nakes_expect(strpos(doclinc_nakes_account_label(Nakes_personal_account_policy::INVALID), 'invalid') === false, 'machine_state_not_exposed_by_presentation');

$strength = new Password_strength_policy();
personal_nakes_expect($strength->validate('Panjang9!Aman', 'Panjang9!Aman') === null, 'valid_password_accepted');
personal_nakes_expect($strength->validate('Panjang9!Aman', 'Berbeda9!Aman') === 'confirmation_mismatch', 'confirmation_mismatch_rejected');
personal_nakes_expect($strength->validate('Password1!') === 'placeholder_not_allowed', 'default_placeholder_rejected');
personal_nakes_expect($strength->validate(str_repeat('A', 72) . 'a1!') === 'invalid_length', 'maximum_length_enforced');

$temporary = 'Sementara9!Unik';
$temporary_hash = password_hash($temporary, PASSWORD_DEFAULT);
$first_login = new First_login_password_policy();
$identity = array('username' => 'nakes.uji', 'email' => 'nakes@example.invalid', 'no_hp' => '081200000001');
personal_nakes_expect($first_login->validate($temporary, $temporary, $identity, $temporary_hash) === 'current_password_reused', 'temporary_credential_cannot_be_reused');
personal_nakes_expect($first_login->validate('Baru9!SangatAman', 'Baru9!SangatAman', $identity, $temporary_hash) === null, 'new_personal_password_accepted');

$tokens = new First_login_token_policy();
$issued = $tokens->issue(51, str_repeat('a', 32), 1000);
personal_nakes_expect(is_array($issued) && $tokens->validate($issued['token'], $issued['state'], 51, str_repeat('a', 32), 1001), 'activation_token_valid_once_context');
personal_nakes_expect(!$tokens->validate($issued['token'], $issued['state'], 52, str_repeat('a', 32), 1001), 'activation_token_rejects_other_user');
personal_nakes_expect(!$tokens->validate($issued['token'], $issued['state'], 51, str_repeat('b', 32), 1001), 'activation_token_rejects_other_session_binding');
personal_nakes_expect(!$tokens->validate($issued['token'], $issued['state'], 51, str_repeat('a', 32), 1600), 'activation_token_expires');
$consumable_state = $issued['state'];
personal_nakes_expect($tokens->consume($issued['token'], $consumable_state, 51, str_repeat('a', 32), 1001), 'activation_token_first_consume_succeeds');
personal_nakes_expect($consumable_state === null, 'activation_token_state_destroyed_on_consume');
personal_nakes_expect(!$tokens->consume($issued['token'], $consumable_state, 51, str_repeat('a', 32), 1001), 'activation_token_replay_fails');

echo "PERSONAL_NAKES_UNIT_PASSED={$passed}\n";
echo "PERSONAL_NAKES_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
