<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Clinical_workflow_surface
{
    private $db; private $resolver; private $presenter; private $policy;
    public function __construct($db = null, $resolver = null, $presenter = null, $policy = null) { $this->db=$db; $this->resolver=$resolver; $this->presenter=$presenter; $this->policy=$policy; }
    public function forRequest($requestId, $actorUserId)
    {
        $id=(int)$requestId; $actor=(int)$actorUserId;
        if (!$this->db || !$this->resolver || !$this->presenter) return array('status'=>'error','safe_error_code'=>'WORKFLOW_SURFACE_UNAVAILABLE');
        $request=$this->row('SELECT request_id, request_status, consultation_mode, visit_status, assigned_puskesmas_code FROM requests WHERE request_id = ?', array($id));
        if (!$request) return array('status'=>'error','safe_error_code'=>'REQUEST_NOT_FOUND');
        if (!$this->canView($id, $actor, $request)) return array('status'=>'error','safe_error_code'=>'ACCESS_DENIED');
        $state=$this->resolver->resolve($id); $context=$this->actorContext($id,$actor); $presentation=$this->presenter->present($state,$context['type']);
        $data=array('request'=>$request,'workflow_state'=>$state,'presentation'=>$presentation,'actor_context'=>$context,
            'disposition_summary'=>$this->row('SELECT decision, version_no FROM visit_dispositions WHERE request_id = ? AND superseded_at IS NULL ORDER BY version_no DESC LIMIT 1',array($id)),
            'responsible_doctor'=>$this->row('SELECT assignment_id, user_id, staff_id, status FROM request_responsible_doctor_assignments WHERE request_id = ? ORDER BY assignment_id DESC LIMIT 1',array($id)),
            'performer'=>$this->row('SELECT visit_assignment_id, user_id, staff_id, status FROM request_visit_performer_assignments WHERE request_id = ? ORDER BY visit_assignment_id DESC LIMIT 1',array($id)),
            'visit_progress'=>array('visit_status'=>$request['visit_status']),
            'result_summary'=>$this->row("SELECT visit_result_id, version_no, status, performer_user_id, performer_staff_id, visit_assignment_id FROM visit_results WHERE request_id = ? AND status = 'submitted' ORDER BY version_no DESC, visit_result_id DESC LIMIT 1",array($id)),
            'review_summary'=>$this->row('SELECT cr.review_id, cr.visit_result_id, cr.decision, cr.correction_reason FROM clinical_reviews cr INNER JOIN visit_results vr ON vr.visit_result_id=cr.visit_result_id WHERE vr.request_id=? ORDER BY vr.version_no DESC, cr.review_id DESC LIMIT 1',array($id)),
            'finalization_summary'=>$this->row('SELECT record_id, clinical_finalized_at, clinical_finalized_by_user_id FROM medicalrecords WHERE request_id = ? ORDER BY record_id DESC LIMIT 1',array($id)),
            'closure_summary'=>$this->row('SELECT closure_operation_id, closed_by_user_id, authority_source, closed_at FROM clinical_closure_operations WHERE request_id = ? LIMIT 1',array($id)), 'available_actions'=>array());
        if ($context['type']==='warga' && isset($data['review_summary']['correction_reason'])) $data['review_summary']['correction_reason']=null;
        return array('status'=>'success','data'=>$data);
    }
    private function canView($requestId,$actorId,$request) { if (is_object($this->policy) && is_callable(array($this->policy,'canView'))) return (bool)$this->policy->canView($requestId,$actorId); if (function_exists('doclinc_can_view_request')) return (bool)doclinc_can_view_request($requestId,$actorId); return false; }
    private function actorContext($requestId,$actorId) { $role=$this->scalar('SELECT role FROM users WHERE userId = ?',array($actorId)); $type='warga'; if ((string)$role==='dokter' && $this->isResponsible($requestId,$actorId)) $type='responsible_doctor'; elseif (in_array((string)$role,array('dokter','nakes'),true) && (int)$this->scalar('SELECT COUNT(*) FROM request_visit_performer_assignments WHERE request_id=? AND user_id=? AND status IN (\'aktif\',\'selesai\')',array($requestId,$actorId))>0) $type='performer'; elseif (in_array((string)$role,array('admin','super_admin','command_center'),true)) $type='command_center'; return array('user_id'=>$actorId,'type'=>$type); }
    private function isResponsible($requestId,$actorId) { return (int)$this->scalar('SELECT COUNT(*) FROM request_responsible_doctor_assignments WHERE request_id=? AND user_id=? AND status=\'aktif\'',array($requestId,$actorId))>0; }
    private function scalar($sql,$params) { $row=$this->row($sql,$params); return $row ? array_values($row)[0] : null; }
    private function row($sql,$params) { $q=$this->db->query($sql,$params); return $q ? $q->row_array() : null; }
}
