<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Visit_vital_signs_policy.php';

class Visit_vital_signs_service
{
	private $db;
	private $policy;

	public function __construct($db = null, $policy = null)
	{
		$CI = function_exists('get_instance') ? get_instance() : null;
		$this->db = $db ?: ($CI ? $CI->db : null);
		$this->policy = $policy ?: new Visit_vital_signs_policy();
	}

	public function schemaReady()
	{
		if (!$this->db || !$this->db->table_exists('request_vital_sign_measurements')) {
			return false;
		}
		$fields = array(
			'measurement_id', 'request_id', 'measured_by_user_id', 'measured_by_staff_id',
			'responsible_doctor_user_id', 'visit_performer_user_id', 'systolic', 'diastolic',
			'pulse', 'respiratory_rate', 'temperature_c', 'oxygen_saturation', 'notes',
			'measured_at', 'created_at',
		);
		foreach ($fields as $field) {
			if (!$this->db->field_exists($field, 'request_vital_sign_measurements')) {
				return false;
			}
		}
		return true;
	}

	public function latest($request_id)
	{
		if (!$this->schemaReady()) {
			return null;
		}
		return $this->db->where('request_id', (int) $request_id)
			->order_by('measurement_id', 'DESC')->limit(1)
			->get('request_vital_sign_measurements')->row();
	}

	public function currentAssignmentMeasurementState($request, $lock = false)
	{
		if (!$this->schemaReady()) {
			return array('schema_ready' => false, 'assignment_valid' => false, 'has_measurement' => false);
		}
		$assignment = $this->currentAssignment($request, $lock);
		if (!$assignment) {
			return array('schema_ready' => true, 'assignment_valid' => false, 'has_measurement' => false);
		}
		$count = $this->db
			->where('request_id', (int) $request->request_id)
			->where('responsible_doctor_user_id', $assignment['responsible_doctor_user_id'])
			->where('visit_performer_user_id', $assignment['visit_performer_user_id'])
			->where('measured_by_user_id', $assignment['visit_performer_user_id'])
			->where('measured_by_staff_id', $assignment['visit_performer_staff_id'])
			->where('measured_at >=', $assignment['assigned_at'])
			->count_all_results('request_vital_sign_measurements');
		return array(
			'schema_ready' => true,
			'assignment_valid' => true,
			'has_measurement' => $count > 0,
			'measurement_count' => $count,
			'assignment' => $assignment,
		);
	}

	public function record($request_id, $user_id, array $identity, array $input)
	{
		$request_id = (int) $request_id;
		$user_id = (int) $user_id;
		if (!$this->schemaReady()) {
			return $this->failure('Pencatatan tanda vital belum tersedia.', 'schema_unavailable');
		}
		$normalized = $this->policy->normalize($input);
		if (empty($normalized['valid'])) {
			return $this->failure($normalized['message'] ?? 'Data tanda vital tidak valid.', 'invalid_values');
		}
		if ($request_id < 1 || $user_id < 1 || empty($identity['valid']) || (int) ($identity['user_id'] ?? 0) !== $user_id) {
			return $this->failure('Anda tidak memiliki akses.', 'access_denied');
		}

		if (!$this->db->trans_begin()) {
			return $this->failure('Coba lagi.', 'write_failed');
		}
		try {
			$request = $this->db->query(
				'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
				array($request_id)
			)->row();
			$fresh_identity = function_exists('doclinc_dokter_identity_context')
				? doclinc_dokter_identity_context($user_id, true)
				: $identity;
			$access = $request && function_exists('doclinc_nakes_request_access_context')
				? doclinc_nakes_request_access_context($request, $fresh_identity)
				: array();
			$status = $request && function_exists('doclinc_normalize_visit_status')
				? doclinc_normalize_visit_status($request->visit_status ?? '')
				: '';
			if (!$request
				|| (string) ($request->request_status ?? '') !== 'Accepted'
				|| (string) ($request->consultation_mode ?? '') !== 'visit'
				|| empty($access['can_visit'])
				|| !in_array($status, array('arrived', 'in_service'), true)
				|| (int) ($request->visit_performer_user_id ?? 0) !== $user_id
				|| (int) ($request->responsible_doctor_user_id ?? 0) < 1) {
				$this->db->trans_rollback();
				return $this->failure('Anda tidak memiliki akses.', 'access_denied');
			}
			$assignment = $this->currentAssignment($request, true);
			if (!$assignment
				|| $assignment['visit_performer_user_id'] !== $user_id
				|| $assignment['visit_performer_staff_id'] !== (int) ($fresh_identity['staff_id'] ?? 0)) {
				$this->db->trans_rollback();
				return $this->failure('Anda tidak memiliki akses.', 'access_denied');
			}

			$now_row = $this->db->query('SELECT NOW(6) AS measured_at')->row();
			$now = $now_row ? (string) $now_row->measured_at : (new DateTimeImmutable())->format('Y-m-d H:i:s.u');
			$row = array_merge($normalized['values'], array(
				'request_id' => $request_id,
				'measured_by_user_id' => $user_id,
				'measured_by_staff_id' => $assignment['visit_performer_staff_id'],
				'responsible_doctor_user_id' => $assignment['responsible_doctor_user_id'],
				'visit_performer_user_id' => $assignment['visit_performer_user_id'],
				'measured_at' => $now,
			));
			if ($row['measured_by_staff_id'] < 1 || !$this->db->insert('request_vital_sign_measurements', $row)) {
				$this->db->trans_rollback();
				return $this->failure('Tanda vital belum tersimpan.', 'write_failed');
			}
			$measurement_id = (int) $this->db->insert_id();
			if ($this->db->trans_status() === false || !$this->db->trans_commit()) {
				$this->db->trans_rollback();
				return $this->failure('Tanda vital belum tersimpan.', 'write_failed');
			}
			return array('status' => 'success', 'message' => 'Tanda vital tersimpan.', 'measurement_id' => $measurement_id);
		} catch (Throwable $exception) {
			$this->db->trans_rollback();
			return $this->failure('Tanda vital belum tersimpan.', 'write_failed');
		}
	}

