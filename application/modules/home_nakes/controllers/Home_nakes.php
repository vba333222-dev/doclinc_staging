<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Home_nakes extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Home_nakes_m');
		$this->load->model('chat/Call_session_m', 'Call_session_m');
		$this->load->helper('request_authz');
		$this->load->helper('visit_routing');
		$this->load->helper('livekit');
		$this->load->helper('notification');
		if ($this->session->userdata('logged_in') != TRUE) {
			if (in_array($this->router->fetch_method(), array('visit_location', 'update_visit_location', 'update_visit_status', 'livekit_token', 'start_livekit_call', 'end_livekit_call', 'livekit_call_status'), true)) {
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

	public function livekit_token()
	{
		$this->output->set_content_type('application/json');
		if (!in_array($this->session->userdata('role'), array('dokter', 'nakes'), true)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$request_id = (int) ($this->input->post('request_id') ?: $this->input->get('request_id', TRUE));
		$result = doclinc_livekit_token_payload($request_id, (int) $this->session->userdata('id'), $this->session->userdata('role'));
		$this->output
			->set_status_header((int) $result['http_status'])
			->set_output(json_encode($result['body']));
	}

	public function start_livekit_call()
	{
		$this->output->set_content_type('application/json');
		if (!in_array($this->session->userdata('role'), array('dokter', 'nakes'), true)) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}
		if (!$this->Call_session_m->table_ready()) {
			$this->output->set_status_header(503)->set_output(json_encode(array('success' => false, 'message' => 'Sesi panggilan belum siap')));
			return;
		}

		$request_id = (int) ($this->input->post('request_id') ?: $this->input->get('request_id', TRUE));
		$call_type = $this->input->post('call_type', TRUE) ?: $this->input->get('call_type', TRUE);
		$call_type = $call_type === 'audio' ? 'audio' : 'video';
		$user_id = (int) $this->session->userdata('id');
		$request = doclinc_request_row($request_id);
		if (!$request || $request->request_status !== 'Accepted' || !doclinc_request_is_handled_by_nakes($request, $user_id)) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Panggilan tidak diizinkan')));
			return;
		}

		$call = $this->Call_session_m->start_or_reuse($request, $user_id, $call_type);
		if (!$call) {
			$this->output->set_status_header(500)->set_output(json_encode(array('success' => false, 'message' => 'Sesi panggilan tidak dapat dibuat')));
			return;
		}

		$result = doclinc_livekit_token_payload($request_id, $user_id, $this->session->userdata('role'));
		if (empty($result['body']['success'])) {
			$this->output->set_status_header((int) $result['http_status'])->set_output(json_encode($result['body']));
			return;
		}

		$body = $result['body'];
		$body['call_id'] = (int) $call->call_id;
		$body['status'] = $call->status;
		$body['call_type'] = $call->call_type;
		$this->output->set_status_header((int) $result['http_status'])->set_output(json_encode($body));
	}

	public function end_livekit_call()
	{
		$this->output->set_content_type('application/json');
		if (!in_array($this->session->userdata('role'), array('dokter', 'nakes'), true)) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$call_id = (int) ($this->input->post('call_id') ?: $this->input->get('call_id', TRUE));
		$request_id = (int) ($this->input->post('request_id') ?: $this->input->get('request_id', TRUE));
		$call = $call_id > 0 ? $this->Call_session_m->get_by_id($call_id) : $this->Call_session_m->get_active_by_request($request_id);
		if (!$call) {
			$this->output->set_status_header(404)->set_output(json_encode(array('success' => false, 'message' => 'Panggilan tidak tersedia')));
			return;
		}

		$request = doclinc_request_row((int) $call->request_id);
		if (!$request || !doclinc_request_is_handled_by_nakes($request, (int) $this->session->userdata('id'))) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$this->Call_session_m->end_call((int) $call->call_id, 0);
		$this->output->set_output(json_encode(array('success' => true, 'call_id' => (int) $call->call_id, 'status' => 'ended', 'message' => 'Panggilan diakhiri')));
	}

	public function livekit_call_status()
	{
		$this->output->set_content_type('application/json');
		if (!in_array($this->session->userdata('role'), array('dokter', 'nakes'), true)) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$call_id = (int) ($this->input->post('call_id') ?: $this->input->get('call_id', TRUE));
		$call = $this->Call_session_m->get_by_id($call_id);
		if (!$call) {
			$this->output->set_status_header(404)->set_output(json_encode(array('success' => false, 'message' => 'Panggilan tidak tersedia')));
			return;
		}

		$request = doclinc_request_row((int) $call->request_id);
		if (!$request || !doclinc_request_is_handled_by_nakes($request, (int) $this->session->userdata('id'))) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$this->output->set_output(json_encode(array(
			'success' => true,
			'call_id' => (int) $call->call_id,
			'status' => $call->status,
			'call_type' => $call->call_type,
			'message' => 'Status panggilan',
		)));
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
	public function visit_location()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'GET') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(array('status' => false, 'message' => 'Metode tidak diizinkan')));
			return;
		}
		if ($this->session->userdata('role') !== 'dokter') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('status' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$request_id = (int) $this->input->get('request_id', TRUE);
		$user_id = (int) $this->session->userdata('id');
		if ($request_id < 1) {
			$this->output
				->set_status_header(400)
				->set_output(json_encode(array('status' => false, 'message' => 'Data request tidak valid')));
			return;
		}

		$request = doclinc_request_row($request_id);
		if (!$request) {
			$this->output
				->set_status_header(404)
				->set_output(json_encode(array('status' => false, 'message' => 'Request tidak ditemukan')));
			return;
		}
		if (!function_exists('doclinc_request_is_handled_by_nakes') || !doclinc_request_is_handled_by_nakes($request, $user_id)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('status' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}
		$current_visit_status = isset($request->visit_status) && $request->visit_status !== null
			? doclinc_normalize_visit_status($request->visit_status)
			: '';
		$current_visit_status = $current_visit_status !== '' ? $current_visit_status : 'not_started';
		if ($request->request_status !== 'Accepted' || $current_visit_status === 'completed') {
			$this->output->set_output(json_encode(array(
				'status' => 'inactive',
				'success' => false,
				'tracking_active' => false,
				'message' => 'Tracking kunjungan sudah selesai.',
				'request_id' => $request_id,
				'request_status' => isset($request->request_status) ? $request->request_status : null,
				'visit_status' => $current_visit_status,
				'visit_status_label' => function_exists('doclinc_visit_status_label') ? doclinc_visit_status_label($current_visit_status) : $current_visit_status,
			)));
			return;
		}

		if (!doclinc_can_update_visit_location($request_id, $user_id, 'dokter')) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('status' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$row = $this->Home_nakes_m->get_visit_location($request_id, $user_id);
		if (!$row) {
			$this->output
				->set_status_header(404)
				->set_output(json_encode(array('status' => false, 'message' => 'Request tidak ditemukan')));
			return;
		}

		$this->output->set_output(json_encode($this->build_nakes_visit_location_payload($row, true)));
	}
	public function update_visit_location()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(['status' => 'error', 'success' => false, 'message' => 'Metode tidak diizinkan']));
			return;
		}
		if ($this->session->userdata('role') !== 'dokter') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'success' => false, 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$request_id = (int) $this->input->post('request_id');
		$latitude = $this->input->post('latitude');
		$longitude = $this->input->post('longitude');
		$accuracy_m = $this->visit_location_optional_float('accuracy_m', 'accuracy');
		$heading = $this->visit_location_optional_float('heading');
		$speed_mps = $this->visit_location_optional_float('speed_mps', 'speed');
		$user_id = (int) $this->session->userdata('id');
		if ($request_id < 1 || !$this->is_valid_latitude($latitude) || !$this->is_valid_longitude($longitude)) {
			if (function_exists('doclinc_log_request_event') && $request_id > 0) {
				doclinc_log_request_event('visit_location_rejected_invalid_coordinate', $request_id);
			}
			$this->output
				->set_status_header(400)
				->set_output(json_encode([
					'status' => 'error',
					'success' => false,
					'reason' => 'invalid_coordinate',
					'message' => 'Data lokasi tidak valid'
				]));
			return;
		}

		$request = doclinc_request_row($request_id);
		if (!$request) {
			$this->output
				->set_status_header(404)
				->set_output(json_encode(['status' => 'error', 'success' => false, 'message' => 'Request tidak ditemukan']));
			return;
		}
		if (!function_exists('doclinc_request_is_handled_by_nakes') || !doclinc_request_is_handled_by_nakes($request, $user_id)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'success' => false, 'message' => 'Akses tidak diizinkan']));
			return;
		}
		$current_visit_status = isset($request->visit_status) && $request->visit_status !== null
			? doclinc_normalize_visit_status($request->visit_status)
			: '';
		$current_visit_status = $current_visit_status !== '' ? $current_visit_status : 'not_started';
		if ($request->request_status !== 'Accepted' || $current_visit_status === 'completed') {
			$this->output->set_output(json_encode(array(
				'status' => 'inactive',
				'success' => false,
				'tracking_active' => false,
				'message' => 'Tracking kunjungan sudah selesai.',
				'request_id' => $request_id,
				'request_status' => isset($request->request_status) ? $request->request_status : null,
				'visit_status' => $current_visit_status,
				'visit_status_label' => function_exists('doclinc_visit_status_label') ? doclinc_visit_status_label($current_visit_status) : $current_visit_status,
			)));
			return;
		}
		if (!doclinc_can_update_visit_location($request_id, $user_id, 'dokter')) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'success' => false, 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$max_accuracy_m = function_exists('doclinc_visit_location_max_accuracy_meters') ? doclinc_visit_location_max_accuracy_meters() : 100;
		if ($accuracy_m !== null && $accuracy_m > $max_accuracy_m) {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('visit_location_rejected_low_accuracy', $request_id, array('accuracy_m' => $accuracy_m, 'max_accuracy_m' => $max_accuracy_m));
			}
			$this->output->set_output(json_encode(array(
				'status' => 'error',
				'success' => false,
				'reason' => 'low_accuracy',
				'message' => 'Akurasi lokasi belum cukup baik. Coba beberapa saat lagi.',
				'accuracy_m' => $accuracy_m,
				'max_accuracy_m' => $max_accuracy_m,
			)));
			return;
		}

		$max_speed_mps = function_exists('doclinc_visit_location_max_speed_mps') ? doclinc_visit_location_max_speed_mps() : 45;
		if ($speed_mps !== null && $speed_mps > $max_speed_mps) {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('visit_location_rejected_suspicious_speed', $request_id, array('speed_mps' => $speed_mps, 'max_speed_mps' => $max_speed_mps));
			}
			$this->output->set_output(json_encode(array(
				'status' => 'error',
				'success' => false,
				'reason' => 'suspicious_speed',
				'message' => 'Data lokasi tidak valid. Coba beberapa saat lagi.',
				'speed_mps' => $speed_mps,
				'max_speed_mps' => $max_speed_mps,
			)));
			return;
		}

		if (!$this->Home_nakes_m->update_visit_location($request_id, $user_id, (float) $latitude, (float) $longitude)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'success' => false, 'message' => 'Lokasi nakes tidak dapat diperbarui']));
			return;
		}

		$row = $this->Home_nakes_m->get_visit_location($request_id, $user_id);
		$payload = $row ? $this->build_nakes_visit_location_payload($row, 'success', (float) $latitude, (float) $longitude) : array();
		$this->output->set_output(json_encode(array_merge($payload, [
			'status' => 'success',
			'success' => true,
			'tracking_active' => true,
			'message' => 'Lokasi nakes diperbarui',
			'location_meta' => array(
				'accuracy_m' => $accuracy_m,
				'heading' => $heading,
				'speed_mps' => $speed_mps,
			),
		])));
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

	private function visit_location_optional_float($primary_key, $fallback_key = null)
	{
		$value = $this->input->post($primary_key);
		if (($value === null || $value === '') && $fallback_key !== null) {
			$value = $this->input->post($fallback_key);
		}
		if ($value === null || $value === '') {
			return null;
		}
		if (!is_numeric($value)) {
			return null;
		}

		return (float) $value;
	}

	private function build_nakes_visit_location_payload($row, $status, $override_nakes_latitude = null, $override_nakes_longitude = null)
	{
		$route = function_exists('doclinc_visit_route_pending_payload') ? doclinc_visit_route_pending_payload() : array();
		$arrival = function_exists('doclinc_visit_arrival_payload') ? doclinc_visit_arrival_payload(null, null, null, null, null) : array();
		$patient_latitude = null;
		$patient_longitude = null;
		if (isset($row->patient_latitude) && $this->is_valid_latitude($row->patient_latitude) && isset($row->patient_longitude) && $this->is_valid_longitude($row->patient_longitude)) {
			$patient_latitude = (float) $row->patient_latitude;
			$patient_longitude = (float) $row->patient_longitude;
		} elseif (isset($row->lattitude) && $this->is_valid_latitude($row->lattitude) && isset($row->longitude) && $this->is_valid_longitude($row->longitude)) {
			$patient_latitude = (float) $row->lattitude;
			$patient_longitude = (float) $row->longitude;
		}

		$nakes_latitude = null;
		$nakes_longitude = null;
		if ($this->is_valid_latitude($override_nakes_latitude) && $this->is_valid_longitude($override_nakes_longitude)) {
			$nakes_latitude = (float) $override_nakes_latitude;
			$nakes_longitude = (float) $override_nakes_longitude;
		} elseif (isset($row->lattitude_dokter) && $this->is_valid_latitude($row->lattitude_dokter) && isset($row->longitude_dokter) && $this->is_valid_longitude($row->longitude_dokter)) {
			$nakes_latitude = (float) $row->lattitude_dokter;
			$nakes_longitude = (float) $row->longitude_dokter;
		}

		$message = 'OK';
		if ($patient_latitude === null || $patient_longitude === null) {
			$message = 'Lokasi pasien belum tersedia';
		} elseif ($nakes_latitude === null || $nakes_longitude === null) {
			$message = 'Aktifkan lokasi untuk menghitung jarak';
		} else {
			$route = doclinc_visit_route_payload($nakes_latitude, $nakes_longitude, $patient_latitude, $patient_longitude);
			$arrival = doclinc_visit_arrival_payload(
				$nakes_latitude,
				$nakes_longitude,
				$patient_latitude,
				$patient_longitude,
				isset($row->visit_status) ? $row->visit_status : null
			);
			if (!isset($row->request_status) || $row->request_status !== 'Accepted') {
				$arrival['should_prompt_arrival'] = false;
			}
		}

		return array(
			'status' => $status,
			'success' => $status === true || $status === 'success',
			'tracking_active' => isset($row->request_status) && $row->request_status === 'Accepted' && (!isset($row->visit_status) || doclinc_normalize_visit_status($row->visit_status) !== 'completed'),
			'message' => $message,
			'request_id' => isset($row->request_id) ? (int) $row->request_id : null,
			'request_status' => isset($row->request_status) ? $row->request_status : null,
			'visit_status' => isset($row->visit_status) ? $row->visit_status : null,
			'consultation_mode' => isset($row->consultation_mode) ? $row->consultation_mode : null,
			'patient' => array(
				'latitude' => $patient_latitude,
				'longitude' => $patient_longitude,
				'available' => $patient_latitude !== null && $patient_longitude !== null,
			),
			'nakes' => array(
				'latitude' => $nakes_latitude,
				'longitude' => $nakes_longitude,
				'available' => $nakes_latitude !== null && $nakes_longitude !== null,
			),
			'route' => $route,
			'arrival' => $arrival,
		);
	}
}
