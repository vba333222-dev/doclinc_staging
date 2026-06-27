<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_get_active_puskesmas')) {
	function doclinc_get_active_puskesmas()
	{
		$CI = &get_instance();
		if (!$CI->db->table_exists('m_puskesmas')) {
			return array();
		}

		$CI->db->select('*');
		if ($CI->db->field_exists('kode_pkm', 'm_puskesmas')) {
			$CI->db->where('kode_pkm !=', 'DEFAULT');
		}
		if ($CI->db->field_exists('status', 'm_puskesmas')) {
			$CI->db->where('status', 'aktif');
		}
		if ($CI->db->field_exists('nama_puskesmas', 'm_puskesmas')) {
			$CI->db->order_by('nama_puskesmas', 'ASC');
		}

		return $CI->db->get('m_puskesmas')->result();
	}
}

if (!function_exists('doclinc_get_puskesmas_by_code')) {
	function doclinc_get_puskesmas_by_code($code)
	{
		$code = trim((string) $code);
		if ($code === '') {
			return null;
		}

		$CI = &get_instance();
		if (!$CI->db->table_exists('m_puskesmas')) {
			return null;
		}

		$field = $CI->db->field_exists('kode_pkm', 'm_puskesmas') ? 'kode_pkm' : 'kode_puskesmas';
		if (!$CI->db->field_exists($field, 'm_puskesmas')) {
			return null;
		}

		$CI->db->where($field, $code);
		if ($field === 'kode_pkm') {
			$CI->db->where('kode_pkm !=', 'DEFAULT');
		}
		if ($CI->db->field_exists('status', 'm_puskesmas')) {
			$CI->db->where('status', 'aktif');
		}

		return $CI->db->get('m_puskesmas')->row();
	}
}

if (!function_exists('doclinc_find_nearest_puskesmas')) {
	function doclinc_find_nearest_puskesmas($lat, $lng)
	{
		if (!is_numeric($lat) || !is_numeric($lng)) {
			return null;
		}

		$lat = (float) $lat;
		$lng = (float) $lng;
		if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
			return null;
		}

		$service_area_match = doclinc_find_puskesmas_by_service_area($lat, $lng);
		if ($service_area_match) {
			return $service_area_match;
		}

		$nearest = null;
		$nearest_distance = null;
		foreach (doclinc_get_active_puskesmas() as $puskesmas) {
			if (!isset($puskesmas->latitude, $puskesmas->longitude) || !is_numeric($puskesmas->latitude) || !is_numeric($puskesmas->longitude)) {
				continue;
			}

			$distance = doclinc_haversine_km($lat, $lng, (float) $puskesmas->latitude, (float) $puskesmas->longitude);
			if ($nearest_distance === null || $distance < $nearest_distance) {
				$nearest = $puskesmas;
				$nearest_distance = $distance;
			}
		}

		return $nearest;
	}
}

if (!function_exists('doclinc_find_puskesmas_by_service_area')) {
	function doclinc_find_puskesmas_by_service_area($lat, $lng)
	{
		if (!is_numeric($lat) || !is_numeric($lng)) {
			return null;
		}

		$lat = (float) $lat;
		$lng = (float) $lng;
		if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
			return null;
		}

		foreach (doclinc_service_area_definitions() as $definition) {
			$path = FCPATH . 'servicearea/' . $definition['file'];
			if (!is_file($path) || !is_readable($path)) {
				continue;
			}

			$geojson = json_decode((string) file_get_contents($path), true);
			if (!is_array($geojson) || !doclinc_geojson_contains_point($geojson, $lat, $lng)) {
				continue;
			}

			$puskesmas = doclinc_resolve_service_area_puskesmas($definition);
			if ($puskesmas) {
				return $puskesmas;
			}
		}

		return null;
	}
}

if (!function_exists('doclinc_service_area_definitions')) {
	function doclinc_service_area_definitions()
	{
		return array(
			array('file' => 'PuskesmasCilegon.geojson', 'codes' => array('10280101')),
			array('file' => 'PuskesmasCibeber.geojson', 'codes' => array('10280201')),
			array('file' => 'PuskesmasCiwandan.geojson', 'codes' => array('10280301')),
			array('file' => 'PuskesmasPulomerak.geojson', 'codes' => array('10280401')),
			array('file' => 'PuskesmasGerogol.geojson', 'codes' => array('10280402')),
			array('file' => 'PuskesmasCitangkil.geojson', 'codes' => array('10280501', '2241001')),
			array('file' => 'PuskesmasPurwakarta.geojson', 'codes' => array('10280601')),
			array('file' => 'PuskesmasJombang.geojson', 'codes' => array('10280701')),
		);
	}
}

if (!function_exists('doclinc_resolve_service_area_puskesmas')) {
	function doclinc_resolve_service_area_puskesmas($definition)
	{
		$codes = isset($definition['codes']) && is_array($definition['codes']) ? $definition['codes'] : array();
		$first_available = null;
		foreach ($codes as $code) {
			$puskesmas = doclinc_get_puskesmas_by_code($code);
			if (!$puskesmas) {
				continue;
			}
			if (!$first_available) {
				$first_available = $puskesmas;
			}
			if (doclinc_get_queue_handler_user_id($code)) {
				return $puskesmas;
			}
		}

		return $first_available;
	}
}

