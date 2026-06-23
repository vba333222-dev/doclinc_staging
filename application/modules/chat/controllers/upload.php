<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Upload extends CI_Controller
{

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(['form', 'url']);
		$this->load->helper('request_authz');
		if ($this->session->userdata('logged_in') != TRUE) {
			$this->output
				->set_content_type('application/json')
				->set_status_header(401)
				->set_output(json_encode(['status' => 'error', 'message' => 'Login diperlukan']));
			return;
		}
	}

	public function foto()
	{
		if (!$this->authorize_upload_request()) {
			return;
		}

		$config['upload_path']   = './uploads/foto/';
		$config['allowed_types'] = 'jpg|jpeg|png';
		$config['max_size']      = 5120; // 5 MB
		$config['encrypt_name']  = TRUE;
		$config['detect_mime']   = TRUE;
		$config['mod_mime_fix']  = TRUE;
		$config['remove_spaces'] = TRUE;

		$this->load->library('upload', $config);

		if (!is_dir($config['upload_path'])) {
			mkdir($config['upload_path'], 0755, true);
		}

		if (!$this->upload->do_upload('foto')) {
			$this->output
				->set_status_header(400)
				->set_output(json_encode(['status' => 'error', 'message' => strip_tags($this->upload->display_errors())]));
		} else {
			$data = $this->upload->data();
			$this->output->set_output(json_encode(['status' => 'success', 'filename' => $data['file_name']]));
		}
	}

	public function video()
	{
		if (!$this->authorize_upload_request()) {
			return;
		}

		$config['upload_path']   = './uploads/video/';
		$config['allowed_types'] = 'mp4|mov|avi|mkv';
		$config['max_size']      = 51200; // 50 MB
		$config['encrypt_name']  = TRUE;
		$config['detect_mime']   = TRUE;
		$config['mod_mime_fix']  = TRUE;
		$config['remove_spaces'] = TRUE;

		$this->load->library('upload', $config);

		if (!is_dir($config['upload_path'])) {
			mkdir($config['upload_path'], 0755, true);
		}

		if (!$this->upload->do_upload('video')) {
			$this->output
				->set_status_header(400)
				->set_output(json_encode(['status' => 'error', 'message' => strip_tags($this->upload->display_errors())]));
		} else {
			$data = $this->upload->data();
			$this->output->set_output(json_encode(['status' => 'success', 'filename' => $data['file_name']]));
		}
	}

	private function authorize_upload_request()
	{
		$this->output->set_content_type('application/json');
		if ($this->session->userdata('logged_in') != TRUE) {
			$this->output
				->set_status_header(401)
				->set_output(json_encode(['status' => 'error', 'message' => 'Login diperlukan']));
			return false;
		}

		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']));
			return false;
		}

		$request_id = (int) $this->input->post('request_id');
		if ($request_id < 1 || !doclinc_can_send_chat($request_id)) {
			doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'legacy_chat_upload'));
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return false;
		}

		return true;
	}
}
