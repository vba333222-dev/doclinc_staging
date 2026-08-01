<?php

if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 3) . '/system/');
}
require_once dirname(__DIR__, 3) . '/application/libraries/Realtime_notification_feature.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Notification_realtime_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Notification_delivery_service.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Realtime_channel_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/First_login_gate_policy.php';
require_once dirname(__DIR__, 3) . '/application/helpers/notification_helper.php';

$passed = 0;
$failed = 0;

function notification_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo 'PASS ' . $label . "\n";
		return;
	}
	$failed++;
	fwrite(STDERR, 'FAIL ' . $label . "\n");
}

final class NotificationUnitDatabase
{
	public $db_debug = true;
	public $notifications = array();
	public $outbox = array();
	public $fail_outbox = false;
	public $duplicate_outbox = false;
	private $snapshot;
	private $insert_id = 0;
	private $error = array('code' => 0);
	private $transaction_ok = true;

	public function trans_begin()
	{
		$this->snapshot = array($this->notifications, $this->outbox);
		$this->transaction_ok = true;
		return true;
	}

	public function trans_status()
	{
		return $this->transaction_ok;
	}

	public function trans_commit()
	{
		$this->snapshot = null;
		return true;
	}

	public function trans_rollback()
	{
		if (is_array($this->snapshot)) {
			$this->notifications = $this->snapshot[0];
			$this->outbox = $this->snapshot[1];
		}
		$this->snapshot = null;
		return true;
	}

	public function insert($table, array $data)
	{
		if ($table === 'notifications') {
			$this->insert_id = count($this->notifications) + 1;
			$data['notification_id'] = $this->insert_id;
			$this->notifications[] = $data;
			$this->error = array('code' => 0);
			return true;
		}
		if ($table === 'realtime_outbox') {
			if ($this->duplicate_outbox) {
				$this->error = array('code' => 1062);
				return false;
			}
			if ($this->fail_outbox) {
				$this->transaction_ok = false;
				$this->error = array('code' => 1205);
				return false;
			}
			$this->insert_id = count($this->outbox) + 1;
			$data['outbox_id'] = $this->insert_id;
			$this->outbox[] = $data;
			$this->error = array('code' => 0);
			return true;
		}
		return false;
	}

	public function insert_id()
	{
		return $this->insert_id;
	}

	public function error()
	{
		return $this->error;
	}
}

final class NotificationVisibilityRecorder
{
	public $conditions = array();
	public function where($condition, $value = null, $escape = null)
	{
		$this->conditions[] = (string) $condition;
		return $this;
	}
}

function notification_data()
{
	return array(
		'recipient_user_id' => 101,
		'recipient_role' => 'warga',
		'recipient_puskesmas_code' => null,
		'actor_user_id' => 202,
		'event_type' => 'request_accepted',
		'entity_type' => 'request',
		'entity_id' => '1001',
		'title' => 'Synthetic notification',
		'message' => 'Synthetic message',
		'is_read' => 0,
		'created_at' => '2026-01-01 00:00:00',
	);
}

foreach (array(null, '', 'malformed', 'false', '0', 'off', 'no') as $flag) {
	$state = Realtime_notification_feature::resolve($flag, 'staging', 'staging', true);
	notification_expect($state['enabled'] === false, 'flag_disabled_' . substr(hash('sha256', serialize($flag)), 0, 8));
}
notification_expect(Realtime_notification_feature::resolve('true', 'staging', 'production', true)['enabled'] === false, 'production_disabled');
notification_expect(Realtime_notification_feature::resolve('true', 'development', 'development', true)['enabled'] === false, 'development_disabled');
notification_expect(Realtime_notification_feature::resolve('true', 'test', 'test', true)['enabled'] === false, 'test_disabled');
notification_expect(Realtime_notification_feature::resolve('true', 'uat', 'staging', true)['enabled'] === false, 'environment_mismatch_disabled');
notification_expect(Realtime_notification_feature::resolve('true', 'staging', 'staging', false)['enabled'] === false, 'global_client_flag_required');
notification_expect(Realtime_notification_feature::resolve('true', 'staging', 'staging', true)['enabled'] === true, 'staging_enabled_when_both_flags_valid');

$db = new NotificationUnitDatabase();
$delivery = new Notification_delivery_service($db, array('enabled' => true));
$notification_id = $delivery->create(notification_data());
notification_expect($notification_id === 1 && count($db->notifications) === 1 && count($db->outbox) === 1, 'notification_outbox_atomic_commit');
$event = json_decode($db->outbox[0]['payload_json'], true);
notification_expect(array_keys($event) === array('aggregate_id', 'audience', 'event_id', 'event_type', 'invalidation', 'version'), 'event_exact_sanitized_keys');
notification_expect($event['audience'] === 'user:101' && $event['event_type'] === 'notification.created'
	&& $event['invalidation'] === 'notifications' && $event['aggregate_id'] === '1', 'event_contract_values');
