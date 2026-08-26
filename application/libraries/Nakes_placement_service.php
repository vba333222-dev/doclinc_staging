<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Nakes_placement_policy.php';

class Nakes_placement_service
{
	private $store;
	private $enabled;
	private $actor;

	public function __construct($store, $enabled = false, $actor = array())
	{
		$this->store = $store;
		$this->enabled = $enabled === true;
		$this->actor = is_array($actor) ? $actor : array();
		if (method_exists($this->store, 'setActorUserId')) {
			$this->store->setActorUserId((int) ($this->actor['user_id'] ?? 0));
		}
	}

	public function schedule($input)
	{
		if (!$this->enabled || !Nakes_placement_policy::canManageTransfer($this->actor)) return false;
		$staffId = (int) ($input['staff_id'] ?? 0);
		$destinationCode = strtoupper(trim((string) ($input['destination_facility'] ?? '')));
		if ($staffId < 1) return false;
		return $this->store->transaction(function () use ($staffId, $destinationCode, $input) {
			if (!method_exists($this->store, 'identityForStaff')) return false;
			$staffIdentity = $this->store->identityForStaff($staffId);
			if (!Nakes_placement_policy::canUsePlacement($staffIdentity, true)) return false;
			$destination = $this->store->destination($destinationCode);
			$active = $this->store->activePlacement($staffId);
			$current = $active ? (string) $active['facility_code'] : '';
			if (!$destination || !Nakes_placement_policy::validDestination($destination, $current)
				|| !$active || strtoupper((string) $active['facility_code']) !== strtoupper($current)
				|| (method_exists($this->store, 'scheduledTransfer') && $this->store->scheduledTransfer($staffId))
				|| !Nakes_placement_policy::transferBlockers($this->store->blockers($staffId))) return false;
			$row = array('staff_id'=>$staffId,'from_placement_id'=>(int)$active['placement_id'],'destination_facility_code'=>$destinationCode,'effective_at'=>(string)$input['effective_at'],'reason'=>trim((string)($input['reason'] ?? '')),'status'=>'scheduled','requested_by_user_id'=>(int)$this->actor['user_id'],'requested_at'=>(string)($input['requested_at'] ?? date('Y-m-d H:i:s')));
			$transfer = $this->store->insertTransfer($row);
			if (!$transfer) return false;
			return $this->store->audit('nakes_transfer_scheduled', array('actor_user_id'=>(int)$this->actor['user_id'],'transfer_id'=>(int)$transfer['transfer_id'],'staff_id'=>(int)$transfer['staff_id'],'destination_facility_code'=>$transfer['destination_facility_code'])) ? $transfer : false;
		});
	}

	public function activate($transferId, $now)
	{
		if (!$this->enabled || !Nakes_placement_policy::canManageTransfer($this->actor)) return false;
		return $this->store->transaction(function () use ($transferId, $now) {
			$transfer = $this->store->transfer((int) $transferId, true);
			if (!$transfer || (string)$transfer['status'] !== 'scheduled') return false;
			$active = $this->store->activePlacement((int)$transfer['staff_id']);
			if (!$active || !Nakes_placement_policy::canActivate($now, $transfer['effective_at'], $this->store->blockers((int)$transfer['staff_id']))) return false;
			if (!$this->store->endPlacement((int)$active['placement_id'], $now)) return false;
			$new = $this->store->insertPlacement(array('staff_id'=>(int)$transfer['staff_id'],'facility_code'=>$transfer['destination_facility_code'],'effective_from'=>$transfer['effective_at'],'effective_until'=>null,'status'=>'active','created_by_user_id'=>(int)$this->actor['user_id'],'created_at'=>$now));
			if (!$new || !$this->store->updateProjection((int)$transfer['staff_id'], $transfer['destination_facility_code'])) return false;
			$completed = $this->store->completeTransfer((int)$transfer['transfer_id'], $now);
			return $completed && $this->store->audit('nakes_transfer_completed', array('actor_user_id'=>(int)$this->actor['user_id'],'transfer_id'=>(int)$transfer['transfer_id'],'staff_id'=>(int)$transfer['staff_id'],'source_facility_code'=>$active['facility_code'],'destination_facility_code'=>$transfer['destination_facility_code'])) ? $completed : false;
		});
	}

	public function cancel($transferId, $now)
	{
		if (!$this->enabled || !Nakes_placement_policy::canManageTransfer($this->actor)) return false;
		return $this->store->transaction(function () use ($transferId, $now) {
			$transfer = $this->store->transfer((int)$transferId, true);
			if (!$transfer || (string)$transfer['status'] !== 'scheduled') return false;
			if (method_exists($this->store, 'cancelTransfer')) $cancelled = $this->store->cancelTransfer((int)$transfer['transfer_id'], (int)$this->actor['user_id'], $now); else $cancelled = false;
			return $cancelled && $this->store->audit('nakes_transfer_cancelled', array('actor_user_id'=>(int)$this->actor['user_id'],'transfer_id'=>(int)$transfer['transfer_id'],'staff_id'=>(int)$transfer['staff_id'])) ? $cancelled : false;
		});
	}
}
