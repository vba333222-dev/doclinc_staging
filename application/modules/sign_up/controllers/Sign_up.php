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
		    $this->form_validation->set_rules('nama_lengkap', 'Nama Lengkap', 'required');
		    $this->form_validation->set_rules('no_hp', 'Nomor Handphone', 'required');
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[users.email]');
            $this->form_validation->set_rules('username', 'Username', 'required|is_unique[users.username]');
            $this->form_validation->set_rules('password', 'Password', 'required|min_length[6]');
            $this->form_validation->set_rules('k_password', 'Konfirmasi Password', 'required|matches[password]');
            if ($this->form_validation->run() == FALSE) {
                // Kirim response JSON jika validasi gagal
                echo json_encode([
                    'success' => false,
                    'message' => validation_errors()
                ]);
            } else {
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
                        'message' => 'Registrasi berhasil!'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Registrasi gagal, coba lagi nanti.'
                    ]);
                }
            }
		}
	}
