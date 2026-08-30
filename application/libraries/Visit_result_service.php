<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'models/Visit_result_m.php';
require_once APPPATH . 'helpers/request_event_helper.php';
require_once __DIR__ . '/Care_team_policy.php';

class Visit_result_service
{
    private $db;
    private $model;
    private $carePolicy;

    public function __construct($db = null, $model = null, $carePolicy = null)
    {
        $CI = function_exists('get_instance') ? get_instance() : null;
        $this->db = $db ?: ($CI ? $CI->db : null);
        $this->model = $model ?: new Visit_result_m($this->db);
        $this->carePolicy = $carePolicy ?: new Care_team_policy();
    }

    public function getOrCreateDraft($requestId, $performerUserId)
    {
        $requestId = (int) $requestId;
        $performerUserId = (int) $performerUserId;
        if ($requestId < 1 || $performerUserId < 1 || !$this->schemaReady()) {
            return $this->failure('ACCESS_DENIED');
        }
        if (!$this->db->trans_begin()) {
            return $this->failure('WRITE_FAILED');
        }
        try {
            $context = $this->lockedContext($requestId, $performerUserId);
            if (empty($context['ok'])) {
                return $this->rollbackFailure($context['code']);
            }
            $assignmentId = (int) $context['assignment']->visit_assignment_id;
            $draft = $this->model->getActiveDraftForAssignmentForUpdate($assignmentId);
            if ($draft) {
                if (!$this->commit()) { return $this->failure('WRITE_FAILED'); }
                return $this->draftSuccess($draft, false);
            }
            $latest = $this->model->getLatestForAssignmentForUpdate($assignmentId);
            if ($latest) {
                return $this->rollbackFailure((string) $latest->status === 'submitted' ? 'RESULT_ALREADY_SUBMITTED' : 'RESULT_CONFLICT');
            }
            $now = $this->now();
            $resultId = $this->model->insertInitialDraft(array(
                'request_id' => $requestId,
                'visit_assignment_id' => $assignmentId,
                'version_no' => 1,
                'supersedes_result_id' => null,
                'performer_user_id' => $performerUserId,
                'performer_staff_id' => (int) $context['assignment']->staff_id,
                'status' => 'draft',
                'draft_revision' => 0,
                'observation_summary' => null,
                'findings_json' => '[]',
                'actions_json' => '[]',
                'performer_notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'submitted_at' => null,
                'submitted_by_user_id' => null,
                'submission_key' => null,
            ));
            if ($resultId < 1 || $this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return $this->replayCanonicalDraft($requestId, $assignmentId, $performerUserId);
            }
            $created = $this->model->findByIdForUpdate($resultId);
            if (!$created || !$this->commit()) {
                return $this->failure('WRITE_FAILED');
            }
            return $this->draftSuccess($created, true);
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            return $this->failure('WRITE_FAILED');
        }
    }

    public function saveDraft($visitResultId, $performerUserId, $expectedRevision, array $payload)
    {
        $visitResultId = (int) $visitResultId;
        $performerUserId = (int) $performerUserId;
        $expectedRevision = (int) $expectedRevision;
        if ($visitResultId < 1 || $performerUserId < 1 || $expectedRevision < 0 || !$this->schemaReady()) {
            return $this->failure('RESULT_NOT_FOUND');
        }
        $candidate = $this->model->findById($visitResultId);
        if (!$candidate) {
            return $this->failure('RESULT_NOT_FOUND');
        }
        if (!$this->db->trans_begin()) {
            return $this->failure('WRITE_FAILED');
        }
        try {
            $context = $this->lockedContext((int) $candidate->request_id, $performerUserId);
            if (empty($context['ok'])) {
                return $this->rollbackFailure($context['code']);
            }
            $result = $this->model->findByIdForUpdate($visitResultId);
            if (!$result || (int) $result->request_id !== (int) $context['request']->request_id) {
                return $this->rollbackFailure('RESULT_NOT_FOUND');
            }
            if ((int) $result->visit_assignment_id !== (int) $context['assignment']->visit_assignment_id
                || (int) $result->performer_user_id !== $performerUserId
                || (int) $result->performer_staff_id !== (int) $context['assignment']->staff_id) {
                return $this->rollbackFailure('ACCESS_DENIED');
            }
            if ((string) $result->status !== 'draft') {
                return $this->rollbackFailure('RESULT_ALREADY_SUBMITTED');
            }
            $normalized = $this->normalizePayload($payload);
            if (empty($normalized['valid'])) {
                return $this->rollbackFailure('INVALID_RESULT_PAYLOAD');
            }
            $affected = $this->model->guardedDraftUpdate($visitResultId, $expectedRevision, $normalized['values'], $this->now());
            if ($affected === false || $this->db->trans_status() === false) {
                return $this->rollbackFailure('WRITE_FAILED');
            }
            if ($affected !== 1) {
                $fresh = $this->model->findByIdForUpdate($visitResultId);
                if (!$fresh) { return $this->rollbackFailure('RESULT_NOT_FOUND'); }
                if ((string) $fresh->status !== 'draft') { return $this->rollbackFailure('RESULT_ALREADY_SUBMITTED'); }
                if ((int) $fresh->draft_revision !== $expectedRevision) { return $this->rollbackFailure('STALE_DRAFT_REVISION'); }
                return $this->rollbackFailure('WRITE_FAILED');
            }
            $saved = $this->model->findByIdForUpdate($visitResultId);
            if (!$saved || !$this->commit()) {
                return $this->failure('WRITE_FAILED');
            }
            return $this->draftSuccess($saved, false);
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            return $this->failure('WRITE_FAILED');
        }
    }

