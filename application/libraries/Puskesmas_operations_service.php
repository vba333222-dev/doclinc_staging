<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Puskesmas_operations_policy.php';

class Puskesmas_operations_service
{
	private $db;
	private $policy;
	private $online_timeout_seconds;

	public function __construct($db = null, $online_timeout_seconds = 90)
	{
		if ($db === null && function_exists('get_instance')) {
			$CI = &get_instance();
			$db = isset($CI->db) ? $CI->db : null;
		}
		$this->db = $db;
		$this->policy = new Puskesmas_operations_policy();
		$this->online_timeout_seconds = max(30, min(300, (int) $online_timeout_seconds));
	}

	public function schemaReady()
	{
		if (!$this->db) {
			return false;
		}
		$requirements = array(
			'users' => array('userId', 'role', 'status'),
			'm_puskesmas' => array('kode_pkm', 'nama_puskesmas', 'status'),
			'puskesmas_staff' => array('staff_id', 'kode_pkm', 'nama', 'profesi', 'user_id', 'status'),
			'nakes_presence' => array('user_id', 'puskesmas_code', 'last_seen_at'),
			'requests' => array('request_id', 'request_status', 'assigned_puskesmas_code', 'assigned_nakes_user_id', 'visit_status'),
			'request_staff_assignments' => array('assignment_id', 'request_id', 'staff_id', 'status'),
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

	public function snapshot(array $actor, $staff_limit = 200, $request_limit = 200)
	{
		$scope = $this->policy->snapshotScope($actor);
		if (empty($scope['allowed'])) {
			return array('ok' => false, 'code' => 'actor_denied');
		}
		if (!$this->schemaReady()) {
			return array('ok' => false, 'code' => 'schema_unavailable');
		}

		$puskesmas_code = (string) $scope['puskesmas_code'];
		$tenant = $this->db->select('kode_pkm')
			->where('kode_pkm', $puskesmas_code)
			->where('status', 'aktif')
			->limit(1)
			->get('m_puskesmas');
		if (!$tenant || !$tenant->row()) {
			return array('ok' => false, 'code' => 'tenant_denied');
		}

		$staff_limit = max(1, min(200, (int) $staff_limit));
		$request_limit = max(1, min(200, (int) $request_limit));
		$timeout = $this->online_timeout_seconds;
		$staff_query = $this->db
			->select('ps.staff_id, ps.user_id, ps.nama AS display_name, ps.profesi')
			->select('TIMESTAMPDIFF(SECOND, np.last_seen_at, NOW(6)) AS last_seen_age_seconds', false)
			->select("CASE WHEN np.last_seen_at IS NOT NULL AND np.last_seen_at >= DATE_SUB(NOW(6), INTERVAL {$timeout} SECOND) THEN 1 ELSE 0 END AS is_online", false)
			->from('puskesmas_staff ps')
			->join('users u', "u.userId = ps.user_id AND u.role = 'dokter' AND u.status = 'aktif'", 'inner', false)
			->join('nakes_presence np', 'np.user_id = ps.user_id AND np.puskesmas_code = ps.kode_pkm', 'left')
			->where('ps.kode_pkm', $puskesmas_code)
			->where('ps.status', 'aktif')
			->where('ps.user_id IS NOT NULL', null, false)
			->order_by('ps.nama', 'ASC')
			->order_by('ps.staff_id', 'ASC')
			->limit($staff_limit + 1)
			->get();
		if (!$staff_query) {
			return array('ok' => false, 'code' => 'read_failed');
		}
		$staff_rows = $staff_query->result();
		if (count($staff_rows) > $staff_limit) {
			return array('ok' => false, 'code' => 'result_too_large');
		}

		$aggregate_query = $this->db
			->select("SUM(CASE WHEN request_status = 'Pending' THEN 1 ELSE 0 END) AS pending_count", false)
			->where('assigned_puskesmas_code', $puskesmas_code)
			->get('requests');
		if (!$aggregate_query || !$aggregate_query->row()) {
			return array('ok' => false, 'code' => 'read_failed');
		}
		$aggregate = $aggregate_query->row();

		$request_query = $this->db
			->select('request_id, request_status, visit_status, assigned_nakes_user_id')
			->where('assigned_puskesmas_code', $puskesmas_code)
			->where('request_status', 'Accepted')
			->order_by('request_id', 'DESC')
			->limit($request_limit + 1)
			->get('requests');
		if (!$request_query) {
			return array('ok' => false, 'code' => 'read_failed');
		}
		$base_requests = $request_query->result();
		if (count($base_requests) > $request_limit) {
			return array('ok' => false, 'code' => 'result_too_large');
		}
		$accepted_count = count($base_requests);
		$request_ids = array();
		$request_by_id = array();
		$request_rows = array();
		foreach ($base_requests as $request) {
			$request_id = (int) $request->request_id;
			$request_ids[] = $request_id;
			$request_by_id[$request_id] = $request;
			$request_rows[] = array(
				'request_id' => $request_id,
				'request_status' => 'Accepted',
				'visit_status' => $request->visit_status,
				'assigned_nakes_user_id' => $request->assigned_nakes_user_id,
				'assignment_id' => null,
				'assignment_staff_id' => null,
				'assignment_user_id' => null,
			);
		}

		if (!empty($request_ids)) {
			$assignment_limit = ($request_limit * 3) + 1;
			$assignment_query = $this->db
				->select('rsa.assignment_id, rsa.request_id, rsa.staff_id AS assignment_staff_id, aps.user_id AS assignment_user_id', false)
				->from('request_staff_assignments rsa')
				->join('puskesmas_staff aps', 'aps.staff_id = rsa.staff_id AND aps.kode_pkm = ' . $this->db->escape($puskesmas_code) . " AND aps.status = 'aktif'", 'left', false)
				->where_in('rsa.request_id', $request_ids)
				->where('rsa.status', 'aktif')
				->order_by('rsa.request_id', 'DESC')
				->order_by('rsa.assignment_id', 'ASC')
				->limit($assignment_limit)
				->get();
			if (!$assignment_query) {
				return array('ok' => false, 'code' => 'read_failed');
			}
			$assignment_rows = $assignment_query->result();
			if (count($assignment_rows) >= $assignment_limit) {
				return array('ok' => false, 'code' => 'result_too_large');
			}
			foreach ($assignment_rows as $assignment) {
				$request_id = (int) $assignment->request_id;
				if (!isset($request_by_id[$request_id])) {
					continue;
				}
				$request = $request_by_id[$request_id];
				$request_rows[] = array(
					'request_id' => $request_id,
					'request_status' => 'Accepted',
					'visit_status' => $request->visit_status,
					'assigned_nakes_user_id' => $request->assigned_nakes_user_id,
					'assignment_id' => $assignment->assignment_id,
					'assignment_staff_id' => $assignment->assignment_staff_id,
					'assignment_user_id' => $assignment->assignment_user_id,
				);
			}
		}

		return array(
			'ok' => true,
			'code' => 'ok',
			'data' => $this->composeSnapshot(
				$puskesmas_code,
				$staff_rows,
				$request_rows,
				max(0, (int) $aggregate->pending_count),
				time(),
				$accepted_count
			),
		);
	}

	public function composeSnapshot($puskesmas_code, array $staff_rows, array $request_rows, $pending_count, $generated_at_epoch, $accepted_count = null)
	{
		$puskesmas_code = trim((string) $puskesmas_code);
		$staff = array();
		$staff_by_user = array();
		foreach ($staff_rows as $row) {
			$user_id = (int) $this->value($row, 'user_id');
			$staff_id = (int) $this->value($row, 'staff_id');
			if ($user_id < 1 || $staff_id < 1 || isset($staff_by_user[$user_id])) {
				continue;
			}
			$staff_by_user[$user_id] = count($staff);
			$staff[] = array(
				'user_id' => $user_id,
				'staff_id' => $staff_id,
				'display_name' => $this->safeText($this->value($row, 'display_name'), 100, 'Nama belum diisi'),
				'profession' => $this->safeText($this->value($row, 'profesi'), 100, 'Profesi belum diisi'),
				'is_online' => (int) $this->value($row, 'is_online') === 1,
				'last_seen_age_seconds' => $this->nullableNonnegativeInteger($this->value($row, 'last_seen_age_seconds')),
				'active_request_count' => 0,
				'not_started_count' => 0,
				'en_route_count' => 0,
				'arrived_count' => 0,
				'in_service_count' => 0,
				'workload_state' => 'offline',
			);
		}

		$grouped = array();
		foreach ($request_rows as $row) {
			$request_id = (int) $this->value($row, 'request_id');
			if ($request_id < 1) {
				continue;
			}
			if (!isset($grouped[$request_id])) {
				$grouped[$request_id] = array(
					'request_id' => $request_id,
					'request_status' => 'Accepted',
					'visit_status' => $this->visitStatus($this->value($row, 'visit_status')),
					'direct_user_id' => (int) $this->value($row, 'assigned_nakes_user_id'),
					'assignments' => array(),
				);
			}
			$assignment_id = (int) $this->value($row, 'assignment_id');
			if ($assignment_id > 0) {
				$grouped[$request_id]['assignments'][$assignment_id] = array(
					'staff_id' => (int) $this->value($row, 'assignment_staff_id'),
					'user_id' => (int) $this->value($row, 'assignment_user_id'),
				);
			}
		}

		$exceptions = array();
		$summary = array(
			'pending_requests' => max(0, (int) $pending_count),
			'accepted_requests' => $accepted_count === null ? count($grouped) : max(count($grouped), (int) $accepted_count),
			'unassigned_requests' => 0,
			'ambiguous_assignments' => 0,
			'not_started_requests' => 0,
			'en_route_requests' => 0,
			'arrived_requests' => 0,
			'in_service_requests' => 0,
			'online_staff' => 0,
			'offline_staff' => 0,
			'available_staff' => 0,
			'busy_staff' => 0,
			'offline_with_active_requests' => 0,
		);

		foreach ($grouped as $request) {
			$visit_key = $request['visit_status'] . '_requests';
			if (isset($summary[$visit_key])) {
				$summary[$visit_key]++;
			}
			$assignment_users = array();
			$invalid_assignment = false;
			foreach ($request['assignments'] as $assignment) {
				if ($assignment['staff_id'] < 1 || $assignment['user_id'] < 1 || !isset($staff_by_user[$assignment['user_id']])) {
					$invalid_assignment = true;
					continue;
				}
				$assignment_users[$assignment['user_id']] = true;
			}
			$assigned_user_id = 0;
			$ambiguous = $invalid_assignment || count($request['assignments']) > 1 || count($assignment_users) > 1;
			if (!$ambiguous && count($assignment_users) === 1) {
				$assigned_user_id = (int) array_key_first($assignment_users);
				if ($request['direct_user_id'] > 0 && $request['direct_user_id'] !== $assigned_user_id) {
					$ambiguous = true;
				}
			} elseif (!$ambiguous && empty($request['assignments']) && $request['direct_user_id'] > 0) {
				$assigned_user_id = isset($staff_by_user[$request['direct_user_id']]) ? $request['direct_user_id'] : 0;
				$ambiguous = $assigned_user_id < 1;
			}

			if ($ambiguous) {
				$summary['ambiguous_assignments']++;
				$exceptions[] = $this->exceptionRow($request, 'ambiguous_assignment');
				continue;
			}
			if ($assigned_user_id < 1) {
				$summary['unassigned_requests']++;
				$exceptions[] = $this->exceptionRow($request, 'unassigned_request');
				continue;
			}

			$index = $staff_by_user[$assigned_user_id];
			$staff[$index]['active_request_count']++;
			$count_key = $request['visit_status'] . '_count';
			if (isset($staff[$index][$count_key])) {
				$staff[$index][$count_key]++;
			}
		}

		foreach ($staff as &$row) {
			if ($row['is_online']) {
				$summary['online_staff']++;
			} else {
				$summary['offline_staff']++;
			}
			if ($row['active_request_count'] > 0) {
				$summary['busy_staff']++;
				if ($row['is_online']) {
					$row['workload_state'] = 'busy';
				} else {
					$row['workload_state'] = 'attention';
					$summary['offline_with_active_requests']++;
				}
			} elseif ($row['is_online']) {
				$row['workload_state'] = 'available';
				$summary['available_staff']++;
			}
		}
		unset($row);

		usort($staff, function ($left, $right) {
			$order = array('attention' => 0, 'busy' => 1, 'available' => 2, 'offline' => 3);
			$state_compare = ($order[$left['workload_state']] ?? 9) <=> ($order[$right['workload_state']] ?? 9);
			if ($state_compare !== 0) {
				return $state_compare;
			}
			return strcasecmp($left['display_name'], $right['display_name']);
		});

		return array(
			'puskesmas_code' => $puskesmas_code,
			'summary' => $summary,
			'staff' => $staff,
			'exceptions' => array_slice($exceptions, 0, 50),
			'online_timeout_seconds' => $this->online_timeout_seconds,
			'generated_at_epoch' => max(0, (int) $generated_at_epoch),
		);
	}

	private function exceptionRow(array $request, $type)
	{
		return array(
			'request_id' => (int) $request['request_id'],
			'type' => (string) $type,
			'request_status' => 'Accepted',
			'visit_status' => (string) $request['visit_status'],
			'visit_status_label' => $this->visitStatusLabel($request['visit_status']),
		);
	}

	private function visitStatus($value)
	{
		$value = strtolower(trim((string) $value));
		return in_array($value, array('not_started', 'en_route', 'arrived', 'in_service'), true)
			? $value
			: 'not_started';
	}

	private function visitStatusLabel($value)
	{
		$labels = array(
			'not_started' => 'Belum dimulai',
			'en_route' => 'Dalam perjalanan',
			'arrived' => 'Sudah tiba',
			'in_service' => 'Sedang ditangani',
		);
		return isset($labels[$value]) ? $labels[$value] : $labels['not_started'];
	}

	private function safeText($value, $maximum, $fallback)
	{
		$value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value));
		$length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
		if ($value === '' || $length > (int) $maximum) {
			return (string) $fallback;
		}
		return $value;
	}

	private function nullableNonnegativeInteger($value)
	{
		if ($value === null || $value === '') {
			return null;
		}
		return max(0, (int) $value);
	}

	private function value($row, $field)
	{
		if (is_object($row) && isset($row->{$field})) {
			return $row->{$field};
		}
		if (is_array($row) && array_key_exists($field, $row)) {
			return $row[$field];
		}
		return null;
	}
}
