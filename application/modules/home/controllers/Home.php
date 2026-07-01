<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Home extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Home_m');
		$this->load->model('chat/Call_session_m', 'Call_session_m');
		$this->load->helper('request_authz');
		$this->load->helper('visit_routing');
		$this->load->helper('livekit');
		$this->load->helper('notification');
		if ($this->session->userdata('logged_in') != TRUE) {
			if (in_array($this->router->fetch_method(), array('visit_location', 'livekit_token', 'livekit_incoming_call', 'answer_livekit_call', 'reject_livekit_call', 'livekit_call_status'), true)) {
				$this->output
					->set_content_type('application/json')
					->set_status_header(401)
					->set_output(json_encode(['status' => 'error', 'message' => 'Login diperlukan']));
				$this->output->_display();
				exit;
			}
			redirect('login');
		}
		$this->load->helper('maps');
	}

	public function livekit_token()
	{
		$this->output->set_content_type('application/json');
		if ($this->session->userdata('role') !== 'warga') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$request_id = (int) ($this->input->post('request_id') ?: $this->input->get('request_id', TRUE));
		$active_call = $this->Call_session_m->get_active_by_request($request_id);
		if (!$active_call || (string) $active_call->callee_user_id !== (string) $this->session->userdata('id')) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('success' => false, 'message' => 'Panggilan masuk tidak tersedia')));
			return;
		}

		$result = doclinc_livekit_token_payload($request_id, (int) $this->session->userdata('id'), 'warga');
		$this->output
			->set_status_header((int) $result['http_status'])
			->set_output(json_encode($result['body']));
	}

	public function livekit_incoming_call()
	{
		$this->output->set_content_type('application/json');
		try {
			if ($this->session->userdata('role') !== 'warga') {
				$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'has_incoming' => false, 'message' => 'Akses tidak diizinkan')));
				return;
			}

			if (!$this->Call_session_m->table_ready()) {
				$this->output->set_status_header(503)->set_output(json_encode(array('success' => false, 'has_incoming' => false, 'message' => 'Fitur panggilan belum siap.')));
				return;
			}

			$user_id = (int) $this->session->userdata('id');
			$request_id = (int) $this->livekit_request_value('request_id');
			if ($request_id > 0) {
				$request = doclinc_request_row($request_id);
				if (!$request || (string) $request->user_id !== (string) $user_id || $request->request_status !== 'Accepted') {
					$this->output->set_output(json_encode(array('success' => true, 'has_incoming' => false, 'message' => 'Tidak ada panggilan aktif')));
					return;
				}
			}

			$call = $this->Call_session_m->get_latest_incoming_for_warga($user_id, $request_id);
			if (!$call) {
				$this->output->set_output(json_encode(array('success' => true, 'has_incoming' => false, 'message' => 'Belum ada panggilan masuk')));
				return;
			}

			$payload = $this->Call_session_m->format_call($call);
			$request = doclinc_request_row((int) $payload['request_id']);
			$payload['queue_code'] = $request ? doclinc_request_queue_code($request) : '';
			$payload['queue_label'] = $request ? doclinc_request_queue_number_label($request) : '';
			$payload['success'] = true;
			$payload['has_incoming'] = true;
			$payload['message'] = 'Panggilan masuk';
			$this->output->set_output(json_encode($payload));
		} catch (Exception $e) {
			log_message('error', 'livekit_incoming_call failed: ' . $e->getMessage());
			$this->output->set_status_header(500)->set_output(json_encode(array('success' => false, 'has_incoming' => false, 'message' => 'Panggilan belum dapat diperiksa')));
		}
	}

	public function answer_livekit_call()
	{
		$this->output->set_content_type('application/json');
		if ($this->session->userdata('role') !== 'warga') {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$call_id = (int) $this->livekit_request_value('call_id');
		$call = $this->Call_session_m->get_by_id($call_id);
		$user_id = (int) $this->session->userdata('id');
		if (!$call || (string) $call->callee_user_id !== (string) $user_id || !in_array($call->status, array('ringing', 'answered'), true)) {
			$this->output->set_status_header(404)->set_output(json_encode(array('success' => false, 'message' => 'Panggilan tidak tersedia')));
			return;
		}

		$request = doclinc_request_row((int) $call->request_id);
		if (!$request || $request->request_status !== 'Accepted' || (string) $request->user_id !== (string) $user_id) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Panggilan tidak dapat dijawab')));
			return;
		}

		$call = $this->Call_session_m->answer($call_id);
		$result = doclinc_livekit_token_payload((int) $call->request_id, $user_id, 'warga');
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

	public function reject_livekit_call()
	{
		$this->output->set_content_type('application/json');
		if ($this->session->userdata('role') !== 'warga') {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$call_id = (int) $this->livekit_request_value('call_id');
		$call = $this->Call_session_m->get_by_id($call_id);
		$user_id = (int) $this->session->userdata('id');
		if (!$call || (string) $call->callee_user_id !== (string) $user_id) {
			$this->output->set_status_header(404)->set_output(json_encode(array('success' => false, 'message' => 'Panggilan tidak tersedia')));
			return;
		}
		$request = doclinc_request_row((int) $call->request_id);
		if (!$request || $request->request_status !== 'Accepted' || (string) $request->user_id !== (string) $user_id) {
			$this->output->set_status_header(403)->set_output(json_encode(array('success' => false, 'message' => 'Panggilan tidak dapat ditolak')));
			return;
		}
		if ($call->status !== 'ringing') {
			$this->output->set_status_header(409)->set_output(json_encode(array('success' => false, 'message' => 'Panggilan sudah tidak berdering', 'status' => $call->status)));
			return;
		}

		$this->Call_session_m->reject($call_id);
		$this->output->set_output(json_encode(array('success' => true, 'call_id' => $call_id, 'status' => 'rejected', 'message' => 'Panggilan ditolak')));
	}

	public function livekit_call_status()
	{
		$this->output->set_content_type('application/json');
		$call_id = (int) $this->livekit_request_value('call_id');
		$call = $this->Call_session_m->get_by_id($call_id);
		$user_id = (int) $this->session->userdata('id');
		if (!$call || (string) $call->callee_user_id !== (string) $user_id) {
			$this->output->set_status_header(404)->set_output(json_encode(array('success' => false, 'message' => 'Panggilan tidak tersedia')));
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
		if ($this->session->userdata('logged_in') == TRUE) {
			$role = $this->session->userdata('role');
			if ($role == 'warga') {
				$userid = $this->session->userdata('id');

				$x['data_feeds'] = $this->Home_m->get_data_feeds();
				$x['data_profile'] = $this->Home_m->get_data_profile($this->session->userdata('id'));

				if ($userid) {
					$statuses = ['Pending', 'Accepted'];
					$x['getAllDataRequests'] = $this->Home_m->getAllDataRequests($userid, $statuses);
					$x['active_consultation_request'] = doclinc_active_consultation_request($userid);
				} else {
					$x['getAllDataRequests'] = [];
					$x['active_consultation_request'] = null;
				}

				$x['getAllDataRequestsCompleted'] = $this->Home_m->getAllDataRequestsCompleted($userid);
				$x['getAllDataDoctor'] = $this->Home_m->getAllDataDoctor($this->session->userdata('remark'));
				$x['getAllRequestPendingAccept'] = $this->Home_m->getAllRequestPendingAccept(
					$this->session->userdata('id')
				);
				$x['getAllRating'] = $this->Home_m->getAllRating();
				$x['getAllRequestJumlah'] = $this->Home_m->getAllRequestJumlah();

				$x['dataDoctor'] = $this->Home_m->getDataDoctor();

				$this->load->view('home_v', $x);
			} elseif ($role == 'dokter') {
				redirect('/home_nakes');
			} elseif ($role == 'admin') {
				redirect('/home_admin');
			} else {
				echo 'Error: User role not recognized';
			}
		} else {
			redirect('login');
			// $this->load->view('login_v');
		}
	}
	public function save_konsultasi()
	{
		if (!$this->require_post_json()) {
			return;
		}
		$nama = $this->input->post('nama');
		$keluhan = $this->input->post('keluhan');
		$no_hp = $this->input->post('no_hp');
		$lat = $this->input->post('lat');
		$lng = $this->input->post('lng');
		$alamat = $this->input->post('alamat');
		$tanggal = $this->input->post('tanggal');

		$this->Home_m->saveKonsultasi($nama, $keluhan, $no_hp, $lat, $lng, $alamat, $tanggal);

		//	echo json_encode(['status' => 'success', 'message' => 'Data berhasil disimpan']);
	}

	public function updateRequestById()
	{
		if (!$this->require_post_json()) {
			return;
		}
		$id = (int) $this->input->post('requestId');
		if ($id < 1 || !doclinc_can_view_request($id, $this->session->userdata('id'), 'warga')) {
			doclinc_log_request_event('unauthorized_request_update', $id, array('target' => 'warga_update'));
			$this->output->set_status_header(403)->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}
		if ($this->Home_m->updateRequestById($id)) {
			$response = (['status' => 'success', 'message' => 'Data berhasil diupdate']);
		} else {
			doclinc_log_request_event('unauthorized_request_update', $id, array('target' => 'warga_update'));
			$response = (['status' => 'error', 'message' => 'Gagal mengupdate data']);
		}
		$this->output->set_output(json_encode($response));
	}

	public function deleterequestbyid()
	{
		if (!$this->require_post_json()) {
			return;
		}
		$id = (int) $this->input->post('requestId');
		if ($id < 1 || !doclinc_can_view_request($id, $this->session->userdata('id'), 'warga')) {
			doclinc_log_request_event('unauthorized_request_update', $id, array('target' => 'warga_delete'));
			$this->output->set_status_header(403)->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}
		if ($this->Home_m->deleteRequestById($id)) {
			$this->output->set_output(json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus']));
			return;
		}

		doclinc_log_request_event('unauthorized_request_update', $id, array('target' => 'warga_delete'));
		$this->output->set_status_header(403);
		$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
	}

	public function cancel_request()
	{
		if (!$this->require_post_json()) {
			return;
		}

		$request_id = (int) ($this->input->post('request_id') ?: $this->input->post('requestId'));
		$user_id = (int) $this->session->userdata('id');
		$role = $this->session->userdata('role');
		if ($role !== 'warga' || $request_id < 1 || !doclinc_can_cancel_request($request_id, $user_id, $role)) {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'warga_cancel'));
			}
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$request = doclinc_request_row($request_id);
		if (!$this->Home_m->cancel_request($request_id, $user_id)) {
			if (function_exists('doclinc_log_request_event')) {
				doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'warga_cancel'));
			}
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Request tidak dapat dibatalkan']));
			return;
		}

		if (function_exists('doclinc_log_request_event')) {
			doclinc_log_request_event('request_cancelled', $request_id);
		}
		if ($request && function_exists('doclinc_notify_user')) {
			$recipient_id = doclinc_request_handling_nakes_id($request);
			if (!empty($recipient_id)) {
				doclinc_notify_user(
					$recipient_id,
					'request_cancelled',
					'request',
					$request_id,
					'Konsultasi dibatalkan',
					'Permintaan konsultasi dibatalkan oleh pasien.',
					$user_id
				);
			}
		}

		$this->output->set_output(json_encode([
			'status' => 'success',
			'message' => 'Request berhasil dibatalkan',
			'request_id' => $request_id,
			'request_status' => 'Cancelled'
		]));
	}

	public function visit_location()
	{
		$this->output->set_content_type('application/json');
		$default_route = function_exists('doclinc_visit_route_pending_payload') ? doclinc_visit_route_pending_payload() : array();
		$default_arrival = function_exists('doclinc_visit_arrival_payload') ? doclinc_visit_arrival_payload(null, null, null, null, null) : array();
		if ($this->input->method(TRUE) !== 'GET') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan', 'route' => $default_route, 'arrival' => $default_arrival]));
			return;
		}

		$request_id = (int) $this->input->get('request_id', TRUE);
		$user_id = (int) $this->session->userdata('id');
		$role = $this->session->userdata('role');
		if ($request_id < 1) {
			$this->output
				->set_status_header(400)
				->set_output(json_encode(['status' => 'error', 'message' => 'Data request tidak valid', 'route' => $default_route, 'arrival' => $default_arrival]));
			return;
		}
		if ($role !== 'warga') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan', 'route' => $default_route, 'arrival' => $default_arrival]));
			return;
		}

		$request = doclinc_request_row($request_id);
		if (!$request) {
			$this->output
				->set_status_header(404)
				->set_output(json_encode(['status' => 'error', 'message' => 'Request tidak ditemukan', 'route' => $default_route, 'arrival' => $default_arrival]));
			return;
		}
		if ((string) $request->user_id !== (string) $user_id) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan', 'route' => $default_route, 'arrival' => $default_arrival]));
			return;
		}
		if ($request->request_status !== 'Accepted') {
			$terminal_visit_status = isset($request->visit_status) && $request->visit_status !== null
				? doclinc_normalize_visit_status($request->visit_status)
				: '';
			$terminal_visit_status = $terminal_visit_status !== '' ? $terminal_visit_status : 'not_started';
			$this->output->set_output(json_encode(array(
				'status' => 'inactive',
				'success' => false,
				'tracking_active' => false,
				'message' => 'Tracking kunjungan sudah selesai.',
				'request_id' => $request_id,
				'request_status' => $request->request_status,
				'visit_status' => $terminal_visit_status,
				'visit_status_label' => doclinc_visit_status_label($terminal_visit_status),
				'route' => $default_route,
				'arrival' => $default_arrival,
			)));
			return;
		}
		if (!doclinc_can_view_visit_location($request_id, $user_id, $role)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan', 'route' => $default_route, 'arrival' => $default_arrival]));
			return;
		}

		$row = $this->Home_m->get_visit_location($request_id, $user_id);
		if (!$row) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan', 'route' => $default_route, 'arrival' => $default_arrival]));
			return;
		}

		$visit_status = isset($row->visit_status) && $row->visit_status !== null
			? doclinc_normalize_visit_status($row->visit_status)
			: '';
		$visit_status = $visit_status !== '' ? $visit_status : 'not_started';
		$visit_workflow = [
			'request_id' => $request_id,
			'request_status' => $row->request_status,
			'visit_status' => $visit_status,
			'visit_status_label' => doclinc_visit_status_label($visit_status),
			'consultation_mode' => isset($row->consultation_mode) ? $row->consultation_mode : null,
			'consultation_mode_label' => function_exists('doclinc_consultation_mode_label') ? doclinc_consultation_mode_label(isset($row->consultation_mode) ? $row->consultation_mode : null) : '',
			'visit_started_at' => isset($row->visit_started_at) ? $row->visit_started_at : null,
			'visit_arrived_at' => isset($row->visit_arrived_at) ? $row->visit_arrived_at : null,
			'visit_in_service_at' => isset($row->visit_in_service_at) ? $row->visit_in_service_at : null,
			'visit_completed_at' => isset($row->visit_completed_at) ? $row->visit_completed_at : null,
			'route' => $default_route,
			'arrival' => $default_arrival,
			'tracking_active' => true,
		];

		$patient_latitude = null;
		$patient_longitude = null;
		if ($this->is_valid_latitude($row->patient_latitude) && $this->is_valid_longitude($row->patient_longitude)) {
			$patient_latitude = (float) $row->patient_latitude;
			$patient_longitude = (float) $row->patient_longitude;
		} elseif ($this->is_valid_latitude($row->lattitude) && $this->is_valid_longitude($row->longitude)) {
			$patient_latitude = (float) $row->lattitude;
			$patient_longitude = (float) $row->longitude;
		}

		$nakes_latitude = null;
		$nakes_longitude = null;
		if ($this->is_valid_latitude($row->lattitude_dokter) && $this->is_valid_longitude($row->longitude_dokter)) {
			$nakes_latitude = (float) $row->lattitude_dokter;
			$nakes_longitude = (float) $row->longitude_dokter;
		}
		if ($patient_latitude !== null && $patient_longitude !== null && $nakes_latitude !== null && $nakes_longitude !== null) {
			$visit_workflow['route'] = doclinc_visit_route_payload(
				$nakes_latitude,
				$nakes_longitude,
				$patient_latitude,
				$patient_longitude
			);
			$visit_workflow['arrival'] = doclinc_visit_arrival_payload(
				$nakes_latitude,
				$nakes_longitude,
				$patient_latitude,
				$patient_longitude,
				$visit_status
			);
			if ($row->request_status !== 'Accepted') {
				$visit_workflow['arrival']['should_prompt_arrival'] = false;
			}
		}

		$location_payload = [
			'nakes' => [
				'latitude' => $nakes_latitude,
				'longitude' => $nakes_longitude,
				'lat' => $nakes_latitude,
				'lng' => $nakes_longitude,
				'accuracy' => isset($row->nakes_accuracy) && is_numeric($row->nakes_accuracy) ? (float) $row->nakes_accuracy : null,
				'heading' => isset($row->nakes_heading) && is_numeric($row->nakes_heading) ? (float) $row->nakes_heading : null,
				'speed' => isset($row->nakes_speed) && is_numeric($row->nakes_speed) ? (float) $row->nakes_speed : null,
				'updated_at' => isset($row->updated_at) ? $row->updated_at : null,
			],
			'patient' => [
				'latitude' => $patient_latitude,
				'longitude' => $patient_longitude,
				'lat' => $patient_latitude,
				'lng' => $patient_longitude,
			],
		];

		if ($nakes_latitude === null || $nakes_longitude === null) {
			$this->output->set_output(json_encode(array_merge([
				'status' => 'pending',
				'success' => true,
				'tracking_active' => true,
				'message' => 'Lokasi nakes belum tersedia',
			], $visit_workflow, $location_payload)));
			return;
		}

		$this->output->set_output(json_encode(array_merge([
			'status' => 'success',
			'success' => true,
			'tracking_active' => true,
			'message' => 'Lokasi nakes tersedia',
		], $visit_workflow, $location_payload)));
	}

	public function submit_rating()
	{
		if (!$this->require_post_json()) {
			return;
		}
		$iduser = $this->session->userdata('id');
		$iddokter = (int) $this->input->post('id_dokter');
		$rating = $this->input->post('rating');

		$result = $this->Home_m->submit_rating($iduser, $iddokter, $rating);

		if ($result) {
			$this->output->set_output(json_encode(['status' => 'success', 'message' => 'Rating berhasil dikirim']));
		} else {
			$this->output->set_output(json_encode(['status' => 'error', 'message' => 'Gagal mengirim rating']));
		}
	}

	public function getDokterRating()
	{
		$id_dokter = $this->input->post('id_dokter');
		$result = $this->Home_m->getDokterRating($id_dokter)->result_array();
		echo json_encode($result);
	}

	public function getDuration()
	{
		$this->load->model('Home_m');
		$kode_pkm = strval($this->session->userdata('remark'));
		$nama = $this->Home_m->getAllDataDoctors($kode_pkm);
		$nama = array_column($nama, 'name');

		$coordinate = $this->Home_m->getAllDataLocations($nama);
		$mode = 'driving';

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$latitudeB = $_POST['latitude'] ?? '';
			$longitudeB = $_POST['longitude'] ?? '';

			if (empty($latitudeB) || empty($longitudeB)) {
				http_response_code(400);
				echo json_encode(['status' => 'error', 'message' => 'Koordinat tidak lengkap']);
				exit;
			}

			$response = [];

			foreach ($coordinate as $coordinate) {
				$latitudeA = $coordinate['latitude'];
				$longitudeA = $coordinate['longitude'];
				$name = $coordinate['name'];

				$duration = get_duration_maps($latitudeA, $longitudeA, $latitudeB, $longitudeB, $mode);

				$response[] = [
					'name' => $name,
					'time' => $duration
				];
			}

			header('Content-Type: application/json');
			echo json_encode($response);
			exit;
		} else {
			http_response_code(405); // Method Not Allowed
			echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']);
			exit;
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

	private function is_valid_latitude($value)
	{
		return is_numeric($value) && (float) $value >= -90 && (float) $value <= 90;
	}

	private function is_valid_longitude($value)
	{
		return is_numeric($value) && (float) $value >= -180 && (float) $value <= 180;
	}
}