    public function submit($visitResultId, $performerUserId, array $measurementIds, $submissionKey)
    {
        $visitResultId = (int) $visitResultId;
        $performerUserId = (int) $performerUserId;
        $normalizedIds = $this->normalizeMeasurementIds($measurementIds);
        if ($visitResultId < 1 || $performerUserId < 1 || !$this->schemaReady()) {
            return $this->failure('RESULT_NOT_FOUND');
        }
        if (empty($normalizedIds['valid'])) {
            return $this->failure('INVALID_MEASUREMENT_REFERENCES');
        }
        if (!$this->validSubmissionKey($submissionKey)) {
            return $this->failure('INVALID_SUBMISSION_KEY');
        }
        $submissionKey = trim((string) $submissionKey);
        $candidate = $this->model->findById($visitResultId);
        if (!$candidate) {
            return $this->failure('RESULT_NOT_FOUND');
        }
        if (!$this->db->trans_begin()) {
            return $this->failure('WRITE_FAILED');
        }
        try {
            $context = $this->lockedContext((int) $candidate->request_id, $performerUserId, array('completed'));
            if (empty($context['ok'])) {
                return $this->rollbackFailure($context['code']);
            }
            $result = $this->model->findByIdForUpdate($visitResultId);
            if (!$result || (int) $result->request_id !== (int) $context['request']->request_id) {
                return $this->rollbackFailure('RESULT_NOT_FOUND');
            }
            if ((int) $result->visit_assignment_id !== (int) $context['assignment']->visit_assignment_id
                || (int) $result->performer_user_id !== $performerUserId
                || (int) $result->performer_staff_id !== (int) $context['assignment']->staff_id) {
                return $this->rollbackFailure('ACCESS_DENIED');
            }
            $latest = $this->model->getLatestForAssignmentForUpdate((int) $context['assignment']->visit_assignment_id);
            if (!$latest || (int) $latest->visit_result_id !== $visitResultId) {
                return $this->rollbackFailure('RESULT_CONFLICT');
            }
            $keyOwner = $this->model->findBySubmissionKey($submissionKey);
            if ($keyOwner && (int) $keyOwner->visit_result_id !== $visitResultId) {
                return $this->rollbackFailure('SUBMISSION_KEY_CONFLICT');
            }
            if ((string) $result->status === 'submitted') {
                if ((string) $result->submission_key !== $submissionKey) {
                    return $this->rollbackFailure('RESULT_ALREADY_SUBMITTED');
                }
                if ($this->model->linkedMeasurementIds($visitResultId) !== $normalizedIds['values']) {
                    return $this->rollbackFailure('SUBMISSION_KEY_CONFLICT');
                }
                if (!$this->commit()) { return $this->failure('WRITE_FAILED'); }
                return $this->submissionSuccess($result, $normalizedIds['values'], true);
            }
            if ((string) $result->status !== 'draft') {
                return $this->rollbackFailure('RESULT_CONFLICT');
            }
            if ($keyOwner) {
                return $this->rollbackFailure('SUBMISSION_KEY_CONFLICT');
            }
            if (!$this->storedPayloadValid($result)) {
                return $this->rollbackFailure('INVALID_RESULT_PAYLOAD');
            }
            $measurements = $this->model->measurementRowsForUpdate($normalizedIds['values']);
            if (!$this->validMeasurements($measurements, $normalizedIds['values'], $context, $result)) {
                return $this->rollbackFailure('INVALID_MEASUREMENT_REFERENCES');
            }
            $now = $this->now();
            if (!$this->model->insertMeasurementLinks($visitResultId, $normalizedIds['values'], $now)) {
                return $this->rollbackFailure('WRITE_FAILED');
            }
            $affected = $this->model->guardedDraftSubmit($visitResultId, $performerUserId, $submissionKey, $now);
            if ($affected === false || $this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return $this->submissionWriteFailure($submissionKey, $visitResultId);
            }
            if ($affected !== 1) {
                $fresh = $this->model->findByIdForUpdate($visitResultId);
                if (!$fresh) { return $this->rollbackFailure('RESULT_NOT_FOUND'); }
                if ((string) $fresh->status === 'submitted') { return $this->rollbackFailure('RESULT_ALREADY_SUBMITTED'); }
                return $this->rollbackFailure('WRITE_FAILED');
            }
            if (!doclinc_append_request_event((int) $context['request']->request_id, 'visit_result.submitted', array(
                'puskesmas_code' => (string) ($context['request']->assigned_puskesmas_code ?? ''),
                'actor_user_id' => $performerUserId,
                'actor_staff_id' => (int) $context['assignment']->staff_id,
                'actor_role' => (string) ($context['identity']['role'] ?? ''),
                'domain_event_key' => 'visit-result:' . $visitResultId . ':submitted',
                'metadata' => array(
                    'visit_result_id' => $visitResultId,
                    'version_no' => (int) $result->version_no,
                    'visit_assignment_id' => (int) $result->visit_assignment_id,
                ),
            ), get_instance())) {
                return $this->rollbackFailure('WRITE_FAILED');
            }
            $submitted = $this->model->findByIdForUpdate($visitResultId);
            if (!$submitted || !$this->commit()) {
                return $this->failure('WRITE_FAILED');
            }
            return $this->submissionSuccess($submitted, $normalizedIds['values'], false);
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            return $this->submissionWriteFailure($submissionKey, $visitResultId);
        }
    }

