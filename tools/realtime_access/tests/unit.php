<?php
declare(strict_types=1);

define('BASEPATH', __DIR__ . DIRECTORY_SEPARATOR);
define('APPPATH', dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR);

require_once APPPATH . 'libraries/Doclinc_feature_flags.php';
require_once APPPATH . 'libraries/Realtime_access_feature.php';
require_once APPPATH . 'libraries/Realtime_access_exception.php';
require_once APPPATH . 'libraries/Realtime_token_service.php';
require_once APPPATH . 'libraries/Realtime_channel_policy.php';
require_once APPPATH . 'libraries/First_login_gate_policy.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $name): void
{
	global $passed, $failed;
	if (!$condition) {
		$failed++;
		fwrite(STDERR, "FAIL {$name}\n");
		return;
	}
	$passed++;
}

function base64url_decode_safe(string $value): string
{
	$padding = (4 - strlen($value) % 4) % 4;
	$result = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
	return $result === false ? '' : $result;
}

function jwt_parts(string $token): array
{
	$parts = explode('.', $token);
	return array(
		'parts' => $parts,
		'header' => json_decode(base64url_decode_safe($parts[0] ?? ''), true),
		'claims' => json_decode(base64url_decode_safe($parts[1] ?? ''), true),
		'signature' => base64url_decode_safe($parts[2] ?? ''),
	);
}

$validUrl = 'wss://staging.example.invalid/connection/websocket';
foreach (array(null, '', 'maybe', '0', 'false', 'off', 'no') as $value) {
	check(Realtime_access_feature::resolve($value, 'staging', 'staging', $validUrl)['enabled'] === false, 'flag_disabled_' . md5(serialize($value)));
}
check(Realtime_access_feature::resolve('on', 'production', 'production', $validUrl)['enabled'] === false, 'production_disabled');
check(Realtime_access_feature::resolve('on', 'development', 'development', $validUrl)['enabled'] === false, 'development_disabled');
check(Realtime_access_feature::resolve('on', 'test', 'test', $validUrl)['enabled'] === false, 'test_disabled');
check(Realtime_access_feature::resolve('on', 'staging', 'uat', $validUrl)['enabled'] === false, 'environment_mismatch_disabled');
check(Realtime_access_feature::resolve('on', 'staging', 'staging', 'ws://example.invalid/connection/websocket')['enabled'] === false, 'insecure_websocket_disabled');
check(Realtime_access_feature::resolve('on', 'staging', 'staging', 'wss://example.invalid/other')['enabled'] === false, 'unknown_websocket_path_disabled');
check(Realtime_access_feature::resolve('on', 'staging', 'staging', $validUrl)['enabled'] === true, 'valid_feature_enabled');

$secret = str_repeat('s', 48);
$issuedAt = 1800000000;
$service = new Realtime_token_service(array('secret' => $secret, 'ttl_seconds' => 120));
$connection = $service->connectionToken(101, $issuedAt);
$decoded = jwt_parts($connection);
check(count($decoded['parts']) === 3, 'jwt_three_parts');
check($decoded['header'] === array('alg' => 'HS256', 'typ' => 'JWT'), 'jwt_header_exact');
check($decoded['claims'] === array('sub' => '101', 'iat' => $issuedAt, 'exp' => $issuedAt + 120), 'connection_claims_exact');
$signingInput = $decoded['parts'][0] . '.' . $decoded['parts'][1];
check(hash_equals(hash_hmac('sha256', $signingInput, $secret, true), $decoded['signature']), 'jwt_signature_valid');
check($decoded['claims']['exp'] > $decoded['claims']['iat'], 'jwt_expiration_future');
check((new Realtime_token_service(array('secret' => $secret, 'ttl_seconds' => 60)))->expiresAt($issuedAt) === $issuedAt + 60, 'minimum_ttl_accepted');
check((new Realtime_token_service(array('secret' => $secret, 'ttl_seconds' => 300)))->expiresAt($issuedAt) === $issuedAt + 300, 'maximum_ttl_accepted');

$subscription = jwt_parts($service->subscriptionToken(101, 'request:301', $issuedAt));
check($subscription['claims'] === array('sub' => '101', 'channel' => 'request:301', 'iat' => $issuedAt, 'exp' => $issuedAt + 120), 'subscription_claims_exact');
check(!array_key_exists('name', $subscription['claims']) && !array_key_exists('info', $subscription['claims']), 'claims_exclude_profile_data');

foreach (array(
	array('secret' => '', 'ttl_seconds' => 120),
	array('secret' => 'short', 'ttl_seconds' => 120),
	array('secret' => $secret, 'ttl_seconds' => 59),
	array('secret' => $secret, 'ttl_seconds' => 301),
) as $case) {
	$rejected = false;
	try {
		new Realtime_token_service($case);
	} catch (Realtime_access_exception $exception) {
		$rejected = strpos($exception->getMessage(), $secret) === false;
	}
	check($rejected, 'invalid_token_configuration_' . md5(serialize($case)));
}

$channelPolicy = new Realtime_channel_policy();
$warga = array('authenticated' => true, 'user_id' => 201, 'role' => 'warga', 'status' => 'aktif', 'must_change_password' => false);
$doctorActor = array('authenticated' => true, 'user_id' => 101, 'role' => 'dokter', 'status' => 'aktif', 'must_change_password' => false);
$personal = array('valid' => true, 'user_id' => 101, 'role' => 'dokter', 'user_status' => 'aktif', 'account_type' => 'personal', 'puskesmas_code' => 'PKM01', 'staff_status' => 'aktif');
$commandCenter = array('valid' => true, 'user_id' => 101, 'role' => 'dokter', 'user_status' => 'aktif', 'account_type' => 'command_center', 'puskesmas_code' => 'PKM01');

