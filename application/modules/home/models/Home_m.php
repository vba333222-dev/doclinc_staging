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

		$request_date_select = $this->db->field_exists('date', 'requests')
			? 'requests.date AS date'
			: 'DATE(requests.created_at) AS date';

		// Query database
		$this->db->select("requests.*, {$request_date_select}, m_dokter.name AS nama_dokter", FALSE);
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
		if (!$this->db->table_exists('konsultasi') && !$this->db->table_exists('medicalrecords')) {
			return [];
		}

		// Load library encryption
		$CI = &get_instance();
		$CI->load->library('encryption');
		$request_date_select = $this->db->field_exists('date', 'requests')
			? 'requests.date AS date'
			: ($this->db->field_exists('updated_at', 'requests') ? 'DATE(requests.updated_at) AS date' : 'DATE(requests.created_at) AS date');

		// Ambil data utama (konsultasi dan user tanpa join terapi)
		$this->db->select("requests.*, {$request_date_select}, users.nama, m_dokter.name AS nama_dokter", FALSE);
		$this->db->from('requests');
		$this->db->join('users', 'requests.user_id = users.userId');
		$this->db->join('m_dokter', 'requests.dokter_id = m_dokter.professional_id');
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
				->select('medicalrecords.record_id AS konsul_id')
				->select('medicalrecords.diagnosis AS diagnosa')
				->select('medicalrecords.recommendations AS saran')
				->select('medicalrecords.diagnosis AS diagnosis')
				->select('medicalrecords.treatment AS treatment')
				->select('medicalrecords.recommendations AS recommendations')
				->select('medicalrecords.created_at AS result_created_at')
				->join('medicalrecords', 'requests.request_id = medicalrecords.request_id', 'left');
		}
		$this->db->where('requests.request_status', 'Completed');
		$this->db->where('requests.user_id', $user_id);
		$this->db->order_by('requests.request_id', 'DESC');
		$query = $this->db->get();
		$hasil = $query->result();

		// Ambil terapi untuk semua konsultasi yang ditemukan
		foreach ($hasil as &$row) {
			$row->terapi_list = $this->db->table_exists('konsultasi') ? $this->getTerapiByKonsulId($row->konsul_id) : [];

			try {
				$row->request_description = $CI->encryption->decrypt(base64_decode($row->request_description));
			} catch (Exception $e) {
				$row->request_description = 'Keluhan tersimpan';
			}
		}

		return $hasil;
	}

	public function getTerapiByKonsulId($konsul_id)
	{
		if (!$this->db->table_exists('terapi')) {
			return [];
		}

		$this->db->where('konsul_id', $konsul_id);
		return $this->db->get('terapi')->result();
	}

	public function getAllDataDoctors($kode_pkm)
	{
		return $this->db
			->select('users.*, locations.*')
			->from('users')
			->join('locations', 'users.userId = locations.id_user')
			->where('users.remark', $kode_pkm)
			->where('users.role', 'dokter')
			->group_by('users.userId')
			->get()
			->result_array();
	}


	public function getAllDataDoctor($kode_pkm)
	{
		if (!$this->db->table_exists('locations')) {
			return $this->db->query("SELECT NULL AS userId, NULL AS nama, NULL AS foto, NULL AS request_id, NULL AS user_id, NULL AS dokter_id, NULL AS request_status, NULL AS date, NULL AS create_date WHERE 1=0");
		}

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
								AND users.remark=" . $this->db->escape($kode_pkm) . "
								GROUP BY users.userId");
	}

	public function getAllRequestPendingAccept($user_id)
	{
		$request_date_select = $this->db->field_exists('date', 'requests')
			? 'requests.date AS date'
			: 'DATE(requests.created_at) AS date';

		return $this->db
			->select("requests.*, {$request_date_select}", FALSE)
			->where('user_id', $user_id)
			->get('requests');
	}

	public function getAllRequestJumlah()
	{
		if ($this->db->field_exists('date', 'requests')) {
			return $this->db->query("SELECT COUNT(*) AS jumlah, NULL AS user_id FROM requests WHERE date=CURDATE()");
		}

		return $this->db->query("SELECT COUNT(*) AS jumlah, NULL AS user_id FROM requests WHERE DATE(created_at)=CURDATE()");
	}

	// public function getAllDataDoctor($kode_pkm)
	// {
	// 	return $this->db->query("SELECT * FROM m_dokter WHERE kode_pkm='$kode_pkm'");
	// }

	public function get_data_feeds()
	{
		if (!$this->db->table_exists('feeds')) {
			return $this->db->query("SELECT NULL AS feedId, NULL AS gambar, NULL AS status WHERE 1=0");
		}

		return $this->db->query("SELECT * FROM feeds WHERE status='aktif' ORDER BY feedId DESC");
	}

	public function get_data_profile($user_id)
	{
		$select = ['users.*'];
		foreach (['tgl', 'gender', 'no_hp', 'alamat', 'foto'] as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				$select[] = 'NULL AS ' . $field;
			}
		}

		if ($this->db->field_exists('tgl', 'users')) {
			$select[] = 'TIMESTAMPDIFF(YEAR, tgl, CURDATE()) AS usia';
		} else {
			$select[] = 'NULL AS usia';
		}

		return $this->db
			->select(implode(', ', $select), FALSE)
			->where('userId', $user_id)
			->get('users');
	}

	public function getDokterRating($idUser)
	{
		return $this->db->where('userId', $idUser)->get('users');
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
		if (!$this->db->table_exists('rating')) {
			return $this->db->query("SELECT NULL AS id_user, NULL AS id_dokter, NULL AS rating WHERE 1=0");
		}

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
