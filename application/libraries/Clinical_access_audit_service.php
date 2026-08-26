<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_access_audit_service
{
	public static function authorize(array $actor, $featureEnabled, $hasCapability, $recordId, $nonce, array $reason, array $context, $ledger)
	{
		$denied = array('allowed'=>false, 'requires_reason'=>false, 'error'=>null);
		if (!$featureEnabled || !$hasCapability || !Clinical_access_audit_policy::activeAdmin($actor)) {
			$denied['error'] = 'clinical_access_denied'; return $denied;
		}
		$recordId = (int) $recordId;
		$sessionHash = Clinical_access_audit_policy::sessionHash($nonce);
		if ($recordId < 1 || $sessionHash === null) { $denied['error'] = 'invalid_audit_session'; return $denied; }
		$repeat = method_exists($ledger, 'hasRecordForSession') && $ledger->hasRecordForSession($recordId, $sessionHash, (int) $actor['user_id']);
		$validated = Clinical_access_audit_policy::reason($reason);
		if (!$repeat && empty($validated['valid'])) { $denied['requires_reason'] = true; $denied['error'] = $validated['error']; return $denied; }
		$row = array(
			'actor_user_id' => (int) $actor['user_id'], 'record_id' => $recordId,
			'request_id' => (int) ($context['request_id'] ?? 0), 'patient_user_id' => (int) ($context['patient_user_id'] ?? 0),
			'puskesmas_code' => (string) ($context['puskesmas_code'] ?? ''), 'access_kind' => (string) ($context['access_kind'] ?? 'read'),
			'audit_session_hash' => $sessionHash, 'first_access_in_session' => $repeat ? 0 : 1,
			'reason_code' => $repeat ? null : $validated['code'], 'reason_text' => $repeat ? null : ($validated['text'] ?? null),
			'reason_root_access_id' => null, 'ip_address' => (string) ($context['ip_address'] ?? ''),
			'user_agent' => (string) ($context['user_agent'] ?? ''), 'created_at' => (string) ($context['created_at'] ?? date('Y-m-d H:i:s')),
		);
		if (!$ledger->append($row)) { $denied['error'] = 'audit_write_failed'; return $denied; }
		return array('allowed'=>true, 'requires_reason'=>false, 'error'=>null, 'audit_row'=>$row);
	}
}
