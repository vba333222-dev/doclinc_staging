<?php

if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 3) . '/system/');
}
require_once dirname(__DIR__, 3) . '/application/libraries/Realtime_outbox_contract.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Realtime_outbox_feature_flags.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Realtime_outbox_writer.php';
require_once dirname(__DIR__) . '/HttpRealtimeTransport.php';
require_once dirname(__DIR__) . '/CentrifugoTransport.php';
require_once dirname(__DIR__) . '/InMemoryRealtimeTransport.php';

$passed = 0;
$failed = 0;

function outbox_expect($condition, $label)
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

function outbox_rejected(Realtime_outbox_contract $contract, array $event, $safe_code, array $codes = array('PKM01'))
{
	try {
		$contract->prepare($event, $codes);
	} catch (Realtime_outbox_exception $exception) {
		return hash_equals($safe_code, $exception->getSafeCode());
	}
	return false;
}

function outbox_event()
{
	return array(
		'event_id' => 'evt.synthetic.1001',
		'event_type' => 'request.assignment.changed',
		'aggregate_type' => 'request',
		'aggregate_id' => '1001',
		'version' => 2,
		'invalidation' => 'assignment',
		'audience' => 'user:101',
	);
}

final class UnitWriterDatabase
{
	public $db_debug = true;
	public $mode = 'success';
	public $insert_count = 0;

	public function insert($table, array $data)
	{
		$this->insert_count++;
		return $this->mode === 'success';
	}

	public function error()
	{
		return array('code' => $this->mode === 'duplicate' ? 1062 : ($this->mode === 'failure' ? 1205 : 0));
	}

	public function insert_id()
	{
		return 7;
	}
}

$contract = new Realtime_outbox_contract();
$valid = $contract->prepare(outbox_event(), array('PKM01'));
outbox_expect($valid['audience_type'] === 'user' && $valid['audience_key'] === 'user:101', 'valid_user_audience');
outbox_expect(strlen($valid['idempotency_key']) === 64, 'deterministic_idempotency_shape');
outbox_expect(hash_equals($valid['idempotency_key'], $contract->prepare(outbox_event(), array('PKM01'))['idempotency_key']), 'deterministic_idempotency_repeat');
outbox_expect($valid['payload_json'] === '{"aggregate_id":"1001","audience":"user:101","event_id":"evt.synthetic.1001","event_type":"request.assignment.changed","invalidation":"assignment","version":2}', 'canonical_json');

$puskesmas = outbox_event();
$puskesmas['audience'] = 'puskesmas:PKM01:ops';
outbox_expect($contract->prepare($puskesmas, array('PKM01'))['audience_type'] === 'puskesmas', 'valid_puskesmas_audience');
$request = outbox_event();
$request['audience'] = 'request:1001';
outbox_expect($contract->prepare($request)['audience_type'] === 'request', 'valid_request_audience');

$invalid = outbox_event();
$invalid['event_type'] = 'unknown.event';
outbox_expect(outbox_rejected($contract, $invalid, 'event_type_invalid'), 'unknown_event_rejected');
$invalid = outbox_event();
$invalid['aggregate_type'] = 'notification';
outbox_expect(outbox_rejected($contract, $invalid, 'aggregate_type_invalid'), 'aggregate_mismatch_rejected');
foreach (array('user:0', 'user:-1', 'request:0', 'admin:1', 'puskesmas:PKM99:ops', 'user:1:extra') as $audience) {
	$invalid = outbox_event();
	$invalid['audience'] = $audience;
	outbox_expect(outbox_rejected($contract, $invalid, strpos($audience, 'puskesmas:') === 0 ? 'audience_puskesmas_unknown' : 'audience_invalid'), 'malformed_audience_' . substr(hash('sha256', $audience), 0, 8));
}
$invalid = outbox_event();
$invalid['aggregate_id'] = 0;
outbox_expect(outbox_rejected($contract, $invalid, 'aggregate_id_invalid'), 'zero_aggregate_rejected');
$invalid['aggregate_id'] = -2;
outbox_expect(outbox_rejected($contract, $invalid, 'aggregate_id_invalid'), 'negative_aggregate_rejected');

