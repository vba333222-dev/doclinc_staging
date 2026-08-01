<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Nakes_presence_policy
{
	public function heartbeatAllowed(array $actor)
	{
		$identity = isset($actor['identity']) && is_array($actor['identity']) ? $actor['identity'] : array();
		return !empty($actor['authenticated'])
			&& (int) ($actor['user_id'] ?? 0) > 0
			&& (string) ($actor['role'] ?? '') === 'dokter'
			&& (string) ($actor['status'] ?? '') === 'aktif'
			&& empty($actor['must_change_password'])
			&& !empty($identity['valid'])
			&& (string) ($identity['account_type'] ?? '') === 'personal'
			&& (int) ($identity['user_id'] ?? 0) === (int) $actor['user_id']
			&& (int) ($identity['staff_id'] ?? 0) > 0
			&& (string) ($identity['staff_status'] ?? '') === 'aktif'
			&& trim((string) ($identity['puskesmas_code'] ?? '')) !== '';
	}

	public function snapshotScope(array $actor)
	{
		if (empty($actor['authenticated'])
			|| (string) ($actor['status'] ?? '') !== 'aktif'
			|| !empty($actor['must_change_password'])) {
			return array('allowed' => false, 'scope' => 'denied', 'puskesmas_code' => '');
		}

		if ((string) ($actor['role'] ?? '') === 'admin') {
			return array('allowed' => true, 'scope' => 'all', 'puskesmas_code' => '');
		}

		$identity = isset($actor['identity']) && is_array($actor['identity']) ? $actor['identity'] : array();
		$puskesmas_code = trim((string) ($identity['puskesmas_code'] ?? ''));
		if ((string) ($actor['role'] ?? '') === 'dokter'
			&& !empty($identity['valid'])
			&& (string) ($identity['account_type'] ?? '') === 'command_center'
			&& !empty($identity['is_command_center'])
			&& $puskesmas_code !== '') {
			return array('allowed' => true, 'scope' => 'tenant', 'puskesmas_code' => $puskesmas_code);
		}

		return array('allowed' => false, 'scope' => 'denied', 'puskesmas_code' => '');
	}
}
