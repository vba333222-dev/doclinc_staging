<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 3) . '/'); }
if (!defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }
require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';
require_once APPPATH . 'libraries/Request_realtime_delivery.php';
require_once APPPATH . 'libraries/Request_transition_orchestrator.php';
require_once APPPATH . 'helpers/visit_routing_helper.php';
require_once APPPATH . 'helpers/visit_proof_helper.php';
require_once APPPATH . 'helpers/nakes_credential_enforcement_helper.php';
require_once __DIR__ . '/MariaDbReadiness.php';

class MX_Controller { public $config; }

class ModelIntegrationConfig
{
	private $items = array();

	public function item($key)
	{
		return array_key_exists($key, $this->items) ? $this->items[$key] : null;
	}

	public function set($key, $value)
	{
		$this->items[$key] = $value;
	}
}

class ModelIntegrationLoader
{
	public function helper($name)
	{
		return $name !== '';
	}
}

class ModelIntegrationApplication
{
	public $db;
	public $config;
	public $load;

	public function __construct()
	{
		$this->config = new ModelIntegrationConfig();
		$this->load = new ModelIntegrationLoader();
	}
}

$GLOBALS['request_model_application'] = new ModelIntegrationApplication();

function &get_instance()
{
	return $GLOBALS['request_model_application'];
}

function base_url($path = '')
{
	return 'https://fixture.invalid/' . ltrim((string) $path, '/');
}

$GLOBALS['request_model_realtime_enabled'] = true;
$GLOBALS['request_model_notification_enabled'] = true;
$GLOBALS['request_model_identity'] = array();
$GLOBALS['request_model_direct_publish_count'] = 0;
$GLOBALS['request_model_orchestrator_calls'] = 0;

function doclinc_realtime_requests_enabled()
{
	return $GLOBALS['request_model_realtime_enabled'] === true;
}

function doclinc_request_realtime_delivery($db)
{
	return new Request_realtime_delivery(
		$db,
		array('enabled' => $GLOBALS['request_model_realtime_enabled'] === true),
		array('enabled' => $GLOBALS['request_model_notification_enabled'] === true)
	);
}

function doclinc_dokter_identity_context($user_id = null, $refresh = false)
{
	$user_id = (int) $user_id;
	return $GLOBALS['request_model_identity'][$user_id] ?? array('valid' => false, 'user_id' => $user_id);
}

function doclinc_can_coordinate_request($request, $identity)
{
	return is_object($request)
		&& !empty($identity['valid'])
		&& ($identity['account_type'] ?? '') === 'command_center'
		&& trim((string) ($request->assigned_puskesmas_code ?? '')) === trim((string) ($identity['puskesmas_code'] ?? ''));
}

function doclinc_nakes_request_access_context($request_id, $identity)
{
	return array(
		'valid' => !empty($identity['valid']),
		'can_view' => !empty($identity['valid']),
		'can_handle' => !empty($identity['valid']) && ($identity['account_type'] ?? '') === 'personal',
		'tenant_match' => !empty($identity['valid']),
	);
}

function doclinc_request_row($request_id)
{
	$CI = &get_instance();
	return (int) $request_id > 0
		? $CI->db->where('request_id', (int) $request_id)->get('requests')->row()
		: null;
}

function doclinc_request_handling_nakes_id($request)
{
	if (!$request) { return null; }
	foreach (array('assigned_nakes_user_id', 'accepted_by_user_id', 'dokter_id') as $field) {
		if (isset($request->{$field}) && (int) $request->{$field} > 0) {
			return (int) $request->{$field};
		}
	}
	return null;
}

function doclinc_can_view_request_notification($request_id, $identity = null)
{
	$request = doclinc_request_row($request_id);
	if (!$request || !is_array($identity) || empty($identity['valid'])) { return false; }
	if (trim((string) ($request->assigned_puskesmas_code ?? '')) !== trim((string) ($identity['puskesmas_code'] ?? ''))) {
		return false;
	}
	if (($identity['account_type'] ?? '') === 'command_center') { return true; }
	return ($identity['account_type'] ?? '') === 'personal'
		&& (int) doclinc_request_handling_nakes_id($request) === (int) ($identity['user_id'] ?? 0);
}

require_once APPPATH . 'modules/home/models/Home_m.php';
require_once APPPATH . 'modules/home_nakes/models/Home_nakes_m.php';
require_once APPPATH . 'modules/konsultasi/models/Konsultasi_m.php';
require_once APPPATH . 'modules/konsultasi_nakes/models/Konsultasi_nakes_m.php';
require_once APPPATH . 'helpers/notification_helper.php';

// CI3's mysqli driver contract reports a failed query as FALSE and marks the
// transaction failed. Keep mysqli error reporting and MariaDB strict SQL mode,
// but do not convert the suite's intentional fault-injection queries to PHP
// exceptions before CI3 can exercise that contract.
mysqli_report(MYSQLI_REPORT_ERROR);
$passed = 0;
$failed = 0;
$database_name = '';
$admin = null;
$db = null;
$stage = 'bootstrap';
$visit_proof_temp_directories = array();

function model_integration_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; fwrite(STDERR, "FAIL {$label}\n");
}

function model_integration_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function model_integration_identifier($value)
{
	if (preg_match('/\A[A-Za-z0-9_]+\z/D', $value) !== 1) { throw new InvalidArgumentException('identifier_invalid'); }
	return '`' . $value . '`';
}

function model_integration_model($class, $db)
{
	$reflection = new ReflectionClass($class);
	$model = $reflection->newInstanceWithoutConstructor();
	if ($reflection->hasProperty('db')) {
		$property = $reflection->getProperty('db');
		$property->setAccessible(true);
		$property->setValue($model, $db);
	} else {
		$model->db = $db;
	}
	$model->config = $GLOBALS['request_model_application']->config;
	return $model;
}

function model_integration_count($db, $table, $where = '')
{
	$row = $db->query('SELECT COUNT(1) AS total FROM ' . model_integration_identifier($table) . ($where !== '' ? ' WHERE ' . $where : ''))->row();
	return (int) $row->total;
}

function model_integration_digest($db, $request_id)
{
	$request = $db->query('SELECT request_status, dokter_id, assigned_nakes_user_id, accepted_by_user_id, assigned_nakes_by_user_id FROM requests WHERE request_id = ?', array($request_id))->row_array();
	$assignments = $db->query('SELECT assignment_id, staff_id, status, ended_at FROM request_staff_assignments WHERE request_id = ? ORDER BY assignment_id', array($request_id))->result_array();
	$notifications = $db->query('SELECT notification_id, recipient_user_id, recipient_role, recipient_puskesmas_code, actor_user_id, event_type, entity_type, entity_id, title, message, is_read, created_at FROM notifications ORDER BY notification_id')->result_array();
	$outbox = $db->query('SELECT outbox_id, event_type, aggregate_type, aggregate_id, audience_type, audience_key, payload_json, event_version, idempotency_key, state, attempt_count, available_at, claimed_at, published_at, last_error_code, created_at, updated_at FROM realtime_outbox ORDER BY outbox_id')->result_array();
	return hash('sha256', json_encode(array(
		'request' => $request,
		'assignments' => $assignments,
		'notifications' => $notifications,
		'outbox' => $outbox,
	), JSON_UNESCAPED_SLASHES));
}

function model_integration_full_digest($db)
{
	$queries = array(
		'requests' => 'SELECT request_id, user_id, dokter_id, request_status, assigned_puskesmas_code, assigned_nakes_user_id, accepted_by_user_id, assigned_nakes_by_user_id FROM requests ORDER BY request_id',
		'assignments' => 'SELECT assignment_id, request_id, staff_id, kode_pkm, assigned_by_user_id, status, note, assigned_at, ended_at, created_at, updated_at FROM request_staff_assignments ORDER BY assignment_id',
		'medicalrecords' => 'SELECT record_id, request_id, diagnosis, treatment, recommendations, anamnesis, created_at FROM medicalrecords ORDER BY record_id',
		'diagnoses' => 'SELECT diagnosis_id, medicalrecord_id, request_id, position, diagnosis_role, diagnosis_code, diagnosis_label, display_text, suggestion_term_id, reference_source, reference_version, created_by_user_id FROM medicalrecord_diagnoses ORDER BY diagnosis_id',
		'konsultasi' => 'SELECT konsul_id, request_id, diagnosa, saran, kriteria, rujukan, foto, create_date, create_user FROM konsultasi ORDER BY konsul_id',
		'terapi' => 'SELECT terapi_id, konsul_id, terapi, signa, keterangan, create_date, create_user FROM terapi ORDER BY terapi_id',
		'history' => 'SELECT id, idUser, riwayat, created_at, updated_at FROM tbl_riwayat ORDER BY id',
		'notifications' => 'SELECT notification_id, recipient_user_id, recipient_role, recipient_puskesmas_code, actor_user_id, event_type, entity_type, entity_id, title, message, is_read, created_at FROM notifications ORDER BY notification_id',
		'outbox' => 'SELECT outbox_id, event_type, aggregate_type, aggregate_id, audience_type, audience_key, payload_json, event_version, idempotency_key, state, attempt_count, available_at, claimed_at, published_at, last_error_code, created_at, updated_at FROM realtime_outbox ORDER BY outbox_id',
		'events' => 'SELECT event_id, request_id, event_type, puskesmas_code, actor_user_id, actor_staff_id, actor_role, message, metadata_json, created_at FROM request_events ORDER BY event_id',
		'visit_media' => 'SELECT media_id, request_id, medicalrecord_id, uploaded_by_user_id, media_type, storage_key, mime_type, size_bytes, sha256, lifecycle_state, associated_at, finalized_at, failed_at, failure_code FROM consultation_visit_media ORDER BY media_id',
		'visit_locations' => 'SELECT location_update_id, request_id, nakes_user_id, latitude, longitude, accuracy_m, client_sequence, idempotency_key, captured_at, received_at FROM visit_location_updates ORDER BY location_update_id',
	);
	$state = array();
	foreach ($queries as $key => $sql) { $state[$key] = $db->query($sql)->result_array(); }
	return hash('sha256', json_encode($state, JSON_UNESCAPED_SLASHES));
}

function model_integration_in_transaction($db)
{
	$row = $db->query('SELECT @@in_transaction AS active_transaction')->row();
	return $row && (int) $row->active_transaction === 1;
}

function model_integration_reset_fixture($db)
{
	$db->query('DROP TRIGGER IF EXISTS fail_realtime_outbox');
	$db->query('DROP TRIGGER IF EXISTS fail_medicalrecord_diagnosis');
	foreach (array('terapi','konsultasi','consultation_visit_media','visit_location_updates','medicalrecord_diagnoses','medicalrecords','request_events','request_staff_assignments','notifications','realtime_outbox','requests','tbl_riwayat','puskesmas_staff','users','m_puskesmas') as $table) {
		$db->query('DELETE FROM ' . model_integration_identifier($table));
	}
	$db->query("INSERT INTO users(userId,password,role,status,remark,must_change_password) VALUES (10,'unchanged-command','dokter','aktif','PKM01',0),(101,'unchanged-owner','warga','aktif',NULL,0),(201,'unchanged-personal-one','dokter','aktif','PKM01',0),(202,'unchanged-personal-two','dokter','aktif','PKM01',0)");
	$db->query("INSERT INTO m_puskesmas(kode_pkm,nama_puskesmas,status) VALUES ('PKM01','Synthetic clinic','aktif'),('PKM02','Synthetic alternate','aktif')");
	$db->query("INSERT INTO puskesmas_staff(staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (1,'PKM01',201,'Synthetic staff one','Dokter','aktif'),(2,'PKM01',202,'Synthetic staff two','Dokter','aktif')");
	$GLOBALS['request_model_identity'] = array(
		10 => array('valid' => true, 'account_type' => 'command_center', 'user_id' => 10, 'role' => 'dokter', 'user_status' => 'aktif', 'puskesmas_code' => 'PKM01'),
		201 => array('valid' => true, 'account_type' => 'personal', 'user_id' => 201, 'role' => 'dokter', 'user_status' => 'aktif', 'puskesmas_code' => 'PKM01', 'staff_id' => 1),
		202 => array('valid' => true, 'account_type' => 'personal', 'user_id' => 202, 'role' => 'dokter', 'user_status' => 'aktif', 'puskesmas_code' => 'PKM01', 'staff_id' => 2),
	);
	$GLOBALS['request_model_realtime_enabled'] = true;
	$GLOBALS['request_model_notification_enabled'] = true;
}

