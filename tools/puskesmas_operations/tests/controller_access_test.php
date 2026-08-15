<?php
if (!defined('BASEPATH')) {
	define('BASEPATH', __DIR__ . '/');
}
if (!defined('APPPATH')) {
	define('APPPATH', dirname(__DIR__, 3) . '/application/');
}

$operations_controller_passed = 0;
$operations_controller_failed = 0;
$operations_prerequisite_state = array('allowed' => true, 'complete' => true);
$operations_prerequisite_calls = array();
$operations_redirects = array();
$operations_identity = array(
	'valid' => true,
	'account_type' => 'command_center',
	'is_command_center' => true,
	'user_id' => 10,
	'puskesmas_code' => 'PKM-A',
);

function operations_controller_expect($condition, $label)
{
	global $operations_controller_passed, $operations_controller_failed;
	if ($condition) {
		$operations_controller_passed++;
		echo "PASS {$label}\n";
		return;
	}
	$operations_controller_failed++;
	echo "FAIL {$label}\n";
}

function doclinc_role_prerequisite_state($user_id = null, $refresh = false)
{
	global $operations_prerequisite_state, $operations_prerequisite_calls;
	$operations_prerequisite_calls[] = array((int) $user_id, (bool) $refresh);
	return $operations_prerequisite_state;
}

function doclinc_nakes_credential_schema_allows_runtime($db)
{
	return true;
}

function doclinc_nakes_password_changed_at_projection($db)
{
	return 'NULL AS password_changed_at';
}

function doclinc_nakes_password_change_blocked($role, $must_change_password, $password_changed_at)
{
	return false;
}

function doclinc_dokter_identity_context($user_id, $refresh = false)
{
	global $operations_identity;
	return $operations_identity;
}

function doclinc_role_prerequisite_error_payload(array $state)
{
	return array('message' => 'Lengkapi profil sebelum melanjutkan.');
}

function redirect($target, $method = '')
{
	global $operations_redirects;
	$operations_redirects[] = (string) $target;
}

class OperationsControllerInput
{
	public $method = 'GET';
	public $query = array();
	public function method($upper = false) { return $upper ? strtoupper($this->method) : strtolower($this->method); }
	public function get($key = null, $xss = false) { return $key === null ? $this->query : ($this->query[$key] ?? null); }
	public function post($key = null, $xss = false) { return null; }
	public function server($key) { return null; }
	public function is_ajax_request() { return false; }
}

class OperationsControllerOutput
{
	public $status = 200;
	public $body = '';
	public function set_content_type($type, $charset = null) { return $this; }
	public function set_header($header) { return $this; }
	public function set_status_header($status) { $this->status = (int) $status; return $this; }
	public function set_output($body) { $this->body = (string) $body; return $this; }
}

class OperationsControllerSession
{
	public $data = array('logged_in' => true, 'id' => 10, 'role' => 'dokter');
	public $flash = array();
	public function userdata($key) { return $this->data[$key] ?? null; }
	public function set_flashdata($key, $value) { $this->flash[$key] = $value; }
}

class OperationsControllerConfig
{
	public function item($key)
	{
		$values = array(
			'puskesmas_operations_enabled' => true,
			'nakes_presence_online_timeout_seconds' => 90,
			'puskesmas_operations_staff_limit' => 200,
			'puskesmas_operations_request_limit' => 200,
		);
		return $values[$key] ?? null;
	}
}

class OperationsControllerQuery
{
	private $row;
	public function __construct($row = null) { $this->row = $row; }
	public function row() { return $this->row; }
}

class OperationsControllerDb
{
	public $user_role = 'dokter';
	public $service_table_checks = array();
	public function table_exists($table)
	{
		if ($table === 'users') {
			return true;
		}
		$this->service_table_checks[] = (string) $table;
		return false;
	}
	public function field_exists($field, $table) { return $table === 'users'; }
	public function select($select, $escape = null) { return $this; }
	public function where($key, $value = null, $escape = null) { return $this; }
	public function limit($limit) { return $this; }
	public function get($table)
	{
		if ($table === 'users') {
			return new OperationsControllerQuery((object) array(
				'userId' => 10,
				'role' => $this->user_role,
				'status' => 'aktif',
				'must_change_password' => 0,
				'password_changed_at' => null,
			));
		}
		return new OperationsControllerQuery();
	}
}

class OperationsControllerLoader
{
	private $owner;
	public function __construct($owner) { $this->owner = $owner; }
	public function database() {}
	public function library($name) {}
	public function helper($helpers) {}
	public function model($name, $alias = null) {}
}

#[AllowDynamicProperties]
class CI_Controller
{
	public function __construct()
	{
		$this->input = new OperationsControllerInput();
		$this->output = new OperationsControllerOutput();
		$this->session = new OperationsControllerSession();
		$this->config = new OperationsControllerConfig();
		$this->db = new OperationsControllerDb();
		$this->load = new OperationsControllerLoader($this);
	}
}

#[AllowDynamicProperties]
class MX_Controller extends CI_Controller
{
}

require_once APPPATH . 'controllers/Puskesmas_operations.php';

