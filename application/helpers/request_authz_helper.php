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

if (!function_exists('doclinc_normalize_puskesmas_code')) {
	function doclinc_normalize_puskesmas_code($code)
	{
		$code = trim((string) $code);
		return $code !== '' && strtoupper($code) !== 'DEFAULT' ? $code : '';
	}
}

if (!function_exists('doclinc_request_assigned_puskesmas_code')) {
	function doclinc_request_assigned_puskesmas_code($request)
	{
		return $request && isset($request->assigned_puskesmas_code)
			? doclinc_normalize_puskesmas_code($request->assigned_puskesmas_code)
			: '';
	}
}

if (!function_exists('doclinc_user_puskesmas_code')) {
	function doclinc_user_puskesmas_code($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1) {
			return '';
		}

		$CI = &get_instance();
		$remark = $CI->session->userdata('id') && (string) $CI->session->userdata('id') === (string) $user_id
			? $CI->session->userdata('remark')
			: '';
		$code = doclinc_normalize_puskesmas_code($remark);
		if ($code !== '') {
			return $code;
		}

		if (!$CI->db->field_exists('remark', 'users')) {
			return '';
		}

		$user = $CI->db
			->select('remark')
			->where('userId', $user_id)
			->where('role', 'dokter')
			->get('users')
			->row();

		return $user ? doclinc_normalize_puskesmas_code($user->remark) : '';
	}
}

if (!function_exists('doclinc_request_matches_user_puskesmas')) {
	function doclinc_request_matches_user_puskesmas($request, $user_id)
	{
		$request_code = doclinc_request_assigned_puskesmas_code($request);
		if ($request_code === '') {
			return true;
		}

		return $request_code === doclinc_user_puskesmas_code($user_id);
	}
}

if (!function_exists('doclinc_active_consultation_request')) {
	function doclinc_active_consultation_request($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1) {
			return null;
		}

		$CI = &get_instance();
		$CI->db
			->where('user_id', $user_id)
			->where_in('request_status', array('Pending', 'Accepted'));

		if ($CI->db->field_exists('created_at', 'requests')) {
			$CI->db->order_by('created_at', 'DESC');
		}

		return $CI->db
			->order_by('request_id', 'DESC')
			->limit(1)
			->get('requests')
			->row();
	}
}

if (!function_exists('doclinc_queue_bucket')) {
	function doclinc_queue_bucket($assigned_puskesmas_code)
	{
		$bucket = trim((string) $assigned_puskesmas_code);
		return $bucket !== '' ? $bucket : 'LEGACY';
	}
}

if (!function_exists('doclinc_queue_date_value')) {
	function doclinc_queue_date_value($request)
	{
		if (!$request) {
			return date('Ymd');
		}

		foreach (array('queue_date', 'created_at', 'date') as $field) {
			if (isset($request->{$field}) && trim((string) $request->{$field}) !== '') {
				$time = strtotime((string) $request->{$field});
				if ($time) {
					return date('Ymd', $time);
				}
			}
		}

		return date('Ymd');
	}
}

if (!function_exists('doclinc_request_queue_code')) {
	function doclinc_request_queue_code($request)
	{
		if (!$request) {
			return '';
		}

		if (isset($request->queue_code) && trim((string) $request->queue_code) !== '') {
			return trim((string) $request->queue_code);
		}

		$bucket = doclinc_queue_bucket(isset($request->assigned_puskesmas_code) ? $request->assigned_puskesmas_code : '');
		$date = doclinc_queue_date_value($request);
		$number = isset($request->queue_number) && (int) $request->queue_number > 0
			? (int) $request->queue_number
			: (isset($request->request_id) ? (int) $request->request_id : 0);

		return $bucket . '-' . $date . '-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
	}
}

if (!function_exists('doclinc_request_queue_number_label')) {
	function doclinc_request_queue_number_label($request)
	{
		$number = null;
		if ($request && isset($request->queue_number) && (int) $request->queue_number > 0) {
			$number = (int) $request->queue_number;
		} elseif ($request && isset($request->queue_code) && preg_match('/-(\d+)$/', trim((string) $request->queue_code), $match)) {
			$number = (int) $match[1];
		}

		if ($number === null || $number < 1) {
			return 'No. -';
		}

		return 'No. ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
	}
}

if (!function_exists('doclinc_request_puskesmas_label')) {
	function doclinc_request_puskesmas_label($request)
	{
		if ($request && isset($request->assigned_puskesmas_name) && trim((string) $request->assigned_puskesmas_name) !== '') {
			return trim((string) $request->assigned_puskesmas_name);
		}

		return 'Data puskesmas belum lengkap';
	}
}

