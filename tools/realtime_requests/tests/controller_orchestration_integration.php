<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 3) . '/'); }

$GLOBALS['controller_ci_instance'] = null;
$GLOBALS['controller_care_team_workflow_enabled'] = false;

if (!function_exists('get_instance')) {
	function &get_instance()
	{
		$instance = &$GLOBALS['controller_ci_instance'];
		return $instance;
	}
}

require_once APPPATH . 'libraries/Request_transition_orchestrator.php';
require_once APPPATH . 'libraries/Medicalrecord_diagnosis_service.php';

class MX_Controller
{
	public $session;
	public $input;
	public $output;
	public $config;
	public $encryption;
	public $db;
	public $load;
	public $Konsultasi_m;
	public $Home_m;
	public $Home_nakes_m;
	public $Konsultasi_nakes_m;
}

final class ControllerSession
{
	private $values;
	public function __construct(array $values) { $this->values = $values; }
	public function userdata($key) { return $this->values[$key] ?? null; }
}

final class ControllerInput
{
	private $values;
	public function __construct(array $values) { $this->values = $values; }
	public function method($upper = false) { return $upper ? 'POST' : 'post'; }
	public function post($key, $xss_clean = null) { return $this->values[$key] ?? null; }
	public function get($key, $xss_clean = null) { return $this->values[$key] ?? null; }
}

final class ControllerOutput
{
	public $status = 200;
	public $body = '';
	public $content_type = '';
	public function set_content_type($value) { $this->content_type = (string) $value; return $this; }
	public function set_status_header($value) { $this->status = (int) $value; return $this; }
	public function set_output($value) {
		$this->body = (string) $value;
		$GLOBALS['controller_trace'][] = 'response.write';
		return $this;
	}
}

final class ControllerConfig
{
	public function item($key) {
		if ($key === 'clinical_suggestions_enabled') { return false; }
		if ($key === 'care_team_workflow_enabled') { return $GLOBALS['controller_care_team_workflow_enabled'] === true; }
		return null;
	}
}

final class ControllerEncryption
{
	public function encrypt($value) { return 'encrypted:' . (string) $value; }
}

final class ControllerDatabase
{
	public function table_exists($table) { return false; }
}

final class ControllerLoader
{
	public function library($name, $config = null) { return true; }
}

final class ControllerModelCollaborator
{
	private $operation;
	private $success;
	public function __construct($operation, $success) { $this->operation = $operation; $this->success = (bool) $success; }
	private function begin() { $GLOBALS['controller_trace'][] = 'model.begin'; }
	private function finish() { $GLOBALS['controller_trace'][] = $this->success ? 'model.success' : 'model.failure'; }
	private function replaceRequest($request_id, array $changes) {
		$current = clone $GLOBALS['controller_requests'][$request_id];
		foreach ($changes as $key => $value) { $current->{$key} = $value; }
		$GLOBALS['controller_requests'][$request_id] = $current;
	}
	public function save_konsultasi($owner_id, $handler_id, $history, $description, $location, $latitude, $longitude, $date, $photo, $video, array $routing) {
		$this->begin();
		if ($this->success) {
			$GLOBALS['controller_requests'][5001] = (object) array(
				'request_id' => 5001, 'user_id' => (int) $owner_id, 'dokter_id' => (int) $handler_id,
				'request_status' => 'Pending', 'assigned_puskesmas_code' => $routing['assigned_puskesmas_code'],
				'assigned_puskesmas_name' => $routing['assigned_puskesmas_name'],
			);
		}
		$this->finish();
		return $this->success ? 5001 : false;
	}
	public function accept_request($request_id, $actor_id, $latitude, $longitude, $puskesmas_code, $identity) {
		$this->begin();
		if ($this->success) { $this->replaceRequest($request_id, array('request_status' => 'Accepted', 'accepted_by_user_id' => (int) $actor_id)); }
		$this->finish();
		return $this->success
			? array('status' => 'success', 'message' => 'Konsultasi diterima.', 'already_accepted' => false)
			: array('status' => 'error', 'message' => 'Permintaan tidak dapat diakses.');
	}
	public function cancel_request($request_id, $actor_id, $puskesmas_code = null, $identity = null) {
		$this->begin();
		if ($this->success) { $this->replaceRequest($request_id, array('request_status' => 'Cancelled')); }
		$this->finish();
		if ($this->operation === 'cancel_warga') { return $this->success; }
		return $this->success
			? array('status' => 'success', 'message' => 'Permintaan dibatalkan.')
			: array('status' => 'error', 'message' => 'Permintaan tidak dapat dibatalkan.');
	}
	public function save_konsultasi_nakes($request_id, $diagnosis, $recommendation, $criteria, $referral, $photo, $therapy, $actor_id, $identity, $anamnesis, $write_anamnesis, $visit_proof = null, $visit_proof_required = false) {
		$this->begin();
		if ($this->success) { $this->replaceRequest($request_id, array('request_status' => 'Completed')); }
		$this->finish();
		return $this->success;
	}
	public function append_request_event($request_id, $event_type, array $data) { $GLOBALS['controller_trace'][] = 'event.append'; return true; }
}

