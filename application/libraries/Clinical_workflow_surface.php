<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH . 'libraries/Visit_workflow_state_resolver.php';
require_once APPPATH . 'libraries/Visit_workflow_presenter.php';
require_once APPPATH . 'libraries/Visit_workflow_policy.php';
require_once APPPATH . 'libraries/Clinical_workflow_requirements.php';
require_once APPPATH . 'libraries/Clinical_workflow_summary.php';
class Clinical_workflow_surface
{
    private $db; private $resolver; private $presenter; private $policy;
    public function __construct($db = null, $resolver = null, $presenter = null, $policy = null) {
        $ci = get_instance();
        $this->db = $db ?: $ci->db;
        $enabled = (bool) $ci->config->item('visit_clinical_workflow_enabled');
        $this->policy = $policy ?: new Visit_workflow_policy($this->db, $enabled);
        $this->resolver = $resolver ?: new Visit_workflow_state_resolver($this->db, $this->policy, $enabled);
        $this->presenter = $presenter ?: new Visit_workflow_presenter();
    }
    public function forRequest($requestId, $actorUserId)
    {
        $id=(int)$requestId; $actor=(int)$actorUserId;
        if (!$this->db || !$this->resolver || !$this->presenter) return array('status'=>'error','safe_error_code'=>'WORKFLOW_SURFACE_UNAVAILABLE');
        if (!$this->schemaReady()) return array('status'=>'error','safe_error_code'=>'schema_unavailable');
        $request=$this->row('SELECT request_id, request_status, consultation_mode, visit_status, assigned_puskesmas_code FROM requests WHERE request_id = ?', array($id));
        if (!$request) return array('status'=>'error','safe_error_code'=>'REQUEST_NOT_FOUND');
        if (!$this->canView($id, $actor, $request)) return array('status'=>'error','safe_error_code'=>'ACCESS_DENIED');
        $state=$this->resolver->resolve($id); $context=$this->actorContext($id,$actor); $presentation=$this->presenter->present($state,$context['type'],array('consultation_mode'=>(string)($request['consultation_mode'] ?? '')));
        $data=array('request'=>$request,'workflow_state'=>$state,'presentation'=>$presentation,'actor_context'=>$context,
            'disposition_summary'=>$this->row('SELECT decision, version_no FROM visit_dispositions WHERE request_id = ? AND superseded_at IS NULL ORDER BY version_no DESC LIMIT 1',array($id)),
            'responsible_doctor'=>$this->row('SELECT responsible_assignment_id, user_id, staff_id, status FROM request_responsible_doctor_assignments WHERE request_id = ? ORDER BY responsible_assignment_id DESC LIMIT 1',array($id)),
            'performer'=>$this->row('SELECT visit_assignment_id, user_id, staff_id, status FROM request_visit_performer_assignments WHERE request_id = ? ORDER BY visit_assignment_id DESC LIMIT 1',array($id)),
            'visit_progress'=>array('visit_status'=>$request['visit_status']),
            'result_summary'=>$this->row("SELECT visit_result_id, version_no, status, performer_user_id, performer_staff_id, visit_assignment_id FROM visit_results WHERE request_id = ? AND status = 'submitted' ORDER BY version_no DESC, visit_result_id DESC LIMIT 1",array($id)),
            'review_summary'=>$this->row('SELECT cr.clinical_review_id, cr.visit_result_id, cr.decision, cr.correction_reason FROM clinical_reviews cr INNER JOIN visit_results vr ON vr.visit_result_id=cr.visit_result_id WHERE vr.request_id=? ORDER BY vr.version_no DESC, cr.clinical_review_id DESC LIMIT 1',array($id)),
            'finalization_summary'=>$this->row('SELECT record_id, clinical_finalized_at, clinical_finalized_by_user_id FROM medicalrecords WHERE request_id = ? ORDER BY record_id DESC LIMIT 1',array($id)),
            'closure_summary'=>$this->row('SELECT closure_operation_id, closed_by_user_id, authority_source, closed_at FROM clinical_closure_operations WHERE request_id = ? LIMIT 1',array($id)), 'available_actions'=>array());
        $data['available_actions']=$this->availableActions($data,$context['type']);
        if ($context['type']==='warga' && isset($data['review_summary']['correction_reason'])) $data['review_summary']['correction_reason']=null;
        $workflow_ui=(new Clinical_workflow_summary())->forSurface($data);
        if ($context['type']==='warga' && $workflow_ui['state']==='CORRECTION_REQUIRED') {
            $workflow_ui['steps']=(new Clinical_workflow_summary())->steps($workflow_ui['summary'],'WAITING_DOCTOR_REVIEW');
            $workflow_ui['step_index']=5;
        }
        $workflow_ui['state']=$presentation['state'];
        $workflow_ui['label']=isset($presentation['label']) ? $presentation['label'] : (string) $presentation['state'];
        $workflow_ui['tone']=isset($presentation['tone']) ? $presentation['tone'] : 'neutral';
        $workflow_ui['owner']=isset($presentation['owner']) ? $presentation['owner'] : null;
        $workflow_ui['next_action']=isset($presentation['next_action']) ? $presentation['next_action'] : '';
        $workflow_ui['next_action_url']=isset($presentation['next_action_url']) ? $presentation['next_action_url'] : '';
        $workflow_ui['step_key']=isset($presentation['step_key']) ? $presentation['step_key'] : '';
        $workflow_ui['correction_reason']=$context['type']==='warga' ? null : ($data['review_summary']['correction_reason'] ?? null);
        $data['workflow_ui']=$workflow_ui;
        return array('status'=>'success','data'=>$data);
    }
    private function canView($requestId,$actorId,$request) {
        if (is_object($this->policy) && is_callable(array($this->policy,'canView'))) return (bool)$this->policy->canView($requestId,$actorId);
        $role = (string) $this->scalar('SELECT role FROM users WHERE userId = ?', array((int) $actorId));
        if (in_array($role, array('admin', 'super_admin', 'command_center'), true)) return true;
        if (function_exists('doclinc_dokter_identity_context') && function_exists('doclinc_can_coordinate_request')) {
            $identity = doclinc_dokter_identity_context((int)$actorId, true);
            if (doclinc_can_coordinate_request((object)$request, $identity)) return true;
        }
        if (function_exists('doclinc_can_view_request')) return (bool)doclinc_can_view_request($requestId,$actorId);
        return false;
    }
    private function actorContext($requestId,$actorId) {
        $role = $this->scalar('SELECT role FROM users WHERE userId = ?', array($actorId));
        $type = 'warga';
        $identity = function_exists('doclinc_dokter_identity_context')
            ? doclinc_dokter_identity_context((int) $actorId, true)
            : array();
        if (!empty($identity['valid']) && ($identity['account_type'] ?? '') === 'command_center') {
            $type = 'command_center';
        } elseif ((string) $role === 'dokter' && $this->isResponsible($requestId, $actorId)) {
            $type = 'responsible_doctor';
        } elseif (in_array((string) $role, array('dokter', 'nakes'), true)
            && (int) $this->scalar("SELECT COUNT(*) FROM request_visit_performer_assignments WHERE request_id=? AND user_id=? AND status IN ('aktif','selesai')", array($requestId, $actorId)) > 0) {
            $type = 'performer';
        } elseif (in_array((string) $role, array('admin', 'super_admin', 'command_center'), true)) {
            $type = 'command_center';
        }
        return array('user_id' => $actorId, 'type' => $type);
    }
    private function schemaReady() { return (new Clinical_workflow_requirements($this->db))->ready(); }
    private function availableActions(array $data, $actorType)
    {
        $actions = array(
            'dispose' => false, 'revise_disposition' => false, 'assign_performer' => false,
            'save_result' => false, 'submit_result' => false, 'correct_result' => false,
            'review' => false, 'finalize' => false, 'amend' => false, 'close' => false, 'change_mode' => false,
        );
        $request = isset($data['request']) && is_array($data['request']) ? $data['request'] : array();
        $disposition = isset($data['disposition_summary']) && is_array($data['disposition_summary']) ? $data['disposition_summary'] : null;
        $performer = isset($data['performer']) && is_array($data['performer']) ? $data['performer'] : null;
        $result = isset($data['result_summary']) && is_array($data['result_summary']) ? $data['result_summary'] : null;
        $review = isset($data['review_summary']) && is_array($data['review_summary']) ? $data['review_summary'] : null;
        $record = isset($data['finalization_summary']) && is_array($data['finalization_summary']) ? $data['finalization_summary'] : null;
        $closure = isset($data['closure_summary']) && is_array($data['closure_summary']) ? $data['closure_summary'] : null;

        $accepted = (string) ($request['request_status'] ?? '') === 'Accepted';
        $visitStatus = strtolower((string) ($request['visit_status'] ?? ''));
        $mode = strtolower((string) ($request['consultation_mode'] ?? ''));
        $notStarted = $visitStatus === '' || $visitStatus === 'not_started';
        $activePerformer = $performer && (string) ($performer['status'] ?? '') === 'aktif';
        $latestResultId = $result ? (int) ($result['visit_result_id'] ?? 0) : 0;
        $reviewed = $review && (int) ($review['visit_result_id'] ?? 0) === $latestResultId;
        $approved = $reviewed && (string) ($review['decision'] ?? '') === 'approved';
        $correctionNeeded = $reviewed && (string) ($review['decision'] ?? '') === 'correction_required';
        $finalized = $record && $record['clinical_finalized_at'] !== null;
        $closed = !empty($closure);

        if ($actorType === 'responsible_doctor' && $accepted) {
            $actions['dispose'] = !$disposition && $notStarted;
            $actions['revise_disposition'] = $disposition && $notStarted;
            $actions['assign_performer'] = $disposition && (string) ($disposition['decision'] ?? '') === 'visit' && !$activePerformer && $notStarted;
            $actions['review'] = $latestResultId > 0 && !$reviewed;
            $actions['finalize'] = $record && !$finalized && ($mode === 'non_visit' || $approved);
            $actions['amend'] = $record && $finalized;
            $actions['close'] = $record && $finalized && !$closed;
        }
        if ($actorType === 'performer' && $accepted) {
            $canRecord = $mode === 'visit' && in_array($visitStatus, array('arrived', 'in_service', 'completed'), true);
            $actions['save_result'] = $canRecord;
            $actions['submit_result'] = $canRecord;
            $actions['correct_result'] = $mode === 'visit' && $correctionNeeded;
        }
        $actorId = (int) ($data['actor_context']['user_id'] ?? 0);
        $actorIsPerformer = $performer && (int) ($performer['user_id'] ?? 0) === $actorId;
        if ($actorType === 'responsible_doctor' && $actorIsPerformer && $accepted) {
            $canRecord = $mode === 'visit' && in_array($visitStatus, array('arrived', 'in_service', 'completed'), true);
            $actions['save_result'] = $canRecord;
            $actions['submit_result'] = $canRecord;
            $actions['correct_result'] = $mode === 'visit' && $correctionNeeded;
        }
        return $actions;
    }

    private function isResponsible($requestId,$actorId) { return (int)$this->scalar('SELECT COUNT(*) FROM request_responsible_doctor_assignments WHERE request_id=? AND user_id=? AND status=\'aktif\'',array($requestId,$actorId))>0; }
    private function scalar($sql,$params) { $row=$this->row($sql,$params); return $row ? array_values($row)[0] : null; }
    private function row($sql,$params) { $q=$this->db->query($sql,$params); return $q ? $q->row_array() : null; }
}
