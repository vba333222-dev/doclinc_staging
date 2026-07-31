<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
require_once dirname(__DIR__, 3) . '/application/libraries/Realtime_request_feature.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Request_realtime_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Request_realtime_delivery.php';
require_once dirname(__DIR__, 3) . '/application/libraries/First_login_gate_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Realtime_channel_policy.php';

$passed = 0; $failed = 0;
function request_expect($condition, $label) { global $passed, $failed; if ($condition) { $passed++; echo "PASS {$label}\n"; } else { $failed++; fwrite(STDERR, "FAIL {$label}\n"); } }

final class RequestUnitDatabase
{
	public $db_debug = true; public $notifications = array(); public $outbox = array(); public $fail_outbox = false; public $duplicate = false;
	private $insert_id = 0; private $error = array('code' => 0);
	public function insert($table, array $data) {
		if ($table === 'notifications') { $this->insert_id++; $this->notifications[] = $data; $this->error = array('code' => 0); return true; }
		if ($table === 'realtime_outbox') {
			if ($this->duplicate) { $this->error = array('code' => 1062); return false; }
			if ($this->fail_outbox) { $this->error = array('code' => 1205); return false; }
			$this->insert_id++; $this->outbox[] = $data; $this->error = array('code' => 0); return true;
		}
		return false;
	}
	public function insert_id() { return $this->insert_id; }
	public function error() { return $this->error; }
}

foreach (array(null, '', 'false', '0', 'off', 'no', 'malformed') as $flag) {
	request_expect(!Realtime_request_feature::resolve($flag, 'staging', 'staging', true)['enabled'], 'flag_disabled_' . md5(serialize($flag)));
}
request_expect(!Realtime_request_feature::resolve('true', 'production', 'production', true)['enabled'], 'production_disabled');
request_expect(!Realtime_request_feature::resolve('true', 'uat', 'uat', true)['enabled'], 'uat_disabled_by_staging_contract');
request_expect(!Realtime_request_feature::resolve('true', 'staging', 'staging', false)['enabled'], 'client_dependency_required');
request_expect(Realtime_request_feature::resolve('true', 'staging', 'staging', true)['enabled'], 'exact_staging_enabled');

$db = new RequestUnitDatabase();
$delivery = new Request_realtime_delivery($db, array('enabled' => true), array('enabled' => true));
$notification = array('recipient_user_id' => 101, 'recipient_role' => 'warga', 'event_type' => 'request_accepted',
	'entity_type' => 'request', 'entity_id' => '5001', 'title' => 'Synthetic', 'message' => 'Synthetic', 'is_read' => 0, 'created_at' => '2026-01-01 00:00:00');
request_expect($delivery->deliver('accepted', 5001, 5001, array('user:101', 'puskesmas:PKM01:ops'), array('PKM01'), array($notification)), 'delivery_success');
request_expect(count($db->notifications) === 1 && count($db->outbox) === 3, 'notification_and_request_events_coexist');
$events = array_map(function ($row) { return json_decode($row['payload_json'], true); }, $db->outbox);
request_expect($events[1]['event_type'] === 'request.accepted' && $events[2]['event_type'] === 'request.accepted', 'exact_lifecycle_event');
request_expect(array_keys($events[1]) === array('aggregate_id', 'audience', 'event_id', 'event_type', 'invalidation', 'version'), 'exact_payload_keys');
$serialized = json_encode($events);
foreach (array('Synthetic', 'diagnosis', 'anamnesis', 'latitude', 'longitude', 'phone', 'file_path') as $forbidden) {
	request_expect(strpos($serialized, $forbidden) === false, 'payload_forbidden_absent_' . md5($forbidden));
}
request_expect($events[1]['event_id'] === 'request.accepted:5001' && $events[1]['version'] === 1, 'stable_identity_and_envelope_version');

$duplicate_db = new RequestUnitDatabase(); $duplicate_db->duplicate = true;
request_expect((new Request_realtime_delivery($duplicate_db, array('enabled' => true), array('enabled' => false)))
	->deliver('created', 5001, 5001, array('user:101'), array()), 'duplicate_idempotency_accepted');
$failure_db = new RequestUnitDatabase(); $failure_db->fail_outbox = true;
request_expect(!(new Request_realtime_delivery($failure_db, array('enabled' => true), array('enabled' => false)))
	->deliver('created', 5001, 5001, array('user:101'), array()), 'enqueue_failure_reported');
