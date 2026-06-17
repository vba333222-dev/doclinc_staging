<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Error_404 extends MX_Controller{
		function __construct(){
			parent::__construct();
			if($this->session->userdata('is_login')==FALSE)
	        {
	        	redirect('/','refresh');
	        }
		}

		public function index(){
			$this->load->view('commons/header');
			$this->load->view('error_404');
			$this->load->view('commons/footer');
		}
	}