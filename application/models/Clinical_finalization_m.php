<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Clinical_finalization_m {
    private $db;
    public function __construct($db=null){$this->db=$db?:get_instance()->db;}
    public function eventByKey($key){return $this->db->where('domain_event_key',(string)$key)->where('event_type','clinical_record.finalized')->get('request_events')->row();}
    public function eventByKeyForUpdate($key){return $this->db->query("SELECT * FROM request_events WHERE domain_event_key=? AND event_type='clinical_record.finalized' LIMIT 1 FOR UPDATE",[(string)$key])->row();}
    public function latestRecordForUpdate($requestId){return $this->db->query('SELECT * FROM medicalrecords WHERE request_id=? ORDER BY record_id DESC LIMIT 1 FOR UPDATE',[(int)$requestId])->row();}
    public function finalizeRecord($recordId,$userId,$at){$this->db->where('record_id',(int)$recordId)->where('clinical_finalized_at IS NULL',null,false);$ok=$this->db->update('medicalrecords',['clinical_finalized_at'=>$at,'clinical_finalized_by_user_id'=>(int)$userId]);return $ok&&$this->db->affected_rows()===1;}
}
