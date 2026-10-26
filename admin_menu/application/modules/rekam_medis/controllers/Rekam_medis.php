<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Rekam_medis extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Rekam_medis_m');
		if ($this->session->userdata('is_login') == FALSE) {
			if ($this->input->is_ajax_request()) {
				$this->output_json(array(
					'success' => false,
					'status' => 'error',
					'message' => 'Sesi admin berakhir. Silakan login ulang.'
				), 401);
				exit;
			}
			redirect('/', 'refresh');
		}
		if ($this->session->userdata('level') !== 'admin') {
			if ($this->input->is_ajax_request()) {
				$this->output_json(array(
					'success' => false,
					'status' => 'error',
					'message' => 'Akses admin diperlukan.'
				), 403);
				exit;
			}
			redirect('home', 'refresh');
		}
	}

	private function output_json($payload, $http_status = 200)
	{
		$this->output
			->set_status_header($http_status)
			->set_content_type('application/json')
			->set_output(json_encode($payload));
	}

	/** Baris per halaman daftar arsip. */
	const PER_PAGE = 25;

	private function filters()
	{
		return array(
			'keyword' => trim((string) $this->input->post('keyword', TRUE)),
			'puskesmas' => trim((string) $this->input->post('puskesmas', TRUE)),
			'period' => trim((string) $this->input->post('period', TRUE)),
		);
	}

	/**
	 * Halaman yang diminta tombol "Sebelumnya"/"Berikutnya". Tombol itu berada
	 * di dalam form filter, jadi filter yang sedang aktif ikut terkirim dan
	 * tidak hilang saat berpindah halaman.
	 */
	private function requested_page()
	{
		$page = (int) $this->input->post('page', TRUE);

		return $page > 0 ? $page : 1;
	}

	/**
	 * Apakah isi klinis bisa dibuka di lingkungan ini. Hanya membaca feature
	 * flag tanpa query: hak akses per akun tetap diputuskan gateway saat rekam
	 * dibuka, karena keputusan itu memerlukan alasan dan jejak audit.
	 */
	private function clinical_detail_ready()
	{
		$libraries = dirname(APPPATH, 2) . '/application/libraries/';
		foreach (array('Doclinc_feature_flags', 'Admin_capability_flags') as $class) {
			if (!is_file($libraries . $class . '.php')) {
				return false;
			}
			require_once $libraries . $class . '.php';
		}

		return Admin_capability_flags::enabled('clinical_audit');
	}

	public function index()
	{
		$this->session->set_flashdata('title', 'Pemantauan layanan');
		$this->session->set_flashdata('active_tab_rekam_medis', 'active');

		$filters = $this->input->method(TRUE) === 'POST'
			? $this->filters()
			: array('keyword' => '', 'puskesmas' => '', 'period' => '30');

		$total = $this->Rekam_medis_m->count_records($filters);
		$pages = max(1, (int) ceil($total / self::PER_PAGE));
		$page = min($this->requested_page(), $pages);
		$offset = ($page - 1) * self::PER_PAGE;
		$records = $total > 0 ? $this->Rekam_medis_m->get_records($filters, self::PER_PAGE, $offset) : array();

		$x['filters'] = $filters;
		$x['summary'] = $this->Rekam_medis_m->get_summary($filters);
		$x['records'] = $records;
		$x['puskesmas_options'] = $this->Rekam_medis_m->get_puskesmas_options();
		$x['pagination'] = array(
			'page' => $page,
			'pages' => $pages,
			'total' => $total,
			'per_page' => self::PER_PAGE,
			'from' => $total > 0 ? $offset + 1 : 0,
			'to' => $offset + count($records),
		);
		$x['clinical_ready'] = $this->clinical_detail_ready();

		$this->load->view('commons/header');
		$this->load->view('rekam_medis_v', $x);
		$this->load->view('commons/footer');
	}

	public function detail()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output_json(array('success' => false, 'status' => 'error', 'message' => 'Metode tidak diizinkan.'), 405);
			return;
		}
		$gateway_file = APPPATH . 'libraries/Admin_clinical_access_gateway.php';
		if (is_file($gateway_file)) require_once $gateway_file;
		$gateway = new Admin_clinical_access_gateway($this);
		if (!$gateway->featureEnabled()) {
			$this->output_json(array('success' => false, 'status' => 'error', 'message' => 'Detail klinis tidak tersedia untuk Admin.'), 403);
			return;
		}
		$record_id = (int) $this->input->post('record_id', TRUE);
		$context = $this->Rekam_medis_m->get_record_audit_context($record_id);
		if (!$context) {
			$this->output_json(array('success' => false, 'status' => 'error', 'message' => 'Rekam medis tidak ditemukan.'), 404);
			return;
		}
		$decision = $gateway->authorizeRecord($record_id, array(
			'reason_code' => $this->input->post('reason_code', TRUE),
			'reason_text' => $this->input->post('reason_text', TRUE),
		), $context);
		if (empty($decision['allowed'])) {
			$this->output_json(array('success' => false, 'status' => 'error', 'message' => !empty($decision['requires_reason']) ? 'Alasan akses diperlukan.' : 'Akses detail klinis ditolak.', 'reason' => $decision['error']), !empty($decision['requires_reason']) ? 428 : 403);
			return;
		}
		$detail = $this->Rekam_medis_m->get_record_detail($record_id);
		$this->output_json(array('success' => true, 'status' => 'ok', 'data' => $detail), 200);
	}
}
