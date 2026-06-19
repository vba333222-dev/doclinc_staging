<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Login extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Login_m');
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
		$password = sha1(htmlspecialchars($this->input->post('password')));
		$location = htmlspecialchars($this->input->post('location'));
		$lattitude = htmlspecialchars($this->input->post('lattitude'));
		$longitude = htmlspecialchars($this->input->post('longitude'));
		$auth = $this->Login_m->auth($username, $password);
		if ($auth->num_rows() > 0) {
			$user = $auth->row();
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
			// echo "OK";
			echo "1";
		} else {
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
