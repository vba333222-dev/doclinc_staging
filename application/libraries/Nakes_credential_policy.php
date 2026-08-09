<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Nakes_credential_policy
{
	const ACTIVE = 'active';
	const FIRST_LOGIN_PENDING = 'first_login_pending';
	const ADMIN_RESET_PENDING = 'admin_reset_pending';
	const NOT_APPLICABLE = 'not_applicable';

	public function state($role, $must_change_password, $password_changed_at)
	{
		if ((string) $role !== 'dokter') {
			return self::NOT_APPLICABLE;
		}

		$has_verified_change = $this->validChangedAt($password_changed_at);
		if (!$has_verified_change) {
			return self::FIRST_LOGIN_PENDING;
		}
		if ((int) $must_change_password === 1) {
			return self::ADMIN_RESET_PENDING;
		}

		return self::ACTIVE;
	}

	public function requiresChange($role, $must_change_password, $password_changed_at)
	{
		$state = $this->state($role, $must_change_password, $password_changed_at);
		return $state === self::FIRST_LOGIN_PENDING || $state === self::ADMIN_RESET_PENDING;
	}

	private function validChangedAt($value)
	{
		if (!is_string($value) || trim($value) === '') {
			return false;
		}
		$parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', trim($value));
		$errors = DateTimeImmutable::getLastErrors();
		return $parsed instanceof DateTimeImmutable
			&& ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0));
	}
}
