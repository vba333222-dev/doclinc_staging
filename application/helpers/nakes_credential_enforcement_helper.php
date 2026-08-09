<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_nakes_credential_enforcement_policy')) {
	function doclinc_nakes_credential_enforcement_policy()
	{
		static $policies = array();
		$CI = &get_instance();
		$enabled = $CI->config->item('nakes_credential_enforcement_enabled') === true;
		$key = $enabled ? 'enabled' : 'disabled';
		if (!isset($policies[$key])) {
			require_once APPPATH . 'libraries/Nakes_credential_enforcement_policy.php';
			$policies[$key] = new Nakes_credential_enforcement_policy($enabled);
		}
		return $policies[$key];
	}
}

if (!function_exists('doclinc_nakes_password_change_blocked')) {
	function doclinc_nakes_password_change_blocked($role, $must_change_password, $password_changed_at)
	{
		return doclinc_nakes_credential_enforcement_policy()->blocks(
			$role,
			$must_change_password,
			$password_changed_at
		);
	}
}

if (!function_exists('doclinc_nakes_credential_schema_allows_runtime')) {
	function doclinc_nakes_credential_schema_allows_runtime($db)
	{
		return doclinc_nakes_credential_enforcement_policy()->schemaAllowsRuntime(
			$db->field_exists('must_change_password', 'users'),
			$db->field_exists('password_changed_at', 'users')
		);
	}
}

if (!function_exists('doclinc_nakes_password_changed_at_projection')) {
	function doclinc_nakes_password_changed_at_projection($db)
	{
		return $db->field_exists('password_changed_at', 'users')
			? 'password_changed_at'
			: 'NULL AS password_changed_at';
	}
}

if (!function_exists('doclinc_nakes_effective_must_change_password')) {
	function doclinc_nakes_effective_must_change_password($role, $must_change_password, $password_changed_at)
	{
		return doclinc_nakes_credential_enforcement_policy()->effectiveMustChangePassword(
			$role,
			$must_change_password,
			$password_changed_at
		);
	}
}
