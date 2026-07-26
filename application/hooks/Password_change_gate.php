<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Password_change_gate
{
	public function enforce()
	{
		if (PHP_SAPI === 'cli') {
			return;
		}
		$CI = &get_instance();
		if (!$CI->session->userdata('logged_in') || (int) $CI->session->userdata('must_change_password') !== 1) {
			return;
		}

		require_once APPPATH . 'libraries/First_login_gate_policy.php';
		$policy = new First_login_gate_policy();
		$class = (string) $CI->router->class;
		$method = (string) $CI->router->method;
		if ($policy->allowed($class, $method)) {
			return;
		}

		$enabled = $CI->config->item('first_login_password_change_enabled') === true;
		if (!$enabled || !$CI->db->table_exists('users') || !$CI->db->field_exists('must_change_password', 'users')) {
			$this->deny($CI, $policy, 'password_change_unavailable');
		}

		$user_id = (int) $CI->session->userdata('id');
		$user = $CI->db
			->select('userId, role, status, must_change_password')
			->where('userId', $user_id)
			->get('users')
			->row();
		if (!$user || $user->role !== 'dokter' || $user->status !== 'aktif') {
			$CI->session->sess_destroy();
			$this->deny($CI, $policy, 'session_not_allowed');
		}
		if ((int) $user->must_change_password !== 1) {
			$CI->session->set_userdata('must_change_password', 0);
			return;
		}

		$this->deny($CI, $policy, 'password_change_required');
	}

	private function deny($CI, First_login_gate_policy $policy, $code)
	{
		if ($code !== 'password_change_required') {
			$CI->session->sess_destroy();
		}
		$is_json = $policy->jsonResponse(
			(string) $CI->router->class,
			(string) $CI->router->method,
			$CI->input->is_ajax_request(),
			(string) $CI->input->server('HTTP_ACCEPT'),
			(string) $CI->input->server('CONTENT_TYPE')
		);
		if ($is_json) {
			$CI->output
				->set_status_header(403)
				->set_content_type('application/json', 'utf-8')
				->set_header('Cache-Control: no-store, private')
				->set_output(json_encode(array(
					'success' => false,
					'error' => 'password_change_required',
				)));
			$CI->output->_display();
			exit;
		}

		if ($code !== 'password_change_required') {
			redirect('login');
			exit;
		}
		redirect('login/change-password');
		exit;
	}
}
