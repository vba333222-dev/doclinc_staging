<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_suggestion_feature
{
	/**
	 * Environments where clinical suggestions may resolve.
	 *
	 * 'production' must be listed. This is runtime code, not a migration guard:
	 * when the environment is unrecognised it is blanked and the feature reports
	 * itself disabled. Leaving production out therefore switches the feature off
	 * there permanently, silently, with no error to point at.
	 */
	private const ALLOWED_ENVIRONMENTS = array('staging', 'uat', 'production');

	public static function resolve($enabledValue, $environmentValue)
	{
		$environment = strtolower(trim((string) $environmentValue));
		if (!in_array($environment, self::ALLOWED_ENVIRONMENTS, true)) {
			$environment = '';
		}

		$enabled = filter_var($enabledValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		return array(
			'enabled' => $enabled === true && $environment !== '',
			'environment' => $environment,
		);
	}
}
