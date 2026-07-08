<?php
class Home_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	private function empty_result($columns)
	{
		$select = array();
		foreach ($columns as $column) {
			$select[] = 'NULL AS ' . $column;
		}

		return $this->db->query('SELECT ' . implode(', ', $select) . ' WHERE 1=0');
	}

	private function assigned_puskesmas_code_expr($request_alias = 'requests')
	{
		$raw = $this->db->field_exists('assigned_puskesmas_code', 'requests')
			? "NULLIF(TRIM($request_alias.assigned_puskesmas_code), '')"
			: 'NULL';

		return "CASE WHEN UPPER(COALESCE($raw, '')) = 'DEFAULT' THEN NULL ELSE $raw END";
	}

	private function assigned_puskesmas_name_expr($request_alias = 'requests')
	{
		$raw = $this->db->field_exists('assigned_puskesmas_name', 'requests')
			? "NULLIF(TRIM($request_alias.assigned_puskesmas_name), '')"
			: 'NULL';

		return "CASE WHEN UPPER(COALESCE($raw, '')) IN ('DEFAULT', 'PUSKESMAS DEFAULT') THEN NULL ELSE $raw END";
	}

	private function puskesmas_display_expr($request_alias = 'requests', $puskesmas_alias = 'assigned_puskesmas')
	{
		$assigned_name = $this->assigned_puskesmas_name_expr($request_alias);
		$assigned_code = $this->assigned_puskesmas_code_expr($request_alias);
		$master_name = $this->can_join_puskesmas() ? "$puskesmas_alias.nama_puskesmas" : 'NULL';

		return "COALESCE($assigned_name, $master_name, $assigned_code, 'Legacy / Belum terklasifikasi')";
	}

	private function puskesmas_key_expr($request_alias = 'requests')
	{
		$assigned_code = $this->assigned_puskesmas_code_expr($request_alias);

		return "COALESCE($assigned_code, 'LEGACY_UNCLASSIFIED')";
	}

	private function can_join_puskesmas()
	{
		return $this->db->table_exists('m_puskesmas')
			&& $this->db->field_exists('kode_pkm', 'm_puskesmas')
			&& $this->db->field_exists('nama_puskesmas', 'm_puskesmas');
	}

	private function request_date_expr($request_alias = 'requests')
	{
		if ($this->db->field_exists('created_at', 'requests')) {
			return "$request_alias.created_at";
		}

		if ($this->db->field_exists('date', 'requests')) {
			return "$request_alias.date";
		}

		return 'NULL';
	}

	private function request_updated_expr($request_alias = 'requests')
	{
		if ($this->db->field_exists('updated_at', 'requests')) {
			return "$request_alias.updated_at";
		}

		if ($this->db->field_exists('modify_date', 'requests')) {
			return "$request_alias.modify_date";
		}

		return $this->request_date_expr($request_alias);
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

	private function can_query_pic_assignment()
	{
		if (!$this->db->table_exists('request_staff_assignments')) {
			return false;
		}

		foreach (array('request_id', 'status') as $field) {
			if (!$this->db->field_exists($field, 'request_staff_assignments')) {
				return false;
			}
		}

		return true;
	}

	private function event_title($event_type)
	{
		$labels = array(
			'request_created' => 'Konsultasi baru dibuat',
			'request_accepted' => 'Konsultasi diterima',
			'request_cancelled' => 'Konsultasi dibatalkan',
			'visit_started' => 'Kunjungan dimulai',
			'visit_arrived' => 'Nakes tiba di lokasi',
			'visit_in_service' => 'Layanan sedang berjalan',
			'visit_completed' => 'Kunjungan selesai',
			'request_completed' => 'Konsultasi selesai',
			'pic_assigned' => 'PIC ditugaskan',
			'pic_changed' => 'PIC diganti',
			'pic_cleared' => 'PIC dihapus',
		);

		return isset($labels[$event_type]) ? $labels[$event_type] : 'Aktivitas konsultasi diperbarui';
	}

	private function event_message($event_type)
	{
		if (in_array($event_type, array('pic_assigned', 'pic_changed', 'pic_cleared'), true)) {
			return 'Penugasan PIC pada konsultasi diperbarui.';
		}

		if (in_array($event_type, array('visit_started', 'visit_arrived', 'visit_in_service', 'visit_completed'), true)) {
			return 'Aktivitas kunjungan Puskesmas diperbarui.';
		}

		return 'Status konsultasi diperbarui.';
	}

	private function format_datetime($value)
	{
		$timestamp = strtotime((string) $value);
		return $timestamp ? date('d M Y H:i', $timestamp) : '-';
	}

	public function get_operational_summary()
	{
		$summary = array(
			'today' => 0,
			'pending' => 0,
			'accepted' => 0,
			'in_progress' => 0,
			'completed' => 0,
			'legacy' => 0,
		);

		if (!$this->db->table_exists('requests')) {
			return $summary;
		}

		$date_expr = $this->request_date_expr('requests');
		$assigned_code = $this->assigned_puskesmas_code_expr('requests');

		$this->db->select("
			SUM(CASE WHEN $date_expr IS NOT NULL AND DATE($date_expr) = CURDATE() THEN 1 ELSE 0 END) AS today,
			SUM(CASE WHEN request_status = 'Pending' THEN 1 ELSE 0 END) AS pending,
			SUM(CASE WHEN request_status = 'Accepted' THEN 1 ELSE 0 END) AS accepted,
			SUM(CASE WHEN request_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
			SUM(CASE WHEN $assigned_code IS NULL THEN 1 ELSE 0 END) AS legacy
		", FALSE);
		$row = $this->db->get('requests')->row();

		if ($row) {
			$summary['today'] = (int) $row->today;
			$summary['pending'] = (int) $row->pending;
			$summary['accepted'] = (int) $row->accepted;
			$summary['completed'] = (int) $row->completed;
			$summary['legacy'] = (int) $row->legacy;
		}

		$summary['in_progress'] = $summary['accepted'];
		if ($this->db->field_exists('visit_status', 'requests')) {
			$this->db->where('visit_status IS NOT NULL', NULL, FALSE);
			$this->db->where("TRIM(visit_status) <>", '', FALSE);
			$this->db->where('request_status <>', 'Completed');
			$summary['in_progress'] = (int) $this->db->count_all_results('requests');
		}

		return $summary;
	}

	public function get_puskesmas_distribution($limit = 8)
	{
		if (!$this->db->table_exists('requests')) {
			return array();
		}

		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = 8;
		}

		$display_expr = $this->puskesmas_display_expr('requests', 'assigned_puskesmas');
		$code_expr = $this->puskesmas_key_expr('requests');

		$this->db->select("$display_expr AS puskesmas, $code_expr AS puskesmas_code, COUNT(requests.request_id) AS total", FALSE);
		$this->db->from('requests');
		if ($this->can_join_puskesmas()) {
			$this->db->join('m_puskesmas assigned_puskesmas', 'assigned_puskesmas.kode_pkm = ' . $this->assigned_puskesmas_code_expr('requests'), 'left', FALSE);
		}
		$this->db->group_by($display_expr, FALSE);
		$this->db->group_by($code_expr, FALSE);
		$this->db->order_by('total', 'DESC');
		$this->db->limit($limit);

		return $this->db->get()->result();
	}

	public function get_attention_requests($limit = 8)
	{
		if (!$this->db->table_exists('requests')) {
			return array();
		}

		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = 8;
		}

		$display_expr = $this->puskesmas_display_expr('requests', 'assigned_puskesmas');
		$assigned_code = $this->assigned_puskesmas_code_expr('requests');
		$date_expr = $this->request_date_expr('requests');
		$updated_expr = $this->request_updated_expr('requests');
		$has_pic_assignment = $this->can_query_pic_assignment();
		$pic_select = $has_pic_assignment ? 'pic_assignment.request_id AS active_pic_request_id' : 'NULL AS active_pic_request_id';
		$pic_request_expr = $has_pic_assignment ? 'pic_assignment.request_id' : 'NULL';
		$pic_join = $has_pic_assignment
			? "LEFT JOIN request_staff_assignments pic_assignment ON pic_assignment.request_id = requests.request_id AND pic_assignment.status = 'aktif'"
			: '';
		$reason_expr = "CASE
			WHEN $assigned_code IS NULL THEN 'Belum terklasifikasi ke Puskesmas'
			WHEN request_status = 'Pending' AND $date_expr IS NOT NULL AND $date_expr < DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'Pending lebih dari 24 jam'
			WHEN request_status = 'Pending' THEN 'Menunggu penerimaan'
			WHEN request_status = 'Accepted' AND $pic_request_expr IS NULL THEN 'Diterima tanpa PIC aktif'
			ELSE 'Perlu dipantau'
		END";
		if (!$has_pic_assignment) {
			$reason_expr = "CASE
				WHEN $assigned_code IS NULL THEN 'Belum terklasifikasi ke Puskesmas'
				WHEN request_status = 'Pending' THEN 'Menunggu penerimaan'
				ELSE 'Perlu dipantau'
			END";
		}

		$sql = "SELECT
				requests.request_id,
				requests.request_status,
				$display_expr AS puskesmas,
				$date_expr AS created_at,
				$updated_expr AS updated_at,
				$pic_select,
				$reason_expr AS attention_reason
			FROM requests
			" . ($this->can_join_puskesmas() ? 'LEFT JOIN m_puskesmas assigned_puskesmas ON assigned_puskesmas.kode_pkm = ' . $this->assigned_puskesmas_code_expr('requests') : '') . "
			$pic_join
			WHERE
				$assigned_code IS NULL
				OR requests.request_status = 'Pending'
				" . ($has_pic_assignment ? "OR (requests.request_status = 'Accepted' AND pic_assignment.request_id IS NULL)" : '') . "
			GROUP BY requests.request_id
			ORDER BY
				CASE WHEN $assigned_code IS NULL THEN 0 WHEN requests.request_status = 'Pending' THEN 1 ELSE 2 END,
				$date_expr ASC
			LIMIT " . (int) $limit;

		$rows = $this->db->query($sql)->result();
		foreach ($rows as $row) {
			$row->created_at_label = $this->format_datetime($row->created_at);
			$row->updated_at_label = $this->format_datetime($row->updated_at);
		}

		return $rows;
	}

	public function get_recent_request_events($limit = 8)
	{
		if (!$this->can_query_request_events()) {
			return array();
		}

		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = 8;
		}

		$display_expr = $this->puskesmas_display_expr('requests', 'assigned_puskesmas');
		$has_requests = $this->db->table_exists('requests') && $this->db->field_exists('request_id', 'requests');
		$select_puskesmas = $has_requests ? "$display_expr AS puskesmas" : "'Legacy / Belum terklasifikasi' AS puskesmas";

		$this->db->select("request_events.event_id, request_events.request_id, request_events.event_type, request_events.created_at, $select_puskesmas", FALSE);
		$this->db->from('request_events');
		if ($has_requests) {
			$this->db->join('requests', 'requests.request_id = request_events.request_id', 'left');
			if ($this->can_join_puskesmas()) {
				$this->db->join('m_puskesmas assigned_puskesmas', 'assigned_puskesmas.kode_pkm = ' . $this->assigned_puskesmas_code_expr('requests'), 'left', FALSE);
			}
		}
		$this->db->order_by('request_events.event_id', 'DESC');
		$this->db->limit($limit);

		$rows = $this->db->get()->result();
		foreach ($rows as $row) {
			$row->event_label = $this->event_title(isset($row->event_type) ? (string) $row->event_type : '');
			$row->event_message = $this->event_message(isset($row->event_type) ? (string) $row->event_type : '');
			$row->created_at_label = $this->format_datetime($row->created_at);
		}

		return $rows;
	}

	public function get_konsul_perbulan($ym)
	{
		if (!$this->db->table_exists('requests')) {
			return $this->db->query('SELECT 0 AS total_nilai');
		}

		$date_column = $this->db->field_exists('date', 'requests') ? 'date' : 'created_at';
		return $this->db->query("SELECT COUNT(request_id) AS total_nilai FROM requests WHERE DATE_FORMAT(`$date_column`, '%Y-%m') = " . $this->db->escape($ym));
	}

	private function konsultasi_list_by_status($status, $tanggal = null)
	{
		if (!$this->db->table_exists('requests') || !$this->db->table_exists('users')) {
			return $this->empty_result(array('request_id', 'created_at', 'nama', 'username', 'name', 'remark', 'nama_puskesmas'));
		}

		$doctor_name = $this->db->table_exists('m_dokter') ? "COALESCE(m_dokter.name, dokter_user.nama, '-')" : "COALESCE(dokter_user.nama, '-')";
		$remark_select = $this->puskesmas_key_expr('requests');
		$puskesmas_select = $this->puskesmas_display_expr('requests', 'assigned_puskesmas');

		$this->db->select("requests.*, requests.created_at, users.nama, users.username, $doctor_name AS name, $remark_select AS remark, $puskesmas_select AS nama_puskesmas", FALSE);
		$this->db->from('requests');
		$this->db->join('users', 'requests.user_id = users.userId');
		$this->db->join('users dokter_user', 'requests.dokter_id = dokter_user.userId', 'left');
		if ($this->db->table_exists('m_dokter')) {
			$this->db->join('m_dokter', 'requests.dokter_id = m_dokter.professional_id', 'left');
		}
		if ($this->can_join_puskesmas()) {
			$this->db->join('m_puskesmas assigned_puskesmas', 'assigned_puskesmas.kode_pkm = ' . $this->assigned_puskesmas_code_expr('requests'), 'left', FALSE);
		}
		$this->db->where('request_status', $status);

		if (!empty($tanggal)) {
			$this->db->where('DATE(requests.created_at)', $tanggal);
		}

		return $this->db->get();
	}

	public function konsultasi_baru_list($tanggal = null)
	{
		return $this->konsultasi_list_by_status('Pending', $tanggal);
	}


	public function konsultasi_proses_list($tanggal = null)
	{
		return $this->konsultasi_list_by_status('Accepted', $tanggal);
	}

	public function konsultasi_selesai_list()
	{
		if (!$this->db->table_exists('requests')) {
			return $this->empty_result(array('nama_puskesmas', 'remark', 'total_selesai'));
		}

		$has_puskesmas = $this->can_join_puskesmas();
		$remark_select = $this->puskesmas_key_expr('requests');
		$puskesmas_select = $this->puskesmas_display_expr('requests', 'assigned_puskesmas');

		$this->db->select("$puskesmas_select AS nama_puskesmas, $remark_select AS remark, COUNT(requests.request_id) AS total_selesai", FALSE);
		$this->db->from('requests');
		if ($has_puskesmas) {
			$this->db->join('m_puskesmas assigned_puskesmas', 'assigned_puskesmas.kode_pkm = ' . $this->assigned_puskesmas_code_expr('requests'), 'left', FALSE);
		}
		$this->db->where('request_status', 'Completed');
		$this->db->group_by($remark_select, FALSE);
		$this->db->group_by($puskesmas_select, FALSE);
		return $this->db->get();
	}

	public function selesai_konsultasi_per_puskesmas()
	{
		if (!$this->db->table_exists('requests') || !$this->db->table_exists('konsultasi')) {
			return array();
		}

		$remark_select = $this->puskesmas_key_expr('requests');

		$this->db->select("$remark_select AS remark, COUNT(konsultasi.kriteria) AS jumlah_selesai", FALSE);
		$this->db->from('requests');
		$this->db->join('konsultasi', 'requests.request_id = konsultasi.request_id');
		$this->db->where('konsultasi.kriteria', 'Selesai Konsultasi');
		$this->db->group_by($remark_select, FALSE);
		return $this->db->get()->result();
	}

	public function kunjungan_nakes_per_puskesmas()
	{
		if (!$this->db->table_exists('requests') || !$this->db->table_exists('konsultasi')) {
			return array();
		}

		$remark_select = $this->puskesmas_key_expr('requests');

		$this->db->select("$remark_select AS remark, COUNT(konsultasi.kriteria) AS jumlah_kunjungan", FALSE);
		$this->db->from('requests');
		$this->db->join('konsultasi', 'requests.request_id = konsultasi.request_id');
		$this->db->where('konsultasi.kriteria', 'Kunjungan Nakes');
		$this->db->group_by($remark_select, FALSE);
		return $this->db->get()->result();
	}

	public function get_top_diagnosa($limit)
	{
		$limit = (int) $limit;
		if ($limit <= 0) {
			$limit = 5;
		}

		if ($this->db->table_exists('konsultasi')) {
			$this->db->select('diagnosa, COUNT(*) as jumlah');
			$this->db->from('konsultasi');
		} elseif ($this->db->table_exists('medicalrecords')) {
			$this->db->select('diagnosis AS diagnosa, COUNT(*) as jumlah');
			$this->db->from('medicalrecords');
			$this->db->where('diagnosis IS NOT NULL', NULL, FALSE);
			$this->db->where('diagnosis <>', '');
		} else {
			return array();
		}

		$this->db->group_by('diagnosa');
		$this->db->order_by('jumlah', 'DESC');
		$this->db->limit($limit);
		return $this->db->get()->result();
	}

	public function change_password($email, $new_password)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$this->db->where('email', $email);
		return $this->db->update('users', array('password' => $new_password, 'updated_at' => date('Y-m-d H:i:s')));
	}

	public function get_user_by_email($email)
	{
		if (!$this->db->table_exists('users')) {
			return $this->empty_result(array('userId', 'email', 'password'));
		}

		return $this->db->where('email', $email)->get('users');
	}
}
