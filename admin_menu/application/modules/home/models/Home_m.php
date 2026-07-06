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
		$master_name = $this->db->table_exists('m_puskesmas') ? "$puskesmas_alias.nama_puskesmas" : 'NULL';

		return "COALESCE($assigned_name, $master_name, $assigned_code, 'Legacy / Belum terklasifikasi')";
	}

	private function puskesmas_key_expr($request_alias = 'requests')
	{
		$assigned_code = $this->assigned_puskesmas_code_expr($request_alias);

		return "COALESCE($assigned_code, 'LEGACY_UNCLASSIFIED')";
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
		if ($this->db->table_exists('m_puskesmas')) {
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

		$has_puskesmas = $this->db->table_exists('m_puskesmas');
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
