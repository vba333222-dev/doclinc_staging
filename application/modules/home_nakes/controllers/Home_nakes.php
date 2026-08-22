<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Home_nakes extends MX_Controller
{
	private $initial_section = 'beranda';

	function __construct()
	{
		parent::__construct();
		$this->load->model('Home_nakes_m');
		$this->load->model('chat/Call_session_m', 'Call_session_m');
		$this->load->helper('request_authz');
		$this->load->helper('visit_routing');
		$this->load->helper('livekit');
		$this->load->helper('notification');
		$this->load->helper('request_realtime');
		$this->load->helper('role_prerequisite');
		$this->load->helper('nakes_presence_client');
		$this->load->library('Visit_monitoring_policy');
		if ($this->session->userdata('logged_in') != TRUE) {
			if (in_array($this->router->fetch_method(), array('visit_location', 'update_visit_location', 'update_visit_status', 'presence_heartbeat', 'presence_snapshot', 'livekit_token', 'start_livekit_call', 'end_livekit_call', 'livekit_call_status', 'assign_staff', 'clear_staff_assignment', 'assign_responsible_doctor', 'choose_service_mode', 'assign_visit_performer'), true)) {
				$this->output
					->set_content_type('application/json')
					->set_status_header(401)
					->set_output(json_encode(['status' => 'error', 'message' => 'Silakan masuk terlebih dahulu.']));
				$this->output->_display();
				exit;
			}
			redirect('login');
		}
	}

	public function livekit_token()
	{
		$this->output->set_content_type('application/json');
		if (!$this->require_livekit_post()) {
			return;
		}
		if (!in_array($this->session->userdata('role'), array('dokter', 'nakes'), true)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('success' => false, 'message' => 'Anda tidak memiliki akses.')));
			return;
		}
		$user_id = (int) $this->session->userdata('id');
		if (!$this->require_role_prerequisites($user_id)) {
			return;
		}

		$request_id = (int) ($this->input->post('request_id') ?: $this->input->get('request_id', TRUE));
		$result = doclinc_livekit_token_payload($request_id, $user_id, $this->session->userdata('role'));
		$this->output
			->set_status_header((int) $result['http_status'])
			->set_output(json_encode($result['body']));
	}

	public function start_livekit_call()
	{
		$this->output->set_content_type('application/json');
		if (!$this->require_livekit_post()) {
			return;
		}
		if (!in_array($this->session->userdata('role'), array('dokter', 'nakes'), true)) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Anda tidak memiliki akses.')));
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
		if (!$this->require_role_prerequisites($user_id)) {
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
		if (!empty($call->was_created)) {
			$notified = doclinc_notify_user(
				(int) $request->user_id,
				'incoming_call',
				'request',
				$request_id,
				'Panggilan Doclinc masuk',
				$call_type === 'audio' ? 'Ada panggilan suara dari Nakes.' : 'Ada panggilan video dari Nakes.',
				$user_id
			);
			if (!$notified) {
				log_message('error', 'Incoming LiveKit call notification could not be delivered for call_id=' . (int) $call->call_id);
			}
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
		if (!$this->require_livekit_post()) {
			return;
		}
		if (!in_array($this->session->userdata('role'), array('dokter', 'nakes'), true)) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Anda tidak memiliki akses.')));
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
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Anda tidak memiliki akses.')));
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
		if (!$this->require_livekit_post()) {
			return;
		}
		if (!in_array($this->session->userdata('role'), array('dokter', 'nakes'), true)) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Anda tidak memiliki akses.')));
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
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Anda tidak memiliki akses.')));
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

	private function require_livekit_post()
	{
		if ($this->input->method(TRUE) === 'POST') {
			return true;
		}

		$this->output
			->set_status_header(405)
			->set_output(json_encode(array(
				'success' => false,
				'safe_error_code' => 'method_not_allowed',
				'message' => 'Metode permintaan tidak didukung.',
			)));
		return false;
	}

	public function presence_heartbeat()
	{
		$this->output->set_content_type('application/json')->set_header('Cache-Control: no-store');
		if ($this->input->method(TRUE) !== 'POST') {
			$this->presence_respond(405, false, 'method_not_allowed');
			return;
		}
		if ($this->config->item('nakes_presence_enabled') !== true) {
			$this->presence_respond(403, false, 'feature_disabled');
			return;
		}
		if (!$this->require_role_prerequisites((int) $this->session->userdata('id'))) {
			return;
		}

		$actor = $this->presence_actor();
		require_once APPPATH . 'libraries/Nakes_presence_service.php';
		$service = new Nakes_presence_service(
			$this->db,
			(int) $this->config->item('nakes_presence_online_timeout_seconds'),
			(int) $this->config->item('nakes_presence_write_throttle_seconds')
		);
		$result = $service->touch($actor);
		$this->presence_respond(!empty($result['ok']) ? 200 : ($result['code'] === 'schema_unavailable' ? 503 : 403), !empty($result['ok']), $result['code'], array(
			'persisted' => !empty($result['persisted']),
		));
	}

	public function presence_snapshot()
	{
		$this->output->set_content_type('application/json')->set_header('Cache-Control: no-store');
		if ($this->input->method(TRUE) !== 'GET') {
			$this->presence_respond(405, false, 'method_not_allowed');
			return;
		}
		if (!empty($this->input->get(null, true))) {
			$this->presence_respond(400, false, 'query_not_allowed');
			return;
		}
		if ($this->config->item('nakes_presence_enabled') !== true) {
			$this->presence_respond(403, false, 'feature_disabled');
			return;
		}

		$actor = $this->presence_actor();
		require_once APPPATH . 'libraries/Nakes_presence_service.php';
		$service = new Nakes_presence_service(
			$this->db,
			(int) $this->config->item('nakes_presence_online_timeout_seconds'),
			(int) $this->config->item('nakes_presence_write_throttle_seconds')
		);
		$result = $service->snapshot($actor, 500);
		if (empty($result['ok'])) {
			$this->presence_respond($result['code'] === 'schema_unavailable' ? 503 : 403, false, $result['code']);
			return;
		}
		$this->presence_respond(200, true, 'ok', $result['data']);
	}

	private function presence_actor()
	{
		$user_id = (int) $this->session->userdata('id');
		$actor = array(
			'authenticated' => $this->session->userdata('logged_in') == true,
			'user_id' => $user_id,
			'role' => (string) $this->session->userdata('role'),
			'status' => '',
			'must_change_password' => true,
			'identity' => array(),
			'session_binding_valid' => $this->config->item('single_active_session_enabled') !== true,
		);
		if ($this->config->item('single_active_session_enabled') === true) {
			$this->load->library('Session_binding_service');
			$actor['session_binding_valid'] = $this->session_binding_service->validate(
				$user_id,
				$this->session->userdata('normal_session_token')
			);
		}
		if ($user_id < 1 || !$this->db->table_exists('users')
			|| !$this->db->field_exists('must_change_password', 'users')
			|| !doclinc_nakes_credential_schema_allows_runtime($this->db)) {
			return $actor;
		}
		$user = $this->db->select(
			'userId, role, status, must_change_password, ' . doclinc_nakes_password_changed_at_projection($this->db),
			false
		)
			->where('userId', $user_id)->limit(1)->get('users')->row();
		if (!$user || (string) $user->role !== $actor['role']) {
			return $actor;
		}
		$actor['status'] = (string) $user->status;
		$actor['must_change_password'] = doclinc_nakes_password_change_blocked(
			$user->role,
			$user->must_change_password,
			$user->password_changed_at
		);
		if ($actor['role'] === 'dokter') {
			$actor['identity'] = doclinc_dokter_identity_context($user_id, true);
		}
		return $actor;
	}

	private function presence_respond($status, $success, $code, array $data = array())
	{
		$body = array('success' => (bool) $success, 'safe_error_code' => (string) $code);
		if ($success) {
			$body['data'] = $data;
		}
		$this->output->set_status_header((int) $status)->set_output(json_encode($body));
	}

	public function operations()
	{
		if (!$this->require_dokter_session()) {
			return;
		}
		if ($this->config->item('puskesmas_operations_enabled') !== true) {
			show_404();
			return;
		}
		$user_id = (int) $this->session->userdata('id');
		$identity = doclinc_dokter_identity_context($user_id, true);
		if (empty($identity['valid'])
			|| (string) ($identity['account_type'] ?? '') !== 'command_center'
			|| empty($identity['is_command_center'])) {
			show_404();
			return;
		}
		if (!$this->require_role_prerequisites($user_id, false, true)) {
			return;
		}
		$this->initial_section = 'operasional';
		$this->index();
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
		$d['nakes_initial_section'] = $this->initial_section;
		$d['nakes_identity_valid'] = !empty($identity_context['valid']);
		$d['can_coordinate_staff'] = $account_type === 'command_center';
		$d['nakes_identity_staff'] = array(
			'staff_id' => isset($identity_context['staff_id']) ? $identity_context['staff_id'] : null,
			'staff_status' => isset($identity_context['staff_status']) ? $identity_context['staff_status'] : null,
			'staff_profesi' => isset($identity_context['staff_profesi']) ? $identity_context['staff_profesi'] : null,
			'staff_gelar' => isset($identity_context['staff_gelar']) ? $identity_context['staff_gelar'] : null,
			'staff_nomor_sip' => isset($identity_context['staff_nomor_sip']) ? $identity_context['staff_nomor_sip'] : null,
			'staff_sip_expired_at' => isset($identity_context['staff_sip_expired_at']) ? $identity_context['staff_sip_expired_at'] : null,
		);
		$d['nakes_puskesmas_code'] = $puskesmas_code;
		$d['nakes_puskesmas_name'] = !empty($identity_context['puskesmas_name']) ? (string) $identity_context['puskesmas_name'] : '';
		$d['nakes_presence_bootstrap'] = doclinc_nakes_presence_client_bootstrap($identity_context, true);
		$d['nakes_presence_enabled'] = !empty($d['nakes_presence_bootstrap']['enabled']);
		$d['role_prerequisite_state'] = doclinc_role_prerequisite_state($uid, true);
		$d['puskesmas_operations_enabled'] = $this->config->item('puskesmas_operations_enabled') === true
			&& $account_type === 'command_center'
			&& !empty($identity_context['valid'])
			&& !empty($d['role_prerequisite_state']['allowed'])
			&& !empty($d['role_prerequisite_state']['complete']);
		$d['puskesmas_operations_bootstrap'] = array(
			'enabled' => $d['puskesmas_operations_enabled'],
			'snapshotUrl' => base_url('puskesmas/operations/snapshot'),
			'medicalRecordUrl' => base_url('puskesmas/operations/medical-record'),
			'pageUrl' => site_url('puskesmas/operations'),
			'pollIntervalMs' => max(15000, (int) $this->config->item('puskesmas_operations_poll_seconds') * 1000),
			'containerId' => 'doclincPuskesmasOperations',
		);
		$d['profile'] = $this->Home_nakes_m->get_profile_by_id($uid);
		$d['care_team_workflow_enabled'] = $this->config->item('care_team_workflow_enabled') === true;
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
		$d['puskesmas_data_readiness'] = array();
		$d['puskesmas_operation_exceptions'] = array();

		if ($account_type === 'command_center') {
			$d['data_request_new'] = $this->Home_nakes_m->request_keluhan_command_center($identity_context);
			$d['data_request_accept'] = $this->Home_nakes_m->request_keluhan_accept_command_center($identity_context);
			$d['data_request_completed'] = $this->Home_nakes_m->request_keluhan_completed_command_center($identity_context);
			$d['puskesmas_staff_list'] = $this->Home_nakes_m->get_puskesmas_staff_by_code($puskesmas_code);
			$d['puskesmas_staff_count'] = $this->Home_nakes_m->count_puskesmas_staff_by_code($puskesmas_code);
			$d['staff_assignment_ready'] = $this->Home_nakes_m->staff_assignment_table_ready();
			$d['puskesmas_staff_options'] = $this->Home_nakes_m->get_active_staff_options_by_code($puskesmas_code);
			$this->load->library('Puskesmas_data_readiness');
			$d['puskesmas_data_readiness'] = $this->puskesmas_data_readiness->summarize(
				$d['role_prerequisite_state'],
				$d['puskesmas_staff_options']
			);

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
			$accepted_without_pic = 0;
			foreach ($assignment_request_ids as $request_id) {
				if (empty($d['request_staff_assignment_map'][(int) $request_id])) {
					$accepted_without_pic++;
				}
			}
			$d['puskesmas_operation_exceptions'] = array(
				'pending_requests' => (int) $d['data_request_new']->num_rows(),
				'accepted_requests' => count($assignment_request_ids),
				'accepted_without_pic' => $accepted_without_pic,
				'staff_data_attention' => isset($d['puskesmas_data_readiness']['attention_count']) ? (int) $d['puskesmas_data_readiness']['attention_count'] : 0,
			);
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
			if ($d['care_team_workflow_enabled']) {
				$d['puskesmas_staff_options'] = $this->Home_nakes_m->get_active_staff_options_by_code($puskesmas_code);
			}
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
		if ($this->reject_legacy_staff_assignment_when_care_team_enabled()) {
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
			if ($this->staff_assignment_json_requested()) {
				$this->respond_staff_assignment_json(403, array('status' => 'error', 'message' => 'Anda tidak memiliki akses.'), $request_id);
				return;
			}
			$this->session->set_flashdata('staff_assignment_error', 'Anda tidak memiliki akses.');
			redirect('home_nakes#riwayat_konsul');
			return;
		}
		if (!$this->require_role_prerequisites($user_id, false)) {
			return;
		}

		$result = $this->Home_nakes_m->assign_staff_to_request($request_id, $staff_id, $puskesmas_code, $user_id, $note, $identity_context);
		$message = !empty($result['message']) ? $result['message'] : 'Gagal memperbarui penanggung jawab.';
		if ($this->staff_assignment_json_requested()) {
			$result['message'] = $message;
			$this->respond_staff_assignment_json(
				isset($result['status']) && $result['status'] === 'success' ? 200 : 409,
				$result,
				$request_id,
				true
			);
			return;
		}
		$this->session->set_flashdata(
			isset($result['status']) && $result['status'] === 'success' ? 'staff_assignment_success' : 'staff_assignment_error',
			$message
		);
		redirect('home_nakes#riwayat_konsul');
	}

	public function assign_responsible_doctor()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->care_team_response(405, array('status' => 'error', 'message' => 'Metode tidak diizinkan.'));
			return;
		}
		if (!$this->care_team_post_ready()) {
			return;
		}
		$user_id = (int) $this->session->userdata('id');
		$identity = doclinc_dokter_identity_context($user_id, true);
		if (empty($identity['valid']) || $identity['account_type'] !== 'command_center') {
			$this->care_team_response(403, array('status' => 'error', 'message' => 'Anda tidak memiliki akses.'));
			return;
		}
		$this->load->library('Care_team_service');
		$result = $this->care_team_service->assignResponsibleDoctor(
			(int) $this->input->post('request_id'),
			(int) $this->input->post('staff_id'),
			$user_id,
			$this->session->userdata('normal_session_token'),
			$this->config->item('single_active_session_enabled') === true
		);
		$this->care_team_response(!empty($result['ok']) ? 200 : 409, $result);
	}

	public function choose_service_mode()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->care_team_response(405, array('status' => 'error', 'message' => 'Metode tidak diizinkan.'));
			return;
		}
		if (!$this->care_team_post_ready()) {
			return;
		}
		$user_id = (int) $this->session->userdata('id');
		$this->load->library('Care_team_service');
		$result = $this->care_team_service->chooseMode(
			(int) $this->input->post('request_id'),
			$this->input->post('service_mode', true),
			$user_id,
			$this->session->userdata('normal_session_token'),
			$this->config->item('single_active_session_enabled') === true
		);
		if (!empty($result['ok']) && !empty($result['changed'])) {
			$this->notify_service_mode((int) $this->input->post('request_id'), $user_id, $result['consultation_mode']);
		}
		$this->care_team_response(!empty($result['ok']) ? 200 : 409, $result);
	}

	private function notify_service_mode($request_id, $actor_user_id, $mode)
	{
		$request = doclinc_request_row((int) $request_id);
		if (!$request || (int) ($request->responsible_doctor_user_id ?? 0) !== (int) $actor_user_id) {
			return;
		}
		$is_visit = (string) $mode === 'visit';
		$title = 'Jenis layanan diperbarui';
		$message = $is_visit ? 'Konsultasi dilanjutkan dengan kunjungan.' : 'Konsultasi dilanjutkan tanpa kunjungan.';
		$event_type = $is_visit ? 'consultation_visit_selected' : 'consultation_non_visit_selected';
		$patient_id = (int) ($request->user_id ?? 0);
		$puskesmas_code = trim((string) ($request->assigned_puskesmas_code ?? ''));
		if ($patient_id > 0) {
			doclinc_notify_user($patient_id, $event_type, 'request', (int) $request_id, $title, $message, (int) $actor_user_id);
		}
		if ($puskesmas_code !== '') {
			doclinc_notify_puskesmas($puskesmas_code, $event_type, 'request', (int) $request_id, $title, $message, (int) $actor_user_id);
		}
	}

	public function assign_visit_performer()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->care_team_response(405, array('status' => 'error', 'message' => 'Metode tidak diizinkan.'));
			return;
		}
		if (!$this->care_team_post_ready()) {
			return;
		}
		$user_id = (int) $this->session->userdata('id');
		$this->load->library('Care_team_service');
		$result = $this->care_team_service->assignVisitPerformer(
			(int) $this->input->post('request_id'),
			(int) $this->input->post('staff_id'),
			$user_id,
			$this->session->userdata('normal_session_token'),
			$this->config->item('single_active_session_enabled') === true
		);
		$this->care_team_response(!empty($result['ok']) ? 200 : 409, $result);
	}

	private function care_team_post_ready()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->care_team_response(405, array('status' => 'error', 'message' => 'Metode tidak diizinkan.'));
			return false;
		}
		if ($this->config->item('care_team_workflow_enabled') !== true) {
			$this->care_team_response(403, array('status' => 'error', 'message' => 'Layanan belum tersedia.'));
			return false;
		}
		if (!$this->require_dokter_session()) {
			return false;
		}
		return true;
	}

	private function care_team_response($status, array $result)
	{
		if ($this->input->is_ajax_request()
			|| strpos(strtolower((string) $this->input->server('HTTP_ACCEPT')), 'application/json') !== false) {
			$this->output->set_content_type('application/json')->set_header('Cache-Control: no-store')
				->set_status_header((int) $status)->set_output(json_encode($result));
			return;
		}
		$this->session->set_flashdata(
			!empty($result['ok']) ? 'care_team_success' : 'care_team_error',
			(string) ($result['message'] ?? 'Coba lagi.')
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
		if ($this->reject_legacy_staff_assignment_when_care_team_enabled()) {
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
			if ($this->staff_assignment_json_requested()) {
				$this->respond_staff_assignment_json(403, array('status' => 'error', 'message' => 'Anda tidak memiliki akses.'), $request_id);
				return;
			}
			$this->session->set_flashdata('staff_assignment_error', 'Anda tidak memiliki akses.');
			redirect('home_nakes#riwayat_konsul');
			return;
		}
		if (!$this->require_role_prerequisites($user_id, false)) {
			return;
		}

		$result = $this->Home_nakes_m->clear_staff_assignment($request_id, $puskesmas_code, $user_id, $identity_context);
		$message = !empty($result['message']) ? $result['message'] : 'Gagal menghapus penugasan.';
		if ($this->staff_assignment_json_requested()) {
			$result['message'] = $message;
			$this->respond_staff_assignment_json(
				isset($result['status']) && $result['status'] === 'success' ? 200 : 409,
				$result,
				$request_id,
				false
			);
			return;
		}
		$this->session->set_flashdata(
			isset($result['status']) && $result['status'] === 'success' ? 'staff_assignment_success' : 'staff_assignment_error',
			$message
		);
		redirect('home_nakes#riwayat_konsul');
	}

	public function tes_save_lokasi()
	{
		show_404();
	}

	public function save_location()
	{
		show_404();
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
				->set_output(json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses.']));
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
				->set_output(json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses.']));
			return;
		}
		if (!$this->require_role_prerequisites($id_user)) {
			return;
		}

		$result = $this->Home_nakes_m->accept_request($id, $id_user, $latitude, $longitude, $puskesmas_code, $identity_context);
		if (!empty($result['status']) && $result['status'] === 'success') {
			if (empty($result['already_accepted'])) {
				doclinc_log_request_event('request_accepted', $id);
				$request = doclinc_request_row($id);
			}
			$orchestration = doclinc_request_transition_orchestrator()->requestAccepted(
				$id,
				isset($request) ? $request : doclinc_request_row($id),
				$id_user,
				$result
			);
			$this->output->set_output(json_encode($orchestration['response']));
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
				->set_output(json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses.']));
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
				->set_output(json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses.']));
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
		$orchestration = doclinc_request_transition_orchestrator()->requestCancelledByCommandCenter(
			$request_id,
			$request,
			$user_id,
			$result
		);
		$this->output->set_output(json_encode($orchestration['response']));
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
				->set_output(json_encode(array('status' => false, 'message' => 'Anda tidak memiliki akses.')));
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
		if ((string) $request->request_status === 'Pending') {
			if (empty($access_context['can_view']) || empty($access_context['tenant_match']) || (string) ($identity_context['account_type'] ?? '') !== 'command_center') {
				$this->output->set_status_header(403)->set_output(json_encode(array('status' => false, 'message' => 'Anda tidak memiliki akses.')));
				return;
			}
			$this->output->set_output(json_encode(array(
				'status' => 'patient_only', 'success' => true, 'request_id' => $request_id,
				'request_status' => 'Pending',
				'patient' => array('lat' => isset($request->lattitude) ? (float) $request->lattitude : null, 'lng' => isset($request->longitude) ? (float) $request->longitude : null, 'address' => isset($request->location) ? (string) $request->location : ''),
				'patient_location_only' => true, 'tracking_active' => false, 'nakes' => null, 'route' => null, 'eta' => null,
				'viewer_can_update' => false, 'message' => 'Lokasi pasien untuk triase. Pelacakan kunjungan belum aktif.'
			)));
			return;
		}
		$monitoring_access = Visit_monitoring_policy::resolve($identity_context, $access_context, $request);
		if (empty($monitoring_access['allowed'])) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('status' => false, 'message' => 'Anda tidak memiliki akses.')));
			return;
		}
		$current_visit_status = isset($request->visit_status) && $request->visit_status !== null
			? doclinc_normalize_visit_status($request->visit_status)
			: '';
		$current_visit_status = $current_visit_status !== '' ? $current_visit_status : 'not_started';
		if ($request->request_status !== 'Accepted' || strtolower((string) ($request->consultation_mode ?? '')) !== 'visit' || $current_visit_status === 'completed') {
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

		$this->output->set_output(json_encode($this->build_nakes_visit_location_payload(
			$row,
			true,
			null,
			null,
			!empty($monitoring_access['can_update'])
		)));
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
				->set_output(json_encode(['status' => 'error', 'success' => false, 'message' => 'Anda tidak memiliki akses.']));
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
			$location_message = $request_id < 1
				? 'Permintaan tidak valid.'
				: 'Lokasi Nakes tidak valid.';
			$this->output
				->set_status_header(400)
				->set_output(json_encode([
					'status' => 'error',
					'success' => false,
					'reason' => 'invalid_coordinate',
					'message' => $location_message
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
		$can_visit = $this->config->item('care_team_workflow_enabled') === true
			? !empty($access_context['can_visit'])
			: !empty($access_context['can_handle']);
		if (!$can_visit) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'success' => false, 'message' => 'Anda tidak memiliki akses.']));
			return;
		}
		if (!$this->require_role_prerequisites($user_id)) {
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
				->set_output(json_encode(['status' => 'error', 'success' => false, 'message' => 'Lokasi belum dapat diperbarui.']));
			return;
		}

		$row = $this->Home_nakes_m->get_visit_location($request_id, $user_id, $identity_context);
		$payload = $row ? $this->build_nakes_visit_location_payload($row, 'success', (float) $latitude, (float) $longitude, true) : array();
		$this->output->set_output(json_encode(array_merge($payload, [
			'status' => 'success',
			'success' => true,
			'tracking_active' => true,
			'message' => 'Lokasi Nakes diperbarui.',
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
				->set_output(json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses.']));
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
		$can_visit = $this->config->item('care_team_workflow_enabled') === true
			? !empty($access_context['can_visit'])
			: !empty($access_context['can_handle']);
		if (!$can_visit) {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'visit_status', 'visit_status' => $visit_status));
			}
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses.']));
			return;
		}
		if (!$this->require_role_prerequisites($user_id)) {
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

		if (!empty($result['changed'])) {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('visit_status_updated', $request_id, array('visit_status' => $result['visit_status']));
			}
			$this->notify_visit_status($request, $user_id, $result['visit_status'], $result['visit_status_label']);
		}

		$this->output->set_output(json_encode(array(
			'status' => 'success',
			'message' => $result['message'],
			'request_id' => $request_id,
			'visit_status' => $result['visit_status'],
			'visit_status_label' => $result['visit_status_label'],
		)));
	}

	private function notify_visit_status($request, $actor_user_id, $visit_status, $visit_status_label)
	{
		if (!$request) {
			return;
		}
		$event_type = 'visit_' . (string) $visit_status;
		$title = 'Kunjungan diperbarui';
		$message = 'Status kunjungan: ' . (string) $visit_status_label . '.';
		$patient_id = (int) ($request->user_id ?? 0);
		$responsible_id = (int) ($request->responsible_doctor_user_id ?? 0);
		$puskesmas_code = trim((string) ($request->assigned_puskesmas_code ?? ''));
		if ($patient_id > 0) {
			doclinc_notify_user($patient_id, $event_type, 'request', (int) $request->request_id, $title, $message, (int) $actor_user_id);
		}
		if ($responsible_id > 0 && $responsible_id !== (int) $actor_user_id) {
			doclinc_notify_user($responsible_id, $event_type, 'request', (int) $request->request_id, $title, $message, (int) $actor_user_id);
		}
		if ($puskesmas_code !== '') {
			doclinc_notify_puskesmas($puskesmas_code, $event_type, 'request', (int) $request->request_id, $title, $message, (int) $actor_user_id);
		}
	}
	public function get_location_user()
	{
		show_404();
	}
	private function get_estimation($origin_lat, $origin_lng, $dest_lat, $dest_lng)
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
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_CONNECTTIMEOUT => 2,
			CURLOPT_TIMEOUT => 5,
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
		if ($this->session->userdata('role') !== 'dokter') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode([
					'status' => 'error',
					'safe_error_code' => 'actor_denied',
					'message' => 'Anda tidak memiliki akses.',
				]));
			return;
		}
		$response = ['status' => 'error', 'message' => 'Gagal menyimpan'];

		$id = (int) $this->session->userdata('id');
		$identity = doclinc_dokter_identity_context($id, true);
		if (empty($identity['valid']) || !in_array((string) ($identity['account_type'] ?? ''), array('personal', 'command_center'), true)) {
			$this->output->set_status_header(403)->set_output(json_encode(array(
				'status' => 'error',
				'safe_error_code' => 'actor_denied',
				'message' => 'Identitas akun belum valid.',
			)));
			return;
		}
		$validated = $this->validated_profile_input((string) $identity['account_type']);
		if ($validated['success'] !== true) {
			$this->output
				->set_status_header(422)
				->set_output(json_encode([
					'status' => 'error',
					'safe_error_code' => 'profile_validation_failed',
					'message' => $validated['message'],
				]));
			return;
		}
		$data = $validated['data'];
		if (!$this->Home_nakes_m->email_available_for_user($id, $data['email'])) {
			$this->output
				->set_status_header(409)
				->set_output(json_encode(array(
					'status' => 'error',
					'safe_error_code' => 'profile_email_conflict',
					'message' => 'Email sudah digunakan akun lain.',
				)));
			return;
		}
		$uploaded_profile_key = '';
		$profile_storage = null;

		// Upload foto jika ada
		if (!empty($_FILES['foto']['name'])) {
			require_once APPPATH . 'libraries/Profile_image_storage.php';
			$profile_storage = new Profile_image_storage(array(
				'storage_path' => $this->config->item('profile_image_storage_path'),
				'public_root' => FCPATH,
			));
			$upload_directory = $profile_storage->ensure_storage_directory();
			if ($upload_directory === false) {
				$this->output
					->set_status_header(503)
					->set_output(json_encode(array('status' => 'error', 'message' => 'Penyimpanan foto profil belum siap.')));
				return;
			}

			$config['upload_path']   = $upload_directory;
			$config['allowed_types'] = 'jpg|jpeg|png|webp';
			$config['max_size']      = max(1, (int) $this->config->item('profile_image_max_size_kb'));
			$config['encrypt_name']  = TRUE;
			$config['detect_mime']   = TRUE;
			$config['mod_mime_fix']  = TRUE;
			$config['remove_spaces'] = TRUE;

			$this->load->library('upload', $config);
			if ($this->upload->do_upload('foto')) {
				$uploadData = $this->upload->data();
				$uploaded_profile_key = $profile_storage->stored_key_from_upload($uploadData);
				$uploaded_profile_path = isset($uploadData['full_path']) ? (string) $uploadData['full_path'] : '';
				if (!$profile_storage->upload_is_valid($uploaded_profile_path, $uploaded_profile_key, $config['max_size'])) {
					if ($uploaded_profile_path !== '' && is_file($uploaded_profile_path)) {
						@unlink($uploaded_profile_path);
					}
					$this->output
						->set_status_header(422)
						->set_output(json_encode(array('status' => 'error', 'message' => 'Isi file foto tidak valid. Gunakan JPG, PNG, atau WebP.')));
					return;
				}
				@chmod($uploaded_profile_path, 0600);
				$data['foto'] = $uploaded_profile_key;
			} else {
				$response['message'] = 'Foto profil belum dapat diunggah. Periksa file dan coba lagi.';
				$this->output->set_status_header(422)->set_output(json_encode($response));
				return;
			}
		}

		// Simpan lewat model
		if ($this->Home_nakes_m->update_profile($id, $data)) {
			$this->session->set_userdata($data);
			$this->output->set_output(json_encode(['status' => 'success', 'message' => 'Profil diperbarui.']));
		} else {
			if ($profile_storage && $uploaded_profile_key !== '') {
				$profile_storage->remove_private_file($uploaded_profile_key);
			}
			$this->output->set_status_header(500);
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Gagal memperbarui profil.']));
		}
	}

	private function validated_profile_input($account_type)
	{
		$name = trim(strip_tags((string) $this->input->post('nama_lengkap')));
		$name = preg_replace('/\s+/u', ' ', $name);
		$email = strtolower(trim((string) $this->input->post('email')));
		$phone = preg_replace('/[\s().-]+/', '', trim((string) $this->input->post('no_hp')));
		$birthdate = trim((string) $this->input->post('tgl_lahir'));
		$gender = trim((string) $this->input->post('jk'));
		$address = trim(strip_tags((string) $this->input->post('alamat')));
		$address = preg_replace('/[\t ]+/u', ' ', $address);

		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 100) {
			return ['success' => false, 'message' => 'Email belum valid.'];
		}
		if (!preg_match('/^\+?[0-9]{8,20}$/', $phone)) {
			return ['success' => false, 'message' => 'Nomor HP belum valid. Gunakan 8 sampai 20 angka.'];
		}
		if ($account_type === 'command_center') {
			return [
				'success' => true,
				'data' => [
					'email' => $email,
					'no_hp' => $phone,
				],
			];
		}
		if ($account_type !== 'personal') {
			return ['success' => false, 'message' => 'Identitas akun belum valid.'];
		}
		if ($name === '' || $this->profile_text_length($name) < 2 || $this->profile_text_length($name) > 100) {
			return ['success' => false, 'message' => 'Nama lengkap harus terdiri dari 2 sampai 100 karakter.'];
		}
		$birthdate_value = DateTime::createFromFormat('!Y-m-d', $birthdate);
		if (!$birthdate_value || $birthdate_value->format('Y-m-d') !== $birthdate || $birthdate_value > new DateTime('today')) {
			return ['success' => false, 'message' => 'Tanggal lahir belum valid.'];
		}
		if (!in_array($gender, ['Laki-laki', 'Perempuan'], true)) {
			return ['success' => false, 'message' => 'Jenis kelamin belum valid.'];
		}
		if ($address !== '' && ($this->profile_text_length($address) < 5 || $this->profile_text_length($address) > 500)) {
			return ['success' => false, 'message' => 'Alamat harus terdiri dari 5 sampai 500 karakter.'];
		}

		return [
			'success' => true,
			'data' => [
				'nama' => $name,
				'email' => $email,
				'no_hp' => $phone,
				'tgl' => $birthdate,
				'gender' => $gender,
				'alamat' => $address,
			],
		];
	}

	private function profile_text_length($value)
	{
		return function_exists('mb_strlen') ? mb_strlen((string) $value, 'UTF-8') : strlen((string) $value);
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
		if ($this->staff_assignment_json_requested()) {
			$this->respond_staff_assignment_json(403, array('status' => 'error', 'message' => 'Anda tidak memiliki akses.'), (int) $this->input->post('request_id'));
			return false;
		}

		$role = $this->session->userdata('role');
		if ($role === 'warga') {
			redirect('home');
			return false;
		}
		if ($role === 'admin') {
			redirect('admin_menu/');
			return false;
		}

		redirect('login');
		return false;
	}

	private function require_role_prerequisites($user_id, $json_only = true, $require_complete = false)
	{
		$state = doclinc_role_prerequisite_state((int) $user_id, true);
		if (!empty($state['allowed']) && (!$require_complete || !empty($state['complete']))) {
			return true;
		}

		$payload = doclinc_role_prerequisite_error_payload($state);
		if ($json_only || $this->staff_assignment_json_requested()) {
			$this->output
				->set_content_type('application/json')
				->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
				->set_status_header(doclinc_role_prerequisite_http_status($state))
				->set_output(json_encode($payload));
			return false;
		}

		$this->session->set_flashdata('staff_assignment_error', $payload['message']);
		redirect('home_nakes#profile');
		return false;
	}

	private function staff_assignment_json_requested()
	{
		$accept = isset($_SERVER['HTTP_ACCEPT']) ? (string) $_SERVER['HTTP_ACCEPT'] : '';
		return $this->input->is_ajax_request() || stripos($accept, 'application/json') !== false;
	}

	private function reject_legacy_staff_assignment_when_care_team_enabled()
	{
		if ($this->config->item('care_team_workflow_enabled') !== true) {
			return false;
		}

		$request_id = (int) $this->input->post('request_id');
		$message = 'Pengaturan ini sudah tidak tersedia.';
		if ($this->staff_assignment_json_requested()) {
			$this->respond_staff_assignment_json(403, array('status' => 'error', 'message' => $message), $request_id);
			return true;
		}

		$this->session->set_flashdata('staff_assignment_error', $message);
		redirect('home_nakes#riwayat_konsul');
		return true;
	}

	private function respond_staff_assignment_json($http_status, array $result, $request_id, $expect_assignment = null)
	{
		$success = isset($result['status']) && $result['status'] === 'success';
		$assignment = null;
		if ($success) {
			$current = $this->Home_nakes_m->get_active_staff_assignment((int) $request_id);
			$snapshot_valid = $expect_assignment === null
				|| ($expect_assignment === true && $current)
				|| ($expect_assignment === false && !$current);
			if (!$snapshot_valid) {
				$success = false;
				$http_status = 500;
				$result['message'] = 'Penanggung jawab berhasil diproses. Muat ulang untuk melihat status terbaru.';
			}
			if ($current) {
				$assignment = array(
					'staff_id' => (int) $current->staff_id,
					'staff_name' => (string) $current->staff_nama,
					'staff_profession' => isset($current->staff_profesi) ? (string) $current->staff_profesi : '',
					'staff_contact' => isset($current->staff_no_hp) ? (string) $current->staff_no_hp : '',
				);
			}
		}

		$body = array(
			'status' => $success ? 'success' : 'error',
			'message' => isset($result['message']) ? (string) $result['message'] : ($success ? 'Penanggung jawab diperbarui.' : 'Gagal memperbarui penanggung jawab.'),
			'request_id' => (int) $request_id,
			'assignment' => $assignment,
		);
		if (!$success && isset($result['safe_error_code']) && preg_match('/^[a-z0-9_]{1,80}$/', (string) $result['safe_error_code'])) {
			$body['safe_error_code'] = (string) $result['safe_error_code'];
		}
		$this->output
			->set_content_type('application/json')
			->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
			->set_status_header((int) $http_status)
			->set_output(json_encode($body));
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

	private function build_nakes_visit_location_payload($row, $status, $override_nakes_latitude = null, $override_nakes_longitude = null, $viewer_can_update = false)
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
			if (!$viewer_can_update) {
				$arrival['should_prompt_arrival'] = false;
			}
		}

		return array(
			'status' => $status,
			'success' => $status === true || $status === 'success',
			'viewer_can_update' => (bool) $viewer_can_update,
			'viewer_mode' => $viewer_can_update ? 'operator' : 'monitor',
			'location_updated_at' => isset($row->updated_at) ? $row->updated_at : null,
			'location_updated_text' => isset($row->updated_at) && strtotime((string) $row->updated_at)
				? date('d M Y H:i', strtotime((string) $row->updated_at))
				: null,
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
