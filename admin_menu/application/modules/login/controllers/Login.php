<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Login extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Login_m');
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
			$email = htmlspecialchars($this->input->post('email'));
		    $password = htmlspecialchars(sha1($this->input->post('password')));
		    $res = $this->Login_m->isThere($email,$password)->num_rows();
		    if ($res>0) {
		    	$getEmail	  = "";
		    	$getUsername  = "";
		    	$getRole    = "";
				$cekUsername  = $this->Login_m->cekUsername($email);
				foreach($cekUsername as $x){
					$getEmail	  = $x->email;
					$getUsername  = $x->username;
					$getRole    = $x->role;
				} 
		    	$data_session = array(
					'email' => $getEmail,
					'username' => $getUsername,
					'level' => $getRole,
					'is_login' => 'TRUE'
				);
				$this->session->set_userdata($data_session);
				echo 1;
		    }else{
		    	echo 0;
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
	    	$length = 8;
            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            $encrypted = sha1($randomString);
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