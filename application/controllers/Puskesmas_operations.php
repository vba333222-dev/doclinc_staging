<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Puskesmas_operations extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library('session');
		$this->load->helper(array('request_authz', 'role_prerequisite'));
	}

	public function snapshot()
	{
		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0')
			->set_header('Pragma: no-cache');
		if ($this->input->method(true) !== 'GET') {
			$this->respond(405, false, 'method_not_allowed');
			return;
		}
		$filters = $this->filters();
		if ($filters === false) {
			$this->respond(400, false, 'invalid_filter');
			return;
		}
		if ($this->config->item('puskesmas_operations_enabled') !== true) {
			$this->respond(403, false, 'feature_disabled');
			return;
		}

		$actor = $this->freshActor();
		$prerequisite = doclinc_role_prerequisite_state((int) $actor['user_id'], true);
		if (empty($prerequisite['allowed']) || empty($prerequisite['complete'])) {
			$this->respond(403, false, 'profile_prerequisites_missing');
			return;
		}

		require_once APPPATH . 'libraries/Puskesmas_operations_service.php';
		$service = new Puskesmas_operations_service(
			$this->db,
			(int) $this->config->item('nakes_presence_online_timeout_seconds')
		);
		$result = $service->snapshot(
			$actor,
			(int) $this->config->item('puskesmas_operations_staff_limit'),
			(int) $this->config->item('puskesmas_operations_request_limit'),
			$filters
		);
		if (empty($result['ok'])) {
			$status = in_array($result['code'], array('schema_unavailable', 'read_failed', 'result_too_large'), true) ? 503 : 403;
			$this->respond($status, false, $result['code']);
			return;
		}

		$this->respond(200, true, 'ok', $result['data']);
	}

	public function medical_record($request_id = 0)
	{
		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
		if ($this->input->method(true) !== 'GET' || !empty($this->input->get(null, true))) {
			$this->respond(405, false, 'method_not_allowed');
			return;
		}
		if ($this->config->item('puskesmas_operations_enabled') !== true) {
			$this->respond(403, false, 'feature_disabled');
			return;
		}
		$actor = $this->freshActor();
		$prerequisite = doclinc_role_prerequisite_state((int) $actor['user_id'], true);
		if (empty($prerequisite['allowed']) || empty($prerequisite['complete'])) {
			$this->respond(403, false, 'profile_prerequisites_missing');
			return;
		}
		require_once APPPATH . 'libraries/Puskesmas_operations_service.php';
		$service = new Puskesmas_operations_service($this->db, (int) $this->config->item('nakes_presence_online_timeout_seconds'));
		$result = $service->medicalRecord($actor, (int) $request_id);
		if (empty($result['ok'])) {
			$this->respond($result['code'] === 'schema_unavailable' ? 503 : 403, false, $result['code']);
			return;
		}
		$this->respond(200, true, 'ok', $result['data']);
	}

	private function filters()
	{
		$input = $this->input->get(null, true);
		$input = is_array($input) ? $input : array();
		foreach (array_keys($input) as $key) {
			if (!in_array($key, array('q', 'date_from', 'date_to'), true)) {
				return false;
			}
		}
		$q = trim((string) ($input['q'] ?? ''));
		$from = trim((string) ($input['date_from'] ?? date('Y-m-d')));
		$to = trim((string) ($input['date_to'] ?? $from));
		if (strlen($q) > 100 || !$this->validDate($from) || !$this->validDate($to)) {
			return false;
		}
		$from_time = strtotime($from . ' 00:00:00');
		$to_time = strtotime($to . ' 00:00:00');
		if ($from_time > $to_time || $to_time - $from_time > 31 * 86400) {
			return false;
		}
		return array('q' => $q, 'date_from' => $from, 'date_to' => $to);
	}

	private function validDate($value)
	{
		$date = DateTime::createFromFormat('!Y-m-d', (string) $value);
		return $date && $date->format('Y-m-d') === $value;
	}

	private function freshActor()
	{
		$user_id = (int) $this->session->userdata('id');
		$actor = array(
			'authenticated' => $this->session->userdata('logged_in') == true,
			'user_id' => $user_id,
			'role' => (string) $this->session->userdata('role'),
			'status' => '',
			'must_change_password' => true,
			'identity' => array(),
		);
		if ($user_id < 1 || !$this->db->table_exists('users')
			|| !$this->db->field_exists('must_change_password', 'users')
			|| !doclinc_nakes_credential_schema_allows_runtime($this->db)) {
			return $actor;
		}
		$user = $this->db->select(
			'userId, role, status, must_change_password, ' . doclinc_nakes_password_changed_at_projection($this->db),
			false
		)
			->where('userId', $user_id)
			->limit(1)
			->get('users')
			->row();
		if (!$user || (string) $user->role !== $actor['role']) {
			return $actor;
		}
		$actor['status'] = (string) $user->status;
		$actor['must_change_password'] = doclinc_nakes_password_change_blocked(
			$user->role,
			$user->must_change_password,
			$user->password_changed_at
		);
		if ($actor['role'] === 'dokter') {
			$actor['identity'] = doclinc_dokter_identity_context($user_id, true);
		}
		return $actor;
	}

	private function respond($status, $success, $code, array $data = array())
	{
		$body = array('success' => (bool) $success, 'safe_error_code' => (string) $code);
		if ($success) {
			$body['data'] = $data;
		}
		$this->output->set_status_header((int) $status)->set_output(json_encode($body));
	}
}