$serialized_event = json_encode($event);
foreach (array('Synthetic notification', 'Synthetic message', 'diagnosis', 'latitude', 'file_path') as $forbidden) {
	notification_expect(strpos($serialized_event, $forbidden) === false, 'event_content_absent_' . substr(hash('sha256', $forbidden), 0, 8));
}

$failure_db = new NotificationUnitDatabase();
$failure_db->fail_outbox = true;
$failure = (new Notification_delivery_service($failure_db, array('enabled' => true)))->create(notification_data());
notification_expect($failure === false && count($failure_db->notifications) === 0 && count($failure_db->outbox) === 0, 'enqueue_failure_rolls_back_notification');

$caller_db = new NotificationUnitDatabase();
$caller_db->trans_begin();
$inside = (new Notification_delivery_service($caller_db, array('enabled' => true)))->createWithinTransaction(notification_data());
$caller_db->trans_rollback();
notification_expect($inside === 1 && count($caller_db->notifications) === 0 && count($caller_db->outbox) === 0, 'caller_rollback_removes_both_rows');

$duplicate_db = new NotificationUnitDatabase();
$duplicate_db->duplicate_outbox = true;
$duplicate = (new Notification_delivery_service($duplicate_db, array('enabled' => true)))->create(notification_data());
notification_expect($duplicate === 1 && count($duplicate_db->notifications) === 1 && count($duplicate_db->outbox) === 0, 'duplicate_idempotency_treated_as_delivered');

$legacy_db = new NotificationUnitDatabase();
$legacy = (new Notification_delivery_service($legacy_db, array('enabled' => false)))->create(notification_data());
notification_expect($legacy === 1 && count($legacy_db->notifications) === 1 && count($legacy_db->outbox) === 0, 'flag_off_preserves_notification_without_outbox');

$policy = new Notification_realtime_policy();
$warga = array('authenticated' => true, 'user_id' => 101, 'role' => 'warga', 'status' => 'aktif', 'must_change_password' => false);
notification_expect($policy->actorAllowed($warga), 'warga_active_allowed');
foreach (array(
	'anonymous' => array('authenticated' => false, 'user_id' => 101, 'role' => 'warga', 'status' => 'aktif'),
	'inactive' => array('authenticated' => true, 'user_id' => 101, 'role' => 'warga', 'status' => 'nonaktif'),
	'must_change' => array('authenticated' => true, 'user_id' => 101, 'role' => 'warga', 'status' => 'aktif', 'must_change_password' => true),
	'admin' => array('authenticated' => true, 'user_id' => 1, 'role' => 'admin', 'status' => 'aktif'),
) as $label => $actor) {
	notification_expect(!$policy->actorAllowed($actor), $label . '_denied');
}
$personal_identity = array('valid' => true, 'user_id' => 202, 'role' => 'dokter', 'user_status' => 'aktif', 'account_type' => 'personal');
$personal = array('authenticated' => true, 'user_id' => 202, 'role' => 'dokter', 'status' => 'aktif', 'must_change_password' => false, 'identity' => $personal_identity);
notification_expect($policy->actorAllowed($personal), 'personal_nakes_allowed');
$command_identity = $personal_identity;
$command_identity['account_type'] = 'command_center';
$command = $personal;
$command['identity'] = $command_identity;
notification_expect($policy->actorAllowed($command), 'command_center_allowed');
$unlinked = $personal;
$unlinked['identity']['valid'] = false;
notification_expect(!$policy->actorAllowed($unlinked), 'unlinked_staff_denied');

$channel_policy = new Realtime_channel_policy();
$parsed_user = $channel_policy->parse('user:101');
notification_expect($channel_policy->authorize($warga, $parsed_user), 'warga_own_user_channel_allowed');
notification_expect(!$channel_policy->authorize($warga, $channel_policy->parse('user:102')), 'warga_other_user_channel_denied');
$request = (object) array('request_id' => 1001, 'user_id' => 101);
notification_expect($channel_policy->authorize($warga, $channel_policy->parse('request:1001'), $request), 'warga_owner_request_channel_allowed');
$other_warga = $warga;
$other_warga['user_id'] = 102;
notification_expect(!$channel_policy->authorize($other_warga, $channel_policy->parse('request:1001'), $request), 'warga_cross_owner_request_denied');
$visibility = new NotificationVisibilityRecorder();
doclinc_apply_notification_visibility($visibility, array('role' => 'warga', 'user_id' => 101));
notification_expect(count($visibility->conditions) === 1
	&& strpos($visibility->conditions[0], 'notification_request.user_id = 101') !== false, 'warga_snapshot_request_owner_filter');
