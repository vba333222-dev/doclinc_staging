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

if (!function_exists('doclinc_dokter_identity_context')) {
	function doclinc_dokter_identity_context($user_id = null)
	{
		static $context_cache = array();

		if ($user_id === null) {
			$user_id = doclinc_current_user_id();
		}
		$user_id = (int) $user_id;
		$cache_key = (string) $user_id;
		if (array_key_exists($cache_key, $context_cache)) {
			return $context_cache[$cache_key];
		}

		$context = array(
			'valid' => false,
			'account_type' => 'unclassified',
			'user_id' => $user_id > 0 ? $user_id : 0,
			'role' => null,
			'user_status' => null,
			'remark' => null,
			'puskesmas_code' => null,
			'puskesmas_name' => null,
			'puskesmas_status' => null,
			'staff_id' => null,
			'staff_status' => null,
			'staff_profesi' => null,
			'is_command_center' => false,
			'is_personal' => false,
			'errors' => array(),
		);

		if ($user_id < 1) {
			$context['errors'][] = 'user_missing';
			$context_cache[$cache_key] = $context;
			return $context;
		}

		$CI = &get_instance();
		$db = $CI->load->database('default', true);
		$db_debug = $db->db_debug;
		$db->db_debug = false;
		$finish = function ($result) use (&$context_cache, $cache_key, $db, $db_debug) {
			$db->db_debug = $db_debug;
			$context_cache[$cache_key] = $result;
			return $result;
		};

		if (!$db->table_exists('users')) {
			$context['errors'][] = 'user_missing';
			return $finish($context);
		}
		foreach (array('userId', 'role', 'status', 'remark') as $field) {
			if (!$db->field_exists($field, 'users')) {
				$context['errors'][] = 'user_schema_invalid';
				return $finish($context);
			}
		}

		$user_query = $db
			->select('userId, role, status, remark')
			->where('userId', $user_id)
			->limit(1)
			->get('users');
		if (!$user_query) {
			$context['errors'][] = 'user_lookup_failed';
			return $finish($context);
		}

		$user = $user_query->row();
		if (!$user) {
			$context['errors'][] = 'user_missing';
			return $finish($context);
		}

		$context['role'] = isset($user->role) ? (string) $user->role : null;
		$context['user_status'] = isset($user->status) ? (string) $user->status : null;
		$context['remark'] = isset($user->remark) ? trim((string) $user->remark) : null;
		if ($context['role'] !== 'dokter') {
			$context['errors'][] = 'role_invalid';
			return $finish($context);
		}
		if ($context['user_status'] !== 'aktif') {
			$context['errors'][] = 'user_inactive';
			return $finish($context);
		}

		$puskesmas_code = doclinc_normalize_puskesmas_code($context['remark']);
		if ($puskesmas_code === '') {
			$context['errors'][] = 'remark_invalid';
			return $finish($context);
		}
		$context['puskesmas_code'] = $puskesmas_code;

		if (!$db->table_exists('m_puskesmas') || !$db->field_exists('kode_pkm', 'm_puskesmas')) {
			$context['errors'][] = 'puskesmas_invalid';
			return $finish($context);
		}

		$puskesmas_select = array('kode_pkm');
		if ($db->field_exists('nama_puskesmas', 'm_puskesmas')) {
			$puskesmas_select[] = 'nama_puskesmas';
		}
		if ($db->field_exists('status', 'm_puskesmas')) {
			$puskesmas_select[] = 'status';
		}
		$puskesmas_query = $db
			->select(implode(', ', $puskesmas_select))
			->where('kode_pkm', $puskesmas_code)
			->limit(1)
			->get('m_puskesmas');
		if (!$puskesmas_query) {
			$context['errors'][] = 'puskesmas_invalid';
			return $finish($context);
		}

		$puskesmas = $puskesmas_query->row();
		if (!$puskesmas) {
			$context['errors'][] = 'puskesmas_invalid';
			return $finish($context);
		}
		$context['puskesmas_name'] = isset($puskesmas->nama_puskesmas) ? (string) $puskesmas->nama_puskesmas : null;
		$context['puskesmas_status'] = isset($puskesmas->status) ? (string) $puskesmas->status : null;
		if ($db->field_exists('status', 'm_puskesmas') && $context['puskesmas_status'] !== 'aktif') {
			$context['errors'][] = 'puskesmas_invalid';
			return $finish($context);
		}

		$command_center_query = $db
			->select('userId')
			->where('role', 'dokter')
			->where('status', 'aktif')
			->where('TRIM(remark) = ' . $db->escape($puskesmas_code), null, false)
			->order_by('userId', 'ASC')
			->limit(1)
			->get('users');
		if (!$command_center_query) {
			$context['errors'][] = 'command_center_lookup_failed';
			return $finish($context);
		}
		$command_center = $command_center_query->row();
		$is_canonical_command_center = $command_center
			&& (string) $command_center->userId === (string) $user_id;

		if (!$db->table_exists('puskesmas_staff')) {
			$context['errors'][] = 'staff_schema_invalid';
			return $finish($context);
		}
		foreach (array('staff_id', 'user_id', 'kode_pkm', 'status') as $field) {
			if (!$db->field_exists($field, 'puskesmas_staff')) {
				$context['errors'][] = 'staff_schema_invalid';
				return $finish($context);
			}
		}

		$staff_select = array('staff_id', 'user_id', 'kode_pkm', 'status');
		if ($db->field_exists('profesi', 'puskesmas_staff')) {
			$staff_select[] = 'profesi';
		}
		$staff_query = $db
			->select(implode(', ', $staff_select))
			->where('user_id', $user_id)
			->order_by('staff_id', 'ASC')
			->get('puskesmas_staff');
		if (!$staff_query) {
			$context['errors'][] = 'staff_lookup_failed';
			return $finish($context);
		}

		$staff_rows = $staff_query->result();
		$staff_count = count($staff_rows);
		if ($is_canonical_command_center) {
			if ($staff_count > 0) {
				$context['errors'][] = 'command_center_linked_to_staff';
				if ($staff_count > 1) {
					$context['errors'][] = 'multiple_staff_links';
				}
				return $finish($context);
			}

			$context['valid'] = true;
			$context['account_type'] = 'command_center';
			$context['is_command_center'] = true;
			return $finish($context);
		}

		if ($staff_count < 1) {
			$context['errors'][] = 'personal_staff_missing';
			return $finish($context);
		}
		if ($staff_count > 1) {
			$context['errors'][] = 'multiple_staff_links';
			return $finish($context);
		}

		$staff = $staff_rows[0];
		$context['staff_id'] = isset($staff->staff_id) ? (int) $staff->staff_id : null;
		$context['staff_status'] = isset($staff->status) ? (string) $staff->status : null;
		$context['staff_profesi'] = isset($staff->profesi) ? (string) $staff->profesi : null;
		if ($context['staff_status'] !== 'aktif') {
			$context['errors'][] = 'personal_staff_inactive';
			return $finish($context);
		}

		$staff_puskesmas_code = isset($staff->kode_pkm)
			? doclinc_normalize_puskesmas_code($staff->kode_pkm)
			: '';
		if ($staff_puskesmas_code === '' || $staff_puskesmas_code !== $puskesmas_code) {
			$context['errors'][] = 'staff_puskesmas_mismatch';
			return $finish($context);
		}

		$context['valid'] = true;
		$context['account_type'] = 'personal';
		$context['is_personal'] = true;
		return $finish($context);
	}
}

