<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Doclinc_feature_flags.php';

class Realtime_notification_feature
{
	public static function resolve($enabled, $feature_environment, $runtime_environment, $realtime_client_enabled)
	{
		$notification = Doclinc_feature_flags::resolve($enabled, $feature_environment, $runtime_environment);
		$notification['enabled'] = $notification['enabled'] === true && $realtime_client_enabled === true;
		if (!$notification['enabled'] && $notification['reason'] === 'enabled') {
			$notification['reason'] = 'realtime_client_disabled';
		}
		return $notification;
	}
}
