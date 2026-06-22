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
				$this->load->view('login_v');
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
				if (doclinc_password_needs_rehash((string) $user->password)) {
					$this->Login_m->update_password($user->userId, doclinc_password_hash($password));
				}
				$this->session->sess_regenerate(TRUE);
		    	$data_session = array(
					'email' => $user->email,
					'username' => $user->username,
					'level' => $user->role,
					'is_login' => 'TRUE'
				);
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
	                    <strong> Selamat datang!</strong> Silahkan Login.
	                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
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
	    	$this->Login_m->reset_password($email,$encrypted);
	    	$this->send_mail($email,$randomString);

	    	$info = '<div class="alert alert-success alert-dismissible fade show shadow border border-success animated fadeInDown" style="z-index:auto;" role="alert">
	                    <strong>Berhasil!</strong> Kamu baru saja me-reset password, silakan cek email kamu.<br>Note : Jika email tidak ada pada kotak masuk, mohon dilihat pada SPAM.
	                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
	                        <span aria-hidden="true">&times;</span>
	                    </button>
	                </div>';
        	$this->session->set_flashdata('info',$info);
			redirect('login','refresh');
	    }
		public function logout(){
		    $this->session->sess_destroy();
		    redirect('/','refresh');
		}
	}
