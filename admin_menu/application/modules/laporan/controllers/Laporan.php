<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Laporan extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Laporan_m');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
	}
	public function index()
	{
		$this->session->set_flashdata('title', 'Laporan');
		$this->session->set_flashdata('active_tab_laporan', 'active');
		$year = date('Y');
		$month = date('m');

		if ($this->input->is_ajax_request()) {
			$tipe = $this->input->get('tipe'); // perhari / perminggu / perbulan (opsional untuk masa depan)

			if ($tipe === 'perhari') {
				$tanggal = $this->input->get('tanggal');
				$tanggal_awal = $this->input->get('tanggal_awal');
				$tanggal_akhir = $this->input->get('tanggal_akhir');
				$puskesmas = $this->input->get('puskesmas');
				$dokter = $this->input->get('dokter');
				$status = $this->input->get('status');
				$keyword = $this->input->get('keyword');

				$tipeBtn = $this->input->get('tipeBtn');
				$summary = array();
				$breakdown = array();

				if ($tipeBtn == 1) {
					$data = $this->Laporan_m->get_laporan_perhari($tanggal, $puskesmas, $dokter, $status, $keyword, $tanggal_awal, $tanggal_akhir);
					$summary = $this->Laporan_m->summarize_report_rows($data);
					$breakdown = $this->Laporan_m->build_puskesmas_breakdown($data);
				} elseif ($tipeBtn == 2) {
					$data = $this->Laporan_m->get_laporan_perhari_jumlah_pasien($tanggal, $puskesmas, $dokter, $status, $keyword, $tanggal_awal, $tanggal_akhir);
				} elseif ($tipeBtn == 3) {
					$data = $this->Laporan_m->get_laporan_perhari_jumlah_diagnosa($tanggal, $puskesmas, $dokter, $status, $keyword, $tanggal_awal, $tanggal_akhir);
				}
			} elseif ($tipe === 'perminggu') {
				$tanggal_awal = $this->input->get('tanggal_awal'); // optional filter mingguan
				$tanggal_akhir = $this->input->get('tanggal_akhir');
				$data = array();
			} else {
				// Jika tipe tidak valid
				$this->output->set_content_type('application/json')->set_output(json_encode(['status' => 'error', 'message' => 'Tipe laporan tidak dikenali']));
				return;
			}

			$this->output->set_content_type('application/json')->set_output(json_encode([
				'status' => 'success',
				'data' => $data ?? [],
				'summary' => $summary ?? array(),
				'breakdown' => $breakdown ?? array()
			]));
		} else {
			// Akses biasa (non-AJAX)
			$x['puskesmas_options'] = $this->Laporan_m->get_puskesmas_options();
			$this->load->view('commons/header');
			$this->load->view('laporan_v', $x);
			$this->load->view('commons/footer');
		}
	}
}
