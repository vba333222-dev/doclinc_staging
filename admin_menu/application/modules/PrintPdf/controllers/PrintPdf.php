<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	require FCPATH.'vendor/autoload.php';
	class PrintPdf extends CI_Controller { 
		function __construct(){
			parent::__construct(); 
			if ($this->session->userdata('is_login') == FALSE) {
				redirect('/', 'refresh');
			}
			if ($this->session->userdata('level') !== 'admin') {
				redirect('home', 'refresh');
			}
		}
	    public function index()
	    {
			$mpdf = new \Mpdf\Mpdf();
			$mpdf->WriteHTML('<h1>hello dunya</h1>');
			$mpdf->Output();
	    }
	}
