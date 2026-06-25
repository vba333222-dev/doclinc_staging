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

	private function completed_result_expression($table, $field)
	{
		if (!$this->db->table_exists($table) || !$this->db->field_exists($field, $table)) {
			return 'NULL';
		}

		return "NULLIF({$table}.{$field}, '')";
	}

	private function select_completed_result_fields($has_konsultasi, $has_medicalrecords)
	{
		$medical_diagnosis = $this->completed_result_expression('medicalrecords', 'diagnosis');
		$legacy_diagnosa = $this->completed_result_expression('konsultasi', 'diagnosa');
		$medical_recommendations = $this->completed_result_expression('medicalrecords', 'recommendations');
		$legacy_saran = $this->completed_result_expression('konsultasi', 'saran');
		$medical_treatment = $this->completed_result_expression('medicalrecords', 'treatment');
		$legacy_treatment = $this->completed_result_expression('konsultasi', 'treatment');

		if ($has_konsultasi) {
			foreach (['konsul_id', 'kriteria', 'rujukan', 'foto', 'create_date', 'create_user'] as $field) {
				if ($this->db->field_exists($field, 'konsultasi')) {
					$this->db->select("konsultasi.{$field} AS {$field}", FALSE);
				} elseif ($field === 'konsul_id') {
					$this->db->select('NULL AS konsul_id', FALSE);
				}
			}
			$this->db
				->select($this->db->field_exists('diagnosa', 'konsultasi') ? 'konsultasi.diagnosa AS legacy_diagnosa' : 'NULL AS legacy_diagnosa', FALSE)
				->select($this->db->field_exists('saran', 'konsultasi') ? 'konsultasi.saran AS legacy_saran' : 'NULL AS legacy_saran', FALSE);
		} else {
			$this->db
				->select(($has_medicalrecords && $this->db->field_exists('record_id', 'medicalrecords')) ? 'medicalrecords.record_id AS konsul_id' : 'NULL AS konsul_id', FALSE)
				->select('NULL AS legacy_diagnosa', FALSE)
				->select('NULL AS legacy_saran', FALSE);
		}

		if ($has_medicalrecords) {
			$this->db
				->select($this->db->field_exists('record_id', 'medicalrecords') ? 'medicalrecords.record_id AS record_id' : 'NULL AS record_id', FALSE)
				->select($this->db->field_exists('diagnosis', 'medicalrecords') ? 'medicalrecords.diagnosis AS medicalrecord_diagnosis' : 'NULL AS medicalrecord_diagnosis', FALSE)
				->select($this->db->field_exists('treatment', 'medicalrecords') ? 'medicalrecords.treatment AS medicalrecord_treatment' : 'NULL AS medicalrecord_treatment', FALSE)
				->select($this->db->field_exists('recommendations', 'medicalrecords') ? 'medicalrecords.recommendations AS medicalrecord_recommendations' : 'NULL AS medicalrecord_recommendations', FALSE)
				->select($this->db->field_exists('created_at', 'medicalrecords') ? 'medicalrecords.created_at AS result_created_at' : 'NULL AS result_created_at', FALSE);
		} else {
			$this->db
				->select('NULL AS record_id', FALSE)
				->select('NULL AS medicalrecord_diagnosis', FALSE)
				->select('NULL AS medicalrecord_treatment', FALSE)
				->select('NULL AS medicalrecord_recommendations', FALSE)
				->select('NULL AS result_created_at', FALSE);
		}

		$diagnosis_expr = "COALESCE({$medical_diagnosis}, {$legacy_diagnosa}, '-')";
		$recommendations_expr = "COALESCE({$medical_recommendations}, {$legacy_saran}, '-')";
		$treatment_expr = "COALESCE({$medical_treatment}, {$legacy_treatment})";

		$this->db
			->select("{$diagnosis_expr} AS diagnosa", FALSE)
			->select("{$diagnosis_expr} AS diagnosis", FALSE)
			->select("{$recommendations_expr} AS saran", FALSE)
			->select("{$recommendations_expr} AS recommendations", FALSE)
			->select("{$treatment_expr} AS treatment", FALSE);
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
		$this->db->group_start();
		$this->db->where('requests.dokter_id', $id);
		if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
			$this->db->or_where('requests.accepted_by_user_id', $id);
		}
		$this->db->group_end();

		return $this->db->order_by('requests.request_id', 'DESC')->get();
	}
	public function request_keluhan_completed($id)
	{
		$has_konsultasi = $this->db->table_exists('konsultasi');
		$has_medicalrecords = $this->db->table_exists('medicalrecords');

		if (!$has_konsultasi && !$has_medicalrecords) {
			return $this->empty_query();
		}

		$this->select_request_base();
		$this->db
			->from('requests')
			->join('users', 'requests.user_id = users.userId');
		$this->join_riwayat_or_default();
		if ($has_medicalrecords) {
			$this->db->join('medicalrecords', 'requests.request_id = medicalrecords.request_id', 'left');
		}
		if ($has_konsultasi) {
			$this->db->join('konsultasi', 'requests.request_id = konsultasi.request_id', 'left');
		}
		$this->select_completed_result_fields($has_konsultasi, $has_medicalrecords);

		$this->db->where('requests.request_status', 'Completed');
		$this->db->group_start();
		$this->db->where('requests.dokter_id', $id);
		if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
			$this->db->or_where('requests.accepted_by_user_id', $id);
		}
		$this->db->group_end();

		return $this->db->order_by('requests.request_id', 'DESC')->get();
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
			if ((string) $request->dokter_id === (string) $id_user || (string) $accepted_by_user_id === (string) $id_user) {
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
