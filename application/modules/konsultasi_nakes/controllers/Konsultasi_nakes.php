<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Konsultasi_nakes extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Konsultasi_nakes_m');
		$this->load->model('home_nakes/Home_nakes_m');
		$this->load->helper('request_authz');
		$this->load->helper('notification');
		$this->load->helper('request_realtime');
		$this->load->library('Clinical_anamnesis');
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
		$doctor_id = (int) $this->session->userdata('id');
		$identity_context = doclinc_dokter_identity_context($doctor_id);
		$access_context = doclinc_nakes_request_access_context($x['request_id'], $identity_context);
		if (empty($access_context['can_view'])) {
			redirect('home_nakes');
			return;
		}
		$data_request = $this->Konsultasi_nakes_m->get_data_request($x['request_id'], $doctor_id, $identity_context);
		$request = $data_request->row();
		if (!$request) {
			doclinc_log_request_event('unauthorized_request_access', $x['request_id'], array('target' => 'konsultasi_nakes'));
			redirect('home_nakes');
			return;
		}

		$x['userid'] = $request->userid;
		$x['nama_pasien'] = $request->nama;
		$x['keluhan'] = $request->request_description;
		$x['queue_code'] = doclinc_request_queue_code($request);
		$x['tgl_lahir'] = !empty($request->tgl) ? $request->tgl : null;
		$x['umur'] = '-';
		$x['kriteria'] = '';
		$x['can_handle_request'] = !empty($access_context['can_handle']);
		$x['anamnesis_schema_ready'] = false;
		$x['anamnesis_existing'] = '';
		if ((bool) $this->config->item('clinical_suggestions_enabled')) {
			$x['anamnesis_schema_ready'] = Clinical_anamnesis::schema_ready($this->db);
			if ($x['anamnesis_schema_ready']) {
				$existing_record = $this->db->query(
					'SELECT anamnesis FROM ' . $this->db->dbprefix('medicalrecords') . ' WHERE request_id = ? ORDER BY record_id DESC LIMIT 1',
					array($x['request_id'])
				)->row();
				$x['anamnesis_existing'] = $existing_record && isset($existing_record->anamnesis)
					? (string) $existing_record->anamnesis
					: '';
			}
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
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']));
			return;
		}

		$request_id = (int) $this->input->post('request_id');
		$doctor_id = (int) $this->session->userdata('id');
		$identity_context = doclinc_dokter_identity_context($doctor_id);
		$diagnosa = $this->input->post('diagnosa');
		$saran = $this->input->post('saran');
		$kriteria = $this->input->post('kriteria');
		$rujukan = $this->input->post('rujukan');
		$terapi = json_decode($this->input->post('terapi'), true);
		$terapi = is_array($terapi) ? $terapi : [];
		$foto = null;
		$write_anamnesis = (bool) $this->config->item('clinical_suggestions_enabled');
		$anamnesis = null;

		if (!$request_id || !$diagnosa || !$saran || !$kriteria) {
			$validation_message = !$request_id
				? 'Permintaan tidak valid.'
				: (!$diagnosa
					? 'Masukkan diagnosis.'
					: (!$saran ? 'Tambahkan saran.' : 'Pilih jenis layanan.'));
			$this->output->set_output(json_encode(['status' => 'error', 'message' => $validation_message]));
			return;
		}
		$access_context = doclinc_nakes_request_access_context($request_id, $identity_context);
		if (empty($access_context['can_handle'])) {
			$this->output->set_status_header(403);
			doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'complete'));
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses.']));
			return;
		}
		if ($write_anamnesis) {
			if (!Clinical_anamnesis::schema_ready($this->db)) {
				$this->output
					->set_status_header(503)
					->set_output(json_encode(['status' => 'error', 'message' => 'Penyimpanan anamnesis belum siap. Hubungi administrator.']));
				return;
			}
			$normalized_anamnesis = Clinical_anamnesis::normalize($this->input->post('anamnesis', false));
			if (empty($normalized_anamnesis['valid'])) {
				$message = $normalized_anamnesis['error'] === 'value_too_long'
					? 'Anamnesis maksimal 5.000 karakter.'
					: 'Format anamnesis tidak valid.';
				$this->output
					->set_status_header(422)
					->set_output(json_encode(['status' => 'error', 'message' => $message]));
				return;
			}
			$anamnesis = $normalized_anamnesis['value'];
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
				$this->output
					->set_status_header(400)
					->set_output(json_encode(['status' => 'error', 'message' => $this->result_upload_error_message()]));
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
			$doctor_id,
			$identity_context,
			$anamnesis,
			$write_anamnesis
		);

		if ($result) {
			doclinc_log_request_event('consultation_completed', $request_id);
			$request = doclinc_request_row($request_id);
			$event_metadata = array(
				'request_status' => $request && isset($request->request_status) ? $request->request_status : 'Completed',
			);
			if ($request && isset($request->updated_at) && !empty($request->updated_at)) {
				$event_metadata['completed_at'] = $request->updated_at;
			}
			if ($this->db->table_exists('medicalrecords')) {
				foreach (array('medicalrecord_id', 'record_id', 'id') as $record_field) {
					if ($this->db->field_exists($record_field, 'medicalrecords')) {
						$medical_record = $this->db
							->select($record_field)
							->from('medicalrecords')
							->where('request_id', $request_id)
							->get()
							->row();
						if ($medical_record && isset($medical_record->{$record_field})) {
							$event_metadata[$record_field] = $medical_record->{$record_field};
						}
						break;
					}
				}
			}
			$event_puskesmas_code = $request && isset($request->assigned_puskesmas_code) && trim((string) $request->assigned_puskesmas_code) !== ''
				? $request->assigned_puskesmas_code
				: $identity_context['puskesmas_code'];
			$this->Home_nakes_m->append_request_event($request_id, 'request_completed', array(
				'puskesmas_code' => $event_puskesmas_code,
				'actor_user_id' => $doctor_id,
				'actor_role' => 'dokter',
				'message' => 'Permintaan diselesaikan.',
				'metadata' => $event_metadata,
			));
			$orchestration = doclinc_request_transition_orchestrator()->requestCompleted(
				$request_id,
				$request,
				$doctor_id,
				$result
			);
			$this->output->set_output(json_encode($orchestration['response']));
			return;
		}

		$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Konsultasi belum dapat diselesaikan.']));
	}


	public function getICD_json()
	{
		$term = $this->input->get('term');
		$data = $this->Konsultasi_nakes_m->getICD($term);
		echo json_encode($data);
	}

	private function result_upload_error_message()
	{
		return 'File hasil konsultasi tidak valid. Gunakan JPG/PNG dengan ukuran maksimal 5 MB.';
	}
}
