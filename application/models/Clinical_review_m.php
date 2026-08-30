<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_review_m
{
    private $db;
    public function __construct($db = null) { $this->db = $db ?: get_instance()->db; }
    public function getForResult($visitResultId) { return $this->db->where('visit_result_id', (int) $visitResultId)->get('clinical_reviews')->row(); }
    public function getByIdempotencyKey($key) { return $this->db->where('idempotency_key', (string) $key)->get('clinical_reviews')->row(); }
    public function getForResultForUpdate($visitResultId) { return $this->db->query('SELECT * FROM clinical_reviews WHERE visit_result_id = ? LIMIT 1 FOR UPDATE', array((int) $visitResultId))->row(); }
    public function insertReview(array $row) { return $this->db->insert('clinical_reviews', $row) ? (int) $this->db->insert_id() : 0; }
}
