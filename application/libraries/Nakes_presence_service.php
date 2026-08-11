<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Nakes_presence_policy.php';

class Nakes_presence_service
{
	private $db;
	private $policy;
	private $online_timeout_seconds;
	private $write_throttle_seconds;

	public function __construct($db = null, $online_timeout_seconds = 90, $write_throttle_seconds = 45)
	{
		if ($db === null) {
			$CI = &get_instance();
			$db = $CI->db;
		}
		$this->db = $db;
		$this->policy = new Nakes_presence_policy();
		$this->online_timeout_seconds = max(30, min(300, (int) $online_timeout_seconds));
		$this->write_throttle_seconds = max(15, min($this->online_timeout_seconds - 1, (int) $write_throttle_seconds));
	}

	public function schemaReady()
	{
		$requirements = array(
			'nakes_presence' => array('user_id', 'puskesmas_code', 'last_seen_at', 'last_transition_at', 'last_persisted_at', 'updated_at'),
			'users' => array('userId', 'nama', 'role', 'status'),
			'puskesmas_staff' => array('staff_id', 'kode_pkm', 'nama', 'profesi', 'user_id', 'status'),
			'm_puskesmas' => array('kode_pkm', 'nama_puskesmas'),
		);
		foreach ($requirements as $table => $fields) {
			if (!$this->db->table_exists($table)) {
				return false;
			}
			foreach ($fields as $field) {
				if (!$this->db->field_exists($field, $table)) {
					return false;
				}
			}
		}
		return true;
	}

	public function touch(array $actor)
	{
		if (!$this->policy->heartbeatAllowed($actor)) {
			return array('ok' => false, 'code' => 'actor_denied', 'persisted' => false);
		}
		if (!$this->schemaReady()) {
			return array('ok' => false, 'code' => 'schema_unavailable', 'persisted' => false);
		}

		$user_id = (int) $actor['user_id'];
		$puskesmas_code = trim((string) $actor['identity']['puskesmas_code']);
		// CI3 exposes nested transaction levels through balanced public calls,
		// but has no public transaction-state inspector. The service therefore
		// owns exactly one level for every touch and never commits an outer level.
		if (!$this->db->trans_begin()) {
			return array('ok' => false, 'code' => 'write_failed', 'persisted' => false);
		}

		try {
			$lock_query = $this->db->query(
				'SELECT user_id, puskesmas_code, last_seen_at, '
				. 'TIMESTAMPDIFF(SECOND, last_persisted_at, NOW(6)) AS seconds_since_persist, '
				. 'TIMESTAMPDIFF(SECOND, last_seen_at, NOW(6)) AS seconds_since_seen '
				. 'FROM ' . $this->db->dbprefix('nakes_presence') . ' WHERE user_id = ? FOR UPDATE',
				array($user_id)
			);
			if (!$lock_query) {
				$this->db->trans_rollback();
				return array('ok' => false, 'code' => 'write_failed', 'persisted' => false);
			}
			$row = $lock_query->row();

			if ($row && (int) $row->seconds_since_persist < $this->write_throttle_seconds
				&& hash_equals((string) $row->puskesmas_code, $puskesmas_code)) {
				if (!$this->db->trans_commit()) {
					$this->db->trans_rollback();
					return array('ok' => false, 'code' => 'write_failed', 'persisted' => false);
				}
				return array('ok' => true, 'code' => 'throttled', 'persisted' => false);
			}

			if ($row) {
				$transition = (int) $row->seconds_since_seen >= $this->online_timeout_seconds
					|| !hash_equals((string) $row->puskesmas_code, $puskesmas_code);
				$sql = 'UPDATE ' . $this->db->dbprefix('nakes_presence')
					. ' SET puskesmas_code = ?, last_seen_at = NOW(6), last_persisted_at = NOW(6)'
					. ($transition ? ', last_transition_at = NOW(6)' : '')
					. ' WHERE user_id = ?';
				$written = $this->db->query($sql, array($puskesmas_code, $user_id));
			} else {
				$written = $this->db->query(
					'INSERT INTO ' . $this->db->dbprefix('nakes_presence')
					. ' (user_id, puskesmas_code, last_seen_at, last_transition_at, last_persisted_at) '
					. 'VALUES (?, ?, NOW(6), NOW(6), NOW(6))',
					array($user_id, $puskesmas_code)
				);
			}

			if (!$written || $this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('ok' => false, 'code' => 'write_failed', 'persisted' => false);
			}
			if (!$this->db->trans_commit()) {
				$this->db->trans_rollback();
				return array('ok' => false, 'code' => 'write_failed', 'persisted' => false);
			}
			return array('ok' => true, 'code' => 'persisted', 'persisted' => true);
		} catch (Throwable $exception) {
			$this->db->trans_rollback();
			return array('ok' => false, 'code' => 'write_failed', 'persisted' => false);
		}
	}

