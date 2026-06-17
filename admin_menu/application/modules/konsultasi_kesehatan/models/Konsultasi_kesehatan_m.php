<?php
class Konsultasi_kesehatan_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}
	public function get_data_konsul()
	{

		// Load library encryption
		$CI = &get_instance();
		$CI->load->library('encryption');
		$query = $this->db->query("SELECT
										requests.request_id,
										requests.user_id,
										(SELECT users.nama FROM users WHERE users.userId=requests.user_id) AS nama_warga,
										requests.dokter_id,
										(SELECT users.nama FROM users WHERE users.userId=requests.dokter_id) AS nama_dokter,
										m_puskesmas.nama_puskesmas AS puskesmas,
										requests.date,
										requests.request_description,
										requests.request_status,
										konsultasi.diagnosa,
										konsultasi.saran,
										konsultasi.kriteria,
										konsultasi.foto,
										requests.location,
										requests.location_detail,
										requests.lattitude,
										requests.longitude,
										requests.lattitude_dokter,
										requests.longitude_dokter,
										requests.created_at
									FROM
										requests
									LEFT JOIN konsultasi ON konsultasi.request_id=requests.request_id
									LEFT JOIN users ON users.userId = requests.user_id
									LEFT JOIN m_puskesmas ON m_puskesmas.kode_pkm = users.remark
									ORDER BY requests.date DESC");
		$hasil = $query->result();

		// Ambil terapi untuk semua konsultasi yang ditemukan
		foreach ($hasil as $row) {
			try {
				$row->request_description = $CI->encryption->decrypt(base64_decode($row->request_description));
			} catch (Exception $e) {
				$row->request_description = '[Keluhan tidak dapat didekripsi]';
			}
		}
		return $hasil;
	}
}
