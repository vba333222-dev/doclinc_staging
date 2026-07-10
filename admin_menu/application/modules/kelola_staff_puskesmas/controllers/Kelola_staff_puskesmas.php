<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Kelola_staff_puskesmas extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Kelola_staff_puskesmas_m');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
		if ($this->session->userdata('level') !== 'admin') {
			redirect('home', 'refresh');
		}
	}

	public function index()
	{
		$this->render_page();
	}

	public function create()
	{
		$this->render_page('create');
	}

	public function store()
	{
		if (!$this->require_post()) {
			return;
		}
		if (!$this->Kelola_staff_puskesmas_m->table_ready()) {
			$this->session->set_flashdata('error', 'Tabel personel Puskesmas belum tersedia.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$data = $this->validated_payload();
		if ($data === false) {
			redirect('kelola_staff_puskesmas/create', 'refresh');
			return;
		}

		if ($this->Kelola_staff_puskesmas_m->insert($data)) {
			$this->session->set_flashdata('success', 'Data staff Puskesmas berhasil ditambahkan.');
		} else {
			$this->session->set_flashdata('error', 'Data staff Puskesmas gagal ditambahkan.');
		}
		redirect('kelola_staff_puskesmas', 'refresh');
	}

	public function edit($staff_id = null)
	{
		$staff_id = (int) $staff_id;
		if ($staff_id < 1) {
			$this->session->set_flashdata('error', 'Data staff tidak valid.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$this->render_page('edit', $staff_id);
	}

	public function update($staff_id = null)
	{
		if (!$this->require_post()) {
			return;
		}
		if (!$this->Kelola_staff_puskesmas_m->table_ready()) {
			$this->session->set_flashdata('error', 'Tabel personel Puskesmas belum tersedia.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$staff_id = (int) $staff_id;
		if ($staff_id < 1 || !$this->Kelola_staff_puskesmas_m->get_by_id($staff_id)) {
			$this->session->set_flashdata('error', 'Data staff tidak ditemukan.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$data = $this->validated_payload();
		if ($data === false) {
			redirect('kelola_staff_puskesmas/edit/' . $staff_id, 'refresh');
			return;
		}

		if ($this->Kelola_staff_puskesmas_m->update($staff_id, $data)) {
			$this->session->set_flashdata('success', 'Data staff Puskesmas berhasil diperbarui.');
		} else {
			$this->session->set_flashdata('error', 'Data staff Puskesmas gagal diperbarui.');
		}
		redirect('kelola_staff_puskesmas', 'refresh');
	}

	public function activate($staff_id = null)
	{
		$this->set_status($staff_id, 'aktif');
	}

	public function deactivate($staff_id = null)
	{
		$this->set_status($staff_id, 'nonaktif');
	}

	public function bind_account()
	{
		if (!$this->require_post()) {
			return;
		}
		if (!$this->Kelola_staff_puskesmas_m->table_ready()) {
			$this->session->set_flashdata('error', 'Tabel personel Puskesmas belum tersedia.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$staff_id = (int) $this->input->post('staff_id');
		$user_id = (int) $this->input->post('user_id');
		if ($staff_id < 1 || $user_id < 1) {
			$this->session->set_flashdata('error', 'Data staff atau akun login tidak valid.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$result = $this->Kelola_staff_puskesmas_m->bind_staff_account($staff_id, $user_id);
		$this->session->set_flashdata(
			!empty($result['status']) && $result['status'] === 'success' ? 'success' : 'error',
			!empty($result['message']) ? $result['message'] : 'Akun login belum dapat dihubungkan.'
		);
		redirect('kelola_staff_puskesmas', 'refresh');
	}

	public function unbind_account()
	{
		if (!$this->require_post()) {
			return;
		}
		if (!$this->Kelola_staff_puskesmas_m->table_ready()) {
			$this->session->set_flashdata('error', 'Tabel personel Puskesmas belum tersedia.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$staff_id = (int) $this->input->post('staff_id');
		if ($staff_id < 1) {
			$this->session->set_flashdata('error', 'Data staff tidak valid.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$result = $this->Kelola_staff_puskesmas_m->unbind_staff_account($staff_id);
		$this->session->set_flashdata(
			!empty($result['status']) && $result['status'] === 'success' ? 'success' : 'error',
			!empty($result['message']) ? $result['message'] : 'Akun login belum dapat dilepas.'
		);
		redirect('kelola_staff_puskesmas', 'refresh');
	}

	private function render_page($form_mode = '', $staff_id = null)
	{
		$this->session->set_flashdata('title', 'Staff Puskesmas');
		$this->session->set_flashdata('active_tab_kelola_staff_puskesmas', 'active');

		$filter_input = $this->input->method(TRUE) === 'POST' ? 'post' : 'get';
		$filters = array(
			'kode_pkm' => trim((string) $this->input->{$filter_input}('kode_pkm', TRUE)),
			'status' => trim((string) $this->input->{$filter_input}('status', TRUE)),
			'keyword' => trim((string) $this->input->{$filter_input}('keyword', TRUE)),
		);

		$data = array(
			'table_ready' => $this->Kelola_staff_puskesmas_m->table_ready(),
			'filters' => $filters,
			'staff_rows' => array(),
			'puskesmas_options' => $this->Kelola_staff_puskesmas_m->get_active_puskesmas_options(),
			'account_candidates_by_staff' => array(),
			'command_center_user_ids' => array(),
			'form_mode' => $form_mode,
			'form_staff' => null,
		);

		if ($data['table_ready']) {
			$data['staff_rows'] = $this->Kelola_staff_puskesmas_m->get_all($filters);
			foreach ($data['staff_rows'] as $staff_row) {
				$current_staff_id = (int) ($staff_row->staff_id ?? 0);
				$kode_pkm = trim((string) ($staff_row->kode_pkm ?? ''));
				if ($current_staff_id > 0) {
					$data['account_candidates_by_staff'][$current_staff_id] = $this->Kelola_staff_puskesmas_m->get_eligible_account_candidates($kode_pkm, $current_staff_id);
				}
				if ($kode_pkm !== '' && !array_key_exists($kode_pkm, $data['command_center_user_ids'])) {
					$data['command_center_user_ids'][$kode_pkm] = $this->Kelola_staff_puskesmas_m->get_command_center_user_id($kode_pkm);
				}
			}
			if ($form_mode === 'edit') {
				$data['form_staff'] = $this->Kelola_staff_puskesmas_m->get_by_id((int) $staff_id);
				if (!$data['form_staff']) {
					$this->session->set_flashdata('error', 'Data staff tidak ditemukan.');
					redirect('kelola_staff_puskesmas', 'refresh');
					return;
				}
			}
		}

		$this->load->view('commons/header');
		$this->load->view('kelola_staff_puskesmas_v', $data);
		$this->load->view('commons/footer');
	}

	private function validated_payload()
	{
		$this->load->library('form_validation');
		$this->form_validation->set_rules('kode_pkm', 'Puskesmas', 'trim|required');
		$this->form_validation->set_rules('nama', 'Nama', 'trim|required');
		$this->form_validation->set_rules('status', 'Status', 'trim|required');

		$kode_pkm = trim((string) $this->input->post('kode_pkm', TRUE));
		$status = trim((string) $this->input->post('status', TRUE));
		if (!in_array($status, array('aktif', 'nonaktif'), true)) {
			$this->session->set_flashdata('error', 'Status staff tidak valid.');
			return false;
		}
		if (!$this->Kelola_staff_puskesmas_m->puskesmas_is_active($kode_pkm)) {
			$this->session->set_flashdata('error', 'Puskesmas aktif wajib dipilih.');
			return false;
		}
		if ($this->form_validation->run() === FALSE) {
			$this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
			return false;
		}

		return array(
			'kode_pkm' => $kode_pkm,
			'nama' => trim((string) $this->input->post('nama', TRUE)),
			'no_hp' => trim((string) $this->input->post('no_hp', TRUE)),
			'profesi' => trim((string) $this->input->post('profesi', TRUE)),
			'nomor_sip' => trim((string) $this->input->post('nomor_sip', TRUE)),
			'status' => $status,
		);
	}

	private function set_status($staff_id, $status)
	{
		if (!$this->require_post()) {
			return;
		}
		if (!$this->Kelola_staff_puskesmas_m->table_ready()) {
			$this->session->set_flashdata('error', 'Tabel personel Puskesmas belum tersedia.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$staff_id = (int) $staff_id;
		if ($staff_id < 1 || !$this->Kelola_staff_puskesmas_m->get_by_id($staff_id)) {
			$this->session->set_flashdata('error', 'Data staff tidak ditemukan.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		if ($this->Kelola_staff_puskesmas_m->set_status($staff_id, $status)) {
			$this->session->set_flashdata('success', 'Status staff Puskesmas berhasil diperbarui.');
		} else {
			$this->session->set_flashdata('error', 'Status staff Puskesmas gagal diperbarui.');
		}
		redirect('kelola_staff_puskesmas', 'refresh');
	}

	private function require_post()
	{
		if ($this->input->method(TRUE) === 'POST') {
			return true;
		}

		$this->output->set_status_header(405);
		$this->session->set_flashdata('error', 'Metode tidak diizinkan.');
		redirect('kelola_staff_puskesmas', 'refresh');
		return false;
	}
}
