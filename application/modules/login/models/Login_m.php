<?php 
    class Login_m extends MX_Controller{
        function __construct(){
            parent::__construct();
           $this->db = $this->load->database('default', TRUE);
        }
        public function auth($username)
        {
            return $this->db
                ->where('username', $username)
                ->get('users');
		}
		public function update_password($user_id, $password_hash)
		{
			return $this->db
				->where('userId', $user_id)
				->update('users', array('password' => $password_hash, 'updated_at' => date('Y-m-d H:i:s')));
		}
		public function get_password_change_identity($user_id)
		{
			return $this->get_personal_activation_identity($user_id);
		}

		public function get_personal_activation_identity($user_id)
		{
			$user_id = (int) $user_id;
			if ($user_id < 1 || !$this->personal_activation_schema_ready()) {
				return null;
			}
			$user = $this->db
				->select('userId, username, email, no_hp, password, role, status, remark, must_change_password, password_changed_at')
				->where('userId', $user_id)
				->where('role', 'dokter')
				->where('status', 'aktif')
				->get('users')
				->row_array();
			if (!$user) {
				return null;
			}
			$facility = trim((string) $user['remark']);
			$links = $this->db
				->select('staff_id, kode_pkm, status')
				->where('user_id', $user_id)
				->where('status', 'aktif')
				->get('puskesmas_staff')
				->result_array();
			$facility_row = $facility === '' ? null : $this->db
				->select('kode_pkm, status')
				->where('kode_pkm', $facility)
				->limit(1)
				->get('m_puskesmas')
				->row_array();
			$command_center = $facility === '' ? null : $this->db
				->select('userId')
				->where('role', 'dokter')
				->where('status', 'aktif')
				->where('TRIM(remark) = ' . $this->db->escape($facility), null, false)
				->order_by('userId', 'ASC')
				->limit(1)
				->get('users')
				->row_array();
			$link = count($links) === 1 ? $links[0] : array();
			require_once APPPATH . 'libraries/Nakes_personal_account_policy.php';
			$result = (new Nakes_personal_account_policy())->evaluate(array(
				'staff_exists' => count($links) === 1,
				'staff_status' => $link['status'] ?? '',
				'staff_facility' => $link['kode_pkm'] ?? '',
				'facility_status' => $facility_row['status'] ?? '',
				'user_id' => $user_id,
				'user_exists' => true,
				'user_role' => $user['role'],
				'user_status' => $user['status'],
				'user_facility' => $facility,
				'active_link_count' => count($links),
				'is_command_center' => $command_center && (int) $command_center['userId'] === $user_id,
				'must_change_password' => $user['must_change_password'],
				'password_changed_at' => $user['password_changed_at'],
			));
			if (!$result['valid'] || !in_array($result['state'], array(
				Nakes_personal_account_policy::FIRST_LOGIN_PENDING,
				Nakes_personal_account_policy::ADMIN_RESET_PENDING,
			), true)) {
				return null;
			}
			$user['staff_id'] = (int) $link['staff_id'];
			$user['credential_state'] = $result['state'];
			return $user;
		}
		public function complete_required_password_change($user_id, $password_hash, $expected_current_hash)
		{
			if (!is_string($password_hash) || $password_hash === ''
				|| !is_string($expected_current_hash) || $expected_current_hash === ''
				|| !$this->db->field_exists('must_change_password', 'users')
				|| !$this->db->field_exists('password_changed_at', 'users')
				|| !$this->personal_activation_schema_ready()) {
				return false;
			}
			$this->db->trans_begin();
			$staff_query = $this->db->query(
				'SELECT staff_id, kode_pkm, status FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE user_id = ? AND status = ? FOR UPDATE',
				array((int) $user_id, 'aktif')
			);
			$staff_rows = $staff_query ? $staff_query->result() : array();
			$locked = $this->db->query(
				'SELECT userId, password, role, status, remark, must_change_password, password_changed_at FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? FOR UPDATE',
				array((int) $user_id)
			)->row();
			$facility = $locked ? trim((string) $locked->remark) : '';
			$facility_query = $facility === '' ? null : $this->db->query(
				'SELECT kode_pkm, status FROM ' . $this->db->dbprefix('m_puskesmas') . ' WHERE kode_pkm = ? FOR UPDATE',
				array($facility)
			);
			$facility_row = $facility_query ? $facility_query->row() : null;
			$command_center_query = $facility === '' ? null : $this->db->query(
				'SELECT userId FROM ' . $this->db->dbprefix('users') . ' WHERE role = ? AND status = ? AND TRIM(remark) = ? ORDER BY userId ASC LIMIT 1 FOR UPDATE',
				array('dokter', 'aktif', $facility)
			);
			$command_center = $command_center_query ? $command_center_query->row() : null;
			$staff = count($staff_rows) === 1 ? $staff_rows[0] : null;
			require_once APPPATH . 'libraries/Nakes_personal_account_policy.php';
			$policy_result = (new Nakes_personal_account_policy())->evaluate(array(
				'staff_exists' => $staff !== null,
				'staff_status' => $staff ? $staff->status : '',
				'staff_facility' => $staff ? $staff->kode_pkm : '',
				'facility_status' => $facility_row ? $facility_row->status : '',
				'user_id' => (int) $user_id,
				'user_exists' => $locked !== null,
				'user_role' => $locked ? $locked->role : '',
				'user_status' => $locked ? $locked->status : '',
				'user_facility' => $facility,
				'active_link_count' => count($staff_rows),
				'is_command_center' => $command_center && (int) $command_center->userId === (int) $user_id,
				'must_change_password' => $locked ? $locked->must_change_password : 0,
				'password_changed_at' => $locked ? $locked->password_changed_at : null,
			));
			if (!$locked || !$policy_result['valid']
				|| !in_array($policy_result['state'], array(Nakes_personal_account_policy::FIRST_LOGIN_PENDING, Nakes_personal_account_policy::ADMIN_RESET_PENDING), true)
				|| !hash_equals($expected_current_hash, (string) $locked->password)) {
				$this->db->trans_rollback();
				return false;
			}
			$updated = $this->db
				->where('userId', (int) $user_id)
				->update('users', array(
					'password' => $password_hash,
					'must_change_password' => 0,
					'password_changed_at' => date('Y-m-d H:i:s'),
					'updated_at' => date('Y-m-d H:i:s'),
				));
			if (!$updated || $this->db->affected_rows() !== 1 || $this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return false;
			}
			$this->db->trans_commit();
			return $this->db->trans_status() !== false;
		}

		private function personal_activation_schema_ready()
		{
			if (!$this->db->table_exists('users') || !$this->db->table_exists('puskesmas_staff') || !$this->db->table_exists('m_puskesmas')) {
				return false;
			}
			foreach (array('userId', 'username', 'email', 'no_hp', 'password', 'role', 'status', 'remark', 'must_change_password', 'password_changed_at') as $field) {
				if (!$this->db->field_exists($field, 'users')) {
					return false;
				}
			}
			foreach (array('staff_id', 'user_id', 'kode_pkm', 'status') as $field) {
				if (!$this->db->field_exists($field, 'puskesmas_staff')) {
					return false;
				}
			}
			return $this->db->field_exists('kode_pkm', 'm_puskesmas') && $this->db->field_exists('status', 'm_puskesmas');
		}
		public function log_login_event($action, $user_id = NULL, $metadata = array())
		{
			if (!$this->db->table_exists('audit_logs')) {
				return false;
			}

			$db_debug = $this->db->db_debug;
			$this->db->db_debug = FALSE;

			$result = $this->db->insert('audit_logs', array(
				'actor_user_id' => $user_id,
				'action' => $action,
				'entity_type' => 'auth',
				'entity_id' => $user_id,
				'ip_address' => $this->input->ip_address(),
				'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
				'metadata_json' => json_encode($metadata),
				'created_at' => date('Y-m-d H:i:s'),
			));

			$this->db->db_debug = $db_debug;
			return $result;
		}
		public function save_location($id,$username,$location,$lattitude,$longitude)
		{
			if (!$this->db->table_exists('locations')) {
				return false;
			}

			$db_debug = $this->db->db_debug;
			$this->db->db_debug = FALSE;

			$result = $this->db->insert('locations', [
				'id_user' => $id,
				'name' => $username,
				'location' => $location,
				'latitude' => $lattitude,
				'longitude' => $longitude,
				'create_date' => date('Y-m-d H:i:s'),
			]);

			$this->db->db_debug = $db_debug;
			return $result;
		}
    }
