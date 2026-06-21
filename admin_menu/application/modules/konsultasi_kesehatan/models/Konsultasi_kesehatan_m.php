<?php
class Konsultasi_kesehatan_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	private function safe_decrypt($value, $fallback = '-')
	{
		if ($value === null || $value === '') {
			return $fallback;
		}

		if (is_array($value) || is_object($value)) {
			return $fallback;
		}

		if (!is_scalar($value)) {
			return $fallback;
		}

		$value = trim((string) $value);
		if ($value === '') {
			return $fallback;
		}

		$is_encoded_payload = $this->is_probably_encoded_payload($value);

		$decrypted = $this->try_decrypt_string($value);
		if ($decrypted === null) {
			$decoded = base64_decode($value, TRUE);
			if (is_string($decoded) && $decoded !== '') {
				$decrypted = $this->try_decrypt_string($decoded);
			}
		}

		if ($decrypted !== null && $decrypted !== '') {
			return $decrypted;
		}

		return $is_encoded_payload ? $fallback : $value;
	}

	private function try_decrypt_string($value)
	{
		try {
			$CI = &get_instance();
			$CI->load->library('encryption');
			set_error_handler(function ($severity, $message, $file, $line) {
				throw new ErrorException($message, 0, $severity, $file, $line);
			});
			$decrypted = $CI->encryption->decrypt($value);
		} catch (Throwable $e) {
			$decrypted = null;
		} finally {
			restore_error_handler();
		}

		return ($decrypted === FALSE || $decrypted === null) ? null : $decrypted;
	}

	private function is_probably_encoded_payload($value)
	{
		if (strlen($value) > 80 && preg_match('/^[A-Za-z0-9+\/=]+$/', $value)) {
			return true;
		}

		$decoded = base64_decode($value, TRUE);
		return is_string($decoded) && strlen($decoded) > 32 && preg_match('/^[A-Fa-f0-9]{32,}/', $decoded);
	}

	public function get_data_konsul()
	{
		if (!$this->db->table_exists('requests') || !$this->db->table_exists('users')) {
			return array();
		}

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

		foreach ($hasil as $row) {
			$row->request_description = $this->safe_decrypt($row->request_description, '-');
		}
		return $hasil;
	}
}
