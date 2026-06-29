<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_visit_route_payload')) {
	function doclinc_visit_route_payload($origin_lat, $origin_lng, $dest_lat, $dest_lng)
	{
		if (!doclinc_visit_route_coordinates_valid($origin_lat, $origin_lng, $dest_lat, $dest_lng)) {
			return doclinc_visit_route_pending_payload();
		}

		$provider = strtolower(trim((string) doclinc_visit_route_config_value('ROUTING_PROVIDER', 'routing_provider', 'none')));
		if ($provider !== 'valhalla') {
			return doclinc_visit_route_pending_payload();
		}

		$base_url = trim((string) doclinc_visit_route_config_value('VALHALLA_BASE_URL', 'valhalla_base_url', ''));
		if ($base_url === '') {
			return doclinc_visit_route_pending_payload();
		}

		$timeout = doclinc_visit_route_config_value('ROUTING_TIMEOUT_SECONDS', 'routing_timeout_seconds', 3);
		$timeout = is_numeric($timeout) ? max(1, min(10, (int) $timeout)) : 3;
		$response = doclinc_visit_route_valhalla_request($base_url, array(
			'locations' => array(
				array('lat' => (float) $origin_lat, 'lon' => (float) $origin_lng),
				array('lat' => (float) $dest_lat, 'lon' => (float) $dest_lng),
			),
			'costing' => 'auto',
			'units' => 'kilometers',
		), $timeout);

		if (!$response || empty($response['trip']['summary'])) {
			return doclinc_visit_route_pending_payload();
		}

		$summary = $response['trip']['summary'];
		$distance_m = isset($summary['length']) && is_numeric($summary['length'])
			? (float) $summary['length'] * 1000
			: null;
		$duration_s = isset($summary['time']) && is_numeric($summary['time'])
			? (float) $summary['time']
			: null;

		if ($distance_m === null || $duration_s === null) {
			return doclinc_visit_route_pending_payload();
		}

		return array(
			'distance_m' => $distance_m,
			'duration_s' => $duration_s,
			'distance_text' => doclinc_format_distance_text($distance_m),
			'eta_text' => doclinc_format_eta_text($duration_s),
			'geometry' => null,
			'provider' => 'valhalla',
			'calculated_at' => date('Y-m-d H:i:s'),
		);
	}
}

if (!function_exists('doclinc_visit_route_config_value')) {
	function doclinc_visit_route_config_value($env_key, $config_key, $default)
	{
		$value = getenv($env_key);
		if ($value !== false && $value !== '') {
			return $value;
		}

		if (isset($_SERVER[$env_key]) && $_SERVER[$env_key] !== '') {
			return $_SERVER[$env_key];
		}

		$CI = &get_instance();
		$config_value = $CI->config->item($config_key);
		return $config_value !== null && $config_value !== '' ? $config_value : $default;
	}
}

if (!function_exists('doclinc_visit_route_coordinates_valid')) {
	function doclinc_visit_route_coordinates_valid($origin_lat, $origin_lng, $dest_lat, $dest_lng)
	{
		return is_numeric($origin_lat) && (float) $origin_lat >= -90 && (float) $origin_lat <= 90
			&& is_numeric($dest_lat) && (float) $dest_lat >= -90 && (float) $dest_lat <= 90
			&& is_numeric($origin_lng) && (float) $origin_lng >= -180 && (float) $origin_lng <= 180
			&& is_numeric($dest_lng) && (float) $dest_lng >= -180 && (float) $dest_lng <= 180;
	}
}

if (!function_exists('doclinc_visit_route_valhalla_request')) {
	function doclinc_visit_route_valhalla_request($base_url, $payload, $timeout)
	{
		$url = rtrim($base_url, '/') . '/route';
		$json = json_encode($payload);
		if ($json === false) {
			return null;
		}

		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			$body = curl_exec($ch);
			$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($body === false || $status < 200 || $status >= 300) {
				return null;
			}

			$decoded = json_decode($body, true);
			return is_array($decoded) ? $decoded : null;
		}

		$context = stream_context_create(array(
			'http' => array(
				'method' => 'POST',
				'header' => "Content-Type: application/json\r\n",
				'content' => $json,
				'timeout' => $timeout,
				'ignore_errors' => true,
			),
		));
		$body = @file_get_contents($url, false, $context);
		if ($body === false) {
			return null;
		}

		$decoded = json_decode($body, true);
		return is_array($decoded) ? $decoded : null;
	}
}
