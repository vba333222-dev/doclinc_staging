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
			if ((int) $this->session->userdata('must_change_password') === 1) {
				redirect('login/change-password');
				return;
			}
			$role = $this->session->userdata('role');
			if ($role == 'warga') {
				redirect('/home');
			} elseif ($role == 'dokter') {
				redirect('/home_nakes');
			} elseif ($role == 'admin') {
				redirect('/home_admin');
			} else {
				echo 'Akun tidak dapat diproses.';
			}
		} else {
			$this->load->view('login_v');
		}
	}

	public function auth()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output->set_status_header(405)->set_output('0');
			return;
		}
		$username = htmlspecialchars($this->input->post('username'));
		$password = (string) $this->input->post('password');
		$location = htmlspecialchars($this->input->post('location'));
		$lattitude = htmlspecialchars($this->input->post('lattitude'));
		$longitude = htmlspecialchars($this->input->post('longitude'));
		$auth = $this->Login_m->auth($username);
		$user = $auth->num_rows() > 0 ? $auth->row() : null;
		$actor_is_allowed = $user && (string) ($user->status ?? '') === 'aktif';
		if ($actor_is_allowed && doclinc_password_verify($password, (string) $user->password)) {
			require_once APPPATH . 'libraries/Nakes_credential_policy.php';
			$credential_policy = new Nakes_credential_policy();
			$must_change_password = $credential_policy->requiresChange(
				(string) $user->role,
				property_exists($user, 'must_change_password') ? (int) $user->must_change_password : 0,
				property_exists($user, 'password_changed_at') ? $user->password_changed_at : null
			) ? 1 : 0;
			if ($must_change_password === 1 && $this->config->item('first_login_password_change_enabled') !== true) {
				$this->Login_m->log_login_event('login_restricted', $user->userId, array('reason' => 'password_change_unavailable'));
				echo "0";
				return;
			}
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
				'remark' => property_exists($user, 'remark') ? $user->remark : null,
				'picture' => property_exists($user, 'foto') ? $user->foto : null,
				'logged_in' => TRUE,
				'must_change_password' => $must_change_password,
			];
			if ($must_change_password === 1) {
				$session_data['password_change_session_binding'] = bin2hex(random_bytes(16));
			}
			$this->session->set_userdata($session_data);	
			
			$id = $this->session->userdata('id');
			$username = $this->session->userdata('username');		
			if ($must_change_password === 0) {
				$this->Login_m->save_location($id,$username,$location, $lattitude,$longitude);
			}
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
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
			return;
		}
		$this->session->unset_userdata(array('password_change_token_state', 'password_change_session_binding'));
		$this->session->sess_destroy();
		redirect('login');
	}

	public function change_password()
	{
		if (!$this->password_change_session_allowed()) {
			redirect('login');
			return;
		}
		if ($this->input->method(TRUE) !== 'GET') {
			$this->output->set_status_header(405);
			return;
		}
		$binding = $this->session->userdata('password_change_session_binding');
		if (!is_string($binding) || preg_match('/\A[a-f0-9]{32}\z/', $binding) !== 1) {
			$binding = bin2hex(random_bytes(16));
			$this->session->set_userdata('password_change_session_binding', $binding);
		}
		$this->load->library('First_login_token_policy');
		$issued = $this->first_login_token_policy->issue((int) $this->session->userdata('id'), $binding);
		if (!is_array($issued)) {
			$this->session->sess_destroy();
			redirect('login');
			return;
		}
		$this->session->set_userdata('password_change_token_state', $issued['state']);
		$this->output->set_header('Cache-Control: no-store, private');
		$this->load->view('change_password_v', array(
			'form_token' => $issued['token'],
			'error_message' => (string) $this->session->flashdata('password_change_error'),
		));
	}

	public function update_password()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output->set_status_header(405);
			return;
		}
		if (!$this->password_change_session_allowed()) {
			$this->session->sess_destroy();
			redirect('login');
			return;
		}
		$submitted_token = $this->input->post('password_change_token', false);
		$token_state = $this->session->userdata('password_change_token_state');
		$binding = $this->session->userdata('password_change_session_binding');
		$this->session->unset_userdata('password_change_token_state');
		$this->load->library('First_login_token_policy');
		if (!$this->first_login_token_policy->validate($submitted_token, $token_state, (int) $this->session->userdata('id'), $binding)) {
			$submitted_token = null;
			$this->password_change_failure('Form sudah tidak berlaku. Silakan coba lagi.');
			return;
		}

		$new_password = $this->input->post('new_password', false);
		$confirmation = $this->input->post('confirm_password', false);
		if (!is_string($new_password) || !is_string($confirmation)) {
			$this->password_change_failure('Password baru belum memenuhi ketentuan.');
			return;
		}
		$user = $this->Login_m->get_password_change_identity((int) $this->session->userdata('id'));
		if (!$user) {
			$this->session->sess_destroy();
			redirect('login');
			return;
		}
		$this->load->library('First_login_password_policy');
		$error = $this->first_login_password_policy->validate($new_password, $confirmation, $user, $user['password']);
		if ($error !== null) {
			$this->password_change_failure($this->password_policy_message($error));
			return;
		}
		$new_hash = doclinc_password_hash($new_password);
		if (!is_string($new_hash) || !$this->Login_m->complete_required_password_change((int) $user['userId'], $new_hash, (string) $user['password'])) {
			$new_hash = null;
			$this->password_change_failure('Password belum dapat diperbarui. Silakan coba lagi.');
			return;
		}
		$new_hash = null;
		$new_password = null;
		$confirmation = null;
		$submitted_token = null;
		$this->session->unset_userdata(array('password_change_token_state', 'password_change_session_binding'));
		$this->session->sess_regenerate(TRUE);
		$this->session->set_userdata('must_change_password', 0);
		redirect('home_nakes');
	}

	private function password_change_session_allowed()
	{
		return $this->config->item('first_login_password_change_enabled') === true
			&& $this->session->userdata('logged_in') == TRUE
			&& $this->session->userdata('role') === 'dokter'
			&& (int) $this->session->userdata('must_change_password') === 1;
	}

	private function password_change_failure($message)
	{
		$this->session->set_flashdata('password_change_error', $message);
		redirect('login/change-password');
	}

	private function password_policy_message($code)
	{
		require_once APPPATH . 'libraries/Password_strength_policy.php';
		$policy = new Password_strength_policy();
		$message = $policy->message($code);
		return $message === 'Password belum memenuhi ketentuan keamanan.'
			? 'Password baru belum memenuhi ketentuan keamanan DocLink.'
			: $message;
	}
}
