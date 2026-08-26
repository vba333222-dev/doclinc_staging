<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Care_team_policy.php';
require_once __DIR__ . '/Visit_workflow_policy.php';
require_once __DIR__ . '/../helpers/request_authz_helper.php';
require_once __DIR__ . '/../helpers/request_event_helper.php';

class Visit_assignment_service
{
    private $db;
    private $policy;
    private $carePolicy;

    public function __construct($db = null, $policy = null)
    {
        $this->db = $db ?: get_instance()->db;
        $this->policy = $policy ?: new Visit_workflow_policy($this->db, false);
        $this->carePolicy = new Care_team_policy();
    }

    public function assign($requestId, $commandCenterUserId, $performerUserId, $idempotencyKey)
    {
        $requestId = (int) $requestId;
        $actor = (int) $commandCenterUserId;
        $performerUserId = (int) $performerUserId;
        $key = trim((string) $idempotencyKey);
        if ($requestId < 1 || $actor < 1 || $performerUserId < 1 || $key === '') {
            return $this->fail('ASSIGNMENT_CONFLICT');
        }
        return $this->transaction(function () use ($requestId, $actor, $performerUserId, $key) {
            $request = $this->lockRequest($requestId);
            if (!$request) return $this->fail('REQUEST_NOT_FOUND');
            $fingerprint = $this->fingerprint('assign', array($requestId, $actor, $performerUserId));
            $receipt = $this->receipt($key);
            if ($receipt) return $this->replay($receipt, 'assign', $requestId, $actor, $fingerprint);
            if ((string) $request->request_status !== 'Accepted') return $this->fail('REQUEST_NOT_ACCEPTED');
            if (!$this->policy->isEnrolled($requestId)) return $this->fail('WORKFLOW_NOT_ENROLLED');
            $disposition = $this->activeDisposition($requestId);
            if (!$disposition || (string) $disposition->decision !== 'visit') return $this->fail('VISIT_NOT_REQUIRED');
            if ((string) $request->visit_status !== 'not_started') return $this->fail('VISIT_ALREADY_STARTED');
            $actorIdentity = $this->identity($actor);
            $facility = trim((string) $request->assigned_puskesmas_code);
            if (!$this->carePolicy->commandCenterEligible($actorIdentity, $facility)) return $this->fail('NOT_COMMAND_CENTER');
            $performer = $this->identity($performerUserId);
            if (!$this->performerEligible($performer, $facility, $disposition->required_profession)) return $this->fail('PERFORMER_NOT_ELIGIBLE');
            if ($this->activeAssignment($requestId)) return $this->fail('ACTIVE_PERFORMER_EXISTS');
            $now = date('Y-m-d H:i:s.u');
            if (!$this->db->insert('request_visit_performer_assignments', array(
                'request_id' => $requestId,
                'staff_id' => (int) $performer['staff_id'],
                'user_id' => $performerUserId,
                'assigned_by_user_id' => $actor,
                'status' => 'aktif',
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ))) return $this->fail('ASSIGNMENT_CONFLICT');
            $assignmentId = (int) $this->db->insert_id();
            if (!$this->db->where('request_id', $requestId)->update('requests', array('visit_performer_user_id' => $performerUserId))) return $this->fail('ASSIGNMENT_CONFLICT');
            if (!$this->insertReceipt($requestId, 'assign', $actor, null, $assignmentId, $key, $fingerprint)) return $this->fail('ASSIGNMENT_CONFLICT');
            if (!$this->event($requestId, 'visit_assignment.assigned', 'visit-assignment:' . $assignmentId . ':assigned', $actor, array('assignment_id' => $assignmentId))) return $this->fail('ASSIGNMENT_CONFLICT');
            return $this->success($assignmentId, null);
        });
    }

