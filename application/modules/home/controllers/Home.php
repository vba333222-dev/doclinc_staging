<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Home extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Home_m');
		$this->load->helper('request_authz');
		if ($this->session->userdata('logged_in') != TRUE) {
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
				} else {
					$x['getAllDataRequests'] = [];
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
}
