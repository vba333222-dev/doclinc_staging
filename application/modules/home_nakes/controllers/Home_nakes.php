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

		$request_id = (int) $this->livekit_request_value('request_id');
		$call_type = $this->livekit_request_value('call_type');
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
		$body['status_poll_seconds'] = $this->Call_session_m->status_poll_seconds();
		$this->output->set_status_header((int) $result['http_status'])->set_output(json_encode($body));
	}

	public function end_livekit_call()
	{
		$this->output->set_content_type('application/json');
		if (!in_array($this->session->userdata('role'), array('dokter', 'nakes'), true)) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$call_id = (int) $this->livekit_request_value('call_id');
		$request_id = (int) $this->livekit_request_value('request_id');
		$this->Call_session_m->expire_stale_ringing_calls($this->Call_session_m->stale_cleanup_limit());
		$call = $call_id > 0 ? $this->Call_session_m->get_by_id($call_id) : $this->Call_session_m->get_active_by_request($request_id);
		if (!$call && $request_id > 0) {
			$call = $this->Call_session_m->get_latest_by_request($request_id);
		}
		if (!$call) {
			$this->output->set_status_header(404)->set_output(json_encode(array('success' => false, 'message' => 'Panggilan tidak tersedia')));
			return;
		}

		$request = doclinc_request_row((int) $call->request_id);
		if (!$request || !doclinc_request_is_handled_by_nakes($request, (int) $this->session->userdata('id'))) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		if (in_array($call->status, $this->Call_session_m->active_statuses(), true)) {
			$this->Call_session_m->end_call((int) $call->call_id, 0);
			$call = $this->Call_session_m->get_by_id((int) $call->call_id);
		}

		$message = $call && $call->status === 'missed' ? 'Panggilan tidak terjawab.' : 'Panggilan diakhiri';
		$this->output->set_output(json_encode($this->Call_session_m->status_payload($call, $message, $call && $call->status === 'missed')));
	}

	public function livekit_call_status()
	{
		$this->output->set_content_type('application/json');
		if (!in_array($this->session->userdata('role'), array('dokter', 'nakes'), true)) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$call_id = (int) $this->livekit_request_value('call_id');
		$this->Call_session_m->expire_stale_ringing_calls($this->Call_session_m->stale_cleanup_limit());
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

		$expired = false;
		if ($this->Call_session_m->is_call_expired($call)) {
			$this->Call_session_m->mark_missed($call_id);
			$call = $this->Call_session_m->get_by_id($call_id);
			$expired = true;
		}
		if ($request->request_status !== 'Accepted' && in_array($call->status, $this->Call_session_m->active_statuses(), true)) {
			$this->Call_session_m->end_call((int) $call->call_id, 0);
			$call = $this->Call_session_m->get_by_id($call_id);
		}

		$message = $call->status === 'missed' ? 'Panggilan tidak dijawab.' : 'Status panggilan';
		$this->output->set_output(json_encode($this->Call_session_m->status_payload($call, $message, $expired)));
	}

	private function livekit_request_value($key)
	{
		$value = $this->input->post($key, TRUE);
		if ($value === null || $value === '') {
			$value = $this->input->get($key, TRUE);
		}
		if (($value === null || $value === '') && stripos((string) $this->input->server('CONTENT_TYPE'), 'application/json') !== false) {
			$json = json_decode((string) $this->input->raw_input_stream, true);
			if (is_array($json) && array_key_exists($key, $json)) {
				$value = $json[$key];
			}
		}

		return $value;
	}

	public function index()
	{
		if (!$this->require_dokter_session()) {
			return;
		}

		$uid = (int) $this->session->userdata('id');
		$identity_context = doclinc_dokter_identity_context($uid);
		$account_type = !empty($identity_context['valid']) ? (string) $identity_context['account_type'] : 'unclassified';
		$puskesmas_code = !empty($identity_context['puskesmas_code']) ? (string) $identity_context['puskesmas_code'] : '';
		$d['nakes_account_type'] = $account_type;
		$d['nakes_identity_valid'] = !empty($identity_context['valid']);
		$d['can_coordinate_staff'] = $account_type === 'command_center';
		$d['nakes_identity_staff'] = array(
			'staff_id' => isset($identity_context['staff_id']) ? $identity_context['staff_id'] : null,
			'staff_status' => isset($identity_context['staff_status']) ? $identity_context['staff_status'] : null,
			'staff_profesi' => isset($identity_context['staff_profesi']) ? $identity_context['staff_profesi'] : null,
		);
		$d['nakes_puskesmas_code'] = $puskesmas_code;
		$d['nakes_puskesmas_name'] = !empty($identity_context['puskesmas_name']) ? (string) $identity_context['puskesmas_name'] : '';
		$d['profile'] = $this->Home_nakes_m->get_profile_by_id($uid);
		if (!is_array($d['profile'])) {
			$d['profile'] = array();
		}
		if ($d['nakes_puskesmas_name'] !== '') {
			$d['profile']['assigned_puskesmas_name'] = $d['nakes_puskesmas_name'];
		}

		$d['data_request_new'] = $this->Home_nakes_m->empty_request_list();
		$d['data_request_accept'] = $this->Home_nakes_m->empty_request_list();
		$d['data_request_completed'] = $this->Home_nakes_m->empty_request_list();
		$d['puskesmas_staff_list'] = array();
		$d['puskesmas_staff_count'] = 0;
		$d['staff_assignment_ready'] = false;
		$d['puskesmas_staff_options'] = array();
		$d['request_staff_assignment_map'] = array();
		$d['request_staff_latest_assignment_map'] = array();
		$d['request_event_ready'] = false;
		$d['request_event_map'] = array();

		if ($account_type === 'command_center') {
			$d['data_request_new'] = $this->Home_nakes_m->request_keluhan_command_center($identity_context);
			$d['data_request_accept'] = $this->Home_nakes_m->request_keluhan_accept_command_center($identity_context);
			$d['data_request_completed'] = $this->Home_nakes_m->request_keluhan_completed_command_center($identity_context);
			$d['puskesmas_staff_list'] = $this->Home_nakes_m->get_puskesmas_staff_by_code($puskesmas_code);
			$d['puskesmas_staff_count'] = $this->Home_nakes_m->count_puskesmas_staff_by_code($puskesmas_code);
			$d['staff_assignment_ready'] = $this->Home_nakes_m->staff_assignment_table_ready();
			$d['puskesmas_staff_options'] = $this->Home_nakes_m->get_active_staff_options_by_code($puskesmas_code);

			$assignment_request_ids = array();
			$completed_assignment_request_ids = array();
			foreach ($d['data_request_accept']->result() as $request_row) {
				$assignment_request_ids[] = (int) $request_row->request_id;
			}
			foreach ($d['data_request_completed']->result() as $request_row) {
				$completed_assignment_request_ids[] = (int) $request_row->request_id;
			}
			$d['request_staff_assignment_map'] = $d['staff_assignment_ready']
				? $this->Home_nakes_m->get_active_staff_assignments_by_request_ids($assignment_request_ids)
				: array();
			$d['request_staff_latest_assignment_map'] = $d['staff_assignment_ready']
				? $this->Home_nakes_m->get_latest_staff_assignments_by_request_ids($completed_assignment_request_ids)
				: array();
			$event_request_ids = array_merge($assignment_request_ids, $completed_assignment_request_ids);
			$d['request_event_ready'] = $this->Home_nakes_m->request_event_table_ready();
			$d['request_event_map'] = $d['request_event_ready']
				? $this->Home_nakes_m->get_request_events_by_request_ids($event_request_ids, 5)
				: array();
		} elseif ($account_type === 'personal') {
			$d['data_request_accept'] = $this->Home_nakes_m->request_keluhan_accept_personal($identity_context);
			$d['data_request_completed'] = $this->Home_nakes_m->request_keluhan_completed_personal($identity_context);
		}

		$d['data_user'] = $d['nakes_identity_valid']
			? $this->Home_nakes_m->get_location_user($uid)
			: $this->Home_nakes_m->empty_request_list();

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

	public function assign_staff()
	{
		if (!$this->require_dokter_session()) {
			return;
		}
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
			return;
		}

		$user_id = (int) $this->session->userdata('id');
		$identity_context = doclinc_dokter_identity_context($user_id, true);
		$puskesmas_code = !empty($identity_context['puskesmas_code']) ? (string) $identity_context['puskesmas_code'] : '';
		$request_id = (int) $this->input->post('request_id');
		$staff_id = (int) $this->input->post('staff_id');
		$note = trim((string) $this->input->post('note', TRUE));

		$request = $request_id > 0 ? doclinc_request_row($request_id) : null;
		if ($staff_id < 1
			|| empty($identity_context['valid'])
			|| $identity_context['account_type'] !== 'command_center'
			|| !doclinc_can_coordinate_request($request, $identity_context)) {
			$this->session->set_flashdata('staff_assignment_error', 'Akses tidak diizinkan untuk tindakan ini.');
			redirect('home_nakes#riwayat_konsul');
			return;
		}

		$result = $this->Home_nakes_m->assign_staff_to_request($request_id, $staff_id, $puskesmas_code, $user_id, $note, $identity_context);
		$message = !empty($result['message']) ? $result['message'] : 'PIC gagal ditetapkan.';
		$this->session->set_flashdata(
			isset($result['status']) && $result['status'] === 'success' ? 'staff_assignment_success' : 'staff_assignment_error',
			$message
		);
		redirect('home_nakes#riwayat_konsul');
	}

	public function clear_staff_assignment()
	{
		if (!$this->require_dokter_session()) {
			return;
		}
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
			return;
		}

		$user_id = (int) $this->session->userdata('id');
		$identity_context = doclinc_dokter_identity_context($user_id, true);
		$puskesmas_code = !empty($identity_context['puskesmas_code']) ? (string) $identity_context['puskesmas_code'] : '';
		$request_id = (int) $this->input->post('request_id');

		$request = $request_id > 0 ? doclinc_request_row($request_id) : null;
		if (empty($identity_context['valid'])
			|| $identity_context['account_type'] !== 'command_center'
			|| !doclinc_can_coordinate_request($request, $identity_context)) {
			$this->session->set_flashdata('staff_assignment_error', 'Akses tidak diizinkan untuk tindakan ini.');
			redirect('home_nakes#riwayat_konsul');
			return;
		}

		$result = $this->Home_nakes_m->clear_staff_assignment($request_id, $puskesmas_code, $user_id, $identity_context);
		$message = !empty($result['message']) ? $result['message'] : 'PIC gagal dilepas.';
		$this->session->set_flashdata(
			isset($result['status']) && $result['status'] === 'success' ? 'staff_assignment_success' : 'staff_assignment_error',
			$message
		);
		redirect('home_nakes#riwayat_konsul');
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
		$id_user = (int) $this->session->userdata('id');
		$identity_context = doclinc_dokter_identity_context($id_user);
		if (empty($identity_context['valid']) || $identity_context['account_type'] !== 'command_center') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$id = (int) $this->input->post('id');
		$puskesmas_code = (string) $identity_context['puskesmas_code'];
		$latitude = $this->input->post('latitude');
		$longitude = $this->input->post('longitude');

		if (empty($id) || empty($id_user)) {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Data permintaan tidak lengkap.']));
			return;
		}

		$request = doclinc_request_row($id);
		if (!$request || (string) $request->request_status !== 'Pending' || !doclinc_can_coordinate_request($request, $identity_context)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan untuk tindakan ini.']));
			return;
		}

		$result = $this->Home_nakes_m->accept_request($id, $id_user, $latitude, $longitude, $puskesmas_code, $identity_context);
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
				'redirect_url' => base_url('konsultasi_nakes/konsultasi/' . $id)
			]));
			return;
		}

		doclinc_log_request_event('unauthorized_request_update', $id, array('target' => 'accept'));
		$message = !empty($result['message']) ? $result['message'] : 'Permintaan tidak dapat diakses.';
		$this->output->set_output(json_encode(['status' => 'error', 'message' => $message]));
	}
	public function cancel_request()
	{
		if (!$this->require_post_json()) {
			return;
		}
		$user_id = (int) $this->session->userdata('id');
		$identity_context = doclinc_dokter_identity_context($user_id);
		if (empty($identity_context['valid']) || $identity_context['account_type'] !== 'command_center') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$request_id = (int) ($this->input->post('request_id') ?: $this->input->post('id'));
		$request = $request_id > 0 ? doclinc_request_row($request_id) : null;
		if (!$request
			|| (string) $request->request_status !== 'Pending'
			|| !doclinc_can_coordinate_request($request, $identity_context)) {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'nakes_cancel'));
			}
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$puskesmas_code = (string) $identity_context['puskesmas_code'];
		$result = $this->Home_nakes_m->cancel_request($request_id, $user_id, $puskesmas_code, $identity_context);
		if (empty($result['status']) || $result['status'] !== 'success') {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'nakes_cancel'));
			}
			$message = !empty($result['message']) ? $result['message'] : 'Permintaan tidak dapat dibatalkan.';
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
		$user_id = (int) $this->session->userdata('id');
		$identity_context = doclinc_dokter_identity_context($user_id);
		if (empty($identity_context['valid'])) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('status' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$request_id = (int) $this->input->get('request_id', TRUE);
		if ($request_id < 1) {
			$this->output
				->set_status_header(400)
				->set_output(json_encode(array('status' => false, 'message' => 'Data permintaan tidak valid.')));
			return;
		}

		$request = doclinc_request_row($request_id);
		if (!$request) {
			$this->output
				->set_status_header(404)
				->set_output(json_encode(array('status' => false, 'message' => 'Permintaan tidak ditemukan.')));
			return;
		}
		$access_context = doclinc_nakes_request_access_context($request, $identity_context);
		if (empty($access_context['can_handle'])) {
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
				'message' => 'Pelacakan kunjungan selesai.',
				'request_id' => $request_id,
				'request_status' => isset($request->request_status) ? $request->request_status : null,
				'visit_status' => $current_visit_status,
				'visit_status_label' => function_exists('doclinc_visit_status_label') ? doclinc_visit_status_label($current_visit_status) : $current_visit_status,
			)));
			return;
		}

		$row = $this->Home_nakes_m->get_visit_location($request_id, $user_id, $identity_context);
		if (!$row) {
			$this->output
				->set_status_header(404)
				->set_output(json_encode(array('status' => false, 'message' => 'Permintaan tidak ditemukan.')));
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
		$user_id = (int) $this->session->userdata('id');
		$identity_context = doclinc_dokter_identity_context($user_id);
		if (empty($identity_context['valid'])) {
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
				->set_output(json_encode(['status' => 'error', 'success' => false, 'message' => 'Permintaan tidak ditemukan.']));
			return;
		}
		$access_context = doclinc_nakes_request_access_context($request, $identity_context);
		if (empty($access_context['can_handle'])) {
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
				'message' => 'Pelacakan kunjungan selesai.',
				'request_id' => $request_id,
				'request_status' => isset($request->request_status) ? $request->request_status : null,
				'visit_status' => $current_visit_status,
				'visit_status_label' => function_exists('doclinc_visit_status_label') ? doclinc_visit_status_label($current_visit_status) : $current_visit_status,
			)));
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

		if (!$this->Home_nakes_m->update_visit_location($request_id, $user_id, (float) $latitude, (float) $longitude, $identity_context)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'success' => false, 'message' => 'Lokasi nakes tidak dapat diperbarui']));
			return;
		}

		$row = $this->Home_nakes_m->get_visit_location($request_id, $user_id, $identity_context);
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
		$user_id = (int) $this->session->userdata('id');
		$identity_context = doclinc_dokter_identity_context($user_id);
		if (empty($identity_context['valid'])) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$request_id = (int) $this->input->post('request_id');
		$visit_status = doclinc_normalize_visit_status($this->input->post('visit_status'));
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
				->set_output(json_encode(['status' => 'error', 'message' => 'Permintaan tidak ditemukan.']));
			return;
		}
		$access_context = doclinc_nakes_request_access_context($request, $identity_context);
		if (empty($access_context['can_handle'])) {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'visit_status', 'visit_status' => $visit_status));
			}
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$result = $this->Home_nakes_m->update_visit_status($request_id, $user_id, $visit_status, $identity_context);
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
