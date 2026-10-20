<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Clinical_workflow_requirements.php';
require_once __DIR__ . '/Visit_workflow_policy.php';
require_once __DIR__ . '/Care_team_policy.php';
require_once __DIR__ . '/../helpers/request_event_helper.php';

/**
 * Draf rekam medis untuk permintaan yang sudah masuk alur klinis.
 *
 * Jalur legacy `save_konsultasi_nakes` menolak permintaan enrolled, sedangkan
 * finalisasi hanya bekerja pada rekam medis yang sudah ada. Tanpa service ini
 * tidak ada cara bagi dokter penanggung jawab menulis diagnosis/terapi pada
 * permintaan enrolled.
 */
class Clinical_record_service
{
    private $db;
    private $policy;
    private $carePolicy;

    public function __construct($db = null, $policy = null, $carePolicy = null)
    {
        $this->db = $db ?: get_instance()->db;
        $enabled = get_instance()->config->item('visit_clinical_workflow_enabled') === true;
        $this->policy = $policy ?: new Visit_workflow_policy($this->db, $enabled);
        $this->carePolicy = $carePolicy ?: new Care_team_policy();
    }

    public function currentRecord($requestId)
    {
        $requestId = (int) $requestId;
        if ($requestId < 1 || !$this->schemaReady()) {
            return null;
        }
        return $this->db->query(
            'SELECT record_id, request_id, diagnosis, treatment, recommendations, anamnesis, '
            . 'responsible_doctor_user_id, recorded_by_user_id, created_at, '
            . 'clinical_finalized_at, clinical_finalized_by_user_id '
            . 'FROM ' . $this->db->dbprefix('medicalrecords') . ' WHERE request_id = ? '
            . 'ORDER BY record_id DESC LIMIT 1',
            array($requestId)
        )->row();
    }

    public function saveDraft($requestId, $userId, array $input, $idempotencyKey)
    {
        $requestId = (int) $requestId;
        $userId = (int) $userId;
        $key = trim((string) $idempotencyKey);
        if ($requestId < 1 || $userId < 1 || $key === '' || strlen($key) > 191) {
            return $this->fail('INVALID_CLINICAL_RECORD_INPUT');
        }
        if (!$this->schemaReady()) {
            return $this->fail('schema_unavailable');
        }

        $fields = $this->normalizeFields($input);
        if (empty($fields['valid'])) {
            return $this->fail($fields['code']);
        }

        $this->db->trans_begin();
        try {
            $request = $this->db->query(
                'SELECT request_id, request_status, consultation_mode FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
                array($requestId)
            )->row();
            if (!$request) { return $this->rollback('REQUEST_NOT_FOUND'); }
            if ((string) $request->request_status !== 'Accepted') { return $this->rollback('REQUEST_NOT_ACCEPTED'); }
            if (!$this->policy->isEnrolled($requestId)) { return $this->rollback('DISPOSITION_REQUIRED'); }
            if (!$this->policy->canReview($requestId, $userId)) { return $this->rollback('NOT_RESPONSIBLE_DOCTOR'); }

            $domainKey = 'clinical-record-draft:' . hash('sha256', $key);
            $existingEvent = $this->db->where('domain_event_key', $domainKey)->get('request_events')->row();
            if ($existingEvent) {
                $metadata = json_decode((string) ($existingEvent->metadata_json ?? ''), true);
                $recordId = is_array($metadata) ? (int) ($metadata['record_id'] ?? 0) : 0;
                if ((int) $existingEvent->request_id !== $requestId || $recordId < 1) {
                    return $this->rollback('IDEMPOTENCY_KEY_CONFLICT');
                }
                if (!$this->db->trans_commit()) { return $this->fail('WRITE_FAILED'); }
                return array('status' => 'success', 'record_id' => $recordId, 'idempotent' => true);
            }

            $record = $this->db->query(
                'SELECT * FROM ' . $this->db->dbprefix('medicalrecords') . ' WHERE request_id = ? ORDER BY record_id DESC LIMIT 1 FOR UPDATE',
                array($requestId)
            )->row();
            $now = date('Y-m-d H:i:s');

            if ($record) {
                if ($record->clinical_finalized_at !== null) {
                    return $this->rollback('CLINICAL_RECORD_ALREADY_FINALIZED');
                }
                $update = $fields['values'];
                if (!$this->db->where('record_id', (int) $record->record_id)->update('medicalrecords', $update)) {
                    return $this->rollback('WRITE_FAILED');
                }
                $recordId = (int) $record->record_id;
            } else {
                $insert = array_merge($fields['values'], array(
                    'request_id' => $requestId,
                    'created_at' => $now,
                    'responsible_doctor_user_id' => $userId,
                    'recorded_by_user_id' => $userId,
                ));
                if (!$this->db->insert('medicalrecords', $insert)) {
                    return $this->rollback('WRITE_FAILED');
                }
                $recordId = (int) $this->db->insert_id();
            }

            if ($recordId < 1 || !doclinc_append_request_event($requestId, 'clinical_record.drafted', array(
                'actor_user_id' => $userId,
                'domain_event_key' => $domainKey,
                'metadata' => array('record_id' => $recordId, 'source' => 'clinical_record_service'),
            ), get_instance())) {
                return $this->rollback('WRITE_FAILED');
            }
            if (!$this->db->trans_commit()) { return $this->fail('WRITE_FAILED'); }

            return array('status' => 'success', 'record_id' => $recordId, 'idempotent' => false);
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            return $this->fail('WRITE_FAILED');
        }
    }

    private function normalizeFields(array $input)
    {
        $diagnosis = $this->normalizeText(isset($input['diagnosis']) ? $input['diagnosis'] : '', 65535);
        $diagnosisLength = $diagnosis === false
            ? 0
            : (function_exists('mb_strlen') ? mb_strlen($diagnosis) : strlen($diagnosis));
        if ($diagnosis === false || $diagnosisLength < 2) {
            return array('valid' => false, 'code' => 'DIAGNOSIS_REQUIRED');
        }
        $treatment = $this->normalizeText(isset($input['treatment']) ? $input['treatment'] : '', 65535);
        $recommendations = $this->normalizeText(isset($input['recommendations']) ? $input['recommendations'] : '', 65535);
        $anamnesis = $this->normalizeText(isset($input['anamnesis']) ? $input['anamnesis'] : '', 65535);
        if ($treatment === false || $recommendations === false || $anamnesis === false) {
            return array('valid' => false, 'code' => 'INVALID_CLINICAL_RECORD_INPUT');
        }

        return array('valid' => true, 'values' => array(
            'diagnosis' => $diagnosis,
            'treatment' => $treatment === '' ? null : $treatment,
            'recommendations' => $recommendations === '' ? null : $recommendations,
            'anamnesis' => $anamnesis === '' ? null : $anamnesis,
        ));
    }

    private function normalizeText($value, $maxBytes)
    {
        if (!is_string($value)) {
            return false;
        }
        if (preg_match('//u', $value) !== 1) {
            return false;
        }
        $value = trim($value);
        if (strlen($value) > (int) $maxBytes) {
            return false;
        }
        return $value;
    }

    private function schemaReady()
    {
        return (new Clinical_workflow_requirements($this->db))->ready();
    }

    private function rollback($code)
    {
        $this->db->trans_rollback();
        return $this->fail($code);
    }

    private function fail($code)
    {
        return array('status' => 'error', 'safe_error_code' => (string) $code);
    }
}