final class ControllerOrchestratorProbe
{
	private $inner;
	public $calls = array();
	public function __construct() { $this->inner = new Request_transition_orchestrator(); }
	private function invoke($method, array $arguments) {
		$this->calls[] = array('method' => $method, 'arguments' => $arguments);
		$GLOBALS['controller_trace'][] = 'orchestrator.' . $method;
		return call_user_func_array(array($this->inner, $method), $arguments);
	}
	public function requestCreated($request_id, $request, $actor_user_id) { return $this->invoke(__FUNCTION__, func_get_args()); }
	public function requestAccepted($request_id, $request, $actor_user_id, array $result) { return $this->invoke(__FUNCTION__, func_get_args()); }
	public function requestCancelledByOwner($request_id, $request, $actor_user_id, $result) { return $this->invoke(__FUNCTION__, func_get_args()); }
	public function requestCancelledByCommandCenter($request_id, $request, $actor_user_id, array $result) { return $this->invoke(__FUNCTION__, func_get_args()); }
	public function requestCompleted($request_id, $request, $actor_user_id, $result) { return $this->invoke(__FUNCTION__, func_get_args()); }
}

$GLOBALS['controller_trace'] = array();
$GLOBALS['controller_requests'] = array();
$GLOBALS['controller_orchestrator'] = null;
$GLOBALS['controller_notification_calls'] = array();
$GLOBALS['controller_notification_succeeds'] = true;
$GLOBALS['controller_visit_proof_required'] = false;

