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
		$this->load->helper(array('visit_routing', 'visit_proof', 'request_navigation'));
		$this->load->helper('role_prerequisite');
		$this->load->helper('nakes_presence_client');
		$this->load->library('Clinical_anamnesis');
		$this->load->library('Visit_proof_service');
		$this->load->library('Medicalrecord_diagnosis_service');
		if ($this->session->userdata('logged_in') != TRUE) {
			redirect('login', 'refresh');
		}

		// Load library ApiClient
		$this->load->library('ApiClient');
	}

	public function index()
	{
		redirect(doclinc_request_return_target('dokter'));
	}

	public function konsultasi()
	{
		$x['request_id'] = $this->uri->segment(3);
		$doctor_id = (int) $this->session->userdata('id');
		$identity_context = doclinc_dokter_identity_context($doctor_id);
		$x['nakes_presence_bootstrap'] = doclinc_nakes_presence_client_bootstrap($identity_context);
		$access_context = doclinc_nakes_request_access_context($x['request_id'], $identity_context);
		if (empty($access_context['can_view'])) {
			redirect(doclinc_request_return_target('dokter'));
			return;
		}
		$data_request = $this->Konsultasi_nakes_m->get_data_request($x['request_id'], $doctor_id, $identity_context);
		$request = $data_request->row();
		if (!$request) {
			doclinc_log_request_event('unauthorized_request_access', $x['request_id'], array('target' => 'konsultasi_nakes'));
			redirect(doclinc_request_return_target('dokter'));
			return;
		}

		$x['userid'] = $request->userid;
		$x['nama_pasien'] = $request->nama;
		$x['keluhan'] = $request->request_description;
		$x['queue_code'] = doclinc_request_queue_code($request);
		$x['return_url'] = doclinc_request_return_url(
			'dokter',
			isset($request->request_status) ? $request->request_status : ''
		);
		$x['completed_return_url'] = doclinc_request_return_url('dokter', 'Completed');
		$x['tgl_lahir'] = !empty($request->tgl) ? $request->tgl : null;
		$x['umur'] = '-';
		$x['care_team_workflow_enabled'] = $this->config->item('care_team_workflow_enabled') === true;
		$x['kriteria'] = $x['care_team_workflow_enabled']
			? ((string) ($request->consultation_mode ?? '') === 'visit'
				? 'Kunjungan Nakes'
				: ((string) ($request->consultation_mode ?? '') === 'non_visit' ? 'Selesai Konsultasi' : ''))
			: '';
		$x['can_handle_request'] = $x['care_team_workflow_enabled']
			? !empty($access_context['can_assess'])
			: !empty($access_context['can_handle']);
		$x['can_open_patient_chat'] = !empty($identity_context['valid'])
			&& (string) ($identity_context['account_type'] ?? '') === 'personal'
			&& doclinc_can_view_chat((int) $x['request_id'], $doctor_id, 'dokter');
		$x['visit_proof_required'] = doclinc_visit_proof_required();
		$x['additional_diagnoses_enabled'] = (bool) $this->config->item('additional_diagnoses_enabled');
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
		if ($this->input->method(TRUE) !== 'GET') {
			$this->output->set_status_header(405)->set_content_type('application/json')->set_output(json_encode([]));
			return;
		}
		if ($this->session->userdata('role') !== 'dokter') {
			$this->output->set_status_header(403)->set_content_type('application/json')->set_output(json_encode([]));
			return;
		}
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
		show_404();
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
		$write_additional_diagnoses = (bool) $this->config->item('additional_diagnoses_enabled');
		$additional_diagnoses = $this->input->post('diagnosa_tambahan', false);
		$normalized_diagnoses = Medicalrecord_diagnosis_service::normalize(
			$diagnosa,
			$additional_diagnoses === null ? array() : $additional_diagnoses,
			$write_additional_diagnoses
		);
		$saran = $this->input->post('saran');
		$kriteria = $this->input->post('kriteria');
		$rujukan = $this->input->post('rujukan');
		$terapi = json_decode($this->input->post('terapi'), true);
		$terapi = is_array($terapi) ? $terapi : [];
		$foto = null;
		$visit_proof_context = null;
		$visit_proof_required = doclinc_visit_proof_required();
		$is_visit_selection = doclinc_visit_proof_is_visit($kriteria);
		$is_non_visit_selection = in_array(strtolower(trim((string) $kriteria)), array('0', 'selesai konsultasi'), true);
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
		if (empty($normalized_diagnoses['valid'])) {
			$diagnosis_messages = array(
				'primary_required' => 'Masukkan diagnosis utama.',
				'too_many_diagnoses' => 'Maksimal tiga diagnosis dapat dicatat.',
				'duplicate_diagnosis' => 'Diagnosis yang sama tidak boleh dicatat dua kali.',
				'diagnosis_too_long' => 'Setiap diagnosis maksimal 255 karakter.',
			);
			$message = isset($diagnosis_messages[$normalized_diagnoses['error']])
				? $diagnosis_messages[$normalized_diagnoses['error']]
				: 'Format diagnosis tidak valid.';
			$this->output->set_status_header(422)->set_output(json_encode(array('status' => 'error', 'message' => $message)));
			return;
		}
		$diagnosa = $normalized_diagnoses['diagnoses'][0];
		if ($visit_proof_required && !$is_visit_selection && !$is_non_visit_selection) {
			$this->output
				->set_status_header(422)
				->set_output(json_encode(['status' => 'error', 'message' => 'Jenis layanan tidak valid. Pilih konsultasi jarak jauh atau Kunjungan Nakes.']));
			return;
		}
		$access_context = doclinc_nakes_request_access_context($request_id, $identity_context);
		$can_assess = $this->config->item('care_team_workflow_enabled') === true
			? !empty($access_context['can_assess'])
			: !empty($access_context['can_handle']);
		if (!$can_assess) {
			$this->output->set_status_header(403);
			doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'complete'));
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses.']));
			return;
		}
		$prerequisite_state = doclinc_role_prerequisite_state($doctor_id, true);
		if (empty($prerequisite_state['allowed'])) {
			$this->output
				->set_status_header(doclinc_role_prerequisite_http_status($prerequisite_state))
				->set_output(json_encode(doclinc_role_prerequisite_error_payload($prerequisite_state)));
			return;
		}
		if ($write_additional_diagnoses && !Medicalrecord_diagnosis_service::schemaReady($this->db)) {
			$this->output
				->set_status_header(503)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Penyimpanan diagnosis tambahan belum siap. Hubungi administrator.')));
			return;
		}
		$request_before_completion = doclinc_request_row($request_id);
		$persisted_visit = doclinc_visit_proof_persisted_visit($request_before_completion);
		if ($visit_proof_required && $persisted_visit && !$is_visit_selection) {
			$this->output
				->set_status_header(422)
				->set_output(json_encode(['status' => 'error', 'message' => 'Kunjungan yang sudah dimulai tidak dapat ditutup sebagai konsultasi non-visit.']));
			return;
		}
		$is_visit_completion = $is_visit_selection || $persisted_visit;
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

		if ($visit_proof_required && $is_visit_completion) {
			if (empty($_FILES['file']['name']) || !isset($_FILES['file']['error']) || (int) $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
				$this->output
					->set_status_header(422)
					->set_output(json_encode(['status' => 'error', 'message' => 'Foto bukti kunjungan wajib diambil sebelum konsultasi diselesaikan.']));
				return;
			}
			$location = doclinc_visit_proof_location_input(
				$this->input->post('proof_latitude'),
				$this->input->post('proof_longitude'),
				$this->input->post('proof_accuracy_m'),
				$this->input->post('proof_captured_at_ms')
			);
			if (empty($location['valid'])) {
				$this->output
					->set_status_header(422)
					->set_output(json_encode(['status' => 'error', 'message' => $this->visit_proof_location_error_message($location['reason'] ?? '')]));
				return;
			}
			if (!$this->visit_proof_service->schemaReady() || !$this->visit_proof_service->ensureStorageReady()) {
				$this->output
					->set_status_header(503)
					->set_output(json_encode(['status' => 'error', 'message' => 'Penyimpanan bukti kunjungan belum siap. Hubungi administrator.']));
				return;
			}
			$storage_key = $this->visit_proof_service->newStorageKey();
			$config = $this->visit_proof_service->uploadConfig($storage_key);
			if ($storage_key === '' || $config === false) {
				$this->output
					->set_status_header(503)
					->set_output(json_encode(['status' => 'error', 'message' => 'Bukti kunjungan belum dapat disiapkan. Coba lagi.']));
				return;
			}
			$this->load->library('upload');
			$this->upload->initialize($config, true);
			if (!$this->upload->do_upload('file')) {
				$this->output
					->set_status_header(400)
					->set_output(json_encode(['status' => 'error', 'message' => $this->result_upload_error_message(true)]));
				return;
			}
			$uploaded = $this->upload->data();
			$visit_proof_context = $this->visit_proof_service->stageUploadedImage($request_id, $doctor_id, $uploaded, $storage_key);
			if (empty($visit_proof_context['success'])) {
				$this->output
					->set_status_header(400)
					->set_output(json_encode(['status' => 'error', 'message' => $this->result_upload_error_message(true)]));
				return;
			}
			$visit_proof_context['location'] = $location;
			$foto = $visit_proof_context['file_name'];
		} elseif ($visit_proof_required && !$is_visit_completion && !empty($_FILES['file']['name'])) {
			$this->output
				->set_status_header(422)
				->set_output(json_encode(['status' => 'error', 'message' => 'Foto bukti hanya digunakan untuk Kunjungan Nakes.']));
			return;
		} elseif (!empty($_FILES['file']['name'])) {
			$config = array(
				'upload_path' => './uploads/',
				'allowed_types' => 'jpg|jpeg|png',
				'max_size' => 5120,
				'encrypt_name' => true,
				'detect_mime' => true,
				'mod_mime_fix' => true,
				'remove_spaces' => true,
			);
			$this->load->library('upload');
			$this->upload->initialize($config, true);
			if (!$this->upload->do_upload('file')) {
				$this->output
					->set_status_header(400)
					->set_output(json_encode(['status' => 'error', 'message' => $this->result_upload_error_message()]));
				return;
			}
			$uploaded = $this->upload->data();
			$foto = $uploaded['file_name'];
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
			$write_anamnesis,
			$visit_proof_context,
			$visit_proof_required,
			$normalized_diagnoses['diagnoses'],
			$write_additional_diagnoses
		);

		if ($result) {
			doclinc_log_request_event('consultation_completed', $request_id);
			$request = doclinc_request_row($request_id);
			$event_metadata = array(
				'request_status' => $request && isset($request->request_status) ? $request->request_status : 'Completed',
			);
			if (is_array($visit_proof_context) && !empty($visit_proof_context['media_id'])) {
				$event_metadata['visit_proof_media_id'] = (int) $visit_proof_context['media_id'];
			}
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
		if (is_array($visit_proof_context)) {
			$this->visit_proof_service->failStaged(
				$visit_proof_context,
				method_exists($this->Konsultasi_nakes_m, 'last_failure_code')
					? $this->Konsultasi_nakes_m->last_failure_code()
					: 'completion_failed'
			);
		} elseif ($foto !== null && $foto !== '') {
			$legacy_path = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($foto);
			if (is_file($legacy_path)) {
				@unlink($legacy_path);
			}
		}

		$this->output->set_output(json_encode(['status' => 'error', 'message' => $this->completion_failure_message()]));
	}


	public function getICD_json()
	{
		show_404();
	}

	private function result_upload_error_message($visit_proof = false)
	{
		return $visit_proof
			? 'Foto bukti tidak valid. Gunakan JPG/JPEG dengan ukuran maksimal 5 MB.'
			: 'File hasil konsultasi tidak valid. Gunakan JPG/PNG dengan ukuran maksimal 5 MB.';
	}

	private function visit_proof_location_error_message($reason)
	{
		if ($reason === 'low_accuracy') {
			return 'Akurasi GPS belum cukup baik. Tunggu sebentar lalu coba lagi.';
		}
		if ($reason === 'stale_location') {
			return 'Lokasi sudah kedaluwarsa. Ambil lokasi ulang lalu coba lagi.';
		}
		return 'Lokasi Nakes wajib tersedia dan valid untuk bukti kunjungan.';
	}

	private function completion_failure_message()
	{
		$code = method_exists($this->Konsultasi_nakes_m, 'last_failure_code')
			? $this->Konsultasi_nakes_m->last_failure_code()
			: '';
		$messages = array(
			'visit_mode_invalid' => 'Jenis layanan tidak valid. Pilih konsultasi jarak jauh atau Kunjungan Nakes.',
			'visit_mode_mismatch' => 'Kunjungan yang sudah dimulai tidak dapat ditutup sebagai konsultasi non-visit.',
			'visit_status_invalid' => 'Status kunjungan harus sudah tiba atau dalam penanganan.',
			'visit_location_outside_radius' => 'Lokasi Nakes belum berada dalam radius pasien.',
			'visit_proof_missing' => 'Foto bukti kunjungan wajib tersedia.',
			'visit_proof_invalid' => 'Bukti kunjungan tidak valid atau bukan milik konsultasi ini.',
			'visit_proof_schema_unavailable' => 'Penyimpanan bukti kunjungan belum siap. Hubungi administrator.',
			'diagnosis_schema_unavailable' => 'Penyimpanan diagnosis tambahan belum siap. Hubungi administrator.',
			'diagnosis_payload_invalid' => 'Format diagnosis tidak valid.',
			'diagnosis_write_failed' => 'Diagnosis belum dapat disimpan. Coba lagi.',
		);
		return isset($messages[$code]) ? $messages[$code] : 'Konsultasi belum dapat diselesaikan.';
	}
}
