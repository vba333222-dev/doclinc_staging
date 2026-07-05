<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_request_event_table_ready')) {
	function doclinc_request_event_table_ready($CI = null)
	{
		$CI = $CI ?: get_instance();
		if (!$CI->db->table_exists('request_events')) {
			return false;
		}

		foreach (array('event_id', 'request_id', 'event_type', 'created_at') as $field) {
			if (!$CI->db->field_exists($field, 'request_events')) {
				return false;
			}
		}

		return true;
	}
}

if (!function_exists('doclinc_request_event_normalize_puskesmas_code')) {
	function doclinc_request_event_normalize_puskesmas_code($code)
	{
		$code = trim((string) $code);
		return strtoupper($code) === 'DEFAULT' ? '' : $code;
	}
}

if (!function_exists('doclinc_append_request_event')) {
	function doclinc_append_request_event($request_id, $event_type, $payload = array(), $CI = null)
	{
		$CI = $CI ?: get_instance();
		$request_id = (int) $request_id;
		$event_type = substr(trim((string) $event_type), 0, 80);
		if ($request_id < 1 || $event_type === '' || !doclinc_request_event_table_ready($CI)) {
			return false;
		}

		$payload = is_array($payload) ? $payload : array();
		if (!empty($payload['deduplicate'])) {
			$existing = $CI->db
				->where('request_id', $request_id)
				->where('event_type', $event_type)
				->count_all_results('request_events');
			if ($existing > 0) {
				return true;
			}
		}

		$data = array(
			'request_id' => $request_id,
			'event_type' => $event_type,
		);

		if ($CI->db->field_exists('puskesmas_code', 'request_events')) {
			$puskesmas_code = isset($payload['puskesmas_code']) ? doclinc_request_event_normalize_puskesmas_code($payload['puskesmas_code']) : '';
			$data['puskesmas_code'] = $puskesmas_code !== '' ? $puskesmas_code : null;
		}
		if ($CI->db->field_exists('actor_user_id', 'request_events')) {
			$actor_user_id = isset($payload['actor_user_id']) ? (int) $payload['actor_user_id'] : 0;
			$data['actor_user_id'] = $actor_user_id > 0 ? $actor_user_id : null;
		}
		if ($CI->db->field_exists('actor_staff_id', 'request_events')) {
			$actor_staff_id = isset($payload['actor_staff_id']) ? (int) $payload['actor_staff_id'] : 0;
			$data['actor_staff_id'] = $actor_staff_id > 0 ? $actor_staff_id : null;
		}
		if ($CI->db->field_exists('actor_role', 'request_events')) {
			$actor_role = isset($payload['actor_role']) ? substr(trim((string) $payload['actor_role']), 0, 50) : '';
			$data['actor_role'] = $actor_role !== '' ? $actor_role : null;
		}
		if ($CI->db->field_exists('message', 'request_events')) {
			$message = isset($payload['message']) ? trim((string) $payload['message']) : '';
			$data['message'] = $message !== '' ? $message : null;
		}
		if ($CI->db->field_exists('metadata_json', 'request_events')) {
			$metadata = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : array();
			$encoded = !empty($metadata) ? json_encode($metadata) : null;
			$data['metadata_json'] = $encoded !== false ? $encoded : null;
		}

		return (bool) $CI->db->insert('request_events', $data);
	}
}
