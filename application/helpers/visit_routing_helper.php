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

		$geometry = doclinc_visit_route_valhalla_geometry($response);

		return array(
			'distance_m' => $distance_m,
			'duration_s' => $duration_s,
			'distance_text' => doclinc_format_distance_text($distance_m),
			'eta_text' => doclinc_format_eta_text($duration_s),
			'geometry' => $geometry,
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

if (!function_exists('doclinc_visit_route_numeric_config')) {
	function doclinc_visit_route_numeric_config($env_key, $config_key, $default, $min, $max)
	{
		$value = doclinc_visit_route_config_value($env_key, $config_key, $default);
		if (!is_numeric($value)) {
			return $default;
		}

		return max($min, min($max, (float) $value));
	}
}

if (!function_exists('doclinc_visit_arrival_radius_m')) {
	function doclinc_visit_arrival_radius_m()
	{
		return doclinc_visit_route_numeric_config('VISIT_ARRIVAL_RADIUS_METERS', 'visit_arrival_radius_meters', 75, 10, 1000);
	}
}

if (!function_exists('doclinc_route_cache_ttl_seconds')) {
	function doclinc_route_cache_ttl_seconds()
	{
		return (int) doclinc_visit_route_numeric_config('ROUTE_CACHE_TTL_SECONDS', 'route_cache_ttl_seconds', 20, 0, 3600);
	}
}

if (!function_exists('doclinc_reroute_min_move_meters')) {
	function doclinc_reroute_min_move_meters()
	{
		return doclinc_visit_route_numeric_config('REROUTE_MIN_MOVE_METERS', 'reroute_min_move_meters', 50, 0, 1000);
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

if (!function_exists('doclinc_haversine_distance_m')) {
	function doclinc_haversine_distance_m($lat1, $lng1, $lat2, $lng2)
	{
		if (!doclinc_visit_route_coordinates_valid($lat1, $lng1, $lat2, $lng2)) {
			return null;
		}

		$earth_radius_m = 6371000;
		$lat1_rad = deg2rad((float) $lat1);
		$lat2_rad = deg2rad((float) $lat2);
		$delta_lat = deg2rad((float) $lat2 - (float) $lat1);
		$delta_lng = deg2rad((float) $lng2 - (float) $lng1);
		$a = sin($delta_lat / 2) * sin($delta_lat / 2)
			+ cos($lat1_rad) * cos($lat2_rad) * sin($delta_lng / 2) * sin($delta_lng / 2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));

		return $earth_radius_m * $c;
	}
}

if (!function_exists('doclinc_visit_arrival_payload')) {
	function doclinc_visit_arrival_payload($nakes_lat, $nakes_lng, $patient_lat, $patient_lng, $visit_status)
	{
		$radius_m = doclinc_visit_arrival_radius_m();
		$distance_m = doclinc_haversine_distance_m($nakes_lat, $nakes_lng, $patient_lat, $patient_lng);
		if ($distance_m === null) {
			return array(
				'distance_to_patient_m' => null,
				'distance_to_patient_text' => null,
				'radius_m' => $radius_m,
				'is_near_patient' => false,
				'should_prompt_arrival' => false,
				'message' => null,
			);
		}

		$visit_status = function_exists('doclinc_normalize_visit_status')
			? doclinc_normalize_visit_status($visit_status)
			: strtolower(trim((string) $visit_status));
		$is_near_patient = $distance_m <= $radius_m;
		$distance_text = function_exists('doclinc_format_distance_text')
			? doclinc_format_distance_text($distance_m)
			: number_format($distance_m, 0, ',', '.') . ' m';
		$should_prompt_arrival = $visit_status === 'en_route' && $is_near_patient;

		return array(
			'distance_to_patient_m' => $distance_m,
			'distance_to_patient_text' => $distance_text,
			'radius_m' => $radius_m,
			'is_near_patient' => $is_near_patient,
			'should_prompt_arrival' => $should_prompt_arrival,
			'message' => $should_prompt_arrival
				? 'Anda sudah dekat dengan lokasi pasien. Konfirmasi tiba di lokasi.'
				: 'Jarak ke lokasi pasien: ' . $distance_text,
		);
	}
}

if (!function_exists('doclinc_visit_route_valhalla_geometry')) {
	function doclinc_visit_route_valhalla_geometry($response)
	{
		if (empty($response['trip']['legs']) || !is_array($response['trip']['legs'])) {
			return null;
		}

		$coordinates = array();
		foreach ($response['trip']['legs'] as $leg) {
			if (!isset($leg['shape']) || !is_string($leg['shape']) || trim($leg['shape']) === '') {
				continue;
			}

			$leg_coordinates = doclinc_decode_valhalla_polyline6($leg['shape']);
			if (empty($leg_coordinates)) {
				continue;
			}

			foreach ($leg_coordinates as $coordinate) {
				if (count($coordinates) >= 1000) {
					break 2;
				}
				$count = count($coordinates);
				if ($count > 0 && $coordinates[$count - 1][0] === $coordinate[0] && $coordinates[$count - 1][1] === $coordinate[1]) {
					continue;
				}
				$coordinates[] = $coordinate;
			}
		}

		return count($coordinates) > 1 ? array(
			'type' => 'polyline6',
			'coordinates' => $coordinates,
		) : null;
	}
}

if (!function_exists('doclinc_decode_valhalla_polyline6')) {
	function doclinc_decode_valhalla_polyline6($encoded)
	{
		if (!is_string($encoded)) {
			return array();
		}

		$encoded = trim($encoded);
		if ($encoded === '' || strlen($encoded) > 20000) {
			return array();
		}

		$coordinates = array();
		$index = 0;
		$length = strlen($encoded);
		$lat = 0;
		$lng = 0;
		$precision = 1000000;

		while ($index < $length) {
			if (count($coordinates) >= 1000) {
				return $coordinates;
			}

			$lat_change = doclinc_decode_valhalla_polyline_value($encoded, $index, $length);
			if ($lat_change === null) {
				return array();
			}
			$lng_change = doclinc_decode_valhalla_polyline_value($encoded, $index, $length);
			if ($lng_change === null) {
				return array();
			}

			$lat += $lat_change;
			$lng += $lng_change;
			$decoded_lat = $lat / $precision;
			$decoded_lng = $lng / $precision;
			if ($decoded_lat < -90 || $decoded_lat > 90 || $decoded_lng < -180 || $decoded_lng > 180) {
				return array();
			}

			$coordinates[] = array($decoded_lat, $decoded_lng);
		}

		return $coordinates;
	}
}

if (!function_exists('doclinc_decode_valhalla_polyline_value')) {
	function doclinc_decode_valhalla_polyline_value($encoded, &$index, $length)
	{
		$result = 0;
		$shift = 0;

		do {
			if ($index >= $length || $shift > 30) {
				return null;
			}

			$byte = ord($encoded[$index++]) - 63;
			if ($byte < 0) {
				return null;
			}
			$result |= ($byte & 0x1f) << $shift;
			$shift += 5;
		} while ($byte >= 0x20);

		return ($result & 1) ? ~($result >> 1) : ($result >> 1);
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
