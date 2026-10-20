<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_review_m
{
    private $db;
    public function __construct($db = null) { $this->db = $db ?: get_instance()->db; }
    public function getForResult($visitResultId) { return $this->db->where('visit_result_id', (int) $visitResultId)->get('clinical_reviews')->row(); }
    public function getByIdempotencyKey($key) { return $this->db->where('idempotency_key', (string) $key)->get('clinical_reviews')->row(); }
    public function getByIdempotencyKeyForUpdate($key) { return $this->db->query('SELECT * FROM clinical_reviews WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', array((string) $key))->row(); }
    public function getForResultForUpdate($visitResultId) { return $this->db->query('SELECT * FROM clinical_reviews WHERE visit_result_id = ? LIMIT 1 FOR UPDATE', array((int) $visitResultId))->row(); }
    public function insertReview(array $row) { return $this->db->insert('clinical_reviews', $row) ? (int) $this->db->insert_id() : 0; }
    public function historyByRequest($requestId)
    {
        if (!$this->db->table_exists('clinical_reviews') || !$this->db->table_exists('visit_results')) {
            return array();
        }
        $prefix = (string) $this->db->dbprefix;
        return $this->db->query(
            'SELECT cr.clinical_review_id, cr.decision, cr.correction_reason, cr.review_notes, cr.reviewed_at, '
            . 'vr.visit_result_id, vr.version_no, u.nama AS reviewer_name '
            . 'FROM ' . $prefix . 'clinical_reviews cr '
            . 'INNER JOIN ' . $prefix . 'visit_results vr ON vr.visit_result_id = cr.visit_result_id '
            . 'LEFT JOIN ' . $prefix . 'users u ON u.userId = cr.reviewer_user_id '
            . 'WHERE vr.request_id = ? ORDER BY cr.reviewed_at DESC, cr.clinical_review_id DESC',
            array((int) $requestId)
        )->result_array();
    }
}
