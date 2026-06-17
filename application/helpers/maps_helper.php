<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('get_duration_maps')) {
	function get_duration_maps($latitudeA, $longitudeA, $latitudeB, $longitudeB, $mode = 'driving')
	{
		$apiKey = 'AIzaSyBTfv2in7EP1cLT71-bVC-66SZsrg4Kr5w'; // Ganti dengan API Key Anda

		$url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=$latitudeA,$longitudeA&destinations=$latitudeB,$longitudeB&mode=$mode&key=$apiKey";

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);

		$response = curl_exec($curl);

		if (curl_errno($curl)) {
			log_message('error', 'Curl error: ' . curl_error($curl));
			curl_close($curl);
			return "Curl error: " . curl_error($curl);
		}

		curl_close($curl);

		$data = json_decode($response, true);

		if (!is_array($data)) {
			log_message('error', "Invalid JSON response: $response");
			return "Respons tidak valid dari Google API.";
		}

		if ($data['status'] !== 'OK') {
			log_message('error', "API Error: " . $data['status']);
			return "API Error: " . $data['status'];
		}

		if (!isset($data['rows'][0]['elements'][0]['status']) || $data['rows'][0]['elements'][0]['status'] !== 'OK') {
			return "Lokasi tidak ditemukan atau rute tidak tersedia.";
		}

		if (!isset($data['rows'][0]['elements'][0]['duration']['text'])) {
			return "Durasi tidak tersedia";
		}

		return $data['rows'][0]['elements'][0]['duration']['text'];
	}
}
