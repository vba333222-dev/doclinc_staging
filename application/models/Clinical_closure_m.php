<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_closure_m
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: get_instance()->db;
    }

    public function findByIdempotencyKey($key)
    {
        $query = $this->db->where('idempotency_key', trim((string) $key))->limit(1)->get('clinical_closure_operations');
        return $query ? $query->row() : null;
    }

    public function findByIdempotencyKeyForUpdate($key)
    {
        $query = $this->db->query('SELECT * FROM clinical_closure_operations WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', array(trim((string) $key)));
        return $query ? $query->row() : null;
    }

    public function findByRequestIdForUpdate($requestId)
    {
        $query = $this->db->query('SELECT * FROM clinical_closure_operations WHERE request_id = ? LIMIT 1 FOR UPDATE', array((int) $requestId));
        return $query ? $query->row() : null;
    }

    public function insertSuccessfulReceipt(array $row)
    {
        $previousDebug = property_exists($this->db, 'db_debug') ? $this->db->db_debug : null;
        if (property_exists($this->db, 'db_debug')) { $this->db->db_debug = false; }
        try {
            $inserted = $this->db->insert('clinical_closure_operations', $row);
            $operationId = $inserted ? (int) $this->db->insert_id() : 0;
        } catch (Throwable $exception) {
            $operationId = 0;
        } finally {
            if (property_exists($this->db, 'db_debug')) { $this->db->db_debug = $previousDebug; }
        }
        return $operationId > 0 ? $operationId : false;
    }
}
