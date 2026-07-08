<?php
class Konsultasi_kesehatan_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	private function safe_decrypt($value, $fallback = '-')
	{
		if ($value === null || $value === '') {
			return $fallback;
		}

		if (is_array($value) || is_object($value)) {
			return $fallback;
		}

		if (!is_scalar($value)) {
			return $fallback;
		}

		$value = trim((string) $value);
		if ($value === '') {
			return $fallback;
		}

		$is_encoded_payload = $this->is_probably_encoded_payload($value);

		$decrypted = $this->try_decrypt_string($value);
		if ($decrypted === null) {
			$decoded = base64_decode($value, TRUE);
			if (is_string($decoded) && $decoded !== '') {
				$decrypted = $this->try_decrypt_string($decoded);
			}
		}

		if ($decrypted !== null && $decrypted !== '') {
			return $decrypted;
		}

		return $is_encoded_payload ? $fallback : $value;
	}

	private function try_decrypt_string($value)
	{
		try {
			$CI = &get_instance();
			$CI->load->library('encryption');
			set_error_handler(function ($severity, $message, $file, $line) {
				throw new ErrorException($message, 0, $severity, $file, $line);
			});
			$decrypted = $CI->encryption->decrypt($value);
		} catch (Throwable $e) {
			$decrypted = null;
		} finally {
			restore_error_handler();
		}

		return ($decrypted === FALSE || $decrypted === null) ? null : $decrypted;
	}

	private function is_probably_encoded_payload($value)
	{
		if (strlen($value) > 80 && preg_match('/^[A-Za-z0-9+\/=]+$/', $value)) {
			return true;
		}

		$decoded = base64_decode($value, TRUE);
		return is_string($decoded) && strlen($decoded) > 32 && preg_match('/^[A-Fa-f0-9]{32,}/', $decoded);
	}

	private function can_query_pic_assignment()
	{
		if (!$this->db->table_exists('request_staff_assignments') || !$this->db->table_exists('puskesmas_staff')) {
			return false;
		}

		foreach (array('assignment_id', 'request_id', 'staff_id', 'assigned_by_user_id', 'status', 'assigned_at') as $field) {
			if (!$this->db->field_exists($field, 'request_staff_assignments')) {
				return false;
			}
		}

		foreach (array('staff_id', 'nama', 'no_hp', 'profesi', 'nomor_sip') as $field) {
			if (!$this->db->field_exists($field, 'puskesmas_staff')) {
				return false;
			}
		}

		return true;
	}

	private function can_query_request_events()
	{
		if (!$this->db->table_exists('request_events')) {
			return false;
		}

		foreach (array('event_id', 'request_id', 'event_type', 'created_at') as $field) {
			if (!$this->db->field_exists($field, 'request_events')) {
				return false;
			}
		}

		return true;
	}

	private function get_request_event_map($request_ids, $limit_per_request = 3)
	{
		if (!$this->can_query_request_events() || !is_array($request_ids)) {
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

		$limit_per_request = (int) $limit_per_request;
		if ($limit_per_request < 1) {
			$limit_per_request = 3;
		}
		if ($limit_per_request > 5) {
			$limit_per_request = 5;
		}

		$select = array('event_id', 'request_id', 'event_type', 'created_at');
		if ($this->db->field_exists('message', 'request_events')) {
			$select[] = 'message';
		}
		foreach (array('actor_role', 'actor_user_id', 'actor_staff_id') as $field) {
			if ($this->db->field_exists($field, 'request_events')) {
				$select[] = $field;
			}
		}

		$rows = $this->db
			->select(implode(', ', $select))
			->from('request_events')
			->where_in('request_id', array_values($ids))
			->order_by('request_id', 'ASC')
			->order_by('created_at', 'DESC')
			->order_by('event_id', 'DESC')
			->get()
			->result();

		$map = array();
		foreach ($rows as $row) {
			$request_id = (int) $row->request_id;
			if (!isset($map[$request_id])) {
				$map[$request_id] = array();
			}
			if (count($map[$request_id]) < $limit_per_request) {
				$map[$request_id][] = $row;
			}
		}

		return $map;
	}

	public function get_puskesmas_options()
	{
		if (!$this->db->table_exists('m_puskesmas') || !$this->db->field_exists('kode_pkm', 'm_puskesmas') || !$this->db->field_exists('nama_puskesmas', 'm_puskesmas')) {
			return array();
		}

		$this->db->select('kode_pkm, nama_puskesmas');
		$this->db->from('m_puskesmas');
		$this->db->where("UPPER(TRIM(kode_pkm)) <>", 'DEFAULT');
		if ($this->db->field_exists('status', 'm_puskesmas')) {
			$this->db->where('status', 'aktif');
		}
		$this->db->order_by('nama_puskesmas', 'ASC');

		return $this->db->get()->result();
	}

	public function get_data_konsul($filters = array())
	{
		if (!$this->db->table_exists('requests') || !$this->db->table_exists('users')) {
			return array();
		}

		$filters = is_array($filters) ? $filters : array();
		$date_select = $this->db->field_exists('date', 'requests')
			? 'requests.date'
			: ($this->db->field_exists('created_at', 'requests') ? 'requests.created_at AS date' : 'NULL AS date');
		$date_filter_expr = $this->db->field_exists('date', 'requests')
			? 'requests.date'
			: ($this->db->field_exists('created_at', 'requests') ? 'requests.created_at' : 'NULL');
		$created_at_select = $this->db->field_exists('created_at', 'requests') ? 'requests.created_at' : 'NULL AS created_at';
		$location_select = $this->db->field_exists('location', 'requests') ? 'requests.location' : 'NULL AS location';
		$location_detail_select = $this->db->field_exists('location_detail', 'requests') ? 'requests.location_detail' : 'NULL AS location_detail';
		$visit_status_select = $this->db->field_exists('visit_status', 'requests') ? 'requests.visit_status' : 'NULL AS visit_status';
		$lattitude_dokter_select = $this->db->field_exists('lattitude_dokter', 'requests') ? 'requests.lattitude_dokter' : 'NULL AS lattitude_dokter';
		$longitude_dokter_select = $this->db->field_exists('longitude_dokter', 'requests') ? 'requests.longitude_dokter' : 'NULL AS longitude_dokter';
		$lattitude_select = $this->db->field_exists('lattitude', 'requests') ? 'requests.lattitude' : 'NULL AS lattitude';
		$longitude_select = $this->db->field_exists('longitude', 'requests') ? 'requests.longitude' : 'NULL AS longitude';
		$has_assigned_puskesmas_code = $this->db->field_exists('assigned_puskesmas_code', 'requests');
		$has_assigned_puskesmas_name = $this->db->field_exists('assigned_puskesmas_name', 'requests');
		$assigned_puskesmas_name_expr = $has_assigned_puskesmas_name ? "NULLIF(TRIM(requests.assigned_puskesmas_name), '')" : 'NULL';
		$assigned_puskesmas_code_expr = $has_assigned_puskesmas_code ? "NULLIF(TRIM(requests.assigned_puskesmas_code), '')" : 'NULL';
		$routed_puskesmas_code_expr = "CASE WHEN UPPER($assigned_puskesmas_code_expr) = 'DEFAULT' THEN NULL ELSE $assigned_puskesmas_code_expr END";
		$assigned_puskesmas_name_clean_expr = "CASE WHEN UPPER(COALESCE($assigned_puskesmas_name_expr, '')) IN ('DEFAULT', 'PUSKESMAS DEFAULT') THEN NULL ELSE $assigned_puskesmas_name_expr END";
		$has_puskesmas = $this->db->table_exists('m_puskesmas') && $this->db->field_exists('kode_pkm', 'm_puskesmas') && $this->db->field_exists('nama_puskesmas', 'm_puskesmas');
		$puskesmas_select = $has_puskesmas
			? "COALESCE($assigned_puskesmas_name_clean_expr, assigned_puskesmas.nama_puskesmas, $routed_puskesmas_code_expr, 'Legacy / Belum terklasifikasi') AS puskesmas"
			: "COALESCE($assigned_puskesmas_name_clean_expr, $routed_puskesmas_code_expr, 'Legacy / Belum terklasifikasi') AS puskesmas";
		if ($this->db->table_exists('konsultasi')) {
			$konsultasi_select = 'konsultasi.diagnosa, konsultasi.saran, konsultasi.kriteria, konsultasi.foto';
			$konsultasi_join = 'LEFT JOIN konsultasi ON konsultasi.request_id=requests.request_id';
		} elseif ($this->db->table_exists('medicalrecords')) {
			$diagnosis_select = $this->db->field_exists('diagnosis', 'medicalrecords') ? 'medicalrecords.diagnosis AS diagnosa' : 'NULL AS diagnosa';
			$recommendations_select = $this->db->field_exists('recommendations', 'medicalrecords') ? 'medicalrecords.recommendations AS saran' : 'NULL AS saran';
			$konsultasi_select = "$diagnosis_select, $recommendations_select, NULL AS kriteria, NULL AS foto";
			$konsultasi_join = $this->db->field_exists('request_id', 'medicalrecords') ? 'LEFT JOIN medicalrecords ON medicalrecords.request_id=requests.request_id' : '';
		} else {
			$konsultasi_select = 'NULL AS diagnosa, NULL AS saran, NULL AS kriteria, NULL AS foto';
			$konsultasi_join = '';
		}
		$puskesmas_join = $has_puskesmas
			? "LEFT JOIN m_puskesmas assigned_puskesmas ON assigned_puskesmas.kode_pkm = $routed_puskesmas_code_expr"
			: '';
		$has_pic_assignment = $this->can_query_pic_assignment();
		$pic_select = $has_pic_assignment
			? "pic_staff.staff_id AS pic_staff_id,
										pic_staff.nama AS pic_staff_name,
										pic_staff.profesi AS pic_staff_profesi,
										pic_staff.no_hp AS pic_staff_no_hp,
										pic_staff.nomor_sip AS pic_staff_nomor_sip,
										pic_assignment.assigned_at AS pic_assigned_at,
										pic_assignment.assigned_by_user_id AS pic_assigned_by_user_id,
										pic_assigned_by.nama AS pic_assigned_by_name,"
			: "NULL AS pic_staff_id,
										NULL AS pic_staff_name,
										NULL AS pic_staff_profesi,
										NULL AS pic_staff_no_hp,
										NULL AS pic_staff_nomor_sip,
										NULL AS pic_assigned_at,
										NULL AS pic_assigned_by_user_id,
										NULL AS pic_assigned_by_name,";
		$pic_join = $has_pic_assignment
			? "LEFT JOIN request_staff_assignments pic_assignment
										ON pic_assignment.assignment_id = (
											SELECT rsa_active.assignment_id
											FROM request_staff_assignments rsa_active
											WHERE rsa_active.request_id = requests.request_id
												AND rsa_active.status = 'aktif'
											ORDER BY rsa_active.assigned_at DESC, rsa_active.assignment_id DESC
											LIMIT 1
										)
									LEFT JOIN puskesmas_staff pic_staff ON pic_staff.staff_id = pic_assignment.staff_id
									LEFT JOIN users pic_assigned_by ON pic_assigned_by.userId = pic_assignment.assigned_by_user_id"
			: '';
		$where_parts = array();
		$status = isset($filters['status']) ? trim((string) $filters['status']) : '';
		if (in_array($status, array('Pending', 'Accepted', 'Completed', 'Cancelled'), true)) {
			$where_parts[] = 'requests.request_status = ' . $this->db->escape($status);
		}
		$puskesmas_filter = isset($filters['puskesmas']) ? trim((string) $filters['puskesmas']) : '';
		if ($puskesmas_filter === '__legacy__') {
			$where_parts[] = "($assigned_puskesmas_code_expr IS NULL OR UPPER(COALESCE($assigned_puskesmas_code_expr, '')) = 'DEFAULT')";
		} elseif ($puskesmas_filter !== '') {
			$where_parts[] = $routed_puskesmas_code_expr . ' = ' . $this->db->escape($puskesmas_filter);
		}
		$date_from = isset($filters['date_from']) ? trim((string) $filters['date_from']) : '';
		if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
			$where_parts[] = 'DATE(' . $date_filter_expr . ') >= ' . $this->db->escape($date_from);
		}
		$date_to = isset($filters['date_to']) ? trim((string) $filters['date_to']) : '';
		if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
			$where_parts[] = 'DATE(' . $date_filter_expr . ') <= ' . $this->db->escape($date_to);
		}
		$keyword = isset($filters['keyword']) ? trim((string) $filters['keyword']) : '';
		if ($keyword !== '') {
			$like = '%' . $this->db->escape_like_str($keyword) . '%';
			$where_parts[] = "(CAST(requests.request_id AS CHAR) LIKE " . $this->db->escape($like) . " OR users.nama LIKE " . $this->db->escape($like) . " OR $assigned_puskesmas_name_clean_expr LIKE " . $this->db->escape($like) . ")";
		}
		$where_sql = empty($where_parts) ? '' : 'WHERE ' . implode(' AND ', $where_parts);
		$query = $this->db->query("SELECT
										requests.request_id,
										requests.user_id,
										(SELECT users.nama FROM users WHERE users.userId=requests.user_id) AS nama_warga,
										requests.dokter_id,
										(SELECT users.nama FROM users WHERE users.userId=requests.dokter_id) AS nama_akun_puskesmas,
										$routed_puskesmas_code_expr AS assigned_puskesmas_code,
										$puskesmas_select,
										$pic_select
										$date_select,
										$visit_status_select,
										requests.request_description,
										requests.request_status,
										$konsultasi_select,
										$location_select,
										$location_detail_select,
										$lattitude_select,
										$longitude_select,
										$lattitude_dokter_select,
										$longitude_dokter_select,
										$created_at_select
									FROM
										requests
									$konsultasi_join
									LEFT JOIN users ON users.userId = requests.user_id
									$puskesmas_join
									$pic_join
									$where_sql
									ORDER BY date DESC");
		$hasil = $query->result();

		foreach ($hasil as $row) {
			$row->request_description = $this->safe_decrypt($row->request_description, '-');
		}

		$request_ids = array();
		foreach ($hasil as $row) {
			$request_ids[] = isset($row->request_id) ? (int) $row->request_id : 0;
		}
		$event_map = $this->get_request_event_map($request_ids, 3);
		foreach ($hasil as $row) {
			$request_id = isset($row->request_id) ? (int) $row->request_id : 0;
			$row->request_events = isset($event_map[$request_id]) ? $event_map[$request_id] : array();
		}

		return $hasil;
	}
}