function base_url($path = '') { return 'https://fixture.invalid/' . ltrim((string) $path, '/'); }
function log_message($level, $message) { return true; }
function redirect($uri = '', $method = 'auto', $code = null) { $GLOBALS['controller_trace'][] = 'redirect'; }
function doclinc_role_prerequisite_state($user_id, $fresh = false) { return array('allowed' => (int) $user_id > 0, 'enforced' => false, 'fresh' => (bool) $fresh); }
function doclinc_realtime_requests_enabled() { return false; }
function doclinc_visit_proof_required() { return $GLOBALS['controller_visit_proof_required'] === true; }
function doclinc_visit_proof_is_visit($criteria) {
	$criteria = strtolower(trim((string) $criteria));
	return $criteria === '1' || $criteria === 'kunjungan nakes';
}
function doclinc_visit_proof_persisted_visit($request) {
	if (!$request) { return false; }
	$mode = strtolower(trim((string) ($request->consultation_mode ?? '')));
	$status = strtolower(trim((string) ($request->visit_status ?? '')));
	return $mode === 'visit' || in_array($status, array('en_route', 'arrived', 'in_service', 'completed'), true);
}
function doclinc_request_transition_orchestrator() { return $GLOBALS['controller_orchestrator']; }
function doclinc_active_consultation_request($user_id) { return false; }
function doclinc_find_puskesmas_by_service_area($latitude, $longitude) { return (object) array('kode_pkm' => 'PKM01', 'nama_puskesmas' => 'Synthetic clinic'); }
function doclinc_get_queue_handler_user_id($puskesmas_code) { return $puskesmas_code === 'PKM01' ? 10 : null; }
function doclinc_request_row($request_id) { return isset($GLOBALS['controller_requests'][(int) $request_id]) ? clone $GLOBALS['controller_requests'][(int) $request_id] : null; }
function doclinc_append_request_event($request_id, $event_type, array $data, $controller = null) { $GLOBALS['controller_trace'][] = 'event.append'; return true; }
function doclinc_log_request_event($event_type, $request_id, array $metadata = array()) { $GLOBALS['controller_trace'][] = 'event.log'; return true; }
function doclinc_can_cancel_request($request_id, $user_id, $role) { $request = doclinc_request_row($request_id); return $request && $role === 'warga' && (int) $request->user_id === (int) $user_id; }
function doclinc_dokter_identity_context($user_id = null, $refresh = false) {
	$user_id = (int) $user_id;
	return $user_id === 10
		? array('valid' => true, 'account_type' => 'command_center', 'user_id' => 10, 'puskesmas_code' => 'PKM01')
		: array('valid' => $user_id === 201, 'account_type' => 'personal', 'user_id' => $user_id, 'puskesmas_code' => 'PKM01', 'staff_id' => 1);
}
function doclinc_can_coordinate_request($request, $identity) { return $request && !empty($identity['valid']) && ($identity['account_type'] ?? '') === 'command_center' && ($request->assigned_puskesmas_code ?? '') === ($identity['puskesmas_code'] ?? ''); }
function doclinc_nakes_request_access_context($request_id, $identity) { return array('can_handle' => (int) $request_id > 0 && !empty($identity['valid']) && ($identity['account_type'] ?? '') === 'personal'); }
function doclinc_request_handling_nakes_id($request) {
	foreach (array('assigned_nakes_user_id', 'accepted_by_user_id', 'dokter_id') as $field) {
		if (isset($request->{$field}) && (int) $request->{$field} > 0) { return (int) $request->{$field}; }
	}
	return null;
}
function doclinc_notify_user($recipient, $event_type, $entity_type, $entity_id, $title, $message = '', $actor = null) {
	$GLOBALS['controller_notification_calls'][] = array('recipient' => (int) $recipient, 'event_type' => $event_type, 'entity_id' => (int) $entity_id, 'actor' => (int) $actor);
	return $GLOBALS['controller_notification_succeeds'] ? 1 : false;
}
function doclinc_notify_puskesmas($code, $event_type, $entity_type, $entity_id, $title, $message = '', $actor = null) {
	$GLOBALS['controller_notification_calls'][] = array('recipient' => (string) $code, 'event_type' => $event_type, 'entity_id' => (int) $entity_id, 'actor' => (int) $actor);
	return $GLOBALS['controller_notification_succeeds'] ? 1 : 0;
}

require_once APPPATH . 'modules/konsultasi/controllers/Konsultasi.php';
require_once APPPATH . 'modules/home/controllers/Home.php';
require_once APPPATH . 'modules/home_nakes/controllers/Home_nakes.php';
require_once APPPATH . 'modules/konsultasi_nakes/controllers/Konsultasi_nakes.php';

$passed = 0;
$failed = 0;
function controller_expect($condition, $label) {
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; fwrite(STDERR, "FAIL {$label}\n");
}

function controller_fixture_request($request_id, $status = 'Pending') {
	return (object) array(
		'request_id' => $request_id, 'user_id' => 101, 'dokter_id' => 10,
		'request_status' => $status, 'assigned_puskesmas_code' => 'PKM01',
		'assigned_puskesmas_name' => 'Synthetic clinic', 'assigned_nakes_user_id' => 201,
		'accepted_by_user_id' => 10, 'updated_at' => '2026-01-01 00:00:00',
	);
}

