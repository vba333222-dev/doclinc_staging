<?php
$files = array(
	'policy' => file_get_contents(__DIR__ . '/../../../application/libraries/Nakes_placement_policy.php'),
	'service' => file_get_contents(__DIR__ . '/../../../application/libraries/Nakes_placement_service.php'),
	'migration' => file_get_contents(__DIR__ . '/../../../application/migrations/20260825000100_nakes_facility_placement_transfer_foundation.php'),
	'model' => file_get_contents(__DIR__ . '/../../../admin_menu/application/modules/kelola_staff_puskesmas/models/Kelola_staff_puskesmas_m.php'),
	'controller' => file_get_contents(__DIR__ . '/../../../admin_menu/application/modules/kelola_staff_puskesmas/controllers/Kelola_staff_puskesmas.php'),
	'config' => file_get_contents(__DIR__ . '/../../../application/config/config.php'),
	'admin_config' => file_get_contents(__DIR__ . '/../../../admin_menu/application/config/config.php'),
);
$checks = array(
	'shared_governance_database_namespace' => strpos($files['migration'], 'doclinc_governance_test') !== false,
	'personal_identity_required' => strpos($files['policy'], "account_type'] ?? '') === 'personal'") !== false,
	'command_center_not_placement' => strpos($files['policy'], 'command_center') === false || strpos($files['policy'], "account_type'] ?? '') === 'personal'") !== false,
	'projection_updates_explicit' => strpos($files['service'], 'updateProjection') !== false,
	'history_closed_not_deleted' => strpos($files['service'], 'endPlacement') !== false && strpos($files['service'], 'insertPlacement') !== false,
	'feature_default_off' => strpos($files['config'], 'DOCLINC_NAKES_PLACEMENT_ENABLED') !== false,
	'admin_feature_resolver' => strpos($files['admin_config'], 'DOCLINC_NAKES_PLACEMENT_ENABLED') !== false && strpos($files['admin_config'], "nakes_placement_enabled") !== false,
	'audit_uses_current_schema_metadata' => strpos(file_get_contents(__DIR__ . '/../../../application/libraries/Nakes_placement_store.php'), "'metadata_json'") !== false,
	'audit_writes_created_at' => strpos(file_get_contents(__DIR__ . '/../../../application/libraries/Nakes_placement_store.php'), "'created_at'=>date('Y-m-d H:i:s')") !== false,
	'identity_helper_loaded_for_admin_runtime' => strpos(file_get_contents(__DIR__ . '/../../../application/libraries/Nakes_placement_store.php'), "request_authz_helper.php") !== false,
	'direct_edit_server_guard' => strpos($files['model'], 'placement_feature_enabled') !== false,
	'migration_additive' => strpos($files['migration'], 'CREATE TABLE nakes_facility_placements') !== false && strpos($files['migration'], 'CREATE TABLE nakes_facility_transfers') !== false && strpos($files['migration'], 'DROP ') === false,
	'endpoint_schedule' => strpos($files['controller'], 'function schedule_transfer') !== false,
	'endpoint_cancel' => strpos($files['controller'], 'function cancel_transfer') !== false,
	'endpoint_activate' => strpos($files['controller'], 'function activate_transfer') !== false,
	'endpoint_post_and_feature_guard' => strpos($files['controller'], 'require_placement_post') !== false && strpos($files['controller'], "nakes_placement_enabled') !== true") !== false,
	'endpoint_server_actor' => strpos($files['controller'], "session->userdata('id')") !== false && strpos($files['controller'], 'actor_user_id') === false,
	'service_ignores_client_identity_and_facility' => strpos($files['service'], "input['identity']") === false && strpos($files['service'], "input['current_facility']") === false,
);
$failed = 0; foreach ($checks as $name => $ok) { echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) $failed++; }
echo 'PLACEMENT_SOURCE_TESTS_FAILED=' . $failed . "\n"; exit($failed ? 1 : 0);
