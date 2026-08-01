<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_visit_proof_required')) {
	function doclinc_visit_proof_required()
	{
		$CI = &get_instance();
		return $CI->config->item('visit_proof_required') === true;
	}
}

if (!function_exists('doclinc_visit_proof_max_size_kb')) {
	function doclinc_visit_proof_max_size_kb()
	{
		$CI = &get_instance();
		$value = $CI->config->item('visit_proof_max_size_kb');
		return is_numeric($value) ? max(256, min(10240, (int) $value)) : 5120;
	}
}

if (!function_exists('doclinc_visit_proof_location_max_age_seconds')) {
	function doclinc_visit_proof_location_max_age_seconds()
	{
		$CI = &get_instance();
		$value = $CI->config->item('visit_proof_location_max_age_seconds');
		return is_numeric($value) ? max(30, min(600, (int) $value)) : 120;
	}
}

if (!function_exists('doclinc_visit_proof_is_visit')) {
	function doclinc_visit_proof_is_visit($kriteria)
	{
		$normalized = strtolower(trim((string) $kriteria));
		return $normalized === '1' || $normalized === 'kunjungan nakes';
	}
}

if (!function_exists('doclinc_visit_proof_persisted_visit')) {
	function doclinc_visit_proof_persisted_visit($request)
	{
		if (!is_object($request) && !is_array($request)) {
			return false;
		}
		$read = function ($field) use ($request) {
			if (is_array($request)) {
				return array_key_exists($field, $request) ? $request[$field] : null;
			}
			return isset($request->{$field}) ? $request->{$field} : null;
		};
		$mode = strtolower(trim((string) $read('consultation_mode')));
		if ($mode === 'visit') {
			return true;
		}
		$status = strtolower(trim((string) $read('visit_status')));
		return in_array($status, array('en_route', 'arrived', 'in_service', 'completed'), true);
	}
}

if (!function_exists('doclinc_visit_proof_location_input')) {
	function doclinc_visit_proof_location_input($latitude, $longitude, $accuracy_m, $captured_at_ms, $now_ms = null)
	{
		if (!function_exists('doclinc_visit_route_coordinates_valid')
			|| !doclinc_visit_route_coordinates_valid($latitude, $longitude, $latitude, $longitude)) {
			return array('valid' => false, 'reason' => 'invalid_coordinate');
		}
		if (!is_numeric($accuracy_m)) {
			return array('valid' => false, 'reason' => 'accuracy_required');
		}
		$accuracy_m = (float) $accuracy_m;
		$max_accuracy_m = function_exists('doclinc_visit_location_max_accuracy_meters')
			? (float) doclinc_visit_location_max_accuracy_meters()
			: 100.0;
		if ($accuracy_m < 0 || $accuracy_m > $max_accuracy_m) {
			return array('valid' => false, 'reason' => 'low_accuracy');
		}
		if (!is_numeric($captured_at_ms)) {
			return array('valid' => false, 'reason' => 'captured_at_required');
		}

		$captured_at_ms = (int) $captured_at_ms;
		$now_ms = $now_ms === null ? (int) round(microtime(true) * 1000) : (int) $now_ms;
		$age_ms = $now_ms - $captured_at_ms;
		$max_age_ms = doclinc_visit_proof_location_max_age_seconds() * 1000;
		if ($captured_at_ms < 1 || $age_ms < -30000 || $age_ms > $max_age_ms) {
			return array('valid' => false, 'reason' => 'stale_location');
		}

		$captured_seconds = (int) floor($captured_at_ms / 1000);
		return array(
			'valid' => true,
			'latitude' => (float) $latitude,
			'longitude' => (float) $longitude,
			'accuracy_m' => $accuracy_m,
			'captured_at_ms' => $captured_at_ms,
			'captured_at' => date('Y-m-d H:i:s', $captured_seconds),
			'client_sequence' => $captured_at_ms,
		);
	}
}