    private function lockedContext($requestId, $performerUserId, ?array $allowedStates = null)
    {
        $request = $this->db->query(
            'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
            array((int) $requestId)
        )->row();
        if (!$request) { return array('ok' => false, 'code' => 'REQUEST_NOT_FOUND'); }
        if ((string) $request->request_status !== 'Accepted') { return array('ok' => false, 'code' => 'REQUEST_NOT_ACCEPTED'); }
        $disposition = $this->db->query(
            'SELECT * FROM ' . $this->db->dbprefix('visit_dispositions') . ' WHERE request_id = ? AND superseded_at IS NULL ORDER BY version_no DESC LIMIT 1 FOR UPDATE',
            array((int) $requestId)
        )->row();
        if (!$disposition) { return array('ok' => false, 'code' => 'WORKFLOW_NOT_ENROLLED'); }
        if ((string) $request->consultation_mode !== 'visit' || (string) $disposition->decision !== 'visit') {
            return array('ok' => false, 'code' => 'VISIT_NOT_REQUIRED');
        }
        $state = function_exists('doclinc_normalize_visit_status')
            ? doclinc_normalize_visit_status($request->visit_status ?? '')
            : strtolower(trim((string) ($request->visit_status ?? '')));
        $allowedStates = $allowedStates ?: array('arrived', 'in_service', 'completed');
        if (!in_array($state, $allowedStates, true)) {
            return array('ok' => false, 'code' => 'INVALID_WORKFLOW_STATE');
        }
        $assignments = $this->db->query(
            "SELECT * FROM " . $this->db->dbprefix('request_visit_performer_assignments') . " WHERE request_id = ? AND status = 'aktif' ORDER BY visit_assignment_id ASC FOR UPDATE",
            array((int) $requestId)
        )->result();
        if (count($assignments) !== 1) { return array('ok' => false, 'code' => 'ACCESS_DENIED'); }
        $assignment = $assignments[0];
        $identity = function_exists('doclinc_dokter_identity_context')
            ? doclinc_dokter_identity_context((int) $performerUserId, true)
            : array();
        $facility = trim((string) ($request->assigned_puskesmas_code ?? ''));
        if ((int) $assignment->user_id !== (int) $performerUserId
            || (int) $assignment->staff_id !== (int) ($identity['staff_id'] ?? 0)
            || (int) ($request->visit_performer_user_id ?? 0) !== (int) $performerUserId
            || !$this->carePolicy->visitPerformerEligible((array) $identity, $facility)
            || !$this->activePlacementExists((int) $assignment->staff_id, $facility)) {
            return array('ok' => false, 'code' => 'ACCESS_DENIED');
        }
        return array('ok' => true, 'request' => $request, 'disposition' => $disposition, 'assignment' => $assignment, 'identity' => $identity);
    }

