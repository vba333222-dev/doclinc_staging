<?php
define('BASEPATH', dirname(__DIR__, 3) . '/system/');
define('FCPATH', dirname(__DIR__, 3) . DIRECTORY_SEPARATOR);

require_once dirname(__DIR__, 3) . '/application/libraries/Nakes_presence_policy.php';

class Presence_client_test_config
{
	private $enabled;

	public function __construct($enabled)
	{
		$this->enabled = $enabled;
	}

	public function item($key)
	{
		if ($key === 'nakes_presence_enabled') {
			return $this->enabled;
		}
		if ($key === 'nakes_presence_heartbeat_seconds') {
			return 30;
		}
		return null;
	}
}

class Presence_client_test_ci
{
	public $config;

	public function __construct($enabled)
	{
		$this->config = new Presence_client_test_config($enabled);
	}
}

$presence_client_test_ci = new Presence_client_test_ci(true);
if (!function_exists('get_instance')) {
	function &get_instance()
	{
		global $presence_client_test_ci;
		return $presence_client_test_ci;
	}
}
if (!function_exists('base_url')) {
	function base_url($path = '')
	{
		return 'https://example.test/' . ltrim((string) $path, '/');
	}
}

require_once dirname(__DIR__, 3) . '/application/helpers/nakes_presence_client_helper.php';

$passed = 0;
$failed = 0;
function presence_expect($condition, $name)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$name}\n";
		return;
	}
	$failed++;
	echo "FAIL {$name}\n";
}

$policy = new Nakes_presence_policy();
$personal = array(
	'authenticated' => true,
	'user_id' => 201,
	'role' => 'dokter',
	'status' => 'aktif',
	'must_change_password' => false,
	'identity' => array(
		'valid' => true,
		'account_type' => 'personal',
		'user_id' => 201,
		'staff_id' => 31,
		'staff_status' => 'aktif',
		'puskesmas_code' => 'PKM01',
	),
);
$command = $personal;
$command['user_id'] = 10;
$command['identity'] = array(
	'valid' => true,
	'account_type' => 'command_center',
	'user_id' => 10,
	'is_command_center' => true,
	'puskesmas_code' => 'PKM01',
);

presence_expect($policy->heartbeatAllowed($personal), 'personal_heartbeat_allowed');
presence_expect(!$policy->heartbeatAllowed($command), 'command_center_heartbeat_denied');
$inactive = $personal;
$inactive['status'] = 'nonaktif';
presence_expect(!$policy->heartbeatAllowed($inactive), 'inactive_heartbeat_denied');
$must_change = $personal;
$must_change['must_change_password'] = true;
presence_expect(!$policy->heartbeatAllowed($must_change), 'must_change_heartbeat_denied');
$unlinked = $personal;
$unlinked['identity']['valid'] = false;
presence_expect(!$policy->heartbeatAllowed($unlinked), 'unlinked_heartbeat_denied');
$cross_identity = $personal;
$cross_identity['identity']['user_id'] = 202;
presence_expect(!$policy->heartbeatAllowed($cross_identity), 'cross_identity_heartbeat_denied');

$tenant_scope = $policy->snapshotScope($command);
presence_expect(!empty($tenant_scope['allowed']) && $tenant_scope['scope'] === 'tenant' && $tenant_scope['puskesmas_code'] === 'PKM01', 'command_center_tenant_snapshot');
presence_expect(empty($policy->snapshotScope($personal)['allowed']), 'personal_snapshot_denied');
presence_expect($policy->snapshotScope(array('authenticated' => true, 'role' => 'admin', 'status' => 'aktif', 'must_change_password' => false))['scope'] === 'all', 'admin_all_snapshot');
presence_expect(empty($policy->snapshotScope(array('authenticated' => false, 'role' => 'admin', 'status' => 'aktif'))['allowed']), 'anonymous_admin_denied');
presence_expect(empty($policy->snapshotScope(array('authenticated' => true, 'role' => 'admin', 'status' => 'nonaktif'))['allowed']), 'inactive_admin_denied');

