<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Visit_disposition_m
{
    private $db;
    public function __construct($db = null) { $this->db = $db ?: get_instance()->db; }
    public function getActiveForUpdate($requestId)
    {
        return $this->db->query('SELECT * FROM ' . $this->db->dbprefix('visit_dispositions') . ' WHERE request_id = ? AND superseded_at IS NULL ORDER BY version_no DESC LIMIT 1 FOR UPDATE', array((int) $requestId))->row();
    }
    public function getActive($requestId)
    {
        return $this->db->query('SELECT * FROM ' . $this->db->dbprefix('visit_dispositions') . ' WHERE request_id = ? AND superseded_at IS NULL ORDER BY version_no DESC LIMIT 1', array((int) $requestId))->row();
    }
    public function findByIdempotencyKey($key)
    {
        return $this->db->query('SELECT * FROM ' . $this->db->dbprefix('visit_dispositions') . ' WHERE idempotency_key = ? LIMIT 1', array((string) $key))->row();
    }
    public function insert(array $data) { return $this->db->insert('visit_dispositions', $data) ? (int) $this->db->insert_id() : 0; }
    public function update($id, array $data) { return (bool) $this->db->where('disposition_id', (int) $id)->update('visit_dispositions', $data); }
}
