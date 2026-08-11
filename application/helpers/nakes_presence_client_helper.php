<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_nakes_presence_client_bootstrap')) {
	function doclinc_nakes_presence_client_bootstrap(array $identity_context, $allow_monitor = false)
	{
		$CI = &get_instance();
		$feature_enabled = $CI->config->item('nakes_presence_enabled') === true;
		$identity_valid = !empty($identity_context['valid']);
		$account_type = isset($identity_context['account_type']) ? (string) $identity_context['account_type'] : '';
		$mode = 'disabled';

		if ($feature_enabled && $identity_valid && $account_type === 'personal') {
			$mode = 'heartbeat';
		} elseif ($feature_enabled && $identity_valid && $allow_monitor && $account_type === 'command_center') {
			$mode = 'monitor';
		}

		return array(
			'enabled' => $mode !== 'disabled',
			'mode' => $mode,
			'heartbeatUrl' => base_url('home_nakes/presence_heartbeat'),
			'snapshotUrl' => base_url('home_nakes/presence_snapshot'),
			'heartbeatIntervalMs' => max(15000, (int) $CI->config->item('nakes_presence_heartbeat_seconds') * 1000),
			'snapshotIntervalMs' => 30000,
		);
	}
}
