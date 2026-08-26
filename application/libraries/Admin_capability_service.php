<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Admin_capability_service
{
	private $store;
	private $auditWriter;
	private $enabled;

	public function __construct($store, $auditWriter, $enabled = false)
	{
		$this->store = $store;
		$this->auditWriter = $auditWriter;
		$this->enabled = $enabled === true;
	}

	public function featureEnabled()
	{
		return $this->enabled;
	}

	public function hasCapability(array $actor, $code)
	{
		if (!$this->enabled || !Admin_capability_policy::activeAdmin($actor) || !Admin_capability_policy::validCode($code)) {
			return false;
		}
		return $this->store->has((int) $actor['user_id'], (string) $code);
	}

	public function grant(array $actor, $targetUserId, $code)
	{
		return $this->mutate($actor, (int) $targetUserId, (string) $code, 'grant');
	}

	public function revoke(array $actor, $targetUserId, $code)
	{
		return $this->mutate($actor, (int) $targetUserId, (string) $code, 'revoke');
	}

	private function mutate(array $actor, $targetUserId, $code, $operation)
	{
		$result = array('allowed' => false, 'changed' => false, 'error' => null);
		if (!$this->enabled || !Admin_capability_policy::activeAdmin($actor)
			|| !Admin_capability_policy::validCode($code) || $targetUserId < 1
			|| !method_exists($this->store, 'eligibleAdmin') || !$this->store->eligibleAdmin($targetUserId)
			|| !$this->store->has((int) $actor['user_id'], Admin_capability_policy::SUPER_ADMIN)) {
			$result['error'] = 'capability_management_denied';
			return $result;
		}
		if ($operation === 'revoke' && $code === Admin_capability_policy::SUPER_ADMIN
			&& !$this->store->has($targetUserId, Admin_capability_policy::SUPER_ADMIN)) {
			$result['error'] = 'capability_not_granted';
			return $result;
		}
		if ($operation === 'revoke' && $code === Admin_capability_policy::SUPER_ADMIN
			&& !Admin_capability_policy::canRemoveLastSuperAdmin($this->store->activeSuperAdminCount())) {
			$result['error'] = 'last_active_super_admin';
			return $result;
		}
		if ($operation === 'revoke' && $code === Admin_capability_policy::SUPER_ADMIN
			&& method_exists($this->store, 'revokeSuperAdminWithAudit')) {
			$guarded = $this->store->revokeSuperAdminWithAudit($targetUserId, (int) $actor['user_id'], $this->auditWriter);
			return array('allowed'=>(bool)$guarded['changed'], 'changed'=>(bool)$guarded['changed'], 'error'=>$guarded['error']);
		}
		if (method_exists($this->store, 'mutateWithAudit')) {
			return $this->store->mutateWithAudit($targetUserId, $code, (int)$actor['user_id'], $operation, $this->auditWriter);
		}
		$already = $this->store->has($targetUserId, $code);
		if ($operation === 'grant') {
			if (!$already && !$this->store->grant($targetUserId, $code, (int) $actor['user_id'])) {
				$result['error'] = 'capability_write_failed';
				return $result;
			}
			$result['allowed'] = true;
			$result['changed'] = !$already;
		} else {
			if (!$already || !$this->store->revoke($targetUserId, $code)) {
				$result['error'] = !$already ? 'capability_not_granted' : 'capability_write_failed';
				return $result;
			}
			$result['allowed'] = true;
			$result['changed'] = true;
		}
		$event = array(
			'action' => 'admin_capability_' . ($operation === 'grant' ? 'granted' : 'revoked'),
			'actor_user_id' => (int) $actor['user_id'],
			'target_user_id' => $targetUserId,
			'capability_code' => $code,
		);
		if ($result['changed'] && !$this->auditWriter->write($event)) {
			if ($operation === 'grant') {
				$this->store->revoke($targetUserId, $code);
			} else {
				$this->store->grant($targetUserId, $code, (int) $actor['user_id']);
			}
			$result['allowed'] = false;
			$result['changed'] = false;
			$result['error'] = 'audit_write_failed';
		}
		return $result;
	}
}
