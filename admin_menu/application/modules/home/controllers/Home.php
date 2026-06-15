<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Home extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Home_m');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
	}
	public function index()
	{
		$this->session->set_flashdata('title', 'Dashboard');
		$this->session->set_flashdata('active_tab_dashboard', 'active');
		unset($_SESSION['active_tab_keluhan']);
		$year = date('Y');
		$month = date('m');
		$get_konsultasi_baru = $this->db->query("SELECT COUNT(request_id) AS jml FROM requests WHERE request_status='Pending'");
		$get_konsultasi_proses = $this->db->query("SELECT COUNT(request_id) AS jml FROM requests WHERE request_status='Accepted'");
		$get_konsultasi_selesai = $this->db->query("SELECT COUNT(request_id) AS jml FROM requests WHERE request_status='Completed'");
		$get_konsultasi_cancel = $this->db->query("SELECT COUNT(request_id) AS jml FROM requests WHERE request_status='Cancelled'");
		$x['konsultasi_baru'] = $get_konsultasi_baru->row()->jml;
		$x['konsultasi_proses'] = $get_konsultasi_proses->row()->jml;
		$x['konsultasi_selesai'] = $get_konsultasi_selesai->row()->jml;
		$x['konsultasi_cancel'] = $get_konsultasi_cancel->row()->jml;
		$x['konsultasi_baru_list'] = $this->Home_m->konsultasi_baru_list();
		$x['konsultasi_proses_list'] = $this->Home_m->konsultasi_proses_list();
		$x['konsultasi_selesai_list'] = $this->Home_m->konsultasi_selesai_list()->result();
		$x['selesai_konsultasi'] = $this->Home_m->selesai_konsultasi_per_puskesmas();
		$x['kunjungan_nakes'] = $this->Home_m->kunjungan_nakes_per_puskesmas();
		$bulan = array('01' => 'JANUARI', '02' => 'FEBRUARI', '03' => 'MARET', '04' => 'APRIL', '05' => 'MEI', '06' => 'JUNI', '07' => 'JULI', '08' => 'AGUSTUS', '09' => 'SEPTEMBER', '10' => 'OKTOBER', '11' => 'NOVEMBER', '12' => 'DESEMBER');
		$bulan_txt = '';
		$nilai_txt = '';
		for ($i = 1; $i <= 12; $i++) {
			$value_bln = str_pad($i, 2, "0", STR_PAD_LEFT);
			$bulan_txt .= "'" . $bulan[$value_bln] . "'";
			$data = $this->Home_m->get_konsul_perbulan(date('Y-' . $value_bln));
			$nilai_txt .= "'" . $data->row()->total_nilai . "'";
			if ($i < 12) {
				$bulan_txt .= ", ";
				$nilai_txt .= ", ";
			}
		}
		$x['bulan_txt'] = $bulan_txt;
		$x['nilai_txt'] = $nilai_txt;

		// Konversi ke array indexed by remark
		$konsultasi_map = [];
		foreach ($x['selesai_konsultasi'] as $row) {
			$konsultasi_map[$row->remark] = $row->jumlah_selesai;
		}

		$kunjungan_map = [];
		foreach ($x['kunjungan_nakes'] as $row) {
			$kunjungan_map[$row->remark] = $row->jumlah_kunjungan;
		}

		// ✅ Kirim hasil konversi ke view
		$x['konsultasi_map'] = $konsultasi_map;
		$x['kunjungan_map'] = $kunjungan_map;

		$this->load->view('commons/header');
		$this->load->view('home_v', $x);
		$this->load->view('commons/footer');
	}
	public function change_password()
	{
		$email = $this->session->userdata('email');
		$password = htmlspecialchars(sha1($this->input->post('old_password')));
		$new_password = htmlspecialchars(sha1($this->input->post('new_password')));
		$cek_old_password = $this->db->query("SELECT * FROM tbl_user WHERE email='$email' AND password='$password'");
		if ($cek_old_password->num_rows() > 0) {
			$this->Home_m->change_password($email, $new_password);
			$info = '<div class="alert alert-success alert-dismissible fade show shadow-sm border border-success animate__animated animate__bounceInUp" role="alert">
		                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
		                        <span aria-hidden="true">&times;</span>
		                    </button>
		                    <p class="font-weight-bold mb-0">Berhasil!</p>
						    <hr>
						    <p class="mb-0">Kamu baru saja mengganti password.</p>
		                </div>';
			$this->session->set_flashdata('info', $info);
		} else {
			$info = '<div class="alert alert-danger alert-dismissible fade show shadow-sm border border-danger animate__animated animate__bounceInUp" role="alert">
		                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
		                        <span aria-hidden="true">&times;</span>
		                    </button>
		                    <p class="font-weight-bold mb-0">Gagal!</p>
						    <hr>
						    <p class="mb-0">Kamu salah memasukkan password lama kamu.</p>
		                </div>';
			$this->session->set_flashdata('info', $info);
		}
		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'home';
		redirect($referer);
	}

	public function get_realtime_konsultasi()
	{
		$start = $this->input->get('start'); // format yyyy-mm-dd
		$end = $this->input->get('end');

		if ($start && $end) {
			$whereTanggal = "AND DATE(created_at) BETWEEN " . $this->db->escape($start) . " AND " . $this->db->escape($end);
		} else {
			$whereTanggal = "";
		}

		$get_konsultasi_baru = $this->db->query("SELECT COUNT(request_id) AS jml FROM requests WHERE request_status='Pending' $whereTanggal");
		$get_konsultasi_proses = $this->db->query("SELECT COUNT(request_id) AS jml FROM requests WHERE request_status='Accepted' $whereTanggal");
		$get_konsultasi_selesai = $this->db->query("SELECT COUNT(request_id) AS jml FROM requests WHERE request_status='Completed' $whereTanggal");
		$get_konsultasi_selesai_total = $this->db->query("SELECT COUNT(request_id) AS jml FROM requests WHERE request_status='Completed'");

		$data = [
			'baru' => (int)$get_konsultasi_baru->row()->jml,
			'proses' => (int)$get_konsultasi_proses->row()->jml,
			'selesai' => (int)$get_konsultasi_selesai->row()->jml,

		];
		$data['total'] = array_sum($data);
		$x = ['total_selesai' => (int)$get_konsultasi_selesai_total->row()->jml];

		$datas = array_merge($data, $x);

		echo json_encode($datas);
	}


	public function get_konsultasi_baru_ajax()
	{
		$tanggal = $this->input->get('tanggal'); // Format: yyyy-mm-dd

		$list = $this->Home_m->konsultasi_baru_list($tanggal); // kirim tanggal ke model
		$data = [];

		foreach ($list->result() as $row) {
			$created_at = new DateTime($row->created_at);
			$now = new DateTime();
			$interval = $created_at->diff($now);
			// $tertunda_jam = $interval->days * 24 + $interval->h;
			$tertunda_jam = sprintf(
				'%02d:%02d:%02d',
				$interval->days * 24 + $interval->h,
				$interval->i,
				$interval->s
			);

			$data[] = [
				'request_id' => $row->request_id,
				'nama' => $row->nama,
				'created_at' => $row->created_at,
				'tertunda_jam' => $tertunda_jam,
				'nama_puskesmas' => $row->nama_puskesmas,
				'dokter' => $row->name
			];
		}

		echo json_encode($data);
	}


	public function get_konsultasi_proses_ajax()
	{
		$tanggal = $this->input->get('tanggal'); // Format: yyyy-mm-dd

		$list = $this->Home_m->konsultasi_proses_list($tanggal);
		$data = [];

		foreach ($list->result() as $row) {
			$created_at = new DateTime($row->created_at);
			$now = new DateTime();
			$interval = $created_at->diff($now);
			// $tertunda_jam = $interval->days * 24 + $interval->h;
			$tertunda_jam = sprintf(
				'%02d:%02d:%02d',
				$interval->days * 24 + $interval->h,
				$interval->i,
				$interval->s
			);

			$data[] = [
				'request_id' => $row->request_id,
				'nama' => $row->nama,
				'created_at' => $row->created_at,
				'tertunda_jam' => $tertunda_jam,
				'nama_puskesmas' => $row->nama_puskesmas,
				'dokter' => $row->name
			];
		}

		echo json_encode($data);
	}

	public function ajax_konsultasi_selesai()
	{
		$list = $this->Home_m->konsultasi_selesai_list()->result();
		$selesai_konsultasi = $this->Home_m->selesai_konsultasi_per_puskesmas();
		$kunjungan_nakes = $this->Home_m->kunjungan_nakes_per_puskesmas();

		$konsultasi_map = [];
		foreach ($selesai_konsultasi as $row) {
			$konsultasi_map[$row->remark] = $row->jumlah_selesai;
		}

		$kunjungan_map = [];
		foreach ($kunjungan_nakes as $row) {
			$kunjungan_map[$row->remark] = $row->jumlah_kunjungan;
		}

		// Total dari semua total_selesai
		$total_konsultasi = array_sum(array_column($list, 'total_selesai'));

		$data = [
			'list' => $list,
			'konsultasi_map' => $konsultasi_map,
			'kunjungan_map' => $kunjungan_map,
			'total_konsultasi' => $total_konsultasi,
		];

		echo json_encode($data);
	}

	public function get_top_diagnosa()
	{
		$tanggal = $this->input->get('tanggal'); // Format: yyyy-mm-dd (optional)
		$limit = $this->input->get('limit') ?: 5; // Default top 5

		$list = $this->Home_m->get_top_diagnosa($limit);

		$data = [];
		foreach ($list as $row) {
			$data[] = [
				'diagnosa' => $row->diagnosa,
				'total' => (int)$row->jumlah
			];
		}

		echo json_encode($data);
	}
}
