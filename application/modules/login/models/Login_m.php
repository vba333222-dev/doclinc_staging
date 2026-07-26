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
			if (!$this->db->field_exists('must_change_password', 'users') || !$this->db->field_exists('password_changed_at', 'users')) {
				return null;
			}
			return $this->db
				->select('userId, username, email, no_hp, password, role, status, must_change_password')
				->where('userId', (int) $user_id)
				->where('role', 'dokter')
				->where('status', 'aktif')
				->where('must_change_password', 1)
				->get('users')
				->row_array();
		}
		public function complete_required_password_change($user_id, $password_hash, $expected_current_hash)
		{
			if (!is_string($password_hash) || $password_hash === ''
				|| !is_string($expected_current_hash) || $expected_current_hash === ''
				|| !$this->db->field_exists('must_change_password', 'users')
				|| !$this->db->field_exists('password_changed_at', 'users')) {
				return false;
			}
			$this->db->trans_begin();
			$locked = $this->db->query(
				'SELECT userId, password, role, status, must_change_password FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? FOR UPDATE',
				array((int) $user_id)
			)->row();
			if (!$locked || $locked->role !== 'dokter' || $locked->status !== 'aktif' || (int) $locked->must_change_password !== 1
				|| !hash_equals($expected_current_hash, (string) $locked->password)) {
				$this->db->trans_rollback();
				return false;
			}
			$updated = $this->db
				->where('userId', (int) $user_id)
				->where('must_change_password', 1)
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
