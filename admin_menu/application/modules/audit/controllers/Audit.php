<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Penampil audit: siapa melakukan apa, kapan, dari mana.
 *
 * Halaman ini BACA SAJA. Tidak ada aksi tulis di sini - bukan karena belum
 * dibuat, tetapi karena penampil bukti tidak boleh bisa mengubah buktinya.
 *
 * Filter memakai POST lalu diarahkan ke URL segmen, mengikuti pola yang sudah
 * dipakai halaman diagnosis dan master wilayah: panel ini memakai
 * `index_page = 'index.php?'` sehingga query string ditolak filter URI
 * CodeIgniter (dijawab 400).
 */
class Audit extends MX_Controller
{
	/** Batas baris yang ditampilkan sekali muat. */
	const ROW_LIMIT = 200;

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Audit_m');
		$this->load->helper('form');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
		if ($this->session->userdata('level') !== 'admin') {
			show_error('Akses admin diperlukan.', 403, 'Akses ditolak');
		}
	}

	public function index($days = null, $action = '-', $actor_user_id = 0)
	{
		$this->session->set_flashdata('title', 'Penampil audit');
		$filters = $this->filters_from_args($days, $action, $actor_user_id);
		$schema_ready = $this->Audit_m->schemaReady();

		$this->load->view('commons/header');
		$this->load->view('audit_v', array(
			'schema_ready' => $schema_ready,
			'filters' => $filters,
			'total' => $schema_ready ? $this->Audit_m->countLogs($filters) : 0,
			'logs' => $schema_ready ? $this->Audit_m->getLogs($filters, self::ROW_LIMIT) : array(),
			'action_summary' => $schema_ready ? $this->Audit_m->getActionSummary($filters, 8) : array(),
			'actor_summary' => $schema_ready ? $this->Audit_m->getActorSummary($filters, 8) : array(),
			'coverage' => $schema_ready ? $this->Audit_m->getCoverage($filters) : array('terlama' => '', 'terbaru' => ''),
			'action_options' => $schema_ready ? $this->Audit_m->getActionOptions() : array(),
			'actor_options' => $schema_ready ? $this->Audit_m->getActorOptions() : array(),
			'row_limit' => self::ROW_LIMIT,
		));
		$this->load->view('commons/footer');
	}

	/** Terapkan filter dari formulir (POST), lalu arahkan ke URL segmen. */
	public function filter()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect('audit', 'refresh');
			return;
		}
		$filters = $this->Audit_m->normalizeFilters(array(
			'days' => (string) $this->input->post('days', TRUE),
			'action' => (string) $this->input->post('action', TRUE),
			'actor_user_id' => (string) $this->input->post('actor_user_id', TRUE),
		));
		redirect('audit/index/' . (int) $filters['days'] . '/' . $this->action_segment($filters['action']) . '/' . (int) $filters['actor_user_id'], 'refresh');
	}

	/**
	 * Segmen kosong tidak boleh dipakai untuk "semua aksi": ia akan menggeser
	 * posisi segmen berikutnya. Karena itu "semua aksi" diwakili tanda '-'.
	 */
	private function action_segment($action)
	{
		return (string) $action === '' ? '-' : (string) $action;
	}

	private function filters_from_args($days, $action, $actor_user_id)
	{
		$action = trim((string) $action);
		if ($action === '-') {
			$action = '';
		}
		return $this->Audit_m->normalizeFilters(array(
			'days' => (string) $days,
			'action' => $action,
			'actor_user_id' => (string) $actor_user_id,
		));
	}
}
