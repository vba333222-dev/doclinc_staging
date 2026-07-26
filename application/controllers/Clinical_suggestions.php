<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_suggestions extends CI_Controller
{
	const MAX_QUERY_LENGTH = 80;
	const MAX_RESULTS = 10;

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Clinical_suggestion_m');
		$this->load->library('Clinical_suggestion_policy');
		$this->load->helper('request_authz');
	}

	public function search()
	{
		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_header('Cache-Control: no-store, private, max-age=0')
			->set_header('Pragma: no-cache')
			->set_header('X-Content-Type-Options: nosniff');

		if ($this->input->method(true) !== 'GET') {
			$this->respond(405, false, array(), array(), 'method_not_allowed');
			return;
		}
		if (!$this->config->item('clinical_suggestions_enabled')) {
			$this->respond(404, false, array(), array(), 'feature_disabled');
			return;
		}
		if ($this->session->userdata('logged_in') !== true) {
			$this->respond(401, false, array(), array(), 'authentication_required');
			return;
		}

		$type = strtolower(trim((string) $this->input->get('type', true)));
		$query = preg_replace('/\s+/u', ' ', trim((string) $this->input->get('q', true)));
		$request_id = (int) $this->input->get('request_id', true);
		$allowed_types = array('complaint', 'symptom', 'diagnosis', 'medicine');
		if (!in_array($type, $allowed_types, true)) {
			$this->respond(400, false, array(), array(), 'invalid_type');
			return;
		}
		if (preg_match('/[\x00-\x1F\x7F]/', $query) === 1 || Clinical_suggestion_m::text_length($query) > self::MAX_QUERY_LENGTH) {
			$this->respond(400, false, array(), array(), 'invalid_query');
			return;
		}
		if (!$this->is_authorized($type, $request_id)) {
			$this->respond(403, false, array(), array(), 'access_denied');
			return;
		}

		$meta = array('query' => $query, 'limit' => self::MAX_RESULTS, 'has_more' => false);
		if (Clinical_suggestion_m::text_length(Clinical_suggestion_m::normalize($query)) < 2) {
			$this->respond(200, true, array(), $meta);
			return;
		}

		try {
			$environment = (string) $this->config->item('clinical_suggestions_environment');
			$rows = $this->Clinical_suggestion_m->search($type, $query, $environment, self::MAX_RESULTS);
			$meta['has_more'] = count($rows) > self::MAX_RESULTS;
			$rows = array_slice($rows, 0, self::MAX_RESULTS);
			$data = array();
			foreach ($rows as $row) {
				$code = trim((string) $row['term_code']);
				$label = trim((string) $row['preferred_label']);
				$data[] = array(
					'id' => $row['term_type'] . ':' . $row['reference_key'],
					'type' => $row['term_type'],
					'code' => $code,
					'label' => $label,
					'display' => $code !== '' ? $code . ' — ' . $label : $label,
					'source' => (string) $row['source_name'],
					'version' => (string) $row['source_version'],
				);
			}
			$this->respond(200, true, $data, $meta);
		} catch (Throwable $exception) {
			log_message('error', 'Clinical suggestion search unavailable.');
			$this->respond(503, false, array(), $meta, 'reference_unavailable');
		}
	}

	private function is_authorized($type, $request_id)
	{
		$role = (string) $this->session->userdata('role');
		$user_id = (int) $this->session->userdata('id');
		if ($role === 'warga') {
			$request = $request_id > 0 ? doclinc_request_row($request_id) : null;
			return $this->clinical_suggestion_policy->authorize($role, $type, $user_id, $request_id, $request);
		}

		if ($role !== 'dokter' || $request_id < 1) return false;
		$identity = doclinc_dokter_identity_context($user_id);
		$access = doclinc_nakes_request_access_context($request_id, $identity);
		return $this->clinical_suggestion_policy->authorize($role, $type, $user_id, $request_id, null, $access);
	}

	private function respond($status, $success, array $data, array $meta, $error = null)
	{
		$payload = array('success' => (bool) $success, 'data' => $data, 'meta' => (object) $meta);
		if ($error !== null) {
			$payload['error'] = (string) $error;
		}
		$encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$this->output->set_status_header((int) $status)->set_output($encoded === false ? '{"success":false,"data":[],"meta":{},"error":"encoding_failed"}' : $encoded);
	}
}