foreach (array('name', 'phone', 'email', 'address', 'diagnosis', 'anamnesis', 'treatment', 'complaint', 'title', 'message', 'latitude', 'longitude', 'file_path', 'token', 'password') as $field) {
	$invalid = outbox_event();
	$invalid[$field] = 'forbidden';
	outbox_expect(outbox_rejected($contract, $invalid, 'payload_key_not_allowed'), 'forbidden_payload_' . $field);
}
$invalid = outbox_event();
$invalid['event_id'] = array('nested' => 'value');
outbox_expect(outbox_rejected($contract, $invalid, 'payload_value_invalid'), 'nested_metadata_rejected');
$invalid = outbox_event();
$invalid['event_id'] = new stdClass();
outbox_expect(outbox_rejected($contract, $invalid, 'payload_value_invalid'), 'object_payload_rejected');
$resource = fopen('php://memory', 'rb');
$invalid = outbox_event();
$invalid['event_id'] = $resource;
outbox_expect(outbox_rejected($contract, $invalid, 'payload_value_invalid'), 'resource_payload_rejected');
fclose($resource);
$invalid = outbox_event();
$invalid['event_id'] = "invalid\xC3\x28";
outbox_expect(outbox_rejected($contract, $invalid, 'payload_utf8_invalid'), 'invalid_utf8_rejected');
outbox_expect(outbox_rejected(new Realtime_outbox_contract(64), outbox_event(), 'payload_too_large'), 'oversized_payload_rejected');

foreach (array(null, '', 'invalid', 'false', '0', 'off', 'no') as $flag) {
	outbox_expect(Realtime_outbox_feature_flags::writer($flag, 'staging', 'staging')['enabled'] === false, 'writer_flag_disabled_' . md5(serialize($flag)));
}
outbox_expect(Realtime_outbox_feature_flags::writer('true', 'staging', 'production')['enabled'] === false, 'writer_production_disabled');
outbox_expect(Realtime_outbox_feature_flags::dispatcher(null, null, 'staging', 'staging')['enabled'] === false, 'dispatcher_missing_flags_disabled');
outbox_expect(Realtime_outbox_feature_flags::dispatcher('invalid', 'true', 'staging', 'staging')['enabled'] === false, 'dispatcher_malformed_flag_disabled');
outbox_expect(Realtime_outbox_feature_flags::dispatcher('true', 'true', 'staging', 'production')['enabled'] === false, 'dispatcher_production_disabled');
outbox_expect(Realtime_outbox_feature_flags::dispatcher('true', 'false', 'staging', 'staging')['enabled'] === false, 'dispatcher_write_flag_required');

$writer_db = new UnitWriterDatabase();
$writer = new Realtime_outbox_writer($contract);
$off = $writer->enqueue($writer_db, outbox_event(), array('PKM01'), array('enabled' => false));
outbox_expect(!$off['success'] && $writer_db->insert_count === 0, 'writer_flag_off_zero_insert');
$default_off = $writer->enqueue($writer_db, outbox_event(), array('PKM01'));
outbox_expect(!$default_off['success'] && $writer_db->insert_count === 0, 'writer_default_off_zero_insert');
$written = $writer->enqueue($writer_db, outbox_event(), array('PKM01'), array('enabled' => true));
outbox_expect($written['success'] && $written['outbox_id'] === 7 && $writer_db->db_debug === true, 'writer_success');
$writer_db->mode = 'duplicate';
$duplicate = $writer->enqueue($writer_db, outbox_event(), array('PKM01'), array('enabled' => true));
outbox_expect($duplicate['success'] && $duplicate['duplicate'], 'writer_duplicate_idempotent');
$writer_db->mode = 'failure';
$failure = $writer->enqueue($writer_db, outbox_event(), array('PKM01'), array('enabled' => true));
outbox_expect(!$failure['success'] && $failure['safe_error_code'] === 'outbox_insert_failed', 'writer_failure_visible');

