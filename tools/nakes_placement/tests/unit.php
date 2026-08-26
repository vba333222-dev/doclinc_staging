<?php
if (!defined('BASEPATH')) define('BASEPATH', __DIR__ . '/../../../system/');
require_once __DIR__ . '/../../../application/libraries/Nakes_placement_policy.php';
require_once __DIR__ . '/../../../application/libraries/Nakes_placement_service.php';

$passed = 0; $failed = 0;
function expect_true($condition, $label) { global $passed, $failed; if ($condition) { $passed++; echo "PASS {$label}\n"; } else { $failed++; echo "FAIL {$label}\n"; } }

expect_true(Nakes_placement_policy::canUsePlacement(array('valid'=>true,'account_type'=>'personal','role'=>'dokter','status'=>'aktif'), true), 'active_personal_identity_eligible');
expect_true(!Nakes_placement_policy::canUsePlacement(array('valid'=>true,'account_type'=>'command_center','role'=>'dokter','status'=>'aktif'), true), 'command_center_not_personal_placement');
expect_true(!Nakes_placement_policy::canUsePlacement(array('valid'=>true,'account_type'=>'personal','role'=>'dokter','status'=>'nonaktif'), true), 'inactive_identity_rejected');
expect_true(Nakes_placement_policy::canManageTransfer(array('user_id'=>2,'role'=>'admin','status'=>'aktif')), 'active_admin_can_manage_transfer');
expect_true(!Nakes_placement_policy::canManageTransfer(array('user_id'=>2,'role'=>'dokter','status'=>'aktif')), 'personal_clinician_cannot_manage_transfer');
expect_true(!Nakes_placement_policy::validDestination(array('kode_pkm'=>'PKM01','status'=>'aktif'), 'PKM01'), 'same_facility_rejected');
expect_true(!Nakes_placement_policy::validDestination(array('kode_pkm'=>'PKM02','status'=>'nonaktif'), 'PKM02'), 'inactive_destination_rejected');
expect_true(Nakes_placement_policy::validDestination(array('kode_pkm'=>'PKM02','status'=>'aktif'), 'PKM01'), 'active_destination_accepted');
expect_true(Nakes_placement_policy::canDirectEditFacility('PKM01','PKM02', false), 'legacy_edit_when_feature_off');
expect_true(!Nakes_placement_policy::canDirectEditFacility('PKM01','PKM02', true), 'direct_facility_edit_blocked_when_enabled');
expect_true(Nakes_placement_policy::canDirectEditFacility('PKM01','PKM01', true), 'unchanged_facility_edit_allowed');
expect_true(Nakes_placement_policy::transferBlockers(array()), 'no_active_assignment_allows_transfer');
expect_true(!Nakes_placement_policy::transferBlockers(array(array('kind'=>'responsible_doctor','active'=>true))), 'responsible_doctor_blocks_transfer');
expect_true(!Nakes_placement_policy::transferBlockers(array(array('kind'=>'visit_performer','active'=>true))), 'visit_performer_blocks_transfer');
expect_true(!Nakes_placement_policy::transferBlockers(array(array('kind'=>'staff_assignment','active'=>true))), 'legacy_staff_assignment_blocks_transfer');
expect_true(!Nakes_placement_policy::transferBlockers(array(array('kind'=>'accepted_request','active'=>true))), 'accepted_request_blocks_transfer');
expect_true(Nakes_placement_policy::canActivate('2026-08-25 10:00:00','2026-08-25 09:00:00', array()), 'due_transfer_can_activate');
expect_true(!Nakes_placement_policy::canActivate('2026-08-25 08:00:00','2026-08-25 09:00:00', array()), 'early_transfer_cannot_activate');
expect_true(!Nakes_placement_policy::canActivate('2026-08-25 10:00:00','2026-08-25 09:00:00', array(array('kind'=>'accepted_request','active'=>true))), 'blocked_transfer_cannot_activate');

