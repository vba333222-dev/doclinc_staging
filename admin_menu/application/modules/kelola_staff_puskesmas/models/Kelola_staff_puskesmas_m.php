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
		$this->db
			->select('puskesmas_staff.staff_id, puskesmas_staff.kode_pkm, puskesmas_staff.nama, puskesmas_staff.no_hp, puskesmas_staff.profesi, puskesmas_staff.nomor_sip, puskesmas_staff.status')
			->from('puskesmas_staff');

		if ($has_puskesmas) {
			$this->db->select('m_puskesmas.nama_puskesmas');
			$this->db->join('m_puskesmas', 'm_puskesmas.kode_pkm = puskesmas_staff.kode_pkm', 'left');
		} else {
			$this->db->select('NULL AS nama_puskesmas', FALSE);
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
