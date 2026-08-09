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
		if (!$CI->session->userdata('logged_in')) {
			return;
		}

		require_once APPPATH . 'libraries/First_login_gate_policy.php';
		$policy = new First_login_gate_policy();
		$class = (string) $CI->router->class;
		$method = (string) $CI->router->method;

		$user_id = (int) $CI->session->userdata('id');
		$session_role = (string) $CI->session->userdata('role');
		if ($user_id < 1 || $session_role === '' || !$CI->db->table_exists('users')) {
			$CI->session->sess_destroy();
			$this->deny($CI, $policy, 'session_not_allowed');
		}

		$has_password_gate = $CI->db->field_exists('must_change_password', 'users')
			&& $CI->db->field_exists('password_changed_at', 'users');
		$select = 'userId, role, status';
		if ($has_password_gate) {
			$select .= ', must_change_password, password_changed_at';
		}
		$user = $CI->db
			->select($select)
			->where('userId', $user_id)
			->limit(1)
			->get('users')
			->row();
		if (!$user || (string) $user->role !== $session_role || (string) $user->status !== 'aktif') {
			$CI->session->sess_destroy();
			$this->deny($CI, $policy, 'session_not_allowed');
		}

		require_once APPPATH . 'libraries/Nakes_credential_policy.php';
		$credential_policy = new Nakes_credential_policy();
		$must_change_password = $has_password_gate && $credential_policy->requiresChange(
			(string) $user->role,
			(int) $user->must_change_password,
			$user->password_changed_at
		) ? 1 : 0;
		if ((int) $CI->session->userdata('must_change_password') !== $must_change_password) {
			$CI->session->set_userdata('must_change_password', $must_change_password);
		}
		if ($must_change_password !== 1) {
			return;
		}
		if ($policy->allowed($class, $method)) {
			return;
		}

		$enabled = $CI->config->item('first_login_password_change_enabled') === true;
		if (!$enabled || !$has_password_gate || $session_role !== 'dokter') {
			$CI->session->sess_destroy();
			$this->deny($CI, $policy, 'password_change_unavailable');
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
				->set_status_header($code === 'session_not_allowed' ? 401 : 403)
				->set_content_type('application/json', 'utf-8')
				->set_header('Cache-Control: no-store, private')
				->set_output(json_encode(array(
					'success' => false,
					'safe_error_code' => $code,
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
