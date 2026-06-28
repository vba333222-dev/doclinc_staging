<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_current_user_id')) {
	function doclinc_current_user_id()
	{
		$CI = &get_instance();
		return $CI->session->userdata('id');
	}
}

if (!function_exists('doclinc_current_user_role')) {
	function doclinc_current_user_role()
	{
		$CI = &get_instance();
		return $CI->session->userdata('role') ?: '';
	}
}

if (!function_exists('doclinc_request_row')) {
	function doclinc_request_row($request_id)
	{
		if (empty($request_id)) {
			return null;
		}

		$CI = &get_instance();
		return $CI->db
			->where('request_id', $request_id)
			->get('requests')
			->row();
	}
}

if (!function_exists('doclinc_can_view_request')) {
	function doclinc_can_view_request($request_id, $user_id = null, $role = null)
	{
		$user_id = $user_id ?: doclinc_current_user_id();
		$role = $role ?: doclinc_current_user_role();
		$CI = &get_instance();
		$request = doclinc_request_row($request_id);

		if (!$request || empty($user_id)) {
			return false;
		}

		if ($role === 'warga') {
			return (string) $request->user_id === (string) $user_id;
		}

		if ($role === 'dokter') {
			if (!empty($request->dokter_id) && (string) $request->dokter_id === (string) $user_id) {
				return true;
			}
			if (isset($request->accepted_by_user_id) && !empty($request->accepted_by_user_id) && (string) $request->accepted_by_user_id === (string) $user_id) {
				return true;
			}

			$user = $CI->db
				->select('remark')
				->where('userId', $user_id)
				->where('role', 'dokter')
				->get('users')
				->row();

			return $user
				&& $request->request_status === 'Pending'
				&& trim((string) $user->remark) !== ''
				&& isset($request->assigned_puskesmas_code)
				&& trim((string) $request->assigned_puskesmas_code) === trim((string) $user->remark);
		}

		return $role === 'admin';
	}
}

if (!function_exists('doclinc_can_update_request')) {
	function doclinc_can_update_request($request_id, $user_id = null, $role = null, $allowed_statuses = array())
	{
		$user_id = $user_id ?: doclinc_current_user_id();
		$role = $role ?: doclinc_current_user_role();
		$request = doclinc_request_row($request_id);

		if (!$request || empty($user_id) || $role !== 'dokter') {
			return false;
		}

		if ((string) $request->dokter_id !== (string) $user_id) {
			if (!isset($request->accepted_by_user_id) || (string) $request->accepted_by_user_id !== (string) $user_id) {
				return false;
			}
		}

		return empty($allowed_statuses) || in_array($request->request_status, $allowed_statuses, true);
	}
}

if (!function_exists('doclinc_can_cancel_request')) {
	function doclinc_can_cancel_request($request_id, $user_id = null, $role = null)
	{
		$request_id = (int) $request_id;
		$user_id = $user_id ?: doclinc_current_user_id();
		$role = $role ?: doclinc_current_user_role();
		$CI = &get_instance();
		$request = doclinc_request_row($request_id);

		if (!$request || empty($user_id) || in_array($request->request_status, array('Completed', 'Cancelled'), true)) {
			return false;
		}

		if ($role === 'warga') {
			return $request->request_status === 'Pending'
				&& (string) $request->user_id === (string) $user_id;
		}

		if ($role !== 'dokter') {
			return false;
		}

		if ($request->request_status === 'Accepted') {
			if ((string) $request->dokter_id === (string) $user_id) {
				return true;
			}

			return isset($request->accepted_by_user_id)
				&& !empty($request->accepted_by_user_id)
				&& (string) $request->accepted_by_user_id === (string) $user_id;
		}

		if ($request->request_status !== 'Pending') {
			return false;
		}

		if (!empty($request->dokter_id) && (string) $request->dokter_id === (string) $user_id) {
			return true;
		}

		$user = $CI->db
			->select('remark')
			->where('userId', $user_id)
			->where('role', 'dokter')
			->get('users')
			->row();

		return $user
			&& trim((string) $user->remark) !== ''
			&& isset($request->assigned_puskesmas_code)
			&& trim((string) $request->assigned_puskesmas_code) === trim((string) $user->remark);
	}
}

if (!function_exists('doclinc_can_update_visit_location')) {
	function doclinc_can_update_visit_location($request_id, $user_id = null, $role = null)
	{
		$user_id = $user_id ?: doclinc_current_user_id();
		$role = $role ?: doclinc_current_user_role();
		$request = doclinc_request_row($request_id);

		if (!$request || empty($user_id) || $role !== 'dokter' || $request->request_status !== 'Accepted') {
			return false;
		}

		if (!empty($request->dokter_id) && (string) $request->dokter_id === (string) $user_id) {
			return true;
		}

		return isset($request->accepted_by_user_id)
			&& !empty($request->accepted_by_user_id)
			&& (string) $request->accepted_by_user_id === (string) $user_id;
	}
}

