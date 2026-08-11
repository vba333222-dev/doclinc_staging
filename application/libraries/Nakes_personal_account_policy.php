<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Nakes_personal_account_policy
{
	const STAFF_ONLY = 'staff_only';
	const STAFF_WITH_UNLINKED_ACCOUNT = 'staff_with_unlinked_account';
	const FIRST_LOGIN_PENDING = 'first_login_pending';
	const ACTIVE = 'active';
	const ADMIN_RESET_PENDING = 'admin_reset_pending';
	const INVALID = 'invalid';

	public function evaluate(array $evidence)
	{
		$user_id = (int) ($evidence['user_id'] ?? 0);
		if ($user_id < 1) {
			return $this->result(
				!empty($evidence['unlinked_account_available'])
					? self::STAFF_WITH_UNLINKED_ACCOUNT
					: self::STAFF_ONLY,
				true,
				'account_unlinked'
			);
		}

		$staff_facility = $this->normalizeFacility($evidence['staff_facility'] ?? '');
		$user_facility = $this->normalizeFacility($evidence['user_facility'] ?? '');
		$valid = !empty($evidence['staff_exists'])
			&& !empty($evidence['user_exists'])
			&& empty($evidence['is_command_center'])
			&& (string) ($evidence['staff_status'] ?? '') === 'aktif'
			&& (string) ($evidence['facility_status'] ?? '') === 'aktif'
			&& (string) ($evidence['user_role'] ?? '') === 'dokter'
			&& (string) ($evidence['user_status'] ?? '') === 'aktif'
			&& (int) ($evidence['active_link_count'] ?? 0) === 1
			&& $staff_facility !== ''
			&& hash_equals($staff_facility, $user_facility);
		if (!$valid) {
			return $this->result(self::INVALID, false, 'identity_conflict');
		}

		require_once __DIR__ . '/Nakes_credential_policy.php';
		$credential = new Nakes_credential_policy();
		$credential_state = $credential->state(
			(string) ($evidence['user_role'] ?? ''),
			(int) ($evidence['must_change_password'] ?? 0),
			$evidence['password_changed_at'] ?? null
		);
		if ($credential_state === Nakes_credential_policy::FIRST_LOGIN_PENDING) {
			return $this->result(self::FIRST_LOGIN_PENDING, true, 'first_activation_required');
		}
		if ($credential_state === Nakes_credential_policy::ADMIN_RESET_PENDING) {
			return $this->result(self::ADMIN_RESET_PENDING, true, 'password_reset_completion_required');
		}
		if ($credential_state === Nakes_credential_policy::ACTIVE) {
			return $this->result(self::ACTIVE, true, 'account_active');
		}

		return $this->result(self::INVALID, false, 'credential_state_invalid');
	}

	private function result($state, $valid, $reason)
	{
		return array(
			'state' => $state,
			'valid' => (bool) $valid,
			'reason' => $reason,
		);
	}

	private function normalizeFacility($value)
	{
		return strtoupper(trim((string) $value));
	}
}
