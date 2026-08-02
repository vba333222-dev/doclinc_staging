<?php
class Home_nakes_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db = $this->load->database('default', TRUE);
	}

	private function optional_request_selects()
	{
		$selects = [];
		foreach (['photos', 'video', 'lattitude_dokter', 'longitude_dokter'] as $field) {
			if (!$this->db->field_exists($field, 'requests')) {
				$selects[] = "NULL AS {$field}";
			}
		}

		return $selects;
	}

	private function select_request_base()
	{
		$this->db->select('requests.*, users.nama');
		$handler_name_parts = array();
		if ($this->db->field_exists('assigned_nakes_user_id', 'requests')) {
			$handler_name_parts[] = 'assigned_nakes_user.nama';
		}
		if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
			$handler_name_parts[] = 'accepted_nakes_user.nama';
		}
		$explicit_handler_name_expr = !empty($handler_name_parts) ? 'COALESCE(' . implode(', ', $handler_name_parts) . ')' : 'NULL';
		$legacy_handler_name_expr = 'COALESCE(' . implode(', ', array_merge($handler_name_parts, array('dokter_user.nama'))) . ')';
		$this->db->select("CASE WHEN requests.request_status = 'Pending' THEN {$explicit_handler_name_expr} ELSE {$legacy_handler_name_expr} END AS handling_nakes_name", FALSE);
		foreach ($this->optional_request_selects() as $select) {
			$this->db->select($select, FALSE);
		}
	}

	private function join_handling_nakes_display()
	{
		if ($this->db->field_exists('assigned_nakes_user_id', 'requests')) {
			$this->db->join('users AS assigned_nakes_user', 'requests.assigned_nakes_user_id = assigned_nakes_user.userId', 'left');
		}
		if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
			$this->db->join('users AS accepted_nakes_user', 'requests.accepted_by_user_id = accepted_nakes_user.userId', 'left');
		}
		$this->db->join('users AS dokter_user', 'requests.dokter_id = dokter_user.userId', 'left');
	}

	private function join_riwayat_or_default()
	{
		if ($this->db->table_exists('tbl_riwayat')) {
			$this->db->select('tbl_riwayat.riwayat');
			$this->db->join('tbl_riwayat', 'tbl_riwayat.idUser = users.userId', 'left');
		} else {
			$this->db->select("'' AS riwayat", FALSE);
		}
	}

	private function empty_query()
	{
		return $this->db->query('SELECT 1 WHERE 1 = 0');
	}

	public function empty_request_list()
	{
		return $this->empty_query();
	}

	private function normalize_puskesmas_code($puskesmas_code)
	{
		$puskesmas_code = trim((string) $puskesmas_code);
		return strtoupper($puskesmas_code) === 'DEFAULT' ? '' : $puskesmas_code;
	}

	private function request_assigned_puskesmas_code($request)
	{
		return $request && isset($request->assigned_puskesmas_code)
			? $this->normalize_puskesmas_code($request->assigned_puskesmas_code)
			: '';
	}

	private function get_user_puskesmas_code($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1) {
			return '';
		}

		$auth_db = $this->load->database('default', true);
		if (!$auth_db->field_exists('remark', 'users')) {
			return '';
		}

		$user = $auth_db
			->select('remark')
			->where('userId', $user_id)
			->where('role', 'dokter')
			->get('users')
			->row();
		if (method_exists($auth_db, 'reset_query')) {
			$auth_db->reset_query();
		}

		return $user ? $this->normalize_puskesmas_code($user->remark) : '';
	}

	private function request_matches_puskesmas_code($request, $puskesmas_code)
	{
		$request_code = $this->request_assigned_puskesmas_code($request);
		return $request_code === '' || $request_code === $this->normalize_puskesmas_code($puskesmas_code);
	}

	private function command_center_identity_matches($identity_context, $user_id, $puskesmas_code)
	{
		$user_id = (int) $user_id;
		$puskesmas_code = $this->normalize_puskesmas_code($puskesmas_code);
		if ($user_id < 1 || $puskesmas_code === '' || !function_exists('doclinc_dokter_identity_context')) {
			return false;
		}

		$database_context = doclinc_dokter_identity_context($user_id);
		return is_array($identity_context)
			&& !empty($identity_context['valid'])
			&& $identity_context['account_type'] === 'command_center'
			&& (int) $identity_context['user_id'] === $user_id
			&& trim((string) $identity_context['puskesmas_code']) === $puskesmas_code
			&& !empty($database_context['valid'])
			&& $database_context['account_type'] === 'command_center'
			&& trim((string) $database_context['puskesmas_code']) === $puskesmas_code;
	}

	private function request_matches_command_center_tenant($request, $user_id, $puskesmas_code)
	{
		if (!$request) {
			return false;
		}
		$request_code = isset($request->assigned_puskesmas_code)
			? trim((string) $request->assigned_puskesmas_code)
			: '';
		if ($request_code !== '') {
			return strtoupper($request_code) !== 'DEFAULT'
				&& $request_code === $this->normalize_puskesmas_code($puskesmas_code);
		}
		return isset($request->dokter_id) && (string) $request->dokter_id === (string) $user_id;
	}

	private function locked_command_center_context($user_id, $puskesmas_code, $request)
	{
		$user_id = (int) $user_id;
		$puskesmas_code = $this->normalize_puskesmas_code($puskesmas_code);
		if ($user_id < 1 || $puskesmas_code === '' || !$request
			|| !$this->db->field_exists('must_change_password', 'users')
			|| !$this->db->field_exists('status', 'm_puskesmas')
			|| !$this->request_matches_command_center_tenant($request, $user_id, $puskesmas_code)) {
			return null;
		}

		$user_query = $this->db->query(
			'SELECT userId, role, status, remark, must_change_password FROM ' . $this->db->dbprefix('users')
				. ' WHERE userId = ? FOR UPDATE',
			array($user_id)
		);
		$user = $user_query ? $user_query->row() : null;
		if (!$user
			|| (int) $user->userId !== $user_id
			|| (string) $user->role !== 'dokter'
			|| (string) $user->status !== 'aktif'
			|| (int) $user->must_change_password !== 0
			|| $this->normalize_puskesmas_code($user->remark) !== $puskesmas_code) {
			return null;
		}

		$puskesmas_query = $this->db->query(
			'SELECT kode_pkm, status FROM ' . $this->db->dbprefix('m_puskesmas')
				. ' WHERE kode_pkm = ? FOR UPDATE',
			array($puskesmas_code)
		);
		$puskesmas = $puskesmas_query ? $puskesmas_query->row() : null;
		if (!$puskesmas || (string) $puskesmas->kode_pkm !== $puskesmas_code || (string) $puskesmas->status !== 'aktif') {
			return null;
		}

		$canonical_query = $this->db->query(
			'SELECT userId FROM ' . $this->db->dbprefix('users')
				. ' WHERE role = ? AND status = ? AND TRIM(remark) = ? ORDER BY userId ASC LIMIT 1 FOR UPDATE',
			array('dokter', 'aktif', $puskesmas_code)
		);
		$canonical = $canonical_query ? $canonical_query->row() : null;
		if (!$canonical || (int) $canonical->userId !== $user_id) {
			return null;
		}

		$staff_query = $this->db->query(
			'SELECT staff_id FROM ' . $this->db->dbprefix('puskesmas_staff')
				. ' WHERE user_id = ? ORDER BY staff_id ASC FOR UPDATE',
			array($user_id)
		);
		if (!$staff_query || count($staff_query->result()) !== 0) {
			return null;
		}

		return array(
			'valid' => true,
			'account_type' => 'command_center',
			'user_id' => $user_id,
			'role' => 'dokter',
			'user_status' => 'aktif',
			'puskesmas_code' => $puskesmas_code,
		);
	}

	private function where_command_center_tenant($user_id, $puskesmas_code)
	{
		$user_id = (int) $user_id;
		$puskesmas_code = $this->normalize_puskesmas_code($puskesmas_code);
		$this->db->group_start()
			->where('TRIM(assigned_puskesmas_code) = ' . $this->db->escape($puskesmas_code), null, false)
			->or_group_start()
				->group_start()
					->where('assigned_puskesmas_code IS NULL', null, false)
					->or_where("TRIM(assigned_puskesmas_code) = ''", null, false)
				->group_end()
				->where('dokter_id', $user_id)
			->group_end()
		->group_end();
	}

	private function where_legacy_assigned_puskesmas($prefix = '')
	{
		$field = $prefix . 'assigned_puskesmas_code';
		$this->db->group_start();
		$this->db->where($field . ' IS NULL', null, false);
		$this->db->or_where("TRIM({$field}) = ''", null, false);
		$this->db->or_where("UPPER(TRIM({$field})) = 'DEFAULT'", null, false);
		$this->db->group_end();
	}

	private function where_pending_queue_owner($id, $puskesmas_code)
	{
		$puskesmas_code = $this->normalize_puskesmas_code($puskesmas_code);

		$this->db->group_start();
		if ($puskesmas_code !== '' && $this->db->field_exists('assigned_puskesmas_code', 'requests')) {
			$this->db->where('TRIM(assigned_puskesmas_code) = ' . $this->db->escape($puskesmas_code), null, false);
			$this->db->or_group_start();
			$this->where_legacy_assigned_puskesmas();
			$this->db->where('dokter_id', $id);
			$this->db->group_end();
		} else {
			$this->db->where('dokter_id', $id);
		}
		$this->db->group_end();
	}

	private function where_puskesmas_flow_owner($user_id, $puskesmas_code = '', $prefix = '')
	{
		if (!$this->db->field_exists('assigned_puskesmas_code', 'requests')) {
			return;
		}

		$puskesmas_code = $this->normalize_puskesmas_code($puskesmas_code);
		if ($puskesmas_code === '') {
			$puskesmas_code = $this->get_user_puskesmas_code($user_id);
		}

		if ($puskesmas_code === '') {
			$this->where_legacy_assigned_puskesmas($prefix);
			return;
		}

		$field = $prefix . 'assigned_puskesmas_code';
		$this->db->group_start();
		$this->db->where('TRIM(' . $field . ') = ' . $this->db->escape($puskesmas_code), null, false);
		$this->db->or_group_start();
		$this->where_legacy_assigned_puskesmas($prefix);
		$this->db->group_end();
		$this->db->group_end();
	}

	private function where_handling_nakes_owner($id, $prefix = '')
	{
		$this->db->group_start();
		if ($this->db->field_exists('assigned_nakes_user_id', 'requests')) {
			$this->db->where($prefix . 'assigned_nakes_user_id', $id);
			if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
				$this->db->or_where($prefix . 'accepted_by_user_id', $id);
			}
			if ($this->db->field_exists('dokter_id', 'requests')) {
				$this->db->or_where($prefix . 'dokter_id', $id);
			}
		} else {
			$this->db->where($prefix . 'dokter_id', $id);
			if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
				$this->db->or_where($prefix . 'accepted_by_user_id', $id);
			}
		}
		$this->db->group_end();
	}

	private function classified_identity_is($identity_context, $account_type)
	{
		return is_array($identity_context)
			&& !empty($identity_context['valid'])
			&& isset($identity_context['account_type'])
			&& $identity_context['account_type'] === $account_type
			&& !empty($identity_context['user_id'])
			&& !empty($identity_context['puskesmas_code']);
	}

	private function begin_classified_request_query($with_completed_result = false)
	{
		$this->select_request_base();
		$this->db
			->from('requests')
			->join('users', 'requests.user_id = users.userId', 'left');
		$this->join_handling_nakes_display();
		$this->join_riwayat_or_default();

		if (!$with_completed_result) {
			return;
		}

		if ($this->db->table_exists('medicalrecords')) {
			$this->db
				->select('medicalrecords.record_id AS medical_record_id')
				->select('medicalrecords.diagnosis AS diagnosa')
				->select('medicalrecords.recommendations AS saran')
				->select('medicalrecords.diagnosis AS diagnosis')
				->select('medicalrecords.treatment AS treatment')
				->select('medicalrecords.recommendations AS recommendations')
				->select('medicalrecords.created_at AS result_created_at')
				->join('(SELECT request_id, MAX(record_id) AS record_id FROM medicalrecords GROUP BY request_id) latest_medicalrecords', 'latest_medicalrecords.request_id = requests.request_id', 'left', false)
				->join('medicalrecords', 'medicalrecords.record_id = latest_medicalrecords.record_id', 'left');
			$this->db->select($this->db->field_exists('anamnesis', 'medicalrecords') ? 'medicalrecords.anamnesis AS anamnesis' : 'NULL AS anamnesis', false);
			if ($this->db->table_exists('medicalrecord_diagnoses')) {
				$this->db->select("COALESCE(NULLIF((SELECT GROUP_CONCAT(mrd.display_text ORDER BY mrd.position SEPARATOR '\\n') FROM " . $this->db->dbprefix('medicalrecord_diagnoses') . " mrd WHERE mrd.medicalrecord_id = medicalrecords.record_id AND mrd.position BETWEEN 1 AND 3), ''), medicalrecords.diagnosis) AS diagnoses_display", false);
			} else {
				$this->db->select('medicalrecords.diagnosis AS diagnoses_display', false);
			}
			return;
		}

		if ($this->db->table_exists('konsultasi')) {
			$this->db
				->select('konsultasi.*')
				->join('konsultasi', 'requests.request_id = konsultasi.request_id', 'left');
			foreach (array('diagnosa', 'saran', 'diagnosis', 'treatment', 'recommendations') as $field) {
				if (!$this->db->field_exists($field, 'konsultasi')) {
					$this->db->select('NULL AS ' . $field, false);
				}
			}
			return;
		}

		$this->db
			->select('NULL AS diagnosa', false)
			->select('NULL AS saran', false)
			->select('NULL AS diagnosis', false)
			->select('NULL AS treatment', false)
			->select('NULL AS recommendations', false);
	}

	private function where_command_center_puskesmas($user_id, $puskesmas_code)
	{
		$user_id = (int) $user_id;
		$puskesmas_code = $this->normalize_puskesmas_code($puskesmas_code);
		$this->db->group_start();
		if ($puskesmas_code !== '' && $this->db->field_exists('assigned_puskesmas_code', 'requests')) {
			$this->db->where('TRIM(requests.assigned_puskesmas_code) = ' . $this->db->escape($puskesmas_code), null, false);
			$this->db->or_group_start()
				->group_start()
					->where('requests.assigned_puskesmas_code IS NULL', null, false)
					->or_where("TRIM(requests.assigned_puskesmas_code) = ''", null, false)
				->group_end()
				->where('requests.dokter_id', $user_id)
			->group_end();
		} else {
			$this->db->where('1 = 0', null, false);
		}
		$this->db->group_end();
	}

	private function where_personal_request_visibility($identity_context)
	{
		$user_id = (int) $identity_context['user_id'];
		$staff_id = (int) $identity_context['staff_id'];
		$puskesmas_code = $this->normalize_puskesmas_code($identity_context['puskesmas_code']);
		if ($user_id < 1 || $staff_id < 1 || $puskesmas_code === '' || !$this->db->field_exists('assigned_puskesmas_code', 'requests')) {
			$this->db->where('1 = 0', null, false);
			return;
		}

		$this->db->where('TRIM(requests.assigned_puskesmas_code) = ' . $this->db->escape($puskesmas_code), null, false);
		$direct_assignment = $this->db->field_exists('assigned_nakes_user_id', 'requests')
			? 'COALESCE(requests.assigned_nakes_user_id, 0)'
			: '0';
		$legacy_owners = array();
		if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
			$legacy_owners[] = 'requests.accepted_by_user_id = ' . $user_id;
		}
		if ($this->db->field_exists('dokter_id', 'requests')) {
			$legacy_owners[] = 'requests.dokter_id = ' . $user_id;
		}
		$legacy_owner_sql = !empty($legacy_owners) ? '(' . implode(' OR ', $legacy_owners) . ')' : '0 = 1';

		$active_assignment_count = '0';
		$matching_assignment_count = '0';
		if ($this->staff_assignment_table_ready()) {
			$assignment_table = $this->db->dbprefix('request_staff_assignments');
			$staff_table = $this->db->dbprefix('puskesmas_staff');
			$active_assignment_count = "(SELECT COUNT(*) FROM {$assignment_table} rsa_visibility WHERE rsa_visibility.request_id = requests.request_id AND rsa_visibility.status = 'aktif')";
			$matching_assignment_count = "(SELECT COUNT(*) FROM {$assignment_table} rsa_personal INNER JOIN {$staff_table} ps_personal ON ps_personal.staff_id = rsa_personal.staff_id WHERE rsa_personal.request_id = requests.request_id AND rsa_personal.status = 'aktif' AND rsa_personal.staff_id = {$staff_id} AND ps_personal.user_id = {$user_id} AND ps_personal.status = 'aktif' AND TRIM(ps_personal.kode_pkm) = " . $this->db->escape($puskesmas_code) . ')';
		}

		$explicit_sources_agree = "({$active_assignment_count} = 0 OR ({$active_assignment_count} = 1 AND {$matching_assignment_count} = 1))";
		$matching_staff_assignment = "({$active_assignment_count} = 1 AND {$matching_assignment_count} = 1)";
		$legacy_fallback = "({$active_assignment_count} = 0 AND {$legacy_owner_sql})";
		$visibility_sql = "(({$direct_assignment} = {$user_id} AND {$explicit_sources_agree}) OR ({$direct_assignment} = 0 AND ({$matching_staff_assignment} OR {$legacy_fallback})))";
		$this->db->where($visibility_sql, null, false);
	}

	public function request_keluhan_command_center($identity_context)
	{
		if (!$this->classified_identity_is($identity_context, 'command_center')) {
			return $this->empty_query();
		}

		$this->begin_classified_request_query(false);
		$this->db->where('requests.request_status', 'Pending');
		$this->where_command_center_puskesmas($identity_context['user_id'], $identity_context['puskesmas_code']);
		return $this->db->order_by('requests.request_id', 'DESC')->get();
	}

	public function request_keluhan_accept_command_center($identity_context)
	{
		if (!$this->classified_identity_is($identity_context, 'command_center')) {
			return $this->empty_query();
		}

		$this->begin_classified_request_query(false);
		$this->db->where('requests.request_status', 'Accepted');
		$this->where_command_center_puskesmas($identity_context['user_id'], $identity_context['puskesmas_code']);
		return $this->db->order_by('requests.request_id', 'DESC')->get();
	}

	public function request_keluhan_completed_command_center($identity_context)
	{
		if (!$this->classified_identity_is($identity_context, 'command_center')) {
			return $this->empty_query();
		}

		$this->begin_classified_request_query(true);
		$this->db->where('requests.request_status', 'Completed');
		$this->where_command_center_puskesmas($identity_context['user_id'], $identity_context['puskesmas_code']);
		return $this->db->order_by('requests.request_id', 'DESC')->get();
	}

	public function request_keluhan_accept_personal($identity_context)
	{
		if (!$this->classified_identity_is($identity_context, 'personal') || empty($identity_context['staff_id'])) {
			return $this->empty_query();
		}

		$this->begin_classified_request_query(false);
		$this->db->where('requests.request_status', 'Accepted');
		$this->where_personal_request_visibility($identity_context);
		return $this->db->order_by('requests.request_id', 'DESC')->get();
	}

	public function request_keluhan_completed_personal($identity_context)
	{
		if (!$this->classified_identity_is($identity_context, 'personal') || empty($identity_context['staff_id'])) {
			return $this->empty_query();
		}

		$this->begin_classified_request_query(true);
		$this->db->where('requests.request_status', 'Completed');
		$this->where_personal_request_visibility($identity_context);
		return $this->db->order_by('requests.request_id', 'DESC')->get();
	}

	public function request_keluhan($id, $puskesmas_code = '')
	{
		$this->select_request_base();
		$this->db
			->from('requests')
			->join('users', 'requests.user_id = users.userId', 'left');
		$this->join_handling_nakes_display();
		$this->join_riwayat_or_default();

		$this->db->where('requests.request_status', 'Pending');
		$this->where_pending_queue_owner($id, $puskesmas_code);

		return $this->db
			->order_by('requests.request_id', 'DESC')
			->get();
	}

	// public function request_keluhan($id)
	// {
	// 	$CI = &get_instance();
	// 	$CI->load->library('encryption');

	// 	$this->db->select('requests.*, users.nama');
	// 	$this->db->from('requests');
	// 	$this->db->join('users', 'requests.user_id = users.userId');
	// 	$this->db->where('requests.request_status', 'Pending');
	// 	$this->db->where('requests.dokter_id', $id);
	// 	$this->db->order_by('requests.request_id', 'DESC');

	// 	$result = $this->db->get();

	// 	// Dekripsi kolom request_description
	// 	foreach ($result as $row) {
	// 		try {
	// 			$row->request_description = $CI->encryption->decrypt(base64_decode($row->request_description));
	// 		} catch (Exception $e) {
	// 			$row->request_description = '[Keluhan tidak dapat didekripsi]';
	// 		}
	// 	}

	// 	return $result;
	// }



	public function request_keluhan_accept($id)
	{
		$this->select_request_base();
		$this->db
			->from('requests')
			->join('users', 'requests.user_id = users.userId');
		$this->join_handling_nakes_display();
		$this->join_riwayat_or_default();

		$this->db->where('requests.request_status', 'Accepted');
		$this->where_handling_nakes_owner($id, 'requests.');

		return $this->db->order_by('requests.request_id', 'DESC')->get();
	}
	public function get_visit_location($request_id, $nakes_user_id, $identity_context = null)
	{
		$request_id = (int) $request_id;
		$nakes_user_id = (int) $nakes_user_id;
		if ($request_id < 1 || $nakes_user_id < 1) {
			return null;
		}
		$database_identity = function_exists('doclinc_dokter_identity_context')
			? doclinc_dokter_identity_context($nakes_user_id, true)
			: null;
		if (!is_array($identity_context)
			|| empty($identity_context['valid'])
			|| empty($database_identity['valid'])
			|| (int) $identity_context['user_id'] !== $nakes_user_id
			|| !function_exists('doclinc_can_view_nakes_request')
			|| !doclinc_can_view_nakes_request($request_id, $database_identity)) {
			return null;
		}

		$fields = array(
			'request_id',
			'request_status',
			'visit_status',
			'consultation_mode',
			'lattitude',
			'longitude',
			'lattitude_dokter',
			'longitude_dokter',
			'dokter_id',
			'accepted_by_user_id',
			'assigned_nakes_user_id',
			'assigned_puskesmas_code',
			'assigned_puskesmas_name',
			'patient_latitude',
			'patient_longitude',
			'updated_at',
		);
		$select = array();
		foreach ($fields as $field) {
			$select[] = $this->db->field_exists($field, 'requests') ? $field : 'NULL AS ' . $field;
		}

		$this->db
			->select(implode(', ', $select), FALSE)
			->where('request_id', $request_id)
			->where('request_status', 'Accepted');

		return $this->db->get('requests')->row();
	}
	public function request_keluhan_completed($id)
	{
		$this->select_request_base();
		$this->db
			->from('requests')
			->join('users', 'requests.user_id = users.userId');
		$this->join_handling_nakes_display();
		$this->join_riwayat_or_default();
		if ($this->db->table_exists('medicalrecords')) {
			$this->db
				->select('medicalrecords.record_id AS medical_record_id')
				->select('medicalrecords.diagnosis AS diagnosa')
				->select('medicalrecords.recommendations AS saran')
				->select('medicalrecords.diagnosis AS diagnosis')
				->select('medicalrecords.treatment AS treatment')
				->select('medicalrecords.recommendations AS recommendations')
				->select('medicalrecords.created_at AS result_created_at')
				->join('(SELECT request_id, MAX(record_id) AS record_id FROM medicalrecords GROUP BY request_id) latest_medicalrecords', 'latest_medicalrecords.request_id = requests.request_id', 'left', FALSE)
				->join('medicalrecords', 'medicalrecords.record_id = latest_medicalrecords.record_id', 'left');
			$this->db->select($this->db->field_exists('anamnesis', 'medicalrecords') ? 'medicalrecords.anamnesis AS anamnesis' : 'NULL AS anamnesis', FALSE);
			if ($this->db->table_exists('medicalrecord_diagnoses')) {
				$this->db->select("COALESCE(NULLIF((SELECT GROUP_CONCAT(mrd.display_text ORDER BY mrd.position SEPARATOR '\\n') FROM " . $this->db->dbprefix('medicalrecord_diagnoses') . " mrd WHERE mrd.medicalrecord_id = medicalrecords.record_id AND mrd.position BETWEEN 1 AND 3), ''), medicalrecords.diagnosis) AS diagnoses_display", FALSE);
			} else {
				$this->db->select('medicalrecords.diagnosis AS diagnoses_display', FALSE);
			}
		} else {
			if ($this->db->table_exists('konsultasi')) {
				$this->db
					->select('konsultasi.*')
					->join('konsultasi', 'requests.request_id = konsultasi.request_id', 'left');
				foreach (['diagnosa', 'saran', 'diagnosis', 'treatment', 'recommendations'] as $field) {
					if (!$this->db->field_exists($field, 'konsultasi')) {
						$this->db->select("NULL AS {$field}", FALSE);
					}
				}
			} else {
				$this->db
					->select('NULL AS diagnosa', FALSE)
					->select('NULL AS saran', FALSE)
					->select('NULL AS diagnosis', FALSE)
					->select('NULL AS treatment', FALSE)
					->select('NULL AS recommendations', FALSE);
			}
			$this->db->select('NULL AS anamnesis', FALSE);
		}

		$this->db->where('requests.request_status', 'Completed');
		$this->where_handling_nakes_owner($id, 'requests.');

		return $this->db
			->order_by('requests.request_id', 'DESC')
			->get();
	}
	public function check_ip_exists($ip_address)
	{
		if (!$this->db->table_exists('locations')) {
			return null;
		}

		$this->db->where('ip_address', $ip_address);
		$query = $this->db->get('locations');
		return $query->row(); //
	}
	public function update_location($data, $ip_address)
	{
		if (!$this->db->table_exists('locations')) {
			return false;
		}

		$this->db->where('ip_address', $ip_address);
		return $this->db->update('locations', $data);
	}
	public function save_location($data)
	{
		if (!$this->db->table_exists('locations')) {
			return false;
		}

		return $this->db->insert('locations', $data);
	}
	public function accept_request($id, $id_user, $latitude, $longitude, $puskesmas_code = '', $identity_context = null)
	{
		if (empty($id) || empty($id_user)) {
			return array('status' => 'error', 'message' => 'Data permintaan tidak lengkap.');
		}
		$puskesmas_code = $this->normalize_puskesmas_code($puskesmas_code);
		if (!$this->command_center_identity_matches($identity_context, $id_user, $puskesmas_code)) {
			return array('status' => 'error', 'message' => 'Anda tidak memiliki akses.');
		}

		$realtime_enabled = function_exists('doclinc_realtime_requests_enabled') && doclinc_realtime_requests_enabled();
		if ($realtime_enabled && !$this->db->trans_begin()) {
			return array('status' => 'error', 'message' => 'Permintaan belum dapat diterima.');
		}
		$request = $realtime_enabled
			? $this->db->query('SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE', array((int) $id))->row()
			: $this->db->where('request_id', $id)->get('requests')->row();
		if (!$request) {
			if ($realtime_enabled) { $this->db->trans_rollback(); }
			return array('status' => 'error', 'message' => 'Permintaan tidak ditemukan.');
		}
		$locked_identity = $realtime_enabled
			? $this->locked_command_center_context($id_user, $puskesmas_code, $request)
			: null;
		if (($realtime_enabled && !$locked_identity)
			|| (!$realtime_enabled && !$this->request_matches_command_center_tenant($request, $id_user, $puskesmas_code))) {
			if ($realtime_enabled) { $this->db->trans_rollback(); }
			return array('status' => 'error', 'message' => 'Permintaan tidak dapat diakses.');
		}

		if ($request->request_status === 'Accepted') {
			if ($realtime_enabled) { $this->db->trans_rollback(); }
			return array('status' => 'error', 'message' => 'Permintaan sudah diterima.');
		}

		if ($request->request_status !== 'Pending') {
			if ($realtime_enabled) { $this->db->trans_rollback(); }
			return array('status' => 'error', 'message' => 'Permintaan tidak dapat diterima.');
		}

		$data = [
			'request_status' => 'Accepted',
			'dokter_id' => $id_user,
		];
		if ($this->db->field_exists('updated_at', 'requests')) {
			$data['updated_at'] = date('Y-m-d H:i:s');
		}
		if ($this->db->field_exists('lattitude_dokter', 'requests')) {
			$data['lattitude_dokter'] = $latitude;
		}
		if ($this->db->field_exists('longitude_dokter', 'requests')) {
			$data['longitude_dokter'] = $longitude;
		}
		if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
			$data['accepted_by_user_id'] = $id_user;
		}
		if ($this->db->field_exists('assigned_nakes_user_id', 'requests')) {
			$data['assigned_nakes_user_id'] = $id_user;
		}
		if ($this->db->field_exists('assigned_nakes_at', 'requests')) {
			$data['assigned_nakes_at'] = date('Y-m-d H:i:s');
		}
		if ($this->db->field_exists('assigned_nakes_by_user_id', 'requests')) {
			$data['assigned_nakes_by_user_id'] = $id_user;
		}
		if ($this->db->field_exists('visit_status', 'requests')) {
			$data['visit_status'] = 'not_started';
		}

		$this->db
			->where('request_id', $id)
			->where('request_status', 'Pending');

		$this->where_command_center_tenant($id_user, $puskesmas_code);

		$this->db->update('requests', $data);
		if ($this->db->affected_rows() > 0) {
			$updated_request = $this->db
				->where('request_id', $id)
				->get('requests')
				->row();
			$event_puskesmas_code = $puskesmas_code;
			if ($updated_request && isset($updated_request->assigned_puskesmas_code) && trim((string) $updated_request->assigned_puskesmas_code) !== '') {
				$event_puskesmas_code = $updated_request->assigned_puskesmas_code;
			}
			$event_metadata = array(
				'request_status' => 'Accepted',
				'accepted_by_user_id' => $this->db->field_exists('accepted_by_user_id', 'requests') ? $id_user : null,
				'assigned_nakes_user_id' => $updated_request && isset($updated_request->assigned_nakes_user_id) ? (int) $updated_request->assigned_nakes_user_id : null,
			);
			if ($updated_request && function_exists('doclinc_request_queue_code')) {
				$event_metadata['queue_code'] = doclinc_request_queue_code($updated_request);
			}
			$this->append_request_event($id, 'request_accepted', array(
				'puskesmas_code' => $event_puskesmas_code,
				'actor_user_id' => $id_user,
				'actor_role' => 'dokter',
				'message' => 'Permintaan diterima oleh Puskesmas.',
				'metadata' => $event_metadata,
			));
			if ($realtime_enabled) {
				$notification = array(
					'recipient_user_id' => (int) $request->user_id, 'recipient_role' => 'warga',
					'actor_user_id' => (int) $id_user, 'event_type' => 'request_accepted',
					'entity_type' => 'request', 'entity_id' => (string) $id,
					'title' => 'Konsultasi diterima', 'message' => 'Permintaan konsultasi Anda sudah diterima oleh petugas.',
					'is_read' => 0, 'created_at' => date('Y-m-d H:i:s'),
				);
				$delivery = doclinc_request_realtime_delivery($this->db);
				if (!$delivery->deliver('accepted', $id, $id, array(
					'user:' . (int) $request->user_id,
					'puskesmas:' . $event_puskesmas_code . ':ops',
				), array($event_puskesmas_code), array($notification))
					|| $this->db->trans_status() === false || !$this->db->trans_commit()) {
					$this->db->trans_rollback();
					return array('status' => 'error', 'message' => 'Permintaan belum dapat diterima.');
				}
			}
			return array('status' => 'success', 'message' => 'Konsultasi diterima.', 'already_accepted' => false);
		}
		if ($realtime_enabled) { $this->db->trans_rollback(); }

		return array('status' => 'error', 'message' => 'Permintaan tidak dapat diakses.');
	}
	public function cancel_request($request_id, $user_id, $puskesmas_code = '', $identity_context = null)
	{
		$request_id = (int) $request_id;
		$user_id = (int) $user_id;
		if ($request_id < 1 || $user_id < 1) {
			return array('status' => 'error', 'message' => 'Data permintaan tidak lengkap.');
		}
		$puskesmas_code = $this->normalize_puskesmas_code($puskesmas_code);
		if (!is_array($identity_context)
			|| empty($identity_context['valid'])
			|| (int) $identity_context['user_id'] !== $user_id) {
			return array('status' => 'error', 'message' => 'Anda tidak memiliki akses.');
		}

		$this->db->trans_begin();
		$request = $this->db->query(
			'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
			array($request_id)
		)->row();
		if (!$request) {
			$this->db->trans_rollback();
			return array('status' => 'error', 'message' => 'Permintaan tidak ditemukan.');
		}
		$database_identity = $this->locked_command_center_context($user_id, $puskesmas_code, $request);
		if (empty($database_identity['valid'])
			|| $database_identity['account_type'] !== 'command_center'
			|| trim((string) $database_identity['puskesmas_code']) !== $puskesmas_code
			|| (string) $request->request_status !== 'Pending'
			|| !function_exists('doclinc_can_coordinate_request')
			|| !doclinc_can_coordinate_request($request, $database_identity)) {
			$this->db->trans_rollback();
			return array('status' => 'error', 'message' => 'Permintaan tidak dapat dibatalkan.');
		}

		$data = array('request_status' => 'Cancelled');
		if ($this->db->field_exists('updated_at', 'requests')) {
			$data['updated_at'] = date('Y-m-d H:i:s');
		}

		$this->db
			->where('request_id', $request_id)
			->where('request_status', 'Pending');
		$this->where_command_center_tenant($user_id, $puskesmas_code);

		$this->db->update('requests', $data);
		if ($this->db->affected_rows() > 0 && $this->db->trans_status() !== false) {
			$realtime_enabled = function_exists('doclinc_realtime_requests_enabled') && doclinc_realtime_requests_enabled();
			if (!$realtime_enabled && !$this->db->trans_commit()) {
				$this->db->trans_rollback();
				return array('status' => 'error', 'message' => 'Permintaan belum dapat dibatalkan.');
			}
			$event_puskesmas_code = $puskesmas_code;
			if (isset($request->assigned_puskesmas_code) && trim((string) $request->assigned_puskesmas_code) !== '') {
				$event_puskesmas_code = $request->assigned_puskesmas_code;
			}
			$event_metadata = array(
				'request_status' => 'Cancelled',
			);
			if (isset($request->cancel_reason) && trim((string) $request->cancel_reason) !== '') {
				$event_metadata['reason'] = trim((string) $request->cancel_reason);
			}
			$this->append_request_event($request_id, 'request_cancelled', array(
				'puskesmas_code' => $event_puskesmas_code,
				'actor_user_id' => $user_id,
				'actor_role' => 'dokter',
				'message' => 'Permintaan dibatalkan/ditolak oleh Puskesmas.',
				'metadata' => $event_metadata,
			));
			if ($realtime_enabled) {
				$notification = array(
					'recipient_user_id' => (int) $request->user_id, 'recipient_role' => 'warga',
					'actor_user_id' => $user_id, 'event_type' => 'request_cancelled',
					'entity_type' => 'request', 'entity_id' => (string) $request_id,
					'title' => 'Konsultasi dibatalkan', 'message' => 'Permintaan konsultasi Anda dibatalkan oleh petugas.',
					'is_read' => 0, 'created_at' => date('Y-m-d H:i:s'),
				);
				if (!doclinc_request_realtime_delivery($this->db)->deliver('cancelled', $request_id, $request_id, array(
					'user:' . (int) $request->user_id,
					'puskesmas:' . $event_puskesmas_code . ':ops',
				), array($event_puskesmas_code), array($notification))) {
					$this->db->trans_rollback();
					return array('status' => 'error', 'message' => 'Permintaan belum dapat dibatalkan.');
				}
			}
			if ($realtime_enabled && !$this->db->trans_commit()) {
				$this->db->trans_rollback();
				return array('status' => 'error', 'message' => 'Permintaan belum dapat dibatalkan.');
			}
			return array('status' => 'success', 'message' => 'Permintaan dibatalkan.');
		}

		$this->db->trans_rollback();
		return array('status' => 'error', 'message' => 'Permintaan tidak dapat diakses.');
	}
	public function update_visit_status($request_id, $user_id, $next_status, $identity_context = null)
	{
		$request_id = (int) $request_id;
		$user_id = (int) $user_id;
		$next_status = function_exists('doclinc_normalize_visit_status') ? doclinc_normalize_visit_status($next_status) : '';
		if ($request_id < 1 || $user_id < 1 || $next_status === '') {
			return array('status' => 'error', 'message' => 'Data status kunjungan tidak valid');
		}

		$required_fields = array('request_id', 'request_status', 'dokter_id', 'visit_status');
		foreach ($required_fields as $field) {
			if (!$this->db->field_exists($field, 'requests')) {
				return array('status' => 'error', 'message' => 'Kolom status kunjungan belum tersedia');
			}
		}

		if (!is_array($identity_context)
			|| empty($identity_context['valid'])
			|| (int) $identity_context['user_id'] !== $user_id) {
			return array('status' => 'error', 'message' => 'Anda tidak memiliki akses.');
		}

		$this->db->trans_begin();
		$request = $this->db->query(
			'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
			array($request_id)
		)->row();
		if (!$request) {
			$this->db->trans_rollback();
			return array('status' => 'error', 'message' => 'Permintaan tidak ditemukan.');
		}
		if ($request->request_status !== 'Accepted') {
			$this->db->trans_rollback();
			return array('status' => 'error', 'message' => 'Status kunjungan hanya dapat diperbarui untuk konsultasi aktif');
		}
		$database_identity = function_exists('doclinc_dokter_identity_context')
			? doclinc_dokter_identity_context($user_id, true)
			: null;
		$access_context = function_exists('doclinc_nakes_request_access_context')
			? doclinc_nakes_request_access_context($request_id, $database_identity)
			: null;
		if (empty($access_context['can_handle'])) {
			$this->db->trans_rollback();
			return array('status' => 'error', 'message' => 'Status kunjungan tidak dapat diperbarui');
		}

		$current_status = function_exists('doclinc_normalize_visit_status')
			? (doclinc_normalize_visit_status($request->visit_status) ?: 'not_started')
			: ($request->visit_status ?: 'not_started');
		if (function_exists('doclinc_allowed_visit_status_transition') && !doclinc_allowed_visit_status_transition($current_status, $next_status)) {
			$this->db->trans_rollback();
			return array('status' => 'error', 'message' => 'Perubahan status kunjungan tidak valid');
		}

		$date = date('Y-m-d H:i:s');
		$data = array('visit_status' => $next_status);
		if ($this->db->field_exists('consultation_mode', 'requests')) {
			$data['consultation_mode'] = 'visit';
		}
		$timestamp_fields = array(
			'en_route' => 'visit_started_at',
			'arrived' => 'visit_arrived_at',
			'in_service' => 'visit_in_service_at',
			'completed' => 'visit_completed_at',
		);
		if (isset($timestamp_fields[$next_status])) {
			$timestamp_field = $timestamp_fields[$next_status];
			if ($this->db->field_exists($timestamp_field, 'requests') && empty($request->{$timestamp_field})) {
				$data[$timestamp_field] = $date;
			}
		}
		if ($this->db->field_exists('updated_at', 'requests')) {
			$data['updated_at'] = $date;
		}

		$this->db
			->where('request_id', $request_id)
			->where('request_status', 'Accepted');
		$request_code = isset($request->assigned_puskesmas_code) ? trim((string) $request->assigned_puskesmas_code) : '';
		if ($request_code !== '') {
			$this->db->where('TRIM(assigned_puskesmas_code) = ' . $this->db->escape($database_identity['puskesmas_code']), null, false);
		} else {
			$this->db->group_start()->where('assigned_puskesmas_code IS NULL', null, false)->or_where("TRIM(assigned_puskesmas_code) = ''", null, false)->group_end();
		}
		$this->db->update('requests', $data);

		if ($this->db->affected_rows() < 1 && $current_status !== $next_status) {
			$this->db->trans_rollback();
			return array('status' => 'error', 'message' => 'Status kunjungan tidak dapat diperbarui');
		}
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return array('status' => 'error', 'message' => 'Status kunjungan tidak dapat diperbarui');
		}
		$this->db->trans_commit();

		$row = $this->db
			->where('request_id', $request_id)
			->get('requests')
			->row();
		$visit_status = $row && isset($row->visit_status) ? $row->visit_status : $next_status;
		if ($current_status !== $next_status) {
			$visit_event_map = array(
				'en_route' => array('visit_started', 'Perjalanan kunjungan dimulai.', 'visit_started_at'),
				'arrived' => array('visit_arrived', 'Petugas tiba di lokasi warga.', 'visit_arrived_at'),
				'in_service' => array('visit_in_service', 'Pelayanan di lokasi dimulai.', 'visit_in_service_at'),
				'completed' => array('visit_completed', 'Kunjungan di lokasi selesai.', 'visit_completed_at'),
			);
			if (isset($visit_event_map[$next_status])) {
				$event = $visit_event_map[$next_status];
				$event_metadata = array('visit_status' => $visit_status);
				if ($row && isset($row->{$event[2]}) && !empty($row->{$event[2]})) {
					$event_metadata[$event[2]] = $row->{$event[2]};
				}
				$this->append_request_event($request_id, $event[0], array(
					'puskesmas_code' => $row && isset($row->assigned_puskesmas_code) ? $row->assigned_puskesmas_code : null,
					'actor_user_id' => $user_id,
					'actor_role' => 'dokter',
					'message' => $event[1],
					'metadata' => $event_metadata,
				));
			}
		}

		return array(
			'status' => 'success',
			'message' => 'Status kunjungan diperbarui.',
			'visit_status' => $visit_status,
			'visit_status_label' => function_exists('doclinc_visit_status_label') ? doclinc_visit_status_label($visit_status) : $visit_status,
		);
	}
	public function update_visit_location($request_id, $user_id, $latitude, $longitude, $identity_context = null)
	{
		$request_id = (int) $request_id;
		$user_id = (int) $user_id;
		if ($request_id < 1 || $user_id < 1) {
			return false;
		}
		if (!is_array($identity_context)
			|| empty($identity_context['valid'])
			|| (int) $identity_context['user_id'] !== $user_id) {
			return false;
		}

		$this->db->trans_begin();
		$request = $this->db->query(
			'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
			array($request_id)
		)->row();
		$database_identity = function_exists('doclinc_dokter_identity_context')
			? doclinc_dokter_identity_context($user_id, true)
			: null;
		$access_context = $request && function_exists('doclinc_nakes_request_access_context')
			? doclinc_nakes_request_access_context($request_id, $database_identity)
			: null;
		if (!$request || empty($access_context['can_handle']) || (string) $request->request_status !== 'Accepted') {
			$this->db->trans_rollback();
			return false;
		}

		$data = array(
			'lattitude_dokter' => $latitude,
			'longitude_dokter' => $longitude,
		);
		if ($this->db->field_exists('consultation_mode', 'requests')) {
			$data['consultation_mode'] = 'visit';
		}
		if ($this->db->field_exists('updated_at', 'requests')) {
			$data['updated_at'] = date('Y-m-d H:i:s');
		}

		$this->db
			->where('request_id', $request_id)
			->where('request_status', 'Accepted');
		$request_code = isset($request->assigned_puskesmas_code) ? trim((string) $request->assigned_puskesmas_code) : '';
		if ($request_code !== '') {
			$this->db->where('TRIM(assigned_puskesmas_code) = ' . $this->db->escape($database_identity['puskesmas_code']), null, false);
		} else {
			$this->db->group_start()->where('assigned_puskesmas_code IS NULL', null, false)->or_where("TRIM(assigned_puskesmas_code) = ''", null, false)->group_end();
		}
		$this->db->update('requests', $data);
		$changed = $this->db->affected_rows() > 0;
		if (!$changed || $this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return false;
		}
		$this->db->trans_commit();
		return true;
	}
	public function get_location_user($id)
	{
		if (!$this->db->table_exists('locations')) {
			return $this->empty_query();
		}

		if ($this->db->field_exists('create_date', 'locations')) {
			$this->db->where('DATE(create_date)', 'DATE(NOW())', FALSE);
		}

		return $this->db
			->where('id_user', $id)
			->get('locations');
	}

	public function get_profile_by_id($id)
	{
		$this->db->select('users.*');
		if ($this->db->table_exists('m_puskesmas') && $this->db->field_exists('remark', 'users')) {
			$this->db->select('m_puskesmas.nama_puskesmas AS assigned_puskesmas_name');
			$this->db->join('m_puskesmas', 'm_puskesmas.kode_pkm = users.remark', 'left');
		} else {
			$this->db->select('NULL AS assigned_puskesmas_name', FALSE);
		}
		foreach (['foto', 'tgl', 'gender', 'no_hp', 'alamat'] as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				$this->db->select("NULL AS {$field}", FALSE);
			}
		}

		return $this->db->get_where('users', ['userId' => $id])->row_array();
	}

	private function normalize_staff_puskesmas_code($kode_pkm)
	{
		$kode_pkm = trim((string) $kode_pkm);
		return strtoupper($kode_pkm) === 'DEFAULT' ? '' : $kode_pkm;
	}

	private function can_query_puskesmas_staff()
	{
		if (!$this->db->table_exists('puskesmas_staff')) {
			return false;
		}

		foreach (array('staff_id', 'kode_pkm', 'nama', 'no_hp', 'profesi', 'nomor_sip', 'user_id', 'status') as $field) {
			if (!$this->db->field_exists($field, 'puskesmas_staff')) {
				return false;
			}
		}

		return true;
	}

	public function get_puskesmas_staff_by_code($kode_pkm)
	{
		$kode_pkm = $this->normalize_staff_puskesmas_code($kode_pkm);
		if ($kode_pkm === '' || !$this->can_query_puskesmas_staff()) {
			return array();
		}

		return $this->db
			->select('staff_id, nama, no_hp, profesi, nomor_sip, user_id, status')
			->from('puskesmas_staff')
			->where('kode_pkm', $kode_pkm)
			->where('status', 'aktif')
			->order_by('nama', 'ASC')
			->order_by('staff_id', 'ASC')
			->get()
			->result();
	}

	public function count_puskesmas_staff_by_code($kode_pkm)
	{
		$kode_pkm = $this->normalize_staff_puskesmas_code($kode_pkm);
		if ($kode_pkm === '' || !$this->can_query_puskesmas_staff()) {
			return 0;
		}

		return (int) $this->db
			->where('kode_pkm', $kode_pkm)
			->where('status', 'aktif')
			->count_all_results('puskesmas_staff');
	}

	public function staff_assignment_table_ready()
	{
		if (!$this->db->table_exists('request_staff_assignments') || !$this->can_query_puskesmas_staff()) {
			return false;
		}

		foreach (array('assignment_id', 'request_id', 'staff_id', 'kode_pkm', 'assigned_by_user_id', 'status', 'note', 'assigned_at', 'ended_at', 'created_at', 'updated_at') as $field) {
			if (!$this->db->field_exists($field, 'request_staff_assignments')) {
				return false;
			}
		}

		return true;
	}

	public function get_active_staff_options_by_code($kode_pkm)
	{
		$kode_pkm = $this->normalize_staff_puskesmas_code($kode_pkm);
		if ($kode_pkm === '' || !$this->can_query_puskesmas_staff()) {
			return array();
		}

		$rows = $this->db
			->select('staff_id, nama, no_hp, profesi, nomor_sip, user_id, status, kode_pkm')
			->from('puskesmas_staff')
			->where('kode_pkm', $kode_pkm)
			->where('status', 'aktif')
			->order_by('nama', 'ASC')
			->order_by('staff_id', 'ASC')
			->get()
			->result();

		foreach ($rows as $staff) {
			$owner = $this->staff_personal_owner_context($staff, false);
			$staff->personal_account_state = $owner['state'];
		}

		return $rows;
	}

	public function get_active_staff_assignments_by_request_ids($request_ids)
	{
		if (!$this->staff_assignment_table_ready() || !is_array($request_ids)) {
			return array();
		}

		$ids = array();
		foreach ($request_ids as $request_id) {
			$request_id = (int) $request_id;
			if ($request_id > 0) {
				$ids[$request_id] = $request_id;
			}
		}
		if (empty($ids)) {
			return array();
		}

		$rows = $this->db
			->select('rsa.assignment_id, rsa.request_id, rsa.staff_id, rsa.kode_pkm, rsa.assigned_by_user_id, rsa.status, rsa.note, rsa.assigned_at, rsa.ended_at, ps.user_id AS staff_user_id, ps.nama AS staff_nama, ps.no_hp AS staff_no_hp, ps.profesi AS staff_profesi, ps.nomor_sip AS staff_nomor_sip')
			->from('request_staff_assignments AS rsa')
			->join('puskesmas_staff AS ps', 'ps.staff_id = rsa.staff_id', 'left')
			->where('rsa.status', 'aktif')
			->where_in('rsa.request_id', array_values($ids))
			->order_by('rsa.assigned_at', 'DESC')
			->order_by('rsa.assignment_id', 'DESC')
			->get()
			->result();

		$map = array();
		foreach ($rows as $row) {
			$request_id = (int) $row->request_id;
			if ($request_id > 0 && !isset($map[$request_id])) {
				$map[$request_id] = $row;
			}
		}

		return $map;
	}

	public function get_active_staff_assignment($request_id)
	{
		$request_id = (int) $request_id;
		if ($request_id < 1) {
			return null;
		}

		$map = $this->get_active_staff_assignments_by_request_ids(array($request_id));
		return isset($map[$request_id]) ? $map[$request_id] : null;
	}

	public function get_latest_staff_assignments_by_request_ids($request_ids)
	{
		if (!$this->staff_assignment_table_ready() || !is_array($request_ids)) {
			return array();
		}

		$ids = array();
		foreach ($request_ids as $request_id) {
			$request_id = (int) $request_id;
			if ($request_id > 0) {
				$ids[$request_id] = $request_id;
			}
		}
		if (empty($ids)) {
			return array();
		}

		$rows = $this->db
			->select('rsa.assignment_id, rsa.request_id, rsa.staff_id, rsa.kode_pkm, rsa.assigned_by_user_id, rsa.status, rsa.note, rsa.assigned_at, rsa.ended_at, ps.user_id AS staff_user_id, ps.nama AS staff_nama, ps.no_hp AS staff_no_hp, ps.profesi AS staff_profesi, ps.nomor_sip AS staff_nomor_sip')
			->from('request_staff_assignments AS rsa')
			->join('puskesmas_staff AS ps', 'ps.staff_id = rsa.staff_id', 'left')
			->where_in('rsa.request_id', array_values($ids))
			->order_by('rsa.assigned_at', 'DESC')
			->order_by('rsa.assignment_id', 'DESC')
			->get()
			->result();

		$map = array();
		foreach ($rows as $row) {
			$request_id = (int) $row->request_id;
			if ($request_id > 0 && !isset($map[$request_id])) {
				$map[$request_id] = $row;
			}
		}

		return $map;
	}

	public function request_event_table_ready()
	{
		if (!$this->db->table_exists('request_events')) {
			return false;
		}

		foreach (array('event_id', 'request_id', 'event_type', 'created_at') as $field) {
			if (!$this->db->field_exists($field, 'request_events')) {
				return false;
			}
		}

		return true;
	}

	public function append_request_event($request_id, $event_type, $payload = array())
	{
		$request_id = (int) $request_id;
		$event_type = substr(trim((string) $event_type), 0, 80);
		if ($request_id < 1 || $event_type === '' || !$this->request_event_table_ready()) {
			return false;
		}

		$payload = is_array($payload) ? $payload : array();
		$data = array(
			'request_id' => $request_id,
			'event_type' => $event_type,
		);

		if ($this->db->field_exists('puskesmas_code', 'request_events')) {
			$puskesmas_code = isset($payload['puskesmas_code']) ? $this->normalize_staff_puskesmas_code($payload['puskesmas_code']) : '';
			$data['puskesmas_code'] = $puskesmas_code !== '' ? $puskesmas_code : null;
		}
		if ($this->db->field_exists('actor_user_id', 'request_events')) {
			$actor_user_id = isset($payload['actor_user_id']) ? (int) $payload['actor_user_id'] : 0;
			$data['actor_user_id'] = $actor_user_id > 0 ? $actor_user_id : null;
		}
		if ($this->db->field_exists('actor_staff_id', 'request_events')) {
			$actor_staff_id = isset($payload['actor_staff_id']) ? (int) $payload['actor_staff_id'] : 0;
			$data['actor_staff_id'] = $actor_staff_id > 0 ? $actor_staff_id : null;
		}
		if ($this->db->field_exists('actor_role', 'request_events')) {
			$actor_role = isset($payload['actor_role']) ? substr(trim((string) $payload['actor_role']), 0, 50) : '';
			$data['actor_role'] = $actor_role !== '' ? $actor_role : null;
		}
		if ($this->db->field_exists('message', 'request_events')) {
			$message = isset($payload['message']) ? trim((string) $payload['message']) : '';
			$data['message'] = $message !== '' ? $message : null;
		}
		if ($this->db->field_exists('metadata_json', 'request_events')) {
			$metadata = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : array();
			$encoded = !empty($metadata) ? json_encode($metadata) : null;
			$data['metadata_json'] = $encoded !== false ? $encoded : null;
		}

		return (bool) $this->db->insert('request_events', $data);
	}

	public function get_request_events_by_request_ids($request_ids, $limit_per_request = 5)
	{
		if (!$this->request_event_table_ready() || !is_array($request_ids)) {
			return array();
		}

		$ids = array();
		foreach ($request_ids as $request_id) {
			$request_id = (int) $request_id;
			if ($request_id > 0) {
				$ids[$request_id] = $request_id;
			}
		}
		if (empty($ids)) {
			return array();
		}

		$limit_per_request = (int) $limit_per_request;
		if ($limit_per_request < 1) {
			$limit_per_request = 5;
		}
		if ($limit_per_request > 10) {
			$limit_per_request = 10;
		}

		$select = array('event_id', 'request_id', 'event_type', 'created_at');
		foreach (array('puskesmas_code', 'actor_user_id', 'actor_staff_id', 'actor_role', 'message', 'metadata_json') as $field) {
			if ($this->db->field_exists($field, 'request_events')) {
				$select[] = $field;
			}
		}

		$rows = $this->db
			->select(implode(', ', $select))
			->from('request_events')
			->where_in('request_id', array_values($ids))
			->where_in('event_type', array(
				'pic_assigned',
				'pic_changed',
				'pic_cleared',
				'request_created',
				'request_accepted',
				'request_cancelled',
				'visit_started',
				'visit_arrived',
				'visit_in_service',
				'visit_completed',
				'request_completed',
			))
			->order_by('request_id', 'ASC')
			->order_by('created_at', 'DESC')
			->order_by('event_id', 'DESC')
			->get()
			->result();

		$map = array();
		foreach ($rows as $row) {
			$request_id = (int) $row->request_id;
			if (!isset($map[$request_id])) {
				$map[$request_id] = array();
			}
			if (count($map[$request_id]) < $limit_per_request) {
				$map[$request_id][] = $row;
			}
		}

		return $map;
	}

	public function assign_staff_to_request($request_id, $staff_id, $kode_pkm, $assigned_by_user_id, $note = '', $identity_context = null)
	{
		$request_id = (int) $request_id;
		$staff_id = (int) $staff_id;
		$assigned_by_user_id = (int) $assigned_by_user_id;
		$kode_pkm = $this->normalize_staff_puskesmas_code($kode_pkm);
		$note = trim((string) $note);

		if ($request_id < 1 || $staff_id < 1 || $assigned_by_user_id < 1 || $kode_pkm === '') {
			return array('status' => 'error', 'message' => 'Data PIC tidak valid.');
		}
		if (!$this->staff_assignment_table_ready()) {
			return array('status' => 'error', 'message' => 'Penugasan PIC belum tersedia.');
		}
		if (!is_array($identity_context)
			|| empty($identity_context['valid'])
			|| $identity_context['account_type'] !== 'command_center'
			|| (int) $identity_context['user_id'] !== $assigned_by_user_id
			|| trim((string) $identity_context['puskesmas_code']) !== $kode_pkm) {
			return array('status' => 'error', 'message' => 'Anda tidak memiliki akses.');
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$result = array('status' => 'error', 'message' => 'Gagal memperbarui PIC.');
		$transaction_started = false;
		$committed = false;
		$staff = null;
		$previous_assignment = null;
		$assignment_event = null;
		$new_assignment_id = null;
		$abort = function ($message, $safe_error_code = 'staff_assignment_failed') use (&$result) {
			$result = array('status' => 'error', 'message' => $message, 'safe_error_code' => $safe_error_code);
			throw new RuntimeException('staff_assignment_aborted');
		};

		try {
			if (!$this->db->trans_begin()) {
				$abort('Gagal memperbarui PIC.');
			}
			$transaction_started = true;

			$request_query = $this->db->query(
				'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
				array($request_id)
			);
			$request = $request_query ? $request_query->row() : null;
			$database_identity = $this->locked_command_center_context($assigned_by_user_id, $kode_pkm, $request);
			if (empty($database_identity['valid'])
				|| $database_identity['account_type'] !== 'command_center'
				|| trim((string) $database_identity['puskesmas_code']) !== $kode_pkm
				|| !$request
				|| $this->request_assigned_puskesmas_code($request) !== $kode_pkm
				|| !function_exists('doclinc_can_coordinate_request')
				|| !doclinc_can_coordinate_request($request, $database_identity)) {
				$abort('Permintaan tidak dapat diakses.');
			}
			if ((string) $request->request_status !== 'Accepted') {
				$abort('PIC hanya dapat dipilih saat konsultasi aktif.');
			}

			$active_assignments = $this->locked_active_staff_assignments($request_id);
			if ($active_assignments === false) {
				$abort('Gagal memperbarui PIC.');
			}
			if (count($active_assignments) > 1) {
				$abort('Data PIC aktif perlu diperiksa.', 'ambiguous_active_assignments');
			}
			foreach ($active_assignments as $active_assignment) {
				if ((string) $active_assignment->kode_pkm !== $kode_pkm
					|| (string) $active_assignment->staff_kode_pkm !== $kode_pkm) {
					$abort('Data PIC tidak valid.');
				}
			}
			$previous_assignment = !empty($active_assignments) ? $active_assignments[0] : null;

			$staff_query = $this->db->query(
				'SELECT staff_id, kode_pkm, nama, no_hp, profesi, nomor_sip, user_id, status FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE staff_id = ? FOR UPDATE',
				array($staff_id)
			);
			$staff = $staff_query ? $staff_query->row() : null;
			if (!$staff
				|| (string) $staff->status !== 'aktif'
				|| $this->normalize_staff_puskesmas_code($staff->kode_pkm) !== $kode_pkm
				|| !$this->puskesmas_active_for_assignment($kode_pkm, true)) {
				$abort('Staf bukan dari Puskesmas ini.');
			}

			$owner = $this->staff_personal_owner_context($staff, true);
			if (!$owner['valid']) {
				$abort('Akun perlu dicek. PIC tidak diubah.');
			}
			$target_owner_user_id = $owner['user_id'] ?: $assigned_by_user_id;
			$same_staff_selection = count($active_assignments) === 1
				&& (int) $active_assignments[0]->staff_id === $staff_id;
			$assignment_event = $same_staff_selection
				? null
				: ($previous_assignment ? 'pic_changed' : 'pic_assigned');

			$now = date('Y-m-d H:i:s');
			if (!$same_staff_selection && !empty($active_assignments)) {
				$this->db
					->where('request_id', $request_id)
					->where('status', 'aktif')
					->update('request_staff_assignments', array(
						'status' => 'diganti',
						'ended_at' => $now,
						'updated_at' => $now,
					));
				if ($this->db->affected_rows() !== count($active_assignments)) {
					$abort('Gagal memperbarui PIC.');
				}
			}

			if (!$same_staff_selection) {
				if (!$this->db->insert('request_staff_assignments', array(
					'request_id' => $request_id,
					'staff_id' => $staff_id,
					'kode_pkm' => $kode_pkm,
					'assigned_by_user_id' => $assigned_by_user_id,
					'status' => 'aktif',
					'note' => $note !== '' ? $note : null,
					'assigned_at' => $now,
					'created_at' => $now,
					'updated_at' => $now,
				))) {
					$abort('Gagal memperbarui PIC.');
				}
				$new_assignment_id = (int) $this->db->insert_id();
				if ($new_assignment_id < 1) { $abort('Gagal memperbarui PIC.'); }
			}

			if (!$this->synchronize_request_staff_owner($request_id, $kode_pkm, $target_owner_user_id, $assigned_by_user_id)) {
				$abort('Gagal memperbarui PIC.');
			}
			if (!$this->request_staff_assignment_state_matches(
				$request_id,
				$kode_pkm,
				isset($request->assigned_puskesmas_name) ? $request->assigned_puskesmas_name : null,
				$staff_id,
				$target_owner_user_id,
				$assigned_by_user_id
			) || $this->db->trans_status() === false) {
				$abort('Gagal memperbarui PIC.');
			}
			if ($assignment_event !== null && function_exists('doclinc_realtime_requests_enabled') && doclinc_realtime_requests_enabled()) {
				$audiences = array(
					'user:' . (int) $request->user_id,
					'puskesmas:' . $kode_pkm . ':ops',
				);
				if ($target_owner_user_id > 0) { $audiences[] = 'user:' . $target_owner_user_id; }
				$previous_user_id = $previous_assignment ? (int) ($previous_assignment->staff_user_id ?? 0) : 0;
				if ($previous_user_id > 0) { $audiences[] = 'user:' . $previous_user_id; }
				$transition = $assignment_event === 'pic_changed' ? 'pic_reassigned' : 'pic_assigned';
				if (!doclinc_request_realtime_delivery($this->db)->deliver(
					$transition, $request_id, $new_assignment_id, $audiences, array($kode_pkm)
				)) { $abort('Gagal memperbarui PIC.'); }
			}

			if (!$this->db->trans_commit()) {
				$this->db->trans_rollback();
				$transaction_started = false;
				$abort('Gagal memperbarui PIC.');
			}
			$transaction_started = false;
			$committed = true;
			$result = array(
				'status' => 'success',
				'message' => $same_staff_selection
					? 'PIC tetap sama.'
					: 'PIC diperbarui.',
			);
		} catch (RuntimeException $e) {
			if ($e->getMessage() !== 'staff_assignment_aborted') {
				$result = array('status' => 'error', 'message' => 'Gagal memperbarui PIC.');
			}
		} catch (Throwable $e) {
			$result = array('status' => 'error', 'message' => 'Gagal memperbarui PIC.');
		} finally {
			if ($transaction_started) {
				$this->db->trans_rollback();
			}
			$this->db->db_debug = $db_debug;
		}

		if (!$committed) {
			return $result;
		}
		if ($assignment_event === 'pic_changed' && $previous_assignment) {
			$this->append_request_event($request_id, 'pic_changed', array(
				'puskesmas_code' => $kode_pkm,
				'actor_user_id' => $assigned_by_user_id,
				'actor_staff_id' => $staff_id,
				'actor_role' => 'dokter',
				'message' => 'PIC diganti.',
				'metadata' => array(
					'previous_staff_id' => (int) $previous_assignment->staff_id,
					'previous_staff_name' => (string) $previous_assignment->staff_nama,
					'new_staff_id' => $staff_id,
					'new_staff_name' => (string) $staff->nama,
					'new_staff_profesi' => (string) $staff->profesi,
				),
			));
		} elseif ($assignment_event === 'pic_assigned') {
			$this->append_request_event($request_id, 'pic_assigned', array(
				'puskesmas_code' => $kode_pkm,
				'actor_user_id' => $assigned_by_user_id,
				'actor_staff_id' => $staff_id,
				'actor_role' => 'dokter',
				'message' => 'PIC ditetapkan.',
				'metadata' => array(
					'staff_id' => $staff_id,
					'staff_name' => (string) $staff->nama,
					'staff_profesi' => (string) $staff->profesi,
					'previous_assignment_id' => null,
				),
			));
		}
		return $result;
	}

	public function clear_staff_assignment($request_id, $kode_pkm, $assigned_by_user_id, $identity_context = null)
	{
		$request_id = (int) $request_id;
		$assigned_by_user_id = (int) $assigned_by_user_id;
		$kode_pkm = $this->normalize_staff_puskesmas_code($kode_pkm);

		if ($request_id < 1 || $assigned_by_user_id < 1 || $kode_pkm === '') {
			return array('status' => 'error', 'message' => 'Data PIC tidak valid.');
		}
		if (!$this->staff_assignment_table_ready()) {
			return array('status' => 'error', 'message' => 'Penugasan PIC belum tersedia.');
		}
		if (!is_array($identity_context)
			|| empty($identity_context['valid'])
			|| $identity_context['account_type'] !== 'command_center'
			|| (int) $identity_context['user_id'] !== $assigned_by_user_id
			|| trim((string) $identity_context['puskesmas_code']) !== $kode_pkm) {
			return array('status' => 'error', 'message' => 'Anda tidak memiliki akses.');
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$result = array('status' => 'error', 'message' => 'Gagal melepas PIC.');
		$transaction_started = false;
		$committed = false;
		$previous_assignment = null;
		$abort = function ($message) use (&$result) {
			$result = array('status' => 'error', 'message' => $message);
			throw new RuntimeException('staff_unassignment_aborted');
		};

		try {
			if (!$this->db->trans_begin()) {
				$abort('Gagal melepas PIC.');
			}
			$transaction_started = true;
			$request_query = $this->db->query(
				'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
				array($request_id)
			);
			$request = $request_query ? $request_query->row() : null;
			$database_identity = $this->locked_command_center_context($assigned_by_user_id, $kode_pkm, $request);
			if (empty($database_identity['valid'])
				|| $database_identity['account_type'] !== 'command_center'
				|| trim((string) $database_identity['puskesmas_code']) !== $kode_pkm
				|| !$request
				|| $this->request_assigned_puskesmas_code($request) !== $kode_pkm
				|| !function_exists('doclinc_can_coordinate_request')
				|| !doclinc_can_coordinate_request($request, $database_identity)) {
				$abort('Permintaan tidak dapat diakses.');
			}
			if ((string) $request->request_status !== 'Accepted') {
				$abort('PIC hanya dapat dilepas saat konsultasi aktif.');
			}

			$active_assignments = $this->locked_active_staff_assignments($request_id);
			if ($active_assignments === false
				|| count($active_assignments) !== 1
				|| (string) $active_assignments[0]->kode_pkm !== $kode_pkm
				|| (string) $active_assignments[0]->staff_kode_pkm !== $kode_pkm) {
				$abort('PIC aktif tidak ditemukan.');
			}
			$previous_assignment = $active_assignments[0];
			$now = date('Y-m-d H:i:s');
			$this->db
				->where('assignment_id', (int) $previous_assignment->assignment_id)
				->where('status', 'aktif')
				->update('request_staff_assignments', array(
					'status' => 'dibatalkan',
					'ended_at' => $now,
					'updated_at' => $now,
				));
			if ($this->db->affected_rows() !== 1
				|| !$this->synchronize_request_staff_owner($request_id, $kode_pkm, $assigned_by_user_id, $assigned_by_user_id)) {
				$abort('Gagal melepas PIC.');
			}
			if (!$this->request_staff_assignment_state_matches(
				$request_id,
				$kode_pkm,
				isset($request->assigned_puskesmas_name) ? $request->assigned_puskesmas_name : null,
				null,
				$assigned_by_user_id,
				$assigned_by_user_id
			) || $this->db->trans_status() === false) {
				$abort('Gagal melepas PIC.');
			}
			if (function_exists('doclinc_realtime_requests_enabled') && doclinc_realtime_requests_enabled()) {
				$audiences = array(
					'user:' . (int) $request->user_id,
					'puskesmas:' . $kode_pkm . ':ops',
				);
				$previous_user_id = (int) ($previous_assignment->staff_user_id ?? 0);
				if ($previous_user_id > 0) { $audiences[] = 'user:' . $previous_user_id; }
				if (!doclinc_request_realtime_delivery($this->db)->deliver(
					'pic_cleared', $request_id, (int) $previous_assignment->assignment_id, $audiences, array($kode_pkm)
				)) { $abort('Gagal melepas PIC.'); }
			}

			if (!$this->db->trans_commit()) {
				$this->db->trans_rollback();
				$transaction_started = false;
				$abort('Gagal melepas PIC.');
			}
			$transaction_started = false;
			$committed = true;
			$result = array('status' => 'success', 'message' => 'PIC dilepas.');
		} catch (RuntimeException $e) {
			if ($e->getMessage() !== 'staff_unassignment_aborted') {
				$result = array('status' => 'error', 'message' => 'Gagal melepas PIC.');
			}
		} catch (Throwable $e) {
			$result = array('status' => 'error', 'message' => 'Gagal melepas PIC.');
		} finally {
			if ($transaction_started) {
				$this->db->trans_rollback();
			}
			$this->db->db_debug = $db_debug;
		}

		$changed = $committed;
		if ($changed && $previous_assignment) {
			$this->append_request_event($request_id, 'pic_cleared', array(
				'puskesmas_code' => $kode_pkm,
				'actor_user_id' => $assigned_by_user_id,
				'actor_staff_id' => (int) $previous_assignment->staff_id,
				'actor_role' => 'dokter',
				'message' => 'PIC dilepas.',
				'metadata' => array(
					'previous_staff_id' => (int) $previous_assignment->staff_id,
					'previous_staff_name' => (string) $previous_assignment->staff_nama,
					'previous_staff_profesi' => (string) $previous_assignment->staff_profesi,
				),
			));
		}
		return $result;
	}

	private function locked_active_staff_assignments($request_id)
	{
		$sql = 'SELECT rsa.assignment_id, rsa.request_id, rsa.staff_id, rsa.kode_pkm, rsa.assigned_by_user_id, rsa.status, rsa.note, rsa.assigned_at, '
			. 'ps.kode_pkm AS staff_kode_pkm, ps.user_id AS staff_user_id, ps.nama AS staff_nama, ps.profesi AS staff_profesi '
			. 'FROM ' . $this->db->dbprefix('request_staff_assignments') . ' rsa '
			. 'LEFT JOIN ' . $this->db->dbprefix('puskesmas_staff') . ' ps ON ps.staff_id = rsa.staff_id '
			. 'WHERE rsa.request_id = ? AND rsa.status = ? '
			. 'ORDER BY rsa.assigned_at DESC, rsa.assignment_id DESC FOR UPDATE';
		$query = $this->db->query($sql, array((int) $request_id, 'aktif'));
		return $query ? $query->result() : false;
	}

	private function puskesmas_active_for_assignment($kode_pkm, $lock = false)
	{
		$kode_pkm = $this->normalize_staff_puskesmas_code($kode_pkm);
		if ($kode_pkm === '' || !$this->db->table_exists('m_puskesmas')) {
			return false;
		}
		$has_status = $this->db->field_exists('status', 'm_puskesmas');
		if ($lock) {
			$fields = $has_status ? 'kode_pkm, status' : 'kode_pkm';
			$query = $this->db->query(
				'SELECT ' . $fields . ' FROM ' . $this->db->dbprefix('m_puskesmas') . ' WHERE kode_pkm = ? FOR UPDATE',
				array($kode_pkm)
			);
			$puskesmas = $query ? $query->row() : null;
			return $puskesmas && (!$has_status || (string) $puskesmas->status === 'aktif');
		}
		$this->db->where('kode_pkm', $kode_pkm);
		if ($has_status) {
			$this->db->where('status', 'aktif');
		}
		return $this->db->count_all_results('m_puskesmas') === 1;
	}

	private function staff_personal_owner_context($staff, $refresh_identity)
	{
		$user_id = $staff ? (int) ($staff->user_id ?? 0) : 0;
		if ($user_id < 1) {
			return array('valid' => true, 'state' => 'unlinked', 'user_id' => null);
		}
		if ($refresh_identity) {
			return $this->locked_staff_personal_owner_context($staff, $user_id);
		}
		$identity = function_exists('doclinc_dokter_identity_context')
			? doclinc_dokter_identity_context($user_id)
			: null;
		$valid = !empty($identity['valid'])
			&& $identity['account_type'] === 'personal'
			&& (int) $identity['user_id'] === $user_id
			&& (int) $identity['staff_id'] === (int) $staff->staff_id
			&& (string) $identity['role'] === 'dokter'
			&& (string) $identity['user_status'] === 'aktif'
			&& trim((string) $identity['puskesmas_code']) === $this->normalize_staff_puskesmas_code($staff->kode_pkm);
		return array(
			'valid' => $valid,
			'state' => $valid ? 'linked' : 'invalid',
			'user_id' => $valid ? $user_id : null,
		);
	}

	private function locked_staff_personal_owner_context($staff, $user_id)
	{
		$kode_pkm = $this->normalize_staff_puskesmas_code($staff->kode_pkm);
		$user_query = $this->db->query(
			'SELECT userId, role, status, remark FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? FOR UPDATE',
			array((int) $user_id)
		);
		$user = $user_query ? $user_query->row() : null;
		if (!$user
			|| (string) $user->role !== 'dokter'
			|| (string) $user->status !== 'aktif'
			|| $this->normalize_staff_puskesmas_code($user->remark) !== $kode_pkm) {
			return array('valid' => false, 'state' => 'invalid', 'user_id' => null);
		}

		$canonical_query = $this->db->query(
			'SELECT userId FROM ' . $this->db->dbprefix('users')
				. ' WHERE role = ? AND status = ? AND TRIM(remark) = ? ORDER BY userId ASC LIMIT 1 FOR UPDATE',
			array('dokter', 'aktif', $kode_pkm)
		);
		$canonical = $canonical_query ? $canonical_query->row() : null;
		if (!$canonical || (int) $canonical->userId === (int) $user_id) {
			return array('valid' => false, 'state' => 'invalid', 'user_id' => null);
		}

		$links_query = $this->db->query(
			'SELECT staff_id, kode_pkm, user_id, status FROM ' . $this->db->dbprefix('puskesmas_staff')
				. ' WHERE user_id = ? ORDER BY staff_id ASC FOR UPDATE',
			array((int) $user_id)
		);
		$links = $links_query ? $links_query->result() : array();
		$valid = count($links) === 1
			&& (int) $links[0]->staff_id === (int) $staff->staff_id
			&& (int) $links[0]->user_id === (int) $user_id
			&& (string) $links[0]->status === 'aktif'
			&& $this->normalize_staff_puskesmas_code($links[0]->kode_pkm) === $kode_pkm;
		return array(
			'valid' => $valid,
			'state' => $valid ? 'linked' : 'invalid',
			'user_id' => $valid ? (int) $user_id : null,
		);
	}

	private function synchronize_request_staff_owner($request_id, $kode_pkm, $owner_user_id, $assigned_by_user_id)
	{
		if (!$this->db->field_exists('assigned_nakes_user_id', 'requests')) {
			return false;
		}
		$data = array('assigned_nakes_user_id' => (int) $owner_user_id);
		if ($this->db->field_exists('assigned_nakes_by_user_id', 'requests')) {
			$data['assigned_nakes_by_user_id'] = (int) $assigned_by_user_id;
		}
		$updated = $this->db
			->where('request_id', (int) $request_id)
			->where('request_status', 'Accepted')
			->where('TRIM(assigned_puskesmas_code) = ' . $this->db->escape($kode_pkm), null, false)
			->update('requests', $data);
		if (!$updated || $this->db->trans_status() === false) {
			return false;
		}
		$select = array('request_id', 'request_status', 'assigned_puskesmas_code', 'assigned_nakes_user_id');
		if ($this->db->field_exists('assigned_nakes_by_user_id', 'requests')) {
			$select[] = 'assigned_nakes_by_user_id';
		}
		$request = $this->db
			->select(implode(', ', $select))
			->where('request_id', (int) $request_id)
			->get('requests')
			->row();
		if (!$request
			|| (string) $request->request_status !== 'Accepted'
			|| $this->request_assigned_puskesmas_code($request) !== $kode_pkm
			|| (int) $request->assigned_nakes_user_id !== (int) $owner_user_id) {
			return false;
		}
		return !isset($request->assigned_nakes_by_user_id)
			|| (int) $request->assigned_nakes_by_user_id === (int) $assigned_by_user_id;
	}

	private function request_staff_assignment_state_matches($request_id, $kode_pkm, $puskesmas_name, $expected_staff_id, $owner_user_id, $assigned_by_user_id)
	{
		$active_assignments = $this->locked_active_staff_assignments($request_id);
		if ($active_assignments === false) {
			return false;
		}
		if ($expected_staff_id === null) {
			if (count($active_assignments) !== 0) {
				return false;
			}
		} elseif (count($active_assignments) !== 1
			|| (int) $active_assignments[0]->staff_id !== (int) $expected_staff_id
			|| (string) $active_assignments[0]->kode_pkm !== $kode_pkm
			|| (string) $active_assignments[0]->staff_kode_pkm !== $kode_pkm) {
			return false;
		}

		$select = array('request_id', 'request_status', 'assigned_puskesmas_code', 'assigned_nakes_user_id');
		$has_puskesmas_name = $this->db->field_exists('assigned_puskesmas_name', 'requests');
		if ($has_puskesmas_name) {
			$select[] = 'assigned_puskesmas_name';
		}
		$has_assignment_actor = $this->db->field_exists('assigned_nakes_by_user_id', 'requests');
		if ($has_assignment_actor) {
			$select[] = 'assigned_nakes_by_user_id';
		}
		$request = $this->db
			->select(implode(', ', $select))
			->where('request_id', (int) $request_id)
			->get('requests')
			->row();
		return $request
			&& (string) $request->request_status === 'Accepted'
			&& $this->request_assigned_puskesmas_code($request) === $kode_pkm
			&& (!$has_puskesmas_name || (string) $request->assigned_puskesmas_name === (string) $puskesmas_name)
			&& (int) $request->assigned_nakes_user_id === (int) $owner_user_id
			&& (!$has_assignment_actor || (int) $request->assigned_nakes_by_user_id === (int) $assigned_by_user_id);
	}


	public function update_profile($id, $data)
	{
		$allowed = array('nama', 'email', 'no_hp', 'tgl', 'gender', 'alamat', 'foto');
		foreach (array_keys($data) as $field) {
			if (!in_array($field, $allowed, true) || !$this->db->field_exists($field, 'users')) {
				unset($data[$field]);
			}
		}
		if (empty($data)) {
			return true;
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$updated = $this->db
			->where('userId', (int) $id)
			->where('role', 'dokter')
			->where('status', 'aktif')
			->update('users', $data);
		$this->db->db_debug = $db_debug;
		if (!$updated) {
			return false;
		}
		return (bool) $this->db
			->select('userId')
			->where('userId', (int) $id)
			->where('role', 'dokter')
			->where('status', 'aktif')
			->limit(1)
			->get('users')
			->row();
	}

	public function email_available_for_user($user_id, $email)
	{
		$user_id = (int) $user_id;
		$email = strtolower(trim((string) $email));
		if ($user_id < 1 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || !$this->db->field_exists('email', 'users')) {
			return false;
		}
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$query = $this->db
			->select('userId')
			->where('email', $email)
			->where('userId !=', $user_id)
			->limit(1)
			->get('users');
		$this->db->db_debug = $db_debug;
		return $query !== false && !$query->row();
	}
}
