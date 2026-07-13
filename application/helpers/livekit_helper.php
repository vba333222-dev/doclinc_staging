<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_livekit_config_value')) {
	function doclinc_livekit_config_value($env_key, $config_key, $default = '')
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

if (!function_exists('doclinc_livekit_enabled')) {
	function doclinc_livekit_enabled()
	{
		$value = doclinc_livekit_config_value('LIVEKIT_ENABLED', 'livekit_enabled', false);
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}
}

if (!function_exists('doclinc_livekit_ws_url')) {
	function doclinc_livekit_ws_url()
	{
		return trim((string) doclinc_livekit_config_value('LIVEKIT_WS_URL', 'livekit_ws_url', ''));
	}
}

if (!function_exists('doclinc_livekit_api_key')) {
	function doclinc_livekit_api_key()
	{
		return trim((string) doclinc_livekit_config_value('LIVEKIT_API_KEY', 'livekit_api_key', ''));
	}
}

if (!function_exists('doclinc_livekit_api_secret')) {
	function doclinc_livekit_api_secret()
	{
		return trim((string) doclinc_livekit_config_value('LIVEKIT_API_SECRET', 'livekit_api_secret', ''));
	}
}

if (!function_exists('doclinc_livekit_token_ttl_seconds')) {
	function doclinc_livekit_token_ttl_seconds()
	{
		$value = doclinc_livekit_config_value('LIVEKIT_TOKEN_TTL_SECONDS', 'livekit_token_ttl_seconds', 3600);
		return is_numeric($value) ? max(60, min(86400, (int) $value)) : 3600;
	}
}

if (!function_exists('doclinc_livekit_config_ready')) {
	function doclinc_livekit_config_ready()
	{
		return doclinc_livekit_enabled()
			&& doclinc_livekit_ws_url() !== ''
			&& doclinc_livekit_api_key() !== ''
			&& doclinc_livekit_api_secret() !== '';
	}
}

if (!function_exists('doclinc_livekit_room_name')) {
	function doclinc_livekit_room_name($request_id)
	{
		return 'doclinc-consultation-' . (int) $request_id;
	}
}

if (!function_exists('doclinc_livekit_participant_identity')) {
	function doclinc_livekit_participant_identity($role, $user_id)
	{
		$role = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim((string) $role)));
		$role = trim($role, '-');
		return ($role !== '' ? $role : 'user') . '-' . (int) $user_id;
	}
}

if (!function_exists('doclinc_livekit_base64url')) {
	function doclinc_livekit_base64url($value)
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}
}

if (!function_exists('doclinc_livekit_json_base64url')) {
	function doclinc_livekit_json_base64url($value)
	{
		$json = json_encode($value, JSON_UNESCAPED_SLASHES);
		return $json === false ? false : doclinc_livekit_base64url($json);
	}
}

if (!function_exists('doclinc_generate_livekit_token')) {
	function doclinc_generate_livekit_token($room_name, $identity, $name = '', $metadata = array(), $ttl = null)
	{
		$api_key = doclinc_livekit_api_key();
		$api_secret = doclinc_livekit_api_secret();
		$ttl = $ttl !== null && is_numeric($ttl) ? (int) $ttl : doclinc_livekit_token_ttl_seconds();
		$now = time();

		if ($api_key === '' || $api_secret === '' || trim((string) $room_name) === '' || trim((string) $identity) === '') {
			return false;
		}

		$header = array('alg' => 'HS256', 'typ' => 'JWT');
		$claims = array(
			'iss' => $api_key,
			'sub' => (string) $identity,
			'nbf' => $now,
			'exp' => $now + $ttl,
			'video' => array(
				'roomJoin' => true,
				'room' => (string) $room_name,
				'canPublish' => true,
				'canSubscribe' => true,
				'canPublishData' => true,
			),
		);

		if (trim((string) $name) !== '') {
			$claims['name'] = trim((string) $name);
		}
		if (!empty($metadata)) {
			$metadata_json = json_encode($metadata, JSON_UNESCAPED_SLASHES);
			if ($metadata_json !== false) {
				$claims['metadata'] = $metadata_json;
			}
		}

		$encoded_header = doclinc_livekit_json_base64url($header);
		$encoded_claims = doclinc_livekit_json_base64url($claims);
		if ($encoded_header === false || $encoded_claims === false) {
			return false;
		}

		$unsigned = $encoded_header . '.' . $encoded_claims;
		$signature = hash_hmac('sha256', $unsigned, $api_secret, true);
		return $unsigned . '.' . doclinc_livekit_base64url($signature);
	}
}

