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

	private function filters()
	{
		return array(
			'keyword' => trim((string) $this->input->post('keyword', TRUE)),
			'puskesmas' => trim((string) $this->input->post('puskesmas', TRUE)),
			'period' => trim((string) $this->input->post('period', TRUE)),
		);
	}

	public function index()
	{
		$this->session->set_flashdata('title', 'Rekam Medis');
		$this->session->set_flashdata('active_tab_rekam_medis', 'active');

		$filters = $this->input->method(TRUE) === 'POST'
			? $this->filters()
			: array('keyword' => '', 'puskesmas' => '', 'period' => '30');

		$x['filters'] = $filters;
		$x['summary'] = $this->Rekam_medis_m->get_summary($filters);
		$x['records'] = $this->Rekam_medis_m->get_records($filters);
		$x['puskesmas_options'] = $this->Rekam_medis_m->get_puskesmas_options();

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

		$record_id = (int) $this->input->post('record_id');
		if ($record_id < 1) {
			$this->output_json(array('success' => false, 'status' => 'error', 'message' => 'Rekam medis tidak ditemukan.'), 404);
			return;
		}

		$record = $this->Rekam_medis_m->get_record_detail($record_id);
		if (!$record) {
			$this->output_json(array('success' => false, 'status' => 'error', 'message' => 'Rekam medis tidak ditemukan.'), 404);
			return;
		}

		$this->output_json(array(
			'success' => true,
			'status' => 'success',
			'data' => $record,
		));
	}
}
