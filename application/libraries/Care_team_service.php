<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Care_team_policy.php';
require_once __DIR__ . '/Notification_delivery_service.php';

class Care_team_service
{
	private $db;
	private $policy;
	private $notification_delivery;

	public function __construct($db = null, $notification_feature_state = null, $notification_delivery = null)
	{
		$CI = null;
		if ($db === null) {
			$CI = &get_instance();
			$db = $CI->db;
		}
		if (!is_array($notification_feature_state)) {
			if ($CI === null && function_exists('get_instance')) {
				$CI = &get_instance();
			}
			$notification_feature_state = array(
				'enabled' => $CI !== null && $CI->config->item('realtime_notifications_enabled') === true,
			);
		}
		$this->db = $db;
		$this->policy = new Care_team_policy();
		$this->notification_delivery = $notification_delivery ?: new Notification_delivery_service($db, $notification_feature_state);
	}

	public function schemaReady()
	{
		$requirements = array(
			'users' => array('userId', 'role', 'status', 'remark'),
			'm_puskesmas' => array('kode_pkm', 'status'),
			'puskesmas_staff' => array('staff_id', 'user_id', 'kode_pkm', 'profesi', 'status'),
			'requests' => array('request_id', 'request_status', 'assigned_puskesmas_code', 'responsible_doctor_user_id', 'consultation_mode', 'visit_performer_user_id'),
			'request_responsible_doctor_assignments' => array('responsible_assignment_id', 'request_id', 'staff_id', 'user_id', 'assigned_by_user_id', 'status', 'assigned_at', 'ended_at', 'created_at', 'updated_at'),
			'request_visit_performer_assignments' => array('visit_assignment_id', 'request_id', 'staff_id', 'user_id', 'assigned_by_user_id', 'status', 'assigned_at', 'ended_at', 'created_at', 'updated_at'),
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

	public function assignResponsibleDoctor($request_id, $staff_id, $actor_user_id, $session_token = null, $session_required = false)
	{
		$request_id = (int) $request_id;
		$staff_id = (int) $staff_id;
		$actor_user_id = (int) $actor_user_id;
		if ($request_id < 1 || $staff_id < 1 || $actor_user_id < 1 || !$this->schemaReady()) {
			return $this->failure('Pengaturan dokter belum tersedia.');
		}
		return $this->transaction(function () use ($request_id, $staff_id, $actor_user_id, $session_token, $session_required) {
			$request = $this->lockedRequest($request_id);
			if (!$request || (string) $request->request_status !== 'Accepted') {
				return $this->failure('Konsultasi tidak dapat diperbarui.');
			}
			$handover_failure = $this->policy->handoverFailure(
				array('visit_status' => isset($request->visit_status) ? $request->visit_status : ''),
				$this->dispositionForRequest($request_id)
			);
			if ($handover_failure !== null) {
				return $this->failure($this->policyMessage($handover_failure), $handover_failure);
			}
			$puskesmas_code = trim((string) $request->assigned_puskesmas_code);
			$actor = $this->lockedIdentity($actor_user_id);
			if (!$this->policy->commandCenterEligible($actor, $puskesmas_code)
				|| !$this->activeSession($actor_user_id, $session_token, $session_required)) {
				return $this->failure('Anda tidak memiliki akses.');
			}
			$doctor = $this->lockedStaffIdentity($staff_id);
			if (!$this->policy->responsibleDoctorEligible($doctor, $puskesmas_code)) {
				return $this->failure('Pilih dokter dari Puskesmas ini.');
			}
			// CATATAN: status online sengaja TIDAK diperiksa di sini. Kehadiran
			// bersifat sementara - gangguan jaringan sesaat tidak boleh membuat
			// dokter yang sah kehilangan otorisasinya. Penyaringan dokter yang
			// sedang bertugas dilakukan saat MENAWARKAN pilihan kepada puskesmas
			// (lihat get_active_staff_options_by_code), bukan saat mengotorisasi.
			// Invarian ini dikunci oleh test presence_is_not_care_team_authorization.
			$active = $this->lockedResponsibleAssignments($request_id);
			if ($active === false || count($active) > 1) {
				return $this->failure('Data dokter perlu diperiksa.');
			}
			if (count($active) === 1 && (int) $active[0]->user_id === (int) $doctor['user_id']) {
				if ((int) $request->responsible_doctor_user_id !== (int) $doctor['user_id']) {
					return $this->failure('Data dokter perlu diperiksa.');
				}
				return $this->success('Dokter penanggung jawab tetap sama.');
			}
			$now = date('Y-m-d H:i:s');
			if (count($active) === 1) {
				$this->db->where('responsible_assignment_id', (int) $active[0]->responsible_assignment_id)
					->where('status', 'aktif')
					->update('request_responsible_doctor_assignments', array('status' => 'diganti', 'ended_at' => $now, 'updated_at' => $now));
				if ($this->db->affected_rows() !== 1) {
					return $this->failure('Dokter belum dapat diperbarui.');
				}
			}
			if (!$this->db->insert('request_responsible_doctor_assignments', array(
				'request_id' => $request_id,
				'staff_id' => (int) $doctor['staff_id'],
				'user_id' => (int) $doctor['user_id'],
				'assigned_by_user_id' => $actor_user_id,
				'status' => 'aktif',
				'assigned_at' => $now,
				'created_at' => $now,
				'updated_at' => $now,
			))) {
				return $this->failure('Dokter belum dapat diperbarui.');
			}
			$responsible_assignment_id = (int) $this->db->insert_id();
			$this->db->where('request_id', $request_id)->where('request_status', 'Accepted')
				->update('requests', array('responsible_doctor_user_id' => (int) $doctor['user_id']));
			if ($this->db->affected_rows() !== 1) {
				return $this->failure('Dokter belum dapat diperbarui.');
			}
			if (!$this->createAssignmentNotification(
				(int) $doctor['user_id'],
				$actor_user_id,
				$request_id,
				'responsible_doctor_assigned',
				'Konsultasi baru untuk Anda',
				'Buka konsultasi untuk melihat detail.',
				$now
			)) {
				return $this->failure('Dokter belum dapat diperbarui.');
			}
			if (!$this->appendWorkflowEvent($request_id, 'responsible_doctor.assigned', array(
				'actor_user_id' => $actor_user_id,
				'actor_staff_id' => (int) ($actor['staff_id'] ?? 0),
				'domain_event_key' => 'responsible-doctor:' . $responsible_assignment_id . ':assigned',
				'metadata' => array('staff_id' => (int) $doctor['staff_id'], 'user_id' => (int) $doctor['user_id']),
			))) {
				return $this->failure('Dokter belum dapat diperbarui.');
			}
			return $this->success('Dokter penanggung jawab diperbarui.');
		});
	}

	public function chooseMode($request_id, $mode, $actor_user_id, $session_token = null, $session_required = false)
	{
		$request_id = (int) $request_id;
		$actor_user_id = (int) $actor_user_id;
		$mode = $this->policy->mode($mode);
		if ($request_id < 1 || $actor_user_id < 1 || $mode === null || !$this->schemaReady()) {
			return $this->failure('Pilih jenis layanan.');
		}
		return $this->transaction(function () use ($request_id, $mode, $actor_user_id, $session_token, $session_required) {
			$request = $this->lockedRequest($request_id);
			if (!$request || (string) $request->request_status !== 'Accepted') {
				return $this->failure('Konsultasi tidak dapat diperbarui.');
			}
			$puskesmas_code = trim((string) $request->assigned_puskesmas_code);
			$actor = $this->lockedIdentity($actor_user_id);
			if (!$this->policy->responsibleDoctorEligible($actor, $puskesmas_code)
				|| (int) $request->responsible_doctor_user_id !== $actor_user_id
				|| !$this->activeSession($actor_user_id, $session_token, $session_required)) {
				return $this->failure('Anda tidak memiliki akses.');
			}
			$active_visit_assignments = $this->lockedVisitAssignments($request_id);
			if ($active_visit_assignments === false) {
				return $this->failure('Data Nakes perlu diperiksa.');
			}
			$mode_failure = $this->policy->modeChangeFailure(
				array(
					'consultation_mode' => isset($request->consultation_mode) ? $request->consultation_mode : '',
					'visit_status' => isset($request->visit_status) ? $request->visit_status : '',
				),
				$this->dispositionForRequest($request_id),
				count($active_visit_assignments),
				$this->workflowEnabled(),
				$mode
			);
			if ($mode_failure !== null) {
				return $this->failure($this->policyMessage($mode_failure), $mode_failure);
			}
			$now = date('Y-m-d H:i:s');
			$changed = (string) ($request->consultation_mode ?? '') !== $mode;
			if ($mode === Care_team_policy::NON_VISIT) {
				$active = $active_visit_assignments;
				if ($active === false || count($active) > 1) {
					return $this->failure('Data Nakes perlu diperiksa.');
				}
				if (count($active) === 1) {
					$this->db->where('visit_assignment_id', (int) $active[0]->visit_assignment_id)->where('status', 'aktif')
						->update('request_visit_performer_assignments', array('status' => 'dibatalkan', 'ended_at' => $now, 'updated_at' => $now));
					if ($this->db->affected_rows() !== 1) {
						return $this->failure('Jenis layanan belum dapat diperbarui.');
					}
				}
			}
			$data = array('consultation_mode' => $mode);
			if ($mode === Care_team_policy::NON_VISIT) {
				$data['visit_performer_user_id'] = null;
			}
			$this->db->where('request_id', $request_id)->where('request_status', 'Accepted')->update('requests', $data);
			if ($this->db->trans_status() === false) {
				return $this->failure('Jenis layanan belum dapat diperbarui.');
			}
			if ($changed && !$this->appendWorkflowEvent($request_id, 'service_mode.selected', array(
				'actor_user_id' => $actor_user_id,
				'actor_staff_id' => (int) ($actor['staff_id'] ?? 0),
				'metadata' => array('consultation_mode' => $mode),
			))) {
				return $this->failure('Jenis layanan belum dapat diperbarui.');
			}
			return $this->success(
				$mode === Care_team_policy::VISIT ? 'Kunjungan dipilih.' : 'Tanpa kunjungan dipilih.',
				array('changed' => $changed, 'consultation_mode' => $mode)
			);
		});
	}

	public function assignVisitPerformer($request_id, $staff_id, $actor_user_id, $session_token = null, $session_required = false)
	{
		$request_id = (int) $request_id;
		$staff_id = (int) $staff_id;
		$actor_user_id = (int) $actor_user_id;
		if ($request_id < 1 || $staff_id < 1 || $actor_user_id < 1 || !$this->schemaReady()) {
			return $this->failure('Pengaturan Nakes belum tersedia.');
		}
		return $this->transaction(function () use ($request_id, $staff_id, $actor_user_id, $session_token, $session_required) {
			$request = $this->lockedRequest($request_id);
			if (!$request || (string) $request->request_status !== 'Accepted'
				|| (string) $request->consultation_mode !== Care_team_policy::VISIT) {
				return $this->failure('Pilih layanan kunjungan terlebih dahulu.');
			}
			$performer_failure = $this->policy->performerChangeFailure(array(
				'visit_status' => isset($request->visit_status) ? $request->visit_status : '',
			));
			if ($performer_failure !== null) {
				return $this->failure($this->policyMessage($performer_failure), $performer_failure);
			}
			$puskesmas_code = trim((string) $request->assigned_puskesmas_code);
			$actor = $this->lockedIdentity($actor_user_id);
			if (!$this->policy->responsibleDoctorEligible($actor, $puskesmas_code)
				|| (int) $request->responsible_doctor_user_id !== $actor_user_id
				|| !$this->activeSession($actor_user_id, $session_token, $session_required)) {
				return $this->failure('Anda tidak memiliki akses.');
			}
			$performer = $this->lockedStaffIdentity($staff_id);
			if (!$this->policy->visitPerformerEligible($performer, $puskesmas_code)) {
				return $this->failure('Pilih Nakes dari Puskesmas ini.');
			}
			$managed_failure = $this->managedAssignmentFailure($request_id, $puskesmas_code, $performer);
			if ($managed_failure !== null) {
				return $managed_failure;
			}
			$active = $this->lockedVisitAssignments($request_id);
			if ($active === false || count($active) > 1) {
				return $this->failure('Data Nakes perlu diperiksa.');
			}
			if (count($active) === 1 && (int) $active[0]->staff_id === $staff_id) {
				if ((int) $request->visit_performer_user_id !== (int) $performer['user_id']) {
					return $this->failure('Data Nakes perlu diperiksa.');
				}
				return $this->success('Nakes kunjungan tetap sama.');
			}
			$now = date('Y-m-d H:i:s');
			if (count($active) === 1) {
					$this->db->where('visit_assignment_id', (int) $active[0]->visit_assignment_id)->where('status', 'aktif')
						->update('request_visit_performer_assignments', array('status' => 'diganti', 'ended_at' => $now, 'updated_at' => $now));
				if ($this->db->affected_rows() !== 1) {
					return $this->failure('Nakes belum dapat diperbarui.');
				}
			}
			if (!$this->db->insert('request_visit_performer_assignments', array(
				'request_id' => $request_id,
				'staff_id' => $staff_id,
				'user_id' => (int) $performer['user_id'],
				'assigned_by_user_id' => $actor_user_id,
				'status' => 'aktif',
				'assigned_at' => $now,
				'created_at' => $now,
				'updated_at' => $now,
			))) {
				return $this->failure('Nakes belum dapat diperbarui.');
			}
			$visit_assignment_id = (int) $this->db->insert_id();
			$data = array('visit_performer_user_id' => (int) $performer['user_id']);
			$this->db->where('request_id', $request_id)->where('responsible_doctor_user_id', $actor_user_id)
				->where('request_status', 'Accepted')->update('requests', $data);
			if ($this->db->affected_rows() !== 1) {
				return $this->failure('Nakes belum dapat diperbarui.');
			}
			$doctor_name = $this->doctorDisplayName($actor);
			if ($doctor_name === '' || !$this->createAssignmentNotification(
				(int) $performer['user_id'],
				$actor_user_id,
				$request_id,
				'visit_performer_assigned',
				'Tugas kunjungan baru',
				'Ditugaskan oleh ' . $doctor_name . '.',
				$now
			)) {
				return $this->failure('Nakes belum dapat diperbarui.');
			}
			if (!$this->appendWorkflowEvent($request_id, 'visit_performer.assigned', array(
				'actor_user_id' => $actor_user_id,
				'actor_staff_id' => (int) ($actor['staff_id'] ?? 0),
				'domain_event_key' => 'visit-performer:' . $visit_assignment_id . ':assigned',
				'metadata' => array('staff_id' => $staff_id, 'user_id' => (int) $performer['user_id']),
			))) {
				return $this->failure('Nakes belum dapat diperbarui.');
			}
			return $this->success('Nakes kunjungan diperbarui.');
		});
	}

	private function createAssignmentNotification($recipient_user_id, $actor_user_id, $request_id, $event_type, $title, $message, $created_at)
	{
		return $this->notification_delivery->createWithinTransaction(array(
			'recipient_user_id' => (int) $recipient_user_id,
			'recipient_role' => 'dokter',
			'recipient_puskesmas_code' => null,
			'actor_user_id' => (int) $actor_user_id,
			'event_type' => (string) $event_type,
			'entity_type' => 'request',
			'entity_id' => (string) ((int) $request_id),
			'title' => (string) $title,
			'message' => (string) $message,
			'is_read' => 0,
			'created_at' => (string) $created_at,
		)) !== false;
	}

	public function requestContext($request_id, $user_id)
	{
		$request_id = (int) $request_id;
		$user_id = (int) $user_id;
		$result = array('valid' => false, 'is_responsible_doctor' => false, 'is_visit_performer' => false, 'can_assess' => false, 'can_visit' => false);
		if ($request_id < 1 || $user_id < 1 || !$this->schemaReady()) {
			return $result;
		}
		$request = $this->db->select('request_id, request_status, assigned_puskesmas_code, responsible_doctor_user_id, consultation_mode, visit_performer_user_id')
			->where('request_id', $request_id)->limit(1)->get('requests')->row();
		$identity = $this->identity($user_id);
		if (!$request || !$this->policy->personalEligible($identity, $request->assigned_puskesmas_code)) {
			return $result;
		}
		$result['is_responsible_doctor'] = (int) $request->responsible_doctor_user_id === $user_id
			&& $this->policy->responsibleDoctorEligible($identity, $request->assigned_puskesmas_code);
		$enrolled = false;
		if ($this->db->table_exists('visit_dispositions')) {
			$enrolled = $this->db->where('request_id', $request_id)->limit(1)->get('visit_dispositions')->num_rows() > 0;
		}
		$canonical_performer = false;
		if ($enrolled && $this->db->table_exists('request_visit_performer_assignments')) {
			$canonical_performer = $this->db
				->where('request_id', $request_id)
				->where('user_id', $user_id)
				->where('status', 'aktif')
				->limit(1)
				->get('request_visit_performer_assignments')
				->num_rows() > 0;
		}
		$result['is_visit_performer'] = (string) $request->consultation_mode === Care_team_policy::VISIT
			&& $this->policy->visitPerformerEligible($identity, $request->assigned_puskesmas_code)
			&& ($enrolled ? $canonical_performer : (int) $request->visit_performer_user_id === $user_id);
		$result['can_assess'] = $result['is_responsible_doctor'] && (string) $request->request_status === 'Accepted';
		$result['can_visit'] = $result['is_visit_performer'] && (string) $request->request_status === 'Accepted';
		$result['valid'] = $result['can_assess'] || $result['can_visit'];
		return $result;
	}

	public function activeResponsibleDoctorAssignments($request_id)
	{
		$query = $this->db->query(
			'SELECT responsible_assignment_id, staff_id, user_id FROM ' . $this->db->dbprefix('request_responsible_doctor_assignments') . ' WHERE request_id = ? AND status = ?',
			array((int) $request_id, 'aktif')
		);
		return $query ? $query->result() : false;
	}

	public function activeVisitPerformerAssignments($request_id)
	{
		$query = $this->db->query(
			'SELECT visit_assignment_id, staff_id, user_id FROM ' . $this->db->dbprefix('request_visit_performer_assignments') . ' WHERE request_id = ? AND status = ?',
			array((int) $request_id, 'aktif')
		);
		return $query ? $query->result() : false;
	}

	private function transaction($operation)
	{
		if (!$this->db->trans_begin()) {
			return $this->failure('Coba lagi.');
		}
		try {
			$result = $operation();
			if (empty($result['ok']) || $this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return $result;
			}
			if (!$this->db->trans_commit()) {
				$this->db->trans_rollback();
				return $this->failure('Coba lagi.');
			}
			return $result;
		} catch (Throwable $exception) {
			$this->db->trans_rollback();
			return $this->failure('Coba lagi.');
		}
	}

	private function activeSession($user_id, $session_token, $required)
	{
		if (!$required) {
			return true;
		}
		require_once __DIR__ . '/Session_binding_service.php';
		return (new Session_binding_service($this->db))->validateLocked((int) $user_id, $session_token);
	}

	private function lockedRequest($request_id)
	{
		$query = $this->db->query(
			'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
			array((int) $request_id)
		);
		return $query ? $query->row() : null;
	}

	private function lockedIdentity($user_id)
	{
		return $this->identity((int) $user_id, true);
	}

	private function lockedStaffIdentity($staff_id)
	{
		$query = $this->db->query(
			'SELECT user_id FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE staff_id = ? FOR UPDATE',
			array((int) $staff_id)
		);
		$row = $query ? $query->row() : null;
		if (!$row || (int) $row->user_id < 1) {
			return array('valid' => false);
		}
		$identity = $this->identity((int) $row->user_id, true);
		return (int) ($identity['staff_id'] ?? 0) === (int) $staff_id ? $identity : array('valid' => false);
	}

	private function identity($user_id, $lock = false)
	{
		$user_name = $this->db->field_exists('nama', 'users') ? ', nama' : ', NULL AS nama';
		$user_sql = 'SELECT userId, role, status, remark' . $user_name . ' FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ?' . ($lock ? ' FOR UPDATE' : '');
		$user_query = $this->db->query($user_sql, array((int) $user_id));
		$user = $user_query ? $user_query->row() : null;
		$result = array('valid' => false, 'account_type' => 'unclassified', 'user_id' => (int) $user_id);
		if (!$user || (string) $user->role !== 'dokter' || (string) $user->status !== 'aktif') {
			return $result;
		}
		$puskesmas_code = trim((string) $user->remark);
		$facility_sql = 'SELECT kode_pkm, status FROM ' . $this->db->dbprefix('m_puskesmas') . ' WHERE kode_pkm = ?' . ($lock ? ' FOR UPDATE' : '');
		$facility_query = $this->db->query($facility_sql, array($puskesmas_code));
		$facility = $facility_query ? $facility_query->row() : null;
		if ($puskesmas_code === '' || !$facility || (string) $facility->status !== 'aktif') {
			return $result;
		}
		$staff_name = $this->db->field_exists('nama', 'puskesmas_staff') ? ', nama' : ', NULL AS nama';
		$staff_title = $this->db->field_exists('gelar', 'puskesmas_staff') ? ', gelar' : ', NULL AS gelar';
		$staff_sql = 'SELECT staff_id, user_id, kode_pkm, profesi, status' . $staff_name . $staff_title . ' FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE user_id = ? ORDER BY staff_id ASC' . ($lock ? ' FOR UPDATE' : '');
		$staff_query = $this->db->query($staff_sql, array((int) $user_id));
		$staff_rows = $staff_query ? $staff_query->result() : array();
		$command_query = $this->db->query(
			'SELECT userId FROM ' . $this->db->dbprefix('users') . ' WHERE role = ? AND status = ? AND TRIM(remark) = ? ORDER BY userId ASC LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
			array('dokter', 'aktif', $puskesmas_code)
		);
		$command = $command_query ? $command_query->row() : null;
		$result['user_status'] = (string) $user->status;
		$result['user_name'] = trim((string) $user->nama);
		$result['puskesmas_code'] = $puskesmas_code;
		if ($command && (int) $command->userId === (int) $user_id) {
			if (count($staff_rows) !== 0) {
				return $result;
			}
			$result['valid'] = true;
			$result['account_type'] = 'command_center';
			$result['is_command_center'] = true;
			return $result;
		}
		if (count($staff_rows) !== 1) {
			return $result;
		}
		$staff = $staff_rows[0];
		if ((string) $staff->status !== 'aktif' || trim((string) $staff->kode_pkm) !== $puskesmas_code) {
			return $result;
		}
		$result['valid'] = true;
		$result['account_type'] = 'personal';
		$result['staff_id'] = (int) $staff->staff_id;
		$result['staff_status'] = (string) $staff->status;
		$result['staff_profesi'] = (string) $staff->profesi;
		$result['staff_name'] = trim((string) $staff->nama);
		$result['staff_gelar'] = trim((string) $staff->gelar);
		return $result;
	}

	private function doctorDisplayName(array $identity)
	{
		$name = trim((string) ($identity['staff_name'] ?? $identity['user_name'] ?? ''));
		$title = trim((string) ($identity['staff_gelar'] ?? ''));
		$title_word = rtrim($title, ". \t\n\r\0\x0B");
		$title_present = $title_word !== '' && preg_match('/^' . preg_quote($title_word, '/') . '\.?(?:\s|$)/iu', $name) === 1;
		if ($name === '' || $title === '' || $title_present) {
			return $name;
		}
		return preg_match('/^(?:dr|drg)\.?$/iu', $title) === 1
			? $title . ' ' . $name
			: $name . ', ' . $title;
	}

	private function lockedResponsibleAssignments($request_id)
	{
		$query = $this->db->query(
			'SELECT responsible_assignment_id, staff_id, user_id FROM ' . $this->db->dbprefix('request_responsible_doctor_assignments') . ' WHERE request_id = ? AND status = ? FOR UPDATE',
			array((int) $request_id, 'aktif')
		);
		return $query ? $query->result() : false;
	}

	private function lockedVisitAssignments($request_id)
	{
		$query = $this->db->query(
			'SELECT visit_assignment_id, staff_id, user_id FROM ' . $this->db->dbprefix('request_visit_performer_assignments') . ' WHERE request_id = ? AND status = ? FOR UPDATE',
			array((int) $request_id, 'aktif')
		);
		return $query ? $query->result() : false;
	}

	/**
	 * The managed clinical workflow only permits the performer the responsible
	 * doctor's own disposition authorises. The disposition is the clinical
	 * decision that says a visit is required and which profession may perform
	 * it, so an assignment that ignores it would let the responsible doctor
	 * contradict their own recorded decision - the exact divergence between the
	 * workflow state resolver and the assignment path this guard closes.
	 *
	 * Requests outside the managed workflow keep the legacy behaviour so
	 * unenrolled consultations stay operable.
	 *
	 * Returns a failure array when the assignment must be refused, or null when
	 * it may proceed.
	 */
	private function managedAssignmentFailure($request_id, $puskesmas_code, array $performer)
	{
		if (!$this->managedWorkflowRequest($request_id)) {
			return null;
		}
		$disposition = $this->activeVisitDisposition($request_id);
		if ($disposition === null) {
			return $this->failure('Dokter penanggung jawab belum menetapkan kunjungan.');
		}
		$required = trim((string) ($disposition->required_profession ?? ''));
		if ($required !== '' && strcasecmp(trim((string) ($performer['staff_profesi'] ?? '')), $required) !== 0) {
			return $this->failure('Pilih Nakes dengan profesi ' . $required . '.');
		}
		if (!$this->activeFacilityPlacement((int) ($performer['staff_id'] ?? 0), $puskesmas_code)) {
			return $this->failure('Nakes belum terdaftar di Puskesmas ini.');
		}
		return null;
	}

	private function managedWorkflowRequest($request_id)
	{
		// Enrollment is what makes a request managed, so without the enrollment
		// table no request can be managed and the legacy path is the only path.
		// Guarding here also keeps the check safe on strict-mysqli runtimes.
		if (!$this->db->table_exists('visit_dispositions')) {
			return false;
		}
		require_once __DIR__ . '/Visit_workflow_policy.php';
		$enabled = false;
		if (function_exists('get_instance')) {
			$CI = &get_instance();
			if (is_object($CI) && isset($CI->config) && is_object($CI->config)) {
				$enabled = $CI->config->item('visit_clinical_workflow_enabled') === true;
			}
		}
		return (new Visit_workflow_policy($this->db, $enabled))->isManagedWorkflowRequest((int) $request_id);
	}

	/**
	 * Returns the active disposition only when it requires a visit. A missing
	 * disposition, or one that decided against a visit, both mean no performer
	 * may be assigned.
	 */
	private function activeVisitDisposition($request_id)
	{
		if (!$this->db->table_exists('visit_dispositions')) {
			return null;
		}
		$query = $this->db->query(
			'SELECT decision, required_profession FROM ' . $this->db->dbprefix('visit_dispositions')
				. ' WHERE request_id = ? AND superseded_at IS NULL ORDER BY version_no DESC LIMIT 1 FOR UPDATE',
			array((int) $request_id)
		);
		$row = $query ? $query->row() : null;
		if (!$row || (string) $row->decision !== Care_team_policy::VISIT) {
			return null;
		}
		return $row;
	}

	private function activeFacilityPlacement($staff_id, $puskesmas_code)
	{
		if ($staff_id < 1 || !$this->db->table_exists('nakes_facility_placements')) {
			return false;
		}
		$query = $this->db->query(
			'SELECT placement_id FROM ' . $this->db->dbprefix('nakes_facility_placements')
				. ' WHERE staff_id = ? AND facility_code = ? AND status = ? LIMIT 1',
			array((int) $staff_id, trim((string) $puskesmas_code), 'active')
		);
		return $query && $query->num_rows() === 1;
	}

	private function success($message, array $data = array())
	{
		return array_merge(array('ok' => true, 'status' => 'success', 'message' => $message), $data);
	}

	private function failure($message, $code = null)
	{
		$result = array('ok' => false, 'status' => 'error', 'message' => (string) $message);
		if ($code !== null) {
			$result['safe_error_code'] = (string) $code;
		}
		return $result;
	}

	private function dispositionForRequest($request_id)
	{
		if (!$this->db->table_exists('visit_dispositions')) {
			return null;
		}
		return $this->db->select('disposition_id, request_id, version_no, decision, urgency, required_profession')
			->where('request_id', (int) $request_id)
			->where('superseded_at', null)
			->order_by('version_no', 'DESC')
			->limit(1)
			->get('visit_dispositions')
			->row();
	}

	private function workflowEnabled()
	{
		$CI = function_exists('get_instance') ? get_instance() : null;
		return $CI && isset($CI->config) && $CI->config->item('visit_clinical_workflow_enabled') === true;
	}

	private function policyMessage($code)
	{
		$messages = array(
			'VISIT_ALREADY_STARTED' => 'Kunjungan sudah berjalan. Nakes tidak dapat diganti.',
			'MODE_LOCKED_BY_DISPOSITION' => 'Jenis layanan mengikuti disposisi dokter dan tidak dapat diubah di sini.',
			'PERFORMER_LOCKED' => 'Batalkan atau selesaikan penugasan Nakes sebelum mengubah jenis layanan.',
			'HANDOVER_LOCKED' => 'Dokter penanggung jawab tidak dapat diganti setelah disposisi atau kunjungan dimulai.',
		);
		return isset($messages[$code]) ? $messages[$code] : 'Perubahan tidak dapat dilakukan.';
	}

	private function appendWorkflowEvent($request_id, $event_type, array $payload = array())
	{
		if (!function_exists('doclinc_request_event_table_ready')) {
			require_once APPPATH . 'helpers/request_event_helper.php';
		}
		$CI = function_exists('get_instance') ? get_instance() : null;
		if (!$CI || !doclinc_request_event_table_ready($CI)) {
			// Audit opsional: jangan menggagalkan penugasan hanya karena tabel
			// event belum dimigrasikan.
			return true;
		}
		return doclinc_append_request_event((int) $request_id, (string) $event_type, array(
			'actor_user_id' => (int) ($payload['actor_user_id'] ?? 0),
			'actor_staff_id' => (int) ($payload['actor_staff_id'] ?? 0),
			'domain_event_key' => (string) ($payload['domain_event_key'] ?? ''),
			'metadata' => isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : array(),
		), $CI);
	}
}
