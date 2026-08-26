<?php
define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 3) . '/application/libraries/Admin_capability_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Admin_capability_service.php';

function expect_true($condition, $label)
{
	if (!$condition) {
		fwrite(STDERR, "FAIL: {$label}\n");
		exit(1);
	}
	echo "PASS: {$label}\n";
}

class FakeCapabilityStore
{
	public $caps = array();
	public $activeSuperAdmins = 0;

	public function has($user_id, $code) { return !empty($this->caps[$user_id][$code]); }
	public function eligibleAdmin($user_id) { return (int)$user_id !== 14; }
	public function grant($user_id, $code, $by) { $this->caps[$user_id][$code] = true; return true; }
	public function revoke($user_id, $code) { unset($this->caps[$user_id][$code]); return true; }
	public function activeSuperAdminCount() { return $this->activeSuperAdmins; }
}

class FakeAuditWriter
{
	public $events = array();
	public $fail = false;
	public function write($event) { if ($this->fail) return false; $this->events[] = $event; return true; }
}

$store = new FakeCapabilityStore();
$audit = new FakeAuditWriter();
$service = new Admin_capability_service($store, $audit, true);
$serviceOff = new Admin_capability_service($store, $audit, false);
$ordinary = array('user_id' => 10, 'role' => 'admin', 'status' => 'aktif');
$super = array('user_id' => 11, 'role' => 'admin', 'status' => 'aktif');
$store->grant(11, 'super_admin', 99);
$store->activeSuperAdmins = 1;

expect_true(!$service->hasCapability($ordinary, 'clinical_audit'), 'ordinary admin has no sensitive capability');
expect_true(!$service->hasCapability($super, 'clinical_audit'), 'super_admin does not imply clinical_audit');
expect_true(!$service->hasCapability($super, 'clinical_taxonomy_manage'), 'super_admin does not imply taxonomy capability');
expect_true(!$service->grant($ordinary, 10, 'clinical_audit')['allowed'], 'non-super-admin cannot grant capability');
expect_true($service->grant($super, 10, 'clinical_audit')['allowed'], 'super_admin can grant allowed capability');
expect_true(!$service->grant($super, 10, 'clinical_audit')['changed'], 'duplicate grant is idempotent');
expect_true($service->revoke($super, 10, 'clinical_audit')['allowed'], 'super_admin can revoke capability');
expect_true(count($audit->events) === 2 && $audit->events[1]['action'] === 'admin_capability_revoked', 'revoke is audited');
expect_true(!$service->revoke($super, 11, 'super_admin')['allowed'], 'cannot remove last active super_admin');
expect_true(!$service->grant(array('user_id'=>12,'role'=>'admin','status'=>'nonaktif'), 12, 'clinical_audit')['allowed'], 'inactive admin is ineligible');
expect_true(!$service->grant(array('user_id'=>13,'role'=>'dokter','status'=>'aktif'), 13, 'clinical_audit')['allowed'], 'non-admin is ineligible');
expect_true(!$service->grant($super, 14, 'clinical_audit')['allowed'], 'inactive/non-admin target is ineligible');
$audit->fail = true;
expect_true(!$service->grant($super, 10, 'clinical_audit')['allowed'] && !$store->has(10, 'clinical_audit'), 'capability audit failure rolls back grant');
expect_true(!$serviceOff->featureEnabled(), 'capability feature defaults off');
