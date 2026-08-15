<?php
define('BASEPATH', __DIR__ . '/');

require_once dirname(__DIR__, 3) . '/application/libraries/Puskesmas_operations_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Puskesmas_operations_service.php';

$passed = 0;
$failed = 0;
function operations_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$label}\n";
		return;
	}
	$failed++;
	echo "FAIL {$label}\n";
}

function operations_actor($type = 'command_center', $changes = array())
{
	$actor = array(
		'authenticated' => true,
		'user_id' => 10,
		'role' => 'dokter',
		'status' => 'aktif',
		'must_change_password' => false,
		'identity' => array(
			'valid' => true,
			'account_type' => $type,
			'is_command_center' => $type === 'command_center',
			'user_id' => 10,
			'puskesmas_code' => 'PKM01',
		),
	);
	return array_replace_recursive($actor, $changes);
}

function operations_config_state(array $overrides = array())
{
	$root = dirname(__DIR__, 3);
	$code = "define('BASEPATH',__DIR__);define('FCPATH'," . var_export($root . '/', true)
		. ");define('APPPATH'," . var_export($root . '/application/', true)
		. ");require " . var_export($root . '/application/config/config.php', true)
		. ";echo json_encode(array("
		. "'operations_enabled'=>\$config['puskesmas_operations_enabled'],"
		. "'operations_reason'=>\$config['puskesmas_operations_feature_reason'],"
		. "'role_enabled'=>\$config['role_prerequisites_enabled'],"
		. "'presence_enabled'=>\$config['nakes_presence_enabled']));";
	$environment = getenv();
	if (!is_array($environment)) {
		$environment = array();
	}
	$environment = array_merge($environment, array(
		'DOCLINC_REALTIME_CLIENT_RUNTIME_ENVIRONMENT' => 'staging',
		'DOCLINC_ROLE_PREREQUISITES_ENABLED' => 'false',
		'DOCLINC_ROLE_PREREQUISITES_ENVIRONMENT' => 'staging',
		'DOCLINC_NAKES_PRESENCE_ENABLED' => 'true',
		'DOCLINC_NAKES_PRESENCE_ENVIRONMENT' => 'staging',
		'DOCLINC_PUSKESMAS_OPERATIONS_ENABLED' => 'true',
		'DOCLINC_PUSKESMAS_OPERATIONS_ENVIRONMENT' => 'staging',
	), $overrides);
	$process = proc_open(
		array(PHP_BINARY, '-r', $code),
		array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
		$pipes,
		null,
		$environment,
		array('bypass_shell' => true)
	);
	if (!is_resource($process)) {
		return null;
	}
	$output = stream_get_contents($pipes[1]);
	stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return proc_close($process) === 0 ? json_decode($output, true) : null;
}

$policy = new Puskesmas_operations_policy();
$scope = $policy->snapshotScope(operations_actor());
operations_expect(!empty($scope['allowed']) && $scope['puskesmas_code'] === 'PKM01', 'canonical_command_center_allowed');
operations_expect(empty($policy->snapshotScope(operations_actor('personal'))['allowed']), 'personal_nakes_denied');
operations_expect(empty($policy->snapshotScope(operations_actor('command_center', array('authenticated' => false)))['allowed']), 'unauthenticated_actor_denied');
operations_expect(empty($policy->snapshotScope(operations_actor('command_center', array('role' => 'admin')))['allowed']), 'admin_actor_denied');
operations_expect(empty($policy->snapshotScope(operations_actor('command_center', array('status' => 'nonaktif')))['allowed']), 'inactive_actor_denied');
operations_expect(empty($policy->snapshotScope(operations_actor('command_center', array('must_change_password' => true)))['allowed']), 'must_change_actor_denied');
operations_expect(empty($policy->snapshotScope(operations_actor('command_center', array('identity' => array('user_id' => 99))))['allowed']), 'identity_user_mismatch_denied');
operations_expect(empty($policy->snapshotScope(operations_actor('command_center', array('identity' => array('puskesmas_code' => ''))))['allowed']), 'empty_tenant_denied');

$decoupled = operations_config_state();
operations_expect(is_array($decoupled)
	&& $decoupled['operations_enabled'] === true
	&& $decoupled['operations_reason'] === 'enabled'
	&& $decoupled['role_enabled'] === false
	&& $decoupled['presence_enabled'] === true, 'global_role_off_operations_on_when_presence_ready');
$presence_off = operations_config_state(array('DOCLINC_NAKES_PRESENCE_ENABLED' => 'false'));
operations_expect(is_array($presence_off)
	&& $presence_off['operations_enabled'] === false
	&& $presence_off['operations_reason'] === 'nakes_presence_required'
	&& $presence_off['role_enabled'] === false, 'presence_remains_required_without_enabling_global_role_gate');
$operations_off = operations_config_state(array('DOCLINC_PUSKESMAS_OPERATIONS_ENABLED' => 'false'));
operations_expect(is_array($operations_off)
	&& $operations_off['operations_enabled'] === false
	&& $operations_off['operations_reason'] === 'flag_disabled'
	&& $operations_off['role_enabled'] === false, 'own_feature_reason_preserved_and_global_role_unchanged');