request_expect(!(new Request_realtime_delivery(new RequestUnitDatabase(), array('enabled' => false), array('enabled' => false)))
	->deliver('created', 5001, 5001, array('user:101'), array()), 'flag_off_no_delivery');

$policy = new Request_realtime_policy();
$warga = array('authenticated' => true, 'user_id' => 101, 'role' => 'warga', 'status' => 'aktif', 'must_change_password' => false);
request_expect($policy->channel($warga) === 'user:101', 'warga_channel');
$personal = array('authenticated' => true, 'user_id' => 202, 'role' => 'dokter', 'status' => 'aktif', 'must_change_password' => false,
	'identity' => array('valid' => true, 'user_id' => 202, 'role' => 'dokter', 'user_status' => 'aktif', 'account_type' => 'personal'));
request_expect($policy->channel($personal) === 'user:202', 'personal_channel');
$command = $personal; $command['identity']['account_type'] = 'command_center'; $command['identity']['puskesmas_code'] = 'PKM01';
request_expect($policy->channel($command) === 'puskesmas:PKM01:ops', 'command_center_tenant_channel');
foreach (array(
	'anonymous' => array('authenticated' => false, 'user_id' => 101, 'role' => 'warga', 'status' => 'aktif'),
	'inactive' => array('authenticated' => true, 'user_id' => 101, 'role' => 'warga', 'status' => 'nonaktif'),
	'must_change' => array('authenticated' => true, 'user_id' => 101, 'role' => 'warga', 'status' => 'aktif', 'must_change_password' => true),
	'admin' => array('authenticated' => true, 'user_id' => 1, 'role' => 'admin', 'status' => 'aktif'),
) as $label => $actor) { request_expect(!$policy->actorAllowed($actor), $label . '_denied'); }
$unlinked = $personal; $unlinked['identity']['valid'] = false;
request_expect(!$policy->actorAllowed($unlinked), 'unlinked_denied');

$channel_policy = new Realtime_channel_policy();
$request = (object) array('request_id' => 5001, 'user_id' => 101);
request_expect($channel_policy->authorize($warga, $channel_policy->parse('request:5001'), $request), 'warga_owner_allowed');
$other_warga = $warga; $other_warga['user_id'] = 102;
request_expect(!$channel_policy->authorize($other_warga, $channel_policy->parse('request:5001'), $request), 'warga_cross_owner_denied');
$pic_access = array('valid' => true, 'can_view' => true, 'tenant_match' => true, 'ownership_source' => 'staff_assignment');
request_expect($channel_policy->authorize($personal, $channel_policy->parse('request:5001'), $request, $personal['identity'], $pic_access), 'personal_pic_allowed');
$pic_access['ownership_source'] = 'unproven';
request_expect(!$channel_policy->authorize($personal, $channel_policy->parse('request:5001'), $request, $personal['identity'], $pic_access), 'personal_non_pic_denied');
request_expect($channel_policy->authorize($command, $channel_policy->parse('puskesmas:PKM01:ops'), null, $command['identity']), 'command_center_same_tenant_allowed');
request_expect(!$channel_policy->authorize($command, $channel_policy->parse('puskesmas:PKM02:ops'), null, $command['identity']), 'command_center_cross_tenant_denied');

$gate = new First_login_gate_policy();
request_expect(!$gate->allowed('realtime_requests', 'snapshot') && $gate->jsonResponse('realtime_requests', 'snapshot', false, '', ''), 'first_login_json_denial');
$root = dirname(__DIR__, 3);
$controller = file_get_contents($root . '/application/controllers/Realtime_requests.php');
request_expect(strpos($controller, "set_header('Cache-Control: no-store") !== false, 'snapshot_no_store');
request_expect(strpos($controller, 'new Request_realtime_snapshot($this->db, 100)') !== false, 'snapshot_bounded');
request_expect(strpos($controller, "respond(400, 'query_not_allowed')") !== false, 'snapshot_arbitrary_query_rejected');
$snapshot_source = file_get_contents($root . '/application/libraries/Request_realtime_snapshot.php');
request_expect(strpos($snapshot_source, "order_by('requests.request_id', 'DESC')") !== false, 'snapshot_order_deterministic');
$transaction_sources = array(
	'create' => $root . '/application/modules/konsultasi/models/Konsultasi_m.php',
	'warga_cancel' => $root . '/application/modules/home/models/Home_m.php',
	'nakes_transition' => $root . '/application/modules/home_nakes/models/Home_nakes_m.php',
	'completion' => $root . '/application/modules/konsultasi_nakes/models/Konsultasi_nakes_m.php',
);
foreach ($transaction_sources as $label => $file) {
	$source = file_get_contents($file);
	request_expect(strpos($source, 'doclinc_request_realtime_delivery($this->db)') !== false, $label . '_uses_caller_connection');
}
$assignment_source = file_get_contents($transaction_sources['nakes_transition']);
request_expect(strpos($assignment_source, "? 'pic_reassigned' : 'pic_assigned'") !== false
	&& strpos($assignment_source, '$transition, $request_id, $new_assignment_id') !== false
	&& strpos($assignment_source, "'pic_cleared', \$request_id, (int) \$previous_assignment->assignment_id") !== false, 'pic_idempotency_uses_persisted_assignment_identity');
