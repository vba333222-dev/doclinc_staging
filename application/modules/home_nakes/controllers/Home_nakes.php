<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Home_nakes extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Home_nakes_m');
		if ($this->session->userdata('logged_in') != TRUE) {
			redirect('../');
		}
	}
	public function index()
	{

		$uid = $this->session->userdata('id');
		$name = $this->session->userdata('username');
		$d['data_request_completed'] = $this->Home_nakes_m->request_keluhan_completed($uid);
		$d['data_request_accept'] = $this->Home_nakes_m->request_keluhan_accept($uid);
		$d['data_request_new'] = $this->Home_nakes_m->request_keluhan($uid);
		$d['data_user'] = $this->Home_nakes_m->get_location_user($uid);
		$d['profile'] = $this->Home_nakes_m->get_profile_by_id($uid);

		$latitude_dokter = '';
		$longitude_dokter = '';
		foreach ($d['data_user']->result() as $z) {
			$dokternama = $z->name;
			$latitude_dokter = $z->latitude;
			$longitude_dokter = $z->longitude;
		}
		foreach ($d['data_request_new']->result() as $x) {
			$estimation = $this->get_estimation($x->lattitude, $x->longitude, $latitude_dokter, $longitude_dokter);
			$x->distance = $estimation['distance'];
			$x->duration = $estimation['duration'];
		}
		foreach ($d['data_request_accept']->result() as $x) {
			$estimation = $this->get_estimation($x->lattitude, $x->longitude, $x->lattitude_dokter, $x->longitude_dokter);
			$x->distance = $estimation['distance'];
			$x->duration = $estimation['duration'];
		}
		$this->load->view('home_nakes_v', $d);
	}
	public function tes_save_lokasi()
	{
		$this->load->view('tes_save_lokasi');
	}

	public function save_location()
	{
		$ip_address = $this->input->ip_address();
		$data = array(
			'id_user' => $this->input->post('id_user'),
			'name' => $this->input->post('name'),
			'ip_address' => $ip_address,
			'latitude' => $this->input->post('latitude'),
			'longitude' => $this->input->post('longitude'),
			'update_date' => date('Y-m-d H:i:s')
		);
		// Cek apakah IP address sudah ada
		$existing_location = $this->Home_nakes_m->check_ip_exists($ip_address);
		if ($existing_location) {
			// Jika ada, update data
			$this->Home_nakes_m->update_location($data, $ip_address);
			echo json_encode(['status' => 'updated']);
		} else {
			// Jika tidak ada, insert data baru
			$data['create_date'] = date('Y-m-d H:i:s');
			$this->Home_nakes_m->save_location($data);
			echo json_encode(['status' => 'inserted']);
		}
	}
	public function accept_request()
	{
		$id = $this->input->post('id');
		$id_user = $this->input->post('id_user');
		$latitude = $this->input->post('latitude');
		$longitude = $this->input->post('longitude');
		$data = $this->Home_nakes_m->accept_request($id, $id_user, $latitude, $longitude);
		echo json_encode($data);
	}
	public function get_location_user()
	{
		$name = $_SESSION['name'];
		$d['data_user'] = $this->Home_nakes_m->get_location_user($name);
	}
	public function get_estimation($origin_lat, $origin_lng, $dest_lat, $dest_lng)
	{
		$apiKey = 'AIzaSyBTfv2in7EP1cLT71-bVC-66SZsrg4Kr5w';
		$url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins={$origin_lat},{$origin_lng}&destinations={$dest_lat},{$dest_lng}&mode=driving&key={$apiKey}";

		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $url,
			CURLOPT_SSL_VERIFYPEER => false
		]);

		$response = curl_exec($curl);
		curl_close($curl);

		$data = json_decode($response, true);
		if ($data['status'] == 'OK' && $data['rows'][0]['elements'][0]['status'] == 'OK') {
			$distance = $data['rows'][0]['elements'][0]['distance']['text'];
			$duration = $data['rows'][0]['elements'][0]['duration']['text'];
			return [
				'distance' => $distance,
				'duration' => $duration
			];
		} else {
			return [
				'distance' => 'N/A',
				'duration' => 'N/A'
			];
		}
	}

	public function updateprofile()
	{
		$response = ['status' => 'error', 'message' => 'Gagal menyimpan'];

		$id = $this->session->userdata('id');
		$data = [
			'nama'      => $this->input->post('nama_lengkap'),
			'no_hp'     => $this->input->post('no_hp'),
			'tgl' 		=> $this->input->post('tgl_lahir'),
			'gender'   	=> $this->input->post('jk'),
			'alamat'    => $this->input->post('alamat'),
		];

		// Upload foto jika ada
		if (!empty($_FILES['foto']['name'])) {
			$config['upload_path']   = './uploads/profile/';
			$config['allowed_types'] = 'jpg|jpeg|png';
			$config['file_name']     = time() . '_' . $_FILES['foto']['name'];

			$this->load->library('upload', $config);
			if ($this->upload->do_upload('foto')) {
				$uploadData = $this->upload->data();
				$data['foto'] = $uploadData['file_name'];
			} else {
				$response['message'] = $this->upload->display_errors('', '');
				echo json_encode($response);
				return;
			}
		}

		// Simpan lewat model
		if ($this->Home_nakes_m->update_profile($id, $data)) {
			$this->session->set_userdata($data); // Perbarui session
			echo json_encode(['status' => 'success', 'message' => 'Profil berhasil diperbarui']);
		} else {
			echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui profil']);
		}
	}
}
