<?php

class ClinicalSchemaException extends RuntimeException
{
	private $safeCode;
	private $safeContext;

	public function __construct($safeCode, $safeMessage = '', array $safeContext = array(), ?Throwable $previous = null)
	{
		$this->safeCode = (string) $safeCode;
		$this->safeContext = $safeContext;
		parent::__construct($safeMessage !== '' ? $safeMessage : $this->safeCode, 0, $previous);
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