request_expect(strpos($assignment_source, 'locked_command_center_context') !== false
	&& strpos($assignment_source, 'SELECT userId, role, status, remark, must_change_password') !== false
	&& strpos($assignment_source, "(string) \$puskesmas->status !== 'aktif'") !== false
	&& strpos($assignment_source, 'ORDER BY userId ASC LIMIT 1 FOR UPDATE') !== false,
	'post_lock_identity_revalidation_contract');
request_expect(strpos($assignment_source, 'count($active_assignments) > 1') !== false
	&& strpos($assignment_source, "'ambiguous_active_assignments'") !== false,
	'ambiguous_active_assignment_fails_closed');
$model_integration_source = file_get_contents(__DIR__ . '/model_integration.php');
foreach (array('save_konsultasi(', 'accept_request(', 'cancel_request(', '$home_nakes->cancel_request(',
	'save_konsultasi_nakes(', 'assign_staff_to_request(', 'clear_staff_assignment(') as $method_call) {
	request_expect(strpos($model_integration_source, $method_call) !== false, 'actual_model_call_covered_' . md5($method_call));
}
request_expect(strpos($model_integration_source, "'noncanonical' => function") !== false
	&& strpos($model_integration_source, 'ambiguous_assignment_zero_mutation') !== false
	&& strpos($model_integration_source, 'actual_enqueue_failure_rolls_back_domain_notification_outbox') !== false,
	'model_integration_negative_contracts_present');
$controller_orchestration_contracts = array(
	'application/modules/konsultasi/controllers/Konsultasi.php' => 'requestCreated(',
	'application/modules/home/controllers/Home.php' => 'requestCancelledByOwner(',
	'application/modules/home_nakes/controllers/Home_nakes.php' => array('requestAccepted(', 'requestCancelledByCommandCenter('),
	'application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php' => 'requestCompleted(',
);
foreach ($controller_orchestration_contracts as $relative_file => $methods) {
	$source = file_get_contents($root . '/' . $relative_file);
	foreach ((array) $methods as $method) {
		request_expect(strpos($source, 'doclinc_request_transition_orchestrator()') !== false
			&& strpos($source, $method) !== false
			&& strpos($source, "json_encode(\$orchestration['response'])") !== false,
			'controller_delegates_transition_orchestration_' . md5($relative_file . $method));
	}
}
request_expect(strpos($model_integration_source, "'compatibility' => array('client' => true, 'notification' => true)") !== false
	&& strpos($model_integration_source, "'full_legacy' => array('client' => false, 'notification' => false)") !== false,
	'flag_relationship_matrix_contract');
$contract_source = file_get_contents($root . '/application/libraries/Realtime_outbox_contract.php');
foreach (array('request.created','request.accepted','request.cancelled','request.completed','request.pic_assigned','request.pic_reassigned','request.pic_cleared') as $event_type) {
	request_expect(strpos($contract_source, "'{$event_type}'") !== false, 'event_allowlisted_' . md5($event_type));
}
$views = array($root . '/application/modules/home/views/home_v.php', $root . '/application/modules/home_nakes/views/home_nakes_v.php');
foreach ($views as $view) {
	$source = file_get_contents($view);
	request_expect(strpos($source, 'is_array($request_realtime_bootstrap)') !== false && strpos($source, 'assets/js/doclinc-requests.js') !== false, 'asset_flag_guard_' . md5($view));
}

echo "REALTIME_REQUEST_UNIT_PASSED={$passed}\nREALTIME_REQUEST_UNIT_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
