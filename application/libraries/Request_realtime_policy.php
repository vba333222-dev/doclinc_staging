<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Request_realtime_policy
{
	public function actorAllowed(array $actor)
	{
		$user_id = (int) ($actor['user_id'] ?? 0);
		$role = (string) ($actor['role'] ?? '');
		if (empty($actor['authenticated']) || $user_id < 1 || ($actor['status'] ?? '') !== 'aktif'
			|| !empty($actor['must_change_password']) || !in_array($role, array('warga', 'dokter'), true)) {
			return false;
		}
		if ($role === 'warga') {
			return true;
		}
		$identity = is_array($actor['identity'] ?? null) ? $actor['identity'] : array();
		return !empty($identity['valid'])
			&& (int) ($identity['user_id'] ?? 0) === $user_id
			&& ($identity['user_status'] ?? '') === 'aktif'
			&& in_array(($identity['account_type'] ?? ''), array('personal', 'command_center'), true);
	}

	public function channel(array $actor)
	{
		if (!$this->actorAllowed($actor)) {
			return null;
		}
		if (($actor['role'] ?? '') === 'warga') {
			return 'user:' . (int) $actor['user_id'];
		}
		$identity = $actor['identity'];
		if (($identity['account_type'] ?? '') === 'personal') {
			return 'user:' . (int) $actor['user_id'];
		}
		$code = trim((string) ($identity['puskesmas_code'] ?? ''));
		return $code !== '' && strtoupper($code) !== 'DEFAULT' ? 'puskesmas:' . $code . ':ops' : null;
	}
}