function model_integration_prepare_operation($operation, $db)
{
	model_integration_reset_fixture($db);
	if ($operation === 'create') { return array('request_id' => 0); }
	$status = in_array($operation, array('complete','assign','reassign','clear'), true) ? 'Accepted' : 'Pending';
	$handler = $operation === 'complete' ? 201 : 10;
	$request_id = model_integration_insert_request($db, $status, 101, $handler);
	if (in_array($operation, array('complete','reassign','clear'), true)) {
		$db->query('UPDATE requests SET assigned_nakes_user_id=201, accepted_by_user_id=10, assigned_nakes_by_user_id=10 WHERE request_id=?', array($request_id));
		$db->query("INSERT INTO request_staff_assignments(request_id,staff_id,kode_pkm,assigned_by_user_id,status) VALUES(? ,1,'PKM01',10,'aktif')", array($request_id));
	}
	return array('request_id' => $request_id);
}

function model_integration_invoke_operation($operation, array $context, array $models)
{
	$request_id = (int) $context['request_id'];
	$command_identity = $GLOBALS['request_model_identity'][10];
	if ($operation === 'create') {
		return $models['create']->save_konsultasi(101, 10, 'Synthetic history', 'Synthetic request', 'Synthetic location', '', '', '2026-01-01', null, null, array('assigned_puskesmas_code' => 'PKM01', 'assigned_puskesmas_name' => 'Synthetic clinic'));
	}
	if ($operation === 'accept') { return $models['nakes']->accept_request($request_id, 10, '', '', 'PKM01', $command_identity); }
	if ($operation === 'cancel_warga') { return $models['home']->cancel_request($request_id, 101); }
	if ($operation === 'cancel_command_center') { return $models['nakes']->cancel_request($request_id, 10, 'PKM01', $command_identity); }
	if ($operation === 'complete') {
		return $models['complete']->save_konsultasi_nakes($request_id, 'Synthetic', 'Synthetic', '0', '', null, array(), 201, $GLOBALS['request_model_identity'][201], null, false);
	}
	if ($operation === 'assign') { return $models['nakes']->assign_staff_to_request($request_id, 1, 'PKM01', 10, '', $command_identity); }
	if ($operation === 'reassign') { return $models['nakes']->assign_staff_to_request($request_id, 2, 'PKM01', 10, '', $command_identity); }
	if ($operation === 'clear') { return $models['nakes']->clear_staff_assignment($request_id, 'PKM01', 10, $command_identity); }
	throw new InvalidArgumentException('operation_unknown');
}

function model_integration_invoke_failed_operation($operation, array $context, array $models, $db)
{
	$request_id = (int) $context['request_id'];
	$command_identity = $GLOBALS['request_model_identity'][10];
	if ($operation === 'create') {
		return $models['create']->save_konsultasi(0, 10, '', '', '', '', '', '2026-01-01', null, null, array('assigned_puskesmas_code' => 'PKM01'));
	}
	if ($operation === 'accept') {
		$db->where('request_id', $request_id)->update('requests', array('request_status' => 'Accepted'));
		return $models['nakes']->accept_request($request_id, 10, '', '', 'PKM01', $command_identity);
	}
	if ($operation === 'cancel_warga') {
		return $models['home']->cancel_request($request_id, 999);
	}
	if ($operation === 'cancel_command_center') {
		$db->where('request_id', $request_id)->update('requests', array('request_status' => 'Accepted'));
		return $models['nakes']->cancel_request($request_id, 10, 'PKM01', $command_identity);
	}
	if ($operation === 'complete') {
		$db->where('request_id', $request_id)->update('requests', array('request_status' => 'Pending'));
		return $models['complete']->save_konsultasi_nakes($request_id, 'Synthetic', 'Synthetic', '0', '', null, array(), 201, $GLOBALS['request_model_identity'][201], null, false);
	}
	throw new InvalidArgumentException('failed_operation_unknown');
}

function model_integration_operation_succeeded($operation, $result)
{
	if ($operation === 'create') { return is_int($result) && $result > 0; }
	if (in_array($operation, array('cancel_warga','complete'), true)) { return $result === true; }
	return is_array($result) && ($result['status'] ?? '') === 'success';
}

function model_integration_operation_failed($operation, $result)
{
	if (in_array($operation, array('create','cancel_warga','complete'), true)) { return $result === false; }
	return is_array($result) && ($result['status'] ?? '') === 'error';
}

function model_integration_install_outbox_fault($db, $fail_after)
{
	$db->query('SET @outbox_attempts = 0, @outbox_fail_after = ?', array((int) $fail_after));
	$db->query("CREATE TRIGGER fail_realtime_outbox BEFORE INSERT ON realtime_outbox FOR EACH ROW BEGIN SET @outbox_attempts = COALESCE(@outbox_attempts, 0) + 1; IF @outbox_attempts >= @outbox_fail_after THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic_outbox_failure'; END IF; END");
}

function model_integration_outbox_attempts($db)
{
	$row = $db->query('SELECT COALESCE(@outbox_attempts, 0) AS attempts')->row();
	return (int) $row->attempts;
}

function model_integration_insert_request($db, $status, $owner = 101, $handler = 10)
{
	$db->insert('requests', array(
		'user_id' => $owner,
		'dokter_id' => $handler,
		'request_description' => 'Synthetic request',
		'request_status' => $status,
		'location' => 'Synthetic location',
		'lattitude' => '',
		'longitude' => '',
		'assigned_puskesmas_code' => 'PKM01',
		'assigned_puskesmas_name' => 'Synthetic clinic',
		'assigned_nakes_user_id' => $handler,
		'accepted_by_user_id' => $status === 'Pending' ? null : 10,
		'assigned_nakes_by_user_id' => $status === 'Pending' ? null : 10,
		'created_at' => '2026-01-01 00:00:00',
		'updated_at' => '2026-01-01 00:00:00',
	));
	return (int) $db->insert_id();
}

function model_integration_prepare_visit_request($db, $visit_status = 'arrived')
{
	$request_id = model_integration_insert_request($db, 'Accepted', 101, 201);
	$db->where('request_id', $request_id)->update('requests', array(
		'assigned_nakes_user_id' => 201,
		'accepted_by_user_id' => 10,
		'assigned_nakes_by_user_id' => 10,
		'visit_status' => $visit_status,
		'patient_latitude' => -6.0020000,
		'patient_longitude' => 106.0020000,
		'lattitude' => '-6.0020000',
		'longitude' => '106.0020000',
	));
	$db->query("INSERT INTO request_staff_assignments(request_id,staff_id,kode_pkm,assigned_by_user_id,status) VALUES(? ,1,'PKM01',10,'aktif')", array($request_id));
	return $request_id;
}

function model_integration_create_visit_proof($db, $request_id, $user_id = 201)
{
	global $visit_proof_temp_directories;
	$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'doclinc_visit_proof_' . bin2hex(random_bytes(6));
	if (!mkdir($directory, 0700, true)) { throw new RuntimeException('visit_proof_temp_directory_failed'); }
	$visit_proof_temp_directories[] = $directory;
	$storage_key = hash('sha256', 'visit-proof-' . $request_id . '-' . bin2hex(random_bytes(8)));
	$file_name = $storage_key . '.jpg';
	$full_path = $directory . DIRECTORY_SEPARATOR . $file_name;
	$file_bytes = "synthetic-visit-proof-{$request_id}";
	if (file_put_contents($full_path, $file_bytes) !== strlen($file_bytes)) { throw new RuntimeException('visit_proof_temp_file_failed'); }
	$sha256 = hash_file('sha256', $full_path);
	$db->insert('consultation_visit_media', array(
		'request_id' => $request_id,
		'medicalrecord_id' => null,
		'uploaded_by_user_id' => $user_id,
		'media_type' => 'image',
		'storage_key' => $storage_key,
		'mime_type' => 'image/jpeg',
		'size_bytes' => filesize($full_path),
		'sha256' => $sha256,
		'lifecycle_state' => 'pending',
	));
	$media_id = (int) $db->insert_id();
	$captured_at_ms = (int) round(microtime(true) * 1000);
	return array(
		'media_id' => $media_id,
		'request_id' => $request_id,
		'uploaded_by_user_id' => $user_id,
		'storage_key' => $storage_key,
		'file_name' => $file_name,
		'full_path' => $full_path,
		'mime_type' => 'image/jpeg',
		'size_bytes' => filesize($full_path),
		'sha256' => $sha256,
		'location' => array(
			'latitude' => -6.0020000,
			'longitude' => 106.0020000,
			'accuracy_m' => 12.5,
			'captured_at_ms' => $captured_at_ms,
		),
	);
}

function model_integration_event_audiences($db, $event_type, $request_id)
{
	$rows = $db->query('SELECT audience_type, audience_key FROM realtime_outbox WHERE event_type = ? AND aggregate_id = ? ORDER BY audience_type, audience_key', array($event_type, (string) $request_id))->result_array();
	return array_map(function ($row) { return $row['audience_key']; }, $rows);
}

function model_integration_outbox_rows($db, $request_id)
{
	return $db->query(
		'SELECT outbox_id,event_type,aggregate_type,aggregate_id,audience_key,payload_json,idempotency_key FROM realtime_outbox WHERE aggregate_id = ? OR (event_type = ? AND aggregate_id IN (SELECT CAST(notification_id AS CHAR) FROM notifications WHERE entity_type = ? AND entity_id = ?)) ORDER BY outbox_id',
		array((string) $request_id, 'notification.created', 'request', (string) $request_id)
	)->result_array();
}

function model_integration_notification_rows($db, $request_id)
{
	return $db->query(
		'SELECT notification_id,recipient_user_id,recipient_role,recipient_puskesmas_code,actor_user_id,event_type,entity_type,entity_id,is_read,SHA2(title,256) AS title_digest,SHA2(message,256) AS message_digest FROM notifications WHERE entity_type = ? AND entity_id = ? ORDER BY notification_id',
		array('request', (string) $request_id)
	)->result_array();
}

function model_integration_orchestrate($operation, array $context, $result, $db)
{
	$GLOBALS['request_model_orchestrator_calls']++;
	$orchestrator = new Request_transition_orchestrator();
	$request_id = $operation === 'create' ? (int) $result : (int) $context['request_id'];
	$request = $request_id > 0 ? $db->where('request_id', $request_id)->get('requests')->row() : null;
	if ($operation === 'create') {
		return $orchestrator->requestCreated($result, $request, 101);
	}
	if ($operation === 'accept') {
		return $orchestrator->requestAccepted($request_id, $request, 10, is_array($result) ? $result : array());
	}
	if ($operation === 'cancel_warga') {
		return $orchestrator->requestCancelledByOwner($request_id, $request, 101, $result);
	}
	if ($operation === 'cancel_command_center') {
		return $orchestrator->requestCancelledByCommandCenter($request_id, $request, 10, is_array($result) ? $result : array());
	}
	if ($operation === 'complete') {
		return $orchestrator->requestCompleted($request_id, $request, 201, $result);
	}
	return array('response' => $result, 'notification_result' => null);
}

