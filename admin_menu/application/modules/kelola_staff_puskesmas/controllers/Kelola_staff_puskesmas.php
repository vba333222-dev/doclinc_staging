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
			$this->session->set_flashdata('error', 'Data staf belum tersedia.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$data = $this->validated_payload();
		if ($data === false) {
			redirect('kelola_staff_puskesmas/create', 'refresh');
			return;
		}

		if ($this->Kelola_staff_puskesmas_m->insert($data)) {
			$this->session->set_flashdata('success', 'Staf Puskesmas ditambahkan.');
		} else {
			$this->session->set_flashdata('error', 'Gagal menambahkan staf Puskesmas.');
		}
		redirect('kelola_staff_puskesmas', 'refresh');
	}

	public function edit($staff_id = null)
	{
		$staff_id = (int) $staff_id;
		if ($staff_id < 1) {
			$this->session->set_flashdata('error', 'Pilih staf yang valid.');
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
			$this->session->set_flashdata('error', 'Data staf belum tersedia.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$staff_id = (int) $staff_id;
		if ($staff_id < 1) {
			$this->session->set_flashdata('error', 'Pilih staf yang valid.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}
		if (!$this->Kelola_staff_puskesmas_m->get_by_id($staff_id)) {
			$this->session->set_flashdata('error', 'Staf tidak ditemukan.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$data = $this->validated_payload($staff_id);
		if ($data === false) {
			redirect('kelola_staff_puskesmas/edit/' . $staff_id, 'refresh');
			return;
		}

		if ($this->Kelola_staff_puskesmas_m->update($staff_id, $data)) {
			$this->session->set_flashdata('success', 'Staf Puskesmas diperbarui.');
		} else {
			$this->session->set_flashdata('error', 'Gagal memperbarui staf Puskesmas.');
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
			$this->session->set_flashdata('error', 'Data staf belum tersedia.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$staff_id = (int) $this->input->post('staff_id');
		$user_id = (int) $this->input->post('user_id');
		if ($staff_id < 1 || $user_id < 1) {
			$this->session->set_flashdata('error', 'Pilih staf dan akun yang valid.');
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

	public function create_personal_account()
	{
		if (!$this->require_post()) {
			return;
		}
		if (!$this->Kelola_staff_puskesmas_m->personal_account_creation_ready()) {
			$this->personal_account_flash('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			return;
		}

		$staff_id = (int) $this->input->post('staff_id');
		$username = trim((string) $this->input->post('username', TRUE));
		$email = trim((string) $this->input->post('email', TRUE));
		$password = (string) $this->input->post('password');
		$confirm_password = (string) $this->input->post('confirm_password');

		$staff = $staff_id > 0 ? $this->Kelola_staff_puskesmas_m->get_by_id($staff_id) : null;
		if (!$staff) {
			$this->personal_account_flash('Staf tidak ditemukan.');
			return;
		}
		if ((int) ($staff->user_id ?? 0) > 0) {
			$this->personal_account_flash('Staf sudah memiliki akun personal.');
			return;
		}
		if (($staff->status ?? '') !== 'aktif') {
			$this->personal_account_flash('Aktifkan staf terlebih dahulu.');
			return;
		}
		if (!$this->Kelola_staff_puskesmas_m->puskesmas_is_active($staff->kode_pkm ?? '')) {
			$this->personal_account_flash('Puskesmas staf tidak aktif.');
			return;
		}
		$eligibility = $this->Kelola_staff_puskesmas_m->get_personal_account_creation_eligibility($staff);
		if (empty($eligibility['eligible'])) {
			$this->personal_account_flash(!empty($eligibility['message']) ? $eligibility['message'] : 'Staf belum dapat dibuatkan akun personal.');
			return;
		}
		if ($username === '' || strlen($username) < 3 || strlen($username) > 100 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
			$this->personal_account_flash('Nama pengguna harus 3–100 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.');
			return;
		}
		if ($email === '' || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->personal_account_flash('Masukkan email yang valid.');
			return;
		}
		require_once APPPATH . 'libraries/Password_strength_policy.php';
		$password_policy = new Password_strength_policy();
		$password_error = $password_policy->validate($password, $confirm_password);
		if ($password_error !== null) {
			$this->personal_account_flash($password_policy->message($password_error));
			return;
		}
		$this->load->helper('password_compat');
		$result = $this->Kelola_staff_puskesmas_m->create_and_link_personal_account($staff_id, array(
			'username' => $username,
			'email' => $email,
			'plain_password' => $password,
			'updated_by' => (string) $this->session->userdata('username'),
		));

		$is_success = !empty($result['status']) && $result['status'] === 'success';
		$this->session->set_flashdata(
			$is_success ? 'success' : 'error',
			!empty($result['message']) ? $result['message'] : 'Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.'
		);
		redirect('kelola_staff_puskesmas', 'refresh');
	}

	public function reset_personal_password()
	{
		if (!$this->require_post()) {
			return;
		}
		$staff_id = (int) $this->input->post('staff_id');
		$password = (string) $this->input->post('password');
		$confirmation = (string) $this->input->post('confirm_password');
		require_once APPPATH . 'libraries/Password_strength_policy.php';
		$policy = new Password_strength_policy();
		$error = $policy->validate($password, $confirmation);
		if ($staff_id < 1 || $error !== null) {
			$this->session->set_flashdata('error', $staff_id < 1 ? 'Pilih staf yang valid.' : $policy->message($error));
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$this->load->helper('password_compat');
		$result = $this->Kelola_staff_puskesmas_m->reset_personal_password(
			$staff_id,
			$password,
			(string) $this->session->userdata('username')
		);
		$password = null;
		$confirmation = null;
		$this->session->set_flashdata(
			!empty($result['status']) && $result['status'] === 'success' ? 'success' : 'error',
			!empty($result['message']) ? $result['message'] : 'Password sementara belum dapat direset.'
		);
		redirect('kelola_staff_puskesmas', 'refresh');
	}

	public function unbind_account()
	{
		if (!$this->require_post()) {
			return;
		}
		if (!$this->Kelola_staff_puskesmas_m->table_ready()) {
			$this->session->set_flashdata('error', 'Data staf belum tersedia.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$staff_id = (int) $this->input->post('staff_id');
		if ($staff_id < 1) {
			$this->session->set_flashdata('error', 'Pilih staf yang valid.');
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
		$this->session->set_flashdata('title', 'Staf Puskesmas');
		$this->session->set_flashdata('active_tab_kelola_staff_puskesmas', 'active');

		$filter_input = $this->input->method(TRUE) === 'POST' ? 'post' : 'get';
		$filters = array(
			'kode_pkm' => trim((string) $this->input->{$filter_input}('kode_pkm', TRUE)),
			'status' => trim((string) $this->input->{$filter_input}('status', TRUE)),
			'readiness' => trim((string) $this->input->{$filter_input}('readiness', TRUE)),
			'keyword' => trim((string) $this->input->{$filter_input}('keyword', TRUE)),
		);
		if (!in_array($filters['readiness'], array('', 'ready', 'attention', 'missing_sip', 'missing_nip', 'account', 'first_login', 'password_reset'), true)) {
			$filters['readiness'] = '';
		}

		$data = array(
			'table_ready' => $this->Kelola_staff_puskesmas_m->table_ready(),
			'nip_schema_ready' => $this->Kelola_staff_puskesmas_m->nip_schema_ready(),
			'filters' => $filters,
			'staff_rows' => array(),
			'puskesmas_options' => $this->Kelola_staff_puskesmas_m->get_active_puskesmas_options(),
			'account_candidates_by_staff' => array(),
			'command_center_user_ids' => array(),
			'personal_account_eligibility_by_staff' => array(),
			'staff_readiness_summary' => array('total' => 0, 'ready' => 0, 'attention' => 0, 'issue_counts' => array()),
			'form_mode' => $form_mode,
			'form_staff' => null,
		);

		if ($data['table_ready']) {
			$data['staff_rows'] = $this->Kelola_staff_puskesmas_m->get_all($filters);
			foreach ($data['staff_rows'] as $staff_row) {
				$kode_pkm = trim((string) ($staff_row->kode_pkm ?? ''));
				if ($kode_pkm !== '' && !array_key_exists($kode_pkm, $data['command_center_user_ids'])) {
					$data['command_center_user_ids'][$kode_pkm] = $this->Kelola_staff_puskesmas_m->get_command_center_user_id($kode_pkm);
				}
			}
			foreach ($data['staff_rows'] as $staff_row) {
				$kode_pkm = trim((string) ($staff_row->kode_pkm ?? ''));
				$staff_row->personal_account_state = $this->Kelola_staff_puskesmas_m->personal_account_state(
					$staff_row,
					isset($data['command_center_user_ids'][$kode_pkm]) ? (int) $data['command_center_user_ids'][$kode_pkm] : 0
				);
				$staff_row->readiness_issues = $this->Kelola_staff_puskesmas_m->readiness_issues($staff_row);
			}
			$data['staff_readiness_summary'] = $this->Kelola_staff_puskesmas_m->readiness_summary($data['staff_rows']);
			if ($filters['readiness'] !== '') {
				$data['staff_rows'] = array_values(array_filter($data['staff_rows'], function ($staff_row) use ($filters) {
					return $this->staff_matches_readiness_filter($staff_row, $filters['readiness']);
				}));
			}
			foreach ($data['staff_rows'] as $staff_row) {
				$current_staff_id = (int) ($staff_row->staff_id ?? 0);
				$kode_pkm = trim((string) ($staff_row->kode_pkm ?? ''));
				if ($current_staff_id > 0) {
					$data['account_candidates_by_staff'][$current_staff_id] = $this->Kelola_staff_puskesmas_m->get_eligible_account_candidates($kode_pkm, $current_staff_id);
					$data['personal_account_eligibility_by_staff'][$current_staff_id] = $this->Kelola_staff_puskesmas_m->get_personal_account_creation_eligibility($staff_row);
				}
			}
			if ($form_mode === 'edit') {
				$data['form_staff'] = $this->Kelola_staff_puskesmas_m->get_by_id((int) $staff_id);
				if (!$data['form_staff']) {
					$this->session->set_flashdata('error', 'Staf tidak ditemukan.');
					redirect('kelola_staff_puskesmas', 'refresh');
					return;
				}
			}
		}

		$this->load->view('commons/header');
		$this->load->view('kelola_staff_puskesmas_v', $data);
		$this->load->view('commons/footer');
	}

	private function staff_matches_readiness_filter($staff, $filter)
	{
		if ($filter !== '' && (string) ($staff->status ?? '') !== 'aktif') {
			return false;
		}
		$issues = isset($staff->readiness_issues) && is_array($staff->readiness_issues) ? $staff->readiness_issues : array();
		if ($filter === 'ready') {
			return empty($issues);
		}
		if ($filter === 'attention') {
			return !empty($issues);
		}
		if ($filter === 'missing_sip') {
			return in_array('staff_registration_number', $issues, true);
		}
		if ($filter === 'missing_nip') {
			return in_array('staff_nip', $issues, true);
		}
		if ($filter === 'account') {
			return in_array('staff_account_unlinked', $issues, true)
				|| in_array('staff_account_invalid', $issues, true)
				|| in_array('staff_first_login_pending', $issues, true)
				|| in_array('staff_password_reset_pending', $issues, true);
		}
		if ($filter === 'first_login') {
			return in_array('staff_first_login_pending', $issues, true);
		}
		if ($filter === 'password_reset') {
			return in_array('staff_password_reset_pending', $issues, true);
		}
		return true;
	}

	private function validated_payload($staff_id = 0)
	{
		$this->load->library('form_validation');
		$this->form_validation->set_rules('kode_pkm', 'Puskesmas', 'trim|required', array('required' => 'Pilih Puskesmas.'));
		$this->form_validation->set_rules('nama', 'Nama', 'trim|required', array('required' => 'Nama lengkap wajib diisi.'));
		$this->form_validation->set_rules('no_hp', 'Nomor HP', 'trim|required', array('required' => 'Nomor HP staf wajib diisi.'));
		$this->form_validation->set_rules('profesi', 'Profesi', 'trim|required', array('required' => 'Profesi staf wajib diisi.'));
		$this->form_validation->set_rules('nomor_sip', 'Nomor SIP', 'trim|required', array('required' => 'Nomor SIP staf wajib diisi.'));
		$this->form_validation->set_rules('status', 'Status', 'trim|required', array('required' => 'Pilih status staf.'));

		$kode_pkm = trim((string) $this->input->post('kode_pkm', TRUE));
		$status = trim((string) $this->input->post('status', TRUE));
		if (!in_array($status, array('aktif', 'nonaktif'), true)) {
			$this->session->set_flashdata('error', 'Pilih status staf yang valid.');
			return false;
		}
		if (!$this->Kelola_staff_puskesmas_m->puskesmas_is_active($kode_pkm)) {
			$this->session->set_flashdata('error', 'Pilih Puskesmas yang aktif.');
			return false;
		}
		if ($this->form_validation->run() === FALSE) {
			$this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
			return false;
		}
		$phone = preg_replace('/[\s().-]+/', '', trim((string) $this->input->post('no_hp')));
		if (preg_match('/^\+?[0-9]{8,20}$/', $phone) !== 1) {
			$this->session->set_flashdata('error', 'Nomor HP staf belum valid.');
			return false;
		}
		$nip = '';
		if ($this->Kelola_staff_puskesmas_m->nip_schema_ready()) {
			require_once dirname(APPPATH, 2) . '/application/libraries/Role_identity_policy.php';
			$nip_result = (new Role_identity_policy())->nip($this->input->post('nip'));
			if (!$nip_result['valid']) {
				$this->session->set_flashdata('error', $nip_result['message']);
				return false;
			}
			$nip = $nip_result['value'];
			if (!$this->Kelola_staff_puskesmas_m->nip_available($nip, (int) $staff_id)) {
				$this->session->set_flashdata('error', 'NIP sudah digunakan oleh staf lain.');
				return false;
			}
		}

		$data = array(
			'kode_pkm' => $kode_pkm,
			'nama' => trim((string) $this->input->post('nama', TRUE)),
			'no_hp' => $phone,
			'profesi' => trim((string) $this->input->post('profesi', TRUE)),
			'nomor_sip' => trim((string) $this->input->post('nomor_sip', TRUE)),
			'status' => $status,
		);
		if ($this->Kelola_staff_puskesmas_m->nip_schema_ready()) {
			$data['nip'] = $nip;
		}
		return $data;
	}

	private function set_status($staff_id, $status)
	{
		if (!$this->require_post()) {
			return;
		}
		if (!$this->Kelola_staff_puskesmas_m->table_ready()) {
			$this->session->set_flashdata('error', 'Data staf belum tersedia.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		$staff_id = (int) $staff_id;
		if ($staff_id < 1) {
			$this->session->set_flashdata('error', 'Pilih staf yang valid.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}
		$staff = $this->Kelola_staff_puskesmas_m->get_by_id($staff_id);
		if (!$staff) {
			$this->session->set_flashdata('error', 'Staf tidak ditemukan.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}
		if ($status === 'aktif' && !$this->Kelola_staff_puskesmas_m->staff_is_operationally_complete($staff)) {
			$this->session->set_flashdata('error', 'Staf belum dapat diaktifkan. Lengkapi NIP, SIP, profesi, nomor HP, dan Puskesmas aktif terlebih dahulu.');
			redirect('kelola_staff_puskesmas', 'refresh');
			return;
		}

		if ($this->Kelola_staff_puskesmas_m->set_status($staff_id, $status)) {
			$this->session->set_flashdata('success', $status === 'aktif' ? 'Staf diaktifkan.' : 'Staf dinonaktifkan.');
		} else {
			$this->session->set_flashdata('error', 'Gagal memperbarui status staf Puskesmas.');
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

	private function personal_account_flash($message)
	{
		$this->session->set_flashdata('error', $message);
		redirect('kelola_staff_puskesmas', 'refresh');
	}
}
