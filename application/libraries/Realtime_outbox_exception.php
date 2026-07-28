<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Realtime_outbox_exception extends RuntimeException
{
	private $safe_code;

	public function __construct($safe_code)
	{
		$this->safe_code = is_string($safe_code) && preg_match('/\A[a-z0-9_]+\z/', $safe_code) === 1
			? $safe_code
			: 'outbox_contract_invalid';
		parent::__construct($this->safe_code);
	}

	public function getSafeCode()
	{
		return $this->safe_code;
	}
}
