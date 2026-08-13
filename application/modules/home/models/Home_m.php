<?php
class Home_m extends MX_Controller
{
	protected $db;

	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	private function selectDoctorFields()
	{
		$this->db->select('users.*');

		if (!$this->db->field_exists('foto', 'users')) {
			$this->db->select('NULL AS foto', FALSE);
		}
	}

	public function get_master_gejala_keluhan_options()
	{
		if (!$this->db->table_exists('keluhan')) {
			return array();
		}

		$select = array();
		$select[] = $this->db->field_exists('id_keluhan', 'keluhan') ? 'id_keluhan' : 'NULL AS id_keluhan';
		$select[] = $this->db->field_exists('nama_keluhan', 'keluhan') ? 'nama_keluhan' : 'NULL AS nama_keluhan';
		$select[] = $this->db->field_exists('status', 'keluhan') ? 'status' : 'NULL AS status';

		$this->db->select(implode(', ', $select), FALSE);
		$this->db->from('keluhan');
		if ($this->db->field_exists('status', 'keluhan')) {
			$this->db->where('status', 'Aktif');
		}
		if ($this->db->field_exists('nama_keluhan', 'keluhan')) {
			$this->db->where("TRIM(nama_keluhan) <> ''", null, false);
			$this->db->order_by('nama_keluhan', 'ASC');
		}

		return $this->db->get()->result();
	}

	private function applyDoctorFilters($kode_pkm)
	{
		$this->db->where('users.role', 'dokter');

		if ($this->db->field_exists('status', 'users')) {
			$this->db->where('users.status', 'aktif');
		}

		if (!empty($kode_pkm) && $this->db->field_exists('remark', 'users')) {
			$this->db->where('users.remark', $kode_pkm);
		}
	}

	private function joinDoctorLocations()
	{
		if (!$this->db->table_exists('locations') || !$this->db->field_exists('id_user', 'locations')) {
			$this->db->select('COALESCE(users.updated_at, users.created_at, NOW()) AS create_date, NULL AS latitude, NULL AS longitude, NULL AS location', FALSE);
			return;
		}

		foreach ($this->db->list_fields('locations') as $field) {
			if ($field !== 'create_date') {
				$this->db->select('locations.' . $field);
			}
		}

		$location_join = 'users.userId = locations.id_user';
		if ($this->db->field_exists('create_date', 'locations')) {
			$location_join .= ' AND DATE(locations.create_date) = CURDATE()';
			$this->db->select('COALESCE(locations.create_date, users.updated_at, users.created_at, NOW()) AS create_date', FALSE);
		} else {
			$this->db->select('COALESCE(users.updated_at, users.created_at, NOW()) AS create_date', FALSE);
		}

		$this->db->join('locations', $location_join, 'left', FALSE);
	}

