<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Chat extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper('request_authz');
		if ($this->session->userdata('logged_in') != TRUE) {
			redirect('login');
		}
	}

	public function index()
	{
		$request_id = $this->input->get('reqId', TRUE);
		if (!empty($request_id) && !doclinc_can_view_request($request_id)) {
			doclinc_log_request_event('unauthorized_request_access', $request_id, array('target' => 'chat'));
			$role = doclinc_current_user_role();
			redirect($role === 'dokter' ? 'home_nakes' : 'home');
			return;
		}

		$this->load->view('chat');
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
			$file_url = base_url('uploads/foto/' . $data['file_name']); // tambahkan ini
			$this->output->set_output(json_encode([
				'status' => 'success',
				'filename' => $data['file_name'],
				'file_url' => $file_url // kirim ke frontend
			]));
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
			$file_url = base_url('uploads/video/' . $data['file_name']); // tambahkan ini
			$this->output->set_output(json_encode([
				'status' => 'success',
				'filename' => $data['file_name'],
				'file_url' => $file_url // kirim ke frontend
			]));
		}
	}

	private function authorize_upload_request()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']));
			return false;
		}

		$request_id = (int) $this->input->post('request_id');
		if ($request_id < 1 || !doclinc_can_view_request($request_id)) {
			doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'chat_upload'));
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return false;
		}

		return true;
	}
}
