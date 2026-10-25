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
		$this->session->set_flashdata('title', 'Monitoring Layanan Kesehatan');
		$this->session->set_flashdata('active_tab_dashboard', 'active');
		unset($_SESSION['active_tab_keluhan']);

		/* Filter dibaca lewat query string (base_url() di view, bukan site_url()),
		   karena `index_page` bernilai 'index.php?' dan parameter formulir yang
		   masuk ke URI dijawab 400 oleh filter URI CodeIgniter. */
		$filters = array(
			'periode' => trim((string) $this->input->get('periode', TRUE)),
			'dari' => trim((string) $this->input->get('dari', TRUE)),
			'sampai' => trim((string) $this->input->get('sampai', TRUE)),
			'puskesmas' => trim((string) $this->input->get('puskesmas', TRUE)),
		);

		$period = $this->Home_m->monitoring_resolve_period($filters['periode'], $filters['dari'], $filters['sampai']);
		$filters['periode'] = $period['preset'];

		$puskesmas_options = $this->Home_m->monitoring_puskesmas_options();
		if ($filters['puskesmas'] !== '' && $filters['puskesmas'] !== '__legacy__') {
			$valid = false;
			foreach ($puskesmas_options as $option) {
				if ((string) $option->kode_pkm === $filters['puskesmas']) {
					$valid = true;
					break;
				}
			}
			if (!$valid) {
				$filters['puskesmas'] = '';
			}
		}

		/* Pengecualian diambil lebih dulu karena penanda tindak lanjutnya perlu
		   id permintaan yang benar-benar ditampilkan - bukan seluruh tabel. */
		$pengecualian = $this->Home_m->monitoring_exceptions(10, $filters['puskesmas']);
		$followup_ids = array();
		if (isset($pengecualian['rows']) && is_array($pengecualian['rows'])) {
			foreach ($pengecualian['rows'] as $exception_row) {
				$followup_ids[] = (int) $exception_row->request_id;
			}
		}

		$x = array(
			'filters' => $filters,
			'periode_options' => $this->Home_m->monitoring_period_options(),
			'puskesmas_options' => $puskesmas_options,
			'monitoring' => array(
				'periode' => $period,
				'ringkasan' => $this->Home_m->monitoring_period_summary($period, $filters['puskesmas']),
				'aktif' => $this->Home_m->monitoring_outstanding($filters['puskesmas']),
				'kesiapan' => $this->Home_m->monitoring_facility_readiness(),
				'seri' => $this->Home_m->monitoring_series($period, $filters['puskesmas']),
				'fasilitas' => $this->Home_m->monitoring_facility_comparison($period, $filters['puskesmas']),
				'respons' => $this->Home_m->monitoring_response_median($period, $filters['puskesmas']),
				'pengecualian' => $pengecualian,
				'mutu_data' => $this->Home_m->monitoring_data_quality(),
				'kesegaran' => $this->Home_m->monitoring_freshness(),
				'tindak_lanjut' => $this->Home_m->monitoring_followup_map($followup_ids),
			),
			/* Panel lama dipertahankan apa adanya supaya tidak ada informasi yang
			   hilang dari dasbor sebelumnya. */
			'recent_activity' => $this->Home_m->get_recent_request_events(8),
			'diagnosis_analytics' => $this->Home_m->get_diagnosis_dashboard(30),
			'nakes_presence_enabled' => $this->config->item('nakes_presence_enabled') === true,
		);

		$this->load->view('commons/header');
		$this->load->view('home_v', $x);
		$this->load->view('commons/footer');
	}
	/**
	 * Irisan tipis untuk pembaruan berkala dasbor.
	 *
	 * Otorisasi tidak ditulis ulang di sini: konstruktor kelas ini sudah menolak
	 * permintaan tanpa sesi dan permintaan yang bukan admin, dan konstruktor
	 * berjalan untuk setiap metode. Menambah pemeriksaan kedua hanya akan
	 * membuat dua definisi yang bisa berbeda.
	 */
	public function live_snapshot()
	{
		$this->output->set_content_type('application/json')->set_header('Cache-Control: no-store');

		if ($this->input->method(TRUE) !== 'GET') {
			$this->admin_monitoring_response(405, false, 'method_not_allowed');
			return;
		}

		/* Filter Puskesmas divalidasi persis seperti index(), supaya endpoint ini
		   tidak menjadi jalur kedua yang menerima nilai sembarang. Nilainya juga
		   sudah lewat escape() di model, jadi aman untuk SQL. */
		$filter = trim((string) $this->input->get('puskesmas', TRUE));
		if ($filter !== '' && $filter !== '__legacy__') {
			$valid = false;
			foreach ($this->Home_m->monitoring_puskesmas_options() as $option) {
				if ((string) $option->kode_pkm === $filter) {
					$valid = true;
					break;
				}
			}
			if (!$valid) {
				$filter = '';
			}
		}

		$this->admin_monitoring_response(200, true, 'ok', $this->Home_m->monitoring_live_slice($filter));
	}

	/**
	 * Menandai satu permintaan sebagai sudah ditindaklanjuti.
	 *
	 * POST saja: ini menulis data, dan penulisan lewat GET dapat terpicu oleh
	 * prefetch peramban atau tautan yang dibagikan. Otorisasi tetap dari
	 * konstruktor kelas ini, dan CSRF ditangani CodeIgniter secara global
	 * karena `csrf_protection` aktif.
	 */
	public function followup_mark()
	{
		$this->output->set_content_type('application/json')->set_header('Cache-Control: no-store');

		if ($this->input->method(TRUE) !== 'POST') {
			$this->admin_monitoring_response(405, false, 'method_not_allowed');
			return;
		}

		$result = $this->Home_m->monitoring_mark_followup(
			$this->input->post('request_id', TRUE),
			$this->input->post('note', TRUE)
		);

		$this->admin_monitoring_response($result['ok'] ? 200 : 422, $result['ok'], $result['kode'], $result);
	}

	/** Mencabut penanda, supaya penandaan yang keliru dapat dibatalkan. */
	public function followup_unmark()
	{
		$this->output->set_content_type('application/json')->set_header('Cache-Control: no-store');

		if ($this->input->method(TRUE) !== 'POST') {
			$this->admin_monitoring_response(405, false, 'method_not_allowed');
			return;
		}

		$result = $this->Home_m->monitoring_unmark_followup($this->input->post('request_id', TRUE));

		$this->admin_monitoring_response($result['ok'] ? 200 : 422, $result['ok'], $result['kode'], $result);
	}

	private function admin_monitoring_response($status, $success, $code, array $data = array())
	{
		$body = array('success' => (bool) $success, 'safe_error_code' => (string) $code);
		if ($success) {
			$body['data'] = $data;
		}
		$this->output->set_status_header((int) $status)->set_output(json_encode($body));
	}



	public function nakes_presence_snapshot()
	{
		$this->output->set_content_type('application/json')->set_header('Cache-Control: no-store');
		if ($this->input->method(TRUE) !== 'GET') {
			$this->admin_presence_response(405, false, 'method_not_allowed');
			return;
		}
		if (!empty($this->input->get(null, true))) {
			$this->admin_presence_response(400, false, 'query_not_allowed');
			return;
		}
		if ($this->config->item('nakes_presence_enabled') !== true) {
			$this->admin_presence_response(403, false, 'feature_disabled');
			return;
		}

		$email = trim((string) $this->session->userdata('email'));
		$actor = array(
			'authenticated' => $this->session->userdata('is_login') == true,
			'role' => 'admin',
			'status' => '',
			'must_change_password' => false,
		);
		if ($email !== '' && $this->db->table_exists('users')) {
			$user = $this->db->select('userId, role, status')->where('email', $email)->limit(1)->get('users')->row();
			if ($user && (string) $user->role === 'admin') {
				$actor['user_id'] = (int) $user->userId;
				$actor['status'] = (string) $user->status;
			}
		}

		$service_file = dirname(APPPATH, 2) . '/application/libraries/Nakes_presence_service.php';
		if (!is_file($service_file)) {
			$this->admin_presence_response(503, false, 'service_unavailable');
			return;
		}
		require_once $service_file;
		$service = new Nakes_presence_service(
			$this->db,
			(int) $this->config->item('nakes_presence_online_timeout_seconds'),
			45
		);
		$result = $service->snapshot($actor, 500);
		if (empty($result['ok'])) {
			$this->admin_presence_response($result['code'] === 'schema_unavailable' ? 503 : 403, false, $result['code']);
			return;
		}
		$this->admin_presence_response(200, true, 'ok', $result['data']);
	}

	private function admin_presence_response($status, $success, $code, array $data = array())
	{
		$body = array('success' => (bool) $success, 'safe_error_code' => (string) $code);
		if ($success) {
			$body['data'] = $data;
		}
		$this->output->set_status_header((int) $status)->set_output(json_encode($body));
	}
	public function change_password()
	{
		$email = $this->session->userdata('email');
		$user_id = (int) $this->session->userdata('id');
		$password = (string) $this->input->post('old_password');
		$new_password = doclinc_password_hash((string) $this->input->post('new_password'));
		$user_query = $this->Home_m->get_user_by_email($email);
		if ($user_query->num_rows() > 0 && doclinc_password_verify($password, (string) $user_query->row()->password)) {
			$updated = false;
			if ($this->config->item('single_active_session_enabled') === true) {
				$service_file = dirname(APPPATH, 2) . '/application/libraries/Session_binding_service.php';
				if (is_file($service_file)) {
					require_once $service_file;
					$token = (new Session_binding_service($this->db))->rotatePassword(
						$user_id,
						$this->session->userdata('normal_session_token'),
						(string) $user_query->row()->password,
						$new_password
					);
					if (is_string($token)) {
						try {
							$this->session->set_userdata('normal_session_token', $token);
							$stored_token = $this->session->userdata('normal_session_token');
							$updated = is_string($stored_token) && hash_equals($token, $stored_token);
						} catch (Throwable $exception) {
							$updated = false;
						}
					}
				}
			} else {
				$updated = $this->Home_m->change_password($user_id, $new_password);
			}
			if (!$updated) {
				$this->session->sess_destroy();
				redirect('login?session=expired', 'refresh');
				return;
			}
			$info = '<div class="alert alert-success alert-dismissible fade show shadow-sm border border-success animate__animated animate__bounceInUp" role="alert">
		                    <button type="button" class="close" data-dismiss="alert" aria-label="Tutup">
		                        <span aria-hidden="true">&times;</span>
		                    </button>
		                    <p class="font-weight-bold mb-0">Berhasil!</p>
						    <hr>
						    <p class="mb-0">Kamu baru saja mengganti password.</p>
		                </div>';
			$this->session->set_flashdata('info', $info);
		} else {
			$info = '<div class="alert alert-danger alert-dismissible fade show shadow-sm border border-danger animate__animated animate__bounceInUp" role="alert">
		                    <button type="button" class="close" data-dismiss="alert" aria-label="Tutup">
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
}
