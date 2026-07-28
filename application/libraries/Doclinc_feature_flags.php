<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Doclinc_feature_flags
{
	private const ALLOWED_ENVIRONMENTS = array('staging', 'uat');
	private const TRUE_VALUES = array('1', 'true', 'yes', 'on');
	private const FALSE_VALUES = array('0', 'false', 'no', 'off');

	public static function resolve($enabled_value, $feature_environment, $runtime_environment)
	{
		$enabled = self::parseBoolean($enabled_value);
		$feature_environment = self::normalizeEnvironment($feature_environment);
		$runtime_environment = self::normalizeEnvironment($runtime_environment);

		$result = array(
			'enabled' => false,
			'environment' => '',
			'runtime_environment' => '',
			'reason' => 'disabled',
		);

		if ($enabled === null) {
			$result['reason'] = 'flag_missing_or_malformed';
			return $result;
		}
		if ($enabled === false) {
			$result['reason'] = 'flag_disabled';
			return $result;
		}
		if (!in_array($feature_environment, self::ALLOWED_ENVIRONMENTS, true)) {
			$result['reason'] = 'feature_environment_not_allowed';
			return $result;
		}
		if (!in_array($runtime_environment, self::ALLOWED_ENVIRONMENTS, true)) {
			$result['reason'] = 'runtime_environment_not_allowed';
			return $result;
		}
		if (!hash_equals($feature_environment, $runtime_environment)) {
			$result['reason'] = 'environment_mismatch';
			return $result;
		}

		$result['enabled'] = true;
		$result['environment'] = $feature_environment;
		$result['runtime_environment'] = $runtime_environment;
		$result['reason'] = 'enabled';
		return $result;
	}

	public static function resolveSet(array $raw_flags, $runtime_environment)
	{
		$result = array();
		foreach ($raw_flags as $name => $raw_flag) {
			$enabled = is_array($raw_flag) && array_key_exists('enabled', $raw_flag)
				? $raw_flag['enabled']
				: null;
			$environment = is_array($raw_flag) && array_key_exists('environment', $raw_flag)
				? $raw_flag['environment']
				: null;
			$result[(string) $name] = self::resolve($enabled, $environment, $runtime_environment);
		}
		return $result;
	}

	private static function parseBoolean($value)
	{
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value)) {
			return $value === 1 ? true : ($value === 0 ? false : null);
		}
		if (!is_string($value)) {
			return null;
		}

		$value = strtolower(trim($value));
		if ($value === '') {
			return null;
		}
		if (in_array($value, self::TRUE_VALUES, true)) {
			return true;
		}
		if (in_array($value, self::FALSE_VALUES, true)) {
			return false;
		}
		return null;
	}

	private static function normalizeEnvironment($environment)
	{
		return is_string($environment) ? strtolower(trim($environment)) : '';
	}
}
