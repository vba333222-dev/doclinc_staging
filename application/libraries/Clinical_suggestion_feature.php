<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_suggestion_feature
{
	public static function resolve($enabledValue, $environmentValue)
	{
		$environment = strtolower(trim((string) $environmentValue));
		if (!in_array($environment, array('staging', 'uat'), true)) {
			$environment = '';
		}

		$enabled = filter_var($enabledValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		return array(
			'enabled' => $enabled === true && $environment !== '',
			'environment' => $environment,
		);
	}
}
