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
}
