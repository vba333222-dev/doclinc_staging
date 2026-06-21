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

		$has_user_remark = $this->db->field_exists('remark', 'users');
		$doctor_name = $this->db->table_exists('m_dokter') ? "COALESCE(m_dokter.name, dokter_user.nama, '-')" : "COALESCE(dokter_user.nama, '-')";
		$remark_select = $has_user_remark ? 'users.remark' : 'NULL';
		$puskesmas_select = ($has_user_remark && $this->db->table_exists('m_puskesmas')) ? "COALESCE(m_puskesmas.nama_puskesmas, users.remark, '-')" : "'-'";

		$this->db->select("requests.*, requests.created_at, users.nama, users.username, $doctor_name AS name, $remark_select AS remark, $puskesmas_select AS nama_puskesmas", FALSE);
		$this->db->from('requests');
		$this->db->join('users', 'requests.user_id = users.userId');
		$this->db->join('users dokter_user', 'requests.dokter_id = dokter_user.userId', 'left');
		if ($this->db->table_exists('m_dokter')) {
			$this->db->join('m_dokter', 'requests.dokter_id = m_dokter.professional_id', 'left');
		}
		if ($has_user_remark && $this->db->table_exists('m_puskesmas')) {
			$this->db->join('m_puskesmas', 'users.remark = m_puskesmas.kode_pkm', 'left');
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

		$has_users = $this->db->table_exists('users');
		$has_user_remark = $has_users && $this->db->field_exists('remark', 'users');
		$has_puskesmas = $this->db->table_exists('m_puskesmas');

		if ($has_user_remark && $has_puskesmas) {
			$this->db->select("COALESCE(m_puskesmas.nama_puskesmas, users.remark, '-') AS nama_puskesmas, users.remark, COUNT(requests.request_id) AS total_selesai", FALSE);
		} elseif ($has_user_remark) {
			$this->db->select("COALESCE(users.remark, '-') AS nama_puskesmas, users.remark, COUNT(requests.request_id) AS total_selesai", FALSE);
		} else {
			$this->db->select("'-' AS nama_puskesmas, NULL AS remark, COUNT(requests.request_id) AS total_selesai", FALSE);
		}

		$this->db->from('requests');
		if ($has_users) {
			$this->db->join('users', 'requests.user_id = users.userId', 'left');
		}
		if ($has_user_remark && $has_puskesmas) {
			$this->db->join('m_puskesmas', 'users.remark = m_puskesmas.kode_pkm', 'left');
		}
		$this->db->where('request_status', 'Completed');
		if ($has_user_remark) {
			$this->db->group_by('users.remark');
		}
		return $this->db->get();
	}

	public function selesai_konsultasi_per_puskesmas()
	{
		if (!$this->db->table_exists('requests') || !$this->db->table_exists('users') || !$this->db->table_exists('konsultasi') || !$this->db->field_exists('remark', 'users')) {
			return array();
		}

		$this->db->select('users.remark, COUNT(konsultasi.kriteria) AS jumlah_selesai');
		$this->db->from('requests');
		$this->db->join('users', 'requests.user_id = users.userId');
		$this->db->join('konsultasi', 'requests.request_id = konsultasi.request_id');
		$this->db->where('konsultasi.kriteria', 'Selesai Konsultasi');
		$this->db->group_by('users.remark');
		return $this->db->get()->result();
	}

	public function kunjungan_nakes_per_puskesmas()
	{
		if (!$this->db->table_exists('requests') || !$this->db->table_exists('users') || !$this->db->table_exists('konsultasi') || !$this->db->field_exists('remark', 'users')) {
			return array();
		}

		$this->db->select('users.remark, COUNT(konsultasi.kriteria) AS jumlah_kunjungan');
		$this->db->from('requests');
		$this->db->join('users', 'requests.user_id = users.userId');
		$this->db->join('konsultasi', 'requests.request_id = konsultasi.request_id');
		$this->db->where('konsultasi.kriteria', 'Kunjungan Nakes');
		$this->db->group_by('users.remark');
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
}