function controller_new($class, $operation, $success, array $posts, array $session) {
	$reflection = new ReflectionClass($class);
	$controller = $reflection->newInstanceWithoutConstructor();
	$controller->session = new ControllerSession($session);
	$controller->input = new ControllerInput($posts + array(
		'recipient_user_id' => 999, 'user_id' => 999, 'actor_user_id' => 999,
		'assigned_puskesmas_code' => 'PKM02',
	));
	$controller->output = new ControllerOutput();
	$controller->config = new ControllerConfig();
	$controller->encryption = new ControllerEncryption();
	$controller->db = new ControllerDatabase();
	$controller->load = new ControllerLoader();
	$model = new ControllerModelCollaborator($operation, $success);
	if ($class === 'Konsultasi') { $controller->Konsultasi_m = $model; }
	elseif ($class === 'Home') { $controller->Home_m = $model; }
	elseif ($class === 'Home_nakes') { $controller->Home_nakes_m = $model; }
	else { $controller->Konsultasi_nakes_m = $model; $controller->Home_nakes_m = $model; }
	$GLOBALS['controller_ci_instance'] = $controller;
	return $controller;
}

function controller_scenario_contract($operation) {
	$contracts = array(
		'create' => array('Konsultasi', 'save_konsultasi', 5001, 101, 'requestCreated',
			array('data_penunjang' => 'Synthetic history', 'gejala_utama' => '', 'keluhan' => 'Synthetic complaint', 'alamat' => '', 'lat' => '-6.1', 'lng' => '106.1', 'tanggal' => '2026-01-01'),
			array('id' => 101, 'role' => 'warga'), array('status' => 'success', 'message' => 'Permintaan dikirim.'),
			array('status' => 'error', 'message' => 'Permintaan belum dapat dikirim.'), 200),
		'accept' => array('Home_nakes', 'accept_request', 5002, 10, 'requestAccepted',
			array('id' => 5002, 'latitude' => '', 'longitude' => ''), array('id' => 10, 'role' => 'dokter'),
			array('status' => 'success', 'message' => 'Konsultasi diterima.', 'request_id' => 5002, 'request_status' => 'Accepted', 'redirect_url' => 'https://fixture.invalid/konsultasi_nakes/konsultasi/5002'),
			array('status' => 'error', 'message' => 'Permintaan tidak dapat diakses.'), 200),
		'cancel_warga' => array('Home', 'cancel_request', 5003, 101, 'requestCancelledByOwner',
			array('request_id' => 5003), array('id' => 101, 'role' => 'warga'),
			array('status' => 'success', 'message' => 'Permintaan dibatalkan.', 'request_id' => 5003, 'request_status' => 'Cancelled'),
			array('status' => 'error', 'message' => 'Permintaan tidak dapat dibatalkan.'), 403),
		'cancel_command_center' => array('Home_nakes', 'cancel_request', 5004, 10, 'requestCancelledByCommandCenter',
			array('request_id' => 5004), array('id' => 10, 'role' => 'dokter'),
			array('status' => 'success', 'message' => 'Permintaan dibatalkan.', 'request_id' => 5004, 'request_status' => 'Cancelled'),
			array('status' => 'error', 'message' => 'Permintaan tidak dapat dibatalkan.'), 403),
		'complete' => array('Konsultasi_nakes', 'save_konsultasi_nakes', 5005, 201, 'requestCompleted',
			array('request_id' => 5005, 'diagnosa' => 'Synthetic diagnosis', 'saran' => 'Synthetic recommendation', 'kriteria' => '1', 'rujukan' => '', 'terapi' => '[]'),
			array('id' => 201, 'role' => 'dokter'), array('status' => 'success', 'message' => 'Konsultasi selesai.'),
			array('status' => 'error', 'message' => 'Konsultasi belum dapat diselesaikan.'), 200),
	);
	return $contracts[$operation];
}

