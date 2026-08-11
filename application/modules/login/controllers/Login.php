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
		if ((int) $this->session->userdata('credential_activation_user_id') > 0) {
			if ($this->config->item('first_login_password_change_enabled') === true) {
				redirect('login/change-password');
				return;
			}
			$this->session->unset_userdata(array('credential_activation_user_id', 'credential_activation_mode', 'password_change_session_binding', 'password_change_token_state'));
		}
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
			$this->load->view('login_v', array(
				'activation_available' => $this->config->item('first_login_password_change_enabled') === true,
				'success_message' => (string) $this->session->flashdata('login_success'),
			));
		}
	}

	public function activate()
	{
		if ($this->input->method(TRUE) !== 'GET') {
			$this->output->set_status_header(405);
			return;
		}
		if ($this->session->userdata('logged_in') == TRUE) {
			redirect('login');
			return;
		}
		if ($this->config->item('first_login_password_change_enabled') !== true) {
			$this->session->set_flashdata('login_success', 'Aktivasi akun belum tersedia. Hubungi Admin Dinas Kesehatan.');
			redirect('login');
			return;
		}
		if ((int) $this->session->userdata('credential_activation_user_id') > 0) {
			redirect('login/change-password');
			return;
		}
		$this->output->set_header('Cache-Control: no-store, private');
		$this->load->view('activate_account_v', array(
			'error_message' => (string) $this->session->flashdata('activation_error'),
		));
	}

	public function activate_auth()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output->set_status_header(405);
			return;
		}
		if ($this->config->item('first_login_password_change_enabled') !== true
			|| $this->session->userdata('logged_in') == TRUE) {
			$this->activation_failure('Aktivasi akun belum dapat dilanjutkan.');
			return;
		}
		$username = trim((string) $this->input->post('username', TRUE));
		$password = $this->input->post('password', false);
		if ($username === '' || !is_string($password) || $password === '') {
			$this->activation_failure('Nama pengguna atau password sementara belum sesuai.');
			return;
		}
		$auth = $this->Login_m->auth($username);
		$user = $auth->num_rows() > 0 ? $auth->row() : null;
		$identity = $user && (string) ($user->status ?? '') === 'aktif'
			&& doclinc_password_verify($password, (string) ($user->password ?? ''))
			? $this->Login_m->get_personal_activation_identity((int) $user->userId)
			: null;
		$password = null;
		if (!$identity || (int) ($identity['must_change_password'] ?? 0) !== 1) {
			$this->Login_m->log_login_event('credential_activation_failed', $user ? (int) $user->userId : null, array('area' => 'activation'));
			$this->activation_failure('Nama pengguna atau password sementara belum sesuai.');
			return;
		}

		$this->session->sess_regenerate(TRUE);
		$this->session->unset_userdata(array(
			'id', 'username', 'nama', 'email', 'role', 'remark', 'picture', 'logged_in',
			'must_change_password', 'password_change_token_state',
		));
		$binding = bin2hex(random_bytes(16));
		$this->session->set_userdata(array(
			'credential_activation_user_id' => (int) $identity['userId'],
			'credential_activation_mode' => (string) $identity['credential_state'],
			'password_change_session_binding' => $binding,
		));
		$this->Login_m->log_login_event('credential_activation_authenticated', (int) $identity['userId'], array(
			'credential_state' => (string) $identity['credential_state'],
		));
		redirect('login/change-password');
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
			$must_change_password = doclinc_nakes_effective_must_change_password(
				(string) $user->role,
				property_exists($user, 'must_change_password') ? (int) $user->must_change_password : 0,
				property_exists($user, 'password_changed_at') ? $user->password_changed_at : null
			);
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
		$this->session->unset_userdata(array('password_change_token_state', 'password_change_session_binding', 'credential_activation_user_id', 'credential_activation_mode'));
		$this->session->sess_destroy();
		redirect('login');
	}

	public function change_password()
	{
		$context = $this->password_change_context();
		if (!$context) {
			redirect('login');
			return;
		}
		if (!$this->Login_m->get_personal_activation_identity((int) $context['user_id'])) {
			$this->session->sess_destroy();
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
		$issued = $this->first_login_token_policy->issue((int) $context['user_id'], $binding);
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
			'activation_mode' => $context['mode'] === 'activation',
		));
	}

	public function update_password()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output->set_status_header(405);
			return;
		}
		$context = $this->password_change_context();
		if (!$context) {
			if ($this->config->item('nakes_credential_enforcement_enabled') === true) {
				$this->session->sess_destroy();
			}
			redirect('login');
			return;
		}
		$submitted_token = $this->input->post('password_change_token', false);
		$token_state = $this->session->userdata('password_change_token_state');
		$binding = $this->session->userdata('password_change_session_binding');
		$this->session->unset_userdata('password_change_token_state');
		$this->load->library('First_login_token_policy');
		if (!$this->first_login_token_policy->consume($submitted_token, $token_state, (int) $context['user_id'], $binding)) {
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
		$user = $this->Login_m->get_personal_activation_identity((int) $context['user_id']);
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
		$this->session->unset_userdata(array('password_change_token_state', 'password_change_session_binding', 'credential_activation_user_id', 'credential_activation_mode'));
		$this->session->sess_regenerate(TRUE);
		$this->Login_m->log_login_event('credential_activation_completed', (int) $user['userId'], array('mode' => $context['mode']));
		if ($context['mode'] === 'activation') {
			$this->session->set_flashdata('login_success', 'Password baru berhasil dibuat. Silakan masuk dengan password tersebut.');
			redirect('login');
			return;
		}
		$this->session->set_userdata('must_change_password', 0);
		redirect('home_nakes');
	}

	private function password_change_context()
	{
		if ($this->config->item('first_login_password_change_enabled') !== true) {
			return null;
		}
		$activation_user_id = (int) $this->session->userdata('credential_activation_user_id');
		if ($activation_user_id > 0 && $this->session->userdata('logged_in') != TRUE) {
			return array('user_id' => $activation_user_id, 'mode' => 'activation');
		}
		if ($this->config->item('nakes_credential_enforcement_enabled') === true
			&& $this->session->userdata('logged_in') == TRUE
			&& $this->session->userdata('role') === 'dokter'
			&& (int) $this->session->userdata('must_change_password') === 1) {
			return array('user_id' => (int) $this->session->userdata('id'), 'mode' => 'enforcement');
		}
		return null;
	}

	private function password_change_failure($message)
	{
		$this->session->set_flashdata('password_change_error', $message);
		redirect('login/change-password');
	}

	private function activation_failure($message)
	{
		$this->session->set_flashdata('activation_error', $message);
		redirect('login/activate');
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
