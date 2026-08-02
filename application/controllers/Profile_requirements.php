<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Profile_requirements extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library('session');
		$this->load->helper(array('request_authz', 'role_prerequisite'));
	}

	public function status()
	{
		$this->output
			->set_content_type('application/json')
			->set_header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
		if ($this->input->method(true) !== 'GET') {
			$this->output->set_status_header(405)->set_output(json_encode(array('success' => false, 'safe_error_code' => 'method_not_allowed')));
			return;
		}
		if ($this->session->userdata('logged_in') != true || !in_array((string) $this->session->userdata('role'), array('warga', 'dokter'), true)) {
			$this->output->set_status_header(401)->set_output(json_encode(array('success' => false, 'safe_error_code' => 'session_not_allowed')));
			return;
		}

		$state = doclinc_role_prerequisite_state((int) $this->session->userdata('id'), true);
		$this->output->set_output(json_encode(array('success' => true, 'data' => $state)));
	}
}
