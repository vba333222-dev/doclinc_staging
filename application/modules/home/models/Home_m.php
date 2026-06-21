<?php
class Home_m extends MX_Controller
{
	protected $db;

	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	private function selectDoctorFields()
	{
		$this->db->select('users.*');

		if (!$this->db->field_exists('foto', 'users')) {
			$this->db->select('NULL AS foto', FALSE);
		}
	}

	private function applyDoctorFilters($kode_pkm)
	{
		$this->db->where('users.role', 'dokter');

		if ($this->db->field_exists('status', 'users')) {
			$this->db->where('users.status', 'aktif');
		}

		if (!empty($kode_pkm) && $this->db->field_exists('remark', 'users')) {
			$this->db->where('users.remark', $kode_pkm);
		}
	}

	private function joinDoctorLocations()
	{
		if (!$this->db->table_exists('locations') || !$this->db->field_exists('id_user', 'locations')) {
			$this->db->select('NULL AS create_date, NULL AS latitude, NULL AS longitude, NULL AS location', FALSE);
			return;
		}

		$this->db->select('locations.*');

		$location_join = 'users.userId = locations.id_user';
		if ($this->db->field_exists('create_date', 'locations')) {
			$location_join .= ' AND DATE(locations.create_date) = CURDATE()';
		} else {
			$this->db->select('NULL AS create_date', FALSE);
		}

		$this->db->join('locations', $location_join, 'left', FALSE);
	}

	private function joinLatestPendingRequest()
	{
		$can_join_requests = $this->db->table_exists('requests')
			&& $this->db->field_exists('request_id', 'requests')
			&& $this->db->field_exists('dokter_id', 'requests')
			&& $this->db->field_exists('request_status', 'requests');

		if (!$can_join_requests) {
			$this->db->select('NULL AS request_id, NULL AS user_id, NULL AS dokter_id, NULL AS request_status, NULL AS date', FALSE);
			return;
		}

		$request_select = array(
			'requests.request_id AS request_id',
			$this->db->field_exists('user_id', 'requests') ? 'requests.user_id AS user_id' : 'NULL AS user_id',
			'requests.dokter_id AS dokter_id',
			'requests.request_status AS request_status',
		);

		if ($this->db->field_exists('date', 'requests')) {
			$request_select[] = 'requests.date AS date';
		} elseif ($this->db->field_exists('created_at', 'requests')) {
			$request_select[] = 'DATE(requests.created_at) AS date';
		} else {
			$request_select[] = 'NULL AS date';
		}

		$this->db->select(implode(', ', $request_select), FALSE);
		$this->db->join(
			'requests',
			"requests.request_id = (
				SELECT MAX(r2.request_id)
				FROM requests r2
				WHERE r2.dokter_id = users.userId
				AND r2.request_status = 'Pending'
			)",
			'left',
			FALSE
		);
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
		$this->db->select("requests.*, {$request_date_select}, users.nama, COALESCE(m_dokter.name, dokter_user.nama, 'Dokter') AS nama_dokter", FALSE);
		$this->db->from('requests');
		$this->db->join('users', 'requests.user_id = users.userId');
		$this->db->join('m_dokter', 'requests.dokter_id = m_dokter.professional_id', 'left');
		$this->db->join('users AS dokter_user', 'requests.dokter_id = dokter_user.userId', 'left');
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
		$this->selectDoctorFields();
		$this->joinDoctorLocations();
		$this->db->from('users');
		$this->applyDoctorFilters($kode_pkm);
		$this->db->group_by('users.userId');

		return $this->db->get()->result_array();
	}


	public function getAllDataDoctor($kode_pkm)
	{
		$this->selectDoctorFields();
		$this->joinDoctorLocations();
		$this->joinLatestPendingRequest();
		$this->db->from('users');
		$this->applyDoctorFilters($kode_pkm);
		$this->db->group_by('users.userId');

		return $this->db->get();
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
