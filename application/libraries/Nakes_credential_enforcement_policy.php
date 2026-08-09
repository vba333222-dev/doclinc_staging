<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Nakes_credential_policy.php';

class Nakes_credential_enforcement_policy
{
	private $enabled;
	private $credential_policy;

	public function __construct($enabled = false, $credential_policy = null)
	{
		$this->enabled = $enabled === true;
		$this->credential_policy = $credential_policy instanceof Nakes_credential_policy
			? $credential_policy
			: new Nakes_credential_policy();
	}

	public function enabled()
	{
		return $this->enabled;
	}

	public function rawState($role, $must_change_password, $password_changed_at)
	{
		return $this->credential_policy->state($role, $must_change_password, $password_changed_at);
	}

	public function blocks($role, $must_change_password, $password_changed_at)
	{
		return $this->enabled
			&& $this->credential_policy->requiresChange($role, $must_change_password, $password_changed_at);
	}

	public function schemaAllowsRuntime($must_change_password_exists, $password_changed_at_exists)
	{
		return !$this->enabled || ($must_change_password_exists === true && $password_changed_at_exists === true);
	}

	public function effectiveMustChangePassword($role, $must_change_password, $password_changed_at)
	{
		return $this->blocks($role, $must_change_password, $password_changed_at) ? 1 : 0;
	}
}
