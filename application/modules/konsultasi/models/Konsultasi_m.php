<?php
class Konsultasi_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	private function empty_query()
	{
		return $this->db->query('SELECT 1 WHERE 1 = 0');
	}

	public function save_konsultasi(
		$id_user,
		$dokter_id,
		$riwayat,
		$keluhan,
		$alamat,
		$lattitude,
		$longitude,
		$tanggal,
		$foto = null,
		$video = null
	) {
		if (empty($id_user)) {
			return false;
		}
		if (empty($dokter_id)) {
			return false;
		}

		$this->db->trans_start(); // Mulai transaksi

		$data = [
			'user_id' => $id_user,
			'dokter_id' => $dokter_id,
			'request_description' => $keluhan,
			'request_status' => 'Pending',
			'location' => $alamat,
			'lattitude' => $lattitude,
			'longitude' => $longitude,
		];
		if ($this->db->field_exists('date', 'requests')) {
			$data['date'] = date('Y-m-d', strtotime($tanggal));
		}
		if ($this->db->field_exists('photos', 'requests')) {
			$data['photos'] = $foto;
		}
		if ($this->db->field_exists('video', 'requests')) {
			$data['video'] = $video;
		}
		if ($this->db->field_exists('created_at', 'requests')) {
			$data['created_at'] = date('Y-m-d H:i:s');
		}
		if ($this->db->field_exists('updated_at', 'requests')) {
			$data['updated_at'] = date('Y-m-d H:i:s');
		}

		$this->db->insert('requests', $data);
		$request_id = $this->db->insert_id();

		// Cek apakah sudah ada riwayat untuk user tersebut
		if ($this->db->table_exists('tbl_riwayat')) {
			$cek = $this->db->query("SELECT * FROM tbl_riwayat WHERE idUser = " . $this->db->escape($id_user))->row();

			if ($cek) {
				$this->db->query(
					"UPDATE tbl_riwayat SET
				riwayat = " . $this->db->escape($riwayat) . ",
				updated_at = NOW()
				WHERE idUser = " . $this->db->escape($id_user)
				);
			} else {
				$this->db->query("INSERT INTO tbl_riwayat SET
				idUser = " . $this->db->escape($id_user) . ",
				riwayat = " . $this->db->escape($riwayat) . ",
				created_at = NOW(),
				updated_at = NOW()
			");
			}
		}

		$this->db->trans_complete(); // Selesaikan transaksi

		// Kembalikan status transaksi
		return $this->db->trans_status() ? $request_id : false; // true jika berhasil, false jika gagal
	}

	public function is_active_doctor($dokter_id)
	{
		if (empty($dokter_id)) {
			return false;
		}

		$this->db->where('userId', $dokter_id);
		$this->db->where('role', 'dokter');
		if ($this->db->field_exists('status', 'users')) {
			$this->db->where('status', 'aktif');
		}

		return $this->db->count_all_results('users') > 0;
	}

	public function save_konsultasis($id_user, $dokter_id, $riwayat, $keluhan, $alamat, $lattitude, $longitude, $tanggal, $foto = null, $video = null)
	{

		$this->db->trans_start();

		$data = [
			'user_id'             => $id_user,
			'dokter_id'           => $dokter_id,
			'request_description' => $keluhan,
			'request_status'      => 'Pending',
			'location'            => $alamat,
			'lattitude'           => $lattitude,
			'longitude'           => $longitude,
		];
		if ($this->db->field_exists('date', 'requests')) {
			$data['date'] = date('Y-m-d', strtotime($tanggal));
		}
		if ($this->db->field_exists('photos', 'requests')) {
			$data['photos'] = $foto;
		}
		if ($this->db->field_exists('video', 'requests')) {
			$data['video'] = $video;
		}

		$this->db->insert('requests', $data);
		// $this->db->insert_id(); // atau return true/false kalau kamu tidak perlu ID-nya

		$data_riwayat = [
			'idUser' => $id_user,
			'riwayat' => $riwayat,
		];

		if ($this->db->table_exists('tbl_riwayat')) {
			$this->db->insert('tbl_riwayat', $data_riwayat);
		}

		$this->db->trans_complete();
		return $this->db->trans_status();
	}


	public function getAllDataLocations($dokter)
	{
		if (!$this->db->table_exists('locations')) {
			return [];
		}

		$query = $this->db->query("SELECT
			latitude, longitude, location
		FROM
			locations
		WHERE
			id_user=" . $this->db->escape($dokter) . "
		AND
			date(locations.create_date)=date(now())");
		return $query->result_array();
	}

	public function getLocation($dokter)
	{
		if (!$this->db->table_exists('locations')) {
			return ['location' => 'Lokasi belum tersedia'];
		}

		$query = $this->db->query("SELECT
			location
		FROM
			locations
		WHERE
			id_user=" . $this->db->escape($dokter) . "
		AND
			date(locations.create_date)=date(now())
		ORDER BY
			create_date
		DESC LIMIT 1");
		$result = $query->row_array();
		return $result ?: ['location' => 'Lokasi belum tersedia'];
	}

	public function getDataDoctor($dokter)
	{
		$this->db->select('users.*');
		foreach (['foto', 'no_hp', 'alamat', 'tgl'] as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				$this->db->select("NULL AS {$field}", FALSE);
			}
		}

		return $this->db->where('userId', $dokter)->get('users');
	}

	public function getFotoDokter($dokter)
	{
		return $this->getDataDoctor($dokter);
	}

	public function getDataTokenDoctor($dokter)
	{
		if (!$this->db->table_exists('fcm_tokens') || !$this->db->field_exists('no_hp', 'users')) {
			return $this->empty_query();
		}

		// buatkan query join antara tabel users dan tabel fcm_tokens diaman yang menjadi referensinya adalah no_hp users
		// dan phone di tabel fcm_tokens
		return $this->db
			->select('fcm_tokens.phone, fcm_tokens.token, users.nama')
			->from('fcm_tokens')
			->join('users', 'fcm_tokens.phone = users.no_hp')
			->where('users.userId', $dokter)
			->get();
	}

	public function getDataPenunjangById($id_user)
	{
		if (!$this->db->table_exists('tbl_riwayat')) {
			return '';
		}

		// Load library encryption
		$CI = &get_instance();
		$CI->load->library('encryption');

		$query = $this->db->where('idUser', $id_user)->get('tbl_riwayat')->row();
		// $query = $this->db->query("SELECT * FROM tbl_riwayat WHERE idUser ='$id_user'");
		// $hasil = $query->row();

		if (!$query || empty($query->riwayat)) {
			return '';
		}

		$hasil = $CI->encryption->decrypt(base64_decode($query->riwayat));

		return $hasil;
	}
}
