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
		$keluhan = $this->input->post('keluhan');
		$alamat = $this->input->post('alamat');
		$lattitude = $this->input->post('lat');
		$longitude = $this->input->post('lng');
		$tanggal = $this->input->post('tanggal');
		$has_patient_location = $this->is_valid_latitude($lattitude) && $this->is_valid_longitude($longitude);
		$patient_latitude = $has_patient_location ? (float) $lattitude : null;
		$patient_longitude = $has_patient_location ? (float) $longitude : null;
		$lattitude = $has_patient_location ? (string) $patient_latitude : '';
		$longitude = $has_patient_location ? (string) $patient_longitude : '';

		$foto = '';
		$video = '';

		if (empty($id_user)) {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Session pengguna tidak ditemukan']));
			return;
		}
		if ($role !== 'warga') {
			$this->output->set_status_header(403);
			doclinc_log_request_event('unauthorized_request_update', null, array('target' => 'create', 'role' => $role));
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}
		if (doclinc_active_consultation_request($id_user)) {
			$this->output->set_status_header(409);
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Anda masih memiliki konsultasi aktif. Selesaikan atau batalkan konsultasi tersebut sebelum membuat permintaan baru.']));
			return;
		}
		if (!$has_patient_location) {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Lokasi pasien belum tersedia. Aktifkan izin lokasi lalu coba lagi.']));
			return;
		}

		$assigned_puskesmas = doclinc_find_puskesmas_by_service_area($patient_latitude, $patient_longitude);
		if (!$assigned_puskesmas) {
			log_message('error', 'Konsultasi warga gagal: lokasi tidak masuk area layanan.');
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Lokasi Anda belum masuk area layanan puskesmas aktif.']));
			return;
		}
		$assigned_puskesmas_code = trim((string) ($assigned_puskesmas->kode_pkm ?? ''));
		$assigned_puskesmas_name = trim((string) ($assigned_puskesmas->nama_puskesmas ?? ''));
		if ($assigned_puskesmas_code === '' || strtoupper($assigned_puskesmas_code) === 'DEFAULT') {
			log_message('error', 'Konsultasi warga gagal: kode puskesmas tujuan tidak valid.');
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Puskesmas tujuan belum dapat ditentukan dari lokasi Anda.']));
			return;
		}
		$queue_handler_user_id = doclinc_get_queue_handler_user_id($assigned_puskesmas_code);
		if (!$queue_handler_user_id) {
			$this->output->set_status_header(503);
			log_message('error', 'Konsultasi warga gagal: akun puskesmas aktif tidak ditemukan. puskesmas_code=' . $assigned_puskesmas_code);
			doclinc_log_request_event('unauthorized_request_update', null, array('target' => 'create', 'puskesmas_code' => $assigned_puskesmas_code));
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Puskesmas tujuan belum siap menerima konsultasi. Silakan coba lagi nanti.']));
			return;
		}
		if (empty($alamat)) {
			$alamat = !empty($assigned_puskesmas_name)
				? 'Puskesmas tujuan: ' . $assigned_puskesmas_name
				: 'Alamat belum tersedia';
		}
		if (empty($keluhan)) {
			log_message('error', 'Konsultasi warga gagal: keluhan kosong. user_id=' . $id_user . ' puskesmas_code=' . $assigned_puskesmas_code);
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Data konsultasi belum lengkap']));
			return;
		}

		$config['upload_path'] = './uploads/';
		$config['max_size'] = 10240; // 10MB
		$config['encrypt_name'] = TRUE;
		$config['detect_mime'] = TRUE;
		$config['mod_mime_fix'] = TRUE;
		$config['remove_spaces'] = TRUE;
		$this->load->library('upload');

		if (!empty($_FILES['foto']['name'])) {
			$config['allowed_types'] = 'jpg|jpeg|png';
			$this->upload->initialize($config);
			if ($this->upload->do_upload('foto')) {
				$foto = $this->upload->data('file_name');
			} else {
				$this->output
					->set_status_header(400)
					->set_output(json_encode(array(
						'status' => 'error',
						'message' => $this->consultation_upload_error_message('foto'),
					)));
				return;
			}
		}

		if (!empty($_FILES['video']['name'])) {
			$config['allowed_types'] = 'mp4|mov';
			$this->upload->initialize($config);
			if ($this->upload->do_upload('video')) {
				$video = $this->upload->data('file_name');
			} else {
				$this->output
					->set_status_header(400)
					->set_output(json_encode(array(
						'status' => 'error',
						'message' => $this->consultation_upload_error_message('video'),
					)));
				return;
			}
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
			doclinc_notify_puskesmas(
				$assigned_puskesmas_code,
				'request_created',
				'request',
				$data,
				'Permintaan konsultasi baru',
				'Ada permintaan konsultasi baru untuk Puskesmas ' . $assigned_puskesmas_name,
				$id_user
			);
			$this->output->set_output(json_encode(['status' => 'success', 'message' => 'Konsultasi berhasil dikirim']));
			return;
		}

		log_message('error', 'Konsultasi warga gagal insert request. user_id=' . $id_user . ' puskesmas_code=' . $assigned_puskesmas_code . ' handler_user_id=' . $queue_handler_user_id);
		$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Konsultasi gagal dikirim']));
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

	private function is_valid_longitude($value)
	{
		return is_numeric($value) && (float) $value >= -180 && (float) $value <= 180;
	}

	private function consultation_upload_error_message($field)
	{
		if ($field === 'video') {
			return 'File lampiran tidak valid. Gunakan MP4/MOV untuk video dengan ukuran maksimal 10 MB.';
		}

		return 'File lampiran tidak valid. Gunakan JPG/PNG untuk foto dengan ukuran maksimal 10 MB.';
	}
}
