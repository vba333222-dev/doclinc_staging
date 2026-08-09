<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_realtime_requests_enabled')) {
	function doclinc_realtime_requests_enabled()
	{
		$CI = &get_instance();
		return $CI->config->item('realtime_requests_enabled') === true
			&& $CI->config->item('realtime_client_enabled') === true;
	}
}

if (!function_exists('doclinc_request_realtime_delivery')) {
	function doclinc_request_realtime_delivery($db)
	{
		$CI = &get_instance();
		require_once APPPATH . 'libraries/Request_realtime_delivery.php';
		return new Request_realtime_delivery(
			$db,
			array('enabled' => doclinc_realtime_requests_enabled()),
			array('enabled' => $CI->config->item('realtime_notifications_enabled') === true)
		);
	}
}

if (!function_exists('doclinc_request_transition_orchestrator')) {
	function doclinc_request_transition_orchestrator()
	{
		require_once APPPATH . 'libraries/Request_transition_orchestrator.php';
		return new Request_transition_orchestrator();
	}
}

if (!function_exists('doclinc_request_realtime_bootstrap')) {
	function doclinc_request_realtime_bootstrap()
	{
		$CI = &get_instance();
		if (!doclinc_realtime_requests_enabled() || $CI->session->userdata('logged_in') != true) {
			return null;
		}
		$user_id = (int) $CI->session->userdata('id');
		$role = (string) $CI->session->userdata('role');
		if ($user_id < 1 || !in_array($role, array('warga', 'dokter'), true)
			|| !$CI->db->field_exists('must_change_password', 'users')
			|| !doclinc_nakes_credential_schema_allows_runtime($CI->db)) {
			return null;
		}
		$user = $CI->db->select(
			'userId, role, status, must_change_password, ' . doclinc_nakes_password_changed_at_projection($CI->db),
			false
		)->where('userId', $user_id)->limit(1)->get('users')->row();
		if (!$user || (string) $user->role !== $role || (string) $user->status !== 'aktif') {
			return null;
		}
		$must_change_password = doclinc_nakes_password_change_blocked($user->role, $user->must_change_password, $user->password_changed_at);
		$identity = null;
		if ($role === 'dokter') {
			$CI->load->helper('request_authz');
			$identity = doclinc_dokter_identity_context($user_id, true);
		}
		require_once APPPATH . 'libraries/Request_realtime_policy.php';
		$policy = new Request_realtime_policy();
		$actor = array('authenticated' => true, 'user_id' => $user_id, 'role' => $role,
			'status' => (string) $user->status, 'must_change_password' => $must_change_password, 'identity' => $identity);
		$channel = $policy->channel($actor);
		if ($channel === null) {
			return null;
		}
		$base_path = parse_url(base_url(), PHP_URL_PATH);
		$base_path = is_string($base_path) ? '/' . trim($base_path, '/') : '';
		$base_path = $base_path === '/' ? '' : $base_path;
		return array(
			'enabled' => true,
			'websocket_url' => (string) $CI->config->item('realtime_client_websocket_url'),
			'connection_token_url' => $base_path . '/realtime/connection-token',
			'subscription_token_url' => $base_path . '/realtime/subscription-token',
			'snapshot_url' => $base_path . '/realtime/requests/snapshot',
			'channel' => $channel,
			'poll_interval_ms' => 30000,
		);
	}
}
