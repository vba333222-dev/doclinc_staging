<?php
class Kelola_staff_puskesmas_m extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->db = $this->load->database('default', TRUE);
	}

	public function table_ready()
	{
		if (!$this->db->table_exists('puskesmas_staff')) {
			return false;
		}

		foreach ($this->staff_fields() as $field) {
			if (!$this->db->field_exists($field, 'puskesmas_staff')) {
				return false;
			}
		}

		return true;
	}

	public function get_all($filters = array())
	{
		if (!$this->table_ready()) {
			return array();
		}

		$has_puskesmas = $this->db->table_exists('m_puskesmas');
		$has_users = $this->db->table_exists('users');
		$this->db
			->select('puskesmas_staff.staff_id, puskesmas_staff.kode_pkm, puskesmas_staff.nama, puskesmas_staff.no_hp, puskesmas_staff.profesi, puskesmas_staff.nomor_sip, puskesmas_staff.user_id, puskesmas_staff.status')
			->from('puskesmas_staff');

		if ($has_puskesmas) {
			$this->db->select('m_puskesmas.nama_puskesmas');
			$this->db->select($this->db->field_exists('status', 'm_puskesmas') ? 'm_puskesmas.status AS puskesmas_status' : 'NULL AS puskesmas_status', false);
			$this->db->join('m_puskesmas', 'm_puskesmas.kode_pkm = puskesmas_staff.kode_pkm', 'left');
		} else {
			$this->db->select('NULL AS nama_puskesmas, NULL AS puskesmas_status', FALSE);
		}

		if ($has_users) {
			$this->db->select('staff_user.nama AS akun_nama, staff_user.username AS akun_username, staff_user.email AS akun_email');
			$this->db->select($this->db->field_exists('status', 'users') ? 'staff_user.status AS akun_status' : 'NULL AS akun_status', false);
			$this->db->select($this->db->field_exists('role', 'users') ? 'staff_user.role AS akun_role' : 'NULL AS akun_role', false);
			$this->db->select($this->db->field_exists('remark', 'users') ? 'staff_user.remark AS akun_remark' : 'NULL AS akun_remark', false);
			$this->db->join('users staff_user', 'staff_user.userId = puskesmas_staff.user_id', 'left');
		} else {
			$this->db->select('NULL AS akun_nama, NULL AS akun_username, NULL AS akun_email, NULL AS akun_status, NULL AS akun_role, NULL AS akun_remark', FALSE);
		}

		$kode_pkm = isset($filters['kode_pkm']) ? trim((string) $filters['kode_pkm']) : '';
		if ($kode_pkm !== '') {
			$this->db->where('puskesmas_staff.kode_pkm', $kode_pkm);
		}

		$status = isset($filters['status']) ? trim((string) $filters['status']) : '';
		if (in_array($status, array('aktif', 'nonaktif'), true)) {
			$this->db->where('puskesmas_staff.status', $status);
		}

		$keyword = isset($filters['keyword']) ? trim((string) $filters['keyword']) : '';
		if ($keyword !== '') {
			$this->db->group_start()
				->like('puskesmas_staff.nama', $keyword)
				->or_like('puskesmas_staff.no_hp', $keyword)
				->or_like('puskesmas_staff.profesi', $keyword)
				->or_like('puskesmas_staff.nomor_sip', $keyword)
				->group_end();
		}

		if ($has_puskesmas) {
			$this->db->order_by('m_puskesmas.nama_puskesmas', 'ASC');
		}

		return $this->db
			->order_by('puskesmas_staff.nama', 'ASC')
			->order_by('puskesmas_staff.staff_id', 'ASC')
			->get()
			->result();
	}

	public function get_by_id($staff_id)
	{
		$staff_id = (int) $staff_id;
		if ($staff_id < 1 || !$this->table_ready()) {
			return null;
		}

		return $this->db
			->where('staff_id', $staff_id)
			->get('puskesmas_staff')
			->row();
	}

	public function insert($data)
	{
		if (!$this->table_ready()) {
			return false;
		}

		$row = $this->filter_staff_payload($data);
		if (empty($row['kode_pkm']) || empty($row['nama'])) {
			return false;
		}
		if ($this->db->field_exists('created_at', 'puskesmas_staff')) {
			$row['created_at'] = date('Y-m-d H:i:s');
		}
		if ($this->db->field_exists('updated_at', 'puskesmas_staff')) {
			$row['updated_at'] = date('Y-m-d H:i:s');
		}
		$admin_id = $this->current_admin_id();
		if ($admin_id !== null && $this->db->field_exists('created_by_user_id', 'puskesmas_staff')) {
			$row['created_by_user_id'] = $admin_id;
		}
		if ($admin_id !== null && $this->db->field_exists('updated_by_user_id', 'puskesmas_staff')) {
			$row['updated_by_user_id'] = $admin_id;
		}

		return $this->db->insert('puskesmas_staff', $row);
	}

	public function update($staff_id, $data)
	{
		$staff_id = (int) $staff_id;
		if ($staff_id < 1 || !$this->table_ready()) {
			return false;
		}

		$row = $this->filter_staff_payload($data);
		unset($row['staff_id'], $row['user_id'], $row['created_at'], $row['created_by_user_id']);
		if ($this->db->field_exists('updated_at', 'puskesmas_staff')) {
			$row['updated_at'] = date('Y-m-d H:i:s');
		}
		$admin_id = $this->current_admin_id();
		if ($admin_id !== null && $this->db->field_exists('updated_by_user_id', 'puskesmas_staff')) {
			$row['updated_by_user_id'] = $admin_id;
		}
		if (empty($row)) {
			return false;
		}

		return $this->db
			->where('staff_id', $staff_id)
			->update('puskesmas_staff', $row);
	}

	public function set_status($staff_id, $status)
	{
		$staff_id = (int) $staff_id;
		if ($staff_id < 1 || !$this->table_ready()) {
			return false;
		}

		$status = in_array($status, array('aktif', 'nonaktif'), true) ? $status : 'nonaktif';
		$row = array('status' => $status);
		if ($this->db->field_exists('updated_at', 'puskesmas_staff')) {
			$row['updated_at'] = date('Y-m-d H:i:s');
		}
		$admin_id = $this->current_admin_id();
		if ($admin_id !== null && $this->db->field_exists('updated_by_user_id', 'puskesmas_staff')) {
			$row['updated_by_user_id'] = $admin_id;
		}

		return $this->db
			->where('staff_id', $staff_id)
			->update('puskesmas_staff', $row);
	}

	public function get_active_puskesmas_options()
	{
		if (!$this->db->table_exists('m_puskesmas')) {
			return array();
		}

		$this->db->select('kode_pkm, nama_puskesmas');
		if ($this->db->field_exists('status', 'm_puskesmas')) {
			$this->db->where('status', 'aktif');
		}
		$this->db->where('kode_pkm !=', 'DEFAULT');
		$this->db->order_by('nama_puskesmas', 'ASC');

		return $this->db->get('m_puskesmas')->result();
	}

	public function puskesmas_is_active($kode_pkm)
	{
		$kode_pkm = trim((string) $kode_pkm);
		if ($kode_pkm === '' || strtoupper($kode_pkm) === 'DEFAULT' || !$this->db->table_exists('m_puskesmas')) {
			return false;
		}

		$this->db->where('kode_pkm', $kode_pkm);
		if ($this->db->field_exists('status', 'm_puskesmas')) {
			$this->db->where('status', 'aktif');
		}

		return $this->db->count_all_results('m_puskesmas') > 0;
	}

	public function personal_account_creation_ready()
	{
		if (!$this->table_ready() || !$this->db->table_exists('users') || !$this->db->table_exists('m_puskesmas')) {
			return false;
		}

		foreach (array('userId', 'nama', 'email', 'username', 'password', 'role', 'status', 'remark') as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				return false;
			}
		}

		return true;
	}

	public function username_exists($username)
	{
		return $this->user_value_exists_case_insensitive('username', $username);
	}

	public function email_exists($email)
	{
		return $this->user_value_exists_case_insensitive('email', $email);
	}

	public function get_personal_account_creation_eligibility($staff)
	{
		if (!$staff || (int) ($staff->staff_id ?? 0) < 1) {
			return array('eligible' => false, 'message' => 'Staf tidak ditemukan.');
		}
		if ((int) ($staff->user_id ?? 0) > 0) {
			return array('eligible' => false, 'message' => 'Staf sudah memiliki akun personal.');
		}
		if (($staff->status ?? '') !== 'aktif') {
			return array('eligible' => false, 'message' => 'Aktifkan staf terlebih dahulu.');
		}
		$staff_name = trim((string) ($staff->nama ?? ''));
		if ($staff_name === '' || strlen($staff_name) > 100) {
			return array('eligible' => false, 'message' => 'Nama staf tidak valid.');
		}

		$kode_pkm = trim((string) ($staff->kode_pkm ?? ''));
		if (!$this->puskesmas_is_active($kode_pkm)) {
			return array('eligible' => false, 'message' => 'Puskesmas staf tidak valid atau tidak aktif.');
		}

		$command_center_user_id = $this->get_command_center_user_id($kode_pkm);
		if ($command_center_user_id < 1) {
			return array('eligible' => false, 'message' => 'Akun Puskesmas belum tersedia.');
		}
		if ($this->command_center_linked_to_staff($command_center_user_id)) {
			return array('eligible' => false, 'message' => 'Akun Puskesmas terhubung ke staf; periksa data akun.');
		}

		return array('eligible' => true, 'message' => '');
	}

	public function create_and_link_personal_account($staff_id, $account)
	{
		$staff_id = (int) $staff_id;
		$username = trim((string) ($account['username'] ?? ''));
		$email = trim((string) ($account['email'] ?? ''));
		$password = (string) ($account['plain_password'] ?? '');
		unset($account['plain_password']);
		if ($staff_id < 1
			|| $username === ''
			|| strlen($username) < 3
			|| strlen($username) > 100
			|| !preg_match('/^[A-Za-z0-9._-]+$/', $username)
			|| $email === ''
			|| strlen($email) > 100
			|| !filter_var($email, FILTER_VALIDATE_EMAIL)
			|| strlen($password) < 8
			|| !$this->personal_account_creation_ready()) {
			return array('status' => 'error', 'message' => 'Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
		}

		$lock_name = 'doclinc_staff_personal_account_create';
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		try {
			$lock_query = $this->db->query('SELECT GET_LOCK(?, 5) AS acquired', array($lock_name));
		} catch (Throwable $e) {
			$lock_query = false;
		}
		$lock_row = $lock_query ? $lock_query->row() : null;
		$lock_acquired = $lock_row
			&& isset($lock_row->acquired)
			&& ($lock_row->acquired === 1 || $lock_row->acquired === '1');
		if (!$lock_acquired) {
			$this->db->db_debug = $db_debug;
			return array('status' => 'error', 'message' => 'Proses pembuatan akun sedang digunakan. Silakan coba kembali.');
		}

		$result = array('status' => 'error', 'message' => 'Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
		$transaction_started = false;
		$abort = function ($message) use (&$result) {
			$result = array('status' => 'error', 'message' => $message);
			throw new RuntimeException('personal_account_creation_aborted');
		};

		try {
			if (!$this->db->trans_begin()) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}
			$transaction_started = true;

			$staff_query = $this->db->query(
				'SELECT * FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE staff_id = ? FOR UPDATE',
				array($staff_id)
			);
			if (!$staff_query) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}

			$staff = $staff_query->row();
			if (!$staff) {
				$abort('Staf tidak ditemukan.');
			}
			if ((int) ($staff->user_id ?? 0) > 0) {
				$abort('Staf sudah memiliki akun personal.');
			}
			if (($staff->status ?? '') !== 'aktif') {
				$abort('Aktifkan staf terlebih dahulu.');
			}
			$staff_name = trim((string) ($staff->nama ?? ''));
			if ($staff_name === '' || strlen($staff_name) > 100) {
				$abort('Nama staf tidak valid.');
			}
			$kode_pkm = trim((string) ($staff->kode_pkm ?? ''));
			if (!$this->puskesmas_is_active($kode_pkm)) {
				$abort('Puskesmas staf tidak valid atau tidak aktif.');
			}

			$command_center_user_id = $this->get_command_center_user_id($kode_pkm);
			if ($command_center_user_id < 1) {
				$abort('Akun Puskesmas belum tersedia.');
			}
			$command_center_query = $this->db->query(
				'SELECT userId FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? AND role = ? AND TRIM(remark) = ? AND status = ? FOR UPDATE',
				array($command_center_user_id, 'dokter', $kode_pkm, 'aktif')
			);
			if (!$command_center_query || !$command_center_query->row()) {
				$abort('Akun Puskesmas belum tersedia.');
			}
			if ($this->command_center_linked_to_staff($command_center_user_id, true)) {
				$abort('Akun Puskesmas terhubung ke staf; periksa data akun.');
			}

			$duplicate_query = $this->db->query(
				'SELECT username, email FROM ' . $this->db->dbprefix('users') . ' WHERE LOWER(TRIM(username)) = ? OR LOWER(TRIM(email)) = ? FOR UPDATE',
				array(strtolower($username), strtolower($email))
			);
			if (!$duplicate_query) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}
			foreach ($duplicate_query->result() as $existing_user) {
				if (strtolower(trim((string) $existing_user->username)) === strtolower($username)) {
					$abort('Nama pengguna sudah digunakan.');
				}
				if (strtolower(trim((string) $existing_user->email)) === strtolower($email)) {
					$abort('Email sudah digunakan.');
				}
			}

			if (!function_exists('doclinc_password_hash')) {
				$this->load->helper('password_compat');
			}
			$password_hash = function_exists('doclinc_password_hash') ? doclinc_password_hash($password) : false;
			$password = null;
			if (!$password_hash) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}

			$now = date('Y-m-d H:i:s');
			$user_row = array(
				'nama' => $staff_name,
				'email' => $email,
				'username' => $username,
				'password' => $password_hash,
				'role' => 'dokter',
				'status' => 'aktif',
				'remark' => $kode_pkm,
			);
			if ($this->db->field_exists('created_at', 'users')) {
				$user_row['created_at'] = $now;
			}
			if ($this->db->field_exists('updated_at', 'users')) {
				$user_row['updated_at'] = $now;
			}
			if ($this->db->field_exists('updated_by', 'users')) {
				$user_row['updated_by'] = trim((string) ($account['updated_by'] ?? ''));
			}
			if (!$this->db->insert('users', $user_row)) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}

			$user_id = (int) $this->db->insert_id();
			if ($user_id < 1) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}

			$staff_update = array('user_id' => $user_id);
			if ($this->db->field_exists('updated_at', 'puskesmas_staff')) {
				$staff_update['updated_at'] = $now;
			}
			$admin_id = $this->current_admin_id();
			if ($admin_id !== null && $this->db->field_exists('updated_by_user_id', 'puskesmas_staff')) {
				$staff_update['updated_by_user_id'] = $admin_id;
			}
			$link_succeeded = $this->db
				->where('staff_id', $staff_id)
				->group_start()
					->where('user_id IS NULL', null, false)
					->or_where('user_id', 0)
				->group_end()
				->update('puskesmas_staff', $staff_update);
			if (!$link_succeeded || $this->db->affected_rows() !== 1 || $this->db->trans_status() === false) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}

			if (!$this->db->trans_commit()) {
				$this->db->trans_rollback();
				$transaction_started = false;
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}
			$transaction_started = false;
			$result = array(
				'status' => 'success',
				'message' => 'Akun personal dibuat dan dihubungkan.',
			);
		} catch (RuntimeException $e) {
			if ($e->getMessage() !== 'personal_account_creation_aborted') {
				$result = array('status' => 'error', 'message' => 'Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}
		} catch (Throwable $e) {
			$result = array('status' => 'error', 'message' => 'Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
		} finally {
			if ($transaction_started) {
				$this->db->trans_rollback();
			}
			try {
				$this->db->query('SELECT RELEASE_LOCK(?) AS released', array($lock_name));
			} catch (Throwable $e) {
				// The account operation is not retried when lock cleanup reports a failure.
			}
			$this->db->db_debug = $db_debug;
		}

		return $result;
	}

	public function get_command_center_user_id($kode_pkm)
	{
		$kode_pkm = trim((string) $kode_pkm);
		if ($kode_pkm === '' || !$this->db->table_exists('users') || !$this->db->field_exists('remark', 'users')) {
			return 0;
		}

		$this->db
			->select('userId')
			->from('users')
			->where('role', 'dokter')
			->where('TRIM(remark) =', $kode_pkm);
		if ($this->db->field_exists('status', 'users')) {
			$this->db->where('status', 'aktif');
		}

		$user = $this->db
			->order_by('userId', 'ASC')
			->limit(1)
			->get()
			->row();

		return $user ? (int) $user->userId : 0;
	}

	public function get_eligible_account_candidates($kode_pkm, $staff_id = null)
	{
		$kode_pkm = trim((string) $kode_pkm);
		if ($kode_pkm === '' || !$this->table_ready() || !$this->db->table_exists('users') || !$this->db->field_exists('remark', 'users')) {
			return array();
		}

		$staff_id = (int) $staff_id;
		$command_center_user_id = $this->get_command_center_user_id($kode_pkm);
		$linked_user_ids = $this->get_linked_active_user_ids($staff_id);
		$has_user_status = $this->db->field_exists('status', 'users');

		$this->db
			->select('userId, nama, username, email, remark')
			->from('users')
			->where('role', 'dokter')
			->where('TRIM(remark) =', $kode_pkm);
		if ($has_user_status) {
			$this->db->select('status');
			$this->db->where('status', 'aktif');
		} else {
			$this->db->select('NULL AS status', false);
		}
		if ($command_center_user_id > 0) {
			$this->db->where('userId !=', $command_center_user_id);
		}
		if (!empty($linked_user_ids)) {
			$this->db->where_not_in('userId', $linked_user_ids);
		}

		return $this->db
			->order_by('nama', 'ASC')
			->order_by('username', 'ASC')
			->get()
			->result();
	}

	public function bind_staff_account($staff_id, $user_id)
	{
		$staff_id = (int) $staff_id;
		$user_id = (int) $user_id;
		if ($staff_id < 1 || $user_id < 1) {
			return array('status' => 'error', 'message' => 'Pilih staf dan akun yang valid.');
		}
		if (!$this->table_ready()) {
			return array('status' => 'error', 'message' => 'Data staf belum tersedia.');
		}

		$staff = $this->get_by_id($staff_id);
		$user = $this->get_dokter_account_by_id($user_id);
		if (!$staff || !$user) {
			return array('status' => 'error', 'message' => 'Staf atau akun personal tidak ditemukan.');
		}

		$kode_pkm = trim((string) $staff->kode_pkm);
		$user_remark = trim((string) ($user->remark ?? ''));
		if ($kode_pkm === '' || $user_remark !== $kode_pkm) {
			return array('status' => 'error', 'message' => 'Akun tidak berasal dari Puskesmas ini.');
		}
		if (isset($user->status) && $user->status !== 'aktif') {
			return array('status' => 'error', 'message' => 'Pilih akun yang aktif.');
		}
		if ($this->get_command_center_user_id($kode_pkm) === $user_id) {
			return array('status' => 'error', 'message' => 'Akun Puskesmas tidak dapat dihubungkan sebagai akun personal staf.');
		}
		if ($this->account_linked_to_other_active_staff($user_id, $staff_id)) {
			return array('status' => 'error', 'message' => 'Akun sudah terhubung ke staf lain.');
		}

		$updated = $this->db
			->where('staff_id', $staff_id)
			->update('puskesmas_staff', array('user_id' => $user_id));

		return $updated
			? array('status' => 'success', 'message' => 'Akun personal dihubungkan.')
			: array('status' => 'error', 'message' => 'Akun login belum dapat dihubungkan.');
	}

	public function unbind_staff_account($staff_id)
	{
		$staff_id = (int) $staff_id;
		if ($staff_id < 1) {
			return array('status' => 'error', 'message' => 'Pilih staf yang valid.');
		}
		if (!$this->table_ready()) {
			return array('status' => 'error', 'message' => 'Data staf belum tersedia.');
		}
		if (!$this->get_by_id($staff_id)) {
			return array('status' => 'error', 'message' => 'Staf tidak ditemukan.');
		}

		$updated = $this->db
			->where('staff_id', $staff_id)
			->update('puskesmas_staff', array('user_id' => null));

		return $updated
			? array('status' => 'success', 'message' => 'Akun personal dilepas. Akun tidak dihapus.')
			: array('status' => 'error', 'message' => 'Akun login belum dapat dilepas.');
	}

	public function get_dokter_account_by_id($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->db->table_exists('users')) {
			return null;
		}

		$this->db
			->where('userId', $user_id)
			->where('role', 'dokter');

		return $this->db->get('users')->row();
	}

	public function account_linked_to_other_active_staff($user_id, $staff_id)
	{
		$user_id = (int) $user_id;
		$staff_id = (int) $staff_id;
		if ($user_id < 1 || !$this->table_ready()) {
			return false;
		}

		$this->db
			->where('user_id', $user_id)
			->where('staff_id !=', $staff_id)
			->where('status', 'aktif');

		return $this->db->count_all_results('puskesmas_staff') > 0;
	}

	private function command_center_linked_to_staff($user_id, $lock_rows = false)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->table_ready()) {
			return false;
		}

		$sql = 'SELECT staff_id FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE user_id = ?';
		if ($lock_rows) {
			$sql .= ' FOR UPDATE';
		}
		$query = $this->db->query($sql, array($user_id));

		return $query && $query->num_rows() > 0;
	}

	private function get_linked_active_user_ids($exclude_staff_id = null)
	{
		if (!$this->table_ready()) {
			return array();
		}

		$exclude_staff_id = (int) $exclude_staff_id;
		$this->db
			->select('user_id')
			->from('puskesmas_staff')
			->where('user_id IS NOT NULL', null, false)
			->where('user_id !=', 0)
			->where('status', 'aktif');
		if ($exclude_staff_id > 0) {
			$this->db->where('staff_id !=', $exclude_staff_id);
		}

		$rows = $this->db->get()->result();
		$user_ids = array();
		foreach ($rows as $row) {
			$user_id = (int) ($row->user_id ?? 0);
			if ($user_id > 0) {
				$user_ids[] = $user_id;
			}
		}

		return array_values(array_unique($user_ids));
	}

	private function user_value_exists_case_insensitive($field, $value)
	{
		$value = strtolower(trim((string) $value));
		if ($value === '' || !in_array($field, array('username', 'email'), true) || !$this->db->table_exists('users')) {
			return false;
		}

		return $this->db
			->where('LOWER(TRIM(' . $field . ')) =', $value)
			->count_all_results('users') > 0;
	}

	private function filter_staff_payload($data)
	{
		$row = array();
		foreach (array('kode_pkm', 'nama', 'no_hp', 'profesi', 'nomor_sip', 'status') as $field) {
			if ($this->db->field_exists($field, 'puskesmas_staff') && array_key_exists($field, $data)) {
				$row[$field] = trim((string) $data[$field]);
			}
		}
		if (isset($row['status']) && !in_array($row['status'], array('aktif', 'nonaktif'), true)) {
			$row['status'] = 'aktif';
		}

		return $row;
	}

	private function staff_fields()
	{
		return array('staff_id', 'kode_pkm', 'nama', 'no_hp', 'profesi', 'nomor_sip', 'user_id', 'status');
	}

	private function current_admin_id()
	{
		$username = $this->session->userdata('username');
		if (!$username || !$this->db->table_exists('users')) {
			return null;
		}

		$user = $this->db
			->select('userId')
			->where('username', $username)
			->where('role', 'admin')
			->get('users')
			->row();

		return $user ? (int) $user->userId : null;
	}
}
