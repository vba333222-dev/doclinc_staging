<?php
class Home_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}
	public function get_konsul_perbulan($ym)
	{
		return $this->db->query("SELECT
                                        COUNT(request_id) AS total_nilai
                                    FROM
                                        requests
                                    WHERE
                                        DATE_FORMAT(`date`, '%Y-%m') = '$ym'");
	}

	public function konsultasi_baru_list($tanggal = null)
	{
		$this->db->select('requests.*, requests.created_at, users.nama, users.username, m_dokter.name, users.remark, m_puskesmas.nama_puskesmas');
		$this->db->from('requests');
		$this->db->join('m_dokter', 'requests.dokter_id = m_dokter.professional_id');
		$this->db->join('users', 'requests.user_id = users.userId');
		$this->db->join('m_puskesmas', 'users.remark = m_puskesmas.kode_pkm');
		$this->db->where('request_status', 'Pending');

		if (!empty($tanggal)) {
			$this->db->where('DATE(requests.created_at)', $tanggal); // filter tanggal
		}

		return $this->db->get();
	}


	public function konsultasi_proses_list($tanggal = null)
	{
		$this->db->select('requests.*, requests.created_at, users.nama, users.username, m_dokter.name, users.remark, m_puskesmas.nama_puskesmas');
		$this->db->from('requests');
		$this->db->join('m_dokter', 'requests.dokter_id = m_dokter.professional_id');
		$this->db->join('users', 'requests.user_id = users.userId');
		$this->db->join('m_puskesmas', 'users.remark = m_puskesmas.kode_pkm');
		$this->db->where('request_status', 'Accepted');
		// $this->db->group_by('users.remark');

		if (!empty($tanggal)) {
			$this->db->where('DATE(requests.created_at)', $tanggal); // filter tanggal
		}

		return $this->db->get();
	}

	public function konsultasi_selesai_list()
	{
		$this->db->select('m_puskesmas.nama_puskesmas,users.remark, COUNT(requests.request_id) AS total_selesai');
		$this->db->from('requests');
		$this->db->join('users', 'requests.user_id = users.userId');
		$this->db->join('m_puskesmas', 'users.remark = m_puskesmas.kode_pkm');
		$this->db->where('request_status', 'Completed');
		$this->db->group_by('m_puskesmas.nama_puskesmas');
		return $this->db->get();
	}

	public function selesai_konsultasi_per_puskesmas()
	{
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
		$this->db->select('diagnosa, COUNT(*) as jumlah');
		$this->db->from('konsultasi');
		// $this->db->where('DATE(created_at)', $tanggal);
		$this->db->group_by('diagnosa');
		$this->db->order_by('jumlah', 'DESC');
		$this->db->limit($limit);
		return $this->db->get()->result();
	}
}