function operations_controller_case(array $prerequisite, array $session = array(), array $identity = array())
{
	global $operations_prerequisite_state, $operations_prerequisite_calls, $operations_identity;
	$operations_prerequisite_state = $prerequisite;
	$operations_prerequisite_calls = array();
	$operations_identity = array_replace(array(
		'valid' => true,
		'account_type' => 'command_center',
		'is_command_center' => true,
		'user_id' => 10,
		'puskesmas_code' => 'PKM-A',
	), $identity);
	$controller = new Puskesmas_operations();
	$controller->session->data = array_replace($controller->session->data, $session);
	$controller->db->user_role = (string) ($controller->session->data['role'] ?? '');
	return $controller;
}

$incomplete = operations_controller_case(array('allowed' => true, 'complete' => false));
$incomplete->snapshot();
$incomplete_body = json_decode($incomplete->output->body, true);
operations_controller_expect(
	$incomplete->output->status === 403
	&& ($incomplete_body['safe_error_code'] ?? '') === 'profile_prerequisites_missing'
	&& !isset($incomplete_body['data'])
	&& empty($incomplete->db->service_table_checks)
	&& $operations_prerequisite_calls === array(array(10, true)),
	'incomplete_command_center_snapshot_denied_before_service_with_fresh_state'
);

$incomplete_record = operations_controller_case(array('allowed' => true, 'complete' => false));
$incomplete_record->medical_record(54);
$incomplete_record_body = json_decode($incomplete_record->output->body, true);
operations_controller_expect(
	$incomplete_record->output->status === 403
	&& ($incomplete_record_body['safe_error_code'] ?? '') === 'profile_prerequisites_missing'
	&& !isset($incomplete_record_body['data'])
	&& empty($incomplete_record->db->service_table_checks)
	&& $operations_prerequisite_calls === array(array(10, true)),
	'incomplete_command_center_record_denied_before_service_with_fresh_state'
);

$complete = operations_controller_case(array('allowed' => true, 'complete' => true));
$complete->snapshot();
$complete_body = json_decode($complete->output->body, true);
operations_controller_expect(
	$complete->output->status === 503
	&& ($complete_body['safe_error_code'] ?? '') === 'schema_unavailable'
	&& in_array('m_puskesmas', $complete->db->service_table_checks, true)
	&& $operations_prerequisite_calls === array(array(10, true)),
	'complete_command_center_snapshot_reaches_service_layer'
);

$complete_record = operations_controller_case(array('allowed' => true, 'complete' => true));
$complete_record->medical_record(54);
$complete_record_body = json_decode($complete_record->output->body, true);
operations_controller_expect(
	$complete_record->output->status === 403
	&& ($complete_record_body['safe_error_code'] ?? '') === 'actor_denied'
	&& in_array('medicalrecords', $complete_record->db->service_table_checks, true)
	&& $operations_prerequisite_calls === array(array(10, true)),
	'complete_command_center_record_reaches_service_layer'
);

$personal = operations_controller_case(
	array('allowed' => true, 'complete' => true),
	array(),
	array('account_type' => 'personal', 'is_command_center' => false)
);
$personal->snapshot();
$personal_body = json_decode($personal->output->body, true);
operations_controller_expect(
	$personal->output->status === 403
	&& ($personal_body['safe_error_code'] ?? '') === 'actor_denied'
	&& !isset($personal_body['data']),
	'personal_nakes_denied_with_global_role_gate_off'
);

$admin = operations_controller_case(array('allowed' => true, 'complete' => true), array('role' => 'admin'));
$admin->snapshot();
$admin_body = json_decode($admin->output->body, true);
operations_controller_expect(
	$admin->output->status === 403
	&& ($admin_body['safe_error_code'] ?? '') === 'actor_denied'
	&& !isset($admin_body['data']),
	'admin_denied_with_global_role_gate_off'
);

$anonymous = operations_controller_case(array('allowed' => true, 'complete' => true), array('logged_in' => false));
$anonymous->snapshot();
$anonymous_body = json_decode($anonymous->output->body, true);
operations_controller_expect(
	$anonymous->output->status === 403
	&& ($anonymous_body['safe_error_code'] ?? '') === 'actor_denied'
	&& !isset($anonymous_body['data']),
	'unauthenticated_denied_with_global_role_gate_off'
);

require_once APPPATH . 'modules/home_nakes/controllers/Home_nakes.php';
$operations_prerequisite_state = array('allowed' => true, 'complete' => false);
$operations_prerequisite_calls = array();
$operations_redirects = array();
$operations_identity = array(
	'valid' => true,
	'account_type' => 'command_center',
	'is_command_center' => true,
	'user_id' => 10,
	'puskesmas_code' => 'PKM-A',
);
$operations_page = new Home_nakes();
$operations_page->operations();
operations_controller_expect(
	$operations_redirects === array('home_nakes#profile')
	&& $operations_prerequisite_calls === array(array(10, true)),
	'incomplete_command_center_page_denied_by_fresh_local_gate'
);

echo "PUSKESMAS_OPERATIONS_CONTROLLER_PASSED={$operations_controller_passed}\n";
echo "PUSKESMAS_OPERATIONS_CONTROLLER_FAILED={$operations_controller_failed}\n";
exit($operations_controller_failed > 0 ? 1 : 0);
