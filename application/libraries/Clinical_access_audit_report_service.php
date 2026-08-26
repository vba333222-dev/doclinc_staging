<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_access_audit_report_service
{
	private $ledger;
	private $capabilities;
	public function __construct($ledger, $capabilities) { $this->ledger = $ledger; $this->capabilities = $capabilities; }
	public function report(array $actor, array $filters = array(), $limit = 100)
	{
		if (!$this->capabilities->hasCapability($actor, Admin_capability_policy::CLINICAL_AUDIT)) return array();
		return $this->ledger->report($filters, $limit, true);
	}
}
