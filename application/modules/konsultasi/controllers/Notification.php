<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once '/home/idbcsnet/public_html/cilegon_bersatu/vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\MessagingException;

class Notification extends CI_Controller
{

	public function __construct()
	{
		parent::__construct();

		header('Access-Control-Allow-Origin: *'); // Izinkan permintaan dari semua domain (atau ganti * dengan domain frontend Anda)
		header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
		header('Access-Control-Allow-Headers: Content-Type, Authorization');

		$this->load->database();
	}

	public function test()
	{
		echo json_encode(['status' => 'ok']);
	}

	// public function save()
	// {
	// 	header('Content-Type: application/json');

	// 	// Debug log untuk memeriksa apakah endpoint diakses
	// 	log_message('debug', 'Endpoint savetoken dipanggil.');

	// 	$data = json_decode($this->input->raw_input_stream, true);
	// 	$nohp = $data['userId'];
	// 	$token = $data['token'];

	// 	if (!$data || !isset($data['token'])) {
	// 		// Log jika token tidak ditemukan
	// 		log_message('error', 'Token tidak ditemukan dalam request.');
	// 		echo json_encode(['status' => 'error', 'message' => 'No token provided']);
	// 		return;
	// 	}

	// 	// Simpan token ke database
	// 	$success = $this->db->replace('fcm_tokens', ['phone' => $nohp, 'token' => $token]);

	// 	if ($success) {
	// 		// Log jika berhasil
	// 		log_message('debug', 'Token berhasil disimpan.');
	// 		echo json_encode(['status' => 'success', 'message' => 'Token saved']);
	// 	} else {
	// 		// Log jika gagal menyimpan
	// 		log_message('error', 'Gagal menyimpan token.');
	// 		echo json_encode(['status' => 'error', 'message' => 'Failed to save token']);
	// 	}
	// }

	public function send()
	{
		$this->config->load('firebase');
		$serviceAccountPath = $this->config->item('firebase_service_account');
		$deviceToken = $this->config->item('firebase_device_token');

		$pesan = "Selamat siang Pasien atas nama Bayu";

		if (!$pesan) {
			log_message('error', 'Notification::send called with empty pesan');
			echo json_encode(['status' => 'error', 'message' => 'Pesan tidak boleh kosong']);
			return;
		}

		$firebase = (new Factory)
			->withServiceAccount($serviceAccountPath)
			->createMessaging();

		$message = [
			'notification' => [
				'title' => 'Hai',
				'body' => $pesan,
			],
			'token' => $deviceToken,
		];

		try {
			$firebase->send($message);
			echo json_encode(['status' => 'success', 'message' => 'Notifikasi berhasil dikirim']);
		} catch (MessagingException $e) {
			log_message('error', 'Notification::send Firebase error: ' . $e->getMessage());
			echo json_encode(['status' => 'error', 'message' => 'Gagal mengirim notifikasi']);
		}
	}

	public function service_worker()
	{
		$this->output
			->set_content_type('application/javascript')
			->set_output(file_get_contents(FCPATH . 'firebase-messaging-sw.js'));
	}
}
