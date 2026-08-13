<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Kelola_tindakan extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Kelola_tindakan_m');
			if($this->session->userdata('is_login')==FALSE)
	        {
	        	redirect('/','refresh');
			}
			if($this->session->userdata('level') !== 'admin')
			{
				redirect('home','refresh');
			}
		}
		public function index(){
			$this->session->set_flashdata('title', 'Kelola tindakan');
			$this->session->set_flashdata('active_tab_tindakan', 'active');
			unset($_SESSION['active_tab_dashboard']);
			$this->load->view('commons/header');
			$this->load->view('kelola_tindakan_v');
			$this->load->view('commons/footer');
		}

		public function edit_tindakan()
		{
			if (!$this->require_post()) {
				return;
			}
			$this->deny_clinical_mutation();
		}

		public function delete_tindakan()
		{
			if (!$this->require_post()) {
				return;
			}
			$this->deny_clinical_mutation();
		}

		private function deny_clinical_mutation()
		{
			$this->output->set_status_header(403);
			$this->session->set_flashdata('error', 'Data klinis hanya dapat diubah oleh tenaga kesehatan yang menangani.');
			redirect('kelola_tindakan', 'refresh');
		}

		private function require_post()
		{
			if ($this->input->method(TRUE) === 'POST') {
				return true;
			}
			$this->output->set_status_header(405);
			$this->session->set_flashdata('error', 'Metode tidak diizinkan.');
			redirect('kelola_tindakan', 'refresh');
			return false;
		}
	}
