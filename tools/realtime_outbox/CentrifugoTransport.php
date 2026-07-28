<?php

require_once __DIR__ . '/RealtimeTransport.php';

final class CentrifugoTransport implements RealtimeTransport
{
	private const PUBLISH_PATH = '/api/publish';
	private const PAYLOAD_KEYS = array(
		'aggregate_id', 'audience', 'event_id', 'event_type', 'invalidation', 'version',
	);
	private const EVENT_INVALIDATIONS = array(
		'notification.created' => 'notifications',
		'request.assignment.changed' => 'assignment',
		'nakes.presence.changed' => 'presence',
		'visit.route.changed' => 'route',
		'visit.media.changed' => 'media',
		'medicalrecord.diagnoses.changed' => 'diagnoses',
	);

	private $endpoint;
	private $api_key;
	private $connect_timeout_ms;
	private $total_timeout_ms;
	private $maximum_response_bytes;
	private $client;

	public function __construct(
		$endpoint,
		$api_key,
		$connect_timeout_ms = 1500,
		$total_timeout_ms = 4000,
		?callable $client = null,
		array $allowed_hosts = array(),
		$maximum_response_bytes = 65536
	) {
		$this->endpoint = self::validateEndpoint($endpoint, $allowed_hosts);
		if (!is_string($api_key) || preg_match('/\A[\x21-\x7E]{16,4096}\z/D', $api_key) !== 1) {
			throw new InvalidArgumentException('centrifugo_api_key_invalid');
		}
		$this->api_key = $api_key;
		$this->connect_timeout_ms = self::boundedInteger($connect_timeout_ms, 100, 5000, 'centrifugo_timeout_invalid');
		$this->total_timeout_ms = self::boundedInteger($total_timeout_ms, $this->connect_timeout_ms, 10000, 'centrifugo_timeout_invalid');
		$this->maximum_response_bytes = self::boundedInteger($maximum_response_bytes, 256, 1048576, 'centrifugo_response_limit_invalid');
		$this->client = $client;
	}

