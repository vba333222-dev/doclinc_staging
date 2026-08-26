<?php
define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 3) . '/application/libraries/Clinical_access_audit_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Clinical_access_audit_service.php';

function audit_expect($condition, $label)
{
	if (!$condition) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
	echo "PASS: {$label}\n";
}

class FakeClinicalLedger
{
	public $rows = array(); public $fail = false;
	public function append($row) { if ($this->fail) return false; $this->rows[] = $row; return true; }
	public function hasRecordForSession($record_id, $session_hash, $actor_id = null) {
		foreach ($this->rows as $row) if ((int)$row['record_id'] === (int)$record_id && hash_equals($row['audit_session_hash'], $session_hash) && ($actor_id === null || (int)$row['actor_user_id'] === (int)$actor_id)) return true;
		return false;
	}
}

$ledger = new FakeClinicalLedger();
$actor = array('user_id'=>5, 'role'=>'admin', 'status'=>'aktif');
$nonce = 'logical-session-secret';
$first = Clinical_access_audit_service::authorize($actor, true, true, 101, $nonce, array('reason_code'=>'quality_review'), array('request_id'=>7), $ledger);
audit_expect($first['allowed'] && count($ledger->rows) === 1, 'accepted reason creates audit row');
audit_expect($ledger->rows[0]['audit_session_hash'] !== $nonce && strlen($ledger->rows[0]['audit_session_hash']) === 64, 'raw session nonce never enters ledger');
audit_expect(!isset($ledger->rows[0]['diagnosis']) && !isset($ledger->rows[0]['anamnesis']), 'clinical content absent from access audit row');
$repeat = Clinical_access_audit_service::authorize($actor, true, true, 101, $nonce, array(), array('request_id'=>7), $ledger);
audit_expect($repeat['allowed'] && count($ledger->rows) === 2, 'same record session does not require reason twice and still appends');
$other = Clinical_access_audit_service::authorize($actor, true, true, 102, $nonce, array(), array('request_id'=>8), $ledger);
audit_expect(!$other['allowed'] && $other['requires_reason'], 'different record requires reason');
$newSession = Clinical_access_audit_service::authorize($actor, true, true, 101, 'new-logical-session', array(), array('request_id'=>7), $ledger);
audit_expect(!$newSession['allowed'] && $newSession['requires_reason'], 'new logical session requires reason again');
audit_expect(!Clinical_access_audit_service::authorize($actor, true, false, 103, $nonce, array('reason_code'=>'quality_review'), array(), $ledger)['allowed'], 'missing clinical_audit denies access');
audit_expect(!Clinical_access_audit_service::authorize($actor, true, true, 104, $nonce, array('reason_code'=>'invalid'), array(), $ledger)['allowed'], 'malformed reason rejected');
$ledger->fail = true;
$failedWrite = Clinical_access_audit_service::authorize($actor, true, true, 105, $nonce, array('reason_code'=>'quality_review'), array(), $ledger);
audit_expect(!$failedWrite['allowed'] && $failedWrite['error'] === 'audit_write_failed', 'audit write failure fails closed');
