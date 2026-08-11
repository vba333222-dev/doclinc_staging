<?php
class Konsultasi_nakes_m extends MX_Controller
{
	private $last_failure_code = '';

	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	private function normalize_puskesmas_code($code)
	{
		$code = trim((string) $code);
		return $code !== '' && strtoupper($code) !== 'DEFAULT' ? $code : '';
	}

	private function request_assigned_puskesmas_code($request)
	{
		return $request && isset($request->assigned_puskesmas_code)
			? $this->normalize_puskesmas_code($request->assigned_puskesmas_code)
			: '';
	}

	private function get_user_puskesmas_code($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1) {
			return '';
		}

		$auth_db = $this->load->database('default', true);
		if (!$auth_db->field_exists('remark', 'users')) {
			return '';
		}

		$user = $auth_db
			->select('remark')
			->where('userId', $user_id)
			->where('role', 'dokter')
			->get('users')
			->row();
		if (method_exists($auth_db, 'reset_query')) {
			$auth_db->reset_query();
		}

		return $user ? $this->normalize_puskesmas_code($user->remark) : '';
	}

	private function request_matches_user_puskesmas($request, $user_id)
	{
		$request_code = $this->request_assigned_puskesmas_code($request);
		return $request_code === '' || $request_code === $this->get_user_puskesmas_code($user_id);
	}

	private function where_legacy_assigned_puskesmas($prefix = '')
	{
		$field = $prefix . 'assigned_puskesmas_code';
		$this->db->group_start();
		$this->db->where($field . ' IS NULL', null, false);
		$this->db->or_where("TRIM({$field}) = ''", null, false);
		$this->db->or_where("UPPER(TRIM({$field})) = 'DEFAULT'", null, false);
		$this->db->group_end();
	}

	private function where_puskesmas_flow_owner($user_id, $prefix = '')
	{
		if (!$this->db->field_exists('assigned_puskesmas_code', 'requests')) {
			return;
		}

		$puskesmas_code = $this->get_user_puskesmas_code($user_id);
		if ($puskesmas_code === '') {
			$this->where_legacy_assigned_puskesmas($prefix);
			return;
		}

		$field = $prefix . 'assigned_puskesmas_code';
		$this->db->group_start();
		$this->db->where('TRIM(' . $field . ') = ' . $this->db->escape($puskesmas_code), null, false);
		$this->db->or_group_start();
		$this->where_legacy_assigned_puskesmas($prefix);
		$this->db->group_end();
		$this->db->group_end();
	}

	private function where_handling_nakes_owner($user_id, $prefix = '')
	{
		$this->db->group_start();
		if ($this->config->item('care_team_workflow_enabled') === true
			&& $this->db->field_exists('responsible_doctor_user_id', 'requests')
			&& $this->db->field_exists('visit_performer_user_id', 'requests')) {
			$this->db->where($prefix . 'responsible_doctor_user_id', $user_id);
			$this->db->or_where($prefix . 'visit_performer_user_id', $user_id);
			$this->db->group_end();
			return;
		}
		if ($this->db->field_exists('assigned_nakes_user_id', 'requests')) {
			$this->db->where($prefix . 'assigned_nakes_user_id', $user_id);
			if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
				$this->db->or_where($prefix . 'accepted_by_user_id', $user_id);
			}
			if ($this->db->field_exists('dokter_id', 'requests')) {
				$this->db->or_where($prefix . 'dokter_id', $user_id);
			}
		} else {
			$this->db->where($prefix . 'dokter_id', $user_id);
			if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
				$this->db->or_where($prefix . 'accepted_by_user_id', $user_id);
			}
		}
		$this->db->group_end();
	}

	public function get_data_request($request_id, $doctor_id = null, $identity_context = null)
	{
		$doctor_id = (int) $doctor_id;
		$database_identity = function_exists('doclinc_dokter_identity_context')
			? doclinc_dokter_identity_context($doctor_id, true)
			: null;
		if ($doctor_id < 1
			|| !is_array($identity_context)
			|| empty($identity_context['valid'])
			|| (int) $identity_context['user_id'] !== $doctor_id
			|| empty($database_identity['valid'])
			|| !function_exists('doclinc_can_view_nakes_request')
			|| !doclinc_can_view_nakes_request($request_id, $database_identity)) {
			return $this->db->query('SELECT 1 WHERE 1 = 0');
		}
		$userIdSelect = $this->db->field_exists('userid', 'users') ? 'users.userid' : 'users.userId AS userid';
		$tglSelect = $this->db->field_exists('tgl', 'users') ? 'users.tgl' : 'NULL AS tgl';

		$this->db
			->select('requests.*, users.nama')
			->select($userIdSelect, FALSE)
			->select($tglSelect, FALSE)
			->from('requests')
			->join('users', 'requests.user_id = users.userId')
			->where('requests.request_status', 'Accepted')
			->where('requests.request_id', $request_id);
		return $this->db
			->get();
	}

	// public function save_konsultasi_nakes($request_id, $diagnosa, $saran)
	// {
	// 	$date = date('Y-m-d H:i:s');
	// 	$user = $this->session->userdata('id');
	// 	$this->db->query("UPDATE requests SET request_status = 'Completed' WHERE request_id = '$request_id'");
	// 	$this->db->query("INSERT konsultasi SET
	// 					 request_id = '$request_id',
	// 					 diagnosa = '$diagnosa',
	// 					 saran = '$saran',
	// 					 create_date = '$date',
	// 					 create_user = '$user'");
	// }

	public function save_konsultasi_nakes($request_id, $diagnosa, $saran, $kriteria, $rujukan, $file_path, $terapi, $doctor_id = null, $identity_context = null, $anamnesis = null, $write_anamnesis = false, $visit_proof = null, $visit_proof_required = false, $diagnoses = array(), $write_diagnoses = false)
	{
		$this->last_failure_code = '';
		$date = date('Y-m-d H:i:s');
		$user = $doctor_id ?: $this->session->userdata('id');
		$terapi = is_array($terapi) ? $terapi : [];
		$diagnoses = is_array($diagnoses) ? array_values($diagnoses) : array();

		if (!is_array($identity_context)
			|| empty($identity_context['valid'])
			|| (int) $identity_context['user_id'] !== (int) $user) {
			$this->last_failure_code = 'actor_invalid';
			return false;
		}

		$this->db->trans_begin();
		$request = $this->db->query(
			'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
			array($request_id)
		)->row();
		$database_identity = function_exists('doclinc_dokter_identity_context')
			? doclinc_dokter_identity_context($user, true)
			: null;
		$access_context = $request && function_exists('doclinc_nakes_request_access_context')
			? doclinc_nakes_request_access_context($request_id, $database_identity)
			: null;
		$can_assess = $this->config->item('care_team_workflow_enabled') === true
			? !empty($access_context['can_assess'])
			: !empty($access_context['can_handle']);
		if (!$request || !$can_assess || (string) $request->request_status !== 'Accepted') {
			$this->last_failure_code = 'request_not_completable';
			$this->db->trans_rollback();
			return false;
		}
		if ($write_anamnesis
			&& (!$this->db->table_exists('medicalrecords')
				|| !$this->db->field_exists('record_id', 'medicalrecords')
				|| !$this->db->field_exists('request_id', 'medicalrecords')
				|| !$this->db->field_exists('anamnesis', 'medicalrecords'))) {
			$this->last_failure_code = 'anamnesis_schema_unavailable';
			$this->db->trans_rollback();
			return false;
		}
		if ($write_diagnoses) {
			require_once APPPATH . 'libraries/Medicalrecord_diagnosis_service.php';
			$normalized_diagnoses = Medicalrecord_diagnosis_service::normalize(
				isset($diagnoses[0]) ? $diagnoses[0] : $diagnosa,
				array_slice($diagnoses, 1),
				true
			);
			if (empty($normalized_diagnoses['valid'])) {
				$this->last_failure_code = 'diagnosis_payload_invalid';
				$this->db->trans_rollback();
				return false;
			}
			if (!Medicalrecord_diagnosis_service::schemaReady($this->db)) {
				$this->last_failure_code = 'diagnosis_schema_unavailable';
				$this->db->trans_rollback();
				return false;
			}
			$diagnoses = $normalized_diagnoses['diagnoses'];
			$diagnosa = $diagnoses[0];
		}

		$consultation_mode = $this->consultation_mode_from_kriteria($kriteria);
		if ($this->config->item('care_team_workflow_enabled') === true) {
			$stored_mode = isset($request->consultation_mode) ? (string) $request->consultation_mode : '';
			if ($stored_mode === '' || $consultation_mode !== $stored_mode) {
				$this->last_failure_code = 'service_mode_mismatch';
				$this->db->trans_rollback();
				return false;
			}
			if ($stored_mode === 'visit') {
				$performer_count = $this->db->where('request_id', (int) $request_id)
					->where('status', 'aktif')->count_all_results('request_visit_performer_assignments');
				if ($performer_count !== 1 || empty($request->visit_performer_user_id)) {
					$this->last_failure_code = 'visit_performer_required';
					$this->db->trans_rollback();
					return false;
				}
			}
		}
		if ($visit_proof_required && $consultation_mode === null) {
			$this->last_failure_code = 'visit_mode_invalid';
			$this->db->trans_rollback();
			return false;
		}
		$persisted_visit = function_exists('doclinc_visit_proof_persisted_visit')
			? doclinc_visit_proof_persisted_visit($request)
			: (isset($request->consultation_mode) && (string) $request->consultation_mode === 'visit');
		if ($visit_proof_required && $persisted_visit && $consultation_mode !== 'visit') {
			$this->last_failure_code = 'visit_mode_mismatch';
			$this->db->trans_rollback();
			return false;
		}
		$locked_visit_media = null;
		if ($visit_proof_required && ($consultation_mode === 'visit' || $persisted_visit)) {
			$locked_visit_media = $this->validate_and_record_visit_proof($request, $user, $visit_proof, $file_path);
			if (!$locked_visit_media) {
				$this->db->trans_rollback();
				return false;
			}
		}

		$request_data = ['request_status' => 'Completed'];
		if ($this->db->field_exists('updated_at', 'requests')) {
			$request_data['updated_at'] = $date;
		}
		if ($this->db->field_exists('visit_status', 'requests')) {
			$request_data['visit_status'] = 'completed';
		}
		if ($this->db->field_exists('consultation_mode', 'requests')) {
			if ($consultation_mode !== null) {
				$request_data['consultation_mode'] = $consultation_mode;
			}
		}

		$this->db->where('request_id', $request_id)->where('request_status', 'Accepted');
		$request_code = isset($request->assigned_puskesmas_code) ? trim((string) $request->assigned_puskesmas_code) : '';
		if ($request_code !== '') {
			$this->db->where('TRIM(assigned_puskesmas_code) = ' . $this->db->escape($database_identity['puskesmas_code']), null, false);
		} else {
			$this->db->group_start()->where('assigned_puskesmas_code IS NULL', null, false)->or_where("TRIM(assigned_puskesmas_code) = ''", null, false)->group_end();
		}
		if ($this->db->field_exists('visit_completed_at', 'requests')) {
			$this->db->set('visit_completed_at', 'COALESCE(visit_completed_at, ' . $this->db->escape($date) . ')', FALSE);
		}
		if ($locked_visit_media && isset($visit_proof['location'])) {
			if ($this->db->field_exists('lattitude_dokter', 'requests')) {
				$this->db->set('lattitude_dokter', (float) $visit_proof['location']['latitude']);
			}
			if ($this->db->field_exists('longitude_dokter', 'requests')) {
				$this->db->set('longitude_dokter', (float) $visit_proof['location']['longitude']);
			}
		}
		$this->db->update('requests', $request_data);
		if ($this->db->affected_rows() < 1) {
			$this->last_failure_code = 'request_update_failed';
			$this->db->trans_rollback();
			return false;
		}

		$data_konsul = [
			'request_id' => $request_id,
			'diagnosa' => $diagnosa,
			'saran' => $saran,
			'kriteria' => $kriteria,
			'rujukan' => $rujukan,
			'foto' => $file_path,
			'create_date' => $date,
			'create_user' => $user
		];

		$medicalrecord_id = null;
		if ($this->db->table_exists('medicalrecords')) {
			$record = [
				'request_id' => $request_id,
				'diagnosis' => $diagnosa,
				'treatment' => $this->build_treatment_summary($kriteria, $rujukan, $file_path, $terapi),
				'recommendations' => $saran,
				'created_at' => $date
			];

			if ($write_anamnesis) {
				$existing = $this->db->query(
					'SELECT * FROM ' . $this->db->dbprefix('medicalrecords') . ' WHERE request_id = ? ORDER BY record_id DESC LIMIT 1 FOR UPDATE',
					array($request_id)
				)->row();
				if ($anamnesis !== null || !$existing) {
					$record['anamnesis'] = $anamnesis;
				}
			} else {
				$existing = $this->db->get_where('medicalrecords', ['request_id' => $request_id])->row();
			}
			if ($existing) {
				$medicalrecord_id = isset($existing->record_id) ? (int) $existing->record_id : null;
				if ($write_anamnesis) {
					$this->db->where('record_id', (int) $existing->record_id);
				} else {
					$this->db->where('request_id', $request_id);
				}
				if (!$this->db->update('medicalrecords', $record)) {
					$this->last_failure_code = 'medicalrecord_write_failed';
					$this->db->trans_rollback();
					return false;
				}
			} else {
				if (!$this->db->insert('medicalrecords', $record)) {
					$this->last_failure_code = 'medicalrecord_write_failed';
					$this->db->trans_rollback();
					return false;
				}
				$medicalrecord_id = (int) $this->db->insert_id();
			}
		}

		if ($this->db->table_exists('konsultasi')) {
			$existing_konsul = $this->db->get_where('konsultasi', ['request_id' => $request_id])->row();
			if ($existing_konsul) {
				$this->db->where('request_id', $request_id);
				$this->db->update('konsultasi', $data_konsul);
				$konsul_id = $existing_konsul->konsul_id;
			} else {
				$this->db->insert('konsultasi', $data_konsul);
				$konsul_id = $this->db->insert_id();
			}

			if ($this->db->table_exists('terapi') && !empty($terapi)) {
				foreach ($terapi as $t) {
					$data_terapi = [
						'konsul_id' => $konsul_id,
						'terapi' => $t['terapi'] ?? '',
						'signa' => ($t['jumlah'] ?? '') . ' - ' . ($t['cara'] ?? ''),
						'keterangan' => $t['keterangan'] ?? '',
						'create_date' => $date,
						'create_user' => $user
					];
					$this->db->insert('terapi', $data_terapi);
				}
			}
		}
		if ($write_diagnoses && !Medicalrecord_diagnosis_service::replace(
			$this->db,
			$medicalrecord_id,
			$request_id,
			$user,
			$diagnoses
		)) {
			$this->last_failure_code = 'diagnosis_write_failed';
			$this->db->trans_rollback();
			return false;
		}
		if ($locked_visit_media) {
			if (!$medicalrecord_id || $medicalrecord_id < 1) {
				$this->last_failure_code = 'visit_proof_finalize_failed';
				$this->db->trans_rollback();
				return false;
			}
			$media_update = array(
				'medicalrecord_id' => $medicalrecord_id,
				'lifecycle_state' => 'ready',
				'associated_at' => $date,
				'finalized_at' => $date,
				'failure_code' => null,
				'failed_at' => null,
			);
			$this->db
				->where('media_id', (int) $locked_visit_media->media_id)
				->where('lifecycle_state', 'pending')
				->update('consultation_visit_media', $media_update);
			if ($this->db->affected_rows() !== 1) {
				$this->last_failure_code = 'visit_proof_finalize_failed';
				$this->db->trans_rollback();
				return false;
			}
		}
		if (function_exists('doclinc_realtime_requests_enabled') && doclinc_realtime_requests_enabled()) {
			$puskesmas_code = $this->request_assigned_puskesmas_code($request);
			$audiences = array('user:' . (int) $request->user_id);
			$handler_id = $this->config->item('care_team_workflow_enabled') === true
				? (isset($request->visit_performer_user_id) ? (int) $request->visit_performer_user_id : 0)
				: (isset($request->assigned_nakes_user_id) ? (int) $request->assigned_nakes_user_id : 0);
			if ($handler_id > 0) { $audiences[] = 'user:' . $handler_id; }
			$responsible_id = isset($request->responsible_doctor_user_id) ? (int) $request->responsible_doctor_user_id : 0;
			if ($responsible_id > 0) { $audiences[] = 'user:' . $responsible_id; }
			if ($puskesmas_code !== '') { $audiences[] = 'puskesmas:' . $puskesmas_code . ':ops'; }
			$notification = array(
				'recipient_user_id' => (int) $request->user_id, 'recipient_role' => 'warga',
				'actor_user_id' => (int) $user, 'event_type' => 'consultation_completed',
				'entity_type' => 'request', 'entity_id' => (string) $request_id,
				'title' => 'Konsultasi selesai', 'message' => 'Hasil konsultasi Anda sudah tersedia.',
				'is_read' => 0, 'created_at' => $date,
			);
			if ($puskesmas_code === '' || !doclinc_request_realtime_delivery($this->db)->deliver(
				'completed', $request_id, $request_id, $audiences, array($puskesmas_code), array($notification)
			)) {
				$this->db->trans_rollback();
				return false;
			}
		}

		if ($this->db->trans_status() === false) {
			$this->last_failure_code = 'transaction_failed';
			$this->db->trans_rollback();
			return false;
		}

		$this->db->trans_commit();
		return true;
	}

	public function last_failure_code()
	{
		return $this->last_failure_code;
	}

	private function validate_and_record_visit_proof($request, $user_id, $visit_proof, $file_path)
	{
		if (!is_array($visit_proof) || empty($visit_proof['media_id']) || empty($visit_proof['location'])) {
			$this->last_failure_code = 'visit_proof_missing';
			return false;
		}
		$required_media_fields = array(
			'media_id', 'request_id', 'uploaded_by_user_id', 'media_type', 'storage_key',
			'mime_type', 'size_bytes', 'sha256', 'lifecycle_state',
		);
		if (!$this->db->table_exists('consultation_visit_media')) {
			$this->last_failure_code = 'visit_proof_schema_unavailable';
			return false;
		}
		foreach ($required_media_fields as $field) {
			if (!$this->db->field_exists($field, 'consultation_visit_media')) {
				$this->last_failure_code = 'visit_proof_schema_unavailable';
				return false;
			}
		}

		$status = isset($request->visit_status) && function_exists('doclinc_normalize_visit_status')
			? doclinc_normalize_visit_status($request->visit_status)
			: strtolower(trim((string) ($request->visit_status ?? '')));
		if (!in_array($status, array('arrived', 'in_service', 'completed'), true)) {
			$this->last_failure_code = 'visit_status_invalid';
			return false;
		}

		$location_input = $visit_proof['location'];
		$location = function_exists('doclinc_visit_proof_location_input')
			? doclinc_visit_proof_location_input(
				$location_input['latitude'] ?? null,
				$location_input['longitude'] ?? null,
				$location_input['accuracy_m'] ?? null,
				$location_input['captured_at_ms'] ?? null
			)
			: array('valid' => false);
		if (empty($location['valid'])) {
			$this->last_failure_code = 'visit_proof_invalid';
			return false;
		}

		$patient_latitude = isset($request->patient_latitude) && is_numeric($request->patient_latitude)
			? (float) $request->patient_latitude
			: (isset($request->lattitude) && is_numeric($request->lattitude) ? (float) $request->lattitude : null);
		$patient_longitude = isset($request->patient_longitude) && is_numeric($request->patient_longitude)
			? (float) $request->patient_longitude
			: (isset($request->longitude) && is_numeric($request->longitude) ? (float) $request->longitude : null);
		$distance_m = function_exists('doclinc_haversine_distance_m')
			? doclinc_haversine_distance_m($location['latitude'], $location['longitude'], $patient_latitude, $patient_longitude)
			: null;
		$radius_m = function_exists('doclinc_visit_arrival_radius_m') ? (float) doclinc_visit_arrival_radius_m() : 75.0;
		if ($distance_m === null || $distance_m > $radius_m) {
			$this->last_failure_code = 'visit_location_outside_radius';
			return false;
		}

		$media = $this->db->query(
			'SELECT * FROM ' . $this->db->dbprefix('consultation_visit_media') . ' WHERE media_id = ? FOR UPDATE',
			array((int) $visit_proof['media_id'])
		)->row();
		$expected_file_name = isset($visit_proof['file_name']) ? basename((string) $visit_proof['file_name']) : '';
		$expected_storage_key = isset($visit_proof['storage_key']) ? (string) $visit_proof['storage_key'] : '';
		$actual_extension = strtolower(pathinfo($expected_file_name, PATHINFO_EXTENSION));
		if (!$media
			|| (int) $media->request_id !== (int) $request->request_id
			|| (int) $media->uploaded_by_user_id !== (int) $user_id
			|| (string) $media->media_type !== 'image'
			|| (string) $media->lifecycle_state !== 'pending'
			|| !preg_match('/^[a-f0-9]{64}$/', $expected_storage_key)
			|| !hash_equals((string) $media->storage_key, $expected_storage_key)
			|| pathinfo($expected_file_name, PATHINFO_FILENAME) !== $expected_storage_key
			|| !in_array($actual_extension, array('jpg', 'jpeg', 'png', 'webp'), true)
			|| basename((string) $file_path) !== $expected_file_name) {
			$this->last_failure_code = 'visit_proof_invalid';
			return false;
		}
		$full_path = isset($visit_proof['full_path']) ? (string) $visit_proof['full_path'] : '';
		$file_hash = $full_path !== '' && is_file($full_path) ? @hash_file('sha256', $full_path) : '';
		if (!is_string($file_hash) || !preg_match('/^[a-f0-9]{64}$/', $file_hash)
			|| !hash_equals((string) $media->sha256, $file_hash)
			|| (int) @filesize($full_path) !== (int) $media->size_bytes) {
			$this->last_failure_code = 'visit_proof_invalid';
			return false;
		}

		$required_location_fields = array(
			'location_update_id', 'request_id', 'nakes_user_id', 'latitude', 'longitude',
			'accuracy_m', 'client_sequence', 'idempotency_key', 'captured_at', 'received_at',
		);
		if (!$this->db->table_exists('visit_location_updates')) {
			$this->last_failure_code = 'visit_proof_schema_unavailable';
			return false;
		}
		foreach ($required_location_fields as $field) {
			if (!$this->db->field_exists($field, 'visit_location_updates')) {
				$this->last_failure_code = 'visit_proof_schema_unavailable';
				return false;
			}
		}
		if (!$this->db->table_exists('medicalrecords') || !$this->db->field_exists('record_id', 'medicalrecords')) {
			$this->last_failure_code = 'visit_proof_schema_unavailable';
			return false;
		}
		$idempotency_key = hash('sha256', implode('|', array(
			'visit-proof-location-v1',
			(int) $request->request_id,
			(int) $user_id,
			(int) $visit_proof['media_id'],
			(int) $location['client_sequence'],
		)));
		$location_row = array(
			'request_id' => (int) $request->request_id,
			'nakes_user_id' => (int) $user_id,
			'latitude' => $location['latitude'],
			'longitude' => $location['longitude'],
			'accuracy_m' => $location['accuracy_m'],
			'heading_degrees' => null,
			'speed_mps' => null,
			'client_sequence' => (int) $location['client_sequence'],
			'idempotency_key' => $idempotency_key,
			'captured_at' => $location['captured_at'],
			'received_at' => date('Y-m-d H:i:s'),
		);
		if (!$this->db->insert('visit_location_updates', $location_row)) {
			$this->last_failure_code = 'visit_location_write_failed';
			return false;
		}

		return $media;
	}

	private function consultation_mode_from_kriteria($kriteria)
	{
		$normalized = strtolower(trim((string) $kriteria));
		if ($normalized === '1' || $normalized === 'kunjungan nakes') {
			return 'visit';
		}
		if ($normalized === '0' || $normalized === 'selesai konsultasi') {
			return 'non_visit';
		}

		return null;
	}

	private function build_treatment_summary($kriteria, $rujukan, $file_path, $terapi)
	{
		$parts = [];
		if (!empty($kriteria)) {
			$parts[] = 'Kriteria: ' . $kriteria;
		}
		if (!empty($rujukan)) {
			$parts[] = 'Rujukan: ' . $rujukan;
		}
		if (!empty($file_path)) {
			$parts[] = 'Foto: ' . $file_path;
		}
		if (!empty($terapi)) {
			$terapi_parts = [];
			foreach ($terapi as $t) {
				$terapi_parts[] = trim(($t['terapi'] ?? '') . ' ' . ($t['jumlah'] ?? '') . ' ' . ($t['cara'] ?? '') . ' ' . ($t['keterangan'] ?? ''));
			}
			$parts[] = 'Terapi: ' . implode('; ', array_filter($terapi_parts));
		}

		return implode("\n", $parts);
	}


	public function getICD($term = "")
	{
		if (!$this->db->table_exists('keluhan')) {
			return [];
		}

		// $query = $this->db->query("SELECT id_keluhan, nama_keluhan FROM keluhan");
		// return $query->result();
		$this->db->select('id_keluhan, nama_keluhan');
		$this->db->like('nama_keluhan', $term);
		$this->db->or_like('id_keluhan', $term);
		$query = $this->db->get('keluhan');
		return $query->result();
	}
}
