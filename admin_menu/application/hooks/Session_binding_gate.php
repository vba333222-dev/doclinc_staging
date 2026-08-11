<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Session_binding_gate
{
	public function enforce()
	{
		if (PHP_SAPI === 'cli') {
			return;
		}
		$CI = &get_instance();
		if ($CI->config->item('single_active_session_enabled') !== true
			|| $CI->session->userdata('is_login') != true) {
			return;
		}
		$service_file = dirname(APPPATH, 2) . '/application/libraries/Session_binding_service.php';
		if (!is_file($service_file)) {
			$this->deny($CI);
		}
		require_once $service_file;
		$service = new Session_binding_service($CI->db);
		if ($service->validate(
			(int) $CI->session->userdata('id'),
			$CI->session->userdata('normal_session_token')
		)) {
			return;
		}
		$this->deny($CI);
	}

	private function deny($CI)
	{
		$CI->session->sess_destroy();
		$accept = strtolower((string) $CI->input->server('HTTP_ACCEPT'));
		$content_type = strtolower((string) $CI->input->server('CONTENT_TYPE'));
		if ($CI->input->is_ajax_request()
			|| strpos($accept, 'application/json') !== false
			|| strpos($content_type, 'application/json') !== false) {
			$CI->output
				->set_status_header(401)
				->set_content_type('application/json', 'utf-8')
				->set_output(json_encode(array('success' => false, 'safe_error_code' => 'session_expired')));
			$CI->output->_display();
			exit;
		}
		redirect('login?session=expired');
		exit;
	}
}
