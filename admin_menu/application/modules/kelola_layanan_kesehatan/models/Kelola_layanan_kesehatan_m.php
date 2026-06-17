<?php
class Kelola_layanan_kesehatan_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}
	public function get_data_layanan_kesehatan()
	{
		// Load library encryption
		$CI = &get_instance();
		$CI->load->library('encryption');
		$query = $this->db->query("SELECT
                                        *
                                    FROM
                                        requests
                                    ORDER BY created_at DESC");
		$hasil = $query->result();

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
