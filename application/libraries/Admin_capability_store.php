<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Admin_capability_store
{
	private $db;
	public function __construct($db) { $this->db = $db; }
	public function has($userId, $code)
	{
		if (!$this->db->table_exists('admin_user_capabilities')) return false;
		return $this->db->where('user_id', (int)$userId)->where('capability_code', (string)$code)->count_all_results('admin_user_capabilities') > 0;
	}
	public function eligibleAdmin($userId)
	{
		if (!$this->db->table_exists('users')) return false;
		return $this->db->where('userId',(int)$userId)->where('role','admin')->where('status','aktif')->count_all_results('users') > 0;
	}
	public function grant($userId, $code, $by)
	{
		$sql = "INSERT INTO " . $this->db->dbprefix('admin_user_capabilities') . " (user_id, capability_code, granted_by_user_id, granted_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id=user_id";
		return $this->db->query($sql, array((int)$userId, (string)$code, (int)$by, date('Y-m-d H:i:s')));
	}
	public function revoke($userId, $code)
	{
		return $this->db->where('user_id',(int)$userId)->where('capability_code',(string)$code)->delete('admin_user_capabilities');
	}
	public function activeSuperAdminCount()
	{
		if (!$this->db->table_exists('admin_user_capabilities')) return 0;
		$this->db->from('admin_user_capabilities c')->join('users u','u.userId=c.user_id')->where('c.capability_code', Admin_capability_policy::SUPER_ADMIN)->where('u.role','admin')->where('u.status','aktif');
		return (int) $this->db->count_all_results();
	}
	public function revokeSuperAdminWithAudit($targetUserId, $actorUserId, $auditWriter)
	{
		$this->db->trans_begin();
		$lockedRows = $this->db->query("SELECT c.user_id FROM " . $this->db->dbprefix('admin_user_capabilities') . " c JOIN " . $this->db->dbprefix('users') . " u ON u.userId=c.user_id WHERE c.capability_code=? AND u.role='admin' AND u.status='aktif' FOR UPDATE", array(Admin_capability_policy::SUPER_ADMIN));
		$count = $lockedRows ? $lockedRows->num_rows() : 0;
		if ($count <= 1 || !$this->revoke($targetUserId, Admin_capability_policy::SUPER_ADMIN)) {
			$this->db->trans_rollback(); return array('changed'=>false, 'error'=>$count <= 1 ? 'last_active_super_admin' : 'capability_write_failed');
		}
		$event = array('action'=>'admin_capability_revoked','actor_user_id'=>(int)$actorUserId,'target_user_id'=>(int)$targetUserId,'capability_code'=>Admin_capability_policy::SUPER_ADMIN);
		if (!$auditWriter->write($event) || !$this->db->trans_status()) {
			$this->db->trans_rollback(); return array('changed'=>false, 'error'=>'audit_write_failed');
		}
		$this->db->trans_commit();
		return array('changed'=>true, 'error'=>null);
	}
	public function mutateWithAudit($targetUserId, $code, $actorUserId, $operation, $auditWriter)
	{
		$this->db->trans_begin();
		$already = $this->has($targetUserId, $code);
		$ok = true;
		if ($operation === 'grant' && !$already) $ok = $this->grant($targetUserId, $code, $actorUserId);
		if ($operation === 'revoke' && $already) $ok = $this->revoke($targetUserId, $code);
		$changed = ($operation === 'grant' && !$already) || ($operation === 'revoke' && $already);
		if (!$ok) { $this->db->trans_rollback(); return array('allowed'=>false,'changed'=>false,'error'=>'capability_write_failed'); }
		if ($changed) {
			$event = array('action'=>'admin_capability_' . ($operation === 'grant' ? 'granted' : 'revoked'),'actor_user_id'=>(int)$actorUserId,'target_user_id'=>(int)$targetUserId,'capability_code'=>(string)$code);
			if (!$auditWriter->write($event)) { $this->db->trans_rollback(); return array('allowed'=>false,'changed'=>false,'error'=>'audit_write_failed'); }
		}
		if (!$this->db->trans_status()) { $this->db->trans_rollback(); return array('allowed'=>false,'changed'=>false,'error'=>'capability_write_failed'); }
		$this->db->trans_commit();
		return array('allowed'=>true,'changed'=>$changed,'error'=>null);
	}
}
