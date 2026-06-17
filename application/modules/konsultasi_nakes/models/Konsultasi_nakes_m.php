<?php
class Konsultasi_nakes_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	public function get_data_request($request_id)
	{
		return $this->db
			->select('requests.*, users.userid, users.nama, users.tgl')
			->from('requests')
			->join('users', 'requests.user_id = users.userId')
			->where('requests.request_status', 'Accepted')
			->where('requests.request_id', $request_id)
			->get();
	}

	// public function save_konsultasi_nakes($request_id, $diagnosa, $saran)
	// {
	// 	$date = date('Y-m-d H:i:s');
	// 	$user = $this->session->userdata('id');
	// 	$this->db->query("UPDATE requests SET request_status = 'Completed' WHERE request_id = '$request_id'");
	// 	$this->db->query("INSERT konsultasi SET
	// 					 request_id = '$request_id',
	// 					 diagnosa = '$diagnosa',
	// 					 saran = '$saran',
	// 					 create_date = '$date',
	// 					 create_user = '$user'");
	// }

	public function save_konsultasi_nakes($request_id, $diagnosa, $saran, $kriteria, $rujukan, $file_path, $terapi)
	{
		$date = date('Y-m-d H:i:s');
		$user = $this->session->userdata('id');

		$this->db->trans_start();

		$this->db->where('request_id', $request_id);
		$this->db->update('requests', ['request_status' => 'Completed']);

		$data_konsul = [
			'request_id' => $request_id,
			'diagnosa' => $diagnosa,
			'saran' => $saran,
			'kriteria' => $kriteria,
			'rujukan' => $rujukan,
			'foto' => $file_path,
			'create_date' => $date,
			'create_user' => $user
		];
		$this->db->insert('konsultasi', $data_konsul);
		$konsul_id = $this->db->insert_id();

		if (!empty($terapi)) {
			foreach ($terapi as $t) {
				$data_terapi = [
					'konsul_id' => $konsul_id,
					'terapi' => $t['terapi'],
					'signa' => $t['jumlah'] . ' - ' . $t['cara'],
					'keterangan' => $t['keterangan'],
					'create_date' => $date,
					'create_user' => $user
				];
				$this->db->insert('terapi', $data_terapi);
			}
		}

		$this->db->trans_complete();
		return $this->db->trans_status();
	}


	public function getICD($term = "")
	{
		// $query = $this->db->query("SELECT id_keluhan, nama_keluhan FROM keluhan");
		// return $query->result();
		$this->db->select('id_keluhan, nama_keluhan');
		$this->db->like('nama_keluhan', $term);
		$this->db->or_like('id_keluhan', $term);
		$query = $this->db->get('keluhan');
		return $query->result();
	}
}
