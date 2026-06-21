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
		$this->load->view('konsultasi_v', $data);
	}

	public function chat()
	{
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
		$pahlawan = $this->input->post('dokter_id');
		$riwayat = $this->input->post('data_penunjang');
		$keluhan = $this->input->post('keluhan');
		$alamat = $this->input->post('alamat');
		$lattitude = $this->input->post('lat');
		$longitude = $this->input->post('lng');
		$tanggal = $this->input->post('tanggal');

		$foto = '';
		$video = '';

		if (empty($id_user)) {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Session pengguna tidak ditemukan']));
			return;
		}
		if (empty($pahlawan)) {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Dokter belum dipilih']));
			return;
		}
		if (empty($keluhan) || empty($alamat)) {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Data konsultasi belum lengkap']));
			return;
		}

		$config['upload_path'] = './uploads/';
		$config['allowed_types'] = 'jpg|jpeg|png|mp4|mov';
		$config['max_size'] = 10240; // 10MB
		$this->load->library('upload');

		if (!empty($_FILES['foto']['name'])) {
			$config['file_name'] = 'foto_' . time();
			$this->upload->initialize($config);
			if ($this->upload->do_upload('foto')) {
				$foto = $this->upload->data('file_name');
			}
		}

		if (!empty($_FILES['video']['name'])) {
			$config['file_name'] = 'video_' . time();
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
		$data = $this->Konsultasi_m->save_konsultasi($id_user, $pahlawan, $riwayat, $keluhan, $alamat, $lattitude, $longitude, $tanggal, $foto, $video);
		if ($data) {
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