$personal_request_access = array('valid' => true, 'can_view' => true, 'tenant_match' => true, 'ownership_source' => 'staff_assignment');
notification_expect($channel_policy->authorize($personal, $channel_policy->parse('request:1001'), $request, $personal_identity, $personal_request_access), 'personal_pic_request_allowed');
$personal_request_access['ownership_source'] = 'unproven';
notification_expect(!$channel_policy->authorize($personal, $channel_policy->parse('request:1001'), $request, $personal_identity, $personal_request_access), 'personal_non_pic_request_denied');
$command_actor = $command;
$command_actor['identity']['puskesmas_code'] = 'PKM01';
notification_expect($channel_policy->authorize($command_actor, $channel_policy->parse('puskesmas:PKM01:ops'), null, $command_actor['identity']), 'command_center_tenant_channel_allowed');
notification_expect(!$channel_policy->authorize($command_actor, $channel_policy->parse('puskesmas:PKM02:ops'), null, $command_actor['identity']), 'command_center_cross_tenant_denied');
notification_expect($channel_policy->parse('unknown:101') === null, 'unknown_namespace_denied');
notification_expect(!$channel_policy->authorize(array('authenticated' => true, 'user_id' => 1, 'role' => 'admin', 'status' => 'aktif'), $channel_policy->parse('user:1')), 'admin_subscription_not_added');

$controller = file_get_contents(dirname(__DIR__, 3) . '/application/modules/notifikasi/controllers/Notifikasi.php');
notification_expect(strpos($controller, "set_header('Cache-Control: no-store") !== false, 'snapshot_no_store_header');
notification_expect(strpos($controller, 'min($limit, 50)') !== false, 'snapshot_limit_bounded');
notification_expect(strpos($controller, "order_by('notifications.created_at', 'DESC')") === false, 'controller_delegates_ordering');
$helper = file_get_contents(dirname(__DIR__, 3) . '/application/helpers/notification_helper.php');
notification_expect(strpos($helper, "order_by('notifications.created_at', 'DESC')") !== false
	&& strpos($helper, "order_by('notifications.notification_id', 'DESC')") !== false, 'snapshot_order_deterministic');
$gate_policy = new First_login_gate_policy();
notification_expect(!$gate_policy->allowed('notifikasi', 'snapshot')
	&& $gate_policy->jsonResponse('notifikasi', 'snapshot', false, '', ''), 'first_login_snapshot_json_denial');
$asset_order = array(
	'assets/vendor/centrifuge/5.7.0/centrifuge.js',
	'assets/js/doclinc-realtime-client.js',
	'assets/js/doclinc-notifications.js',
);
foreach (array('home/views/home_v.php', 'home_nakes/views/home_nakes_v.php') as $view_path) {
	$view = file_get_contents(dirname(__DIR__, 3) . '/application/modules/' . $view_path);
	$positions = array_map(function ($asset) use ($view) { return strpos($view, $asset); }, $asset_order);
	notification_expect($positions[0] !== false && $positions[0] < $positions[1] && $positions[1] < $positions[2], 'asset_load_order_' . md5($view_path));
	notification_expect(strpos($view, 'is_array($notification_realtime_bootstrap)') !== false, 'assets_conditionally_loaded_' . md5($view_path));
}
$browser_source = file_get_contents(dirname(__DIR__, 3) . '/assets/js/doclinc-notifications.js');
notification_expect(strpos($browser_source, "setAttribute('aria-label'") !== false
	&& strpos($browser_source, "setAttribute('aria-pressed'") !== false, 'sound_control_accessible_state');
notification_expect(strpos($helper, "'sound_url' => \$base_path . '/assets/audio/doclinc-notification.wav'") !== false
	&& strpos($browser_source, 'this.createAudio(this.config.sound_url)') !== false,
	'natural_local_notification_sound_contract');
notification_expect(strpos($browser_source, "doclinc:notifications:new") !== false
	&& strpos($browser_source, 'onNewNotifications(newNotifications.slice())') !== false,
	'new_notification_event_bridge_contract');
$call_model = file_get_contents(dirname(__DIR__, 3) . '/application/modules/chat/models/Call_session_m.php');
$nakes_controller = file_get_contents(dirname(__DIR__, 3) . '/application/modules/home_nakes/controllers/Home_nakes.php');
notification_expect(strpos($call_model, '$existing->was_created = false;') !== false
	&& strpos($call_model, '$created->was_created = true;') !== false
	&& strpos($nakes_controller, 'if (!empty($call->was_created))') !== false
	&& strpos($nakes_controller, "'incoming_call'") !== false,
	'incoming_call_notification_created_once_per_new_session_contract');

echo 'REALTIME_NOTIFICATION_UNIT_PASSED=' . $passed . "\n";
echo 'REALTIME_NOTIFICATION_UNIT_FAILED=' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
