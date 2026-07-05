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
		$has_assigned_puskesmas_code = $this->db->field_exists('assigned_puskesmas_code', 'requests');
		$has_assigned_puskesmas_name = $this->db->field_exists('assigned_puskesmas_name', 'requests');
		$assigned_puskesmas_name_expr = $has_assigned_puskesmas_name ? "NULLIF(TRIM(requests.assigned_puskesmas_name), '')" : 'NULL';
		$assigned_puskesmas_code_expr = $has_assigned_puskesmas_code ? "NULLIF(TRIM(requests.assigned_puskesmas_code), '')" : 'NULL';
		$routed_puskesmas_code_expr = "CASE WHEN UPPER($assigned_puskesmas_code_expr) = 'DEFAULT' THEN NULL ELSE $assigned_puskesmas_code_expr END";
		$has_user_remark = $this->db->field_exists('remark', 'users');
		$legacy_provider_code_expr = $has_user_remark ? "CASE WHEN UPPER(TRIM(provider_user.remark)) = 'DEFAULT' THEN NULL ELSE NULLIF(TRIM(provider_user.remark), '') END" : 'NULL';
		$has_puskesmas = $this->db->table_exists('m_puskesmas') && $has_user_remark;
		$puskesmas_select = $has_puskesmas
			? "COALESCE($assigned_puskesmas_name_expr, assigned_puskesmas.nama_puskesmas, provider_puskesmas.nama_puskesmas, 'Belum terklasifikasi') AS puskesmas"
			: "COALESCE($assigned_puskesmas_name_expr, $routed_puskesmas_code_expr, $legacy_provider_code_expr, 'Belum terklasifikasi') AS puskesmas";
		$konsultasi_select = $this->db->table_exists('konsultasi')
			? 'konsultasi.diagnosa, konsultasi.saran, konsultasi.kriteria, konsultasi.foto'
			: 'medicalrecords.diagnosis AS diagnosa, medicalrecords.recommendations AS saran, NULL AS kriteria, NULL AS foto';
		$konsultasi_join = $this->db->table_exists('konsultasi')
			? 'LEFT JOIN konsultasi ON konsultasi.request_id=requests.request_id'
			: 'LEFT JOIN medicalrecords ON medicalrecords.request_id=requests.request_id';
		$puskesmas_join = $has_puskesmas
			? "LEFT JOIN m_puskesmas assigned_puskesmas ON assigned_puskesmas.kode_pkm = $routed_puskesmas_code_expr
									LEFT JOIN m_puskesmas provider_puskesmas ON provider_puskesmas.kode_pkm = provider_user.remark AND UPPER(TRIM(provider_user.remark)) <> 'DEFAULT'"
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
									LEFT JOIN users provider_user ON provider_user.userId = requests.dokter_id
									$puskesmas_join
									ORDER BY date DESC");
		$hasil = $query->result();

		foreach ($hasil as $row) {
			$row->request_description = $this->safe_decrypt($row->request_description, '-');
		}
		return $hasil;
	}
}
