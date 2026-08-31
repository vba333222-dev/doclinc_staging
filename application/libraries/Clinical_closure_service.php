<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'models/Clinical_closure_m.php';
require_once APPPATH . 'libraries/Care_team_policy.php';
require_once APPPATH . 'libraries/Visit_workflow_policy.php';
require_once APPPATH . 'helpers/request_authz_helper.php';
require_once APPPATH . 'helpers/request_event_helper.php';
require_once APPPATH . 'helpers/request_realtime_helper.php';

class Clinical_closure_service
{
    private $db;
    private $model;
    private $policy;

    public function __construct($db = null, $model = null, $policy = null)
    {
        $ci = get_instance();
        $this->db = $db ?: $ci->db;
        $this->model = $model ?: new Clinical_closure_m($this->db);
        $this->policy = $policy ?: new Care_team_policy();
    }

    public function closeClinicalVisit($requestId, $userId, $idempotencyKey)
    {
        return $this->close(Care_team_policy::VISIT, $requestId, $userId, $idempotencyKey);
    }

    public function closeClinicalConsultation($requestId, $userId, $idempotencyKey)
    {
        return $this->close(Care_team_policy::NON_VISIT, $requestId, $userId, $idempotencyKey);
    }

    private function close($mode, $requestId, $userId, $key)
    {
        $requestId = (int) $requestId;
        $userId = (int) $userId;
        $key = trim((string) $key);
        if ($requestId < 1 || $userId < 1 || $key === '' || strlen($key) > 191) {
            return $this->fail('CLINICAL_CLOSURE_FORBIDDEN', 'INVALID_INPUT');
        }
        if (!$this->db->trans_begin()) { return $this->fail('WRITE_FAILED'); }
        try {
            $request = $this->db->query('SELECT * FROM requests WHERE request_id=? FOR UPDATE', array($requestId))->row();
            if (!$request) { return $this->rollback('REQUEST_NOT_FOUND'); }
            $fingerprint = hash('sha256', $requestId . "\n" . $mode . "\n" . $userId);
            $receipt = $this->model->findByIdempotencyKeyForUpdate($key);
            if ($receipt) {
                if ((string) $receipt->operation_fingerprint !== $fingerprint || (int) $receipt->request_id !== $requestId) {
                    return $this->rollback('CLOSURE_KEY_CONFLICT');
                }
                $this->db->trans_commit();
                return $this->success($receipt, true);
            }
            if ($this->policy->mode($request->consultation_mode ?? null) !== $mode) {
                return $this->rollback('CLINICAL_CLOSURE_MODE_MISMATCH');
            }
            $existing = $this->model->findByRequestIdForUpdate($requestId);
            if ($existing) { return $this->rollback('CLINICAL_ALREADY_CLOSED'); }
            if ((string) $request->request_status !== 'Accepted') { return $this->rollback('CLINICAL_CLOSURE_NOT_READY', 'REQUEST_NOT_ACCEPTED'); }
            if ($this->isCancelled($request)) { return $this->rollback('CLINICAL_CLOSURE_NOT_READY', 'REQUEST_CANCELLED'); }
            if (!$this->enrolled($requestId)) { return $this->rollback('CLINICAL_CLOSURE_NOT_ENROLLED'); }
            $facility = trim((string) ($request->assigned_puskesmas_code ?? ''));
            $identity = function_exists('doclinc_dokter_identity_context') ? (array) doclinc_dokter_identity_context($userId, true) : array();
            $rd = $this->db->query("SELECT * FROM request_responsible_doctor_assignments WHERE request_id=? AND status='aktif' ORDER BY responsible_assignment_id ASC LIMIT 1 FOR UPDATE", array($requestId))->row();
            $rdOk = $rd && (int) $rd->user_id === $userId && $this->policy->responsibleDoctorEligible($identity, $facility) && (int) $rd->staff_id === (int) ($identity['staff_id'] ?? 0);
            $responsibleId = $rdOk ? (int) $rd->responsible_assignment_id : null;
            $visitId = null;
            if ($mode === Care_team_policy::VISIT) {
                if (strtolower(trim((string) ($request->visit_status ?? ''))) !== 'completed') { return $this->rollback('CLINICAL_CLOSURE_NOT_READY', 'VISIT_NOT_COMPLETED'); }
                $disposition = $this->db->query('SELECT * FROM visit_dispositions WHERE request_id=? AND superseded_at IS NULL ORDER BY version_no DESC LIMIT 1 FOR UPDATE', array($requestId))->row();
                if (!$disposition || (string) $disposition->decision !== 'visit') { return $this->rollback('CLINICAL_CLOSURE_MODE_MISMATCH'); }
                $latest = $this->db->query("SELECT * FROM visit_results WHERE request_id=? AND status='submitted' ORDER BY version_no DESC, visit_result_id DESC LIMIT 1 FOR UPDATE", array($requestId))->row();
                if (!$latest) { return $this->rollback('CLINICAL_CLOSURE_NOT_READY', 'RESULT_NOT_SUBMITTED'); }
                $review = $this->db->query("SELECT * FROM clinical_reviews WHERE request_id=? AND visit_result_id=? AND decision='approved' ORDER BY clinical_review_id DESC LIMIT 1 FOR UPDATE", array($requestId, (int) $latest->visit_result_id))->row();
                if (!$review) { return $this->rollback('CLINICAL_CLOSURE_NOT_READY', 'LATEST_RESULT_NOT_APPROVED'); }
                $assignment = $this->db->query('SELECT * FROM request_visit_performer_assignments WHERE visit_assignment_id=? AND request_id=? LIMIT 1 FOR UPDATE', array((int) $latest->visit_assignment_id, $requestId))->row();
                $performerOk = $assignment && (int) $assignment->user_id === $userId && (int) $latest->performer_user_id === $userId && (int) $latest->performer_staff_id === (int) $assignment->staff_id && (string) $assignment->status === 'selesai' && $this->policy->visitPerformerEligible($identity, $facility) && $this->policy->doctorProfession($identity['staff_profesi'] ?? '') && $this->placement((int) $assignment->staff_id, $facility);
                if (!$rdOk && !$performerOk) { return $this->rollback('CLINICAL_CLOSURE_FORBIDDEN'); }
                if (!$rdOk) { $responsibleId = null; $visitId = (int) $assignment->visit_assignment_id; }
            } elseif (!$rdOk) {
                return $this->rollback('CLINICAL_CLOSURE_FORBIDDEN');
            }
            $record = $this->db->query('SELECT * FROM medicalrecords WHERE request_id=? ORDER BY record_id DESC LIMIT 1 FOR UPDATE', array($requestId))->row();
            if (!$record || $record->clinical_finalized_at === null) { return $this->rollback('CLINICAL_CLOSURE_NOT_READY', 'RECORD_NOT_FINALIZED'); }
            $clock = $this->db->query('SELECT CURRENT_TIMESTAMP(6) AS now')->row();
            $closedAt = $clock ? (string) $clock->now : '';
            if ($closedAt === '') { return $this->rollback('WRITE_FAILED'); }
            $authority = $rdOk ? 'responsible_doctor' : 'doctor_visit_performer';
            $receiptRow = array('request_id' => $requestId, 'closure_mode' => $mode, 'idempotency_key' => $key, 'operation_fingerprint' => $fingerprint, 'closed_by_user_id' => $userId, 'authority_source' => $authority, 'responsible_assignment_id' => $responsibleId, 'visit_assignment_id' => $visitId, 'closed_at' => $closedAt);
            $operationId = $this->model->insertSuccessfulReceipt($receiptRow);
            if (!$operationId) {
                $this->db->trans_rollback();
                $collision = $this->model->findByIdempotencyKey($key);
                if ($collision && (string) $collision->operation_fingerprint === $fingerprint && (int) $collision->request_id === $requestId) { return $this->success($collision, true); }
                if ($collision) { return $this->fail('CLOSURE_KEY_CONFLICT'); }
                return $this->fail('WRITE_FAILED');
            }
            $this->db->where('request_id', $requestId)->where('request_status', 'Accepted')->update('requests', array('request_status' => 'Completed'));
            if ((int) $this->db->affected_rows() !== 1) { return $this->rollback('WRITE_FAILED'); }
            $event = array('puskesmas_code' => $facility, 'actor_user_id' => $userId, 'actor_staff_id' => (int) ($identity['staff_id'] ?? 0), 'domain_event_key' => 'request:' . $requestId . ':clinical-closed', 'metadata' => array('request_id' => $requestId, 'closure_operation_id' => $operationId, 'closure_mode' => $mode, 'authority_source' => $authority, 'closed_by_user_id' => $userId, 'closed_at' => $closedAt));
            if (!doclinc_append_request_event($requestId, 'clinical.closed', $event, get_instance())) { return $this->rollback('WRITE_FAILED'); }
            if (function_exists('doclinc_realtime_requests_enabled') && doclinc_realtime_requests_enabled()) {
                $audiences = array('user:' . (int) $request->user_id);
                if ($rd && (int) $rd->user_id > 0) { $audiences[] = 'user:' . (int) $rd->user_id; }
                if ($mode === Care_team_policy::VISIT && $assignment && (int) $assignment->user_id > 0) { $audiences[] = 'user:' . (int) $assignment->user_id; }
                if ($facility !== '') { $audiences[] = 'puskesmas:' . $facility . ':ops'; }
                $notifications = array(array(
                    'recipient_user_id' => (int) $request->user_id,
                    'recipient_role' => 'warga',
                    'recipient_puskesmas_code' => null,
                    'actor_user_id' => $userId,
                    'event_type' => 'consultation_completed',
                    'entity_type' => 'request',
                    'entity_id' => (string) $requestId,
                    'title' => 'Konsultasi selesai',
                    'message' => 'Hasil konsultasi Anda sudah tersedia.',
                    'is_read' => 0,
                    'created_at' => $closedAt,
                ));
                $delivery = doclinc_request_realtime_delivery($this->db);
                if (!$delivery->deliver('completed', $requestId, $requestId, $audiences, $facility !== '' ? array($facility) : array(), $notifications)) {
                    return $this->rollback('WRITE_FAILED');
                }
            }
            if (!$this->db->trans_commit()) { return $this->fail('WRITE_FAILED'); }
            return array('status' => 'success', 'request_id' => $requestId, 'closure_operation_id' => $operationId, 'closed_at' => $closedAt, 'authority_source' => $authority, 'idempotent' => false);
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            return $this->fail('WRITE_FAILED');
        }
    }