$personal_bootstrap = doclinc_nakes_presence_client_bootstrap($personal['identity']);
$command_monitor_bootstrap = doclinc_nakes_presence_client_bootstrap($command['identity'], true);
$command_work_bootstrap = doclinc_nakes_presence_client_bootstrap($command['identity']);
presence_expect($personal_bootstrap['enabled'] === true && $personal_bootstrap['mode'] === 'heartbeat', 'personal_client_heartbeat_mode');
presence_expect($command_monitor_bootstrap['enabled'] === true && $command_monitor_bootstrap['mode'] === 'monitor', 'command_center_monitor_mode');
presence_expect($command_work_bootstrap['enabled'] === false && $command_work_bootstrap['mode'] === 'disabled', 'command_center_work_surface_no_heartbeat');
$presence_client_test_ci->config = new Presence_client_test_config(false);
$disabled_bootstrap = doclinc_nakes_presence_client_bootstrap($personal['identity']);
presence_expect($disabled_bootstrap['enabled'] === false && $disabled_bootstrap['mode'] === 'disabled', 'disabled_client_emits_nothing');
$presence_client_test_ci->config = new Presence_client_test_config(true);

$asset_fixture = tempnam(sys_get_temp_dir(), 'doclinc-presence-');
$asset_fixture_ready = is_string($asset_fixture) && file_put_contents($asset_fixture, 'presence-client-a') !== false;
$asset_version_a = $asset_fixture_ready ? doclinc_nakes_presence_asset_version($asset_fixture) : '';
$asset_version_a_repeat = $asset_fixture_ready ? doclinc_nakes_presence_asset_version($asset_fixture) : '';
$asset_fixture_changed = $asset_fixture_ready && file_put_contents($asset_fixture, 'presence-client-b') !== false;
$asset_version_b = $asset_fixture_changed ? doclinc_nakes_presence_asset_version($asset_fixture) : '';
if (is_string($asset_fixture) && is_file($asset_fixture)) {
	unlink($asset_fixture);
}
presence_expect($asset_fixture_ready && preg_match('/\A[a-f0-9]{64}\z/', $asset_version_a) === 1, 'presence_asset_version_is_content_hash');
presence_expect($asset_version_a === $asset_version_a_repeat, 'presence_asset_version_stable_while_unchanged');
presence_expect($asset_fixture_changed && $asset_version_b !== $asset_version_a, 'presence_asset_version_changes_with_content');
$presence_asset_url = doclinc_nakes_presence_asset_url();
presence_expect(strpos($presence_asset_url, 'assets/js/doclinc-nakes-presence.js?v=') !== false
	&& substr($presence_asset_url, -64) === doclinc_nakes_presence_asset_version(), 'presence_asset_url_uses_deployed_content_hash');

$service_source = file_get_contents(dirname(__DIR__, 3) . '/application/libraries/Nakes_presence_service.php');
$main_controller = file_get_contents(dirname(__DIR__, 3) . '/application/modules/home_nakes/controllers/Home_nakes.php');
$admin_controller = file_get_contents(dirname(__DIR__, 3) . '/admin_menu/application/modules/home/controllers/Home.php');
$client_source = file_get_contents(dirname(__DIR__, 3) . '/assets/js/doclinc-nakes-presence.js');
$main_config = file_get_contents(dirname(__DIR__, 3) . '/application/config/config.php');
$admin_config = file_get_contents(dirname(__DIR__, 3) . '/admin_menu/application/config/config.php');
$first_login = file_get_contents(dirname(__DIR__, 3) . '/application/libraries/First_login_gate_policy.php');
$integration_source = file_get_contents(__DIR__ . '/integration.php');
$client_helper = file_get_contents(dirname(__DIR__, 3) . '/application/helpers/nakes_presence_client_helper.php');
$runtime_view = file_get_contents(dirname(__DIR__, 3) . '/application/views/nakes_presence_runtime_v.php');
$consultation_controller = file_get_contents(dirname(__DIR__, 3) . '/application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php');
$consultation_view = file_get_contents(dirname(__DIR__, 3) . '/application/modules/konsultasi_nakes/views/konsultasi_nakes_v.php');
$chat_controller = file_get_contents(dirname(__DIR__, 3) . '/application/modules/chat/controllers/Chat.php');
$chat_view = file_get_contents(dirname(__DIR__, 3) . '/application/modules/chat/views/thread_v.php');
$home_view = file_get_contents(dirname(__DIR__, 3) . '/application/modules/home/views/home_v.php');
presence_expect(strpos($service_source, 'FOR UPDATE') !== false && strpos($service_source, 'write_throttle_seconds') !== false, 'database_write_throttled_under_lock');
presence_expect(strpos($service_source, 'trans_active()') === false
	&& strpos($service_source, 'if (!$this->db->trans_begin())') !== false, 'ci3_balanced_transaction_level_contract');
