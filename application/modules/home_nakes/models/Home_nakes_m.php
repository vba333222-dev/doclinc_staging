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

	private function normalize_puskesmas_code($puskesmas_code)
	{
		$puskesmas_code = trim((string) $puskesmas_code);
		return strtoupper($puskesmas_code) === 'DEFAULT' ? '' : $puskesmas_code;
	}

	private function where_pending_queue_owner($id, $puskesmas_code)
	{
		$puskesmas_code = $this->normalize_puskesmas_code($puskesmas_code);

		$this->db->group_start();
		if ($puskesmas_code !== '' && $this->db->field_exists('assigned_puskesmas_code', 'requests')) {
			$this->db->where('TRIM(assigned_puskesmas_code) = ' . $this->db->escape($puskesmas_code), null, false);
			$this->db->or_where('dokter_id', $id);
		} else {
			$this->db->where('dokter_id', $id);
		}
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
	public function get_visit_location($request_id, $nakes_user_id)
	{
		$request_id = (int) $request_id;
		$nakes_user_id = (int) $nakes_user_id;
		if ($request_id < 1 || $nakes_user_id < 1) {
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
		$this->where_handling_nakes_owner($nakes_user_id);

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
	public function accept_request($id, $id_user, $latitude, $longitude, $puskesmas_code = '')
	{
		if (empty($id) || empty($id_user)) {
			return array('status' => 'error', 'message' => 'Data request tidak lengkap');
		}

		$request = $this->db
			->where('request_id', $id)
			->get('requests')
			->row();
		if (!$request) {
			return array('status' => 'error', 'message' => 'Request tidak ditemukan');
		}

		if ($request->request_status === 'Accepted') {
			$accepted_by_user_id = isset($request->accepted_by_user_id) ? $request->accepted_by_user_id : null;
			$assigned_nakes_user_id = isset($request->assigned_nakes_user_id) ? $request->assigned_nakes_user_id : null;
			if (
				(string) $request->dokter_id === (string) $id_user
				|| (string) $accepted_by_user_id === (string) $id_user
				|| (string) $assigned_nakes_user_id === (string) $id_user
			) {
				return array('status' => 'success', 'message' => 'Request konsultasi sudah diterima', 'already_accepted' => true);
			}

			return array('status' => 'error', 'message' => 'Request sudah diterima oleh nakes lain');
		}

		if ($request->request_status !== 'Pending') {
			return array('status' => 'error', 'message' => 'Request tidak dapat diterima');
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

		$this->where_pending_queue_owner($id_user, $puskesmas_code);

		$this->db->update('requests', $data);
		if ($this->db->affected_rows() > 0) {
			return array('status' => 'success', 'message' => 'Request konsultasi diterima', 'already_accepted' => false);
		}

		return array('status' => 'error', 'message' => 'Request tidak ditemukan atau bukan milik dokter login');
	}
	public function cancel_request($request_id, $user_id, $puskesmas_code = '')
	{
		$request_id = (int) $request_id;
		$user_id = (int) $user_id;
		if ($request_id < 1 || $user_id < 1) {
			return array('status' => 'error', 'message' => 'Data request tidak lengkap');
		}

		$request = $this->db
			->where('request_id', $request_id)
			->get('requests')
			->row();
		if (!$request) {
			return array('status' => 'error', 'message' => 'Request tidak ditemukan');
		}

		if (in_array($request->request_status, array('Completed', 'Cancelled'), true)) {
			return array('status' => 'error', 'message' => 'Request tidak dapat dibatalkan');
		}
		if (!in_array($request->request_status, array('Pending', 'Accepted'), true)) {
			return array('status' => 'error', 'message' => 'Request tidak dapat dibatalkan');
		}

		$data = array('request_status' => 'Cancelled');
		if ($this->db->field_exists('updated_at', 'requests')) {
			$data['updated_at'] = date('Y-m-d H:i:s');
		}

		$this->db
			->where('request_id', $request_id)
			->where_in('request_status', array($request->request_status));

		if ($request->request_status === 'Pending') {
			$this->where_pending_queue_owner($user_id, $puskesmas_code);
		} else {
			$this->where_handling_nakes_owner($user_id);
		}

		$this->db->update('requests', $data);
		if ($this->db->affected_rows() > 0) {
			return array('status' => 'success', 'message' => 'Request berhasil dibatalkan');
		}

		return array('status' => 'error', 'message' => 'Request tidak ditemukan atau akses tidak diizinkan');
	}
	public function update_visit_status($request_id, $user_id, $next_status)
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

		$request = $this->db
			->where('request_id', $request_id)
			->get('requests')
			->row();
		if (!$request) {
			return array('status' => 'error', 'message' => 'Request tidak ditemukan');
		}
		if ($request->request_status !== 'Accepted') {
			return array('status' => 'error', 'message' => 'Status kunjungan hanya dapat diperbarui untuk konsultasi aktif');
		}

		$current_status = function_exists('doclinc_normalize_visit_status')
			? (doclinc_normalize_visit_status($request->visit_status) ?: 'not_started')
			: ($request->visit_status ?: 'not_started');
		if (function_exists('doclinc_allowed_visit_status_transition') && !doclinc_allowed_visit_status_transition($current_status, $next_status)) {
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
		$this->where_handling_nakes_owner($user_id);
		$this->db->update('requests', $data);

		if ($this->db->affected_rows() < 1 && $current_status !== $next_status) {
			return array('status' => 'error', 'message' => 'Status kunjungan tidak dapat diperbarui');
		}

		$row = $this->db
			->where('request_id', $request_id)
			->get('requests')
			->row();
		$visit_status = $row && isset($row->visit_status) ? $row->visit_status : $next_status;

		return array(
			'status' => 'success',
			'message' => 'Status kunjungan diperbarui',
			'visit_status' => $visit_status,
			'visit_status_label' => function_exists('doclinc_visit_status_label') ? doclinc_visit_status_label($visit_status) : $visit_status,
		);
	}
	public function update_visit_location($request_id, $user_id, $latitude, $longitude)
	{
		$request_id = (int) $request_id;
		$user_id = (int) $user_id;
		if ($request_id < 1 || $user_id < 1) {
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
		$this->where_handling_nakes_owner($user_id);
		$this->db->update('requests', $data);

		return $this->db->affected_rows() > 0;
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

		return $this->db
			->select('staff_id, nama, no_hp, profesi, nomor_sip, status')
			->from('puskesmas_staff')
			->where('kode_pkm', $kode_pkm)
			->where('status', 'aktif')
			->order_by('nama', 'ASC')
			->order_by('staff_id', 'ASC')
			->get()
			->result();
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
			->select('rsa.assignment_id, rsa.request_id, rsa.staff_id, rsa.kode_pkm, rsa.assigned_by_user_id, rsa.status, rsa.note, rsa.assigned_at, rsa.ended_at, ps.nama AS staff_nama, ps.no_hp AS staff_no_hp, ps.profesi AS staff_profesi, ps.nomor_sip AS staff_nomor_sip')
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
			->select('rsa.assignment_id, rsa.request_id, rsa.staff_id, rsa.kode_pkm, rsa.assigned_by_user_id, rsa.status, rsa.note, rsa.assigned_at, rsa.ended_at, ps.nama AS staff_nama, ps.no_hp AS staff_no_hp, ps.profesi AS staff_profesi, ps.nomor_sip AS staff_nomor_sip')
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
			->where_in('event_type', array('pic_assigned', 'pic_changed', 'pic_cleared'))
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

	public function assign_staff_to_request($request_id, $staff_id, $kode_pkm, $assigned_by_user_id, $note = '')
	{
		$request_id = (int) $request_id;
		$staff_id = (int) $staff_id;
		$assigned_by_user_id = (int) $assigned_by_user_id;
		$kode_pkm = $this->normalize_staff_puskesmas_code($kode_pkm);
		$note = trim((string) $note);

		if ($request_id < 1 || $staff_id < 1 || $assigned_by_user_id < 1 || $kode_pkm === '') {
			return array('status' => 'error', 'message' => 'Data PIC personel tidak valid');
		}
		if (!$this->staff_assignment_table_ready()) {
			return array('status' => 'error', 'message' => 'Tabel assignment PIC belum tersedia');
		}

		$request = $this->db
			->select('request_id, request_status, assigned_puskesmas_code')
			->from('requests')
			->where('request_id', $request_id)
			->where('assigned_puskesmas_code', $kode_pkm)
			->get()
			->row();
		if (!$request) {
			return array('status' => 'error', 'message' => 'Request tidak ditemukan atau bukan milik Puskesmas login');
		}
		if ((string) $request->request_status !== 'Accepted') {
			return array('status' => 'error', 'message' => 'PIC hanya dapat ditetapkan pada request aktif');
		}

		$staff = $this->db
			->select('staff_id, nama, profesi')
			->from('puskesmas_staff')
			->where('staff_id', $staff_id)
			->where('kode_pkm', $kode_pkm)
			->where('status', 'aktif')
			->get()
			->row();
		if (!$staff) {
			return array('status' => 'error', 'message' => 'Personel tidak ditemukan atau bukan milik Puskesmas login');
		}

		$previous_assignment = $this->get_active_staff_assignment($request_id);
		$now = date('Y-m-d H:i:s');
		$this->db->trans_begin();
		$this->db
			->where('request_id', $request_id)
			->where('status', 'aktif')
			->update('request_staff_assignments', array(
				'status' => 'diganti',
				'ended_at' => $now,
				'updated_at' => $now,
			));
		$this->db->insert('request_staff_assignments', array(
			'request_id' => $request_id,
			'staff_id' => $staff_id,
			'kode_pkm' => $kode_pkm,
			'assigned_by_user_id' => $assigned_by_user_id,
			'status' => 'aktif',
			'note' => $note !== '' ? $note : null,
			'assigned_at' => $now,
			'created_at' => $now,
			'updated_at' => $now,
		));

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return array('status' => 'error', 'message' => 'Gagal menetapkan PIC personel');
		}

		$this->db->trans_commit();
		if ($previous_assignment) {
			$this->append_request_event($request_id, 'pic_changed', array(
				'puskesmas_code' => $kode_pkm,
				'actor_user_id' => $assigned_by_user_id,
				'actor_staff_id' => $staff_id,
				'actor_role' => 'dokter',
				'message' => 'PIC personel diganti.',
				'metadata' => array(
					'previous_staff_id' => (int) $previous_assignment->staff_id,
					'previous_staff_name' => (string) $previous_assignment->staff_nama,
					'new_staff_id' => $staff_id,
					'new_staff_name' => (string) $staff->nama,
					'new_staff_profesi' => (string) $staff->profesi,
				),
			));
		} else {
			$this->append_request_event($request_id, 'pic_assigned', array(
				'puskesmas_code' => $kode_pkm,
				'actor_user_id' => $assigned_by_user_id,
				'actor_staff_id' => $staff_id,
				'actor_role' => 'dokter',
				'message' => 'PIC personel ditetapkan.',
				'metadata' => array(
					'staff_id' => $staff_id,
					'staff_name' => (string) $staff->nama,
					'staff_profesi' => (string) $staff->profesi,
					'previous_assignment_id' => null,
				),
			));
		}
		return array('status' => 'success', 'message' => 'PIC personel berhasil ditetapkan');
	}

	public function clear_staff_assignment($request_id, $kode_pkm, $assigned_by_user_id)
	{
		$request_id = (int) $request_id;
		$assigned_by_user_id = (int) $assigned_by_user_id;
		$kode_pkm = $this->normalize_staff_puskesmas_code($kode_pkm);

		if ($request_id < 1 || $assigned_by_user_id < 1 || $kode_pkm === '') {
			return array('status' => 'error', 'message' => 'Data PIC personel tidak valid');
		}
		if (!$this->staff_assignment_table_ready()) {
			return array('status' => 'error', 'message' => 'Tabel assignment PIC belum tersedia');
		}

		$request = $this->db
			->select('request_id, request_status, assigned_puskesmas_code')
			->from('requests')
			->where('request_id', $request_id)
			->where('assigned_puskesmas_code', $kode_pkm)
			->get()
			->row();
		if (!$request) {
			return array('status' => 'error', 'message' => 'Request tidak ditemukan atau bukan milik Puskesmas login');
		}
		if ((string) $request->request_status !== 'Accepted') {
			return array('status' => 'error', 'message' => 'PIC hanya dapat dibatalkan pada request aktif');
		}

		$previous_assignment = $this->get_active_staff_assignment($request_id);
		$now = date('Y-m-d H:i:s');
		$this->db->trans_begin();
		$this->db
			->where('request_id', $request_id)
			->where('kode_pkm', $kode_pkm)
			->where('status', 'aktif')
			->update('request_staff_assignments', array(
				'status' => 'dibatalkan',
				'ended_at' => $now,
				'updated_at' => $now,
			));
		$changed = $this->db->affected_rows() > 0;

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return array('status' => 'error', 'message' => 'Gagal membatalkan PIC personel');
		}

		$this->db->trans_commit();
		if ($changed && $previous_assignment) {
			$this->append_request_event($request_id, 'pic_cleared', array(
				'puskesmas_code' => $kode_pkm,
				'actor_user_id' => $assigned_by_user_id,
				'actor_staff_id' => (int) $previous_assignment->staff_id,
				'actor_role' => 'dokter',
				'message' => 'PIC personel dibatalkan.',
				'metadata' => array(
					'previous_staff_id' => (int) $previous_assignment->staff_id,
					'previous_staff_name' => (string) $previous_assignment->staff_nama,
					'previous_staff_profesi' => (string) $previous_assignment->staff_profesi,
				),
			));
		}
		return $changed
			? array('status' => 'success', 'message' => 'PIC personel berhasil dibatalkan')
			: array('status' => 'error', 'message' => 'PIC aktif tidak ditemukan');
	}


	public function update_profile($id, $data)
	{
		foreach (array_keys($data) as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				unset($data[$field]);
			}
		}
		if (empty($data)) {
			return true;
		}

		$this->db->where('userId', $id);
		return $this->db->update('users', $data);
	}
}
