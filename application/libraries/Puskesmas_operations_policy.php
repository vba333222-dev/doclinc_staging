<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Puskesmas_operations_policy
{
	public function snapshotScope(array $actor)
	{
		$identity = isset($actor['identity']) && is_array($actor['identity'])
			? $actor['identity']
			: array();
		$user_id = (int) ($actor['user_id'] ?? 0);
		$puskesmas_code = trim((string) ($identity['puskesmas_code'] ?? ''));

		if (empty($actor['authenticated'])
			|| $user_id < 1
			|| (string) ($actor['role'] ?? '') !== 'dokter'
			|| (string) ($actor['status'] ?? '') !== 'aktif'
			|| !empty($actor['must_change_password'])
			|| empty($identity['valid'])
			|| (string) ($identity['account_type'] ?? '') !== 'command_center'
			|| empty($identity['is_command_center'])
			|| (int) ($identity['user_id'] ?? 0) !== $user_id
			|| $puskesmas_code === '') {
			return array('allowed' => false, 'puskesmas_code' => '');
		}

		return array('allowed' => true, 'puskesmas_code' => $puskesmas_code);
	}
}
