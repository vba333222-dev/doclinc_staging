<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Nakes_placement_store
{
	private $db;
	private $actorUserId = 0;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function setActorUserId($userId)
	{
		$this->actorUserId = (int) $userId;
	}

	public function transaction($callback)
	{
		$this->db->trans_begin();
		try {
			$result = $callback();
			if ($result === false || $this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return false;
			}
			$this->db->trans_commit();
			return $result;
		} catch (Throwable $exception) {
			$this->db->trans_rollback();
			return false;
		}
	}

	public function activePlacement($staffId)
	{
		$row = $this->db->query('SELECT placement_id,staff_id,facility_code,effective_from,effective_until,status,created_by_user_id FROM ' . $this->db->dbprefix('nakes_facility_placements') . ' WHERE staff_id = ? AND status = ? ORDER BY effective_from DESC, placement_id DESC LIMIT 1 FOR UPDATE', array((int)$staffId, 'active'))->row_array();
		return $row ?: null;
	}

	public function destination($code)
	{
		return $this->db->select('kode_pkm,status')->where('kode_pkm', strtoupper(trim((string)$code)))->limit(1)->get('m_puskesmas')->row_array() ?: null;
	}

	public function identityForStaff($staffId)
	{
		$identityHelper = dirname(APPPATH, 2) . '/application/helpers/request_authz_helper.php';
		if (is_file($identityHelper)) require_once $identityHelper;
		$staff = $this->db->select('user_id')->where('staff_id',(int)$staffId)->limit(1)->get('puskesmas_staff')->row();
		if (!$staff || (int)$staff->user_id < 1 || !function_exists('doclinc_dokter_identity_context')) return array('valid'=>false);
		$identity = doclinc_dokter_identity_context((int)$staff->user_id);
		return is_array($identity) ? array('valid'=>!empty($identity['valid']),'account_type'=>(string)($identity['account_type'] ?? ''),'role'=>(string)($identity['role'] ?? ''),'status'=>(string)($identity['user_status'] ?? '')) : array('valid'=>false);
	}

	public function scheduledTransfer($staffId)
	{
		return $this->db->where('staff_id',(int)$staffId)->where('status','scheduled')->limit(1)->get('nakes_facility_transfers')->row_array() ?: null;
	}

	public function blockers($staffId)
	{
		$staff = $this->db->select('staff_id,user_id')->where('staff_id',(int)$staffId)->limit(1)->get('puskesmas_staff')->row();
		if (!$staff) return array(array('kind'=>'staff_assignment','active'=>true));
		$userId = (int)$staff->user_id;
		$result = array();
		$activeStatuses = array('Pending','pending','Accepted','accepted','In_Service','in_service','In Service','en_route','En_Route','arrived','Arrived','in_service');
		$terminalStatuses = array('Completed','completed','Cancelled','cancelled','Rejected','rejected','Declined','declined','Expired','expired');
		if ($userId > 0 && $this->db->field_exists('responsible_doctor_user_id','requests')) {
			if ($this->db->where('responsible_doctor_user_id',$userId)->where_not_in('request_status',$terminalStatuses)->count_all_results('requests') > 0) $result[] = array('kind'=>'responsible_doctor','active'=>true);
		}
		if ($userId > 0 && $this->db->field_exists('visit_performer_user_id','requests')) {
			$visitProjectionQuery = 'SELECT COUNT(*) AS blocker_count FROM ' . $this->db->dbprefix('requests') . ' r WHERE r.visit_performer_user_id = ? AND r.request_status NOT IN (' . implode(',', array_fill(0, count($terminalStatuses), '?')) . ')';
			$visitProjectionBinds = array($userId);
			foreach ($terminalStatuses as $terminalStatus) $visitProjectionBinds[] = $terminalStatus;
			if ($this->db->table_exists('visit_dispositions')) {
				$visitProjectionQuery .= ' AND NOT EXISTS (SELECT 1 FROM ' . $this->db->dbprefix('visit_dispositions') . ' d WHERE d.request_id = r.request_id)';
			}
			$visitProjection = $this->db->query($visitProjectionQuery, $visitProjectionBinds)->row();
			if ($visitProjection && (int) $visitProjection->blocker_count > 0) $result[] = array('kind'=>'visit_performer','active'=>true);
		}
		if ($userId > 0 && $this->db->field_exists('assigned_nakes_user_id','requests')) {
			if ($this->db->where('assigned_nakes_user_id',$userId)->where_not_in('request_status',$terminalStatuses)->count_all_results('requests') > 0) $result[] = array('kind'=>'accepted_request','active'=>true);
		}
		if ($this->db->table_exists('request_staff_assignments')) {
			if ($this->db->where('staff_id',(int)$staffId)->where('status','aktif')->count_all_results('request_staff_assignments') > 0) $result[] = array('kind'=>'staff_assignment','active'=>true);
		}
		foreach (array('request_responsible_doctor_assignments'=>'responsible_doctor','request_visit_performer_assignments'=>'visit_performer') as $table=>$kind) {
			if ($this->db->table_exists($table) && $this->db->where('staff_id',(int)$staffId)->where('status','aktif')->count_all_results($table) > 0) $result[] = array('kind'=>$kind,'active'=>true);
		}
		return $result;
	}

	public function insertTransfer($row) { unset($row['scheduled_staff_key']); $this->db->insert('nakes_facility_transfers',$row); if ($this->db->affected_rows() !== 1) return false; $row['transfer_id']=(int)$this->db->insert_id(); return $row; }
	public function transfer($id, $forUpdate = false) {
		$query = $forUpdate
			? $this->db->query('SELECT * FROM ' . $this->db->dbprefix('nakes_facility_transfers') . ' WHERE transfer_id = ? LIMIT 1 FOR UPDATE', array((int)$id))
			: $this->db->where('transfer_id',(int)$id)->limit(1)->get('nakes_facility_transfers');
		return $query->row_array() ?: null;
	}
	public function completeTransfer($id,$now) { $ok=$this->db->where('transfer_id',(int)$id)->where('status','scheduled')->update('nakes_facility_transfers',array('status'=>'completed','completed_by_user_id'=>$this->currentAdminId(),'completed_at'=>$now)); return $ok ? $this->transfer($id) : false; }
	public function cancelTransfer($id,$actor,$now) { return $this->db->where('transfer_id',(int)$id)->where('status','scheduled')->update('nakes_facility_transfers',array('status'=>'cancelled','cancelled_by_user_id'=>(int)$actor,'cancelled_at'=>$now)); }
	public function endPlacement($id,$now) { return $this->db->where('placement_id',(int)$id)->where('status','active')->update('nakes_facility_placements',array('status'=>'ended','effective_until'=>$now,'ended_by_user_id'=>$this->currentAdminId())); }
	public function insertPlacement($row) { unset($row['active_staff_key']); $this->db->insert('nakes_facility_placements',$row); if ($this->db->affected_rows() !== 1) return false; $row['placement_id']=(int)$this->db->insert_id(); return $row; }
	public function updateProjection($staffId,$destination) {
		$staff=$this->db->select('user_id')->where('staff_id',(int)$staffId)->limit(1)->get('puskesmas_staff')->row();
		if (!$this->db->where('staff_id',(int)$staffId)->update('puskesmas_staff',array('kode_pkm'=>$destination))) return false;
		if ($staff && (int)$staff->user_id > 0 && $this->db->table_exists('users')) {
			$user=$this->db->select('role,status')->where('userId',(int)$staff->user_id)->limit(1)->get('users')->row();
			if ($user && (string)$user->role === 'dokter' && (string)$user->status === 'aktif' && !$this->db->where('userId',(int)$staff->user_id)->update('users',array('remark'=>$destination))) return false;
		}
		return true;
	}
	public function audit($event,$data) {
		if (!$this->db->table_exists('audit_logs')) return false;
		$actorId = isset($data['actor_user_id']) ? (int)$data['actor_user_id'] : $this->currentAdminId();
		return $this->db->insert('audit_logs',array('actor_user_id'=>$actorId,'action'=>$event,'entity_type'=>'puskesmas_staff','entity_id'=>(string)($data['staff_id'] ?? ''),'metadata_json'=>json_encode($data),'ip_address'=>isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:null,'user_agent'=>isset($_SERVER['HTTP_USER_AGENT'])?substr((string)$_SERVER['HTTP_USER_AGENT'],0,500):null,'created_at'=>date('Y-m-d H:i:s')));
	}
	private function currentAdminId() { return $this->actorUserId > 0 ? $this->actorUserId : (function_exists('current_user_id') ? (int)current_user_id() : 0); }
}