	private function currentAssignment($request, $lock)
	{
		if (!is_object($request)
			|| !$this->assignmentSchemaReady('request_responsible_doctor_assignments', 'responsible_assignment_id')
			|| !$this->assignmentSchemaReady('request_visit_performer_assignments', 'visit_assignment_id')) {
			return null;
		}
		$request_id = (int) ($request->request_id ?? 0);
		$responsible_user_id = (int) ($request->responsible_doctor_user_id ?? 0);
		$performer_user_id = (int) ($request->visit_performer_user_id ?? 0);
		if ($request_id < 1 || $responsible_user_id < 1 || $performer_user_id < 1) {
			return null;
		}
		$suffix = $lock ? ' FOR UPDATE' : '';
		$responsible = $this->db->query(
			'SELECT user_id,staff_id,assigned_at FROM ' . $this->db->dbprefix('request_responsible_doctor_assignments')
				. " WHERE request_id = ? AND status = 'aktif' ORDER BY responsible_assignment_id ASC" . $suffix,
			array($request_id)
		)->result();
		$performer = $this->db->query(
			'SELECT user_id,staff_id,assigned_at FROM ' . $this->db->dbprefix('request_visit_performer_assignments')
				. " WHERE request_id = ? AND status = 'aktif' ORDER BY visit_assignment_id ASC" . $suffix,
			array($request_id)
		)->result();
		if (count($responsible) !== 1 || count($performer) !== 1
			|| (int) $responsible[0]->user_id !== $responsible_user_id
			|| (int) $performer[0]->user_id !== $performer_user_id
			|| (int) $responsible[0]->staff_id < 1
			|| (int) $performer[0]->staff_id < 1
			|| empty($responsible[0]->assigned_at)
			|| empty($performer[0]->assigned_at)) {
			return null;
		}
		return array(
			'responsible_doctor_user_id' => $responsible_user_id,
			'responsible_doctor_staff_id' => (int) $responsible[0]->staff_id,
			'visit_performer_user_id' => $performer_user_id,
			'visit_performer_staff_id' => (int) $performer[0]->staff_id,
			'assigned_at' => max((string) $responsible[0]->assigned_at, (string) $performer[0]->assigned_at),
		);
	}

	private function assignmentSchemaReady($table, $id_field)
	{
		if (!$this->db->table_exists($table)) {
			return false;
		}
		foreach (array($id_field, 'request_id', 'staff_id', 'user_id', 'status', 'assigned_at') as $field) {
			if (!$this->db->field_exists($field, $table)) {
				return false;
			}
		}
		return true;
	}

	private function failure($message, $code)
	{
		return array('status' => 'error', 'message' => (string) $message, 'safe_error_code' => (string) $code);
	}
}
