<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Visit_monitoring_policy
{
	public static function resolve($identity, $access, $request)
	{
		$denied = array('allowed' => false, 'can_update' => false, 'mode' => 'denied');
		if (!is_array($identity) || empty($identity['valid']) || !is_array($access)
			|| !is_object($request) || (string) ($request->request_status ?? '') !== 'Accepted'
			|| empty($access['can_view'])) {
			return $denied;
		}

		$account_type = isset($identity['account_type']) ? (string) $identity['account_type'] : '';
		if (!in_array($account_type, array('command_center', 'personal'), true)) {
			return $denied;
		}
		if (empty($access['tenant_match'])) {
			return $denied;
		}

		$can_update = !empty($access['can_handle']);
		return array(
			'allowed' => true,
			'can_update' => $can_update,
			'mode' => $can_update ? 'operator' : 'monitor',
		);
	}
}