foreach (array('user:101', 'request:301', 'puskesmas:PKM01:ops') as $channel) {
	check($channelPolicy->parse($channel) !== null, 'valid_channel_' . $channel);
}
foreach (array('', 'user:0', 'user:-1', 'user:101:extra', 'request:0', 'request: 1', 'admin:1', 'puskesmas:DEFAULT:ops', 'puskesmas:PKM..01:ops', 'puskesmas:PKM01:write', "user:1\n", str_repeat('x', 129)) as $channel) {
	check($channelPolicy->parse($channel) === null, 'invalid_channel_' . md5($channel));
}

check($channelPolicy->authorize($warga, $channelPolicy->parse('user:201')), 'own_user_channel_allowed');
check(!$channelPolicy->authorize($warga, $channelPolicy->parse('user:202')), 'user_channel_spoof_denied');
$ownedRequest = (object) array('request_id' => 301, 'user_id' => 201);
$otherRequest = (object) array('request_id' => 301, 'user_id' => 202);
check($channelPolicy->authorize($warga, $channelPolicy->parse('request:301'), $ownedRequest), 'request_owner_allowed');
check(!$channelPolicy->authorize($warga, $channelPolicy->parse('request:301'), $otherRequest), 'request_cross_owner_denied');

$personalAccess = array('valid' => true, 'can_view' => true, 'tenant_match' => true, 'ownership_source' => 'staff_assignment');
$notPicAccess = array('valid' => false, 'can_view' => false, 'tenant_match' => true, 'ownership_source' => null);
check($channelPolicy->authorize($doctorActor, $channelPolicy->parse('request:301'), $ownedRequest, $personal, $personalAccess), 'personal_pic_allowed');
check(!$channelPolicy->authorize($doctorActor, $channelPolicy->parse('request:301'), $ownedRequest, $personal, $notPicAccess), 'personal_non_pic_denied');
check($channelPolicy->authorize($doctorActor, $channelPolicy->parse('puskesmas:PKM01:ops'), null, $commandCenter), 'command_center_tenant_allowed');
check(!$channelPolicy->authorize($doctorActor, $channelPolicy->parse('puskesmas:PKM02:ops'), null, $commandCenter), 'command_center_cross_tenant_denied');
check(!$channelPolicy->authorize($doctorActor, $channelPolicy->parse('puskesmas:PKM01:ops'), null, $personal), 'personal_ops_channel_denied');
$crossTenantAccess = array('valid' => false, 'can_view' => false, 'tenant_match' => false, 'ownership_source' => null);
check(!$channelPolicy->authorize($doctorActor, $channelPolicy->parse('request:301'), $ownedRequest, $commandCenter, $crossTenantAccess), 'command_center_request_cross_tenant_denied');

$inactiveActor = $doctorActor;
$inactiveActor['status'] = 'nonaktif';
$mustChangeActor = $doctorActor;
$mustChangeActor['must_change_password'] = true;
$unlinked = $personal;
$unlinked['valid'] = false;
$unlinked['staff_status'] = 'nonaktif';
$unlinked['staff_id'] = null;
check(!$channelPolicy->authorize($inactiveActor, $channelPolicy->parse('user:101'), null, $personal), 'inactive_user_denied');
check(!$channelPolicy->authorize($mustChangeActor, $channelPolicy->parse('user:101'), null, $personal), 'must_change_denied');
check(!$channelPolicy->authorize($doctorActor, $channelPolicy->parse('user:101'), null, $unlinked), 'inactive_or_unlinked_staff_denied');
check(!$channelPolicy->authorize(array('authenticated' => false), $channelPolicy->parse('user:101')), 'anonymous_denied');
check(!$channelPolicy->authorize(array('authenticated' => true, 'user_id' => 1, 'role' => 'admin', 'status' => 'aktif'), $channelPolicy->parse('user:1')), 'admin_not_implicitly_allowed');

$firstLoginPolicy = new First_login_gate_policy();
check(!$firstLoginPolicy->allowed('Realtime_access', 'connection_token'), 'first_login_connection_not_allowed');
check(!$firstLoginPolicy->allowed('Realtime_access', 'subscription_token'), 'first_login_subscription_not_allowed');
check($firstLoginPolicy->jsonResponse('Realtime_access', 'connection_token', false, '', ''), 'first_login_connection_json_403');
check($firstLoginPolicy->jsonResponse('Realtime_access', 'subscription_token', false, '', ''), 'first_login_subscription_json_403');

$controllerSource = file_get_contents(APPPATH . 'controllers/Realtime_access.php');
$routesSource = file_get_contents(APPPATH . 'config/routes.php');
check(strpos($controllerSource, "method(true) !== 'GET'") !== false, 'connection_get_only_source');
check(strpos($controllerSource, "method(true) !== 'POST'") !== false, 'subscription_post_only_source');
check(strpos($controllerSource, 'Cache-Control: no-store') !== false, 'no_store_source');
check(strpos($controllerSource, "select('userId, role, status, must_change_password')") !== false, 'database_identity_source');
check(strpos($routesSource, "realtime/connection-token") !== false && strpos($routesSource, "realtime/subscription-token") !== false, 'routes_present');
check(strpos($controllerSource, 'log_message') === false || strpos($controllerSource, "log_message('error', 'Realtime") !== false, 'logs_use_constant_messages');

fwrite(STDOUT, "REALTIME_ACCESS_UNIT_PASSED={$passed}\n");
fwrite(STDOUT, "REALTIME_ACCESS_UNIT_FAILED={$failed}\n");
exit($failed === 0 ? 0 : 1);
