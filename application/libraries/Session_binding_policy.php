<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Session_binding_policy
{
	public function issueToken()
	{
		return bin2hex(random_bytes(32));
	}

	public function tokenValid($token)
	{
		return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
	}

	public function tokenHash($token)
	{
		return $this->tokenValid($token) ? hash('sha256', $token) : null;
	}

	public function matches($token, $stored_hash)
	{
		$token_hash = $this->tokenHash($token);
		return $token_hash !== null
			&& is_string($stored_hash)
			&& preg_match('/\A[a-f0-9]{64}\z/', $stored_hash) === 1
			&& hash_equals($stored_hash, $token_hash);
	}
}
