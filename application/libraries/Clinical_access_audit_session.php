<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_access_audit_session
{
	const SESSION_KEY = 'doclinc_clinical_audit_nonce';

	public static function ensure($session)
	{
		$current = $session->userdata(self::SESSION_KEY);
		if (is_string($current) && strlen($current) >= 32) return $current;
		$nonce = function_exists('random_bytes') ? bin2hex(random_bytes(32)) : sha1(uniqid('', true) . mt_rand());
		$session->set_userdata(self::SESSION_KEY, $nonce);
		return $nonce;
	}

	public static function hash($nonce)
	{
		return Clinical_access_audit_policy::sessionHash($nonce);
	}
}
