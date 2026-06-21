<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Kelola_news_feed extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Kelola_newsfeed_m');
			if($this->session->userdata('is_login')==FALSE)
	        {
	        	redirect('/','refresh');
	        }
		}
		public function index(){
			$this->session->set_flashdata('title', 'Kelola news_feed');
			$this->session->set_flashdata('active_tab_news_feed', 'active');
			unset($_SESSION['active_tab_dashboard']);
			$year = date('Y');
			$month = date('m');
 			$x['data_news_feed'] = $this->Kelola_newsfeed_m->get_data_news_feed();
			$this->load->view('commons/header');
 			$this->load->view('kelola_news_feed_v',$x);
			$this->load->view('commons/footer');
		}
		public function get_news_feed()
		{
		    $id=$this->input->post('feedId');
		    $data=$this->Kelola_newsfeed_m->get_news_feed($id);
		    $this->output->set_content_type('application/json')->set_output(json_encode($data));
		}
        public function tambah()
        {
            $upload_path = FCPATH . 'uploads/feeds/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, TRUE);
            }
            $config['upload_path']   = $upload_path;
            $config['allowed_types'] = 'jpg|png|jpeg';
            $config['max_size']      = 2048;
            $config['encrypt_name']  = TRUE;
            $config['detect_mime']   = TRUE;
            $config['mod_mime_fix']  = TRUE;
            $config['remove_spaces'] = TRUE;
            $this->load->library('upload', $config);
            if ($this->upload->do_upload('gambar')) {
                $data = $this->input->post();
                $data ['subject']= $this->input->post('subject');
                $data['gambar'] = $this->upload->data('file_name');
                $data['create_at'] = date('Y-m-d H:i:s');
				$data['create_user'] =$this->session->userdata("username");
                $data ['status']= $this->input->post('status');
                $this->Kelola_newsfeed_m->insert_news($data);
                $this->session->set_flashdata('success', 'Anda berhasil menambah data.');
            } else {
                $this->session->set_flashdata('error', strip_tags($this->upload->display_errors('', '')));
            }
			redirect('kelola_news_feed','refresh');
        }
        public function edit()
        {
            $data = $this->input->post();
            $gambar='';
            if (!empty($_FILES['gambar']['name'])) {
                $upload_path = FCPATH . 'uploads/feeds/';
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0755, TRUE);
                }
                $config['upload_path']   = $upload_path;
                $config['allowed_types'] = 'jpg|png|jpeg';
                $config['max_size']      = 2048;
                $config['encrypt_name']  = TRUE;
                $config['detect_mime']   = TRUE;
                $config['mod_mime_fix']  = TRUE;
                $config['remove_spaces'] = TRUE;
                $this->load->library('upload', $config);
                if ($this->upload->do_upload('gambar')) {
                    $gambar = $this->upload->data('file_name');
                } else {
                    $this->session->set_flashdata('error', strip_tags($this->upload->display_errors('', '')));
                    redirect('kelola_news_feed','refresh');
                }
            }
            $feedId= $this->input->post('feedId_edit');
            $subject= $this->input->post('subject_edit');
            $status= $this->input->post('status_edit');
            $this->Kelola_newsfeed_m->update_news($feedId,$gambar,$subject,$status);
			$this->session->set_flashdata('success', 'Anda berhasil edit data.');
			redirect('kelola_news_feed','refresh');
        }
        public function delete()
        {
            $id = $this->input->post('feedId');
            $this->Kelola_newsfeed_m->delete_news($id);
			$this->session->set_flashdata('success', 'Anda berhasil menghapus data.');
			redirect('kelola_news_feed','refresh');
        }
	}
