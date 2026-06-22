<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Konsultasi_nakes extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Konsultasi_nakes_m');
		$this->load->helper('request_authz');
		if ($this->session->userdata('logged_in') != TRUE) {
			redirect('login', 'refresh');
		}

		// Load library ApiClient
		$this->load->library('ApiClient');
	}

	public function index()
	{
		redirect('home_nakes');
	}

	public function konsultasi()
	{
		$x['request_id'] = $this->uri->segment(3);
		$kriteria = $this->input->get('kriteria', TRUE);
		$doctor_id = $this->session->userdata('id');
		$data_request = $this->Konsultasi_nakes_m->get_data_request($x['request_id'], $doctor_id);
		$request = $data_request->row();
		if (!$request) {
			doclinc_log_request_event('unauthorized_request_access', $x['request_id'], array('target' => 'konsultasi_nakes'));
			redirect('home_nakes');
			return;
		}

		$x['userid'] = $request->userid;
		$x['nama_pasien'] = $request->nama;
		$x['keluhan'] = $request->request_description;
		$x['tgl_lahir'] = !empty($request->tgl) ? $request->tgl : null;
		$x['umur'] = '-';
		$x['kriteria'] = '';
		if ((string) $kriteria === '0') {
			$x['kriteria'] = 'Selesai Konsultasi';
		} elseif ((string) $kriteria === '1') {
			$x['kriteria'] = 'Kunjungan Nakes';
		}

		if (!empty($x['tgl_lahir'])) {
			try {
				$lahir = new DateTime($x['tgl_lahir']);
				$today = new DateTime('today');
				$x['umur'] = $lahir->diff($today)->y;
			} catch (Exception $e) {
				$x['tgl_lahir'] = null;
			}
		}

		// Tambahkan data obat ke view
		// $x['data_obat'] = $filteredData;
		$this->load->view('konsultasi_nakes_v', $x);
	}

	public function get_terapi()
	{
		$apiUrl = "https://api-satusehat-stg.dto.kemkes.go.id/kfa-v2/products/all";
		$params = [
			'page' => 1,
			'size' => 100,
			'product_type' => 'farmasi'
		];

		// Ambil parameter pencarian
		$search = $this->input->get('cari');

		// Ambil data dari API
		try {
			$response = $this->apiclient->getData($apiUrl, $params);
		} catch (Exception $e) {
			echo json_encode([]);
			return;
		}

		// var_dump($response);

		// Filter hanya name dan kfa_code
		$filteredData = [];
		if (is_array($response) && !empty($response['items']['data'])) {
			foreach ($response['items']['data'] as $item) {
				if (isset($item['name']) && isset($item['kfa_code'])) {
					// Hanya tambahkan jika cocok dengan pencarian
					if (stripos($item['name'], $search) !== false) {
						$filteredData[] = [
							'label' => $item['name'],
							'value' => $item['name'],
							'kfa_code' => $item['kfa_code']
						];
					}
				}
			}
		}

		echo json_encode($filteredData);
	}

	public function chat()
	{
		$request_id = $this->input->get('reqId', TRUE);
		if (!empty($request_id) && !doclinc_can_view_request($request_id)) {
			doclinc_log_request_event('unauthorized_request_access', $request_id, array('target' => 'konsultasi_nakes_chat'));
			redirect('home_nakes');
			return;
		}

		$this->load->view('chat');
	}

	// public function save_konsultasi_nakes()
	// {
	// 	$request_id = $this->input->post('request_id');
	// 	$diagnosa = $this->input->post('diagnosa');
	// 	$saran = $this->input->post('saran');
	// 	$terapi = $this->input->post('terapi');

	// 	$this->Konsultasi_nakes_m->save_konsultasi_nakes($request_id, $diagnosa, $saran, $terapi);
	// 	echo 1;
	// }

	public function save_konsultasi_nakes()
	{
		$this->output->set_content_type('application/json');

		$request_id = $this->input->post('request_id');
		$doctor_id = $this->session->userdata('id');
		$role = $this->session->userdata('role');
		$diagnosa = $this->input->post('diagnosa');
		$saran = $this->input->post('saran');
		$kriteria = $this->input->post('kriteria');
		$rujukan = $this->input->post('rujukan');
		$terapi = json_decode($this->input->post('terapi'), true);
		$terapi = is_array($terapi) ? $terapi : [];
		$foto = null;

		if (!$request_id || !$diagnosa || !$saran || !$kriteria) {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']));
			return;
		}
		if ($role !== 'dokter' || !doclinc_can_update_request($request_id, $doctor_id, $role, array('Accepted'))) {
			$this->output->set_status_header(403);
			doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'complete', 'role' => $role));
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		// Upload gambar jika ada
		if (!empty($_FILES['file']['name'])) {
			$config['upload_path'] = './uploads/';
			$config['allowed_types'] = 'jpg|jpeg|png';
			$config['max_size'] = 5120; // 5MB
			$config['encrypt_name'] = TRUE;
			$config['detect_mime'] = TRUE;
			$config['mod_mime_fix'] = TRUE;
			$config['remove_spaces'] = TRUE;
			$this->load->library('upload', $config);

			if (!$this->upload->do_upload('file')) {
				$this->output->set_output(json_encode(['status' => 'error', 'message' => strip_tags($this->upload->display_errors())]));
				return;
			} else {
				$uploaded = $this->upload->data();
				$foto = $uploaded['file_name'];
			}
		}

		$result = $this->Konsultasi_nakes_m->save_konsultasi_nakes(
			$request_id,
			$diagnosa,
			$saran,
			$kriteria,
			$rujukan,
			$foto,
			$terapi,
			$doctor_id
		);

		if ($result) {
			doclinc_log_request_event('consultation_completed', $request_id);
			$this->output->set_output(json_encode(['status' => 'success', 'message' => 'Konsultasi berhasil disimpan']));
			return;
		}

		$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Konsultasi gagal disimpan']));
	}


	public function getICD_json()
	{
		$term = $this->input->get('term');
		$data = $this->Konsultasi_nakes_m->getICD($term);
		echo json_encode($data);
	}
}