	public function publish(array $message)
	{
		if (!self::validMessage($message)) {
			return RealtimeTransportResult::permanent('centrifugo_message_invalid');
		}

		try {
			$body = json_encode(array(
				'channel' => $message['audience_key'],
				'data' => $message['payload'],
				'idempotency_key' => $message['idempotency_key'],
			), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		} catch (Throwable $exception) {
			return RealtimeTransportResult::permanent('centrifugo_message_invalid');
		}

		$request = array(
			'method' => 'POST',
			'url' => $this->endpoint,
			'headers' => array(
				'Accept: application/json',
				'Content-Type: application/json',
				'X-API-Key: ' . $this->api_key,
			),
			'body' => $body,
			'connect_timeout_ms' => $this->connect_timeout_ms,
			'total_timeout_ms' => $this->total_timeout_ms,
			'maximum_response_bytes' => $this->maximum_response_bytes,
		);

		try {
			$response = $this->client
				? call_user_func($this->client, $request)
				: $this->curlRequest($request);
		} catch (Throwable $exception) {
			return RealtimeTransportResult::retryable('centrifugo_connection_failed');
		}

		return $this->classifyResponse($response);
	}

	private function classifyResponse($response)
	{
		if (!is_array($response) || !array_key_exists('status', $response) || !array_key_exists('body', $response)) {
			return RealtimeTransportResult::retryable('centrifugo_response_invalid');
		}
		$failure = isset($response['failure']) && is_string($response['failure']) ? $response['failure'] : '';
		if ($failure === 'connect_timeout') {
			return RealtimeTransportResult::retryable('centrifugo_connect_timeout');
		}
		if ($failure === 'total_timeout') {
			return RealtimeTransportResult::retryable('centrifugo_total_timeout');
		}
		if ($failure === 'response_too_large') {
			return RealtimeTransportResult::retryable('centrifugo_response_too_large');
		}
		if ($failure !== '') {
			return RealtimeTransportResult::retryable('centrifugo_connection_failed');
		}

		$body = $response['body'];
		if (!is_string($body)) {
			return RealtimeTransportResult::retryable('centrifugo_response_invalid');
		}
		if (strlen($body) > $this->maximum_response_bytes) {
			return RealtimeTransportResult::retryable('centrifugo_response_too_large');
		}

		$status = filter_var($response['status'], FILTER_VALIDATE_INT);
		if ($status === false || $status < 0 || $status > 599) {
			return RealtimeTransportResult::retryable('centrifugo_response_invalid');
		}
		if ($status === 0) {
			return RealtimeTransportResult::retryable('centrifugo_connection_failed');
		}
		if ($status < 200 || $status >= 300) {
			return self::httpFailure($status);
		}

		try {
			$decoded = json_decode($body, false, 16, JSON_THROW_ON_ERROR);
		} catch (Throwable $exception) {
			return RealtimeTransportResult::retryable('centrifugo_response_invalid');
		}
		if (!is_object($decoded)) {
			return RealtimeTransportResult::retryable('centrifugo_response_invalid');
		}
		if (property_exists($decoded, 'error')) {
			return self::apiFailure($decoded->error);
		}
		if (!property_exists($decoded, 'result') || (!is_object($decoded->result) && !is_array($decoded->result))) {
			return RealtimeTransportResult::retryable('centrifugo_result_invalid');
		}
		return RealtimeTransportResult::success();
	}

	private static function httpFailure($status)
	{
		if ($status === 408) {
			return RealtimeTransportResult::retryable('centrifugo_request_timeout');
		}
		if ($status === 429) {
			return RealtimeTransportResult::retryable('centrifugo_rate_limited');
		}
		if ($status >= 500) {
			return RealtimeTransportResult::retryable('centrifugo_server_unavailable');
		}
		if ($status === 401 || $status === 403) {
			return RealtimeTransportResult::permanent('centrifugo_auth_rejected');
		}
		if ($status === 404) {
			return RealtimeTransportResult::permanent('centrifugo_channel_rejected');
		}
		if ($status >= 300 && $status < 400) {
			return RealtimeTransportResult::permanent('centrifugo_redirect_rejected');
		}
		return RealtimeTransportResult::permanent('centrifugo_request_rejected');
	}

	private static function apiFailure($error)
	{
		if (!is_object($error) || !property_exists($error, 'code') || !is_int($error->code)) {
			return RealtimeTransportResult::permanent('centrifugo_api_error');
		}
		if ($error->code === 100) {
			return RealtimeTransportResult::retryable('centrifugo_server_unavailable');
		}
		if (in_array($error->code, array(102, 104), true)) {
			return RealtimeTransportResult::permanent('centrifugo_channel_rejected');
		}
		return RealtimeTransportResult::permanent('centrifugo_api_error');
	}

	private static function validMessage(array $message)
	{
		$keys = array_keys($message);
		sort($keys);
		if ($keys !== array('audience_key', 'audience_type', 'idempotency_key', 'payload')) {
			return false;
		}
		$channel_type = is_string($message['audience_key']) ? self::channelType($message['audience_key']) : null;
		if ($channel_type === null || !is_string($message['audience_type'])
			|| !hash_equals($channel_type, $message['audience_type'])
			|| !is_string($message['idempotency_key'])
			|| preg_match('/\A[a-f0-9]{64}\z/D', $message['idempotency_key']) !== 1
			|| !is_array($message['payload'])) {
			return false;
		}

		$payload_keys = array_keys($message['payload']);
		sort($payload_keys);
		$expected = self::PAYLOAD_KEYS;
		sort($expected);
		if ($payload_keys !== $expected
			|| !is_string($message['payload']['audience'])
			|| !hash_equals($message['audience_key'], $message['payload']['audience'])
			|| !is_string($message['payload']['aggregate_id'])
			|| preg_match('/\A[1-9][0-9]{0,17}\z/D', $message['payload']['aggregate_id']) !== 1
			|| !is_string($message['payload']['event_id'])
			|| strlen($message['payload']['event_id']) > 128
			|| preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,31}[.:][A-Za-z0-9][A-Za-z0-9._:-]{0,126}\z/D', $message['payload']['event_id']) !== 1
			|| !is_string($message['payload']['event_type'])
			|| !array_key_exists($message['payload']['event_type'], self::EVENT_INVALIDATIONS)
			|| !is_string($message['payload']['invalidation'])
			|| !hash_equals(self::EVENT_INVALIDATIONS[$message['payload']['event_type']], $message['payload']['invalidation'])
			|| !is_int($message['payload']['version']) || $message['payload']['version'] < 1) {
			return false;
		}
		return true;
	}

