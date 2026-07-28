<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Mutation_token_policy
{
	private const STORE_KEY = 'doclinc_mutation_tokens_v1';
	private const TOKEN_VERSION = 'v1';
	private const DEFAULT_TTL_SECONDS = 600;
	private const MIN_TTL_SECONDS = 60;
	private const MAX_TTL_SECONDS = 900;
	private const MAX_ACTIVE_TOKENS = 12;

	private $hmac_secret;

	public function __construct($hmac_secret)
	{
		if (!is_string($hmac_secret) || strlen($hmac_secret) < 32) {
			throw new InvalidArgumentException('mutation_token_secret_invalid');
		}
		$this->hmac_secret = $hmac_secret;
	}

	public function issue(array &$session_store, $user_id, $session_id, $purpose, $ttl_seconds = self::DEFAULT_TTL_SECONDS, $now = null)
	{
		$user_id = (int) $user_id;
		$session_id = $this->validSessionId($session_id);
		$purpose = $this->validPurpose($purpose);
		$now = $this->validNow($now);
		$ttl_seconds = (int) $ttl_seconds;
		if ($user_id < 1 || $session_id === '' || $purpose === ''
			|| $ttl_seconds < self::MIN_TTL_SECONDS || $ttl_seconds > self::MAX_TTL_SECONDS) {
			throw new InvalidArgumentException('mutation_token_context_invalid');
		}

		$this->purgeExpired($session_store, $now);
		$tokens = $this->tokens($session_store);
		if (count($tokens) >= self::MAX_ACTIVE_TOKENS) {
			uasort($tokens, function ($left, $right) {
				return ((int) $left['issued_at']) <=> ((int) $right['issued_at']);
			});
			while (count($tokens) >= self::MAX_ACTIVE_TOKENS) {
				array_shift($tokens);
			}
		}

		$token_id = $this->base64UrlEncode(random_bytes(12));
		$nonce = $this->base64UrlEncode(random_bytes(32));
		$expires_at = $now + $ttl_seconds;
		$digest = $this->digest($token_id, $nonce, $user_id, $session_id, $purpose, $expires_at);
		$tokens[$token_id] = array(
			'digest' => $digest,
			'user_id' => $user_id,
			'session_id' => hash('sha256', $session_id),
			'purpose' => $purpose,
			'issued_at' => $now,
			'expires_at' => $expires_at,
		);
		$session_store[self::STORE_KEY] = $tokens;

		return array(
			'token' => self::TOKEN_VERSION . '.' . $token_id . '.' . $nonce,
			'expires_at' => $expires_at,
		);
	}

	public function consume(array &$session_store, $token, $user_id, $session_id, $purpose, $now = null)
	{
		$now = $this->validNow($now);
		$user_id = (int) $user_id;
		$session_id = $this->validSessionId($session_id);
		$purpose = $this->validPurpose($purpose);
		if (!is_string($token) || strlen($token) > 160 || $user_id < 1 || $session_id === '' || $purpose === '') {
			return false;
		}

		$parts = explode('.', $token);
		if (count($parts) !== 3 || !hash_equals(self::TOKEN_VERSION, $parts[0])
			|| !$this->validEncodedPart($parts[1], 16) || !$this->validEncodedPart($parts[2], 43)) {
			return false;
		}

		$tokens = $this->tokens($session_store);
		$token_id = $parts[1];
		if (!isset($tokens[$token_id]) || !is_array($tokens[$token_id])) {
			return false;
		}

		$record = $tokens[$token_id];
		unset($tokens[$token_id]);
		$session_store[self::STORE_KEY] = $tokens;

		$required = array('digest', 'user_id', 'session_id', 'purpose', 'expires_at');
		foreach ($required as $field) {
			if (!array_key_exists($field, $record)) {
				return false;
			}
		}
		if ((int) $record['expires_at'] <= $now || (int) $record['user_id'] !== $user_id
			|| !hash_equals((string) $record['session_id'], hash('sha256', $session_id))
			|| !hash_equals((string) $record['purpose'], $purpose)) {
			return false;
		}

		$expected = $this->digest($token_id, $parts[2], $user_id, $session_id, $purpose, (int) $record['expires_at']);
		return hash_equals((string) $record['digest'], $expected);
	}

	public function purgeExpired(array &$session_store, $now = null)
	{
		$now = $this->validNow($now);
		$tokens = $this->tokens($session_store);
		foreach ($tokens as $token_id => $record) {
			if (!is_array($record) || !isset($record['expires_at']) || (int) $record['expires_at'] <= $now) {
				unset($tokens[$token_id]);
			}
		}
		$session_store[self::STORE_KEY] = $tokens;
	}

	public function clear(array &$session_store)
	{
		unset($session_store[self::STORE_KEY]);
	}

	private function digest($token_id, $nonce, $user_id, $session_id, $purpose, $expires_at)
	{
		$message = implode('|', array(
			self::TOKEN_VERSION,
			$token_id,
			$nonce,
			(string) $user_id,
			$session_id,
			$purpose,
			(string) $expires_at,
		));
		return hash_hmac('sha256', $message, $this->hmac_secret);
	}

	private function tokens(array $session_store)
	{
		return isset($session_store[self::STORE_KEY]) && is_array($session_store[self::STORE_KEY])
			? $session_store[self::STORE_KEY]
			: array();
	}

	private function validSessionId($session_id)
	{
		if (!is_string($session_id)) {
			return '';
		}
		$session_id = trim($session_id);
		return $session_id !== '' && strlen($session_id) <= 256 ? $session_id : '';
	}

	private function validPurpose($purpose)
	{
		if (!is_string($purpose)) {
			return '';
		}
		$purpose = strtolower(trim($purpose));
		return preg_match('/\A[a-z][a-z0-9_.:-]{2,63}\z/', $purpose) === 1 ? $purpose : '';
	}

	private function validNow($now)
	{
		$now = $now === null ? time() : (int) $now;
		if ($now < 1) {
			throw new InvalidArgumentException('mutation_token_time_invalid');
		}
		return $now;
	}

	private function validEncodedPart($value, $expected_length)
	{
		return is_string($value) && strlen($value) === $expected_length
			&& preg_match('/\A[A-Za-z0-9_-]+\z/', $value) === 1;
	}

	private function base64UrlEncode($value)
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}
}
