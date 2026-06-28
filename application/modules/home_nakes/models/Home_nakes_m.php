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
		foreach ($this->optional_request_selects() as $select) {
			$this->db->select($select, FALSE);
		}
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
		return trim((string) $puskesmas_code);
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
		$this->join_riwayat_or_default();

		$this->db->where('requests.request_status', 'Accepted');
		$this->where_handling_nakes_owner($id, 'requests.');

		return $this->db->order_by('requests.request_id', 'DESC')->get();
	}
	public function request_keluhan_completed($id)
	{
		$this->select_request_base();
		$this->db
			->from('requests')
			->join('users', 'requests.user_id = users.userId');
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
		foreach (['foto', 'tgl', 'gender', 'no_hp', 'alamat'] as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				$this->db->select("NULL AS {$field}", FALSE);
			}
		}

		return $this->db->get_where('users', ['userId' => $id])->row_array();
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
