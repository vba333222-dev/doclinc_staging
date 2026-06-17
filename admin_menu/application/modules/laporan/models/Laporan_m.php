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
		return $this->db->query("SELECT
                                        t_pengaduan.id_pengaduan, 
                                        t_pengaduan.status,
                                        t_pengaduan.kategori, 
                                        kecamatan.nama_kecamatan, 
                                        t_pengaduan.deskripsi, 
                                        t_pengaduan.nama,
                                        t_pengaduan.email, 
                                        t_pengaduan.tanggal, 
                                        t_pengaduan.file, 
                                        t_pengaduan.dinas
                                    FROM
                                        t_pengaduan
                                        INNER JOIN
                                        kecamatan
                                        ON 
                                            t_pengaduan.lokasi = kecamatan.id_kecamatan
                                    ORDER BY t_pengaduan.created_at DESC");
	}

	public function get_laporan_perhari($tanggal = null, $puskesmas = null, $dokter = null)
	{
		$this->db->select("DATE(konsultasi.create_date) as tanggal, konsultasi.create_date as waktu, m_dokter.name,konsultasi.diagnosa, m_puskesmas.nama_puskesmas, users.nama as nama_user");
		$this->db->from("requests");
		$this->db->join("users", "users.userId = requests.user_id",);
		$this->db->join("m_puskesmas", "m_puskesmas.kode_pkm = users.remark",);
		$this->db->join("m_dokter", "m_dokter.professional_id = requests.dokter_id",);
		$this->db->join("konsultasi", "konsultasi.request_id = requests.request_id");

		if ($tanggal) {
			$this->db->where('DATE(konsultasi.create_date)', $tanggal);
		}
		if ($puskesmas) {
			$this->db->where('m_puskesmas.nama_puskesmas', $puskesmas);
		}
		if ($dokter) {
			$this->db->where('m_dokter.name', $dokter);
		}

		$this->db->order_by("m_puskesmas.nama_puskesmas", "ASC");
		$query = $this->db->get();
		return $query->result();
	}

	public function get_laporan_perhari_jumlah_pasien($tanggal = null, $puskesmas = null, $dokter = null)
	{
		$this->db->select("DATE(konsultasi.create_date) as tanggal, konsultasi.create_date as waktu, m_dokter.name,konsultasi.diagnosa, m_puskesmas.nama_puskesmas, users.nama as nama_user, COUNT(*) as jumlah_pasien");
		$this->db->from("requests");
		$this->db->join("users", "users.userId = requests.user_id",);
		$this->db->join("m_puskesmas", "m_puskesmas.kode_pkm = users.remark",);
		$this->db->join("m_dokter", "m_dokter.professional_id = requests.dokter_id",);
		$this->db->join("konsultasi", "konsultasi.request_id = requests.request_id");

		if ($tanggal) {
			$this->db->where('DATE(konsultasi.create_date)', $tanggal);
		}
		if ($puskesmas) {
			$this->db->where('m_puskesmas.nama_puskesmas', $puskesmas);
		}
		if ($dokter) {
			$this->db->where('m_dokter.name', $dokter);
		}

		$this->db->order_by("m_puskesmas.nama_puskesmas", "ASC");
		$this->db->group_by("m_puskesmas.nama_puskesmas");
		$query = $this->db->get();
		return $query->result();
	}

	public function get_laporan_perhari_jumlah_diagnosa($tanggal = null, $puskesmas = null, $dokter = null)
	{
		$this->db->select("DATE(konsultasi.create_date) as tanggal, konsultasi.create_date as waktu, m_dokter.name,konsultasi.diagnosa, m_puskesmas.nama_puskesmas, users.nama as nama_user, COUNT(*) as jumlah_diagnosa");
		$this->db->from("requests");
		$this->db->join("users", "users.userId = requests.user_id",);
		$this->db->join("m_puskesmas", "m_puskesmas.kode_pkm = users.remark",);
		$this->db->join("m_dokter", "m_dokter.professional_id = requests.dokter_id",);
		$this->db->join("konsultasi", "konsultasi.request_id = requests.request_id");

		if ($tanggal) {
			$this->db->where('DATE(konsultasi.create_date)', $tanggal);
		}
		if ($puskesmas) {
			$this->db->where('m_puskesmas.nama_puskesmas', $puskesmas);
		}
		if ($dokter) {
			$this->db->where('m_dokter.name', $dokter);
		}

		$this->db->order_by("m_puskesmas.nama_puskesmas", "ASC");
		$this->db->order_by("jumlah_diagnosa", "DESC");
		$this->db->group_by(array("m_puskesmas.nama_puskesmas", "konsultasi.diagnosa"));
		$query = $this->db->get();
		return $query->result();
	}
}
