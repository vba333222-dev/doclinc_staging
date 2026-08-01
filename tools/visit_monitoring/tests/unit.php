<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
require_once dirname(__DIR__, 3) . '/application/libraries/Visit_monitoring_policy.php';

$passed = 0; $failed = 0;
function visit_monitor_expect($condition, $label) {
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; fwrite(STDERR, "FAIL {$label}\n");
}

$request = (object) array('request_id' => 5001, 'request_status' => 'Accepted');
$command = array('valid' => true, 'account_type' => 'command_center', 'user_id' => 10);
$personal = array('valid' => true, 'account_type' => 'personal', 'user_id' => 201);
$monitor = Visit_monitoring_policy::resolve($command, array('can_view' => true, 'can_handle' => false, 'tenant_match' => true), $request);
visit_monitor_expect($monitor === array('allowed' => true, 'can_update' => false, 'mode' => 'monitor'), 'same_tenant_command_center_read_only');
$operator = Visit_monitoring_policy::resolve($personal, array('can_view' => true, 'can_handle' => true, 'tenant_match' => true), $request);
visit_monitor_expect($operator === array('allowed' => true, 'can_update' => true, 'mode' => 'operator'), 'assigned_personal_operator');
$centerOperator = Visit_monitoring_policy::resolve($command, array('can_view' => true, 'can_handle' => true, 'tenant_match' => true), $request);
visit_monitor_expect($centerOperator['allowed'] && $centerOperator['can_update'] && $centerOperator['mode'] === 'operator', 'unassigned_command_center_legacy_operator_preserved');
visit_monitor_expect(!Visit_monitoring_policy::resolve($command, array('can_view' => true, 'can_handle' => false, 'tenant_match' => false), $request)['allowed'], 'cross_tenant_denied');
visit_monitor_expect(!Visit_monitoring_policy::resolve(array('valid' => false, 'account_type' => 'command_center'), array('can_view' => true, 'tenant_match' => true), $request)['allowed'], 'invalid_identity_denied');
visit_monitor_expect(!Visit_monitoring_policy::resolve(array('valid' => true, 'account_type' => 'unclassified'), array('can_view' => true, 'tenant_match' => true), $request)['allowed'], 'unclassified_identity_denied');
$completed = clone $request; $completed->request_status = 'Completed';
visit_monitor_expect(!Visit_monitoring_policy::resolve($command, array('can_view' => true, 'tenant_match' => true), $completed)['allowed'], 'completed_request_denied');

$root = dirname(__DIR__, 3);
$controller = file_get_contents($root . '/application/modules/home_nakes/controllers/Home_nakes.php');
$model = file_get_contents($root . '/application/modules/home_nakes/models/Home_nakes_m.php');
$view = file_get_contents($root . '/application/modules/home_nakes/views/home_nakes_v.php');
$partial = file_get_contents($root . '/application/modules/home_nakes/views/partials/nakes_history_v.php');
visit_monitor_expect(strpos($controller, 'Visit_monitoring_policy::resolve') !== false
	&& strpos($controller, "'viewer_can_update'") !== false
	&& strpos($controller, "'viewer_mode'") !== false, 'controller_returns_explicit_viewer_capability');
visit_monitor_expect(strpos($model, '!doclinc_can_view_nakes_request($request_id, $database_identity)') !== false, 'read_model_uses_view_authorization');
visit_monitor_expect(strpos($model, "empty(\$access_context['can_handle'])") !== false, 'write_model_retains_handle_authorization');
visit_monitor_expect(strpos($view, 'monitorRefreshIntervalMs = 10000') !== false
	&& strpos($view, 'response.viewer_can_update === true') !== false
	&& strpos($view, "hidden.bs.offcanvas") !== false, 'browser_monitor_poll_is_read_only_and_bounded');
visit_monitor_expect(strpos($view, "response.visit_status === 'en_route'") !== false
	&& strpos($view, 'ns.startNakesVisitTracking(requestId)') !== false, 'personal_tracking_starts_with_en_route_transition');
visit_monitor_expect(strpos($view, 'legacyNakesDeviceLocationEnabled') !== false
	&& strpos($view, 'hasGoogleMaps() && legacyNakesDeviceLocationEnabled') !== false, 'command_center_does_not_start_legacy_device_geolocation');
visit_monitor_expect(strpos($partial, 'Pantau rute Nakes') !== false
	&& strpos($partial, 'Puskesmas hanya memonitor perjalanan') !== false
	&& strpos($partial, 'data-monitor-only') !== false, 'command_center_monitor_ui_contract');
visit_monitor_expect(strpos($controller, "'location_updated_text'") !== false
	&& strpos($view, 'response.location_updated_text') !== false, 'actual_location_freshness_displayed');

echo "VISIT_MONITORING_UNIT_PASSED={$passed}\nVISIT_MONITORING_UNIT_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
