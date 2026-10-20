<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Clinical_amendment_m {
 private $db; public function __construct($db=null){$this->db=$db?:get_instance()->db;}
 public function byKey($key){return $this->db->where('idempotency_key',(string)$key)->get('clinical_amendments')->row();}
 public function byKeyForUpdate($key){return $this->db->query('SELECT * FROM clinical_amendments WHERE idempotency_key=? LIMIT 1 FOR UPDATE',[(string)$key])->row();}
 public function latestForUpdate($recordId){return $this->db->query('SELECT * FROM clinical_amendments WHERE record_id=? ORDER BY sequence_no DESC LIMIT 1 FOR UPDATE',[(int)$recordId])->row();}
 public function insertHeader(array $row){return $this->db->insert('clinical_amendments',$row)?(int)$this->db->insert_id():0;}
 public function historyByRecord($recordId){
  if(!$this->db->table_exists('clinical_amendments')||!$this->db->table_exists('clinical_amendment_items'))return [];
  $prefix=(string)$this->db->dbprefix;
  return $this->db->query('SELECT a.amendment_id,a.sequence_no,a.reason,a.created_at,a.created_by_user_id,u.nama AS created_by_name,i.field_key,i.previous_value,i.corrected_value,i.item_order FROM '.$prefix.'clinical_amendments a LEFT JOIN '.$prefix.'clinical_amendment_items i ON i.amendment_id=a.amendment_id LEFT JOIN '.$prefix.'users u ON u.userId=a.created_by_user_id WHERE a.record_id=? ORDER BY a.sequence_no DESC,i.item_order ASC',[(int)$recordId])->result_array();
 }
}
