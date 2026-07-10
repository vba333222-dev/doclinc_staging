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
			$this->db->join('m_puskesmas', 'm_puskesmas.kode_pkm = puskesmas_staff.kode_pkm', 'left');
		} else {
			$this->db->select('NULL AS nama_puskesmas', FALSE);
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
		if ($staff_id < 1 || $user_id < 1 || !$this->table_ready()) {
			return array('status' => 'error', 'message' => 'Data staff atau akun login tidak valid.');
		}

		$staff = $this->get_by_id($staff_id);
		$user = $this->get_dokter_account_by_id($user_id);
		if (!$staff || !$user) {
			return array('status' => 'error', 'message' => 'Staff atau akun login tidak ditemukan.');
		}

		$kode_pkm = trim((string) $staff->kode_pkm);
		$user_remark = trim((string) ($user->remark ?? ''));
		if ($kode_pkm === '' || $user_remark !== $kode_pkm) {
			return array('status' => 'error', 'message' => 'Akun login harus berasal dari Puskesmas yang sama.');
		}
		if (isset($user->status) && $user->status !== 'aktif') {
			return array('status' => 'error', 'message' => 'Akun login harus dalam status aktif.');
		}
		if ($this->get_command_center_user_id($kode_pkm) === $user_id) {
			return array('status' => 'error', 'message' => 'Akun koordinator Puskesmas tidak dapat dihubungkan sebagai akun personal staff.');
		}
		if ($this->account_linked_to_other_active_staff($user_id, $staff_id)) {
			return array('status' => 'error', 'message' => 'Akun login sudah terhubung ke staff aktif lain.');
		}

		$updated = $this->db
			->where('staff_id', $staff_id)
			->update('puskesmas_staff', array('user_id' => $user_id));

		return $updated
			? array('status' => 'success', 'message' => 'Akun login personal berhasil dihubungkan.')
			: array('status' => 'error', 'message' => 'Akun login belum dapat dihubungkan.');
	}

	public function unbind_staff_account($staff_id)
	{
		$staff_id = (int) $staff_id;
		if ($staff_id < 1 || !$this->table_ready()) {
			return array('status' => 'error', 'message' => 'Data staff tidak valid.');
		}
		if (!$this->get_by_id($staff_id)) {
			return array('status' => 'error', 'message' => 'Staff tidak ditemukan.');
		}

		$updated = $this->db
			->where('staff_id', $staff_id)
			->update('puskesmas_staff', array('user_id' => null));

		return $updated
			? array('status' => 'success', 'message' => 'Akun login berhasil dilepas dari staff. Akun tidak dihapus.')
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
