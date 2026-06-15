<?php
class Konsultasi_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	public function save_konsultasi(
		$id_user,
		$dokter_id,
		$riwayat = null,
		$keluhan,
		$alamat,
		$lattitude,
		$longitude,
		$tanggal,
		$foto = null,
		$video = null
	) {
		$this->db->trans_start(); // Mulai transaksi

		// Query insert ke tabel requests
		$this->db->query(
			"INSERT INTO requests SET
		user_id = " . $this->db->escape($id_user) . ",
		dokter_id = " . $this->db->escape($dokter_id) . ",
		request_description = " . $this->db->escape($keluhan) . ",
		request_status = 'Pending',
		location = " . $this->db->escape($alamat) . ",
		lattitude = " . $this->db->escape($lattitude) . ",
		longitude = " . $this->db->escape($longitude) . ",
		date = " . $this->db->escape(date('Y-m-d', strtotime($tanggal))) . ",
		photos = " . $this->db->escape($foto) . ",
		video = " . $this->db->escape($video)
		);

		// Cek apakah sudah ada riwayat untuk user tersebut
		$cek = $this->db->query("SELECT * FROM tbl_riwayat WHERE idUser = " . $this->db->escape($id_user))->row();

		if ($cek) {
			// Jika ada → update riwayat dan updated_at saja
			$this->db->query(
				"UPDATE tbl_riwayat SET
			riwayat = " . $this->db->escape($riwayat) . ",
			updated_at = NOW()
			WHERE idUser = " . $this->db->escape($id_user)
			);
		} else {
			// Jika tidak ada → insert baru
			$this->db->query("INSERT INTO tbl_riwayat SET
			idUser = " . $this->db->escape($id_user) . ",
			riwayat = " . $this->db->escape($riwayat) . ",
			created_at = NOW(),
			updated_at = NOW()
		");
		}

		$this->db->trans_complete(); // Selesaikan transaksi

		// Kembalikan status transaksi
		return $this->db->trans_status(); // true jika berhasil, false jika gagal
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
			'date'                => date('Y-m-d', strtotime($tanggal)),
			'photos'              => $foto,
			'video'               => $video,
		];

		$this->db->insert('requests', $data);
		// $this->db->insert_id(); // atau return true/false kalau kamu tidak perlu ID-nya

		$data_riwayat = [
			'idUser' => $id_user,
			'riwayat' => $riwayat,
		];

		$this->db->insert('tbl_riwayat', $data_riwayat);

		$this->db->trans_complete();
		return $this->db->trans_status();
	}


	public function getAllDataLocations($dokter)
	{
		$query = $this->db->query("SELECT
			latitude, longitude, location
		FROM
			locations
		WHERE
			id_user='$dokter'
		AND
			date(locations.create_date)=date(now())");
		return $query->result_array();
	}

	public function getLocation($dokter)
	{
		$query = $this->db->query("SELECT
			location
		FROM
			locations
		WHERE
			id_user='$dokter'
		AND
			date(locations.create_date)=date(now())
		ORDER BY
			create_date
		DESC LIMIT 1");
		return $query->row_array();
	}

	public function getDataDoctor($dokter)
	{
		return $this->db->query("SELECT * FROM users WHERE userId='$dokter'");
	}

	public function getFotoDokter($dokter)
	{
		return $this->db->query("SELECT * FROM users WHERE userId='$dokter'");
	}

	public function getDataTokenDoctor($dokter)
	{
		// buatkan query join antara tabel users dan tabel fcm_tokens diaman yang menjadi referensinya adalah no_hp users
		// dan phone di tabel fcm_tokens
		return $this->db->query("SELECT fcm_tokens.phone,fcm_tokens.token, users.nama FROM fcm_tokens JOIN users ON fcm_tokens.phone=users.no_hp WHERE users.userId='$dokter'");
	}

	public function getDataPenunjangById($id_user)
	{

		// Load library encryption
		$CI = &get_instance();
		$CI->load->library('encryption');

		$query = $this->db->query("SELECT * FROM tbl_riwayat WHERE idUser ='$id_user'")->row();
		// $query = $this->db->query("SELECT * FROM tbl_riwayat WHERE idUser ='$id_user'");
		// $hasil = $query->row();

		if (!$query || empty($query->riwayat)) {
			return '';
		}

		$decoded = base64_decode($query->riwayat);
		if ($decoded === false) {
			return '';
		}

		$hasil = $CI->encryption->decrypt($decoded);

		return $hasil ?: '';
	}
}
