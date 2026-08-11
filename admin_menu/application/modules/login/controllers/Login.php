<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Login extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Login_m');
			$this->load->helper('password_compat');
		}

		public function index(){
			if($this->session->userdata('is_login')==TRUE){
				// if ($this->session->userdata('level')==10 || $this->session->userdata('level')==1 || $this->session->userdata('level')==3) {
				// 	redirect('/order');
				// }else{
				// 	redirect('/home');
				// }
				redirect('/home');
			}else{
				$this->load->view('login_v', array(
					'session_message' => (string) $this->input->get('session', true) === 'expired' ? 'Sesi berakhir. Masuk lagi.' : '',
				));
			}
		}

		public function ceklogin(){
			if ($this->input->method(TRUE) !== 'POST') {
				return $this->output->set_status_header(405)->set_output('0');
			}
			$email = htmlspecialchars($this->input->post('email'));
		    $password = (string) $this->input->post('password');
		    $user_query = $this->Login_m->get_admin_by_email($email);
		    if ($user_query->num_rows() > 0 && doclinc_password_verify($password, (string) $user_query->row()->password)) {
				$user = $user_query->row();
				$login_password_hash = (string) $user->password;
				if (doclinc_password_needs_rehash($login_password_hash)
					&& $this->config->item('single_active_session_enabled') !== true) {
					$login_password_hash = doclinc_password_hash($password);
					$this->Login_m->update_password($user->userId, $login_password_hash);
				}
				$this->session->sess_regenerate(TRUE);
				$normal_session_token = null;
				if ($this->config->item('single_active_session_enabled') === true) {
					$service_file = dirname(APPPATH, 2) . '/application/libraries/Session_binding_service.php';
					if (!is_file($service_file)) {
						return $this->output->set_output('0');
					}
					require_once $service_file;
					$normal_session_token = (new Session_binding_service($this->db))->issue((int) $user->userId, $login_password_hash);
					if (!is_string($normal_session_token)) {
						$this->session->sess_destroy();
						return $this->output->set_output('0');
					}
				}
		    	$data_session = array(
					'id' => (int) $user->userId,
					'email' => $user->email,
					'username' => $user->username,
					'level' => $user->role,
					'is_login' => 'TRUE',
					'admin_logout_token' => bin2hex(random_bytes(32)),
				);
				if ($normal_session_token !== null) {
					$data_session['normal_session_token'] = $normal_session_token;
				}
				$this->session->set_userdata($data_session);
				$this->Login_m->log_login_event('login_success', $user->userId, array('role' => $user->role, 'area' => 'admin'));
				return $this->output->set_output('1');
		    }else{
				$this->Login_m->log_login_event('login_failed', NULL, array('area' => 'admin'));
				return $this->output->set_output('0');
		    }
		}

		public function welcome(){
			// $decode_email = base64_decode($encode_email);
			$decode_email = htmlspecialchars($this->input->post('email'));
			$chat_id=$this->uri->segment('3');
			$username=$this->uri->segment('4');
	    	$this->Login_m->addchat($chat_id,$username);
	    	
	    	$this->Login_m->verification($decode_email);
	    	$info = '<div class="alert alert-success alert-dismissible fade show shadow border border-success animated fadeInDown" style="z-index:auto;" role="alert">
	                    <strong>Selamat datang!</strong> Silakan masuk.
	                    <button type="button" class="close" data-dismiss="alert" aria-label="Tutup">
	                        <span aria-hidden="true">&times;</span>
	                    </button>
	                </div>';
        	$this->session->set_flashdata('info',$info);
	    	redirect('login','refresh');
	    }
	    public function reset_password(){
			if ($this->input->method(TRUE) !== 'POST') {
				redirect('login', 'refresh');
				return;
			}
	    	$length = 8;
            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            $encrypted = doclinc_password_hash($randomString);
	    	$email = $this->input->post('email_cust');
			if (!$this->Login_m->reset_password($email,$encrypted)) {
				$this->session->set_flashdata('info', '<div class="alert alert-danger" role="alert">Password belum dapat direset. Coba lagi.</div>');
				redirect('login','refresh');
				return;
			}
	    	$this->send_mail($email,$randomString);

	    	$info = '<div class="alert alert-success alert-dismissible fade show shadow border border-success animated fadeInDown" style="z-index:auto;" role="alert">
	                    <strong>Password baru dikirim ke email Anda.</strong>
	                    <button type="button" class="close" data-dismiss="alert" aria-label="Tutup">
	                        <span aria-hidden="true">&times;</span>
	                    </button>
	                </div>';
        	$this->session->set_flashdata('info',$info);
			redirect('login','refresh');
	    }
		public function logout(){
			if ($this->input->method(TRUE) !== 'POST') {
				show_404();
				return;
			}
			$expected = $this->session->userdata('admin_logout_token');
			$submitted = $this->input->post('_logout_token', false);
			if (!is_string($expected) || !is_string($submitted) || !hash_equals($expected, $submitted)) {
				show_error('Form sudah tidak berlaku.', 403, 'Coba lagi');
				return;
			}
			if ($this->config->item('single_active_session_enabled') === true) {
				$service_file = dirname(APPPATH, 2) . '/application/libraries/Session_binding_service.php';
				if (is_file($service_file)) {
					require_once $service_file;
					(new Session_binding_service($this->db))->revokeCurrent(
						(int) $this->session->userdata('id'),
						$this->session->userdata('normal_session_token')
					);
				}
			}
			$this->session->sess_destroy();
			redirect('/','refresh');
		}
	}
