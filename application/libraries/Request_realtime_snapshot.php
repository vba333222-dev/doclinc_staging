<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Request_realtime_snapshot
{
	private $db;
	private $limit;

	public function __construct($db, $limit = 100)
	{
		$this->db = $db;
		$this->limit = max(1, min((int) $limit, 100));
	}

	public function read(array $actor)
	{
		$user_id = (int) ($actor['user_id'] ?? 0);
		$role = (string) ($actor['role'] ?? '');
		if ($user_id < 1 || !in_array($role, array('warga', 'dokter'), true)) {
			return false;
		}
		$this->db->select('requests.request_id, requests.request_status');
		foreach (array('assigned_nakes_user_id', 'accepted_by_user_id', 'updated_at') as $field) {
			$this->db->select($this->db->field_exists($field, 'requests') ? 'requests.' . $field : 'NULL AS ' . $field, false);
		}
		$this->db->from('requests');
		if ($role === 'warga') {
			$this->db->where('requests.user_id', $user_id);
		} else {
			$identity = is_array($actor['identity'] ?? null) ? $actor['identity'] : array();
			if (empty($identity['valid'])) {
				return false;
			}
			if (($identity['account_type'] ?? '') === 'command_center') {
				$this->db->where('TRIM(requests.assigned_puskesmas_code) = ' . $this->db->escape((string) $identity['puskesmas_code']), null, false);
			} elseif (($identity['account_type'] ?? '') === 'personal' && (int) ($identity['staff_id'] ?? 0) > 0) {
				$staff_id = (int) $identity['staff_id'];
				$assignment_table = $this->db->dbprefix('request_staff_assignments');
				$ownership = array();
				foreach (array('assigned_nakes_user_id', 'accepted_by_user_id', 'dokter_id') as $field) {
					if ($this->db->field_exists($field, 'requests')) {
						$ownership[] = 'requests.' . $field . ' = ' . $user_id;
					}
				}
				$ownership[] = 'EXISTS (SELECT 1 FROM ' . $assignment_table . ' snapshot_rsa WHERE snapshot_rsa.request_id = requests.request_id AND snapshot_rsa.staff_id = ' . $staff_id . " AND snapshot_rsa.status = 'aktif')";
				$this->db->where('(' . implode(' OR ', $ownership) . ')', null, false);
			} else {
				return false;
			}
		}
		$rows = $this->db->order_by('requests.request_id', 'DESC')->limit($this->limit)->get()->result();
		$result = array();
		foreach ($rows as $row) {
			if ($role === 'dokter') {
				$access = function_exists('doclinc_nakes_request_access_context')
					? doclinc_nakes_request_access_context((int) $row->request_id, $actor['identity'])
					: null;
				if (empty($access['can_view'])) {
					continue;
				}
			}
			$assignment_id = null;
			if ($this->db->table_exists('request_staff_assignments')) {
				$assignment = $this->db->select('assignment_id')->where('request_id', (int) $row->request_id)
					->where('status', 'aktif')->order_by('assignment_id', 'DESC')->limit(1)
					->get('request_staff_assignments')->row();
				$assignment_id = $assignment ? (int) $assignment->assignment_id : null;
			}
			$result[] = array(
				'request_id' => (int) $row->request_id,
				'status' => (string) $row->request_status,
				'assigned_user_id' => isset($row->assigned_nakes_user_id) && $row->assigned_nakes_user_id !== null ? (int) $row->assigned_nakes_user_id : null,
				'active_assignment_id' => $assignment_id,
				'updated_at' => isset($row->updated_at) ? (string) $row->updated_at : null,
			);
		}
		$json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		return array('requests' => $result, 'count' => count($result), 'fingerprint' => hash('sha256', $json));
	}
}
