<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_access_audit_policy
{
	const REASONS = array('clinical_governance', 'quality_review', 'incident_review', 'complaint_followup', 'legal_regulatory', 'other');

	public static function activeAdmin(array $actor)
	{
		return (int) ($actor['user_id'] ?? 0) > 0 && ($actor['role'] ?? '') === 'admin' && ($actor['status'] ?? '') === 'aktif';
	}

	public static function reason(array $reason)
	{
		$code = trim((string) ($reason['reason_code'] ?? ''));
		if (!in_array($code, self::REASONS, true)) return array('valid' => false, 'error' => 'invalid_reason_code');
		$text = trim((string) ($reason['reason_text'] ?? ''));
		if ($code === 'other' && ($text === '' || strlen($text) > 500)) return array('valid' => false, 'error' => 'other_reason_text_required');
		return array('valid' => true, 'code' => $code, 'text' => $code === 'other' ? $text : null);
	}

	public static function sessionHash($nonce)
	{
		return is_string($nonce) && strlen($nonce) >= 16 ? hash('sha256', $nonce) : null;
	}
}
