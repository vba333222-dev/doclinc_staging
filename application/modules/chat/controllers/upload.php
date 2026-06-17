<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Upload extends CI_Controller
{

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(['form', 'url']);
	}

	public function foto()
	{
		$config['upload_path']   = './uploads/foto/';
		$config['allowed_types'] = 'jpg|jpeg|png';
		$config['max_size']      = 5120; // 5 MB
		$config['encrypt_name']  = TRUE;

		$this->load->library('upload', $config);

		if (!is_dir($config['upload_path'])) {
			mkdir($config['upload_path'], 0777, true);
		}

		if (!$this->upload->do_upload('foto')) {
			http_response_code(400);
			echo json_encode(['status' => 'error', 'message' => $this->upload->display_errors()]);
		} else {
			$data = $this->upload->data();
			echo json_encode(['status' => 'success', 'filename' => $data['file_name']]);
		}
	}

	public function video()
	{
		$config['upload_path']   = './uploads/video/';
		$config['allowed_types'] = 'mp4|mov|avi|mkv';
		$config['max_size']      = 51200; // 50 MB
		$config['encrypt_name']  = TRUE;

		$this->load->library('upload', $config);

		if (!is_dir($config['upload_path'])) {
			mkdir($config['upload_path'], 0777, true);
		}

		if (!$this->upload->do_upload('video')) {
			http_response_code(400);
			echo json_encode(['status' => 'error', 'message' => $this->upload->display_errors()]);
		} else {
			$data = $this->upload->data();
			echo json_encode(['status' => 'success', 'filename' => $data['file_name']]);
		}
	}
}