function model_integration_expected_response($operation, $request_id)
{
	if ($operation === 'create') { return array('status' => 'success', 'message' => 'Permintaan dikirim.'); }
	if ($operation === 'accept') {
		return array('status' => 'success', 'message' => 'Konsultasi diterima.', 'request_id' => $request_id,
			'request_status' => 'Accepted', 'redirect_url' => 'https://fixture.invalid/konsultasi_nakes/konsultasi/' . $request_id);
	}
	if ($operation === 'cancel_warga') {
		return array('status' => 'success', 'message' => 'Permintaan dibatalkan.', 'request_id' => $request_id, 'request_status' => 'Cancelled');
	}
	if ($operation === 'cancel_command_center') {
		return array('status' => 'success', 'message' => 'Permintaan dibatalkan.', 'request_id' => $request_id, 'request_status' => 'Cancelled');
	}
	if ($operation === 'complete') { return array('status' => 'success', 'message' => 'Konsultasi selesai.'); }
	if ($operation === 'assign' || $operation === 'reassign') { return array('status' => 'success', 'message' => 'Penanggung jawab diperbarui.'); }
	if ($operation === 'clear') { return array('status' => 'success', 'message' => 'Penugasan dihapus.'); }
	throw new InvalidArgumentException('expected_response_unknown');
}

function model_integration_expected_notification($operation, $request_id)
{
	$map = array(
		'create' => array(10, 'dokter', 'PKM01', 101, 'request_created', '90144a6089a70d19eeecaffa3bfb64846b510f73a0fd4dd5c75e4d9f9e015339', '5de46d6d16d8f3a9253e327e656aef805f69daad49339639c995fa9c9d9de2db'),
		'accept' => array(101, 'warga', null, 10, 'request_accepted', '7c9a16743eb83ea6d145fe1d661e0a612afc29517d12040d1c91317ce9d544a3', 'b29d4d4c82b36bfaa8aaf0fdcca853aeb958121ce6d00af771fcc3a31ef10c3f'),
		'cancel_warga' => array(10, 'dokter', null, 101, 'request_cancelled', 'd33b573abf65115c4a79c177d58909096e1c15c76b09b2aa3279479fb407b4d8', 'aeb3c3c8aba6056b286ce4cc4ca50ccb6839ea75d3e6fbe4d734430a9b288d6d'),
		'cancel_command_center' => array(101, 'warga', null, 10, 'request_cancelled', 'd33b573abf65115c4a79c177d58909096e1c15c76b09b2aa3279479fb407b4d8', '4f0edf9c69203ea9f32c2939a911042f32e6f5f5bde84870887a923fba88c12f'),
		'complete' => array(101, 'warga', null, 201, 'consultation_completed', 'd20df7e1246fee6184546df7a12adc5c3ba2fffbd3222c77f69c8047d0d896bd', '5dcd2045264e1c0617efaacf5391627303d5b78849834aa876d12094a1dae517'),
	);
	if (!isset($map[$operation])) { return null; }
	return array(
		'recipient_user_id' => $map[$operation][0], 'recipient_role' => $map[$operation][1],
		'recipient_puskesmas_code' => $map[$operation][2], 'actor_user_id' => $map[$operation][3],
		'event_type' => $map[$operation][4], 'entity_type' => 'request', 'entity_id' => (string) $request_id, 'is_read' => 0,
		'title_digest' => $map[$operation][5], 'message_digest' => $map[$operation][6],
	);
}

function model_integration_assert_domain_post_state($operation, $request_id, $db)
{
	$request = $db->where('request_id', $request_id)->get('requests')->row_array();
	if ($operation === 'create') {
		return $request && $request['request_status'] === 'Pending' && (int) $request['user_id'] === 101
			&& (int) $request['dokter_id'] === 10 && $request['assigned_puskesmas_code'] === 'PKM01';
	}
	if ($operation === 'accept') {
		return $request && $request['request_status'] === 'Accepted' && (int) $request['dokter_id'] === 10
			&& (int) $request['assigned_nakes_user_id'] === 10 && (int) $request['accepted_by_user_id'] === 10;
	}
	if ($operation === 'cancel_warga' || $operation === 'cancel_command_center') {
		return $request && $request['request_status'] === 'Cancelled';
	}
	if ($operation === 'complete') {
		return $request && $request['request_status'] === 'Completed'
			&& model_integration_count($db, 'medicalrecords', 'request_id=' . (int) $request_id) === 1
			&& model_integration_count($db, 'konsultasi', 'request_id=' . (int) $request_id) === 1;
	}
	$assignments = $db->query('SELECT staff_id,status FROM request_staff_assignments WHERE request_id=? ORDER BY assignment_id', array($request_id))->result_array();
	if ($operation === 'assign') {
		return count($assignments) === 1 && (int) $assignments[0]['staff_id'] === 1 && $assignments[0]['status'] === 'aktif'
			&& (int) $request['assigned_nakes_user_id'] === 201;
	}
	if ($operation === 'reassign') {
		return count($assignments) === 2 && (int) $assignments[0]['staff_id'] === 1 && $assignments[0]['status'] === 'diganti'
			&& (int) $assignments[1]['staff_id'] === 2 && $assignments[1]['status'] === 'aktif'
			&& (int) $request['assigned_nakes_user_id'] === 202;
	}
	if ($operation === 'clear') {
		return count($assignments) === 1 && (int) $assignments[0]['staff_id'] === 1 && $assignments[0]['status'] === 'dibatalkan'
			&& (int) $request['assigned_nakes_user_id'] === 10;
	}
	return false;
}

$password = model_integration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($password === '') { fwrite(STDERR, "REALTIME_REQUEST_MODEL_INTEGRATION=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n"); exit(2); }

