<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_access_audit_ledger
{
	private $db;
	public function __construct($db) { $this->db = $db; }
	public function hasRecordForSession($recordId, $sessionHash, $actorUserId = null)
	{
		if (!$this->db->table_exists('clinical_access_audit')) return false;
		$this->db->where('record_id',(int)$recordId)->where('audit_session_hash',(string)$sessionHash);
		if ($actorUserId !== null) $this->db->where('actor_user_id',(int)$actorUserId);
		return $this->db->count_all_results('clinical_access_audit') > 0;
	}
	public function append(array $row)
	{
		if (!$this->db->table_exists('clinical_access_audit')) return false;
		$allowed = array('actor_user_id','record_id','request_id','patient_user_id','puskesmas_code','access_kind','audit_session_hash','first_access_in_session','reason_code','reason_text','reason_root_access_id','ip_address','user_agent','created_at');
		$safe = array();
		foreach ($allowed as $field) if (array_key_exists($field, $row)) $safe[$field] = $row[$field];
		return count($safe) === count($allowed) && $this->db->insert('clinical_access_audit', $safe);
	}
	public function report(array $filters = array(), $limit = 100, $authorized = false)
	{
		if (!$authorized || !$this->db->table_exists('clinical_access_audit')) return array();
		$limit = max(1, min(500, (int) $limit));
		if (isset($filters['actor_user_id'])) $this->db->where('actor_user_id', (int)$filters['actor_user_id']);
		if (isset($filters['record_id'])) $this->db->where('record_id', (int)$filters['record_id']);
		return $this->db->order_by('created_at', 'DESC')->limit($limit)->get('clinical_access_audit')->result_array();
	}
}
