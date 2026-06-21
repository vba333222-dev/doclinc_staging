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
		if (!$this->db->table_exists('requests') || !$this->db->table_exists('users')) {
			return array();
		}

		// Load library encryption
		$CI = &get_instance();
		$CI->load->library('encryption');
		$date_select = $this->db->field_exists('date', 'requests') ? 'requests.date' : 'requests.created_at AS date';
		$location_detail_select = $this->db->field_exists('location_detail', 'requests') ? 'requests.location_detail' : 'NULL AS location_detail';
		$lattitude_dokter_select = $this->db->field_exists('lattitude_dokter', 'requests') ? 'requests.lattitude_dokter' : 'NULL AS lattitude_dokter';
		$longitude_dokter_select = $this->db->field_exists('longitude_dokter', 'requests') ? 'requests.longitude_dokter' : 'NULL AS longitude_dokter';
		$puskesmas_select = ($this->db->table_exists('m_puskesmas') && $this->db->field_exists('remark', 'users')) ? 'm_puskesmas.nama_puskesmas AS puskesmas' : 'NULL AS puskesmas';
		$konsultasi_select = $this->db->table_exists('konsultasi')
			? 'konsultasi.diagnosa, konsultasi.saran, konsultasi.kriteria, konsultasi.foto'
			: 'medicalrecords.diagnosis AS diagnosa, medicalrecords.recommendations AS saran, NULL AS kriteria, NULL AS foto';
		$konsultasi_join = $this->db->table_exists('konsultasi')
			? 'LEFT JOIN konsultasi ON konsultasi.request_id=requests.request_id'
			: 'LEFT JOIN medicalrecords ON medicalrecords.request_id=requests.request_id';
		$puskesmas_join = ($this->db->table_exists('m_puskesmas') && $this->db->field_exists('remark', 'users'))
			? 'LEFT JOIN m_puskesmas ON m_puskesmas.kode_pkm = users.remark'
			: '';
		$query = $this->db->query("SELECT
										requests.request_id,
										requests.user_id,
										(SELECT users.nama FROM users WHERE users.userId=requests.user_id) AS nama_warga,
										requests.dokter_id,
										(SELECT users.nama FROM users WHERE users.userId=requests.dokter_id) AS nama_dokter,
										$puskesmas_select,
										$date_select,
										requests.request_description,
										requests.request_status,
										$konsultasi_select,
										requests.location,
										$location_detail_select,
										requests.lattitude,
										requests.longitude,
										$lattitude_dokter_select,
										$longitude_dokter_select,
										requests.created_at
									FROM
										requests
									$konsultasi_join
									LEFT JOIN users ON users.userId = requests.user_id
									$puskesmas_join
									ORDER BY date DESC");
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
