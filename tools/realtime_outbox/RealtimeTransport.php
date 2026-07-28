<?php

final class RealtimeTransportResult
{
	public $successful;
	public $retryable;
	public $safe_error_code;

	private function __construct($successful, $retryable, $safe_error_code)
	{
		$this->successful = (bool) $successful;
		$this->retryable = (bool) $retryable;
		$this->safe_error_code = (string) $safe_error_code;
	}

	public static function success()
	{
		return new self(true, false, 'none');
	}

	public static function retryable($safe_error_code)
	{
		return new self(false, true, self::safeCode($safe_error_code));
	}

	public static function permanent($safe_error_code)
	{
		return new self(false, false, self::safeCode($safe_error_code));
	}

	private static function safeCode($value)
	{
		return is_string($value) && preg_match('/\A[a-z0-9_]{1,64}\z/', $value) === 1
			? $value
			: 'transport_error';
	}
}

interface RealtimeTransport
{
	public function publish(array $message);
}
