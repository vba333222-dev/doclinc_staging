<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/../models/Visit_disposition_m.php';
require_once __DIR__ . '/Visit_workflow_policy.php';
require_once __DIR__ . '/../helpers/request_event_helper.php';

class Visit_disposition_service
{
    private $db; private $model; private $policy; private $enabled;
    public function __construct($db = null, $policy = null, $enabled = null)
    {
        $this->db = $db ?: get_instance()->db; $this->model = new Visit_disposition_m($this->db);
        $this->enabled = $enabled === null ? (bool) get_instance()->config->item('visit_clinical_workflow_enabled') : (bool) $enabled;
        $this->policy = $policy ?: new Visit_workflow_policy($this->db, $this->enabled);
    }
    public function create($requestId, $actorUserId, array $input, $idempotencyKey)
    {
        if (trim((string) $idempotencyKey) === '') return $this->fail('IDEMPOTENCY_KEY_CONFLICT');
        return $this->transact(function () use ($requestId, $actorUserId, $input, $idempotencyKey) {
            $request = $this->lockRequest($requestId); if (!$request) return $this->fail('REQUEST_NOT_FOUND');
            $existingKey = $this->model->findByIdempotencyKey($idempotencyKey);
            if ($existingKey) return ((int)$existingKey->request_id === (int)$requestId && (int)$existingKey->created_by_user_id === (int)$actorUserId) ? $this->ok($existingKey) : $this->fail('IDEMPOTENCY_KEY_CONFLICT');
            if (!$this->enabled) return $this->fail('WORKFLOW_ENROLLMENT_DISABLED');
            if ((string)$request->request_status !== 'Accepted') return $this->fail('REQUEST_NOT_ACCEPTED');
            if ($this->model->getActiveForUpdate($requestId)) return $this->fail('DISPOSITION_ALREADY_EXISTS');
            if (!$this->policy->canCreateDisposition($requestId, $actorUserId)) return $this->fail('NOT_RESPONSIBLE_DOCTOR');
            $data = $this->normalize($input, $actorUserId, $requestId, 1, $idempotencyKey);
            $id = $this->model->insert($data); if (!$id) return $this->fail('DISPOSITION_CONFLICT');
            if (!$this->db->where('request_id',(int)$requestId)->update('requests', array('consultation_mode'=>$data['decision']))) return $this->fail('DISPOSITION_CONFLICT');
            if (!doclinc_append_request_event($requestId, 'visit_disposition.created', array('actor_user_id'=>$actorUserId,'domain_event_key'=>'visit-disposition:'.$id.':created','metadata'=>array('disposition_id'=>$id)), get_instance())) return $this->fail('DISPOSITION_CONFLICT');
            return $this->okRow($id, 1);
        });
    }
    public function revise($requestId, $actorUserId, $expectedVersion, array $input, $idempotencyKey)
    {
        if (trim((string) $idempotencyKey) === '') return $this->fail('IDEMPOTENCY_KEY_CONFLICT');
        return $this->transact(function () use ($requestId, $actorUserId, $expectedVersion, $input, $idempotencyKey) {
            $request = $this->lockRequest($requestId); if (!$request) return $this->fail('REQUEST_NOT_FOUND');
            $existingKey = $this->model->findByIdempotencyKey($idempotencyKey);
            if ($existingKey) return ((int)$existingKey->request_id === (int)$requestId && (int)$existingKey->created_by_user_id === (int)$actorUserId) ? $this->ok($existingKey) : $this->fail('IDEMPOTENCY_KEY_CONFLICT');
            $active = $this->model->getActiveForUpdate($requestId); if (!$active) return $this->fail('DISPOSITION_NOT_FOUND');
            if ((int)$active->version_no !== (int)$expectedVersion) return $this->fail('DISPOSITION_VERSION_CONFLICT');
            if (!$this->policy->canReview($requestId, $actorUserId)) return $this->fail('NOT_RESPONSIBLE_DOCTOR');
            if (in_array((string)$request->visit_status, array('en_route','arrived','in_service','completed'), true)) return $this->fail('DISPOSITION_LOCKED');
            $nextVersion = (int)$active->version_no + 1; $data = $this->normalize($input, $actorUserId, $requestId, $nextVersion, $idempotencyKey);
            $performer = $this->lockActivePerformer($requestId);
            $supersededAt = date('Y-m-d H:i:s.u');
            if (!$this->model->update($active->disposition_id, array('superseded_at'=>$supersededAt))) return $this->fail('DISPOSITION_CONFLICT');
            $newId = $this->model->insert($data); if (!$newId) return $this->fail('DISPOSITION_CONFLICT');
            if (!$this->model->update($active->disposition_id, array('superseded_by_disposition_id'=>$newId))) return $this->fail('DISPOSITION_CONFLICT');
            if ($performer && ($data['decision'] === 'non_visit' || ($data['required_profession'] !== null && !$this->performerMatches($performer, $data['required_profession']))) ) {
                $status = $data['decision'] === 'non_visit' ? 'dibatalkan' : 'diganti';
                if (!$this->db->where('visit_assignment_id',(int)$performer->visit_assignment_id)->update('request_visit_performer_assignments', array('status'=>$status,'ended_by_user_id'=>(int)$actorUserId,'end_reason'=>$data['decision']==='non_visit'?'Disposition changed to non_visit':'Disposition requirements changed','completed_at'=>null,'ended_at'=>date('Y-m-d H:i:s.u')))) return $this->fail('DISPOSITION_CONFLICT');
                if (!$this->db->where('request_id',(int)$requestId)->update('requests', array('visit_performer_user_id'=>null))) return $this->fail('DISPOSITION_CONFLICT');
            }
            if (!$this->db->where('request_id',(int)$requestId)->update('requests', array('consultation_mode'=>$data['decision']))) return $this->fail('DISPOSITION_CONFLICT');
            if (!doclinc_append_request_event($requestId, 'visit_disposition.revised', array('actor_user_id'=>$actorUserId,'domain_event_key'=>'visit-disposition:'.$newId.':created','metadata'=>array('disposition_id'=>$newId,'supersedes'=>(int)$active->disposition_id)), get_instance())) return $this->fail('DISPOSITION_CONFLICT');
            if (!doclinc_append_request_event($requestId, 'visit_disposition.superseded', array('actor_user_id'=>$actorUserId,'domain_event_key'=>'visit-disposition:'.$active->disposition_id.':superseded','metadata'=>array('disposition_id'=>(int)$active->disposition_id,'superseded_by'=>$newId)), get_instance())) return $this->fail('DISPOSITION_CONFLICT');
            return $this->okRow($newId, $nextVersion);
        });
    }
    private function normalize(array $input, $actor, $request, $version, $key)
    {
        $decision = strtolower(trim((string)($input['decision'] ?? ''))); if (!in_array($decision,array('visit','non_visit'),true)) throw new InvalidArgumentException('INVALID_DECISION');
        $urgency = isset($input['urgency']) && trim((string)$input['urgency']) !== '' ? strtolower(trim((string)$input['urgency'])) : null;
        if ($decision === 'visit' && !in_array($urgency,array('routine','priority','urgent'),true)) throw new InvalidArgumentException('INVALID_URGENCY');
        if ($decision === 'non_visit') $urgency = null;
        $profession = $decision === 'visit' && isset($input['required_profession']) && trim((string)$input['required_profession']) !== '' ? trim((string)$input['required_profession']) : null;
        if ($profession !== null && strlen($profession) > 100) throw new InvalidArgumentException('INVALID_REQUIRED_PROFESSION');
        return array('request_id'=>(int)$request,'version_no'=>(int)$version,'decision'=>$decision,'urgency'=>$urgency,'instructions'=>$this->text($input,'instructions'),'rationale'=>$this->text($input,'rationale'),'required_profession'=>$profession,'required_competency_note'=>$decision==='visit'?$this->text($input,'required_competency_note'):null,'created_by_user_id'=>(int)$actor,'created_at'=>date('Y-m-d H:i:s.u'),'idempotency_key'=>(string)$key);
    }
    private function text($input,$key){$v=isset($input[$key])?trim((string)$input[$key]):'';return $v===''?null:$v;}
    private function lockRequest($id){return $this->db->query('SELECT * FROM '.$this->db->dbprefix('requests').' WHERE request_id = ? FOR UPDATE',array((int)$id))->row();}
    private function lockActivePerformer($id){$q=$this->db->query('SELECT visit_assignment_id, user_id, staff_id FROM '.$this->db->dbprefix('request_visit_performer_assignments').' WHERE request_id = ? AND status = ? LIMIT 1 FOR UPDATE',array((int)$id,'aktif'));return $q?$q->row():null;}
    private function performerMatches($performer,$required){$q=$this->db->query('SELECT profesi FROM '.$this->db->dbprefix('puskesmas_staff').' WHERE staff_id = ? LIMIT 1',array((int)$performer->staff_id));$r=$q?$q->row():null;return $r && strcasecmp(trim((string)$r->profesi),trim((string)$required))===0;}
    private function transact($callback){$this->db->trans_begin();try{$r=$callback();if(empty($r['ok'])){$this->db->trans_rollback();return $r;}if(!$this->db->trans_status()||!$this->db->trans_commit()){ $this->db->trans_rollback();return $this->fail('DISPOSITION_CONFLICT');}return $r;}catch(InvalidArgumentException $e){$this->db->trans_rollback();return $this->fail($e->getMessage());}catch(Throwable $e){$this->db->trans_rollback();return $this->fail('DISPOSITION_CONFLICT');}}
    private function ok($row){return array('ok'=>true,'disposition_id'=>(int)$row->disposition_id,'version_no'=>(int)$row->version_no);}
    private function okRow($id,$version){return array('ok'=>true,'disposition_id'=>(int)$id,'version_no'=>(int)$version);}
    private function fail($code){return array('ok'=>false,'code'=>$code);}
}
