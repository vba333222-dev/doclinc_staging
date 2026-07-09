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
			if($this->session->userdata('level') !== 'admin')
			{
				redirect('home','refresh');
			}
		}
		public function index(){
			$this->session->set_flashdata('title', 'Monitoring Konsultasi');
			$this->session->set_flashdata('active_tab_konsultasi_kesehatan', 'active');
			unset($_SESSION['active_tab_dashboard']);
			$filters = array(
				'status' => trim((string) $this->input->get('status', TRUE)),
				'puskesmas' => trim((string) $this->input->get('puskesmas', TRUE)),
				'date_from' => trim((string) $this->input->get('date_from', TRUE)),
				'date_to' => trim((string) $this->input->get('date_to', TRUE)),
				'keyword' => trim((string) $this->input->get('keyword', TRUE)),
			);
			$x['filters'] = $filters;
			$x['puskesmas_options'] = $this->Konsultasi_kesehatan_m->get_puskesmas_options();
			$x['data_konsultasi'] = $this->Konsultasi_kesehatan_m->get_data_konsul($filters);
			$this->load->view('commons/header');
			$this->load->view('konsultasi_kesehatan_v',$x);
			$this->load->view('commons/footer');
		}
	}