function controller_run($operation, $success, $notification_succeeds = true, $care_team_enabled = false) {
	list($class, $action, $request_id, $actor_id, $orchestrator_method, $posts, $session, $success_response, $failure_response, $failure_status) = controller_scenario_contract($operation);
	$GLOBALS['controller_trace'] = array();
	$GLOBALS['controller_notification_calls'] = array();
	$GLOBALS['controller_notification_succeeds'] = (bool) $notification_succeeds;
	$GLOBALS['controller_care_team_workflow_enabled'] = (bool) $care_team_enabled;
	$GLOBALS['controller_visit_proof_required'] = false;
	$GLOBALS['controller_requests'] = $operation === 'create' ? array() : array($request_id => controller_fixture_request($request_id, $operation === 'complete' ? 'Accepted' : 'Pending'));
	$GLOBALS['controller_orchestrator'] = new ControllerOrchestratorProbe();
	$_FILES = array();
	$controller = controller_new($class, $operation, $success, $posts, $session);
	$controller->{$action}();
	return array(
		'controller' => $controller,
		'probe' => $GLOBALS['controller_orchestrator'],
		'trace' => $GLOBALS['controller_trace'],
		'notifications' => $GLOBALS['controller_notification_calls'],
		'request_id' => $request_id,
		'actor_id' => $actor_id,
		'orchestrator_method' => $orchestrator_method,
		'expected_response' => $success ? $success_response : $failure_response,
		'expected_status' => $success ? 200 : $failure_status,
	);
}

function controller_trace_index(array $trace, $value) {
	$index = array_search($value, $trace, true);
	return $index === false ? -1 : $index;
}

$success_scenarios = 0;
$failure_scenarios = 0;
$notification_failure_scenarios = 0;
foreach (array('create','accept','cancel_warga','cancel_command_center','complete') as $operation) {
	$success = controller_run($operation, true);
	$body = json_decode($success['controller']->output->body, true);
	$calls = $success['probe']->calls;
	$trace = $success['trace'];
	controller_expect(count($calls) === 1 && $calls[0]['method'] === $success['orchestrator_method'], $operation . '_success_exact_orchestrator_call');
	controller_expect(controller_trace_index($trace, 'model.begin') >= 0
		&& controller_trace_index($trace, 'model.begin') < controller_trace_index($trace, 'model.success')
		&& controller_trace_index($trace, 'model.success') < controller_trace_index($trace, 'orchestrator.' . $success['orchestrator_method'])
		&& controller_trace_index($trace, 'orchestrator.' . $success['orchestrator_method']) < controller_trace_index($trace, 'response.write'),
		$operation . '_success_ordered_model_orchestrator_response_trace');
	controller_expect($body === $success['expected_response'] && $success['controller']->output->status === 200,
		$operation . '_success_exact_public_response');
	$arguments = $calls[0]['arguments'];
	controller_expect((int) $arguments[0] === $success['request_id']
		&& is_object($arguments[1]) && (int) $arguments[1]->request_id === $success['request_id']
		&& (int) $arguments[2] === $success['actor_id'], $operation . '_persisted_request_and_session_actor_arguments');
	controller_expect((int) $arguments[2] !== 999
		&& (!isset($arguments[1]->user_id) || (int) $arguments[1]->user_id === 101), $operation . '_malicious_recipient_input_ignored');
	controller_expect(count(array_filter($trace, function ($entry) { return $entry === 'response.write'; })) === 1,
		$operation . '_success_single_terminal_response');
	$success_scenarios++;

	$failure = controller_run($operation, false);
	$failure_body = json_decode($failure['controller']->output->body, true);
	controller_expect(count($failure['probe']->calls) === 0, $operation . '_failure_zero_orchestrator_calls');
	controller_expect(controller_trace_index($failure['trace'], 'model.begin') >= 0
		&& controller_trace_index($failure['trace'], 'model.failure') > controller_trace_index($failure['trace'], 'model.begin')
		&& controller_trace_index($failure['trace'], 'response.write') > controller_trace_index($failure['trace'], 'model.failure'),
		$operation . '_failure_ordered_model_response_trace');
	controller_expect($failure_body === $failure['expected_response']
		&& $failure['controller']->output->status === $failure['expected_status'], $operation . '_failure_exact_public_response');
	controller_expect(count($failure['notifications']) === 0, $operation . '_failure_zero_notification_attempt');
	$failure_scenarios++;

	$notification_failure = controller_run($operation, true, false);
	controller_expect(json_decode($notification_failure['controller']->output->body, true) === $notification_failure['expected_response']
		&& count($notification_failure['probe']->calls) === 1
		&& count($notification_failure['notifications']) === 1,
		$operation . '_notification_failure_preserves_legacy_public_success');
	$notification_failure_scenarios++;
}

