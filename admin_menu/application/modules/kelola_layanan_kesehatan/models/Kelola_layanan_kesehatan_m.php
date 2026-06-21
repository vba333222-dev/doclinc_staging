<?php
class Kelola_layanan_kesehatan_m extends MX_Controller
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

	public function get_data_layanan_kesehatan()
	{
		if (!$this->db->table_exists('requests')) {
			return array();
		}

		$date_select = $this->db->field_exists('date', 'requests') ? 'date' : 'created_at AS date';
		$query = $this->db->query("SELECT
                                        request_id,
                                        request_description,
                                        request_status,
                                        $date_select
                                    FROM
                                        requests
                                    ORDER BY created_at DESC");
		$hasil = $query->result();

		foreach ($hasil as $row) {
			$row->request_description = $this->safe_decrypt($row->request_description, '-');
		}
		return $hasil;
	}
}