    public function reassign($requestId, $commandCenterUserId, $newPerformerUserId, $reason, $idempotencyKey)
    {
        $requestId = (int) $requestId;
        $actor = (int) $commandCenterUserId;
        $newPerformerUserId = (int) $newPerformerUserId;
        $key = trim((string) $idempotencyKey);
        $reason = $this->reason($reason);
        if ($requestId < 1 || $actor < 1 || $newPerformerUserId < 1 || $key === '' || $reason === '') return $this->fail('ASSIGNMENT_CONFLICT');
        return $this->transaction(function () use ($requestId, $actor, $newPerformerUserId, $reason, $key) {
            $request = $this->lockRequest($requestId);
            if (!$request) return $this->fail('REQUEST_NOT_FOUND');
            $fingerprint = $this->fingerprint('reassign', array($requestId, $actor, $newPerformerUserId, $reason));
            $receipt = $this->receipt($key);
            if ($receipt) return $this->replay($receipt, 'reassign', $requestId, $actor, $fingerprint);
            if ((string) $request->request_status !== 'Accepted') return $this->fail('REQUEST_NOT_ACCEPTED');
            if (!$this->policy->isEnrolled($requestId)) return $this->fail('WORKFLOW_NOT_ENROLLED');
            $disposition = $this->activeDisposition($requestId);
            if (!$disposition || (string) $disposition->decision !== 'visit') return $this->fail('VISIT_NOT_REQUIRED');
            if ((string) $request->visit_status !== 'not_started') return $this->fail('VISIT_ALREADY_STARTED');
            $facility = trim((string) $request->assigned_puskesmas_code);
            if (!$this->carePolicy->commandCenterEligible($this->identity($actor), $facility)) return $this->fail('NOT_COMMAND_CENTER');
            $old = $this->activeAssignment($requestId);
            if (!$old) return $this->fail('NO_ACTIVE_PERFORMER');
            if ((int) $old->user_id === $newPerformerUserId) return $this->fail('SAME_PERFORMER');
            if (!$this->performerEligible($this->identity($newPerformerUserId), $facility, $disposition->required_profession)) return $this->fail('PERFORMER_NOT_ELIGIBLE');
            $now = date('Y-m-d H:i:s.u');
            if (!$this->db->where('visit_assignment_id', (int) $old->visit_assignment_id)->where('status', 'aktif')->update('request_visit_performer_assignments', array('status' => 'diganti', 'ended_by_user_id' => $actor, 'end_reason' => $reason, 'completed_at' => null, 'ended_at' => $now, 'updated_at' => $now))) return $this->fail('ASSIGNMENT_CONFLICT');
            $new = $this->identity($newPerformerUserId);
            if (!$this->db->insert('request_visit_performer_assignments', array('request_id' => $requestId, 'staff_id' => (int) $new['staff_id'], 'user_id' => $newPerformerUserId, 'assigned_by_user_id' => $actor, 'status' => 'aktif', 'assigned_at' => $now, 'created_at' => $now, 'updated_at' => $now))) return $this->fail('ASSIGNMENT_CONFLICT');
            $newId = (int) $this->db->insert_id();
            if (!$this->db->where('request_id', $requestId)->update('requests', array('visit_performer_user_id' => $newPerformerUserId))) return $this->fail('ASSIGNMENT_CONFLICT');
            if (!$this->insertReceipt($requestId, 'reassign', $actor, (int) $old->visit_assignment_id, $newId, $key, $fingerprint)) return $this->fail('ASSIGNMENT_CONFLICT');
            if (!$this->event($requestId, 'visit_assignment.reassigned', 'visit-assignment:' . $newId . ':reassigned', $actor, array('source_assignment_id' => (int) $old->visit_assignment_id, 'result_assignment_id' => $newId))) return $this->fail('ASSIGNMENT_CONFLICT');
            return $this->success($newId, (int) $old->visit_assignment_id);
        });
    }

    public function cancelBeforeStart($requestId, $actorUserId, $reason, $idempotencyKey)
    {
        $requestId = (int) $requestId; $actor = (int) $actorUserId; $key = trim((string) $idempotencyKey); $reason = $this->reason($reason);
        if ($requestId < 1 || $actor < 1 || $key === '' || $reason === '') return $this->fail('CANCELLATION_NOT_AUTHORIZED');
        return $this->transaction(function () use ($requestId, $actor, $reason, $key) {
            $request = $this->lockRequest($requestId); if (!$request) return $this->fail('REQUEST_NOT_FOUND');
            $fingerprint = $this->fingerprint('cancel_before_start', array($requestId, $actor, $reason));
            $receipt = $this->receipt($key); if ($receipt) return $this->replay($receipt, 'cancel_before_start', $requestId, $actor, $fingerprint);
            if ((string) $request->request_status !== 'Accepted') return $this->fail('CANCELLATION_NOT_AUTHORIZED');
            if (!$this->policy->isEnrolled($requestId)) return $this->fail('WORKFLOW_NOT_ENROLLED');
            $disposition = $this->activeDisposition($requestId); if (!$disposition || (string) $disposition->decision !== 'visit') return $this->fail('VISIT_NOT_REQUIRED');
            if ((string) $request->visit_status !== 'not_started') return $this->fail('VISIT_ALREADY_STARTED');
            $cancelAuthorized = function_exists('doclinc_can_cancel_request') && doclinc_can_cancel_request($requestId, $actor, 'dokter'); if (!$cancelAuthorized) return $this->fail('CANCELLATION_NOT_AUTHORIZED');
            $old = $this->activeAssignment($requestId); if (!$old) return $this->fail('NO_ACTIVE_PERFORMER');
            $now = date('Y-m-d H:i:s.u');
            if (!$this->db->where('visit_assignment_id', (int) $old->visit_assignment_id)->where('status', 'aktif')->update('request_visit_performer_assignments', array('status' => 'dibatalkan', 'ended_by_user_id' => $actor, 'end_reason' => $reason, 'completed_at' => null, 'ended_at' => $now, 'updated_at' => $now))) return $this->fail('ASSIGNMENT_CONFLICT');
            if (!$this->db->where('request_id', $requestId)->where('request_status', 'Accepted')->update('requests', array('request_status' => 'Cancelled', 'visit_performer_user_id' => null, 'updated_at' => date('Y-m-d H:i:s')))) return $this->fail('ASSIGNMENT_CONFLICT');
            if (!$this->insertReceipt($requestId, 'cancel_before_start', $actor, (int) $old->visit_assignment_id, null, $key, $fingerprint)) return $this->fail('ASSIGNMENT_CONFLICT');
            if (!$this->event($requestId, 'visit_assignment.cancelled', 'visit-assignment:' . $old->visit_assignment_id . ':cancelled', $actor, array('assignment_id' => (int) $old->visit_assignment_id, 'reason' => $reason))) return $this->fail('ASSIGNMENT_CONFLICT');
            return $this->success(null, (int) $old->visit_assignment_id);
        });
    }

