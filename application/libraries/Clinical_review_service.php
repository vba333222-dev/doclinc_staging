<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'models/Clinical_review_m.php';
require_once APPPATH . 'libraries/Care_team_policy.php';
require_once APPPATH . 'helpers/request_event_helper.php';

class Clinical_review_service
{
    private $db; private $model; private $policy;
    public function __construct($db = null, $model = null, $policy = null) { $ci = get_instance(); $this->db = $db ?: $ci->db; $this->model = $model ?: new Clinical_review_m($this->db); $this->policy = $policy ?: new Care_team_policy(); }

    public function review($requestId, $visitResultId, $reviewerUserId, $decision, $correctionReason, $reviewNotes, $idempotencyKey)
    {
        $requestId=(int)$requestId; $visitResultId=(int)$visitResultId; $reviewerUserId=(int)$reviewerUserId;
        $decision=strtolower(trim((string)$decision)); $reason=$this->text($correctionReason); $notes=$this->text($reviewNotes); $key=trim((string)$idempotencyKey);
        if ($requestId<1||$visitResultId<1||$reviewerUserId<1||$key===''||strlen($key)>191||!in_array($decision,array('approved','correction_required'),true)) return $this->fail('INVALID_REVIEW_INPUT');
        if ($decision==='correction_required' && trim($reason)==='') return $this->fail('CORRECTION_REASON_REQUIRED');
        if (!$this->db->trans_begin()) return $this->fail('WRITE_FAILED');
        try {
            $historic=$this->model->getByIdempotencyKey($key);
            if($historic){
                if((int)$historic->request_id!==$requestId||(int)$historic->visit_result_id!==$visitResultId||(int)$historic->reviewer_user_id!==$reviewerUserId||(string)$historic->decision!==$decision||(trim((string)$historic->correction_reason)!==trim($reason))||(trim((string)$historic->review_notes)!==trim($notes))) return $this->rollback('REVIEW_KEY_CONFLICT');
                if(!$this->db->trans_commit()) return $this->fail('WRITE_FAILED');
                return array('status'=>'success','clinical_review_id'=>(int)$historic->clinical_review_id,'review'=>$historic,'idempotent'=>true);
            }
            $request=$this->db->query('SELECT * FROM requests WHERE request_id = ? FOR UPDATE',array($requestId))->row();
            if(!$request) return $this->rollback('REQUEST_NOT_FOUND');
            $existing=$this->model->getByIdempotencyKeyForUpdate($key);
            if($existing){ return $this->replayOrConflict($existing,$requestId,$visitResultId,$reviewerUserId,$decision,$reason,$notes,false,false); }
            if((string)$request->request_status!=='Accepted') return $this->rollback('REQUEST_NOT_ACCEPTED');
            if(strtolower(trim((string)$request->visit_status))!=='completed') return $this->rollback('INVALID_WORKFLOW_STATE');
            if((string)$request->consultation_mode!=='visit') return $this->rollback('VISIT_NOT_REQUIRED');
            $disp=$this->db->query('SELECT * FROM visit_dispositions WHERE request_id=? AND superseded_at IS NULL ORDER BY version_no DESC LIMIT 1 FOR UPDATE',array($requestId))->row();
            if(!$disp || (string)$disp->decision!=='visit') return $this->rollback('VISIT_NOT_REQUIRED');
            $assignment=$this->db->query("SELECT * FROM request_visit_performer_assignments WHERE request_id=? AND status='aktif' ORDER BY visit_assignment_id ASC FOR UPDATE",array($requestId))->row();
            if(!$assignment) return $this->rollback('ACCESS_DENIED');
            $identity=function_exists('doclinc_dokter_identity_context')?doclinc_dokter_identity_context($reviewerUserId,true):array();
            $facility=trim((string)$request->assigned_puskesmas_code);
            $performerOk=(int)$assignment->user_id===$reviewerUserId && $this->policy->doctorProfession($identity['staff_profesi']??'') && $this->policy->responsibleDoctorEligible((array)$identity,$facility) && (int)$assignment->staff_id===(int)($identity['staff_id']??0) && $this->placement((int)$assignment->staff_id,$facility);
            $rd=$this->db->query("SELECT * FROM request_responsible_doctor_assignments WHERE request_id=? AND status='aktif' ORDER BY responsible_assignment_id ASC FOR UPDATE",array($requestId))->row();
            $rdOk=$rd && (int)$rd->user_id===$reviewerUserId && $this->policy->responsibleDoctorEligible((array)$identity,$facility) && (int)$rd->staff_id===(int)($identity['staff_id']??0);
            $result=$this->db->query('SELECT * FROM visit_results WHERE visit_result_id=? LIMIT 1 FOR UPDATE',array($visitResultId))->row();
            if(!$result || (int)$result->request_id!==$requestId || (int)$result->visit_assignment_id!==(int)$assignment->visit_assignment_id || (string)$result->status!=='submitted') return $this->rollback('RESULT_NOT_ALLOWED');
            $latest=$this->db->query("SELECT visit_result_id FROM visit_results WHERE request_id=? AND status='submitted' ORDER BY version_no DESC, visit_result_id DESC LIMIT 1",array($requestId))->row();
            if(!$latest || (int)$latest->visit_result_id!==$visitResultId) return $this->rollback('RESULT_NOT_LATEST');
            if($performerOk && ((int)$result->visit_assignment_id !== (int)$assignment->visit_assignment_id || (int)$result->performer_user_id !== $reviewerUserId || (int)$result->performer_user_id !== (int)$assignment->user_id || (int)$result->performer_staff_id !== (int)$assignment->staff_id)) return $this->rollback('REVIEW_RESULT_PERFORMER_MISMATCH');
            $existingResult=$this->model->getForResultForUpdate($visitResultId);
            if($existingResult) return $this->rollback('REVIEW_ALREADY_EXISTS');
            if(!$rdOk && !$performerOk) return $this->rollback('ACCESS_DENIED');
            $row=array('request_id'=>$requestId,'visit_result_id'=>$visitResultId,'reviewer_user_id'=>$reviewerUserId,'responsible_assignment_id'=>$rdOk?(int)$rd->responsible_assignment_id:null,'reviewer_visit_assignment_id'=>$rdOk?null:(int)$assignment->visit_assignment_id,'decision'=>$decision,'correction_reason'=>$reason===''?null:$reason,'review_notes'=>$notes===''?null:$notes,'reviewed_at'=>date('Y-m-d H:i:s.u'),'idempotency_key'=>$key);
            $id=$this->model->insertReview($row);
            if($id<1) return $this->rollback('WRITE_FAILED');
            $eventType=$decision==='approved'?'visit_result.approved':'visit_result.correction_required';
            $eventSuffix=$decision==='approved'?'approved':'correction_required';
            if($decision==='approved') {
                $now=date('Y-m-d H:i:s.u');
                if(!$this->db->where('visit_assignment_id',(int)$assignment->visit_assignment_id)->where('status','aktif')->update('request_visit_performer_assignments',array('status'=>'selesai','ended_at'=>$now,'ended_by_user_id'=>$reviewerUserId,'end_reason'=>'Visit Result approved','completed_at'=>$now,'updated_at'=>$now)) || $this->db->affected_rows()!==1) return $this->rollback('WRITE_FAILED');
            }
            if(!doclinc_append_request_event($requestId,$eventType,array('puskesmas_code'=>$facility,'actor_user_id'=>$reviewerUserId,'actor_staff_id'=>(int)$assignment->staff_id,'domain_event_key'=>'clinical-review:'.$id.':'.$eventSuffix,'metadata'=>array('clinical_review_id'=>$id,'visit_result_id'=>$visitResultId,'visit_assignment_id'=>(int)$assignment->visit_assignment_id,'reviewer_user_id'=>$reviewerUserId,'provenance_assignment_id'=>$rdOk?(int)$rd->responsible_assignment_id:(int)$assignment->visit_assignment_id)),get_instance())) return $this->rollback('WRITE_FAILED');
            $saved=$this->model->getForResultForUpdate($visitResultId); if(!$this->db->trans_commit()) return $this->fail('WRITE_FAILED'); return array('status'=>'success','clinical_review_id'=>$id,'review'=>$saved);
        } catch(Throwable $e){$this->db->trans_rollback(); return $this->fail('WRITE_FAILED');}
    }
    private function replayOrConflict($r,$req,$result,$user,$decision,$reason,$notes,$rdOk,$performerOk){$prov=$r->responsible_assignment_id!==null?(int)$r->responsible_assignment_id:null; $expected=$rdOk?$prov:($performerOk?(int)$r->reviewer_visit_assignment_id:null); if((int)$r->request_id!==$req||(int)$r->visit_result_id!==$result||(int)$r->reviewer_user_id!==$user||(string)$r->decision!==$decision||(trim((string)$r->correction_reason)!==trim($reason))||(trim((string)$r->review_notes)!==trim($notes))) return $this->rollback('REVIEW_KEY_CONFLICT'); if(!$this->db->trans_commit()) return $this->fail('WRITE_FAILED'); return array('status'=>'success','clinical_review_id'=>(int)$r->clinical_review_id,'review'=>$r,'idempotent'=>true);}
    private function placement($staff,$facility){$r=$this->db->query("SELECT placement_id FROM nakes_facility_placements WHERE staff_id=? AND facility_code=? AND status='active' LIMIT 1",array($staff,$facility))->row();return(bool)$r;}
    private function text($v){return is_scalar($v)?trim((string)$v):'';}
    private function fail($c){return array('status'=>'error','safe_error_code'=>$c);}
    private function rollback($c){$this->db->trans_rollback();return $this->fail($c);}
}