	private static function channelType($channel)
	{
		if (strlen($channel) > 128 || preg_match('/[\x00-\x20\x7F]/', $channel) === 1) {
			return null;
		}
		if (preg_match('/\A(user|request):[1-9][0-9]{0,17}\z/D', $channel, $match) === 1) {
			return $match[1];
		}
		if (preg_match('/\Apuskesmas:([A-Za-z0-9][A-Za-z0-9._-]{0,99}):ops\z/D', $channel, $match) === 1
			&& strtoupper($match[1]) !== 'DEFAULT' && strpos($match[1], '..') === false) {
			return 'puskesmas';
		}
		return null;
	}

	private static function validateEndpoint($endpoint, array $allowed_hosts)
	{
		if (!is_string($endpoint) || strlen($endpoint) > 2048 || preg_match('/\A[\x21-\x7E]+\z/D', $endpoint) !== 1) {
			throw new InvalidArgumentException('centrifugo_endpoint_invalid');
		}
		try {
			$parts = parse_url($endpoint);
		} catch (Throwable $exception) {
			throw new InvalidArgumentException('centrifugo_endpoint_invalid');
		}
		if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])
			|| isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
			|| !isset($parts['path']) || !hash_equals(self::PUBLISH_PATH, $parts['path'])) {
			throw new InvalidArgumentException('centrifugo_endpoint_invalid');
		}
		$scheme = strtolower($parts['scheme']);
		$host = strtolower($parts['host']);
		$loopback = in_array($host, array('localhost', '127.0.0.1'), true);
		if ($scheme !== 'https' && !($scheme === 'http' && $loopback)) {
			throw new InvalidArgumentException('centrifugo_endpoint_not_allowed');
		}
		$normalized_hosts = array();
		foreach ($allowed_hosts as $allowed_host) {
			$allowed_host = is_string($allowed_host) ? strtolower(trim($allowed_host)) : '';
			$is_ip = filter_var($allowed_host, FILTER_VALIDATE_IP) !== false;
			$is_dns = preg_match('/\A[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?\z/D', $allowed_host) === 1
				&& strpos($allowed_host, '..') === false;
			if ($allowed_host === '' || (!$is_ip && !$is_dns)) {
				throw new InvalidArgumentException('centrifugo_host_allowlist_invalid');
			}
			$normalized_hosts[] = $allowed_host;
		}
		if (!$loopback && !in_array($host, array_values(array_unique($normalized_hosts)), true)) {
			throw new InvalidArgumentException('centrifugo_host_not_allowed');
		}
		return $endpoint;
	}

	private static function boundedInteger($value, $minimum, $maximum, $safe_code)
	{
		$value = filter_var($value, FILTER_VALIDATE_INT);
		if ($value === false || $value < $minimum || $value > $maximum) {
			throw new InvalidArgumentException($safe_code);
		}
		return (int) $value;
	}

	private function curlRequest(array $request)
	{
		if (!function_exists('curl_init')) {
			return array('status' => 0, 'body' => '', 'failure' => 'connection_failed');
		}
		$body = '';
		$response_too_large = false;
		$maximum = $this->maximum_response_bytes;
		$handle = curl_init($request['url']);
		curl_setopt_array($handle, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_HTTPHEADER => $request['headers'],
			CURLOPT_POSTFIELDS => $request['body'],
			CURLOPT_CONNECTTIMEOUT_MS => $request['connect_timeout_ms'],
			CURLOPT_TIMEOUT_MS => $request['total_timeout_ms'],
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_MAXREDIRS => 0,
			CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
			CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$body, &$response_too_large, $maximum) {
				if (strlen($body) + strlen($chunk) > $maximum) {
					$response_too_large = true;
					return 0;
				}
				$body .= $chunk;
				return strlen($chunk);
			},
		));
		curl_exec($handle);
		$error_number = curl_errno($handle);
		$status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
		$total_time = (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME);
		curl_close($handle);

		$failure = '';
		if ($response_too_large) {
			$failure = 'response_too_large';
		} elseif ($error_number === CURLE_OPERATION_TIMEDOUT) {
			$failure = $total_time * 1000 < $this->connect_timeout_ms + 50 ? 'connect_timeout' : 'total_timeout';
		} elseif ($error_number !== CURLE_OK) {
			$failure = 'connection_failed';
		}
		return array('status' => $status, 'body' => $body, 'failure' => $failure);
	}
}