if (!function_exists('doclinc_consultation_mode_label')) {
	function doclinc_consultation_mode_label($mode)
	{
		$mode = strtolower(trim((string) $mode));
		if ($mode === 'visit') {
			return 'Kunjungan Nakes';
		}
		if ($mode === 'non_visit') {
			return 'Konsultasi Non-Kunjungan';
		}

		return 'Belum ditentukan';
	}
}

if (!function_exists('doclinc_format_distance_text')) {
	function doclinc_format_distance_text($meters)
	{
		if (!is_numeric($meters)) {
			return 'Menghitung...';
		}

		$meters = max(0, (float) $meters);
		if ($meters < 1000) {
			return number_format($meters, 0, ',', '.') . ' m';
		}

		return number_format($meters / 1000, 1, ',', '.') . ' km';
	}
}

if (!function_exists('doclinc_format_eta_text')) {
	function doclinc_format_eta_text($seconds)
	{
		if (!is_numeric($seconds)) {
			return 'Menghitung...';
		}

		$minutes = max(1, (int) ceil(((float) $seconds) / 60));
		if ($minutes < 60) {
			return $minutes . ' menit';
		}

		$hours = (int) floor($minutes / 60);
		$remaining = $minutes % 60;
		return $remaining > 0 ? $hours . ' jam ' . $remaining . ' menit' : $hours . ' jam';
	}
}

if (!function_exists('doclinc_visit_route_pending_payload')) {
	function doclinc_visit_route_pending_payload()
	{
		return array(
			'distance_m' => null,
			'duration_s' => null,
			'distance_text' => 'Menghitung...',
			'eta_text' => 'Menghitung...',
			'geometry' => null,
			'provider' => null,
			'calculated_at' => null,
		);
	}
}

if (!function_exists('doclinc_request_handling_nakes_name')) {
	function doclinc_request_handling_nakes_name($request)
	{
		if (!$request) {
			return '';
		}

		foreach (array('handling_nakes_name', 'assigned_nakes_name', 'accepted_nakes_name') as $field) {
			if (isset($request->{$field}) && trim((string) $request->{$field}) !== '') {
				return trim((string) $request->{$field});
			}
		}

		if (isset($request->request_status) && $request->request_status === 'Pending') {
			return '';
		}

		foreach (array('dokter_user_name', 'nama_dokter') as $field) {
			if (isset($request->{$field}) && trim((string) $request->{$field}) !== '') {
				return trim((string) $request->{$field});
			}
		}

		return '';
	}
}

if (!function_exists('doclinc_request_handling_nakes_id')) {
	function doclinc_request_handling_nakes_id($request)
	{
		if (!$request) {
			return null;
		}

		foreach (array('assigned_nakes_user_id', 'accepted_by_user_id', 'dokter_id') as $field) {
			if (isset($request->{$field}) && trim((string) $request->{$field}) !== '') {
				return $request->{$field};
			}
		}

		return null;
	}
}

if (!function_exists('doclinc_request_is_handled_by_nakes')) {
	function doclinc_request_is_handled_by_nakes($request, $nakes_user_id)
	{
		$handling_nakes_id = doclinc_request_handling_nakes_id($request);
		return doclinc_request_matches_user_puskesmas($request, $nakes_user_id)
			&& $handling_nakes_id !== null
			&& trim((string) $handling_nakes_id) !== ''
			&& (string) $handling_nakes_id === (string) $nakes_user_id;
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
			if (doclinc_request_is_handled_by_nakes($request, $user_id)) {
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
				&& doclinc_normalize_puskesmas_code($user->remark) !== ''
				&& isset($request->assigned_puskesmas_code)
				&& doclinc_request_assigned_puskesmas_code($request) === doclinc_normalize_puskesmas_code($user->remark);
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

		if (!doclinc_request_is_handled_by_nakes($request, $user_id)) {
			return false;
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
			return doclinc_request_is_handled_by_nakes($request, $user_id);
		}

		if ($request->request_status !== 'Pending') {
			return false;
		}

		if (doclinc_request_assigned_puskesmas_code($request) === '' && !empty($request->dokter_id) && (string) $request->dokter_id === (string) $user_id) {
			return true;
		}

		$user = $CI->db
			->select('remark')
			->where('userId', $user_id)
			->where('role', 'dokter')
			->get('users')
			->row();

		return $user
			&& doclinc_normalize_puskesmas_code($user->remark) !== ''
			&& isset($request->assigned_puskesmas_code)
			&& doclinc_request_assigned_puskesmas_code($request) === doclinc_normalize_puskesmas_code($user->remark);
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

		return doclinc_request_is_handled_by_nakes($request, $user_id);
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
			'not_started' => 'Belum dimulai',
			'en_route' => 'Nakes menuju lokasi',
			'arrived' => 'Nakes tiba di lokasi',
			'in_service' => 'Sedang ditangani',
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
			return doclinc_request_is_handled_by_nakes($request, $user_id);
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
