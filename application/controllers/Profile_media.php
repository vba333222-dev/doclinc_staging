<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Profile_media extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library('session');
		$this->load->helper('request_authz');
	}

	public function photo($target_user_id = 0)
	{
		if ($this->input->method(true) !== 'GET' || $this->session->userdata('logged_in') != true) {
			show_404();
			return;
		}
		if (!$this->db->table_exists('users')) {
			show_404();
			return;
		}
		foreach (array('userId', 'role', 'status', 'must_change_password', 'foto') as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				show_404();
				return;
			}
		}

		$actor_id = (int) $this->session->userdata('id');
		$target_user_id = (int) $target_user_id;
		$actor = $this->user_row($actor_id);
		$target = $this->user_row($target_user_id);
		if (!$actor || !$target || (string) $actor->role !== (string) $this->session->userdata('role')) {
			show_404();
			return;
		}

		require_once APPPATH . 'libraries/Profile_image_policy.php';
		$policy = new Profile_image_policy($this->db);
		if (!$policy->can_view($actor, $target)) {
			show_404();
			return;
		}

		require_once APPPATH . 'libraries/Profile_image_storage.php';
		$storage = new Profile_image_storage(array(
			'storage_path' => $this->config->item('profile_image_storage_path'),
			'public_root' => FCPATH,
		));
		$path = $storage->resolve_stored_file((string) $target->foto);
		$mime = $path !== false ? $storage->allowed_mime($path) : '';
		if ($path === false || $mime === '') {
			$path = FCPATH . 'assets/doclinc/img/default-profile.png';
			$mime = is_file($path) ? $storage->allowed_mime($path) : '';
		}
		if (!is_file($path) || !is_readable($path) || $mime === '') {
			show_404();
			return;
		}

		$body = file_get_contents($path);
		if ($body === false) {
			show_404();
			return;
		}

		$this->output
			->set_status_header(200)
			->set_content_type($mime)
			->set_header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0')
			->set_header('Pragma: no-cache')
			->set_header('X-Content-Type-Options: nosniff')
			->set_header('Cross-Origin-Resource-Policy: same-origin')
			->set_header('Content-Length: ' . strlen($body))
			->set_output($body);
	}

	private function user_row($user_id)
	{
		if ($user_id < 1) {
			return null;
		}
		return $this->db
			->select('userId, role, status, must_change_password, foto')
			->where('userId', $user_id)
			->limit(1)
			->get('users')
			->row();
	}
}
