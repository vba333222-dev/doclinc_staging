<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'models/Clinical_finalization_m.php';
require_once APPPATH.'libraries/Care_team_policy.php';
require_once APPPATH.'helpers/request_authz_helper.php';
require_once APPPATH.'helpers/request_event_helper.php';
class Clinical_finalization_service {
    private $db; private $model; private $policy;
    public function __construct($db=null,$model=null,$policy=null){$ci=get_instance();$this->db=$db?:$ci->db;$this->model=$model?:new Clinical_finalization_m($this->db);$this->policy=$policy?:new Care_team_policy();}
    public function finalize($requestId,$userId,$key){$requestId=(int)$requestId;$userId=(int)$userId;$key=trim((string)$key);if($requestId<1||$userId<1||$key===''||strlen($key)>191)return $this->fail('INVALID_FINALIZATION_INPUT');
        if(!$this->db->trans_begin())return $this->fail('WRITE_FAILED');
        try {
            $eventKey='clinical-finalization:'.$key;
            $historic=$this->model->eventByKey($eventKey);
            if($historic){$meta=json_decode((string)$historic->metadata_json,true)?:[];if((int)$historic->request_id!==$requestId||(int)($meta['finalized_by_user_id']??0)!==$userId)return $this->rollback('FINALIZATION_KEY_CONFLICT');$recordId=(int)($meta['record_id']??0);if($recordId<1)return $this->rollback('FINALIZATION_KEY_CONFLICT');$row=$this->db->where('record_id',$recordId)->get('medicalrecords')->row();if(!$row)return $this->rollback('FINALIZATION_KEY_CONFLICT');$this->db->trans_commit();return ['status'=>'success','record_id'=>$recordId,'idempotent'=>true,'medicalrecord'=>$row];}
            $request=$this->db->query('SELECT * FROM requests WHERE request_id=? FOR UPDATE',[$requestId])->row();if(!$request)return $this->rollback('REQUEST_NOT_FOUND');
            $serialized=$this->model->eventByKeyForUpdate($eventKey);if($serialized){$meta=json_decode((string)$serialized->metadata_json,true)?:[];if((int)$serialized->request_id!==$requestId||(int)($meta['finalized_by_user_id']??0)!==$userId)return $this->rollback('FINALIZATION_KEY_CONFLICT');$rid=(int)($meta['record_id']??0);$saved=$rid?$this->db->where('record_id',$rid)->get('medicalrecords')->row():null;if(!$saved)return $this->rollback('FINALIZATION_KEY_CONFLICT');$this->db->trans_commit();return ['status'=>'success','record_id'=>$rid,'idempotent'=>true,'medicalrecord'=>$saved];}
            if((string)$request->request_status!=='Accepted')return $this->rollback('REQUEST_NOT_ACCEPTED');
            $mode=$this->policy->mode($request->consultation_mode);if(!$mode)return $this->rollback('INVALID_CONSULTATION_MODE');
            $facility=trim((string)$request->assigned_puskesmas_code);$identity=doclinc_dokter_identity_context($userId,true);$rd=$this->db->query("SELECT * FROM request_responsible_doctor_assignments WHERE request_id=? AND status='aktif' ORDER BY responsible_assignment_id ASC LIMIT 1 FOR UPDATE",[$requestId])->row();
            $rdOk=$rd&&(int)$rd->user_id===$userId&&$this->policy->responsibleDoctorEligible((array)$identity,$facility)&&(int)$rd->staff_id===(int)($identity['staff_id']??0);
            $record=null;$result=null;$assignment=null;$performerOk=false;
            if($mode===Care_team_policy::VISIT){
                if(strtolower(trim((string)$request->visit_status))!=='completed')return $this->rollback('INVALID_WORKFLOW_STATE');
                $disp=$this->db->query('SELECT * FROM visit_dispositions WHERE request_id=? AND superseded_at IS NULL ORDER BY version_no DESC LIMIT 1 FOR UPDATE',[$requestId])->row();if(!$disp||$disp->decision!=='visit')return $this->rollback('VISIT_NOT_REQUIRED');
                $assignment=$this->db->query("SELECT * FROM request_visit_performer_assignments WHERE request_id=? ORDER BY (status='aktif') DESC, visit_assignment_id ASC LIMIT 1 FOR UPDATE",[$requestId])->row();
                $latest=$this->db->query("SELECT * FROM visit_results WHERE request_id=? AND status='submitted' ORDER BY version_no DESC,visit_result_id DESC LIMIT 1 FOR UPDATE",[$requestId])->row();if(!$latest)return $this->rollback('RESULT_NOT_LATEST');
                $review=$this->db->query("SELECT * FROM clinical_reviews WHERE visit_result_id=? AND decision='approved' LIMIT 1 FOR UPDATE",[(int)$latest->visit_result_id])->row();if(!$review)return $this->rollback('CLINICAL_REVIEW_REQUIRED');
                if($assignment&&(int)$assignment->user_id===$userId&&(int)$latest->visit_assignment_id===(int)$assignment->visit_assignment_id&&(int)$latest->performer_user_id===$userId&&(int)$latest->performer_staff_id===(int)$assignment->staff_id&&$this->policy->responsibleDoctorEligible((array)$identity,$facility)&&$this->placement((int)$assignment->staff_id,$facility)&&$assignment->status==='selesai')$performerOk=true;
                if(!$rdOk&&!$performerOk)return $this->rollback('ACCESS_DENIED');
            } else { if(!$rdOk)return $this->rollback('ACCESS_DENIED'); }
            $record=$this->model->latestRecordForUpdate($requestId);if(!$record)return $this->rollback('CLINICAL_RECORD_NOT_FOUND');if($record->clinical_finalized_at!==null)return $this->rollback('CLINICAL_RECORD_ALREADY_FINALIZED');
            $now=date('Y-m-d H:i:s.u');if(!$this->model->finalizeRecord((int)$record->record_id,$userId,$now))return $this->rollback('WRITE_FAILED');
            $event=['puskesmas_code'=>$facility,'actor_user_id'=>$userId,'actor_staff_id'=>(int)($identity['staff_id']??0),'domain_event_key'=>$eventKey,'metadata'=>['record_id'=>(int)$record->record_id,'request_id'=>$requestId,'finalized_by_user_id'=>$userId]];
            if(!doclinc_append_request_event($requestId,'clinical_record.finalized',$event,get_instance()))return $this->rollback('WRITE_FAILED');
            $saved=$this->db->where('record_id',(int)$record->record_id)->get('medicalrecords')->row();if(!$this->db->trans_commit())return $this->fail('WRITE_FAILED');return ['status'=>'success','record_id'=>(int)$record->record_id,'medicalrecord'=>$saved,'idempotent'=>false];
        } catch(Throwable $e){$this->db->trans_rollback();$eventKey='clinical-finalization:'.$key;$existing=$this->model->eventByKey($eventKey);if($existing){$meta=json_decode((string)$existing->metadata_json,true)?:[];if((int)$existing->request_id!==$requestId||(int)($meta['finalized_by_user_id']??0)!==$userId)return $this->fail('FINALIZATION_KEY_CONFLICT');return ['status'=>'success','record_id'=>(int)($meta['record_id']??0),'idempotent'=>true];}return $this->fail('WRITE_FAILED');}
    }
    private function placement($staff,$facility){return(bool)$this->db->query("SELECT placement_id FROM nakes_facility_placements WHERE staff_id=? AND facility_code=? AND status='active' LIMIT 1",[$staff,$facility])->row();}
    private function rollback($c){$this->db->trans_rollback();return $this->fail($c);} private function fail($c){return ['status'=>'error','safe_error_code'=>$c];}
}
