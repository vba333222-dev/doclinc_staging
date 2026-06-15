<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Kelola_warga extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Kelola_warga_m');
			if($this->session->userdata('is_login')==FALSE)
	        {
	        	redirect('/','refresh');
	        }
		}
		public function index(){
			$this->session->set_flashdata('title', 'Kelola_warga');
			$this->session->set_flashdata('active_tab_kelola_warga', 'active');
			$year = date('Y');
			$month = date('m');
			$x['data_warga'] = $this->Kelola_warga_m->get_data_warga();
			$this->load->view('commons/header');
			$this->load->view('kelola_warga_v',$x);
			$this->load->view('commons/footer');
		}
		public function aktifkan_user()
		{
			$id_user = $this->input->post('id_user');
			$remark_aktif = $this->input->post('remark_aktif');
			$user = $this->session->userdata('username');
			$this->Kelola_warga_m->aktifkan_user($id_user,$user,$remark_aktif);
			$this->session->set_flashdata('success', 'Anda berhasil mengaktifkan user.');
			redirect('kelola_warga','refresh');
		}
		public function nonaktifkan_user()
		{
			$id_user = $this->input->post('id_user');
			$remark_nonaktif = $this->input->post('remark_nonaktif');
			$user = $this->session->userdata('username');
			$this->Kelola_warga_m->nonaktifkan_user($id_user,$user,$remark_nonaktif);
			$this->session->set_flashdata('success', 'Anda berhasil menonaktifkan user.');
			redirect('kelola_warga','refresh');
		}
	}