<?php
defined('BASEPATH') or exit('No direct script access allowed');

class First_login_gate_policy
{
	private $json_routes = array(
		'clinical_suggestions' => array('*'),
		'realtime_access' => array('connection_token', 'subscription_token'),
		'realtime_requests' => array('snapshot'),
		'puskesmas_operations' => array('snapshot'),
		'profile_media' => array('photo'),
		'profile_requirements' => array('status'),
		'chat' => array('messages', 'send', 'mark_read', 'foto', 'video'),
		'upload' => array('foto', 'video'),
		'notifikasi' => array('list_json', 'snapshot', 'mark_read'),
		'konsultasi' => array('save_konsultasi'),
		'konsultasi_nakes' => array('get_terapi', 'save_konsultasi_nakes'),
		'home' => array('livekit_token', 'livekit_incoming_call', 'answer_livekit_call', 'reject_livekit_call', 'livekit_call_status', 'updaterequestbyid', 'deleterequestbyid', 'cancel_request', 'visit_location', 'submit_rating', 'getdokterrating', 'getduration', 'update_profile', 'update_profile_photo'),
		'home_nakes' => array('livekit_token', 'start_livekit_call', 'end_livekit_call', 'livekit_call_status', 'assign_staff', 'clear_staff_assignment', 'accept_request', 'cancel_request', 'visit_location', 'update_visit_location', 'update_visit_status', 'presence_heartbeat', 'presence_snapshot', 'updateprofile'),
	);

	public function allowed($class, $method)
	{
		return strtolower((string) $class) === 'login'
			&& in_array(strtolower((string) $method), array('change_password', 'update_password', 'logout'), true);
	}

	public function jsonResponse($class, $method, $ajax, $accept, $content_type)
	{
		$class = strtolower((string) $class);
		$method = strtolower((string) $method);
		if ($ajax || stripos((string) $accept, 'application/json') !== false || stripos((string) $content_type, 'application/json') !== false) {
			return true;
		}
		if (!isset($this->json_routes[$class])) {
			return false;
		}
		return in_array('*', $this->json_routes[$class], true) || in_array($method, $this->json_routes[$class], true);
	}
}
