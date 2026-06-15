<?php
class Home_m extends MX_Controller
{
	protected $db;

	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	public function getAllDataLocations($nama)
	{
		// 		$query = $this->db->query("SELECT latitude, longitude, location, name FROM locations WHERE name='$nama' AND date(locations.create_date)=date(now())");
		// 		return $query->result_array();

		$this->db->select('locations.*, users.*');
		$this->db->from('locations');
		$this->db->join('users', 'locations.id_user = users.userId');
		if (!empty($nama)) { // Pastikan array tidak kosong
			$this->db->where_in('users.username', $nama); // Filter berdasarkan nama
		}
		$this->db->where('DATE(locations.create_date)', 'CURDATE()', false); // Filter berdasarkan tanggal hari ini
		$this->db->group_by('locations.id_user');
		// $this->db->order_by('locations.id_user', 'DESC'); // Urutkan data berdasarkan 'location_id' secara descending
		$query = $this->db->get(); // Ambil data dari tabel 'locations'
		return $query->result_array(); // Kembalikan hasil sebagai array
	}

	public function getAllDataRequestss($user_id, $statuses)
	{
		$this->db->select("requests.*, m_dokter.name AS nama_dokter");
		$this->db->from("m_dokter");
		$this->db->join("requests", "requests.dokter_id=m_dokter.professional_id", "inner");
		$this->db->where("user_id", $user_id);
		$this->db->where_in("request_status", $statuses);
		return $this->db->get()->result();
	}

	public function getAllDataRequests($user_id, $statuses)
	{
		// Load library encryption
		$CI = &get_instance();
		$CI->load->library('encryption');

		// Query database
		$this->db->select("requests.*, m_dokter.name AS nama_dokter");
		$this->db->from("m_dokter");
		$this->db->join("requests", "requests.dokter_id=m_dokter.professional_id", "inner");
		$this->db->where("user_id", $user_id);
		$this->db->where_in("request_status", $statuses);
		$result = $this->db->get()->result();

		// Dekripsi field request_description
		foreach ($result as $row) {
			try {
				$row->request_description = $CI->encryption->decrypt(base64_decode($row->request_description));
			} catch (Exception $e) {
				$row->request_description = '[Keluhan tidak dapat didekripsi]';
			}
		}

		return $result;
	}

	public function getAllDataRequestsCompleted($user_id)
	{
		// Load library encryption
		$CI = &get_instance();
		$CI->load->library('encryption');
		// Ambil data utama (konsultasi dan user tanpa join terapi)
		$this->db->select('requests.*, users.nama, konsultasi.*, m_dokter.name AS nama_dokter');
		$this->db->from('requests');
		$this->db->join('users', 'requests.user_id = users.userId');
		$this->db->join('konsultasi', 'requests.request_id = konsultasi.request_id');
		$this->db->join('m_dokter', 'requests.dokter_id = m_dokter.professional_id');
		$this->db->where('requests.request_status', 'Completed');
		$this->db->where('requests.user_id', $user_id);
		$this->db->order_by('requests.request_id', 'DESC');
		$query = $this->db->get();
		$hasil = $query->result();

		// Ambil terapi untuk semua konsultasi yang ditemukan
		foreach ($hasil as &$row) {
			$row->terapi_list = $this->getTerapiByKonsulId($row->konsul_id);

			try {
				$row->request_description = $CI->encryption->decrypt(base64_decode($row->request_description));
			} catch (Exception $e) {
				$row->request_description = '[Keluhan tidak dapat didekripsi]';
			}
		}

		return $hasil;
	}

	public function getTerapiByKonsulId($konsul_id)
	{
		$this->db->where('konsul_id', $konsul_id);
		return $this->db->get('terapi')->result();
	}

	public function getAllDataDoctors($kode_pkm)
	{
		$query = $this->db->query("SELECT users.*, locations.* FROM users INNER JOIN locations ON users.userId=locations.id_user WHERE users.remark='$kode_pkm' AND users.role='dokter' GROUP BY users.userId");
		return $query->result_array();
	}


	public function getAllDataDoctor($kode_pkm)
	{
		return $this->db->query("SELECT
									users.*,
									locations.*,
									requests.*
								FROM
									users
								INNER JOIN
									locations ON users.userId=locations.id_user
								AND DATE(locations.create_date)=CURDATE()
								LEFT JOIN
									requests ON requests.request_id = (
				SELECT MAX(r2.request_id)
				FROM requests r2
				WHERE r2.dokter_id = users.userId
				AND r2.request_status = 'Pending'
				-- AND date(r2.date) = CURDATE()
			)
								WHERE users.role='dokter'
								AND users.remark='$kode_pkm'
								GROUP BY users.userId");
	}

	public function getAllRequestPendingAccept($user_id)
	{
		return $this->db->query("SELECT * FROM requests WHERE user_id = '$user_id'");
	}

	public function getAllRequestJumlah()
	{
		return $this->db->query("SELECT COUNT(*) as jumlah FROM requests WHERE date=CURDATE()");
	}

	// public function getAllDataDoctor($kode_pkm)
	// {
	// 	return $this->db->query("SELECT * FROM m_dokter WHERE kode_pkm='$kode_pkm'");
	// }

	public function get_data_feeds()
	{
		return $this->db->query("SELECT * FROM feeds WHERE status='aktif' ORDER BY feedId DESC");
	}

	public function get_data_profile($user_id)
	{
		return $this->db->query("SELECT *, TIMESTAMPDIFF(YEAR, tgl, CURDATE()) AS usia FROM users WHERE userId = '$user_id'");
	}

	public function getDokterRating($idUser)
	{
		return $this->db->query("SELECT * FROM users WHERE userId = '$idUser'");
	}

	public function updateRequestById($id)
	{

		$data = [
			'date' => date('Y-m-d'),
		];

		$this->db->where('request_id', $id);
		$this->db->where('user_id', $this->session->userdata('id')); // Pastikan user_id sesuai dengan session
		$this->db->where('request_status', 'Pending'); // Pastikan status request adalah 'Pending'
		$this->db->update('requests', $data);

		if ($this->db->affected_rows() > 0) {
			log_message('debug', "Update berhasil untuk request ID $id");
			return true;
		} else {
			log_message('error', "Gagal update request ID $id");
			return false;
		}
	}

	public function deleteRequestById($id)
	{
		$this->db->where('request_id', $id);
		$this->db->where('user_id', $this->session->userdata('id')); // Pastikan user_id sesuai dengan session
		$this->db->where('request_status', 'Pending'); // Pastikan status request adalah 'Pending'
		$this->db->delete('requests');
		if ($this->db->affected_rows() > 0) {
			return true; // Data berhasil dihapus
		} else {
			return false; // Data tidak ditemukan atau gagal dihapus
		}
	}

	public function getAllRating()
	{
		return $this->db->query("SELECT * FROM rating");
	}

	public function submit_rating($iduser, $iddokter, $rating)
	{
		$data = array(
			'id_user' => $iduser,
			'id_dokter' => $iddokter,
			'rating' => $rating,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		);
		return $this->db->insert('rating', $data);
	}

	public function getDataDoctor()
	{
		return $this->db->query("SELECT * FROM m_dokter");
	}
}
