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
		$this->session->set_flashdata('title', 'Puskesmas');
		$this->session->set_flashdata('active_tab_kelola_dokter_nakes', 'active');
		/* Sembilan baris Puskesmas inti - bukan daftar akun. Akun, staf, dan
		   profil fasilitas semuanya dikelola dari halaman detail, supaya tidak
		   ada tiga layar berbeda yang menampilkan Puskesmas yang sama. */
		$x['puskesmas_rows'] = $this->Kelola_dokter_nakes_m->get_core_puskesmas_rows();
		/* Kesiapan fasilitas dulu hanya terlihat di `master_puskesmas`; rute
		   itu kini mengalihkan ke sini, jadi ringkasannya ikut pindah. */
		$x['puskesmas_readiness_summary'] = $this->Kelola_dokter_nakes_m->readiness_summary($x['puskesmas_rows']);
		$this->load->view('commons/header');
		$this->load->view('kelola_dokter_nakes_v', $x);
		$this->load->view('commons/footer');
	}

	/**
	 * Satu Puskesmas: profil fasilitas, akun koordinator, dan daftar stafnya.
	 *
	 * Halaman ini adalah pengganti `master_puskesmas` dan
	 * `kelola_staff_puskesmas` untuk pekerjaan sehari-hari. Dua rute lama tetap
	 * hidup, tetapi tidak lagi ditautkan dari sidebar.
	 *
	 * @param string|null $kode_pkm
	 */
	public function detail($kode_pkm = null)
	{
		$puskesmas = $this->Kelola_dokter_nakes_m->get_puskesmas($kode_pkm);
		if (!$puskesmas) {
			$this->session->set_flashdata('error', 'Puskesmas tidak ditemukan. Pilih salah satu dari sembilan Puskesmas inti.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		$this->session->set_flashdata('title', 'Puskesmas ' . $puskesmas->nama_puskesmas);
		$this->session->set_flashdata('active_tab_kelola_dokter_nakes', 'active');

		$x['puskesmas'] = $puskesmas;
		$x['account'] = $this->Kelola_dokter_nakes_m->get_puskesmas_account($puskesmas->kode_pkm);
		$x['profile_schema_ready'] = $this->Kelola_dokter_nakes_m->profile_schema_ready();
		$x['staff_section'] = $this->staff_section_html($puskesmas->kode_pkm);

		$this->load->view('commons/header');
		$this->load->view('puskesmas_detail_v', $x);
		$this->load->view('commons/footer');
	}
	/**
	 * Menyimpan profil fasilitas dari layar detail.
	 */
	public function update_profile()
	{
		if (!$this->require_post()) {
			return;
		}

		$kode_pkm = $this->Kelola_dokter_nakes_m->core_puskesmas()->normalize($this->input->post('kode_pkm', TRUE));
		$destination = 'kelola_dokter_nakes/detail/' . rawurlencode($kode_pkm);
		if (!$this->Kelola_dokter_nakes_m->get_puskesmas($kode_pkm)) {
			$this->session->set_flashdata('error', 'Puskesmas tidak dikenal; profil tidak disimpan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		$email = trim((string) $this->input->post('email', TRUE));
		if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->session->set_flashdata('error', 'Alamat email Puskesmas tidak valid; profil tidak disimpan.');
			redirect($destination, 'refresh');
			return;
		}

		$telepon = trim((string) $this->input->post('telepon', TRUE));
		if ($telepon !== '' && preg_match('/^[0-9 +().-]{5,50}$/', $telepon) !== 1) {
			$this->session->set_flashdata('error', 'Nomor telepon hanya boleh berisi angka, spasi, tanda hubung, titik, kurung, dan tanda plus.');
			redirect($destination, 'refresh');
			return;
		}

		/* Titik lokasi menentukan Puskesmas mana yang muncul di peta sebaran,
		   jadi di luar rentang berarti Puskesmas itu hilang dari peta tanpa
		   pesan apa pun. Rentangnya sama dengan validasi `master_puskesmas`. */
		$coordinates = array();
		foreach (array('latitude' => 90, 'longitude' => 180) as $field => $limit) {
			$raw = trim((string) $this->input->post($field, TRUE));
			if ($raw === '') {
				$coordinates[$field] = '';
				continue;
			}
			if (!is_numeric($raw) || (float) $raw < -$limit || (float) $raw > $limit) {
				$this->session->set_flashdata('error', ucfirst($field) . ' harus berupa angka antara -' . $limit . ' dan ' . $limit . '; profil tidak disimpan.');
				redirect($destination, 'refresh');
				return;
			}
			$coordinates[$field] = $raw;
		}

		$saved = $this->Kelola_dokter_nakes_m->update_puskesmas_profile($kode_pkm, array(
			'alamat' => $this->input->post('alamat', TRUE),
			'telepon' => $telepon,
			'email' => $email,
			'kepala_puskesmas' => $this->input->post('kepala_puskesmas', TRUE),
			'latitude' => $coordinates['latitude'],
			'longitude' => $coordinates['longitude'],
		));
		$this->session->set_flashdata(
			$saved ? 'success' : 'error',
			$saved
				? 'Profil Puskesmas diperbarui.'
				: 'Profil Puskesmas tidak dapat disimpan. Periksa apakah kolom profil sudah tersedia di basis data.'
		);
		redirect($destination, 'refresh');
	}

	/**
	 * Halaman reset password akun koordinator Puskesmas.
	 *
	 * @param string|null $kode_pkm
	 */
	public function password($kode_pkm = null)
	{
		$puskesmas = $this->Kelola_dokter_nakes_m->get_puskesmas($kode_pkm);
		if (!$puskesmas) {
			$this->session->set_flashdata('error', 'Puskesmas tidak ditemukan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		$this->session->set_flashdata('title', 'Password akun Puskesmas');
		$this->session->set_flashdata('active_tab_kelola_dokter_nakes', 'active');

		$x['puskesmas'] = $puskesmas;
		$x['account'] = $this->Kelola_dokter_nakes_m->get_puskesmas_account($puskesmas->kode_pkm);

		$this->load->view('commons/header');
		$this->load->view('puskesmas_password_v', $x);
		$this->load->view('commons/footer');
	}


	/**
	 * Menyimpan password baru untuk akun koordinator Puskesmas.
	 *
	 * Memakai jalur yang sudah ada (`update_dokter_nakes`): password di-hash,
	 * `must_change_password` disetel 1, sesi lama dicabut, dan audit
	 * `admin_reset_puskesmas_nakes_password` ditulis. Tidak ada jalur tulis
	 * password kedua yang perlu dijaga terpisah.
	 */
	public function reset_password()
	{
		if (!$this->require_post()) {
			return;
		}

		$kode_pkm = $this->Kelola_dokter_nakes_m->core_puskesmas()->normalize($this->input->post('kode_pkm', TRUE));
		$puskesmas = $this->Kelola_dokter_nakes_m->get_puskesmas($kode_pkm);
		$destination = 'kelola_dokter_nakes/password/' . rawurlencode($kode_pkm);
		if (!$puskesmas) {
			$this->session->set_flashdata('error', 'Puskesmas tidak ditemukan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		$account = $this->Kelola_dokter_nakes_m->get_puskesmas_account($kode_pkm);
		if (!$account) {
			$this->session->set_flashdata('error', 'Puskesmas ini belum punya akun login. Tambahkan akun terlebih dahulu.');
			redirect('kelola_dokter_nakes/detail/' . rawurlencode($kode_pkm), 'refresh');
			return;
		}

		$password = (string) $this->input->post('password');
		$confirmation = (string) $this->input->post('confirm_password');
		require_once APPPATH . 'libraries/Password_strength_policy.php';
		$policy = new Password_strength_policy();
		$error = $policy->validate($password, $confirmation);
		if ($error !== null) {
			$this->session->set_flashdata('error', $policy->message($error));
			redirect($destination, 'refresh');
			return;
		}

		$target = $this->Kelola_dokter_nakes_m->get_dokter_user((int) $account->userId);
		if (!$target) {
			$this->session->set_flashdata('error', 'Akun Puskesmas tidak ditemukan atau bukan akun Puskesmas.');
			redirect($destination, 'refresh');
			return;
		}

		$saved = $this->Kelola_dokter_nakes_m->update_dokter_nakes((int) $target->userId, array(
			'password' => password_hash($password, PASSWORD_DEFAULT),
			'updated_by' => $this->session->userdata('username'),
			'updated_at' => date('Y-m-d H:i:s'),
		));
		$this->session->set_flashdata(
			$saved ? 'success' : 'error',
			$saved
				? 'Password akun ' . (string) $account->username . ' diganti. Pengelola Puskesmas harus membuat password pribadi saat masuk berikutnya.'
				: 'Password tidak dapat diganti. Periksa dukungan kolom password di basis data lalu ulangi.'
		);
		redirect($saved ? 'kelola_dokter_nakes/detail/' . rawurlencode($kode_pkm) : $destination, 'refresh');
	}

	/**
	 * Daftar staf satu Puskesmas, dirender oleh modul staf itu sendiri.
	 *
	 * HMVC (`Modules::run`) dipakai supaya CRUD staf tidak disalin: satu view,
	 * satu controller, satu model. Bila HMVC tidak tersedia, halaman detail
	 * tetap terbuka dan menautkan ke rute staf lengkap - keadaan yang lebih baik
	 * daripada halaman galat.
	 *
	 * @param string $kode_pkm
	 * @return string
	 */
	private function staff_section_html($kode_pkm)
	{
		if (!class_exists('Modules') || !method_exists('Modules', 'run')) {
			return '';
		}

		return (string) Modules::run('kelola_staff_puskesmas/staff_section', $kode_pkm);
	}




	public function store()
	{
		if (!$this->require_post()) {
			return;
		}
		$this->load->library('form_validation');

		$this->form_validation->set_rules('nama', 'Nama', 'trim|required', array('required' => 'Nama lengkap wajib diisi.'));
		$this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email', array('required' => 'Email wajib diisi.', 'valid_email' => 'Masukkan email yang valid.'));
		$this->form_validation->set_rules('username', 'Username', 'trim|required', array('required' => 'Nama pengguna wajib diisi.'));
		$this->form_validation->set_rules('password', 'Password', 'required|min_length[8]|max_length[72]', array('required' => 'Password wajib diisi.', 'min_length' => 'Password minimal 8 karakter.', 'max_length' => 'Password maksimal 72 karakter.'));
		$this->form_validation->set_rules('confirm_password', 'Konfirmasi Password', 'required|matches[password]', array('required' => 'Konfirmasi password wajib diisi.', 'matches' => 'Konfirmasi password tidak sama.'));

		$email = trim((string) $this->input->post('email', TRUE));
		$username = trim((string) $this->input->post('username', TRUE));
		if ($email && $this->Kelola_dokter_nakes_m->email_exists($email)) {
			$this->session->set_flashdata('error', 'Email sudah digunakan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}
		if ($username && $this->Kelola_dokter_nakes_m->username_exists($username)) {
			$this->session->set_flashdata('error', 'Nama pengguna sudah digunakan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		if ($this->form_validation->run() === FALSE) {
			$this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}
		require_once APPPATH . 'libraries/Password_strength_policy.php';
		$password_policy = new Password_strength_policy();
		$password_error = $password_policy->validate(
			(string) $this->input->post('password'),
			(string) $this->input->post('confirm_password')
		);
		if ($password_error !== null) {
			$this->session->set_flashdata('error', $password_policy->message($password_error));
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		$status = $this->input->post('status', TRUE);
		$status = in_array($status, array('aktif', 'nonaktif'), TRUE) ? $status : 'aktif';
		$posted_remark = $this->input->post('remark', TRUE);
		if ($this->Kelola_dokter_nakes_m->has_active_puskesmas() && !$this->Kelola_dokter_nakes_m->puskesmas_exists($posted_remark)) {
			$this->session->set_flashdata('error', 'Pilih Puskesmas yang aktif.');
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
			$this->session->set_flashdata('success', 'Akun Puskesmas ditambahkan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		$this->session->set_flashdata('error', 'Gagal menambahkan akun Puskesmas. Tidak ada akun yang dibuat; periksa kembali isian lalu simpan ulang.');
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
		$this->session->set_flashdata('success', 'Akun Puskesmas berhasil diaktifkan.');
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
		$this->session->set_flashdata('success', 'Akun Puskesmas berhasil dinonaktifkan.');
		redirect('kelola_dokter_nakes', 'refresh');
	}

	public function update($id_user)
	{
		if (!$this->require_post()) {
			return;
		}
		$this->load->library('form_validation');
		$id_user = (int) $id_user;
		$this->form_validation->set_rules('nama', 'Nama', 'trim|required', array('required' => 'Nama lengkap wajib diisi.'));
		$this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email', array('required' => 'Email wajib diisi.', 'valid_email' => 'Masukkan email yang valid.'));
		$this->form_validation->set_rules('username', 'Username', 'trim|required', array('required' => 'Nama pengguna wajib diisi.'));

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
			$this->session->set_flashdata('error', 'Nama pengguna sudah digunakan.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}
		if ($password !== '') {
			require_once APPPATH . 'libraries/Password_strength_policy.php';
			$password_policy = new Password_strength_policy();
			$password_error = $password_policy->validate($password, $confirm_password);
			if ($password_error !== null) {
				$this->session->set_flashdata('error', $password_policy->message($password_error));
				redirect('kelola_dokter_nakes', 'refresh');
				return;
			}
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
			$this->session->set_flashdata('error', 'Pilih Puskesmas yang aktif.');
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
		$remark_nonaktif = $this->input->post('remark_nonaktif');
		$user = $this->session->userdata('username');
		$this->Kelola_dokter_nakes_m->delete_dokter_nakes($id_dokter_nakes, $user, $remark_nonaktif);
		$this->session->set_flashdata('success', 'Akun Puskesmas dinonaktifkan. Data akun tidak dihapus.');
		redirect('kelola_dokter_nakes', 'refresh');
	}

	public function destroy_dokter_nakes()
	{
		if (!$this->require_post()) {
			return;
		}

		$id_user = (int) $this->input->post('id_user');
		$target = $this->Kelola_dokter_nakes_m->get_dokter_user($id_user);
		if (!$target) {
			$this->session->set_flashdata('error', 'Akun Puskesmas tidak ditemukan atau bukan akun Puskesmas.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		if ((string) ($target->username ?? '') === (string) $this->session->userdata('username')) {
			$this->session->set_flashdata('error', 'Akun yang sedang digunakan tidak dapat dihapus permanen.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		$blocking_relations = $this->Kelola_dokter_nakes_m->blocking_relations_for_user($id_user);
		if (!empty($blocking_relations)) {
			$this->session->set_flashdata('error', 'Akun tidak dapat dihapus permanen karena masih memiliki riwayat/relasi data. Nonaktifkan akun sebagai alternatif.');
			redirect('kelola_dokter_nakes', 'refresh');
			return;
		}

		if ($this->Kelola_dokter_nakes_m->destroy_dokter_nakes($id_user)) {
			$this->session->set_flashdata('success', 'Akun Puskesmas dihapus permanen.');
		} else {
			$this->session->set_flashdata('error', 'Gagal menghapus akun Puskesmas secara permanen. Akun belum terhapus; muat ulang halaman lalu ulangi.');
		}
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