    private function activePlacementExists($staffId, $facility)
    {
        $row = $this->db->query(
            "SELECT placement_id FROM " . $this->db->dbprefix('nakes_facility_placements') . " WHERE staff_id = ? AND facility_code = ? AND status = 'active' ORDER BY placement_id DESC LIMIT 1",
            array((int) $staffId, trim((string) $facility))
        )->row();
        return (bool) $row;
    }

    private function normalizePayload(array $payload)
    {
        $expected = array('actions', 'findings', 'observation_summary', 'performer_notes');
        $keys = array_keys($payload);
        sort($keys, SORT_STRING);
        if ($keys !== $expected) { return array('valid' => false); }
        $summary = $this->normalizeText($payload['observation_summary'], 65535, true);
        $notes = $this->normalizeText($payload['performer_notes'], 65535, true);
        $findings = $this->normalizeItems($payload['findings']);
        $actions = $this->normalizeItems($payload['actions']);
        if (!$summary['valid'] || !$notes['valid'] || !$findings['valid'] || !$actions['valid']) {
            return array('valid' => false);
        }
        $findingsJson = json_encode($findings['values'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $actionsJson = json_encode($actions['values'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($findingsJson) || !is_string($actionsJson)) { return array('valid' => false); }
        return array('valid' => true, 'values' => array(
            'observation_summary' => $summary['value'],
            'findings_json' => $findingsJson,
            'actions_json' => $actionsJson,
            'performer_notes' => $notes['value'],
        ));
    }

    private function storedPayloadValid($result)
    {
        $findings = json_decode((string) $result->findings_json, true);
        $actions = json_decode((string) $result->actions_json, true);
        if (!is_array($findings) || !is_array($actions) || json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        $normalized = $this->normalizePayload(array(
            'observation_summary' => $result->observation_summary,
            'findings' => $findings,
            'actions' => $actions,
            'performer_notes' => $result->performer_notes,
        ));
        return !empty($normalized['valid']);
    }

    private function normalizeMeasurementIds(array $measurementIds)
    {
        $normalized = array();
        foreach ($measurementIds as $measurementId) {
            if (is_int($measurementId)) {
                $id = $measurementId;
            } elseif (is_string($measurementId) && preg_match('/^[1-9][0-9]*$/D', $measurementId) === 1) {
                $id = (int) $measurementId;
            } else {
                return array('valid' => false);
            }
            if ($id < 1 || isset($normalized[$id])) {
                return array('valid' => false);
            }
            $normalized[$id] = $id;
        }
        $values = array_values($normalized);
        sort($values, SORT_NUMERIC);
        return array('valid' => true, 'values' => $values);
    }

    private function validSubmissionKey($submissionKey)
    {
        return is_string($submissionKey) && trim($submissionKey) !== '' && strlen(trim($submissionKey)) <= 191 && $this->validUtf8($submissionKey);
    }

    private function validMeasurements(array $measurements, array $measurementIds, array $context, $result)
    {
        if (count($measurements) !== count($measurementIds)) {
            return false;
        }
        $assignment = $context['assignment'];
        foreach ($measurements as $measurement) {
            if ((int) $measurement->request_id !== (int) $result->request_id
                || (int) $measurement->measured_by_user_id !== (int) $assignment->user_id
                || (int) $measurement->visit_performer_user_id !== (int) $assignment->user_id
                || (int) $measurement->measured_by_staff_id !== (int) $assignment->staff_id
                || (string) $measurement->measured_at < (string) $assignment->assigned_at
                || (!empty($assignment->ended_at) && (string) $measurement->measured_at > (string) $assignment->ended_at)) {
                return false;
            }
        }
        return true;
    }

    private function normalizeItems($items)
    {
        if (!is_array($items) || !$this->isList($items) || count($items) > 50) { return array('valid' => false); }
        $normalized = array();
        foreach ($items as $item) {
            if (!is_array($item)) { return array('valid' => false); }
            $keys = array_keys($item);
            sort($keys, SORT_STRING);
            if ($keys !== array('key', 'label', 'value') || !is_string($item['key']) || !is_string($item['label'])) {
                return array('valid' => false);
            }
            $key = trim($item['key']);
            $label = trim($item['label']);
            if (!$this->validUtf8($key) || !$this->validUtf8($label)
                || preg_match('/^[a-z0-9_]{1,64}$/D', $key) !== 1
                || $this->characterLength($label) > 120) {
                return array('valid' => false);
            }
            $value = $item['value'];
            if (!is_scalar($value) || (is_float($value) && !is_finite($value))) { return array('valid' => false); }
            $value = trim((string) $value);
            if (!$this->validUtf8($value) || $this->characterLength($value) > 2000) { return array('valid' => false); }
            $normalized[] = array('key' => $key, 'label' => $label, 'value' => $value);
        }
        return array('valid' => true, 'values' => $normalized);
    }

    private function normalizeText($value, $maxBytes, $nullable)
    {
        if ($value === null && $nullable) { return array('valid' => true, 'value' => null); }
        if (!is_string($value) || !$this->validUtf8($value) || strlen($value) > $maxBytes) { return array('valid' => false); }
        $value = trim($value);
        return array('valid' => true, 'value' => $value === '' && $nullable ? null : $value);
    }

    private function validUtf8($value)
    {
        return preg_match('//u', (string) $value) === 1;
    }

    private function characterLength($value)
    {
        return function_exists('mb_strlen') ? mb_strlen((string) $value, 'UTF-8') : count(preg_split('//u', (string) $value, -1, PREG_SPLIT_NO_EMPTY));
    }

    private function isList(array $value)
    {
        return function_exists('array_is_list') ? array_is_list($value) : array_keys($value) === range(0, count($value) - 1);
    }

    private function schemaReady()
    {
        return $this->db && $this->db->table_exists('visit_results') && $this->db->table_exists('visit_dispositions')
            && $this->db->table_exists('request_visit_performer_assignments') && $this->db->table_exists('nakes_facility_placements')
            && $this->db->table_exists('request_vital_sign_measurements') && $this->db->table_exists('visit_result_vital_sign_measurements')
            && $this->db->table_exists('request_events');
    }

    private function replayCanonicalDraft($requestId, $assignmentId, $performerUserId)
    {
        $draft = $this->model->getActiveDraftForAssignment($assignmentId);
        if ($draft && (int) $draft->request_id === (int) $requestId && (int) $draft->performer_user_id === (int) $performerUserId
            && (int) $draft->version_no === 1 && (string) $draft->status === 'draft') {
            return $this->draftSuccess($draft, false);
        }
        return $this->failure('WRITE_FAILED');
    }

    private function now()
    {
        $row = $this->db->query('SELECT NOW(6) AS server_now')->row();
        return $row ? (string) $row->server_now : (new DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }

    private function draftSuccess($row, $created)
    {
        return array(
            'status' => 'success',
            'visit_result_id' => (int) $row->visit_result_id,
            'request_id' => (int) $row->request_id,
            'visit_assignment_id' => (int) $row->visit_assignment_id,
            'version_no' => (int) $row->version_no,
            'draft_revision' => (int) $row->draft_revision,
            'created' => $created === true,
        );
    }

    private function submissionSuccess($row, array $measurementIds, $idempotentReplay)
    {
        return array(
            'status' => 'success',
            'visit_result_id' => (int) $row->visit_result_id,
            'request_id' => (int) $row->request_id,
            'visit_assignment_id' => (int) $row->visit_assignment_id,
            'version_no' => (int) $row->version_no,
            'measurement_ids' => $measurementIds,
            'idempotent_replay' => $idempotentReplay === true,
        );
    }

    private function submissionWriteFailure($submissionKey, $visitResultId)
    {
        $owner = $this->model->findBySubmissionKey($submissionKey);
        if ($owner && (int) $owner->visit_result_id !== (int) $visitResultId) {
            return $this->failure('SUBMISSION_KEY_CONFLICT');
        }
        return $this->failure('WRITE_FAILED');
    }

    private function rollbackFailure($code)
    {
        $this->db->trans_rollback();
        return $this->failure($code);
    }

    private function commit()
    {
        if ($this->db->trans_status() === false || !$this->db->trans_commit()) {
            $this->db->trans_rollback();
            return false;
        }
        return true;
    }

    private function failure($code)
    {
        return array('status' => 'error', 'message' => 'Operasi hasil Visit tidak dapat diproses.', 'safe_error_code' => (string) $code);
    }
}