    private function transaction($callback)
    {
        $this->db->trans_begin();
        try { $result = $callback(); if (empty($result['ok']) || $this->db->trans_status() === false) { $this->db->trans_rollback(); return $result; } if (!$this->db->trans_commit()) { $this->db->trans_rollback(); return $this->fail('ASSIGNMENT_CONFLICT'); } return $result; }
        catch (Throwable $exception) { $this->db->trans_rollback(); return $this->fail('ASSIGNMENT_CONFLICT'); }
    }

    private function lockRequest($id) { $q = $this->db->query('SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE', array((int) $id)); return $q ? $q->row() : null; }
    private function activeDisposition($id) { return $this->db->query('SELECT * FROM ' . $this->db->dbprefix('visit_dispositions') . ' WHERE request_id = ? AND superseded_at IS NULL ORDER BY version_no DESC LIMIT 1 FOR UPDATE', array((int) $id))->row(); }
    private function activeAssignment($id) { $q = $this->db->query('SELECT * FROM ' . $this->db->dbprefix('request_visit_performer_assignments') . ' WHERE request_id = ? AND status = ? LIMIT 1 FOR UPDATE', array((int) $id, 'aktif')); return $q ? $q->row() : null; }
    private function receipt($key) { $q = $this->db->query('SELECT * FROM ' . $this->db->dbprefix('visit_assignment_operations') . ' WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', array($key)); return $q ? $q->row() : null; }
    private function insertReceipt($request, $type, $actor, $source, $result, $key, $fingerprint) { return $this->db->insert('visit_assignment_operations', array('request_id' => $request, 'operation_type' => $type, 'actor_user_id' => $actor, 'source_assignment_id' => $source, 'result_assignment_id' => $result, 'idempotency_key' => $key, 'operation_fingerprint' => $fingerprint, 'created_at' => date('Y-m-d H:i:s.u'))); }
    private function identity($userId) { return function_exists('doclinc_dokter_identity_context') ? doclinc_dokter_identity_context((int) $userId, true) : array('valid' => false); }
    private function performerEligible($identity, $facility, $required) { if (!$this->carePolicy->visitPerformerEligible((array) $identity, $facility)) return false; if ($required !== null && trim((string) $required) !== '' && strcasecmp(trim((string) $identity['staff_profesi']), trim((string) $required)) !== 0) return false; $exists = $this->db->query('SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', array('nakes_facility_placements')); if (!$exists || (int) $exists->row()->table_count !== 1) return false; $placement = $this->db->query('SELECT placement_id FROM ' . $this->db->dbprefix('nakes_facility_placements') . ' WHERE staff_id = ? AND facility_code = ? AND status = ? LIMIT 1', array((int) $identity['staff_id'], trim((string) $facility), 'active')); return $placement && $placement->num_rows() === 1; }
    private function event($request, $type, $key, $actor, $metadata) { return function_exists('doclinc_append_request_event') && doclinc_append_request_event($request, $type, array('actor_user_id' => $actor, 'domain_event_key' => $key, 'metadata' => $metadata), get_instance()); }
    private function fingerprint($type, array $parts) { return hash('sha256', implode('|', array_merge(array('visit-assignment-op-v1', $type), array_map('strval', $parts)))); }
    private function reason($reason) { $reason = trim((string) $reason); $reason = preg_replace('/\s+/u', ' ', $reason); return strlen($reason) > 65535 ? substr($reason, 0, 65535) : $reason; }
    private function replay($receipt, $type, $request, $actor, $fingerprint) { if ((string) $receipt->operation_type !== $type || (int) $receipt->request_id !== $request || (int) $receipt->actor_user_id !== $actor || !hash_equals((string) $receipt->operation_fingerprint, $fingerprint)) return $this->fail('IDEMPOTENCY_KEY_CONFLICT'); return array('ok' => true, 'assignment_id' => $receipt->result_assignment_id === null ? null : (int) $receipt->result_assignment_id, 'source_assignment_id' => $receipt->source_assignment_id === null ? null : (int) $receipt->source_assignment_id, 'operation_id' => (int) $receipt->assignment_operation_id); }
    private function success($result, $source) { return array('ok' => true, 'assignment_id' => $result, 'source_assignment_id' => $source); }
    private function fail($code) { return array('ok' => false, 'code' => $code); }
}
