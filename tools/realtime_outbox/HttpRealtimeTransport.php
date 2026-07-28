<?php

require_once __DIR__ . '/RealtimeTransport.php';

final class HttpRealtimeTransport implements RealtimeTransport
{
	private $endpoint;
	private $secret;
	private $connect_timeout_ms;
	private $total_timeout_ms;
	private $client;

	public function __construct($endpoint, $secret, $connect_timeout_ms = 1500, $total_timeout_ms = 4000, ?callable $client = null, array $allowed_hosts = array())
	{
		$this->endpoint = self::validateEndpoint($endpoint, $allowed_hosts);
		if (!is_string($secret) || strlen($secret) < 16 || strlen($secret) > 4096) {
			throw new InvalidArgumentException('gateway_secret_invalid');
		}
		$this->secret = $secret;
		$this->connect_timeout_ms = self::boundedTimeout($connect_timeout_ms, 100, 5000);
		$this->total_timeout_ms = self::boundedTimeout($total_timeout_ms, $this->connect_timeout_ms, 10000);
		$this->client = $client;
	}

	public function publish(array $message)
	{
		if (!$this->validMessage($message)) {
			return RealtimeTransportResult::permanent('transport_message_invalid');
		}
		try {
			$body = json_encode(array(
				'channel' => $message['audience_key'],
				'data' => $message['payload'],
				'idempotency_key' => $message['idempotency_key'],
			), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		} catch (Throwable $exception) {
			return RealtimeTransportResult::permanent('transport_message_invalid');
		}

		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->secret,
		);
		try {
			$response = $this->client
				? call_user_func($this->client, $this->endpoint, $headers, $body, $this->connect_timeout_ms, $this->total_timeout_ms)
				: $this->curlRequest($headers, $body);
		} catch (Throwable $exception) {
			return RealtimeTransportResult::retryable('gateway_request_failed');
		}
		if (!is_array($response) || !isset($response['status']) || !array_key_exists('body', $response)) {
			return RealtimeTransportResult::retryable('gateway_response_invalid');
		}
		if (!empty($response['timed_out'])) {
			return RealtimeTransportResult::retryable('gateway_timeout');
		}
		$status = (int) $response['status'];
		if ($status < 200 || $status >= 300) {
			return $status >= 400 && $status < 500 && !in_array($status, array(408, 409, 425, 429), true)
				? RealtimeTransportResult::permanent('gateway_http_permanent')
				: RealtimeTransportResult::retryable('gateway_http_retryable');
		}
		try {
			$decoded = json_decode((string) $response['body'], true, 16, JSON_THROW_ON_ERROR);
		} catch (Throwable $exception) {
			return RealtimeTransportResult::retryable('gateway_json_invalid');
		}
		if (!is_array($decoded)) {
			return RealtimeTransportResult::retryable('gateway_json_invalid');
		}
		if (array_key_exists('error', $decoded) && $decoded['error'] !== null) {
			$retryable = is_array(isset($decoded['error']) ? $decoded['error'] : null)
				&& isset($decoded['error']['retryable']) && $decoded['error']['retryable'] === true;
			return $retryable
				? RealtimeTransportResult::retryable('gateway_logical_retryable')
				: RealtimeTransportResult::permanent('gateway_logical_permanent');
		}
		if (!isset($decoded['success']) || $decoded['success'] !== true) {
			return RealtimeTransportResult::retryable('gateway_result_invalid');
		}
		return RealtimeTransportResult::success();
	}

	private static function validateEndpoint($endpoint, array $allowed_hosts)
	{
		if (!is_string($endpoint) || strlen($endpoint) > 2048 || preg_match('//u', $endpoint) !== 1) {
			throw new InvalidArgumentException('gateway_endpoint_invalid');
		}
		$parts = parse_url($endpoint);
		if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])
			|| isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
			throw new InvalidArgumentException('gateway_endpoint_invalid');
		}
		$scheme = strtolower($parts['scheme']);
		$host = strtolower($parts['host']);
		$loopback = in_array($host, array('localhost', '127.0.0.1', '::1'), true);
		if ($scheme !== 'https' && !($scheme === 'http' && $loopback)) {
			throw new InvalidArgumentException('gateway_endpoint_not_allowed');
		}
		$allowed_hosts = array_values(array_unique(array_map(function ($value) {
			return is_string($value) ? strtolower(trim($value)) : '';
		}, $allowed_hosts)));
		if (!$loopback && !in_array($host, $allowed_hosts, true)) {
			throw new InvalidArgumentException('gateway_host_not_allowed');
		}
		return $endpoint;
	}

	private static function boundedTimeout($value, $minimum, $maximum)
	{
		$value = (int) $value;
		if ($value < $minimum || $value > $maximum) {
			throw new InvalidArgumentException('gateway_timeout_invalid');
		}
		return $value;
	}

	private function validMessage(array $message)
	{
		return isset($message['audience_key'], $message['payload'], $message['idempotency_key'])
			&& is_string($message['audience_key'])
			&& is_array($message['payload'])
			&& is_string($message['idempotency_key'])
			&& preg_match('/\A[a-f0-9]{64}\z/D', $message['idempotency_key']) === 1;
	}

	private function curlRequest(array $headers, $body)
	{
		if (!function_exists('curl_init')) {
			return array('status' => 0, 'body' => '', 'timed_out' => false);
		}
		$handle = curl_init($this->endpoint);
		curl_setopt_array($handle, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_CONNECTTIMEOUT_MS => $this->connect_timeout_ms,
			CURLOPT_TIMEOUT_MS => $this->total_timeout_ms,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
		));
		$response_body = curl_exec($handle);
		$error_number = curl_errno($handle);
		$status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
		curl_close($handle);
		return array(
			'status' => $status,
			'body' => is_string($response_body) ? $response_body : '',
			'timed_out' => $error_number === CURLE_OPERATION_TIMEDOUT,
		);
	}
}
