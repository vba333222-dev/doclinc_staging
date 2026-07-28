<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'libraries/Realtime_access_exception.php';

class Realtime_token_service
{
	private const MIN_SECRET_BYTES = 32;
	private const MAX_SECRET_BYTES = 4096;
	private const MIN_TTL_SECONDS = 60;
	private const MAX_TTL_SECONDS = 300;

	private $secret;
	private $ttl_seconds;

	public function __construct(array $parameters = array())
	{
		$secret = $parameters['secret'] ?? '';
		$ttl = $parameters['ttl_seconds'] ?? 120;
		if (!is_string($secret)
			|| strlen($secret) < self::MIN_SECRET_BYTES
			|| strlen($secret) > self::MAX_SECRET_BYTES
			|| preg_match('/[\x00-\x1F\x7F]/', $secret) === 1) {
			throw new Realtime_access_exception('token_secret_invalid');
		}
		if (!is_int($ttl) || $ttl < self::MIN_TTL_SECONDS || $ttl > self::MAX_TTL_SECONDS) {
			throw new Realtime_access_exception('token_ttl_invalid');
		}
		$this->secret = $secret;
		$this->ttl_seconds = $ttl;
	}

	public function connectionToken($user_id, $issued_at = null)
	{
		return $this->encode($this->claims($user_id, null, $issued_at));
	}

	public function subscriptionToken($user_id, $channel, $issued_at = null)
	{
		if (!is_string($channel) || $channel === '') {
			throw new Realtime_access_exception('channel_invalid');
		}
		return $this->encode($this->claims($user_id, $channel, $issued_at));
	}

	public function expiresAt($issued_at = null)
	{
		$issued_at = $issued_at === null ? time() : $issued_at;
		if (!is_int($issued_at) || $issued_at < 1) {
			throw new Realtime_access_exception('issued_at_invalid');
		}
		return $issued_at + $this->ttl_seconds;
	}

	private function claims($user_id, $channel, $issued_at)
	{
		if (!is_int($user_id) || $user_id < 1) {
			throw new Realtime_access_exception('subject_invalid');
		}
		$issued_at = $issued_at === null ? time() : $issued_at;
		if (!is_int($issued_at) || $issued_at < 1) {
			throw new Realtime_access_exception('issued_at_invalid');
		}
		$claims = array('sub' => (string) $user_id);
		if ($channel !== null) {
			$claims['channel'] = $channel;
		}
		$claims['iat'] = $issued_at;
		$claims['exp'] = $issued_at + $this->ttl_seconds;
		return $claims;
	}

	private function encode(array $claims)
	{
		$header = array('alg' => 'HS256', 'typ' => 'JWT');
		$header_json = json_encode($header, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		$claims_json = json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		$input = self::base64Url($header_json) . '.' . self::base64Url($claims_json);
		return $input . '.' . self::base64Url(hash_hmac('sha256', $input, $this->secret, true));
	}

	private static function base64Url($value)
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}
}
