<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Kelola_dokter_nakes extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Kelola_dokter_nakes_m');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
		if ($this->session->userdata('level') !== 'admin') {
			redirect('home', 'refresh');
		}
	}
	public function index()
	{
		$this->session->set_flashdata('title', 'Kelola Dokter/Nakes');
		$this->session->set_flashdata('active_tab_kelola_dokter_nakes', 'active');
		$x['data_dokter_nakes'] = $this->Kelola_dokter_nakes_m->get_data_dokter_nakes();
		$x['puskesmas_options'] = $this->Kelola_dokter_nakes_m->get_puskesmas_options();
		$this->load->view('commons/header');
		$this->load->view('kelola_dokter_nakes_v', $x);
		$this->load->view('commons/footer');
	}

	public function store()
	{
		if (!$this->require_post()) {
			return;
		}
		$this->load->library('form_validation');

		$this->form_validation->set_rules('nama', 'Nama', 'trim|required');
		$this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');
		$this->form_validation->set_rules('username', 'Username', 'trim|required');
		$this->form_validation->set_rules('password', 'Password', 'required|min_length[8]');
		$this->form_validation->set_rules('confirm_password', 'Konfirmasi Password', 'required|matches[password]');

		$email = trim((string) $this->input->post('email', TRUE));
		$username = trim((string) $this->input->post('username', TRUE));
		if ($email && $this->Kelola_dokter_nakes_m->email_exists($email)) {
			$this->session->set_flashdata('error', 'Email sudah digunakan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}
		if ($username && $this->Kelola_dokter_nakes_m->username_exists($username)) {
			$this->session->set_flashdata('error', 'Username sudah digunakan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		if ($this->form_validation->run() === FALSE) {
			$this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		$status = $this->input->post('status', TRUE);
		$status = in_array($status, array('aktif', 'nonaktif'), TRUE) ? $status : 'aktif';
		$posted_remark = $this->input->post('remark', TRUE);
		if ($this->Kelola_dokter_nakes_m->has_active_puskesmas() && !$this->Kelola_dokter_nakes_m->puskesmas_exists($posted_remark)) {
			$this->session->set_flashdata('error', 'Puskesmas aktif wajib dipilih.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}
		$remark = $this->Kelola_dokter_nakes_m->normalize_remark($posted_remark);

		$data = array(
			'nama' => $this->input->post('nama', TRUE),
			'email' => $email,
			'username' => $username,
			'no_hp' => $this->input->post('no_hp', TRUE),
			'password' => password_hash((string) $this->input->post('password'), PASSWORD_DEFAULT),
			'role' => 'dokter',
			'status' => $status,
			'remark' => $remark,
			'updated_by' => $this->session->userdata('username'),
		);

		$result = $this->Kelola_dokter_nakes_m->create_dokter_nakes($data);
		if ($result) {
			$this->session->set_flashdata('success', 'Akun Puskesmas/Nakes berhasil ditambahkan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		$this->session->set_flashdata('error', 'Akun Puskesmas/Nakes gagal ditambahkan.');
		redirect('kelola_dokter_nakes', 'refresh');
	}
	public function aktifkan_user()
	{
		if (!$this->require_post()) {
			return;
		}
		$id_user = (int) $this->input->post('id_user');
		$remark_aktif = $this->input->post('remark_aktif');
		$user = $this->session->userdata('username');
		$this->Kelola_dokter_nakes_m->aktifkan_user($id_user, $user, $remark_aktif);
		$this->session->set_flashdata('success', 'Anda berhasil mengaktifkan user.');
		redirect('kelola_dokter_nakes', 'refresh');
	}
	public function nonaktifkan_user()
	{
		if (!$this->require_post()) {
			return;
		}
		$id_user = (int) $this->input->post('id_user');
		$remark_nonaktif = $this->input->post('remark_nonaktif');
		$user = $this->session->userdata('username');
		$this->Kelola_dokter_nakes_m->nonaktifkan_user($id_user, $user, $remark_nonaktif);
		$this->session->set_flashdata('success', 'Anda berhasil menonaktifkan user.');
		redirect('kelola_dokter_nakes', 'refresh');
	}

	public function update($id_user)
	{
		if (!$this->require_post()) {
			return;
		}
		$this->load->library('form_validation');
		$id_user = (int) $id_user;
		$this->form_validation->set_rules('nama', 'Nama', 'trim|required');
		$this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');
		$this->form_validation->set_rules('username', 'Username', 'trim|required');

		$email = trim((string) $this->input->post('email', TRUE));
		$username = trim((string) $this->input->post('username', TRUE));
		$password = (string) $this->input->post('password');
		$confirm_password = (string) $this->input->post('confirm_password');

		if ($email && $this->Kelola_dokter_nakes_m->email_exists($email, $id_user)) {
			$this->session->set_flashdata('error', 'Email sudah digunakan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}
		if ($username && $this->Kelola_dokter_nakes_m->username_exists($username, $id_user)) {
			$this->session->set_flashdata('error', 'Username sudah digunakan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}
		if ($password !== '' && strlen($password) < 8) {
			$this->session->set_flashdata('error', 'Password minimal 8 karakter.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}
		if ($password !== '' && $password !== $confirm_password) {
			$this->session->set_flashdata('error', 'Konfirmasi password tidak sesuai.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}
		if ($this->form_validation->run() === FALSE) {
			$this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		$status = $this->input->post('status', TRUE);
		$status = in_array($status, array('aktif', 'nonaktif'), TRUE) ? $status : 'aktif';
		$posted_remark = $this->input->post('remark', TRUE);
		if ($this->Kelola_dokter_nakes_m->has_active_puskesmas() && !$this->Kelola_dokter_nakes_m->puskesmas_exists($posted_remark)) {
			$this->session->set_flashdata('error', 'Puskesmas aktif wajib dipilih.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		$data = array(
			'nama' => $this->input->post('nama', TRUE),
			'email' => $email,
			'username' => $username,
			'remark' => $this->Kelola_dokter_nakes_m->normalize_remark($posted_remark),
			'no_hp' => $this->input->post('no_hp', TRUE),
			'status' => $status,
			'updated_by' => $this->session->userdata('username'),
			'updated_at' => date('Y-m-d H:i:s')
		);
		if ($password !== '') {
			$data['password'] = password_hash($password, PASSWORD_DEFAULT);
		}

		$this->Kelola_dokter_nakes_m->update_dokter_nakes($id_user, $data);
		$this->session->set_flashdata('success', 'Data berhasil diperbarui.');
		redirect('kelola_dokter_nakes', 'refresh');
	}

	public function delete_dokter_nakes()
	{
		if (!$this->require_post()) {
			return;
		}
		$id_dokter_nakes = (int) $this->input->post('id_dokter_nakes');
		$this->Kelola_dokter_nakes_m->delete_dokter_nakes($id_dokter_nakes);
		$this->session->set_flashdata('success', 'Data berhasil dihapus.');
		redirect('kelola_dokter_nakes', 'refresh');
	}

	private function require_post()
	{
		if ($this->input->method(TRUE) === 'POST') {
			return true;
		}

		$this->output->set_status_header(405);
		$this->session->set_flashdata('error', 'Metode tidak diizinkan.');
		redirect('kelola_dokter_nakes', 'refresh');
		return false;
	}
}
