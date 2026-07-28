<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');

if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 2) . '/system/');
}
require_once dirname(__DIR__, 2) . '/application/libraries/Realtime_outbox_feature_flags.php';
require_once __DIR__ . '/CentrifugoTransport.php';
require_once __DIR__ . '/RealtimeOutboxDispatcher.php';

function realtime_outbox_cli_fail($safe_error_code)
{
	fwrite(STDERR, "DISPATCH_RESULT=FAIL\nSAFE_ERROR_CODE=" . $safe_error_code . "\n");
	exit(1);
}

function realtime_outbox_cli_true($name)
{
	$value = getenv($name);
	return is_string($value) && in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
}

$options = getopt('', array(
	'dispatch', 'environment:', 'confirm-database:', 'batch-size::',
	'lease-seconds::', 'max-attempts::',
));
if (!array_key_exists('dispatch', $options)) {
	echo "DISPATCH_RESULT=PASS\nEXECUTION_MODE=PLAN\nDATABASE_CONNECTION_OPENED=false\nDATABASE_WRITE_EXECUTED=false\nPUBLISH_EXECUTED=false\nPLANNED_WORKER_MODE=one_shot\n";
	exit(0);
}

if (!function_exists('mysqli_report')) {
	realtime_outbox_cli_fail('mysqli_extension_missing');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$runtime_environment = isset($options['environment']) ? strtolower(trim((string) $options['environment'])) : '';
$feature_environment = trim((string) (getenv('DOCLINC_REALTIME_DISPATCH_ENVIRONMENT') ?: ''));
$flags = Realtime_outbox_feature_flags::dispatcher(
	getenv('DOCLINC_REALTIME_DISPATCH_ENABLED'),
	getenv('DOCLINC_REALTIME_DISPATCH_WRITE_ENABLED'),
	$feature_environment,
	$runtime_environment
);
if (!$flags['enabled']) {
	realtime_outbox_cli_fail('dispatcher_disabled');
}

$database = trim((string) (getenv('DOCLINC_REALTIME_DISPATCH_DB_NAME') ?: ''));
if ($database === '' || !isset($options['confirm-database'])
	|| !hash_equals($database, trim((string) $options['confirm-database']))) {
	realtime_outbox_cli_fail('database_confirmation_mismatch');
}
$disposable = realtime_outbox_cli_true('DOCLINC_REALTIME_DISPATCH_DISPOSABLE_TEST');
if ($runtime_environment === 'test') {
	if (!$disposable || preg_match('/\Adoclinc_realtime_outbox_test_[a-z0-9_]+\z/', strtolower($database)) !== 1) {
		realtime_outbox_cli_fail('disposable_database_required');
	}
} elseif ($disposable || !in_array($runtime_environment, array('staging', 'uat'), true)
	|| !hash_equals('doclinc-staging', $database)) {
	realtime_outbox_cli_fail('staging_database_required');
}

$user = trim((string) (getenv('DOCLINC_REALTIME_DISPATCH_DB_USER') ?: ''));
$allowed_users = array_values(array_filter(array_map('trim', explode(',', (string) (getenv('DOCLINC_REALTIME_DISPATCH_ALLOWED_USERS') ?: '')))));
if ($user === '' || !in_array($user, $allowed_users, true)) {
	realtime_outbox_cli_fail('dispatcher_identity_not_allowed');
}
$password = getenv('DOCLINC_REALTIME_DISPATCH_DB_PASSWORD');
if (!is_string($password) || $password === '') {
	realtime_outbox_cli_fail('database_password_missing');
}

try {
	$transport = new CentrifugoTransport(
		getenv('DOCLINC_REALTIME_GATEWAY_URL') ?: 'http://127.0.0.1:8000/api/publish',
		getenv('DOCLINC_REALTIME_GATEWAY_SECRET'),
		(int) (getenv('DOCLINC_REALTIME_GATEWAY_CONNECT_TIMEOUT_MS') ?: 1500),
		(int) (getenv('DOCLINC_REALTIME_GATEWAY_TOTAL_TIMEOUT_MS') ?: 4000),
		null,
		array_values(array_filter(array_map('trim', explode(',', (string) (getenv('DOCLINC_REALTIME_GATEWAY_ALLOWED_HOSTS') ?: '')))))
	);
	$db = new mysqli(
		getenv('DOCLINC_REALTIME_DISPATCH_DB_HOST') ?: 'localhost',
		$user,
		$password,
		$database,
		(int) (getenv('DOCLINC_REALTIME_DISPATCH_DB_PORT') ?: 3306)
	);
	$password = null;
	$db->set_charset('utf8mb4');
	RealtimeOutboxDispatcher::assertIdentityAndGrants($db, $database, $user, $allowed_users);
	$dispatcher = new RealtimeOutboxDispatcher($db, $transport);
	$summary = $dispatcher->run(
		isset($options['batch-size']) ? $options['batch-size'] : 20,
		isset($options['lease-seconds']) ? $options['lease-seconds'] : 120,
		isset($options['max-attempts']) ? $options['max-attempts'] : 5
	);
	echo "DISPATCH_RESULT=PASS\nEXECUTION_MODE=DISPATCH\nDATABASE_WRITE_EXECUTED=true\nPUBLISH_EXECUTED=" . ($summary['published'] > 0 ? 'true' : 'false') . "\n";
	foreach ($summary as $name => $value) {
		echo strtoupper($name) . '=' . (int) $value . "\n";
	}
} catch (Throwable $exception) {
	$password = null;
	$safe_codes = array(
		'dispatcher_identity_not_allowed', 'dispatcher_grants_excessive', 'dispatcher_grants_incomplete',
		'dispatcher_lock_unavailable', 'outbox_schema_contract_missing', 'target_table_signature_mismatch',
		'target_schema_hash_mismatch', 'batch_size_invalid', 'lease_seconds_invalid',
		'maximum_attempts_invalid', 'gateway_endpoint_invalid', 'gateway_endpoint_not_allowed',
		'gateway_host_not_allowed', 'gateway_secret_invalid', 'gateway_timeout_invalid', 'dispatcher_lease_lost',
		'centrifugo_endpoint_invalid', 'centrifugo_endpoint_not_allowed', 'centrifugo_host_not_allowed',
		'centrifugo_host_allowlist_invalid', 'centrifugo_api_key_invalid', 'centrifugo_timeout_invalid',
		'centrifugo_response_limit_invalid',
	);
	$message = (string) $exception->getMessage();
	if (strpos($message, ':') !== false) {
		$message = explode(':', $message, 2)[0];
	}
	$code = in_array($message, $safe_codes, true) ? $message : 'dispatcher_execution_failed';
	realtime_outbox_cli_fail($code);
} finally {
	$password = null;
	if (isset($db) && $db instanceof mysqli) {
		$db->close();
	}
}