$secret = bin2hex(random_bytes(24));
$message = array('audience_key' => 'user:101', 'payload' => json_decode($valid['payload_json'], true), 'idempotency_key' => $valid['idempotency_key']);
$responses = array(
	array(array('status' => 200, 'body' => '{"success":true}', 'timed_out' => false), true, false, 'none'),
	array(array('status' => 200, 'body' => '{"success":false,"error":{"retryable":true}}', 'timed_out' => false), false, true, 'gateway_logical_retryable'),
	array(array('status' => 200, 'body' => '{"success":false,"error":{"retryable":false}}', 'timed_out' => false), false, false, 'gateway_logical_permanent'),
	array(array('status' => 503, 'body' => '{}', 'timed_out' => false), false, true, 'gateway_http_retryable'),
	array(array('status' => 400, 'body' => '{}', 'timed_out' => false), false, false, 'gateway_http_permanent'),
	array(array('status' => 200, 'body' => '{invalid', 'timed_out' => false), false, true, 'gateway_json_invalid'),
	array(array('status' => 0, 'body' => '', 'timed_out' => true), false, true, 'gateway_timeout'),
);
foreach ($responses as $index => $case) {
	$transport = new HttpRealtimeTransport('https://gateway.invalid/publish', $secret, 100, 200, function () use ($case) {
		return $case[0];
	}, array('gateway.invalid'));
	$result = $transport->publish($message);
	outbox_expect($result->successful === $case[1] && $result->retryable === $case[2]
		&& $result->safe_error_code === $case[3], 'http_classification_' . $index);
	outbox_expect(strpos($result->safe_error_code, $secret) === false, 'secret_absent_from_result_' . $index);
}
try {
	new HttpRealtimeTransport('http://external.invalid/publish', $secret);
	outbox_expect(false, 'external_http_rejected');
} catch (InvalidArgumentException $exception) {
	outbox_expect($exception->getMessage() === 'gateway_endpoint_not_allowed' && strpos($exception->getMessage(), $secret) === false, 'external_http_rejected');
}
try {
	new HttpRealtimeTransport('https://gateway.invalid/publish', $secret);
	outbox_expect(false, 'https_host_allowlist_required');
} catch (InvalidArgumentException $exception) {
	outbox_expect($exception->getMessage() === 'gateway_host_not_allowed', 'https_host_allowlist_required');
}

$centrifugo_message = $message;
$centrifugo_message['audience_type'] = 'user';
$centrifugo_requests = array();
$centrifugo_client = function ($request) use (&$centrifugo_requests) {
	$centrifugo_requests[] = $request;
	return array('status' => 200, 'body' => '{"result":{}}', 'failure' => '');
};
$centrifugo = new CentrifugoTransport(
	'http://127.0.0.1:8000/api/publish',
	$secret,
	100,
	200,
	$centrifugo_client
);
$centrifugo_result = $centrifugo->publish($centrifugo_message);
$sent = isset($centrifugo_requests[0]) ? $centrifugo_requests[0] : array();
$sent_body = isset($sent['body']) ? json_decode($sent['body'], true) : null;
outbox_expect($centrifugo_result->successful, 'centrifugo_result_success');
outbox_expect(isset($sent['method'], $sent['url']) && $sent['method'] === 'POST'
	&& $sent['url'] === 'http://127.0.0.1:8000/api/publish', 'centrifugo_exact_method_url');
outbox_expect(isset($sent['headers']) && in_array('Content-Type: application/json', $sent['headers'], true)
	&& in_array('X-API-Key: ' . $secret, $sent['headers'], true), 'centrifugo_exact_headers');
outbox_expect(is_array($sent_body) && array_keys($sent_body) === array('channel', 'data', 'idempotency_key')
	&& $sent_body['channel'] === $centrifugo_message['audience_key']
	&& $sent_body['data'] === $centrifugo_message['payload']
	&& $sent_body['idempotency_key'] === $centrifugo_message['idempotency_key'], 'centrifugo_exact_body');

