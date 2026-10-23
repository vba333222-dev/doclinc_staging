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
				redirect('login-nakes/change-password');
				return;
			}
			$this->session->unset_userdata(array('credential_activation_user_id', 'credential_activation_mode', 'password_change_session_binding', 'password_change_token_state', 'normal_session_token'));
		}
		if ($this->session->userdata('logged_in') == TRUE) {
			if ((int) $this->session->userdata('must_change_password') === 1) {
				redirect('login-nakes/change-password');
				return;
			}
			$role = $this->session->userdata('role');
			if ($role == 'warga') {
				redirect('/home');
			} elseif ($role == 'dokter') {
				redirect('/home_nakes');
			} elseif ($role == 'admin') {
				redirect('admin_menu/');
			} else {
				echo 'Akun tidak dapat diproses.';
			}
		} else {
			$session_notice = $this->session_notice_message();
			/* Halaman masuk warga tidak lagi memuat tautan aktivasi nakes:
			   langkah verifikasi nakes ada di permukaan `login-nakes`. */
			$this->load->view('login_v', array(
				'success_message' => $session_notice['message'] !== '' ? $session_notice['message'] : (string) $this->session->flashdata('login_success'),
				'session_notice_level' => $session_notice['message'] !== '' ? $session_notice['level'] : 'success',
			));
		}
	}

	/**
	 * Pesan di halaman login setelah sesi berakhir.
	 *
	 * Pemetaan sebab -> pesan ada di Session_binding_response_policy supaya
	 * aplikasi utama dan panel admin tidak pernah menampilkan kalimat berbeda
	 * untuk kejadian yang sama. Isian formulir yang hilang disebut apa adanya
	 * alih-alih dibiarkan tanpa penjelasan.
	 */
	private function session_notice_message()
	{
		require_once APPPATH . 'libraries/Session_binding_response_policy.php';
		$policy = new Session_binding_response_policy();
		return $policy->noticeFor(
			$this->input->get('session', true),
			$this->input->get('reason', true),
			$this->input->get('unsaved', true)
		);
	}


	public function auth()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output->set_status_header(405)->set_output('0');
			return;
		}
		$username = htmlspecialchars($this->input->post('username'));
		$password = (string) $this->input->post('password');
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
			$login_password_hash = (string) $user->password;
			if (doclinc_password_needs_rehash($login_password_hash)
				&& $this->config->item('single_active_session_enabled') !== true) {
				$login_password_hash = doclinc_password_hash($password);
				$this->Login_m->update_password($user->userId, $login_password_hash);
			}
			$this->session->sess_regenerate(TRUE);
			$normal_session_token = null;
			if ($this->config->item('single_active_session_enabled') === true) {
				$this->load->library('Session_binding_service');
				$normal_session_token = $this->session_binding_service->issue((int) $user->userId, $login_password_hash);
				if (!is_string($normal_session_token)) {
					$this->session->sess_destroy();
					echo "0";
					return;
				}
			}
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
			if ($normal_session_token !== null) {
				$session_data['normal_session_token'] = $normal_session_token;
			}
			if ($must_change_password === 1) {
				$session_data['password_change_session_binding'] = bin2hex(random_bytes(16));
			}
			$this->session->set_userdata($session_data);	
			
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
			// Keep direct navigation intentional and valid; state-changing logout
			// remains POST-only and CSRF-protected by the rendered clients.
			redirect('login');
			return;
		}
		if ($this->config->item('single_active_session_enabled') === true) {
			$this->load->library('Session_binding_service');
			$this->session_binding_service->revokeCurrent(
				(int) $this->session->userdata('id'),
				$this->session->userdata('normal_session_token')
			);
		}
		$this->session->unset_userdata(array('password_change_token_state', 'password_change_session_binding', 'credential_activation_user_id', 'credential_activation_mode', 'normal_session_token'));
		$this->session->sess_destroy();
		redirect('login');
	}

}
