<?php
class Konsultasi_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	public function save_konsultasi($id_user, $keluhan, $alamat, $lattitude, $longitude, $tanggal)
	{
		$query = $this->db->query("INSERT requests SET user_id='" . $id_user . "',
                                    dokter_id='1',
                                    request_description='" . $keluhan . "',
                                    request_status='Pending',
                                    location='" . $alamat . "',
                                    lattitude='" . $lattitude . "',
                                    longitude='" . $longitude . "',
                                    date='" . date('Y-m-d', strtotime($tanggal)) . "'
                                    ");
		return $query;
	}

	public function getAllDataLocations()
	{
		$query = $this->db->query("SELECT
			latitude, longitude, location
		FROM
			locations
		WHERE
			name='dokter1'
		AND
			date(locations.create_date)=date(now())");
		return $query->result_array();
	}

	public function getLocation()
	{
		$query = $this->db->query("SELECT
			location
		FROM
			locations
		WHERE
			name='dokter1'
		AND
			date(locations.create_date)=date(now())
		ORDER BY
			create_date
		DESC LIMIT 1");
		return $query->row_array();
	}

	public function getCoordinate()
	{
		// Data koordinat hardcoded untuk titik A
		return [
			'latitude' => -5.975178,
			'longitude' => 106.052544
		];
	}
}
