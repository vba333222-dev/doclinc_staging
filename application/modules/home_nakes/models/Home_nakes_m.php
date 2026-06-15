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
		return $this->db->query("SELECT
										requests.*, users.nama
									FROM
										requests
									INNER JOIN users ON requests.user_id = users.userId
									WHERE
										requests.request_status = 'Accepted'
									AND
										requests.dokter_id = '$id'
									ORDER BY
										requests.request_id DESC");
	}
	public function request_keluhan_completed($id)
	{
		return $this->db->query("SELECT
										requests.*, users.nama, konsultasi.*
									FROM
										requests
									INNER JOIN users ON requests.user_id = users.userId
									INNER JOIN konsultasi ON requests.request_id = konsultasi.request_id
									WHERE
										requests.request_status = 'Completed'
									AND
										requests.dokter_id = '$id'
									ORDER BY
										requests.request_id DESC");
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
		// echo ("UPDATE requests SET request_status='Accepted', lattitude_dokter='$latitude', longitude_dokter='$longitude', updated_at='" . date('Y-m-d H:i:s') . "' WHERE request_id='$id'");
		$query = $this->db->query("UPDATE requests SET request_status='Accepted', lattitude_dokter='$latitude', longitude_dokter='$longitude', updated_at='" . date('Y-m-d H:i:s') . "' WHERE request_id='$id'");
		if ($query) {
			return true;
		}
	}

	/**
	 * Verify that a request belongs to the given doctor.
	 * Returns true only if the request's dokter_id matches the doctor's userId.
	 */
	public function verify_request_owner($request_id, $doctor_id)
	{
		$this->db->where('request_id', $request_id);
		$this->db->where('dokter_id', $doctor_id);
		$query = $this->db->get('requests');
		return $query->num_rows() > 0;
	}
	public function get_location_user($id)
	{
		// echo("SELECT * FROM locations WHERE date(create_date)=date(now()) and name='".$name."'");
		return $this->db->query("SELECT * FROM locations WHERE date(create_date)=date(now()) and id_user='" . $id . "'");
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
