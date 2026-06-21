<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('get_duration_maps')) {
	function get_duration_maps($latitudeA, $longitudeA, $latitudeB, $longitudeB, $mode = 'driving')
	{
		$CI = get_instance();
		$apiKey = $CI->config->item('google_maps_api_key');
		$mapProvider = $CI->config->item('map_provider');

		if ($mapProvider !== 'google' || empty($apiKey)) {
			return '-';
		}

		$query = http_build_query([
			'origins' => $latitudeA . ',' . $longitudeA,
			'destinations' => $latitudeB . ',' . $longitudeB,
			'mode' => $mode,
			'key' => $apiKey,
		]);
		$url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . $query;

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);

		$response = curl_exec($curl);

		if (curl_errno($curl)) {
			log_message('error', 'Curl error: ' . curl_error($curl));
			curl_close($curl);
			return '-';
		}

		curl_close($curl);

		$data = json_decode($response, true);

		if (!is_array($data)) {
			log_message('error', "Invalid JSON response: $response");
			return '-';
		}

		if ($data['status'] !== 'OK') {
			log_message('error', "API Error: " . $data['status']);
			return '-';
		}

		if (!isset($data['rows'][0]['elements'][0]['status']) || $data['rows'][0]['elements'][0]['status'] !== 'OK') {
			return '-';
		}

		if (!isset($data['rows'][0]['elements'][0]['duration']['text'])) {
			return '-';
		}

		return $data['rows'][0]['elements'][0]['duration']['text'];
	}
}
