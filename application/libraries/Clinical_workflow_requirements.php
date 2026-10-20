<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Kesiapan skema alur klinis.
 *
 * Sebelum kelas ini ada, setiap service klinis menebak sendiri tabel/kolom yang
 * diperlukan. Bila satu tabel belum dimigrasikan, query melempar exception dan
 * endpoint membalas 500. Di sini daftar kebutuhannya ditulis satu kali, dan
 * semua pemanggil bisa gagal dengan 503 + kode `schema_unavailable`.
 *
 * Pemeriksaan memakai information_schema langsung, bukan
 * $this->db->table_exists()/field_exists(), karena CodeIgniter menyimpan
 * hasilnya di data_cache: tabel/kolom yang baru dibuat pada request yang sama
 * akan terbaca "tidak ada".
 */
class Clinical_workflow_requirements
{
	private $db;
	private $status = null;

	public function __construct($db = null)
	{
		$this->db = $db ?: get_instance()->db;
	}

	public function status()
	{
		if ($this->status !== null) {
			return $this->status;
		}

		$requirements = array(
			'nakes_facility_placements' => array('placement_id', 'staff_id', 'facility_code', 'status', 'active_staff_key'),
			'nakes_facility_transfers' => array('transfer_id', 'staff_id', 'status'),
			'visit_dispositions' => array('disposition_id', 'request_id', 'version_no', 'decision', 'urgency', 'required_profession', 'idempotency_key', 'superseded_at'),
			'visit_results' => array('visit_result_id', 'request_id', 'visit_assignment_id', 'status', 'draft_revision', 'submission_key', 'anamnesis', 'therapy'),
			'visit_result_vital_sign_measurements' => array('visit_result_id', 'measurement_id'),
			'request_vital_sign_measurements' => array('measurement_id', 'request_id', 'measured_by_user_id', 'visit_performer_user_id'),
			'clinical_reviews' => array('clinical_review_id', 'request_id', 'visit_result_id', 'reviewer_user_id', 'responsible_assignment_id', 'reviewer_visit_assignment_id', 'decision', 'idempotency_key'),
			'clinical_amendments' => array('amendment_id', 'record_id', 'idempotency_key'),
			'clinical_amendment_items' => array('amendment_item_id', 'amendment_id', 'field_key'),
			'clinical_closure_operations' => array('closure_operation_id', 'request_id', 'closure_mode', 'authority_source'),
			'visit_assignment_operations' => array('assignment_operation_id', 'operation_type', 'idempotency_key'),
		);

		$missing = array();
		foreach ($requirements as $table => $columns) {
			if (!$this->tableExists($table)) {
				$missing[] = 'table:' . $table;
				continue;
			}
			foreach ($columns as $column) {
				if (!$this->columnExists($table, $column)) {
					$missing[] = 'column:' . $table . '.' . $column;
				}
			}
		}

		foreach (array('ended_by_user_id', 'end_reason', 'completed_at', 'active_request_key') as $column) {
			if (!$this->columnExists('request_visit_performer_assignments', $column)) {
				$missing[] = 'column:request_visit_performer_assignments.' . $column;
			}
		}
		if (stripos($this->columnType('request_visit_performer_assignments', 'status'), "'selesai'") === false) {
			$missing[] = 'enum:request_visit_performer_assignments.status.selesai';
		}
		foreach (array('clinical_finalized_at', 'clinical_finalized_by_user_id') as $column) {
			if (!$this->columnExists('medicalrecords', $column)) {
				$missing[] = 'column:medicalrecords.' . $column;
			}
		}
		if (!$this->columnExists('request_events', 'domain_event_key')) {
			$missing[] = 'column:request_events.domain_event_key';
		}

		$this->status = array('ready' => empty($missing), 'missing' => $missing);
		return $this->status;
	}

	public function ready()
	{
		$status = $this->status();
		return !empty($status['ready']);
	}

	public function missing()
	{
		$status = $this->status();
		return isset($status['missing']) ? $status['missing'] : array();
	}

	private function tableExists($table)
	{
		$row = $this->db->query(
			'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
			array((string) $table)
		)->row();
		return $row && (int) $row->total > 0;
	}

	private function columnExists($table, $column)
	{
		$row = $this->db->query(
			'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
			array((string) $table, (string) $column)
		)->row();
		return $row && (int) $row->total > 0;
	}

	private function columnType($table, $column)
	{
		$row = $this->db->query(
			'SELECT COLUMN_TYPE AS column_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
			array((string) $table, (string) $column)
		)->row();
		return $row ? (string) $row->column_type : '';
	}
}
