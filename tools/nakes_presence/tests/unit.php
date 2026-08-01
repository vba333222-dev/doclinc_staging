<?php
define('BASEPATH', dirname(__DIR__, 3) . '/system/');

require_once dirname(__DIR__, 3) . '/application/libraries/Nakes_presence_policy.php';

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

$service_source = file_get_contents(dirname(__DIR__, 3) . '/application/libraries/Nakes_presence_service.php');
$main_controller = file_get_contents(dirname(__DIR__, 3) . '/application/modules/home_nakes/controllers/Home_nakes.php');
$admin_controller = file_get_contents(dirname(__DIR__, 3) . '/admin_menu/application/modules/home/controllers/Home.php');
$client_source = file_get_contents(dirname(__DIR__, 3) . '/assets/js/doclinc-nakes-presence.js');
$main_config = file_get_contents(dirname(__DIR__, 3) . '/application/config/config.php');
$admin_config = file_get_contents(dirname(__DIR__, 3) . '/admin_menu/application/config/config.php');
$first_login = file_get_contents(dirname(__DIR__, 3) . '/application/libraries/First_login_gate_policy.php');
$integration_source = file_get_contents(__DIR__ . '/integration.php');
presence_expect(strpos($service_source, 'FOR UPDATE') !== false && strpos($service_source, 'write_throttle_seconds') !== false, 'database_write_throttled_under_lock');
presence_expect(strpos($service_source, "ps.kode_pkm', (string) \$scope['puskesmas_code']") !== false, 'tenant_filter_applied');
presence_expect(strpos($service_source, 'no_hp') === false && strpos($service_source, 'nomor_sip') === false, 'snapshot_excludes_contact_fields');
presence_expect(strpos($service_source, 'latitude') === false && strpos($service_source, 'diagnosis') === false, 'snapshot_excludes_location_and_clinical_fields');
presence_expect(strpos($main_controller, "method(TRUE) !== 'POST'") !== false && strpos($main_controller, 'presence_actor()') !== false, 'heartbeat_post_and_fresh_actor_contract');
presence_expect(strpos($admin_controller, 'Nakes_presence_service') !== false && strpos($admin_controller, '->touch(') === false, 'admin_read_only_contract');
presence_expect(strpos($client_source, "visibilityState !== 'hidden'") !== false && strpos($client_source, "addEventListener('pagehide'") !== false, 'browser_lifecycle_contract');
presence_expect(strpos($client_source, 'setInterval(heartbeat') !== false && strpos($client_source, 'setInterval(snapshot') !== false, 'bounded_polling_contract');
presence_expect(strpos($main_config, "getenv('DOCLINC_NAKES_PRESENCE_ENABLED')") !== false
	&& strpos($main_config, 'Doclinc_feature_flags::resolve') !== false, 'main_feature_default_off_resolver_contract');
presence_expect(strpos($admin_config, "getenv('DOCLINC_NAKES_PRESENCE_ENABLED')") !== false
	&& strpos($admin_config, "'resolver_unavailable'") !== false, 'admin_feature_fail_closed_contract');
presence_expect(strpos($first_login, "'presence_heartbeat', 'presence_snapshot'") !== false, 'first_login_json_gate_contract');
presence_expect(strpos($integration_source, 'second_heartbeat_throttled_without_write') !== false
	&& strpos($integration_source, 'tenant_snapshot_cannot_cross_puskesmas') !== false
	&& strpos($integration_source, 'snapshot_exact_safe_keys') !== false, 'official_mariadb_integration_contract');

echo "NAKES_PRESENCE_UNIT_PASSED={$passed}\n";
echo "NAKES_PRESENCE_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
