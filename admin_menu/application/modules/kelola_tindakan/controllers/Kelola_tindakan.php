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

		public function edit_tindakan()
		{
			$konsul_id = $this->input->post('konsul_id');
			$saran = $this->input->post('saran');
			$this->Kelola_tindakan_m->edit_tindakan($konsul_id, $saran);
			$this->session->set_flashdata('success', 'Data tindakan berhasil diperbarui.');
			redirect('kelola_tindakan', 'refresh');
		}

		public function delete_tindakan()
		{
			$konsul_id = $this->input->post('konsul_id');
			$remark = $this->input->post('remark');
			$this->Kelola_tindakan_m->delete_tindakan($konsul_id, $remark);
			$this->session->set_flashdata('success', 'Data tindakan berhasil dinonaktifkan.');
			redirect('kelola_tindakan', 'refresh');
		}
	}
