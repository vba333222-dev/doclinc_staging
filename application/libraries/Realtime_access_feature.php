<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'libraries/Doclinc_feature_flags.php';

class Realtime_access_feature
{
	private const MAX_URL_LENGTH = 300;
	private const ALLOWED_PATH = '/connection/websocket';

	public static function resolve($enabled_value, $feature_environment, $runtime_environment, $websocket_url)
	{
		$state = Doclinc_feature_flags::resolve($enabled_value, $feature_environment, $runtime_environment);
		$state['websocket_url'] = '';
		if (!$state['enabled']) {
			return $state;
		}

		$url = self::validateWebsocketUrl($websocket_url);
		if ($url === null) {
			$state['enabled'] = false;
			$state['reason'] = 'websocket_url_invalid';
			return $state;
		}

		$state['websocket_url'] = $url;
		return $state;
	}

	private static function validateWebsocketUrl($value)
	{
		if (!is_string($value)) {
			return null;
		}
		$value = trim($value);
		if ($value === '' || strlen($value) > self::MAX_URL_LENGTH || preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
			return null;
		}

		$parts = parse_url($value);
		if (!is_array($parts)
			|| strtolower((string) ($parts['scheme'] ?? '')) !== 'wss'
			|| empty($parts['host'])
			|| isset($parts['user'])
			|| isset($parts['pass'])
			|| isset($parts['query'])
			|| isset($parts['fragment'])
			|| (isset($parts['path']) ? $parts['path'] : '') !== self::ALLOWED_PATH) {
			return null;
		}
		if (isset($parts['port']) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535)) {
			return null;
		}
		return $value;
	}
}
