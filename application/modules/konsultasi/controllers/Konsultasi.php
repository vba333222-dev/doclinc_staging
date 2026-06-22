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
		$data['puskesmas_options'] = doclinc_get_active_puskesmas();
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

	// public function save_konsultasi()
	// {
	// 	$id_user = $this->input->post('id_user');
	// 	$pahlawan = $this->input->post('dokter_id');
	// 	$keluhan = $this->input->post('keluhan');
	// 	$alamat = $this->input->post('alamat');
	// 	$lattitude = $this->input->post('lat');
	// 	$longitude = $this->input->post('lng');
	// 	$tanggal = $this->input->post('tanggal');

	// 	$data = $this->Konsultasi_m->save_konsultasi($id_user, $pahlawan, $keluhan, $alamat, $lattitude, $longitude, $tanggal);
	// 	echo json_encode($data);
	// }

	public function save_konsultasi()
	{
		$this->output->set_content_type('application/json');

		$id_user = $this->session->userdata('id');
		$role = $this->session->userdata('role');
		$pahlawan = $this->input->post('dokter_id');
		$riwayat = $this->input->post('data_penunjang');
		$keluhan = $this->input->post('keluhan');
		$alamat = $this->input->post('alamat');
		$lattitude = $this->input->post('lat');
		$longitude = $this->input->post('lng');
		$manual_puskesmas_code = $this->input->post('assigned_puskesmas_code', TRUE);
		$tanggal = $this->input->post('tanggal');

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
		$assigned_puskesmas = doclinc_find_nearest_puskesmas($lattitude, $longitude);
		if (!$assigned_puskesmas) {
			$assigned_puskesmas = doclinc_get_puskesmas_by_code($manual_puskesmas_code);
		}

		if (!$assigned_puskesmas) {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Puskesmas tujuan belum tersedia']));
			return;
		}
		$assigned_puskesmas_code = $assigned_puskesmas->kode_pkm ?? $manual_puskesmas_code;
		$assigned_puskesmas_name = $assigned_puskesmas->nama_puskesmas ?? '';
		$queue_handler_user_id = doclinc_get_queue_handler_user_id($assigned_puskesmas_code);
		if (!$queue_handler_user_id) {
			$this->output->set_status_header(403);
			doclinc_log_request_event('unauthorized_request_update', null, array('target' => 'create', 'puskesmas_code' => $assigned_puskesmas_code));
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Akun Puskesmas/Nakes belum tersedia']));
			return;
		}
		if (empty($keluhan) || empty($alamat)) {
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
			}
		}

		if (!empty($_FILES['video']['name'])) {
			$config['allowed_types'] = 'mp4|mov';
			$this->upload->initialize($config);
			if ($this->upload->do_upload('video')) {
				$video = $this->upload->data('file_name');
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
				'patient_latitude' => is_numeric($lattitude) ? $lattitude : null,
				'patient_longitude' => is_numeric($longitude) ? $longitude : null,
			)
		);
		if ($data) {
			doclinc_log_request_event('request_created', $data, array('puskesmas_code' => $assigned_puskesmas_code));
			doclinc_log_request_event('puskesmas_assigned', $data, array('puskesmas_code' => $assigned_puskesmas_code));
			$this->output->set_output(json_encode(['status' => 'success', 'message' => 'Konsultasi berhasil dikirim']));
			return;
		}

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
}
