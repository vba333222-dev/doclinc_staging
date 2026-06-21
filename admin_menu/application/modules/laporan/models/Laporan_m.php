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

	private function apply_consultation_report_base($aggregate_select = '')
	{
		$has_konsultasi = $this->db->table_exists('konsultasi');
		$has_puskesmas = $this->db->table_exists('m_puskesmas') && $this->db->field_exists('remark', 'users');
		$has_dokter = $this->db->table_exists('m_dokter');

		$date_expr = $has_konsultasi ? 'konsultasi.create_date' : 'medicalrecords.created_at';
		$diagnosa_expr = $has_konsultasi ? 'konsultasi.diagnosa' : 'medicalrecords.diagnosis';
		$puskesmas_expr = $has_puskesmas ? "COALESCE(m_puskesmas.nama_puskesmas, users.remark, '-')" : "'-'";
		$dokter_expr = $has_dokter ? "COALESCE(m_dokter.name, dokter_user.nama, '-')" : "COALESCE(dokter_user.nama, '-')";

		$select = "DATE($date_expr) as tanggal, $date_expr as waktu, $dokter_expr AS name, $diagnosa_expr AS diagnosa, $puskesmas_expr AS nama_puskesmas, users.nama as nama_user";
		if ($aggregate_select !== '') {
			$select .= ', ' . $aggregate_select;
		}

		$this->db->select($select, FALSE);
		$this->db->from('requests');
		$this->db->join('users', 'users.userId = requests.user_id', 'left');
		$this->db->join('users dokter_user', 'dokter_user.userId = requests.dokter_id', 'left');
		if ($has_puskesmas) {
			$this->db->join('m_puskesmas', 'm_puskesmas.kode_pkm = users.remark', 'left');
		}
		if ($has_dokter) {
			$this->db->join('m_dokter', 'm_dokter.professional_id = requests.dokter_id', 'left');
		}
		if ($has_konsultasi) {
			$this->db->join('konsultasi', 'konsultasi.request_id = requests.request_id', 'left');
			$this->db->where('konsultasi.request_id IS NOT NULL', NULL, FALSE);
		} else {
			$this->db->join('medicalrecords', 'medicalrecords.request_id = requests.request_id', 'left');
			$this->db->where('medicalrecords.request_id IS NOT NULL', NULL, FALSE);
		}

		return array($date_expr, $diagnosa_expr, $puskesmas_expr, $dokter_expr);
	}

	public function get_laporan_perhari($tanggal = null, $puskesmas = null, $dokter = null)
	{
		if (!$this->can_query_consultation_reports()) {
			return array();
		}

		list($date_expr, $diagnosa_expr, $puskesmas_expr, $dokter_expr) = $this->apply_consultation_report_base();

		if ($tanggal) {
			$this->db->where("DATE($date_expr) = " . $this->db->escape($tanggal), NULL, FALSE);
		}
		if ($puskesmas) {
			$this->db->where($puskesmas_expr . ' = ' . $this->db->escape($puskesmas), NULL, FALSE);
		}
		if ($dokter) {
			$this->db->where($dokter_expr . ' = ' . $this->db->escape($dokter), NULL, FALSE);
		}

		$this->db->order_by("nama_puskesmas", "ASC");
		$query = $this->db->get();
		return $query->result();
	}

	public function get_laporan_perhari_jumlah_pasien($tanggal = null, $puskesmas = null, $dokter = null)
	{
		if (!$this->can_query_consultation_reports()) {
			return array();
		}

		list($date_expr, $diagnosa_expr, $puskesmas_expr, $dokter_expr) = $this->apply_consultation_report_base('COUNT(*) as jumlah_pasien');

		if ($tanggal) {
			$this->db->where("DATE($date_expr) = " . $this->db->escape($tanggal), NULL, FALSE);
		}
		if ($puskesmas) {
			$this->db->where($puskesmas_expr . ' = ' . $this->db->escape($puskesmas), NULL, FALSE);
		}
		if ($dokter) {
			$this->db->where($dokter_expr . ' = ' . $this->db->escape($dokter), NULL, FALSE);
		}

		$this->db->order_by("nama_puskesmas", "ASC");
		$this->db->group_by("nama_puskesmas");
		$query = $this->db->get();
		return $query->result();
	}

	public function get_laporan_perhari_jumlah_diagnosa($tanggal = null, $puskesmas = null, $dokter = null)
	{
		if (!$this->can_query_consultation_reports()) {
			return array();
		}

		list($date_expr, $diagnosa_expr, $puskesmas_expr, $dokter_expr) = $this->apply_consultation_report_base('COUNT(*) as jumlah_diagnosa');

		if ($tanggal) {
			$this->db->where("DATE($date_expr) = " . $this->db->escape($tanggal), NULL, FALSE);
		}
		if ($puskesmas) {
			$this->db->where($puskesmas_expr . ' = ' . $this->db->escape($puskesmas), NULL, FALSE);
		}
		if ($dokter) {
			$this->db->where($dokter_expr . ' = ' . $this->db->escape($dokter), NULL, FALSE);
		}

		$this->db->order_by("nama_puskesmas", "ASC");
		$this->db->order_by("jumlah_diagnosa", "DESC");
		$this->db->group_by(array("nama_puskesmas", "diagnosa"));
		$query = $this->db->get();
		return $query->result();
	}
}
