<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Puskesmas_operations_policy.php';

class Puskesmas_operations_service
{
	private $db;
	private $policy;
	private $online_timeout_seconds;
	private $care_team_schema_ready;
	private $care_team_enabled;

	public function __construct($db = null, $online_timeout_seconds = 90, $care_team_workflow_enabled = null)
	{
		if ($db === null && function_exists('get_instance')) {
			$CI = &get_instance();
			$db = isset($CI->db) ? $CI->db : null;
		}
		$this->db = $db;
		$this->policy = new Puskesmas_operations_policy();
		$this->online_timeout_seconds = max(30, min(300, (int) $online_timeout_seconds));

		// Sumber kebenaran penanggung jawab mengikuti flag care-team (sama dengan jalur tulis
		// aplikasi), bukan sekadar keberadaan kolom — schema bisa tertinggal atau lebih dulu
		// dari flag dan menyebabkan dua layar menghitung PIC dari sumber berbeda.
		$this->care_team_schema_ready = $db !== null
			&& $db->field_exists('responsible_doctor_user_id', 'requests')
			&& $db->field_exists('visit_performer_user_id', 'requests')
			&& $db->field_exists('consultation_mode', 'requests');
		$this->care_team_enabled = $care_team_workflow_enabled === null
			? $this->care_team_schema_ready
			: ($care_team_workflow_enabled === true && $this->care_team_schema_ready);
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

	public function snapshot(array $actor, $staff_limit = 200, $request_limit = 200, array $filters = array())
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
			->join('nakes_presence np', 'np.user_id = ps.user_id AND np.puskesmas_code COLLATE utf8mb4_unicode_ci = CONVERT(ps.kode_pkm USING utf8mb4) COLLATE utf8mb4_unicode_ci', 'left', false)
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

		$date_field = $this->dateField('requests');
		$nik_ready = $this->db->field_exists('nik', 'users');
		$request_select = 'requests.request_id, requests.request_status, requests.visit_status, requests.assigned_nakes_user_id';
		$request_select .= ', DATE(' . $date_field . ') AS service_date';
		$request_select .= ', patient.nama AS patient_name';
		$request_select .= $nik_ready ? ', patient.nik AS patient_nik' : ', NULL AS patient_nik';
		/* Nomor antrian dipakai untuk label pendek di daftar pengecualian.
		   Kolomnya berasal dari migrasi queue number, jadi dijaga field_exists. */
		$request_select .= $this->db->field_exists('queue_number', 'requests') ? ', requests.queue_number' : ', NULL AS queue_number';
		if ($this->care_team_enabled) {
			$request_select .= ', requests.responsible_doctor_user_id, requests.visit_performer_user_id, requests.consultation_mode';
		}
		$request_query = $this->db
			->select($request_select, false)
			->join('users patient', 'patient.userId = requests.user_id', 'inner')
			->where('requests.assigned_puskesmas_code', $puskesmas_code)
			->where('requests.request_status', 'Accepted')
			->order_by('requests.request_id', 'DESC')
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
			$canonical_user_id = 0;
			if ($this->care_team_enabled) {
				$canonical_user_id = strtolower((string) $request->consultation_mode) === 'visit'
					? (int) $request->visit_performer_user_id
					: (int) $request->responsible_doctor_user_id;
			}
			$request_rows[] = array(
				'request_id' => $request_id,
				'request_status' => 'Accepted',
				'visit_status' => $request->visit_status,
				'assigned_nakes_user_id' => $this->care_team_enabled ? $canonical_user_id : $request->assigned_nakes_user_id,
				'assignment_id' => null,
				'assignment_staff_id' => null,
				'assignment_user_id' => null,
				'patient_name' => isset($request->patient_name) ? (string) $request->patient_name : '',
				'patient_nik' => isset($request->patient_nik) ? (string) $request->patient_nik : '',
				'service_date' => isset($request->service_date) ? (string) $request->service_date : '',
			);
		}

