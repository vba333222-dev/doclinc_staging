<?php

class ClinicalPackageException extends RuntimeException
{
	private $safeCode;
	private $safeContext;

	public function __construct($safeCode, $message, array $safeContext = array())
	{
		parent::__construct((string) $message);
		$this->safeCode = (string) $safeCode;
		$this->safeContext = $safeContext;
	}

	public function getSafeCode()
	{
		return $this->safeCode;
	}

	public function getSafeContext()
	{
		return $this->safeContext;
	}
}
