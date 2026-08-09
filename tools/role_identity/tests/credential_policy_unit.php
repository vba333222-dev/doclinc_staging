<?php
if (!defined('BASEPATH')) { define('BASEPATH', __DIR__ . '/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
require_once dirname(__DIR__, 3) . '/application/libraries/Nakes_credential_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Nakes_credential_enforcement_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Doclinc_feature_flags.php';
require_once dirname(__DIR__, 3) . '/application/helpers/nakes_credential_enforcement_helper.php';

$passed = 0;
$failed = 0;
function credential_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; echo "FAIL {$label}\n";
}

$policy = new Nakes_credential_policy();
$enforcement_off = new Nakes_credential_enforcement_policy(false, $policy);
$enforcement_on = new Nakes_credential_enforcement_policy(true, $policy);
$changed = '2026-08-03 03:30:00';

function credential_runtime_access_allowed($session_role, $user_role, $status, Nakes_credential_enforcement_policy $enforcement, $must_change_password, $password_changed_at)
{
	return (string) $session_role === (string) $user_role
		&& (string) $status === 'aktif'
		&& !$enforcement->blocks($user_role, $must_change_password, $password_changed_at);
}

class CredentialTestConfig
{
	public $enabled = false;
	public function item($name) { return $name === 'nakes_credential_enforcement_enabled' ? $this->enabled : null; }
}

class CredentialTestCi
{
	public $config;
	public function __construct() { $this->config = new CredentialTestConfig(); }
}

class CredentialTestDb
{
	private $fields;
	public function __construct(array $fields) { $this->fields = $fields; }
	public function field_exists($field, $table) { return $table === 'users' && in_array($field, $this->fields, true); }
}

$credential_test_ci = new CredentialTestCi();
function &get_instance()
{
	global $credential_test_ci;
	return $credential_test_ci;
}

function credential_config_state($root, $enabled, $feature_environment, $runtime_environment)
{
	$code = "define('BASEPATH',__DIR__);define('FCPATH'," . var_export($root . '/', true)
		. ");define('APPPATH'," . var_export($root . '/application/', true)
		. ");require " . var_export($root . '/application/config/config.php', true)
		. ";echo json_encode(array('enabled'=>\$config['nakes_credential_enforcement_enabled'],'reason'=>\$config['nakes_credential_enforcement_feature_reason']));";
	$environment = getenv();
	if (!is_array($environment)) { $environment = array(); }
	$environment['DOCLINC_NAKES_CREDENTIAL_ENFORCEMENT_ENABLED'] = $enabled;
	$environment['DOCLINC_NAKES_CREDENTIAL_ENFORCEMENT_ENVIRONMENT'] = $feature_environment;
	$environment['DOCLINC_REALTIME_CLIENT_RUNTIME_ENVIRONMENT'] = $runtime_environment;
	$process = proc_open(array(PHP_BINARY, '-r', $code), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, $environment, array('bypass_shell' => true));
	if (!is_resource($process)) { return null; }
	$output = stream_get_contents($pipes[1]);
	stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return proc_close($process) === 0 ? json_decode($output, true) : null;
}

credential_expect($policy->state('warga', 0, null) === Nakes_credential_policy::NOT_APPLICABLE, 'warga_not_subject_to_nakes_credential_gate');
credential_expect($policy->state('dokter', 1, null) === Nakes_credential_policy::FIRST_LOGIN_PENDING, 'temporary_password_never_changed_is_first_login_pending');
credential_expect($policy->state('dokter', 0, null) === Nakes_credential_policy::FIRST_LOGIN_PENDING, 'legacy_default_without_change_evidence_fails_closed');
credential_expect($policy->state('dokter', 1, $changed) === Nakes_credential_policy::ADMIN_RESET_PENDING, 'admin_reset_preserves_prior_change_evidence');
credential_expect($policy->state('dokter', 0, $changed) === Nakes_credential_policy::ACTIVE, 'verified_user_password_change_is_active');
credential_expect($policy->state('dokter', 0, 'invalid-time') === Nakes_credential_policy::FIRST_LOGIN_PENDING, 'malformed_change_evidence_fails_closed');
credential_expect($policy->requiresChange('dokter', 0, null) === true, 'unverified_legacy_nakes_must_change_password');
credential_expect($policy->requiresChange('dokter', 0, $changed) === false, 'verified_nakes_not_forced_again');
credential_expect($enforcement_off->rawState('dokter', 1, null) === Nakes_credential_policy::FIRST_LOGIN_PENDING
	&& $enforcement_off->blocks('dokter', 1, null) === false, 'enforcement_off_preserves_raw_first_login_pending_without_blocking');
credential_expect($enforcement_off->rawState('dokter', 0, null) === Nakes_credential_policy::FIRST_LOGIN_PENDING
	&& credential_runtime_access_allowed('dokter', 'dokter', 'aktif', $enforcement_off, 0, null), 'legacy_pending_nakes_allowed_when_enforcement_off');
credential_expect($enforcement_on->blocks('dokter', 0, null) === true, 'enforcement_on_blocks_first_login_pending');
credential_expect($enforcement_on->blocks('dokter', 1, $changed) === true, 'enforcement_on_blocks_admin_reset_pending');
credential_expect($enforcement_on->blocks('dokter', 0, $changed) === false, 'enforcement_on_allows_active_credential');
credential_expect($enforcement_on->blocks('warga', 1, null) === false, 'warga_not_subject_to_effective_nakes_enforcement');
credential_expect($enforcement_on->blocks('warga', 0, null) === false, 'warga_with_null_change_evidence_not_blocked_when_enforcement_on');
credential_expect($enforcement_on->blocks('dokter', 0, 'malformed') === true, 'malformed_change_evidence_blocks_dokter_when_enforcement_on');
credential_expect($enforcement_off->schemaAllowsRuntime(true, false) === true
	&& $enforcement_off->blocks('dokter', 1, null) === false, 'missing_password_changed_at_allows_valid_runtime_when_enforcement_off');
credential_expect($enforcement_on->schemaAllowsRuntime(true, false) === false, 'missing_credential_schema_fails_closed_when_enforcement_on');
$missing_changed_at_db = new CredentialTestDb(array('must_change_password'));
$credential_test_ci->config->enabled = false;
credential_expect(doclinc_nakes_credential_schema_allows_runtime($missing_changed_at_db) === true
	&& doclinc_nakes_password_changed_at_projection($missing_changed_at_db) === 'NULL AS password_changed_at', 'runtime_helper_projects_null_evidence_when_schema_missing_and_enforcement_off');
$credential_test_ci->config->enabled = true;
credential_expect(doclinc_nakes_credential_schema_allows_runtime($missing_changed_at_db) === false, 'runtime_helper_fails_closed_when_schema_missing_and_enforcement_on');
$stale_session_must_change_password = 1;
$refreshed_session_must_change_password = $enforcement_off->effectiveMustChangePassword('dokter', 1, null);
credential_expect($stale_session_must_change_password === 1 && $refreshed_session_must_change_password === 0, 'enforcement_off_clears_stale_effective_session_state');
credential_expect(!credential_runtime_access_allowed('dokter', 'dokter', 'nonaktif', $enforcement_off, 1, null)
	&& !credential_runtime_access_allowed('warga', 'dokter', 'aktif', $enforcement_off, 1, null), 'inactive_and_session_role_mismatch_denied_with_enforcement_off');
credential_expect($policy->state('dokter', 1, $changed) === Nakes_credential_policy::ADMIN_RESET_PENDING
	&& $enforcement_off->rawState('dokter', 1, $changed) === Nakes_credential_policy::ADMIN_RESET_PENDING, 'readiness_raw_admin_reset_state_visible_when_enforcement_off');
$first_login_password_change_enabled = false;
credential_expect($first_login_password_change_enabled === false
	&& credential_runtime_access_allowed('dokter', 'dokter', 'aktif', $enforcement_off, 1, null), 'workflow_unavailable_does_not_deny_login_when_enforcement_off');
credential_expect(Doclinc_feature_flags::resolve(null, 'staging', 'staging')['enabled'] === false
	&& Doclinc_feature_flags::resolve('', 'staging', 'staging')['enabled'] === false
	&& Doclinc_feature_flags::resolve('invalid', 'staging', 'staging')['enabled'] === false, 'credential_enforcement_missing_empty_or_invalid_defaults_off');
credential_expect(Doclinc_feature_flags::resolve('true', 'production', 'production')['enabled'] === false
	&& Doclinc_feature_flags::resolve('true', 'staging', 'staging')['enabled'] === true, 'credential_enforcement_environment_allowlist_applied');
$mismatched_config = credential_config_state(dirname(__DIR__, 3), 'true', 'staging', 'production');
credential_expect(is_array($mismatched_config)
	&& $mismatched_config['enabled'] === false
	&& in_array($mismatched_config['reason'], array('runtime_environment_not_allowed', 'environment_mismatch'), true), 'config_wiring_disables_staging_feature_in_production_runtime');

echo "NAKES_CREDENTIAL_POLICY_UNIT_PASSED={$passed}\n";
echo "NAKES_CREDENTIAL_POLICY_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
