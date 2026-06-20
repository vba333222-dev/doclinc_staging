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
		$userIdSelect = $this->db->field_exists('userid', 'users') ? 'users.userid' : 'users.userId AS userid';
		$tglSelect = $this->db->field_exists('tgl', 'users') ? 'users.tgl' : 'NULL AS tgl';

		return $this->db
			->select('requests.*, users.nama')
			->select($userIdSelect, FALSE)
			->select($tglSelect, FALSE)
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
		$terapi = is_array($terapi) ? $terapi : [];

		$this->db->trans_start();

		$request_data = ['request_status' => 'Completed'];
		if ($this->db->field_exists('updated_at', 'requests')) {
			$request_data['updated_at'] = $date;
		}

		$this->db->where('request_id', $request_id);
		$this->db->update('requests', $request_data);

		if ($this->db->table_exists('konsultasi')) {
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

			if ($this->db->table_exists('terapi') && !empty($terapi)) {
				foreach ($terapi as $t) {
					$data_terapi = [
						'konsul_id' => $konsul_id,
						'terapi' => $t['terapi'] ?? '',
						'signa' => ($t['jumlah'] ?? '') . ' - ' . ($t['cara'] ?? ''),
						'keterangan' => $t['keterangan'] ?? '',
						'create_date' => $date,
						'create_user' => $user
					];
					$this->db->insert('terapi', $data_terapi);
				}
			}
		} elseif ($this->db->table_exists('medicalrecords')) {
			$this->db->insert('medicalrecords', [
				'request_id' => $request_id,
				'diagnosis' => $diagnosa,
				'treatment' => $this->build_treatment_summary($kriteria, $rujukan, $file_path, $terapi),
				'recommendations' => $saran,
				'created_at' => $date
			]);
		}

		$this->db->trans_complete();
		return $this->db->trans_status();
	}

	private function build_treatment_summary($kriteria, $rujukan, $file_path, $terapi)
	{
		$parts = [];
		if (!empty($kriteria)) {
			$parts[] = 'Kriteria: ' . $kriteria;
		}
		if (!empty($rujukan)) {
			$parts[] = 'Rujukan: ' . $rujukan;
		}
		if (!empty($file_path)) {
			$parts[] = 'Foto: ' . $file_path;
		}
		if (!empty($terapi)) {
			$terapi_parts = [];
			foreach ($terapi as $t) {
				$terapi_parts[] = trim(($t['terapi'] ?? '') . ' ' . ($t['jumlah'] ?? '') . ' ' . ($t['cara'] ?? '') . ' ' . ($t['keterangan'] ?? ''));
			}
			$parts[] = 'Terapi: ' . implode('; ', array_filter($terapi_parts));
		}

		return implode("\n", $parts);
	}


	public function getICD($term = "")
	{
		if (!$this->db->table_exists('keluhan')) {
			return [];
		}

		// $query = $this->db->query("SELECT id_keluhan, nama_keluhan FROM keluhan");
		// return $query->result();
		$this->db->select('id_keluhan, nama_keluhan');
		$this->db->like('nama_keluhan', $term);
		$this->db->or_like('id_keluhan', $term);
		$query = $this->db->get('keluhan');
		return $query->result();
	}
}
