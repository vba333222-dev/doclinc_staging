<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Role_prerequisite_gate_policy
{
	private $json_routes = array(
		'clinical_suggestions' => array('*'),
		'realtime_access' => array('connection_token', 'subscription_token'),
		'realtime_requests' => array('snapshot'),
		'puskesmas_operations' => array('snapshot'),
		'profile_requirements' => array('status'),
		'chat' => array('messages', 'send', 'mark_read', 'foto'),
		'notifikasi' => array('list_json', 'snapshot', 'mark_read'),
		'konsultasi' => array('save_konsultasi'),
		'konsultasi_nakes' => array('get_terapi', 'save_konsultasi_nakes'),
		'home' => array('update_profile', 'update_profile_photo', 'livekit_token', 'livekit_incoming_call', 'answer_livekit_call', 'reject_livekit_call', 'livekit_call_status', 'cancel_request'),
		'home_nakes' => array('updateprofile', 'livekit_token', 'start_livekit_call', 'end_livekit_call', 'livekit_call_status', 'assign_staff', 'clear_staff_assignment', 'accept_request', 'cancel_request', 'presence_heartbeat', 'presence_snapshot'),
	);

	public function allowed($class, $method, $role, $resource_user_id = 0, $actor_user_id = 0)
	{
		$class = strtolower((string) $class);
		$method = strtolower((string) $method);
		$role = strtolower((string) $role);
		if ($class === 'login' && $method === 'logout') {
			return true;
		}
		if ($class === 'profile_completion' && $method === 'index') {
			return true;
		}
		if ($class === 'profile_requirements' && $method === 'status') {
			return true;
		}
		if ($class === 'profile_media' && $method === 'photo') {
			return (int) $resource_user_id > 0 && (int) $resource_user_id === (int) $actor_user_id;
		}
		if ($role === 'warga' && $class === 'home' && in_array($method, array('update_profile', 'update_profile_photo'), true)) {
			return true;
		}
		if ($role === 'dokter' && $class === 'home_nakes' && $method === 'updateprofile') {
			return true;
		}
		return false;
	}

	public function jsonResponse($class, $method, $ajax, $accept, $content_type)
	{
		$class = strtolower((string) $class);
		$method = strtolower((string) $method);
		if ($ajax || stripos((string) $accept, 'application/json') !== false || stripos((string) $content_type, 'application/json') !== false) {
			return true;
		}
		return isset($this->json_routes[$class])
			&& (in_array('*', $this->json_routes[$class], true) || in_array($method, $this->json_routes[$class], true));
	}
}