		if (!empty($request_ids) && !$this->care_team_enabled) {
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
					'patient_name' => isset($request->patient_name) ? (string) $request->patient_name : '',
					'patient_nik' => isset($request->patient_nik) ? (string) $request->patient_nik : '',
					'service_date' => isset($request->service_date) ? (string) $request->service_date : '',
				);
			}
		}

		$operational = $this->operationalRows($puskesmas_code, $filters, $request_limit);
		if (empty($operational['ok'])) {
			return $operational;
		}
		$data = $this->composeSnapshot(
			$puskesmas_code,
			$staff_rows,
			$request_rows,
			max(0, (int) $aggregate->pending_count),
			time(),
			$accepted_count,
			$this->care_team_enabled
		);
		$data['requests'] = $operational['rows'];
		$data['filters'] = $operational['filters'];
		return array(
			'ok' => true,
			'code' => 'ok',
			'data' => $data,
		);
	}

	public function medicalRecord(array $actor, $request_id)
	{
		$scope = $this->policy->snapshotScope($actor);
		$request_id = (int) $request_id;
		if (empty($scope['allowed']) || $request_id < 1 || !$this->db->table_exists('medicalrecords')) {
			return array('ok' => false, 'code' => 'actor_denied');
		}
		$columns = array('record_id', 'request_id', 'responsible_doctor_user_id', 'recorded_by_user_id', 'diagnosis', 'treatment', 'recommendations', 'created_at');
		foreach ($columns as $column) {
			if (!$this->db->field_exists($column, 'medicalrecords')) {
				return array('ok' => false, 'code' => 'schema_unavailable');
			}
		}
		$query = $this->db
			->select('r.request_id, r.request_status, r.consultation_mode, r.visit_status, patient.nama AS patient_name')
			->select('record_doctor.nama AS responsible_doctor_name, record_author.nama AS recorded_by_name')
			->select('mr.diagnosis, mr.treatment, mr.recommendations, mr.created_at AS service_date')
			->select($this->db->field_exists('anamnesis', 'medicalrecords') ? 'mr.anamnesis' : 'NULL AS anamnesis', false)
			->from('requests r')
			->join('users patient', 'patient.userId = r.user_id', 'inner')
			->join('(SELECT request_id, MAX(record_id) AS record_id FROM medicalrecords GROUP BY request_id) latest', 'latest.request_id = r.request_id', 'left', false)
			->join('medicalrecords mr', 'mr.record_id = latest.record_id', 'left')
			->join('users record_doctor', 'record_doctor.userId = mr.responsible_doctor_user_id', 'left')
			->join('users record_author', 'record_author.userId = mr.recorded_by_user_id', 'left')
			->where('r.request_id', $request_id)
			->where('r.assigned_puskesmas_code', (string) $scope['puskesmas_code'])
			->limit(1)
			->get();
		$row = $query ? $query->row_array() : null;
		if (!$row) {
			return array('ok' => false, 'code' => 'request_denied');
		}
		return array('ok' => true, 'code' => 'ok', 'data' => array(
			'patient_name' => $this->safeText($row['patient_name'], 100, 'Warga'),
			'service_date' => isset($row['service_date']) ? $row['service_date'] : null,
			'service_mode_label' => $this->serviceModeLabel($row['consultation_mode']),
			'status_label' => $this->operationalStatusLabel($row['request_status'], $row['consultation_mode'], $row['visit_status']),
			'responsible_doctor_name' => $this->safeText($row['responsible_doctor_name'], 100, 'Belum tercatat'),
			'recorded_by_name' => $this->safeText($row['recorded_by_name'], 100, 'Belum tercatat'),
			'anamnesis' => $this->safeText($row['anamnesis'], 4000, '-'),
			'diagnosis' => $this->safeText($row['diagnosis'], 4000, '-'),
			'treatment' => $this->safeText($row['treatment'], 4000, '-'),
			'recommendations' => $this->safeText($row['recommendations'], 4000, '-'),
		));
	}

	private function operationalRows($puskesmas_code, array $filters, $limit)
	{
		$date_from = isset($filters['date_from']) ? (string) $filters['date_from'] : date('Y-m-d', strtotime('-7 days'));
		$date_to = isset($filters['date_to']) ? (string) $filters['date_to'] : date('Y-m-d');
		$search = isset($filters['q']) ? trim((string) $filters['q']) : '';
		$date_field = $this->dateField('r');
		$nik_ready = $this->db->field_exists('nik', 'users');
		$this->db
			->select('r.request_id, r.request_status, r.visit_status')
			->select($this->care_team_enabled ? 'r.consultation_mode' : 'NULL AS consultation_mode', false)
			->select("DATE({$date_field}) AS service_date", false)
			->select('patient.nama AS patient_name')
			->select($this->care_team_enabled ? 'doctor.nama AS responsible_doctor_name, performer.nama AS visit_performer_name' : 'NULL AS responsible_doctor_name, NULL AS visit_performer_name', false)
			->select($nik_ready ? 'patient.nik AS patient_nik' : 'NULL AS patient_nik', false)
			->from('requests r')
			->join('users patient', 'patient.userId = r.user_id', 'inner');
		if ($this->care_team_enabled) {
			$this->db->join($this->validStaffSubquery() . ' doctor_staff', 'doctor_staff.user_id = r.responsible_doctor_user_id AND CONVERT(doctor_staff.kode_pkm USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(r.assigned_puskesmas_code USING utf8mb4) COLLATE utf8mb4_unicode_ci', 'left', false)
				->join('users doctor', 'doctor.userId = doctor_staff.user_id', 'left')
				->join($this->validStaffSubquery() . ' performer_staff', 'performer_staff.user_id = r.visit_performer_user_id AND CONVERT(performer_staff.kode_pkm USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(r.assigned_puskesmas_code USING utf8mb4) COLLATE utf8mb4_unicode_ci', 'left', false)
				->join('users performer', 'performer.userId = performer_staff.user_id', 'left');
		}
		$this->db->where('r.assigned_puskesmas_code', $puskesmas_code)
			->where('DATE(' . $date_field . ') >= ' . $this->db->escape($date_from), null, false)
			->where('DATE(' . $date_field . ') <= ' . $this->db->escape($date_to), null, false);
		if ($search !== '') {
			$this->db->group_start()->like('patient.nama', $search);
			$nik_search = preg_replace('/\D+/', '', $search);
			if ($nik_ready && $nik_search !== '') {
				$this->db->or_where('patient.nik', $nik_search);
			}
			$this->db->group_end();
		}
		$query = $this->db->order_by($date_field, 'ASC', false)->order_by('r.request_id', 'ASC')->limit($limit + 1)->get();
		if (!$query) {
			return array('ok' => false, 'code' => 'read_failed');
		}
		$rows = $query->result_array();
		if (count($rows) > $limit) {
			return array('ok' => false, 'code' => 'result_too_large');
		}
		$result = array();
		foreach ($rows as $index => $row) {
			$result[] = array(
				'request_id' => (int) $row['request_id'],
				'queue_number' => $index + 1,
				'service_date' => (string) $row['service_date'],
				'patient_name' => $this->safeText($row['patient_name'], 100, 'Warga'),
				'patient_nik_masked' => $this->maskNik($row['patient_nik']),
				'status_label' => $this->operationalStatusLabel($row['request_status'], $row['consultation_mode'], $row['visit_status']),
				'service_mode_label' => $this->serviceModeLabel($row['consultation_mode']),
				'visit_status_label' => $this->visitStatusLabel($this->visitStatus($row['visit_status'])),
				'responsible_doctor_name' => $this->safeText($row['responsible_doctor_name'], 100, '-'),
				'visit_performer_name' => $this->safeText($row['visit_performer_name'], 100, '-'),
				'has_medical_record' => (string) $row['request_status'] === 'Completed',
			);
		}
		return array('ok' => true, 'rows' => $result, 'filters' => array('q' => $search, 'date_from' => $date_from, 'date_to' => $date_to));
	}

	public function composeSnapshot($puskesmas_code, array $staff_rows, array $request_rows, $pending_count, $generated_at_epoch, $accepted_count = null, $care_team_enabled = null)
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
					'patient_name' => (string) $this->value($row, 'patient_name'),
					'patient_nik' => (string) $this->value($row, 'patient_nik'),
					'service_date' => (string) $this->value($row, 'service_date'),
					'consultation_mode' => strtolower(trim((string) $this->value($row, 'consultation_mode'))),
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
			'completed_requests' => 0,
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
			$care_team_enabled = $care_team_enabled === null ? $this->care_team_enabled : (bool) $care_team_enabled;
			$assigned_user_id = 0;
			$ambiguous = $invalid_assignment || count($request['assignments']) > 1 || count($assignment_users) > 1;
			// Akun staf fasilitas ini (bukan akun puskesmas yang menerima, bukan staf fasilitas lain).
			$direct_is_staff = $request['direct_user_id'] > 0 && isset($staff_by_user[$request['direct_user_id']]);
			if (!$ambiguous && count($assignment_users) === 1) {
				$assigned_user_id = (int) array_key_first($assignment_users);
				// Legacy: penugasan staf adalah sumber kebenaran. Handler langsung hanya konflik
				// bila ia akun staf fasilitas ini dan berbeda (akun puskesmas yang menerima bukan konflik).
				if ($direct_is_staff && $request['direct_user_id'] !== $assigned_user_id) {
					$ambiguous = true;
				}
			} elseif (!$ambiguous && $direct_is_staff) {
				$assigned_user_id = $request['direct_user_id'];
			} elseif (!$ambiguous && $care_team_enabled && $request['direct_user_id'] > 0) {
				// Care-team: handler kanonik menunjuk akun di luar fasilitas → perlu diperiksa.
				$ambiguous = true;
			}

			if ($ambiguous) {
				$summary['ambiguous_assignments']++;
				$exceptions[] = $this->exceptionRow($request, 'ambiguous_assignment', $puskesmas_code);
				continue;
			}
			if ($assigned_user_id < 1) {
				$summary['unassigned_requests']++;
				$exceptions[] = $this->exceptionRow($request, 'unassigned_request', $puskesmas_code);
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

	private function exceptionRow(array $request, $type, $puskesmas_code)
	{
		$row = array(
			'request_id' => (int) $request['request_id'],
			'type' => (string) $type,
			'request_status' => 'Accepted',
			'visit_status' => (string) $request['visit_status'],
			'visit_status_label' => $this->visitStatusLabel($request['visit_status']),
		);
		// Identitas pasien + nomor antrian agar operator tahu request mana yang perlu ditindak
		// dan bisa membukanya langsung (hanya disertakan bila tersedia).
		$patient_name = $this->safeText($this->value($request, 'patient_name'), 100, '');
		if ($patient_name !== '') {
			$row['patient_name'] = $patient_name;
		}
		$queue_code = $this->queueCode($puskesmas_code, $this->value($request, 'service_date'), (int) $request['request_id']);
		if ($queue_code !== '') {
			$row['queue_code'] = $queue_code;
		}
		/* Antarmuka menampilkan nomor antrian saja, bukan kode lengkapnya.
		   Helper yang sama dipakai halaman Puskesmas lain supaya nomornya
		   konsisten; fallback ke request_id ada di dalam helper. */
		if (function_exists('doclinc_request_queue_number_label')) {
			$row['queue_label'] = doclinc_request_queue_number_label((object) array(
				'queue_number' => isset($request['queue_number']) ? (int) $request['queue_number'] : 0,
				'queue_code' => $queue_code,
				'request_id' => (int) $request['request_id'],
			));
		}
		$nik_masked = $this->maskNik($this->value($request, 'patient_nik'));
		if ($nik_masked !== '') {
			$row['patient_nik_masked'] = $nik_masked;
		}
		$mode = strtolower(trim((string) $this->value($request, 'consultation_mode')));
		if (in_array($mode, array('visit', 'non_visit'), true)) {
			$row['service_mode_label'] = $this->serviceModeLabel($mode);
		}
		return $row;
	}

	private function queueCode($puskesmas_code, $service_date, $request_id)
	{
		$date_part = preg_replace('/\D+/', '', (string) $service_date);
		if ($date_part === '' || strlen($date_part) < 8) {
			return '';
		}
		$date_part = substr($date_part, 0, 4) . '-' . substr($date_part, 4, 2) . '-' . substr($date_part, 6, 2);
		return trim((string) $puskesmas_code) . '-' . $date_part . '-' . str_pad((string) max(1, (int) $request_id), 3, '0', STR_PAD_LEFT);
	}

	private function dateField($table_alias)
	{
		$parts = array();
		$parts[] = $this->db->field_exists('date', 'requests') ? $table_alias . '.date' : 'NULL';
		$parts[] = $this->db->field_exists('created_at', 'requests') ? $table_alias . '.created_at' : 'NULL';
		$parts[] = $this->db->field_exists('updated_at', 'requests') ? $table_alias . '.updated_at' : 'CURRENT_DATE()';
		return 'COALESCE(' . implode(', ', $parts) . ')';
	}

	private function visitStatus($value)
	{
		$value = strtolower(trim((string) $value));
		return in_array($value, array('not_started', 'en_route', 'arrived', 'in_service', 'completed'), true)
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
			'completed' => 'Kunjungan selesai',
		);
		return isset($labels[$value]) ? $labels[$value] : $labels['not_started'];
	}

	private function serviceModeLabel($value)
	{
		$value = strtolower(trim((string) $value));
		if ($value === 'visit') {
			return 'Kunjungan';
		}
		if ($value === 'non_visit') {
			return 'Tanpa kunjungan';
		}
		return 'Belum dipilih';
	}

	private function operationalStatusLabel($request_status, $mode, $visit_status)
	{
		if ((string) $request_status === 'Pending') {
			return 'Menunggu diterima';
		}
		if ((string) $request_status === 'Completed') {
			return 'Konsultasi selesai';
		}
		if ((string) $request_status === 'Cancelled') {
			return 'Dibatalkan';
		}
		if (strtolower(trim((string) $mode)) === 'non_visit') {
			return 'Konsultasi tanpa kunjungan';
		}
		if (strtolower(trim((string) $mode)) === 'visit') {
			return $this->visitStatusLabel($this->visitStatus($visit_status));
		}
		return 'Sedang ditangani';
	}

	private function maskNik($value)
	{
		$value = preg_replace('/\D+/', '', (string) $value);
		if (strlen($value) !== 16) {
			return '';
		}
		return substr($value, 0, 4) . '********' . substr($value, -4);
	}

	private function validStaffSubquery()
	{
		return '(SELECT user_id, MAX(kode_pkm) AS kode_pkm FROM ' . $this->db->dbprefix('puskesmas_staff')
			. " WHERE status = 'aktif' AND user_id IS NOT NULL GROUP BY user_id HAVING COUNT(*) = 1)";
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