$centrifugo_cases = array(
	'result_array' => array(array('status' => 200, 'body' => '{"result":[]}', 'failure' => ''), true, false, 'none'),
	'missing_result' => array(array('status' => 200, 'body' => '{}', 'failure' => ''), false, true, 'centrifugo_result_invalid'),
	'null_error' => array(array('status' => 200, 'body' => '{"error":null,"result":{}}', 'failure' => ''), false, false, 'centrifugo_api_error'),
	'logical_error' => array(array('status' => 200, 'body' => '{"error":{"code":102,"message":"ignored"}}', 'failure' => ''), false, false, 'centrifugo_channel_rejected'),
	'internal_error' => array(array('status' => 200, 'body' => '{"error":{"code":100}}', 'failure' => ''), false, true, 'centrifugo_server_unavailable'),
	'malformed_json' => array(array('status' => 200, 'body' => '{invalid', 'failure' => ''), false, true, 'centrifugo_response_invalid'),
	'http_400' => array(array('status' => 400, 'body' => '{}', 'failure' => ''), false, false, 'centrifugo_request_rejected'),
	'http_401' => array(array('status' => 401, 'body' => '{}', 'failure' => ''), false, false, 'centrifugo_auth_rejected'),
	'http_403' => array(array('status' => 403, 'body' => '{}', 'failure' => ''), false, false, 'centrifugo_auth_rejected'),
	'http_404' => array(array('status' => 404, 'body' => '{}', 'failure' => ''), false, false, 'centrifugo_channel_rejected'),
	'http_408' => array(array('status' => 408, 'body' => '{}', 'failure' => ''), false, true, 'centrifugo_request_timeout'),
	'http_429' => array(array('status' => 429, 'body' => '{}', 'failure' => ''), false, true, 'centrifugo_rate_limited'),
	'http_500' => array(array('status' => 500, 'body' => '{}', 'failure' => ''), false, true, 'centrifugo_server_unavailable'),
	'http_502' => array(array('status' => 502, 'body' => '{}', 'failure' => ''), false, true, 'centrifugo_server_unavailable'),
	'http_503' => array(array('status' => 503, 'body' => '{}', 'failure' => ''), false, true, 'centrifugo_server_unavailable'),
	'connection_refused' => array(array('status' => 0, 'body' => '', 'failure' => 'connection_failed'), false, true, 'centrifugo_connection_failed'),
	'connect_timeout' => array(array('status' => 0, 'body' => '', 'failure' => 'connect_timeout'), false, true, 'centrifugo_connect_timeout'),
	'total_timeout' => array(array('status' => 0, 'body' => '', 'failure' => 'total_timeout'), false, true, 'centrifugo_total_timeout'),
	'redirect' => array(array('status' => 302, 'body' => '', 'failure' => ''), false, false, 'centrifugo_redirect_rejected'),
);
foreach ($centrifugo_cases as $label => $case) {
	$transport = new CentrifugoTransport('http://localhost:8000/api/publish', $secret, 100, 200, function () use ($case) {
		return $case[0];
	});
	$result = $transport->publish($centrifugo_message);
	outbox_expect($result->successful === $case[1] && $result->retryable === $case[2]
		&& $result->safe_error_code === $case[3], 'centrifugo_classification_' . $label);
	outbox_expect(strpos($result->safe_error_code, $secret) === false, 'centrifugo_secret_absent_' . $label);
}

$oversized_transport = new CentrifugoTransport('http://127.0.0.1:8000/api/publish', $secret, 100, 200, function () {
	return array('status' => 200, 'body' => str_repeat('x', 257), 'failure' => '');
}, array(), 256);
$oversized_result = $oversized_transport->publish($centrifugo_message);
outbox_expect(!$oversized_result->successful && $oversized_result->retryable
	&& $oversized_result->safe_error_code === 'centrifugo_response_too_large', 'centrifugo_oversized_response');

$retry_bodies = array();
$stable_transport = new CentrifugoTransport('http://127.0.0.1:8000/api/publish', $secret, 100, 200, function ($request) use (&$retry_bodies) {
	$retry_bodies[] = $request['body'];
	return array('status' => 503, 'body' => '{}', 'failure' => '');
});
$stable_transport->publish($centrifugo_message);
$stable_transport->publish($centrifugo_message);
outbox_expect(count($retry_bodies) === 2 && hash_equals($retry_bodies[0], $retry_bodies[1])
	&& json_decode($retry_bodies[0], true)['idempotency_key'] === $centrifugo_message['idempotency_key'], 'centrifugo_retry_stable_idempotency');

foreach (array('user:101', 'request:1001', 'puskesmas:PKM01:ops', 'puskesmas:PKM.01:ops') as $channel) {
	$channel_message = $centrifugo_message;
	$channel_message['audience_key'] = $channel;
	$channel_message['payload']['audience'] = $channel;
	$channel_message['audience_type'] = strpos($channel, 'puskesmas:') === 0 ? 'puskesmas' : explode(':', $channel, 2)[0];
	$result = $centrifugo->publish($channel_message);
	outbox_expect($result->successful, 'centrifugo_valid_channel_' . substr(hash('sha256', $channel), 0, 8));
}

$invalid_channels = array(
	'', 'user:0', 'user:-1', 'user:1:extra', 'request:0', 'admin:1', 'other:1',
	'puskesmas:DEFAULT:ops', 'puskesmas:../x:ops', 'puskesmas:PKM..01:ops', 'puskesmas:PKM%3A01:ops',
	"user:1\n", ' user:1', 'user:1 ', str_repeat('A', 129),
);
foreach ($invalid_channels as $channel) {
	$channel_message = $centrifugo_message;
	$channel_message['audience_key'] = $channel;
	$channel_message['payload']['audience'] = $channel;
	$result = $centrifugo->publish($channel_message);
	outbox_expect(!$result->successful && !$result->retryable
		&& $result->safe_error_code === 'centrifugo_message_invalid', 'centrifugo_invalid_channel_' . substr(hash('sha256', $channel), 0, 8));
}

