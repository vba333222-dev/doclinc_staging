<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Location_address extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library('session');
	}

	public function address()
	{
		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0')
			->set_header('X-Content-Type-Options: nosniff');
		if ($this->input->method(true) !== 'POST') {
			$this->respond(405, 'method_not_allowed');
			return;
		}
		if (!empty($this->input->get(null, true))) {
			$this->respond(400, 'query_not_allowed');
			return;
		}
		$user_id = (int) $this->session->userdata('id');
		$role = (string) $this->session->userdata('role');
		if ($this->session->userdata('logged_in') != true || $user_id < 1 || !in_array($role, array('warga', 'dokter'), true)) {
			$this->respond(401, 'session_required');
			return;
		}
		if (!$this->db->table_exists('users') || !$this->db->field_exists('must_change_password', 'users')) {
			$this->respond(503, 'actor_schema_unavailable');
			return;
		}
		$actor = $this->db->select('userId, role, status, must_change_password')
			->where('userId', $user_id)->limit(1)->get('users')->row();
		if (!$actor || (string) $actor->role !== $role || (string) $actor->status !== 'aktif' || (int) $actor->must_change_password === 1) {
			$this->respond(403, 'actor_denied');
			return;
		}

		$latitude = $this->input->post('latitude', true);
		$longitude = $this->input->post('longitude', true);
		if (!$this->valid_coordinate($latitude, -90, 90) || !$this->valid_coordinate($longitude, -180, 180)) {
			$this->respond(422, 'invalid_location');
			return;
		}

		require_once APPPATH . 'libraries/Reverse_geocoding_service.php';
		$service = new Reverse_geocoding_service(array(
			'cache_path' => $this->config->item('reverse_geocoding_cache_path'),
			'enabled' => $this->config->item('reverse_geocoding_enabled') === true,
			'endpoint' => $this->config->item('reverse_geocoding_endpoint'),
			'allowed_hosts' => $this->config->item('reverse_geocoding_allowed_hosts'),
			'user_agent' => $this->config->item('reverse_geocoding_user_agent'),
		));
		$result = $service->resolve($latitude, $longitude);
		$this->output->set_status_header(200)->set_output(json_encode(array(
			'success' => true,
			'data' => $result,
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	private function valid_coordinate($value, $minimum, $maximum)
	{
		return is_numeric($value) && is_finite((float) $value) && (float) $value >= $minimum && (float) $value <= $maximum;
	}

	private function respond($status, $code)
	{
		$this->output->set_status_header((int) $status)->set_output(json_encode(array(
			'success' => false,
			'safe_error_code' => (string) $code,
		)));
	}
}
