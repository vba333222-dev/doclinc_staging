<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Konsultasi extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Konsultasi_m');
		$this->load->helper('request_authz');
		$this->load->helper('puskesmas_routing');
		$this->load->helper('notification');
		$this->load->helper('request_realtime');
		$this->load->helper('request_event');
		$this->load->helper('role_prerequisite');
		if ($this->session->userdata('logged_in') != TRUE) {
			redirect('login', 'refresh');
		}
		$this->load->library('encryption');
	}

	public function index()
	{
		$userid = $this->session->userdata('id');
		$nama = $_GET['nama'] ?? '';
		$data['getDataDoctor'] = $this->Konsultasi_m->getDataDoctor($nama);
		$data['getFotoDokter'] = $this->Konsultasi_m->getFotoDokter($nama);
		$data['getDataTokenDoctor'] = $this->Konsultasi_m->getDataTokenDoctor($nama);
		$data['getDataPenunjangById'] = $this->Konsultasi_m->getDataPenunjangById($userid);
		$data['master_gejala_keluhan_options'] = $this->Konsultasi_m->get_master_gejala_keluhan_options();
		if (doclinc_active_consultation_request($userid)) {
			redirect('home#riwayat');
			return;
		}
		$this->load->view('konsultasi_v', $data);
	}

	public function chat()
	{
		show_404();
	}

	public function save_konsultasi()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']));
			return;
		}

		$id_user = $this->session->userdata('id');
		$role = $this->session->userdata('role');
		$riwayat = $this->input->post('data_penunjang');
		// The dropdown offers "Tulis sendiri…" for complaints that are not in the
		// master list; it posts a sentinel rather than free text. Resolve it here so
		// the stored description never carries the marker itself, and so choosing it
		// without typing anything yields no chief complaint rather than a literal
		// "__custom__" written into the patient's record.
		$gejala_utama = $this->sanitize_gejala_keluhan_choice($this->input->post('gejala_utama', TRUE));
		if ($gejala_utama === '__custom__') {
			$gejala_utama = $this->sanitize_gejala_keluhan_choice($this->input->post('gejala_utama_custom', TRUE));
		}
		$keluhan = trim((string) $this->input->post('keluhan', TRUE));
		$alamat = $this->input->post('alamat');
		$lattitude = $this->input->post('lat');
		$longitude = $this->input->post('lng');
		$tanggal = $this->input->post('tanggal');
		$has_missing_patient_location = trim((string) $lattitude) === '' || trim((string) $longitude) === '';
		$has_patient_location = $this->is_valid_latitude($lattitude) && $this->is_valid_longitude($longitude);
		$patient_latitude = $has_patient_location ? (float) $lattitude : null;
		$patient_longitude = $has_patient_location ? (float) $longitude : null;
		$lattitude = $has_patient_location ? (string) $patient_latitude : '';
		$longitude = $has_patient_location ? (string) $patient_longitude : '';

		$foto = '';
		$video = '';

		if (empty($id_user)) {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Silakan masuk terlebih dahulu.']));
			return;
		}
		if ($role !== 'warga') {
			$this->output->set_status_header(403);
			doclinc_log_request_event('unauthorized_request_update', null, array('target' => 'create', 'role' => $role));
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses.']));
			return;
		}
		$prerequisite_state = doclinc_role_prerequisite_state((int) $id_user, true);
		if (empty($prerequisite_state['allowed'])) {
			$this->output
				->set_status_header(doclinc_role_prerequisite_http_status($prerequisite_state))
				->set_output(json_encode(doclinc_role_prerequisite_error_payload($prerequisite_state)));
			return;
		}
		if (doclinc_active_consultation_request($id_user)) {
			$this->output->set_status_header(409);
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Selesaikan atau batalkan konsultasi aktif terlebih dahulu.']));
			return;
		}
		if ($this->has_supplied_request_media()) {
			$this->output
				->set_status_header(422)
				->set_output(json_encode([
					'status' => 'error',
					'message' => 'Lampiran tidak didukung pada permintaan konsultasi.',
				]));
			return;
		}
		if (!$has_patient_location) {
			$location_message = $has_missing_patient_location
				? 'Pilih lokasi terlebih dahulu.'
				: 'Lokasi yang dipilih tidak valid.';
			$this->output->set_output(json_encode(['status' => 'error', 'message' => $location_message]));
			return;
		}

		$assigned_puskesmas = doclinc_find_puskesmas_by_service_area($patient_latitude, $patient_longitude);
		if (!$assigned_puskesmas) {
			log_message('error', 'Konsultasi warga gagal: lokasi tidak masuk area layanan.');
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Lokasi di luar area layanan.']));
			return;
		}
		$assigned_puskesmas_code = trim((string) ($assigned_puskesmas->kode_pkm ?? ''));
		$assigned_puskesmas_name = trim((string) ($assigned_puskesmas->nama_puskesmas ?? ''));
		if ($assigned_puskesmas_code === '' || strtoupper($assigned_puskesmas_code) === 'DEFAULT') {
			log_message('error', 'Konsultasi warga gagal: kode puskesmas tujuan tidak valid.');
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Puskesmas tujuan belum tersedia.']));
			return;
		}
		$queue_handler_user_id = doclinc_get_queue_handler_user_id($assigned_puskesmas_code);
		if (!$queue_handler_user_id) {
			$this->output->set_status_header(503);
			log_message('error', 'Konsultasi warga gagal: akun puskesmas aktif tidak ditemukan. puskesmas_code=' . $assigned_puskesmas_code);
			doclinc_log_request_event('unauthorized_request_update', null, array('target' => 'create', 'puskesmas_code' => $assigned_puskesmas_code));
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Puskesmas belum dapat menerima konsultasi.']));
			return;
		}
		if (empty($alamat)) {
			$alamat = !empty($assigned_puskesmas_name)
				? 'Puskesmas tujuan: ' . $assigned_puskesmas_name
				: 'Alamat belum tersedia';
		}
		// Assemble the description unconditionally so every request carries the same
		// structure, whichever fields the patient filled.
		//
		// The previous form only assembled when a dropdown choice was present. A
		// patient who typed a detail but left the dropdown empty - which is the
		// only thing possible while the keluhan master table is empty - had that
		// detail stored as bare text: no Anamnesa header, no "Detail keluhan:"
		// label. The receiving nakes could not tell a structured chief complaint
		// from free text, and the "Gejala/Keluhan utama:" marker that this code
		// itself looks for when a request is later edited was absent, so an edit
		// composed the description differently from the original submission.
		$keluhan = $this->build_request_description($gejala_utama, $keluhan);
		if (empty($keluhan)) {
			log_message('error', 'Konsultasi warga gagal: keluhan kosong. user_id=' . $id_user . ' puskesmas_code=' . $assigned_puskesmas_code);
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Masukkan keluhan.']));
			return;
		}

		// CI_Encryption::encrypt() returns FALSE when the configured key is empty
		// or unusable, which is what happens when DOCLINC_ENCRYPTION_KEY is unset.
		// The create path used to ignore that: base64_encode(false) is the empty
		// string, so the request was saved with no complaint at all. The patient
		// saw a successful submission, the puskesmas saw a blank description, and
		// nothing appeared in the logs to connect the two. Refuse the request and
		// say so instead of losing the complaint silently.
		$encrypted_keluhan = $this->encryption->encrypt($keluhan);
		if ($encrypted_keluhan === false) {
			log_message('error', 'Konsultasi warga gagal: enkripsi keluhan mengembalikan false, periksa DOCLINC_ENCRYPTION_KEY. user_id=' . $id_user);
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Keluhan belum dapat disimpan. Hubungi petugas.']));
			return;
		}
		$keluhan = base64_encode($encrypted_keluhan);

		$encrypted_riwayat = $this->encryption->encrypt($riwayat);
		if ($encrypted_riwayat === false) {
			log_message('error', 'Konsultasi warga gagal: enkripsi riwayat mengembalikan false, periksa DOCLINC_ENCRYPTION_KEY. user_id=' . $id_user);
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Keluhan belum dapat disimpan. Hubungi petugas.']));
			return;
		}
		$riwayat = base64_encode($encrypted_riwayat);

		// Simpan data ke model
		$data = $this->Konsultasi_m->save_konsultasi(
			$id_user,
			$queue_handler_user_id,
			$riwayat,
			$keluhan,
			$alamat,
			$lattitude,
			$longitude,
			$tanggal,
			$foto,
			$video,
			array(
				'assigned_puskesmas_code' => $assigned_puskesmas_code,
				'assigned_puskesmas_name' => $assigned_puskesmas_name,
				'patient_latitude' => $patient_latitude,
				'patient_longitude' => $patient_longitude,
			)
		);
		if ($data) {
			$created_request = doclinc_request_row($data);
			$event_metadata = array(
				'assigned_puskesmas_code' => $assigned_puskesmas_code,
				'assigned_puskesmas_name' => $assigned_puskesmas_name,
				'request_status' => $created_request && isset($created_request->request_status) ? $created_request->request_status : 'Pending',
				'patient_latitude' => $patient_latitude,
				'patient_longitude' => $patient_longitude,
				'has_location' => $has_patient_location,
				'has_attachment' => ($foto !== '' || $video !== ''),
				'created_from' => 'warga',
			);
			foreach (array('queue_code', 'queue_number', 'consultation_mode') as $event_field) {
				if ($created_request && isset($created_request->{$event_field}) && $created_request->{$event_field} !== null && $created_request->{$event_field} !== '') {
					$event_metadata[$event_field] = $created_request->{$event_field};
				}
			}
			doclinc_append_request_event($data, 'request_created', array(
				'puskesmas_code' => $assigned_puskesmas_code,
				'actor_user_id' => $id_user,
				'actor_role' => 'warga',
				'message' => 'Permintaan konsultasi dibuat oleh warga.',
				'metadata' => $event_metadata,
				'deduplicate' => true,
			), $this);
			doclinc_log_request_event('request_created', $data, array('puskesmas_code' => $assigned_puskesmas_code));
			doclinc_log_request_event('puskesmas_assigned', $data, array('puskesmas_code' => $assigned_puskesmas_code));
			$orchestration = doclinc_request_transition_orchestrator()->requestCreated(
				$data,
				$created_request,
				$id_user
			);
			$this->output->set_output(json_encode($orchestration['response']));
			return;
		}

		log_message('error', 'Konsultasi warga gagal insert request. user_id=' . $id_user . ' puskesmas_code=' . $assigned_puskesmas_code . ' handler_user_id=' . $queue_handler_user_id);
		$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Permintaan belum dapat dikirim.']));
	}


	public function send()
	{
		show_404();
	}

	public function service_worker()
	{
		show_404();
	}

	private function is_valid_latitude($value)
	{
		return is_numeric($value) && (float) $value >= -90 && (float) $value <= 90;
	}

	private function sanitize_gejala_keluhan_choice($value)
	{
		$value = trim(strip_tags((string) $value));
		$value = preg_replace('/\s+/u', ' ', $value);
		if (function_exists('mb_substr')) {
			return mb_substr($value, 0, 120, 'UTF-8');
		}

		return substr($value, 0, 120);
	}

	private function build_request_description($gejala_utama, $detail_keluhan)
	{
		$parts = array('Anamnesa');
		$gejala_utama = $this->sanitize_gejala_keluhan_choice($gejala_utama);
		$detail_keluhan = trim(strip_tags((string) $detail_keluhan));
		if ($gejala_utama !== '') {
			$parts[] = 'Gejala/Keluhan utama: ' . $gejala_utama;
		}
		if ($detail_keluhan !== '') {
			$parts[] = 'Detail keluhan: ' . $detail_keluhan;
		}

		return count($parts) > 1 ? implode("\n", $parts) : '';
	}

	private function is_valid_longitude($value)
	{
		return is_numeric($value) && (float) $value >= -180 && (float) $value <= 180;
	}

	private function has_supplied_request_media()
	{
		foreach (array('foto', 'video') as $field) {
			if (!array_key_exists($field, $_FILES)) {
				continue;
			}

			$upload = $_FILES[$field];
			if (!is_array($upload) || !array_key_exists('error', $upload)) {
				return true;
			}

			$error = $upload['error'];
			if (is_array($error)) {
				return true;
			}
			if ($error !== UPLOAD_ERR_NO_FILE && $error !== (string) UPLOAD_ERR_NO_FILE) {
				return true;
			}
		}

		return false;
	}
}
