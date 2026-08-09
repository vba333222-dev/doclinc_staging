<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_role_prerequisite_state')) {
	function doclinc_role_prerequisite_state($user_id = null, $refresh = false)
	{
		static $cache = array();
		$CI = &get_instance();
		$user_id = $user_id === null ? (int) $CI->session->userdata('id') : (int) $user_id;
		$enforced = $CI->config->item('role_prerequisites_enabled') === true;
		$key = $user_id . ':' . ($enforced ? '1' : '0');
		if (!$refresh && isset($cache[$key])) {
			return $cache[$key];
		}

		require_once APPPATH . 'libraries/Profile_image_storage.php';
		require_once APPPATH . 'libraries/Role_prerequisite_service.php';
		$storage = new Profile_image_storage(array(
			'storage_path' => $CI->config->item('profile_image_storage_path'),
			'public_root' => FCPATH,
		));
		$service = new Role_prerequisite_service($CI->db, array(
			'credential_enforcement_enabled' => $CI->config->item('nakes_credential_enforcement_enabled') === true,
			'photo_validator' => function ($stored_key) use ($storage) {
				$path = $storage->resolve_stored_file($stored_key);
				return $path !== false && $storage->allowed_mime($path) !== '';
			},
		));
		$cache[$key] = $service->evaluate($user_id, $enforced);
		return $cache[$key];
	}
}

if (!function_exists('doclinc_role_prerequisite_error_payload')) {
	function doclinc_role_prerequisite_error_payload(array $state)
	{
		$code = isset($state['safe_error_code']) ? (string) $state['safe_error_code'] : 'profile_prerequisites_missing';
		if (!in_array($code, array('actor_denied', 'profile_schema_unavailable', 'profile_prerequisites_missing'), true)) {
			$code = 'profile_prerequisites_missing';
		}
		$message = 'Lengkapi profil sebelum melanjutkan.';
		if ($code === 'actor_denied') {
			$message = 'Anda tidak memiliki akses.';
		} elseif ($code === 'profile_schema_unavailable') {
			$message = 'Data profil belum siap. Hubungi pengelola.';
		}
		return array(
			'status' => 'error',
			'success' => false,
			'safe_error_code' => $code,
			'message' => $message,
			'missing_fields' => isset($state['missing_fields']) ? array_values($state['missing_fields']) : array(),
			'missing_labels' => isset($state['missing_labels']) ? array_values($state['missing_labels']) : array(),
			'self_service_fields' => isset($state['self_service_fields']) ? array_values($state['self_service_fields']) : array(),
			'managed_fields' => isset($state['managed_fields']) ? array_values($state['managed_fields']) : array(),
			'remediation_mode' => isset($state['remediation_mode']) ? (string) $state['remediation_mode'] : 'none',
			'cta_url' => $code === 'profile_prerequisites_missing' && !empty($state['cta_url'])
				? base_url($state['cta_url'])
				: '',
			'cta_label' => $code === 'profile_prerequisites_missing' && !empty($state['cta_label'])
				? (string) $state['cta_label']
				: '',
		);
	}
}

if (!function_exists('doclinc_role_prerequisite_http_status')) {
	function doclinc_role_prerequisite_http_status(array $state)
	{
		$code = isset($state['safe_error_code']) ? (string) $state['safe_error_code'] : '';
		return $code === 'actor_denied' ? 403 : ($code === 'profile_schema_unavailable' ? 503 : 422);
	}
}
