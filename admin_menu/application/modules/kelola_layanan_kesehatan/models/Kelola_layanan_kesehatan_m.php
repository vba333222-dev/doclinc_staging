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

		if (!is_string($value)) {
			$value = (string) $value;
		}

		$decoded = base64_decode($value, TRUE);
		if ($decoded === FALSE || $decoded === '') {
			return $value !== '' ? $value : $fallback;
		}

		try {
			$CI = &get_instance();
			$CI->load->library('encryption');
			$decrypted = $CI->encryption->decrypt($decoded);
		} catch (Throwable $e) {
			return $value !== '' ? $value : $fallback;
		}

		if ($decrypted === FALSE || $decrypted === null || $decrypted === '') {
			return $value !== '' ? $value : $fallback;
		}

		return $decrypted;
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