$service = new Puskesmas_operations_service(null, 90);
$staff = array(
	(object) array('staff_id' => 1, 'user_id' => 101, 'display_name' => 'Nakes Satu', 'profesi' => 'Dokter', 'is_online' => 1, 'last_seen_age_seconds' => 15),
	(object) array('staff_id' => 2, 'user_id' => 102, 'display_name' => 'Nakes Dua', 'profesi' => 'Perawat', 'is_online' => 0, 'last_seen_age_seconds' => 180),
	(object) array('staff_id' => 3, 'user_id' => 103, 'display_name' => '<img onerror=alert(1)>', 'profesi' => 'Bidan', 'is_online' => 1, 'last_seen_age_seconds' => null),
);
$requests = array(
	(object) array('request_id' => 1, 'request_status' => 'Accepted', 'visit_status' => 'not_started', 'assigned_nakes_user_id' => 101, 'assignment_id' => 11, 'assignment_staff_id' => 1, 'assignment_user_id' => 101),
	(object) array('request_id' => 2, 'request_status' => 'Accepted', 'visit_status' => 'en_route', 'assigned_nakes_user_id' => 102, 'assignment_id' => null, 'assignment_staff_id' => null, 'assignment_user_id' => null),
	(object) array('request_id' => 3, 'request_status' => 'Accepted', 'visit_status' => 'arrived', 'assigned_nakes_user_id' => null, 'assignment_id' => null, 'assignment_staff_id' => null, 'assignment_user_id' => null),
	(object) array('request_id' => 4, 'request_status' => 'Accepted', 'visit_status' => 'in_service', 'assigned_nakes_user_id' => 101, 'assignment_id' => 41, 'assignment_staff_id' => 1, 'assignment_user_id' => 101),
	(object) array('request_id' => 4, 'request_status' => 'Accepted', 'visit_status' => 'in_service', 'assigned_nakes_user_id' => 101, 'assignment_id' => 42, 'assignment_staff_id' => 2, 'assignment_user_id' => 102),
	(object) array('request_id' => 5, 'request_status' => 'Accepted', 'visit_status' => 'invalid', 'assigned_nakes_user_id' => 101, 'assignment_id' => 51, 'assignment_staff_id' => 3, 'assignment_user_id' => 103),
);
$snapshot = $service->composeSnapshot('PKM01', $staff, $requests, 7, 1785643200);
$summary = $snapshot['summary'];
operations_expect($summary['pending_requests'] === 7 && $summary['accepted_requests'] === 5, 'tenant_request_counts_exact');
operations_expect($summary['unassigned_requests'] === 1 && $summary['ambiguous_assignments'] === 2, 'assignment_exceptions_exact');
operations_expect($summary['online_staff'] === 2 && $summary['offline_staff'] === 1, 'presence_counts_exact');
operations_expect($summary['busy_staff'] === 2 && $summary['available_staff'] === 1, 'workload_counts_exact');
operations_expect($summary['offline_with_active_requests'] === 1, 'offline_busy_staff_flagged');
operations_expect($summary['not_started_requests'] === 2 && $summary['en_route_requests'] === 1 && $summary['arrived_requests'] === 1 && $summary['in_service_requests'] === 1, 'visit_states_counted');
operations_expect(count($snapshot['exceptions']) === 3, 'exception_rows_bounded_and_exact');

$by_user = array();
foreach ($snapshot['staff'] as $row) {
	$by_user[$row['user_id']] = $row;
}
operations_expect($by_user[101]['workload_state'] === 'busy' && $by_user[101]['active_request_count'] === 1, 'online_assigned_staff_busy');
operations_expect($by_user[102]['workload_state'] === 'attention' && $by_user[102]['active_request_count'] === 1, 'offline_assigned_staff_attention');
operations_expect($by_user[103]['workload_state'] === 'available' && $by_user[103]['active_request_count'] === 0, 'online_unassigned_staff_available');
operations_expect($by_user[103]['display_name'] === '<img onerror=alert(1)>', 'display_text_preserved_for_contextual_escaping');

$serialized = json_encode($snapshot);
foreach (array('nik', 'no_kk', 'bpjs', 'nip', 'nomor_sip', 'no_hp', 'latitude', 'longitude', 'diagnosis', 'treatment', 'patient_name') as $forbidden) {
	operations_expect(stripos($serialized, $forbidden) === false, 'forbidden_payload_absent_' . $forbidden);
}
$allowed_staff_keys = array('user_id', 'staff_id', 'display_name', 'profession', 'is_online', 'last_seen_age_seconds', 'active_request_count', 'not_started_count', 'en_route_count', 'arrived_count', 'in_service_count', 'workload_state');
sort($allowed_staff_keys);
$actual_staff_keys = array_keys($snapshot['staff'][0]);
sort($actual_staff_keys);
operations_expect($actual_staff_keys === $allowed_staff_keys, 'staff_payload_exact_keys');

echo "PUSKESMAS_OPERATIONS_UNIT_PASSED={$passed}\n";
echo "PUSKESMAS_OPERATIONS_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
