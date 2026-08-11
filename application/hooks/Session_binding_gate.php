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
			|| !$CI->session->userdata('logged_in')) {
			return;
		}
		require_once APPPATH . 'libraries/Session_binding_service.php';
		$service = new Session_binding_service($CI->db);
		$user_id = (int) $CI->session->userdata('id');
		$token = $CI->session->userdata('normal_session_token');
		if ($service->validate($user_id, $token)) {
			return;
		}
		$CI->session->sess_destroy();
		require_once APPPATH . 'libraries/First_login_gate_policy.php';
		$policy = new First_login_gate_policy();
		$is_json = $policy->jsonResponse(
			(string) $CI->router->class,
			(string) $CI->router->method,
			$CI->input->is_ajax_request(),
			(string) $CI->input->server('HTTP_ACCEPT'),
			(string) $CI->input->server('CONTENT_TYPE')
		);
		if ($is_json) {
			$CI->output
				->set_status_header(401)
				->set_content_type('application/json', 'utf-8')
				->set_header('Cache-Control: no-store, private')
				->set_output(json_encode(array('success' => false, 'safe_error_code' => 'session_expired')));
			$CI->output->_display();
			exit;
		}
		redirect('login?session=expired');
		exit;
	}
}
