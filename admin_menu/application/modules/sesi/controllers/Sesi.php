<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Panel sesi & keamanan (baca saja).
 *
 * Halaman ini menjawab pertanyaan yang biasanya harus ditelusuri manual:
 * sesi siapa yang sedang terikat, apa yang terakhir terjadi pada sesi itu, dan
 * konfigurasi keamanan mana yang benar-benar berlaku saat ini. Nilainya justru
 * pada kejujuran tampilannya - termasuk menampilkan hal yang belum beres
 * (misalnya `display_errors` dan `log_errors` yang mati) supaya tidak terlupa.
 */
class Sesi extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Sesi_m');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
		if ($this->session->userdata('level') !== 'admin') {
			show_error('Akses admin diperlukan.', 403, 'Akses ditolak');
		}
	}

	public function index()
	{
		$this->session->set_flashdata('title', 'Sesi dan keamanan');
		$schema_ready = $this->Sesi_m->schemaReady();
		$this->load->view('commons/header');
		$this->load->view('sesi_v', array(
			'schema_ready' => $schema_ready,
			'bindings' => $schema_ready ? $this->Sesi_m->getBindings() : array(),
			'summary' => $this->Sesi_m->getSummary(),
			'audit' => $schema_ready ? $this->Sesi_m->getSessionAudit(12) : array(),
			'snapshot' => $this->Sesi_m->getRuntimeSnapshot(),
		));
		$this->load->view('commons/footer');
	}
}