	private function joinLatestPendingRequest()
	{
		$can_join_requests = $this->db->table_exists('requests')
			&& $this->db->field_exists('request_id', 'requests')
			&& $this->db->field_exists('dokter_id', 'requests')
			&& $this->db->field_exists('request_status', 'requests');

		if (!$can_join_requests) {
			$this->db->select('NULL AS request_id, NULL AS user_id, NULL AS dokter_id, NULL AS request_status, NULL AS date', FALSE);
			return;
		}

		$request_select = array(
			'requests.request_id AS request_id',
			$this->db->field_exists('user_id', 'requests') ? 'requests.user_id AS user_id' : 'NULL AS user_id',
			'requests.dokter_id AS dokter_id',
			'requests.request_status AS request_status',
		);

		if ($this->db->field_exists('date', 'requests')) {
			$request_select[] = 'requests.date AS date';
		} elseif ($this->db->field_exists('created_at', 'requests')) {
			$request_select[] = 'DATE(requests.created_at) AS date';
		} else {
			$request_select[] = 'NULL AS date';
		}

		$this->db->select(implode(', ', $request_select), FALSE);
		$this->db->join(
			'requests',
			"requests.request_id = (
				SELECT MAX(r2.request_id)
				FROM requests r2
				WHERE r2.dokter_id = users.userId
				AND r2.request_status = 'Pending'
			)",
			'left',
			FALSE
		);
	}

	public function getAllDataLocations($nama)
	{
		// 		$query = $this->db->query("SELECT latitude, longitude, location, name FROM locations WHERE name='$nama' AND date(locations.create_date)=date(now())");
		// 		return $query->result_array();

		$this->db->select('locations.*, users.*');
		$this->db->from('locations');
		$this->db->join('users', 'locations.id_user = users.userId');
		if (!empty($nama)) { // Pastikan array tidak kosong
			$this->db->where_in('users.username', $nama); // Filter berdasarkan nama
		}
		$this->db->where('DATE(locations.create_date)', 'CURDATE()', false); // Filter berdasarkan tanggal hari ini
		$this->db->group_by('locations.id_user');
		// $this->db->order_by('locations.id_user', 'DESC'); // Urutkan data berdasarkan 'location_id' secara descending
		$query = $this->db->get(); // Ambil data dari tabel 'locations'
		return $query->result_array(); // Kembalikan hasil sebagai array
	}

	public function getAllDataRequestss($user_id, $statuses)
	{
		$this->db->select("requests.*, dokter_user.nama AS nama_dokter");
		$this->db->from("requests");
		$this->db->join("users AS dokter_user", "requests.dokter_id=dokter_user.userId", "left");
		$this->db->where("requests.user_id", $user_id);
		$this->db->where_in("requests.request_status", $statuses);
		return $this->db->get()->result();
	}

	public function getAllDataRequests($user_id, $statuses)
	{
		// Load library encryption
		$CI = &get_instance();
		$CI->load->library('encryption');

		$request_date_select = $this->db->field_exists('date', 'requests')
			? 'requests.date AS date'
			: 'DATE(requests.created_at) AS date';

		$care_team_ready = $this->care_team_display_ready();
		$handler_name_parts = array();
		if ($care_team_ready) {
			$handler_name_parts[] = 'responsible_doctor_user.nama';
			$handler_name_parts[] = 'visit_performer_user.nama';
		} else {
			if ($this->db->field_exists('assigned_nakes_user_id', 'requests')) {
				$handler_name_parts[] = 'assigned_nakes_user.nama';
			}
			if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
				$handler_name_parts[] = 'accepted_nakes_user.nama';
			}
		}
		$explicit_handler_name_expr = !empty($handler_name_parts) ? 'COALESCE(' . implode(', ', $handler_name_parts) . ')' : 'NULL';
		$legacy_handler_name_expr = 'COALESCE(' . implode(', ', array_merge($handler_name_parts, array('dokter_user.nama'))) . ')';
		$handler_name_expr = $care_team_ready
			? $explicit_handler_name_expr
			: "CASE WHEN requests.request_status = 'Pending' THEN {$explicit_handler_name_expr} ELSE {$legacy_handler_name_expr} END";

		// Query database
		$this->db->select("requests.*, {$request_date_select}, COALESCE({$handler_name_expr}, 'Nakes') AS nama_dokter, {$handler_name_expr} AS handling_nakes_name", FALSE);
		$this->db->select($care_team_ready ? 'responsible_doctor_user.nama AS responsible_doctor_name, visit_performer_user.nama AS visit_performer_name' : 'NULL AS responsible_doctor_name, NULL AS visit_performer_name', FALSE);
		$this->db->from('requests');
		$this->db->join('users AS dokter_user', 'requests.dokter_id = dokter_user.userId', 'left');
		if ($care_team_ready) {
			$this->db->join('users AS responsible_doctor_user', 'requests.responsible_doctor_user_id = responsible_doctor_user.userId', 'left');
			$this->db->join('users AS visit_performer_user', 'requests.visit_performer_user_id = visit_performer_user.userId', 'left');
		}
		if ($this->db->field_exists('assigned_nakes_user_id', 'requests')) {
			$this->db->join('users AS assigned_nakes_user', 'requests.assigned_nakes_user_id = assigned_nakes_user.userId', 'left');
		}
		if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
			$this->db->join('users AS accepted_nakes_user', 'requests.accepted_by_user_id = accepted_nakes_user.userId', 'left');
		}
		$this->db->where('requests.user_id', $user_id);
		$this->db->where_in('requests.request_status', $statuses);
		$this->db->order_by('requests.request_id', 'DESC');
		$result = $this->db->get()->result();

		// Dekripsi field request_description
		foreach ($result as $row) {
			try {
				$row->request_description = $CI->encryption->decrypt(base64_decode($row->request_description));
			} catch (Exception $e) {
				$row->request_description = '[Keluhan tidak dapat didekripsi]';
			}
		}
		$this->decorate_warga_visit_display($result);

		return $result;
	}

	public function getAllDataRequestsCompleted($user_id)
	{
		if (!$this->db->table_exists('konsultasi') && !$this->db->table_exists('medicalrecords')) {
			return [];
		}

		// Load library encryption
		$CI = &get_instance();
		$CI->load->library('encryption');
		$request_date_select = $this->db->field_exists('date', 'requests')
			? 'requests.date AS date'
			: ($this->db->field_exists('updated_at', 'requests') ? 'DATE(requests.updated_at) AS date' : 'DATE(requests.created_at) AS date');

		$care_team_ready = $this->care_team_display_ready();
		$handler_name_parts = array();
		if ($care_team_ready) {
			$handler_name_parts[] = 'responsible_doctor_user.nama';
			$handler_name_parts[] = 'visit_performer_user.nama';
		} else {
			if ($this->db->field_exists('assigned_nakes_user_id', 'requests')) {
				$handler_name_parts[] = 'assigned_nakes_user.nama';
			}
			if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
				$handler_name_parts[] = 'accepted_nakes_user.nama';
			}
			$handler_name_parts[] = 'dokter_user.nama';
		}
		$handler_name_expr = 'COALESCE(' . implode(', ', $handler_name_parts) . ", 'Dokter')";

		// Ambil data utama (hasil konsultasi dan user tanpa join terapi)
		$this->db->select("requests.*, {$request_date_select}, users.nama, {$handler_name_expr} AS nama_dokter, {$handler_name_expr} AS handling_nakes_name", FALSE);
		$this->db->select($care_team_ready ? 'responsible_doctor_user.nama AS responsible_doctor_name, visit_performer_user.nama AS visit_performer_name' : 'NULL AS responsible_doctor_name, NULL AS visit_performer_name', FALSE);
		$this->db->from('requests');
		$this->db->join('users', 'requests.user_id = users.userId');
		$this->db->join('users AS dokter_user', 'requests.dokter_id = dokter_user.userId', 'left');
		if ($care_team_ready) {
			$this->db->join('users AS responsible_doctor_user', 'requests.responsible_doctor_user_id = responsible_doctor_user.userId', 'left');
			$this->db->join('users AS visit_performer_user', 'requests.visit_performer_user_id = visit_performer_user.userId', 'left');
		}
		if ($this->db->field_exists('assigned_nakes_user_id', 'requests')) {
			$this->db->join('users AS assigned_nakes_user', 'requests.assigned_nakes_user_id = assigned_nakes_user.userId', 'left');
		}
		if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
			$this->db->join('users AS accepted_nakes_user', 'requests.accepted_by_user_id = accepted_nakes_user.userId', 'left');
		}
		if ($this->db->table_exists('medicalrecords')) {
			$this->db
				->select('medicalrecords.record_id AS medical_record_id')
				->select('medicalrecords.diagnosis AS diagnosa')
				->select('medicalrecords.recommendations AS saran')
				->select('medicalrecords.diagnosis AS diagnosis')
				->select('medicalrecords.treatment AS treatment')
				->select('medicalrecords.recommendations AS recommendations')
				->select('medicalrecords.created_at AS result_created_at')
				->join('(SELECT request_id, MAX(record_id) AS record_id FROM medicalrecords GROUP BY request_id) latest_medicalrecords', 'latest_medicalrecords.request_id = requests.request_id', 'left', FALSE)
				->join('medicalrecords', 'medicalrecords.record_id = latest_medicalrecords.record_id', 'left');
			$this->db->select($this->db->field_exists('anamnesis', 'medicalrecords') ? 'medicalrecords.anamnesis AS anamnesis' : 'NULL AS anamnesis', FALSE);
			if ($this->db->table_exists('medicalrecord_diagnoses')) {
				$this->db->select("COALESCE(NULLIF((SELECT GROUP_CONCAT(mrd.display_text ORDER BY mrd.position SEPARATOR '\\n') FROM " . $this->db->dbprefix('medicalrecord_diagnoses') . " mrd WHERE mrd.medicalrecord_id = medicalrecords.record_id AND mrd.position BETWEEN 1 AND 3), ''), medicalrecords.diagnosis) AS diagnoses_display", FALSE);
			} else {
				$this->db->select('medicalrecords.diagnosis AS diagnoses_display', FALSE);
			}
			if ($this->db->table_exists('konsultasi')) {
				$this->db->select('(SELECT k.konsul_id FROM konsultasi k WHERE k.request_id = requests.request_id ORDER BY k.konsul_id DESC LIMIT 1) AS konsul_id', FALSE);
			} else {
				$this->db->select('NULL AS konsul_id', FALSE);
			}
		} else {
			$this->db
				->select('konsultasi.*')
				->join('konsultasi', 'requests.request_id = konsultasi.request_id', 'left');
			foreach (['diagnosa', 'saran', 'diagnosis', 'treatment', 'recommendations'] as $field) {
				if (!$this->db->field_exists($field, 'konsultasi')) {
					$this->db->select("NULL AS {$field}", FALSE);
				}
			}
			$this->db->select('NULL AS anamnesis', FALSE);
		}
		$this->db->where('requests.request_status', 'Completed');
		$this->db->where('requests.user_id', $user_id);
		$this->db->order_by('requests.request_id', 'DESC');
		$query = $this->db->get();
		$hasil = $query->result();

		// Ambil terapi untuk semua konsultasi yang ditemukan
		foreach ($hasil as &$row) {
			$row->terapi_list = $this->db->table_exists('konsultasi') && !empty($row->konsul_id) ? $this->getTerapiByKonsulId($row->konsul_id) : [];

			try {
				$row->request_description = $CI->encryption->decrypt(base64_decode($row->request_description));
			} catch (Exception $e) {
				$row->request_description = 'Detail keluhan belum tersedia.';
			}
		}
		$this->decorate_warga_visit_display($hasil);

		return $hasil;
	}

	public function warga_visit_status_label($status)
	{
		$raw_status = strtolower(trim((string) $status));
		$status = function_exists('doclinc_normalize_visit_status') ? doclinc_normalize_visit_status($status) : $raw_status;
		if ($raw_status !== '' && $status === '') {
			return 'Status belum tersedia';
		}
		$status = $status !== '' ? $status : 'not_started';
		$labels = array(
			'not_started' => 'Belum dimulai',
			'en_route' => 'Dalam perjalanan',
			'arrived' => 'Sudah tiba',
			'in_service' => 'Sedang ditangani',
			'completed' => 'Selesai',
		);

		return isset($labels[$status]) ? $labels[$status] : 'Status belum tersedia';
	}

	public function get_warga_visit_timeline($request_id, $request = null)
	{
		$request_id = (int) $request_id;
		if ($request_id < 1) {
			return array();
		}

		$event_labels = array(
			'request_accepted' => 'Diterima',
			'visit_started' => 'Dalam perjalanan',
			'visit_arrived' => 'Sudah tiba',
			'visit_in_service' => 'Sedang ditangani',
			'visit_completed' => 'Selesai',
		);

		$timeline = array();
		if ($this->warga_request_event_table_ready()) {
			$events = $this->db
				->select('event_type, created_at')
				->from('request_events')
				->where('request_id', $request_id)
				->where_in('event_type', array_keys($event_labels))
				->order_by('created_at', 'ASC')
				->get()
				->result();
			foreach ($events as $event) {
				$event_type = isset($event->event_type) ? (string) $event->event_type : '';
				if (!isset($event_labels[$event_type])) {
					continue;
				}
				$timeline[] = array(
					'label' => $event_labels[$event_type],
					'time' => isset($event->created_at) ? $event->created_at : '',
					'active' => true,
				);
			}
		}

		if (!empty($timeline) || !$request) {
			return $timeline;
		}

		$current_status = isset($request->visit_status) ? $this->warga_normalize_visit_status($request->visit_status) : 'not_started';
		$status_order = array('not_started' => 0, 'en_route' => 1, 'arrived' => 2, 'in_service' => 3, 'completed' => 4);
		$fallback_steps = array(
			array('status' => 'en_route', 'label' => 'Dalam perjalanan', 'field' => 'visit_started_at'),
			array('status' => 'arrived', 'label' => 'Sudah tiba', 'field' => 'visit_arrived_at'),
			array('status' => 'in_service', 'label' => 'Sedang ditangani', 'field' => 'visit_in_service_at'),
			array('status' => 'completed', 'label' => 'Selesai', 'field' => 'visit_completed_at'),
		);

		foreach ($fallback_steps as $step) {
			$is_active = isset($status_order[$current_status], $status_order[$step['status']]) && $status_order[$current_status] >= $status_order[$step['status']];
			$time = isset($request->{$step['field']}) ? $request->{$step['field']} : '';
			if (!$is_active && empty($time)) {
				continue;
			}
			$timeline[] = array(
				'label' => $step['label'],
				'time' => $time,
				'active' => $is_active,
			);
		}

		return $timeline;
	}

	private function decorate_warga_visit_display(&$requests)
	{
		if (empty($requests) || !is_array($requests)) {
			return;
		}

		foreach ($requests as $row) {
			$request_status = isset($row->request_status) ? trim((string) $row->request_status) : '';
			$consultation_mode = isset($row->consultation_mode) ? strtolower(trim((string) $row->consultation_mode)) : '';
			$status = isset($row->visit_status) ? $this->warga_normalize_visit_status($row->visit_status) : 'not_started';
			$is_visit = $consultation_mode === 'visit';
			$row->warga_visit_status = $status;
			$row->warga_is_visit = $is_visit;
			$row->warga_visit_is_active = $is_visit && $request_status === 'Accepted' && $status !== 'completed';
			$row->warga_visit_location_available = $row->warga_visit_is_active
				&& in_array($status, array('en_route', 'arrived', 'in_service'), true);
			$row->warga_visit_status_label = $is_visit ? $this->warga_visit_status_label($status) : '';
			$row->warga_top_status_label = $this->warga_top_status_label($request_status, $status, $consultation_mode);
			$row->warga_visit_updated_at = $is_visit ? $this->warga_visit_updated_at($row, $status) : '';
			$row->warga_visit_timeline = $is_visit
				? $this->get_warga_visit_timeline(isset($row->request_id) ? (int) $row->request_id : 0, $row)
				: array();
		}
		$this->decorate_warga_pic_display($requests);
	}

	public function warga_top_status_label($request_status, $visit_status = null, $consultation_mode = null)
	{
		$request_status = trim((string) $request_status);
		if ($request_status === 'Completed') {
			return 'Konsultasi selesai';
		}
		if ($request_status === 'Cancelled') {
			return 'Dibatalkan';
		}
		$consultation_mode = strtolower(trim((string) $consultation_mode));
		$visit_status = $this->warga_normalize_visit_status($visit_status);
		if ($consultation_mode === 'visit' && $visit_status !== 'not_started') {
			return $this->warga_visit_status_label($visit_status);
		}

		if ($request_status === 'Pending') {
			return 'Menunggu Puskesmas';
		}
		if ($request_status === 'Accepted') {
			return strtolower(trim((string) $consultation_mode)) === 'non_visit' ? 'Konsultasi tanpa kunjungan' : 'Sedang ditangani';
		}
		if ($request_status === 'in_service') {
			return 'Sedang ditangani';
		}

		return 'Status belum tersedia';
	}

	public function get_warga_pic_assignment($request_id)
	{
		$map = $this->get_warga_pic_assignments_by_request_ids(array($request_id));
		$request_id = (int) $request_id;
		return isset($map[$request_id]) ? $map[$request_id] : null;
	}

	private function decorate_warga_pic_display(&$requests)
	{
		$request_ids = array();
		foreach ($requests as $row) {
			if (isset($row->request_id) && (int) $row->request_id > 0) {
				$request_ids[] = (int) $row->request_id;
			}
		}

		$assignment_map = $this->get_warga_pic_assignments_by_request_ids($request_ids);
		foreach ($requests as $row) {
			$request_id = isset($row->request_id) ? (int) $row->request_id : 0;
			$assignment = $request_id > 0 && isset($assignment_map[$request_id]) ? $assignment_map[$request_id] : null;
			$this->apply_warga_pic_assignment($row, $assignment);
		}
	}

	private function get_warga_pic_assignments_by_request_ids($request_ids)
	{
		if (!$this->warga_pic_assignment_table_ready() || !is_array($request_ids)) {
			return array();
		}

		$ids = array();
		foreach ($request_ids as $request_id) {
			$request_id = (int) $request_id;
			if ($request_id > 0) {
				$ids[$request_id] = $request_id;
			}
		}
		if (empty($ids)) {
			return array();
		}

		$select = array(
			'rsa.assignment_id',
			'rsa.request_id',
			'rsa.staff_id',
			'rsa.assigned_at',
			'ps.nama AS staff_nama',
			$this->db->field_exists('profesi', 'puskesmas_staff') ? 'ps.profesi AS staff_profesi' : 'NULL AS staff_profesi',
			$this->db->field_exists('no_hp', 'puskesmas_staff') ? 'ps.no_hp AS staff_no_hp' : 'NULL AS staff_no_hp',
		);

		$rows = $this->db
			->select(implode(', ', $select), FALSE)
			->from('request_staff_assignments AS rsa')
			->join('puskesmas_staff AS ps', 'ps.staff_id = rsa.staff_id', 'left')
			->where('rsa.status', 'aktif')
			->where_in('rsa.request_id', array_values($ids))
			->order_by('rsa.assigned_at', 'DESC')
			->order_by('rsa.assignment_id', 'DESC')
			->get()
			->result();

		$map = array();
		foreach ($rows as $row) {
			$request_id = isset($row->request_id) ? (int) $row->request_id : 0;
			if ($request_id > 0 && !isset($map[$request_id])) {
				$map[$request_id] = $row;
			}
		}

		return $map;
	}

	private function apply_warga_pic_assignment($row, $assignment)
	{
		$pic_name = $assignment && !empty($assignment->staff_nama) ? trim((string) $assignment->staff_nama) : '';
		$pic_profesi = $assignment && !empty($assignment->staff_profesi) ? trim((string) $assignment->staff_profesi) : '';
		$pic_no_hp = $assignment && !empty($assignment->staff_no_hp) ? trim((string) $assignment->staff_no_hp) : '';
		$row->assigned_pic_name = $pic_name;
		$row->assigned_pic_profesi = $pic_profesi;
		$row->assigned_pic_no_hp = $pic_no_hp;
		$row->assigned_pic_label = $pic_name !== ''
			? 'PIC layanan: ' . $pic_name . ($pic_profesi !== '' ? ' - ' . $pic_profesi : '')
			: 'PIC layanan belum ditentukan';
	}

	private function warga_pic_assignment_table_ready()
	{
		if (!$this->db->table_exists('request_staff_assignments') || !$this->db->table_exists('puskesmas_staff')) {
			return false;
		}

		foreach (array('assignment_id', 'request_id', 'staff_id', 'status', 'assigned_at') as $field) {
			if (!$this->db->field_exists($field, 'request_staff_assignments')) {
				return false;
			}
		}

		foreach (array('staff_id', 'nama') as $field) {
			if (!$this->db->field_exists($field, 'puskesmas_staff')) {
				return false;
			}
		}

		return true;
	}

	private function warga_normalize_visit_status($status)
	{
		$normalized = function_exists('doclinc_normalize_visit_status') ? doclinc_normalize_visit_status($status) : strtolower(trim((string) $status));
		return $normalized !== '' ? $normalized : 'not_started';
	}

	private function warga_visit_updated_at($request, $status)
	{
		$status = $this->warga_normalize_visit_status($status);
		$field_map = array(
			'en_route' => 'visit_started_at',
			'arrived' => 'visit_arrived_at',
			'in_service' => 'visit_in_service_at',
			'completed' => 'visit_completed_at',
		);

		if (isset($field_map[$status]) && !empty($request->{$field_map[$status]})) {
			return $request->{$field_map[$status]};
		}
		return !empty($request->updated_at) ? $request->updated_at : '';
	}

	private function warga_request_event_table_ready()
	{
		if (!$this->db->table_exists('request_events')) {
			return false;
		}

		foreach (array('request_id', 'event_type', 'created_at') as $field) {
			if (!$this->db->field_exists($field, 'request_events')) {
				return false;
			}
		}

		return true;
	}

	public function getTerapiByKonsulId($konsul_id)
	{
		if (!$this->db->table_exists('terapi')) {
			return [];
		}

		$this->db->where('konsul_id', $konsul_id);
		return $this->db->get('terapi')->result();
	}

	public function getAllDataDoctors($kode_pkm)
	{
		$this->selectDoctorFields();
		$this->joinDoctorLocations();
		$this->db->from('users');
		$this->applyDoctorFilters($kode_pkm);
		$this->db->group_by('users.userId');

		return $this->db->get()->result_array();
	}


	public function getAllDataDoctor($kode_pkm)
	{
		$this->selectDoctorFields();
		$this->joinDoctorLocations();
		$this->joinLatestPendingRequest();
		$this->db->from('users');
		$this->applyDoctorFilters($kode_pkm);
		$this->db->group_by('users.userId');

		return $this->db->get();
	}

	public function getAllRequestPendingAccept($user_id)
	{
		$request_date_select = $this->db->field_exists('date', 'requests')
			? 'requests.date AS date'
			: 'DATE(requests.created_at) AS date';

		return $this->db
			->select("requests.*, {$request_date_select}", FALSE)
			->where('user_id', $user_id)
			->get('requests');
	}

	public function getAllRequestJumlah()
	{
		if ($this->db->field_exists('date', 'requests')) {
			return $this->db->query("SELECT COUNT(*) AS jumlah, NULL AS user_id FROM requests WHERE date=CURDATE()");
		}

		return $this->db->query("SELECT COUNT(*) AS jumlah, NULL AS user_id FROM requests WHERE DATE(created_at)=CURDATE()");
	}

	public function get_data_feeds()
	{
		if (!$this->db->table_exists('feeds')) {
			return $this->db->query("SELECT NULL AS feedId, NULL AS gambar, NULL AS status WHERE 1=0");
		}

		return $this->db->query("SELECT * FROM feeds WHERE status='aktif' ORDER BY feedId DESC");
	}

	public function get_data_profile($user_id)
	{
		$select = ['users.*'];
		foreach (['tgl', 'gender', 'no_hp', 'alamat', 'foto'] as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				$select[] = 'NULL AS ' . $field;
			}
		}

		if ($this->db->field_exists('tgl', 'users')) {
			$select[] = 'TIMESTAMPDIFF(YEAR, tgl, CURDATE()) AS usia';
		} else {
			$select[] = 'NULL AS usia';
		}

		return $this->db
			->select(implode(', ', $select), FALSE)
			->where('userId', $user_id)
			->get('users');
	}

	public function getDokterRating($idUser)
	{
		return $this->db->where('userId', $idUser)->get('users');
	}

	public function update_profile_photo($user_id, $stored_key)
	{
		$user_id = (int) $user_id;
		$stored_key = trim((string) $stored_key);
		if ($user_id < 1 || $stored_key === '' || !$this->db->field_exists('foto', 'users')) {
			return false;
		}

		$this->db
			->where('userId', $user_id)
			->where('role', 'warga')
			->where('status', 'aktif')
			->update('users', array('foto' => $stored_key));
		return $this->db->affected_rows() === 1;
	}

	public function email_available_for_user($user_id, $email)
	{
		$user_id = (int) $user_id;
		$email = strtolower(trim((string) $email));
		if ($user_id < 1 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || !$this->db->field_exists('email', 'users')) {
			return false;
		}
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$query = $this->db
			->select('userId')
			->where('email', $email)
			->where('userId !=', $user_id)
			->limit(1)
			->get('users');
		$this->db->db_debug = $db_debug;
		return $query !== false && !$query->row();
	}

	public function update_profile($user_id, array $data)
	{
		$user_id = (int) $user_id;
		$allowed = array('nama', 'email', 'no_hp', 'tgl', 'gender', 'alamat');
		foreach (array('nik', 'nomor_kk', 'nomor_bpjs_kis') as $identity_field) {
			if ($this->db->field_exists($identity_field, 'users')) {
				$allowed[] = $identity_field;
			}
		}
		foreach (array_keys($data) as $field) {
			if (!in_array($field, $allowed, true) || !$this->db->field_exists($field, 'users')) {
				unset($data[$field]);
			}
		}
		if ($user_id < 1 || count($data) !== count($allowed)) {
			return false;
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$updated = $this->db
			->where('userId', $user_id)
			->where('role', 'warga')
			->where('status', 'aktif')
			->update('users', $data);
		$this->db->db_debug = $db_debug;
		if (!$updated) {
			return false;
		}
		return (bool) $this->db
			->select('userId')
			->where('userId', $user_id)
			->where('role', 'warga')
			->where('status', 'aktif')
			->limit(1)
			->get('users')
			->row();
	}

	public function identity_value_available($user_id, $field, $value)
	{
		$user_id = (int) $user_id;
		$field = (string) $field;
		$value = trim((string) $value);
		if ($user_id < 1 || $value === '' || !in_array($field, array('nik', 'nomor_bpjs_kis'), true)
			|| !$this->db->field_exists($field, 'users')) {
			return false;
		}
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$query = $this->db
			->select('userId')
			->where($field, $value)
			->where('userId !=', $user_id)
			->limit(1)
			->get('users');
		$this->db->db_debug = $db_debug;
		return $query !== false && !$query->row();
	}

	public function get_request_for_pending_edit($request_id, $user_id)
	{
		$request_id = (int) $request_id;
		$user_id = (int) $user_id;
		if ($request_id < 1 || $user_id < 1) {
			return null;
		}

		$select = array(
			'request_id',
			'user_id',
			'request_status',
			$this->db->field_exists('request_description', 'requests') ? 'request_description' : 'NULL AS request_description',
			$this->db->field_exists('location', 'requests') ? 'location' : 'NULL AS location',
			$this->db->field_exists('lattitude', 'requests') ? 'lattitude' : 'NULL AS lattitude',
			$this->db->field_exists('longitude', 'requests') ? 'longitude' : 'NULL AS longitude',
			$this->db->field_exists('patient_latitude', 'requests') ? 'patient_latitude' : 'NULL AS patient_latitude',
			$this->db->field_exists('patient_longitude', 'requests') ? 'patient_longitude' : 'NULL AS patient_longitude',
			$this->db->field_exists('accepted_by_user_id', 'requests') ? 'accepted_by_user_id' : 'NULL AS accepted_by_user_id',
			$this->db->field_exists('assigned_nakes_user_id', 'requests') ? 'assigned_nakes_user_id' : 'NULL AS assigned_nakes_user_id',
			$this->db->field_exists('assigned_nakes_by_user_id', 'requests') ? 'assigned_nakes_by_user_id' : 'NULL AS assigned_nakes_by_user_id',
			$this->db->field_exists('responsible_doctor_user_id', 'requests') ? 'responsible_doctor_user_id' : 'NULL AS responsible_doctor_user_id',
			$this->db->field_exists('visit_performer_user_id', 'requests') ? 'visit_performer_user_id' : 'NULL AS visit_performer_user_id',
			$this->db->field_exists('consultation_mode', 'requests') ? 'consultation_mode' : 'NULL AS consultation_mode',
			$this->db->field_exists('visit_status', 'requests') ? 'visit_status' : 'NULL AS visit_status',
		);

		return $this->db
			->select(implode(', ', $select), FALSE)
			->where('request_id', $request_id)
			->where('user_id', $user_id)
			->get('requests')
			->row();
	}

	public function request_has_processing_assignment($request)
	{
		if (!$request) {
			return true;
		}

		foreach (array('accepted_by_user_id', 'assigned_nakes_user_id', 'assigned_nakes_by_user_id', 'responsible_doctor_user_id', 'visit_performer_user_id') as $field) {
			if (isset($request->{$field}) && (int) $request->{$field} > 0) {
				return true;
			}
		}
		if (!empty($request->consultation_mode) || (!empty($request->visit_status) && $request->visit_status !== 'not_started')) {
			return true;
		}
		foreach (array('request_responsible_doctor_assignments', 'request_visit_performer_assignments') as $table) {
			if ($this->db->table_exists($table) && $this->db->field_exists('request_id', $table)
				&& $this->db->field_exists('status', $table)
				&& $this->db->where('request_id', (int) $request->request_id)->where('status', 'aktif')->count_all_results($table) > 0) {
				return true;
			}
		}

		if (
			!$this->db->table_exists('request_staff_assignments')
			|| !$this->db->field_exists('request_id', 'request_staff_assignments')
			|| !$this->db->field_exists('status', 'request_staff_assignments')
		) {
			return false;
		}

		return $this->db
			->where('request_id', (int) $request->request_id)
			->where('status', 'aktif')
			->count_all_results('request_staff_assignments') > 0;
	}

	public function updateRequestById($id, $user_id = null, $payload = array())
	{
		$id = (int) $id;
		$user_id = (int) ($user_id ?: $this->session->userdata('id'));
		if ($id < 1 || $user_id < 1) {
			return false;
		}

		$data = array();
		if (isset($payload['request_description']) && $payload['request_description'] !== '' && $this->db->field_exists('request_description', 'requests')) {
			$data['request_description'] = $payload['request_description'];
		}
		if (array_key_exists('location', $payload) && $this->db->field_exists('location', 'requests')) {
			$data['location'] = $payload['location'];
		}
		$lat = isset($payload['lat']) ? $payload['lat'] : null;
		$lng = isset($payload['lng']) ? $payload['lng'] : null;
		if ($this->is_valid_latitude($lat) && $this->is_valid_longitude($lng)) {
			if ($this->db->field_exists('lattitude', 'requests')) {
				$data['lattitude'] = $lat;
			}
			if ($this->db->field_exists('longitude', 'requests')) {
				$data['longitude'] = $lng;
			}
			if ($this->db->field_exists('patient_latitude', 'requests')) {
				$data['patient_latitude'] = $lat;
			}
			if ($this->db->field_exists('patient_longitude', 'requests')) {
				$data['patient_longitude'] = $lng;
			}
		}
		if ($this->db->field_exists('updated_at', 'requests')) {
			$data['updated_at'] = date('Y-m-d H:i:s');
		}
		if (empty($data)) {
			return false;
		}

		$this->db->trans_begin();
		$locked = $this->db->query(
			'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? AND user_id = ? FOR UPDATE',
			array($id, $user_id)
		)->row();
		if (!$locked || (string) $locked->request_status !== 'Pending' || $this->request_has_processing_assignment($locked)) {
			$this->db->trans_rollback();
			return false;
		}
		$this->db->where('request_id', $id)->where('user_id', $user_id)->where('request_status', 'Pending')->update('requests', $data);
		if ($this->db->trans_status() === false || $this->db->affected_rows() < 1) {
			$this->db->trans_rollback();
			return false;
		}
		$this->db->trans_commit();
		return true;
	}

	private function care_team_display_ready()
	{
		return $this->config->item('care_team_workflow_enabled') === true
			&& $this->db->field_exists('responsible_doctor_user_id', 'requests')
			&& $this->db->field_exists('visit_performer_user_id', 'requests');
	}

	private function is_valid_latitude($value)
	{
		return is_numeric($value) && (float) $value >= -90 && (float) $value <= 90;
	}

	private function is_valid_longitude($value)
	{
		return is_numeric($value) && (float) $value >= -180 && (float) $value <= 180;
	}

	public function cancel_request($request_id, $user_id)
	{
		$request_id = (int) $request_id;
		$user_id = (int) $user_id;
		if ($request_id < 1 || $user_id < 1) {
			return false;
		}
		if (function_exists('doclinc_realtime_requests_enabled') && doclinc_realtime_requests_enabled()) {
			return $this->cancel_request_with_realtime($request_id, $user_id);
		}

		$data = array('request_status' => 'Cancelled');
		if ($this->db->field_exists('updated_at', 'requests')) {
			$data['updated_at'] = date('Y-m-d H:i:s');
		}

		$this->db
			->where('request_id', $request_id)
			->where('user_id', $user_id)
			->where_in('request_status', array('Pending'))
			->update('requests', $data);

		return $this->db->affected_rows() > 0;
	}

	private function cancel_request_with_realtime($request_id, $user_id)
	{
		if (!$this->db->trans_begin()) { return false; }
		$request = $this->db->query(
			'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
			array($request_id)
		)->row();
		if (!$request || (int) $request->user_id !== $user_id || (string) $request->request_status !== 'Pending') {
			$this->db->trans_rollback();
			return false;
		}
		$puskesmas_code = trim((string) ($request->assigned_puskesmas_code ?? ''));
		if ($puskesmas_code === '' || strtoupper($puskesmas_code) === 'DEFAULT') {
			$this->db->trans_rollback();
			return false;
		}
		$data = array('request_status' => 'Cancelled');
		if ($this->db->field_exists('updated_at', 'requests')) { $data['updated_at'] = date('Y-m-d H:i:s'); }
		$this->db->where('request_id', $request_id)->where('user_id', $user_id)->where('request_status', 'Pending')->update('requests', $data);
		if ($this->db->affected_rows() !== 1) { $this->db->trans_rollback(); return false; }
		$care_team_enabled = $this->config->item('care_team_workflow_enabled') === true;
		$recipient_id = $care_team_enabled ? $this->care_team_command_center_user_id($puskesmas_code) : 0;
		if ($care_team_enabled && $recipient_id < 1) {
			$this->db->trans_rollback();
			return false;
		}
		if (!$care_team_enabled) {
			foreach (array('assigned_nakes_user_id', 'accepted_by_user_id', 'dokter_id') as $field) {
				if (isset($request->{$field}) && (int) $request->{$field} > 0) { $recipient_id = (int) $request->{$field}; break; }
			}
		}
		$notifications = array();
		$audiences = array('user:' . $user_id, 'puskesmas:' . $puskesmas_code . ':ops');
		if ($recipient_id > 0) {
			$audiences[] = 'user:' . $recipient_id;
			$notifications[] = array(
				'recipient_user_id' => $recipient_id, 'recipient_role' => 'dokter',
				'recipient_puskesmas_code' => $puskesmas_code, 'actor_user_id' => $user_id,
				'event_type' => 'request_cancelled', 'entity_type' => 'request', 'entity_id' => (string) $request_id,
				'title' => 'Konsultasi dibatalkan', 'message' => 'Permintaan konsultasi dibatalkan oleh pasien.',
				'is_read' => 0, 'created_at' => date('Y-m-d H:i:s'),
			);
		}
		if (!doclinc_request_realtime_delivery($this->db)->deliver(
			'cancelled', $request_id, $request_id, $audiences, array($puskesmas_code), $notifications
		) || $this->db->trans_status() === false || !$this->db->trans_commit()) {
			$this->db->trans_rollback();
			return false;
		}
		return true;
	}

	private function care_team_command_center_user_id($puskesmas_code)
	{
		if (!$this->db->table_exists('users') || !$this->db->table_exists('m_puskesmas')
			|| !$this->db->table_exists('puskesmas_staff') || !$this->db->field_exists('remark', 'users')) {
			return 0;
		}
		$facility = $this->db->query(
			'SELECT kode_pkm, status FROM ' . $this->db->dbprefix('m_puskesmas') . ' WHERE kode_pkm = ? FOR UPDATE',
			array((string) $puskesmas_code)
		)->row();
		if (!$facility || (string) $facility->status !== 'aktif') {
			return 0;
		}
		$user = $this->db->query(
			'SELECT userId FROM ' . $this->db->dbprefix('users') . ' WHERE role = ? AND status = ? AND TRIM(remark) = ? ORDER BY userId ASC LIMIT 1 FOR UPDATE',
			array('dokter', 'aktif', (string) $puskesmas_code)
		)->row();
		if (!$user) {
			return 0;
		}
		$staff = $this->db->query(
			'SELECT staff_id FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE user_id = ? FOR UPDATE',
			array((int) $user->userId)
		)->result();
		return count($staff) === 0 ? (int) $user->userId : 0;
	}

	public function get_visit_location($request_id, $user_id)
	{
		$request_id = (int) $request_id;
		$user_id = (int) $user_id;
		if ($request_id < 1 || $user_id < 1) {
			return null;
		}

		$select = array(
			'request_id',
			'request_status',
			'user_id',
			$this->db->field_exists('lattitude', 'requests') ? 'lattitude' : 'NULL AS lattitude',
			$this->db->field_exists('longitude', 'requests') ? 'longitude' : 'NULL AS longitude',
			$this->db->field_exists('lattitude_dokter', 'requests') ? 'lattitude_dokter' : 'NULL AS lattitude_dokter',
			$this->db->field_exists('longitude_dokter', 'requests') ? 'longitude_dokter' : 'NULL AS longitude_dokter',
			$this->db->field_exists('patient_latitude', 'requests') ? 'patient_latitude' : 'NULL AS patient_latitude',
			$this->db->field_exists('patient_longitude', 'requests') ? 'patient_longitude' : 'NULL AS patient_longitude',
			$this->db->field_exists('updated_at', 'requests') ? 'updated_at' : 'NULL AS updated_at',
			$this->db->field_exists('visit_status', 'requests') ? 'visit_status' : 'NULL AS visit_status',
			$this->db->field_exists('consultation_mode', 'requests') ? 'consultation_mode' : 'NULL AS consultation_mode',
			$this->db->field_exists('visit_started_at', 'requests') ? 'visit_started_at' : 'NULL AS visit_started_at',
			$this->db->field_exists('visit_arrived_at', 'requests') ? 'visit_arrived_at' : 'NULL AS visit_arrived_at',
			$this->db->field_exists('visit_in_service_at', 'requests') ? 'visit_in_service_at' : 'NULL AS visit_in_service_at',
			$this->db->field_exists('visit_completed_at', 'requests') ? 'visit_completed_at' : 'NULL AS visit_completed_at',
			$this->db->field_exists('nakes_accuracy', 'requests') ? 'nakes_accuracy' : 'NULL AS nakes_accuracy',
			$this->db->field_exists('nakes_heading', 'requests') ? 'nakes_heading' : 'NULL AS nakes_heading',
			$this->db->field_exists('nakes_speed', 'requests') ? 'nakes_speed' : 'NULL AS nakes_speed',
		);

		return $this->db
			->select(implode(', ', $select), FALSE)
			->where('request_id', $request_id)
			->where('user_id', $user_id)
			->where('request_status', 'Accepted')
			->get('requests')
			->row();
	}

	public function deleteRequestById($id)
	{
		$id = (int) $id;
		if ($id < 1) {
			return false;
		}
		$this->db->where('request_id', $id);
		$this->db->where('user_id', $this->session->userdata('id')); // Pastikan user_id sesuai dengan session
		$this->db->where('request_status', 'Pending'); // Pastikan status request adalah 'Pending'
		$this->db->delete('requests');
		if ($this->db->affected_rows() > 0) {
			return true; // Data berhasil dihapus
		} else {
			return false; // Data tidak ditemukan atau gagal dihapus
		}
	}

	public function getAllRating()
	{
		if (!$this->db->table_exists('rating')) {
			return $this->db->query("SELECT NULL AS id_user, NULL AS id_dokter, NULL AS rating WHERE 1=0");
		}

		return $this->db->query("SELECT * FROM rating");
	}

	public function submit_rating($iduser, $iddokter, $rating)
	{
		$data = array(
			'id_user' => $iduser,
			'id_dokter' => $iddokter,
			'rating' => $rating,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		);
		return $this->db->insert('rating', $data);
	}

	public function getDataDoctor()
	{
		return $this->db
			->where('role', 'dokter')
			->where('status', 'aktif')
			->get('users');
	}
}
