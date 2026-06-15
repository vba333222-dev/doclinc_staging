<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Home extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Home_m');
		if ($this->session->userdata('logged_in') != TRUE) {
// 			redirect('../');
echo("tidak ada");
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
			redirect('../');
			// $this->load->view('login_v');
		}
	}
	public function save_konsultasi()
	{
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
		$id = $this->input->post('requestId');
		if ($this->Home_m->updateRequestById($id)) {
			$response = (['status' => 'success', 'message' => 'Data berhasil diupdate']);
		} else {
			$response = (['status' => 'error', 'message' => 'Gagal mengupdate data']);
		}
		echo json_encode($response);
	}

	public function deleterequestbyid()
	{
		$id = $this->input->post('requestId');
		$this->Home_m->deleteRequestById($id);
		echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus']);
	}

	public function submit_rating()
	{
		$iduser = $this->input->post('id_user');
		$iddokter = $this->input->post('id_dokter');
		$rating = $this->input->post('rating');

		$result = $this->Home_m->submit_rating($iduser, $iddokter, $rating);

		if ($result) {
			echo json_encode(['status' => 'success', 'message' => 'Rating berhasil dikirim']);
		} else {
			echo json_encode(['status' => 'error', 'message' => 'Gagal mengirim rating']);
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
}
