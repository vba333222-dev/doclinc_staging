<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Splash_screen extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Splash_screen_m');
		}

		public function index(){
			$this->load->view('splash_screen_v');
		}
		public function b(){
			$this->load->view('splash_screen_b_v');
		}
	}