controller_expect($success_scenarios === 5, 'controller_success_scenario_count_exact');
controller_expect($failure_scenarios === 5, 'controller_failure_scenario_count_exact');
controller_expect($notification_failure_scenarios === 5, 'controller_notification_failure_scenario_count_exact');

$care_team_cancel = controller_run('cancel_warga', true, true, true);
$care_team_cancel_body = json_decode($care_team_cancel['controller']->output->body, true);
$care_team_cancel_notifications = $care_team_cancel['notifications'];
controller_expect(get_instance() === $care_team_cancel['controller']
	&& $care_team_cancel['controller']->config->item('care_team_workflow_enabled') === true,
	'care_team_fake_ci_singleton_and_config_enabled');
controller_expect(count($care_team_cancel['probe']->calls) === 1
	&& $care_team_cancel['probe']->calls[0]['method'] === 'requestCancelledByOwner',
	'care_team_pending_cancel_orchestrates_once');
controller_expect(count($care_team_cancel_notifications) === 1
	&& $care_team_cancel_notifications[0] === array(
		'recipient' => 'PKM01', 'event_type' => 'request_cancelled', 'entity_id' => 5003, 'actor' => 101,
	), 'care_team_pending_cancel_targets_puskesmas_once');
controller_expect($care_team_cancel_notifications[0]['recipient'] !== 'PKM02'
	&& $care_team_cancel_notifications[0]['recipient'] !== 999,
	'care_team_pending_cancel_ignores_malicious_recipient_input');
controller_expect($care_team_cancel_body === $care_team_cancel['expected_response']
	&& $care_team_cancel['controller']->output->status === 200,
	'care_team_pending_cancel_public_response_unchanged');

$care_team_notification_failure = controller_run('cancel_warga', true, false, true);
controller_expect(json_decode($care_team_notification_failure['controller']->output->body, true) === $care_team_notification_failure['expected_response']
	&& $care_team_notification_failure['controller']->output->status === 200
	&& count($care_team_notification_failure['probe']->calls) === 1
	&& count($care_team_notification_failure['notifications']) === 1,
	'care_team_pending_cancel_notification_failure_contract');

$repeated_accept = controller_run('accept', true);
$repeated_accept['controller']->accept_request();
controller_expect(count($repeated_accept['probe']->calls) === 1
	&& count($GLOBALS['controller_notification_calls']) === 1,
	'repeated_accept_does_not_repeat_orchestration_or_notification');
controller_expect($repeated_accept['controller']->output->status === 403
	&& json_decode($repeated_accept['controller']->output->body, true) === array('status' => 'error', 'message' => 'Anda tidak memiliki akses.'),
	'repeated_accept_exact_controller_noop_response');

$proof_required = controller_run('complete', true);
$GLOBALS['controller_visit_proof_required'] = true;
$proof_required['controller']->output->status = 200;
$proof_required['controller']->output->body = '';
$proof_required['controller']->save_konsultasi_nakes();
$proof_body = json_decode($proof_required['controller']->output->body, true);
controller_expect($proof_required['controller']->output->status === 422
	&& ($proof_body['status'] ?? '') === 'error'
	&& strpos((string) ($proof_body['message'] ?? ''), 'Foto bukti kunjungan wajib') === 0,
	'visit_proof_required_missing_file_controller_rejected');
