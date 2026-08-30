<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Clinical_amendment_m {
 private $db; public function __construct($db=null){$this->db=$db?:get_instance()->db;}
 public function byKey($key){return $this->db->where('idempotency_key',(string)$key)->get('clinical_amendments')->row();}
 public function byKeyForUpdate($key){return $this->db->query('SELECT * FROM clinical_amendments WHERE idempotency_key=? LIMIT 1 FOR UPDATE',[(string)$key])->row();}
 public function latestForUpdate($recordId){return $this->db->query('SELECT * FROM clinical_amendments WHERE record_id=? ORDER BY sequence_no DESC LIMIT 1 FOR UPDATE',[(int)$recordId])->row();}
 public function insertHeader(array $row){return $this->db->insert('clinical_amendments',$row)?(int)$this->db->insert_id():0;}
}
