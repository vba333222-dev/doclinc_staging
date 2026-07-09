<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Home extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Home_m');
		$this->load->helper('password_compat');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
		if ($this->session->userdata('level') !== 'admin') {
			show_error('Akses admin diperlukan.', 403, 'Akses ditolak');
		}
	}
	public function index()
	{
		$this->session->set_flashdata('title', 'Dashboard');
		$this->session->set_flashdata('active_tab_dashboard', 'active');
		unset($_SESSION['active_tab_keluhan']);
		$x['operational_summary'] = $this->Home_m->get_operational_summary();
		$x['puskesmas_distribution'] = $this->Home_m->get_puskesmas_distribution(8);
		$x['attention_requests'] = $this->Home_m->get_attention_requests(8);
		$x['recent_activity'] = $this->Home_m->get_recent_request_events(8);
		$x['diagnosis_analytics'] = $this->Home_m->get_diagnosis_dashboard(30);

		$this->load->view('commons/header');
		$this->load->view('home_v', $x);
		$this->load->view('commons/footer');
	}
	public function change_password()
	{
		$email = $this->session->userdata('email');
		$password = (string) $this->input->post('old_password');
		$new_password = doclinc_password_hash((string) $this->input->post('new_password'));
		$user_query = $this->Home_m->get_user_by_email($email);
		if ($user_query->num_rows() > 0 && doclinc_password_verify($password, (string) $user_query->row()->password)) {
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
		redirect($_SERVER['HTTP_REFERER']);
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

		$this->output->set_content_type('application/json')->set_output(json_encode($datas));
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

		$this->output->set_content_type('application/json')->set_output(json_encode($data));
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

		$this->output->set_content_type('application/json')->set_output(json_encode($data));
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
		if ($total_konsultasi <= 0) {
			$list = [];
		}

		$data = [
			'list' => $list,
			'konsultasi_map' => $konsultasi_map,
			'kunjungan_map' => $kunjungan_map,
			'total_konsultasi' => $total_konsultasi,
		];

		$this->output->set_content_type('application/json')->set_output(json_encode($data));
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

		$this->output->set_content_type('application/json')->set_output(json_encode($data));
	}
}
