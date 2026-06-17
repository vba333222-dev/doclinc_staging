<?php
class Home_nakes_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db = $this->load->database('default', TRUE);
	}
	public function request_keluhan($id)
	{
		// return $this->db->query("SELECT
		// 								requests.*, users.nama
		// 							FROM
		// 								requests
		// 							INNER JOIN users ON requests.user_id = users.userId
		// 							WHERE
		// 								requests.request_status = 'Pending'
		// 							AND
		// 							    requests.dokter_id = '$id'
		// 							ORDER BY
		// 								requests.request_id DESC");

		return $this->db->query("SELECT
				requests.*,
				users.nama,
				tbl_riwayat.riwayat
			FROM
				requests
			INNER JOIN users ON requests.user_id = users.userId
			INNER JOIN tbl_riwayat ON tbl_riwayat.idUser = users.userId
			WHERE
				requests.request_status = 'Pending'
				AND requests.dokter_id = " . $this->db->escape($id) . "
			ORDER BY
				requests.request_id DESC
		");
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
		return $this->db
			->select('requests.*, users.nama')
			->from('requests')
			->join('users', 'requests.user_id = users.userId')
			->where('requests.request_status', 'Accepted')
			->where('requests.dokter_id', $id)
			->order_by('requests.request_id', 'DESC')
			->get();
	}
	public function request_keluhan_completed($id)
	{
		return $this->db
			->select('requests.*, users.nama, konsultasi.*')
			->from('requests')
			->join('users', 'requests.user_id = users.userId')
			->join('konsultasi', 'requests.request_id = konsultasi.request_id')
			->where('requests.request_status', 'Completed')
			->where('requests.dokter_id', $id)
			->order_by('requests.request_id', 'DESC')
			->get();
	}
	public function check_ip_exists($ip_address)
	{
		$this->db->where('ip_address', $ip_address);
		$query = $this->db->get('locations');
		return $query->row(); //
	}
	public function update_location($data, $ip_address)
	{
		$this->db->where('ip_address', $ip_address);
		return $this->db->update('locations', $data);
	}
	public function save_location($data)
	{
		return $this->db->insert('locations', $data);
	}
	public function accept_request($id, $id_user, $latitude, $longitude)
	{
		return $this->db
			->where('request_id', $id)
			->where('dokter_id', $id_user)
			->update('requests', [
				'request_status' => 'Accepted',
				'lattitude_dokter' => $latitude,
				'longitude_dokter' => $longitude,
				'updated_at' => date('Y-m-d H:i:s'),
			]);
	}
	public function get_location_user($id)
	{
		return $this->db
			->where('DATE(create_date)', 'DATE(NOW())', FALSE)
			->where('id_user', $id)
			->get('locations');
	}

	public function get_profile_by_id($id)
	{
		return $this->db->get_where('users', ['userId' => $id])->row_array();
	}


	public function update_profile($id, $data)
	{
		$this->db->where('userId', $id);
		return $this->db->update('users', $data);
	}
}