	public function snapshot(array $actor, $limit = 500)
	{
		$scope = $this->policy->snapshotScope($actor);
		if (empty($scope['allowed'])) {
			return array('ok' => false, 'code' => 'actor_denied');
		}
		if (!$this->schemaReady()) {
			return array('ok' => false, 'code' => 'schema_unavailable');
		}

		$limit = max(1, min(500, (int) $limit));
		$timeout = $this->online_timeout_seconds;
		$this->db->select('ps.staff_id, ps.user_id, ps.kode_pkm AS puskesmas_code, ps.nama AS display_name, ps.profesi');
		$this->db->select('mp.nama_puskesmas AS puskesmas_name, np.last_seen_at, np.last_transition_at', false);
		$this->db->select("CASE WHEN np.last_seen_at IS NOT NULL AND np.last_seen_at >= DATE_SUB(NOW(6), INTERVAL {$timeout} SECOND) THEN 1 ELSE 0 END AS is_online", false);
		$this->db->from('puskesmas_staff ps');
		$this->db->join('users u', "u.userId = ps.user_id AND u.role = 'dokter' AND u.status = 'aktif'", 'inner', false);
		$this->db->join('m_puskesmas mp', 'mp.kode_pkm = ps.kode_pkm', 'inner');
		$this->db->join('nakes_presence np', 'np.user_id = ps.user_id', 'left');
		$this->db->where('ps.status', 'aktif');
		$this->db->where('ps.user_id IS NOT NULL', null, false);
		if ($this->db->field_exists('status', 'm_puskesmas')) {
			$this->db->where('mp.status', 'aktif');
		}
		if ((string) $scope['scope'] === 'tenant') {
			$this->db->where('ps.kode_pkm', (string) $scope['puskesmas_code']);
		}
		$this->db->order_by('is_online', 'DESC', false);
		$this->db->order_by('ps.nama', 'ASC');
		$this->db->order_by('ps.staff_id', 'ASC');
		$query = $this->db->limit($limit)->get();
		if (!$query) {
			return array('ok' => false, 'code' => 'read_failed');
		}

		$rows = array();
		$online = 0;
		foreach ($query->result() as $row) {
			$is_online = (int) $row->is_online === 1;
			$online += $is_online ? 1 : 0;
			$rows[] = array(
				'user_id' => (int) $row->user_id,
				'staff_id' => (int) $row->staff_id,
				'display_name' => trim((string) $row->display_name),
				'profession' => trim((string) $row->profesi),
				'puskesmas_code' => trim((string) $row->puskesmas_code),
				'puskesmas_name' => trim((string) $row->puskesmas_name),
				'is_online' => $is_online,
				'last_seen_at' => $row->last_seen_at !== null ? (string) $row->last_seen_at : null,
			);
		}

		return array(
			'ok' => true,
			'code' => 'ok',
			'data' => array(
				'rows' => $rows,
				'online_count' => $online,
				'offline_count' => count($rows) - $online,
				'total_count' => count($rows),
				'online_timeout_seconds' => $this->online_timeout_seconds,
				'generated_at_epoch' => time(),
			),
		);
	}
}