if (!function_exists('doclinc_geojson_contains_point')) {
	function doclinc_geojson_contains_point($geojson, $lat, $lng)
	{
		if (!is_array($geojson) || empty($geojson['type'])) {
			return false;
		}

		switch ($geojson['type']) {
			case 'FeatureCollection':
				foreach (($geojson['features'] ?? array()) as $feature) {
					if (doclinc_geojson_contains_point($feature, $lat, $lng)) {
						return true;
					}
				}
				return false;
			case 'Feature':
				return isset($geojson['geometry']) && doclinc_geojson_contains_point($geojson['geometry'], $lat, $lng);
			case 'Polygon':
				return isset($geojson['coordinates']) && doclinc_polygon_contains_point($geojson['coordinates'], $lat, $lng);
			case 'MultiPolygon':
				foreach (($geojson['coordinates'] ?? array()) as $polygon) {
					if (doclinc_polygon_contains_point($polygon, $lat, $lng)) {
						return true;
					}
				}
				return false;
			case 'GeometryCollection':
				foreach (($geojson['geometries'] ?? array()) as $geometry) {
					if (doclinc_geojson_contains_point($geometry, $lat, $lng)) {
						return true;
					}
				}
				return false;
			default:
				return false;
		}
	}
}

if (!function_exists('doclinc_polygon_contains_point')) {
	function doclinc_polygon_contains_point($rings, $lat, $lng)
	{
		if (empty($rings) || !is_array($rings) || !doclinc_ring_contains_point($rings[0], $lat, $lng)) {
			return false;
		}

		for ($i = 1; $i < count($rings); $i++) {
			if (doclinc_ring_contains_point($rings[$i], $lat, $lng)) {
				return false;
			}
		}

		return true;
	}
}

if (!function_exists('doclinc_ring_contains_point')) {
	function doclinc_ring_contains_point($ring, $lat, $lng)
	{
		if (!is_array($ring) || count($ring) < 3) {
			return false;
		}

		$inside = false;
		$count = count($ring);
		for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
			if (!isset($ring[$i][0], $ring[$i][1], $ring[$j][0], $ring[$j][1])) {
				continue;
			}

			$xi = (float) $ring[$i][0];
			$yi = (float) $ring[$i][1];
			$xj = (float) $ring[$j][0];
			$yj = (float) $ring[$j][1];

			if (doclinc_point_on_segment($lng, $lat, $xi, $yi, $xj, $yj)) {
				return true;
			}

			$intersects = (($yi > $lat) !== ($yj > $lat))
				&& ($lng < (($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1.0) + $xi));
			if ($intersects) {
				$inside = !$inside;
			}
		}

		return $inside;
	}
}

if (!function_exists('doclinc_point_on_segment')) {
	function doclinc_point_on_segment($px, $py, $x1, $y1, $x2, $y2)
	{
		$epsilon = 0.000000001;
		$cross = ($py - $y1) * ($x2 - $x1) - ($px - $x1) * ($y2 - $y1);
		if (abs($cross) > $epsilon) {
			return false;
		}

		return $px >= min($x1, $x2) - $epsilon
			&& $px <= max($x1, $x2) + $epsilon
			&& $py >= min($y1, $y2) - $epsilon
			&& $py <= max($y1, $y2) + $epsilon;
	}
}

if (!function_exists('doclinc_haversine_km')) {
	function doclinc_haversine_km($lat1, $lng1, $lat2, $lng2)
	{
		$earth_radius = 6371;
		$dlat = deg2rad($lat2 - $lat1);
		$dlng = deg2rad($lng2 - $lng1);
		$a = sin($dlat / 2) * sin($dlat / 2)
			+ cos(deg2rad($lat1)) * cos(deg2rad($lat2))
			* sin($dlng / 2) * sin($dlng / 2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));

		return $earth_radius * $c;
	}
}

if (!function_exists('doclinc_get_puskesmas_nakes_users')) {
	function doclinc_get_puskesmas_nakes_users($puskesmas_code)
	{
		$puskesmas_code = trim((string) $puskesmas_code);
		if ($puskesmas_code === '' || $puskesmas_code === 'DEFAULT') {
			return array();
		}

		$CI = &get_instance();
		if (!$CI->db->table_exists('users') || !$CI->db->field_exists('remark', 'users')) {
			return array();
		}

		$CI->db
			->where('role', 'dokter')
			->where('remark', $puskesmas_code);
		if ($CI->db->field_exists('status', 'users')) {
			$CI->db->where('status', 'aktif');
		}

		return $CI->db->get('users')->result();
	}
}

if (!function_exists('doclinc_get_queue_handler_user_id')) {
	function doclinc_get_queue_handler_user_id($puskesmas_code)
	{
		$users = doclinc_get_puskesmas_nakes_users($puskesmas_code);
		return empty($users) ? null : $users[0]->userId;
	}
}

if (!function_exists('doclinc_can_user_access_puskesmas_request')) {
	function doclinc_can_user_access_puskesmas_request($request_id, $user)
	{
		if (!$user || empty($request_id)) {
			return false;
		}

		$CI = &get_instance();
		$request = $CI->db->where('request_id', $request_id)->get('requests')->row();
		if (!$request) {
			return false;
		}

		$user_id = is_array($user) ? ($user['id'] ?? ($user['userId'] ?? null)) : ($user->id ?? ($user->userId ?? null));
		$remark = trim((string) (is_array($user) ? ($user['remark'] ?? '') : ($user->remark ?? '')));

		if (!empty($request->dokter_id) && (string) $request->dokter_id === (string) $user_id) {
			return true;
		}
		if (isset($request->accepted_by_user_id) && !empty($request->accepted_by_user_id) && (string) $request->accepted_by_user_id === (string) $user_id) {
			return true;
		}

		return $request->request_status === 'Pending'
			&& $remark !== ''
			&& isset($request->assigned_puskesmas_code)
			&& trim((string) $request->assigned_puskesmas_code) === $remark;
	}
}
