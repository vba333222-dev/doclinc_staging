<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Home extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Home_m');
		$this->load->helper('request_authz');
		$this->load->helper('notification');
		if ($this->session->userdata('logged_in') != TRUE) {
			if ($this->router->fetch_method() === 'visit_location') {
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
			$recipient_id = isset($request->accepted_by_user_id) && !empty($request->accepted_by_user_id)
				? $request->accepted_by_user_id
				: (isset($request->dokter_id) ? $request->dokter_id : null);
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
		if ($this->input->method(TRUE) !== 'GET') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']));
			return;
		}

		$request_id = (int) $this->input->get('request_id', TRUE);
		$user_id = (int) $this->session->userdata('id');
		$role = $this->session->userdata('role');
		if ($request_id < 1) {
			$this->output
				->set_status_header(400)
				->set_output(json_encode(['status' => 'error', 'message' => 'Data request tidak valid']));
			return;
		}
		if ($role !== 'warga') {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$request = doclinc_request_row($request_id);
		if (!$request) {
			$this->output
				->set_status_header(404)
				->set_output(json_encode(['status' => 'error', 'message' => 'Request tidak ditemukan']));
			return;
		}
		if (!doclinc_can_view_visit_location($request_id, $user_id, $role)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$row = $this->Home_m->get_visit_location($request_id, $user_id);
		if (!$row) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan']));
			return;
		}

		$visit_status = isset($row->visit_status) && $row->visit_status !== null
			? doclinc_normalize_visit_status($row->visit_status)
			: '';
		$visit_status = $visit_status !== '' ? $visit_status : 'not_started';
		$visit_workflow = [
			'visit_status' => $visit_status,
			'visit_status_label' => doclinc_visit_status_label($visit_status),
			'visit_started_at' => isset($row->visit_started_at) ? $row->visit_started_at : null,
			'visit_arrived_at' => isset($row->visit_arrived_at) ? $row->visit_arrived_at : null,
			'visit_in_service_at' => isset($row->visit_in_service_at) ? $row->visit_in_service_at : null,
			'visit_completed_at' => isset($row->visit_completed_at) ? $row->visit_completed_at : null,
		];

		if (!$this->is_valid_latitude($row->lattitude_dokter) || !$this->is_valid_longitude($row->longitude_dokter)) {
			$this->output->set_output(json_encode(array_merge([
				'status' => 'pending',
				'message' => 'Lokasi nakes belum tersedia'
			], $visit_workflow)));
			return;
		}

		$patient_latitude = null;
		$patient_longitude = null;
		if ($this->is_valid_latitude($row->patient_latitude) && $this->is_valid_longitude($row->patient_longitude)) {
			$patient_latitude = (float) $row->patient_latitude;
			$patient_longitude = (float) $row->patient_longitude;
		} elseif ($this->is_valid_latitude($row->lattitude) && $this->is_valid_longitude($row->longitude)) {
			$patient_latitude = (float) $row->lattitude;
			$patient_longitude = (float) $row->longitude;
		}

		$this->output->set_output(json_encode(array_merge([
			'status' => 'success',
			'request_status' => 'Accepted',
			'nakes' => [
				'latitude' => (float) $row->lattitude_dokter,
				'longitude' => (float) $row->longitude_dokter,
				'updated_at' => $row->updated_at,
			],
			'patient' => [
				'latitude' => $patient_latitude,
				'longitude' => $patient_longitude,
			],
		], $visit_workflow)));
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
