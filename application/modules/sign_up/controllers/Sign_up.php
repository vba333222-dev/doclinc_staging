<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Sign_up extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Sign_up_m');
			$this->load->helper('password_compat');
		}
		public function index(){
			$this->load->view('sign_up_v');
		}
		public function save_user()
		{
			if ($this->input->method(TRUE) !== 'POST') {
				$this->output
					->set_content_type('application/json')
					->set_status_header(405)
					->set_output(json_encode(array(
						'success' => false,
						'safe_error_code' => 'method_not_allowed',
						'message' => 'Metode permintaan tidak didukung.',
					)));
				return;
			}
		    $this->form_validation->set_rules('nama_lengkap', 'Nama Lengkap', 'required', array('required' => 'Nama lengkap wajib diisi.'));
		    $this->form_validation->set_rules('no_hp', 'Nomor Handphone', 'required', array('required' => 'Nomor HP wajib diisi.'));
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[users.email]', array('required' => 'Email wajib diisi.', 'valid_email' => 'Masukkan email yang valid.', 'is_unique' => 'Email sudah digunakan.'));
            $this->form_validation->set_rules('username', 'Username', 'required|is_unique[users.username]', array('required' => 'Nama pengguna wajib diisi.', 'is_unique' => 'Nama pengguna sudah digunakan.'));
			$this->form_validation->set_rules('password', 'Password', 'required|min_length[8]|max_length[72]', array('required' => 'Password wajib diisi.', 'min_length' => 'Password minimal 8 karakter.', 'max_length' => 'Password maksimal 72 karakter.'));
            $this->form_validation->set_rules('k_password', 'Konfirmasi Password', 'required|matches[password]', array('required' => 'Konfirmasi password wajib diisi.', 'matches' => 'Konfirmasi password tidak sama.'));
            if ($this->form_validation->run() == FALSE) {
                // Kirim response JSON jika validasi gagal
                echo json_encode([
                    'success' => false,
                    'message' => validation_errors()
                ]);
			} else {
				require_once APPPATH . 'libraries/Password_strength_policy.php';
				$password_policy = new Password_strength_policy();
				$password_error = $password_policy->validate(
					(string) $this->input->post('password'),
					(string) $this->input->post('k_password')
				);
				if ($password_error !== null) {
					$this->output->set_content_type('application/json')->set_status_header(422)->set_output(json_encode(array(
						'success' => false,
						'safe_error_code' => 'password_policy_failed',
						'message' => $password_policy->message($password_error),
					)));
					return;
				}
				// Ambil data dari form
                $data = [
                    'nama' => $this->input->post('nama_lengkap'),
                    'email' => $this->input->post('email'),
                    'no_hp' => $this->input->post('no_hp'),
                    'username' => $this->input->post('username'),
                    'password' => doclinc_password_hash((string) $this->input->post('password')),
                    'role' =>'warga',
                    'status' =>'aktif',
                    'created_at' =>date('Y-m-d H:i:s'),
                ];
                // Simpan data ke database menggunakan model
                if ($this->Sign_up_m->save_user($data)) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Akun dibuat.'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Akun belum dapat dibuat. Coba lagi.'
                    ]);
                }
            }
		}
	}