$store = new class {
	public $placements = array(array('placement_id'=>1,'staff_id'=>8,'facility_code'=>'PKM01','status'=>'active','effective_from'=>'2026-01-01 00:00:00','effective_until'=>null));
	public $transfers = array(); public $audit = array(); public $projection = array('kode_pkm'=>'PKM01','remark'=>'PKM01','status'=>'aktif'); public $failAudit = false;
	public function transaction($callback) { $snapshot = array($this->placements,$this->transfers,$this->audit,$this->projection); try { $result = $callback(); if ($result === false) { throw new Exception('rollback'); } return $result; } catch (Exception $e) { $this->placements=$snapshot[0];$this->transfers=$snapshot[1];$this->audit=$snapshot[2];$this->projection=$snapshot[3]; return false; } }
	public function activePlacement($staff) { foreach($this->placements as $p){if($p['staff_id']==$staff && $p['status']==='active') return $p;} return null; }
	public function destination($code) { return array('kode_pkm'=>$code,'status'=>'aktif'); }
	public function identityForStaff($staff) { return array('valid'=>true,'account_type'=>'personal','role'=>'dokter','status'=>'aktif'); }
	public function blockers($staff) { return array(); }
	public function insertTransfer($row) { $row['transfer_id']=count($this->transfers)+1; $this->transfers[]=$row; return $row; }
	public function transfer($id) { foreach($this->transfers as $t){if($t['transfer_id']==$id)return $t;} return null; }
	public function completeTransfer($id,$now) { foreach($this->transfers as &$t){if($t['transfer_id']==$id){$t['status']='completed';$t['completed_at']=$now;return $t;}} return null; }
	public function endPlacement($id,$now) { foreach($this->placements as &$p){if($p['placement_id']==$id){$p['status']='ended';$p['effective_until']=$now;return true;}} return false; }
	public function insertPlacement($row) { $row['placement_id']=count($this->placements)+1;$this->placements[]=$row;return $row; }
	public function updateProjection($staff,$destination) { $this->projection['kode_pkm']=$destination;$this->projection['remark']=$destination;return true; }
	public function cancelTransfer($id,$actor,$now) { foreach($this->transfers as &$t){if($t['transfer_id']==$id && $t['status']==='scheduled'){$t['status']='cancelled';$t['cancelled_by_user_id']=$actor;$t['cancelled_at']=$now;return $t;}} return false; }
	public function audit($event,$data) { if($this->failAudit)return false;$this->audit[]=array('event'=>$event,'data'=>$data);return true; }
};
$service = new Nakes_placement_service($store, true, array('user_id'=>2,'role'=>'admin','status'=>'aktif'));
$blockedService = new Nakes_placement_service($store, true, array('user_id'=>9002,'role'=>'dokter','status'=>'aktif'));
expect_true($blockedService->schedule(array('staff_id'=>8,'current_facility'=>'PKM01','destination_facility'=>'PKM02','effective_at'=>'2026-09-01 00:00:00','identity'=>array('valid'=>true,'account_type'=>'personal','role'=>'dokter','status'=>'aktif'))) === false, 'non_admin_schedule_rejected');
$scheduled = $service->schedule(array('staff_id'=>8,'current_facility'=>'PKM01','destination_facility'=>'PKM02','effective_at'=>'2026-09-01 00:00:00','reason'=>'penugasan','actor_user_id'=>2,'identity'=>array('valid'=>true,'account_type'=>'personal','role'=>'dokter','status'=>'aktif')));
expect_true(is_array($scheduled) && $store->projection['kode_pkm']==='PKM01' && count($store->transfers)===1, 'schedule_preserves_current_projection');
expect_true($service->activate($scheduled['transfer_id'],'2026-08-25 00:00:00') === false, 'before_effective_at_rejected');
$activated = $service->activate($scheduled['transfer_id'],'2026-09-01 00:00:00');
expect_true(is_array($activated) && $store->projection['kode_pkm']==='PKM02', 'activation_updates_projection');
expect_true($store->placements[0]['status']==='ended' && count($store->placements)===2, 'activation_closes_old_creates_new');
expect_true($store->projection['status']==='aktif', 'account_status_unchanged');
expect_true(count($store->audit)>=2, 'governance_audit_written');
expect_true($service->activate($scheduled['transfer_id'],'2026-09-01 00:00:00') === false, 'duplicate_activation_is_idempotent');
$cancelStore = new class extends stdClass {
	public $transfers=array(array('transfer_id'=>9,'staff_id'=>8,'status'=>'scheduled','effective_at'=>'2027-01-01 00:00:00'));
	public function transaction($callback){return $callback();} public function transfer($id){return $this->transfers[0];}
	public function cancelTransfer($id,$actor,$now){$this->transfers[0]['status']='cancelled';return $this->transfers[0];}
	public function audit($event,$data){return true;}
};
$cancelService = new Nakes_placement_service($cancelStore, true, array('user_id'=>2,'role'=>'admin','status'=>'aktif'));
expect_true(is_array($cancelService->cancel(9,'2026-08-25 00:00:00')) && $cancelStore->transfers[0]['status']==='cancelled', 'cancel_keeps_placement_unchanged');
$failStore = new class extends stdClass {
	public $placements=array(array('placement_id'=>1,'staff_id'=>8,'facility_code'=>'PKM01','status'=>'active')); public $projection='PKM01';
	public function transaction($callback){$snapshot=array($this->placements,$this->projection);$r=$callback();if($r===false){$this->placements=$snapshot[0];$this->projection=$snapshot[1];}return $r;}
	public function activePlacement($id){return $this->placements[0];} public function blockers($id){return array();} public function transfer($id){return array('transfer_id'=>1,'staff_id'=>8,'status'=>'scheduled','effective_at'=>'2026-01-01 00:00:00','destination_facility_code'=>'PKM02','requested_by_user_id'=>2);}
	public function endPlacement($id,$now){$this->placements[0]['status']='ended';return true;} public function insertPlacement($row){$this->placements[]=array('placement_id'=>2,'staff_id'=>8,'facility_code'=>'PKM02','status'=>'active');return $this->placements[1];} public function updateProjection($id,$code){$this->projection=$code;return true;} public function completeTransfer($id,$now){return array('status'=>'completed');} public function audit($e,$d){return false;}
};
$failService=new Nakes_placement_service($failStore,true,array('user_id'=>2,'role'=>'admin','status'=>'aktif'));
expect_true($failService->activate(1,'2026-08-25 00:00:00')===false && $failStore->placements[0]['status']==='active' && $failStore->projection==='PKM01', 'audit_failure_rolls_transfer_back');

echo "PLACEMENT_TESTS_PASSED={$passed}\nPLACEMENT_TESTS_FAILED={$failed}\n";
exit($failed ? 1 : 0);
