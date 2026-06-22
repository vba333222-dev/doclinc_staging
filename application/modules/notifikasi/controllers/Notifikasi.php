<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Notifikasi extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper('notification');
	}

	public function index()
	{
		$this->list_json();
	}

	public function list_json()
	{
		$this->output->set_content_type('application/json');
		if ($this->session->userdata('logged_in') != TRUE) {
			$this->output
				->set_status_header(401)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Login diperlukan')));
			return;
		}

		$user_id = (int) $this->session->userdata('id');
		$limit = (int) $this->input->get('limit', TRUE);
		$limit = $limit > 0 ? $limit : 20;

		$this->output->set_output(json_encode(array(
			'status' => 'success',
			'unread_count' => doclinc_count_unread_notifications($user_id),
			'notifications' => doclinc_get_unread_notifications($user_id, $limit),
		)));
	}

	public function mark_read()
	{
		$this->output->set_content_type('application/json');
		if ($this->session->userdata('logged_in') != TRUE) {
			$this->output
				->set_status_header(401)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Login diperlukan')));
			return;
		}

		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan')));
			return;
		}

		$notification_id = (int) $this->input->post('notification_id');
		$user_id = (int) $this->session->userdata('id');
		if (!doclinc_mark_notification_read($notification_id, $user_id)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$this->output->set_output(json_encode(array('status' => 'success')));
	}
}
