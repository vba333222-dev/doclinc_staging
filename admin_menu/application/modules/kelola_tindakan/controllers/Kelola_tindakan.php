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
		}
		public function index(){
			$this->session->set_flashdata('title', 'Kelola tindakan');
			$this->session->set_flashdata('active_tab_tindakan', 'active');
			unset($_SESSION['active_tab_dashboard']);
			$year = date('Y');
			$month = date('m');
			$x['data_tindakan'] = $this->Kelola_tindakan_m->get_data_tindakan();
			$this->load->view('commons/header');
			$this->load->view('kelola_tindakan_v',$x);
			$this->load->view('commons/footer');
		}
	}