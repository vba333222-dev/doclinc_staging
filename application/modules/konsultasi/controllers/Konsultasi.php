<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\MessagingException;

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
		$request_id = $this->input->get('reqId', TRUE);
		if (!empty($request_id) && !doclinc_can_view_request($request_id)) {
			doclinc_log_request_event('unauthorized_request_access', $request_id, array('target' => 'konsultasi_chat'));
			redirect('home');
			return;
		}

		$this->load->view('chat');
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
		$gejala_utama = $this->sanitize_gejala_keluhan_choice($this->input->post('gejala_utama', TRUE));
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
		if ($gejala_utama !== '' && stripos($keluhan, 'Gejala/Keluhan utama:') === false) {
			$keluhan = $this->build_request_description($gejala_utama, $keluhan);
		}
		if (empty($keluhan)) {
			$keluhan = $this->build_request_description($gejala_utama, '');
		}
		if (empty($keluhan)) {
			log_message('error', 'Konsultasi warga gagal: keluhan kosong. user_id=' . $id_user . ' puskesmas_code=' . $assigned_puskesmas_code);
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Masukkan keluhan.']));
			return;
		}

		$keluhan = $this->encryption->encrypt($keluhan);
		$keluhan = base64_encode($keluhan);

		$riwayat = $this->encryption->encrypt($riwayat);
		$riwayat = base64_encode($riwayat);

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
		if (!(bool) $this->config->item('firebase_enabled')) {
			redirect('home#riwayat');
			return;
		}

		$token = $this->input->get('token', TRUE);
		if (empty($token)) {
			redirect('home#riwayat');
			return;
		}

		$this->config->load('firebase');

		// $token = $this->input->post('token');
		$serviceAccountPath = $this->config->item('firebase_service_account');
		if (empty($serviceAccountPath) || !is_file($serviceAccountPath)) {
			redirect('home#riwayat');
			return;
		}
		// $deviceToken = $this->config->item('firebase_device_token');
		$deviceToken = $token;

		// Ambil pesan dari input
		$pesan = "Warga telah melakukan konsultasi, silakan cek sekarang!";

		if (empty($pesan)) {
			echo "Pesan tidak boleh kosong!";
			return;
		}

		// Buat instance Firebase
		if (!class_exists(Factory::class)) {
			log_message('error', 'Firebase dependency is missing.');
			redirect('home#riwayat');
			return;
		}

		$firebase = (new Factory)
			->withServiceAccount($serviceAccountPath)
			->createMessaging();

		// Data notifikasi
		$message = [
			'notification' => [
				'title' => 'Hai',
				'body' => $pesan,
			],
			'token' => $deviceToken, // Token perangkat tujuan
		];

		try {
			$firebase->send($message);
			header('Location: ' . base_url('home#riwayat'));
		} catch (MessagingException $e) {
			log_message('error', 'Gagal mengirim notifikasi konsultasi: ' . $e->getMessage());
			redirect('home#riwayat');
		}
	}

	public function service_worker()
	{
		$this->output
			->set_content_type('application/javascript')
			->set_output(file_get_contents(FCPATH . 'firebase-messaging-sw.js'));
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
