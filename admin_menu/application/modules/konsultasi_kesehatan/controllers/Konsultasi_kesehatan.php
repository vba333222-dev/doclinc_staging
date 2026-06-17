<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Konsultasi_kesehatan extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Konsultasi_kesehatan_m');
			if($this->session->userdata('is_login')==FALSE)
	        {
	        	redirect('/','refresh');
	        }
		}
		public function index(){
			$this->session->set_flashdata('title', 'Kelola Konsultasi Kesehatan');
			$this->session->set_flashdata('active_tab_konsultasi_kesehatan', 'active');
			unset($_SESSION['active_tab_dashboard']);
			$year = date('Y');
			$month = date('m');
			$x['data_konsultasi'] = $this->Konsultasi_kesehatan_m->get_data_konsul();
			$this->load->view('commons/header');
			$this->load->view('konsultasi_kesehatan_v',$x);
			$this->load->view('commons/footer');
		}
	}