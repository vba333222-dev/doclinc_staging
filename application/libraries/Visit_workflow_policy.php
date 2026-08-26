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
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM visit_dispositions WHERE request_id = ?');
        $id = (int) $requestId; $stmt->bind_param('i', $id); $stmt->execute(); $stmt->bind_result($count); $stmt->fetch(); $stmt->close();
        return (int) $count > 0;
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
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE request_id = ? AND user_id = ? AND status = 'aktif'");
        $requestId = (int) $requestId; $actor = (int) $actorUserId; $stmt->bind_param('ii', $requestId, $actor); $stmt->execute(); $stmt->bind_result($count); $stmt->fetch(); $stmt->close();
        return (int) $count === 1;
    }

    private function request($requestId)
    {
        if (is_callable($this->requestResolver)) { return call_user_func($this->requestResolver, (int) $requestId); }
        if (!$this->db) { return null; }
        $stmt = $this->db->prepare('SELECT request_status, assigned_puskesmas_code FROM requests WHERE request_id = ?');
        $id = (int) $requestId; $stmt->bind_param('i', $id); $stmt->execute(); $result = $stmt->get_result(); $row = $result->fetch_assoc(); $stmt->close(); return $row ?: null;
    }
    private function requestStatus($requestId) { $row = $this->request($requestId); return $row ? (string) $row['request_status'] : null; }
    private function requestFacility($requestId) { $row = $this->request($requestId); return $row ? (string) ($row['assigned_puskesmas_code'] ?? '') : ''; }
}
