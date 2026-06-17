<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Kelola_layanan_kesehatan extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Kelola_layanan_kesehatan_m');
			if($this->session->userdata('is_login')==FALSE)
	        {
	        	redirect('/','refresh');
	        }
		}
		public function index(){
			$this->session->set_flashdata('title', 'Kelola Layanan Kesehatan');
			$this->session->set_flashdata('active_tab_kelola_layanan_kesehatan', 'active');
			unset($_SESSION['active_tab_dashboard']);
			$year = date('Y');
			$month = date('m');
			$x['data_konsultasi'] = $this->Kelola_layanan_kesehatan_m->get_data_layanan_kesehatan();
			$this->load->view('commons/header');
			$this->load->view('kelola_layanan_kesehatan_v',$x);
			$this->load->view('commons/footer');
		}
	}