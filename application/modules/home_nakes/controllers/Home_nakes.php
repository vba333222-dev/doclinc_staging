<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Home_nakes extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Home_nakes_m');
		$this->load->helper('request_authz');
		$this->load->helper('visit_routing');
		$this->load->helper('notification');
		if ($this->session->userdata('logged_in') != TRUE) {
			if (in_array($this->router->fetch_method(), array('update_visit_location', 'update_visit_status'), true)) {
				$this->output
					->set_content_type('application/json')
					->set_status_header(401)
					->set_output(json_encode(['status' => 'error', 'message' => 'Login diperlukan']));
				$this->output->_display();
				exit;
			}
			redirect('login');
		}
	}
	public function index()
	{
		if (!$this->require_dokter_session()) {
			return;
		}

		$uid = $this->session->userdata('id');
		$name = $this->session->userdata('username');
		$d['profile'] = $this->Home_nakes_m->get_profile_by_id($uid);
		$puskesmas_code = trim((string) (isset($d['profile']['remark']) ? $d['profile']['remark'] : ''));
		if ($puskesmas_code === '') {
			$puskesmas_code = trim((string) $this->session->userdata('remark'));
		}
		if ($puskesmas_code !== '') {
			$this->session->set_userdata('remark', $puskesmas_code);
		}
		if (empty($puskesmas_code)) {
			log_message('error', 'Akun Puskesmas/Nakes belum memiliki kode puskesmas: ' . $uid);
		}
		$d['data_request_completed'] = $this->Home_nakes_m->request_keluhan_completed($uid);
		$d['data_request_accept'] = $this->Home_nakes_m->request_keluhan_accept($uid);
		$d['data_request_new'] = $this->Home_nakes_m->request_keluhan($uid, $puskesmas_code);
		$d['data_user'] = $this->Home_nakes_m->get_location_user($uid);

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
		if (!$this->require_post_json()) {
			return;
		}
		$ip_address = $this->input->ip_address();
		$data = array(
			'id_user' => $this->session->userdata('id'),
			'name' => $this->session->userdata('username'),
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
			$this->output->set_output(json_encode(['status' => 'updated']));
		} else {
			// Jika tidak ada, insert data baru
			$data['create_date'] = date('Y-m-d H:i:s');
			$this->Home_nakes_m->save_location($data);
			$this->output->set_output(json_encode(['status' => 'inserted']));
		}
	}
	public function accept_request()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']));
			return;
		}
		if ($this->session->userdata('role') !== 'dokter') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$id = (int) $this->input->post('id');
		$id_user = $this->session->userdata('id');
		$profile = $this->Home_nakes_m->get_profile_by_id($id_user);
		$puskesmas_code = trim((string) (isset($profile['remark']) ? $profile['remark'] : ''));
		if ($puskesmas_code === '') {
			$puskesmas_code = trim((string) $this->session->userdata('remark'));
		}
		if ($puskesmas_code !== '') {
			$this->session->set_userdata('remark', $puskesmas_code);
		}
		$latitude = $this->input->post('latitude');
		$longitude = $this->input->post('longitude');

		if (empty($id) || empty($id_user)) {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Data request tidak lengkap']));
			return;
		}

		$result = $this->Home_nakes_m->accept_request($id, $id_user, $latitude, $longitude, $puskesmas_code);
		if (!empty($result['status']) && $result['status'] === 'success') {
			if (empty($result['already_accepted'])) {
				doclinc_log_request_event('request_accepted', $id);
				$request = doclinc_request_row($id);
				if ($request) {
					doclinc_notify_user(
						$request->user_id,
						'request_accepted',
						'request',
						$id,
						'Konsultasi diterima',
						'Permintaan konsultasi Anda sudah diterima oleh petugas.',
						$id_user
					);
				}
			}
			$this->output->set_output(json_encode([
				'status' => 'success',
				'message' => $result['message'],
				'request_id' => $id,
				'request_status' => 'Accepted',
				'redirect_url' => base_url('konsultasi_nakes/konsultasi/' . $id) . '?kriteria=1'
			]));
			return;
		}

		doclinc_log_request_event('unauthorized_request_update', $id, array('target' => 'accept'));
		$message = !empty($result['message']) ? $result['message'] : 'Request tidak ditemukan atau bukan milik dokter login';
		$this->output->set_output(json_encode(['status' => 'error', 'message' => $message]));
	}
	public function cancel_request()
	{
		if (!$this->require_post_json()) {
			return;
		}
		if ($this->session->userdata('role') !== 'dokter') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$request_id = (int) ($this->input->post('request_id') ?: $this->input->post('id'));
		$user_id = (int) $this->session->userdata('id');
		if ($request_id < 1 || !doclinc_can_cancel_request($request_id, $user_id, 'dokter')) {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'nakes_cancel'));
			}
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$profile = $this->Home_nakes_m->get_profile_by_id($user_id);
		$puskesmas_code = trim((string) (isset($profile['remark']) ? $profile['remark'] : ''));
		if ($puskesmas_code === '') {
			$puskesmas_code = trim((string) $this->session->userdata('remark'));
		}
		if ($puskesmas_code !== '') {
			$this->session->set_userdata('remark', $puskesmas_code);
		}

		$request = doclinc_request_row($request_id);
		$result = $this->Home_nakes_m->cancel_request($request_id, $user_id, $puskesmas_code);
		if (empty($result['status']) || $result['status'] !== 'success') {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'nakes_cancel'));
			}
			$message = !empty($result['message']) ? $result['message'] : 'Request tidak dapat dibatalkan';
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => $message]));
			return;
		}

		if (function_exists('doclinc_log_request_event')) {
			doclinc_log_request_event('request_cancelled', $request_id);
		}
		if ($request && function_exists('doclinc_notify_user')) {
			doclinc_notify_user(
				$request->user_id,
				'request_cancelled',
				'request',
				$request_id,
				'Konsultasi dibatalkan',
				'Permintaan konsultasi Anda dibatalkan oleh petugas.',
				$user_id
			);
		}

		$this->output->set_output(json_encode([
			'status' => 'success',
			'message' => $result['message'],
			'request_id' => $request_id,
			'request_status' => 'Cancelled'
		]));
	}
	public function update_visit_location()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']));
			return;
		}
		if ($this->session->userdata('role') !== 'dokter') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$request_id = (int) $this->input->post('request_id');
		$latitude = $this->input->post('latitude');
		$longitude = $this->input->post('longitude');
		$user_id = (int) $this->session->userdata('id');
		if ($request_id < 1 || !$this->is_valid_latitude($latitude) || !$this->is_valid_longitude($longitude)) {
			$this->output
				->set_status_header(400)
				->set_output(json_encode(['status' => 'error', 'message' => 'Data lokasi tidak valid']));
			return;
		}

		$request = doclinc_request_row($request_id);
		if (!$request) {
			$this->output
				->set_status_header(404)
				->set_output(json_encode(['status' => 'error', 'message' => 'Request tidak ditemukan']));
			return;
		}
		if (!doclinc_can_update_visit_location($request_id, $user_id, 'dokter')) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		if (!$this->Home_nakes_m->update_visit_location($request_id, $user_id, (float) $latitude, (float) $longitude)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Lokasi nakes tidak dapat diperbarui']));
			return;
		}

		$route = doclinc_visit_route_pending_payload();
		$patient_latitude = null;
		$patient_longitude = null;
		if (isset($request->patient_latitude) && $this->is_valid_latitude($request->patient_latitude) && isset($request->patient_longitude) && $this->is_valid_longitude($request->patient_longitude)) {
			$patient_latitude = (float) $request->patient_latitude;
			$patient_longitude = (float) $request->patient_longitude;
		} elseif (isset($request->lattitude) && $this->is_valid_latitude($request->lattitude) && isset($request->longitude) && $this->is_valid_longitude($request->longitude)) {
			$patient_latitude = (float) $request->lattitude;
			$patient_longitude = (float) $request->longitude;
		}
		if ($patient_latitude !== null && $patient_longitude !== null) {
			$route = doclinc_visit_route_payload(
				(float) $latitude,
				(float) $longitude,
				$patient_latitude,
				$patient_longitude
			);
		}

		$this->output->set_output(json_encode([
			'status' => 'success',
			'message' => 'Lokasi nakes diperbarui',
			'route' => $route
		]));
	}
	public function update_visit_status()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']));
			return;
		}
		if ($this->session->userdata('role') !== 'dokter') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$request_id = (int) $this->input->post('request_id');
		$visit_status = doclinc_normalize_visit_status($this->input->post('visit_status'));
		$user_id = (int) $this->session->userdata('id');
		if ($request_id < 1 || $visit_status === '') {
			$this->output
				->set_status_header(400)
				->set_output(json_encode(['status' => 'error', 'message' => 'Data status kunjungan tidak valid']));
			return;
		}

		$request = doclinc_request_row($request_id);
		if (!$request) {
			$this->output
				->set_status_header(404)
				->set_output(json_encode(['status' => 'error', 'message' => 'Request tidak ditemukan']));
			return;
		}
		if (!doclinc_can_update_visit_status($request_id, $user_id, 'dokter')) {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'visit_status', 'visit_status' => $visit_status));
			}
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$result = $this->Home_nakes_m->update_visit_status($request_id, $user_id, $visit_status);
		if (empty($result['status']) || $result['status'] !== 'success') {
			$this->output
				->set_status_header(400)
				->set_output(json_encode(array(
					'status' => 'error',
					'message' => !empty($result['message']) ? $result['message'] : 'Status kunjungan tidak dapat diperbarui',
				)));
			return;
		}

		if (function_exists('doclinc_log_request_event')) {
			doclinc_log_request_event('visit_status_updated', $request_id, array('visit_status' => $result['visit_status']));
		}

		$this->output->set_output(json_encode(array(
			'status' => 'success',
			'message' => $result['message'],
			'request_id' => $request_id,
			'visit_status' => $result['visit_status'],
			'visit_status_label' => $result['visit_status_label'],
		)));
	}
	public function get_location_user()
	{
		$name = $_SESSION['name'];
		$d['data_user'] = $this->Home_nakes_m->get_location_user($name);
	}
	public function get_estimation($origin_lat, $origin_lng, $dest_lat, $dest_lng)
	{
		$apiKey = $this->config->item('google_maps_api_key') ?: '';
		$mapProvider = $this->config->item('map_provider') ?: 'none';
		if ($mapProvider !== 'google' || empty($apiKey) || empty($origin_lat) || empty($origin_lng) || empty($dest_lat) || empty($dest_lng)) {
			return [
				'distance' => 'N/A',
				'duration' => 'N/A'
			];
		}

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
		if (isset($data['status'], $data['rows'][0]['elements'][0]['status']) && $data['status'] == 'OK' && $data['rows'][0]['elements'][0]['status'] == 'OK') {
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
		if (!$this->require_post_json()) {
			return;
		}
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
			$config['max_size']      = 5120; // 5MB
			$config['encrypt_name']  = TRUE;
			$config['detect_mime']   = TRUE;
			$config['mod_mime_fix']  = TRUE;
			$config['remove_spaces'] = TRUE;

			$this->load->library('upload', $config);
			if ($this->upload->do_upload('foto')) {
				$uploadData = $this->upload->data();
				$data['foto'] = $uploadData['file_name'];
			} else {
				$response['message'] = $this->upload->display_errors('', '');
				$this->output->set_output(json_encode($response));
				return;
			}
		}

		// Simpan lewat model
		if ($this->Home_nakes_m->update_profile($id, $data)) {
			$this->session->set_userdata($data); // Perbarui session
			$this->output->set_output(json_encode(['status' => 'success', 'message' => 'Profil berhasil diperbarui']));
		} else {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Gagal memperbarui profil']));
		}
	}

	private function require_post_json()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) === 'POST') {
			return true;
		}

		$this->output
			->set_status_header(405)
			->set_output(json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']));
		return false;
	}

	private function require_dokter_session()
	{
		if ($this->session->userdata('role') === 'dokter') {
			return true;
		}

		$role = $this->session->userdata('role');
		if ($role === 'warga') {
			redirect('home');
			return false;
		}
		if ($role === 'admin') {
			redirect('home_admin');
			return false;
		}

		redirect('login');
		return false;
	}

	private function is_valid_latitude($value)
	{
		return is_numeric($value) && (float) $value >= -90 && (float) $value <= 90;
	}

	private function is_valid_longitude($value)
	{
		return is_numeric($value) && (float) $value >= -180 && (float) $value <= 180;
	}
}