$mismatch = $centrifugo_message;
$mismatch['payload']['audience'] = 'request:1001';
outbox_expect($centrifugo->publish($mismatch)->safe_error_code === 'centrifugo_message_invalid', 'centrifugo_channel_payload_mismatch');
$mismatch = $centrifugo_message;
$mismatch['audience_type'] = 'request';
outbox_expect($centrifugo->publish($mismatch)->safe_error_code === 'centrifugo_message_invalid', 'centrifugo_audience_type_mismatch');
$override = $centrifugo_message;
$override['payload']['channel'] = 'user:999';
outbox_expect($centrifugo->publish($override)->safe_error_code === 'centrifugo_message_invalid', 'centrifugo_payload_channel_override_rejected');
$override = $centrifugo_message;
$override['payload']['idempotency_key'] = str_repeat('a', 64);
outbox_expect($centrifugo->publish($override)->safe_error_code === 'centrifugo_message_invalid', 'centrifugo_payload_idempotency_override_rejected');

$invalid_urls = array(
	'http://external.invalid/api/publish',
	'ftp://127.0.0.1:8000/api/publish',
	'http://127.0.0.1:8000/api',
	'http://127.0.0.1:8000/api/publish/',
	'http://127.0.0.1:8000/api/publish?value=1',
	'http://127.0.0.1:8000/api/publish#fragment',
	'http://name@127.0.0.1:8000/api/publish',
	'http://127.0.0.1:99999/api/publish',
);
foreach ($invalid_urls as $url) {
	try {
		new CentrifugoTransport($url, $secret, 100, 200);
		outbox_expect(false, 'centrifugo_invalid_url_' . substr(hash('sha256', $url), 0, 8));
	} catch (InvalidArgumentException $exception) {
		outbox_expect(strpos($exception->getMessage(), 'centrifugo_') === 0
			&& strpos($exception->getMessage(), $secret) === false, 'centrifugo_invalid_url_' . substr(hash('sha256', $url), 0, 8));
	}
}
$https_transport = new CentrifugoTransport('https://realtime.internal.invalid/api/publish', $secret, 100, 200, function () {
	return array('status' => 200, 'body' => '{"result":{}}', 'failure' => '');
}, array('realtime.internal.invalid'));
outbox_expect($https_transport->publish($centrifugo_message)->successful, 'centrifugo_https_allowlist_accepted');
try {
	new CentrifugoTransport('https://realtime.internal.invalid/api/publish', $secret, 100, 200);
	outbox_expect(false, 'centrifugo_https_allowlist_required');
} catch (InvalidArgumentException $exception) {
	outbox_expect($exception->getMessage() === 'centrifugo_host_not_allowed', 'centrifugo_https_allowlist_required');
}
foreach (array(null, '', 'too-short', "invalid\nvalue") as $invalid_secret) {
	try {
		new CentrifugoTransport('http://127.0.0.1:8000/api/publish', $invalid_secret, 100, 200);
		outbox_expect(false, 'centrifugo_invalid_secret_' . md5(serialize($invalid_secret)));
	} catch (InvalidArgumentException $exception) {
		outbox_expect($exception->getMessage() === 'centrifugo_api_key_invalid', 'centrifugo_invalid_secret_' . md5(serialize($invalid_secret)));
	}
}
$dispatcher_command_source = file_get_contents(dirname(__DIR__) . '/realtime_outbox.php');
$transport_position = strpos($dispatcher_command_source, 'new CentrifugoTransport(');
$database_position = strpos($dispatcher_command_source, 'new mysqli(');
outbox_expect($transport_position !== false && $database_position !== false
	&& $transport_position < $database_position, 'centrifugo_configuration_validated_before_database_connection');
outbox_expect(strpos($dispatcher_command_source, "'http://127.0.0.1:8000/api/publish'") !== false
	&& strpos($dispatcher_command_source, 'new HttpRealtimeTransport(') === false, 'centrifugo_selected_by_dispatch_command');

echo 'OUTBOX_UNIT_TESTS_PASSED=' . $passed . "\n";
echo 'OUTBOX_UNIT_TESTS_FAILED=' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
