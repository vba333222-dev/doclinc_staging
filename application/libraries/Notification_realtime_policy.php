<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Notification_realtime_policy
{
	public function actorAllowed(array $actor)
	{
		$user_id = isset($actor['user_id']) ? (int) $actor['user_id'] : 0;
		$role = isset($actor['role']) ? (string) $actor['role'] : '';
		if ($user_id < 1 || empty($actor['authenticated']) || ($actor['status'] ?? '') !== 'aktif'
			|| !empty($actor['must_change_password']) || !in_array($role, array('warga', 'dokter'), true)) {
			return false;
		}
		if ($role === 'warga') {
			return true;
		}
		$identity = isset($actor['identity']) && is_array($actor['identity']) ? $actor['identity'] : array();
		return !empty($identity['valid'])
			&& (int) ($identity['user_id'] ?? 0) === $user_id
			&& ($identity['role'] ?? '') === 'dokter'
			&& ($identity['user_status'] ?? '') === 'aktif'
			&& in_array(($identity['account_type'] ?? ''), array('personal', 'command_center'), true);
	}
}
