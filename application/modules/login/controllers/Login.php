<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Login extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Login_m');
		$this->load->helper('password_compat');
	}

	public function index()
	{
		if ($this->session->userdata('logged_in') == TRUE) {
			$role = $this->session->userdata('role');
			if ($role == 'warga') {
				redirect('/home');
			} elseif ($role == 'dokter') {
				redirect('/home_nakes');
			} elseif ($role == 'admin') {
				redirect('/home_admin');
			} else {
				echo 'Error: User role not recognized';
			}
		} else {
			$this->load->view('login_v');
		}
	}

	public function auth()
	{
		$username = htmlspecialchars($this->input->post('username'));
		$password = (string) $this->input->post('password');
		$location = htmlspecialchars($this->input->post('location'));
		$lattitude = htmlspecialchars($this->input->post('lattitude'));
		$longitude = htmlspecialchars($this->input->post('longitude'));
		$auth = $this->Login_m->auth($username);
		if ($auth->num_rows() > 0 && doclinc_password_verify($password, (string) $auth->row()->password)) {
			$user = $auth->row();
			if (doclinc_password_needs_rehash((string) $user->password)) {
				$this->Login_m->update_password($user->userId, doclinc_password_hash($password));
			}
			$this->session->sess_regenerate(TRUE);
			$session_data = [
				'id' => $user->userId,
				'username' => $user->username,
				'nama' => $user->nama,
				'email' => $user->email,
				'role' => $user->role,
				'picture' => property_exists($user, 'foto') ? $user->foto : null,
				'logged_in' => TRUE
			];
			$this->session->set_userdata($session_data);	
			
			$id = $this->session->userdata('id');
			$username = $this->session->userdata('username');		
			$this->Login_m->save_location($id,$username,$location, $lattitude,$longitude);
			$this->Login_m->log_login_event('login_success', $user->userId, array('role' => $user->role));
			// echo "OK";
			echo "1";
		} else {
			$this->Login_m->log_login_event('login_failed', NULL, array('area' => 'public'));
			echo "0";
			// echo "NO";
		}
	}

	public function logout()
	{
		$this->session->sess_destroy();
		redirect('login');
	}
}