    private function enrolled($requestId)
    {
        $row = $this->db->query('SELECT COUNT(*) AS c FROM visit_dispositions WHERE request_id=?', array($requestId))->row();
        return $row && (int) $row->c > 0;
    }

    private function isCancelled($request) { return strtolower(trim((string) ($request->request_status ?? ''))) === 'cancelled'; }
    private function placement($staffId, $facility)
    {
        if (!$this->tableExists('nakes_facility_placements')) { return true; }
        return (bool) $this->db->query("SELECT placement_id FROM nakes_facility_placements WHERE staff_id=? AND facility_code=? AND status='active' LIMIT 1", array($staffId, $facility))->row();
    }
    private function tableExists($table)
    {
        $row = $this->db->query('SELECT COUNT(*) AS c FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', array($table))->row();
        return $row && (int) $row->c === 1;
    }
    private function success($receipt, $idempotent)
    {
        return array('status' => 'success', 'request_id' => (int) $receipt->request_id, 'closure_operation_id' => (int) $receipt->closure_operation_id, 'closed_at' => (string) $receipt->closed_at, 'authority_source' => (string) $receipt->authority_source, 'idempotent' => (bool) $idempotent);
    }
    private function rollback($code, $reason = null) { $this->db->trans_rollback(); return $this->fail($code, $reason); }
    private function fail($code, $reason = null) { $result = array('status' => 'error', 'safe_error_code' => $code); if ($reason !== null) { $result['safe_reason'] = $reason; } return $result; }
}