controller_expect(count($proof_required['probe']->calls) === 1
	&& count(array_filter($GLOBALS['controller_trace'], function ($entry) { return $entry === 'model.begin'; })) === 1,
	'visit_proof_controller_gate_blocks_second_model_and_orchestrator_call');

$GLOBALS['controller_trace'] = array();
$GLOBALS['controller_notification_calls'] = array();
$GLOBALS['controller_orchestrator'] = new ControllerOrchestratorProbe();
$GLOBALS['controller_requests'] = array(5005 => controller_fixture_request(5005, 'Accepted'));
$GLOBALS['controller_requests'][5005]->visit_status = 'arrived';
$GLOBALS['controller_visit_proof_required'] = true;
$_FILES = array();
$bypass_controller = controller_new('Konsultasi_nakes', 'complete', true, array(
	'request_id' => 5005,
	'diagnosa' => 'Synthetic diagnosis',
	'saran' => 'Synthetic recommendation',
	'kriteria' => 'Selesai Konsultasi',
	'rujukan' => '',
	'terapi' => '[]',
), array('id' => 201, 'role' => 'dokter'));
$bypass_controller->save_konsultasi_nakes();
$bypass_body = json_decode($bypass_controller->output->body, true);
controller_expect($bypass_controller->output->status === 422
	&& strpos((string) ($bypass_body['message'] ?? ''), 'Kunjungan yang sudah dimulai') === 0,
	'visit_proof_controller_rejects_non_visit_downgrade');
controller_expect(count($GLOBALS['controller_orchestrator']->calls) === 0
	&& count(array_filter($GLOBALS['controller_trace'], function ($entry) { return $entry === 'model.begin'; })) === 0,
	'visit_proof_non_visit_downgrade_blocks_model_and_orchestration');

$GLOBALS['controller_trace'] = array();
$GLOBALS['controller_notification_calls'] = array();
$GLOBALS['controller_orchestrator'] = new ControllerOrchestratorProbe();
$GLOBALS['controller_requests'] = array(5005 => controller_fixture_request(5005, 'Accepted'));
$GLOBALS['controller_visit_proof_required'] = true;
$_FILES = array();
$invalid_mode_controller = controller_new('Konsultasi_nakes', 'complete', true, array(
	'request_id' => 5005,
	'diagnosa' => 'Synthetic diagnosis',
	'saran' => 'Synthetic recommendation',
	'kriteria' => 'arbitrary-mode',
	'rujukan' => '',
	'terapi' => '[]',
), array('id' => 201, 'role' => 'dokter'));
$invalid_mode_controller->save_konsultasi_nakes();
$invalid_mode_body = json_decode($invalid_mode_controller->output->body, true);
controller_expect($invalid_mode_controller->output->status === 422
	&& strpos((string) ($invalid_mode_body['message'] ?? ''), 'Jenis layanan tidak valid') === 0,
	'visit_proof_controller_rejects_unknown_service_mode');
controller_expect(count($GLOBALS['controller_orchestrator']->calls) === 0
	&& count(array_filter($GLOBALS['controller_trace'], function ($entry) { return $entry === 'model.begin'; })) === 0,
	'visit_proof_unknown_mode_blocks_model_and_orchestration');
$GLOBALS['controller_visit_proof_required'] = false;

echo "CONTROLLER_SUCCESS_SCENARIOS={$success_scenarios}\n";
echo "CONTROLLER_FAILURE_SCENARIOS={$failure_scenarios}\n";
echo "CONTROLLER_NOTIFICATION_FAILURE_SCENARIOS={$notification_failure_scenarios}\n";
echo "CONTROLLER_INTEGRATION_UNIQUE_ASSERTIONS=" . ($passed + $failed) . "\n";
echo "REALTIME_REQUEST_CONTROLLER_ORCHESTRATION_INTEGRATION_PASSED={$passed}\n";
echo "REALTIME_REQUEST_CONTROLLER_ORCHESTRATION_INTEGRATION_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
