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
		if ($puskesmas_code === '') {
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
