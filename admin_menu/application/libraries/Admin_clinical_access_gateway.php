<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Admin_clinical_access_gateway
{
	private $CI;
	private $actor;
	private $capabilities;
	private $clinicalEnabled;

	public function __construct($CI)
	{
		$this->CI = $CI;
		$root = dirname(APPPATH, 2) . '/application/libraries/';
		foreach (array('Doclinc_feature_flags','Admin_capability_policy','Admin_capability_flags','Admin_capability_store','Admin_audit_log_writer','Clinical_access_audit_policy','Clinical_access_audit_session','Clinical_access_audit_service','Clinical_access_audit_ledger') as $class) {
			$file = $root . $class . '.php';
			if (is_file($file)) require_once $file;
		}
		$userId = (int) $CI->session->userdata('id');
		$row = $userId > 0 ? $CI->db->where('userId', $userId)->where('role', 'admin')->where('status', 'aktif')->get('users')->row_array() : array();
		$this->actor = array('user_id'=>$userId, 'role'=>(string)($row['role'] ?? ''), 'status'=>(string)($row['status'] ?? ''));
		$store = new Admin_capability_store($CI->db);
		$this->capabilities = new Admin_capability_service($store, new Admin_audit_log_writer($CI->db), Admin_capability_flags::enabled('capabilities'));
		$this->clinicalEnabled = Admin_capability_flags::enabled('clinical_audit');
	}

	public function featureEnabled() { return $this->clinicalEnabled; }

	public function authorizeRecord($recordId, array $reason, array $context)
	{
		if (!$this->clinicalEnabled) return array('allowed'=>false, 'error'=>'feature_disabled', 'requires_reason'=>false);
		$ledger = new Clinical_access_audit_ledger($this->CI->db);
		$nonce = Clinical_access_audit_session::ensure($this->CI->session);
		$context['ip_address'] = (string) $this->CI->input->ip_address();
		$context['user_agent'] = (string) $this->CI->input->user_agent();
		return Clinical_access_audit_service::authorize($this->actor, true, $this->capabilities->hasCapability($this->actor, Admin_capability_policy::CLINICAL_AUDIT), $recordId, $nonce, $reason, $context, $ledger);
	}
}