if (!function_exists('doclinc_is_command_center')) {
	function doclinc_is_command_center($user_id = null)
	{
		$context = doclinc_dokter_identity_context($user_id);
		return $context['valid'] === true && $context['is_command_center'] === true;
	}
}

if (!function_exists('doclinc_is_personal_dokter')) {
	function doclinc_is_personal_dokter($user_id = null)
	{
		$context = doclinc_dokter_identity_context($user_id);
		return $context['valid'] === true && $context['is_personal'] === true;
	}
}

if (!function_exists('doclinc_is_classified_dokter')) {
	function doclinc_is_classified_dokter($user_id = null)
	{
		$context = doclinc_dokter_identity_context($user_id);
		return $context['valid'] === true
			&& in_array($context['account_type'], array('command_center', 'personal'), true);
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

		$auth_db = $CI->load->database('default', true);
		if (!$auth_db->field_exists('remark', 'users')) {
			return '';
		}

		$user = $auth_db
			->select('remark')
			->where('userId', $user_id)
			->where('role', 'dokter')
			->get('users')
			->row();
		if (method_exists($auth_db, 'reset_query')) {
			$auth_db->reset_query();
		}

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

if (!function_exists('doclinc_can_view_request_notification')) {
	function doclinc_can_view_request_notification($request_id, $identity_context = null)
	{
			$request_id = (int) $request_id;
			if ($request_id < 1) {
				return false;
			}

			if ($identity_context === null) {
				$identity_context = doclinc_dokter_identity_context();
			}
			if (!is_array($identity_context) || empty($identity_context['valid'])) {
				return false;
			}

			$user_id = isset($identity_context['user_id']) ? (int) $identity_context['user_id'] : 0;
			$puskesmas_code = isset($identity_context['puskesmas_code'])
				? doclinc_normalize_puskesmas_code($identity_context['puskesmas_code'])
				: '';
			$request = doclinc_request_row($request_id);
			if (!$request || $user_id < 1 || $puskesmas_code === '') {
				return false;
			}

			$request_puskesmas_code = isset($request->assigned_puskesmas_code)
				? trim((string) $request->assigned_puskesmas_code)
				: '';
			if ($identity_context['account_type'] === 'command_center') {
				return $request_puskesmas_code === $puskesmas_code
					|| ($request_puskesmas_code === '' && (string) $request->dokter_id === (string) $user_id);
			}

			if ($identity_context['account_type'] !== 'personal' || $request_puskesmas_code !== $puskesmas_code) {
				return false;
			}

			$staff_id = isset($identity_context['staff_id']) ? (int) $identity_context['staff_id'] : 0;
			if ($staff_id < 1) {
				return false;
			}

			$CI = &get_instance();
			$active_assignments = array();
			if ($CI->db->table_exists('request_staff_assignments')) {
				$active_assignments = $CI->db
					->select('staff_id')
					->where('request_id', $request_id)
					->where('status', 'aktif')
					->get('request_staff_assignments')
					->result();
			}

			$active_count = count($active_assignments);
			$matching_count = 0;
			foreach ($active_assignments as $assignment) {
				if ((int) $assignment->staff_id === $staff_id) {
					$matching_count++;
				}
			}
			$direct_user_id = isset($request->assigned_nakes_user_id) ? (int) $request->assigned_nakes_user_id : 0;
			if ($direct_user_id > 0) {
				return $direct_user_id === $user_id
					&& ($active_count === 0 || ($active_count === 1 && $matching_count === 1));
			}
			if ($active_count > 0) {
				return $active_count === 1 && $matching_count === 1;
			}

			return (isset($request->accepted_by_user_id) && (string) $request->accepted_by_user_id === (string) $user_id)
				|| (isset($request->dokter_id) && (string) $request->dokter_id === (string) $user_id);
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
