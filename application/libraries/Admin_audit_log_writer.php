<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Admin_audit_log_writer
{
	private $db;
	public function __construct($db) { $this->db = $db; }
	public function write(array $event)
	{
		if (!$this->db->table_exists('audit_logs')) return false;
		$metadata = array('target_user_id'=>(int)($event['target_user_id'] ?? 0),'capability_code'=>(string)($event['capability_code'] ?? ''));
		return $this->db->insert('audit_logs', array('actor_user_id'=>(int)$event['actor_user_id'],'action'=>(string)$event['action'],'entity_type'=>'admin_user_capability','entity_id'=>(string)($event['target_user_id'] ?? ''),'metadata_json'=>json_encode($metadata),'created_at'=>date('Y-m-d H:i:s')));
	}
}
