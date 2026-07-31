<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Doclinc_feature_flags.php';

class Realtime_request_feature
{
	public static function resolve($enabled, $feature_environment, $runtime_environment, $realtime_client_enabled)
	{
		$state = Doclinc_feature_flags::resolve($enabled, $feature_environment, $runtime_environment);
		$state['enabled'] = $state['enabled'] === true
			&& $realtime_client_enabled === true
			&& hash_equals('staging', (string) $state['environment'])
			&& hash_equals('staging', trim(strtolower((string) $runtime_environment)));
		if (!$state['enabled'] && $state['reason'] === 'enabled') {
			$state['reason'] = $realtime_client_enabled === true ? 'staging_required' : 'realtime_client_disabled';
		}
		return $state;
	}
}