if (!function_exists('doclinc_livekit_authorized_request')) {
	function doclinc_livekit_authorized_request($request_id, $user_id = null, $role = null)
	{
		$user_id = $user_id ?: doclinc_current_user_id();
		$role = $role ?: doclinc_current_user_role();
		$request = doclinc_request_row($request_id);

		if (!$request || empty($user_id)) {
			return array(false, null, 'Request tidak ditemukan');
		}
		if ($request->request_status !== 'Accepted') {
			return array(false, $request, 'Panggilan hanya tersedia untuk konsultasi aktif.');
		}
		if ($role === 'warga' && (string) $request->user_id === (string) $user_id) {
			return array(true, $request, 'OK');
		}
		if (in_array($role, array('dokter', 'nakes'), true) && function_exists('doclinc_request_is_handled_by_nakes') && doclinc_request_is_handled_by_nakes($request, $user_id)) {
			return array(true, $request, 'OK');
		}

		return array(false, $request, 'Akses tidak diizinkan');
	}
}

if (!function_exists('doclinc_livekit_current_user_name')) {
	function doclinc_livekit_current_user_name()
	{
		$CI = &get_instance();
		foreach (array('nama', 'name', 'username') as $key) {
			$value = trim((string) $CI->session->userdata($key));
			if ($value !== '') {
				return $value;
			}
		}

		return 'Pengguna DocLink';
	}
}

if (!function_exists('doclinc_livekit_token_payload')) {
	function doclinc_livekit_token_payload($request_id, $user_id = null, $role = null)
	{
		$request_id = (int) $request_id;
		$user_id = $user_id ?: doclinc_current_user_id();
		$role = $role ?: doclinc_current_user_role();

		if ($request_id < 1) {
			return array('http_status' => 400, 'body' => array('success' => false, 'message' => 'Data request tidak valid'));
		}
		if (!doclinc_livekit_enabled()) {
			return array('http_status' => 503, 'body' => array('success' => false, 'message' => 'Panggilan belum aktif'));
		}
		if (!doclinc_livekit_config_ready()) {
			return array('http_status' => 503, 'body' => array('success' => false, 'message' => 'Konfigurasi panggilan belum lengkap'));
		}

		list($allowed, $request, $message) = doclinc_livekit_authorized_request($request_id, $user_id, $role);
		if (!$allowed) {
			return array(
				'http_status' => $request ? 403 : 404,
				'body' => array(
					'success' => false,
					'message' => $message,
					'request_status' => $request && isset($request->request_status) ? $request->request_status : null,
				),
			);
		}

		$room = doclinc_livekit_room_name($request_id);
		$identity = doclinc_livekit_participant_identity($role, $user_id);
		$ttl = doclinc_livekit_token_ttl_seconds();
		$metadata = array(
			'request_id' => $request_id,
			'role' => $role,
			'user_id' => (int) $user_id,
		);
		$token = doclinc_generate_livekit_token($room, $identity, doclinc_livekit_current_user_name(), $metadata, $ttl);
		if ($token === false) {
			return array('http_status' => 500, 'body' => array('success' => false, 'message' => 'Token panggilan tidak dapat dibuat'));
		}

		return array(
			'http_status' => 200,
			'body' => array(
				'success' => true,
				'ws_url' => doclinc_livekit_ws_url(),
				'token' => $token,
				'room' => $room,
				'identity' => $identity,
				'ttl_seconds' => $ttl,
				'message' => 'Token panggilan dibuat',
			),
		);
	}
}
