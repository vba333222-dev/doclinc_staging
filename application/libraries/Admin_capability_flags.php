<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Admin_capability_flags
{
	public static function resolve($runtimeEnvironment = null)
	{
		$runtimeEnvironment = $runtimeEnvironment ?: (getenv('DOCLINC_RUNTIME_ENVIRONMENT') ?: '');
		return array(
			'capabilities' => Doclinc_feature_flags::resolve(getenv('DOCLINC_ADMIN_CAPABILITIES_ENABLED'), getenv('DOCLINC_ADMIN_CAPABILITIES_ENVIRONMENT'), $runtimeEnvironment),
			'clinical_audit' => Doclinc_feature_flags::resolve(getenv('DOCLINC_CLINICAL_AUDIT_ENABLED'), getenv('DOCLINC_CLINICAL_AUDIT_ENVIRONMENT'), $runtimeEnvironment),
		);
	}

	public static function enabled($name, $runtimeEnvironment = null)
	{
		$flags = self::resolve($runtimeEnvironment);
		return isset($flags[$name]['enabled']) && $flags[$name]['enabled'] === true;
	}
}
