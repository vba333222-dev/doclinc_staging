<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	require FCPATH.'vendor/autoload.php';
	class PrintPdf extends CI_Controller { 
		function __construct(){
			parent::__construct(); 
		}
	    public function index()
	    {
			$mpdf = new \Mpdf\Mpdf();
			$mpdf->WriteHTML('<h1>hello dunya</h1>');
			$mpdf->Output();
	    }
	}