presence_expect(strpos($service_source, "ps.kode_pkm', (string) \$scope['puskesmas_code']") !== false, 'tenant_filter_applied');
presence_expect(strpos($service_source, 'no_hp') === false && strpos($service_source, 'nomor_sip') === false, 'snapshot_excludes_contact_fields');
presence_expect(strpos($service_source, 'latitude') === false && strpos($service_source, 'diagnosis') === false, 'snapshot_excludes_location_and_clinical_fields');
presence_expect(strpos($main_controller, "method(TRUE) !== 'POST'") !== false && strpos($main_controller, 'presence_actor()') !== false, 'heartbeat_post_and_fresh_actor_contract');
presence_expect(strpos($admin_controller, 'Nakes_presence_service') !== false && strpos($admin_controller, '->touch(') === false, 'admin_read_only_contract');
presence_expect(strpos($client_source, 'if (stopped || cachePaused || heartbeatPending)') !== false
	&& strpos($client_source, 'if (stopped || heartbeatPending || !visible())') === false
	&& strpos($client_source, "addEventListener('pagehide'") !== false
	&& strpos($client_source, "addEventListener('pageshow'") !== false, 'browser_lifecycle_contract');
presence_expect(strpos($client_source, 'setInterval(heartbeat') !== false && strpos($client_source, 'setInterval(snapshot') !== false, 'bounded_polling_contract');
presence_expect(strpos($main_controller, 'doclinc_nakes_presence_client_bootstrap($identity_context, true)') !== false
	&& strpos($consultation_controller, 'doclinc_nakes_presence_client_bootstrap($identity_context)') !== false
	&& strpos($chat_controller, "\$current_role === 'dokter'") !== false
	&& substr_count($client_helper, "base_url('assets/js/doclinc-nakes-presence.js')") === 1, 'shared_presence_bootstrap_contract');
presence_expect(strpos($runtime_view, 'doclinc_nakes_presence_asset_url()') !== false
	&& strpos($client_helper, "hash_file('sha256', \$path)") !== false
	&& strpos($client_helper, 'uniqid(') === false
	&& strpos($client_helper, 'random_bytes(') === false
	&& strpos($client_helper, 'time()') === false, 'presence_asset_cache_version_is_deterministic');
presence_expect(strpos($main_controller, 'doclinc_nakes_presence_client_bootstrap($identity_context, true)') !== false
	&& strpos($consultation_view, "load->view('nakes_presence_runtime_v'") !== false
	&& strpos($chat_view, "load->view('nakes_presence_runtime_v'") !== false, 'personal_nakes_surface_coverage');
presence_expect(strpos($client_helper, "\$account_type === 'personal'") !== false
	&& strpos($client_helper, "\$account_type === 'command_center'") !== false
	&& strpos($home_view, 'nakes_presence_runtime_v') === false
	&& strpos($admin_controller, 'doclinc_nakes_presence_client_bootstrap') === false, 'warga_admin_do_not_emit_personal_heartbeat');
presence_expect(strpos($main_config, "\$config['nakes_presence_write_throttle_seconds'] = 45;") !== false
	&& strpos($main_config, "\$config['nakes_presence_online_timeout_seconds'] = 90;") !== false, 'server_timing_contract_unchanged');
presence_expect(strpos($main_config, "getenv('DOCLINC_NAKES_PRESENCE_ENABLED')") !== false
	&& strpos($main_config, 'Doclinc_feature_flags::resolve') !== false, 'main_feature_default_off_resolver_contract');
presence_expect(strpos($admin_config, "getenv('DOCLINC_NAKES_PRESENCE_ENABLED')") !== false
	&& strpos($admin_config, "'resolver_unavailable'") !== false, 'admin_feature_fail_closed_contract');
presence_expect(strpos($first_login, "'presence_heartbeat', 'presence_snapshot'") !== false, 'first_login_json_gate_contract');
presence_expect(strpos($integration_source, 'second_heartbeat_throttled_without_write') !== false
	&& strpos($integration_source, 'nested_success_keeps_caller_transaction_open') !== false
	&& strpos($integration_source, 'caller_rollback_after_nested_failure_is_effective') !== false
	&& strpos($integration_source, 'tenant_snapshot_cannot_cross_puskesmas') !== false
	&& strpos($integration_source, 'snapshot_exact_safe_keys') !== false, 'official_mariadb_integration_contract');

echo "NAKES_PRESENCE_UNIT_PASSED={$passed}\n";
echo "NAKES_PRESENCE_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
