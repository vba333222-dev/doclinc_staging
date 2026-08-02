<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Role_prerequisite_gate
{
	public function enforce()
	{
		if (PHP_SAPI === 'cli') {
			return;
		}
		$CI = &get_instance();
		if (!$CI->session->userdata('logged_in') || $CI->config->item('role_prerequisites_enabled') !== true) {
			return;
		}
		if (!in_array((string) $CI->session->userdata('role'), array('warga', 'dokter'), true)) {
			return;
		}
		if (!isset($CI->db)) {
			$CI->load->database();
		}

		require_once APPPATH . 'libraries/Role_prerequisite_gate_policy.php';
		$policy = new Role_prerequisite_gate_policy();
		$class = (string) $CI->router->class;
		$method = (string) $CI->router->method;
		$role = (string) $CI->session->userdata('role');
		if ($policy->allowed($class, $method, $role, (int) $CI->uri->segment(3), (int) $CI->session->userdata('id'))) {
			return;
		}

		$CI->load->helper(array('request_authz', 'role_prerequisite'));
		$state = doclinc_role_prerequisite_state((int) $CI->session->userdata('id'), true);
		if (!empty($state['allowed'])) {
			return;
		}
		$payload = doclinc_role_prerequisite_error_payload($state);
		$payload['request_id'] = $this->request_id();
		$payload['retryable'] = false;
		$payload['field_errors'] = $this->field_errors($state);
		$payload['cta_url'] = base_url('profile/complete');
		$payload['cta_label'] = 'Lengkapi data';

		if ($policy->jsonResponse(
			$class,
			$method,
			$CI->input->is_ajax_request(),
			(string) $CI->input->server('HTTP_ACCEPT'),
			(string) $CI->input->server('CONTENT_TYPE')
		)) {
			$CI->output
				->set_status_header(doclinc_role_prerequisite_http_status($state))
				->set_content_type('application/json', 'utf-8')
				->set_header('Cache-Control: no-store, private')
				->set_output(json_encode($payload));
			$CI->output->_display();
			exit;
		}

		redirect('profile/complete');
		exit;
	}

	private function field_errors(array $state)
	{
		$fields = isset($state['missing_fields']) && is_array($state['missing_fields']) ? $state['missing_fields'] : array();
		$labels = isset($state['missing_labels']) && is_array($state['missing_labels']) ? $state['missing_labels'] : array();
		$self = isset($state['self_service_fields']) && is_array($state['self_service_fields']) ? $state['self_service_fields'] : array();
		$result = array();
		foreach ($fields as $index => $field) {
			$result[] = array(
				'field' => (string) $field,
				'label' => isset($labels[$index]) ? (string) $labels[$index] : 'Data wajib',
				'owner' => in_array($field, $self, true) ? 'self' : 'administrator',
			);
		}
		return $result;
	}

	private function request_id()
	{
		try {
			return bin2hex(random_bytes(8));
		} catch (Throwable $exception) {
			return substr(hash('sha256', uniqid('', true)), 0, 16);
		}
	}
}
