<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'libraries/Realtime_access_exception.php';
require_once APPPATH . 'libraries/Realtime_token_service.php';

class Realtime_access extends CI_Controller
{
	private const MAX_SUBSCRIPTION_BODY_BYTES = 1024;

	public function __construct()
	{
		parent::__construct();
		$this->load->library('Realtime_channel_policy');
		$this->load->helper('request_authz');
	}

	public function connection_token()
	{
		$this->prepareJsonResponse();
		if ($this->input->method(true) !== 'GET') {
			$this->respond(405, false, null, 'method_not_allowed');
			return;
		}
		if (!$this->config->item('realtime_client_enabled')) {
			$this->respond(404, false, null, 'feature_disabled');
			return;
		}

		$actor = $this->authenticatedActor();
		if ($actor === null) {
			return;
		}

		try {
			$issued_at = time();
			$tokens = $this->tokenService();
			$this->respond(200, true, array(
				'token' => $tokens->connectionToken($actor['user_id'], $issued_at),
				'expires_at' => $tokens->expiresAt($issued_at),
			));
		} catch (Realtime_access_exception $exception) {
			log_message('error', 'Realtime connection token service unavailable.');
			$this->respond(503, false, null, $exception->safeErrorCode());
		} catch (Throwable $exception) {
			log_message('error', 'Realtime connection token service unavailable.');
			$this->respond(503, false, null, 'token_service_unavailable');
		}
	}

	public function subscription_token()
	{
		$this->prepareJsonResponse();
		if ($this->input->method(true) !== 'POST') {
			$this->respond(405, false, null, 'method_not_allowed');
			return;
		}
		if (!$this->config->item('realtime_client_enabled')) {
			$this->respond(404, false, null, 'feature_disabled');
			return;
		}

		$actor = $this->authenticatedActor();
		if ($actor === null) {
			return;
		}
		$channel = $this->subscriptionChannel();
		if ($channel === null) {
			$this->respond(400, false, null, 'channel_invalid');
			return;
		}
		$parsed = $this->realtime_channel_policy->parse($channel);
		if ($parsed === null || !$this->authorizeChannel($actor, $parsed)) {
			$this->respond(403, false, null, 'subscription_denied');
			return;
		}

		try {
			$issued_at = time();
			$tokens = $this->tokenService();
			$this->respond(200, true, array(
				'token' => $tokens->subscriptionToken($actor['user_id'], $parsed['channel'], $issued_at),
				'expires_at' => $tokens->expiresAt($issued_at),
			));
		} catch (Realtime_access_exception $exception) {
			log_message('error', 'Realtime subscription token service unavailable.');
			$this->respond(503, false, null, $exception->safeErrorCode());
		} catch (Throwable $exception) {
			log_message('error', 'Realtime subscription token service unavailable.');
			$this->respond(503, false, null, 'token_service_unavailable');
		}
	}

	private function authenticatedActor()
	{
		if ($this->session->userdata('logged_in') !== true) {
			$this->respond(401, false, null, 'authentication_required');
			return null;
		}
		$user_id = (int) $this->session->userdata('id');
		$session_role = (string) $this->session->userdata('role');
		if ($user_id < 1 || !in_array($session_role, array('warga', 'dokter'), true)) {
			$this->respond(403, false, null, 'access_denied');
			return null;
		}
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		try {
			if (!$this->db->field_exists('must_change_password', 'users')
				|| !doclinc_nakes_credential_schema_allows_runtime($this->db)) {
				$this->db->db_debug = $db_debug;
				$this->respond(503, false, null, 'account_contract_unavailable');
				return null;
			}
			$query = $this->db
				->select(
					'userId, role, status, must_change_password, ' . doclinc_nakes_password_changed_at_projection($this->db),
					false
				)
				->where('userId', $user_id)
				->limit(1)
				->get('users');
			$user = $query ? $query->row() : null;
		} catch (Throwable $exception) {
			$this->db->db_debug = $db_debug;
			$this->respond(503, false, null, 'account_contract_unavailable');
			return null;
		}
		$this->db->db_debug = $db_debug;
		if (!$user || (string) $user->role !== $session_role) {
			$this->respond(401, false, null, 'authentication_required');
			return null;
		}
		if ((string) $user->status !== 'aktif') {
			$this->respond(403, false, null, 'access_denied');
			return null;
		}
		$must_change_password = doclinc_nakes_password_change_blocked(
			$user->role,
			$user->must_change_password,
			$user->password_changed_at
		);
		if ($must_change_password) {
			$this->respond(403, false, null, 'password_change_required');
			return null;
		}

		$actor = array(
			'authenticated' => true,
			'user_id' => $user_id,
			'role' => $session_role,
			'status' => 'aktif',
			'must_change_password' => $must_change_password,
			'identity' => null,
		);
		if ($session_role === 'dokter') {
			$actor['identity'] = doclinc_dokter_identity_context($user_id, true);
			if (empty($actor['identity']['valid'])) {
				$this->respond(403, false, null, 'access_denied');
				return null;
			}
		}
		return $actor;
	}

	private function authorizeChannel(array $actor, array $parsed)
	{
		$request = null;
		$access = null;
		if ($parsed['type'] === 'request') {
			$request = doclinc_request_row($parsed['id']);
			if ($request && $actor['role'] === 'dokter') {
				$access = doclinc_nakes_request_access_context($request, $actor['identity']);
			}
		}
		return $this->realtime_channel_policy->authorize(
			$actor,
			$parsed,
			$request,
			$actor['identity'],
			$access
		);
	}

	private function subscriptionChannel()
	{
		$content_length = (int) $this->input->server('CONTENT_LENGTH');
		if ($content_length > self::MAX_SUBSCRIPTION_BODY_BYTES) {
			return null;
		}
		$raw = (string) $this->input->raw_input_stream;
		if ($raw === '' || strlen($raw) > self::MAX_SUBSCRIPTION_BODY_BYTES) {
			return null;
		}
		try {
			$body = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
		} catch (Throwable $exception) {
			return null;
		}
		if (!is_array($body) || array_keys($body) !== array('channel') || !is_string($body['channel'])) {
			return null;
		}
		return $body['channel'];
	}

	private function tokenService()
	{
		return new Realtime_token_service(array(
			'secret' => $this->config->item('realtime_client_token_hmac_secret'),
			'ttl_seconds' => $this->config->item('realtime_client_token_ttl_seconds'),
		));
	}

	private function prepareJsonResponse()
	{
		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_header('Cache-Control: no-store, private, max-age=0')
			->set_header('Pragma: no-cache')
			->set_header('X-Content-Type-Options: nosniff');
	}

	private function respond($status, $success, $data = null, $error = null)
	{
		$payload = array('success' => (bool) $success);
		if ($data !== null) {
			$payload['data'] = $data;
		}
		if ($error !== null) {
			$payload['error'] = (string) $error;
		}
		$encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$this->output
			->set_status_header((int) $status)
			->set_output($encoded === false ? '{"success":false,"error":"encoding_failed"}' : $encoded);
	}
}
