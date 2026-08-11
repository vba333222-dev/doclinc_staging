<?php
defined('BASEPATH') or exit('No direct script access allowed');

class First_login_token_policy
{
	const TTL_SECONDS = 600;

	public function issue($user_id, $session_binding, $now = null)
	{
		$user_id = (int) $user_id;
		$now = $now === null ? time() : (int) $now;
		if ($user_id < 1 || !$this->validBinding($session_binding) || $now < 1) {
			return null;
		}
		$token = bin2hex(random_bytes(32));
		return array(
			'token' => $token,
			'state' => array(
				'version' => 1,
				'user_id' => $user_id,
				'digest' => hash_hmac('sha256', $token, $session_binding),
				'expires_at' => $now + self::TTL_SECONDS,
			),
		);
	}

	public function validate($token, $state, $user_id, $session_binding, $now = null)
	{
		$now = $now === null ? time() : (int) $now;
		if (!is_string($token) || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1
			|| !is_array($state) || array_keys($state) !== array('version', 'user_id', 'digest', 'expires_at')
			|| (int) $state['version'] !== 1 || (int) $state['user_id'] !== (int) $user_id
			|| !is_string($state['digest']) || preg_match('/\A[a-f0-9]{64}\z/', $state['digest']) !== 1
			|| !is_int($state['expires_at']) || $now < 1 || $now >= $state['expires_at']
			|| !$this->validBinding($session_binding)) {
			return false;
		}
		return hash_equals($state['digest'], hash_hmac('sha256', $token, $session_binding));
	}

	public function consume($token, &$state, $user_id, $session_binding, $now = null)
	{
		$candidate_state = $state;
		$state = null;
		return $this->validate($token, $candidate_state, $user_id, $session_binding, $now);
	}

	private function validBinding($binding)
	{
		return is_string($binding) && preg_match('/\A[a-f0-9]{32}\z/', $binding) === 1;
	}
}
