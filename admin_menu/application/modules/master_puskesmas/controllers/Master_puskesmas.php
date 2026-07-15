<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Master_puskesmas extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Master_puskesmas_m');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
		if ($this->session->userdata('level') !== 'admin') {
			redirect('home', 'refresh');
		}
	}

	public function index()
	{
		$this->session->set_flashdata('title', 'Master Puskesmas');
		$this->session->set_flashdata('active_tab_master_puskesmas', 'active');
		$data['puskesmas'] = $this->Master_puskesmas_m->get_all();
		$this->load->view('commons/header');
		$this->load->view('master_puskesmas_v', $data);
		$this->load->view('commons/footer');
	}

	public function store()
	{
		if (!$this->require_post()) {
			return;
		}
		$this->load->library('form_validation');
		$this->form_validation->set_rules('kode_pkm', 'Kode Puskesmas', 'trim|required', array('required' => 'Kode Puskesmas wajib diisi.'));
		$this->form_validation->set_rules('nama_puskesmas', 'Nama Puskesmas', 'trim|required', array('required' => 'Nama Puskesmas wajib diisi.'));

		$kode = trim((string) $this->input->post('kode_pkm', TRUE));
		if ($kode !== '' && $this->Master_puskesmas_m->code_exists($kode)) {
			$this->session->set_flashdata('error', 'Kode puskesmas sudah digunakan.');
			redirect('master_puskesmas', 'refresh');
			return;
		}
		$data = $this->build_payload();
		if ($this->form_validation->run() === FALSE || !$this->valid_coordinates($data)) {
			$this->session->set_flashdata('error', 'Data puskesmas belum valid.');
			redirect('master_puskesmas', 'refresh');
			return;
		}

		if ($this->Master_puskesmas_m->create($data)) {
			$this->session->set_flashdata('success', 'Puskesmas ditambahkan.');
		} else {
			$this->session->set_flashdata('error', 'Gagal menambahkan Puskesmas.');
		}
		redirect('master_puskesmas', 'refresh');
	}

	public function update($kode)
	{
		if (!$this->require_post()) {
			return;
		}
		$this->load->library('form_validation');
		$this->form_validation->set_rules('nama_puskesmas', 'Nama Puskesmas', 'trim|required', array('required' => 'Nama Puskesmas wajib diisi.'));
		$kode = trim((string) $kode);
		$data = $this->build_payload(false);

		if ($this->form_validation->run() === FALSE || !$this->valid_coordinates($data)) {
			$this->session->set_flashdata('error', 'Data puskesmas belum valid.');
			redirect('master_puskesmas', 'refresh');
			return;
		}

		if ($this->Master_puskesmas_m->update($kode, $data)) {
			$this->session->set_flashdata('success', 'Puskesmas diperbarui.');
		} else {
			$this->session->set_flashdata('error', 'Gagal memperbarui Puskesmas.');
		}
		redirect('master_puskesmas', 'refresh');
	}

	public function enable()
	{
		$this->set_status('aktif');
	}

	public function disable()
	{
		$this->set_status('nonaktif');
	}

	private function set_status($status)
	{
		if (!$this->require_post()) {
			return;
		}
		$kode = trim((string) $this->input->post('kode_pkm', TRUE));
		if ($kode === '') {
			$this->session->set_flashdata('error', 'Kode puskesmas tidak valid.');
			redirect('master_puskesmas', 'refresh');
			return;
		}
		if (strtoupper($kode) === 'DEFAULT' && $status === 'aktif') {
			$this->session->set_flashdata('error', 'Puskesmas default tidak dapat diaktifkan.');
			redirect('master_puskesmas', 'refresh');
			return;
		}

		if ($this->Master_puskesmas_m->set_status($kode, $status)) {
			$this->session->set_flashdata('success', $status === 'aktif' ? 'Puskesmas diaktifkan.' : 'Puskesmas dinonaktifkan.');
		} else {
			$this->session->set_flashdata('error', 'Gagal memperbarui status Puskesmas.');
		}
		redirect('master_puskesmas', 'refresh');
	}

	private function build_payload($include_code = true)
	{
		$status = $this->input->post('status', TRUE);
		$status = in_array($status, array('aktif', 'nonaktif'), TRUE) ? $status : 'aktif';
		$data = array(
			'nama_puskesmas' => trim((string) $this->input->post('nama_puskesmas', TRUE)),
			'alamat' => trim((string) $this->input->post('alamat', TRUE)),
			'latitude' => trim((string) $this->input->post('latitude', TRUE)),
			'longitude' => trim((string) $this->input->post('longitude', TRUE)),
			'status' => $status,
		);
		if ($include_code) {
			$data['kode_pkm'] = trim((string) $this->input->post('kode_pkm', TRUE));
		}
		if (($include_code && strtoupper($data['kode_pkm']) === 'DEFAULT') || (!$include_code && strtoupper((string) $this->uri->segment(3)) === 'DEFAULT')) {
			$data['status'] = 'nonaktif';
		}

		return $data;
	}

	private function valid_coordinates($data)
	{
		foreach (array('latitude', 'longitude') as $field) {
			if ($data[$field] !== '' && !is_numeric($data[$field])) {
				return false;
			}
		}

		return true;
	}

	private function require_post()
	{
		if ($this->input->method(TRUE) === 'POST') {
			return true;
		}

		$this->output->set_status_header(405);
		$this->session->set_flashdata('error', 'Metode tidak diizinkan.');
		redirect('master_puskesmas', 'refresh');
		return false;
	}
}
