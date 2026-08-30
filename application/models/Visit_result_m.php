<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Visit_result_m
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: get_instance()->db;
    }

    public function findById($visitResultId)
    {
        return $this->db->query(
            'SELECT * FROM ' . $this->db->dbprefix('visit_results') . ' WHERE visit_result_id = ? LIMIT 1',
            array((int) $visitResultId)
        )->row();
    }

    public function findByIdForUpdate($visitResultId)
    {
        return $this->db->query(
            'SELECT * FROM ' . $this->db->dbprefix('visit_results') . ' WHERE visit_result_id = ? LIMIT 1 FOR UPDATE',
            array((int) $visitResultId)
        )->row();
    }

    public function getActiveDraftForAssignment($visitAssignmentId)
    {
        return $this->db->query(
            "SELECT * FROM " . $this->db->dbprefix('visit_results') . " WHERE visit_assignment_id = ? AND status = 'draft' ORDER BY version_no DESC LIMIT 1",
            array((int) $visitAssignmentId)
        )->row();
    }

    public function getActiveDraftForAssignmentForUpdate($visitAssignmentId)
    {
        return $this->db->query(
            "SELECT * FROM " . $this->db->dbprefix('visit_results') . " WHERE visit_assignment_id = ? AND status = 'draft' ORDER BY version_no DESC LIMIT 1 FOR UPDATE",
            array((int) $visitAssignmentId)
        )->row();
    }

    public function getLatestForAssignmentForUpdate($visitAssignmentId)
    {
        return $this->db->query(
            'SELECT * FROM ' . $this->db->dbprefix('visit_results') . ' WHERE visit_assignment_id = ? ORDER BY version_no DESC, visit_result_id DESC LIMIT 1 FOR UPDATE',
            array((int) $visitAssignmentId)
        )->row();
    }

    public function insertInitialDraft(array $row)
    {
        return $this->db->insert('visit_results', $row) ? (int) $this->db->insert_id() : 0;
    }

    public function guardedDraftUpdate($visitResultId, $expectedRevision, array $payload, $updatedAt)
    {
        $this->db->set('observation_summary', $payload['observation_summary']);
        $this->db->set('findings_json', $payload['findings_json']);
        $this->db->set('actions_json', $payload['actions_json']);
        $this->db->set('performer_notes', $payload['performer_notes']);
        $this->db->set('draft_revision', 'draft_revision + 1', false);
        $this->db->set('updated_at', (string) $updatedAt);
        $this->db->where('visit_result_id', (int) $visitResultId);
        $this->db->where('status', 'draft');
        $this->db->where('draft_revision', (int) $expectedRevision);
        if (!$this->db->update('visit_results')) {
            return false;
        }
        return (int) $this->db->affected_rows();
    }

    public function findBySubmissionKey($submissionKey)
    {
        return $this->db->query(
            'SELECT * FROM ' . $this->db->dbprefix('visit_results') . ' WHERE submission_key = ? LIMIT 1',
            array((string) $submissionKey)
        )->row();
    }

    public function measurementRowsForUpdate(array $measurementIds)
    {
        if (empty($measurementIds)) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($measurementIds), '?'));
        return $this->db->query(
            'SELECT * FROM ' . $this->db->dbprefix('request_vital_sign_measurements')
                . ' WHERE measurement_id IN (' . $placeholders . ') ORDER BY measurement_id ASC FOR UPDATE',
            array_values($measurementIds)
        )->result();
    }

    public function insertMeasurementLinks($visitResultId, array $measurementIds, $linkedAt)
    {
        foreach ($measurementIds as $measurementId) {
            if (!$this->db->insert('visit_result_vital_sign_measurements', array(
                'visit_result_id' => (int) $visitResultId,
                'measurement_id' => (int) $measurementId,
                'linked_at' => (string) $linkedAt,
            ))) {
                return false;
            }
        }
        return true;
    }

    public function linkedMeasurementIds($visitResultId)
    {
        $rows = $this->db->query(
            'SELECT measurement_id FROM ' . $this->db->dbprefix('visit_result_vital_sign_measurements')
                . ' WHERE visit_result_id = ? ORDER BY measurement_id ASC',
            array((int) $visitResultId)
        )->result();
        return array_map(function ($row) { return (int) $row->measurement_id; }, $rows);
    }

    public function guardedDraftSubmit($visitResultId, $performerUserId, $submissionKey, $submittedAt)
    {
        $this->db->set('status', 'submitted');
        $this->db->set('submitted_at', (string) $submittedAt);
        $this->db->set('submitted_by_user_id', (int) $performerUserId);
        $this->db->set('submission_key', (string) $submissionKey);
        $this->db->set('updated_at', (string) $submittedAt);
        $this->db->where('visit_result_id', (int) $visitResultId);
        $this->db->where('status', 'draft');
        if (!$this->db->update('visit_results')) {
            return false;
        }
        return (int) $this->db->affected_rows();
    }
}
