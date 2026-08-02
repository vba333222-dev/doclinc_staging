<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Profile_completion extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library('session');
		$this->load->helper(array('request_authz', 'role_prerequisite', 'profile_image', 'csrf_bootstrap'));
	}

	public function index()
	{
		$this->output
			->set_header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0')
			->set_header('Pragma: no-cache');
		if ($this->input->method(true) !== 'GET') {
			show_404();
			return;
		}
		$user_id = (int) $this->session->userdata('id');
		$role = (string) $this->session->userdata('role');
		if ($this->session->userdata('logged_in') != true || $user_id < 1 || !in_array($role, array('warga', 'dokter'), true)) {
			redirect('login');
			return;
		}

		$state = doclinc_role_prerequisite_state($user_id, true);
		if (!empty($state['allowed'])) {
			redirect($role === 'warga' ? 'home' : 'home_nakes');
			return;
		}

		$fields = array('userId', 'nama', 'email', 'role', 'no_hp', 'alamat', 'tgl', 'gender', 'foto', 'nik', 'nomor_kk', 'nomor_bpjs_kis');
		$select = array();
		foreach ($fields as $field) {
			$select[] = $this->db->field_exists($field, 'users') ? $field : 'NULL AS ' . $field;
		}
		$user = $this->db->select(implode(', ', $select), false)
			->where('userId', $user_id)
			->where('role', $role)
			->limit(1)
			->get('users')
			->row_array();
		if (!$user) {
			$this->session->sess_destroy();
			redirect('login');
			return;
		}

		$identity = $role === 'dokter' ? doclinc_dokter_identity_context($user_id, true) : array();
		$this->load->view('profile_completion_v', array(
			'profile_user' => $user,
			'profile_state' => $state,
			'profile_role' => $role,
			'profile_account_type' => !empty($identity['valid']) ? (string) $identity['account_type'] : 'unclassified',
		));
	}
}
