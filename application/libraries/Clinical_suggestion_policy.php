<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_suggestion_policy
{
	public function authorize($role, $type, $userId, $requestId, $request = null, array $nakesAccess = array())
	{
		$role = (string) $role;
		$type = (string) $type;
		$userId = (int) $userId;
		$requestId = (int) $requestId;
		if ($role === 'warga') {
			if ($type !== 'complaint' || $userId < 1) return false;
			if ($requestId < 1) return true;
			return is_object($request)
				&& isset($request->user_id, $request->request_status)
				&& (string) $request->user_id === (string) $userId
				&& (string) $request->request_status === 'Pending';
		}

		return $role === 'dokter'
			&& in_array($type, array('symptom', 'diagnosis', 'medicine'), true)
			&& $requestId > 0
			&& !empty($nakesAccess['can_handle'])
			&& isset($nakesAccess['request_status'])
			&& (string) $nakesAccess['request_status'] === 'Accepted';
	}
}
