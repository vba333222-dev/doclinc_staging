<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Realtime_access_exception extends RuntimeException
{
	private $safe_error_code;

	public function __construct($safe_error_code)
	{
		$this->safe_error_code = (string) $safe_error_code;
		parent::__construct('Realtime access operation failed.');
	}

	public function safeErrorCode()
	{
		return $this->safe_error_code;
	}
}
