<?php
	defined('BASEPATH') OR exit('No direct script access allowed');
	class Landing_page extends MX_Controller{
		function __construct(){
			parent::__construct();
			$this->load->model('Landing_page_m');
			if($this->session->userdata('is_login')==FALSE)
	        {
				redirect('/','refresh');
	        }
			if ($this->session->userdata('level') !== 'admin') {
				redirect('home', 'refresh');
			}
		}
		// rowcode for home
		public function home(){
			$this->session->set_flashdata('title', 'Landing Page - Home');
			$x['data_home'] = $this->Landing_page_m->getDataHome();
			$this->load->view('commons/header');
			$this->load->view('home',$x);
			$this->load->view('commons/footer');
		}
		public function update_tagline(){
			$tagline = $this->input->post('tagline');
			$data = $this->Landing_page_m->update_tagline($tagline);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Tagline baru saja kamu perbarui.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/home');
		}
		// rowcode for home

		// rowcode for about
		public function about(){
			$this->session->set_flashdata('title', 'Landing Page - About Us');
			$x['data_about'] = $this->Landing_page_m->getDataAbout();
			$this->load->view('commons/header');
			$this->load->view('about',$x);
			$this->load->view('commons/footer');
		}
		public function about_update_desc(){
			$desc = $this->input->post('desc');
			$data = $this->Landing_page_m->about_update_desc($desc);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Deskripsi tentang kami baru saja kamu perbarui.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/about');
		}
		public function about_update_visi(){
			$visi = $this->input->post('visi');
			$data = $this->Landing_page_m->about_update_visi($visi);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Visi baru saja kamu perbarui.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/about');
		}
		public function about_update_misi(){
			$misi = $this->input->post('misi');
			$data = $this->Landing_page_m->about_update_misi($misi);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Misi baru saja kamu perbarui.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/about');
		}
		public function about_update_pict(){
			$date = date('Ymd_His_');
			$temp   = $_FILES['pict']['tmp_name'];
	        $name   = $_FILES['pict']['name'];
	        $size   = $_FILES['pict']['size'];
	        $type   = $_FILES['pict']['type'];
	        $datename = $date.$name;
	        $folder = "../assets/img/";
	        $target_file = $folder.$datename;
	        move_uploaded_file($temp, $target_file);

	        $data = $this->Landing_page_m->about_update_pict($datename);
			echo json_encode($data);	
			
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Gambar baru saja kamu perbarui.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/about');
		}
		// rowcode for about

		// rocode for news & events
		public function news_events(){
			$this->session->set_flashdata('title', 'Landing Page - News & Events');
			$x['data_news_events'] = $this->Landing_page_m->getDataNewsEvents();
			$this->load->view('commons/header');
			$this->load->view('news_events',$x);
			$this->load->view('commons/footer');
		}
		public function news_events_add(){
			$title = $this->input->post('title');
			$content = addslashes($this->input->post('content'));
			$preview = addslashes($this->input->post('preview'));
			$status = $this->input->post('status');

			$date = date('Ymd_');
			$temp   = $_FILES['gambar']['tmp_name'];
	        $name   = str_replace(' ', '_', $_FILES['gambar']['name']);
	        $size   = $_FILES['gambar']['size'];
	        $type   = $_FILES['gambar']['type'];
	        $filename = $date.$name;
	        // $filename = $name;
	        $folder = "../assets/img/uploads/news/";
	        $target_file = $folder.$filename;
	        move_uploaded_file($temp, $target_file);

			$data = $this->Landing_page_m->news_events_add($title,$content,$preview,$filename,$status);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menambahkan news & events baru.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/news_events');
		}
		public function news_events_update(){
			$id = $this->input->post('idnya');
			$title = $this->input->post('title');
			$thumbnail = $this->input->post('gambar_now');
			$content = addslashes($this->input->post('content'));
			$preview = addslashes($this->input->post('preview'));
			$status = $this->input->post('status');


			$date = date('Ymd_');
			$temp   = $_FILES['gambar']['tmp_name'];
	        $name   = $_FILES['gambar']['name'];
	        $size   = $_FILES['gambar']['size'];
	        $type   = $_FILES['gambar']['type'];
	        $filename = $date.$name;
	        // $filename = $name;
	        $folder = "../assets/img/uploads/news/";
	        $target_file = $folder.$filename;
	        move_uploaded_file($temp, $target_file);

			$data = $this->Landing_page_m->news_events_update($id,$title,$content,$preview,$filename,$status);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja perbarui news & events.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/news_events');
		}
		public function news_events_delete(){
			$id = $this->input->post('idnya');

			$data = $this->Landing_page_m->news_events_delete($id);
			echo json_encode($data);
			$info = '<div class="alert alert-info border-info shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menghapus 1 file news & events.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/news_events');
		}
		// rowcode for news & events
		
		// rowcode for services
		public function services(){
			$this->session->set_flashdata('title', 'Landing Page - Our Services');
			$x['data_services'] = $this->Landing_page_m->getDataServices();
			$this->load->view('commons/header');
			$this->load->view('services',$x);
			$this->load->view('commons/footer');
		}
		public function service_detail($id,$service){
			$this->session->set_flashdata('title', 'Our Services - '.$service);
			$x['nama_service'] = $service;
			$x['data_service_detail'] = $this->Landing_page_m->service_detail($id,$service);
			$this->load->view('commons/header');
			$this->load->view('service_detail',$x);
			$this->load->view('commons/footer');
		}
		public function service_detail_sub($id,$title){
			$this->session->set_flashdata('title', $title);
			$x['nama_title'] = $title;
			$x['data_service_detail_sub'] = $this->Landing_page_m->service_detail_sub($id,$title);
			$x['data_harga_all'] = $this->Landing_page_m->getDataHarga();
			$x['data_alat_test_all'] = $this->Landing_page_m->getDataAlatTest();
			$this->load->view('commons/header');
			$this->load->view('service_detail_sub',$x);
			$this->load->view('commons/footer');
		}
		public function service_update(){
			$id = $this->input->post('idnya');
			$service = addslashes($this->input->post('service'));
			$short_desc = addslashes($this->input->post('short_desc'));
			$no_wa = $this->input->post('no_wa');
			$data = $this->Landing_page_m->service_update($id,$service,$short_desc,$no_wa);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja perbarui layanan.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/services');
		}
		public function service_add_new(){
			$service = addslashes($this->input->post('service'));
			$short_desc = addslashes($this->input->post('short_desc'));
			$no_wa = $this->input->post('no_wa');
			$data = $this->Landing_page_m->service_add_new($service,$short_desc,$no_wa);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menambahkan layanan.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/services');
		}
		public function service_delete($id){
			$data = $this->Landing_page_m->service_delete($id);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menghapus layanan.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/services');
		}
		public function service_detail_update($id,$url1,$url2){
			$title = addslashes($this->input->post('title'));
			$content = $this->input->post('content');
			$data = $this->Landing_page_m->service_detail_update($id,$title,$content);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja perbarui layanan.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/service_detail/'.$url1.'/'.$url2);
		}
		public function service_detail_add_new($id,$url1,$url2){
			$title = addslashes($this->input->post('title'));
			$content = $this->input->post('content');
			$data = $this->Landing_page_m->service_detail_add_new($id,$title,$content);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menambahkan layanan baru.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/service_detail/'.$url1.'/'.$url2);
		}
		public function service_detail_delete($id,$url1,$url2){
			$data = $this->Landing_page_m->service_detail_delete($id);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menghapus layanan.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/service_detail/'.$url1.'/'.$url2);
		}
		public function service_detail_sub_update($id,$url1,$url2){
			$paket = addslashes($this->input->post('paket'));
			$desc = $this->input->post('desc');
			$data = $this->Landing_page_m->service_detail_sub_update($id,$paket,$desc);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja perbarui layanan.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/service_detail_sub/'.$url1.'/'.$url2);
		}
		public function service_detail_sub_add_new($id,$url1,$url2){
			$paket = addslashes($this->input->post('paket'));
			$desc = $this->input->post('desc');
			$data = $this->Landing_page_m->service_detail_sub_add_new($id,$paket,$desc);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menambahkan paket.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/service_detail_sub/'.$url1.'/'.$url2);
		}
		public function service_detail_sub_delete($id,$url1,$url2){
			$data = $this->Landing_page_m->service_detail_sub_delete($id);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menghapus paket.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/service_detail_sub/'.$url1.'/'.$url2);
		}
		public function service_harga_update($id,$url1,$url2){
			$nama_paket = $this->input->post('nama_paket');
			$harga = $this->input->post('harga');
			$harga2 = $this->input->post('harga2');
			$exp_diskon = $this->input->post('exp_diskon_');
			$data = $this->Landing_page_m->service_harga_update($id,$nama_paket,$harga,$harga2,$exp_diskon);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja perbarui harga.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/service_detail_sub/'.$url1.'/'.$url2);
		}
		public function service_harga_add_new($url1,$url2){
			$idservicesubdetail = $this->input->post('idservicesubdetail');
			$nama_paket = $this->input->post('nama_paket');
			$harga = $this->input->post('harga');
			$harga2 = $this->input->post('harga2');
			$exp_diskon = $this->input->post('exp_diskon');
			$data = $this->Landing_page_m->service_harga_add_new($idservicesubdetail,$nama_paket,$harga,$harga2,$exp_diskon);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menambahkan investasi.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/service_detail_sub/'.$url1.'/'.$url2);
		}
		public function service_harga_delete($id,$url1,$url2){
			$data = $this->Landing_page_m->service_harga_delete($id);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menghapus investasi.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/service_detail_sub/'.$url1.'/'.$url2);
		}
		// rowcode for services

		// rowcode for contact us
		public function contact_us(){ 
			$this->session->set_flashdata('title', 'Landing Page - Contact Us');
			$x['data_contact'] = $this->Landing_page_m->getDataContactus();
			$this->load->view('commons/header');
			$this->load->view('contact_us',$x);
			$this->load->view('commons/footer');
		}
		public function contact_address_update(){
			$address = $this->input->post('address');
			$data = $this->Landing_page_m->contact_address_update($address);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kontak alamat baru saja kamu perbarui.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/contact_us');
		}
		public function contact_address2_update(){
			$address2 = $this->input->post('address2');
			$data = $this->Landing_page_m->contact_address2_update($address2);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kontak alamat 2 baru saja kamu perbarui.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/contact_us');
		}
		public function contact_email_update(){
			$email = $this->input->post('email');
			$data = $this->Landing_page_m->contact_email_update($email);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kontak email baru saja kamu perbarui.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/contact_us');
		}
		public function contact_phone_update(){
			$phone = $this->input->post('phone');
			$data = $this->Landing_page_m->contact_phone_update($phone);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kontak telepon baru saja kamu perbarui.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/contact_us');
		}
		public function contact_maps_update(){
			$gmap = $this->input->post('gmap');
			$data = $this->Landing_page_m->contact_maps_update($gmap);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kontak Google Maps baru saja kamu perbarui.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/contact_us');
		}
		public function contact_maps2_update(){
			$gmap2 = $this->input->post('gmap2');
			$data = $this->Landing_page_m->contact_maps2_update($gmap2);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kontak Google Maps2 baru saja kamu perbarui.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/contact_us');
		}
		// rowcode for contact us

		// rowcode for partner
		public function partner(){ 
			$this->session->set_flashdata('title', 'Landing Page - Partner');
			$x['data_partner'] = $this->Landing_page_m->getDataPartner();
			$this->load->view('commons/header');
			$this->load->view('partner',$x);
			$this->load->view('commons/footer');
		}
		public function add_partner(){ 
			$alt_name=$this->input->post('alt_name');  
			$date = date('Ymd_His_');
			$temp   = $_FILES['logo_partner']['tmp_name'];
	        $name   = $_FILES['logo_partner']['name'];
	        $size   = $_FILES['logo_partner']['size'];
	        $type   = $_FILES['logo_partner']['type'];
	        $datename = $date.$name; 
	        $folder = "../assets/img/";
	        $target_file = $folder.$datename;
	        move_uploaded_file($temp, $target_file);

	        $data = $this->Landing_page_m->add_partner($datename, $alt_name);
			echo json_encode($data);	
			
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Tambah Partner Berhasil .</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/partner');
		}
		public function partner_delete($id){
			$data = $this->Landing_page_m->partner_delete($id);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menghapus Partner.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/partner');
		}
		public function partner_update(){ 
			$id=$this->input->post('idnya'); 
			$logo=$this->input->post('logo_edit');  
			$alt_name=$this->input->post('alt_name_edit'); 

			$date = date('Ymd_His_');
			$temp   = $_FILES['logo_edit2']['tmp_name'];
	        $name   = $_FILES['logo_edit2']['name'];
	        $size   = $_FILES['logo_edit2']['size'];
	        $type   = $_FILES['logo_edit2']['type'];
	        $datename = $date.$name; 
	        $folder = "../assets/img/";
	        $target_file = $folder.$datename;
			if ($name!=''){
				move_uploaded_file($temp, $target_file);
				$logo=$datename;
			}else{
				$logo=$logo;
			}

	        $data = $this->Landing_page_m->partner_update($id, $logo, $alt_name);
			echo json_encode($data);	
			
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Update Partner Berhasil .</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/partner'); 
		}
		// rowcode for partner

		// rowcode for the team
		public function the_team(){
			$this->session->set_flashdata('title', 'Landing Page - The Team');
		 	$x['data_the_team'] = $this->Landing_page_m->getDataTheTeam();
		 	$x['data_level'] = $this->Landing_page_m->getDataLevel();
			$this->load->view('commons/header');
			$this->load->view('the_team',$x);
			$this->load->view('commons/footer'); 
		} 
		public function add_the_team(){ 
			$nama=addslashes($this->input->post('nama'));  
			$jabatan=addslashes($this->input->post('jabatan'));
			$no_sipp=addslashes($this->input->post('no_sipp'));
			$quotes=addslashes($this->input->post('quotes'));  
			$level=$this->input->post('level');
			$email=$this->input->post('email');
			$username=$this->input->post('username');
			$password=sha1($this->input->post('password'));
			$ig=$this->input->post('ig');
			$twitter=$this->input->post('twitter');  
			$fb=$this->input->post('fb');   
			$date = date('Ymd_His_');
			$temp   = $_FILES['avatar']['tmp_name'];
	        $name   = $_FILES['avatar']['name'];
	        $size   = $_FILES['avatar']['size'];
	        $type   = $_FILES['avatar']['type'];
	        $datename = $date.$name; 
	        $folder = "../assets/img/testimonials/";
	        $target_file = $folder.$datename;

	        $temp_sign   = $_FILES['signature']['tmp_name'];
	        $name_sign   = $_FILES['signature']['name'];
	        $size_sign   = $_FILES['signature']['size'];
	        $type_sign   = $_FILES['signature']['type'];
	        $datename_sign = $date.$name_sign; 
	        $folder_sign = "assets/img/";
	        $target_file_sign = $folder_sign.$datename_sign;

	        $cek_username = $this->Landing_page_m->cek_username($username);
			$valid_username = $cek_username->row_array()['username'];
			if ($valid_username==NULL) {
		        move_uploaded_file($temp, $target_file);

		        if ($temp_sign!=''){
					move_uploaded_file($temp_sign, $target_file_sign);
				}

		        $data = $this->Landing_page_m->add_the_team($nama, $jabatan, $no_sipp, $quotes, $level, $email, $username, $password, $ig, $twitter, $fb, $datename, $datename_sign);
				echo json_encode($data);	
				
				$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
						  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
						    <span aria-hidden="true">&times;</span>
						  </button>
						  <p class="font-weight-bold mb-0">Berhasil!</p>
						  <hr>
						  <p class="mb-0">Kamu baru saja menambahkan team.</p>
						</div>';
		    	$this->session->set_flashdata('info',$info);
			}else{
				$info = '<div class="alert alert-danger border-danger shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
						  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
						    <span aria-hidden="true">&times;</span>
						  </button>
						  <p class="font-weight-bold mb-0">Gagal!</p>
						  <hr>
						  <p class="mb-0">Username sudah digunakan.</p>
						</div>';
		    	$this->session->set_flashdata('info',$info);
			}
			redirect('landing_page/the_team');
		}
		public function the_team_delete($id,$username){
			$data = $this->Landing_page_m->the_team_delete($id,$username);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menghapus Team.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/the_team');
		}
		public function the_team_update(){
			$id=$this->input->post('idnya');
			$nama=addslashes($this->input->post('nama_')); 
			$jabatan=addslashes($this->input->post('jabatan_')); 
			$no_sipp=addslashes($this->input->post('no_sipp_')); 
			$quotes=addslashes($this->input->post('quotes_')); 
			$email=$this->input->post('email_');
			$username=$this->input->post('username_');
			$password=$this->input->post('password_');
			$level=$this->input->post('level_');
			$ig=$this->input->post('ig_');
			$twitter=$this->input->post('twitter_'); 
			$fb=$this->input->post('fb_'); 
		    $avatar=$this->input->post('avatar_');   
		    $signature=$this->input->post('signature_');   
			$date = date('Ymd_His_');
			$temp   = $_FILES['avatar2_']['tmp_name'];
	        $name   = $_FILES['avatar2_']['name'];
	        $size   = $_FILES['avatar2_']['size'];
	        $type   = $_FILES['avatar2_']['type'];
	        $datename = $date.$name; 
	        $folder = "../assets/img/testimonials/";
	        $target_file = $folder.$datename;
			if ($name!=''){
				move_uploaded_file($temp, $target_file);
				$avatar=$datename;
			}else{
				$avatar=$avatar;
			} 

			$temp_sign   = $_FILES['signature2_']['tmp_name'];
	        $name_sign   = $_FILES['signature2_']['name'];
	        $size_sign   = $_FILES['signature2_']['size'];
	        $type_sign   = $_FILES['signature2_']['type'];
	        $datename_sign = $date.$name_sign; 
	        $folder_sign = "assets/img/";
	        $target_file_sign = $folder_sign.$datename_sign;
	        if ($name_sign!=''){
				move_uploaded_file($temp_sign, $target_file_sign);
				$signature=$datename_sign;
			}else{
				$signature=$signature;
			}
 
	        $data = $this->Landing_page_m->the_team_update($id, $nama, $jabatan, $no_sipp, $quotes, $ig, $twitter, $fb, $avatar, $signature, $email, $username, $password, $level);
			echo json_encode($data);	
			
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Update Team Berhasil .</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/the_team'); 
		}
		// rowcode for the team

		// rowcode for faq
		public function f_a_q(){
			$this->session->set_flashdata('title', 'Landing Page - FAQ');
			$x['data_faq'] = $this->Landing_page_m->getDataFaq();
			$this->load->view('commons/header');
			$this->load->view('f_a_q',$x);
			$this->load->view('commons/footer');
		} 
		public function add_faq(){ 
			$question=addslashes($this->input->post('question'));  
			$answer=addslashes($this->input->post('answer'));    

	        $data = $this->Landing_page_m->add_faq($question, $answer);
			echo json_encode($data);	
			
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Tambah FAQ Berhasil .</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/f_a_q');
		}
		public function faq_update(){ 
			$id = $this->input->post('idnya');
			$question = addslashes($this->input->post('question_'));
			$answer = addslashes($this->input->post('answer_'));
			$data = $this->Landing_page_m->faq_update($id,$question,$answer);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja perbarui FAQ.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/f_a_q');
		}
		public function delete_faq($id){ 
			$data = $this->Landing_page_m->delete_faq($id);
			echo json_encode($data);
			$info = '<div class="alert alert-info border-info shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menghapus 1 file FAQ.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/f_a_q');
		} 
		// rowcode for faq

		// rowcode for portofolio
		public function portofolio(){
			$this->session->set_flashdata('title', 'Landing Page - Portofolio');
		 	$x['data_portofolio'] = $this->Landing_page_m->getDataPortofolio();
			$this->load->view('commons/header');
			$this->load->view('portofolio',$x);
			$this->load->view('commons/footer'); 
		} 
		public function add_portofolio(){ 
			$title=addslashes($this->input->post('title'));
			$description=addslashes($this->input->post('description'));
			$status=$this->input->post('status');
			$date = date('Ymd_His_');
			$temp   = $_FILES['pict']['tmp_name'];
	        $name   = $_FILES['pict']['name'];
	        $size   = $_FILES['pict']['size'];
	        $type   = $_FILES['pict']['type'];
	        $datename = $date.$name; 
	        $folder = "../assets/img/uploads/portofolio/";
	        $target_file = $folder.$datename;
			
	        move_uploaded_file($temp, $target_file);

	        $data = $this->Landing_page_m->add_portofolio($title, $description, $datename, $status);
			echo json_encode($data);	
			
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja berhasil menambahkan portofolio.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/portofolio');
		}
		public function portofolio_update(){ 
			$id=$this->input->post('idnya');
			$title=addslashes($this->input->post('title'));
			$description=addslashes($this->input->post('description'));
			$status=$this->input->post('status');
			$pict=$this->input->post('pictnya');
			$date = date('Ymd_His_');
			$temp   = $_FILES['pict']['tmp_name'];
	        $name   = $_FILES['pict']['name'];
	        $size   = $_FILES['pict']['size'];
	        $type   = $_FILES['pict']['type'];
	        $datename = $date.$name; 
	        $folder = "../assets/img/uploads/portofolio/";
	        $target_file = $folder.$datename;
			if ($name!='') {
				move_uploaded_file($temp, $target_file);
				$pict = $datename;
			}else{
				$pict = $pict;
			}

			$data = $this->Landing_page_m->portofolio_update($id, $title, $description, $pict, $status);
			echo json_encode($data);	
			
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja berhasil mengubah portofolio.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/portofolio');
		}
		public function portofolio_delete($id){
			$data = $this->Landing_page_m->portofolio_delete($id);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menghapus portofolio.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/portofolio');
		}
		// rowcode for portofolio

		public function service_harga_detail_delete_alat($id,$url1,$url2)
		{
			$data = $this->Landing_page_m->service_harga_detail_delete_alat($id);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menghapus alat test yang ada di paket tersebut.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/service_detail_sub/'.$url1.'/'.$url2);
		}
		public function service_harga_detail_add_alat($url1,$url2)
		{
			$id_harga = $this->input->post('idharga');
			$alat_test = $this->input->post('alat_test');
			$data = $this->Landing_page_m->service_harga_detail_add_alat($id_harga,$alat_test);
			echo json_encode($data);
			$info = '<div class="alert alert-success border-success shadow-sm mb-0 animate__animated animate__bounceInUp" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
					    <span aria-hidden="true">&times;</span>
					  </button>
					  <p class="font-weight-bold mb-0">Berhasil!</p>
					  <hr>
					  <p class="mb-0">Kamu baru saja menambahkan alat test di paket tersebut.</p>
					</div>';
	    	$this->session->set_flashdata('info',$info);
			redirect('landing_page/service_detail_sub/'.$url1.'/'.$url2);
		}
	}
