<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Home extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Home_m');
		$this->load->model('CoordinateModel');
		if ($this->session->userdata('logged_in') != TRUE) {
			redirect('login', 'refresh');
		}
	}

	public function index()
	{

		// menambahkan coordinate

		$this->load->model('CoordinateModel');

		$coordinate = $this->CoordinateModel->getCoordinate();

		$latitudeA = $coordinate['latitude']; // Latitude pusat kesehatan
		$longitudeA = $coordinate['longitude']; // Longitude pusat kesehatan
		$latitudeB = -5.981953; // Latitude pahlawan 1
		$longitudeB = 106.008903; // Longitude pahlawan 1
		$mode = 'driving';

		$duration = $this->getDuration($latitudeA, $longitudeA, $latitudeB, $longitudeB, $mode);

		$data['duration'] = $duration;
		$this->load->view('home_v');
	}

	// memebuat function getDuration untuk keperluan mengambil durasi dari latitude dan longitude dan
	public function getDuration($latitudeA, $longitudeA, $latitudeB, $longitudeB, $mode)
	{
		$apiKey = 'AIzaSyBBAlyuqqIRtJj68YxHyj8lpVRtiDcMjAc'; // Ganti dengan API Key Google Maps Anda
		$url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=$latitudeA,$longitudeA&destinations=$latitudeB,$longitudeB&mode=$mode&key=$apiKey";

		// Mengirim permintaan ke API
		$response = file_get_contents($url);
		$data = json_decode($response, true);
		if ($data['status'] === 'OK') {
			$duration = $data['rows'][0]['elements'][0]['duration']['text'];
			return $duration;
		} else {
			return null;
		}
	}
}
