<?php
class Laporan_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}
	public function get_count_by_category($category)
	{
		if (!$this->db->table_exists('t_pengaduan')) {
			return 0;
		}

		$this->db->select('COUNT(kategori) AS jml');
		$this->db->from('t_pengaduan');
		$this->db->where('kategori', $category);
		$query = $this->db->get();

		// Check if row exists
		if ($query->num_rows() > 0) {
			return $query->row()->jml;
		}
		return 0; // Return 0 if no records found
	}
	public function get_data_pengaduan()
	{
		if (!$this->db->table_exists('t_pengaduan')) {
			return $this->db->query("SELECT NULL AS id_pengaduan, NULL AS status, NULL AS kategori, NULL AS nama_kecamatan, NULL AS deskripsi, NULL AS nama, NULL AS email, NULL AS tanggal, NULL AS file, NULL AS dinas WHERE 1=0");
		}

		$kecamatan_join = $this->db->table_exists('kecamatan')
			? 'LEFT JOIN kecamatan ON t_pengaduan.lokasi = kecamatan.id_kecamatan'
			: '';
		$kecamatan_select = $this->db->table_exists('kecamatan') ? 'kecamatan.nama_kecamatan' : 'NULL AS nama_kecamatan';
		return $this->db->query("SELECT
                                        t_pengaduan.id_pengaduan, 
                                        t_pengaduan.status,
                                        t_pengaduan.kategori, 
                                        $kecamatan_select,
                                        t_pengaduan.deskripsi, 
                                        t_pengaduan.nama,
                                        t_pengaduan.email, 
                                        t_pengaduan.tanggal, 
                                        t_pengaduan.file, 
                                        t_pengaduan.dinas
                                    FROM
                                        t_pengaduan
                                        $kecamatan_join
                                    ORDER BY t_pengaduan.created_at DESC");
	}

	private function can_query_consultation_reports()
	{
		return $this->db->table_exists('requests')
			&& $this->db->table_exists('users')
			&& ($this->db->table_exists('konsultasi') || $this->db->table_exists('medicalrecords'));
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

		foreach (array('event_id', 'request_id', 'event_type', 'message', 'created_at') as $field) {
			if (!$this->db->field_exists($field, 'request_events')) {
				return false;
			}
		}

		return true;
	}

	private function get_latest_request_event_map($request_ids)
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

		$select = array('event_id', 'request_id', 'event_type', 'message', 'created_at');
		foreach (array('actor_role', 'actor_user_id', 'actor_staff_id') as $field) {
			if ($this->db->field_exists($field, 'request_events')) {
				$select[] = $field;
			}
		}

		$rows = $this->db
			->select(implode(', ', $select))
			->from('request_events')
			->where_in('request_id', array_values($ids))
			->where_in('event_type', array(
				'request_created',
				'request_accepted',
				'request_cancelled',
				'pic_assigned',
				'pic_changed',
				'pic_cleared',
				'visit_started',
				'visit_arrived',
				'visit_in_service',
				'visit_completed',
				'request_completed',
			))
			->order_by('request_id', 'ASC')
			->order_by('created_at', 'DESC')
			->order_by('event_id', 'DESC')
			->get()
			->result();

		$map = array();
		foreach ($rows as $row) {
			$request_id = (int) $row->request_id;
			if (!isset($map[$request_id])) {
				$map[$request_id] = $row;
			}
		}

		return $map;
	}

	public function get_puskesmas_options()
	{
		if (!$this->db->table_exists('m_puskesmas')) {
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

	private function apply_report_filters($date_expr, $diagnosa_expr, $puskesmas_code_expr, $dokter_expr, $tanggal = null, $puskesmas = null, $dokter = null, $status = null, $keyword = null)
	{
		if ($tanggal) {
			$this->db->where("DATE($date_expr) = " . $this->db->escape($tanggal), NULL, FALSE);
		}
		if ($puskesmas === '__legacy__') {
			$this->db->where("($puskesmas_code_expr IS NULL)", NULL, FALSE);
		} elseif ($puskesmas) {
			$this->db->where($puskesmas_code_expr . ' = ' . $this->db->escape($puskesmas), NULL, FALSE);
		}
		if ($dokter) {
			$this->db->where($dokter_expr . ' LIKE ' . $this->db->escape('%' . $this->db->escape_like_str($dokter) . '%'), NULL, FALSE);
		}
		if (in_array($status, array('Pending', 'Accepted', 'Completed', 'Cancelled'), true)) {
			$this->db->where('requests.request_status', $status);
		}
		if ($keyword) {
			$like = '%' . $this->db->escape_like_str($keyword) . '%';
			$this->db->where("(CAST(requests.request_id AS CHAR) LIKE " . $this->db->escape($like) . " OR users.nama LIKE " . $this->db->escape($like) . " OR $diagnosa_expr LIKE " . $this->db->escape($like) . ")", NULL, FALSE);
		}
	}

	private function apply_consultation_report_base($aggregate_select = '')
	{
		$has_konsultasi = $this->db->table_exists('konsultasi');
		$has_puskesmas = $this->db->table_exists('m_puskesmas');
		$has_dokter = $this->db->table_exists('m_dokter');
		$has_assigned_puskesmas_code = $this->db->field_exists('assigned_puskesmas_code', 'requests');
		$has_assigned_puskesmas_name = $this->db->field_exists('assigned_puskesmas_name', 'requests');

		$date_expr = $has_konsultasi ? 'konsultasi.create_date' : 'medicalrecords.created_at';
		$diagnosa_expr = $has_konsultasi ? 'konsultasi.diagnosa' : 'medicalrecords.diagnosis';
		$assigned_puskesmas_name_expr = $has_assigned_puskesmas_name ? "NULLIF(TRIM(requests.assigned_puskesmas_name), '')" : 'NULL';
		$assigned_puskesmas_code_expr = $has_assigned_puskesmas_code ? "NULLIF(TRIM(requests.assigned_puskesmas_code), '')" : 'NULL';
		$routed_puskesmas_code_expr = "CASE WHEN UPPER($assigned_puskesmas_code_expr) = 'DEFAULT' THEN NULL ELSE $assigned_puskesmas_code_expr END";
		$assigned_puskesmas_name_clean_expr = "CASE WHEN UPPER(COALESCE($assigned_puskesmas_name_expr, '')) IN ('DEFAULT', 'PUSKESMAS DEFAULT') THEN NULL ELSE $assigned_puskesmas_name_expr END";
		$puskesmas_expr = $has_puskesmas
			? "COALESCE($assigned_puskesmas_name_clean_expr, assigned_puskesmas.nama_puskesmas, $routed_puskesmas_code_expr, 'Legacy / Belum terklasifikasi')"
			: "COALESCE($assigned_puskesmas_name_clean_expr, $routed_puskesmas_code_expr, 'Legacy / Belum terklasifikasi')";
		$dokter_expr = $has_dokter ? "COALESCE(m_dokter.name, dokter_user.nama, '-')" : "COALESCE(dokter_user.nama, '-')";
		$include_pic = $aggregate_select === '' && $this->can_query_pic_assignment();

		if ($aggregate_select === '') {
			$saran_expr = $has_konsultasi ? 'konsultasi.saran' : 'medicalrecords.recommendations';
			$select = "requests.request_id, DATE($date_expr) as tanggal, $date_expr as waktu, requests.request_status, $dokter_expr AS name, $diagnosa_expr AS diagnosa, $saran_expr AS saran, $puskesmas_expr AS nama_puskesmas, users.nama as nama_user";
		} elseif (strpos($aggregate_select, 'jumlah_diagnosa') !== false) {
			$select = "$puskesmas_expr AS nama_puskesmas, $diagnosa_expr AS diagnosa";
		} else {
			$select = "$puskesmas_expr AS nama_puskesmas";
		}
		if ($include_pic) {
			$select .= ", pic_staff.staff_id AS pic_staff_id, pic_staff.nama AS pic_staff_name, pic_staff.profesi AS pic_staff_profesi, pic_staff.no_hp AS pic_staff_no_hp, pic_staff.nomor_sip AS pic_staff_nomor_sip, pic_assignment.assigned_at AS pic_assigned_at, pic_assignment.assigned_by_user_id AS pic_assigned_by_user_id, pic_assigned_by.nama AS pic_assigned_by_name";
		} elseif ($aggregate_select === '') {
			$select .= ", NULL AS pic_staff_id, NULL AS pic_staff_name, NULL AS pic_staff_profesi, NULL AS pic_staff_no_hp, NULL AS pic_staff_nomor_sip, NULL AS pic_assigned_at, NULL AS pic_assigned_by_user_id, NULL AS pic_assigned_by_name";
		}
		if ($aggregate_select !== '') {
			$select .= ', ' . $aggregate_select;
		}

		$this->db->select($select, FALSE);
		$this->db->from('requests');
		$this->db->join('users', 'users.userId = requests.user_id', 'left');
		$this->db->join('users dokter_user', 'dokter_user.userId = requests.dokter_id', 'left');
		if ($has_puskesmas) {
			$this->db->join('m_puskesmas assigned_puskesmas', "assigned_puskesmas.kode_pkm = $routed_puskesmas_code_expr", 'left', FALSE);
		}
		if ($has_dokter) {
			$this->db->join('m_dokter', 'm_dokter.professional_id = requests.dokter_id', 'left');
		}
		if ($include_pic) {
			$this->db->join('request_staff_assignments pic_assignment', "pic_assignment.assignment_id = (
				SELECT rsa_active.assignment_id
				FROM request_staff_assignments rsa_active
				WHERE rsa_active.request_id = requests.request_id
					AND rsa_active.status = 'aktif'
				ORDER BY rsa_active.assigned_at DESC, rsa_active.assignment_id DESC
				LIMIT 1
			)", 'left', FALSE);
			$this->db->join('puskesmas_staff pic_staff', 'pic_staff.staff_id = pic_assignment.staff_id', 'left');
			$this->db->join('users pic_assigned_by', 'pic_assigned_by.userId = pic_assignment.assigned_by_user_id', 'left');
		}
		if ($has_konsultasi) {
			$this->db->join('konsultasi', 'konsultasi.request_id = requests.request_id', 'left');
			$this->db->where('konsultasi.request_id IS NOT NULL', NULL, FALSE);
		} else {
			$this->db->join('medicalrecords', 'medicalrecords.request_id = requests.request_id', 'left');
			$this->db->where('medicalrecords.request_id IS NOT NULL', NULL, FALSE);
		}

		return array($date_expr, $diagnosa_expr, $puskesmas_expr, $dokter_expr, $routed_puskesmas_code_expr);
	}

	public function get_laporan_perhari($tanggal = null, $puskesmas = null, $dokter = null, $status = null, $keyword = null)
	{
		if (!$this->can_query_consultation_reports()) {
			return array();
		}

		list($date_expr, $diagnosa_expr, $puskesmas_expr, $dokter_expr, $puskesmas_code_expr) = $this->apply_consultation_report_base();
		$this->apply_report_filters($date_expr, $diagnosa_expr, $puskesmas_code_expr, $dokter_expr, $tanggal, $puskesmas, $dokter, $status, $keyword);

		$this->db->order_by("nama_puskesmas", "ASC");
		$query = $this->db->get();
		$rows = $query->result();
		$request_ids = array();
		foreach ($rows as $row) {
			$request_ids[] = isset($row->request_id) ? (int) $row->request_id : 0;
		}
		$event_map = $this->get_latest_request_event_map($request_ids);
		foreach ($rows as $row) {
			$request_id = isset($row->request_id) ? (int) $row->request_id : 0;
			$row->latest_request_event = isset($event_map[$request_id]) ? $event_map[$request_id] : null;
		}

		return $rows;
	}

	public function get_laporan_perhari_jumlah_pasien($tanggal = null, $puskesmas = null, $dokter = null, $status = null, $keyword = null)
	{
		if (!$this->can_query_consultation_reports()) {
			return array();
		}

		list($date_expr, $diagnosa_expr, $puskesmas_expr, $dokter_expr, $puskesmas_code_expr) = $this->apply_consultation_report_base('COUNT(*) as jumlah_pasien');
		$this->apply_report_filters($date_expr, $diagnosa_expr, $puskesmas_code_expr, $dokter_expr, $tanggal, $puskesmas, $dokter, $status, $keyword);

		$this->db->order_by("nama_puskesmas", "ASC");
		$this->db->group_by("nama_puskesmas");
		$query = $this->db->get();
		return $query->result();
	}

	public function get_laporan_perhari_jumlah_diagnosa($tanggal = null, $puskesmas = null, $dokter = null, $status = null, $keyword = null)
	{
		if (!$this->can_query_consultation_reports()) {
			return array();
		}

		list($date_expr, $diagnosa_expr, $puskesmas_expr, $dokter_expr, $puskesmas_code_expr) = $this->apply_consultation_report_base('COUNT(*) as jumlah_diagnosa');
		$this->apply_report_filters($date_expr, $diagnosa_expr, $puskesmas_code_expr, $dokter_expr, $tanggal, $puskesmas, $dokter, $status, $keyword);

		$this->db->order_by("nama_puskesmas", "ASC");
		$this->db->order_by("jumlah_diagnosa", "DESC");
		$this->db->group_by(array("nama_puskesmas", "diagnosa"));
		$query = $this->db->get();
		return $query->result();
	}
}
