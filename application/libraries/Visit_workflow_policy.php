<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Care_team_policy.php';

class Visit_workflow_policy
{
    private $db;
    private $enabled;
    private $identityResolver;
    private $requestResolver;
    private $assignmentResolver;

    public function __construct($db = null, $enabled = false, $identityResolver = null, $requestResolver = null, $assignmentResolver = null)
    {
        $this->db = $db;
        $this->enabled = $enabled === true;
        $this->identityResolver = $identityResolver;
        $this->requestResolver = $requestResolver;
        $this->assignmentResolver = $assignmentResolver;
    }

    public function isEnrolled($requestId)
    {
        if ($this->request($requestId) === null) { return false; }
        if (is_callable($this->assignmentResolver)) { return (bool) call_user_func($this->assignmentResolver, 'enrolled', (int) $requestId); }
        if (!$this->db) { return false; }
        $row = $this->db->select('COUNT(*) AS count', false)->where('request_id', (int) $requestId)->get('visit_dispositions')->row();
        return $row && (int) $row->count > 0;
    }

    public function mayEnrollNewRequest($requestId)
    {
        return $this->enabled && !$this->isEnrolled($requestId) && $this->requestStatus($requestId) === 'Accepted';
    }

    public function canCreateDisposition($requestId, $actorUserId)
    {
        return $this->mayEnrollNewRequest($requestId) && $this->isResponsibleDoctor($requestId, $actorUserId);
    }

    public function canActAsPerformer($requestId, $actorUserId)
    {
        if (!$this->isEnrolled($requestId)) { return false; }
        return $this->assignmentOwner($requestId, 'performer', $actorUserId) && $this->personalEligible($actorUserId, $this->requestFacility($requestId));
    }

    public function canReview($requestId, $actorUserId)
    {
        return $this->isEnrolled($requestId) && $this->isResponsibleDoctor($requestId, $actorUserId);
    }

    private function isResponsibleDoctor($requestId, $actorUserId)
    {
        if (!$this->personalEligible($actorUserId, $this->requestFacility($requestId))) { return false; }
        return $this->assignmentOwner($requestId, 'responsible', $actorUserId);
    }

    private function personalEligible($actorUserId, $facility)
    {
        $identity = is_callable($this->identityResolver)
            ? call_user_func($this->identityResolver, (int) $actorUserId)
            : (function_exists('doclinc_dokter_identity_context') ? doclinc_dokter_identity_context((int) $actorUserId, true) : array());
        $carePolicy = new Care_team_policy();
        return $carePolicy->personalEligible((array) $identity, $facility);
    }

    private function assignmentOwner($requestId, $kind, $actorUserId)
    {
        if (is_callable($this->assignmentResolver)) { return (bool) call_user_func($this->assignmentResolver, $kind, (int) $requestId, (int) $actorUserId); }
        if (!$this->db) { return false; }
        $table = $kind === 'responsible' ? 'request_responsible_doctor_assignments' : 'request_visit_performer_assignments';
        $row = $this->db->select('COUNT(*) AS count', false)->where('request_id', (int) $requestId)->where('user_id', (int) $actorUserId)->where('status', 'aktif')->get($table)->row();
        return $row && (int) $row->count === 1;
    }

    private function request($requestId)
    {
        if (is_callable($this->requestResolver)) { return call_user_func($this->requestResolver, (int) $requestId); }
        if (!$this->db) { return null; }
        $row = $this->db->select('request_status, assigned_puskesmas_code')->where('request_id', (int) $requestId)->get('requests')->row_array();
        return $row ?: null;
    }
    private function requestStatus($requestId) { $row = $this->request($requestId); return $row ? (string) $row['request_status'] : null; }
    private function requestFacility($requestId) { $row = $this->request($requestId); return $row ? (string) ($row['assigned_puskesmas_code'] ?? '') : ''; }
}
