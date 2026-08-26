<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Visit_workflow_state_resolver
{
    private $db; private $policy; private $enabled; private $reader;
    public function __construct($db, $policy = null, $enabled = false, $reader = null) { $this->db = $db; $this->policy = $policy; $this->enabled = $enabled === true; $this->reader = $reader; }
    public function resolve($requestId)
    {
        $id = (int) $requestId; $request = $this->row('SELECT request_status, visit_status, consultation_mode FROM requests WHERE request_id = ?', $id);
        if (!$request) return array('request_id'=>$id,'managed'=>false,'enrolled'=>false,'state'=>null);
        $enrolled = $this->policy ? $this->policy->isEnrolled($id) : ((int) $this->scalar('SELECT COUNT(*) FROM visit_dispositions WHERE request_id = ?', $id) > 0);
        if (!$enrolled && !$this->enabled) return array('request_id'=>$id,'managed'=>false,'enrolled'=>false,'state'=>null);
        if ((string)$request['request_status'] === 'Completed') return $this->result($id,$enrolled,'COMPLETED');
        if (!$enrolled) return $this->result($id,false,'WAITING_DOCTOR_DISPOSITION');
        $disposition = $this->row('SELECT decision FROM visit_dispositions WHERE request_id = ? AND superseded_at IS NULL ORDER BY version_no DESC LIMIT 1', $id);
        if (!$disposition) return $this->result($id,true,'WAITING_DOCTOR_DISPOSITION');
        if ($disposition['decision'] === 'non_visit') {
            $record = $this->row('SELECT clinical_finalized_at FROM medicalrecords WHERE request_id = ? ORDER BY record_id DESC LIMIT 1', $id);
            return $this->result($id,true, $record && $record['clinical_finalized_at'] !== null ? 'READY_FOR_CLOSURE' : 'WAITING_CLINICAL_FINALIZATION');
        }
        // Review state is anchored to the latest submitted result. A newer draft
        // must not hide a correction/approval decision on that submitted result.
        $result = $this->row("SELECT visit_result_id,status FROM visit_results WHERE request_id = ? AND status = 'submitted' ORDER BY version_no DESC, visit_result_id DESC LIMIT 1", $id);
        if ($result) {
            $review = $this->row('SELECT decision FROM clinical_reviews WHERE visit_result_id = ? LIMIT 1', (int)$result['visit_result_id']);
            if ($review && $review['decision'] === 'correction_required') return $this->result($id,true,'CORRECTION_REQUIRED');
            if ($review && $review['decision'] === 'approved') {
                $record = $this->row('SELECT clinical_finalized_at FROM medicalrecords WHERE request_id = ? ORDER BY record_id DESC LIMIT 1', $id);
                return $this->result($id,true, $record && $record['clinical_finalized_at'] !== null ? 'READY_FOR_CLOSURE' : 'WAITING_CLINICAL_FINALIZATION');
            }
            return $this->result($id,true,'WAITING_DOCTOR_REVIEW');
        }
        // A physically completed visit awaits its result even when its
        // performer assignment has already been closed as selesai.
        if ((string)$request['visit_status'] === 'completed') return $this->result($id,true,'WAITING_VISIT_RESULT');
        $active = $this->row("SELECT visit_assignment_id FROM request_visit_performer_assignments WHERE request_id = ? AND status = 'aktif' LIMIT 1", $id);
        if (!$active) return $this->result($id,true,'WAITING_PERFORMER_ASSIGNMENT');
        $status = strtoupper((string)$request['visit_status']);
        $map = array('not_started'=>'VISIT_NOT_STARTED','en_route'=>'VISIT_EN_ROUTE','arrived'=>'VISIT_ARRIVED','in_service'=>'VISIT_IN_SERVICE');
        return $this->result($id,true,$map[strtolower((string)$request['visit_status'])] ?? 'WAITING_PERFORMER_ASSIGNMENT');
    }
    private function result($id,$enrolled,$state) { return array('request_id'=>$id,'managed'=>true,'enrolled'=>$enrolled,'state'=>$state); }
    private function scalar($sql,$id) { $row=$this->row($sql,$id); return $row ? (int)array_values($row)[0] : 0; }
    private function row($sql,$id) { if (is_callable($this->reader)) return call_user_func($this->reader, $sql, $id); $query=$this->db->query($sql, array((int)$id)); return $query ? $query->row_array() : null; }
}