if (!function_exists('doclinc_normalize_visit_status')) {
	function doclinc_normalize_visit_status($status)
	{
		$status = strtolower(trim((string) $status));
		$allowed = array('not_started', 'en_route', 'arrived', 'in_service', 'completed');
		return in_array($status, $allowed, true) ? $status : '';
	}
}

if (!function_exists('doclinc_visit_status_label')) {
	function doclinc_visit_status_label($status)
	{
		$labels = array(
			'not_started' => 'Menunggu nakes memulai kunjungan',
			'en_route' => 'Nakes menuju lokasi',
			'arrived' => 'Nakes tiba di lokasi',
			'in_service' => 'Nakes sedang menangani',
			'completed' => 'Kunjungan selesai',
		);

		$status = doclinc_normalize_visit_status($status);
		return isset($labels[$status]) ? $labels[$status] : $labels['not_started'];
	}
}

if (!function_exists('doclinc_allowed_visit_status_transition')) {
	function doclinc_allowed_visit_status_transition($current, $next)
	{
		$current = doclinc_normalize_visit_status($current) ?: 'not_started';
		$next = doclinc_normalize_visit_status($next);
		if ($next === '') {
			return false;
		}
		if ($current === $next) {
			return true;
		}

		$allowed = array(
			'not_started' => 'en_route',
			'en_route' => 'arrived',
			'arrived' => 'in_service',
			'in_service' => 'completed',
		);

		return isset($allowed[$current]) && $allowed[$current] === $next;
	}
}

if (!function_exists('doclinc_can_update_visit_status')) {
	function doclinc_can_update_visit_status($request_id, $user_id = null, $role = null)
	{
		return doclinc_can_update_visit_location($request_id, $user_id, $role);
	}
}

if (!function_exists('doclinc_can_view_visit_location')) {
	function doclinc_can_view_visit_location($request_id, $user_id = null, $role = null)
	{
		$user_id = $user_id ?: doclinc_current_user_id();
		$role = $role ?: doclinc_current_user_role();
		$request = doclinc_request_row($request_id);

		return $request
			&& !empty($user_id)
			&& $role === 'warga'
			&& $request->request_status === 'Accepted'
			&& (string) $request->user_id === (string) $user_id;
	}
}

if (!function_exists('doclinc_can_view_chat')) {
	function doclinc_can_view_chat($request_id, $user_id = null, $role = null)
	{
		$user_id = $user_id ?: doclinc_current_user_id();
		$role = $role ?: doclinc_current_user_role();
		$request = doclinc_request_row($request_id);

		if (!$request || empty($user_id)) {
			return false;
		}

		if (!in_array($request->request_status, array('Accepted', 'Completed', 'Cancelled'), true)) {
			return false;
		}

		if ($role === 'warga') {
			return (string) $request->user_id === (string) $user_id;
		}

		if ($role === 'dokter') {
			if (!empty($request->dokter_id) && (string) $request->dokter_id === (string) $user_id) {
				return true;
			}
			return isset($request->accepted_by_user_id)
				&& !empty($request->accepted_by_user_id)
				&& (string) $request->accepted_by_user_id === (string) $user_id;
		}

		return false;
	}
}

if (!function_exists('doclinc_can_send_chat')) {
	function doclinc_can_send_chat($request_id, $user_id = null, $role = null)
	{
		$request = doclinc_request_row($request_id);
		return $request
			&& $request->request_status === 'Accepted'
			&& doclinc_can_view_chat($request_id, $user_id, $role);
	}
}

if (!function_exists('doclinc_log_request_event')) {
	function doclinc_log_request_event($action, $request_id = null, $metadata = array())
	{
		$CI = &get_instance();
		if (!$CI->db->table_exists('audit_logs')) {
			return false;
		}

		$previous_debug = $CI->db->db_debug;
		$CI->db->db_debug = false;
		$result = $CI->db->insert('audit_logs', array(
			'actor_user_id' => doclinc_current_user_id() ?: null,
			'action' => $action,
			'entity_type' => 'request',
			'entity_id' => $request_id,
			'ip_address' => $CI->input->ip_address(),
			'user_agent' => substr((string) $CI->input->user_agent(), 0, 255),
			'metadata_json' => empty($metadata) ? null : json_encode($metadata),
			'created_at' => date('Y-m-d H:i:s'),
		));
		$CI->db->db_debug = $previous_debug;

		return $result;
	}
}
