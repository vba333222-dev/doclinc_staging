<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Doclinc_feature_flags.php';

class Realtime_outbox_feature_flags
{
	public static function writer($enabled, $feature_environment, $runtime_environment)
	{
		return Doclinc_feature_flags::resolve($enabled, $feature_environment, $runtime_environment);
	}

	public static function dispatcher($enabled, $write_enabled, $feature_environment, $runtime_environment)
	{
		$feature = Doclinc_feature_flags::resolve($enabled, $feature_environment, $runtime_environment);
		$write = Doclinc_feature_flags::resolve($write_enabled, $feature_environment, $runtime_environment);
		return array(
			'enabled' => $feature['enabled'] === true && $write['enabled'] === true,
			'feature' => $feature,
			'write' => $write,
		);
	}
}
