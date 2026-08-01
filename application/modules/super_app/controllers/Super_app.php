<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Super_app extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Super_app_m');
		}

		public function index(){
			if ($this->input->method(TRUE) !== 'GET') {
				show_404();
				return;
			}
			$this->load->view('super_app_v');
		}
		public function b(){
			if ($this->input->method(TRUE) !== 'GET') {
				show_404();
				return;
			}
			$this->load->view('super_app_b_v');
		}
	}
