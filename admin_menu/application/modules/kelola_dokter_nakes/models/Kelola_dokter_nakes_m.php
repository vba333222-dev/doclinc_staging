<?php
class Kelola_dokter_nakes_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}
	public function get_data_dokter_nakes()
	{
		if (!$this->db->table_exists('users')) {
			return $this->db->query("SELECT NULL AS userId, NULL AS nama, NULL AS email, NULL AS username, NULL AS status, NULL AS remark, NULL AS no_hp, NULL AS foto, NULL AS nama_puskesmas, NULL AS puskesmas_status WHERE 1=0");
		}

		$this->db->select('users.*, m_puskesmas.nama_puskesmas, m_puskesmas.status AS puskesmas_status');
		$this->db->from('users');
		if ($this->db->table_exists('m_puskesmas') && $this->db->field_exists('remark', 'users')) {
			$this->db->join('m_puskesmas', 'm_puskesmas.kode_pkm = users.remark', 'left');
		}
		$this->db->where('users.role', 'dokter');
		$this->db->order_by('users.nama', 'ASC');

		return $this->db->get();
	}

	public function get_puskesmas_options()
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

	public function normalize_remark($remark)
	{
		$remark = trim((string) $remark);
		if ($remark === '') {
			return 'DEFAULT';
		}

		if (!$this->db->table_exists('m_puskesmas')) {
			return $remark;
		}

		$exists = $this->db
			->where('kode_pkm', $remark)
			->where('kode_pkm !=', 'DEFAULT')
			->count_all_results('m_puskesmas') > 0;

		return $exists ? $remark : 'DEFAULT';
	}

	public function has_active_puskesmas()
	{
		return count($this->get_puskesmas_options()) > 0;
	}

	public function puskesmas_exists($remark)
	{
		$remark = trim((string) $remark);
		if ($remark === '' || !$this->db->table_exists('m_puskesmas')) {
			return false;
		}

		$this->db->where('kode_pkm', $remark);
		$this->db->where('kode_pkm !=', 'DEFAULT');
		if ($this->db->field_exists('status', 'm_puskesmas')) {
			$this->db->where('status', 'aktif');
		}

		return $this->db->count_all_results('m_puskesmas') > 0;
	}

	public function email_exists($email, $exclude_user_id = null)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$this->db->where('email', $email);
		if ($exclude_user_id !== null) {
			$this->db->where('userId !=', $exclude_user_id);
		}

		return $this->db->count_all_results('users') > 0;
	}

	public function username_exists($username, $exclude_user_id = null)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$this->db->where('username', $username);
		if ($exclude_user_id !== null) {
			$this->db->where('userId !=', $exclude_user_id);
		}

		return $this->db->count_all_results('users') > 0;
	}

	public function create_dokter_nakes($data)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$now = date('Y-m-d H:i:s');
		$insert = array(
			'nama' => $data['nama'],
			'email' => $data['email'],
			'username' => $data['username'],
			'password' => $data['password'],
			'role' => 'dokter',
			'status' => in_array($data['status'], array('aktif', 'nonaktif'), TRUE) ? $data['status'] : 'aktif',
		);

		foreach (array('no_hp', 'remark', 'updated_by') as $field) {
			if ($this->db->field_exists($field, 'users')) {
				$insert[$field] = isset($data[$field]) ? $data[$field] : null;
			}
		}
		if ($this->db->field_exists('created_at', 'users')) {
			$insert['created_at'] = $now;
		}
		if ($this->db->field_exists('updated_at', 'users')) {
			$insert['updated_at'] = $now;
		}

		$this->db->trans_begin();
		$this->db->insert('users', $insert);
		$user_id = $this->db->insert_id();
		if (!$user_id) {
			$this->db->trans_rollback();
			return false;
		}

		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		}

		$this->db->trans_commit();
		if (!$this->sync_m_dokter($user_id, $insert)) {
			log_message('error', 'Gagal sinkronisasi m_dokter untuk user dokter ' . $user_id);
		}
		$this->log_audit('admin_create_puskesmas_nakes_user', $user_id);

		return $user_id;
	}
	public function aktifkan_user($id_user, $user, $remark_aktif)
	{
		return $this->update_status($id_user, 'aktif', $user, $remark_aktif);
	}
	public function nonaktifkan_user($id_user, $user, $remark_nonaktif)
	{
		return $this->update_status($id_user, 'nonaktif', $user, $remark_nonaktif);
	}

	public function delete_dokter_nakes($id_user, $user = null, $remark_nonaktif = null)
	{
		return $this->update_status($id_user, 'nonaktif', $user, $remark_nonaktif);
	}

	public function update_dokter_nakes($id_user, $data)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$allowed = array();
		foreach (array('nama', 'email', 'username', 'remark', 'no_hp', 'status', 'password', 'updated_by', 'updated_at') as $field) {
			if ($this->db->field_exists($field, 'users') && array_key_exists($field, $data)) {
				$allowed[$field] = $data[$field];
			}
		}

		if (empty($allowed)) {
			return false;
		}

		$this->db->where('userId', $id_user);
		$this->db->where('role', 'dokter');
		$result = $this->db->update('users', $allowed);
		if ($result) {
			$this->log_audit('admin_update_puskesmas_nakes_user', $id_user);
			if (isset($allowed['password'])) {
				$this->log_audit('admin_reset_puskesmas_nakes_password', $id_user);
			}
			$user = $this->db
				->where('userId', $id_user)
				->where('role', 'dokter')
				->get('users')
				->row_array();
			if ($user) {
				$this->sync_m_dokter($id_user, $user);
			}
		}

		return $result;
	}

	private function update_status($id_user, $status, $user, $remark)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$data = array('status' => $status);
		$updates = array('updated_by' => $user, 'updated_at' => date('Y-m-d H:i:s'));
		if ($remark !== null) {
			$updates['remark'] = $remark;
		}

		foreach ($updates as $field => $value) {
			if ($this->db->field_exists($field, 'users')) {
				$data[$field] = $value;
			}
		}

		$this->db->where('userId', $id_user);
		$this->db->where('role', 'dokter');
		$result = $this->db->update('users', $data);
		if ($result) {
			$this->log_audit($status === 'aktif' ? 'admin_enable_puskesmas_nakes_user' : 'admin_disable_puskesmas_nakes_user', $id_user);
		}

		return $result;
	}

	private function sync_m_dokter($user_id, $user)
	{
		if (!$this->db->table_exists('m_dokter')) {
			return true;
		}

		$row = array();
		$map = array(
			'userId' => $user_id,
			'professional_id' => $user_id,
			'name' => isset($user['nama']) ? $user['nama'] : '',
			'phone' => !empty($user['no_hp']) ? $user['no_hp'] : 'user-' . $user_id,
			'email' => isset($user['email']) ? $user['email'] : '',
			'password' => isset($user['password']) ? $user['password'] : '',
			'specialization' => 'Umum',
			'status' => (isset($user['status']) && $user['status'] === 'aktif') ? 'On Duty' : 'Off Duty',
			'created_at' => date('Y-m-d H:i:s'),
		);

		foreach ($map as $field => $value) {
			if ($this->db->field_exists($field, 'm_dokter')) {
				$row[$field] = $value;
			}
		}

		if (empty($row)) {
			return true;
		}

		$key_field = null;
		if ($this->db->field_exists('userId', 'm_dokter')) {
			$key_field = 'userId';
		} elseif ($this->db->field_exists('professional_id', 'm_dokter')) {
			$key_field = 'professional_id';
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;

		if ($key_field !== null) {
			$existing = $this->db
				->where($key_field, $user_id)
				->get('m_dokter')
				->row();
			if ($existing) {
				unset($row['userId'], $row['professional_id'], $row['created_at']);
				if (empty($row)) {
					$this->db->db_debug = $db_debug;
					return true;
				}

				$result = $this->db
					->where($key_field, $user_id)
					->update('m_dokter', $row);
				$this->db->db_debug = $db_debug;
				return $result;
			}
		}

		$result = $this->db->insert('m_dokter', $row);
		$this->db->db_debug = $db_debug;
		return $result;
	}

	private function log_audit($action, $target_user_id)
	{
		if (!$this->db->table_exists('audit_logs')) {
			return false;
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$result = $this->db->insert('audit_logs', array(
			'actor_user_id' => $this->current_admin_id(),
			'action' => $action,
			'entity_type' => 'users',
			'entity_id' => $target_user_id,
			'ip_address' => $this->input->ip_address(),
			'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
			'metadata_json' => json_encode(array('target_user_id' => $target_user_id, 'role' => 'dokter')),
			'created_at' => date('Y-m-d H:i:s'),
		));
		$this->db->db_debug = $db_debug;

		return $result;
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

		return $user ? $user->userId : null;
	}
}
