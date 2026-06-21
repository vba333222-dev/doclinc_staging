<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Kelola_keluhan extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Kelola_keluhan_m');
			if($this->session->userdata('is_login')==FALSE)
	        {
	        	redirect('/','refresh');
	        }
		}
		public function index(){
			$this->session->set_flashdata('title', 'Kelola Keluhan');
			$this->session->set_flashdata('active_tab_keluhan', 'active');
			unset($_SESSION['active_tab_dashboard']);
			$year = date('Y');
			$month = date('m');
			$x['data_keluhan'] = $this->Kelola_keluhan_m->get_data_keluhan();
			$this->load->view('commons/header');
			$this->load->view('kelola_keluhan_v',$x);
			$this->load->view('commons/footer');
		}

		public function tambah_keluhan()
		{
			// Ambil ID terakhir dari database
			$last_id = $this->Kelola_keluhan_m->get_last_id();
			// Jika tidak ada data, mulai dari K001
			if ($last_id) {
			    // Ambil angka dari ID terakhir (tanpa huruf "K")
			    $number = (int) substr($last_id, 1);
			    // Tambah 1 untuk ID berikutnya
			    $new_number = $number + 1;
			    // Format dengan padding nol (misal K001, K002, ..., K999, K1000)
			    $id_keluhan = 'K' . str_pad($new_number, 3, '0', STR_PAD_LEFT);
			} else {
			    $id_keluhan = 'K001';
			}
			$kategori = $this->input->post('kategori');
			$namakeluhan = $this->input->post('nama_keluhan');
			$deskripsi = $this->input->post('deskripsi');
			$create_user = $this->session->userdata('username');
			$create_date = date('Y-m-d H:i:s');
			$this->Kelola_keluhan_m->tambah_keluhan($id_keluhan,$kategori,$namakeluhan,$deskripsi,$create_user,$create_date);
			$this->session->set_flashdata('success', 'Anda berhasil menambah data keluhan.');
			redirect('kelola_keluhan','refresh');
		}

		public function edit_keluhan()
		{
			$id_keluhan = $this->input->post('id_keluhan');
			$kategori = $this->input->post('kategori');
			$namakeluhan = $this->input->post('namakeluhan');
			$deskripsi = $this->input->post('deskripsi');
			$modify_user = $this->session->userdata('username');
			$modify_date = date('Y-m-d H:i:s');
			$this->Kelola_keluhan_m->edit_keluhan($id_keluhan,$kategori,$namakeluhan,$deskripsi,$modify_user,$modify_date);
			$this->session->set_flashdata('success', 'Anda berhasil mengubah data keluhan.');
			redirect('kelola_keluhan','refresh');
		}

		public function activate_keluhan()
		{
			$id_keluhan = $this->input->post('id_keluhan');
			$remark = $this->input->post('remark');
			$modify_user = $this->session->userdata('username');
			$modify_date = date('Y-m-d H:i:s');
			$this->Kelola_keluhan_m->activate_keluhan($id_keluhan,$remark,$modify_user,$modify_date);
			$this->session->set_flashdata('success', 'Anda berhasil mengaktifkan data keluhan.');
			redirect('kelola_keluhan','refresh');
		}

		public function delete_keluhan()
		{
			$id_keluhan = $this->input->post('id_keluhan');
			$remark = $this->input->post('remark');
			$modify_user = $this->session->userdata('username');
			$modify_date = date('Y-m-d H:i:s');
			$this->Kelola_keluhan_m->delete_keluhan($id_keluhan,$remark,$modify_user,$modify_date);
			$this->session->set_flashdata('success', 'Anda berhasil menonaktifkan data keluhan.');
			redirect('kelola_keluhan','refresh');
		}
	}
