<?php
defined('BASEPATH') or exit('No direct script access allowed');

class First_login_password_policy
{
	public function validate($password, $confirmation, array $identity, $stored_hash)
	{
		if (!is_string($password) || !is_string($confirmation)) {
			return 'invalid_payload';
		}
		if (!hash_equals($password, $confirmation)) {
			return 'confirmation_mismatch';
		}
		if (strlen($password) < 10 || strlen($password) > 72) {
			return 'invalid_length';
		}
		if (preg_match('/[\x00-\x1F\x7F]/', $password) === 1) {
			return 'invalid_characters';
		}

		$candidates = array();
		foreach (array('username', 'email', 'no_hp') as $field) {
			$value = isset($identity[$field]) && is_string($identity[$field]) ? trim($identity[$field]) : '';
			if ($value !== '') {
				$candidates[] = $value;
			}
		}
		$phone = isset($identity['no_hp']) ? trim((string) $identity['no_hp']) : '';
		if (strpos($phone, '+62') === 0) {
			$candidates[] = '0' . substr($phone, 3);
		}
		foreach (array_unique($candidates) as $candidate) {
			if (hash_equals(strtolower($candidate), strtolower($password))) {
				return 'identity_value_reused';
			}
		}
		if (is_string($stored_hash) && $stored_hash !== '' && password_verify($password, $stored_hash)) {
			return 'current_password_reused';
		}
		return null;
	}
}