try {
	$host = model_integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1');
	$port = (int) model_integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306');
	$user = model_integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root');
	$stage = 'admin_readiness';
	$readiness = MariaDbReadiness::wait(array(
		'host' => $host, 'user' => $user, 'password' => $password, 'port' => $port,
		'timeout_ms' => (int) model_integration_env('DOCLINC_TEST_DB_READY_TIMEOUT_MS', '45000'),
		'interval_ms' => (int) model_integration_env('DOCLINC_TEST_DB_READY_INTERVAL_MS', '500'),
	));
	$admin = $readiness->connection;
	$database_name = 'doclinc_request_model_test_' . bin2hex(random_bytes(5));
	$admin->query('CREATE DATABASE ' . model_integration_identifier($database_name) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$db = DB(array(
		'dsn' => '', 'hostname' => $host, 'username' => $user, 'password' => $password,
		'database' => $database_name, 'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false,
		'db_debug' => false, 'cache_on' => false, 'cachedir' => '', 'char_set' => 'utf8mb4',
		'dbcollat' => 'utf8mb4_unicode_ci', 'swap_pre' => '', 'encrypt' => false,
		'compress' => false, 'stricton' => true, 'failover' => array(), 'save_queries' => true,
		'port' => $port,
	), true);
	$GLOBALS['request_model_application']->db = $db;
	$GLOBALS['request_model_application']->config->set('realtime_client_enabled', true);
	$GLOBALS['request_model_application']->config->set('realtime_notifications_enabled', true);
	$GLOBALS['request_model_application']->config->set('nakes_presence_enabled', true);
	$GLOBALS['request_model_application']->config->set('nakes_presence_online_timeout_seconds', 90);
	$GLOBALS['request_model_application']->config->set('care_team_workflow_enabled', false);
	$GLOBALS['request_model_application']->config->set('visit_proof_location_max_age_seconds', 120);
	$GLOBALS['request_model_application']->config->set('visit_location_max_accuracy_meters', 100);
	$GLOBALS['request_model_application']->config->set('visit_arrival_radius_meters', 75);

	$ddl = array(
		"CREATE TABLE users (userId int NOT NULL AUTO_INCREMENT,password varchar(100) NOT NULL,role enum('admin','dokter','warga','') NOT NULL,status enum('aktif','nonaktif') NULL,remark varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL,must_change_password tinyint(1) NOT NULL DEFAULT 0,PRIMARY KEY(userId)) ENGINE=InnoDB",
		"CREATE TABLE m_puskesmas (kode_pkm varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,nama_puskesmas varchar(150) NULL,status enum('aktif','nonaktif') NOT NULL,PRIMARY KEY(kode_pkm)) ENGINE=InnoDB",
		"CREATE TABLE puskesmas_staff (staff_id int(10) unsigned NOT NULL AUTO_INCREMENT,kode_pkm varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,user_id int NULL,nama varchar(150) NOT NULL,no_hp varchar(30) NULL,profesi varchar(100) NULL,nomor_sip varchar(100) NULL,status enum('aktif','nonaktif') NOT NULL,PRIMARY KEY(staff_id)) ENGINE=InnoDB",
		"CREATE TABLE nakes_presence (user_id int NOT NULL,puskesmas_code varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,last_seen_at datetime(6) NOT NULL,PRIMARY KEY(user_id),KEY idx_presence_tenant(puskesmas_code,last_seen_at,user_id)) ENGINE=InnoDB",
		"CREATE TABLE requests (request_id int NOT NULL AUTO_INCREMENT,user_id int NOT NULL,dokter_id int NOT NULL,request_description text NULL,request_status enum('Pending','Accepted','Completed','Cancelled') NOT NULL DEFAULT 'Pending',location text NULL,lattitude varchar(50) NULL,longitude varchar(50) NULL,patient_latitude decimal(10,7) NULL,patient_longitude decimal(10,7) NULL,lattitude_dokter varchar(100) NULL,longitude_dokter varchar(100) NULL,assigned_puskesmas_code varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL,assigned_puskesmas_name varchar(150) NULL,assigned_nakes_user_id int NULL,accepted_by_user_id int NULL,assigned_nakes_by_user_id int NULL,visit_status varchar(30) NULL,consultation_mode varchar(30) NULL,visit_completed_at datetime NULL,created_at datetime NULL,updated_at datetime NULL,PRIMARY KEY(request_id)) ENGINE=InnoDB",
		"CREATE TABLE request_staff_assignments (assignment_id int(10) unsigned NOT NULL AUTO_INCREMENT,request_id int NOT NULL,staff_id int(10) unsigned NOT NULL,kode_pkm varchar(100) NOT NULL,assigned_by_user_id int NOT NULL,status enum('aktif','diganti','dibatalkan') NOT NULL DEFAULT 'aktif',note text NULL,assigned_at datetime NOT NULL DEFAULT current_timestamp(),ended_at datetime NULL,created_at datetime NOT NULL DEFAULT current_timestamp(),updated_at datetime NULL DEFAULT NULL ON UPDATE current_timestamp(),PRIMARY KEY(assignment_id),KEY idx_request_status(request_id,status)) ENGINE=InnoDB",
		"CREATE TABLE notifications (notification_id int NOT NULL AUTO_INCREMENT,recipient_user_id int NULL,recipient_role varchar(32) NULL,recipient_puskesmas_code varchar(100) NULL,actor_user_id int NULL,event_type varchar(64) NOT NULL,entity_type varchar(64) NOT NULL,entity_id varchar(64) NOT NULL,title varchar(160) NOT NULL,message text NULL,is_read tinyint(1) NOT NULL DEFAULT 0,created_at datetime NOT NULL,PRIMARY KEY(notification_id)) ENGINE=InnoDB",
		"CREATE TABLE realtime_outbox (outbox_id bigint unsigned NOT NULL AUTO_INCREMENT,event_type varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,aggregate_type varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,aggregate_id varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,audience_type varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,audience_key varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,payload_json longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,event_version bigint unsigned NOT NULL DEFAULT 1,idempotency_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,state varchar(16) NOT NULL DEFAULT 'pending',attempt_count smallint unsigned NOT NULL DEFAULT 0,available_at datetime(6) NOT NULL DEFAULT current_timestamp(6),claimed_at datetime(6) NULL,published_at datetime(6) NULL,last_error_code varchar(64) NULL,created_at datetime(6) NOT NULL DEFAULT current_timestamp(6),updated_at datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),PRIMARY KEY(outbox_id),UNIQUE KEY uq_realtime_outbox_idempotency(idempotency_key)) ENGINE=InnoDB",
		"CREATE TABLE request_events (event_id bigint unsigned NOT NULL AUTO_INCREMENT,request_id int NOT NULL,event_type varchar(80) NOT NULL,puskesmas_code varchar(100) NULL,actor_user_id int NULL,actor_staff_id int NULL,actor_role varchar(50) NULL,message text NULL,metadata_json text NULL,created_at datetime NOT NULL DEFAULT current_timestamp(),PRIMARY KEY(event_id)) ENGINE=InnoDB",
		"CREATE TABLE tbl_riwayat (id int NOT NULL AUTO_INCREMENT,idUser int NOT NULL,riwayat text NULL,created_at datetime NULL,updated_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY uq_history_user(idUser)) ENGINE=InnoDB",
		"CREATE TABLE medicalrecords (record_id int NOT NULL AUTO_INCREMENT,request_id int NOT NULL,diagnosis text NULL,treatment text NULL,recommendations text NULL,anamnesis text NULL,created_at datetime NULL,PRIMARY KEY(record_id),UNIQUE KEY uq_medical_request(request_id)) ENGINE=InnoDB",
		"CREATE TABLE medicalrecord_diagnoses (diagnosis_id bigint unsigned NOT NULL AUTO_INCREMENT,medicalrecord_id int NOT NULL,request_id int NOT NULL,position tinyint unsigned NOT NULL,diagnosis_role varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,diagnosis_code varchar(32) NULL,diagnosis_label varchar(255) NOT NULL,display_text varchar(320) NOT NULL,suggestion_term_id bigint unsigned NULL,reference_source varchar(128) NULL,reference_version varchar(64) NULL,created_by_user_id int NOT NULL,created_at datetime(6) NOT NULL DEFAULT current_timestamp(6),updated_at datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),PRIMARY KEY(diagnosis_id),UNIQUE KEY uq_medicalrecord_diagnosis_position(medicalrecord_id,position),CONSTRAINT chk_diagnosis_position CHECK(position BETWEEN 1 AND 5),CONSTRAINT chk_diagnosis_role CHECK(diagnosis_role IN ('primary','secondary'))) ENGINE=InnoDB",
		"CREATE TABLE konsultasi (konsul_id int NOT NULL AUTO_INCREMENT,request_id int NOT NULL,diagnosa text NULL,saran text NULL,kriteria varchar(100) NULL,rujukan text NULL,foto text NULL,create_date datetime NULL,create_user int NULL,PRIMARY KEY(konsul_id),UNIQUE KEY uq_consultation_request(request_id)) ENGINE=InnoDB",
		"CREATE TABLE terapi (terapi_id int NOT NULL AUTO_INCREMENT,konsul_id int NOT NULL,terapi text NULL,signa text NULL,keterangan text NULL,create_date datetime NULL,create_user int NULL,PRIMARY KEY(terapi_id)) ENGINE=InnoDB",
		"CREATE TABLE consultation_visit_media (media_id bigint unsigned NOT NULL AUTO_INCREMENT,request_id int NOT NULL,medicalrecord_id int NULL,uploaded_by_user_id int NOT NULL,media_type varchar(16) NOT NULL,storage_key char(64) NOT NULL,mime_type varchar(100) NOT NULL,size_bytes bigint unsigned NOT NULL,sha256 char(64) NOT NULL,lifecycle_state varchar(16) NOT NULL DEFAULT 'pending',staged_at datetime(6) NOT NULL DEFAULT current_timestamp(6),associated_at datetime(6) NULL,finalized_at datetime(6) NULL,failed_at datetime(6) NULL,failure_code varchar(64) NULL,created_at datetime(6) NOT NULL DEFAULT current_timestamp(6),updated_at datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),PRIMARY KEY(media_id),UNIQUE KEY uq_visit_media_storage_key(storage_key)) ENGINE=InnoDB",
		"CREATE TABLE visit_location_updates (location_update_id bigint unsigned NOT NULL AUTO_INCREMENT,request_id int NOT NULL,nakes_user_id int NOT NULL,latitude decimal(10,7) NOT NULL,longitude decimal(10,7) NOT NULL,accuracy_m decimal(10,2) NULL,heading_degrees decimal(6,2) NULL,speed_mps decimal(10,2) NULL,client_sequence bigint unsigned NOT NULL,idempotency_key char(64) NOT NULL,captured_at datetime(6) NOT NULL,received_at datetime(6) NOT NULL DEFAULT current_timestamp(6),created_at datetime(6) NOT NULL DEFAULT current_timestamp(6),PRIMARY KEY(location_update_id),UNIQUE KEY uq_visit_location_idempotency(nakes_user_id,idempotency_key),UNIQUE KEY uq_visit_location_sequence(request_id,nakes_user_id,client_sequence)) ENGINE=InnoDB",
	);
	foreach ($ddl as $statement) { $db->query($statement); }

	$db->query("INSERT INTO users(userId,password,role,status,remark,must_change_password) VALUES (10,'unchanged-command','dokter','aktif','PKM01',0),(101,'unchanged-owner','warga','aktif',NULL,0),(201,'unchanged-personal-one','dokter','aktif','PKM01',0),(202,'unchanged-personal-two','dokter','aktif','PKM01',0)");
	$db->query("INSERT INTO m_puskesmas(kode_pkm,nama_puskesmas,status) VALUES ('PKM01','Synthetic clinic','aktif'),('PKM02','Synthetic alternate','aktif')");
	$db->query("INSERT INTO puskesmas_staff(staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (1,'PKM01',201,'Synthetic staff one','Dokter','aktif'),(2,'PKM01',202,'Synthetic staff two','Dokter','aktif')");
	$db->query("INSERT INTO nakes_presence(user_id,puskesmas_code,last_seen_at) VALUES (201,'PKM01',NOW(6)),(202,'PKM01',DATE_SUB(NOW(6),INTERVAL 5 MINUTE))");
	$GLOBALS['request_model_identity'][10] = array('valid' => true, 'account_type' => 'command_center', 'user_id' => 10, 'role' => 'dokter', 'user_status' => 'aktif', 'puskesmas_code' => 'PKM01');
	$GLOBALS['request_model_identity'][201] = array('valid' => true, 'account_type' => 'personal', 'user_id' => 201, 'role' => 'dokter', 'user_status' => 'aktif', 'puskesmas_code' => 'PKM01', 'staff_id' => 1);

	$home = model_integration_model('Home_m', $db);
	$home_nakes = model_integration_model('Home_nakes_m', $db);
	$konsultasi = model_integration_model('Konsultasi_m', $db);
	$completion = model_integration_model('Konsultasi_nakes_m', $db);
	$command_identity = $GLOBALS['request_model_identity'][10];
	$collations = $db->query("SELECT TABLE_NAME,COLUMN_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (TABLE_NAME,COLUMN_NAME) IN (('users','remark'),('m_puskesmas','kode_pkm'),('puskesmas_staff','kode_pkm'),('nakes_presence','puskesmas_code'),('requests','assigned_puskesmas_code'))")->result_array();
	$collation_map = array();
	foreach ($collations as $collation) {
		$collation_map[$collation['TABLE_NAME'] . '.' . $collation['COLUMN_NAME']] = $collation['COLLATION_NAME'];
	}
	ksort($collation_map);
	model_integration_expect($collation_map === array(
		'm_puskesmas.kode_pkm' => 'utf8mb4_general_ci',
		'nakes_presence.puskesmas_code' => 'utf8mb4_unicode_ci',
		'puskesmas_staff.kode_pkm' => 'utf8mb4_general_ci',
		'requests.assigned_puskesmas_code' => 'latin1_swedish_ci',
		'users.remark' => 'latin1_swedish_ci',
	), 'facility_code_fixture_uses_real_collation_boundaries');
	$command_staff_options = $home_nakes->get_active_staff_options_by_code('PKM01');
	model_integration_expect(count($command_staff_options) === 2
		&& (int) $command_staff_options[0]->is_online === 1
		&& (int) $command_staff_options[1]->is_online === 0, 'command_center_staff_options_join_mixed_presence_collations');
	$GLOBALS['request_model_application']->config->set('care_team_workflow_enabled', true);
	$personal_staff_options = $home_nakes->get_active_staff_options_by_code('PKM01');
	model_integration_expect(count($personal_staff_options) === 2, 'personal_staff_options_queryable_with_care_team_enabled');
	$GLOBALS['request_model_application']->config->set('care_team_workflow_enabled', false);
	$GLOBALS['request_model_application']->config->set('nakes_presence_enabled', false);
	$presence_disabled_options = $home_nakes->get_active_staff_options_by_code('PKM01');
	model_integration_expect(count($presence_disabled_options) === 2
		&& $presence_disabled_options[0]->is_online === null
		&& $presence_disabled_options[1]->is_online === null, 'presence_disabled_staff_options_keep_null_fallback');
	$GLOBALS['request_model_application']->config->set('nakes_presence_enabled', true);
	$profile = $home_nakes->get_profile_by_id(10);
	model_integration_expect(is_array($profile) && ($profile['assigned_puskesmas_name'] ?? '') === 'Synthetic clinic', 'profile_join_handles_latin1_remark_and_utf8mb4_facility_code');

	$created_id = $konsultasi->save_konsultasi(101, 10, 'Synthetic history', 'Synthetic request', 'Synthetic location', '', '', '2026-01-01', null, null, array('assigned_puskesmas_code' => 'PKM01', 'assigned_puskesmas_name' => 'Synthetic clinic'));
	model_integration_expect(is_int($created_id) && $created_id > 0, 'actual_create_request_succeeds');
	model_integration_expect(model_integration_event_audiences($db, 'request.created', $created_id) === array('puskesmas:PKM01:ops', 'user:101'), 'actual_create_exact_audiences');

	$stage = 'actual_accept_request';
	$accept_result = $home_nakes->accept_request($created_id, 10, '', '', 'PKM01', $command_identity);
	model_integration_expect(($accept_result['status'] ?? '') === 'success' && $db->where('request_id', $created_id)->get('requests')->row()->request_status === 'Accepted', 'actual_accept_commits_domain');
	model_integration_expect(model_integration_event_audiences($db, 'request.accepted', $created_id) === array('puskesmas:PKM01:ops', 'user:101'), 'actual_accept_exact_audiences');

	$owner_cancel_id = model_integration_insert_request($db, 'Pending');
	model_integration_expect($home->cancel_request($owner_cancel_id, 101) === true, 'actual_owner_cancel_succeeds');
	model_integration_expect(model_integration_event_audiences($db, 'request.cancelled', $owner_cancel_id) === array('puskesmas:PKM01:ops', 'user:10', 'user:101'), 'actual_owner_cancel_exact_audiences');

	$center_cancel_id = model_integration_insert_request($db, 'Pending');
	$center_cancel = $home_nakes->cancel_request($center_cancel_id, 10, 'PKM01', $command_identity);
	model_integration_expect(($center_cancel['status'] ?? '') === 'success', 'actual_command_center_cancel_succeeds');
	model_integration_expect(model_integration_event_audiences($db, 'request.cancelled', $center_cancel_id) === array('puskesmas:PKM01:ops', 'user:101'), 'actual_command_center_cancel_exact_audiences');

	$assignment_id = model_integration_insert_request($db, 'Accepted');
	$assigned = $home_nakes->assign_staff_to_request($assignment_id, 1, 'PKM01', 10, '', $command_identity);
	model_integration_expect(($assigned['status'] ?? '') === 'success', 'actual_assign_pic_succeeds');
	model_integration_expect(model_integration_event_audiences($db, 'request.pic_assigned', $assignment_id) === array('puskesmas:PKM01:ops', 'user:101', 'user:201'), 'actual_assign_pic_exact_audiences');
	$reassigned = $home_nakes->assign_staff_to_request($assignment_id, 2, 'PKM01', 10, '', $command_identity);
	model_integration_expect(($reassigned['status'] ?? '') === 'success', 'actual_reassign_pic_succeeds');
	model_integration_expect(model_integration_event_audiences($db, 'request.pic_reassigned', $assignment_id) === array('puskesmas:PKM01:ops', 'user:101', 'user:201', 'user:202'), 'actual_reassign_pic_old_and_new_audiences');
	$cleared = $home_nakes->clear_staff_assignment($assignment_id, 'PKM01', 10, $command_identity);
	model_integration_expect(($cleared['status'] ?? '') === 'success', 'actual_clear_pic_succeeds');
	model_integration_expect(model_integration_event_audiences($db, 'request.pic_cleared', $assignment_id) === array('puskesmas:PKM01:ops', 'user:101', 'user:202'), 'actual_clear_pic_exact_audiences');
	foreach (array('request.pic_assigned' => 3, 'request.pic_reassigned' => 4, 'request.pic_cleared' => 3) as $event_type => $expected_rows) {
		$row = $db->query('SELECT COUNT(1) AS total, COUNT(DISTINCT idempotency_key) AS unique_total FROM realtime_outbox WHERE event_type = ? AND aggregate_id = ?', array($event_type, (string) $assignment_id))->row();
		model_integration_expect((int) $row->total === $expected_rows && (int) $row->unique_total === $expected_rows, str_replace('.', '_', $event_type) . '_audience_idempotency_unique');
	}

	$completion_id = model_integration_insert_request($db, 'Accepted', 101, 201);
	$db->query("UPDATE requests SET assigned_nakes_user_id=201,accepted_by_user_id=10,assigned_nakes_by_user_id=10 WHERE request_id=?", array($completion_id));
	$db->query("INSERT INTO request_staff_assignments(request_id,staff_id,kode_pkm,assigned_by_user_id,status) VALUES(? ,1,'PKM01',10,'aktif')", array($completion_id));
	$completed = $completion->save_konsultasi_nakes($completion_id, 'Synthetic', 'Synthetic', '0', '', null, array(), 201, $GLOBALS['request_model_identity'][201], null, false);
	model_integration_expect($completed === true && $db->where('request_id', $completion_id)->get('requests')->row()->request_status === 'Completed', 'actual_complete_succeeds');
	model_integration_expect(model_integration_event_audiences($db, 'request.completed', $completion_id) === array('puskesmas:PKM01:ops', 'user:101', 'user:201'), 'actual_complete_exact_audiences');

	$stage = 'additional_diagnoses_success';
	$diagnosis_context = model_integration_prepare_operation('complete', $db);
	$diagnosis_request_id = (int) $diagnosis_context['request_id'];
	$diagnosis_values = array('Diagnosis utama', 'Diagnosis tambahan satu', 'Diagnosis tambahan dua');
	$diagnosis_result = $completion->save_konsultasi_nakes(
		$diagnosis_request_id, $diagnosis_values[0], 'Synthetic', '0', '', null, array(), 201,
		$GLOBALS['request_model_identity'][201], null, false, null, false, $diagnosis_values, true
	);
	$diagnosis_rows = $db->query(
		'SELECT position,diagnosis_role,diagnosis_label,display_text,diagnosis_code,suggestion_term_id,created_by_user_id FROM medicalrecord_diagnoses WHERE request_id=? ORDER BY position',
		array($diagnosis_request_id)
	)->result_array();
	$diagnosis_record = $db->where('request_id', $diagnosis_request_id)->get('medicalrecords')->row();
	$diagnosis_consultation = $db->where('request_id', $diagnosis_request_id)->get('konsultasi')->row();
	model_integration_expect($diagnosis_result === true
		&& count($diagnosis_rows) === 3
		&& array_map('intval', array_column($diagnosis_rows, 'position')) === array(1, 2, 3),
		'additional_diagnoses_exact_three_ordered_rows');
	model_integration_expect(array_column($diagnosis_rows, 'diagnosis_role') === array('primary', 'secondary', 'secondary')
		&& array_column($diagnosis_rows, 'display_text') === $diagnosis_values
		&& array_unique(array_map('intval', array_column($diagnosis_rows, 'created_by_user_id'))) === array(201),
		'additional_diagnoses_exact_roles_values_and_actor');
	model_integration_expect($diagnosis_record && $diagnosis_record->diagnosis === $diagnosis_values[0]
		&& $diagnosis_consultation && $diagnosis_consultation->diagnosa === $diagnosis_values[0]
		&& !model_integration_in_transaction($db), 'additional_diagnoses_preserve_legacy_primary_and_close_transaction');

	$stage = 'additional_diagnoses_flag_off';
	$legacy_diagnosis_context = model_integration_prepare_operation('complete', $db);
	$legacy_diagnosis_id = (int) $legacy_diagnosis_context['request_id'];
	$legacy_diagnosis_result = $completion->save_konsultasi_nakes(
		$legacy_diagnosis_id, 'Legacy primary', 'Synthetic', '0', '', null, array(), 201,
		$GLOBALS['request_model_identity'][201], null, false, null, false,
		array('Legacy primary', 'Ignored additional'), false
	);
	model_integration_expect($legacy_diagnosis_result === true
		&& model_integration_count($db, 'medicalrecord_diagnoses') === 0
		&& $db->where('request_id', $legacy_diagnosis_id)->get('medicalrecords')->row()->diagnosis === 'Legacy primary',
		'additional_diagnoses_flag_off_preserves_single_legacy_diagnosis');

	$stage = 'additional_diagnoses_duplicate';
	$duplicate_diagnosis_context = model_integration_prepare_operation('complete', $db);
	$duplicate_diagnosis_id = (int) $duplicate_diagnosis_context['request_id'];
	$duplicate_diagnosis_before = model_integration_full_digest($db);
	$duplicate_diagnosis_result = $completion->save_konsultasi_nakes(
		$duplicate_diagnosis_id, 'Duplicate', 'Synthetic', '0', '', null, array(), 201,
		$GLOBALS['request_model_identity'][201], null, false, null, false,
		array('Duplicate', 'duplicate'), true
	);
	model_integration_expect($duplicate_diagnosis_result === false
		&& $completion->last_failure_code() === 'diagnosis_payload_invalid'
		&& hash_equals($duplicate_diagnosis_before, model_integration_full_digest($db))
		&& !model_integration_in_transaction($db), 'additional_diagnoses_duplicate_full_rollback');

	$stage = 'additional_diagnoses_insert_failure';
	$failing_diagnosis_context = model_integration_prepare_operation('complete', $db);
	$failing_diagnosis_id = (int) $failing_diagnosis_context['request_id'];
	$failing_diagnosis_before = model_integration_full_digest($db);
	$db->query('SET @diagnosis_insert_attempts=0');
	$db->query("CREATE TRIGGER fail_medicalrecord_diagnosis BEFORE INSERT ON medicalrecord_diagnoses FOR EACH ROW BEGIN SET @diagnosis_insert_attempts=COALESCE(@diagnosis_insert_attempts,0)+1; IF @diagnosis_insert_attempts=2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_diagnosis_failure'; END IF; END");
	$failing_diagnosis_result = $completion->save_konsultasi_nakes(
		$failing_diagnosis_id, 'Primary', 'Synthetic', '0', '', null, array(), 201,
		$GLOBALS['request_model_identity'][201], null, false, null, false,
		array('Primary', 'Secondary'), true
	);
	$db->query('DROP TRIGGER IF EXISTS fail_medicalrecord_diagnosis');
	model_integration_expect($failing_diagnosis_result === false
		&& $completion->last_failure_code() === 'diagnosis_write_failed'
		&& hash_equals($failing_diagnosis_before, model_integration_full_digest($db))
		&& !model_integration_in_transaction($db), 'additional_diagnoses_insert_failure_full_atomic_rollback');

	$stage = 'visit_proof_success';
	model_integration_reset_fixture($db);
	$visit_request_id = model_integration_prepare_visit_request($db, 'arrived');
	$visit_proof = model_integration_create_visit_proof($db, $visit_request_id);
	$visit_completed = $completion->save_konsultasi_nakes(
		$visit_request_id, 'Synthetic visit diagnosis', 'Synthetic visit recommendation', '1', '',
		$visit_proof['file_name'], array(), 201, $GLOBALS['request_model_identity'][201], null, false,
		$visit_proof, true
	);
	$visit_request = $db->where('request_id', $visit_request_id)->get('requests')->row();
	$visit_media = $db->where('media_id', $visit_proof['media_id'])->get('consultation_visit_media')->row();
	$visit_location = $db->where('request_id', $visit_request_id)->get('visit_location_updates')->row();
	$visit_record = $db->where('request_id', $visit_request_id)->get('medicalrecords')->row();
	model_integration_expect($visit_completed === true
		&& $visit_request && $visit_request->request_status === 'Completed'
		&& $visit_request->visit_status === 'completed'
		&& $visit_request->consultation_mode === 'visit', 'visit_proof_success_exact_completion_state');
	model_integration_expect($visit_media
		&& $visit_record
		&& $visit_media->lifecycle_state === 'ready'
		&& (int) $visit_media->medicalrecord_id === (int) $visit_record->record_id
		&& !empty($visit_media->associated_at)
		&& !empty($visit_media->finalized_at), 'visit_proof_success_media_finalized_with_medicalrecord');
	model_integration_expect($visit_location
		&& (int) $visit_location->nakes_user_id === 201
		&& abs((float) $visit_location->latitude - (-6.0020000)) < 0.0000001
		&& abs((float) $visit_location->longitude - 106.0020000) < 0.0000001,
		'visit_proof_success_fresh_location_persisted');
	model_integration_expect(abs((float) $visit_request->lattitude_dokter - (-6.0020000)) < 0.0000001
		&& abs((float) $visit_request->longitude_dokter - 106.0020000) < 0.0000001
		&& !model_integration_in_transaction($db), 'visit_proof_success_request_location_and_transaction_closed');

	$stage = 'visit_proof_missing';
	model_integration_reset_fixture($db);
	$missing_request_id = model_integration_prepare_visit_request($db, 'arrived');
	$missing_result = $completion->save_konsultasi_nakes(
		$missing_request_id, 'Synthetic', 'Synthetic', '1', '', null, array(), 201,
		$GLOBALS['request_model_identity'][201], null, false, null, true
	);
	model_integration_expect($missing_result === false
		&& $completion->last_failure_code() === 'visit_proof_missing'
		&& $db->where('request_id', $missing_request_id)->get('requests')->row()->request_status === 'Accepted',
		'visit_proof_missing_blocks_completion');
	model_integration_expect(model_integration_count($db, 'medicalrecords') === 0
		&& model_integration_count($db, 'konsultasi') === 0
		&& model_integration_count($db, 'visit_location_updates') === 0
		&& !model_integration_in_transaction($db), 'visit_proof_missing_zero_domain_write');

	$stage = 'visit_proof_outside_radius';
	model_integration_reset_fixture($db);
	$outside_request_id = model_integration_prepare_visit_request($db, 'arrived');
	$outside_proof = model_integration_create_visit_proof($db, $outside_request_id);
	$outside_proof['location']['latitude'] = -6.0120000;
	$outside_result = $completion->save_konsultasi_nakes(
		$outside_request_id, 'Synthetic', 'Synthetic', '1', '', $outside_proof['file_name'], array(), 201,
		$GLOBALS['request_model_identity'][201], null, false, $outside_proof, true
	);
	model_integration_expect($outside_result === false
		&& $completion->last_failure_code() === 'visit_location_outside_radius'
		&& $db->where('request_id', $outside_request_id)->get('requests')->row()->request_status === 'Accepted',
		'visit_proof_outside_radius_blocks_completion');
	model_integration_expect($db->where('media_id', $outside_proof['media_id'])->get('consultation_visit_media')->row()->lifecycle_state === 'pending'
		&& model_integration_count($db, 'visit_location_updates') === 0
		&& !model_integration_in_transaction($db), 'visit_proof_outside_radius_rolls_back_without_finalizing_media');

	$stage = 'visit_proof_status_gate';
	model_integration_reset_fixture($db);
	$status_request_id = model_integration_prepare_visit_request($db, 'en_route');
	$status_proof = model_integration_create_visit_proof($db, $status_request_id);
	$status_result = $completion->save_konsultasi_nakes(
		$status_request_id, 'Synthetic', 'Synthetic', '1', '', $status_proof['file_name'], array(), 201,
		$GLOBALS['request_model_identity'][201], null, false, $status_proof, true
	);
	model_integration_expect($status_result === false
		&& $completion->last_failure_code() === 'visit_status_invalid'
		&& model_integration_count($db, 'visit_location_updates') === 0,
		'visit_proof_requires_arrived_or_in_service_status');

	$stage = 'visit_proof_non_visit_bypass';
	model_integration_reset_fixture($db);
	$bypass_request_id = model_integration_prepare_visit_request($db, 'arrived');
	$bypass_result = $completion->save_konsultasi_nakes(
		$bypass_request_id, 'Synthetic', 'Synthetic', '0', '', null, array(), 201,
		$GLOBALS['request_model_identity'][201], null, false, null, true
	);
	model_integration_expect($bypass_result === false
		&& $completion->last_failure_code() === 'visit_mode_mismatch'
		&& $db->where('request_id', $bypass_request_id)->get('requests')->row()->request_status === 'Accepted',
		'visit_proof_persisted_visit_cannot_downgrade_to_non_visit');
	model_integration_expect(model_integration_count($db, 'medicalrecords') === 0
		&& model_integration_count($db, 'konsultasi') === 0
		&& model_integration_count($db, 'consultation_visit_media') === 0
		&& model_integration_count($db, 'visit_location_updates') === 0,
		'visit_proof_non_visit_bypass_zero_write');

	$stage = 'visit_proof_unknown_mode';
	model_integration_reset_fixture($db);
	$unknown_mode_request_id = model_integration_insert_request($db, 'Accepted', 101, 201);
	$db->query("UPDATE requests SET assigned_nakes_user_id=201,accepted_by_user_id=10,assigned_nakes_by_user_id=10,consultation_mode=NULL,visit_status='not_started' WHERE request_id=?", array($unknown_mode_request_id));
	$db->query("INSERT INTO request_staff_assignments(request_id,staff_id,kode_pkm,assigned_by_user_id,status) VALUES(? ,1,'PKM01',10,'aktif')", array($unknown_mode_request_id));
	$unknown_mode_before = model_integration_digest($db, $unknown_mode_request_id);
	$unknown_mode_result = $completion->save_konsultasi_nakes(
		$unknown_mode_request_id, 'Synthetic', 'Synthetic', 'arbitrary-mode', '', null, array(), 201,
		$GLOBALS['request_model_identity'][201], null, false, null, true
	);
	model_integration_expect($unknown_mode_result === false
		&& $completion->last_failure_code() === 'visit_mode_invalid',
		'visit_proof_unknown_service_mode_rejected');
	model_integration_expect(hash_equals($unknown_mode_before, model_integration_digest($db, $unknown_mode_request_id))
		&& !model_integration_in_transaction($db),
		'visit_proof_unknown_service_mode_zero_write');

	$stage = 'visit_proof_flag_off_compatibility';
	model_integration_reset_fixture($db);
	$legacy_visit_request_id = model_integration_prepare_visit_request($db, 'en_route');
	$legacy_visit_result = $completion->save_konsultasi_nakes(
		$legacy_visit_request_id, 'Synthetic', 'Synthetic', '1', '', null, array(), 201,
		$GLOBALS['request_model_identity'][201], null, false, null, false
	);
	model_integration_expect($legacy_visit_result === true
		&& $db->where('request_id', $legacy_visit_request_id)->get('requests')->row()->request_status === 'Completed'
		&& model_integration_count($db, 'consultation_visit_media') === 0
		&& model_integration_count($db, 'visit_location_updates') === 0,
		'visit_proof_flag_off_preserves_legacy_completion');

	$stage = 'visit_proof_realtime_failure';
	model_integration_reset_fixture($db);
	$fault_request_id = model_integration_prepare_visit_request($db, 'in_service');
	$fault_proof = model_integration_create_visit_proof($db, $fault_request_id);
	model_integration_install_outbox_fault($db, 2);
	$fault_result = $completion->save_konsultasi_nakes(
		$fault_request_id, 'Synthetic', 'Synthetic', '1', '', $fault_proof['file_name'], array(), 201,
		$GLOBALS['request_model_identity'][201], null, false, $fault_proof, true
	);
	$db->query('DROP TRIGGER IF EXISTS fail_realtime_outbox');
	$fault_request = $db->where('request_id', $fault_request_id)->get('requests')->row();
	$fault_media = $db->where('media_id', $fault_proof['media_id'])->get('consultation_visit_media')->row();
	model_integration_expect($fault_result === false
		&& $fault_request->request_status === 'Accepted'
		&& $fault_media->lifecycle_state === 'pending', 'visit_proof_realtime_failure_rolls_back_completion_and_media_finalize');
	model_integration_expect(model_integration_count($db, 'visit_location_updates') === 0
		&& model_integration_count($db, 'medicalrecords') === 0
		&& model_integration_count($db, 'konsultasi') === 0
		&& model_integration_count($db, 'realtime_outbox') === 0
		&& !model_integration_in_transaction($db), 'visit_proof_realtime_failure_full_transaction_rollback');

	foreach (array(
		'inactive' => function ($db) { $db->query("UPDATE users SET status='nonaktif' WHERE userId=10"); },
		'cross_tenant' => function ($db) { $db->query("UPDATE users SET remark='PKM02' WHERE userId=10"); },
		'noncanonical' => function ($db) { $db->query("INSERT INTO users(userId,password,role,status,remark,must_change_password) VALUES(5,'unchanged-lower','dokter','aktif','PKM01',0)"); },
	) as $state => $mutation) {
		$db->query("UPDATE users SET status='aktif',remark='PKM01',must_change_password=0 WHERE userId=10");
		$db->query('DELETE FROM users WHERE userId=5');
		$request_id = model_integration_insert_request($db, 'Pending');
		$before = model_integration_digest($db, $request_id);
		$mutation($db);
		$result = $home_nakes->accept_request($request_id, 10, '', '', 'PKM01', $command_identity);
		model_integration_expect(($result['status'] ?? '') === 'error' && model_integration_digest($db, $request_id) === $before, 'post_lock_' . $state . '_rejected_zero_write');
	}
	$db->query("DELETE FROM users WHERE userId=5");
	$db->query("UPDATE users SET status='aktif',remark='PKM01',must_change_password=1 WHERE userId=10");
	$raw_pending_id = model_integration_insert_request($db, 'Pending');
	$raw_pending = $home_nakes->accept_request($raw_pending_id, 10, '', '', 'PKM01', $command_identity);
	model_integration_expect(($raw_pending['status'] ?? '') === 'success', 'post_lock_raw_credential_pending_allowed_when_enforcement_off');
	$db->query("DELETE FROM users WHERE userId=5");
	$db->query("UPDATE users SET status='aktif',remark='PKM01',must_change_password=0 WHERE userId=10");
	$positive_id = model_integration_insert_request($db, 'Pending');
	$positive = $home_nakes->accept_request($positive_id, 10, '', '', 'PKM01', $command_identity);
	model_integration_expect(($positive['status'] ?? '') === 'success', 'post_lock_valid_identity_positive_control');

	$ambiguous_id = model_integration_insert_request($db, 'Accepted');
	$db->query("INSERT INTO request_staff_assignments(request_id,staff_id,kode_pkm,assigned_by_user_id,status) VALUES(? ,1,'PKM01',10,'aktif'),(? ,2,'PKM01',10,'aktif')", array($ambiguous_id, $ambiguous_id));
	$ambiguous_before = model_integration_digest($db, $ambiguous_id);
	$ambiguous = $home_nakes->assign_staff_to_request($ambiguous_id, 2, 'PKM01', 10, '', $command_identity);
	model_integration_expect(($ambiguous['safe_error_code'] ?? '') === 'ambiguous_active_assignments', 'ambiguous_assignment_safe_error');
	model_integration_expect(model_integration_digest($db, $ambiguous_id) === $ambiguous_before, 'ambiguous_assignment_zero_mutation');
	model_integration_expect(model_integration_count($db, 'realtime_outbox', "aggregate_id=" . $db->escape((string) $ambiguous_id)) === 0, 'ambiguous_assignment_zero_outbox');

	$GLOBALS['request_model_realtime_enabled'] = false;
	$legacy_id = model_integration_insert_request($db, 'Pending');
	$outbox_before = model_integration_count($db, 'realtime_outbox');
	model_integration_expect($home->cancel_request($legacy_id, 101) === true && model_integration_count($db, 'realtime_outbox') === $outbox_before, 'flag_off_legacy_cancel_zero_outbox');
	$GLOBALS['request_model_realtime_enabled'] = true;

	$db->query("CREATE TRIGGER fail_realtime_outbox BEFORE INSERT ON realtime_outbox FOR EACH ROW SIGNAL SQLSTATE '45000'");
	$failure_id = model_integration_insert_request($db, 'Pending');
	$failure_before = model_integration_digest($db, $failure_id);
	$failure_result = $home_nakes->accept_request($failure_id, 10, '', '', 'PKM01', $command_identity);
	model_integration_expect(($failure_result['status'] ?? '') === 'error' && model_integration_digest($db, $failure_id) === $failure_before, 'actual_enqueue_failure_rolls_back_domain_notification_outbox');
	$db->query('DROP TRIGGER fail_realtime_outbox');

	$models = array('home' => $home, 'nakes' => $home_nakes, 'create' => $konsultasi, 'complete' => $completion);
	$operations = array('create','accept','cancel_warga','cancel_command_center','complete','assign','reassign','clear');
	$fault_scenarios = 0;
	foreach ($operations as $operation) {
		$stage = 'fault_' . $operation;
		$context = model_integration_prepare_operation($operation, $db);
		$before = model_integration_full_digest($db);
		model_integration_install_outbox_fault($db, 2);
		$result = model_integration_invoke_operation($operation, $context, $models);
		$attempts = model_integration_outbox_attempts($db);
		$db->query('DROP TRIGGER fail_realtime_outbox');
		model_integration_expect(model_integration_operation_failed($operation, $result), 'fault_' . $operation . '_returns_failure');
		model_integration_expect($attempts === 2, 'fault_' . $operation . '_reaches_second_fanout_enqueue');
		model_integration_expect(model_integration_full_digest($db) === $before, 'fault_' . $operation . '_full_atomic_digest_unchanged');
		model_integration_expect(!model_integration_in_transaction($db), 'fault_' . $operation . '_transaction_closed');
		$fault_scenarios++;
	}
	model_integration_expect($fault_scenarios === 8, 'fault_injection_scenario_count_exact');

	$state_c_expected_audiences = array(
		'create' => array('puskesmas:PKM01:ops', 'user:101'),
		'accept' => array('puskesmas:PKM01:ops', 'user:101'),
		'cancel_warga' => array('puskesmas:PKM01:ops', 'user:10', 'user:101'),
		'cancel_command_center' => array('puskesmas:PKM01:ops', 'user:101'),
		'complete' => array('puskesmas:PKM01:ops', 'user:101', 'user:201'),
	);
	$state_c_event_types = array(
		'create' => 'request.created',
		'accept' => 'request.accepted',
		'cancel_warga' => 'request.cancelled',
		'cancel_command_center' => 'request.cancelled',
		'complete' => 'request.completed',
	);
	$state_c_scenarios = 0;
	$state_c_mutation_controls = 0;
	$state_c_orchestrator_calls_before = (int) $GLOBALS['request_model_orchestrator_calls'];
	foreach (array('create','accept','cancel_warga','cancel_command_center','complete') as $operation) {
		$stage = 'state_c_' . $operation;
		$context = model_integration_prepare_operation($operation, $db);
		$GLOBALS['request_model_realtime_enabled'] = true;
		$GLOBALS['request_model_notification_enabled'] = true;
		$GLOBALS['request_model_application']->config->set('realtime_client_enabled', true);
		$GLOBALS['request_model_application']->config->set('realtime_notifications_enabled', true);
		$result = model_integration_invoke_operation($operation, $context, $models);
		$request_id = $operation === 'create' ? (int) $result : (int) $context['request_id'];
		$orchestrator_call_before = (int) $GLOBALS['request_model_orchestrator_calls'];
		$orchestration = model_integration_orchestrate($operation, $context, $result, $db);
		$expected_notification = model_integration_expected_notification($operation, $request_id);
		if ($operation === 'cancel_warga') {
			$expected_notification['recipient_puskesmas_code'] = 'PKM01';
		}
		$notification_rows = model_integration_notification_rows($db, $request_id);
		$outbox_rows = model_integration_outbox_rows($db, $request_id);
		$request_event_rows = array_values(array_filter($outbox_rows, function ($row) {
			return strpos((string) $row['event_type'], 'request.') === 0;
		}));
		$notification_event_rows = array_values(array_filter($outbox_rows, function ($row) {
			return $row['event_type'] === 'notification.created';
		}));

		model_integration_expect(model_integration_operation_succeeded($operation, $result), $stage . '_actual_model_success');
		model_integration_expect((int) $GLOBALS['request_model_orchestrator_calls'] === $orchestrator_call_before + 1,
			$stage . '_production_orchestrator_invoked_once');
		model_integration_expect(is_array($orchestration)
			&& $orchestration['response'] === model_integration_expected_response($operation, $request_id),
			$stage . '_exact_public_response');
		model_integration_expect($orchestration['notification_result'] === null,
			$stage . '_controller_notification_guarded_when_request_realtime_enabled');
		model_integration_expect(model_integration_assert_domain_post_state($operation, $request_id, $db),
			$stage . '_exact_domain_post_state');
		$row = count($notification_rows) === 1 ? $notification_rows[0] : array();
		$actual_notification = $row ? array(
			'recipient_user_id' => (int) $row['recipient_user_id'],
			'recipient_role' => $row['recipient_role'],
			'recipient_puskesmas_code' => $row['recipient_puskesmas_code'],
			'actor_user_id' => (int) $row['actor_user_id'],
			'event_type' => $row['event_type'], 'entity_type' => $row['entity_type'],
			'entity_id' => $row['entity_id'], 'is_read' => (int) $row['is_read'],
			'title_digest' => $row['title_digest'], 'message_digest' => $row['message_digest'],
		) : array();
		model_integration_expect(count($notification_rows) === 1 && $actual_notification === $expected_notification,
			$stage . '_exact_single_persisted_notification');
		model_integration_expect(count($notification_event_rows) === 1,
			$stage . '_exact_single_notification_created');
		$notification_id = (int) $notification_rows[0]['notification_id'];
		$notification_payload = json_encode(array(
			'aggregate_id' => (string) $notification_id,
			'audience' => 'user:' . $expected_notification['recipient_user_id'],
			'event_id' => 'notification:' . $notification_id,
			'event_type' => 'notification.created',
			'invalidation' => 'notifications',
			'version' => 1,
		), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$expected_notification_idempotency = hash('sha256', implode("\n", array(
			'doclinc-realtime-outbox-v1', 'notification', $notification_payload,
		)));
		model_integration_expect($notification_event_rows[0]['aggregate_type'] === 'notification'
			&& $notification_event_rows[0]['aggregate_id'] === (string) $notification_id
			&& $notification_event_rows[0]['audience_key'] === 'user:' . $expected_notification['recipient_user_id']
			&& $notification_event_rows[0]['idempotency_key'] === $expected_notification_idempotency,
			$stage . '_notification_created_exact_identity_audience_idempotency');
		model_integration_expect(count($request_event_rows) === count($state_c_expected_audiences[$operation])
			&& count(array_unique(array_column($request_event_rows, 'idempotency_key'))) === count($request_event_rows),
			$stage . '_request_event_exact_count_and_unique_idempotency');
		model_integration_expect(array_values(array_unique(array_column($request_event_rows, 'event_type')))
			=== array($state_c_event_types[$operation]), $stage . '_request_event_exact_type');
		model_integration_expect(model_integration_event_audiences($db, $state_c_event_types[$operation], $request_id)
			=== $state_c_expected_audiences[$operation], $stage . '_request_event_exact_audiences');
		model_integration_expect($GLOBALS['request_model_direct_publish_count'] === 0, $stage . '_direct_publish_zero');
		model_integration_expect(!model_integration_in_transaction($db), $stage . '_transaction_closed');

		if ($operation === 'create') {
			$GLOBALS['request_model_realtime_enabled'] = false;
			$duplicate_orchestration = model_integration_orchestrate($operation, $context, $result, $db);
			$duplicate_notifications = model_integration_notification_rows($db, $request_id);
			$duplicate_outbox = model_integration_outbox_rows($db, $request_id);
			$duplicate_notification_events = array_values(array_filter($duplicate_outbox, function ($row) {
				return $row['event_type'] === 'notification.created';
			}));
			model_integration_expect(is_array($duplicate_orchestration)
				&& count($duplicate_notifications) === 2
				&& count($duplicate_notification_events) === 2,
				'state_c_duplicate_mutation_control_produces_detectable_duplicate');
			model_integration_expect(!(count($duplicate_notifications) === 1 && count($duplicate_notification_events) === 1),
				'state_c_exact_count_assertion_rejects_duplicate_orchestration');
			$state_c_mutation_controls++;
			$GLOBALS['request_model_realtime_enabled'] = true;
		}
		$state_c_scenarios++;
	}
	model_integration_expect($state_c_scenarios === 5, 'state_c_scenario_count_exact');
	model_integration_expect((int) $GLOBALS['request_model_orchestrator_calls'] - $state_c_orchestrator_calls_before === 6,
		'state_c_orchestrator_call_counter_includes_five_valid_and_one_mutation_control');
	model_integration_expect($state_c_mutation_controls === 1, 'state_c_mutation_control_count_exact');

	$flag_configurations = array(
		'compatibility' => array('client' => true, 'notification' => true),
		'full_legacy' => array('client' => false, 'notification' => false),
	);
	$flag_off_scenarios = 0;
	foreach ($flag_configurations as $configuration => $flags) {
		foreach ($operations as $operation) {
			$stage = 'flag_off_' . $configuration . '_' . $operation;
			$context = model_integration_prepare_operation($operation, $db);
			$GLOBALS['request_model_realtime_enabled'] = false;
			$GLOBALS['request_model_notification_enabled'] = $flags['notification'];
			$GLOBALS['request_model_application']->config->set('realtime_client_enabled', $flags['client']);
			$GLOBALS['request_model_application']->config->set('realtime_notifications_enabled', $flags['notification']);
			$result = model_integration_invoke_operation($operation, $context, $models);
			$orchestration = model_integration_orchestrate($operation, $context, $result, $db);
			$request_id = $operation === 'create' ? (int) $result : (int) $context['request_id'];
			$expected_notification = model_integration_expected_notification($operation, $request_id);
			$notification_rows = model_integration_notification_rows($db, $request_id);
			$outbox_rows = model_integration_outbox_rows($db, $request_id);

			model_integration_expect(model_integration_operation_succeeded($operation, $result), $stage . '_production_model_result_success');
			model_integration_expect(is_array($orchestration)
				&& $orchestration['response'] === model_integration_expected_response($operation, $request_id), $stage . '_exact_public_response_contract');
			model_integration_expect(model_integration_assert_domain_post_state($operation, $request_id, $db), $stage . '_exact_domain_post_state');
			if ($expected_notification === null) {
				model_integration_expect(count($notification_rows) === 0, $stage . '_exact_zero_legacy_notification');
			} else {
				$row = count($notification_rows) === 1 ? $notification_rows[0] : array();
				$actual_notification = $row ? array(
					'recipient_user_id' => (int) $row['recipient_user_id'],
					'recipient_role' => $row['recipient_role'],
					'recipient_puskesmas_code' => $row['recipient_puskesmas_code'],
					'actor_user_id' => (int) $row['actor_user_id'],
					'event_type' => $row['event_type'], 'entity_type' => $row['entity_type'],
					'entity_id' => $row['entity_id'], 'is_read' => (int) $row['is_read'],
					'title_digest' => $row['title_digest'], 'message_digest' => $row['message_digest'],
				) : array();
				model_integration_expect(count($notification_rows) === 1 && $actual_notification === $expected_notification,
					$stage . '_exact_persisted_notification_recipient_and_contract');
			}
			$request_event_rows = array_values(array_filter($outbox_rows, function ($row) {
				return strpos((string) $row['event_type'], 'request.') === 0;
			}));
			$notification_event_rows = array_values(array_filter($outbox_rows, function ($row) {
				return $row['event_type'] === 'notification.created';
			}));
			model_integration_expect(count($request_event_rows) === 0, $stage . '_request_event_delta_zero');
			$expected_notification_events = $flags['notification'] && $expected_notification !== null ? 1 : 0;
			model_integration_expect(count($notification_event_rows) === $expected_notification_events,
				$stage . '_notification_created_exact_delta');
			if ($expected_notification_events === 1) {
				$notification_id = (int) $notification_rows[0]['notification_id'];
				model_integration_expect($notification_event_rows[0]['aggregate_type'] === 'notification'
					&& $notification_event_rows[0]['aggregate_id'] === (string) $notification_id
					&& $notification_event_rows[0]['audience_key'] === 'user:' . $expected_notification['recipient_user_id'],
					$stage . '_notification_created_exact_identity_and_audience');
			}
			model_integration_expect(!model_integration_in_transaction($db), $stage . '_transaction_closed');
			model_integration_expect($GLOBALS['request_model_direct_publish_count'] === 0, $stage . '_direct_publish_zero');
			$flag_off_scenarios++;
		}
	}
	model_integration_expect($flag_off_scenarios === 16, 'flag_off_scenario_count_exact');

	$flag_off_model_failure_scenarios = 0;
	$flag_off_notification_failure_scenarios = 0;
	foreach ($flag_configurations as $configuration => $flags) {
		foreach (array('create','accept','cancel_warga','cancel_command_center','complete') as $operation) {
			$stage = 'flag_off_model_failure_' . $configuration . '_' . $operation;
			$context = model_integration_prepare_operation($operation, $db);
			$GLOBALS['request_model_realtime_enabled'] = false;
			$GLOBALS['request_model_application']->config->set('realtime_client_enabled', $flags['client']);
			$GLOBALS['request_model_application']->config->set('realtime_notifications_enabled', $flags['notification']);
			$result = model_integration_invoke_failed_operation($operation, $context, $models, $db);
			$orchestration = model_integration_orchestrate($operation, $context, $result, $db);
			model_integration_expect(model_integration_operation_failed($operation, $result), $stage . '_exact_model_failure');
			model_integration_expect($orchestration === null, $stage . '_controller_orchestration_not_run');
			model_integration_expect(model_integration_count($db, 'notifications') === 0, $stage . '_notification_delta_zero');
			model_integration_expect(model_integration_count($db, 'realtime_outbox') === 0, $stage . '_all_outbox_delta_zero');
			model_integration_expect(!model_integration_in_transaction($db), $stage . '_transaction_closed');
			$flag_off_model_failure_scenarios++;

			$stage = 'flag_off_notification_failure_' . $configuration . '_' . $operation;
			$context = model_integration_prepare_operation($operation, $db);
			$GLOBALS['request_model_realtime_enabled'] = false;
			$GLOBALS['request_model_application']->config->set('realtime_client_enabled', $flags['client']);
			$GLOBALS['request_model_application']->config->set('realtime_notifications_enabled', $flags['notification']);
			$db->query("CREATE TRIGGER fail_notifications BEFORE INSERT ON notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic_notification_failure'");
			$result = model_integration_invoke_operation($operation, $context, $models);
			$orchestration = model_integration_orchestrate($operation, $context, $result, $db);
			$db->query('DROP TRIGGER fail_notifications');
			$request_id = $operation === 'create' ? (int) $result : (int) $context['request_id'];
			model_integration_expect(model_integration_operation_succeeded($operation, $result), $stage . '_domain_result_remains_success');
			model_integration_expect(is_array($orchestration)
				&& $orchestration['response'] === model_integration_expected_response($operation, $request_id), $stage . '_public_result_remains_success');
			model_integration_expect($orchestration['notification_result'] === false || $orchestration['notification_result'] === 0,
				$stage . '_notification_failure_exposed_to_orchestration');
			model_integration_expect(model_integration_assert_domain_post_state($operation, $request_id, $db), $stage . '_legacy_domain_commit_preserved');
			model_integration_expect(model_integration_count($db, 'notifications') === 0, $stage . '_failed_notification_zero_row');
			model_integration_expect(model_integration_count($db, 'realtime_outbox') === 0, $stage . '_failed_notification_zero_outbox');
			model_integration_expect(!model_integration_in_transaction($db), $stage . '_transaction_closed');
			$flag_off_notification_failure_scenarios++;
		}
	}
	model_integration_expect($flag_off_model_failure_scenarios === 10, 'flag_off_model_failure_scenario_count_exact');
	model_integration_expect($flag_off_notification_failure_scenarios === 10, 'flag_off_notification_failure_scenario_count_exact');

	$flag_off_retry_scenarios = 0;
	foreach ($flag_configurations as $configuration => $flags) {
		foreach (array('accept','cancel_warga','cancel_command_center','complete') as $operation) {
			$stage = 'flag_off_retry_' . $configuration . '_' . $operation;
			$context = model_integration_prepare_operation($operation, $db);
			$GLOBALS['request_model_realtime_enabled'] = false;
			$GLOBALS['request_model_application']->config->set('realtime_client_enabled', $flags['client']);
			$GLOBALS['request_model_application']->config->set('realtime_notifications_enabled', $flags['notification']);
			$first = model_integration_invoke_operation($operation, $context, $models);
			$first_orchestration = model_integration_orchestrate($operation, $context, $first, $db);
			$before_retry = model_integration_full_digest($db);
			$second = model_integration_invoke_operation($operation, $context, $models);
			$second_orchestration = model_integration_orchestrate($operation, $context, $second, $db);
			model_integration_expect(is_array($first_orchestration) && model_integration_operation_failed($operation, $second)
				&& $second_orchestration === null, $stage . '_second_call_deterministic_failure');
			model_integration_expect(model_integration_full_digest($db) === $before_retry, $stage . '_zero_duplicate_notification_or_event');
			model_integration_expect(!model_integration_in_transaction($db), $stage . '_transaction_closed');
			$flag_off_retry_scenarios++;
		}

		$stage = 'flag_off_retry_' . $configuration . '_same_pic';
		$context = model_integration_prepare_operation('assign', $db);
		$GLOBALS['request_model_realtime_enabled'] = false;
		$GLOBALS['request_model_application']->config->set('realtime_client_enabled', $flags['client']);
		$GLOBALS['request_model_application']->config->set('realtime_notifications_enabled', $flags['notification']);
		$first = model_integration_invoke_operation('assign', $context, $models);
		$before_retry = model_integration_full_digest($db);
		$second = model_integration_invoke_operation('assign', $context, $models);
		model_integration_expect(($first['message'] ?? '') === 'Penanggung jawab diperbarui.' && ($second['message'] ?? '') === 'Penanggung jawab tetap sama.',
			$stage . '_explicit_result_contract');
		model_integration_expect(model_integration_full_digest($db) === $before_retry && !model_integration_in_transaction($db),
			$stage . '_zero_duplicate_assignment_notification_event');
		$flag_off_retry_scenarios++;

		$stage = 'flag_off_retry_' . $configuration . '_repeated_clear';
		$context = model_integration_prepare_operation('clear', $db);
		$GLOBALS['request_model_realtime_enabled'] = false;
		$first = model_integration_invoke_operation('clear', $context, $models);
		$before_retry = model_integration_full_digest($db);
		$second = model_integration_invoke_operation('clear', $context, $models);
		model_integration_expect(($first['status'] ?? '') === 'success' && ($first['message'] ?? '') === 'Penugasan dihapus.'
			&& ($second['status'] ?? '') === 'error' && ($second['message'] ?? '') === 'Penanggung jawab aktif tidak ditemukan.',
			$stage . '_explicit_result_contract');
		model_integration_expect(model_integration_full_digest($db) === $before_retry && !model_integration_in_transaction($db),
			$stage . '_zero_duplicate_mutation_notification_event');
		$flag_off_retry_scenarios++;
	}
	model_integration_expect($flag_off_retry_scenarios === 12, 'flag_off_retry_scenario_count_exact');
	$GLOBALS['request_model_realtime_enabled'] = true;

	$stage = 'same_pic_edge';
	$context = model_integration_prepare_operation('assign', $db);
	$first_assign = model_integration_invoke_operation('assign', $context, $models);
	$same_pic_before = model_integration_full_digest($db);
	$assignment_count = model_integration_count($db, 'request_staff_assignments');
	$event_count = model_integration_count($db, 'request_events');
	$notification_count = model_integration_count($db, 'notifications');
	$outbox_count = model_integration_count($db, 'realtime_outbox');
	$same_pic = model_integration_invoke_operation('assign', $context, $models);
	model_integration_expect(model_integration_operation_succeeded('assign', $first_assign)
		&& model_integration_operation_succeeded('assign', $same_pic)
		&& ($same_pic['message'] ?? '') === 'Penanggung jawab tetap sama.', 'same_pic_return_contract_stable');
	model_integration_expect(model_integration_count($db, 'request_staff_assignments') === $assignment_count
		&& model_integration_count($db, 'request_events') === $event_count
		&& model_integration_count($db, 'notifications') === $notification_count
		&& model_integration_count($db, 'realtime_outbox') === $outbox_count
		&& model_integration_full_digest($db) === $same_pic_before, 'same_pic_zero_duplicate_mutation_event_notification_outbox');

	$stage = 'repeated_clear_edge';
	$context = model_integration_prepare_operation('clear', $db);
	$first_clear = model_integration_invoke_operation('clear', $context, $models);
	$clear_before_retry = model_integration_full_digest($db);
	$second_clear = model_integration_invoke_operation('clear', $context, $models);
	model_integration_expect(model_integration_operation_succeeded('clear', $first_clear)
		&& model_integration_operation_failed('clear', $second_clear), 'repeated_clear_first_success_second_failure');
	model_integration_expect(model_integration_full_digest($db) === $clear_before_retry
		&& !model_integration_in_transaction($db), 'repeated_clear_zero_retry_mutation_and_transaction_closed');

	foreach (array('accept','cancel_warga','cancel_command_center','complete') as $operation) {
		$stage = 'repeated_' . $operation;
		$context = model_integration_prepare_operation($operation, $db);
		$first = model_integration_invoke_operation($operation, $context, $models);
		$before_retry = model_integration_full_digest($db);
		$second = model_integration_invoke_operation($operation, $context, $models);
		$retry_safe = model_integration_operation_failed($operation, $second);
		model_integration_expect(model_integration_operation_succeeded($operation, $first) && $retry_safe, 'repeated_' . $operation . '_deterministic_contract');
		model_integration_expect(model_integration_full_digest($db) === $before_retry
			&& !model_integration_in_transaction($db), 'repeated_' . $operation . '_zero_duplicate_outbox_and_closed_transaction');
	}

	$stage = 'ambiguous_regression';
	$context = model_integration_prepare_operation('reassign', $db);
	$db->query("INSERT INTO request_staff_assignments(request_id,staff_id,kode_pkm,assigned_by_user_id,status) VALUES(? ,2,'PKM01',10,'aktif')", array($context['request_id']));
	$ambiguous_before = model_integration_full_digest($db);
	$ambiguous = model_integration_invoke_operation('reassign', $context, $models);
	model_integration_expect(($ambiguous['safe_error_code'] ?? '') === 'ambiguous_active_assignments'
		&& model_integration_full_digest($db) === $ambiguous_before
		&& !model_integration_in_transaction($db), 'ambiguous_regression_full_zero_write_and_closed_transaction');

	$stage = 'idempotency_collision_regression';
	model_integration_reset_fixture($db);
	$contract = new Realtime_outbox_contract();
	$prepared_owner = $contract->prepare(array('event_id' => 'request.created:9001', 'event_type' => 'request.created', 'aggregate_type' => 'request', 'aggregate_id' => '9001', 'version' => 1, 'invalidation' => 'requests', 'audience' => 'user:101'));
	$prepared_tenant = $contract->prepare(array('event_id' => 'request.created:9001', 'event_type' => 'request.created', 'aggregate_type' => 'request', 'aggregate_id' => '9001', 'version' => 1, 'invalidation' => 'requests', 'audience' => 'puskesmas:PKM01:ops'), array('PKM01'));
	model_integration_expect($prepared_owner['idempotency_key'] !== $prepared_tenant['idempotency_key']
		&& $prepared_owner['payload_json'] !== $prepared_tenant['payload_json'], 'different_audience_cannot_share_duplicate_idempotency');

	echo "REALTIME_REQUEST_FAULT_SCENARIOS={$fault_scenarios}\n";
	echo "REALTIME_REQUEST_STATE_C_SCENARIOS={$state_c_scenarios}\n";
	echo "REALTIME_REQUEST_STATE_C_MUTATION_CONTROLS={$state_c_mutation_controls}\n";
	echo "REALTIME_REQUEST_FLAG_OFF_SCENARIOS={$flag_off_scenarios}\n";
	echo "REALTIME_REQUEST_FLAG_OFF_MODEL_FAILURE_SCENARIOS={$flag_off_model_failure_scenarios}\n";
	echo "REALTIME_REQUEST_FLAG_OFF_NOTIFICATION_FAILURE_SCENARIOS={$flag_off_notification_failure_scenarios}\n";
	echo "REALTIME_REQUEST_FLAG_OFF_RETRY_SCENARIOS={$flag_off_retry_scenarios}\n";
	if (defined('DOCLINC_CONTROLLER_ORCHESTRATION_ENTRYPOINT')) {
		echo "REALTIME_REQUEST_CONTROLLER_ORCHESTRATION_INTEGRATION_PASSED={$passed}\n";
		echo "REALTIME_REQUEST_CONTROLLER_ORCHESTRATION_INTEGRATION_FAILED={$failed}\n";
	}
	echo "MODEL_INTEGRATION_UNIQUE_ASSERTIONS=" . ($passed + $failed) . "\n";
	echo "REALTIME_REQUEST_MODEL_INTEGRATION_PASSED={$passed}\nREALTIME_REQUEST_MODEL_INTEGRATION_FAILED={$failed}\n";
} catch (Throwable $exception) {
	$failed++;
	$sql_state = method_exists($exception, 'getSqlState') ? (string) $exception->getSqlState() : '';
	$safe_message = preg_replace('/[\r\n]+/', ' ', (string) $exception->getMessage());
	fwrite(STDERR, "FAIL model_integration_safe_error\nSAFE_ERROR_STAGE={$stage}\nSAFE_EXCEPTION_CLASS=" . get_class($exception) . "\nSAFE_EXCEPTION_CODE=" . (int) $exception->getCode() . "\nSAFE_SQLSTATE={$sql_state}\nSAFE_DB_MESSAGE={$safe_message}\nSAFE_EXCEPTION_FILE=" . basename($exception->getFile()) . "\nSAFE_EXCEPTION_LINE=" . (int) $exception->getLine() . "\n");
} finally {
	if (is_object($db) && method_exists($db, 'close')) { $db->close(); }
	if ($admin instanceof mysqli) {
		if ($database_name !== '') { $admin->query('DROP DATABASE IF EXISTS ' . model_integration_identifier($database_name)); }
		$admin->close();
	}
	foreach ($visit_proof_temp_directories as $directory) {
		if (!is_dir($directory)) { continue; }
		foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: array() as $path) {
			if (is_file($path)) { @unlink($path); }
		}
		@rmdir($directory);
	}
}
exit($failed === 0 ? 0 : 1);
