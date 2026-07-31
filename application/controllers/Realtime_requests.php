<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Realtime_requests extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library('session');
		$this->load->helper(array('request_authz', 'request_realtime'));
	}

	public function snapshot()
	{
		$this->output->set_content_type('application/json')
			->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
			->set_header('Pragma: no-cache');
		if ($this->input->method(true) !== 'GET') {
			return $this->respond(405, 'method_not_allowed');
		}
		if (!empty($this->input->get(null, true))) {
			return $this->respond(400, 'query_not_allowed');
		}
		if (!doclinc_realtime_requests_enabled() || $this->session->userdata('logged_in') != true) {
			return $this->respond(403, 'feature_or_session_denied');
		}
		$user_id = (int) $this->session->userdata('id');
		$role = (string) $this->session->userdata('role');
		if ($user_id < 1 || !in_array($role, array('warga', 'dokter'), true)
			|| !$this->db->field_exists('must_change_password', 'users')) {
			return $this->respond(403, 'actor_denied');
		}
		$user = $this->db->select('userId, role, status, must_change_password')->where('userId', $user_id)->limit(1)->get('users')->row();
		if (!$user || (string) $user->role !== $role || (string) $user->status !== 'aktif' || (int) $user->must_change_password === 1) {
			return $this->respond(403, 'actor_denied');
		}
		$identity = $role === 'dokter' ? doclinc_dokter_identity_context($user_id, true) : null;
		require_once APPPATH . 'libraries/Request_realtime_policy.php';
		$policy = new Request_realtime_policy();
		$actor = array('authenticated' => true, 'user_id' => $user_id, 'role' => $role,
			'status' => (string) $user->status, 'must_change_password' => false, 'identity' => $identity);
		if (!$policy->actorAllowed($actor)) {
			return $this->respond(403, 'actor_denied');
		}
		require_once APPPATH . 'libraries/Request_realtime_snapshot.php';
		$data = (new Request_realtime_snapshot($this->db, 100))->read($actor);
		if ($data === false) {
			return $this->respond(403, 'snapshot_denied');
		}
		$this->output->set_status_header(200)->set_output(json_encode(array('success' => true, 'data' => $data)));
	}

	private function respond($status, $code)
	{
		$this->output->set_status_header($status)->set_output(json_encode(array('success' => false, 'safe_error_code' => $code)));
	}
}
