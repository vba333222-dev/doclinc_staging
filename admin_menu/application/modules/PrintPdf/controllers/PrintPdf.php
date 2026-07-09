<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
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
		private function load_pdf_dependency()
		{
			$autoload = FCPATH . 'vendor/autoload.php';
			if (!is_file($autoload)) {
				return FALSE;
			}

			require_once $autoload;
			return class_exists('Mpdf\Mpdf');
		}

		private function pdf_unavailable()
		{
			$this->output
				->set_status_header(503)
				->set_content_type('text/html', 'utf-8')
				->set_output('<h1>PDF tidak tersedia</h1><p>Modul PDF belum tersedia di lingkungan ini.</p>');
		}

	    public function index()
	    {
			if (!$this->load_pdf_dependency()) {
				$this->pdf_unavailable();
				return;
			}

			$mpdf = new \Mpdf\Mpdf();
			$mpdf->WriteHTML('<h1>hello dunya</h1>');
			$mpdf->Output();
	    }
	}
