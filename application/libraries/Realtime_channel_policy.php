<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Realtime_channel_policy
{
	private const MAX_CHANNEL_LENGTH = 128;
	private const PERSONAL_OWNERSHIP = array(
		'staff_assignment',
		'staff_assignment_command_center_bridge',
		'assigned_nakes_user_id',
		'accepted_by_user_id',
		'dokter_id',
	);

	public function parse($channel)
	{
		if (!is_string($channel)
			|| $channel === ''
			|| strlen($channel) > self::MAX_CHANNEL_LENGTH
			|| preg_match('/[\x00-\x20\x7F]/', $channel) === 1) {
			return null;
		}
		if (preg_match('/\Auser:([1-9][0-9]{0,17})\z/D', $channel, $match) === 1) {
			return array('channel' => $channel, 'type' => 'user', 'id' => (int) $match[1]);
		}
		if (preg_match('/\Arequest:([1-9][0-9]{0,17})\z/D', $channel, $match) === 1) {
			return array('channel' => $channel, 'type' => 'request', 'id' => (int) $match[1]);
		}
		if (preg_match('/\Apuskesmas:([A-Za-z0-9][A-Za-z0-9._-]{0,99}):ops\z/D', $channel, $match) === 1
			&& strtoupper($match[1]) !== 'DEFAULT'
			&& strpos($match[1], '..') === false) {
			return array('channel' => $channel, 'type' => 'puskesmas', 'code' => $match[1]);
		}
		return null;
	}

	public function authorize(array $actor, array $parsed_channel, $request = null, $identity = null, $access = null)
	{
		$user_id = isset($actor['user_id']) ? (int) $actor['user_id'] : 0;
		$role = isset($actor['role']) ? (string) $actor['role'] : '';
		if ($user_id < 1
			|| empty($actor['authenticated'])
			|| ($actor['status'] ?? '') !== 'aktif'
			|| !empty($actor['must_change_password'])
			|| !in_array($role, array('warga', 'dokter'), true)) {
			return false;
		}

		$type = $parsed_channel['type'] ?? '';
		if ($type === 'user') {
			return (int) ($parsed_channel['id'] ?? 0) === $user_id
				&& ($role === 'warga' || $this->validDoctorIdentity($identity, $user_id));
		}
		if ($type === 'request') {
			if (!is_object($request) || (int) ($request->request_id ?? 0) !== (int) ($parsed_channel['id'] ?? 0)) {
				return false;
			}
			if ($role === 'warga') {
				return (int) ($request->user_id ?? 0) === $user_id;
			}
			if (!$this->validDoctorIdentity($identity, $user_id) || !is_array($access)) {
				return false;
			}
			if (($identity['account_type'] ?? '') === 'command_center') {
				return !empty($access['valid']) && !empty($access['can_view']) && !empty($access['tenant_match']);
			}
			return ($identity['account_type'] ?? '') === 'personal'
				&& !empty($access['valid'])
				&& !empty($access['can_view'])
				&& !empty($access['tenant_match'])
				&& in_array((string) ($access['ownership_source'] ?? ''), self::PERSONAL_OWNERSHIP, true);
		}
		if ($type === 'puskesmas') {
			return $role === 'dokter'
				&& $this->validDoctorIdentity($identity, $user_id)
				&& ($identity['account_type'] ?? '') === 'command_center'
				&& hash_equals((string) ($identity['puskesmas_code'] ?? ''), (string) ($parsed_channel['code'] ?? ''));
		}
		return false;
	}

	private function validDoctorIdentity($identity, $user_id)
	{
		return is_array($identity)
			&& !empty($identity['valid'])
			&& (int) ($identity['user_id'] ?? 0) === $user_id
			&& ($identity['role'] ?? '') === 'dokter'
			&& ($identity['user_status'] ?? '') === 'aktif'
			&& in_array(($identity['account_type'] ?? ''), array('personal', 'command_center'), true);
	}
}
