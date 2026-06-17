<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Super_app extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Super_app_m');
		}

		public function index(){
			$this->load->view('super_app_v');
		}
		public function b(){
			$this->load->view('super_app_b_v');
		}
	}